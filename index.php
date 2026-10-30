<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use Atk4\Ui\Button;
use Atk4\Ui\Header;
use Atk4\Ui\View;
use Scheduler\App;
use Scheduler\Database;

$app = new App();
$actions = View::addTo($app, ['class' => ['home-actions']]);
Button::addTo($actions, ['Vecākiem', 'class' => ['large primary']])->link(['parents']);
Button::addTo($actions, ['Skolotājiem', 'class' => ['large']])->link(['teachers_access']);
Header::addTo($app, [$app->text('main_page_middle_text'), 'aligned' => 'center']);
Header::addTo($app, [$app->text('main_page_sub_text'), 'size' => 5, 'aligned' => 'center']);
Database::connect()->exec('INSERT INTO counter (id, counter) VALUES (1, 1) ON DUPLICATE KEY UPDATE counter = counter + 1');
