<?php

declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';

set_exception_handler(static function (Throwable $error): void {
    error_log((string) $error);
    http_response_code(500);
    echo 'Pakalpojums īslaicīgi nav pieejams.';
});

\Scheduler\Security::boot();
