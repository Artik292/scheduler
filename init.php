<?php
date_default_timezone_set('UTC');
require 'vendor/autoload.php';
$app = new App('public');
$subject = new Model\Subject($app->db);
$teacher = new Model\Teacher($app->db);
$day = new Model\Inter($app->db);
$timeslot = new Model\Timeslot($app->db);
$parents = new Model\Parents($app->db);
$date_day = date('d');
$date_month = date('m');
