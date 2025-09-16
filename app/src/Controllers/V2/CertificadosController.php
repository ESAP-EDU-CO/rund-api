<?php

/**
 * RUND API v2 - Certificados Controller
 *
 * Maneja operaciones relacionadas con certificados.
 * Estructura RESTful moderna.
 *
 * @author ESAP Development Team / Oliver Castelblanco Martínez
 * @version 2.0
 * @since PHP 8.3
 */

declare(strict_types=1);

namespace RUND\Controllers\V2;

use RUND\Controllers\BaseController;
use RUND\Handlers\CertificadosHandlers;

class CertificadosController extends BaseController
{
    /**
     * GET /api/v2/certificados/{id}
     * Obtiene información específica de un certificado
     */
    public function show(array $params = []): array
    {
        if (!isset($params['id'])) {
            return $this->errorResponse('ID es requerido', 400);
        }

        $info = CertificadosHandlers::getCertificadoInfo($params['id']);

        if ($info === null) {
            return $this->errorResponse('Certificado no encontrado', 404);
        }

        return $this->successResponse([
            'certificado' => $info,
            'id' => $params['id'],
            'meta' => [
                'tipo' => $info['tipo'] ?? 'unknown',
                'plantilla' => $info['plantilla'] ?? 'unknown',
                'version' => '2.0'
            ]
        ]);
    }

    /**
     * POST /api/v2/certificados/generar
     * Genera un nuevo certificado
     */
    public function generar(array $params = []): ?array
    {
        $postData = $this->getPostData();

        if (!$postData) {
            return $this->errorResponse('Datos requeridos para generar certificado');
        }

        return $this->fileResponse(function() use ($postData) {
            CertificadosHandlers::getCertificado($postData);
        });
    }

    /**
     * GET /api/v2/certificados/plantillas
     * Obtiene lista de plantillas disponibles
     */
    public function getPlantillas(array $params = []): array
    {
        $plantillas = [
            [
                'id' => '1050',
                'nombre' => 'Certificación de categorización y evaluación',
                'descripcion' => 'Certificado estándar para categorización docente',
                'formatos' => ['docx', 'pdf']
            ],
            [
                'id' => '1051',
                'nombre' => 'Certificación de vinculación',
                'descripcion' => 'Certificado de vinculación docente',
                'formatos' => ['docx', 'pdf']
            ],
            [
                'id' => '1231',
                'nombre' => 'Certificación de puntos por bonificación',
                'descripcion' => 'Certificado de puntos por bonificación académica',
                'formatos' => ['docx', 'pdf']
            ]
        ];

        return $this->successResponse([
            'plantillas' => $plantillas,
            'meta' => [
                'total' => count($plantillas),
                'formatos_disponibles' => ['docx', 'pdf'],
                'version' => '2.0'
            ]
        ]);
    }
}