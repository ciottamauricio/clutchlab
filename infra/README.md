# Cloud skeletons — compose translated twice

Study material for topic 12 on the study page ("Orchestration: compose vs. cloud").
`docker-compose.yml` stays the **local truth**; each directory here is the same
architecture written as a **cloud truth**, so the diff between them is the lesson.

Both are validated skeletons (`terraform validate` passes), not battle-tested
deployments: HTTP-only ingress, small SKUs, no CI wiring, no custom domain.

| | [`azure/`](azure/) (Container Apps) | [`aws/`](aws/) (ECS Fargate) |
|---|---|---|
| service graph | one `container_app` per service | task definition + service per service |
| `clutchnet` DNS | environment-internal DNS (`http://api`) | Cloud Map (`api.clutchlab.local`) |
| `env_file: .env` | ACA secrets + env blocks | SSM parameters + env blocks |
| gateway | nginx keeps the job, sole external ingress | nginx keeps the job, behind the ALB |
| postgres | Flexible Server | RDS |
| redis queue | Azure Cache — **clients must switch to TLS (:6380)** | ElastiCache — plain 6379 in-VPC, zero client changes |
| MinIO / demos | Blob Storage — **requires an AzureBlobDemoStorage implementation** (api + worker) | S3 — **config-only swap**, task-role creds, no keys |
| meilisearch | container + Azure Files share | container + EFS |
| network/IAM | hidden by the platform | explicit: VPC, NAT (~$32/mo floor), SGs, roles |
| `depends_on` | doesn't exist — services must retry connections at boot | same |

What compose can't express and both add: managed database backups, secret stores,
per-service logs, and replica supervision. What both lose: one-file simplicity and
`docker compose up` as the entire deployment.

## Trying one out

```sh
cd infra/azure   # or infra/aws
terraform init
TF_VAR_db_password=... TF_VAR_laravel_app_key=... TF_VAR_meili_master_key=... \
  terraform plan
```

Images are expected in the created registry (ACR/ECR) as `clutchlab-<service>:<tag>`
— pushing them is CI's job (per-service path filters in the monorepo, see topic 11).
The nginx image must bake the built frontend `dist/` (multi-stage build): the Vite
dev-server container has no cloud counterpart.
