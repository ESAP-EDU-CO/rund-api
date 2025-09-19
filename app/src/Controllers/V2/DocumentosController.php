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
use RUND\Handlers\CertificadosHandlers;

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
     * Genera un documento personalizado (certificados, reportes, etc.)
     */
    public function generar(array $params = []): ?array
    {
        // Obtener datos del POST (soportar tanto JSON como form-data)
        $postData = $this->getPostData();

        // Si no hay datos JSON, verificar si hay datos de formulario
        if (!$postData && !empty($_POST)) {
            $postData = $_POST;
        }

        if (!$postData) {
            return $this->errorResponse('Datos requeridos para generar documento');
        }

        // Validar que se especifique el tipo de documento
        if (!isset($postData['tipo'])) {
            return $this->errorResponse('Parámetro "tipo" es requerido (ej: certificado, reporte)', 400);
        }

        $tipoDocumento = $postData['tipo'];

        switch ($tipoDocumento) {
            case 'certificado':
                return $this->fileResponse(function() use ($postData) {
                    CertificadosHandlers::getCertificado($postData);
                });

            case 'reporte':
            case 'consulta':
                // Validar que tenga los datos necesarios para reportes
                if (!isset($postData['data'])) {
                    return $this->errorResponse('Parámetro "data" es requerido para reportes');
                }

                $data = is_string($postData['data']) ? json_decode($postData['data'], true) : $postData['data'];
                if (json_last_error() !== JSON_ERROR_NONE) {
                    return $this->errorResponse('Datos JSON inválidos en "data"');
                }

                $formato = $postData['formato'] ?? 'xlsx'; // Por defecto Excel

                return $this->fileResponse(function() use ($data, $formato) {
                    FileHandlers::getConsultaFile($data, $formato);
                });

            default:
                return $this->errorResponse("Tipo de documento no soportado: {$tipoDocumento}. Tipos válidos: certificado, reporte, consulta", 400);
        }
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