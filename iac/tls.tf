resource "kubernetes_manifest" "evolvia_backend_tls_certificate" {
  manifest = {
    apiVersion = "cert-manager.io/v1"
    kind       = "Certificate"

    metadata = {
      name      = "evolvia-backend-tls"
      namespace = "istio-system"
    }

    spec = {
      secretName = "evolvia-backend-tls" # ebbe a Secret-be kerül a TLS cert+key

      dnsNames = [
        "evolvia-backend.cloudmentor.hu"
      ]

      issuerRef = {
        name = "letsencrypt-dns01"
        kind = "ClusterIssuer"
      }
    }
  }
}
