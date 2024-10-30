<?php

require 'vendor/autoload.php';
$app = new App('public');

session_start();

If (isset($_SESSION['teachers_access'])) {
  If (($_SESSION['teachers_access']) == 'nggyupn$g0lyoud)))') {

    $button_back = $app->add(['Button','Atgriezties mājaslapā','big primary','icon'=>'home'])
    ->link(['index']);

    $app->add(['ui'=>'divider']);

    $teacher = new Model\Teacher($app->db);
    $teacher->setOrder('surname');
    $grid = $app->add('Grid');
    $grid->setModel($teacher,['name','surname','class','cabinet','subject']);
    $grid->addQuickSearch(['name']);
    $grid->addDecorator('name', new \atk4\ui\TableColumn\Link('parentslist.php?id={$id}'));
    } else {
      header('Location: index.php');
    }
  } else {
    header('Location: index.php');
}