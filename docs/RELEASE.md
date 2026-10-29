# Релиз через GitHub Actions

Этот выпуск добавляет несколько предметов у одного преподавателя и **один раз заменяет всех учителей и записи к ним** списком из `docs/pedagogu_saraksts.xlsx`: 103 учителя без назначенных предметов. Тексты сайта и предметы сохраняются. [Что именно импортируется](TEACHER-ROSTER.md).

## 1. Проверить переменные

В GitHub откройте **Settings → Secrets and variables → Actions → Variables**. Можно также использовать переменные environment `production`. Нужны именно **Variables**, поскольку workflow читает `vars.*`:

| Переменная | Значение |
| --- | --- |
| `GCP_PROJECT_ID` | `vecaku-diena-2026` |
| `GCP_REGION` | `europe-north1` |
| `GCP_WORKLOAD_IDENTITY_PROVIDER` | `projects/109229299371/locations/global/workloadIdentityPools/scheduler-github-2026/providers/github` |
| `GCP_DEPLOY_SERVICE_ACCOUNT` | `scheduler-deploy@vecaku-diena-2026.iam.gserviceaccount.com` |

Рабочий pipeline уже использует Workload Identity Federation; JSON-ключ для этого выпуска не нужен.

## 2. Создать резервную копию базы

Перед merge в `main` выполните и дождитесь успешного завершения:

```bash
gcloud sql backups create \
  --instance=vecaku-diena \
  --project=vecaku-diena-2026 \
  --description="Before multiple subjects and teacher roster replacement"
```

Команда ожидает завершения, если не указан `--async`. [Документация Cloud SQL backup](https://docs.cloud.google.com/sdk/gcloud/reference/sql/backups/create).

## 3. Сохранить изменения и открыть PR

Из корня проекта создайте рабочую ветку, если сейчас находитесь в `main`:

```bash
git switch -c feature/teacher-subjects-roster
```

Проверьте изменения и добавьте файлы этого выпуска:

```bash
git status --short
git diff

git add -- .github/workflows/google-cloudrun-docker.yml README.md parents.php \
  bin/migrate.php bin/convert_teacher_roster.py \
  database/schema.sql database/imports/pedagogu_saraksts_2026.csv \
  src/TeacherRoster.php src/Model/Teacher.php src/Model/Subject.php \
  tests/reception_hours.php tests/unavailable_teacher.php tests/multiple_subjects.php \
  tests/teacher_roster_conversion.py tests/roster_import.php \
  tests/migrations.sh tests/verify_migration.php \
  docs/pedagogu_saraksts.xlsx docs/TEACHER-ROSTER.md docs/RELEASE.md docs/GCP-DEPLOYMENT.md

git diff --cached --stat
git commit -m "Support multiple teacher subjects and replace the teacher roster"
git push -u origin feature/teacher-subjects-roster
```

Откройте PR этой ветки в **main** и дождитесь зелёного **verify**. Он собирает образ, проверяет совпадение XLSX и CSV, старую и новую схемы, сохранность данных при импорте, повторные миграции, общие слоты и прежние функции приложения на отдельной MySQL.

## 4. Выпустить main

Выполните merge PR. Workflow **Build, verify and deploy** автоматически запустится для `main`:

1. `verify` проверит приложение.
2. `deploy` соберёт и загрузит образ в Artifact Registry.
3. Cloud Run Job выполнит `php bin/migrate.php`: сначала `006_teacher_multiple_subjects`, затем `007_teacher_roster_2026`.
4. После успешных миграций pipeline развернёт новую ревизию `vecaku-diena-cloudrun` и проверит `/healthz.php`.

Для работающего проекта не нужны ручной SQL-импорт и `terraform apply`. Cloudflare Worker продолжит использовать прежний адрес Cloud Run.

Повторный запуск пропускает `007`: ручные назначения предметов, новые записи родителей и изменённые часы останутся на месте. Если импорт завершился, а последующий deploy упал, устраните ошибку и повторите pipeline. При ошибке самого импорта его транзакция откатится полностью.

## 5. Назначить предметы и проверить сайт

Откройте <https://esgribu.lv/go.php>, затем **Skolotāji**:

- В списке 103 новых учителя с кабинетами из файла; Tatjana Aļeņina одна.
- В редактировании учителя выберите один или несколько **Priekšmeti** и сохраните. До назначения предмета учитель виден только в административном/учительском списке.
- Настройте индивидуальные часы и **Būs**, где необходимо. После импорта часы по умолчанию 17:00–19:00.
- На странице родителей проверьте, что учитель появляется в обоих выбранных предметах с одинаковыми временами. Запись на один слот должна блокировать его в обоих предметах.
- Проверьте сохранность **Priekšmeti** и **Virsraksti**. Сообщение об отсутствии остаётся редактируемым как прежде.

Импорт не восстанавливается откатом только контейнерного образа: для возврата старого списка и старых записей потребуется резервная копия БД. Не удаляйте отметку `007_teacher_roster_2026` для повторного выпуска.

## Ручной повторный запуск

Откройте **Actions → Build, verify and deploy → Run workflow → Branch: main → Run workflow**. Workflow с `workflow_dispatch` должен быть в ветке по умолчанию. [Документация GitHub](https://docs.github.com/en/actions/how-tos/manage-workflow-runs/manually-run-a-workflow).
