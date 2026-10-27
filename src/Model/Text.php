<?php

declare(strict_types=1);

namespace Model;

class Text extends \Atk4\Data\Model
{
    public $table = 'text';
    public $caption = 'Text';

    protected function init(): void
    {
        parent::init();
        $this->addField('code', ['required' => true]);
        $this->addField('text', ['required' => true]);
    }
}
