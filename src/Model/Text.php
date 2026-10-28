<?php

declare(strict_types=1);

namespace Model;

class Text extends \Atk4\Data\Model
{
    public const TEACHER_UNAVAILABLE_MESSAGE = 'teacher_unavailable_message';
    public const DEFAULT_TEACHER_UNAVAILABLE_MESSAGE = 'Pedagoga nebūs. Lūdzam sazināties individuāli e-klasē un sarunāt citu laiku. Paldies!';

    public $table = 'text';
    public $caption = 'Text';

    protected function init(): void
    {
        parent::init();
        $this->addField('code', ['required' => true]);
        $this->addField('text', ['required' => true]);
    }
}
