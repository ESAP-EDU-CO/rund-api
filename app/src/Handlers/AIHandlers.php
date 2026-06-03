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
   * Procesa el callback de extracción completada desde rund-ai.
   *
   * Preserva todas las categorías existentes del documento: sólo reemplaza las
   * categorías de CTGR_EXTRACTION (pendiente/procesando/completado/error) y
   * añade opcionalmente categorías extra (p.ej. IA_CLASIFICADO).
   *
   * @param array $callbackData   Datos del callback desde rund-ai
   * @param array $extraCategorias Rutas de categorías adicionales a añadir (ya creadas o se crean aquí)
   * @return array Resultado del procesamiento
   */
  public static function procesarCallbackExtraccion(array $callbackData, array $extraCategorias = []): array
  {
    $documentId = $callbackData['document_id'];
    $status     = $callbackData['status'];
    $extraction     = $callbackData['extraction']      ?? null;
    $error          = $callbackData['error']           ?? null;
    $processingInfo = $callbackData['processing_info'] ?? [];

    $salida = [
      'document_id' => $documentId,
      'status'      => $status,
      'updated_at'  => date('Y-m-d H:i:s'),
    ];

    try {
      // 1. Determinar nueva categoría de estado de extracción
      $categoriaEstado = match($status) {
        'completed' => Config::CTGR_EXTRACTION . 'completado',
        'failed'    => Config::CTGR_EXTRACTION . 'error',
        default     => Config::CTGR_EXTRACTION . 'procesando',
      };
      OpenKM::creaCarpetas([$categoriaEstado], Config::ROOT_CTG);

      // 2. Leer categorías actuales del documento
      $propsRaw    = OpenKM::consulta('document/getProperties?docId=' . urlencode($documentId));
      $props       = json_decode($propsRaw, true) ?? [];
      $catsActuales = $props['categories'] ?? [];

      // 3. Normalizar y filtrar: eliminar sólo las categorías de extracción antiguas
      $catsArray = self::normalizarCategorias($catsActuales);
      $catsArray = array_values(array_filter(
        $catsArray,
        fn($c) => !str_starts_with($c['path'], Config::CTGR_EXTRACTION)
      ));

      // 4. Añadir nueva categoría de estado
      $catsArray[] = ['path' => $categoriaEstado];

      // 5. Añadir categorías extra (p.ej. IA_CLASIFICADO) sin duplicar
      foreach ($extraCategorias as $extraPath) {
        if (!in_array(['path' => $extraPath], $catsArray, true)) {
          OpenKM::creaCarpetas([$extraPath], Config::ROOT_CTG);
          $catsArray[] = ['path' => $extraPath];
        }
      }

      // 6. Una sola llamada setProperties con el conjunto completo
      OpenKM::consulta('document/setProperties', 'PUT', [
        'uuid'       => $documentId,
        'categories' => $catsArray,
      ]);
      $salida['openkm_updated'] = true;

      if ($status === 'completed' && $extraction) {
        $salida['extraction']       = $extraction;
        $salida['processing_info']  = $processingInfo;
      }
      if ($status === 'failed' && $error) {
        $salida['error'] = $error;
      }

      return $salida;

    } catch (\Exception $e) {
      error_log("ERROR actualizando documento tras callback: " . $e->getMessage());
      throw $e;
    }
  }

  /**
   * Normaliza el campo 'categories' de OpenKM al formato [['path'=>'...'], ...].
   * OpenKM devuelve un objeto cuando hay una sola categoría y un array cuando hay varias.
   */
  private static function normalizarCategorias(mixed $catsActuales): array
  {
    if (empty($catsActuales)) return [];
    if (isset($catsActuales['path'])) return [['path' => $catsActuales['path']]];
    $result = [];
    foreach ((array) $catsActuales as $c) {
      if (isset($c['path'])) $result[] = ['path' => $c['path']];
    }
    return $result;
  }
}
