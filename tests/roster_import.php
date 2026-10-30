<?php

declare(strict_types=1);

require getcwd() . '/vendor/autoload.php';

use Scheduler\Database;
use Scheduler\TeacherRoster;

function rosterCheck(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
    echo 'PASS ' . $message . PHP_EOL;
}

$pdo = Database::connect();
$file = getcwd() . '/database/fixtures/teacher_roster.csv';
$snapshot = static function () use ($pdo): array {
    $result = [];
    foreach (['subject', 'text', 'counter', 'teacher', 'teacher_subject', 'vecaki', 'schema_migrations'] as $table) {
        $result[$table] = $pdo->query('SELECT * FROM ' . $table . ' ORDER BY 1, 2')->fetchAll(PDO::FETCH_ASSOC);
    }

    return $result;
};
$before = $snapshot();
try {
    TeacherRoster::importOnce($pdo, '/missing/private-roster.csv');
    throw new LogicException('Missing private roster was accepted.');
} catch (RuntimeException $expected) {
    rosterCheck($snapshot() === $before, 'missing private roster stops before deleting any data');
}
// A database write failure after DELETE must roll the entire replacement back.
$pdo->exec('CREATE UNIQUE INDEX reject_roster_duplicates ON teacher (cabinet)');
try {
    try {
        TeacherRoster::importOnce($pdo, $file);
        throw new RuntimeException('Expected import failure.');
    } catch (PDOException $expected) {
        rosterCheck($snapshot() === $before, 'failed import restores teachers, bookings, subjects, texts and migration marker');
    }
} finally {
    $pdo->exec('DROP INDEX reject_roster_duplicates ON teacher');
}
$invalid = tempnam(sys_get_temp_dir(), 'invalid-roster-');
try {
    foreach (["name,surname,cabinet\n", "name,surname,cabinet\nTest,,101\n", "name,surname,cabinet\nTest,Teacher,101\nTest,Teacher,102\n"] as $csv) {
        file_put_contents($invalid, $csv);
        try {
            TeacherRoster::importOnce($pdo, $invalid);
            throw new LogicException('Invalid roster was accepted.');
        } catch (RuntimeException $expected) {
            rosterCheck($snapshot() === $before, 'invalid CSV leaves all database records intact');
        }
    }
} finally {
    unlink($invalid);
}
putenv('TEACHER_ROSTER_CSV=' . file_get_contents($file));
try {
    rosterCheck(TeacherRoster::importOnce($pdo, '/missing/private-roster.csv'), 'private CSV secret imports once without a bundled roster');
} finally {
    putenv('TEACHER_ROSTER_CSV');
}
$after = $snapshot();
foreach (['subject', 'text', 'counter'] as $table) {
    rosterCheck($after[$table] === $before[$table], $table . ' records preserved byte for byte');
}
rosterCheck(count($after['teacher']) === 103 && $after['vecaki'] === [] && $after['teacher_subject'] === [], 'old teachers and bookings replaced by 103 teachers without subjects');
rosterCheck($pdo->query('SELECT name, surname, cabinet FROM teacher ORDER BY id')->fetchAll(PDO::FETCH_NUM) === TeacherRoster::read($file), 'all teacher names and rooms match the release CSV');
rosterCheck((int) $pdo->query("SELECT COUNT(*) FROM teacher WHERE subject_id IS NOT NULL OR class IS NOT NULL OR available <> 1 OR start_time <> '17:00' OR end_time <> '19:00'")->fetchColumn() === 0, 'import initializes only the requested defaults');
rosterCheck((int) min(array_column($after['teacher'], 'id')) > (int) max(array_column($before['teacher'], 'id')), 'old teacher IDs are never reused for new people');

$db = new Atk4\Data\Persistence\Sql(Database::parameters());
$teacher = (new Model\Teacher($db))->load((int) $after['teacher'][0]['id']);
$teacher->save(['subject_ids' => [(int) $before['subject'][0]['id']], 'start_time' => '16:30']);
Scheduler\Booking::create((int) $teacher->getId(), '16:30', ['student_name' => 'New booking', 'grade' => '5A', 'parent_name' => 'Parent', 'contact_phone' => '12345678']);
$edited = $snapshot();
rosterCheck(!TeacherRoster::importOnce($pdo, $file), 'repeated roster import is skipped');
rosterCheck(!TeacherRoster::importOnce($pdo, '/missing/private-roster.csv'), 'an applied roster migration does not need its private source again');
for ($i = 0; $i < 2; ++$i) {
    $process = proc_open([PHP_BINARY, 'bin/migrate.php'], [STDIN, STDOUT, STDERR], $pipes);
    rosterCheck(is_resource($process) && proc_close($process) === 0, 'full migration rerun succeeds');
}
rosterCheck($snapshot() === $edited, 'release retries preserve assigned subjects, custom hours, new bookings and all other data');
