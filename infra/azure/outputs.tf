output "app_url" {
  description = "Public entry point — nginx's external ingress (compose's localhost:8080)."
  value       = "https://${azurerm_container_app.nginx.ingress[0].fqdn}"
}

output "acr_login_server" {
  description = "Registry CI pushes images to."
  value       = azurerm_container_registry.acr.login_server
}

output "postgres_fqdn" {
  value = azurerm_postgresql_flexible_server.db.fqdn
}

output "redis_hostname" {
  value = azurerm_redis_cache.queue.hostname
}

output "demos_storage_account" {
  description = "Blob storage for demos — live once DemoStorage grows an Azure implementation."
  value       = azurerm_storage_account.demos.name
}
