<?php

declare(strict_types=1);

namespace Model;

use Atk4\Data\Persistence\Sql;
use Atk4\Data\ValidationException;
use Scheduler\Booking;

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
        $this->addField('time', ['caption' => 'Laiks', 'required' => true, 'enum' => Booking::slots('00:00', '24:00')]);
        $this->hasOne('teacher_id', ['model' => [Teacher::class], 'required' => true])->addTitle();
        $this->setOrder('time');

        $this->onHook(self::HOOK_BEFORE_SAVE, static function (self $entity): void {
            $connection = Sql::assertInstanceOf($entity->getModel()->getPersistence())->getConnection()->getConnection();
            $teacher = $connection->fetchAssociative(
                'SELECT start_time, end_time FROM teacher WHERE id = ? FOR UPDATE',
                [$entity->get('teacher_id')]
            );
            if ($teacher === false) {
                throw new ValidationException(['teacher_id' => 'Skolotājs nav pieejams.'], $entity);
            }
            if (!in_array($entity->get('time'), Booking::slots($teacher['start_time'], $teacher['end_time']), true)) {
                throw new ValidationException(['time' => 'Šis laiks neietilpst skolotāja pieņemšanas laikā.'], $entity);
            }
        });
    }
}
