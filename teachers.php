<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use Atk4\Ui\Button;
use Atk4\Ui\Grid;
use Atk4\Ui\Table;
use Scheduler\App;
use Scheduler\Security;

Security::requireRole('teachers');
$app = new App();
Button::addTo($app, ['Atgriezties mājaslapā', 'icon' => 'home'])->link(['index']);
Button::addTo($app, ['Iziet'])->link(['logout']);
$grid = Grid::addTo($app);
$grid->setModel(new Model\Teacher($app->db), ['name', 'surname', 'class', 'cabinet', 'subject']);
$grid->addQuickSearch(['name', 'surname']);
$grid->addDecorator('name', [Table\Column\Link::class, ['parentslist'], ['id' => 'id']]);
