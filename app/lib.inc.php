<?php
ini_set('display_errors', 1);
error_reporting(E_ALL ^ (E_NOTICE | E_WARNING | E_DEPRECATED));
date_default_timezone_set('America/Bogota');
require_once __DIR__ . '/vendor/autoload.php';

// Importar las clases necesarias para endroid/qr-code v6.x
use Endroid\QrCode\Color\Color;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Label\Label;
use Endroid\QrCode\Logo\Logo;
use Endroid\QrCode\RoundBlockSizeMode;
use Endroid\QrCode\Writer\PngWriter;
use Endroid\QrCode\Writer\ValidationException;

// Importar las clases necesarias para PHPOffice
use PhpOffice\PhpSpreadsheet\Chart\Chart;
use PhpOffice\PhpSpreadsheet\Chart\DataSeries;
use PhpOffice\PhpSpreadsheet\Chart\Legend;
use PhpOffice\PhpSpreadsheet\Chart\PlotArea;
use PhpOffice\PhpSpreadsheet\Chart\Title;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpWord\TemplateProcessor;
use PhpOffice\PhpWord\Element\TextRun;

const USER = "okmAdmin";
const PASSWORD = "admin";
const REST = "/services/rest/";

const TEMP_DIR = __DIR__ . "/tmp/";

// Constantes de rutas y categorías en OpenKM
const ROOT_TAX = "/okm:root/RUND/";
const ROOT_CTG = "/okm:categories/RUND/";
const ROOT_TAX_DOCS = ROOT_TAX . "DOCUMENTOS/";
const ROOT_CTG_DOCS = ROOT_CTG . "DOCUMENTOS/";
const ROOT_TAX_PROF = ROOT_TAX . "DOCENTES/";
const ROOT_CTG_PROF = ROOT_CTG . "DOCENTES/";

const CTGR_LISTADOS = ROOT_CTG_DOCS . "LISTADOS/";
const CTGR_FIRMAS = ROOT_CTG_DOCS . "FIRMAS/";
const CTGR_DOCS_HOJAS = ROOT_CTG_DOCS . "HOJAS_DE_VIDA/";

const TAX_FIRMAS = ROOT_TAX_DOCS . "FIRMAS/";
const TAX_LISTADOS = ROOT_TAX_DOCS . "LISTADOS/";
const TAX_HOJAS = ROOT_TAX_PROF . "HOJAS_DE_VIDA/";
const TAX_CERTIFICADOS = ROOT_TAX_DOCS . "CERTIFICADOS/";

const TAX_PLANTILLAS = ROOT_TAX_DOCS . "PLANTILLAS/";
const TAX_PLANTILLAS_CERTIFICADOS = TAX_PLANTILLAS . "CERTIFICADOS/";
const TAX_PLANTILLAS_REPORTES = TAX_PLANTILLAS . "REPORTES/";

const TAX_APP_DATA = ROOT_TAX_DOCS . "DATA/";
const TAX_APP_IMG = ROOT_TAX_DOCS . "IMG/";


// ---------- Funciones API OpenKM
/**
 * Obtiene los datos de achivos de datos JSON desde OpenKM.
 * @param string $tipo Tipo de archivo a obtener, por ejemplo: "labels", "categorias", "documentos", etc.
 * @return array|string Devuelve un array asociativo con los datos del JSON de datos solicitado.
 */
function getDataFile(string $nombre): array
{
  $query = "search/find?name=" . urlencode("$nombre.json") . "&path=" . urlencode(TAX_APP_DATA);
  $uuid = json_decode(consulta($query), true)["queryResult"]["node"]["uuid"];
  if ($uuid) {
    return json_decode(getArchivo($uuid), true);
  }
  return ["error" => "No se pudo obtener $nombre.json"];
}
/**
 * Devuelve directamente una imagen almacenada en OpenKM, a partir del nombre y la ruta.
 * Si no se encuentra, devuelve un JSON con el error.
 * @param string $nombre Nombre del archivo de imagen, con extensión
 * @param string $ruta Ruta completa en OpenKM donde se encuentra la imagen
 * @return void
 */
function getImageFile(string $nombre, string $ruta = TAX_APP_IMG): void
{
  $query = "search/find?name=" . urlencode($nombre) . "&path=" . urlencode($ruta);
  $rawResp = consulta($query);
  if (substr($rawResp, 0, 19) == "RepositoryException") { // No se encuentra la imagen en la ruta
    header("Content-Type: application/json");
    echo json_encode(["error" => "No se encontró la imagen $nombre en la ruta $ruta"]);
    exit();
  }
  $uuid = json_decode($rawResp, true)["queryResult"]["node"]["uuid"];
  $mimeType = json_decode(consulta("document/getProperties?docId=$uuid"), true)["mimeType"];
  $respuesta = getArchivo($uuid);
  header("Content-Type: " . $mimeType);
  print $respuesta;
}
/**
 * creaCarpetas
 * Crea carpetas para categorias o taxonomía, carpeta a carpeta, en OpenKM a partir de una ruta dada y un prefijo.
 * @param array $rutas Rutas de las carpetas a crear, por ejemplo: ["/RUTA/A_LA_NUEVA/CARPETA", "/RUTA/A_OTRA/CARPETA"]
 * @param string $prefijo Prefijo que se añadirá a la ruta, por ejemplo: "okm:root/RUND/" o "okm:categories/RUND/"
 * @return void
 */
function creaCarpetas(array $rutas, string $prefijo): array
{
  $respuesta = [];
  foreach ($rutas as $ruta) {
    $ruta = str_replace($prefijo, "", $ruta); // Elimina el prefijo de la ruta, si existe
    $path = explode("/", $ruta);
    $ruta = rtrim($prefijo, '/');
    foreach ($path as $part) { // Recorre cada parte de la ruta
      $ruta .= "/" . $part;
      $respGetNodeUuid = consulta("repository/getNodeUuid?nodePath=" . urlencode($ruta)); // Consulta si la ruta ya existe
      if (strpos($respGetNodeUuid, "PathNotFoundException") !== false) { // La carpeta no existe, se debe crear
        $respCreateSimple = consulta("folder/createSimple", "POST", $ruta);
        $respuesta[] = [
          "getNodeUuid" => $respGetNodeUuid,
          "ruta" => $ruta,
          "accion" => "folder/createSimple",
          "consulta" => $respCreateSimple,
        ];
      } else { // La carpeta ya existe
        $respuesta[] = [
          "getNodeUuid" => "La carpeta ya existe: $respGetNodeUuid",
          "ruta" => $ruta,
          "accion" => null,
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
  $nomCats = getDataFile("labels");
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
  $path = $_ENV["CORE_API_URL"] . REST . $consulta;
  if (!arrayMatch($headers, "Accept:")) array_push($headers, "Accept: application/json");
  if (!arrayMatch($headers, "Content-Type:")) array_push($headers, "Content-Type: application/json");
  $postData = (is_array($postData)) ? json_encode($postData) : $postData;
  $curl = curl_init();
  curl_setopt($curl, CURLOPT_URL, $path);
  curl_setopt($curl, CURLOPT_HTTPAUTH, CURLAUTH_BASIC); // Forzar auth básica
  curl_setopt($curl, CURLOPT_USERNAME, USER);
  curl_setopt($curl, CURLOPT_PASSWORD, PASSWORD);
  curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);

  curl_setopt($curl, CURLOPT_FRESH_CONNECT, true);
  curl_setopt($curl, CURLOPT_FORBID_REUSE, true);
  curl_setopt($curl, CURLOPT_TIMEOUT, 30);
  curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 10);
  curl_setopt($curl, CURLOPT_COOKIEJAR, ''); // Evitar cookies
  switch ($tipo) { // Determina el tipo de consulta
    case "GET":
      curl_setopt($curl, CURLOPT_HTTPGET, true);
      break;
    case "POST":
      if (!$postData) throw new Error("Se solicita una consulta POST pero no se proporciona postData");
      curl_setopt($curl, CURLOPT_POST, true);
      curl_setopt($curl, CURLOPT_POSTFIELDS, $postData);
      array_push($headers, "Content-Length: " . strlen($postData));
      break;
    case "PUT":
      if (!$postData) throw new Error("Se solicita una consulta PUT pero no se proporciona postData");
      curl_setopt($curl, CURLOPT_CUSTOMREQUEST, 'PUT');
      curl_setopt($curl, CURLOPT_POSTFIELDS, $postData);
      array_push($headers, "Content-Length: " . strlen($postData));
      break;
    case "DELETE":
      curl_setopt($curl, CURLOPT_CUSTOMREQUEST, 'DELETE');
      break;
  }
  curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
  $resp = curl_exec($curl);
  if (curl_errno($curl)) $resp = curl_error($curl);
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
  $nomCats = getDataFile("labels");
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
  $nomCats = getDataFile("labels");
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
    // Si ya existe el uuid, se extrae de $propiedades, de lo contrario se consulta a OpenKM
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
    // Se crean las carpetas (si no existen) donde se ubicará el archivo
    $folderResp = creaCarpetas([$path], ROOT_TAX); // Crea la ruta del archivo si no existe
    // Si es nueva versión, llama a nuevaVersion(), de lo contrario, hace un createSimple directo.
    $resp = $version ?
      nuevaVersion($uuid, $comentarioNV, $postData) :
      documentAction("createSimple", $postData); // Versión mínima de carga de archivos a OpenKM
    $salida = verificaCarga($resp, $salida, $folderResp);
  }
  return $salida;
}
function documentAction(string $action, array $postData): string
{
  $path = $_ENV["CORE_API_URL"] . REST . "document/$action";
  $curl = curl_init();
  curl_setopt($curl, CURLOPT_URL, $path);
  curl_setopt($curl, CURLOPT_USERNAME, USER);
  curl_setopt($curl, CURLOPT_PASSWORD, PASSWORD);
  curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($curl, CURLOPT_TIMEOUT, 30);
  curl_setopt($curl, CURLOPT_POST, true);
  curl_setopt($curl, CURLOPT_POSTFIELDS, $postData);
  curl_setopt($curl, CURLOPT_HTTPHEADER, ["Accept: application/json"]);
  $resp = curl_exec($curl);
  curl_close($curl);
  return $resp;
}
function verificaCarga(string $resp, array $salida, array | string | null $folderResp = null): array
{
  if (substr($resp, 0, 1) == '{' || substr($resp, 0, 1) == '[' || substr($resp, 0, 1) == "1") { // Verifica si la respuesta es JSON
    $salida["respuesta"] = json_decode($resp, true);
    $salida["error"] = false;
  } else {
    $salida["respuesta"] = $resp;
    $salida["error"] = "No se pudo crear el documento";
  }
  if ($folderResp) $salida["folderResp"] = $folderResp;
  return $salida;
}
function nuevaVersion(string $uuid, string $comentario, array $postData): string
// Hace un checkout / checkin cuando el documento está duplicado y se quiere crear una nueva versión del mismo
{
  consulta("document/checkout?docId=$uuid"); // Hace checkout del documento, es decir, lo marca como en uso para ser reemplazado
  $postData["comment"] = $comentario;
  $postData["docId"] = $uuid;
  // Verifica que se haya podido hacer checkout
  $respCheckOut = json_decode(consulta("document/isCheckedOut?docId=$uuid", "GET", null, ["Accept: text/plain"]), true);
  // Si se pudo hacer checkout, se hace checkin, de lo contrario se devuelve el error
  $salida = $respCheckOut ?
    //consulta("document/checkin", "POST", $postData) :
    documentAction("checkin", $postData) :
    json_encode(["error" => "No se pudo hacer checkout.", "respCheckOut" => $respCheckOut, "consulta" => "document/isCheckedOut?docId=$uuid"]);
  return $salida;
}
/**
 * Genera un JSON a partir de un array asociativo y lo carga en la $path específica
 * @param array $dataJSON Una array asociativa con la información que se convertirá a JSON
 * @param string $nombreJSON El nombre del archivo JSON
 * @param string $path Ruta completa en la taxonomía de OpenKM
 * @param string | null $uuid El UUID del archivo JSON, si ya existe o null si es nuevo. Por defecto, null.
 * @param string | null $mensaje El mensaje que se va a usar para el checkin/checkout del archivo, cuando se crea una nueva versión. Por defecto, null.
 * @return array La respuesta, como una array asociativa, del proceso de creación o nueva versión del archivo.
 */
function cargaJSON(array $dataJSON, string $nombreJSON, string $path, string | null $uuid = null, string | null $mensaje = null): array
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
    nuevaVersion($uuid, "Modificado " . date("Y-m-d H:i:s") . ($mensaje ? " => " . $mensaje : ""), $postData) :
    documentAction("createSimple", $postData);
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
/**
 * Añade un objeto a un documento JSON que DEBE ser un array de objetos.
 * En caso de que no existe en OpenKM, se generará como nuevo archivo, con la información proporcionada
 * @param string $nombre El nombre completo del JSON, con extensión
 * @param string $ruta La ruta completa al archivo
 * @param string $data Un objeto (array asociativa PHP) que se añadirá al array existente (o recién creado) del JSON
 * @param string $mensaje El mensaje que se usará al momento de hacer checkin/chekout en el JSON destino.
 * @return array Array asociativo generado por la función verificaCarga()
 */
function addToJSON(string $nombre, string $ruta, array $data, string $mensaje): array
{
  $uuid = findArchivo($nombre, $ruta);
  $dataJson = $uuid ? json_decode(getArchivo($uuid), true) : [];
  array_push($dataJson, $data);
  return cargaJSON($dataJson, $nombre, $ruta, $uuid, $mensaje);
}
/**
 * Encuentra un archivo a partir del nombre y ruta
 * @param string $nombre El nombre del archivo (case-sensitive) con extensión
 * @param string $ruta La ruta absoluta del archivo
 * @return string | null El UUID del archivo o, si no se encuentra, null
 */
function findArchivo(string $nombre, string $ruta): string | null
{
  $query = "search/find?name=" . urlencode($nombre) . "&path=" . urlencode($ruta);
  $uuid = json_decode(consulta($query), true)["queryResult"]["node"]["uuid"];
  return $uuid ?? null;
}
/**
 * Obtiene información a partir de los documentos del profesor
 * @param string $cedula Cédula del profesor, que es el nombre de la carpeta donde se encuentra su hoja de vida
 * @param bool $demo Indica si se quiere la información demográfica (true, por defecto) o solo la información del documento (tipo, origen, formato)
 * @return array|null Devuelve un array con la información del documento y sus categorías, o null si no se encuentra el documento
  Ejemplo de salida:
  [
    "nombre" => "hoja_de_vida.pdf",
    "categorias" => [
      ["tipo_de_doc", "origen", "formato"],
      ...
    ]
  ]
 */
function getInfoArchivosProfesor(string $cedula, bool $demo = true): array | null
{
  $buscaNombre = $demo ? "&name=" . urlencode("cedula") : "";
  $query = "search/find?path=" . urlencode(TAX_HOJAS . $cedula) . $buscaNombre;
  $resp = json_decode(consulta($query), true)["queryResult"];
  if (!$resp) return null;
  // Es información demográfica, que se obtiene de un único documento: la cédula
  if ($demo) return extraeDatosDocumento($resp["node"], TAX_HOJAS, ROOT_CTG_PROF);
  // Se solicita información de TODOS los documentos almacenados del profesor
  $datos = [];
  foreach ($resp as $el) {
    $nodo = $el["node"];
    $datos[] = extraeDatosDocumento($nodo, TAX_HOJAS, CTGR_DOCS_HOJAS);
  }
  return $datos;
}
/**
 * Extrae la información de un documento (nombre y categorias)
 * @param array $nodo Nodo del documento, tal como lo devuelve la API de OpenKM
 * @param string $tax Ruta base de la taxonomía donde se encuentra el documento
 * @param string $cat Ruta base de las categorías donde se encuentran las categorías del documento (pueden ser de documento o demográficas)
 * @return array Array con el nombre del documento y un array de arrays con las categorías asignadas al documento
 */
function extraeDatosDocumento(array $nodo, string $tax, string $cat): array
{
  // Extrae el nombre del archivo
  $path = $nodo["path"];
  $path = preg_replace("#$tax#", "", $path);
  $partes = explode("/", $path);
  $nombre = array_pop($partes);
  // Extrae las categorías (sean de documento o demográficas) del documento
  $cates = $nodo["categories"];
  $categorias = [];
  foreach ($cates as $cate) {
    $pathCate = $cate["path"];
    if (preg_match("#$cat#", $pathCate)) {
      $ruta = preg_replace("#$cat#", "", $pathCate);
      $partes = explode("/", $ruta);
      $categorias[] = $partes;
    }
  }
  return [
    "nombre" => $nombre,
    "categorias" => $categorias,
  ];
}
/**
 * Estructura un array de categorías en un array multidimensional, cambiando los nombres de los elementos por los "labels" correspondientes
 * @param array $categorias Array de categorías, cada una con 2 o 3 niveles
 * @return array Array multidimensional con las categorías estructuradas y los labels correspondientes
 */
function estructuraCategorias(array $categorias): array
{
  $resultado = [];
  $labels = getDataFile("labels"); // Los labels obtenidos de labels.json en rund-core
  foreach ($categorias as $item) {
    if (count($item) === 3) {
      // Caso de 3 niveles: [categoria][subcategoria] = valor
      $categoria = $labels[$item[0]];
      $subcategoria = $labels[$item[1]];
      $valor = $labels[$item[2]];
      // Si ya existe esta combinación categoria/subcategoria
      if (isset($resultado[$categoria][$subcategoria])) {
        // Si no es array todavía, convertirlo
        if (!is_array($resultado[$categoria][$subcategoria])) {
          $resultado[$categoria][$subcategoria] = [$resultado[$categoria][$subcategoria]];
        }
        // Añadir el nuevo valor
        $resultado[$categoria][$subcategoria][] = $valor;
      } else {
        // Primera vez que aparece esta combinación
        $resultado[$categoria][$subcategoria] = $valor;
      }
    } elseif (count($item) === 2) {
      // Caso de 2 niveles: [categoria][] = valor
      $categoria = $labels[$item[0]];
      $valor = $labels[$item[1]];
      if (!isset($resultado[$categoria])) {
        $resultado[$categoria] = [];
      }
      $resultado[$categoria][] = $valor;
    }
  }
  return $resultado;
}
// ---------------  Para PhpSpreadsheet
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
// ---------------  Para PhpWord
function creaCertificado(string $nombrePlantilla, array $estructura, string $id, string $ruta = TAX_PLANTILLAS_CERTIFICADOS): TemplateProcessor
{
  $query = "search/find?name=" . urlencode($nombrePlantilla) . "&path=" . urlencode($ruta);
  $rawResp = consulta($query);
  $uuid = json_decode($rawResp, true)["queryResult"]["node"]["uuid"];
  $respuesta = getArchivo($uuid);
  $rutaPlantilla = TEMP_DIR . $nombrePlantilla;
  file_put_contents($rutaPlantilla, $respuesta);
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
  // Crea la URL y el QR del validador
  $validacion = creaQR($id);
  $templateProcessor->setImageValue('valida_qr', $validacion["qr"]);
  $templateProcessor->setValue('valida_url', $validacion["url"]);
  unlink($validacion["qr"]); // Borra la imagen QR temporal
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
// ---------------  Usa LibreOffice
function convierteExcelToPDF(string $excel, string $dir = TEMP_DIR): array
{
  return convierteOfficeToPDF($excel, "calc_pdf_Export", $dir);
}
function convierteWordToPDF(string $word = "certificado.docx", string $dir = TEMP_DIR): array
{
  return convierteOfficeToPDF($word, "writer_pdf_Export", $dir);
}
function convierteOfficeToPDF(string $file, string $handler, string $dir = TEMP_DIR): array
{
  $fileInfo = pathinfo($file);
  $nombrePDF = $dir . $fileInfo["filename"] . ".pdf";
  $comando = escapeshellcmd(
    $_ENV["LIBREOFFICE_EXECUTABLE"] . " --headless --convert-to pdf:$handler --outdir " .
      escapeshellarg($dir) . " " . escapeshellarg($dir . $file) . " 2>/dev/null"
  );
  $salida = exec($comando, $output, $rtn);
  if (false === $salida || 0 !== $rtn) $err = implode("\n", $output);
  if (file_exists($nombrePDF)) {
    return ["error" => null, "salida" => $nombrePDF];
  } else {
    return ["error" => $err ?? "ERROR: no se pudo convertir el archivo.", "salida" => $salida, "rtn" => $rtn];
  }
}
/*************************** FUNCIONES OCR/AI ******************************/
/**
 * analizaDocumento Analiza un documento usando OCR y AI
 * @param string $filePath Ruta del archivo del documento a procesar
 * @param string $tipoDocumento Tipo de documento (ej. "certificado", "contrato", "documento de identidad", "hoja de vida")
 * @param array $datosExtraer Datos a extraer del documento
 * @param string|null $prompt Prompt personalizado para la AI (opcional)
 * @return array Resultado del análisis con OCR y AI
 */
function analizaDocumento(string $filePath, string $tipoDocumento, array $datosExtraer): array
{
  $ocrResult = extraerTextoDeDocumento($filePath);
  unlink($filePath);
  $extractedText = $ocrResult['text'];
  if ($extractedText) {
    $aiPayload = construyeAiPayload($tipoDocumento, $datosExtraer, $extractedText);
    $respuestaIA = requestAI($aiPayload);
    $aiResult = procesarRespuestaIA($respuestaIA['data']);
    $aiResult['ocr_result'] = $ocrResult;
    return $aiResult;
  }
  return $ocrResult;
}
/**
 * extraerTextoDeDocumento Extrae con OCR los textos de un documento
 * @param string $filePath Ruta del archivo del documento a procesar
 * @return array Resultado del OCR con el texto extraído
 */
function extraerTextoDeDocumento(string $filePath): array
{
  $ocrUrl = $_ENV['OCR_API_URL'] . '/extract-text';
  $curl = curl_init();
  curl_setopt($curl, CURLOPT_URL, $ocrUrl);
  curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($curl, CURLOPT_POST, true);
  curl_setopt($curl, CURLOPT_TIMEOUT, 60);
  curl_setopt($curl, CURLOPT_POSTFIELDS, ['file' => new CURLFile($filePath)]);
  curl_setopt($curl, CURLOPT_HTTPHEADER, ["Accept: application/json"]);
  $resp = curl_exec($curl);
  $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
  curl_close($curl);
  if ($httpCode === 200) {
    return json_decode($resp, true);
  } else {
    return ["error" => "Error OCR: " . $resp];
  }
}
/**
 * Realiza petición a rund-ai con manejo robusto de errores
 *
 * @param array $aiPayload Payload para rund-ai
 * @return array Respuesta procesada con status
 * @throws Exception Si hay errores en la petición
 */
function requestAI(array $aiPayload): array
{
  $aiUrl = $_ENV['AI_API_URL'] . '/api/generate';
  // Validar que el payload sea un array
  if (!is_array($aiPayload)) {
    throw new InvalidArgumentException('El payload debe ser un array');
  }
  // Validar campos requeridos
  $camposRequeridos = ['model', 'prompt'];
  foreach ($camposRequeridos as $campo) {
    if (!isset($aiPayload[$campo]) || empty($aiPayload[$campo])) {
      throw new InvalidArgumentException("Campo requerido faltante: {$campo}");
    }
  }
  // Codificar JSON con flags específicos para evitar problemas de encoding
  $jsonPayload = json_encode($aiPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
  if (json_last_error() !== JSON_ERROR_NONE) {
    throw new Exception('Error al codificar JSON: ' . json_last_error_msg());
  }
  // Configurar cURL
  $curl = curl_init();
  $curlOptions = [
    CURLOPT_URL => $aiUrl,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_TIMEOUT => 480, // Aumentado para documentos complejos
    CURLOPT_CONNECTTIMEOUT => 30,
    CURLOPT_POSTFIELDS => $jsonPayload,
    CURLOPT_HTTPHEADER => [
      'Content-Type: application/json',
      'Content-Length: ' . strlen($jsonPayload),
      'Accept: application/json'
    ],
    CURLOPT_SSL_VERIFYPEER => false, // Solo si usas HTTPS local
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_MAXREDIRS => 3,
  ];
  curl_setopt_array($curl, $curlOptions);
  // Ejecutar petición
  $aiResult = curl_exec($curl);
  $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
  $curlError = curl_error($curl);
  $curlInfo = curl_getinfo($curl);
  curl_close($curl);
  // Verificar errores de cURL
  if ($aiResult === false) {
    throw new Exception("Error de cURL: {$curlError}");
  }
  // Procesar respuesta según código HTTP
  if ($httpCode !== 200) {
    return [
      'success' => false,
      'error' => "Error HTTP {$httpCode}",
      'message' => $aiResult,
      'http_code' => $httpCode
    ];
  }
  // Decodificar respuesta JSON
  $decodedResult = json_decode($aiResult, true);
  if (json_last_error() !== JSON_ERROR_NONE) {
    return [
      'success' => false,
      'error' => 'Error al decodificar respuesta JSON: ' . json_last_error_msg(),
      'raw_response' => $aiResult
    ];
  }
  return [
    'success' => true,
    'data' => $decodedResult,
    'http_code' => $httpCode
  ];
}
/**
 * Función específica para extracción de datos con reintentos
 */
function extraerDatosIA(
  string $tipoDocumento,
  array $datosExtraer,
  string $extractedText,
  int $maxReintentos = 3
): array {
  $intento = 1;
  while ($intento <= $maxReintentos) {
    try {
      error_log("Intento {$intento} de extracción para documento: {$tipoDocumento}");
      // Construir payload
      $payload = construyeAiPayload($tipoDocumento, $datosExtraer, $extractedText);
      // Hacer petición
      $response = requestAI($payload);
      if (!$response['success']) {
        throw new Exception("Error en petición: " . $response['error']);
      }
      // Procesar respuesta específica de rund-ai
      $resultado = procesarRespuestaIA($response['data']);
      if ($resultado['success']) {
        error_log("Extracción exitosa en intento {$intento}");
        return $resultado;
      }
      // Si no fue exitoso pero no es error crítico, reintenta
      error_log("Fallo en procesamiento, reintentando... " . $resultado['error']);
      $intento++;
      // Esperar antes del siguiente intento
      if ($intento <= $maxReintentos) {
        sleep(2);
      }
    } catch (Exception $e) {
      error_log("Error en intento {$intento}: " . $e->getMessage());
      if ($intento === $maxReintentos) {
        return [
          'success' => false,
          'error' => "Falló después de {$maxReintentos} intentos: " . $e->getMessage(),
          'intento_fallido' => $intento
        ];
      }
      $intento++;
      sleep(2);
    }
  }
  return [
    'success' => false,
    'error' => "Agotados todos los reintentos ({$maxReintentos})"
  ];
}
/**
 * Función para validar la conexión con rund-ai
 */
function validarConexionIA(): array
{
  $aiUrl = $_ENV['AI_API_URL'] . '/api/tags';
  $curl = curl_init();
  curl_setopt_array($curl, [
    CURLOPT_URL => $aiUrl,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 10,
    CURLOPT_HTTPHEADER => ['Accept: application/json']
  ]);
  $result = curl_exec($curl);
  $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
  curl_close($curl);
  if ($httpCode === 200) {
    $models = json_decode($result, true);
    return [
      'success' => true,
      'models' => $models['models'] ?? [],
      'message' => 'Conexión exitosa con rund-ai'
    ];
  } else {
    return [
      'success' => false,
      'error' => "No se puede conectar a rund-ai (HTTP {$httpCode})",
      'response' => $result
    ];
  }
}
/**
 * Construye el payload para rund-ai para extracción de datos de documentos
 * @param string $tipoDocumento Tipo de documento a procesar
 * @param array $datosExtraer Array con la estructura de datos a extraer
 * @param string $extractedText Texto extraído por rund-ocr
 * @param float $temp Temperatura para la generación (0.0-1.0)
 * @param float $top Top-p para la generación (0.0-1.0)
 * @param int $numPredict Número máximo de tokens a generar
 * @param array $stopTokens Tokens de parada opcionales
 * @return array Payload completo para rund-ai
 * @throws InvalidArgumentException Si los parámetros no son válidos
 */
function construyeAiPayload(
  string $tipoDocumento,
  array $datosExtraer,
  string $extractedText,
  float $temp = 0.1,
  float $top = 0.9,
  int $numPredict = 2048,
  array $stopTokens = [],
): array {
  // Validación de parámetros
  if (empty($tipoDocumento)) {
    throw new InvalidArgumentException('El tipo de documento no puede estar vacío');
  }
  if (empty($datosExtraer)) {
    throw new InvalidArgumentException('Los datos a extraer no pueden estar vacíos');
  }
  if (empty(trim($extractedText))) {
    throw new InvalidArgumentException('El texto extraído no puede estar vacío');
  }
  if ($temp < 0.0 || $temp > 1.0) {
    throw new InvalidArgumentException('La temperatura debe estar entre 0.0 y 1.0');
  }
  if ($top < 0.0 || $top > 1.0) {
    throw new InvalidArgumentException('El valor top_p debe estar entre 0.0 y 1.0');
  }
  if ($numPredict < 1) {
    throw new InvalidArgumentException('El número de predicciones debe ser mayor a 0');
  }
  // Limpiar y preparar el texto
  $cleanedText = limpiarTextoOCR($extractedText);
  // Obtener descripción de campos específica para el tipo de documento
  $fieldsDescription = obtenerDescripcionCampos($tipoDocumento, $datosExtraer);
  // Construir el prompt estructurado a menos que se haya indicado un prompt
  $prompt = construirPromptEstructurado($tipoDocumento, $cleanedText, $fieldsDescription, $datosExtraer);
  // Agregar tokens de parada específicos para JSON
  if (empty($stopTokens)) {
    $stopTokens = obtenerStopTokensPorTipo($tipoDocumento);
  }
  // Construir payload base
  $aiPayload = [
    //'model' => 'phi3:mini',
    'model' => 'mistral',
    'prompt' => $prompt,
    'stream' => false,
    'options' => [
      'temperature' => $temp,
      'top_p' => $top,
      'num_predict' => $numPredict,
      'repeat_penalty' => 1.1,
      'top_k' => 40,
      'stop' => $stopTokens,
    ],
  ];
  return $aiPayload;
}
// Genera, a partir del tipo de documento, una serie de stop tokens
function obtenerStopTokensPorTipo(string $tipoDocumento): array
{
  $stopTokensGenericos = [
    //'```',
    '\n\nNota:',
    '\n\nImportante:',
    'Análisis adicional:',
    'Comentarios extra:',
  ];
  $stopTokensEspecificos = [
    'documento_identidad' => [
      'Verificación adicional:',
      'Datos complementarios:',
    ],
    'hoja_vida' => [
      'Recomendaciones:',
      'Sugerencias:',
    ],
    // ... más tipos
  ];
  $especificos = $stopTokensEspecificos[$tipoDocumento] ?? [];
  return array_merge($stopTokensGenericos, $especificos);
}
/**
 * Construye el prompt estructurado para la extracción de datos
 */
function construirPromptEstructurado(string $tipoDocumento, string $cleanedText, string $fieldsDescription, array $datosExtraer): string
{
  $jsonStructure = json_encode($datosExtraer, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
  $prompt = <<<PROMPT
  Eres un experto en análisis de documentos académicos colombianos. Tu tarea es extraer información específica de textos obtenidos mediante OCR.

  **TIPO DE DOCUMENTO:** {$tipoDocumento}

  **TEXTO DEL DOCUMENTO:**
  {$cleanedText}

  **DATOS A EXTRAER:**
  {$fieldsDescription}

  **ESTRUCTURA JSON ESPERADA:**
  {$jsonStructure}

  **INSTRUCCIONES CRÍTICAS:**
  1. Analiza cuidadosamente el texto proporcionado
  2. Extrae únicamente los datos solicitados de la estructura JSON
  3. Si un dato no se encuentra en el texto, usa null (no string "null")
  4. Para fechas, usa formato DD/MM/AAAA cuando sea posible
  5. Para arrays, incluye todos los elementos encontrados en el texto
  6. Mantén la precisión en nombres, números de documento y fechas
  7. Si encuentras abreviaciones comunes (ej: "Nal" = "Nacional"), expándelas
  8. Responde ÚNICAMENTE con un JSON válido, sin texto adicional antes o después
  9. No incluyas anotaciones o comentarios en el JSON; evita comentarios como // No se menciona en el texto, por lo que es nulo

  **FORMATO DE RESPUESTA EXACTO:**
  ```json
  {
  "status": "success",
  "tipo_documento": "{$tipoDocumento}",
  "datos_extraidos": {
    // Estructura completa con los datos extraídos
  },
  "confianza": "alta|media|baja",
  "observaciones": "comentarios si hay inconsistencias o datos poco claros"
  }
  ```

  Responde ahora con el JSON:
  PROMPT;

  return $prompt;
}
/**
 * Obtiene la descripción específica de campos según el tipo de documento
 */
function obtenerDescripcionCampos(string $tipoDocumento, array $datosExtraer): string
{
  $descripciones = [
    'documento_identidad' => [
      'nombres' => 'Nombres completos de la persona (solo nombres, no apellidos)',
      'apellidos' => 'Apellidos completos de la persona (solo apellidos, no nombres)',
      'numero_documento' => 'Número de identificación (solo números, sin puntos ni espacios)',
      'fecha_nacimiento' => 'Fecha de nacimiento en formato DD/MM/AAAA',
      'fecha_expedicion' => 'Fecha de expedición del documento en formato DD/MM/AAAA',
      'lugar_nacimiento' => 'Ciudad y/o departamento de nacimiento',
      'lugar_expedicion' => 'Ciudad y/o departamento donde se expidió el documento'
    ],

    'hoja_vida' => [
      'datos_personales' => 'Información personal: nombres, apellidos, documento, teléfono, email, dirección',
      'formacion_academica' => 'Array con estudios: título, institución, año de graduación, nivel académico',
      'experiencia_laboral' => 'Array con trabajos: cargo, empresa, fecha inicio, fecha fin, funciones principales',
      'idiomas' => 'Array con idiomas: idioma, nivel (básico/intermedio/avanzado/nativo)',
      'referencias' => 'Array con referencias: nombre completo, cargo, teléfono, email, empresa'
    ],

    'certificado_experiencia_laboral' => [
      'empleado' => 'Datos del empleado: nombres, apellidos, número de documento',
      'empresa' => 'Datos de la empresa: nombre/razón social, NIT, representante legal',
      'experiencia' => 'Detalles del empleo: cargo, fechas de inicio y fin, duración, tipo de contrato, funciones realizadas'
    ],

    'certificado_experiencia_docente' => [
      'docente' => 'Datos del docente: nombres, apellidos, documento',
      'institucion' => 'Datos de la institución: nombre, tipo (universidad/colegio/instituto), NIT',
      'experiencia_docente' => 'Detalles docentes: asignaturas, programas, fechas, modalidad, nivel educativo'
    ],

    'articulo_productividad' => [
      'articulo' => 'Datos del artículo: título, autores, revista, ISSN, año, volumen, número, páginas, DOI',
      'clasificacion' => 'Clasificación académica: categoría Minciencias, área de conocimiento, tipo de artículo'
    ],

    'certificado_investigacion' => [
      'investigador' => 'Datos del investigador: nombres, apellidos, documento',
      'proyecto' => 'Datos del proyecto: título, código, entidad financiadora, fechas, presupuesto, rol',
      'clasificacion' => 'Clasificación: área de conocimiento, grupo de investigación, línea de investigación'
    ],

    'certificado_idiomas' => [
      'estudiante' => 'Datos del estudiante: nombres, apellidos, documento',
      'certificacion' => 'Datos del certificado: idioma, nivel, marco de referencia, puntaje, fechas, institución',
      'habilidades' => 'Niveles por habilidad: lectura, escritura, escucha, habla'
    ],

    'certificado_estudios_no_formales' => [
      'participante' => 'Datos del participante: nombres, apellidos, documento',
      'curso' => 'Datos del curso: nombre, tipo, área, intensidad horaria, fechas, modalidad',
      'institucion' => 'Datos de la institución: nombre, tipo, registro oficial',
      'certificacion' => 'Datos del certificado: número, fecha expedición, vigencia'
    ]
  ];
  $tipoNormalizado = strtolower(str_replace([' ', '-'], '_', $tipoDocumento));
  $descripcionCampos = $descripciones[$tipoNormalizado] ?? [];
  if (empty($descripcionCampos)) {
    // Descripción genérica si no se encuentra el tipo específico
    return "Extrae todos los campos especificados en la estructura JSON proporcionada";
  }
  $descripcionTexto = "";
  foreach ($descripcionCampos as $campo => $descripcion) {
    $descripcionTexto .= "- {$campo}: {$descripcion}\n";
  }
  return trim($descripcionTexto);
}
/**
 * Limpia y normaliza el texto extraído por OCR
 */
function limpiarTextoOCR(string $texto): string
{
  // Eliminar caracteres de control y espacios extra
  $texto = preg_replace('/[\x00-\x1F\x7F]/u', '', $texto);
  $texto = preg_replace('/\s+/', ' ', $texto);
  // Corregir caracteres comunes mal interpretados por OCR
  $correcciones = [
    'l' => '1',  // En contextos numéricos
    'O' => '0',  // En contextos numéricos
    '|' => '1',  // Pipes interpretados como 1
    'º' => '°',  // Símbolos de grado
    '№' => 'No.', // Número
    'С' => 'C',  // C cirílica por C latina
  ];
  // Aplicar correcciones contextuales
  foreach ($correcciones as $incorrecto => $correcto) {
    $texto = str_replace($incorrecto, $correcto, $texto);
  }
  // Normalizar espacios y saltos de línea
  $texto = preg_replace('/\n\s*\n/', "\n\n", $texto); // Dobles saltos
  $texto = trim($texto);
  return $texto;
}
/**
 * Procesa la respuesta de rund-ai
 */
function procesarRespuestaIA(array $response): array
{
  try {
    if (!isset($response['response'])) {
      throw new Exception('Respuesta de API inválida: falta campo response');
    }
    $responseText = $response['response'];
    // Extraer JSON de la respuesta
    $jsonData = extraerJsonDeRespuesta($responseText);
    // Validar estructura básica
    if (!isset($jsonData['status']) || !isset($jsonData['datos_extraidos'])) {
      throw new Exception('Estructura de respuesta JSON inválida');
    }
    return [
      'success' => true,
      'error' => null,
      'data' => $jsonData,
      'raw_response' => $responseText
    ];
  } catch (Exception $e) {
    return [
      'success' => false,
      'error' => $e->getMessage(),
      'raw_response' => $response['response'] ?? 'No response'
    ];
  }
}
/**
 * Extrae JSON válido de la respuesta del modelo
 */
function extraerJsonDeRespuesta(string $response): array
{
  // Buscar el primer { y el último }
  $startPos = strpos($response, '{');
  $endPos = strrpos($response, '}');
  if ($startPos === false || $endPos === false || $startPos >= $endPos) {
    throw new Exception('No se encontró JSON válido en la respuesta');
  }
  $jsonString = substr($response, $startPos, $endPos - $startPos + 1);
  // Intentar decodificar JSON
  $decoded = json_decode($jsonString, true);
  if (json_last_error() !== JSON_ERROR_NONE) {
    throw new Exception('Error al decodificar JSON: ' . json_last_error_msg());
  }
  return $decoded;
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
/**
 * Genera un ID aleatorio de 16 caracteres a partir de una función SHA-256
 * @param array $actualData Un array asociativo con objetos que contengan una key 'id', para evitar duplicados.
 * @return string Una cadena alfanumérica ASCII de 16 caracteres con un ID único.
 */
function generaID(array $actualData = []): string
{
  $idsExistentes = array_flip(array_column($actualData, 'id'));
  do {
    $bytes = random_bytes(8); // 8 bytes = 16 caracteres hex
    $id = bin2hex($bytes);
  } while (isset($idsExistentes[$id]));
  return $id;
}
/**
 * Elimina múltiples archivos, usando patrones, tales como *, ?, {txt, log} y demás patrones de la función glob()
 * @param string $nombre El nombre, incluyendo patrones o comodines, de los archivos que se quieren borrar
 * @return array Un array asociativo con la llave "error". Si el valor de dicha llave es null, se ha ejecutado la acción sin problemas
 */
function borrarMultiplesArchivos(string $nombre): array
{
  // Obtener la lista de archivos que coinciden con el patrón
  $archivos = glob($nombre);
  // Verificar si glob tuvo un error (retorna false)
  if ($archivos === false) {
    return ['error' => 'Error al procesar el patrón.'];
  }
  // Si no hay archivos coincidentes, no hay acción
  if (empty($archivos)) {
    return ['error' => null];
  }
  // Intentar eliminar cada archivo
  foreach ($archivos as $archivo) {
    // Saltar directorios (unlink falla con directorios)
    if (is_dir($archivo)) {
      continue;
    }
    // Intentar borrar el archivo y capturar errores
    if (!@unlink($archivo)) {
      $error = error_get_last();
      return ['error' => $error['message'] ?? "Error desconocido al borrar: $archivo"];
    }
  }
  return ['error' => null];
}
/**
 * Busca en un array de arrays asociativos por el campo 'id'
 * @param array $array El array de arrays asociativos
 * @param string $id El valor 'id' que se busca
 * @return array El objeto que tiene un campo 'id' como el buscado o null si no existe
 */
function buscarPorId(array $array, string $id): array | null
{
  foreach ($array as $item) {
    if ($item['id'] === $id) {
      return $item;
    }
  }
  return null;
}
/**
 * Crea un código QR con una URL de validación y lo almacena en el directorio temporal
 * 
 * @param string $id Cadena alfanumérica de 16 caracteres
 * @return string Ruta completa donde se almacenó la imagen QR
 * @throws InvalidArgumentException Si el ID no tiene el formato correcto
 * @throws RuntimeException Si hay problemas al generar o guardar el QR
 */
function creaQR(string $id): array
{
  // Validar ID
  if (!preg_match('/^[a-zA-Z0-9]{16}$/', $id)) {
    throw new InvalidArgumentException('El ID debe ser una cadena alfanumérica de exactamente 16 caracteres');
  }

  // URL
  $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
  $host = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'localhost';
  $url = $protocol . '://' . $host . "/validacion?certificado=" . $id;


  $url = "http://localhost:4000/validacion?certificado=" . $id;



  $writer = new PngWriter();
  $qrCode = new QrCode(
    data: $url,
    encoding: new Encoding('UTF-8'),
    errorCorrectionLevel: ErrorCorrectionLevel::Low,
    size: 300,
    margin: 10,
    roundBlockSizeMode: RoundBlockSizeMode::Margin,
    foregroundColor: new Color(0, 0, 0),
    backgroundColor: new Color(255, 255, 255)
  );

  /*
  // Create generic logo
  $logo = new Logo(
    path: __DIR__ . '/assets/bender.png',
    resizeToWidth: 50,
    punchoutBackground: true
  );

  // Create generic label
  $label = new Label(
    text: 'Label',
    textColor: new Color(255, 0, 0)
  );
  */

  $result = $writer->write($qrCode); //, $logo, $label);

  // Validate the result
  //$writer->validateResult($result, $url);

  // Escribe
  $filepath = TEMP_DIR . "qr_" . $id . ".png";
  file_put_contents($filepath, $result->getString());

  return ["qr" => $filepath, "url" => $url];
}
// --- Handlers para el enrutador ---
function handleGetCategorias(): array // Devuelve un árbol de categorias (no Taxonomía) desde la categoría principal
{
  $respuesta = [];
  $respuesta["uuidCat_URL"] = $_ENV["CORE_API_URL"] . REST . "repository/getCategoriesFolder";
  $uuidCat = json_decode(consulta("repository/getCategoriesFolder"), true)["uuid"];
  $respuesta["consulta_total"] = json_decode(consulta("repository/getCategoriesFolder"), true);
  $respuesta["uuidCat"] = $uuidCat;
  $respuesta["tieneHijos_URL"] = $_ENV["CORE_API_URL"] . REST . "folder/getChildren?fldId=$uuidCat";
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
      $csvContent = consulta("document/getContent?docId=$uuid", "GET", null, $headers);
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
  // Genera un ID aleatorio para la solicitud y permitir que se pueda recuperar después, a menos que se envíe en el payload
  $nomJson = "expedidos.json";
  $uuidJSON = findArchivo($nomJson, TAX_CERTIFICADOS);
  $dataJson = $uuidJSON ? json_decode(getArchivo($uuidJSON), true) : [];
  // Verifica si es necesario crear un nuevo ID
  $crearNuevoId = true;
  if (isset($postData["id"])) $crearNuevoId = buscarPorId($dataJson, $postData["id"]) === null; // El id no existe en "expedidos.json"
  if ($crearNuevoId) {
    $nuevoID = generaID($dataJson);
    $postData["id"] = $nuevoID;
    // Añade el objeto al JSON 'expedidos.json' para su posterior recuperación
    $resp = addToJSON($nomJson, TAX_CERTIFICADOS, $postData, "Añadido certificado ID:" . $nuevoID);
  }
  $nombrePlantilla = "$plantilla.docx";
  $phpTemplate = creaCertificado($nombrePlantilla, $estructura, $postData["id"]);
  if ($tipo == "docx") {
    header("Content-Description: File Transfer");
    header('Content-Disposition: attachment; filename="' . $nombrePlantilla . '"');
    header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
    header('Content-Transfer-Encoding: binary');
    header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
    header('Expires: 0');
    $phpTemplate->saveAs("php://output");
    unlink(TEMP_DIR . $nombrePlantilla);
  } elseif ($tipo == "pdf") {
    $nombreDOCX = "certificado_" . (new DateTime())->format("Y-m-d-H-i-s") . ".docx";
    $phpTemplate->saveAs(TEMP_DIR . $nombreDOCX);
    $resp = convierteWordToPDF($nombreDOCX);
    if (null == $resp["error"]) {
      $pdfFilePath = $resp["salida"];
      header('Content-Type: application/pdf');
      header('Content-Disposition: attachment; filename="' . basename($pdfFilePath) . '"');
      header('Content-Length: ' . filesize($pdfFilePath));
      readfile($pdfFilePath);
      unlink($pdfFilePath);
      unlink(TEMP_DIR . $nombreDOCX);
      unlink(TEMP_DIR . $nombrePlantilla);
      borrarMultiplesArchivos(TEMP_DIR . "certificado_*"); // Intenta borrar todos los archivos de certificados en la carpeta tmp/
    }
  }
}
function handleGetFirmas(array $getParams): void
{
  if (!isset($getParams["uuid"]) || !isset($getParams["mimeType"])) {
    header('Content-Type: application/json; charset=utf-8');
    print json_encode(getFirmas(TAX_FIRMAS));
  } else {
    $respuesta = getArchivo($getParams["uuid"]);
    header("Content-Type: " . $getParams["mimeType"]);
    print $respuesta;
  }
}
function handleGetConsultaFile(array $postData, string $tipo, string $nombrePlantilla = "plantilla_reporte.xlsx"): void
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
  $query = "search/find?name=" . urlencode($nombrePlantilla) . "&path=" . urlencode(TAX_PLANTILLAS_REPORTES);
  $uuid = json_decode(consulta($query), true)["queryResult"]["node"]["uuid"];
  $contPlantilla = getArchivo($uuid);
  $tempPlantilla = TEMP_DIR . $nombrePlantilla;
  file_put_contents($tempPlantilla, $contPlantilla);

  $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($tempPlantilla);
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
      if (file_exists($tempPlantilla)) unlink($tempPlantilla);
      exit();
      break;
    case "pdf": // Si la salida es un PDF
      // Limpiamos la carpeta tmp de archivos con los nombres que vamos a usar
      if (file_exists(TEMP_DIR . "reporte.xlsx")) unlink(TEMP_DIR . "reporte.xlsx");
      if (file_exists(TEMP_DIR . "reporte.pdf")) unlink(TEMP_DIR . "reporte.pdf");
      $writer->save(TEMP_DIR . "reporte.xlsx");
      $resp = convierteExcelToPDF("reporte.xlsx");
      if ($resp["error"] == null) {
        $pdfFilePath = $resp["salida"];
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . basename($pdfFilePath) . '"');
        header('Content-Length: ' . filesize($pdfFilePath));
        readfile($pdfFilePath);
        if (file_exists($tempPlantilla)) unlink($tempPlantilla);
        exit();
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
  $path = TAX_LISTADOS . $tipo;
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
      $path = TAX_FIRMAS;
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
      $path = TAX_HOJAS . $cedula . $ruta; // Ruta de la carpeta del documento, es decir, la taxonomía
      $dupe = yaExiste($nombreArchivo, $path); // Verifica si ya existe un archivo con el mismo nombre en la ruta
      $esCedula = extraeElemento($propiedades, "label", "esCedula")["valor"]; // Verifica si el documento es una cédula
      $tipoDocumento = extraeElemento($propiedades, "label", "tipo")["valor"]; // Extrae el tipo de documento (cédula o la carpeta de la tax)
      $formatoDocumento = extraeElemento($propiedades, "label", "formato")["valor"]; // Extrae el formato del documento (pdf, docx, etc.)
      $origenDocumento = extraeElemento($propiedades, "label", "origen")["valor"]; // Extrae el origen del documento (OneDrive, etc.)
      $categorias = [];
      $rutasCategorias = [];
      if ($esCedula) { // Genera las categorías a las que pertenece el documento si es la cédula
        $cats = extraeElemento($propiedades, "label", "categorias")["valor"];
        foreach ($cats as $cat) $rutasCategorias[] = ROOT_CTG_PROF . $cat; // Guarda las rutas de las categorías
      }
      $rutasCategorias[] = CTGR_DOCS_HOJAS . "TIPO/" . textoAnombreCarpeta($tipoDocumento); // Añade la categoría del tipo de documento
      $rutasCategorias[] = CTGR_DOCS_HOJAS . "FORMATO/" . textoAnombreCarpeta($formatoDocumento); // Añade la categoría del formato del documento
      $rutasCategorias[] = CTGR_DOCS_HOJAS . "ORIGEN/" . textoAnombreCarpeta($origenDocumento); // Añade la categoría del origen del documento
      foreach ($rutasCategorias as $rutaCategoria) $categorias[] = ["path" => $rutaCategoria]; // Genera el array de categorías a las que pertenece el documento
      $salida = [];
      $salida["creaTaxonomia"] = creaCarpetas([$cedula . $ruta], TAX_HOJAS); // Crea las carpetas de forma recursiva de la taxonomía en OpenKM, si no existe
      $salida["creaCategorias"] = creaCarpetas($rutasCategorias, ROOT_CTG); // Crea las categorías en OpenKM si no existen
      $salida["carga"] = cargaArchivo($files["archivo"], $propiedades, $path, $dupe); // Carga el archivo en OpenKM
      $query = "search/find?name=" . urlencode($nombreArchivo) . "&path=" . urlencode($path); // Genera la query para buscar el UUID del archivo cargado
      $uuid = json_decode(consulta($query), true)["queryResult"]["node"]["uuid"]; // Extrae el UUID del archivo cargado
      $postData = ["uuid" => $uuid, "categories" => $categorias]; // Genera el array de datos para actualizar las propiedades del documento
      $salida["setProperties"] = consulta("document/setProperties", "PUT", $postData); // Actualiza las propiedades del documento en OpenKM
      break;
  }
  return $salida;
}
function handleExtraeDatos(array $params, array $files): array
{
  if (!isset($params["accion"])) return ["error" => "Falta el parámetro 'accion'."];
  $accion = $params["accion"];
  switch ($accion) {
    case "documento":
      if (!isset($params["tipoDocumento"]) || !isset($params["datosExtraer"]) || !isset($files)) {
        return ["error" => "Faltan los parámetros para extraer datos."];
      }
      $fileTemp = $files["documento"]["tmp_name"];
      $filePath = TEMP_DIR . $files["documento"]["name"];
      copy($fileTemp, $filePath);
      $tipoDocumento = $params["tipoDocumento"];
      $datosExtraer = json_decode($params["datosExtraer"], true);
      return analizaDocumento($filePath, $tipoDocumento, $datosExtraer);
      break;
  }
  return ["error" => "Acción no reconocida: $accion"];
}
function handleGetFile(string $tipo, string $nombre): array | null
{
  switch ($tipo) {
    case "data":
      return getDataFile($nombre);
      break;
    case "imagen":
      getImageFile($nombre);
      return null;
      break;
    default:
      return ["error" => "No existe el parámetro 'accion' con valor $tipo"];
  }
}
function handleGetCertificadoInfo(string $id): array | null
{
  $nomJson = "expedidos.json";
  $uuidJSON = findArchivo($nomJson, TAX_CERTIFICADOS);
  $dataJson = $uuidJSON ? json_decode(getArchivo($uuidJSON), true) : [];
  return buscarPorId($dataJson, $id) ?? ["error" => "No existe el id buscado"];
}
function handleGetImagen(array $params): void
{
  $nombre = $params["nombre"];
  $ruta = ROOT_TAX_DOCS . textoAnombreCarpeta($params["ruta"]);
  getImageFile($nombre, $ruta);
}
/**
 * Devuelve la informción de documentos y demográfica de un profesor a partir de su cédula.
 * @param string $cedula Cédula del profesor
 * @return array Un objeto con la información demográfica del profesor y los documentos que están almacenados en rund-core.
 */
function handleGetInfoProfesor(string $cedula): array
{
  // Valida que la cédula esté compuesta solo por números: mínimo 4, máximo 20.
  if (!preg_match('/^\d{4,20}$/', $cedula)) return ["error" => "La cédula debe tener entre 4 y 20 dígitos."];
  $archivosProfesor = getInfoArchivosProfesor($cedula, false);
  $datosDemograficos = getInfoArchivosProfesor($cedula);
  if (null !== $datosDemograficos) {
    $datosDemograficos["categorias"] = estructuraCategorias($datosDemograficos["categorias"]);
    return ["archivosProfesor" => $archivosProfesor, "datosDemograficos" => $datosDemograficos];
  } else {
    return ["error" => null, "resultado" => "El profesor con cédula $cédula no tiene datos registrados en rund-core."];
  }
}
function handleInfo(): array
{
  /*
  $datosExtraer = [
    'nombres' => null,
    'apellidos' => null,
    'numero_documento' => null,
    'fecha_nacimiento' => null,
    'fecha_expedicion' => null,
    'lugar_nacimiento' => null,
    'lugar_expedicion' => null
  ];

  $textoOCR = "
  REPÚBLICA DE COLOMBIA
  CÉDULA DE CIUDADANÍA
  JUAN CARLOS
  RODRIGUEZ MARTINEZ
  C.C. 1234567890
  Lugar de Nacimiento: BOGOTÁ D.C.
  Fecha de Nacimiento: 15/03/1985  
  Fecha de Expedición: 22/08/2003
  ";
  $aiPayload = construyeAiPayload("documento_identidad", $datosExtraer, $textoOCR);
  $respuestaIA = requestAI($aiPayload);
  $resultado = procesarRespuestaIA($respuestaIA['data']);
*/


  $respuesta = [
    "version" => "1.0.0",
    "nombre" => "RUND API",
    "descripcion" => "API para la gestión de documentos y certificados en RUND",
    "autor" => "Oliver Castelblanco Martínez",
    /*
    "payload" => $aiPayload,
    "respuestaIA" => $respuestaIA,
    "procesadoAI" => $resultado,
    */
  ];
  return $respuesta;
}
