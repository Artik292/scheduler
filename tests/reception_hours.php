<?php

declare(strict_types=1);

require getcwd() . '/vendor/autoload.php';

use Atk4\Data\Persistence\Sql;
use Atk4\Data\ValidationException;
use Scheduler\Booking;
use Scheduler\Database;

function hoursCheck(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
    echo 'PASS ' . $message . PHP_EOL;
}

function hoursReject(callable $action, string $exceptionClass, string $message): void
{
    try {
        $action();
    } catch (Throwable $error) {
        if (!$error instanceof $exceptionClass) {
            throw $error;
        }
        hoursCheck(true, $message);

        return;
    }
    throw new RuntimeException($message . ': unexpectedly accepted');
}

$slots = Booking::slots('16:30', '18:00');
hoursCheck(count($slots) === 9 && $slots[0] === '16:30' && end($slots) === '17:50', 'custom hours use complete ten-minute appointments');
hoursCheck(Booking::slots('16:30', '16:40') === ['16:30'], 'a ten-minute window contains one appointment');
hoursCheck(Booking::slots('23:50', '24:00') === ['23:50'], 'an appointment can end at midnight');
$defaultSlots = Booking::slots();
hoursCheck(count($defaultSlots) === 12 && end($defaultSlots) === '18:50', 'default reception hours remain 17:00–19:00');
foreach ([['16:35', '18:00'], ['25:00', '26:00'], ['16.30', '18:00'], ['17:00', '17:00'], ['19:00', '17:00'], ['', '19:00']] as [$start, $end]) {
    hoursReject(static fn () => Booking::slots($start, $end), InvalidArgumentException::class, 'invalid reception window rejected: ' . $start . '–' . $end);
}

$pdo = Database::connect();
$db = new Sql(Database::parameters());
$subject = (new Model\Subject($db))->createEntity()->save(['name' => 'Reception hours test']);
$teacherIds = [];
$data = ['student_name' => 'Test student 😀', 'grade' => '5A', 'parent_name' => 'Test parent', 'contact_phone' => '+37100000000'];
try {
    foreach (['Custom', 'Default'] as $name) {
        $teacher = (new Model\Teacher($db))->createEntity()->save([
            'name' => $name, 'surname' => 'Hours', 'cabinet' => 'TEST', 'subject_ids' => [$subject->getId()],
        ]);
        $teacherIds[] = (int) $teacher->getId();
    }
    [$teacherId, $otherTeacherId] = $teacherIds;
    $legacyTeacher = (new Model\Teacher($db))->createEntity()->save([
        'name' => 'Legacy full name', 'cabinet' => 'TEST', 'subject_ids' => [$subject->getId()],
    ]);
    $teacherIds[] = (int) $legacyTeacher->getId();
    $legacyTeacher->save(['start_time' => '16:30', 'end_time' => '18:00']);
    hoursCheck($legacyTeacher->get('surname') === '' && $legacyTeacher->get('name') === 'Legacy full name', 'legacy full-name teachers can edit reception hours without changing their names');
    $teachers = new Model\Teacher($db);
    $teacher = $teachers->load($teacherId);
    hoursCheck($teacher->get('start_time') === '17:00' && $teacher->get('end_time') === '19:00', 'new teachers receive default reception hours');
    hoursReject(static fn () => $teacher->save(['end_time' => '17:00']), ValidationException::class, 'administrator cannot save an empty reception window');

    Booking::create($teacherId, '17:00', $data);
    $teachers->load($teacherId)->save(['start_time' => '16:30', 'end_time' => '18:00']);
    hoursCheck($teachers->load($teacherId)->getBookingSlots() === $slots, 'teacher-specific reception hours persist');
    Booking::create($teacherId, '16:30', $data);
    hoursCheck(true, 'parent can book 16:30 in a custom reception window');
    hoursReject(static fn () => Booking::create($teacherId, '18:00', $data), InvalidArgumentException::class, 'end time is excluded from booking starts');
    hoursReject(static fn () => Booking::create($teacherId, '18:50', $data), InvalidArgumentException::class, 'stale default-window booking is rejected');
    hoursReject(static fn () => Booking::create($teacherId, '16:35', $data), InvalidArgumentException::class, 'parent cannot bypass ten-minute appointment spacing');
    hoursReject(static fn () => Booking::create($teacherId, '16:30', $data), DomainException::class, 'custom-time duplicate appointment is rejected');
    hoursReject(static fn () => Booking::create($otherTeacherId, '16:30', $data), InvalidArgumentException::class, 'custom hours do not affect other teachers');

    $appointment = (new Model\Vecaki($db))->createEntity()->save($data + ['teacher_id' => $teacherId, 'time' => '16:40']);
    hoursCheck($appointment->get('time') === '16:40', 'administrative booking accepts custom hours');
    hoursReject(
        static fn () => (new Model\Vecaki($db))->createEntity()->save($data + ['teacher_id' => $teacherId, 'time' => '18:10']),
        ValidationException::class,
        'administrative booking cannot bypass teacher hours'
    );
    hoursReject(static fn () => $appointment->save(['time' => '18:10']), ValidationException::class, 'administrative appointment edit validates teacher hours');
    hoursCheck($appointment->reload()->get('time') === '16:40', 'rejected appointment edit preserves its original time');
    hoursReject(static fn () => $teachers->load($teacherId)->save(['start_time' => '17:00']), ValidationException::class, 'start time cannot hide existing appointments');
    hoursReject(static fn () => $teachers->load($teacherId)->save(['end_time' => '16:50']), ValidationException::class, 'end time cannot hide existing appointments');
    $teacher = $teachers->load($teacherId);
    hoursCheck($teacher->get('start_time') === '16:30' && $teacher->get('end_time') === '18:00', 'rejected window changes preserve the current schedule');

    $staleTeacher = $teachers->load($teacherId);
    $teachers->load($teacherId)->save(['end_time' => '17:10']);
    hoursReject(static fn () => $staleTeacher->save(['start_time' => '17:20']), ValidationException::class, 'stale teacher edit validates against the latest saved hours');
    hoursReject(static fn () => Booking::create($teacherId, '17:10', $data), InvalidArgumentException::class, 'booking uses the latest saved reception window');
    $teachers->load($teacherId)->save(['available' => false]);
    hoursReject(static fn () => Booking::create($teacherId, '16:50', $data), InvalidArgumentException::class, 'unavailable teacher still rejects custom-time bookings');
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM vecaki WHERE teacher_id = ?');
    $stmt->execute([$teacherId]);
    hoursCheck((int) $stmt->fetchColumn() === 3, 'all confirmed appointments survive reception-hour changes');
} finally {
    foreach ($teacherIds as $teacherId) {
        $pdo->prepare('DELETE FROM vecaki WHERE teacher_id = ?')->execute([$teacherId]);
        $pdo->prepare('DELETE FROM teacher WHERE id = ?')->execute([$teacherId]);
    }
    $pdo->prepare('DELETE FROM subject WHERE id = ?')->execute([$subject->getId()]);
}
