<?php

class App extends \atk4\ui\App {
    public $db;
    public $sms;

    function __construct($mode) {
        parent::__construct('Vecāku diena');

        if ($mode == 'public') {
            $this->initLayout('Centered');

            $this->layout->template->del('Header');

            $logo = 'logo.png';

            $this->layout->add(['Image',$logo,'small centered'],'Header');
            //$this->layout->add(['Label','Work','red right'],'Header');


            $this->layout->add([
                'Header',
                'Vecāku diena',
                'size'=>'huge',
                'aligned' => 'center',
            ], 'Header');

        }elseif($mode == 'admin') {
            $this->initLayout('Admin');
            $this->layout->leftMenu->addItem(['Galvenā lapa', 'icon'=>'home'], ['logout']);
            $this->layout->leftMenu->addItem(['Priekšmeti', 'icon'=>'book'], ['admin','check'=>'lessons']);
            $this->layout->leftMenu->addItem(['Skolotāji', 'icon'=>'users'], ['admin','check'=>'teachers']);
            $this->layout->leftMenu->addItem(['Ieraksti', 'icon'=>'unordered list'], ['admin']);
            $this->layout->leftMenu->addItem(['Virsraksti', 'icon'=>'pencil alternate'], ['admin','check'=>'text']);
        }elseif($mode == 'print') {
            $this->initLayout('Centered');

            $this->layout->template->del('Header');
        }
       if (isset($_ENV['CLEARDB_DATABASE_URL'])) {
            $this->db = \atk4\data\Persistence::connect($_ENV['CLEARDB_DATABASE_URL']);
        } else {
            $this->db = \atk4\data\Persistence::connect('mysql:host=localhost;dbname=testdb', 'root', 'rootpassword');
        }

}
}
