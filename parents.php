<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use Atk4\Ui\Button;
use Atk4\Ui\Columns;
use Atk4\Ui\Form;
use Atk4\Ui\Header;
use Atk4\Ui\Js\JsModal;
use Atk4\Ui\Menu;
use Atk4\Ui\Message;
use Atk4\Ui\VirtualPage;
use Scheduler\App;
use Scheduler\Booking;
use Scheduler\Security;

$app = new App();
$app->stickyGet('pr');
$app->stickyGet('t');
$subjectId = filter_var($_GET['pr'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$teacherId = filter_var($_GET['t'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$describeTeacher = static function (Model\Teacher $teacher, bool $withClass = false): string {
    $label = trim($teacher->get('name') . ' ' . $teacher->get('surname'));
    if ($withClass && $teacher->get('class')) {
        $label .= ' (' . $teacher->get('class') . ')';
    }
    $cabinet = trim((string) $teacher->get('cabinet'));

    return $label . ($cabinet !== '' ? ' - ' . $cabinet . 'kab.' : '');
};
Button::addTo($app, ['Atgriezties mājaslapā', 'icon' => 'reply'])->link(['index']);
if (isset($_GET['saved'])) {
    Message::addTo($app, ['Jūsu pieprasījums ir iesniegts!', 'type' => 'success']);
}
Message::addTo($app, ['Lietošanas instrukcija', 'type' => 'info'])->text->addParagraph($app->text('parents_page_instruction_text'));
if ($subjectId || $teacherId) {
    $back = ['parents', 't' => false, 'time' => false, 'saved' => false];
    if (!$teacherId) {
        $back['pr'] = false;
    }
    Button::addTo($app, [$teacherId ? 'Mainīt skolotāju' : 'Mainīt priekšmetu', 'icon' => 'arrow left', 'class' => ['booking-back']])->link($back);
}
$columns = Columns::addTo($app, ['class' => ['booking-flow']]);
$columns->addClass('stackable booking-step-' . ($teacherId ? 'time' : ($subjectId ? 'teacher' : 'subject')));
$c1 = $columns->addColumn();
$c2 = $columns->addColumn();
$c3 = $columns->addColumn();
$c1->addClass('booking-subjects');
$c2->addClass('booking-teachers');
$c3->addClass('booking-times');
Header::addTo($c1, ['1. Priekšmets', 'size' => 3]);
Header::addTo($c2, ['2. Skolotājs', 'size' => 3]);
Header::addTo($c3, ['3. Laiks', 'size' => 3]);

$subjects = new Model\Subject($app->db);
$subjectMenu = Menu::addTo($c1, ['class' => ['vertical fluid booking-options']]);
foreach ($subjects as $subject) {
    $item = $subjectMenu->addItem($subject->get('name'));
    $item->link(['parents', 'pr' => $subject->getId(), 't' => false, 'time' => false, 'saved' => false]);
    if ((int) $subject->getId() === $subjectId) {
        $item->addClass('active');
    }
}
if ($subjectId) {
    $teachers = (new Model\Teacher($app->db))->forSubject($subjectId);
    $teacherMenu = Menu::addTo($c2, ['class' => ['vertical fluid booking-options']]);
    foreach ($teachers as $candidate) {
        $item = $teacherMenu->addItem($describeTeacher($candidate, true));
        $item->link(['parents', 'pr' => $subjectId, 't' => $candidate->getId(), 'time' => false, 'saved' => false]);
        if ((int) $candidate->getId() === $teacherId) {
            $item->addClass('active');
        }
    }
}
if (!$teacherId) {
    return;
}
$teacher = (new Model\Teacher($app->db))->tryLoad($teacherId);
if ($teacher === null) {
    http_response_code(404);
    return;
}
$teacherDescription = $describeTeacher($teacher);
if (!$teacher->get('available')) {
    Header::addTo($c3, [$teacherDescription, 'size' => 4]);
    Message::addTo($c3, ['type' => 'warning'])->text->addParagraph($app->text(Model\Text::TEACHER_UNAVAILABLE_MESSAGE));
    return;
}
$slots = $teacher->getBookingSlots();
$menu = Menu::addTo($c3, ['class' => ['vertical fluid booking-slots']]);
$menu->addHeader('Laiki (' . $teacherDescription . ')');
$booked = array_column($teacher->ref('Vecaki')->export(['time']), 'time');
$page = VirtualPage::addTo($app);
$page->set(static function (VirtualPage $page) use ($app, $teacherId, $slots, $teacherDescription) {
    $time = $app->stickyGet('time');
    if (!is_string($time) || !in_array($time, $slots, true)) {
        Message::addTo($page, ['Nederīgs pieraksta laiks.', 'type' => 'error']);
        return;
    }
    Header::addTo($page, [$teacherDescription, 'size' => 4]);
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
