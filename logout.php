<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $_SESSION = [];
    session_destroy();
    setcookie(session_name(), '', ['expires' => time() - 3600, 'path' => '/', 'secure' => getenv('APP_ENV') !== 'local', 'httponly' => true, 'samesite' => 'Lax']);
    header('Location: index.php', true, 303);
    exit;
}
header('Content-Type: text/html; charset=utf-8');
echo '<!doctype html><html lang="lv"><meta charset="utf-8"><title>Iziet</title><form method="post"><input type="hidden" name="_csrf" value="' . $_SESSION['csrf'] . '"><button type="submit">Iziet</button></form></html>';
