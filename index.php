<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use Atk4\Ui\Button;
use Atk4\Ui\Header;
use Scheduler\App;
use Scheduler\Database;

$app = new App();
Button::addTo($app, ['Vecākiem', 'class' => ['massive red']])->link(['parents']);
Button::addTo($app, ['Skolotājiem', 'class' => ['massive green right floated']])->link(['teachers_access']);
Header::addTo($app, [$app->text('main_page_middle_text'), 'class' => ['horizontal divider']]);
Header::addTo($app, [$app->text('main_page_sub_text'), 'size' => 5, 'aligned' => 'center']);
Database::connect()->exec('INSERT INTO counter (id, counter) VALUES (1, 1) ON DUPLICATE KEY UPDATE counter = counter + 1');
