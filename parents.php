<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use Atk4\Ui\Button;
use Atk4\Ui\Columns;
use Atk4\Ui\Form;
use Atk4\Ui\Js\JsModal;
use Atk4\Ui\Menu;
use Atk4\Ui\Message;
use Atk4\Ui\Table;
use Atk4\Ui\VirtualPage;
use Scheduler\App;
use Scheduler\Booking;
use Scheduler\Security;

$app = new App();
Button::addTo($app, ['Atgriezties mājaslapā', 'icon' => 'reply'])->link(['index']);
if (isset($_GET['saved'])) {
    Message::addTo($app, ['Jūsu pieprasījums ir iesniegts!', 'type' => 'success']);
}
$columns = Columns::addTo($app);
$columns->addClass('stackable');
$c1 = $columns->addColumn();
$c2 = $columns->addColumn();
$c3 = $columns->addColumn();
$c4 = $columns->addColumn();
Message::addTo($c4, ['Lietošanas instrukcija', 'type' => 'info'])->text->addParagraph($app->text('parents_page_instruction_text'));

$subjects = new Model\Subject($app->db);
$table = Table::addTo($c1);
$table->setModel($subjects, ['name']);
$table->addDecorator('name', [Table\Column\Link::class, ['parents'], ['pr' => 'id']]);
$app->stickyGet('pr');
$app->stickyGet('t');
$subjectId = filter_var($_GET['pr'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$teacherId = filter_var($_GET['t'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if ($subjectId) {
    $teachers = (new Model\Teacher($app->db))->forSubject($subjectId);
    $table = Table::addTo($c2);
    $table->setModel($teachers, ['name', 'surname', 'class']);
    $table->addDecorator('name', [Table\Column\Link::class, ['parents', 'pr' => $subjectId], ['t' => 'id']]);
}
if (!$teacherId) {
    return;
}
$teacher = (new Model\Teacher($app->db))->tryLoad($teacherId);
if ($teacher === null) {
    http_response_code(404);
    return;
}
if (!$teacher->get('available')) {
    Message::addTo($c3, ['type' => 'warning'])->text->addParagraph($app->text(Model\Text::TEACHER_UNAVAILABLE_MESSAGE));
    return;
}
$slots = $teacher->getBookingSlots();
$menu = Menu::addTo($c3, ['class' => ['vertical fluid']]);
$menu->addHeader('Laiki (' . $teacher->get('name') . ' ' . $teacher->get('surname') . ')');
$booked = array_column($teacher->ref('Vecaki')->export(['time']), 'time');
$page = VirtualPage::addTo($app);
$page->set(static function (VirtualPage $page) use ($app, $teacherId, $slots) {
    $time = $app->stickyGet('time');
    if (!is_string($time) || !in_array($time, $slots, true)) {
        Message::addTo($page, ['Nederīgs pieraksta laiks.', 'type' => 'error']);
        return;
    }
    $form = Form::addTo($page);
    $form->buttonSave->set('Sūtīt');
    $entity = (new Model\Vecaki($app->db))->createEntity();
    $entity->set('teacher_id', $teacherId);
    $entity->set('time', $time);
    $form->setEntity($entity, ['student_name', 'grade', 'parent_name', 'contact_phone']);
    $form->onSubmit(static function (Form $form) use ($app, $teacherId, $time) {
        if (!Security::throttle('booking-' . session_id(), 100, 3600)) {
            return $form->jsError('contact_phone', 'Pārāk daudz pieprasījumu. Lūdzu, mēģiniet vēlāk.');
        }
        try {
            Booking::create($teacherId, $time, $form->entity->get());
        } catch (\DomainException | \InvalidArgumentException $error) {
            return $form->jsError('student_name', $error->getMessage());
        }

        return $app->jsRedirect(['parents', 'saved' => '1']);
    });
});
foreach ($slots as $time) {
    $item = $menu->addItem($time);
    if (in_array($time, $booked, true)) {
        $item->addClass('disabled');
    } else {
        $item->on('click', new JsModal('Ieraksts', $page, ['time' => $time]));
    }
}
