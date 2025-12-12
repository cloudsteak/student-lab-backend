terraform {
  backend "azurerm" {
    resource_group_name  = "terraform-backend-rg"
    storage_account_name = "tfstateevolvia"
    container_name       = "terraform-state"
    key                  = "evolvia-backend/temp-terraform.tfstate" #"evolvia-backend/terraform.tfstate"
  }
}
