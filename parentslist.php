<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use Atk4\Ui\Button;
use Atk4\Ui\Grid;
use Scheduler\App;
use Scheduler\Security;

Security::requireRole('teachers');
$id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if (!$id) {
    http_response_code(404);
    exit;
}
$app = new App();
$teacher = (new Model\Teacher($app->db))->tryLoad($id);
if ($teacher === null) {
    http_response_code(404);
    return;
}
$app->stickyGet('id');
Button::addTo($app, ['Skolotāji'])->link(['teachers']);
Grid::addTo($app)->setModel($teacher->ref('Vecaki'), ['time', 'student_name', 'grade', 'parent_name', 'contact_phone']);
Button::addTo($app, ['Izdrukāt', 'icon' => 'print'])->link(['print', 'id' => $id]);
