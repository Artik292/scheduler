<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use Atk4\Ui\Button;
use Atk4\Ui\Crud;
use Atk4\Ui\Label;
use Scheduler\App;
use Scheduler\Database;
use Scheduler\Security;

Security::requireRole('admin');
$app = new App('admin');
$app->stickyGet('check');
$models = ['lessons' => Model\Subject::class, 'teachers' => Model\Teacher::class, 'text' => Model\Text::class];
$section = $_GET['check'] ?? '';
if (!is_string($section) || ($section !== '' && !isset($models[$section]))) {
    http_response_code(404);
    exit;
}
$class = $models[$section] ?? Model\Vecaki::class;
$crud = Crud::addTo($app);
$crud->setModel(new $class($app->db));
$crud->addQuickSearch(match ($section) {
    'lessons' => ['name'],
    'teachers' => ['name', 'surname', 'cabinet'],
    'text' => ['code', 'text'],
    default => ['student_name', 'parent_name', 'contact_phone', 'time'],
});
if ($section === '') {
    $count = Database::connect()->query('SELECT counter FROM counter WHERE id = 1')->fetchColumn();
    Label::addTo($app, ['Counter = ' . (int) $count]);
    Button::addTo($app, ['Press to reset counter'])->on('click', static function () use ($app) {
        Database::connect()->exec('UPDATE counter SET counter = 0 WHERE id = 1');

        return $app->jsRedirect(['admin']);
    });
}
Button::addTo($app, ['Iziet', 'class' => ['no-print']])->link(['logout']);
