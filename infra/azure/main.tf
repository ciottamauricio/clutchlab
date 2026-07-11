# Clutchlab on Azure Container Apps — the cloud truth mirroring docker-compose.yml.
# Study topic 12: compose's four jobs split up here. The service graph becomes
# container_app resources, the private network becomes the Container Apps environment
# (name-based DNS, same "never localhost" rule), env_file becomes secrets + env blocks,
# and restart policies become min/max replicas. depends_on has no equivalent — every
# service must retry its connections at boot.
#
# The stateful trio leaves the orchestrator entirely:
#   postgres  -> azurerm_postgresql_flexible_server
#   redis     -> azurerm_redis_cache        (the RPUSH/BLPOP list works unchanged; TLS port)
#   minio     -> azurerm_storage_account    (NOT S3-compatible — needs an AzureBlobDemoStorage
#                                            behind the DemoStorage interface, and the Go
#                                            worker's equivalent, before this swap is real)
# Meilisearch stays a container: no managed offering, so it keeps a mounted Azure Files share.

terraform {
  required_providers {
    azurerm = {
      source  = "hashicorp/azurerm"
      version = "~> 4.0"
    }
  }
}

provider "azurerm" {
  features {}
}

resource "azurerm_resource_group" "clutchlab" {
  name     = "rg-clutchlab-${var.environment}"
  location = var.location
}

# ---------------------------------------------------------------------------
# Images live in a registry; compose's `build: ./api` becomes CI pushing here.
# ---------------------------------------------------------------------------

resource "azurerm_container_registry" "acr" {
  name                = "acrclutchlab${var.environment}"
  resource_group_name = azurerm_resource_group.clutchlab.name
  location            = azurerm_resource_group.clutchlab.location
  sku                 = "Basic"
  admin_enabled       = true # skeleton shortcut; managed identity + AcrPull is the grown-up path
}

# ---------------------------------------------------------------------------
# The environment = compose's `networks: [clutchnet]`. Apps inside resolve each
# other by app name over internal DNS, exactly like Docker's bridge network.
# ---------------------------------------------------------------------------

resource "azurerm_log_analytics_workspace" "logs" {
  name                = "log-clutchlab-${var.environment}"
  resource_group_name = azurerm_resource_group.clutchlab.name
  location            = azurerm_resource_group.clutchlab.location
  retention_in_days   = 30
}

resource "azurerm_container_app_environment" "env" {
  name                       = "cae-clutchlab-${var.environment}"
  resource_group_name        = azurerm_resource_group.clutchlab.name
  location                   = azurerm_resource_group.clutchlab.location
  log_analytics_workspace_id = azurerm_log_analytics_workspace.logs.id
}

# Meilisearch's `meilidata:` named volume becomes an Azure Files share mounted
# into the environment. The only container here that still owns a disk.
resource "azurerm_storage_account" "files" {
  name                     = "stclutchfiles${var.environment}"
  resource_group_name      = azurerm_resource_group.clutchlab.name
  location                 = azurerm_resource_group.clutchlab.location
  account_tier             = "Standard"
  account_replication_type = "LRS"
}

resource "azurerm_storage_share" "meili" {
  name               = "meilidata"
  storage_account_id = azurerm_storage_account.files.id
  quota              = 10
}

resource "azurerm_container_app_environment_storage" "meili" {
  name                         = "meilidata"
  container_app_environment_id = azurerm_container_app_environment.env.id
  account_name                 = azurerm_storage_account.files.name
  share_name                   = azurerm_storage_share.meili.name
  access_key                   = azurerm_storage_account.files.primary_access_key
  access_mode                  = "ReadWrite"
}

# ---------------------------------------------------------------------------
# The stateful trio — managed services, no longer your containers.
# ---------------------------------------------------------------------------

resource "azurerm_postgresql_flexible_server" "db" {
  name                   = "psql-clutchlab-${var.environment}"
  resource_group_name    = azurerm_resource_group.clutchlab.name
  location               = azurerm_resource_group.clutchlab.location
  version                = "16"
  administrator_login    = var.db_username
  administrator_password = var.db_password
  sku_name               = "B_Standard_B1ms" # burstable — learning-project sized
  storage_mb             = 32768
  zone                   = "1"
}

resource "azurerm_postgresql_flexible_server_database" "clutchlab" {
  name      = var.db_database
  server_id = azurerm_postgresql_flexible_server.db.id
}

# The plain-list queue survives unchanged: RPUSH/BLPOP are just Redis commands.
# What changes is the wire: Azure Cache speaks TLS on 6380, so both the Laravel
# and Go clients need TLS enabled — that, not the queue semantics, is the migration.
resource "azurerm_redis_cache" "queue" {
  name                 = "redis-clutchlab-${var.environment}"
  resource_group_name  = azurerm_resource_group.clutchlab.name
  location             = azurerm_resource_group.clutchlab.location
  capacity             = 0
  family               = "C"
  sku_name             = "Basic"
  non_ssl_port_enabled = false
  minimum_tls_version  = "1.2"
}

# Demo storage. Blob is not S3 — until DemoStorage grows an Azure implementation
# (api + worker), the alternative is keeping MinIO as one more container app.
resource "azurerm_storage_account" "demos" {
  name                     = "stclutchdemos${var.environment}"
  resource_group_name      = azurerm_resource_group.clutchlab.name
  location                 = azurerm_resource_group.clutchlab.location
  account_tier             = "Standard"
  account_replication_type = "LRS"
}

resource "azurerm_storage_container" "demos" {
  name                  = "demos"
  storage_account_id    = azurerm_storage_account.demos.id
  container_access_type = "private"
}

# ---------------------------------------------------------------------------
# The apps. Shared config that compose injected via `env_file: .env` is inlined
# per app here; secret values ride as ACA secrets, referenced by name.
# ---------------------------------------------------------------------------

locals {
  registry = azurerm_container_registry.acr.login_server

  # compose: env_file .env — the cross-service block every backend gets.
  shared_env = [
    { name = "DB_HOST", value = azurerm_postgresql_flexible_server.db.fqdn },
    { name = "DB_PORT", value = "5432" },
    { name = "DB_DATABASE", value = var.db_database },
    { name = "DB_USERNAME", value = var.db_username },
    { name = "REDIS_HOST", value = azurerm_redis_cache.queue.hostname },
    { name = "REDIS_PORT", value = "6380" }, # TLS port — see redis note above
    { name = "REDIS_PREFIX", value = "" },   # both languages read the raw key demo_parse_jobs
    { name = "PARSE_QUEUE", value = "demo_parse_jobs" },
    { name = "MEILI_HOST", value = "http://meilisearch" }, # internal DNS, like compose
  ]
}

# nginx — the gateway keeps its job: single external ingress, proxying to api and
# realtime by app name. The frontend's built dist/ is baked into this image
# (multi-stage build), so the Vite dev container has no cloud counterpart.
resource "azurerm_container_app" "nginx" {
  name                         = "nginx"
  container_app_environment_id = azurerm_container_app_environment.env.id
  resource_group_name          = azurerm_resource_group.clutchlab.name
  revision_mode                = "Single"

  ingress {
    external_enabled = true # the only app the internet reaches — same as compose's lone `ports:`
    target_port      = 80
    traffic_weight {
      latest_revision = true
      percentage      = 100
    }
  }

  template {
    min_replicas = 1
    max_replicas = 2
    container {
      name   = "nginx"
      image  = "${local.registry}/clutchlab-nginx:${var.image_tag}"
      cpu    = 0.25
      memory = "0.5Gi"
    }
  }
}

resource "azurerm_container_app" "api" {
  name                         = "api"
  container_app_environment_id = azurerm_container_app_environment.env.id
  resource_group_name          = azurerm_resource_group.clutchlab.name
  revision_mode                = "Single"

  # Internal-only ingress: reachable as http://api inside the environment (nginx
  # proxies to it), invisible from the internet.
  ingress {
    external_enabled = false
    target_port      = 8000
    traffic_weight {
      latest_revision = true
      percentage      = 100
    }
  }

  secret {
    name  = "db-password"
    value = var.db_password
  }
  secret {
    name  = "redis-password"
    value = azurerm_redis_cache.queue.primary_access_key
  }
  secret {
    name  = "app-key"
    value = var.laravel_app_key
  }
  secret {
    name  = "meili-master-key"
    value = var.meili_master_key
  }

  template {
    min_replicas = 1
    max_replicas = 3
    container {
      name   = "api"
      image  = "${local.registry}/clutchlab-api:${var.image_tag}"
      cpu    = 0.5
      memory = "1Gi"

      dynamic "env" {
        for_each = local.shared_env
        content {
          name  = env.value.name
          value = env.value.value
        }
      }
      env {
        name        = "DB_PASSWORD"
        secret_name = "db-password"
      }
      env {
        name        = "REDIS_PASSWORD"
        secret_name = "redis-password"
      }
      env {
        name        = "APP_KEY"
        secret_name = "app-key"
      }
      env {
        name        = "MEILI_MASTER_KEY"
        secret_name = "meili-master-key"
      }
      env {
        name  = "APP_ENV"
        value = "production"
      }
      env {
        name  = "APP_DEBUG"
        value = "false"
      }
    }
  }
}

# worker — compose's headless BLPOP consumer. No ingress at all, and min_replicas = 1
# because scale-to-zero would leave nobody blocked on the queue. When the queue grows
# a depth signal (or moves to a broker), a custom scale rule replaces the fixed count.
resource "azurerm_container_app" "worker" {
  name                         = "worker"
  container_app_environment_id = azurerm_container_app_environment.env.id
  resource_group_name          = azurerm_resource_group.clutchlab.name
  revision_mode                = "Single"

  secret {
    name  = "db-password"
    value = var.db_password
  }
  secret {
    name  = "redis-password"
    value = azurerm_redis_cache.queue.primary_access_key
  }
  secret {
    name  = "meili-master-key"
    value = var.meili_master_key
  }

  template {
    min_replicas = 1
    max_replicas = 1 # one consumer; parsing parallelism is a later, deliberate step
    container {
      name   = "worker"
      image  = "${local.registry}/clutchlab-worker:${var.image_tag}"
      cpu    = 1.0 # the CPU-bound one — the whole reason this service exists
      memory = "2Gi"

      dynamic "env" {
        for_each = local.shared_env
        content {
          name  = env.value.name
          value = env.value.value
        }
      }
      env {
        name        = "DB_PASSWORD"
        secret_name = "db-password"
      }
      env {
        name        = "REDIS_PASSWORD"
        secret_name = "redis-password"
      }
      env {
        name        = "MEILI_MASTER_KEY"
        secret_name = "meili-master-key"
      }
    }
  }
}

resource "azurerm_container_app" "realtime" {
  name                         = "realtime"
  container_app_environment_id = azurerm_container_app_environment.env.id
  resource_group_name          = azurerm_resource_group.clutchlab.name
  revision_mode                = "Single"

  ingress {
    external_enabled = false # nginx proxies /realtime/* here; ACA ingress speaks websockets
    target_port      = 8090
    traffic_weight {
      latest_revision = true
      percentage      = 100
    }
  }

  secret {
    name  = "db-password"
    value = var.db_password
  }

  template {
    min_replicas = 1
    max_replicas = 1 # the in-memory hub is per-instance; scaling out needs shared presence first
    container {
      name   = "realtime"
      image  = "${local.registry}/clutchlab-realtime:${var.image_tag}"
      cpu    = 0.25
      memory = "0.5Gi"

      dynamic "env" {
        for_each = local.shared_env
        content {
          name  = env.value.name
          value = env.value.value
        }
      }
      env {
        name        = "DB_PASSWORD"
        secret_name = "db-password"
      }
    }
  }
}

# meilisearch — the one container that keeps a disk (compose's `meilidata:` volume).
resource "azurerm_container_app" "meilisearch" {
  name                         = "meilisearch"
  container_app_environment_id = azurerm_container_app_environment.env.id
  resource_group_name          = azurerm_resource_group.clutchlab.name
  revision_mode                = "Single"

  ingress {
    external_enabled = false
    target_port      = 7700
    traffic_weight {
      latest_revision = true
      percentage      = 100
    }
  }

  secret {
    name  = "meili-master-key"
    value = var.meili_master_key
  }

  template {
    min_replicas = 1
    max_replicas = 1 # single-node engine; its state lives on the mounted share

    container {
      name   = "meilisearch"
      image  = "getmeili/meilisearch:v1.12"
      cpu    = 0.5
      memory = "1Gi"

      env {
        name        = "MEILI_MASTER_KEY"
        secret_name = "meili-master-key"
      }
      env {
        name  = "MEILI_NO_ANALYTICS"
        value = "true"
      }

      volume_mounts {
        name = "meilidata"
        path = "/meili_data"
      }
    }

    volume {
      name         = "meilidata"
      storage_name = azurerm_container_app_environment_storage.meili.name
      storage_type = "AzureFile"
    }
  }
}
