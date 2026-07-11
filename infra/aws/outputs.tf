output "app_url" {
  description = "Public entry point — the ALB in front of nginx (compose's localhost:8080)."
  value       = "http://${aws_lb.main.dns_name}"
}

output "ecr_repositories" {
  description = "Registries CI pushes images to."
  value       = { for s, r in aws_ecr_repository.svc : s => r.repository_url }
}

output "postgres_endpoint" {
  value = aws_db_instance.postgres.address
}

output "redis_endpoint" {
  value = aws_elasticache_cluster.redis.cache_nodes[0].address
}

output "demos_bucket" {
  description = "Real S3 replacing MinIO — a config-only swap, no code changes."
  value       = aws_s3_bucket.demos.bucket
}
