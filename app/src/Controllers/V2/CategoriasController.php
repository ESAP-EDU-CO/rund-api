<?php

/**
 * RUND API v2 - Categorías Controller
 *
 * Maneja operaciones relacionadas con categorías y cruces.
 * Estructura RESTful moderna.
 *
 * @author ESAP Development Team / Oliver Castelblanco Martínez
 * @version 2.0
 * @since PHP 8.3
 */

declare(strict_types=1);

namespace RUND\Controllers\V2;

use RUND\Controllers\BaseController;
use RUND\Handlers\CategoriasHandlers;

class CategoriasController extends BaseController
{
    /**
     * GET /api/v2/categorias/arbol
     * Obtiene el árbol completo de categorías
     */
    public function getArbol(array $params = []): array
    {
        $categorias = CategoriasHandlers::getCategorias();

        return $this->successResponse([
            'arbol' => $categorias,
            'total_categorias' => $this->contarCategorias($categorias),
            'meta' => [
                'estructura' => 'jerárquica',
                'formato' => 'árbol',
                'version' => '2.0'
            ]
        ]);
    }

    /**
     * GET /api/v2/categorias/cruce/{x}/{y}
     * Obtiene el cruce entre dos categorías específicas
     */
    public function getCruce(array $params = []): array
    {
        if (!isset($params['x']) || !isset($params['y'])) {
            return $this->errorResponse('Parámetros x e y son requeridos', 400);
        }

        $cruce = CategoriasHandlers::getCruce($params['x'], $params['y']);

        return $this->successResponse([
            'cruce' => $cruce,
            'parametros' => [
                'categoria_x' => $params['x'],
                'categoria_y' => $params['y']
            ],
            'meta' => [
                'tipo' => 'cruce_categorias',
                'version' => '2.0'
            ]
        ]);
    }

    /**
     * Cuenta recursivamente el número total de categorías
     */
    private function contarCategorias(array $categorias): int
    {
        $total = count($categorias);

        foreach ($categorias as $categoria) {
            if (isset($categoria['children']) && is_array($categoria['children'])) {
                $total += $this->contarCategorias($categoria['children']);
            }
        }

        return $total;
    }
}