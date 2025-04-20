resource "random_password" "internal_secret" {
  length  = 32
  special = true
}
