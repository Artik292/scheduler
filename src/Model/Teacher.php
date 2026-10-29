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
        // Correlated subqueries must reference this teacher, not an inner subject.id.
        $this->persistenceData['use_table_prefixes'] = true;
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
        // Retain the old column for compatibility during rolling deployments.
        $this->addField('subject_id', ['type' => 'bigint', 'system' => true]);
        $this->addExpression('subject', [
            'expr' => '(SELECT GROUP_CONCAT(s.name ORDER BY s.name SEPARATOR ", ") FROM teacher_subject ts JOIN subject s ON s.id = ts.subject_id WHERE ts.teacher_id = [id])',
            'caption' => 'Grupas',
        ]);
        $this->addExpression('subject_ids', [
            'expr' => '(SELECT COALESCE(JSON_ARRAYAGG(ts.subject_id), JSON_ARRAY()) FROM teacher_subject ts WHERE ts.teacher_id = [id])',
            'type' => 'json', 'readOnly' => false, 'neverSave' => true,
            'caption' => 'Priekšmeti',
            'ui' => ['visible' => false, 'editable' => true, 'form' => [
                \Atk4\Ui\Form\Control\Dropdown::class, 'multiple' => true,
                'class' => ['search'], 'model' => new Subject($this->getPersistence()),
            ], 'hint' => 'Var izvēlēties vairākus priekšmetus vai atstāt tukšu.'],
        ]);
        $this->hasMany('Vecaki', ['model' => [Vecaki::class]]);
        $this->setOrder('surname');

        $this->onHook(self::HOOK_BEFORE_SAVE, static function (self $entity): void {
            if ($entity->isLoaded() && $entity->isDirty('subject_ids')) {
                $entity->saveSubjects();
            }
        });
        $this->onHook(self::HOOK_AFTER_INSERT, static function (self $entity): void {
            $entity->saveSubjects();
        });

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

    public function forSubject(int $subjectId): self
    {
        $this->addCondition($this->expr(
            'EXISTS (SELECT 1 FROM teacher_subject ts WHERE ts.teacher_id = [id] AND ts.subject_id = [])',
            [$subjectId]
        ));

        return $this;
    }

    private function saveSubjects(): void
    {
        $ids = $this->get('subject_ids') ?? [];
        $ids = array_values(array_unique(array_map('intval', array_filter($ids, static fn ($id) => $id !== ''))));
        $connection = Sql::assertInstanceOf($this->getModel()->getPersistence())->getConnection()->getConnection();
        $connection->fetchOne('SELECT id FROM teacher WHERE id = ? FOR UPDATE', [$this->getId()]);
        foreach ($ids as $id) {
            if ($connection->fetchOne('SELECT id FROM subject WHERE id = ? FOR SHARE', [$id]) === false) {
                throw new ValidationException(['subject_ids' => 'Izvēlētais priekšmets vairs nav pieejams.'], $this);
            }
        }
        $connection->executeStatement('DELETE FROM teacher_subject WHERE teacher_id = ?', [$this->getId()]);
        foreach ($ids as $id) {
            $connection->executeStatement('INSERT INTO teacher_subject (teacher_id, subject_id) VALUES (?, ?)', [$this->getId(), $id]);
        }
        $connection->executeStatement('UPDATE teacher SET subject_id = NULL WHERE id = ?', [$this->getId()]);
    }

    public function validate(?string $intent = null): array
    {
        $errors = parent::validate($intent);
        $subjectIds = $this->get('subject_ids') ?? [];
        if (!is_array($subjectIds)) {
            $errors['subject_ids'] = 'Izvēlieties priekšmetus no saraksta.';
            $subjectIds = [];
        }
        foreach ($subjectIds as $id) {
            if ($id !== '' && ((!is_int($id) && !is_string($id)) || !preg_match('/^[1-9][0-9]*$/D', (string) $id))) {
                $errors['subject_ids'] = 'Izvēlieties priekšmetus no saraksta.';
            }
        }
        try {
            $this->getBookingSlots();
        } catch (\InvalidArgumentException $error) {
            $errors['end_time'] = $error->getMessage();
        }

        return $errors;
    }
}
