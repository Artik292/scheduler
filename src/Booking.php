<?php

declare(strict_types=1);

namespace Scheduler;

final class Booking
{
    public static function slots(): array
    {
        return array_map(static fn (int $minutes) => sprintf('%02d:%02d', intdiv($minutes, 60), $minutes % 60), range(17 * 60, 18 * 60 + 50, 10));
    }

    public static function create(int $teacherId, string $time, array $data): void
    {
        if (!in_array($time, self::slots(), true)) {
            throw new \InvalidArgumentException('Nederīgs pieraksta laiks.');
        }
        foreach (['student_name', 'grade', 'parent_name', 'contact_phone'] as $field) {
            if (!is_string($data[$field] ?? null) || trim($data[$field]) === '' || mb_strlen($data[$field]) > 255) {
                throw new \InvalidArgumentException('Lūdzu, pārbaudiet ievadītos datus.');
            }
        }
        $pdo = Database::connect();
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare('SELECT available FROM teacher WHERE id = ? FOR UPDATE');
            $stmt->execute([$teacherId]);
            if (!(bool) $stmt->fetchColumn()) {
                throw new \InvalidArgumentException('Skolotājs nav pieejams.');
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
