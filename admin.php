<?php

require 'vendor/autoload.php';

$app = new App('admin');

session_start();

If (isset($_SESSION['admin_access'])) {
  If (($_SESSION['admin_access']) == 'tkvbk0/0ilyvmamy') {
    date_default_timezone_set('UTC');

    $app->stickyget('check');

    if (isset($_GET['check'])) {
      $check = $_GET['check'];
      $crud = $app->add('CRUD');
      if ($check == 'lessons') {
        $crud->setModel(new Model\Subject($app->db));
        $crud->addQuickSearch(['name']);
      } elseif ($check == 'teachers') {
        $crud->setModel(new Model\Teacher($app->db));
        $crud->addQuickSearch(['name','cabinet']);
      } elseif ($check == 'text') {
        $crud->setModel(new Model\Text($app->db));
        $crud->addQuickSearch(['code','text']);
      }

    } else {
      $crud = $app->add('CRUD');
      $crud->setModel(new Model\Vecaki($app->db));
      $crud->addQuickSearch(['student_name','parent_name','contact_phone','time']);
      $counter = new Model\Counter($app->db);
      $counter->tryLoadBy('id','1');
      $label_counter = $app->add(['Label','Counter = '.$counter['counter'],'small red']);
      $counter_show = $app->add(['Button','Press to reset counter','small red']);
      $counter_show->on('click', function($counter) use($app,$label_counter) {
          $counter = new Model\Counter($app->db);
          $counter->tryLoadBy('id','1');
          If ($counter->loaded()) {
            $counter['counter'] = 0;
            $counter->save();
            return new \atk4\ui\jsReload($label_counter);
          } else {
            return 0;
          }
      });
    }
  } else {
    header('Location: index.php');
  }
} else {
  header('Location: index.php');
}
