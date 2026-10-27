terraform {
  required_version = ">= 1.15.0, < 2.0.0"
  cloud {
    organization = "artik292"
    workspaces {
      name = "vecaku-diena"
    }
  }
  required_providers {
    google = {
      source  = "hashicorp/google"
      version = "8.8.1"
    }
    random = {
      source  = "hashicorp/random"
      version = "3.9.1"
    }
  }
}

provider "google" {
  project = var.project_id
  region  = var.region
}

locals {
  service_name = "vecaku-diena-cloudrun"
  db_name      = "vecaku-diena-db"
  environment = {
    APP_ENV = "production"
    DB_HOST = google_sql_database_instance.instance.private_ip_address
    DB_NAME = google_sql_database.database.name
    DB_USER = google_sql_user.user.name
  }
  passwords = {
    DB_PASSWORD       = var.db_password
    ADMIN_PASSWORD    = var.admin_password
    TEACHERS_PASSWORD = var.teachers_password
  }
  secret_names = toset(["DB_PASSWORD", "ADMIN_PASSWORD", "TEACHERS_PASSWORD"])
}

resource "google_project_service" "apis" {
  for_each = toset([
    "run.googleapis.com", "compute.googleapis.com", "sqladmin.googleapis.com",
    "servicenetworking.googleapis.com", "artifactregistry.googleapis.com",
    "secretmanager.googleapis.com", "iam.googleapis.com", "iamcredentials.googleapis.com",
    "sts.googleapis.com", "storage.googleapis.com"
  ])
  service            = each.value
  disable_on_destroy = false
}

# Keep existing network resource addresses to avoid an accidental replacement.
resource "google_compute_network" "peering_network" {
  name                    = "vecaku-diena-private-network"
  auto_create_subnetworks = false
  depends_on              = [google_project_service.apis]
}
resource "google_compute_network" "private_network" {
  name                    = "private-network"
  auto_create_subnetworks = true
  depends_on              = [google_project_service.apis]
}
resource "google_compute_global_address" "private_ip_address" {
  name          = "private-ip-address"
  purpose       = "VPC_PEERING"
  address_type  = "INTERNAL"
  prefix_length = 16
  network       = google_compute_network.private_network.id
}
resource "google_service_networking_connection" "private_vpc_connection" {
  network                 = google_compute_network.private_network.id
  service                 = "servicenetworking.googleapis.com"
  reserved_peering_ranges = [google_compute_global_address.private_ip_address.name]
}
resource "random_integer" "random_int" {
  min = 1000
  max = 9999
}
resource "google_storage_bucket" "bucket" {
  name                        = format("%s_%s", var.project_id, random_integer.random_int.result)
  location                    = var.region
  force_destroy               = false
  storage_class               = "STANDARD"
  uniform_bucket_level_access = true
  public_access_prevention    = "enforced"
  depends_on                  = [google_project_service.apis]
}
resource "google_storage_bucket_object" "import_sql" {
  name         = "schema.sql"
  content      = file("${path.module}/../database/schema.sql")
  content_type = "text/plain; charset=utf-8"
  bucket       = google_storage_bucket.bucket.id
}
resource "google_sql_database_instance" "instance" {
  name                = local.db_name
  region              = var.region
  database_version    = "MYSQL_8_4"
  root_password       = var.db_root_password
  deletion_protection = true
  settings {
    tier                        = "db-custom-1-3840"
    edition                     = "ENTERPRISE"
    disk_size                   = 10
    disk_autoresize             = true
    deletion_protection_enabled = true
    backup_configuration {
      enabled                        = true
      binary_log_enabled             = true
      start_time                     = "02:00"
      transaction_log_retention_days = 7
      backup_retention_settings {
        retained_backups = 7
      }
    }
    ip_configuration {
      ipv4_enabled                                  = false
      private_network                               = google_compute_network.private_network.self_link
      enable_private_path_for_google_cloud_services = true
    }
  }
  depends_on = [google_service_networking_connection.private_vpc_connection]
}
resource "google_sql_database" "database" {
  name      = local.db_name
  instance  = google_sql_database_instance.instance.name
  charset   = "utf8mb4"
  collation = "utf8mb4_unicode_ci"
}
resource "google_sql_user" "user" {
  name     = "scheduler"
  instance = google_sql_database_instance.instance.name
  password = var.db_password
}
resource "google_artifact_registry_repository" "app" {
  location      = var.region
  repository_id = "vecaku-diena"
  format        = "DOCKER"
  depends_on    = [google_project_service.apis]
}
resource "google_service_account" "runtime" {
  account_id   = "scheduler-runtime"
  display_name = "Scheduler application runtime"
  depends_on   = [google_project_service.apis]
}
resource "google_secret_manager_secret" "passwords" {
  for_each  = local.secret_names
  secret_id = "scheduler-${lower(replace(each.key, "_", "-"))}"
  replication {
    auto {}
  }
  depends_on = [google_project_service.apis]
}
resource "google_secret_manager_secret_version" "passwords" {
  for_each    = local.secret_names
  secret      = google_secret_manager_secret.passwords[each.key].id
  secret_data = local.passwords[each.key]
}
resource "google_secret_manager_secret_iam_member" "runtime" {
  for_each  = local.secret_names
  secret_id = google_secret_manager_secret.passwords[each.key].id
  role      = "roles/secretmanager.secretAccessor"
  member    = "serviceAccount:${google_service_account.runtime.email}"
}
resource "google_cloud_run_v2_service" "main" {
  lifecycle {
    ignore_changes = [template[0].containers[0].image]
  }
  name                = local.service_name
  location            = var.region
  deletion_protection = true
  ingress             = "INGRESS_TRAFFIC_ALL"
  template {
    service_account                  = google_service_account.runtime.email
    max_instance_request_concurrency = 20
    scaling {
      min_instance_count = 0
      max_instance_count = 3
    }
    containers {
      image = var.app_image
      ports {
        container_port = 8080
      }
      resources {
        limits = { cpu = "1", memory = "512Mi" }
      }
      startup_probe {
        http_get {
          path = "/healthz.php"
          port = 8080
        }
      }
      dynamic "env" {
        for_each = local.environment
        content {
          name  = env.key
          value = env.value
        }
      }
      dynamic "env" {
        for_each = local.secret_names
        content {
          name = env.value
          value_source {
            secret_key_ref {
              secret  = google_secret_manager_secret.passwords[env.value].secret_id
              version = google_secret_manager_secret_version.passwords[env.value].version
            }
          }
        }
      }
    }
    vpc_access {
      network_interfaces {
        network    = google_compute_network.private_network.id
        subnetwork = "projects/${var.project_id}/regions/${var.region}/subnetworks/private-network"
      }
      egress = "PRIVATE_RANGES_ONLY"
    }
  }
  depends_on = [google_secret_manager_secret_iam_member.runtime]
}
resource "google_cloud_run_service_iam_binding" "default" {
  location = google_cloud_run_v2_service.main.location
  service  = google_cloud_run_v2_service.main.name
  role     = "roles/run.invoker"
  members  = ["allUsers"]
}
resource "google_cloud_run_v2_job" "migrate" {
  lifecycle {
    ignore_changes = [template[0].template[0].containers[0].image]
  }
  name                = "${local.service_name}-migrate"
  location            = var.region
  deletion_protection = true
  template {
    template {
      service_account = google_service_account.runtime.email
      max_retries     = 0
      timeout         = "600s"
      containers {
        image   = var.app_image
        command = ["php"]
        args    = ["bin/migrate.php"]
        dynamic "env" {
          for_each = local.environment
          content {
            name  = env.key
            value = env.value
          }
        }
        env {
          name = "DB_PASSWORD"
          value_source {
            secret_key_ref {
              secret  = google_secret_manager_secret.passwords["DB_PASSWORD"].secret_id
              version = google_secret_manager_secret_version.passwords["DB_PASSWORD"].version
            }
          }
        }
      }
      vpc_access {
        network_interfaces {
          network    = google_compute_network.private_network.id
          subnetwork = "projects/${var.project_id}/regions/${var.region}/subnetworks/private-network"
        }
        egress = "PRIVATE_RANGES_ONLY"
      }
    }
  }
  depends_on = [google_secret_manager_secret_iam_member.runtime]
}
resource "google_service_account" "deploy" {
  account_id = "scheduler-deploy"
  depends_on = [google_project_service.apis]
}
resource "google_iam_workload_identity_pool" "github" {
  workload_identity_pool_id = "scheduler-github"
  depends_on                = [google_project_service.apis]
}
resource "google_iam_workload_identity_pool_provider" "github" {
  workload_identity_pool_id          = google_iam_workload_identity_pool.github.workload_identity_pool_id
  workload_identity_pool_provider_id = "github"
  attribute_condition                = "assertion.repository == '${var.github_repository}' && assertion.ref == 'refs/heads/main'"
  attribute_mapping = {
    "google.subject"       = "assertion.sub"
    "attribute.repository" = "assertion.repository"
  }
  oidc {
    issuer_uri = "https://token.actions.githubusercontent.com"
  }
}
resource "google_service_account_iam_member" "github" {
  service_account_id = google_service_account.deploy.name
  role               = "roles/iam.workloadIdentityUser"
  member             = "principalSet://iam.googleapis.com/${google_iam_workload_identity_pool.github.name}/attribute.repository/${var.github_repository}"
}
resource "google_project_iam_member" "deploy_run" {
  project = var.project_id
  role    = "roles/run.developer"
  member  = "serviceAccount:${google_service_account.deploy.email}"
}
resource "google_service_account_iam_member" "deploy_runtime" {
  service_account_id = google_service_account.runtime.name
  role               = "roles/iam.serviceAccountUser"
  member             = "serviceAccount:${google_service_account.deploy.email}"
}
resource "google_artifact_registry_repository_iam_member" "deploy" {
  location   = google_artifact_registry_repository.app.location
  repository = google_artifact_registry_repository.app.name
  role       = "roles/artifactregistry.writer"
  member     = "serviceAccount:${google_service_account.deploy.email}"
}
output "app_url" {
  value = google_cloud_run_v2_service.main.uri
}
output "workload_identity_provider" {
  value = google_iam_workload_identity_pool_provider.github.name
}
output "deploy_service_account" {
  value = google_service_account.deploy.email
}
