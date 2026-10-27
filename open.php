<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';
\Scheduler\Security::requireRole('teachers');
$id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if (!$id) {
    http_response_code(404);
    exit;
}
header('Location: print.php?id=' . $id, true, 302);
exit;
