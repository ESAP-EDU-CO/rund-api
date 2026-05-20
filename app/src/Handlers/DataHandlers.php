<?php

/**
 * RUND-API - Servicio de Gestión de Documentos
 * 
 * Gestiona peticiones relacionadas con obtención de datos planos e imágenes
 * 
 * @author ESAP Development Team / Oliver Castelblanco Martínez oliver.castelblanco@esap.edu.co
 * @version 2.0
 * @since PHP 8.3
 */

declare(strict_types=1);

namespace RUND\Handlers;

use RUND\Config\Config as Config;
use RUND\Core\OpenKM as OpenKM;
use RUND\Core\Utils as Utils;
use RUND\Services\DocumentService as DocumentService;

class DataHandlers
{
  /**
   * Maneja la obtención de datos CSV desde OpenKM y su conversión a JSON
   *
   * @param array{categoria?: string, tipo?: string, nombre?: string, extension?: string} $params Parámetros necesarios para la consulta
   * @return array{arrayCSV?: array<array<string>>, columnasCSV?: array<object>, rawCSV?: string, error?: string} Datos CSV procesados o error
   */
  public static function getCsvData(array $params): array
  {
    $requiredParams = ['categoria', 'tipo', 'nombre', 'extension'];
    $missingParams = array_diff($requiredParams, array_keys($params));

    if (!empty($missingParams)) {
      return ["error" => "Faltan parámetros requeridos: " . implode(', ', $missingParams)];
    }

    $path = Config::ROOT_TAX_DOCS . Utils::textoAnombreCarpeta($params["categoria"] . "/" . $params["tipo"]);
    $nombreArchivo = $params["nombre"] . $params["extension"];
    $query = "search/find?name=" . urlencode($nombreArchivo) . "&path=" . urlencode($path);
    $resp = json_decode(OpenKM::consulta($query), true);

    if ($resp) {
      $nodo = (Utils::esArraySimple($resp["queryResult"])) ? $resp["queryResult"][0]["node"] : $resp["queryResult"]["node"];
      if ($nodo["path"] == "$path/$nombreArchivo") {
        $headers = ["Accept: application/octet-stream"];
        $uuid = $nodo["uuid"];
        $csvContent = OpenKM::consulta("document/getContent?docId=$uuid", "GET", null, $headers);
        $regex = '/([^\\"]|)(\\n)+([^\\"])/m';
        $doble = '/  /m';
        $csvContent = preg_replace($regex, "$1 $3", $csvContent);
        $csvContent = preg_replace($doble, " ", $csvContent);
        $lines = explode(PHP_EOL, $csvContent);
        $respCSV = [];
        foreach ($lines as $line) {
          if (!empty(trim($line))) $respCSV[] = str_getcsv($line);
        }
        $jsonCSV = Utils::csvToJsonByColumns($respCSV);
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

  /**
   * Maneja la obtención y envío de una imagen almacenada en OpenKM
   * @param array $params Parámetros necesarios para la consulta:
   *   - 'ruta': Ruta dentro de la taxonomía de documentos (por ejemplo, 'FOTOS/2023/PROFESORES')
   *   - 'nombre': Nombre del archivo de imagen (con extensión, por ejemplo, 'foto.png')
   * @return void Envía la imagen directamente al navegador
   */
  public static function getImagen(array $params): void
  {
    $nombre = $params["nombre"];
    $ruta = Config::ROOT_TAX_DOCS . Utils::textoAnombreCarpeta($params["ruta"]);
    OpenKM::getImageFile($nombre, $ruta);
  }

  /**
   * Devuelve la informción de documentos y demográfica de un profesor a partir de su cédula.
   * @param string $cedula Cédula del profesor
   * @return array Un objeto con la información demográfica del profesor y los documentos que están almacenados en rund-core.
   */
  public static function getInfoProfesor(string $cedula): array
  {
    // Valida que la cédula esté compuesta solo por números: mínimo 4, máximo 20.
    if (!preg_match('/^\d{4,20}$/', $cedula)) return ["error" => "La cédula debe tener entre 4 y 20 dígitos."];
    $archivosProfesor = DocumentService::getInfoArchivosProfesor($cedula, false);
    $datosDemograficos = DocumentService::getInfoArchivosProfesor($cedula);
    $indiceResult = FileHandlers::getIndiceDocente();
    $fechaNacimiento = (!$indiceResult['error'] && isset($indiceResult['indice'][$cedula]))
      ? ($indiceResult['indice'][$cedula]['FECHA_NACIMIENTO'] ?? null)
      : null;

    if (null !== $datosDemograficos) {
      $datosDemograficos["categorias"] = DocumentService::estructuraCategorias($datosDemograficos["categorias"]);
      return ["archivosProfesor" => $archivosProfesor, "datosDemograficos" => $datosDemograficos, "fechaNacimiento" => $fechaNacimiento];
    } elseif (!empty($archivosProfesor)) {
      return ["archivosProfesor" => $archivosProfesor, "datosDemograficos" => ["categorias" => new \stdClass()], "fechaNacimiento" => $fechaNacimiento];
    } else {
      return ["error" => null, "resultado" => "El profesor con cédula $cedula no tiene datos registrados en rund-core."];
    }
  }
}
