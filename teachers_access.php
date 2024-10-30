<?php
require 'vendor/autoload.php';
$app = new App('public');
session_start();

$check = new \atk4\data\Model(new \atk4\data\Persistence_Array($a));
$check->addField('password',['type'=>'password','required'=>TRUE]);
$form = $app->layout->add('Form');
$form->buttonSave->set('Enter');
$form->setModel($check);
if (isset($_ENV['teachers_pass'])) {
  $unknown = $_ENV['teachers_pass'];
} else {
  $unknown = 'teachers';
}
$form->onSubmit(function($form) use($unknown,$app) {
  if ($form->model['password'] == $unknown) {
      $_SESSION['teachers_access'] = 'nggyupn$g0lyoud)))';
      return $app->jsRedirect(['teachers']);
  } else {
      return $app->jsRedirect(['index']);
  }
 });
