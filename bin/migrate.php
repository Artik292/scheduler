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
            $invalid = $pdo->query("SELECT COUNT(*) FROM vecaki WHERE time IS NULL OR time NOT REGEXP '^1[78]:[0-5]0$'")->fetchColumn();
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
    ];
    foreach ($migrations as $version => $migration) {
        $stmt = $pdo->prepare('SELECT 1 FROM schema_migrations WHERE version = ?');
        $stmt->execute([$version]);
        if ($stmt->fetchColumn()) {
            continue;
        }
        $migration($pdo);
        $pdo->prepare('INSERT INTO schema_migrations (version) VALUES (?)')->execute([$version]);
        echo 'Applied ' . $version . PHP_EOL;
    }
    if (in_array('--demo', $argv, true)) {
        if (getenv('APP_ENV') !== 'local') {
            throw new RuntimeException('Demo data can only be created with APP_ENV=local.');
        }
        $pdo->exec("INSERT IGNORE INTO subject (id, name) VALUES (1, 'Demonstrācijas grupa')");
        $pdo->exec("INSERT IGNORE INTO teacher (id, name, surname, class, cabinet, subject_id, available) VALUES (1, 'Demo', 'Skolotājs', '5A', '101', 1, 1)");
        echo 'Demo data ready.' . PHP_EOL;
    }
    echo 'Database is up to date.' . PHP_EOL;
} finally {
    $pdo->query("SELECT RELEASE_LOCK('scheduler-migrations')");
}
