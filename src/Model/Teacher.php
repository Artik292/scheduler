<?php

declare(strict_types=1);

namespace Model;

class Teacher extends \Atk4\Data\Model
{
    public $table = 'teacher';
    public $caption = 'Skolotājs';

    protected function init(): void
    {
        parent::init();
        $this->addField('name', ['caption' => 'Vārds', 'required' => true]);
        $this->addField('surname', ['caption' => 'Uzvārds', 'required' => true]);
        $this->addField('class', ['caption' => 'Audzināmā klase']);
        $this->addField('cabinet', ['caption' => 'Kabinets', 'required' => true]);
        $this->addField('available', ['caption' => 'Būs', 'type' => 'boolean', 'default' => true]);
        $this->hasOne('subject_id', ['model' => [Subject::class], 'caption' => 'Grupa'])->addTitle();
        $this->hasMany('Vecaki', ['model' => [Vecaki::class]]);
        $this->setOrder('surname');
    }
}
