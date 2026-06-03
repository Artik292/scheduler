<?php

require "vendor/autoload.php";

$app = new App("public");

$buttons = $app->add("Columns");
$buttons->addClass("stackable doubling equal width");
$columnParents = $buttons->addColumn();
$columnTeachers = $buttons->addColumn();

$button1 = $columnParents->add(["Button", "Vecākiem", "massive red fluid"]);
$button1->link(["parents"]);
$button2 = $columnTeachers->add(["Button", "Skolotājiem", "massive green fluid"]);
$button2->link(["teachers_access"]);

$reminder = $app->add(["ui" => "horizontal divider header"]);
$text = new Model\Text($app->db);
$text->tryLoadBy("code", "main_page_middle_text");
$reminder->set($text["text"]);

$app->add(["ui" => "hidden divider"]);

// $app->add(['Label','This app is made by Colibri School students','red right ribbon'])
// ->link('http://colibrischool.lv');

$text = new Model\Text($app->db);
$text->tryLoadBy("code", "main_page_sub_text");
$app->add(["Header", $text["text"], "tiny", "aligned" => "center"]);

$counter = new Model\Counter($app->db);
$counter->tryLoadBy("id", "1");
if ($counter->loaded()) {
    $counter["counter"] = $counter["counter"] + 1;
    $counter->save();
} else {
    $counter["counter"] = 1;
    $counter->save();
}
