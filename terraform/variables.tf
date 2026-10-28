variable "project_id" {
  type    = string
  default = "terraform-447811"
}
variable "region" {
  type    = string
  default = "europe-north1"
}
variable "app_image" {
  description = "Immutable Artifact Registry image URI, preferably with @sha256 digest."
  type        = string
}
variable "github_repository" {
  description = "GitHub owner/repository allowed to deploy from main."
  type        = string
}
variable "db_password" {
  type      = string
  sensitive = true
  validation {
    condition     = length(var.db_password) >= 24
    error_message = "Use a unique database password of at least 24 characters."
  }
}
variable "db_root_password" {
  description = "Separate administrator credential; never use it for the application."
  type        = string
  sensitive   = true
  validation {
    condition     = length(var.db_root_password) >= 24 && var.db_root_password != var.db_password
    error_message = "Use a separate root password of at least 24 characters."
  }
}
variable "admin_password" {
  type      = string
  sensitive = true
  validation {
    condition     = length(var.admin_password) >= 12
    error_message = "Use an administrator password of at least 12 characters."
  }
}
variable "teachers_password" {
  type      = string
  sensitive = true
  validation {
    condition     = length(var.teachers_password) >= 12
    error_message = "Use a teachers password of at least 12 characters."
  }
}
