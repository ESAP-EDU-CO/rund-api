<?php

/**
 * RUND-API - Servicio de Gestión de Documentos
 * 
 * Gestiona peticiones relacionadas con AI y OCR
 * @author ESAP Development Team / Oliver Castelblanco Martínez oliver.castelblanco@esap.edu.co
 * @version 2.0
 * @since PHP 8.3
 */

declare(strict_types=1);

namespace RUND\Handlers;

use RUND\Config\Config as Config;
use RUND\Services\AIService as AIService;

class AIHandlers
{
  public static function extraeDatos(array $params, array $files): array
  {
    if (!isset($params["accion"])) return ["error" => "Falta el parámetro 'accion'."];
    $accion = $params["accion"];
    switch ($accion) {
      case "documento":
        if (!isset($params["tipoDocumento"]) || !isset($params["datosExtraer"]) || !isset($files)) {
          return ["error" => "Faltan los parámetros para extraer datos."];
        }
        $fileTemp = $files["documento"]["tmp_name"];
        $filePath = Config::TEMP_DIR . $files["documento"]["name"];
        copy($fileTemp, $filePath);
        $tipoDocumento = $params["tipoDocumento"];
        $datosExtraer = json_decode($params["datosExtraer"], true);
        return AIService::analizaDocumento($filePath, $tipoDocumento, $datosExtraer);
        break;
    }
    return ["error" => "Acción no reconocida: $accion"];
  }
}
