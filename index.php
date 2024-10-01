<?php

require 'vendor/autoload.php';
$app = new App('public');

$button1 = $app->add(['Button','Vecākiem','massive red']);
$button1->link(['parents']);
$button2 = $app->add(['Button','Skolotājiem','massive green right floated']);
$button2->link(['teachers']);

$reminder = $app->add(['ui'=>'horizontal divider header',]);
$reminder->set('Cienījamie vecāki! Lūdzam ievērot reglamentu: no 17.00 līdz 19.00.');

$app->add(['ui'=>'hidden divider']);

// $app->add(['Label','This app is made by Colibri School students','red right ribbon'])
// ->link('http://colibrischool.lv');

$app->add(['Header','2018.gada 22.martā plkst. 17.00 - 19.00.','tiny','aligned' => 'center']);

$counter = new Model\Counter($app->db);
$counter->tryLoadBy('id','1');
If ($counter->loaded()) {
  $counter['counter'] = $counter['counter'] + 1;
  $counter->save();
} else {
  $counter['counter'] = 1;
  $counter->save();
}
