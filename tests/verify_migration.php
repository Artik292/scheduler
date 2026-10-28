<?php

declare(strict_types=1);

require getcwd() . '/vendor/autoload.php';
$pdo = \Scheduler\Database::connect();
foreach ([
    'SELECT counter FROM counter WHERE id = 1' => '42',
    'SELECT COUNT(*) FROM vecaki' => '1',
    "SELECT text FROM text WHERE code = 'main_page_middle_text'" => 'Legacy text preserved',
    "SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'teacher' AND column_name = 'class'" => '1',
    'SELECT start_time FROM teacher WHERE id = 1' => getenv('EXPECTED_TEACHER_START') ?: '17:00',
    'SELECT end_time FROM teacher WHERE id = 1' => '19:00',
    "SELECT text FROM text WHERE code = 'teacher_unavailable_message'" => getenv('EXPECTED_UNAVAILABLE_MESSAGE') ?: Model\Text::DEFAULT_TEACHER_UNAVAILABLE_MESSAGE,
    "SELECT COUNT(*) FROM text WHERE code = 'teacher_unavailable_message'" => '1',
    'SELECT COUNT(*) FROM schema_migrations' => '5',
] as $sql => $expected) {
    if ((string) $pdo->query($sql)->fetchColumn() !== $expected) {
        throw new RuntimeException('Legacy migration assertion failed: ' . $sql);
    }
}
echo 'PASS legacy data preserved, missing columns created and migrations repeatable' . PHP_EOL;
