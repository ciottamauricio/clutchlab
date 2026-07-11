variable "environment" {
  description = "Short environment name, used in resource names (e.g. prod, staging)."
  type        = string
  default     = "prod"
}

variable "region" {
  description = "AWS region."
  type        = string
  default     = "sa-east-1"
}

variable "image_tag" {
  description = "Tag of the service images in ECR (CI sets this per deploy)."
  type        = string
  default     = "latest"
}

variable "db_database" {
  type    = string
  default = "clutchlab"
}

variable "db_username" {
  type    = string
  default = "clutchlab"
}

# Secrets: never defaults, never committed — pass via TF_VAR_* env vars or a
# secrets-backed tfvars kept out of git (same rule as the repo-root .env).
variable "db_password" {
  type      = string
  sensitive = true
}

variable "laravel_app_key" {
  description = "Laravel APP_KEY (base64:...)."
  type        = string
  sensitive   = true
}

variable "meili_master_key" {
  type      = string
  sensitive = true
}
