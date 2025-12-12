resource "kubernetes_namespace_v1" "cron_ns" {
  metadata {
    name = "cron-jobs"
  }
}


resource "kubernetes_secret_v1" "cronjob_cleanup_secrets" {
  metadata {
    name      = "lab-cleanup-secret"
    namespace = kubernetes_namespace_v1.cron_ns.metadata[0].name
  }
  data = {
    BACKEND_URL     = var.backend_url
    INTERNAL_SECRET = random_password.internal_secret.result
  }
  type = "Opaque"
}

resource "kubernetes_secret_v1" "ghcr_auth_cron" {
  metadata {
    name      = "ghcr-auth"
    namespace = kubernetes_namespace_v1.cron_ns.metadata[0].name
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
    namespace = kubernetes_namespace_v1.cron_ns.metadata[0].name
    labels = {
      app = "lab-cleanup-trigger"
    }
  }

  spec {
    schedule                      = "*/10 * * * *" # Every 10 minutes
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
              name = kubernetes_secret_v1.ghcr_auth_cron.metadata[0].name
            }
            restart_policy = "OnFailure"

            container {
              name              = "lab-cleanup-trigger"
              image             = "ghcr.io/cloudsteak/lab-cleanup-trigger:latest"
              image_pull_policy = "Always"

              env {
                name = "INTERNAL_SECRET"
                value_from {
                  secret_key_ref {
                    name = kubernetes_secret_v1.cronjob_cleanup_secrets.metadata[0].name
                    key  = "INTERNAL_SECRET"
                  }
                }
              }



              env {
                name = "BACKEND_URL"
                value_from {
                  secret_key_ref {
                    name = kubernetes_secret_v1.cronjob_cleanup_secrets.metadata[0].name
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
