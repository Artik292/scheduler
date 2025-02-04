<?php

require "vendor/autoload.php";

$app = new App("print");

session_start();

if (isset($_SESSION["teachers_access"])) {
    if ($_SESSION["teachers_access"] == 'nggyupn$g0lyoud)))') {
        $app->layout->template->del("Header");

        $teacher = new Model\Teacher($app->db);
        $teacher->load($app->stickyGet("id"));
        $parents = $teacher->ref("Vecaki");
        $parents->setOrder("time");

        $header = $app->add([
            "Header",
            $teacher["name"] .
            " " .
            $teacher["surname"] .
            " " .
            $teacher["class"],
        ]);

        $table = $app->add(["Table"]);
        $table->setModel($parents, [
            "time",
            "student_name",
            "grade",
            "parent_name",
            "contact_phone",
        ]);
    } else {
        header("Location: index.php");
    }
} else {
    header("Location: index.php");
}
/*$header = array('Vecaka Uzvards', 'Skolena Uzvards', 'Kontaktnumurs', 'Laiks');

$pdf = new FPDF();
$pdf->AddPage();
$pdf->SetFont('Arial','B',16);
//$pdf->Cell(40,10,$teacher['name']);
$pdf->Cell(40,10,'По русски');
$pdf->Ln();
$pdf->SetFont('Arial','B',12);
foreach($header as $col)
    $pdf->Cell(45,7,$col,1);
$pdf->Ln();
$pdf->SetFont('Arial','',10);
foreach($parents as $col) {
  $pdf->Cell(45,7,$parents['student_name'],1);
  $pdf->Cell(45,7,$parents['parent_name'],1);
  $pdf->Cell(45,7,$parents['contact_phone'],1);
  $pdf->Cell(45,7,$parents['time'],1);
  $pdf->Ln();
}
// Data
/*foreach($parents as $row)
{
    //foreach($row as $col)
        $pdf->Cell(40,6,$row,1);
    $pdf->Ln();
}

$pdf->Output(); */
