<?php
date_default_timezone_set('America/Bogota');
require_once("lib.inc.php");
cors();

use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Chart\DataSeriesValues;

error_reporting(E_ALL ^ (E_NOTICE | E_WARNING | E_DEPRECATED));
$_POST = json_decode(file_get_contents("php://input"), true);
$nombreHoja = "ConsultaRUND";

if (isset($_GET["tipo"]) && isset($_POST)) {
  // Distribuye las variables que entraron como $_POST;
  $dataCols = $_POST["cols"];
  $numTotalCols = count($dataCols);
  $dataFilas = $_POST["filas"];
  $numTotalFilas = count($dataFilas);
  $dataNomFil = $_POST["nomFil"];
  $dataNomCol = $_POST["nomCol"];
  $tipo = $_GET["tipo"];
  // Carga una plantilla
  $spreadsheet = IOFactory::load("docs/plantilla_reporte.xlsx");
  $hoja = $spreadsheet->getActiveSheet();
  $hoja->setTitle($nombreHoja);
  $inicio = [1, 1]; // Se indica la posición inicial de la tabla. Depende de la plantilla.
  // Calcula las posiciones de los elementos principales de la tabla.
  $posIniCol = [$inicio[0] + 1, $inicio[1]];
  $posFinCol = [($posIniCol[0] + $numTotalCols - 1), $posIniCol[1]];
  $posIniLabel = [$inicio[0],  ($posFinCol[1] + 2)];
  $posIniData = [$posIniLabel[0] + 1, $posIniLabel[1]];
  // Crea la tabla de datos.
  $posTablaDatos = creaTablaDatos($inicio, $hoja, $dataNomFil, $posIniCol, $posFinCol, $dataNomCol, $dataCols, $dataFilas);
  // Obtiene las variables de posición final de los elementos.
  $posNomCol = $posTablaDatos["posNomCol"];
  $posLabel = $posTablaDatos["posLabel"];
  $posData = $posTablaDatos["posData"];
  // Le da estilo a la Tabla
  $hoja->getStyle(arrayToCell($inicio) . ":" . arrayToCell($posNomCol))->getAlignment()
    ->setHorizontal(Alignment::HORIZONTAL_CENTER)
    ->setVertical(Alignment::VERTICAL_CENTER);
  $hoja->getStyle(arrayToCell($inicio) . ":" . arrayToCell($posNomCol))->getFont()->setBold(true);
  $hoja->getStyle(arrayToCell($inicio) . ":" . arrayToCell($posLabel))->getFont()->setBold(true);
  $hoja->getStyle(arrayToCell($inicio) . ":" . arrayToCell($posData))->getBorders()->getAllBorders()
    ->setBorderStyle(Border::BORDER_THIN);
  autoFitCols($hoja);
  // Crea las variables para crear el Gráfico.
  $valores = [];
  $etiquetas = [];
  for ($i = 0; $i < $numTotalCols; $i++) {
    $ini = [$posIniData[0] + $i, $posIniData[1]];
    $fin = [$posIniData[0] + $i, $posIniData[1] + ($numTotalFilas - 1)];
    $rangoVal = $nombreHoja . "!" . arrayToCell($ini, true, true) . ":" . arrayToCell($fin, true, true);
    $valores[] = new DataSeriesValues("Number", $rangoVal, null, $numTotalFilas);
    $posEti = [$posIniCol[0] + $i, $posIniCol[1] + 1];
    $rangoEti = $nombreHoja . "!" . arrayToCell($posEti, true, true);
    $etiquetas[] = new DataSeriesValues("String", $rangoEti, null, 1);
  }
  $rangoCat = $nombreHoja . "!" .
    arrayToCell($posIniLabel, true, true) . ":" .
    arrayToCell([$posIniLabel[0], $posIniLabel[1] + ($numTotalFilas - 1)], true, true);
  $categorias = [new DataSeriesValues("String", $rangoCat, null, $numTotalFilas)];
  $titulo = "Distribución por " . $dataNomCol . " según " . $dataNomFil;
  $posIniGrafico = [$posIniLabel[0], $posFinCol[1] + $numTotalFilas + 4];
  $posFinGrafico = [$posIniGrafico[0] + 9, $posIniGrafico[1] + 12];
  $posGrafico = [arrayToCell($posIniGrafico), arrayToCell($posFinGrafico)];
  // Crea el gráfico de barras.
  creaGraficoBarras($valores, $categorias, $etiquetas, $titulo, $hoja, $posGrafico);
  // Se alista la generación de XLSX
  $spreadsheet->getActiveSheet()->getPageSetup()->setFitToWidth(1);
  $spreadsheet->getActiveSheet()->getPageSetup()->setFitToHeight(0);
  $writer = new Xlsx($spreadsheet);
  $writer->setIncludeCharts(true);
  switch ($tipo) {
    case "xlsx": // Si la salida es un Excel
      header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
      header('Content-Disposition: attachment;filename="reporte.xls"');
      header('Cache-Control: max-age=0');
      $writer->save('php://output'); // Se envía directamente al cliente como Blob
      exit;
      break;
    case "pdf": // Si la salida es un PDF
      $writer->save("reporte.xlsx");
      $resp = convierteExcelToPDF();
      if ($resp["error"] == null) {
        $pdfFilePath = $resp["salida"];
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . basename($pdfFilePath) . '"');
        header('Content-Length: ' . filesize($pdfFilePath));
        readfile($pdfFilePath);
        exit;
      } else {
        print(json_encode($resp));
      }
      break;
  }
}
