<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';
$app = new \Scheduler\App();
\Atk4\Ui\Button::addTo($app, ['Atpakaļ'])->link(['parents']);
\Atk4\Ui\Message::addTo($app, ['Diemžēl, šis laiks ir jau aizņemts!', 'type' => 'error']);
