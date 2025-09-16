<?php

/**
 * RUND-API - Servicio de Gestión de Documentos
 * 
 * Maneja operaciones específicas de gestión de reportes e informes
 * 
 * @author ESAP Development Team / Oliver Castelblanco Martínez oliver.castelblanco@esap.edu.co
 * @version 2.0
 * @since PHP 8.3
 */

declare(strict_types=1);

namespace RUND\Services;


use RUND\Core\OpenKM as OpenKM;
use RUND\Config\Config as Config;

use PhpOffice\PhpSpreadsheet\IOFactory as IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet as Worksheet;
use PhpOffice\PhpSpreadsheet\Cell\DataType as DataType;
use PhpOffice\PhpSpreadsheet\Chart\DataSeries as DataSeries;
use PhpOffice\PhpSpreadsheet\Chart\PlotArea as PlotArea;
use PhpOffice\PhpSpreadsheet\Chart\Legend as Legend;
use PhpOffice\PhpSpreadsheet\Chart\Title as Title;
use PhpOffice\PhpSpreadsheet\Chart\Chart as Chart;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx as Xlsx;

class ReportesService
{

  /**
   * Genera un reporte en Excel a partir de una plantilla y datos proporcionados
   * @param string $nombrePlantilla Nombre del archivo de plantilla en OpenKM (debe estar en la ruta Config::TAX_PLANTILLAS_REPORTES)
   * @param string $nombreHoja Nombre de la hoja donde se colocarán los datos
   * @param int $numTotalCols Número total de columnas de datos
   * @param string $dataNomFil  Nombre de la fila de datos
   * @param string $dataNomCol Nombre de la columna de datos (encabezado secundario)
   * @param array $dataCols Array con los nombres de las columnas de datos
   * @param array $dataFilas Array de arrays con las filas de datos, cada fila debe tener 'label' y 'data' (array de valores)
   * @param int $numTotalFilas Número total de filas de datos
   * @return array Un array el objeto Writer y la ruta de la plantilla utilizada
   */
  public static function generaReporte(
    string $nombrePlantilla,
    string $nombreHoja,
    int $numTotalCols,
    string $dataNomFil,
    string $dataNomCol,
    array $dataCols,
    array $dataFilas,
    int $numTotalFilas
  ): array {
    // Carga una plantilla
    $query = "search/find?name=" . urlencode($nombrePlantilla) . "&path=" . urlencode(Config::TAX_PLANTILLAS_REPORTES);
    $uuid = json_decode(OpenKM::consulta($query), true)["queryResult"]["node"]["uuid"];
    $contPlantilla = OpenKM::getArchivo($uuid);
    $tempPlantilla = Config::TEMP_DIR . $nombrePlantilla;
    file_put_contents($tempPlantilla, $contPlantilla);

    $spreadsheet = IOFactory::load($tempPlantilla);
    $hoja = $spreadsheet->getActiveSheet();
    $hoja->setTitle($nombreHoja);
    $inicio = [1, 1]; // Se indica la posición inicial de la tabla. Depende de la plantilla.
    // Calcula las posiciones de los elementos principales de la tabla.
    $posIniCol = [$inicio[0] + 1, $inicio[1]];
    $posFinCol = [($posIniCol[0] + $numTotalCols - 1), $posIniCol[1]];
    $posIniLabel = [$inicio[0],  ($posFinCol[1] + 2)];
    $posIniData = [$posIniLabel[0] + 1, $posIniLabel[1]];
    // Crea la tabla de datos.
    $posTablaDatos = self::creaTablaDatos($inicio, $hoja, $dataNomFil, $posIniCol, $posFinCol, $dataNomCol, $dataCols, $dataFilas);
    // Obtiene las variables de posición final de los elementos.
    $posNomCol = $posTablaDatos["posNomCol"];
    $posLabel = $posTablaDatos["posLabel"];
    $posData = $posTablaDatos["posData"];
    // Le da estilo a la Tabla
    $hoja->getStyle(self::arrayToCell($inicio) . ":" . self::arrayToCell($posNomCol))->getAlignment()
      ->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER)
      ->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER);
    $hoja->getStyle(self::arrayToCell($inicio) . ":" . self::arrayToCell($posNomCol))->getFont()->setBold(true);
    $hoja->getStyle(self::arrayToCell($inicio) . ":" . self::arrayToCell($posLabel))->getFont()->setBold(true);
    $hoja->getStyle(self::arrayToCell($inicio) . ":" . self::arrayToCell($posData))->getBorders()->getAllBorders()
      ->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);
    self::autoFitCols($hoja);
    // Crea las variables para crear el Gráfico.
    $valores = [];
    $etiquetas = [];
    for ($i = 0; $i < $numTotalCols; $i++) {
      $ini = [$posIniData[0] + $i, $posIniData[1]];
      $fin = [$posIniData[0] + $i, $posIniData[1] + ($numTotalFilas - 1)];
      $rangoVal = $nombreHoja . "!" . self::arrayToCell($ini, true, true) . ":" . self::arrayToCell($fin, true, true);
      $valores[] = new \PhpOffice\PhpSpreadsheet\Chart\DataSeriesValues("Number", $rangoVal, null, $numTotalFilas);
      $posEti = [$posIniCol[0] + $i, $posIniCol[1] + 1];
      $rangoEti = $nombreHoja . "!" . self::arrayToCell($posEti, true, true);
      $etiquetas[] = new \PhpOffice\PhpSpreadsheet\Chart\DataSeriesValues("String", $rangoEti, null, 1);
    }
    $rangoCat = $nombreHoja . "!" . self::arrayToCell($posIniLabel, true, true) . ":" . self::arrayToCell([$posIniLabel[0], $posIniLabel[1] + ($numTotalFilas - 1)], true, true);
    $categorias = [new \PhpOffice\PhpSpreadsheet\Chart\DataSeriesValues("String", $rangoCat, null, $numTotalFilas)];
    $titulo = "Distribución por " . $dataNomCol . " según " . $dataNomFil;
    $posIniGrafico = [$posIniLabel[0], $posFinCol[1] + $numTotalFilas + 4];
    $posFinGrafico = [$posIniGrafico[0] + 9, $posIniGrafico[1] + 12];
    $posGrafico = [self::arrayToCell($posIniGrafico), self::arrayToCell($posFinGrafico)];
    self::creaGraficoBarras($valores, $categorias, $etiquetas, $titulo, $hoja, $posGrafico);
    $spreadsheet->getActiveSheet()->getPageSetup()->setFitToWidth(1);
    $spreadsheet->getActiveSheet()->getPageSetup()->setFitToHeight(0);
    $writer = new Xlsx($spreadsheet);
    $writer->setIncludeCharts(true);
    return [$writer, $tempPlantilla];
  }

  /**
   * Autoajusta el ancho de todas las columnas en una hoja de cálculo
   * @param Worksheet $hoja La hoja de cálculo donde se ajustarán las columnas
   * @return void
   */
  public static function autoFitCols(Worksheet $hoja): void
  {
    foreach ($hoja->getColumnIterator() as $column) {
      $hoja->getColumnDimension($column->getColumnIndex())->setAutoSize(true);
    }
  }

  /**
   * Crea una tabla de datos en una hoja de cálculo a partir de los datos proporcionados
   * @param array $inicio Posición inicial [columna, fila] donde se colocará la tabla
   * @param Worksheet $hoja La hoja de cálculo donde se creará la tabla
   * @param string $dataNomFil Nombre de la fila de datos (encabezado principal)
   * @param array $posIniCol Posición [columna, fila] de la primera columna de datos
   * @param array $posFinCol Posición [columna, fila] de la última columna de datos
   * @param string $dataNomCol Nombre de la columna de datos (encabezado secundario)
   * @param array $dataCols Array con los nombres de las columnas de datos
   * @param array $dataFilas Array de arrays con las filas de datos, cada fila debe tener 'label' y 'data' (array de valores)
   * @return array Un array con las posiciones finales de los elementos creados: 'posNomCol', 'posLabel', 'posData'
   */
  public static function creaTablaDatos(
    array $inicio,
    Worksheet $hoja,
    string $dataNomFil,
    array $posIniCol,
    array $posFinCol,
    string $dataNomCol,
    array $dataCols,
    array $dataFilas
  ): array {
    $posNomCol = [];
    $posLabel = [];
    $posData = [];
    $nomFilCell = self::arrayToCell($inicio) . ":" . self::arrayToCell([$inicio[0], ($inicio[1] + 1)]);
    $hoja->mergeCells($nomFilCell);
    $hoja->setCellValue($inicio, $dataNomFil);
    $nomFilCol = self::arrayToCell($posIniCol) . ":" . self::arrayToCell($posFinCol);
    $hoja->mergeCells($nomFilCol);
    $hoja->setCellValue($posIniCol, $dataNomCol);
    foreach ($dataCols as $numCol => $nomCol) {
      $posNomCol = [$posIniCol[0] + $numCol, $posIniCol[1] + 1];
      $hoja->setCellValue($posNomCol, $nomCol);
    }
    foreach ($dataFilas as $numFil => $fila) {
      //$filaLabel = $fila["label"];
      $filaData = $fila["data"];
      //$arrayFila = array_merge([$filaLabel], $filaData);
      $posLabel = [$inicio[0],  ($posFinCol[1] + 2 + $numFil)];
      $hoja->setCellValue($posLabel, $fila["label"]);
      foreach ($filaData as $numData => $data) {
        $posData = [$posLabel[0] + 1 + $numData, $posLabel[1]];
        $hoja->getCell($posData)->setValueExplicit($data, DataType::TYPE_NUMERIC);
      }
    }
    return [
      "posNomCol" => $posNomCol,
      "posLabel" => $posLabel,
      "posData" => $posData,
    ];
  }

  /**
   * Crea un gráfico de barras en una hoja de cálculo a partir de los datos proporcionados
   * @param array $valores Array de arrays con los valores de las series del gráfico
   * @param array $categorias Array con las categorías del eje X
   * @param array $etiquetas Array con las etiquetas de las series
   * @param string $titulo Título del gráfico
   * @param Worksheet $hoja La hoja de cálculo donde se creará el gráfico
   * @param array $posGrafico Posición del gráfico en la hoja, como un array con dos elementos: posición superior izquierda (por ejemplo, ['E1']) y posición inferior derecha (por ejemplo, ['M15'])
   * @return void
   */
  public static function creaGraficoBarras(
    array $valores,
    array $categorias,
    array $etiquetas,
    string $titulo,
    Worksheet $hoja,
    array $posGrafico
  ): void {
    $series = new DataSeries(
      DataSeries::TYPE_BARCHART,
      DataSeries::GROUPING_CLUSTERED,
      range(0, count($valores) - 1),
      $etiquetas, // Etiquetas para la serie de datos
      $categorias, // Etiquetas del eje X
      $valores // Valores de la serie
    );

    $series->setPlotDirection(DataSeries::DIRECTION_COL);
    $plotArea = new PlotArea(null, [$series]);
    $legend = new Legend(Legend::POSITION_RIGHT, null, false);
    $title = new Title($titulo);
    $chart = new Chart(
      'chart1', // Nombre del gráfico
      $title, // Título
      $legend, // Leyenda
      $plotArea, // Área de trazado (plot area)
      true, // Plot visible
      'gap', // Plot la(s) categoría(s)
      null, // Título del eje X
      null  // Título del eje Y
    );
    $chart->setTopLeftPosition($posGrafico[0]);
    $chart->setBottomRightPosition($posGrafico[1]);
    $hoja->addChart($chart);
  }

  /**
   * Convierte un array de posición [column, row] en una celda A1
   * @param array $array Array con la posición [columna, fila] (por ejemplo, [1, 1] para A1)
   * @param bool $colFija Indica si la columna debe ser fija (con $)
   * @param bool $linFija Indica si la fila debe ser fija (con $)
   * @return string La celda en formato A1 (por ejemplo, 'A1' o '$A$1')
   */
  public static function arrayToCell(array $array, bool $colFija = false, bool $linFija = false)
  {
    return ($colFija ? "$" : "") . chr($array[0] + 64) . ($linFija ? "$" : "") . $array[1];
  }
}
