<?php

declare(strict_types=1);

require getcwd() . '/vendor/autoload.php';

use Atk4\Data\Persistence\Sql;
use Scheduler\Booking;
use Scheduler\Database;
use Scheduler\SubjectOrder;

function orderCheck(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
    echo 'PASS ' . $message . PHP_EOL;
}

function orderedSubjectIds(): array
{
    $db = new Sql(Database::parameters());

    return array_map('intval', array_column((new Model\Subject($db))->export(['id']), 'id'));
}

function orderReject(Closure $action, string $exception, string $message): void
{
    $before = orderedSubjectIds();
    try {
        $action();
    } catch (Throwable $error) {
        if (!$error instanceof $exception) {
            throw $error;
        }
        orderCheck(orderedSubjectIds() === $before, $message);

        return;
    }
    throw new RuntimeException('Unexpectedly accepted: ' . $message);
}

$pdo = Database::connect();
$db = new Sql(Database::parameters());
$originalRows = $pdo->query('SELECT id, sort_order FROM subject ORDER BY sort_order, id')->fetchAll(PDO::FETCH_ASSOC);
$originalIds = array_map('intval', array_column($originalRows, 'id'));
$subjects = [];
$teacher = null;
try {
    foreach (['Zulu order test', 'Alpha order test', 'Middle order test'] as $name) {
        $subjects[] = (new Model\Subject($db))->createEntity()->save(['name' => $name]);
    }
    $ids = array_map(static fn ($subject): int => (int) $subject->getId(), $subjects);
    orderCheck(orderedSubjectIds() === [...$originalIds, ...$ids], 'new subjects append in creation order instead of alphabetical order');
    $teacher = (new Model\Teacher($db))->createEntity()->save([
        'name' => 'Order test teacher', 'surname' => 'Synthetic', 'cabinet' => 'TEST', 'subject_ids' => [$ids[0], $ids[1]],
    ]);
    Booking::create((int) $teacher->getId(), '17:00', ['student_name' => 'Order student', 'grade' => '5A', 'parent_name' => 'Order parent', 'contact_phone' => '12345678']);
    $preserved = static function () use ($pdo): array {
        $result = [];
        foreach (['teacher', 'teacher_subject', 'vecaki', 'text'] as $table) {
            $result[$table] = $pdo->query('SELECT * FROM ' . $table . ' ORDER BY 1, 2')->fetchAll(PDO::FETCH_ASSOC);
        }

        return $result;
    };
    $before = $preserved();
    $desired = [$ids[2], ...$originalIds, $ids[0], $ids[1]];
    SubjectOrder::reorder($desired);
    orderCheck(orderedSubjectIds() === $desired, 'drag order persists and a newly loaded Subject model uses it');
    $base = getenv('TEST_INTERNAL_BASE_URL') ?: 'http://127.0.0.1:8080';
    $body = file_get_contents($base . '/parents.php');
    preg_match_all('~href="/?parents\.php\?pr=(\d+)"~', $body, $matches);
    orderCheck(array_map('intval', $matches[1]) === $desired, 'parent subject links use the exact administrator order');
    SubjectOrder::move($ids[1], -1);
    [$desired[count($desired) - 2], $desired[count($desired) - 1]] = [$desired[count($desired) - 1], $desired[count($desired) - 2]];
    orderCheck(orderedSubjectIds() === $desired, 'up arrow moves a subject one position');
    SubjectOrder::move($ids[1], 1);
    [$desired[count($desired) - 2], $desired[count($desired) - 1]] = [$desired[count($desired) - 1], $desired[count($desired) - 2]];
    orderCheck(orderedSubjectIds() === $desired, 'down arrow moves a subject one position');
    SubjectOrder::move($desired[0], -1);
    SubjectOrder::move($desired[count($desired) - 1], 1);
    orderCheck(orderedSubjectIds() === $desired, 'first and last subjects cannot move past the list boundaries');
    $subjects[2]->save(['name' => 'ZZZ renamed order test']);
    orderCheck(orderedSubjectIds() === $desired, 'renaming a subject keeps its chosen position');
    $subjects[] = (new Model\Subject($db))->createEntity()->save(['name' => '000 appended order test']);
    $allIds = [...$desired, (int) end($subjects)->getId()];
    orderCheck(orderedSubjectIds() === $allIds, 'a new subject appends after a manually arranged list');
    orderReject(static fn () => SubjectOrder::reorder($desired), DomainException::class, 'stale order cannot omit a concurrently added subject');
    $unknown = $allIds;
    $unknown[0] = 2147483647;
    orderReject(static fn () => SubjectOrder::reorder($unknown), DomainException::class, 'unknown IDs cannot change the order');
    orderReject(static fn () => SubjectOrder::reorder([...$allIds, $allIds[0]]), InvalidArgumentException::class, 'duplicate IDs cannot change the order');
    foreach ([['1'], [-1], [[1]], [5 => 1]] as $invalid) {
        orderReject(static fn () => SubjectOrder::reorder($invalid), InvalidArgumentException::class, 'malformed IDs cannot change the order');
    }
    orderReject(static fn () => SubjectOrder::move(2147483647, 1), DomainException::class, 'moving a deleted subject is rejected');
    orderReject(static fn () => SubjectOrder::move($ids[0], 2), InvalidArgumentException::class, 'invalid movement direction is rejected');
    $pdo->exec('ALTER TABLE subject ADD CONSTRAINT test_reject_subject_position CHECK (sort_order <> ' . count($allIds) . ')');
    try {
        orderReject(static fn () => SubjectOrder::reorder(array_reverse($allIds)), PDOException::class, 'a failed position update rolls back every earlier update');
    } finally {
        $pdo->exec('ALTER TABLE subject DROP CHECK test_reject_subject_position');
    }
    orderCheck($preserved() === $before, 'changing subject order preserves teachers, their subject links, bookings and texts');
} finally {
    if ($teacher !== null) {
        $pdo->prepare('DELETE FROM vecaki WHERE teacher_id = ?')->execute([$teacher->getId()]);
        $pdo->prepare('DELETE FROM teacher WHERE id = ?')->execute([$teacher->getId()]);
    }
    foreach ($subjects as $subject) {
        $pdo->prepare('DELETE FROM subject WHERE id = ?')->execute([$subject->getId()]);
    }
    foreach ($originalRows as $row) {
        $pdo->prepare('UPDATE subject SET sort_order = ? WHERE id = ?')->execute([$row['sort_order'], $row['id']]);
    }
}
