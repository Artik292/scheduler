<?php

declare(strict_types=1);

namespace Scheduler;

final class Database
{
    public static function parameters(): array
    {
        $url = getenv('DATABASE_URL');
        if ($url !== false && $url !== '') {
            $parts = parse_url($url);
            if ($parts === false || ($parts['scheme'] ?? '') !== 'mysql') {
                throw new \RuntimeException('DATABASE_URL must be a mysql URL.');
            }
            $params = [
                'driver' => 'pdo_mysql',
                'host' => $parts['host'] ?? '',
                'port' => $parts['port'] ?? 3306,
                'dbname' => rawurldecode(ltrim($parts['path'] ?? '', '/')),
                'user' => rawurldecode($parts['user'] ?? ''),
                'password' => rawurldecode($parts['pass'] ?? ''),
                'charset' => 'utf8mb4',
            ];
        } else {
            $params = [
                'driver' => 'pdo_mysql',
                'host' => getenv('DB_HOST') ?: '',
                'port' => (int) (getenv('DB_PORT') ?: 3306),
                'dbname' => getenv('DB_NAME') ?: '',
                'user' => getenv('DB_USER') ?: '',
                'password' => getenv('DB_PASSWORD') ?: '',
                'charset' => 'utf8mb4',
            ];
        }
        foreach (['host', 'dbname', 'user', 'password'] as $key) {
            if ($params[$key] === '') {
                throw new \RuntimeException('Missing database configuration: ' . $key);
            }
        }

        return $params;
    }

    public static function connect(): \PDO
    {
        $p = self::parameters();
        foreach (['host', 'dbname'] as $key) {
            if (str_contains($p[$key], ';')) {
                throw new \RuntimeException('Invalid database configuration.');
            }
        }

        return new \PDO(
            sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $p['host'], $p['port'], $p['dbname']),
            $p['user'], $p['password'],
            [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION, \PDO::ATTR_EMULATE_PREPARES => false]
        );
    }
}
