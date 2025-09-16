<?php

/**
 * RUND API v2 - Profesores Controller
 *
 * Maneja operaciones relacionadas con información de profesores.
 * Estructura RESTful con endpoints específicos por recurso.
 *
 * @author ESAP Development Team / Oliver Castelblanco Martínez
 * @version 2.0
 * @since PHP 8.3
 */

declare(strict_types=1);

namespace RUND\Controllers\V2;

use RUND\Controllers\BaseController;
use RUND\Handlers\DataHandlers;

class ProfesoresController extends BaseController
{
    /**
     * GET /api/v2/profesores/{cedula}
     * Obtiene información completa del profesor
     */
    public function show(array $params = []): array
    {
        if (!isset($params['cedula'])) {
            return $this->errorResponse('Cédula es requerida', 400);
        }

        $info = DataHandlers::getInfoProfesor($params['cedula']);

        return $this->successResponse([
            'profesor' => $info,
            'cedula' => $params['cedula'],
            'meta' => [
                'total_archivos' => count($info['archivosProfesor'] ?? []),
                'incluye_demografia' => isset($info['datosDemograficos']),
                'version' => '2.0'
            ]
        ]);
    }

    /**
     * GET /api/v2/profesores/{cedula}/archivos
     * Obtiene solo los archivos del profesor
     */
    public function getArchivos(array $params = []): array
    {
        if (!isset($params['cedula'])) {
            return $this->errorResponse('Cédula es requerida', 400);
        }

        $info = DataHandlers::getInfoProfesor($params['cedula']);
        $archivos = $info['archivosProfesor'] ?? [];

        // Estadísticas por categoría
        $estadisticas = $this->calcularEstadisticasArchivos($archivos);

        return $this->successResponse([
            'archivos' => $archivos,
            'cedula' => $params['cedula'],
            'estadisticas' => $estadisticas,
            'meta' => [
                'total' => count($archivos),
                'endpoint' => 'archivos',
                'version' => '2.0'
            ]
        ]);
    }

    /**
     * GET /api/v2/profesores/{cedula}/demografia
     * Obtiene solo los datos demográficos del profesor
     */
    public function getDemografia(array $params = []): array
    {
        if (!isset($params['cedula'])) {
            return $this->errorResponse('Cédula es requerida', 400);
        }

        $info = DataHandlers::getInfoProfesor($params['cedula']);
        $demografia = $info['datosDemograficos'] ?? [];

        return $this->successResponse([
            'demografia' => $demografia,
            'cedula' => $params['cedula'],
            'meta' => [
                'categorias_disponibles' => array_keys($demografia),
                'endpoint' => 'demografia',
                'version' => '2.0'
            ]
        ]);
    }

    /**
     * Calcula estadísticas de archivos por categoría
     */
    private function calcularEstadisticasArchivos(array $archivos): array
    {
        $tipos = [];
        $formatos = [];
        $origenes = [];

        foreach ($archivos as $archivo) {
            foreach ($archivo['categorias'] as $categoria) {
                [$tipo, $valor] = $categoria;
                switch ($tipo) {
                    case 'TIPO':
                        $tipos[$valor] = ($tipos[$valor] ?? 0) + 1;
                        break;
                    case 'FORMATO':
                        $formatos[$valor] = ($formatos[$valor] ?? 0) + 1;
                        break;
                    case 'ORIGEN':
                        $origenes[$valor] = ($origenes[$valor] ?? 0) + 1;
                        break;
                }
            }
        }

        return [
            'por_tipo' => $tipos,
            'por_formato' => $formatos,
            'por_origen' => $origenes
        ];
    }
}