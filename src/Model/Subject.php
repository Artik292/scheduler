<?php

declare(strict_types=1);

namespace Model;

class Subject extends \Atk4\Data\Model
{
    public $table = 'subject';
    public $caption = 'Stunda';

    protected function init(): void
    {
        parent::init();
        $this->addField('name', ['caption' => 'Grupa', 'required' => true]);
        $this->hasMany('Teacher', ['model' => [Teacher::class]]);
        $this->setOrder('name');
    }
}
