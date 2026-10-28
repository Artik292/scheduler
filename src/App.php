<?php

declare(strict_types=1);

namespace Scheduler;

use Atk4\Data\Persistence\Sql;
use Atk4\Ui\Js\JsExpression;
use Atk4\Ui\Layout;

class App extends \Atk4\Ui\App
{
    public function __construct(string $mode = 'public')
    {
        parent::__construct(['title' => 'Vecāku diena']);
        $this->db = new Sql(Database::parameters());
        $this->cdn = array_map(static fn ($url) => preg_replace('~^.*?/public(?=/|$)~', '/atk', $url), $this->cdn);
        if ($mode === 'admin') {
            $this->initLayout([Layout\Admin::class]);
            $this->layout->menuLeft->addItem(['Galvenā lapa', 'icon' => 'home'], ['index']);
            $this->layout->menuLeft->addItem(['Priekšmeti', 'icon' => 'book'], ['admin', 'check' => 'lessons']);
            $this->layout->menuLeft->addItem(['Skolotāji', 'icon' => 'users'], ['admin', 'check' => 'teachers']);
            $this->layout->menuLeft->addItem(['Ieraksti', 'icon' => 'list'], ['admin']);
            $this->layout->menuLeft->addItem(['Virsraksti', 'icon' => 'pencil'], ['admin', 'check' => 'text']);
        } else {
            $this->initLayout([Layout\Centered::class, 'image' => '/logo.png']);
            if ($mode === 'print') {
                $this->layout->template->tryDel('Header');
            }
        }
        $this->html->js(true, new JsExpression('$.ajaxSetup({headers: {"X-CSRF-Token": []}})', [$_SESSION['csrf']]));
        $this->html->template->dangerouslyAppendHtml('Head', '<meta name="csrf-token" content="' . $_SESSION['csrf'] . '">');
        $this->addStyle('@media print { .no-print, .atk-admin-top-menu { display: none !important; } }');
    }

    public function renderExceptionHtml(\Throwable $exception): string
    {
        error_log((string) $exception);
        if (getenv('APP_ENV') === 'local') {
            return parent::renderExceptionHtml($exception);
        }

        return '<p>Pakalpojums īslaicīgi nav pieejams.</p>';
    }

    public function text(string $code): string
    {
        $entity = (new \Model\Text($this->db))->tryLoadBy('code', $code);

        return $entity === null ? '' : $entity->get('text');
    }
}
