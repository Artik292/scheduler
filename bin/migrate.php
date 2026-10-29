<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use Scheduler\Database;

$pdo = Database::connect();
if ((int) $pdo->query("SELECT GET_LOCK('scheduler-migrations', 30)")->fetchColumn() !== 1) {
    throw new RuntimeException('Could not obtain migration lock.');
}
try {
    $pdo->exec('CREATE TABLE IF NOT EXISTS schema_migrations (version VARCHAR(100) NOT NULL PRIMARY KEY, applied_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP)');
    $migrations = [
        '001_base_schema' => static function (PDO $pdo): void {
            $pdo->exec(file_get_contents(dirname(__DIR__) . '/database/schema.sql'));
        },
        '002_legacy_compatibility_and_slot_uniqueness' => static function (PDO $pdo): void {
            $columns = $pdo->query('SHOW COLUMNS FROM teacher')->fetchAll(PDO::FETCH_COLUMN);
            foreach (['surname' => 'VARCHAR(255) NOT NULL DEFAULT \'\'', 'class' => 'VARCHAR(150) DEFAULT NULL'] as $name => $type) {
                if (!in_array($name, $columns, true)) {
                    $pdo->exec('ALTER TABLE teacher ADD COLUMN `' . $name . '` ' . $type);
                }
            }
            $duplicates = $pdo->query('SELECT COUNT(*) FROM (SELECT teacher_id, time FROM vecaki GROUP BY teacher_id, time HAVING COUNT(*) > 1) AS duplicates')->fetchColumn();
            $invalid = $pdo->query("SELECT COUNT(*) FROM vecaki WHERE time IS NULL OR time NOT REGEXP '^([01][0-9]|2[0-3]):[0-5]0$'")->fetchColumn();
            if ((int) $duplicates > 0 || (int) $invalid > 0) {
                throw new RuntimeException('Existing appointments contain duplicate or invalid slots. Resolve them manually and rerun; no appointments were deleted.');
            }
            $pdo->exec('ALTER TABLE vecaki MODIFY time VARCHAR(5) NOT NULL');
            $indexes = $pdo->query('SHOW INDEX FROM vecaki')->fetchAll(PDO::FETCH_ASSOC);
            if (!in_array('unique_teacher_time', array_column($indexes, 'Key_name'), true)) {
                $pdo->exec('ALTER TABLE vecaki ADD CONSTRAINT unique_teacher_time UNIQUE (teacher_id, time)');
            }
            $indexes = $pdo->query('SHOW INDEX FROM counter')->fetchAll(PDO::FETCH_ASSOC);
            if (!in_array('PRIMARY', array_column($indexes, 'Key_name'), true)) {
                $pdo->exec('ALTER TABLE counter ADD PRIMARY KEY (id)');
            }
            $pdo->exec('UPDATE counter SET counter = 0 WHERE counter IS NULL');
            $pdo->exec('ALTER TABLE counter MODIFY counter BIGINT NOT NULL DEFAULT 0');
            $pdo->exec('ALTER TABLE text MODIFY text TEXT NOT NULL');
            foreach (['counter', 'subject', 'teacher', 'text', 'vecaki'] as $table) {
                $pdo->exec('ALTER TABLE `' . $table . '` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
            }
        },
        '003_initial_texts' => static function (PDO $pdo): void {
            $stmt = $pdo->prepare('INSERT INTO text (code, text) SELECT ?, ? WHERE NOT EXISTS (SELECT 1 FROM text WHERE code = ?)');
            foreach ([
                'main_page_middle_text' => 'Laipni lūdzam vecāku dienā!',
                'main_page_sub_text' => 'Izvēlieties skolotāju un pieejamo laiku.',
                'parents_page_instruction_text' => 'Izvēlieties grupu, skolotāju un laiku, pēc tam aizpildiet pieteikumu.',
            ] as $code => $text) {
                $stmt->execute([$code, $text, $code]);
            }
            $pdo->exec('INSERT IGNORE INTO counter (id, counter) VALUES (1, 0)');
        },
        '004_teacher_reception_hours' => static function (PDO $pdo): void {
            $columns = $pdo->query('SHOW COLUMNS FROM teacher')->fetchAll(PDO::FETCH_COLUMN);
            foreach (['start_time' => '17:00', 'end_time' => '19:00'] as $name => $default) {
                if (!in_array($name, $columns, true)) {
                    $pdo->exec('ALTER TABLE teacher ADD COLUMN `' . $name . '` VARCHAR(5) NOT NULL DEFAULT ' . $pdo->quote($default));
                }
            }
        },
        '005_teacher_unavailable_message' => static function (PDO $pdo): void {
            $stmt = $pdo->prepare('INSERT INTO text (code, text) SELECT ?, ? WHERE NOT EXISTS (SELECT 1 FROM text WHERE code = ?)');
            $stmt->execute([
                Model\Text::TEACHER_UNAVAILABLE_MESSAGE,
                Model\Text::DEFAULT_TEACHER_UNAVAILABLE_MESSAGE,
                Model\Text::TEACHER_UNAVAILABLE_MESSAGE,
            ]);
        },
        '006_teacher_multiple_subjects' => static function (PDO $pdo): void {
            $pdo->exec('ALTER TABLE teacher MODIFY subject_id INT UNSIGNED DEFAULT NULL');
            $pdo->exec('CREATE TABLE IF NOT EXISTS teacher_subject (
                teacher_id INT UNSIGNED NOT NULL,
                subject_id INT UNSIGNED NOT NULL,
                PRIMARY KEY (teacher_id, subject_id),
                INDEX teacher_subject_subject (subject_id, teacher_id),
                CONSTRAINT fk_teacher_subject_teacher FOREIGN KEY (teacher_id) REFERENCES teacher(id) ON DELETE CASCADE,
                CONSTRAINT fk_teacher_subject_subject FOREIGN KEY (subject_id) REFERENCES subject(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
            $pdo->exec('INSERT IGNORE INTO teacher_subject (teacher_id, subject_id)
                SELECT t.id, t.subject_id FROM teacher t JOIN subject s ON s.id = t.subject_id');
        },
        '007_teacher_roster_2026' => static function (PDO $pdo): void {
            Scheduler\TeacherRoster::importOnce($pdo, dirname(__DIR__) . '/database/imports/pedagogu_saraksts_2026.csv');
        },
    ];
    $through = null;
    foreach (array_slice($argv, 1) as $argument) {
        if (str_starts_with($argument, '--through=')) {
            $through = substr($argument, strlen('--through='));
        } elseif ($argument !== '--demo') {
            throw new InvalidArgumentException('Unknown migration option: ' . $argument);
        }
    }
    if ($through !== null && !array_key_exists($through, $migrations)) {
        throw new InvalidArgumentException('Unknown target migration.');
    }
    foreach ($migrations as $version => $migration) {
        $stmt = $pdo->prepare('SELECT 1 FROM schema_migrations WHERE version = ?');
        $stmt->execute([$version]);
        if (!$stmt->fetchColumn()) {
            $migration($pdo);
            $pdo->prepare('INSERT IGNORE INTO schema_migrations (version) VALUES (?)')->execute([$version]);
            echo 'Applied ' . $version . PHP_EOL;
        }
        if ($version === $through) {
            break;
        }
    }
    if (in_array('--demo', $argv, true)) {
        if (getenv('APP_ENV') !== 'local') {
            throw new RuntimeException('Demo data can only be created with APP_ENV=local.');
        }
        $db = new Atk4\Data\Persistence\Sql(Database::parameters());
        $subjects = new Model\Subject($db);
        $subject = $subjects->tryLoadBy('name', 'Demonstrācijas grupa')
            ?? $subjects->createEntity()->save(['name' => 'Demonstrācijas grupa']);
        $teachers = new Model\Teacher($db);
        if ($teachers->tryLoadBy('name', 'Demo') === null) {
            $teachers->createEntity()->save([
                'name' => 'Demo', 'surname' => 'Skolotājs', 'class' => '5A', 'cabinet' => '101',
                'subject_ids' => [$subject->getId()],
            ]);
        }
        echo 'Demo data ready.' . PHP_EOL;
    }
    echo 'Database is up to date.' . PHP_EOL;
} finally {
    $pdo->query("SELECT RELEASE_LOCK('scheduler-migrations')");
}
