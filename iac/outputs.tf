# --- outputs.tf ---

output "backend_url" {
  value = "https://lab-backend.cloudmentor.hu"
}

output "internal_secret_value" {
  value     = random_password.internal_secret.result
  sensitive = true
}
