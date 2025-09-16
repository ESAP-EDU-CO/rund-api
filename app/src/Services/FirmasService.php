<?php

/**
 * RUND-API - Servicio de Gestión de Documentos
 * 
 * Maneja operaciones específicas de gestión de firmas digitalizadas
 * 
 * @author ESAP Development Team / Oliver Castelblanco Martínez oliver.castelblanco@esap.edu.co
 * @version 2.0
 * @since PHP 8.3
 */

declare(strict_types=1);

namespace RUND\Services;

use RUND\Config\Constants as Config;
use RUND\Core\OpenKM as OpenKM;

class FirmasService
{

  /**
   * Obtiene todos los archivos de firma que hay en la ruta correspondiente en OpenKM, sean PNG o JSON
   * @param string $rutaFirmas La ruta en OpenKM donde se almacenan las firmas (por ejemplo, Config::ROOT_CTG_PROF . "FIRMAS/")
   * @return array Un array con los datos de las firmas encontradas (uuid, mimeType, nombre) o un string con el error
   */
  public static function getFirmas(string $rutaFirmas): array | string
  // Obtiene todos los archivos de firma que hay en la ruta correspondiente en OpenKM, sean PNG o JSON
  {
    $resp = json_decode(OpenKM::consulta("search/find?path=" . urlencode($rutaFirmas)), true);
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

  /**
   * Descarga de OpenKM un archivo de firma y lo guarda temporalmente
   * @param string $uuid UUID del archivo de firma en OpenKM
   * @return string La ruta completa del archivo temporal generado o un string con el error
   */
  public static function creaFirmaTemp(string $uuid): string
  // Descarga de OpenKM un archivo de firma y lo guarda temporalmente
  {
    $firma = OpenKM::getArchivo($uuid);
    $nombre = "firma_" . $uuid . ".png";
    $ruta = Config::TEMP_DIR . $nombre;
    $file = fopen($ruta, 'w');
    if ($file) {
      fwrite($file, $firma);
      fclose($file);
      return $ruta;
    }
    return "Error: no se pudo guardar la firma temporal";
  }
}
