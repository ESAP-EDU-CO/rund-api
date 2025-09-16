<?php

/**
 * RUND API v2 - Listados Controller
 *
 * Maneja operaciones de carga y procesamiento de listados Excel/CSV.
 *
 * @author ESAP Development Team / Oliver Castelblanco Martínez
 * @version 2.0
 * @since PHP 8.3
 */

declare(strict_types=1);

namespace RUND\Controllers\V2;

use RUND\Controllers\BaseController;
use RUND\Handlers\FileHandlers;
use RUND\Handlers\DataHandlers;

class ListadosController extends BaseController
{
    /**
     * POST /api/v2/listados/cargar
     * POST /upload/listados (alias)
     * Carga y procesa un listado Excel/CSV
     */
    public function cargar(array $params = []): array
    {
        $method = $_SERVER['REQUEST_METHOD'];
        $data = $method === 'GET' ? $this->getQueryParams() : $this->getPostData();
        $files = $this->getFiles();

        if (!$data) {
            return $this->errorResponse('Datos requeridos');
        }

        $validation = $this->validateRequired($data, ['accion', 'propiedades']);
        if (!empty($validation)) {
            return $validation;
        }

        $result = FileHandlers::loadList($method, $data, $files);

        return $this->successResponse([
            'listado' => $result,
            'meta' => [
                'accion' => $data['accion'],
                'archivo' => $files['archivo']['name'] ?? null,
                'metodo' => $method,
                'version' => '2.0'
            ]
        ]);
    }

    /**
     * GET /api/v2/listados/datos
     * Obtiene datos de listados procesados
     */
    public function getDatos(array $params = []): array
    {
        $queryParams = $this->getQueryParams();

        // Reutilizar lógica existente
        $result = DataHandlers::getCsvData($queryParams);

        return $this->successResponse([
            'datos' => $result,
            'parametros' => $queryParams,
            'meta' => [
                'tipo' => 'datos_listado',
                'version' => '2.0'
            ]
        ]);
    }

    /**
     * GET /api/v2/listados/csv
     * Obtiene datos CSV específicos
     */
    public function getCsv(array $params = []): array
    {
        $queryParams = $this->getQueryParams();

        $csvData = DataHandlers::getCsvData($queryParams);

        return $this->successResponse([
            'csv' => $csvData,
            'parametros' => $queryParams,
            'meta' => [
                'formato' => 'csv',
                'filas' => count($csvData['arrayCSV'] ?? []),
                'columnas' => count($csvData['columnasCSV'] ?? []),
                'version' => '2.0'
            ]
        ]);
    }
}