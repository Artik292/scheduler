<?php

declare(strict_types=1);

namespace Scheduler;

final class TeacherRoster
{
    public const VERSION = '007_teacher_roster_2026';

    public static function configuredFile(): string
    {
        return getenv('TEACHER_ROSTER_FILE') ?: dirname(__DIR__) . '/database/imports/pedagogu_saraksts_2026.csv';
    }

    /** @return list<array{0: string, 1: string, 2: string}> */
    public static function read(string $file): array
    {
        $stream = @fopen($file, 'rb');
        if ($stream === false) {
            throw new \RuntimeException('Teacher roster is missing. Supply a private CSV file or the TEACHER_ROSTER_CSV secret before the initial roster migration. Existing data was not changed.');
        }
        try {
            if (fgetcsv($stream, 0, ',', '"', '') !== ['name', 'surname', 'cabinet']) {
                throw new \RuntimeException('Invalid teacher roster header.');
            }
            $rows = [];
            $seen = [];
            while (($row = fgetcsv($stream, 0, ',', '"', '')) !== false) {
                if ($row === [null]) {
                    continue;
                }
                if (count($row) !== 3) {
                    throw new \RuntimeException('Invalid teacher roster row.');
                }
                foreach ($row as $index => $value) {
                    if (!is_string($value) || !mb_check_encoding($value, 'UTF-8') || trim($value) === ''
                        || mb_strlen($value) > ($index === 2 ? 50 : 255) || preg_match('/[\x00-\x1f\x7f]/', $value)) {
                        throw new \RuntimeException('Invalid teacher name or room in the roster.');
                    }
                }
                $key = mb_strtolower($row[0] . "\0" . $row[1], 'UTF-8');
                if (isset($seen[$key])) {
                    throw new \RuntimeException('Duplicate teacher in the CSV roster.');
                }
                $seen[$key] = true;
                $rows[] = $row;
            }
            if ($rows === []) {
                throw new \RuntimeException('The teacher roster is empty.');
            }

            return $rows;
        } finally {
            fclose($stream);
        }
    }

    public static function importOnce(\PDO $pdo, string $file, string $version = self::VERSION): bool
    {
        if ((int) $pdo->query("SELECT GET_LOCK('scheduler-migrations', 30)")->fetchColumn() !== 1) {
            throw new \RuntimeException('Could not obtain migration lock.');
        }
        try {
            $check = $pdo->prepare('SELECT 1 FROM schema_migrations WHERE version = ?');
            $check->execute([$version]);
            if ($check->fetchColumn()) {
                return false;
            }
            // The private roster is needed only for the first import, never for later releases.
            $temporary = null;
            try {
                $csv = getenv('TEACHER_ROSTER_CSV');
                if ($csv !== false && $csv !== '') {
                    $temporary = tempnam(sys_get_temp_dir(), 'scheduler-roster-');
                    if ($temporary === false || file_put_contents($temporary, $csv) === false) {
                        throw new \RuntimeException('Could not prepare the private teacher roster.');
                    }
                    $file = $temporary;
                }
                $rows = self::read($file);
            } finally {
                if ($temporary !== null && $temporary !== false && is_file($temporary)) {
                    unlink($temporary);
                }
            }
            $pdo->beginTransaction();
            try {
                // Serialize with bookings and teacher edits; do not reuse old teacher IDs.
                $pdo->query('SELECT id FROM teacher ORDER BY id FOR UPDATE')->fetchAll();
                $appointments = $pdo->exec('DELETE FROM vecaki');
                $pdo->exec('DELETE FROM teacher_subject');
                $teachers = $pdo->exec('DELETE FROM teacher');
                $insert = $pdo->prepare('INSERT INTO teacher (name, surname, cabinet, class, subject_id, available, start_time, end_time)
                    VALUES (?, ?, ?, NULL, NULL, 1, ?, ?)');
                foreach ($rows as $row) {
                    $insert->execute([...$row, Booking::DEFAULT_START, Booking::DEFAULT_END]);
                }
                // The marker commits with the roster, so retrying a failed release cannot reset it again.
                $pdo->prepare('INSERT INTO schema_migrations (version) VALUES (?)')->execute([$version]);
                $pdo->commit();
                echo sprintf('Teacher roster: replaced %d teachers and %d appointments with %d teachers without subjects.', $teachers, $appointments, count($rows)) . PHP_EOL;

                return true;
            } catch (\Throwable $error) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                throw $error;
            }
        } finally {
            $pdo->query("SELECT RELEASE_LOCK('scheduler-migrations')");
        }
    }
}
