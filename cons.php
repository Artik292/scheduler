<?PHP

require 'vendor/autoload.php';

$app = new App('public');


$teacher=new Model\Teacher($app->db);
$teacher->load($app->stickyGet('id'));

//$t = new \atk4\core\DebugTrait;
$t = $app->add(['Console']);
//$where = 'http://localhost/scheduler/print.php?id='.$_GET['id']; //local
$where = 'https://vecaku-diena.herokuapp.com/print.php?id='.$_GET['id']; //not local
//$file_name = '/tmp/'.$teacher['name'].'.pdf';
$file_name = '"'.$teacher['name'].'.pdf';
//$file_name = '/Applications/XAMPP/xamppfiles/htdocs/scheduler/'.$teacher['name'].'.pdf';
$candidates = array_filter([
    getenv('WKHTMLTOPDF_BIN') ?: null,
    '/usr/local/bin/wkhtmltopdf',
    '/usr/bin/wkhtmltopdf',
    'vendor/h4cc/wkhtmltopdf-i386/bin/wkhtmltopdf-i386',
]);
$de_way = 'wkhtmltopdf';
foreach ($candidates as $binary) {
    if ($binary && is_file($binary) && is_executable($binary)) {
        $de_way = $binary;
        break;
    }
}
$request = $de_way.' "'.$where.'" "'.$file_name.'"';
//echo $request;
$t->exec($request);
$app->add(['Button','Gatavs','primary'])->link('open.php?id='.$_GET['id']);
//header('Location: open.php?id='.$_GET['id']);
//$t->exec('open '.'"'.$file_name.'"');
//$t->exec('/usr/local/bin/wkhtmltopdf http://localhost/scheduler/print.php?id=243 /tmp/sqkirskir.pdf');
//$t->exec('open '.'/tmp/sqkirskir.pdf');
//$t->exec('open /Applications/XAMPP/xamppfiles/htdocs/scheduler/logo.png');
