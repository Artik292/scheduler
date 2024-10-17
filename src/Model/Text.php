<?php
namespace Model;

class Text extends \atk4\data\Model {
    public $table = 'text';
    public $title = 'Text';

    function init() {
        parent::init();

        $this->addField('code',['required'=>TRUE]);
        $this->addField('text',['required'=>TRUE]);
    }
}
