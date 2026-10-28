<?php

declare(strict_types=1);

namespace Scheduler;

final class Booking
{
    public const DEFAULT_START = '17:00';
    public const DEFAULT_END = '19:00';
    public const SLOT_MINUTES = 10;

    public static function slots(string $start = self::DEFAULT_START, string $end = self::DEFAULT_END): array
    {
        $pattern = '/^(?:[01][0-9]|2[0-3]):[0-5]0$/D';
        if (!preg_match($pattern, $start) || (!preg_match($pattern, $end) && $end !== '24:00')) {
            throw new \InvalidArgumentException('Norādiet laiku HH:MM formātā ar 10 minūšu soli.');
        }
        $toMinutes = static fn (string $time): int => (int) substr($time, 0, 2) * 60 + (int) substr($time, 3, 2);
        $from = $toMinutes($start);
        $to = $toMinutes($end);
        if ($to <= $from) {
            throw new \InvalidArgumentException('Pieņemšanas beigām jābūt vēlāk par sākumu tajā pašā dienā.');
        }

        return array_map(
            static fn (int $minutes): string => sprintf('%02d:%02d', intdiv($minutes, 60), $minutes % 60),
            range($from, $to - self::SLOT_MINUTES, self::SLOT_MINUTES)
        );
    }

    public static function create(int $teacherId, string $time, array $data): void
    {
        foreach (['student_name', 'grade', 'parent_name', 'contact_phone'] as $field) {
            if (!is_string($data[$field] ?? null) || trim($data[$field]) === '' || mb_strlen($data[$field]) > 255) {
                throw new \InvalidArgumentException('Lūdzu, pārbaudiet ievadītos datus.');
            }
        }
        $pdo = Database::connect();
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare('SELECT available, start_time, end_time FROM teacher WHERE id = ? FOR UPDATE');
            $stmt->execute([$teacherId]);
            $teacher = $stmt->fetch(\PDO::FETCH_ASSOC);
            if ($teacher === false || !(bool) $teacher['available']) {
                throw new \InvalidArgumentException('Skolotājs nav pieejams.');
            }
            if (!in_array($time, self::slots($teacher['start_time'], $teacher['end_time']), true)) {
                throw new \InvalidArgumentException('Šis laiks neietilpst skolotāja pieņemšanas laikā.');
            }
            $stmt = $pdo->prepare('INSERT INTO vecaki (teacher_id, time, student_name, grade, parent_name, contact_phone) VALUES (?, ?, ?, ?, ?, ?)');
            $stmt->execute([$teacherId, $time, ...array_map(static fn ($field) => trim($data[$field]), ['student_name', 'grade', 'parent_name', 'contact_phone'])]);
            $pdo->commit();
        } catch (\Throwable $error) {
            $pdo->rollBack();
            if ($error instanceof \PDOException && ($error->errorInfo[1] ?? null) === 1062) {
                throw new \DomainException('Diemžēl, šis laiks ir jau aizņemts!', 0, $error);
            }
            throw $error;
        }
    }
}
