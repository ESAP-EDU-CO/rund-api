<?php
require_once("lib.inc.php");
cors();
const ROOT_TAX_DOCS = "/okm:root/RUND/DOCUMENTOS/";
const RUTA_PLANTILLAS = "plantillas/";
const RUTA_CERTIFICADOS = RUTA_PLANTILLAS . "certificados/";
const RUTA_FIRMAS = ROOT_TAX_DOCS . "FIRMAS/";
$_POST = json_decode(file_get_contents("php://input"), true);
if (isset($argv)) parse_str(implode('&', array_slice($argv, 1)), $_GET);
switch ($_GET['accion']) {
  case "getCategorias":
    $respuesta = getCategorias();
    break;
  case "getCruce":
    $respuesta = getCruce($_GET['x'], $_GET['y']);
    break;
  case "getCsvData":
    $path = ROOT_TAX_DOCS . textoAnombreCarpeta($_GET["categoria"] . "/" . $_GET["tipo"]);
    $nombreArchivo = $_GET["nombre"] . $_GET["extension"];
    $query = "search/find?name=" . urlencode($nombreArchivo) . "&path=" . urlencode($path);
    $resp = json_decode(consulta($query), true);
    $found = false;
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
        // preg_replace() ---> Reemplazar por espacios en blanco los \n que se encuentren entre letras; si está dentro de \"\n\" si es un salto de línea válido, no se debe reemplazar
        $lines = explode(PHP_EOL, $csvContent);
        $respCSV = [];
        foreach ($lines as $line) {
          if (!empty(trim($line))) $respCSV[] = str_getcsv($line);
        }
        $jsonCSV = csvToJsonByColumns($respCSV);
        $respuesta = json_encode([
          "arrayCSV" => $respCSV,
          "columnasCSV" => json_decode($jsonCSV, true),
          "rawCSV" => $csvContent,
        ]);
      } else {
        $respuesta = json_encode([
          "error" => "La ruta del archivo encontrado " . $nodo["path"] . " no coincide exactamente con la proporcionada $path/$nombreArchivo",
        ]);
      }
    } else {
      $respuesta = json_encode(["error" => "No se econtró el CSV solicitado"]);
    }
    break;
  case "getCertificado":
    if (isset($_GET["tipo"]) && isset($_GET["plantilla"]) && isset($_POST)) {
      $estructura = $_POST;
      $plantilla = $_GET["plantilla"];
      $tipo = $_GET["tipo"];
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
        exit;
      }
      if ($tipo == "pdf") {
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
          exit;
        }
      }
    } else {
      $respuesta = ["error" => "No se envió el payload completo"];
    }
    break;
  case "getFirmas":
    if (!isset($_GET["uuid"]) || !isset($_GET["mimeType"])) {
      $respuesta = json_encode(getFirmas(RUTA_FIRMAS));
    } else {
      $respuesta = getArchivo($_GET["uuid"]);
      if ($_GET["mimeType"] != "application/json") {
        header("Content-Type: " . $_GET["mimeType"]);
        print $respuesta;
        exit;
      }
    }
    //if ($respuesta["mimeType"] ==)
    break;
  case "deleteFile":
    if ($_SERVER['REQUEST_METHOD'] == 'DELETE') {
      $uuid = $_GET["uuid"];
      $respuesta = json_encode(["error" => null, "salida" => borraArchivo($uuid)]);
    } else {
      $respuesta = json_encode(["error" => "No se envió una solicitud DELETE"]);
    }
    break;
  default:
    $respuesta = "null";
}
header('Content-Type: application/json; charset=utf-8');
print $respuesta;
