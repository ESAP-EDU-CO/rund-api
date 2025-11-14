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

    /**
     * GET /api/v2/ai/extraction/statistics
     * Obtiene estadísticas generales del índice de extracción
     */
    public function getExtractionStatistics(array $params = []): array
    {
        try {
            $aiUrl = $_ENV['RUND_AI_URL'] ?? 'http://rund-ai:8001';
            $url = "$aiUrl/extraction/statistics";

            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode !== 200) {
                return $this->errorResponse('Error consultando estadísticas de rund-ai', 500);
            }

            $data = json_decode($response, true);

            return $this->successResponse([
                'statistics' => $data,
                'meta' => [
                    'source' => 'rund-ai',
                    'version' => '2.0'
                ]
            ]);

        } catch (\Exception $e) {
            error_log("ERROR consultando estadísticas: " . $e->getMessage());
            return $this->errorResponse(
                'Error consultando estadísticas: ' . $e->getMessage(),
                500
            );
        }
    }

    /**
     * GET /api/v2/ai/extraction/professor/{cedula}
     * Obtiene documentos extraídos de un profesor específico
     */
    public function getProfesorExtraction(array $params = []): array
    {
        if (!isset($params['cedula'])) {
            return $this->errorResponse('Cédula es requerida', 400);
        }

        $cedula = $params['cedula'];

        try {
            $aiUrl = $_ENV['RUND_AI_URL'] ?? 'http://rund-ai:8001';
            $url = "$aiUrl/extraction/professor/$cedula";

            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode !== 200) {
                return $this->errorResponse('Error consultando documentos de rund-ai', 500);
            }

            $data = json_decode($response, true);

            return $this->successResponse([
                'professor' => $data,
                'cedula' => $cedula,
                'meta' => [
                    'source' => 'rund-ai',
                    'version' => '2.0'
                ]
            ]);

        } catch (\Exception $e) {
            error_log("ERROR consultando documentos del profesor: " . $e->getMessage());
            return $this->errorResponse(
                'Error consultando documentos: ' . $e->getMessage(),
                500
            );
        }
    }
}