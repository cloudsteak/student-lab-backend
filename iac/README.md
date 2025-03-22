# --- README.md ---

## student-lab-backend Terraform Deploy

```bash
terraform init
terraform plan
terraform apply
```

Make sure you configured Azure CLI authentication and have access to the kubeconfig of your Civo/Azure K8s cluster.
```
az login
az account set --subscription "Your Subscription"
```

All secrets (Brevo, Auth0, GitHub token) are stored securely in Kubernetes Secrets.
