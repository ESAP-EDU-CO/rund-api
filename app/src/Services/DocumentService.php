<?php

/**
 * RUND-API - Servicio de Gestión de Documentos
 * 
 * Maneja operaciones específicas de documentos de profesores y archivos JSON
 * 
 * @author ESAP Development Team / Oliver Castelblanco Martínez oliver.castelblanco@esap.edu.co
 * @version 2.0
 * @since PHP 8.3
 */

declare(strict_types=1);

namespace RUND\Services;

use RUND\Config\Config as Config;
use RUND\Core\OpenKM as OpenKM;

class DocumentService
{
  /**
   * Genera un JSON a partir de un array asociativo y lo carga en la $path específica
   * @param array $dataJSON Una array asociativa con la información que se convertirá a JSON
   * @param string $nombreJSON El nombre del archivo JSON
   * @param string $path Ruta completa en la taxonomía de OpenKM
   * @param ?string $uuid El UUID del archivo JSON, si ya existe o null si es nuevo. Por defecto, null.
   * @param ?string $mensaje El mensaje que se va a usar para el checkin/checkout del archivo, cuando se crea una nueva versión. Por defecto, null.
   * @return array La respuesta, como una array asociativa, del proceso de creación o nueva versión del archivo.
   */
  public static function cargaJSON(array $dataJSON, string $nombreJSON, string $path, ?string $uuid = null, ?string $mensaje = null): array
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
      OpenKM::nuevaVersion($uuid, "Modificado " . date("Y-m-d H:i:s") . ($mensaje ? " => " . $mensaje : ""), $postData) :
      OpenKM::documentAction("createSimple", $postData);
    $salida = OpenKM::verificaCarga($resp, $salida);
    return $salida;
  }

  /**
   * Añade un objeto a un documento JSON que DEBE ser un array de objetos.
   * En caso de que no existe en OpenKM, se generará como nuevo archivo, con la información proporcionada
   * @param string $nombre El nombre completo del JSON, con extensión
   * @param string $ruta La ruta completa al archivo
   * @param string $data Un objeto (array asociativa PHP) que se añadirá al array existente (o recién creado) del JSON
   * @param string $mensaje El mensaje que se usará al momento de hacer checkin/chekout en el JSON destino.
   * @return array Array asociativo generado por la función OpenKM::verificaCarga()
   */
  public static function addToJSON(string $nombre, string $ruta, array $data, string $mensaje): array
  {
    $uuid = OpenKM::findArchivo($nombre, $ruta);
    $dataJson = $uuid ? json_decode(OpenKM::getArchivo($uuid), true) : [];
    array_push($dataJson, $data);
    return self::cargaJSON($dataJson, $nombre, $ruta, $uuid, $mensaje);
  }

  /**
   * Obtiene información a partir de los documentos del profesor
   * @param string $cedula Cédula del profesor, que es el nombre de la carpeta donde se encuentra su hoja de vida
   * @param bool $demografica Indica si se quiere la información demográfica (true, por defecto) o solo la información del documento (tipo, origen, formato)
   * @return array|null Devuelve un array con la información del documento y sus categorías, o null si no se encuentra el documento
   */
  public static function getInfoArchivosProfesor(string $cedula, bool $demografica = true): array | null
  {
    $query = "search/find?path=" . urlencode(Config::TAX_HOJAS . $cedula);
    $resp = json_decode(OpenKM::consulta($query), true)["queryResult"];
    if (!$resp) return null;

    // Es información demográfica, que se obtiene de un único documento: la cédula
    if ($demografica) {
      $items = isset($resp[0]) ? $resp : [$resp];
      $tipoCedulaPath = Config::CTGR_DOCS_HOJAS . "TIPO/CEDULA";

      // Prioridad 1: documento categorizado como TIPO/CEDULA
      foreach ($items as $item) {
        $nodo = $item["node"] ?? null;
        if (!$nodo) continue;
        $cates = $nodo["categories"] ?? [];
        if (isset($cates["path"])) $cates = [$cates];
        if (is_array($cates)) {
          foreach ($cates as $cate) {
            if (isset($cate["path"]) && $cate["path"] === $tipoCedulaPath) {
              return self::extraeDatosDocumento($nodo, Config::TAX_HOJAS, Config::ROOT_CTG_PROF);
            }
          }
        }
      }

      // Prioridad 2: primer PDF directamente en la carpeta raíz del profesor (sin subcarpeta)
      $rootPath = Config::TAX_HOJAS . $cedula . "/";
      foreach ($items as $item) {
        $nodo = $item["node"] ?? null;
        if (!$nodo || ($nodo["mimeType"] ?? '') !== "application/pdf") continue;
        $relPath = str_replace($rootPath, "", $nodo["path"] ?? '');
        if (strlen($relPath) > 0 && !str_contains($relPath, '/')) {
          return self::extraeDatosDocumento($nodo, Config::TAX_HOJAS, Config::ROOT_CTG_PROF);
        }
      }

      return null;
    }

    // Se solicita información de TODOS los documentos almacenados del profesor
    $datos = [];

    // Verificar si hay múltiples resultados o uno solo
    if (isset($resp[0])) {
      // Múltiples resultados
      foreach ($resp as $el) {
        $nodo = $el["node"] ?? null;
        if ($nodo) {
          $datos[] = self::extraeDatosDocumento($nodo, Config::TAX_HOJAS, Config::CTGR_DOCS_HOJAS);
        }
      }
    } else {
      // Un solo resultado
      $nodo = $resp["node"] ?? null;
      if ($nodo) {
        $datos[] = self::extraeDatosDocumento($nodo, Config::TAX_HOJAS, Config::CTGR_DOCS_HOJAS);
      }
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
  public static function extraeDatosDocumento(array $nodo, string $tax, string $cat): array
  {
    // Extrae el nombre del archivo
    $path = $nodo["path"];
    $path = preg_replace("#$tax#", "", $path);
    $partesRuta = explode("/", $path);
    $nombre = array_pop($partesRuta);
    // Extrae las categorías (sean de documento o demográficas) del documento
    $categorias = [];

    // Validar que existan categorías y sean un array
    if (isset($nodo["categories"]) && !empty($nodo["categories"])) {
      $cates = $nodo["categories"];

      // Si es un solo elemento (objeto), convertirlo a array
      if (isset($cates["path"])) {
        $cates = [$cates];
      }

      // Asegurar que sea array antes de iterar
      if (is_array($cates)) {
        foreach ($cates as $cate) {
          if (isset($cate["path"])) {
            $pathCate = $cate["path"];
            if (preg_match("#$cat#", $pathCate)) {
              $ruta = preg_replace("#$cat#", "", $pathCate);
              $partesCat = explode("/", $ruta);
              $categorias[] = $partesCat;
            }
          }
        }
      }
    }

    // Fallback: si search/find no devuelve categorías, las infiere del path y mimeType
    if (empty($categorias)) {
      $mimeFormato = [
        'application/pdf'  => 'PDF',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'DOCX',
        'application/vnd.ms-excel' => 'XLS',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'XLSX',
        'image/jpeg' => 'JPG',
        'image/png'  => 'PNG',
        'application/json' => 'JSON',
        'image/tiff' => 'TIFF',
        'image/bmp'  => 'BMP',
      ];
      $mime    = $nodo['mimeType'] ?? '';
      $formato = $mimeFormato[$mime] ?? strtoupper(pathinfo($nombre, PATHINFO_EXTENSION));
      if ($formato) $categorias[] = ['FORMATO', $formato];
      // Tipo: subcarpeta bajo la cédula; si no hay subcarpeta el archivo es la cédula misma
      if (count($partesRuta) >= 2) {
        $categorias[] = ['TIPO', $partesRuta[1]];
      } else {
        $categorias[] = ['TIPO', 'CEDULA'];
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
  public static function estructuraCategorias(array $categorias): array
  {
    $resultado = [];
    $labels = OpenKM::getDataFile("labels"); // Los labels obtenidos de labels.json en rund-core
    foreach ($categorias as $item) {
      if (count($item) === 3) {
        // Caso de 3 niveles: [categoria][subcategoria] = valor
        // Si la categoría no tiene label, usar el valor original
        $categoria = $labels[$item[0]] ?? $item[0];
        $subcategoria = $labels[$item[1]] ?? $item[1];
        $valor = $labels[$item[2]] ?? $item[2];
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
        // Si la categoría no tiene label, usar el valor original
        $categoria = $labels[$item[0]] ?? $item[0];
        $valor = $labels[$item[1]] ?? $item[1];
        if (!isset($resultado[$categoria])) {
          $resultado[$categoria] = [];
        }
        $resultado[$categoria][] = $valor;
      }
    }
    return $resultado;
  }
}
