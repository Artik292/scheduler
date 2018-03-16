<?php

require 'vendor/autoload.php';
$app = new App('public');

$col = $app->add('Columns');
$col->addClass('stackable');
$subject= new Model\Subject($app->db);

$c1 = $col->addColumn();
$c2 = $col->addColumn();
$c3 = $col->addColumn();
$c4 = $col->addColumn();
$mes = $c4->add(['Message','Lietošanas instrukcija','massive info']);
$mes->text->addParagraph('Lai pabridināt skolotāju par jūsu ierašanas laiku, lūdzu izvēlaties priekšmetu, skolotāja vārdu un vēlamo laiku.');

$table_s = $c1->add(['Table','very basic selectable'])->addStyle('cursor', 'pointer');
$table_s->setModel($subject, [$subject->title_field]);
$table_s->on('click', 'tr', $c2->jsReload(['pr'=>$table_s->jsRow()->data('id')]));
$pr = $app->stickyGet('pr');
if ($pr) {
  $subject->load($pr);
  $teacher = $subject->ref('Teacher');
  $table_t = $c2->add(['Table','very basic selectable'])->addStyle('cursor', 'pointer');
  $table_t->setModel($teacher,[$teacher->title_field]);
  $table_t->on('click', 'tr', $c3->jsReload(['t'=>$table_t->jsRow()->data('id')]));
}
$t = $app->stickyGet('t');
if($t) {
  $teacher= new Model\Teacher($app->db);
  $teacher = $teacher->load($t);
  $parents=$teacher->ref('Vecaki');
  $teacher_id = $teacher->id;
  $parentss = new Model\Vecaki($app->db);

  $menu = $c3->add('Menu');
  $menu->addClass('vertical fluid');
  $menu->addHeader('Laiki ('.$teacher['name'].')');

  $vir = $app->add('VirtualPage');
  $vir->set(function($vir) use($parentss,$app,$teacher_id) {
    $form = $vir->add('Form');
    $form->setModel($parentss,['student_name','parent_name','contact_phone']);
    $parentss['time'] = $app->stickyGet('time');
    $parentss['teacher_id'] = $teacher_id;
    $form->onSubmit(function($form) {
      $form->model->save();
      return [$form->success('Jūsu pieprasījums ir iesniegts!') , new \atk4\ui\jsExpression('document.location="parents.php"')];
    });
  });

  $min=0;
  for ($hour=17;$hour<=18;$hour++) {
    for ($i=1;$i<=12;$i++) {
      if ($min>=60) {
        $min=0;
      }
      if($min<10) {
        $time = $hour.':0'.$min;
      }else {
        $time = $hour.':'.$min;
      }
      $s=1;
      if($parents->tryLoadAny()->loaded()==TRUE) {
        foreach($parents as $rowss) {
          if ($rowss['time']==$time) {
            $array=[$s=>$time];
            $s=$s+1;
          }
        }
        unset($rowss);
        $check=FALSE;
        for($n=0;$n<=$s;$n++){
          if (isset($array[$n]) && $array[$n]==$time) {
            $menu->addItem([$time,'disabled']);
            $check=TRUE;
          }
        }
        if($check==FALSE){
          $menu->addItem($time)->on('click', new \atk4\ui\jsModal('Ieraksts',$vir,['time'=>$time]));
        }
        }else {
          $menu->addItem($time)->on('click', new \atk4\ui\jsModal('Ieraksts',$vir,['time'=>$time]));
        }
      $min=$min+5;
    }
  }
}


$app->add(['ui'=>'divider']);

$app->add(['Label','This app is made by Colibri School students','red right ribbon'])
->link('http://colibrischool.lv');
