<?php

declare(strict_types=1);

namespace Scheduler\Ui;

use Atk4\Data\Field;
use Atk4\Ui\Table\Column\DragHandler;

final class SubjectDragHandle extends DragHandler
{
    public function getDataCellTemplate(?Field $field = null): string
    {
        return $this->getApp()->getTag('button', [
            'type' => 'button', 'class' => 'ui basic icon button atk-handle subject-drag-handle',
            'title' => 'Pārvietot priekšmetu', 'aria-label' => 'Pārvietot priekšmetu',
        ], [$this->getApp()->getTag('i', ['class' => 'bars icon', 'aria-hidden' => 'true'])]);
    }
}
