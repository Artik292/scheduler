# Релиз через GitHub Actions

Эта инструкция обновляет работающий проект `vecaku-diena-2026`. В текущий выпуск входят индивидуальные часы приёма и редактируемое сообщение об отсутствии преподавателя. Команды выполняйте из корня `scheduler`, когда будете готовы опубликовать проверенные изменения.

Pipeline находится в `.github/workflows/google-cloudrun-docker.yml` и называется **Build, verify and deploy**. Ветки `v3` и `v2` запускают проверки, а `main` после проверок запускает развёртывание. Доступ GitHub к GCP через Workload Identity Federation также разрешён только для `main`.

На момент подготовки инструкции рабочая ветка — `v3`, удалённая ветка по умолчанию — `master`, а ветка `main` ещё не создана. Ниже отдельно описан её первый выпуск.

## 1. Проверить environment production

В репозитории GitHub откройте **Settings → Environments → production → Variables** и проверьте значения:

| Переменная | Значение |
| --- | --- |
| `GCP_PROJECT_ID` | `vecaku-diena-2026` |
| `GCP_REGION` | `europe-north1` |
| `GCP_WORKLOAD_IDENTITY_PROVIDER` | `projects/109229299371/locations/global/workloadIdentityPools/scheduler-github-2026/providers/github` |
| `GCP_DEPLOY_SERVICE_ACCOUNT` | `scheduler-deploy@vecaku-diena-2026.iam.gserviceaccount.com` |

Если в environment настроены ограничения веток, разрешите развёртывание из `main`. Если настроено ручное подтверждение релиза, подтвердите job `deploy`, когда pipeline его запросит.

## 2. Создать резервную копию базы перед первым выпуском миграций

```bash
gcloud sql backups create \
  --instance=vecaku-diena \
  --project=vecaku-diena-2026 \
  --description="Before reception hours and absence notice release"
```

Дождитесь успешного завершения. Описание команды: [Cloud SQL backup](https://docs.cloud.google.com/sdk/gcloud/reference/sql/backups/create).

## 3. Сохранить изменения в v3 и запустить проверки

Проверьте текущую ветку и изменения:

```bash
git branch --show-current
git status --short
git diff
```

Для текущего набора изменений добавьте в коммит приложение, тесты, pipeline, документацию и подготовленную конфигурацию:

```bash
git add -- .github/workflows/google-cloudrun-docker.yml README.md \
  parents.php bin/ database/ src/ tests/ \
  docs/scheduler_orig.sql docs/CUSTOM-DOMAIN.md docs/GCP-DEPLOYMENT.md \
  docs/RELEASE.md cloudflare/worker.mjs terraform/main.tf

git diff --cached --stat
git commit -m "Add teacher reception hours and editable absence notice"
git push origin v3
```

Секреты `.env` и `terraform/production.auto.tfvars`, локальный state и планы Terraform исключены из Git. Перед коммитом проверьте список подготовленных файлов.

В GitHub откройте **Actions → Build, verify and deploy** и дождитесь успешного job **verify** для этого коммита из `v3`. Он собирает приложение и выполняет тесты на отдельной MySQL. Job **deploy** в этой ветке будет отмечен как **Skipped** — это предусмотренное поведение.

## 4. Выпустить main

Для первого выпуска, когда удалённой ветки `main` ещё нет, создайте её из уже проверенного коммита `v3`:

```bash
git push origin v3:main
```

Эта команда создаёт `main` и сразу запускает production pipeline. Выполняйте её после настройки `production` и успешной проверки `v3`.

Затем в GitHub **Settings → Default branch** выберите `main`. Это позволит использовать ручной запуск обновлённого workflow из интерфейса GitHub. [Настройка ветки по умолчанию](https://docs.github.com/en/repositories/configuring-branches-and-merges-in-your-repository/managing-branches-in-your-repository/changing-the-default-branch).

Для последующих выпусков создавайте pull request из `v3` в `main`, дождитесь проверок и выполните merge. Push после merge автоматически запустит production pipeline.

## 5. Дождаться pipeline

В **Actions → Build, verify and deploy** откройте запуск для `main`. Последовательность выпуска:

1. **verify** — сборка, проверки PHP, бронирования, индивидуальных часов, сообщения об отсутствии, миграций, HTTP и зависимостей.
2. **Authenticate with Workload Identity Federation** — вход в GCP.
3. **Build and publish immutable image** — сборка `linux/amd64` и загрузка образа с тегом SHA коммита в Artifact Registry.
4. **Apply database migrations before releasing application** — обновление образа задачи миграций и ожидание успешного выполнения `php bin/migrate.php`.
5. **Deploy verified revision** — новая ревизия сервиса `vecaku-diena-cloudrun`.
6. **Check deployed service** — HTTP-проверка `/healthz.php`.

Миграция `004_teacher_reception_hours` добавит преподавателям часы 17:00–19:00, если этот этап ещё не выполнен. Миграция `005_teacher_unavailable_message` добавит текст `teacher_unavailable_message`. Повторные запуски поддерживаются; существующие записи родителей, часы и отредактированное сообщение сохраняются.

Если задача миграций завершится с ошибкой, шаг развёртывания новой ревизии не запустится. Откройте логи упавшего шага, устраните причину и повторите запуск pipeline. Ошибка шага **Check deployed service** возникает уже после развёртывания: в этом случае проверьте состояние новой ревизии Cloud Run.

Для этих изменений достаточно релиза приложения с миграциями. Повторный импорт SQL-дампа и новый `terraform apply` не требуются. Cloudflare Worker продолжит передавать запросы тому же Cloud Run.

## 6. Проверить результат

Откройте <https://esgribu.lv/> либо <https://vecaku-diena-cloudrun-irprdit76a-lz.a.run.app/>.

Войдите администратором через `/go.php` и проверьте:

- В **Skolotāji** есть поля **Pieņemšana no** и **Pieņemšana līdz**.
- Преподаватель с выключенным **Būs** остаётся в списке родителей; при выборе вместо слотов появляется сообщение **Pedagoga nebūs…**.
- В **Virsraksti** появилась запись `teacher_unavailable_message`. Изменение её поля `text` отображается на странице родителей при следующем открытии.
- У доступного преподавателя отображаются слоты его индивидуального расписания.

## Ручной повторный запуск

Когда `main` станет веткой по умолчанию и будет содержать обновлённый workflow, откройте **Actions → Build, verify and deploy → Run workflow**, выберите ветку **main** и нажмите **Run workflow**. Повторный запуск для `v3` выполнит проверки.

Условие GitHub для ручного запуска: workflow с `workflow_dispatch` должен находиться в ветке по умолчанию. [Документация GitHub](https://docs.github.com/en/actions/how-tos/manage-workflow-runs/manually-run-a-workflow).
