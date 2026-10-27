<?php

declare(strict_types=1);

namespace Model;

class Vecaki extends \Atk4\Data\Model
{
    public $table = 'vecaki';
    public $caption = 'Vecāki';
    public ?string $titleField = 'parent_name';

    protected function init(): void
    {
        parent::init();
        foreach ([
            'student_name' => 'Skolnieka vārds un uzvārds',
            'grade' => 'Klase',
            'parent_name' => 'Vecāka vārds un uzvārds',
            'contact_phone' => 'Kontaktnumurs',
        ] as $name => $caption) {
            $this->addField($name, ['caption' => $caption, 'required' => true]);
        }
        $this->addField('time', ['caption' => 'Laiks', 'required' => true, 'enum' => \Scheduler\Booking::slots()]);
        $this->hasOne('teacher_id', ['model' => [Teacher::class], 'required' => true])->addTitle();
        $this->setOrder('time');
    }
}
