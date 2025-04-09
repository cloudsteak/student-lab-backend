resource "kubernetes_namespace" "cron_ns" {
  metadata {
    name = "cron-jobs"
  }
}


resource "kubernetes_secret" "cronjob_cleanup_secrets" {
  metadata {
    name      = "lab-cleanup-secret"
    namespace = kubernetes_namespace.cron_ns.metadata[0].name
  }
  data = {
    AUTH0_DOMAIN        = var.auth0_domain
    AUTH0_CLIENT_ID     = var.auth0_lab_automation_client_id
    AUTH0_CLIENT_SECRET = var.auth0_lab_automation_client_secret
    AUTH0_AUDIENCE      = var.auth0_audience
    BACKEND_URL         = var.backend_url


  }
  type = "Opaque"
}

resource "kubernetes_secret" "ghcr_auth_cron" {
  metadata {
    name      = "ghcr-auth"
    namespace = kubernetes_namespace.cron_ns.metadata[0].name
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


resource "kubernetes_cron_job_v1" "lab_cleanup" {
  metadata {
    name      = "backend-lab-cleanup"
    namespace = kubernetes_namespace.cron_ns.metadata[0].name
    labels = {
      app = "lab-cleanup-trigger"
    }
  }

  spec {
    schedule                      = "*/30 * * * *"
    successful_jobs_history_limit = 1
    failed_jobs_history_limit     = 1

    job_template {
      metadata {
        labels = {
          app = "lab-cleanup-trigger"
        }
      }

      spec {
        template {
          metadata {
            labels = {
              app = "lab-cleanup-trigger"
            }
          }

          spec {
            image_pull_secrets {
              name = kubernetes_secret.ghcr_auth_cron.metadata[0].name
            }
            restart_policy = "OnFailure"

            container {
              name              = "lab-cleanup-trigger"
              image             = "ghcr.io/cloudsteak/lab-cleanup-trigger:latest"
              image_pull_policy = "Always"

              env {
                name = "AUTH0_DOMAIN"
                value_from {
                  secret_key_ref {
                    name = kubernetes_secret.cronjob_cleanup_secrets.metadata[0].name
                    key  = "AUTH0_DOMAIN"
                  }
                }
              }

              env {
                name = "AUTH0_CLIENT_ID"
                value_from {
                  secret_key_ref {
                    name = kubernetes_secret.cronjob_cleanup_secrets.metadata[0].name
                    key  = "AUTH0_CLIENT_ID"
                  }
                }
              }

              env {
                name = "AUTH0_CLIENT_SECRET"
                value_from {
                  secret_key_ref {
                    name = kubernetes_secret.cronjob_cleanup_secrets.metadata[0].name
                    key  = "AUTH0_CLIENT_SECRET"
                  }
                }
              }

              env {
                name = "AUTH0_AUDIENCE"
                value_from {
                  secret_key_ref {
                    name = kubernetes_secret.cronjob_cleanup_secrets.metadata[0].name
                    key  = "AUTH0_AUDIENCE"
                  }
                }
              }

              env {
                name = "BACKEND_URL"
                value_from {
                  secret_key_ref {
                    name = kubernetes_secret.cronjob_cleanup_secrets.metadata[0].name
                    key  = "BACKEND_URL"
                  }
                }
              }

            }
          }
        }
      }
    }
  }
}
