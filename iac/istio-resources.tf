
resource "kubernetes_manifest" "evolvia_backend_gateway" {
  manifest = {
    apiVersion = "networking.istio.io/v1beta1"
    kind       = "Gateway"

    metadata = {
      name      = "evolvia-backend-gateway"
      namespace = kubernetes_namespace_v1.lab_ns.metadata[0].name
    }

    spec = {
      selector = {
        istio = "ingress"
      }

      servers = [
        {
          port = {
            number   = 443
            name     = "https"
            protocol = "HTTPS"
          }

          hosts = [
            "evolvia-backend.cloudmentor.hu"
          ]

          tls = {
            mode           = "SIMPLE"
            credentialName = "evolvia-backend-tls" # a cert-manager által létrehozott Secret
          }
        }
      ]
    }
  }
}



resource "kubernetes_manifest" "evolvia_backend_virtual_service" {
  manifest = {
    apiVersion = "networking.istio.io/v1beta1"
    kind       = "VirtualService"

    metadata = {
      name      = "evolvia-backend"
      namespace = kubernetes_namespace_v1.lab_ns.metadata[0].name
    }

    spec = {
      hosts = [
        "evolvia-backend.cloudmentor.hu"
      ]

      gateways = [
        kubernetes_manifest.evolvia_backend_gateway.manifest.metadata.name
      ]

      http = [
        {
          route = [
            {
              destination = {
                host = kubernetes_service_v1.lab_backend.metadata[0].name
                port = {
                  number = 80
                }
              }
            }
          ]
        }
      ]
    }
  }
}
