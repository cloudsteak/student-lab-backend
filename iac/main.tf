# Terraform deploy for student-lab-backend on Azure Kubernetes (Civo-compatible)

# --- main.tf ---

provider "azurerm" {
  features {
    key_vault {
      purge_soft_delete_on_destroy = true
      recover_soft_deleted_secrets = true
    }
  }

  subscription_id = var.azure_subscription_id
  tenant_id       = var.azure_tenant_id
}

provider "kubernetes" {
  config_path    = var.kubeconfig_path
  config_context = var.kubeconfig_context
}

data "azurerm_client_config" "current" {}

resource "kubernetes_namespace_v1" "lab_ns" {
  metadata {
    name = "student-lab-backend"
  }
}

resource "kubernetes_secret_v1" "lab_secrets" {
  metadata {
    name      = "lab-secrets"
    namespace = kubernetes_namespace_v1.lab_ns.metadata[0].name
  }
  data = {
    BREVO_API_KEY         = var.brevo_api_key
    AUTH0_DOMAIN          = var.auth0_domain
    GITHUB_TOKEN          = var.github_token
    WORDPRESS_SECRET_KEY  = var.wordpress_secret_key
    AZURE_SUBSCRIPTION_ID = var.azure_subscription_id
    AZURE_CLIENT_ID       = var.azure_client_id
    AZURE_TENANT_ID       = var.azure_tenant_id
    AZURE_CLIENT_SECRET   = var.azure_client_secret
  }
  type = "Opaque"
}

resource "kubernetes_secret_v1" "internal_secret_backend" {
  metadata {
    name      = "lab-internal-secret"
    namespace = "student-lab-backend"
  }

  data = {
    INTERNAL_SECRET = random_password.internal_secret.result
  }

  type = "Opaque"
}

resource "kubernetes_secret_v1" "ghcr_auth" {
  metadata {
    name      = "ghcr-auth"
    namespace = kubernetes_namespace_v1.lab_ns.metadata[0].name
  }
  type = "kubernetes.io/dockerconfigjson"
  data = {
    ".dockerconfigjson" = jsonencode({
      "auths" = {
        "https://ghcr.io" = {
          "auth" : base64encode("the1bit:${var.github_token}")
        }
      }
    })
  }
}


resource "kubernetes_config_map_v1" "lab_config" {
  metadata {
    name      = "lab-backend-config"
    namespace = kubernetes_namespace_v1.lab_ns.metadata[0].name
  }
  data = {
    PORTAL_AZURE_URL      = var.azure_portal_url
    PORTAL_AWS_URL        = var.aws_portal_url
    WORDPRESS_WEBHOOK_URL = var.wordpress_webhook_url
  }
}

# Backend Deployment
resource "kubernetes_deployment_v1" "lab_backend" {
  metadata {
    name      = "student-lab-backend"
    namespace = kubernetes_namespace_v1.lab_ns.metadata[0].name
    labels = {
      app = "student-lab-backend"
    }
  }
  spec {
    replicas = 1
    selector {
      match_labels = {
        app = "student-lab-backend"
      }
    }
    template {
      metadata {
        labels = {
          app = "student-lab-backend"
        }
      }
      spec {
        image_pull_secrets {
          name = kubernetes_secret_v1.ghcr_auth.metadata[0].name
        }
        container {
          name  = "backend"
          image = "ghcr.io/cloudsteak/lab-backend:latest"
          port {
            container_port = 8000
          }
          env {
            name  = "REDIS_HOST"
            value = var.redis_host
          }
          env {
            name  = "REDIS_PORT"
            value = "6379"
          }
          env {
            name  = "REDIS_DB"
            value = "1"
          }


          env {
            name = "PORTAL_AWS_URL"
            value_from {
              config_map_key_ref {
                name = kubernetes_config_map_v1.lab_config.metadata[0].name
                key  = "PORTAL_AWS_URL"
              }
            }
          }
          env {
            name = "PORTAL_AZURE_URL"
            value_from {
              config_map_key_ref {
                name = kubernetes_config_map_v1.lab_config.metadata[0].name
                key  = "PORTAL_AZURE_URL"
              }
            }
          }

          env {
            name = "BREVO_API_KEY"
            value_from {
              secret_key_ref {
                name = kubernetes_secret_v1.lab_secrets.metadata[0].name
                key  = "BREVO_API_KEY"
              }
            }
          }
          env {
            name  = "EMAIL_SENDER"
            value = var.email_sender
          }
          env {
            name = "AUTH0_DOMAIN"
            value_from {
              secret_key_ref {
                name = kubernetes_secret_v1.lab_secrets.metadata[0].name
                key  = "AUTH0_DOMAIN"
              }
            }
          }
          env {
            name  = "AUTH0_AUDIENCE"
            value = var.auth0_audience
          }
          env {
            name  = "AUTH0_ALGORITHMS"
            value = var.auth0_algorithms
          }
          env {
            name = "GITHUB_TOKEN"
            value_from {
              secret_key_ref {
                name = kubernetes_secret_v1.lab_secrets.metadata[0].name
                key  = "GITHUB_TOKEN"
              }
            }
          }
          env {
            name  = "GITHUB_REPO"
            value = var.github_repo
          }
          env {
            name  = "GITHUB_WORKFLOW_FILENAME"
            value = var.github_workflow_filename
          }

          env {
            name = "WORDPRESS_WEBHOOK_URL"
            value_from {
              config_map_key_ref {
                name = kubernetes_config_map_v1.lab_config.metadata[0].name
                key  = "WORDPRESS_WEBHOOK_URL"
              }
            }
          }
          env {
            name = "WORDPRESS_SECRET_KEY"
            value_from {
              secret_key_ref {
                name = kubernetes_secret_v1.lab_secrets.metadata[0].name
                key  = "WORDPRESS_SECRET_KEY"
              }
            }
          }

          env {
            name = "AZURE_TENANT_ID"
            value_from {
              secret_key_ref {
                name = kubernetes_secret_v1.lab_secrets.metadata[0].name
                key  = "AZURE_TENANT_ID"
              }
            }
          }

          env {
            name = "AZURE_SUBSCRIPTION_ID"
            value_from {
              secret_key_ref {
                name = kubernetes_secret_v1.lab_secrets.metadata[0].name
                key  = "AZURE_SUBSCRIPTION_ID"
              }
            }
          }

          env {
            name = "AZURE_CLIENT_ID"
            value_from {
              secret_key_ref {
                name = kubernetes_secret_v1.lab_secrets.metadata[0].name
                key  = "AZURE_CLIENT_ID"
              }
            }
          }

          env {
            name = "AZURE_CLIENT_SECRET"
            value_from {
              secret_key_ref {
                name = kubernetes_secret_v1.lab_secrets.metadata[0].name
                key  = "AZURE_CLIENT_SECRET"
              }
            }
          }


          env {
            name = "INTERNAL_SECRET"
            value_from {
              secret_key_ref {
                name = "lab-internal-secret"
                key  = "INTERNAL_SECRET"
              }
            }
          }
        }
      }
    }
  }
}

resource "kubernetes_service_v1" "lab_backend" {
  metadata {
    name      = "student-lab-backend"
    namespace = kubernetes_namespace_v1.lab_ns.metadata[0].name
  }
  spec {
    selector = {
      app = "student-lab-backend"
    }
    port {
      port        = 80
      target_port = 8000
    }
  }
}



