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

    public function getQueueStats(array $params = []): array
    {
        try {
            $aiUrl = $_ENV['RUND_AI_URL'] ?? 'http://rund-ai:8001';
            $ch = curl_init("$aiUrl/queue/stats");
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            if ($httpCode !== 200) {
                return $this->errorResponse('Error consultando cola de rund-ai', 500);
            }
            return $this->successResponse([
                'queue' => json_decode($response, true),
                'meta'  => ['source' => 'rund-ai', 'version' => '2.0']
            ]);
        } catch (\Exception $e) {
            return $this->errorResponse('Error consultando cola: ' . $e->getMessage(), 500);
        }
    }

    public function getDocumentosDocente(array $params = []): array
    {
        if (!isset($params['cedula'])) return $this->errorResponse('Cédula es requerida', 400);
        $cedula = $params['cedula'];
        $aiUrl  = $_ENV['RUND_AI_URL'] ?? 'http://rund-ai:8001';

        $ch = curl_init("$aiUrl/extraction/professor/$cedula");
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 15]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($httpCode !== 200) return $this->errorResponse('Error consultando rund-ai', 500);

        $data    = json_decode($response, true);
        $allDocs = $data['documents'] ?? [];
        $query   = $this->getQueryParams();
        $page    = max(1, (int)($query['page']  ?? 1));
        $size    = min(50, max(1, (int)($query['size'] ?? 10)));
        $total   = count($allDocs);
        $items   = array_slice($allDocs, ($page - 1) * $size, $size);

        // Añadir nombre del JSON side-car para documentos completados
        foreach ($items as &$doc) {
            if (($doc['status'] ?? '') === 'completado' && !empty($doc['file_path'])) {
                $doc['json_nombre'] = pathinfo(basename($doc['file_path']), PATHINFO_FILENAME) . '.json';
            } else {
                $doc['json_nombre'] = null;
            }
        }

        return $this->successResponse([
            'cedula'     => $cedula,
            'documentos' => $items,
            'paginacion' => [
                'page'  => $page,
                'size'  => $size,
                'total' => $total,
                'pages' => (int)ceil($total / max(1, $size)),
            ],
        ]);
    }

    public function getJsonExtraido(array $params = []): array
    {
        $cedula     = $params['cedula']      ?? null;
        $nombreJson = $params['nombre_json'] ?? null;
        if (!$cedula || !$nombreJson) return $this->errorResponse('Parámetros requeridos', 400);

        $path = \RUND\Config\Config::TAX_HOJAS . $cedula;
        $uuid = \RUND\Core\OpenKM::findArchivo($nombreJson, $path);
        if (!$uuid) return $this->errorResponse('JSON no encontrado', 404);

        $contenido = \RUND\Core\OpenKM::getArchivo($uuid);
        $datos = json_decode($contenido, true);
        if (!is_array($datos)) return $this->errorResponse('Contenido JSON inválido', 500);

        return $this->successResponse([
            'cedula'      => $cedula,
            'nombre_json' => $nombreJson,
            'uuid'        => $uuid,
            'datos'       => $datos,
        ]);
    }

    // ─── Scheduler ────────────────────────────────────────────────────────────

    private function schedulerStateFile(): string
    {
        return '/var/www/html/cli/scheduler_state.json';
    }

    private function readSchedulerState(): array
    {
        $file = $this->schedulerStateFile();
        if (!file_exists($file)) {
            return [
                'habilitado'       => false,
                'hora_inicio'      => 22,
                'hora_fin'         => 6,
                'ultimo_run'       => null,
                'ultimo_resultado' => null,
                'actualizado_en'   => null,
            ];
        }
        return json_decode(file_get_contents($file), true) ?? [];
    }

    private function writeSchedulerState(array $state): void
    {
        $state['actualizado_en'] = date('Y-m-d\TH:i:s');
        file_put_contents($this->schedulerStateFile(), json_encode($state, JSON_PRETTY_PRINT));
    }

    /** GET /api/v2/ai/scheduler/status */
    public function getSchedulerStatus(array $params = []): array
    {
        return $this->successResponse(['scheduler' => $this->readSchedulerState()]);
    }

    /** POST /api/v2/ai/scheduler/start */
    public function startScheduler(array $params = []): array
    {
        $state = $this->readSchedulerState();
        $state['habilitado'] = true;
        $this->writeSchedulerState($state);
        return $this->successResponse(['scheduler' => $state, 'message' => 'Scheduler habilitado']);
    }

    /** POST /api/v2/ai/scheduler/pause */
    public function pauseScheduler(array $params = []): array
    {
        $state = $this->readSchedulerState();
        $state['habilitado'] = false;
        $this->writeSchedulerState($state);
        return $this->successResponse(['scheduler' => $state, 'message' => 'Scheduler pausado']);
    }

    /** POST /api/v2/ai/scheduler/config — body: { hora_inicio, hora_fin } */
    public function configScheduler(array $params = []): array
    {
        $post  = $this->getPostData() ?? [];
        $state = $this->readSchedulerState();

        if (array_key_exists('hora_inicio', $post)) {
            $hi = (int) $post['hora_inicio'];
            if ($hi < 0 || $hi > 23) return $this->errorResponse('hora_inicio debe estar entre 0 y 23', 400);
            $state['hora_inicio'] = $hi;
        }
        if (array_key_exists('hora_fin', $post)) {
            $hf = (int) $post['hora_fin'];
            if ($hf < 0 || $hf > 23) return $this->errorResponse('hora_fin debe estar entre 0 y 23', 400);
            $state['hora_fin'] = $hf;
        }

        $this->writeSchedulerState($state);
        return $this->successResponse(['scheduler' => $state, 'message' => 'Configuración actualizada']);
    }

    // ──────────────────────────────────────────────────────────────────────────

    public function retryErrorJobs(array $params = []): array
    {
        try {
            $aiUrl = $_ENV['RUND_AI_URL'] ?? 'http://rund-ai:8001';
            $ch = curl_init("$aiUrl/retry-error-jobs");
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => 15,
                CURLOPT_POST           => true,
                CURLOPT_POSTFIELDS     => '',
            ]);
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode !== 200) {
                return $this->errorResponse('Error ejecutando retry en rund-ai', 500);
            }

            $data = json_decode($response, true);
            return $this->successResponse([
                'retried' => $data['retried'] ?? 0,
                'meta'    => ['source' => 'rund-ai', 'version' => '2.0'],
            ]);
        } catch (\Exception $e) {
            error_log("ERROR retry-error-jobs: " . $e->getMessage());
            return $this->errorResponse('Error ejecutando retry: ' . $e->getMessage(), 500);
        }
    }

    public function resetStuckJobs(array $params = []): array
    {
        try {
            $aiUrl = $_ENV['RUND_AI_URL'] ?? 'http://rund-ai:8001';
            $ch = curl_init("$aiUrl/reset-stuck-jobs");
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => 15,
                CURLOPT_POST           => true,
                CURLOPT_POSTFIELDS     => '',
            ]);
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode !== 200) {
                return $this->errorResponse('Error ejecutando reset en rund-ai', 500);
            }

            $data = json_decode($response, true);
            return $this->successResponse([
                'resetted' => $data['resetted'] ?? 0,
                'meta'     => ['source' => 'rund-ai', 'version' => '2.0'],
            ]);
        } catch (\Exception $e) {
            error_log("ERROR reset-stuck-jobs: " . $e->getMessage());
            return $this->errorResponse('Error ejecutando reset: ' . $e->getMessage(), 500);
        }
    }

    public function getStatsExtraccion(array $params = []): array
    {
        $aiUrl = $_ENV['RUND_AI_URL'] ?? 'http://rund-ai:8001';
        $ch    = curl_init("$aiUrl/extraction/statistics");
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 10]);
        $response = curl_exec($ch);
        curl_close($ch);
        $data = json_decode($response, true) ?? [];

        $meta       = $data['metadata']                    ?? [];
        $byStatus   = $data['statistics']['by_status']    ?? [];
        $byCat      = $data['statistics']['by_category']  ?? [];
        $total      = $meta['total_documents'] ?? 0;
        $completado = $byStatus['completado']  ?? 0;

        return $this->successResponse([
            'total_documentos'     => $total,
            'total_profesores'     => $meta['total_professors']  ?? 0,
            'por_estado'           => $byStatus,
            'por_categoria'        => $byCat,
            'tasa_exito'           => $total > 0 ? (int)round(($completado / $total) * 100) : 0,
            'ultima_actualizacion' => $meta['last_updated'] ?? null,
            'meta'                 => ['source' => 'rund-ai', 'version' => '2.0'],
        ]);
    }
}