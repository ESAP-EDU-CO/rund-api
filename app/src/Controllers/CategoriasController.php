<?php

/**
 * RUND API - Categorías Controller
 *
 * Maneja operaciones relacionadas con categorías y cruces.
 *
 * @author ESAP Development Team / Oliver Castelblanco Martínez
 * @version 3.0
 * @since PHP 8.3
 */

declare(strict_types=1);

namespace RUND\Controllers;

use RUND\Handlers\CategoriasHandlers;

class CategoriasController extends BaseController
{
	/**
	 * Obtiene todas las categorías
	 */
	public function getCategorias(array $params = []): array
	{
		$categorias = CategoriasHandlers::getCategorias();
		return $this->successResponse($categorias);
	}

	/**
	 * Obtiene el cruce entre dos categorías
	 */
	public function getCruce(array $params = []): array
	{
		$queryParams = $this->getQueryParams();
		$validation = $this->validateRequired($queryParams, ['x', 'y']);

		if (!empty($validation)) {
			return $validation;
		}

		$cruce = CategoriasHandlers::getCruce($queryParams['x'], $queryParams['y']);
		return $this->successResponse($cruce);
	}
}
