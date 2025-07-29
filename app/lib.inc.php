<?php
ini_set('display_errors', 1);
error_reporting(E_ALL ^ (E_NOTICE | E_WARNING | E_DEPRECATED));
date_default_timezone_set('America/Bogota');
require_once __DIR__ . '/vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Chart\Chart;
use PhpOffice\PhpSpreadsheet\Chart\DataSeries;
use PhpOffice\PhpSpreadsheet\Chart\Legend;
use PhpOffice\PhpSpreadsheet\Chart\PlotArea;
use PhpOffice\PhpSpreadsheet\Chart\Title;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpWord\TemplateProcessor;
use PhpOffice\PhpWord\Element\TextRun;

// Lee la URL de la API externa (OpenKM) desde la variable de entorno 'EXTERNAL_API_URL'
// para desacoplar la configuración del código. Esta variable se define en docker-compose.yml.
// Si la variable de entorno no está definida, recurre al archivo data.json como respaldo.
$dataInit = json_decode(file_get_contents(__DIR__ . "/data/data.json"), true);
$base = getenv('EXTERNAL_API_URL');
if ($base === false) {
  $base = $dataInit["openKM"];
}
const USER = "okmAdmin";
const PASSWORD = "admin";
const REST = "/services/rest/";
$nomCats = json_decode(file_get_contents(__DIR__ . "/data/labels.json"), true);

const TEMP_DIR = __DIR__ . "/tmp/";

// ---------- Funciones API OpenKM
/**
 * creaCarpetas
 * Crea carpetas para categorias o taxonomía, carpeta a carpeta, en OpenKM a partir de una ruta dada y un prefijo.
 * @param array $rutas Rutas de las carpetas a crear, por ejemplo: ["/RUTA/A_LA_NUEVA/CARPETA", "/RUTA/A_OTRA/CARPETA"]
 * @param string $prefijo Prefijo que se añadirá a la ruta, por ejemplo: "okm:root/RUND/" o "okm:categories/RUND/"
 * @return void
 */
function creaCarpetas(array $rutas, string $prefijo): array
{
  $headers = [
    "Accept: application/json",
    "Content-Type: application/json"
  ];
  $respuesta = [];
  foreach ($rutas as $ruta) {
    $ruta = str_replace($prefijo, "", $ruta); // Elimina el prefijo de la ruta, si existe
    $path = explode("/", $ruta);
    $path = array_filter($path, fn($part) => !empty($part)); // Elimina partes vacías
    $ruta = $prefijo;
    foreach ($path as $part) {
      $ruta .= $part . "/";
      $resp = consulta("repository/getNodeUuid?nodePath=" . urlencode($ruta)); // Consulta si la ruta ya existe
      if (strpos($resp, "PathNotFoundException") !== false) { // Crea la carpeta si no existe
        $rutaSinBarra = rtrim($ruta, '/');
        array_push($headers, "Content-Length: " . strlen($rutaSinBarra));
        $respuesta[] = [
          "getNodeUuid" => $resp,
          "ruta" => $ruta,
          "accion" => "folder/createSimple",
          "consulta" => consulta("folder/createSimple", "POST", $rutaSinBarra, $headers),
        ];
      } else { // La carpeta ya existe
        $respuesta[] = [
          "getNodeUuid" => $resp,
          "ruta" => $ruta,
          "accion" => "Carpeta ya existe",
          "consulta" => null,
        ];
      }
    }
  }
  return $respuesta;
}
function creaCategorias(array $categorias): array // Recibe categorías con el formato ["path" => "/okm:categories/RUTA/A/LA/NUEVA/CATEGORIA", ...]
{
  foreach ($categorias as $categoria) {
    $salida = [];
    $rutaCat = $categoria["path"];
    $uuid = consulta("repository/getNodeUuid?nodePath=" . urlencode($rutaCat));
    if (str_contains($uuid, "PathNotFoundException")) { // No existe en el árbol de categorías
      $headers = [
        "Accept: application/json",
        "Content-Type: application/json"
      ];
      $rutaSinBarra = rtrim($rutaCat, '/');
      array_push($headers, "Content-Length: " . strlen($rutaSinBarra));
      array_push(
        $salida,
        [
          "consulta" => [
            "nodo" => "folder/createSimple",
            "metodo" => "POST",
            "postData" =>  $rutaCat,
            "headers" => $headers
          ],
          "respuesta" => consulta("folder/createSimple", "POST", $rutaSinBarra, $headers)
        ]
      );
    }
  }
  return $salida;
}
function getArbolCarpetas($uuid, $key = null)
// Genera un árbol de carpetas (sirve para Taxonomía o Categorias) a partir de un UUID.
// Si se le proporciona un $key, construye el KEY del árbol a partir de ese número:
// esto es útil para estructura de dato tipo dataTrees con keys únicos en Angular
{
  global $nomCats;
  $carpetas = [];
  $propiedades = json_decode(consulta("folder/getProperties?fldId=$uuid"), true);
  $tieneHijos = $propiedades["hasChildren"];
  if ($tieneHijos) {
    $hijos = json_decode(consulta("folder/getChildren?fldId=$uuid"), true)["folder"];
    if (!esArraySimple($hijos)) $hijos = [$hijos]; // Cuando hay un solo hijo, la API devuelve el hijo directamente, no en array
    foreach ($hijos as $num => $hijo) {
      $clave = $key !== null ? "$key-$num" : $num;
      $carpetas[$num] = [
        "key" => "$clave",
        "label" => $nomCats[simplePath($hijo["path"])],
        "uuid" => $hijo["uuid"],
        "path" => $hijo["path"],
      ];
      if ($hijo["hasChildren"]) {
        $carpetas[$num]["children"] = getArbolCarpetas($hijo["uuid"], $clave);
      } else {
        $documentos = getDocumentosCategorizados($hijo["uuid"]);
        $dataDocumentos = [];
        $numDocs = 0;
        if ($documentos) {
          if (esArraySimple($documentos)) { // Más de un documento hijo
            $numDocs = count($documentos);
            foreach ($documentos as $numDoc => $documento) {
              $docClave = $clave . "-d$numDoc";
              $categorias = [];
              if (esArraySimple($documento["categories"])) { // Varias categorias
                foreach ($documento["categories"] as $categoria) {
                  $categorias[] = [
                    "label" => $nomCats[simplePath($categoria["path"])],
                    "uuid" => $categoria["uuid"],
                    "path" => $categoria["path"],
                  ];
                }
              } else { // Una sola categoria
                $categorias = [
                  "label" => $nomCats[simplePath($documento["categories"]["path"])],
                  "uuid" => $documento["categories"]["uuid"],
                  "path" => $documento["categories"]["path"],
                ];
              }
              $dataDocumentos[] = [
                "key" => $docClave,
                "path" => $documento["path"],
                "uuid" => $documento["uuid"],
                "mimeType" => $documento["mimeType"],
                "categorias" => $categorias,
              ];
            }
          } else { // Solo un documento hijo
            $numDocs = 1;
          }
        }
        $carpetas[$num]["numDocs"] = $numDocs;
        $carpetas[$num]["documentos"] = $dataDocumentos;
      }
    }
  }
  return $carpetas;
}
function getDocumentosCategorizados($uuid) // Retorna los documentos que has sido "marcados" en una categoría dada
{
  return json_decode(consulta("search/getCategorizedDocuments/$uuid"), true)["document"];
}
function simplePath($path) // Extrae la última parte de un path de categorias o taxonomía: el nombre de la carpeta
{
  $rootName = "/\/okm:[categories|root]\//";
  $path = preg_replace($rootName, "", $path);
  $partes = explode("/", $path);
  return array_pop($partes);
}
function consulta(string $consulta, string $tipo = "GET", array|string|null $postData = null, array $headers = []): string
// Hace llamadas a la API de OpenKM, GET por defecto
{
  global $base;
  if (!arrayMatch($headers, "Accept:")) array_push($headers, "Accept: application/json");
  $curl = curl_init();
  curl_setopt($curl, CURLOPT_URL, $base . REST . $consulta);
  curl_setopt($curl, CURLOPT_USERNAME, USER);
  curl_setopt($curl, CURLOPT_PASSWORD, PASSWORD);
  curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
  switch ($tipo) { // Determina el tipo de consulta
    case "GET":
      curl_setopt($curl, CURLOPT_HTTPGET, true);
      array_push($headers, "Content-Type: application/json");
      break;
    case "POST":
      if (!$postData) throw new Error("Se solicita una consulta POST pero no se proporciona postData");
      curl_setopt($curl, CURLOPT_POST, true);
      curl_setopt($curl, CURLOPT_POSTFIELDS, $postData);
      break;
    case "PUT":
      if (!$postData) throw new Error("Se solicita una consulta PUT pero no se proporciona postData");
      $postData = (is_array($postData)) ? json_encode($postData) : $postData;
      curl_setopt($curl, CURLOPT_CUSTOMREQUEST, 'PUT');
      curl_setopt($curl, CURLOPT_POSTFIELDS, $postData);
      array_push($headers, "Content-Type: application/json");
      array_push($headers, "Content-Length: " . strlen($postData));
      break;
    case "DELETE":
      curl_setopt($curl, CURLOPT_CUSTOMREQUEST, 'DELETE');
      array_push($headers, "Content-Type: application/json");
      break;
  }
  curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
  $resp = curl_exec($curl);
  if (curl_errno($curl)) {
    $resp = curl_error($curl);
  }
  curl_close($curl);
  return $resp;
}
function getDocsFromCat($catUUID): array
{ // Devuelve los documentos de una categoría dada
  $docs =  getDocumentosCategorizados($catUUID);
  $docUUID = [];
  if (esArraySimple($docs)) {
    foreach ($docs as $doc) $docUUID[] = $doc["uuid"];
  } else {
    $docUUID[] = $docs["uuid"];
  }
  return $docUUID;
}
function getPathGeneraLabel($uuid) // Genera un label, usando labels.json a partir del uuid de un documento o carpeta
{
  global $nomCats;
  return $nomCats[array_pop(explode("/", json_decode(consulta("folder/getProperties?fldId=$uuid"), true)["path"]))];
}
function getUUIDHijos(string $padreUUID): array // Obtiene los uuid de los hijos de una carpeta
{
  $hijos = json_decode(consulta("folder/getChildren?fldId=$padreUUID"), true)["folder"];
  if (!esArraySimple($hijos)) $hijos = [$hijos];
  return array_map("getID", $hijos);
}
function getID($obj): array // Obtiene el "label" (no nativo de OpenKM) y extrae el uuid de un objeto
{
  global $nomCats;
  $partes = explode("/", $obj["path"]);
  $lastPart = array_pop($partes);
  $label = $nomCats[$lastPart];
  $uuid = $obj["uuid"];
  return ["label" => $label, "uuid" => $uuid];
}
function getLabels($obj): string // Extrae el campo "label"  de un objeto QUE YA LO TIENE
{
  return $obj["label"];
}
// --------------- Funciones API OpenKM -> POST y PUT
/**
 * cargaArchivo
 * @param array $archivo Array con la información del archivo a cargar, incluyendo:
 * - 'error': Código de error del archivo (0 si no hay error)
 * - 'name': Nombre del archivo
 * - 'tmp_name': Ruta temporal del archivo en el servidor
 * - 'type': Tipo MIME del archivo
 * - 'size': Tamaño del archivo
 * @param array $propiedades Array con las propiedades del archivo, incluyendo:
 * - 'Nombre': Nombre del archivo (opcional, si no se proporciona, se usa el nombre del archivo)
 * - 'Uuid': UUID del archivo (opcional, si no se proporciona, se busca en OpenKM)
 * De la forma ["label" => "Nombre", "valor" => "nombre_del_archivo.ext", ...]
 * @param string $path Ruta en OpenKM donde se cargará el archivo
 * @param bool|null $version Indica si se está cargando una nueva versión del archivo (true) o creando un nuevo archivo (false). Si es null, se asume que es una nueva carga.
 * @return array Array con el resultado de la carga, incluyendo:
 * - 'error': Código de error del archivo (0 si no hay error, o mensaje de error si lo hay)
 * - 'postData': Datos enviados en la solicitud POST, incluyendo la ruta del documento y el contenido del archivo
 * - 'respuesta': Respuesta de la API de OpenKM después de intentar crear o actualizar el documento
 * - 'folderResp': Respuesta de la API al intentar crear la carpeta si no existe (opcional)
 * * Esta función maneja la carga de archivos a OpenKM, incluyendo la creación de nuevas versiones si es necesario.
*/
function cargaArchivo(array $archivo, array $propiedades, string $path, bool | null $version = null): array
{
  $salida = ["error" => $archivo["error"]];
  if (!$archivo['error']) {
    $nombre = extraeElemento($propiedades, "label", "Nombre")["valor"] ?? $archivo["name"];
    $temp = $archivo["tmp_name"];
    $type = mime_content_type($temp);
    $fileData = new CURLFile($temp, $type, $nombre);
    $rutaDestino = $path . "/" . $nombre; // Ruta completa del archivo en OpenKM
    $query = "search/find?name=" . urlencode($nombre) . "&path=" . urlencode($path); // Cadena de búsqueda a partir del nombre y ruta
    $uuid = extraeElemento($propiedades, "label", "Uuid") ?
      extraeElemento($propiedades, "label", "Uuid")["valor"] :
      json_decode(consulta($query), true)["queryResult"]["node"]["uuid"];
    $postData = [
      "docPath" => $rutaDestino,
      "content" => $fileData
    ];
    $salida["postData"] = $postData;
    $comentarioNV = extraeElemento($propiedades, "label", "Comentario") ?
      extraeElemento($propiedades, "label", "Comentario")["valor"] :
      "Modificado " . date("Y-m-d H:i:s");
    // Si es nueva versión, llama a nuevaVersion(), de lo contrario, hace un createSimple directo.
    $resp = $version ?
      nuevaVersion($uuid, $comentarioNV, $postData) :
      consulta("document/createSimple", "POST", $postData);
    if (str_contains($resp, "PathNotFoundException")) {
      // No existe el directorio $carpetaDestino dentro de la ruta RUND/LISTADOS; se debe crear
      $headers = ["Content-Type: application/json"];
      // Se crea el directorio y se envía como postData la cadena directamente, sin json_encode
      $folderResp = consulta("folder/createSimple", "POST", $path, $headers);
      // Se intenta crear el documento, de nuevo
      $resp = consulta("document/createSimple", "POST", $postData);
    }
    $salida = verificaCarga($resp, $salida, $folderResp);
  }
  return $salida;
}
function verificaCarga(string $resp, array $salida, string | null $folderResp = null): array
{
  if (substr($resp, 0, 1) == '{') {
    $salida["respuesta"] = json_decode($resp, true);
    $salida["error"] = false;
  } else {
    $salida["error"] = "No se pudo crear el documento";
    if (isset($folderResp)) $salida["folderResp"] = $folderResp;
  }
  return $salida;
}
function nuevaVersion(string $uuid, string $comentario, array $postData): string
// Hace un checkout / checkin cuando el documento está duplicado y se quiere crear una nueva versión del mismo
{
  consulta("document/checkout?docId=$uuid");
  $postData["comment"] = $comentario;
  $postData["docId"] = $uuid;
  // Verifica que se haya podido hacer checkout
  $respCheckOut = json_decode(consulta("document/isCheckedOut?docId=$uuid", "GET", null, ["accept: text/plain"]), true);
  // Si se pudo hacer checkout, se hace checkin, de lo contrario se devuelve el error
  return $respCheckOut ?
    consulta("document/checkin", "POST", $postData) :
    json_encode(["error" => "No se pudo hacer checkout.", "respCheckOut" => $respCheckOut, "consulta" => "document/isCheckedOut?docId=$uuid"]);
}
function cargaJSON(array $dataJSON, string $nombreJSON, string $path, string | null $uuid = null): array
// Genera un JSON a partir de un array asociativo y lo carga en la $path específica
{
  $json = json_encode($dataJSON, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
  $rutaDestino = $path . "/" . $nombreJSON;
  $postData = [
    "docPath" => $rutaDestino,
    "content" => $json,
  ];
  $salida["postData"] = $postData;
  // Si es duplicado hace checkout/checkin o, sino, simplemente createSimple
  $resp = $uuid ?
    nuevaVersion($uuid, "Modificado " . date("Y-m-d H:i:s"), $postData) :
    consulta("document/createSimple", "POST", $postData);
  $salida = verificaCarga($resp, $salida);
  return $salida;
}
function getFirmas(string $rutaFirmas): array | string
// Obtiene todos los archivos de firma que hay en la ruta correspondiente en OpenKM, sean PNG o JSON
{
  $resp = json_decode(consulta("search/find?path=" . urlencode($rutaFirmas)), true);
  $firmas = [];
  foreach ($resp["queryResult"] as $firma) {
    $ruta = explode("/", $firma["node"]["path"]);
    $nombre = array_pop($ruta);
    $firmas[] = [
      "uuid" => $firma["node"]["uuid"],
      "mimeType" => $firma["node"]["mimeType"],
      "nombre" => $nombre,
    ];
  }
  return $firmas;
}
function getArchivo(string $uuid): string
// Función básica para obtener un archivo (binario o no) de OpenKM
{
  $headers = ["Accept: application/octet-stream"];
  return consulta("document/getContent?docId=" . $uuid, "GET", null, $headers);
}
function borraArchivo(string $uuid): string
// Elimina un archivo dado su UUID
{
  return consulta("document/delete?docId=" . $uuid, "DELETE");
}
function borraPapelera(): string
// Vacía la papelera de OpenKM
{
  return consulta("/repository/purgeTrash", "DELETE");
}
function yaExiste(string $nombreArchivo, string $path): bool
// Consulta si un archivo existe actualmente con el nombre y la ruta indicada
{
  $query = "search/find?name=" . urlencode($nombreArchivo) . "&path=" . urlencode($path); // Cadena de búsqueda a partir del nombre y ruta
  $respQuery = json_decode(consulta($query), true); // Consulta si el archivo ya existe
  return $respQuery && count($respQuery); // El archivo existe o no en esa carpeta de la Taxonomía
}
// Para PhpSpreadsheet
function autoFitCols($hoja)
{
  foreach ($hoja->getColumnIterator() as $column) {
    $hoja->getColumnDimension($column->getColumnIndex())->setAutoSize(true);
  }
}
function creaTablaDatos($inicio, $hoja, $dataNomFil, $posIniCol, $posFinCol, $dataNomCol, $dataCols, $dataFilas): array
{
  $posNomCol = [];
  $posLabel = [];
  $posData = [];
  $nomFilCell = arrayToCell($inicio) . ":" . arrayToCell([$inicio[0], ($inicio[1] + 1)]);
  $hoja->mergeCells($nomFilCell);
  $hoja->setCellValue($inicio, $dataNomFil);
  $nomFilCol = arrayToCell($posIniCol) . ":" . arrayToCell($posFinCol);
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
function creaGraficoBarras($valores, $categorias, $etiquetas, $titulo, $hoja, $posGrafico): void
{
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
// Para PhpWord
function creaCertificado(string $ruta, string $nombrePlantilla, array $estructura): TemplateProcessor
{
  $rutaPlantilla = $ruta . $nombrePlantilla;
  $templateProcessor = new TemplateProcessor($rutaPlantilla);
  $numParrafo = 0;
  foreach ($estructura as $bloque) {
    if ($bloque["tipo"] == "parrafo") {
      $numParrafo++;
      $placeholder = $bloque["tipo"] . $numParrafo;
      $texto = html_entity_decode($bloque["value"]);
      if (esComplejo($texto)) {
        $templateProcessor = creaParrafoComplejo($templateProcessor, $texto, $placeholder);
      } else {
        $templateProcessor = creaParrafo($templateProcessor, $texto, $placeholder);
      }
    }
    if ($bloque["tipo"] == "tabla") {
      $valores = [];
      $encabezados = $bloque["value"]["encabezados"];
      $primerEnc = strtolower(explode("_", textoAnombreCarpeta($encabezados[0]))[0]);
      $filas = $bloque["value"]["filas"];
      foreach ($filas as $fila) {
        $linea = [];
        foreach ($encabezados as $numCol => $encabezado) {
          $placeholder = strtolower(explode("_", textoAnombreCarpeta($encabezado))[0]);
          $linea[] = [$placeholder => $fila[$numCol]];
        }
        $valores[] = $linea;
      }
      $templateProcessor = creaTabla($templateProcessor, $valores, $primerEnc);
    }
    if ($bloque["tipo"] == "firma") {
      $templateProcessor = creaFirma($templateProcessor, $bloque["value"], $ruta);
    }
  }
  return $templateProcessor;
}
function creaParrafoComplejo(TemplateProcessor $templateProcessor, string $texto, string $placeholder): TemplateProcessor
{
  $templateProcessor->setComplexValue($placeholder, htmlToTextRun($texto));
  return $templateProcessor;
}
function creaParrafo(TemplateProcessor $templateProcessor, string $texto, string $placeholder): TemplateProcessor
{
  $templateProcessor->setValue($placeholder, $texto);
  return $templateProcessor;
}
function creaTabla(TemplateProcessor $templateProcessor, array $valores, string $primerEnc): TemplateProcessor
{
  $numFilas = count($valores);
  $templateProcessor->cloneRow($primerEnc, $numFilas);
  foreach ($valores as $numFila => $fila) {
    foreach ($fila as $celda) {
      $numPlace = $numFila + 1;
      $placeholder = key($celda);
      $valor = current($celda);
      $templateProcessor->setValue("$placeholder#$numPlace", $valor);
    }
  }
  //$templateProcessor->cloneRowAndSetValues($primerEnc, $valores);
  return $templateProcessor;
}
function creaFirma(TemplateProcessor $templateProcessor, array $valores, string $ruta): TemplateProcessor
{
  $rutaImagen = creaFirmaTemp($valores["uuid"]);
  $templateProcessor->setImageValue('firma_imagen', $rutaImagen);
  $templateProcessor->setValue('firma_nombre', $valores["nombre"]);
  $templateProcessor->setValue('firma_cargo', $valores["cargo"]);
  unlink($rutaImagen); // Borra la firma temporal
  return $templateProcessor;
}
function creaFirmaTemp(string $uuid): string
// Descarga de OpenKM un archivo de firma y lo guarda temporalmente
{
  $firma = getArchivo($uuid);
  $nombre = "firma_" . $uuid . ".png";
  $ruta = TEMP_DIR . $nombre;
  $file = fopen($ruta, 'w');
  if ($file) {
    fwrite($file, $firma);
    fclose($file);
    return $ruta;
  }
  return "Error: no se pudo guardar la firma temporal";
}
function esComplejo(string $texto): bool
// Define si un párrafo de texto es complejo (tiene diversos estilos)
{
  $patron = '/<(em|strong|b|u|i|s|strike)>.*?<\/\1>/i';
  return preg_match($patron, $texto) === 1;
}
function htmlToTextRun(string $texto): TextRun
// Convierte un texto con etiquetas HTML a un TextRun de PhpWord
{
  $textRun = new TextRun();
  $posicion = 0;
  $patron = '/<(strong|b|em|i|u|s|strike)>(.*?)<\/\1>/i';
  preg_match_all($patron, $texto, $coincidencias, PREG_OFFSET_CAPTURE);
  foreach ($coincidencias[0] as $key => $match) {
    $inicioEtiqueta = $match[1];
    $longitudEtiqueta = strlen($match[0]);
    $textoAntes = substr($texto, $posicion, $inicioEtiqueta - $posicion);
    if (!empty($textoAntes)) {
      $textRun->addText($textoAntes);
    }
    $etiqueta = strtolower($coincidencias[1][$key][0]);
    $contenidoEtiqueta = $coincidencias[2][$key][0];
    switch ($etiqueta) {
      case 'strong':
      case 'b':
        $textRun->addText($contenidoEtiqueta, ['bold' => true]);
        break;
      case 'em':
      case 'i':
        $textRun->addText($contenidoEtiqueta, ['italic' => true]);
        break;
      case 'u':
        $textRun->addText($contenidoEtiqueta, ['underline' => 'single']);
        break;
      case 's':
      case 'strike':
        $textRun->addText($contenidoEtiqueta, ['strikethrough' => true]);
        break;
    }
    $posicion = $inicioEtiqueta + $longitudEtiqueta;
  }
  $textoRestante = substr($texto, $posicion);
  if (!empty($textoRestante)) {
    $textRun->addText($textoRestante);
  }
  return $textRun;
}
// Usa LibreOffice
function convierteExcelToPDF(string $excel, string $dir): array
{
  global $dataInit;
  $fileInfo = pathinfo($excel);
  $nombreFile = $fileInfo["filename"];
  $libreofficePath = $dataInit["libreofficePath"];
  $comando = escapeshellcmd(
    "$libreofficePath --headless  --convert-to pdf:calc_pdf_Export --outdir " .
      escapeshellarg($dir) . " " . escapeshellarg("$dir/$excel") . " 2>&1"
  );
  $salida = exec($comando, $output, $rtn);
  if (false === $salida || 0 !== $rtn) $err = implode("\n", $output);
  if (file_exists("$dir/$nombreFile.pdf")) {
    return ["error" => null, "salida" => "$dir/$nombreFile.pdf"];
  } else {
    return ["error" => $err, "salida" => $salida, "rtn" => $rtn];
  }
}
function convierteWordToPDF(string $word = "certificado.docx", string $dir = "./"): array
{
  return convierteOfficeToPDF($word, $dir, "writer_pdf_Export");
}
function convierteOfficeToPDF(string $file, string $dir, string $handler): array
{
  global $dataInit;
  $fileInfo = pathinfo($file);
  $nombreFile = $fileInfo["filename"];
  $libreofficePath = $dataInit["libreofficePath"];
  $comando = escapeshellcmd(
    "$libreofficePath --headless  --convert-to pdf:$handler --outdir " .
      escapeshellarg($dir) . " " . escapeshellarg($file) . " 2>&1"
  );
  $salida = exec($comando, $output, $rtn);
  if (false === $salida || 0 !== $rtn) $err = implode("\n", $output);
  if (file_exists("$nombreFile.pdf")) {
    return ["error" => null, "salida" => "$nombreFile.pdf"];
  } else {
    return ["error" => $err, "salida" => $salida, "rtn" => $rtn];
  }
}
// De uso general
function cors() // Genera acceso a todo CORS
{
  if (isset($_SERVER['HTTP_ORIGIN'])) {
    header("Access-Control-Allow-Origin: {$_SERVER['HTTP_ORIGIN']}");
    header('Access-Control-Allow-Credentials: true');
    header('Access-Control-Max-Age: 86400');
  }
  if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    if (isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_METHOD']))
      header("Access-Control-Allow-Methods: GET, POST, OPTIONS, PUT, DELETE");
    if (isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS']))
      header("Access-Control-Allow-Headers: {$_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS']}");
    exit(0);
  }
}
function getCoincidencias($array1, $array2) // Los elementos que coinciden entre dos arrays
{
  $array1 = array_unique($array1);
  $array2 = array_unique($array2);
  $matches = array_intersect($array1, $array2);
  return $matches;
}
function esArraySimple($array) // TRUE si es un array simple, no asociativo ni stdClass
{
  return is_array($array) && array_key_first($array) === 0;
}
function arrayToCell($array, $colFija = false, $linFija = false) // Para Phpspreadsheet -> convierte un array de posición [column, row] en una celda A1
{
  return ($colFija ? "$" : "") . chr($array[0] + 64) . ($linFija ? "$" : "") . $array[1];
}
function textoAnombreCarpeta(string $texto): string // Transforma cualquier texto en NOMBRE_DE_CARPETA
{
  $reemplazos = [
    'á' => 'a',
    'é' => 'e',
    'í' => 'i',
    'ó' => 'o',
    'ú' => 'u',
    'Á' => 'A',
    'É' => 'E',
    'Í' => 'I',
    'Ó' => 'O',
    'Ú' => 'U',
    'ñ' => 'n',
    'Ñ' => 'N',
    'ü' => 'u',
    'Ü' => 'U'
  ];
  $texto = strtr($texto, $reemplazos);
  $texto = strtoupper($texto);
  $texto = str_replace(' ', '_', $texto);
  return $texto;
}
function extraeElemento(array $array, string $campo, string $busqueda): array | null
// Extrae de un array de arrays asociativos cuando hay coincidencia en alguno de los campos
{
  foreach ($array as $elemento) {
    if (isset($elemento[$campo]) && $elemento[$campo] === $busqueda) {
      return $elemento;
    }
  }
  return null; // Devuelve null si no se encuentra el label
}
function arrayMatch(array $array, string $fragmento): bool
{
  if (count($array) < 0) return false;
  foreach ($array as $elemento) {
    if (str_contains($elemento, $fragmento)) return true;
  }
  return false;
}
function csvToJsonByColumns($csvArray)
{
  if (count($csvArray) < 2) {
    return json_encode([]);
  }
  $headers = $csvArray[0];
  $jsonResult = [];
  foreach ($headers as $header) $jsonResult[$header] = [];
  for ($i = 1; $i < count($csvArray); $i++) {
    $row = $csvArray[$i];
    foreach ($headers as $index => $header) {
      if (isset($row[$index])) {
        $jsonResult[$header][] = $row[$index];
      } else {
        $jsonResult[$header][] = null;
      }
    }
  }
  return json_encode($jsonResult, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
}

// --- Handlers para el enrutador ---

const ROOT_TAX = "/okm:root/RUND/";
const ROOT_CTG = "/okm:categories/RUND/";
const ROOT_TAX_DOCS = ROOT_TAX . "DOCUMENTOS/";
const ROOT_CTG_DOCS = ROOT_CTG . "DOCUMENTOS/";
const ROOT_TAX_PROF = ROOT_TAX . "DOCENTES/";
const ROOT_CTG_PROF = ROOT_CTG . "DOCENTES/";
const RUTA_PLANTILLAS = "plantillas/";
const RUTA_CERTIFICADOS = RUTA_PLANTILLAS . "certificados/";
const RUTA_FIRMAS = ROOT_TAX_DOCS . "FIRMAS/";
const RUTA_LISTADOS = ROOT_TAX_DOCS . "LISTADOS/";
const CTGR_LISTADOS = ROOT_CTG_DOCS . "LISTADOS/";
const CTGR_FIRMAS = ROOT_CTG_DOCS . "FIRMAS/";
const RUTA_HOJAS = ROOT_TAX_PROF . "HOJAS_DE_VIDA/";
const CTGR_DOCS_HOJAS = ROOT_CTG_DOCS . "HOJAS_DE_VIDA/";

function handleGetCategorias(): array // Devuelve un árbol de categorias (no Taxonomía) desde la categoría principal
{
  global $base;
  $respuesta = [];
  $respuesta["uuidCat_URL"] = $base . REST . "repository/getCategoriesFolder";
  $uuidCat = json_decode(consulta("repository/getCategoriesFolder"), true)["uuid"];
  $respuesta["consulta_total"] = json_decode(consulta("repository/getCategoriesFolder"), true);
  $respuesta["uuidCat"] = $uuidCat;
  $respuesta["tieneHijos_URL"] = $base . REST . "folder/getChildren?fldId=$uuidCat";
  $tieneHijos = json_decode(consulta("folder/getProperties?fldId=$uuidCat"), true)["hasChildren"];
  if ($tieneHijos) {
    $uuidRUND = json_decode(consulta("folder/getChildren?fldId=$uuidCat"), true)["folder"]["uuid"];
    $carpetas = getArbolCarpetas($uuidRUND);
    return $carpetas;
  } else {
    $respuesta["error"] = "La categoría principal no tiene nodos hijos.";
    return $respuesta;
  }
}
function handleGetCruce(string $x, string $y): array // Cruza categorías y devuelve una matriz de dos dimensiones
{
  if (!isset($_GET['x']) || !isset($_GET['y'])) {
    return ["error" => "Faltan parámetros para getCruce"];
  }
  $subCatX = getUUIDHijos($x);
  $subCatY = getUUIDHijos($y);
  $filas = [];
  $allDocsX = [];
  foreach ($subCatX as $catX) $allDocsX[] = getDocsFromCat($catX["uuid"]);
  foreach ($subCatY as $catY) {
    $docsY = getDocsFromCat($catY["uuid"]);
    $intData = [];
    foreach ($allDocsX as $docsX) {
      $intData[] = count(getCoincidencias($docsX, $docsY));
    }
    $fila = ["label" => $catY["label"], "data" => $intData];
    $filas[] = $fila;
  }
  $data = [
    "nomCol" => getPathGeneraLabel($x),
    "nomFil" => getPathGeneraLabel($y),
    "cols" => array_map("getLabels", $subCatX),
    "filas" => $filas
  ];
  return $data;
}
function handleGetCsvData(array $params): array
{
  if (!isset($params["categoria"]) || !isset($params["tipo"]) || !isset($params["nombre"]) || !isset($params["extension"])) {
    return ["error" => "Faltan parámetros para getCsvData"];
  }

  $path = ROOT_TAX_DOCS . textoAnombreCarpeta($params["categoria"] . "/" . $params["tipo"]);
  $nombreArchivo = $params["nombre"] . $params["extension"];
  $query = "search/find?name=" . urlencode($nombreArchivo) . "&path=" . urlencode($path);
  $resp = json_decode(consulta($query), true);

  if ($resp) {
    $nodo = (esArraySimple($resp["queryResult"])) ? $resp["queryResult"][0]["node"] : $resp["queryResult"]["node"];
    if ($nodo["path"] == "$path/$nombreArchivo") {
      $headers = ["Accept: application/octet-stream"];
      $uuid = $nodo["uuid"];
      $consulta = "document/getContent?docId=$uuid";
      $csvContent = consulta($consulta, "GET", null, $headers);
      $regex = '/([^\\"]|)(\\n)+([^\\"])/m';
      $doble = '/  /m';
      $csvContent = preg_replace($regex, "$1 $3", $csvContent);
      $csvContent = preg_replace($doble, " ", $csvContent);
      $lines = explode(PHP_EOL, $csvContent);
      $respCSV = [];
      foreach ($lines as $line) {
        if (!empty(trim($line))) $respCSV[] = str_getcsv($line);
      }
      $jsonCSV = csvToJsonByColumns($respCSV);
      // Genera una respuesta correcta, con el CSV convertido a JSON
      return [
        "arrayCSV" => $respCSV,
        "columnasCSV" => json_decode($jsonCSV, true),
        "rawCSV" => $csvContent,
      ];
    } else {
      return ["error" => "La ruta del archivo encontrado " . $nodo["path"] . " no coincide exactamente con la proporcionada $path/$nombreArchivo"];
    }
  }
  return ["error" => "No se encontró el CSV solicitado"];
}
function handleGetCertificado(array $postData): void
{
  $estructura = json_decode($postData["data"], true);
  $plantilla = $postData["plantilla"];
  $tipo = $postData["tipo"];
  $nombrePlantilla = "$plantilla.docx";
  $phpTemplate = creaCertificado(RUTA_CERTIFICADOS, $nombrePlantilla, $estructura);

  if ($tipo == "docx") {
    header("Content-Description: File Transfer");
    header('Content-Disposition: attachment; filename="' . $nombrePlantilla . '"');
    header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
    header('Content-Transfer-Encoding: binary');
    header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
    header('Expires: 0');
    $phpTemplate->saveAs("php://output");
  } elseif ($tipo == "pdf") {
    $nombreDOCX = "certificado_" . (new DateTime())->format("Y-m-d-H-i-s") . ".docx";
    $phpTemplate->saveAs($nombreDOCX);
    $resp = convierteWordToPDF($nombreDOCX);
    if (null == $resp["error"]) {
      $pdfFilePath = $resp["salida"];
      header('Content-Type: application/pdf');
      header('Content-Disposition: attachment; filename="' . basename($pdfFilePath) . '"');
      header('Content-Length: ' . filesize($pdfFilePath));
      readfile($pdfFilePath);
      unlink($pdfFilePath);
      unlink($nombreDOCX);
    }
  }
}
function handleGetFirmas(array $getParams): void
{
  if (!isset($getParams["uuid"]) || !isset($getParams["mimeType"])) {
    header('Content-Type: application/json; charset=utf-8');
    print json_encode(getFirmas(RUTA_FIRMAS));
  } else {
    $respuesta = getArchivo($getParams["uuid"]);
    header("Content-Type: " . $getParams["mimeType"]);
    print $respuesta;
  }
}
function handleGetConsultaFile(array $postData, string $tipo): void
{
  $nombreHoja = "ConsultaRUND";

  // Distribuye las variables que entraron como $postData;
  $dataCols = $postData["cols"];
  $numTotalCols = count($dataCols);
  $dataFilas = $postData["filas"];
  $numTotalFilas = count($dataFilas);
  $dataNomFil = $postData["nomFil"];
  $dataNomCol = $postData["nomCol"];

  // Carga una plantilla
  $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load(__DIR__ . "/docs/plantilla_reporte.xlsx");
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
    ->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER)
    ->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER);
  $hoja->getStyle(arrayToCell($inicio) . ":" . arrayToCell($posNomCol))->getFont()->setBold(true);
  $hoja->getStyle(arrayToCell($inicio) . ":" . arrayToCell($posLabel))->getFont()->setBold(true);
  $hoja->getStyle(arrayToCell($inicio) . ":" . arrayToCell($posData))->getBorders()->getAllBorders()
    ->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);
  autoFitCols($hoja);
  // Crea las variables para crear el Gráfico.
  $valores = [];
  $etiquetas = [];
  for ($i = 0; $i < $numTotalCols; $i++) {
    $ini = [$posIniData[0] + $i, $posIniData[1]];
    $fin = [$posIniData[0] + $i, $posIniData[1] + ($numTotalFilas - 1)];
    $rangoVal = $nombreHoja . "!" . arrayToCell($ini, true, true) . ":" . arrayToCell($fin, true, true);
    $valores[] = new \PhpOffice\PhpSpreadsheet\Chart\DataSeriesValues("Number", $rangoVal, null, $numTotalFilas);
    $posEti = [$posIniCol[0] + $i, $posIniCol[1] + 1];
    $rangoEti = $nombreHoja . "!" . arrayToCell($posEti, true, true);
    $etiquetas[] = new \PhpOffice\PhpSpreadsheet\Chart\DataSeriesValues("String", $rangoEti, null, 1);
  }
  $rangoCat = $nombreHoja . "!" . arrayToCell($posIniLabel, true, true) . ":" . arrayToCell([$posIniLabel[0], $posIniLabel[1] + ($numTotalFilas - 1)], true, true);
  $categorias = [new \PhpOffice\PhpSpreadsheet\Chart\DataSeriesValues("String", $rangoCat, null, $numTotalFilas)];
  $titulo = "Distribución por " . $dataNomCol . " según " . $dataNomFil;
  $posIniGrafico = [$posIniLabel[0], $posFinCol[1] + $numTotalFilas + 4];
  $posFinGrafico = [$posIniGrafico[0] + 9, $posIniGrafico[1] + 12];
  $posGrafico = [arrayToCell($posIniGrafico), arrayToCell($posFinGrafico)];
  creaGraficoBarras($valores, $categorias, $etiquetas, $titulo, $hoja, $posGrafico);
  $spreadsheet->getActiveSheet()->getPageSetup()->setFitToWidth(1);
  $spreadsheet->getActiveSheet()->getPageSetup()->setFitToHeight(0);
  $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
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
      $writer->save(TEMP_DIR . "reporte.xlsx");
      $resp = convierteExcelToPDF("reporte.xlsx", TEMP_DIR);
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
function handleDeleteReport(): array
{
  $aBorrar = ["reporte.xlsx", "reporte.pdf"];
  $borrados = [];
  foreach ($aBorrar as $archivo) {
    if (file_exists(TEMP_DIR . $archivo)) {
      unlink(TEMP_DIR . $archivo);
      $borrados[] = $archivo;
    }
  }
  return ["borrados" => $borrados, "aBorrar" => $aBorrar];
}
function handleLoadList(string $method, array $params, array $files): array
{
  $salida = [];
  $accion = $params["accion"];
  $propiedades = json_decode(html_entity_decode($params["propiedades"], ENT_QUOTES | ENT_HTML5, 'UTF-8'), true);
  $nombreArchivo = extraeElemento($propiedades, "label", "Nombre")["valor"];
  $tipo = textoAnombreCarpeta(extraeElemento($propiedades, "label", "Tipo")["valor"]);
  $path = RUTA_LISTADOS . $tipo;
  $query = "search/find?name=" . urlencode($nombreArchivo) . "&path=" . urlencode($path);

  switch ($accion) {
    case "cargar":
      if ($method == 'POST' && isset($files['archivo'])) {
        $dupe = extraeElemento($propiedades, "label", "Duplicado")["valor"];
        $salida = cargaArchivo($files["archivo"], $propiedades, $path, $dupe);
        if (!$dupe) {
          $uuid = json_decode(consulta($query), true)["queryResult"]["node"]["uuid"];
          $tipo = textoAnombreCarpeta(extraeElemento($propiedades, "label", "Tipo")["valor"]);
          $origen = textoAnombreCarpeta(extraeElemento($propiedades, "label", "Origen")["valor"]);
          $formato = textoAnombreCarpeta(extraeElemento($propiedades, "label", "Formato")["valor"]);
          $categorias = [
            ["path" => CTGR_LISTADOS . "TIPO/$tipo"],
            ["path" => CTGR_LISTADOS . "ORIGEN/$origen"],
            ["path" => CTGR_LISTADOS . "FORMATO/$formato"],
          ];
          $salida["creaCategorias"] = creaCategorias($categorias);
          $postData = ["uuid" => $uuid, "categories" => $categorias];
          $salida["respCategorias"] = consulta("document/setProperties", "PUT", $postData);
        }
      } else {
        $salida["error"] = "Para la acción 'cargar' se requiere método POST y un archivo.";
      }
      break;
    case "duplicado":
      $respQuery = json_decode(consulta($query), true);
      $duplicado = ["nombre" => false, "ruta" => false, "size" => false, "creado" => false, "uuid" => ""];
      if ($respQuery && count($respQuery)) {
        $duplicado["nombre"] = true;
        $nodo = esArraySimple($respQuery["queryResult"]) ? $respQuery["queryResult"][0]["node"] : $respQuery["queryResult"]["node"];
        if ($nodo["path"] == "$path/$nombreArchivo") $duplicado["ruta"] = true;
        if ($nodo["actualVersion"]["size"] == extraeElemento($propiedades, "label", "Size")["valor"]) $duplicado["size"] = true;
        $duplicado["creado"] = $nodo["created"];
        $duplicado["uuid"] = $nodo["uuid"];
      }
      $salida["duplicado"] = $duplicado;
      break;
  }
  return $salida;
}
function handlePostFile(array $params, array $files): array
{
  $salida = [];
  $accion = $params["accion"];
  $nombreArchivo = $files["archivo"]["name"];
  $propiedades = json_decode(html_entity_decode($params["propiedades"], ENT_QUOTES | ENT_HTML5, 'UTF-8'), true);
  array_push($propiedades, ["label" => "Nombre", "valor" => $nombreArchivo]);

  switch ($accion) {
    case "cargaFirma":
      $path = RUTA_FIRMAS;
      $dupe = yaExiste($nombreArchivo, $path);
      $cargo = textoAnombreCarpeta(extraeElemento($propiedades, "label", "cargo")["valor"]);
      $categorias = [
        ["path" => CTGR_FIRMAS . "CARGO/$cargo"],
        ["path" => CTGR_FIRMAS . "TIPO/RUND_FIRMA"],
        ["path" => CTGR_FIRMAS . "TIPO/RUND_FIRMA_SIDE-CAR"],
        ["path" => CTGR_FIRMAS . "FORMATO/PNG"],
        ["path" => CTGR_FIRMAS . "FORMATO/CSV"],
      ];
      $salida["creaCategorias"] = creaCategorias($categorias);
      $salida["cargaPNG"] = cargaArchivo($files["archivo"], $propiedades, $path, $dupe);
      $queryPNG = "search/find?name=" . urlencode($nombreArchivo) . "&path=" . urlencode($path);
      $uuidPNG = json_decode(consulta($queryPNG), true)["queryResult"]["node"]["uuid"];
      $postDataPNG = ["uuid" => $uuidPNG, "categories" => [["path" => CTGR_FIRMAS . "CARGO/$cargo"], ["path" => CTGR_FIRMAS . "TIPO/RUND_FIRMA"], ["path" => CTGR_FIRMAS . "FORMATO/PNG"]]];
      $salida["respCategoriasPNG"] = consulta("document/setProperties", "PUT", $postDataPNG);

      $nombreJSON = preg_replace('/\.png$/i', ".json", $nombreArchivo);
      $queryJSON = "search/find?name=" . urlencode($nombreJSON) . "&path=" . urlencode($path);
      $respQueryJSON = json_decode(consulta($queryJSON), true);
      $dupeJSON = $respQueryJSON && count($respQueryJSON);
      $uuidJSON = $dupeJSON ? $respQueryJSON["queryResult"]["node"]["uuid"] : null;
      $dataJSON = [
        ["label" => "cargo", "valor" => extraeElemento($propiedades, "label", "cargo")["valor"]],
        ["label" => "nombres", "valor" => extraeElemento($propiedades, "label", "nombres")["valor"]],
        ["label" => "apellidos", "valor" => extraeElemento($propiedades, "label", "apellidos")["valor"]],
        ["label" => "fecha", "valor" => extraeElemento($propiedades, "label", "fecha")["valor"]],
        ["label" => "firma", "valor" => $nombreArchivo],
      ];
      $salida["cargaJSON"] = cargaJSON($dataJSON, $nombreJSON, $path, $uuidJSON);
      $uuidJSON_actualizado = json_decode(consulta($queryJSON), true)["queryResult"]["node"]["uuid"];
      $postDataJSON = ["uuid" => $uuidJSON_actualizado, "categories" => [["path" => CTGR_FIRMAS . "CARGO/$cargo"], ["path" => CTGR_FIRMAS . "TIPO/RUND_FIRMA_SIDE-CAR"], ["path" => CTGR_FIRMAS . "FORMATO/CSV"]]];
      $salida["respCategoriasJSON"] = consulta("document/setProperties", "PUT", $postDataJSON);
      break;
    case "cargaDocumento":
      /**
       * Carga un documento en OpenKM y lo categoriza según las propiedades
       * El objeto $propiedades (que es el nodo 'propiedades' del JSON que viene del frontend) debe contener:
       * - label: Nombre del documento (se infiere del nombre del archivo, no viene en el payload)
       * - taxonomia: Ruta de la carpeta del documento (vacía si es Cédula)
       * - tipo: Tipo de documento (Cédula, etc.)
       * - formato: Formato del documento (pdf, docx, etc.)
       * - origen: Origen del documento (OneDrive, etc.)
       * - categorias: Array de categorías a las que pertenece el documento, siempre y cuando sea Cédula, de resto no es obligatoria
       * - esCedula: Booleano que indica si el documento es una cédula o no
       * - cedula: El valor string de la cédula del profesor, que será la carpeta raíz donde se almacenará el documento
      */
      $cedula = extraeElemento($propiedades, "label", "cedula")["valor"]; // Cédula del profesor, que será la carpeta raíz donde se almacenará el documento
      if (!preg_match('/^\d{4,20}$/', $cedula)) { // Verifica que la cédula sea válida
        $salida["error"] = "La cédula debe tener entre 4 y 20 dígitos.";
        break;
      }
      $ruta = extraeElemento($propiedades, "label", "taxonomia")["valor"]; // Ruta de la carpeta del documento, como viene desde el frontend
      $ruta = strlen($ruta) > 2 ? "/" . textoAnombreCarpeta($ruta) : '';
      $path = RUTA_HOJAS . $cedula . $ruta; // Ruta de la carpeta del documento, es decir, la taxonomía
      $dupe = yaExiste($nombreArchivo, $path); // Verifica si ya existe un archivo con el mismo nombre en la ruta
      $esCedula = extraeElemento($propiedades, "label", "esCedula")["valor"]; // Verifica si el documento es una cédula
      $tipoDocumento = extraeElemento($propiedades, "label", "tipo")["valor"]; // Extrae el tipo de documento (cédula o la carpeta de la tax)
      $formatoDocumento = extraeElemento($propiedades, "label", "formato")["valor"]; // Extrae el formato del documento (pdf, docx, etc.)
      $origenDocumento = extraeElemento($propiedades, "label", "origen")["valor"]; // Extrae el origen del documento (OneDrive, etc.)
      $categorias = [];
      $rutasCategorias = [];
      if ($esCedula){ // Genera las categorías a las que pertenece el documento si es la cédula
        $cats = extraeElemento($propiedades, "label", "categorias")["valor"];
        foreach($cats as $cat) $rutasCategorias[] = ROOT_CTG_PROF . $cat; // Guarda las rutas de las categorías
      }
      $rutasCategorias[] = CTGR_DOCS_HOJAS . "TIPO/" . textoAnombreCarpeta($tipoDocumento); // Añade la categoría del tipo de documento
      $rutasCategorias[] = CTGR_DOCS_HOJAS . "FORMATO/" . textoAnombreCarpeta($formatoDocumento); // Añade la categoría del formato del documento
      $rutasCategorias[] = CTGR_DOCS_HOJAS . "ORIGEN/" .textoAnombreCarpeta($origenDocumento); // Añade la categoría del origen del documento
      foreach ($rutasCategorias as $rutaCategoria) $categorias[] = ["path" => $rutaCategoria]; // Genera el array de categorías a las que pertenece el documento
      $salida["creaTaxonomia"] = creaCarpetas([$cedula . $ruta], RUTA_HOJAS); // Crea las carpetas de forma recursiva de la taxonomía en OpenKM, si no existe
      $salida["creaCategorias"] = creaCarpetas($rutasCategorias, ROOT_CTG); // Crea las categorías en OpenKM si no existen
      $salida["carga"] = cargaArchivo($files["archivo"], $propiedades, $path, $dupe); // Carga el archivo en OpenKM
      $query = "search/find?name=" . urlencode($nombreArchivo) . "&path=" . urlencode($path); // Genera la query para buscar el UUID del archivo cargado
      $uuid = json_decode(consulta($query), true)["queryResult"]["node"]["uuid"]; // Extrae el UUID del archivo cargado
      $postData = ["uuid" => $uuid, "categories" => $categorias]; // Genera el array de datos para actualizar las propiedades del documento
      $salida["respCategorias"] = consulta("document/setProperties", "PUT", $postData); // Actualiza las propiedades del documento en OpenKM
      break;
  }
  return $salida;
}
