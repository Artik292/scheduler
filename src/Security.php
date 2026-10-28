<?php

declare(strict_types=1);

namespace Scheduler;

use Symfony\Component\HttpFoundation\Session\Storage\Handler\PdoSessionHandler;

final class Security
{
    public static function boot(): void
    {
        foreach (['ADMIN_PASSWORD', 'TEACHERS_PASSWORD'] as $name) {
            if (strlen(getenv($name) ?: '') < 12) {
                throw new \RuntimeException($name . ' must contain at least 12 characters.');
            }
        }
        date_default_timezone_set('Europe/Riga');
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: DENY');
        header('Referrer-Policy: same-origin');
        header('Cache-Control: no-store');
        ini_set('session.use_strict_mode', '1');
        ini_set('session.gc_maxlifetime', '3600');
        ini_set('session.upload_progress.enabled', '0');
        session_name('scheduler_session');
        session_set_cookie_params([
            'lifetime' => 0, 'path' => '/',
            'secure' => getenv('APP_ENV') !== 'local',
            'httponly' => true, 'samesite' => 'Lax',
        ]);
        session_set_save_handler(new PdoSessionHandler(Database::connect()), true);
        session_start();
        if (isset($_SESSION['last_activity']) && time() - $_SESSION['last_activity'] > 3600) {
            $_SESSION = [];
            session_regenerate_id(true);
        }
        $_SESSION['last_activity'] = time();
        $_SESSION['csrf'] ??= bin2hex(random_bytes(32));
        $isCallback = count(array_filter(array_keys($_GET), static fn ($key) => str_starts_with((string) $key, '__atk_cb'))) > 0;
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET' || $isCallback) {
            $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_POST['_csrf'] ?? '';
            if (!is_string($token) || !hash_equals($_SESSION['csrf'], $token)) {
                http_response_code(403);
                exit('Invalid CSRF token.');
            }
        }
    }

    public static function requireRole(string $role): void
    {
        if (empty($_SESSION['roles'][$role]) && empty($_SESSION['roles']['admin'])) {
            header('Location: ' . ($role === 'admin' ? 'go.php' : 'teachers_access.php'), true, 302);
            exit;
        }
    }

    public static function login(string $role, string $password): bool
    {
        $name = $role === 'admin' ? 'ADMIN_PASSWORD' : 'TEACHERS_PASSWORD';
        if (!hash_equals(getenv($name), $password)) {
            return false;
        }
        session_regenerate_id(true);
        $_SESSION['roles'][$role] = true;
        $key = hash('sha256', 'login-' . $role . ':' . ($_SERVER['REMOTE_ADDR'] ?? 'cli'));
        Database::connect()->prepare('DELETE FROM rate_limits WHERE rate_key = ?')->execute([$key]);

        return true;
    }

    public static function throttle(string $scope, int $limit, int $window): bool
    {
        $key = hash('sha256', $scope . ':' . ($_SERVER['REMOTE_ADDR'] ?? 'cli'));
        $pdo = Database::connect();
        $stmt = $pdo->prepare('INSERT INTO rate_limits (rate_key, attempts, reset_at) VALUES (?, 1, ?) ON DUPLICATE KEY UPDATE attempts = IF(reset_at <= ?, 1, attempts + 1), reset_at = IF(reset_at <= ?, ?, reset_at)');
        $now = time();
        $stmt->execute([$key, $now + $window, $now, $now, $now + $window]);
        $stmt = $pdo->prepare('SELECT attempts FROM rate_limits WHERE rate_key = ?');
        $stmt->execute([$key]);

        return (int) $stmt->fetchColumn() <= $limit;
    }
}
