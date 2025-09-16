<?php

/**
 * RUND-API - Servicio de Gestión de Documentos
 * 
 * Gestiona peticiones relacionadas con firmas
 * @author ESAP Development Team / Oliver Castelblanco Martínez oliver.castelblanco@esap.edu.co
 * @version 2.0
 * @since PHP 8.3
 */

declare(strict_types=1);

namespace RUND\Handlers;

use RUND\Config\Config as Config;
use RUND\Core\OpenKM as OpenKM;
use RUND\Services\FirmasService as FirmasService;

class FirmasHandlers
{

  /**
   * Gestiona la obtención de firmas digitalizadas desde OpenKM
   * @param array $getParams Parámetros GET de la petición:
   *   - 'uuid' (opcional): UUID del archivo de firma en OpenKM
   *   - 'mimeType' (opcional): Tipo MIME del archivo de firma (por ejemplo, 'image/png')
   * Si no se proporcionan 'uuid' y 'mimeType', devuelve un listado de todas las firmas disponibles.
   * Si se proporcionan ambos, descarga y devuelve el archivo de firma correspondiente.
   * @return void Imprime directamente la respuesta (JSON o archivo) y establece las cabeceras HTTP adecuadas
   */
  public static  function getFirmas(array $getParams): void
  {
    if (!isset($getParams["uuid"]) || !isset($getParams["mimeType"])) {
      header('Content-Type: application/json; charset=utf-8');
      print json_encode(FirmasService::getFirmas(Config::TAX_FIRMAS));
    } else {
      $respuesta = OpenKM::getArchivo($getParams["uuid"]);
      header("Content-Type: " . $getParams["mimeType"]);
      print $respuesta;
    }
  }
}
