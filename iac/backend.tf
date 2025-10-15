terraform {
  backend "azurerm" {
    resource_group_name  = "terraform-backend-rg"
    storage_account_name = "tfstateevolvia"
    container_name       = "terraform-state"
    key                  = "evolvia-backend/terraform.tfstate"
  }
}
