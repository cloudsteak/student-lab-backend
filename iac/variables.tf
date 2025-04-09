# --- variables.tf ---

variable "kubeconfig_path" {
  description = "Path to the kubeconfig file"
  type        = string
}

variable "kubeconfig_context" {
  description = "Name of the kubeconfig context to use"
  type        = string
}



variable "brevo_api_key" {
  type      = string
  sensitive = true
}

variable "auth0_domain" {
  type      = string
  sensitive = true
}

variable "auth0_lab_automation_client_id" {
  type      = string
  sensitive = true
}

variable "auth0_lab_automation_client_secret" {
  type      = string
  sensitive = true
}

variable "auth0_audience" {
  type = string
}
variable "github_token" {
  type      = string
  sensitive = true
}

variable "email_sender" {
  type = string
}



variable "auth0_algorithms" {
  type    = string
  default = "RS256"
}

variable "github_repo" {
  type = string
}

variable "github_workflow_filename" {
  type = string
}

variable "lab_ttl_seconds" {
  type    = string
  default = "5400"
}


variable "redis_host" {
  type = string
}

variable "azure_portal_url" {
  type = string
}

variable "aws_portal_url" {
  type = string
}

variable "backend_url" {
  type = string
}
