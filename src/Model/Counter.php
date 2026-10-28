<?php

declare(strict_types=1);

namespace Model;

class Counter extends \Atk4\Data\Model
{
    public $table = 'counter';

    protected function init(): void
    {
        parent::init();
        $this->addField('counter', ['type' => 'bigint', 'default' => 0]);
    }
}
