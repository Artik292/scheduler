# Релиз через GitHub Actions

Текущий выпуск адаптирует страницы для телефона и убирает реальные данные преподавателей из Git. На телефоне предмет, учитель и время выбираются последовательно, есть кнопка назад; на компьютере списки расположены рядом. Существующие учителя, тексты, предметы и записи в БД сохраняются.

## Переменные GCP

В **Settings → Secrets and variables → Actions → Variables** или в environment `production` проверьте:

| Переменная | Значение |
| --- | --- |
| `GCP_PROJECT_ID` | `vecaku-diena-2026` |
| `GCP_REGION` | `europe-north1` |
| `GCP_WORKLOAD_IDENTITY_PROVIDER` | `projects/109229299371/locations/global/workloadIdentityPools/scheduler-github-2026/providers/github` |
| `GCP_DEPLOY_SERVICE_ACCOUNT` | `scheduler-deploy@vecaku-diena-2026.iam.gserviceaccount.com` |

Если `007_teacher_roster_2026` уже выполнена в GCP, CSV для выпуска не требуется. Если ещё не выполнена, добавьте Actions **secret** `TEACHER_ROSTER_CSV` с полным содержимым локального CSV, включая заголовок. Pipeline передаст его только выполнению миграций; данные не включаются в Git или образ. После первого импорта секрет можно удалить. [Подробности импорта](TEACHER-ROSTER.md).

## Текущий выпуск с очисткой истории

Чтобы убрать имена и из прежних коммитов, используйте [пошаговую инструкцию очистки Git](PRIVATE-DATA.md). Порядок: локальный коммит → повторная подготовка проверенного mirror → ручная публикация очищенных веток → запуск pipeline для `main`. До завершения очистки не отправляйте обычный push из старой истории.

Автоматическая публикация истории и force push инструментами проекта не выполняются.

## Последующие обычные выпуски

После очистки работайте из нового clone. Создавайте PR рабочей ветки в `main`, дождитесь успешного `verify` и выполните merge. Для важных изменений данных предварительно можно создать резервную копию:

```bash
gcloud sql backups create \
  --instance=vecaku-diena \
  --project=vecaku-diena-2026 \
  --description="Before application release"
```

[Документация резервной копии Cloud SQL](https://docs.cloud.google.com/sdk/gcloud/reference/sql/backups/create).

Workflow **Build, verify and deploy**:

1. Соберёт образ и проверит бронирование, несколько предметов, индивидуальные часы, сообщение об отсутствии, миграции и HTTP на вымышленных данных.
2. Загрузит образ `linux/amd64` в Artifact Registry.
3. Выполнит Cloud Run Job `php bin/migrate.php`. Уже применённая `007` пропускается без CSV и без сброса данных.
4. Выпустит сервис `vecaku-diena-cloudrun` и проверит `/healthz.php`.

Для этого выпуска `terraform apply` и повторный SQL-импорт не нужны. Cloudflare Worker продолжит использовать прежний Cloud Run URL.

## Проверить результат

На телефоне откройте <https://esgribu.lv/>. Проверьте выбор предмета и учителя, возврат назад, выбор времени и отправку формы. В админке проверьте поиск и редактирование учителя. Уже занятый слот должен быть заблокирован во всех предметах этого учителя; учитель с выключенным **Būs** показывает сообщение вместо времён.

Ручной повторный запуск: **Actions → Build, verify and deploy → Run workflow → Branch: main → Run workflow**. [Документация GitHub](https://docs.github.com/en/actions/how-tos/manage-workflow-runs/manually-run-a-workflow).
