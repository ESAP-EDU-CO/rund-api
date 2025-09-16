<?php

/**
 * RUND API v2 - Firmas Controller
 *
 * Maneja operaciones relacionadas con firmas digitales.
 *
 * @author ESAP Development Team / Oliver Castelblanco Martínez
 * @version 2.0
 * @since PHP 8.3
 */

declare(strict_types=1);

namespace RUND\Controllers\V2;

use RUND\Controllers\BaseController;
use RUND\Handlers\FirmasHandlers;

class FirmasController extends BaseController
{
    /**
     * GET /api/v2/firmas/lista
     * Obtiene lista de firmas disponibles
     */
    public function getLista(array $params = []): ?array
    {
        $queryParams = $this->getQueryParams();

        return $this->fileResponse(function() use ($queryParams) {
            FirmasHandlers::getFirmas($queryParams);
        });
    }

    /**
     * GET /api/v2/firmas/{uuid}
     * Obtiene una firma específica por UUID
     */
    public function show(array $params = []): array
    {
        if (!isset($params['uuid'])) {
            return $this->errorResponse('UUID es requerido', 400);
        }

        // Por ahora endpoint en construcción
        return $this->errorResponse('Endpoint en construcción - usar /api/v1/getFirmas por ahora', 501);
    }

    /**
     * POST /api/v2/firmas/subir
     * Sube una nueva firma al sistema
     */
    public function subir(array $params = []): array
    {
        $postData = $this->getPostData();
        $files = $this->getFiles();

        if (!$postData) {
            return $this->errorResponse('Datos de firma requeridos');
        }

        if (!isset($files['archivo'])) {
            return $this->errorResponse('Archivo de firma requerido');
        }

        // Por ahora endpoint en construcción
        return $this->errorResponse('Endpoint en construcción - usar /api/v1/postFile por ahora', 501);
    }
}