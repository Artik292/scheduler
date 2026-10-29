# Первый запуск в vecaku-diena-2026

Инфраструктура уже создана Terraform. Следующий этап — импорт `docs/scheduler_orig.sql`, затем запуск миграций и проверка приложения. Выполняйте команды из корня `scheduler` в одном терминале.

Для обновления уже работающего сайта используйте [инструкцию релиза через GitHub Actions](RELEASE.md).

Адаптированный дамп проверен на MySQL 8.4.13 и PHP 8.3: импорт, два запуска миграций и интеграционные тесты прошли успешно. Все исходные записи сохранены: 6 групп, 26 преподавателей и счётчик. Записей родителей в этом дампе нет. Строки с текстами страниц в исходном файле закомментированы; миграции добавят начальные тексты приложения.

Импорт предназначен для новой базы до первого использования приложения. После успешного импорта повторно этот этап выполнять не нужно. Первый автоматический deploy через GitHub запускайте после завершения переноса: workflow сам выполняет миграции перед выпуском приложения.

## 1. Указать ресурсы

```bash
export GCP_PROJECT_ID="vecaku-diena-2026"
export GCP_REGION="europe-north1"
export GCP_IMPORT_BUCKET="gs://vecaku-diena-2026_7315"
export GCP_IMPORT_SQL_URI="${GCP_IMPORT_BUCKET}/imports/scheduler_orig-20261027.sql"
```

## 2. Загрузить дамп и предоставить Cloud SQL доступ на чтение

```bash
gcloud storage cp docs/scheduler_orig.sql "$GCP_IMPORT_SQL_URI" \
  --project="$GCP_PROJECT_ID"

GCP_SQL_SERVICE_ACCOUNT="$(gcloud sql instances describe vecaku-diena \
  --project="$GCP_PROJECT_ID" \
  --format='value(serviceAccountEmailAddress)')"

gcloud storage buckets add-iam-policy-binding "$GCP_IMPORT_BUCKET" \
  --member="serviceAccount:${GCP_SQL_SERVICE_ACCOUNT}" \
  --role=roles/storage.objectViewer \
  --project="$GCP_PROJECT_ID"
```

Доступ предоставляется служебной учётной записи экземпляра Cloud SQL. Новые права могут вступать в силу несколько минут.

## 3. Импортировать данные

```bash
gcloud sql import sql vecaku-diena "$GCP_IMPORT_SQL_URI" \
  --database=vecaku-diena \
  --project="$GCP_PROJECT_ID"
```

Подтвердите импорт, если CLI запросит подтверждение, и дождитесь успешного завершения команды. При ошибке сначала проверьте причину: импорт SQL может выполниться частично. Миграции запускайте после успешного импорта.

После успешного импорта временные права чтения бакета можно убрать:

```bash
gcloud storage buckets remove-iam-policy-binding "$GCP_IMPORT_BUCKET" \
  --member="serviceAccount:${GCP_SQL_SERVICE_ACCOUNT}" \
  --role=roles/storage.objectViewer \
  --project="$GCP_PROJECT_ID"
```

SQL-файл останется в приватном бакете как копия исходных данных.

## 4. Запустить миграции

```bash
gcloud run jobs execute vecaku-diena-cloudrun-migrate \
  --region="$GCP_REGION" \
  --project="$GCP_PROJECT_ID" \
  --wait
```

Дождитесь успешного завершения выполнения. Миграции создадут служебные таблицы сессий и ограничений запросов, зарегистрируют версию схемы и добавят начальные тексты. Повторный запуск миграций поддерживается.

## 5. Проверить приложение

Откройте <https://vecaku-diena-cloudrun-irprdit76a-lz.a.run.app>.

Проверьте главную страницу, вход администратора, сохранённые группы и тексты, кабинеты и латышские символы. В текущей версии миграция `007_teacher_roster_2026` заменяет учителей исторического дампа на 103 учителя из XLSX, без предметов. Назначьте предметы в **Skolotāji → Priekšmeti**; [подробности](TEACHER-ROSTER.md). Для входа используйте значения `admin_password` и `teachers_password` из локального `terraform/production.auto.tfvars`.

Проверка <https://vecaku-diena-cloudrun-irprdit76a-lz.a.run.app/healthz.php> должна возвращать `ok`, однако подключение к базе нужно проверить открытием страниц приложения.

## 6. Настроить следующие релизы через GitHub

В GitHub откройте **Settings → Environments → production → Variables** и проверьте четыре переменные:

| Переменная | Значение |
| --- | --- |
| `GCP_PROJECT_ID` | `vecaku-diena-2026` |
| `GCP_REGION` | `europe-north1` |
| `GCP_WORKLOAD_IDENTITY_PROVIDER` | `projects/109229299371/locations/global/workloadIdentityPools/scheduler-github-2026/providers/github` |
| `GCP_DEPLOY_SERVICE_ACCOUNT` | `scheduler-deploy@vecaku-diena-2026.iam.gserviceaccount.com` |

Эти значения также доступны через Terraform:

```bash
terraform -chdir=terraform output
```

Автоматическое развёртывание настроено для ветки `main`. После самостоятельного переноса изменений в эту ветку дождитесь успешного завершения workflow `Build, verify and deploy`. Ручной запуск workflow для развёртывания также выполняйте из `main`.

Описание импорта и необходимых прав: [Cloud SQL — импорт и экспорт SQL](https://docs.cloud.google.com/sql/docs/mysql/import-export/import-export-sql).

Подключение собственного домена: [esgribu.lv через Cloudflare](CUSTOM-DOMAIN.md).

## Обновление индивидуальных часов приёма

Для работающего приложения используется миграция `004_teacher_reception_hours`: она добавляет `start_time` и `end_time` с прежними значениями 17:00 и 19:00. Исходные записи сохраняются. Повторный импорт SQL-дампа для этого обновления не требуется.

При выпуске через GitHub Actions новая версия задачи миграций выполняется перед развёртыванием приложения. После успешного выпуска часы каждого преподавателя можно менять в разделе **Skolotāji**, в полях **Pieņemšana no** и **Pieņemšana līdz**. Приём длится 10 минут; последняя запись должна полностью помещаться в заданный диапазон. Подробности — в [README](../README.md#индивидуальное-время-приёма).
