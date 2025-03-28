# Terraform deploy for student-lab-backend on Azure Kubernetes (Civo-compatible)

# --- main.tf ---

provider "azurerm" {
  features {}
}

provider "kubernetes" {
  config_path    = var.kubeconfig_path
  config_context = var.kubeconfig_context
}

resource "kubernetes_namespace" "lab_ns" {
  metadata {
    name = "student-lab-backend"
  }
}

resource "kubernetes_secret" "lab_secrets" {
  metadata {
    name      = "lab-secrets"
    namespace = kubernetes_namespace.lab_ns.metadata[0].name
  }
  data = {
    BREVO_API_KEY = var.brevo_api_key
    AUTH0_DOMAIN  = var.auth0_domain
    GITHUB_TOKEN  = var.github_token
  }
  type = "Opaque"
}


resource "kubernetes_secret" "ghcr_auth" {
  metadata {
    name      = "ghcr-auth"
    namespace = kubernetes_namespace.lab_ns.metadata[0].name
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


resource "kubernetes_config_map" "lab_config" {
  metadata {
    name      = "lab-backend-config"
    namespace = kubernetes_namespace.lab_ns.metadata[0].name
  }
  data = {
    LAB_TTL_SECONDS  = var.lab_ttl_seconds
    PORTAL_AZURE_URL = var.azure_portal_url
    PORTAL_AWS_URL   = var.aws_portal_url
  }
}

resource "kubernetes_deployment" "lab_backend" {
  metadata {
    name      = "student-lab-backend"
    namespace = kubernetes_namespace.lab_ns.metadata[0].name
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
          name = kubernetes_secret.ghcr_auth.metadata[0].name
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
            value = "0"
          }
          env {
            name = "LAB_TTL_SECONDS"
            value_from {
              config_map_key_ref {
                name = kubernetes_config_map.lab_config.metadata[0].name
                key  = "LAB_TTL_SECONDS"
              }
            }

          }
          env {
            name = "PORTAL_AWS_URL"
            value_from {
              config_map_key_ref {
                name = kubernetes_config_map.lab_config.metadata[0].name
                key  = "PORTAL_AWS_URL"
              }
            }
          }
          env {
            name = "PORTAL_AZURE_URL"
            value_from {
              config_map_key_ref {
                name = kubernetes_config_map.lab_config.metadata[0].name
                key  = "PORTAL_AZURE_URL"
              }
            }
          }

          env {
            name = "BREVO_API_KEY"
            value_from {
              secret_key_ref {
                name = kubernetes_secret.lab_secrets.metadata[0].name
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
                name = kubernetes_secret.lab_secrets.metadata[0].name
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
                name = kubernetes_secret.lab_secrets.metadata[0].name
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
        }
      }
    }
  }
}

resource "kubernetes_service" "lab_backend" {
  metadata {
    name      = "student-lab-backend"
    namespace = kubernetes_namespace.lab_ns.metadata[0].name
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

resource "kubernetes_ingress_v1" "lab_backend" {
  metadata {
    name      = "student-lab-backend"
    namespace = kubernetes_namespace.lab_ns.metadata[0].name
    annotations = {
      "cert-manager.io/cluster-issuer"           = "letsencrypt-prod"
      "nginx.ingress.kubernetes.io/ssl-redirect" = "true"
    }
  }
  spec {
    ingress_class_name = "nginx"
    tls {
      hosts       = ["lab-backend.cloudmentor.hu"]
      secret_name = "lab-backend-cert"
    }
    rule {
      host = "lab-backend.cloudmentor.hu"
      http {
        path {
          path      = "/"
          path_type = "Prefix"
          backend {
            service {
              name = kubernetes_service.lab_backend.metadata[0].name
              port {
                number = 80
              }
            }
          }
        }
      }
    }
  }
}
