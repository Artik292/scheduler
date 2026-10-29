<?php

declare(strict_types=1);

require getcwd() . '/vendor/autoload.php';

use Atk4\Data\Persistence\Sql;
use Scheduler\Booking;
use Scheduler\Database;

function noticeCheck(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
    echo 'PASS ' . $message . PHP_EOL;
}

function noticePage(int $subjectId, int $teacherId): string
{
    $base = getenv('TEST_INTERNAL_BASE_URL') ?: 'http://127.0.0.1:8080';
    $body = file_get_contents($base . '/parents.php?pr=' . $subjectId . '&t=' . $teacherId);
    if ($body === false) {
        throw new RuntimeException('Could not load parent booking page.');
    }

    return $body;
}

$pdo = Database::connect();
$db = new Sql(Database::parameters());
$text = (new Model\Text($db))->loadBy('code', Model\Text::TEACHER_UNAVAILABLE_MESSAGE);
$originalText = $text->get('text');
noticeCheck($originalText === 'Pedagoga nebūs. Lūdzam sazināties individuāli e-klasē un sarunāt citu laiku. Paldies!', 'absence notice is seeded with the requested Latvian text');
$subject = (new Model\Subject($db))->createEntity()->save(['name' => 'Absence notice test group']);
$teacher = null;
try {
    $teacher = (new Model\Teacher($db))->createEntity()->save([
        'name' => 'Absent test teacher', 'surname' => 'NoticeTest', 'cabinet' => 'TEST',
        'subject_ids' => [$subject->getId()], 'available' => false,
    ]);
    $teacherId = (int) $teacher->getId();
    $body = noticePage((int) $subject->getId(), $teacherId);
    noticeCheck(str_contains($body, 'Absent test teacher') && str_contains($body, 'NoticeTest'), 'unavailable teacher remains visible in the parent list');
    noticeCheck(str_contains($body, $originalText), 'absence notice replaces the appointment menu');
    noticeCheck(!str_contains($body, 'Laiki (') && !str_contains($body, '>17:00<'), 'unavailable teacher has no booking slots');

    $customText = '<script>window.noticeTest=true</script> Pielāgots paziņojums e-klasē.';
    $text->save(['text' => $customText]);
    $body = noticePage((int) $subject->getId(), $teacherId);
    noticeCheck(str_contains($body, htmlspecialchars($customText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')), 'text changes are displayed immediately as escaped text');
    noticeCheck(!str_contains($body, '<script>window.noticeTest=true</script>'), 'notice content cannot inject executable HTML');

    $teacher->save(['available' => true]);
    $body = noticePage((int) $subject->getId(), $teacherId);
    noticeCheck(str_contains($body, 'Laiki (') && str_contains($body, '17:00') && str_contains($body, '18:50'), 'available teacher shows booking slots');
    noticeCheck(!str_contains($body, 'Pielāgots paziņojums e-klasē.'), 'available teacher does not show the absence notice');
    $data = ['student_name' => 'Notice test student', 'grade' => '5A', 'parent_name' => 'Notice test parent', 'contact_phone' => '+37100000000'];
    Booking::create($teacherId, '17:00', $data);
    $teacher->save(['available' => false]);
    try {
        Booking::create($teacherId, '17:10', $data);
        throw new RuntimeException('Unavailable teacher accepted a booking.');
    } catch (InvalidArgumentException $expected) {
        noticeCheck(true, 'server rejects booking after teacher becomes unavailable');
    }
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM vecaki WHERE teacher_id = ?');
    $stmt->execute([$teacherId]);
    noticeCheck((int) $stmt->fetchColumn() === 1, 'marking a teacher unavailable preserves existing appointments');
} finally {
    $text->reload()->save(['text' => $originalText]);
    if ($teacher !== null) {
        $pdo->prepare('DELETE FROM vecaki WHERE teacher_id = ?')->execute([$teacher->getId()]);
        $pdo->prepare('DELETE FROM teacher WHERE id = ?')->execute([$teacher->getId()]);
    }
    $pdo->prepare('DELETE FROM subject WHERE id = ?')->execute([$subject->getId()]);
}
