<?php
include_once "../../../config/Connection.php";
include_once '../models/reporte.php';
include_once '../../../assets/vendor/phpexcel/PHPExcel.php';


if (isset($_GET['ids'])) {
    $proyectos = $_GET['ids'];

} else {
    echo "No se recibieron los parámetros necesarios.";
}
 
$reporte = new mdlReportesDash();
$reportes = $reporte->reporteGeneralDash($proyectos);

 
// Cargar la hoja de excel 
$excel = PHPExcel_IOFactory::load('./EquipoGeneralDash.xlsx');
$excel->SetActiveSheetIndex(0);

$total = 0;
$fila = 6;
$columna = 'A';


foreach ($reportes as $algoquenose) {
    $excel->SetActiveSheetIndex(0)->setCellValue($columna . $fila, ($algoquenose['codigoSAP']));
    $columna++;
    $excel->SetActiveSheetIndex(0)->setCellValue($columna . $fila, ($algoquenose['ClaseAF']));
    $columna++;
    $excel->SetActiveSheetIndex(0)->setCellValue($columna . $fila, ($algoquenose['descripcionGeneral']));
    $columna++;
    $excel->SetActiveSheetIndex(0)->setCellValue($columna . $fila, ($algoquenose['nombreModelo']));
    $columna++;
    $excel->SetActiveSheetIndex(0)->setCellValue($columna . $fila, ($algoquenose['serie']));
    $columna++;
    $excel->SetActiveSheetIndex(0)->setCellValue($columna . $fila, ($algoquenose['fechaAdquisicion']));
    $columna++;
    $excel->SetActiveSheetIndex(0)->setCellValue($columna . $fila, ($algoquenose['precioAdquisicion']));
    $columna++;
    $excel->SetActiveSheetIndex(0)->setCellValue($columna . $fila, ($algoquenose['Asignado']));
    $columna++;
    $excel->SetActiveSheetIndex(0)->setCellValue($columna . $fila, ($algoquenose['estado']));
    $columna++;
    $excel->SetActiveSheetIndex(0)->setCellValue($columna . $fila, ($algoquenose['Observaciones']));
    // $total = $total + floatval($trasplante['total']);
    $columna = 'A';
    $fila++;
}
// $fila++;

// Propiedades del documento
$excel->getProperties()->setCreator("Inventario")
    ->setLastModifiedBy("IT")
    ->setSubject("Reporte General de Equipo")
    ->setDescription("Reporte General de Equipo2");

header('Content-Type: application/vnd.ms-excel');
header('Content-Disposition: attachment;filename=ReporteGeneralDash.xlsx');
header('Cache-Control: max-age=0');

$objWriter = PHPExcel_IOFactory::createWriter($excel, 'Excel2007');
$objWriter->save('php://output');
