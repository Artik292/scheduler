<?php

declare(strict_types=1);

require getcwd() . '/vendor/autoload.php';

use Scheduler\Booking;
use Scheduler\Database;

function check(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
    echo 'PASS ' . $message . PHP_EOL;
}

check(PHP_MAJOR_VERSION === 8 && PHP_MINOR_VERSION === 3, 'PHP 8.3 runtime');
$pdo = Database::connect();
check(str_starts_with($pdo->query('SELECT VERSION()')->fetchColumn(), '8.4.'), 'MySQL 8.4 runtime');
$pdo->prepare('INSERT INTO subject (name) VALUES (?)')->execute(['Integration test']);
$subjectId = (int) $pdo->lastInsertId();
$pdo->prepare('INSERT INTO teacher (name, surname, cabinet, subject_id, available) VALUES (?, ?, ?, ?, 1)')->execute(['Integration', 'Teacher', 'TEST', $subjectId]);
$teacherId = (int) $pdo->lastInsertId();
try {
    $data = ['student_name' => 'Test student 😀', 'grade' => '5A', 'parent_name' => '<script>test</script>', 'contact_phone' => '+37100000000'];
    Booking::create($teacherId, '17:00', $data);
    $stmt = $pdo->prepare('SELECT student_name FROM vecaki WHERE teacher_id = ?');
    $stmt->execute([$teacherId]);
    check($stmt->fetchColumn() === $data['student_name'], 'utf8mb4 appointment persistence');
    try {
        Booking::create($teacherId, '17:00', $data);
        throw new RuntimeException('Duplicate appointment accepted');
    } catch (DomainException $expected) {
        check(true, 'duplicate appointment rejected');
    }
    try {
        Booking::create($teacherId, '23:59', $data);
        throw new RuntimeException('Invalid slot accepted');
    } catch (InvalidArgumentException $expected) {
        check(true, 'invalid time rejected');
    }
    $pdo->prepare('UPDATE teacher SET available = 0 WHERE id = ?')->execute([$teacherId]);
    try {
        Booking::create($teacherId, '17:10', $data);
        throw new RuntimeException('Unavailable teacher accepted');
    } catch (InvalidArgumentException $expected) {
        check(true, 'unavailable teacher rejected');
    }
    $db = new \Atk4\Data\Persistence\Sql(Database::parameters());
    $entity = (new \Model\Teacher($db))->load($teacherId);
    check($entity->get('surname') === 'Teacher', 'ATK 6 entity loading');
    check(count($entity->ref('Vecaki')->export()) === 1, 'ATK 6 relationship loading');
    check(count(Booking::slots()) === 12, 'twelve ten-minute appointment slots');
} finally {
    $pdo->prepare('DELETE FROM vecaki WHERE teacher_id = ?')->execute([$teacherId]);
    $pdo->prepare('DELETE FROM teacher WHERE id = ?')->execute([$teacherId]);
    $pdo->prepare('DELETE FROM subject WHERE id = ?')->execute([$subjectId]);
}
