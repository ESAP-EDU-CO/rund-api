<?php

/**
 * RUND API v2 - Documentos Controller
 *
 * Maneja operaciones de generación y exportación de documentos.
 *
 * @author ESAP Development Team / Oliver Castelblanco Martínez
 * @version 2.0
 * @since PHP 8.3
 */

declare(strict_types=1);

namespace RUND\Controllers\V2;

use RUND\Controllers\BaseController;
use RUND\Handlers\FileHandlers;

class DocumentosController extends BaseController
{
    /**
     * GET /api/v2/documentos/plantillas
     * Obtiene plantillas de documentos disponibles
     */
    public function getPlantillas(array $params = []): array
    {
        // Por ahora devolvemos las plantillas conocidas
        // En el futuro esto podría venir de una base de datos
        $plantillas = [
            [
                'id' => 'reporte_xlsx',
                'nombre' => 'Reporte Excel',
                'descripcion' => 'Plantilla para reportes en formato Excel',
                'formato' => 'xlsx'
            ],
            [
                'id' => 'reporte_pdf',
                'nombre' => 'Reporte PDF',
                'descripcion' => 'Plantilla para reportes en formato PDF',
                'formato' => 'pdf'
            ]
        ];

        return $this->successResponse([
            'plantillas' => $plantillas,
            'meta' => [
                'total' => count($plantillas),
                'formatos' => ['xlsx', 'pdf'],
                'version' => '2.0'
            ]
        ]);
    }

    /**
     * POST /api/v2/documentos/generar
     * Genera un documento personalizado
     */
    public function generar(array $params = []): array
    {
        $postData = $this->getPostData();

        if (!$postData) {
            return $this->errorResponse('Datos requeridos para generar documento');
        }

        // Por ahora redirigimos a la funcionalidad existente
        return $this->errorResponse('Endpoint en construcción - usar /api/v1/getCertificado por ahora', 501);
    }

    /**
     * POST /api/v2/documentos/exportar
     * Exporta consulta como archivo
     */
    public function exportar(array $params = []): ?array
    {
        $postData = $this->getPostData();

        if (!$postData || !isset($postData['data']) || !isset($postData['tipo'])) {
            return $this->errorResponse('Se requieren parámetros "data" y "tipo"');
        }

        $data = json_decode($postData['data'], true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return $this->errorResponse('Datos JSON inválidos');
        }

        return $this->fileResponse(function() use ($data, $postData) {
            FileHandlers::getConsultaFile($data, $postData['tipo']);
        });
    }
}