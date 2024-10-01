<?php
require 'vendor/autoload.php';
$app = new App('public');
session_start();

$check = new \atk4\data\Model(new \atk4\data\Persistence_Array($a));
$check->addField('password',['type'=>'password','required'=>TRUE]);
$form = $app->layout->add('Form');
$form->buttonSave->set('Enter');
$form->setModel($check);
if (isset($_ENV['pass'])) {
  $unknown = $_ENV['pass'];
} else {
  $unknown = 'admin';
}
$form->onSubmit(function($form) use($unknown,$app) {
  if ($form->model['password'] == $unknown) {
      $_SESSION['admin_access'] = 'tkvbk0/0ilyvmamy';
      return $app->jsRedirect(['admin']);
  } else {
      return $app->jsRedirect(['index']);
  }
 });
