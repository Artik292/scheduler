<?php

declare(strict_types=1);

namespace Model;

use Atk4\Data\Persistence\Sql;
use Atk4\Data\ValidationException;
use Scheduler\Booking;

class Teacher extends \Atk4\Data\Model
{
    public $table = 'teacher';
    public $caption = 'Skolotājs';

    protected function init(): void
    {
        parent::init();
        $this->addField('name', ['caption' => 'Vārds', 'required' => true]);
        $this->addField('surname', ['caption' => 'Uzvārds', 'default' => '']);
        $this->addField('class', ['caption' => 'Audzināmā klase']);
        $this->addField('cabinet', ['caption' => 'Kabinets', 'required' => true]);
        $this->addField('available', ['caption' => 'Būs', 'type' => 'boolean', 'default' => true]);
        $times = Booking::slots('00:00', '24:00');
        $this->addField('start_time', [
            'caption' => 'Pieņemšana no', 'required' => true, 'default' => Booking::DEFAULT_START,
            'enum' => $times, 'ui' => ['form' => ['class' => ['search']]],
        ]);
        $this->addField('end_time', [
            'caption' => 'Pieņemšana līdz', 'required' => true, 'default' => Booking::DEFAULT_END,
            'enum' => [...array_slice($times, 1), '24:00'],
            'ui' => ['form' => ['class' => ['search']], 'hint' => 'Pieņemšana notiek ik pēc 10 minūtēm.'],
        ]);
        $this->hasOne('subject_id', ['model' => [Subject::class], 'caption' => 'Grupa'])->addTitle();
        $this->hasMany('Vecaki', ['model' => [Vecaki::class]]);
        $this->setOrder('surname');

        $this->onHook(self::HOOK_BEFORE_SAVE, static function (self $entity): void {
            if (!$entity->isLoaded() || (!$entity->isDirty('start_time') && !$entity->isDirty('end_time'))) {
                return;
            }
            // Use the model's transaction and the same teacher lock as Booking::create().
            $connection = Sql::assertInstanceOf($entity->getModel()->getPersistence())->getConnection()->getConnection();
            $current = $connection->fetchAssociative('SELECT start_time, end_time FROM teacher WHERE id = ? FOR UPDATE', [$entity->getId()]);
            if ($current === false) {
                throw new ValidationException(['start_time' => 'Skolotājs vairs nav pieejams.'], $entity);
            }
            $start = $entity->isDirty('start_time') ? $entity->get('start_time') : $current['start_time'];
            $end = $entity->isDirty('end_time') ? $entity->get('end_time') : $current['end_time'];
            try {
                Booking::slots($start, $end);
            } catch (\InvalidArgumentException $error) {
                throw new ValidationException(['end_time' => $error->getMessage()], $entity);
            }
            $outside = $connection->fetchOne(
                'SELECT time FROM vecaki WHERE teacher_id = ? AND (time < ? OR time >= ?) LIMIT 1 FOR UPDATE',
                [$entity->getId(), $start, $end]
            );
            if ($outside !== false) {
                throw new ValidationException([
                    $outside < $start ? 'start_time' : 'end_time' => 'Jaunajā laikā neietilpst esošie pieraksti. Vispirms pārceliet tos.',
                ], $entity);
            }
        });
    }

    public function getBookingSlots(): array
    {
        return Booking::slots($this->get('start_time'), $this->get('end_time'));
    }

    public function validate(?string $intent = null): array
    {
        $errors = parent::validate($intent);
        try {
            $this->getBookingSlots();
        } catch (\InvalidArgumentException $error) {
            $errors['end_time'] = $error->getMessage();
        }

        return $errors;
    }
}
