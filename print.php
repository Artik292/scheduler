<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use Atk4\Ui\Button;
use Atk4\Ui\Header;
use Atk4\Ui\Js\JsExpression;
use Atk4\Ui\Table;
use Scheduler\App;
use Scheduler\Security;

Security::requireRole('teachers');
$id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if (!$id) {
    http_response_code(404);
    exit;
}
$app = new App('print');
$teacher = (new Model\Teacher($app->db))->tryLoad($id);
if ($teacher === null) {
    http_response_code(404);
    return;
}
Header::addTo($app, [$teacher->get('name') . ' ' . $teacher->get('surname') . ' ' . $teacher->get('class')]);
Table::addTo($app)->setModel($teacher->ref('Vecaki'), ['time', 'student_name', 'grade', 'parent_name', 'contact_phone']);
Button::addTo($app, ['Izdrukāt / PDF', 'class' => ['no-print']])->on('click', new JsExpression('window.print()'));
