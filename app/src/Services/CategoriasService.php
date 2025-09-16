<?php

/**
 * RUND-API - Servicio de Gestión de Documentos
 * 
 * Maneja operaciones específicas de gestión de categorías
 * 
 * @author ESAP Development Team / Oliver Castelblanco Martínez oliver.castelblanco@esap.edu.co
 * @version 2.0
 * @since PHP 8.3
 */

declare(strict_types=1);

namespace RUND\Services;

use RUND\Core\OpenKM as OpenKM;
use RUND\Core\Utils as Utils;

class CategoriasService
{

  /**
   * Crea categorías en OpenKM si no existen, a partir de un array de rutas completas.
   * @param array $categorias Un array de arrays con la clave 'path' que contiene la ruta completa de la categoría a crear (por ejemplo, [ ["path" => "/okm:categories/RUTA/A/LA/NUEVA/CATEGORIA"], ... ])
   * @return array Un array con los resultados de las operaciones realizadas
   */
  public static function creaCategorias(array $categorias): array
  {
    foreach ($categorias as $categoria) {
      $salida = [];
      $rutaCat = $categoria["path"];
      $uuid = OpenKM::consulta("repository/getNodeUuid?nodePath=" . urlencode($rutaCat));
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
            "respuesta" => OpenKM::consulta("folder/createSimple", "POST", $rutaSinBarra, $headers)
          ]
        );
      }
    }
    return $salida;
  }

  /**
   * Genera un árbol de carpetas (sirve para Taxonomía o Categorias) a partir de un UUID. Si se le proporciona un $key, construye el KEY del árbol a partir de ese número: esto es útil para estructura de dato tipo dataTrees con keys únicos en Angular
   * @param string $uuid UUID de la carpeta raíz desde donde se generará el árbol
   * @param ?string $key Un string que se usará como prefijo para las keys del árbol. Por defecto, null.
   * @return array Un array representando el árbol de carpetas y documentos
   */
  public static function getArbolCarpetas(string $uuid, string | int | null $key = null)
  {
    $nomCats = OpenKM::getDataFile("labels");
    $carpetas = [];
    $propiedades = json_decode(OpenKM::consulta("folder/getProperties?fldId=$uuid"), true);
    $tieneHijos = $propiedades["hasChildren"];
    if ($tieneHijos) {
      $hijos = json_decode(OpenKM::consulta("folder/getChildren?fldId=$uuid"), true)["folder"];
      if (!Utils::esArraySimple($hijos)) $hijos = [$hijos]; // Cuando hay un solo hijo, la API devuelve el hijo directamente, no en array
      foreach ($hijos as $num => $hijo) {
        $clave = $key !== null ? "$key-$num" : $num;
        $carpetas[$num] = [
          "key" => "$clave",
          "label" => $nomCats[self::simplePath($hijo["path"])],
          "uuid" => $hijo["uuid"],
          "path" => $hijo["path"],
        ];
        if ($hijo["hasChildren"]) {
          $carpetas[$num]["children"] = self::getArbolCarpetas($hijo["uuid"], $clave);
        } else {
          $documentos = self::getDocumentosCategorizados($hijo["uuid"]);
          $dataDocumentos = [];
          $numDocs = 0;
          if ($documentos) {
            if (Utils::esArraySimple($documentos)) { // Más de un documento hijo
              $numDocs = count($documentos);
              foreach ($documentos as $numDoc => $documento) {
                $docClave = $clave . "-d$numDoc";
                $categorias = [];
                if (Utils::esArraySimple($documento["categories"])) { // Varias categorias
                  foreach ($documento["categories"] as $categoria) {
                    $categorias[] = [
                      "label" => $nomCats[self::simplePath($categoria["path"])],
                      "uuid" => $categoria["uuid"],
                      "path" => $categoria["path"],
                    ];
                  }
                } else { // Una sola categoria
                  $categorias = [
                    "label" => $nomCats[self::simplePath($documento["categories"]["path"])],
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

  /**
   * Retorna los documentos que has sido "marcados" en una categoría dada
   * @param string $uuid UUID de la categoría
   * @return array Un array con los documentos que tienen asignada la categoría dada
   */
  public static function getDocumentosCategorizados($uuid) // Retorna los documentos que has sido "marcados" en una categoría dada
  {
    return json_decode(OpenKM::consulta("search/getCategorizedDocuments/$uuid"), true)["document"];
  }

  /**
   * Maneja la obtención y conversión a JSON de un archivo CSV almacenado en OpenKM
   * @param string $path Ruta en OpenKM donde se encuentra el archivo CSV (por ejemplo, Config::ROOT_TAX_DOCS . "FOTOS/2023/PROFESORES")
   * @param string $nombreArchivo Nombre del archivo CSV (con extensión, por ejemplo, 'archivo.csv')
   * @return array Un array con 'arrayCSV' (array de arrays), 'columnasCSV' (array de objetos) y 'rawCSV' (contenido original) o un array con 'error'
   */
  public static function simplePath($path) // Extrae la última parte de un path de categorias o taxonomía: el nombre de la carpeta
  {
    $rootName = "/\/okm:[categories|root]\//";
    $path = preg_replace($rootName, "", $path);
    $partes = explode("/", $path);
    return array_pop($partes);
  }

  /**
   * Extrae los documentos de una categoría dada
   * @param string $catUUID UUID de la categoría
   * @return array Un array con los UUID de los documentos que tienen asignada la categoría dada
   */
  public static function getDocsFromCat($catUUID): array
  { // Devuelve los documentos de una categoría dada
    $docs =  self::getDocumentosCategorizados($catUUID);
    $docUUID = [];
    if (Utils::esArraySimple($docs)) {
      foreach ($docs as $doc) $docUUID[] = $doc["uuid"];
    } else {
      $docUUID[] = $docs["uuid"];
    }
    return $docUUID;
  }
}
