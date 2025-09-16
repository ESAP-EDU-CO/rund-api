<?php

/**
 * RUND-API - Cliente OpenKM
 * 
 * Maneja todas las operaciones con la API REST de OpenKM
 * 
 * @author ESAP Development Team / Oliver Castelblanco Martínez oliver.castelblanco@esap.edu.co
 * @version 2.0
 * @since PHP 8.3
 */

declare(strict_types=1);

namespace RUND\Core;

use RUND\Config\Constants as Config;
use RUND\Core\Utils as Utils;

class OpenKM
{

  // =========================================================================== //
  // =========================== UTILIDADES GENÉRICAS ========================== //
  // =========================================================================== //

  /**
   * Realiza una consulta a la API REST de OpenKM
   * @param string $consulta Ruta de la consulta
   * @param string $tipo Tipo de consulta (GET, POST, PUT, DELETE)
   * @param array|string|null $postData Datos a enviar en consultas POST o PUT
   * @param array $headers Encabezados adicionales para la consulta
   * @return string Respuesta de la API
   * @throws Exception Si se solicita POST o PUT sin postData
   */
  public static function consulta(string $consulta, string $tipo = "GET", array|string|null $postData = null, array $headers = []): string
  {
    $path = $_ENV["CORE_API_URL"] . Config::REST . $consulta;
    if (!Utils::arrayMatch($headers, "Accept:")) array_push($headers, "Accept: application/json");
    if (!Utils::arrayMatch($headers, "Content-Type:")) array_push($headers, "Content-Type: application/json");
    $postData = (is_array($postData)) ? json_encode($postData) : $postData;
    $curl = curl_init();
    curl_setopt($curl, CURLOPT_URL, $path);
    curl_setopt($curl, CURLOPT_HTTPAUTH, CURLAUTH_BASIC); // Forzar auth básica
    curl_setopt($curl, CURLOPT_USERNAME, Config::USER);
    curl_setopt($curl, CURLOPT_PASSWORD, Config::PASSWORD);
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
        if (!$postData) throw new \Exception("Se solicita una consulta POST pero no se proporciona postData");
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $postData);
        array_push($headers, "Content-Length: " . strlen($postData));
        break;
      case "PUT":
        if (!$postData) throw new \Exception("Se solicita una consulta PUT pero no se proporciona postData");
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

  /**
   * Obtiene un archivo de OpenKM dado su UUID
   * @param string $uuid UUID del archivo a obtener
   * @return string Contenido del archivo
   * @throws Exception Si la consulta falla
   */
  public static function getArchivo(string $uuid): string
  {
    $headers = ["Accept: application/octet-stream"];
    return self::consulta("document/getContent?docId=" . $uuid, "GET", null, $headers);
  }

  /**
   * Encuentra un archivo a partir del nombre y ruta
   * @param string $nombre El nombre del archivo (case-sensitive) con extensión
   * @param string $ruta La ruta absoluta del archivo
   * @return string | null El UUID del archivo o, si no se encuentra, null
   */
  public static function findArchivo(string $nombre, string $ruta): string | null
  {
    $query = "search/find?name=" . urlencode($nombre) . "&path=" . urlencode($ruta);
    $uuid = json_decode(self::consulta($query), true)["queryResult"]["node"]["uuid"];
    return $uuid ?? null;
  }

  /**
   * Elimina un archivo a partir de su UUID
   * @param string $uuid UUID del archivo a eliminar
   * @return string Respuesta de la API
   */
  public static function borraArchivo(string $uuid): string
  {
    return self::consulta("document/delete?docId=" . $uuid, "DELETE");
  }

  /**
   * Vacía la papelera de OpenKM
   * @return string Respuesta de la API
   */
  public static function borraPapelera(): string
  {
    return self::consulta("repository/purgeTrash", "DELETE");
  }

  /**
   * Consulta si un archivo existe actualmente con el nombre y la ruta indicada
   * @param string $nombreArchivo Nombre del archivo a buscar
   * @param string $path Ruta donde se busca el archivo
   * @return bool True si el archivo existe, false si no
   */
  public static function yaExiste(string $nombreArchivo, string $path): bool
  {
    $query = "search/find?name=" . urlencode($nombreArchivo) . "&path=" . urlencode($path); // Cadena de búsqueda a partir del nombre y ruta
    $respQuery = json_decode(self::consulta($query), true); // Consulta si el archivo ya existe
    return $respQuery && count($respQuery); // El archivo existe o no en esa carpeta de la Taxonomía
  }

  /**
   * Genera un label, usando labels.json a partir del uuid de un documento o carpeta
   * @param string $uuid UUID del documento o carpeta
   * @return string El label correspondiente o el UUID si no se encuentra
   */
  public static function getPathGeneraLabel($uuid)
  {
    $nomCats = self::getDataFile("labels");
    return $nomCats[array_pop(explode("/", json_decode(self::consulta("folder/getProperties?fldId=$uuid"), true)["path"]))];
  }

  /**
   * Obtiene el "label" (no nativo de OpenKM) y extrae el uuid de un objeto
   * @param array $obj Objeto tal como lo devuelve la API de OpenKM
   * @return array Array con "label" y "uuid"
   */
  public static function getID(array $obj): array
  {
    $nomCats = self::getDataFile("labels");
    $partes = explode("/", $obj["path"]);
    $lastPart = array_pop($partes);
    $label = $nomCats[$lastPart];
    $uuid = $obj["uuid"];
    return ["label" => $label, "uuid" => $uuid];
  }

  /**
   * Obtiene los uuid de los "hijos" de una carpeta
   * @param string $padreUUID UUID de la carpeta padre
   * @return array Array con los uuid de los hijos
   */
  public static function getUUIDHijos(string $padreUUID): array
  {
    $hijos = json_decode(self::consulta("folder/getChildren?fldId=$padreUUID"), true)["folder"];
    if (!Utils::esArraySimple($hijos)) $hijos = [$hijos];
    return array_map("getID", $hijos);
  }

  // =========================================================================== //
  // ============================ CONFIG FILE GETTERS ========================== //
  // =========================================================================== //

  /**
   * Devuelve un archivo JSON almacenado en OpenKM, a partir del nombre y la ruta. Por defecto, los JSON de configuración.
   * @param string $nombre Nombre del archivo (sin extensión .json)
   * @param string $ruta Ruta completa en OpenKM donde se encuentra el archivo. Por defecto, la ruta de datos de configuración la aplicación.
   * @return array Devuelve un array asociativo con los datos del JSON de datos solicitado.
   */
  public static function getDataFile(string $nombre, string $ruta = Config::TAX_APP_DATA): array
  {
    $query = "search/find?name=" . urlencode("$nombre.json") . "&path=" . urlencode($ruta);
    $uuid = json_decode(self::consulta($query), true)["queryResult"]["node"]["uuid"];
    if ($uuid) {
      return json_decode(self::getArchivo($uuid), true);
    }
    return ["error" => "No se pudo obtener $nombre.json"];
  }

  /**
   * Devuelve directamente una imagen almacenada en OpenKM, a partir del nombre y la ruta.
   * Si no se encuentra, devuelve un JSON con el error.
   * @param string $nombre Nombre del archivo de imagen (con extensión, por ejemplo: "logo.png")
   * @param string $ruta Ruta completa en OpenKM donde se encuentra la imagen. Por defecto, la ruta de imágenes de la aplicación.
   * @return void (la imagen se imprime directamente en la salida estándar con el header adecuado)
   * @throws Exception Si la imagen no se encuentra (se devuelve un JSON con el error en su lugar).
   */
  public static function getImageFile(string $nombre, string $ruta = Config::TAX_APP_IMG): void
  {
    $query = "search/find?name=" . urlencode($nombre) . "&path=" . urlencode($ruta);
    $rawResp = self::consulta($query);
    if (substr($rawResp, 0, 19) == "RepositoryException") { // No se encuentra la imagen en la ruta
      header("Content-Type: application/json");
      echo json_encode(["error" => "No se encontró la imagen $nombre en la ruta $ruta"]);
      exit();
    }
    $uuid = json_decode($rawResp, true)["queryResult"]["node"]["uuid"];
    $mimeType = json_decode(self::consulta("document/getProperties?docId=$uuid"), true)["mimeType"];
    $respuesta = self::getArchivo($uuid);
    header("Content-Type: " . $mimeType);
    print $respuesta;
    exit();
  }

  // =========================================================================== //
  // ========================= CREA ARCHIVOS O CARPETAS ======================== //
  // =========================================================================== //

  /**
   * Gestiona la carga de archivos a OpenKM, incluyendo la creación de nuevas versiones si es necesario.
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
   */
  public static function cargaArchivo(array $archivo, array $propiedades, string $path, bool | null $version = null): array
  {
    $salida = ["error" => $archivo["error"]];
    if (!$archivo['error']) {
      $nombre = Utils::extraeElemento($propiedades, "label", "Nombre")["valor"] ?? $archivo["name"];
      $temp = $archivo["tmp_name"];
      $type = mime_content_type($temp);
      $fileData = new \CURLFile($temp, $type, $nombre);
      $rutaDestino = $path . "/" . $nombre; // Ruta completa del archivo en OpenKM
      $query = "search/find?name=" . urlencode($nombre) . "&path=" . urlencode($path); // Cadena de búsqueda a partir del nombre y ruta
      // Si ya existe el uuid, se extrae de $propiedades, de lo contrario se consulta a OpenKM
      $uuid = Utils::extraeElemento($propiedades, "label", "Uuid") ?
        Utils::extraeElemento($propiedades, "label", "Uuid")["valor"] :
        json_decode(self::consulta($query), true)["queryResult"]["node"]["uuid"];
      $postData = [
        "docPath" => $rutaDestino,
        "content" => $fileData
      ];
      $salida["postData"] = $postData;
      // Si no se incluye un comentario para la nueva versión, se crea uno automático
      $comentarioNV = Utils::extraeElemento($propiedades, "label", "Comentario") ?
        Utils::extraeElemento($propiedades, "label", "Comentario")["valor"] :
        "Modificado " . date("Y-m-d H:i:s");
      // Se crean las carpetas (si no existen) donde se ubicará el archivo
      $folderResp = self::creaCarpetas([$path], Config::ROOT_TAX); // Crea la ruta del archivo si no existe
      // Si es nueva versión, llama a nuevaVersion(), de lo contrario, hace un createSimple directo.
      $resp = $version ?
        self::nuevaVersion($uuid, $comentarioNV, $postData) :
        self::documentAction("createSimple", $postData); // Versión mínima de carga de archivos a OpenKM
      $salida = self::verificaCarga($resp, $salida, $folderResp);
    }
    return $salida;
  }

  /**
   * Crea carpetas para categorias o taxonomía, carpeta a carpeta, en OpenKM a partir de una ruta dada y un prefijo.
   * @param array $rutas Rutas de las carpetas a crear, por ejemplo: ["/RUTA/A_LA_NUEVA/CARPETA", "/RUTA/A_OTRA/CARPETA"]
   * @param string $prefijo Prefijo que se añadirá a la ruta, por ejemplo: "okm:root/RUND/" o "okm:categories/RUND/"
   * @return void
   */
  public static function creaCarpetas(array $rutas, string $prefijo): array
  {
    $respuesta = [];
    foreach ($rutas as $ruta) {
      $ruta = str_replace($prefijo, "", $ruta); // Elimina el prefijo de la ruta, si existe
      $path = explode("/", $ruta);
      $ruta = rtrim($prefijo, '/');
      foreach ($path as $part) { // Recorre cada parte de la ruta
        $ruta .= "/" . $part;
        $respGetNodeUuid = self::consulta("repository/getNodeUuid?nodePath=" . urlencode($ruta)); // Consulta si la ruta ya existe
        if (strpos($respGetNodeUuid, "PathNotFoundException") !== false) { // La carpeta no existe, se debe crear
          $respCreateSimple = self::consulta("folder/createSimple", "POST", $ruta);
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

  /**
   * Realiza un checkout y checkin de un documento en OpenKM para crear una nueva versión del mismo.
   * @param string $uuid UUID del documento a actualizar
   * @param string $comentario Comentario para la nueva versión
   * @param array $postData Datos del archivo a subir (docId, docPath, content)
   * @return string Respuesta de la API de OpenKM
   */
  public static function nuevaVersion(string $uuid, string $comentario, array $postData): string
  {
    self::consulta("document/checkout?docId=$uuid"); // Hace checkout del documento, es decir, lo marca como en uso para ser reemplazado
    $postData["comment"] = $comentario;
    $postData["docId"] = $uuid;
    // Verifica que se haya podido hacer checkout
    $respCheckOut = json_decode(self::consulta("document/isCheckedOut?docId=$uuid", "GET", null, ["Accept: text/plain"]), true);
    // Si se pudo hacer checkout, se hace checkin, de lo contrario se devuelve el error
    $salida = $respCheckOut ?
      //self::consulta("document/checkin", "POST", $postData) :
      self::documentAction("checkin", $postData) :
      json_encode(["error" => "No se pudo hacer checkout.", "respCheckOut" => $respCheckOut, "consulta" => "document/isCheckedOut?docId=$uuid"]);
    return $salida;
  }

  /**
   * Realiza una acción de documento en OpenKM (createSimple, checkin, etc.) usando la API REST.
   * @param string $action Acción a realizar (por ejemplo, "createSimple", "checkin", etc.)
   * @param array $postData Datos a enviar en la solicitud POST
   * @return string Respuesta de la API de OpenKM
   * @throws Exception Si la acción no es válida
   * @throws Exception Si no se proporciona postData para acciones que lo requieren
   */
  public static function documentAction(string $action, array $postData): string
  {
    $path = $_ENV["CORE_API_URL"] . Config::REST . "document/$action";
    $curl = curl_init();
    curl_setopt($curl, CURLOPT_URL, $path);
    curl_setopt($curl, CURLOPT_USERNAME, Config::USER);
    curl_setopt($curl, CURLOPT_PASSWORD, Config::PASSWORD);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl, CURLOPT_TIMEOUT, 30);
    curl_setopt($curl, CURLOPT_POST, true);
    curl_setopt($curl, CURLOPT_POSTFIELDS, $postData);
    curl_setopt($curl, CURLOPT_HTTPHEADER, ["Accept: application/json"]);
    $resp = curl_exec($curl);
    curl_close($curl);
    return $resp;
  }

  /**
   * Verifica la respuesta de una carga o actualización de archivo en OpenKM.
   * @param string $resp Respuesta cruda de la API de OpenKM
   * @param array $salida Array con la información de la carga, que se actualizará con la respuesta
   * @param array|string|null $folderResp Respuesta de la creación de carpetas, si aplica
   * @return array Array actualizado con la respuesta y el estado de error
   */
  public static function verificaCarga(string $resp, array $salida, array | string | null $folderResp = null): array
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
}
