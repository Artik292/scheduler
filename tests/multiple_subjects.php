<?php

declare(strict_types=1);

require getcwd() . '/vendor/autoload.php';

use Atk4\Data\Persistence\Sql;
use Atk4\Data\ValidationException;
use Scheduler\Booking;
use Scheduler\Database;

function subjectsCheck(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
    echo 'PASS ' . $message . PHP_EOL;
}

function subjectsPage(int $subjectId, ?int $teacherId = null): string
{
    $base = getenv('TEST_INTERNAL_BASE_URL') ?: 'http://127.0.0.1:8080';
    $body = file_get_contents($base . '/parents.php?pr=' . $subjectId . ($teacherId === null ? '' : '&t=' . $teacherId));
    if ($body === false || str_contains($body, 'Critical Error') || str_contains($body, 'Stack Trace')) {
        throw new RuntimeException('Could not render subject page.');
    }

    return $body;
}

$pdo = Database::connect();
$db = new Sql(Database::parameters());
$subjects = [];
$teacher = null;
try {
    foreach (['Multi subject A', 'Multi subject B', 'Multi subject C'] as $name) {
        $subjects[] = (new Model\Subject($db))->createEntity()->save(['name' => $name]);
    }
    $ids = array_map(static fn ($subject) => (int) $subject->getId(), $subjects);
    $teacher = (new Model\Teacher($db))->createEntity()->save([
        'name' => 'MultiSubjectUniqueTeacher', 'surname' => 'Test', 'cabinet' => 'TEST',
        'subject_ids' => [$ids[0], $ids[1], $ids[0]], 'start_time' => '16:30', 'end_time' => '18:00',
    ]);
    $teacherId = (int) $teacher->getId();
    subjectsCheck(count($teacher->reload()->get('subject_ids')) === 2, 'one teacher has two unique subject links');
    subjectsCheck(str_contains($teacher->get('subject'), 'Multi subject A') && str_contains($teacher->get('subject'), 'Multi subject B'), 'teacher grid displays both subjects');
    foreach (array_slice($ids, 0, 2) as $subjectId) {
        subjectsCheck(str_contains(subjectsPage($subjectId), 'MultiSubjectUniqueTeacher'), 'shared teacher appears in subject ' . $subjectId);
    }
    subjectsCheck(!str_contains(subjectsPage($ids[2]), 'MultiSubjectUniqueTeacher'), 'teacher is not listed for an unrelated subject');
    $unsafeName = '<script>window.teacherNameTest=true</script>';
    $teacher->save(['name' => $unsafeName]);
    $page = subjectsPage($ids[0]);
    subjectsCheck(str_contains($page, htmlspecialchars($unsafeName, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')) && !str_contains($page, $unsafeName), 'teacher list escapes editable names');
    $teacher->save(['name' => 'MultiSubjectUniqueTeacher']);
    $subjects[2]->save(['name' => '<script>window.subjectNameTest=true</script>']);
    $page = subjectsPage($ids[0]);
    subjectsCheck(str_contains($page, '&lt;script&gt;window.subjectNameTest=true&lt;/script&gt;') && !str_contains($page, '<script>window.subjectNameTest=true</script>'), 'subject list escapes editable names');
    foreach (array_slice($ids, 0, 2) as $subjectId) {
        $page = subjectsPage($subjectId, $teacherId);
        subjectsCheck(str_contains($page, '>16:30<') && str_contains($page, '>17:50<') && !str_contains($page, '>18:00<'), 'both subjects show the same custom reception window');
    }
    $data = ['student_name' => 'Shared schedule student', 'grade' => '5A', 'parent_name' => 'Shared schedule parent', 'contact_phone' => '+37100000000'];
    Booking::create($teacherId, '16:30', $data);
    foreach (array_slice($ids, 0, 2) as $subjectId) {
        subjectsCheck((bool) preg_match('/class="[^"]*\bdisabled\b[^"]*"[^>]*>16:30</', subjectsPage($subjectId, $teacherId)), 'a booked time is unavailable under either subject');
    }
    try {
        Booking::create($teacherId, '16:30', $data);
        throw new RuntimeException('Shared time was double-booked.');
    } catch (DomainException $expected) {
        subjectsCheck(true, 'shared teacher cannot be double-booked through another subject');
    }
    // Only changing the virtual field must still save the link table.
    $teacher->reload()->save(['subject_ids' => [(string) $ids[1]]]);
    subjectsCheck($teacher->reload()->get('subject_ids') === [$ids[1]], 'subject-only administrative edits persist');
    subjectsCheck(!str_contains(subjectsPage($ids[0]), 'MultiSubjectUniqueTeacher'), 'removing a subject updates the parent list');
    try {
        $teacher->save(['subject_ids' => [$ids[1], 2147483647]]);
        throw new RuntimeException('Nonexistent subject was accepted.');
    } catch (ValidationException $expected) {
        subjectsCheck($teacher->reload()->get('subject_ids') === [$ids[1]], 'invalid subject edit preserves the previous links');
    }
    foreach ([[['bad']], ['1 OR 1=1']] as $invalid) {
        try {
            $teacher->save(['subject_ids' => $invalid]);
            throw new RuntimeException('Malformed subject IDs were accepted.');
        } catch (ValidationException $expected) {
            $teacher->reload();
        }
    }
    $teacher->save(['subject_ids' => ['']]);
    subjectsCheck($teacher->reload()->get('subject_ids') === [], 'clearing the multi-select leaves a teacher without subjects');
    subjectsCheck(count($teacher->ref('Vecaki')->export()) === 1, 'subject edits preserve the teacher and existing appointments');
    $teacher->save(['subject_ids' => $ids]);
    $subjects[0]->delete();
    $subjects[0] = null;
    subjectsCheck(count($teacher->reload()->get('subject_ids')) === 2, 'deleting one subject preserves the teacher and other subject links');
    $teacher->save(['available' => false]);
    subjectsCheck(str_contains(subjectsPage($ids[1]), 'MultiSubjectUniqueTeacher'), 'absent multi-subject teacher stays listed');
    subjectsCheck(str_contains(subjectsPage($ids[1], $teacherId), Model\Text::DEFAULT_TEACHER_UNAVAILABLE_MESSAGE), 'absent multi-subject teacher shows the editable notice');
} finally {
    if ($teacher !== null) {
        $pdo->prepare('DELETE FROM vecaki WHERE teacher_id = ?')->execute([$teacher->getId()]);
        $pdo->prepare('DELETE FROM teacher WHERE id = ?')->execute([$teacher->getId()]);
    }
    foreach ($subjects as $subject) {
        if ($subject !== null) {
            $pdo->prepare('DELETE FROM subject WHERE id = ?')->execute([$subject->getId()]);
        }
    }
}
