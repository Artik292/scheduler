<?php

declare(strict_types=1);

// Run only inside the disposable legacy migration database.
require getcwd() . '/vendor/autoload.php';
$pdo = Scheduler\Database::connect();
$preserved = [];
foreach (['teacher', 'teacher_subject', 'vecaki', 'text'] as $table) {
    $preserved[$table] = $pdo->query('SELECT * FROM ' . $table . ' ORDER BY 1, 2')->fetchAll(PDO::FETCH_ASSOC);
}
$pdo->exec('ALTER TABLE subject DROP INDEX subject_sort_order, DROP COLUMN sort_order');
$pdo->exec("DELETE FROM schema_migrations WHERE version = '008_subject_order'");
$pdo->exec("INSERT INTO subject (name) VALUES ('Zulu migration test'), ('Alpha migration test')");
$run = static function (): void {
    $process = proc_open([PHP_BINARY, 'bin/migrate.php'], [STDIN, STDOUT, STDERR], $pipes);
    if (!is_resource($process) || proc_close($process) !== 0) {
        throw new RuntimeException('Subject order migration failed.');
    }
};
$run();
$run();
$rows = $pdo->query('SELECT id, name, sort_order FROM subject ORDER BY sort_order, id')->fetchAll(PDO::FETCH_ASSOC);
if (array_column($rows, 'name') !== ['Alpha migration test', 'Legacy subject', 'Zulu migration test'] || array_map('intval', array_column($rows, 'sort_order')) !== [1, 2, 3]) {
    throw new RuntimeException('Initial subject migration did not preserve alphabetical order.');
}
echo 'PASS existing subjects keep their alphabetical order when adding manual ordering' . PHP_EOL;
foreach ($rows as $index => $row) {
    $pdo->prepare('UPDATE subject SET sort_order = ? WHERE id = ?')->execute([count($rows) - $index, $row['id']]);
}
$chosen = $pdo->query('SELECT id, sort_order FROM subject ORDER BY sort_order, id')->fetchAll(PDO::FETCH_ASSOC);
$pdo->exec("DELETE FROM schema_migrations WHERE version = '008_subject_order'");
$run();
$run();
if ($pdo->query('SELECT id, sort_order FROM subject ORDER BY sort_order, id')->fetchAll(PDO::FETCH_ASSOC) !== $chosen) {
    throw new RuntimeException('Migration reset manually chosen subject positions.');
}
foreach ($preserved as $table => $rows) {
    if ($pdo->query('SELECT * FROM ' . $table . ' ORDER BY 1, 2')->fetchAll(PDO::FETCH_ASSOC) !== $rows) {
        throw new RuntimeException('Subject migration changed data in ' . $table);
    }
}
echo 'PASS repeated or resumed ordering migration preserves chosen order and all unrelated data' . PHP_EOL;
