<?php

/**
 * RUND API v2 - AI Controller
 *
 * Maneja operaciones de inteligencia artificial y extracción de datos.
 *
 * @author ESAP Development Team / Oliver Castelblanco Martínez
 * @version 2.0
 * @since PHP 8.3
 */

declare(strict_types=1);

namespace RUND\Controllers\V2;

use RUND\Controllers\BaseController;
use RUND\Handlers\AIHandlers;

class AIController extends BaseController
{
    /**
     * POST /api/v2/ai/extraer
     * Extrae datos de documentos usando IA
     */
    public function extraer(array $params = []): array
    {
        $postData = $this->getPostData();
        $files = $this->getFiles();

        if (!$postData) {
            return $this->errorResponse('Datos POST requeridos');
        }

        if (!isset($files['documento'])) {
            return $this->errorResponse('Archivo "documento" requerido');
        }

        $result = AIHandlers::extraeDatos($postData, $files);

        return $this->successResponse([
            'extraccion' => $result,
            'documento' => $files['documento']['name'],
            'meta' => [
                'accion' => $postData['accion'] ?? 'extraer',
                'tipo_documento' => $postData['tipoDocumento'] ?? 'desconocido',
                'tamaño_archivo' => $files['documento']['size'],
                'version' => '2.0'
            ]
        ]);
    }

    /**
     * POST /api/v2/ai/webhook/extraction-complete
     * Webhook que recibe callbacks de rund-ai cuando termina una extracción
     */
    public function extractionComplete(array $params = []): array
    {
        $postData = $this->getPostData();

        if (!$postData) {
            return $this->errorResponse('Datos POST requeridos');
        }

        // Validar campos requeridos
        $validation = $this->validateRequired($postData, ['document_id', 'status']);
        if (!empty($validation)) {
            return $validation;
        }

        // Log del callback recibido
        error_log("WEBHOOK extraction-complete: " . json_encode([
            'document_id' => $postData['document_id'],
            'status' => $postData['status'],
            'timestamp' => date('Y-m-d H:i:s')
        ]));

        // Procesar según el estado
        try {
            $result = AIHandlers::procesarCallbackExtraccion($postData);

            return $this->successResponse([
                'message' => 'Callback procesado correctamente',
                'document_id' => $postData['document_id'],
                'status' => $postData['status'],
                'processed' => $result
            ]);

        } catch (\Exception $e) {
            error_log("ERROR procesando webhook: " . $e->getMessage());
            return $this->errorResponse(
                'Error procesando callback: ' . $e->getMessage(),
                500
            );
        }
    }
}