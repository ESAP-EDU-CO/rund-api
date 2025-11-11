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
use RUND\Core\OpenKM as OpenKM;

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

  /**
   * Procesa el callback de extracción completada desde rund-ai
   *
   * @param array $callbackData Datos del callback desde rund-ai
   * @return array Resultado del procesamiento
   */
  public static function procesarCallbackExtraccion(array $callbackData): array
  {
    $documentId = $callbackData['document_id'];
    $status = $callbackData['status'];
    $extraction = $callbackData['extraction'] ?? null;
    $error = $callbackData['error'] ?? null;
    $processingInfo = $callbackData['processing_info'] ?? [];

    $salida = [
      'document_id' => $documentId,
      'status' => $status,
      'updated_at' => date('Y-m-d H:i:s')
    ];

    try {
      // Actualizar categoría según el estado
      $categoria = match($status) {
        'completed' => Config::CTGR_EXTRACTION . 'completado',
        'failed' => Config::CTGR_EXTRACTION . 'error',
        default => Config::CTGR_EXTRACTION . 'procesando'
      };

      // Crear categoría si no existe
      OpenKM::creaCarpetas([$categoria], Config::ROOT_CTG);

      // Actualizar documento en OpenKM
      $postData = [
        "uuid" => $documentId,
        "categories" => [
          ["path" => $categoria]
        ]
      ];

      $updateResult = OpenKM::consulta("document/setProperties", "PUT", $postData);
      $salida['openkm_updated'] = true;

      // Si fue exitosa, guardar detalles adicionales
      if ($status === 'completed' && $extraction) {
        $salida['extraction'] = $extraction;
        $salida['processing_info'] = $processingInfo;
      }

      // Si falló, guardar el error
      if ($status === 'failed' && $error) {
        $salida['error'] = $error;
      }

      return $salida;

    } catch (\Exception $e) {
      error_log("ERROR actualizando documento tras callback: " . $e->getMessage());
      throw $e;
    }
  }
}
