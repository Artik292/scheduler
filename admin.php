<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use Atk4\Ui\Button;
use Atk4\Ui\Crud;
use Atk4\Ui\Label;
use Atk4\Ui\Message;
use Atk4\Ui\Js\JsBlock;
use Atk4\Ui\Js\JsReload;
use Atk4\Ui\Js\JsToast;
use Scheduler\App;
use Scheduler\Database;
use Scheduler\Security;
use Scheduler\SubjectOrder;

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
if ($section === 'lessons') {
    Message::addTo($app, ['type' => 'info'])->text->addParagraph('Velciet priekšmetus aiz roktura vai izmantojiet bultiņas, lai mainītu secību. Izmaiņas saglabājas automātiski.');
}
$crud = Crud::addTo($app, $section === 'lessons' ? ['paginator' => false, 'sortable' => false] : []);
$crud->setModel(new $class($app->db));
if ($section === 'lessons') {
    $crud->table->addClass('subject-order-table');
    $changeOrder = static function (\Closure $change) use ($crud): JsBlock {
        try {
            if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
                throw new InvalidArgumentException('Nederīgs pieprasījums.');
            }
            $change();
            $notice = new JsToast(['message' => 'Secība saglabāta.', 'position' => 'bottom center']);
        } catch (InvalidArgumentException | DomainException $error) {
            $notice = new JsToast(['message' => $error->getMessage(), 'class' => 'error', 'position' => 'bottom center']);
        }

        return new JsBlock([$notice, new JsReload($crud->container)]);
    };
    $drag = $crud->table->addColumn(null, [Scheduler\Ui\SubjectDragHandle::class]);
    array_unshift($crud->table->columns, array_pop($crud->table->columns));
    $drag->onReorder(static fn (array $ids) => $changeOrder(static fn () => SubjectOrder::reorder($ids)));
    foreach ([-1 => 'up', 1 => 'down'] as $direction => $icon) {
        $label = $direction === -1 ? 'Pārvietot uz augšu' : 'Pārvietot uz leju';
        $button = $crud->addActionButton(
            ['icon' => 'arrow ' . $icon, 'element' => 'button', 'class' => ['subject-order-arrow']],
            static fn ($js, $id) => $changeOrder(static fn () => SubjectOrder::move((int) $id, $direction))
        );
        $button->setAttr(['type' => 'button', 'title' => $label, 'aria-label' => $label]);
    }
} else {
    $crud->addQuickSearch(match ($section) {
        'teachers' => ['name', 'surname', 'cabinet'],
        'text' => ['code', 'text'],
        default => ['student_name', 'parent_name', 'contact_phone', 'time'],
    });
}
if ($section === '') {
    $count = Database::connect()->query('SELECT counter FROM counter WHERE id = 1')->fetchColumn();
    Label::addTo($app, ['Counter = ' . (int) $count]);
    Button::addTo($app, ['Press to reset counter'])->on('click', static function () use ($app) {
        Database::connect()->exec('UPDATE counter SET counter = 0 WHERE id = 1');

        return $app->jsRedirect(['admin']);
    });
}
Button::addTo($app, ['Iziet', 'class' => ['no-print']])->link(['logout']);
