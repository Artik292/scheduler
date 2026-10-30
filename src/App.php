<?php

declare(strict_types=1);

namespace Scheduler;

use Atk4\Data\Persistence\Sql;
use Atk4\Ui\Js\JsExpression;
use Atk4\Ui\Layout;
use Atk4\Ui\View;

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
        $this->html->template->set('class', 'scheduler-app ' . ($mode === 'admin' ? 'scheduler-admin-page' : 'scheduler-public-page'));
        $this->html->template->set('lang', 'lv');
        $credit = '<span class="site-credit no-print">Izstrāde un uzturēšana: '
            . '<a href="https://www.linkedin.com/in/arthur-konevnikov-3b24a7190/" target="_blank" rel="noopener noreferrer">Arthur Konevnikov</a></span>';
        if ($mode === 'admin') {
            $this->layout->template->dangerouslySetHtml('Footer', $credit);
        } elseif ($mode !== 'print') {
            $footer = View::addTo($this->html, ['element' => 'footer', 'class' => ['site-footer no-print']]);
            $footer->template->dangerouslySetHtml('Content', $credit);
        }
        $this->html->js(true, new JsExpression('$.ajaxSetup({headers: {"X-CSRF-Token": []}})', [$_SESSION['csrf']]));
        $this->html->template->dangerouslyAppendHtml('Head', '<meta name="csrf-token" content="' . $_SESSION['csrf'] . '">');
        $this->html->template->dangerouslyAppendHtml('Head', '<link rel="stylesheet" href="/app.css?v=3">');
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
