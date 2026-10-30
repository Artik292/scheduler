<?php

declare(strict_types=1);

namespace Scheduler;

final class SubjectOrder
{
    public const UNSORTED = 4294967295;

    /** @param list<int> $ids */
    public static function reorder(array $ids): void
    {
        if (!array_is_list($ids)) {
            throw new \InvalidArgumentException('Nederīga priekšmetu secība.');
        }
        foreach ($ids as $id) {
            if (!is_int($id) || $id < 1) {
                throw new \InvalidArgumentException('Nederīga priekšmetu secība.');
            }
        }
        if (count(array_unique($ids, SORT_NUMERIC)) !== count($ids)) {
            throw new \InvalidArgumentException('Nederīga priekšmetu secība.');
        }
        self::change(static function (\PDO $pdo, array $current) use ($ids): void {
            $expected = $current;
            $received = $ids;
            sort($expected, SORT_NUMERIC);
            sort($received, SORT_NUMERIC);
            if ($expected !== $received) {
                throw new \DomainException('Priekšmetu saraksts ir mainījies. Lūdzu, atjaunojiet lapu un mēģiniet vēlreiz.');
            }
            self::write($pdo, $ids);
        });
    }

    public static function move(int $id, int $direction): void
    {
        if ($id < 1 || !in_array($direction, [-1, 1], true)) {
            throw new \InvalidArgumentException('Nederīga pārvietošana.');
        }
        self::change(static function (\PDO $pdo, array $ids) use ($id, $direction): void {
            $index = array_search($id, $ids, true);
            if ($index === false) {
                throw new \DomainException('Priekšmetu saraksts ir mainījies. Lūdzu, atjaunojiet lapu un mēģiniet vēlreiz.');
            }
            $target = $index + $direction;
            if ($target < 0 || $target >= count($ids)) {
                return;
            }
            [$ids[$index], $ids[$target]] = [$ids[$target], $ids[$index]];
            self::write($pdo, $ids);
        });
    }

    /** @param \Closure(\PDO, list<int>): void $action */
    private static function change(\Closure $action): void
    {
        $pdo = Database::connect();
        $pdo->beginTransaction();
        try {
            // Use one lock order for drag, arrow controls and concurrent requests.
            $rows = $pdo->query('SELECT id, sort_order FROM subject ORDER BY id FOR UPDATE')->fetchAll(\PDO::FETCH_ASSOC);
            usort($rows, static fn (array $a, array $b): int => [(int) $a['sort_order'], (int) $a['id']] <=> [(int) $b['sort_order'], (int) $b['id']]);
            $action($pdo, array_map(static fn (array $row): int => (int) $row['id'], $rows));
            $pdo->commit();
        } catch (\Throwable $error) {
            $pdo->rollBack();
            throw $error;
        }
    }

    /** @param list<int> $ids */
    private static function write(\PDO $pdo, array $ids): void
    {
        $update = $pdo->prepare('UPDATE subject SET sort_order = ? WHERE id = ?');
        foreach ($ids as $index => $id) {
            $update->execute([$index + 1, $id]);
        }
    }
}
