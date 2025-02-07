# IAM
# SQL
# Cloud Run
# Artifact Registry
# Cloud Storage
# # VPC

terraform {
  required_providers {
    google = {
      source  = "hashicorp/google"
      version = "6.16.0"
    }
  }
}

locals {
  project                = "vecaku-diena"
  region                 = "europe-north1"
  zone                   = "europe-north1-a"
  db_name                = "vecaku-diena-db"
  db_version             = "MYSQL_5_6"
  db_root_password       = "root"
  db_user                = "admin"
  db_user_password       = "admin"
  db_deletion_protection = false
}

provider "google" {
  project = local.project
  region  = local.region
  zone    = local.zone
}
resource "google_compute_network" "peering_network" {
  name                    = "vecaku-diena-private-network"
  auto_create_subnetworks = "false"
}

resource "google_compute_network" "private_network" {
  name = "private-network"
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
  name          = format("%s_%s", local.project, random_integer.random_int.result)
  location      = "EUROPE-NORTH1"
  force_destroy = true
  storage_class = "ARCHIVE"
}

resource "google_storage_bucket_object" "import_sql" {
  name         = "db.sql"
  source       = "../docs/db.sql"
  content_type = "text/plain; charset=utf-8"
  bucket       = google_storage_bucket.bucket.id
  depends_on   = [google_storage_bucket.bucket]
}

resource "google_sql_database" "database" {
  name     = local.db_name
  instance = google_sql_database_instance.instance.id
}

# resource "null_resource" "sql_import" {
#   provisioner "local-exec" {
#     command = "mysql -h ${google_sql_database_instance.instance.private_ip_address} -u ${local.db_user} -p${local.db_user_password} < ../docs/db.sql"
#   }
#   depends_on = [google_sql_database.database]
# }

resource "google_sql_database_instance" "instance" {
  name             = local.db_name
  region           = local.region
  database_version = local.db_version
  settings {
    tier      = "db-f1-micro"
    edition   = "ENTERPRISE"
    disk_size = "10"
    ip_configuration {
      ipv4_enabled                                  = false
      private_network                               = google_compute_network.private_network.self_link #google_compute_network.peering_network.id
      enable_private_path_for_google_cloud_services = true
    }
  }
  root_password       = local.db_root_password
  deletion_protection = local.db_deletion_protection
  depends_on          = [google_service_networking_connection.private_vpc_connection] #[google_compute_global_address.private_ip_address, google_compute_network.peering_network]
}

resource "google_sql_user" "user" {
  name     = local.db_user
  instance = google_sql_database_instance.instance.name
  password = local.db_user_password
}

resource "google_cloud_run_service_iam_binding" "default" {
  location = google_cloud_run_v2_service.main.location
  service  = google_cloud_run_v2_service.main.name
  role     = "roles/run.invoker"
  members = [
    "allUsers"
  ]
}

resource "google_cloud_run_v2_service" "main" {
  name                = "vecaku-diena-cloudrun"
  location            = local.region
  deletion_protection = false
  ingress             = "INGRESS_TRAFFIC_ALL"

  template {
    containers {
      image = "phpmyadmin:5.2.2-apache"
      ports {
        container_port = 80
      }
      env {
        name  = "MYSQL_ROOT_PASSWORD"
        value = local.db_root_password
      }
      env {
        name  = "PMA_HOST"
        value = google_sql_database_instance.instance.private_ip_address
      }
    }
    vpc_access {
      network_interfaces {
        network = google_compute_network.private_network.id
        # subnetwork = google_compute_network.private_network.
        # network    = google_compute_network.peering_network.id
        # subnetwork = google_compute_subnetwork.subnetwork.id
      }
      # connector = google_vpc_access_connector.connector.id
      egress = "PRIVATE_RANGES_ONLY"
    }
    volumes {
      name = "cloudsql"
      cloud_sql_instance {
        instances = [google_sql_database_instance.instance.connection_name]
      }
    }
  }
  depends_on = [
    google_sql_database.database,
    # google_vpc_access_connector.connector,
    # google_compute_network.peering_network,
    # google_compute_subnetwork.subnetwork
  ]
}


output "phpmyadmin-url" {
  value = tolist(google_cloud_run_v2_service.main.urls)[0]
}

output "db-root-password" {
  value = local.db_root_password
}
