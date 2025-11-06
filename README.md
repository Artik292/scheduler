# Vecāku Diena / Parents' Day Scheduler

## Overview
- Web application for scheduling appointments between parents and teachers during school open days. Built with PHP (ATK4 UI) and MySQL, packaged for Docker and Cloud Run.

## Minimum Requirements
- Docker 24+ (for containerised run) or PHP 8.2 with extensions `pdo_mysql`, `intl`, `gd`, `zip`; Composer 2; MySQL 5.6+; wkhtmltopdf binary (for PDF export).

## Environment Variables
- `DATABASE_URL`
  - Mandatory. DSN for the MySQL database in PDO format, for example `mysql://user:password@host/dbname`. Defaults to `mysql:host=localhost;dbname=testdb` with `root/rootpassword` (development only).
- `pass`
  - Optional but recommended. Admin portal password (route `go.php`). Default fallback: `admin`.
- `teachers_pass`
  - Optional but recommended. Password for teacher access (route `teachers_access.php`). Default fallback: `teachers`.

## Quick Start with Docker
1. Pull or build the image.
   ```bash
   docker build -t vecaku-diena:local .
   ```
2. Run the container with required variables (replace placeholders with your secrets).
   ```bash
   docker run -d \
     --name vecaku_diena \
     -p 8080:80 \
     -e DATABASE_URL="mysql://user:password@host/dbname" \
     -e pass="change_me_admin" \
     -e teachers_pass="change_me_teachers" \
     vecaku-diena:local
   ```
3. Access the app at `http://localhost:8080`.
4. Create database backups if needed.
   ```bash
   docker exec vecaku_diena /usr/bin/mysqldump -u root --password=PASSWORD DATABASE > backup.sql
   ```

## Database Notes
- SQL schema and seed data live in `docs/db.sql` (legacy dumps in `docs/scheduler*.sql`). Update these files if schema changes.

## CI/CD & Infrastructure
- GitHub Actions workflow `.github/workflows/google-cloudrun-docker.yml` builds the image, pushes it to Artifact Registry, and deploys to Cloud Run. Configure repository secrets `GCP_PROJECT_ID` and `GOOGLE_APPLICATION_CREDENTIALS` (JSON key).
- `terraform/` contains infrastructure-as-code templates for Cloud SQL, Cloud Run, Artifact Registry, and Cloud Storage. Update the project ID and credentials before running `terraform init/plan/apply`.

## Terraform Prep
- Install Terraform CLI ≥ 1.5 and authenticate the Google Cloud SDK (`gcloud auth application-default login`) or prepare a service account JSON key for Terraform Cloud/local runs.
- Create a new Google Cloud project with billing enabled; edit `terraform/main.tf` to update `locals.project`, any hard-coded project IDs, and the preferred region/zone.
- Enable required APIs:
   * Cloud SQL Admin
   * Cloud Run Admin
   * Artifact Registry
   * IAM
   * Service Usage
   * Service Networking
   * Compute Engine
   * Cloud Storage
- Provision a service account (e.g., `terraform-runner`) with roles: `roles/run.admin`, `roles/cloudsql.admin`, `roles/artifactregistry.admin`, `roles/storage.admin`, `roles/iam.serviceAccountUser`. Download its JSON key and supply it via `GOOGLE_APPLICATION_CREDENTIALS` or Terraform Cloud variable `GOOGLE_CREDENTIALS`.
- Update or remove the Terraform Cloud backend stanza (currently targeting organization `artik292`, workspace `vecaku-diena`) if you plan to run locally or under a different account.
- Run `terraform init`, inspect the execution plan with `terraform plan`, then apply with `terraform apply` once the project setup matches your configuration.

## Useful Assets
- Custom styles live in `assets/css/app.css`; modify as needed to align with school branding.

----------------------------------------------------------------

## Обзор
- Веб‑приложение для записи родителей к учителям на дни открытых дверей. Написано на PHP (ATK4 UI) с базой данных MySQL, упаковано для Docker и Cloud Run.

## Минимальные требования
- Docker 24+ (для запуска в контейнере) либо PHP 8.2 с расширениями `pdo_mysql`, `intl`, `gd`, `zip`; Composer 2; MySQL 5.6+; бинарник wkhtmltopdf (для экспорта PDF).

## Переменные окружения
- `DATABASE_URL`
  - Обязательно. Строка подключения к MySQL в формате PDO, например `mysql://user:password@host/dbname`. По умолчанию используется `mysql:host=localhost;dbname=testdb` с `root/rootpassword` (только для разработки).
- `pass`
  - Необязательная, но рекомендуемая. Пароль админ-панели (маршрут `go.php`). Значение по умолчанию: `admin`.
- `teachers_pass`
  - Необязательная, но рекомендуемая. Пароль входа для учителей (маршрут `teachers_access.php`). Значение по умолчанию: `teachers`.

## Быстрый старт через Docker
1. Скачайте или соберите образ.
   ```bash
   docker build -t vecaku-diena:local .
   ```
2. Запустите контейнер, подставив свои значения переменных.
   ```bash
   docker run -d \
     --name vecaku_diena \
     -p 8080:80 \
     -e DATABASE_URL="mysql://user:password@host/dbname" \
     -e pass="change_me_admin" \
     -e teachers_pass="change_me_teachers" \
     vecaku-diena:local
   ```
3. Откройте приложение по адресу `http://localhost:8080`.
4. Создавайте резервные копии базы по необходимости (команда выше).
   ```bash
   docker exec vecaku_diena /usr/bin/mysqldump -u root --password=PASSWORD DATABASE > backup.sql
   ```

## Работа с базой данных
- Схема и тестовые данные находятся в `docs/db.sql` (старые дампы — `docs/scheduler*.sql`). Обновляйте файлы при изменении структуры.

## CI/CD и инфраструктура
- GitHub Actions (`.github/workflows/google-cloudrun-docker.yml`) собирает образ, отправляет его в Artifact Registry и деплоит в Cloud Run. Настройте секреты репозитория `GCP_PROJECT_ID` и `GOOGLE_APPLICATION_CREDENTIALS` (JSON ключ).
- Папка `terraform/` содержит Terraform-конфигурации для Cloud SQL, Cloud Run, Artifact Registry и Cloud Storage. Перед `terraform init/plan/apply` задайте корректный идентификатор проекта и учетные данные.

## Подготовка Terraform
- Установите Terraform CLI версии ≥ 1.5 и выполните аутентификацию Google Cloud SDK (`gcloud auth application-default login`) либо подготовьте JSON-ключ сервисного аккаунта для Terraform Cloud/локального запуска.
- Создайте новый проект Google Cloud с включённым биллингом; отредактируйте `terraform/main.tf`, чтобы задать `locals.project`, актуальные идентификаторы проекта и нужный регион/зону.
- Включите необходимые API: 
   * Cloud SQL Admin
   * Cloud Run Admin
   * Artifact Registry
   * IAM
   * Service Usage
   * Service Networking
   * Compute Engine
   * Cloud Storage
- Создайте сервисный аккаунт (например, `terraform-runner`) с ролями `roles/run.admin`, `roles/cloudsql.admin`, `roles/artifactregistry.admin`, `roles/storage.admin`, `roles/iam.serviceAccountUser`. Скачайте JSON-ключ и передайте его через `GOOGLE_APPLICATION_CREDENTIALS` либо переменную Terraform Cloud `GOOGLE_CREDENTIALS`.
- Обновите или удалите блок Terraform Cloud (сейчас указывает на организацию `artik292`, workspace `vecaku-diena`), если собираетесь запускать локально либо под другой учётной записью.
- Выполните `terraform init`, проверьте план с `terraform plan`, затем запустите `terraform apply`, когда настройки проекта полностью соответствуют конфигурации.

## Полезные материалы
- Пользовательские стили расположены в `assets/css/app.css`; меняйте при необходимости под фирменный стиль школы.

----------------------------------------------------------------

# Stats

2024:
8012 visitors
471 records from 1020 available