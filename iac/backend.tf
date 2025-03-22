terraform {
  backend "azurerm" {
    resource_group_name  = "terraform-backend-rg"
    storage_account_name = "tfstatecloudmentor"
    container_name       = "terraform-state"
    key                  = "student-lab-backend/terraform.tfstate"
  }
}
