# Private teacher data

Keep the real `pedagogu_saraksts_2026.csv` here locally. CSV files in this directory are ignored by Git and excluded from Docker builds. Local Compose mounts this directory read-only.

CI uses the invented records in `database/fixtures/teacher_roster.csv`. For an initial GCP import, provide the real CSV through the GitHub Actions secret `TEACHER_ROSTER_CSV`. After migration `007` has run, subsequent releases do not need the CSV.
