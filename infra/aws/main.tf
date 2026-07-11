# Clutchlab on AWS ECS (Fargate) — the same compose file, translated. Where the Azure
# skeleton had to *note* a storage rewrite, here MinIO -> S3 is config-only: the api and
# worker already speak the S3 API (that's what MinIO is), so AWS_ENDPOINT and the static
# keys simply disappear — the task role's IAM credentials take over.
#
# What ECS makes explicit that Container Apps hides: the network (VPC, subnets, NAT),
# IAM (who may pull images, who may touch the bucket), and load balancing are all
# resources you declare. Same four compose jobs, more visible seams:
#   service graph   -> task definitions + services
#   clutchnet DNS   -> Cloud Map private namespace ("api.clutchlab.local")
#   env_file .env   -> SSM parameters + env blocks in the task definitions
#   restart:always  -> desired_count + ECS replacing unhealthy tasks

terraform {
  required_providers {
    aws = {
      source  = "hashicorp/aws"
      version = "~> 5.0"
    }
  }
}

provider "aws" {
  region = var.region
}

locals {
  name     = "clutchlab-${var.environment}"
  services = ["nginx", "api", "worker", "realtime"] # images CI builds; meilisearch pulls upstream
}

# ---------------------------------------------------------------------------
# Network — compose's `networks: [clutchnet]`, spelled out. Public subnets hold
# the ALB; everything else lives in private subnets behind a NAT gateway
# (the single most expensive line in this file — ~$32/mo before traffic).
# ---------------------------------------------------------------------------

module "vpc" {
  source  = "terraform-aws-modules/vpc/aws"
  version = "~> 5.0"

  name = local.name
  cidr = "10.0.0.0/16"

  azs             = ["${var.region}a", "${var.region}b"]
  public_subnets  = ["10.0.1.0/24", "10.0.2.0/24"]
  private_subnets = ["10.0.11.0/24", "10.0.12.0/24"]

  enable_nat_gateway = true
  single_nat_gateway = true
}

resource "aws_security_group" "alb" {
  name_prefix = "${local.name}-alb-"
  vpc_id      = module.vpc.vpc_id

  ingress {
    from_port   = 80
    to_port     = 80
    protocol    = "tcp"
    cidr_blocks = ["0.0.0.0/0"] # skeleton: HTTP only; production adds :443 + ACM cert
  }
  egress {
    from_port   = 0
    to_port     = 0
    protocol    = "-1"
    cidr_blocks = ["0.0.0.0/0"]
  }
}

# One SG for all tasks: anything inside may talk to anything inside — the same
# trust model as compose's bridge network, made explicit.
resource "aws_security_group" "tasks" {
  name_prefix = "${local.name}-tasks-"
  vpc_id      = module.vpc.vpc_id

  ingress {
    from_port       = 0
    to_port         = 65535
    protocol        = "tcp"
    security_groups = [aws_security_group.alb.id]
  }
  ingress {
    from_port = 0
    to_port   = 65535
    protocol  = "tcp"
    self      = true
  }
  egress {
    from_port   = 0
    to_port     = 0
    protocol    = "-1"
    cidr_blocks = ["0.0.0.0/0"]
  }
}

# ---------------------------------------------------------------------------
# Registries + cluster + the DNS that replaces Docker's name resolution.
# ---------------------------------------------------------------------------

resource "aws_ecr_repository" "svc" {
  for_each = toset(local.services)
  name     = "${local.name}/${each.key}"
}

resource "aws_ecs_cluster" "main" {
  name = local.name
}

# "services talk by name, never localhost" survives as api.clutchlab.local etc.
resource "aws_service_discovery_private_dns_namespace" "internal" {
  name = "clutchlab.local"
  vpc  = module.vpc.vpc_id
}

resource "aws_service_discovery_service" "svc" {
  for_each = toset(["api", "realtime", "meilisearch"]) # the ones called by name

  name = each.key
  dns_config {
    namespace_id = aws_service_discovery_private_dns_namespace.internal.id
    dns_records {
      ttl  = 10
      type = "A"
    }
  }
}

# ---------------------------------------------------------------------------
# The stateful trio — managed, outside the orchestrator.
# ---------------------------------------------------------------------------

resource "aws_db_subnet_group" "db" {
  name       = local.name
  subnet_ids = module.vpc.private_subnets
}

resource "aws_db_instance" "postgres" {
  identifier             = local.name
  engine                 = "postgres"
  engine_version         = "16"
  instance_class         = "db.t4g.micro"
  allocated_storage      = 20
  db_name                = var.db_database
  username               = var.db_username
  password               = var.db_password
  db_subnet_group_name   = aws_db_subnet_group.db.name
  vpc_security_group_ids = [aws_security_group.tasks.id]
  skip_final_snapshot    = true # learning project; flip for anything that matters
}

resource "aws_elasticache_subnet_group" "redis" {
  name       = local.name
  subnet_ids = module.vpc.private_subnets
}

# Inside the VPC ElastiCache speaks plain Redis on 6379 — unlike Azure's TLS-only
# cache, neither client changes at all. RPUSH/BLPOP carry on as if nothing moved.
resource "aws_elasticache_cluster" "redis" {
  cluster_id           = local.name
  engine               = "redis"
  node_type            = "cache.t4g.micro"
  num_cache_nodes      = 1
  subnet_group_name    = aws_elasticache_subnet_group.redis.name
  security_group_ids   = [aws_security_group.tasks.id]
  parameter_group_name = "default.redis7"
}

# The config-only swap: this bucket replaces the MinIO container. FILESYSTEM_DISK
# stays "s3"; AWS_ENDPOINT and the root user/password vanish; the task role below
# carries the credentials.
resource "aws_s3_bucket" "demos" {
  bucket = "${local.name}-demos"
}

resource "aws_s3_bucket_public_access_block" "demos" {
  bucket                  = aws_s3_bucket.demos.id
  block_public_acls       = true
  block_public_policy     = true
  ignore_public_acls      = true
  restrict_public_buckets = true
}

# Meilisearch's named volume becomes EFS — the one container that keeps a disk.
resource "aws_efs_file_system" "meili" {
  creation_token = "${local.name}-meili"
}

resource "aws_efs_mount_target" "meili" {
  count           = length(module.vpc.private_subnets)
  file_system_id  = aws_efs_file_system.meili.id
  subnet_id       = module.vpc.private_subnets[count.index]
  security_groups = [aws_security_group.tasks.id]
}

resource "aws_security_group_rule" "efs" {
  type              = "ingress"
  from_port         = 2049
  to_port           = 2049
  protocol          = "tcp"
  self              = true
  security_group_id = aws_security_group.tasks.id
}

# ---------------------------------------------------------------------------
# Secrets — compose's env_file lines that were secret become SSM parameters,
# referenced (not inlined) by the task definitions.
# ---------------------------------------------------------------------------

resource "aws_ssm_parameter" "secret" {
  for_each = {
    db-password      = var.db_password
    app-key          = var.laravel_app_key
    meili-master-key = var.meili_master_key
  }
  name  = "/${local.name}/${each.key}"
  type  = "SecureString"
  value = each.value
}

# ---------------------------------------------------------------------------
# IAM — the seam ACA hid. Execution role: ECS itself pulling images and reading
# secrets. Task role: what the *code* may do — here, exactly one bucket.
# ---------------------------------------------------------------------------

data "aws_iam_policy_document" "assume_ecs" {
  statement {
    actions = ["sts:AssumeRole"]
    principals {
      type        = "Service"
      identifiers = ["ecs-tasks.amazonaws.com"]
    }
  }
}

resource "aws_iam_role" "execution" {
  name               = "${local.name}-execution"
  assume_role_policy = data.aws_iam_policy_document.assume_ecs.json
}

resource "aws_iam_role_policy_attachment" "execution" {
  role       = aws_iam_role.execution.name
  policy_arn = "arn:aws:iam::aws:policy/service-role/AmazonECSTaskExecutionRolePolicy"
}

resource "aws_iam_role_policy" "execution_ssm" {
  name = "read-secrets"
  role = aws_iam_role.execution.id
  policy = jsonencode({
    Version = "2012-10-17"
    Statement = [{
      Effect   = "Allow"
      Action   = ["ssm:GetParameters"]
      Resource = [for p in aws_ssm_parameter.secret : p.arn]
    }]
  })
}

resource "aws_iam_role" "task" {
  name               = "${local.name}-task"
  assume_role_policy = data.aws_iam_policy_document.assume_ecs.json
}

resource "aws_iam_role_policy" "task_s3" {
  name = "demos-bucket"
  role = aws_iam_role.task.id
  policy = jsonencode({
    Version = "2012-10-17"
    Statement = [{
      Effect   = "Allow"
      Action   = ["s3:GetObject", "s3:PutObject", "s3:DeleteObject", "s3:ListBucket"]
      Resource = [aws_s3_bucket.demos.arn, "${aws_s3_bucket.demos.arn}/*"]
    }]
  })
}

# ---------------------------------------------------------------------------
# ALB — compose's `ports: 8080:80`, industrialized. nginx keeps its gateway job
# (same architecture as local); the alternative is ALB path rules straight to
# api/realtime + CloudFront for the static frontend, dropping nginx entirely.
# ---------------------------------------------------------------------------

resource "aws_lb" "main" {
  name               = local.name
  load_balancer_type = "application"
  subnets            = module.vpc.public_subnets
  security_groups    = [aws_security_group.alb.id]
}

resource "aws_lb_target_group" "nginx" {
  name        = "${local.name}-nginx"
  port        = 80
  protocol    = "HTTP"
  vpc_id      = module.vpc.vpc_id
  target_type = "ip"

  health_check {
    path    = "/"
    matcher = "200-399"
  }
}

resource "aws_lb_listener" "http" {
  load_balancer_arn = aws_lb.main.arn
  port              = 80
  protocol          = "HTTP"

  default_action {
    type             = "forward"
    target_group_arn = aws_lb_target_group.nginx.arn
  }
}

# ---------------------------------------------------------------------------
# Task definitions + services. Env that compose injected once via env_file is
# assembled here (locals) and repeated per task — the price of explicitness.
# ---------------------------------------------------------------------------

resource "aws_cloudwatch_log_group" "svc" {
  for_each          = toset(concat(local.services, ["meilisearch"]))
  name              = "/ecs/${local.name}/${each.key}"
  retention_in_days = 30
}

locals {
  registry = { for s in local.services : s => aws_ecr_repository.svc[s].repository_url }

  shared_env = [
    { name = "DB_HOST", value = aws_db_instance.postgres.address },
    { name = "DB_PORT", value = "5432" },
    { name = "DB_DATABASE", value = var.db_database },
    { name = "DB_USERNAME", value = var.db_username },
    { name = "REDIS_HOST", value = aws_elasticache_cluster.redis.cache_nodes[0].address },
    { name = "REDIS_PORT", value = "6379" },
    { name = "REDIS_PREFIX", value = "" }, # both languages read the raw key demo_parse_jobs
    { name = "PARSE_QUEUE", value = "demo_parse_jobs" },
    { name = "MEILI_HOST", value = "http://meilisearch.clutchlab.local:7700" },
    # The MinIO block from .env, after the config-only swap: real S3, no endpoint,
    # no static keys (the task role signs requests), path-style off.
    { name = "FILESYSTEM_DISK", value = "s3" },
    { name = "AWS_BUCKET", value = aws_s3_bucket.demos.bucket },
    { name = "AWS_DEFAULT_REGION", value = var.region },
    { name = "AWS_USE_PATH_STYLE_ENDPOINT", value = "false" },
  ]

  shared_secrets = [
    { name = "DB_PASSWORD", valueFrom = aws_ssm_parameter.secret["db-password"].arn },
    { name = "MEILI_MASTER_KEY", valueFrom = aws_ssm_parameter.secret["meili-master-key"].arn },
  ]

  log_conf = { for s in concat(local.services, ["meilisearch"]) : s => {
    logDriver = "awslogs"
    options = {
      awslogs-group         = aws_cloudwatch_log_group.svc[s].name
      awslogs-region        = var.region
      awslogs-stream-prefix = s
    }
  } }
}

resource "aws_ecs_task_definition" "nginx" {
  family                   = "${local.name}-nginx"
  requires_compatibilities = ["FARGATE"]
  network_mode             = "awsvpc"
  cpu                      = 256
  memory                   = 512
  execution_role_arn       = aws_iam_role.execution.arn

  container_definitions = jsonencode([{
    name             = "nginx"
    image            = "${local.registry["nginx"]}:${var.image_tag}"
    portMappings     = [{ containerPort = 80 }]
    logConfiguration = local.log_conf["nginx"]
  }])
}

resource "aws_ecs_service" "nginx" {
  name            = "nginx"
  cluster         = aws_ecs_cluster.main.id
  task_definition = aws_ecs_task_definition.nginx.arn
  desired_count   = 1
  launch_type     = "FARGATE"

  network_configuration {
    subnets         = module.vpc.private_subnets
    security_groups = [aws_security_group.tasks.id]
  }

  load_balancer {
    target_group_arn = aws_lb_target_group.nginx.arn
    container_name   = "nginx"
    container_port   = 80
  }
}

resource "aws_ecs_task_definition" "api" {
  family                   = "${local.name}-api"
  requires_compatibilities = ["FARGATE"]
  network_mode             = "awsvpc"
  cpu                      = 512
  memory                   = 1024
  execution_role_arn       = aws_iam_role.execution.arn
  task_role_arn            = aws_iam_role.task.arn # S3 access rides on the role, not on keys

  container_definitions = jsonencode([{
    name         = "api"
    image        = "${local.registry["api"]}:${var.image_tag}"
    portMappings = [{ containerPort = 8000 }]
    environment = concat(local.shared_env, [
      { name = "APP_ENV", value = "production" },
      { name = "APP_DEBUG", value = "false" },
    ])
    secrets = concat(local.shared_secrets, [
      { name = "APP_KEY", valueFrom = aws_ssm_parameter.secret["app-key"].arn },
    ])
    logConfiguration = local.log_conf["api"]
  }])
}

resource "aws_ecs_service" "api" {
  name            = "api"
  cluster         = aws_ecs_cluster.main.id
  task_definition = aws_ecs_task_definition.api.arn
  desired_count   = 1
  launch_type     = "FARGATE"

  network_configuration {
    subnets         = module.vpc.private_subnets
    security_groups = [aws_security_group.tasks.id]
  }

  service_registries {
    registry_arn = aws_service_discovery_service.svc["api"].arn
  }
}

# The headless BLPOP consumer — ECS is fine with no ports and no load balancer;
# desired_count = 1 because scale-to-zero would leave nobody blocked on the queue.
resource "aws_ecs_task_definition" "worker" {
  family                   = "${local.name}-worker"
  requires_compatibilities = ["FARGATE"]
  network_mode             = "awsvpc"
  cpu                      = 1024 # the CPU-bound one — the whole reason this service exists
  memory                   = 2048
  execution_role_arn       = aws_iam_role.execution.arn
  task_role_arn            = aws_iam_role.task.arn

  container_definitions = jsonencode([{
    name             = "worker"
    image            = "${local.registry["worker"]}:${var.image_tag}"
    environment      = local.shared_env
    secrets          = local.shared_secrets
    logConfiguration = local.log_conf["worker"]
  }])
}

resource "aws_ecs_service" "worker" {
  name            = "worker"
  cluster         = aws_ecs_cluster.main.id
  task_definition = aws_ecs_task_definition.worker.arn
  desired_count   = 1
  launch_type     = "FARGATE"

  network_configuration {
    subnets         = module.vpc.private_subnets
    security_groups = [aws_security_group.tasks.id]
  }
}

resource "aws_ecs_task_definition" "realtime" {
  family                   = "${local.name}-realtime"
  requires_compatibilities = ["FARGATE"]
  network_mode             = "awsvpc"
  cpu                      = 256
  memory                   = 512
  execution_role_arn       = aws_iam_role.execution.arn

  container_definitions = jsonencode([{
    name             = "realtime"
    image            = "${local.registry["realtime"]}:${var.image_tag}"
    portMappings     = [{ containerPort = 8090 }]
    environment      = local.shared_env
    secrets          = local.shared_secrets
    logConfiguration = local.log_conf["realtime"]
  }])
}

# desired_count stays 1: the in-memory hub is per-instance; scaling out needs
# shared presence (and sticky routing) first.
resource "aws_ecs_service" "realtime" {
  name            = "realtime"
  cluster         = aws_ecs_cluster.main.id
  task_definition = aws_ecs_task_definition.realtime.arn
  desired_count   = 1
  launch_type     = "FARGATE"

  network_configuration {
    subnets         = module.vpc.private_subnets
    security_groups = [aws_security_group.tasks.id]
  }

  service_registries {
    registry_arn = aws_service_discovery_service.svc["realtime"].arn
  }
}

resource "aws_ecs_task_definition" "meilisearch" {
  family                   = "${local.name}-meilisearch"
  requires_compatibilities = ["FARGATE"]
  network_mode             = "awsvpc"
  cpu                      = 512
  memory                   = 1024
  execution_role_arn       = aws_iam_role.execution.arn

  container_definitions = jsonencode([{
    name         = "meilisearch"
    image        = "getmeili/meilisearch:v1.12"
    portMappings = [{ containerPort = 7700 }]
    environment  = [{ name = "MEILI_NO_ANALYTICS", value = "true" }]
    secrets = [
      { name = "MEILI_MASTER_KEY", valueFrom = aws_ssm_parameter.secret["meili-master-key"].arn },
    ]
    mountPoints = [{
      sourceVolume  = "meilidata"
      containerPath = "/meili_data"
    }]
    logConfiguration = local.log_conf["meilisearch"]
  }])

  volume {
    name = "meilidata"
    efs_volume_configuration {
      file_system_id = aws_efs_file_system.meili.id
    }
  }
}

resource "aws_ecs_service" "meilisearch" {
  name            = "meilisearch"
  cluster         = aws_ecs_cluster.main.id
  task_definition = aws_ecs_task_definition.meilisearch.arn
  desired_count   = 1
  launch_type     = "FARGATE"

  network_configuration {
    subnets         = module.vpc.private_subnets
    security_groups = [aws_security_group.tasks.id]
  }

  service_registries {
    registry_arn = aws_service_discovery_service.svc["meilisearch"].arn
  }
}
