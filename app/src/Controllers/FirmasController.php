<?php

/**
 * RUND API - Firmas Controller
 *
 * Maneja operaciones relacionadas con firmas digitales.
 *
 * @author ESAP Development Team / Oliver Castelblanco Martínez
 * @version 3.0
 * @since PHP 8.3
 */

declare(strict_types=1);

namespace RUND\Controllers;

use RUND\Handlers\FirmasHandlers;

class FirmasController extends BaseController
{
	/**
	 * Obtiene firmas (puede devolver JSON o archivo binario)
	 */
	public function getFirmas(array $params = []): ?array
	{
		$queryParams = $this->getQueryParams();

		return $this->fileResponse(function () use ($queryParams) {
			FirmasHandlers::getFirmas($queryParams);
		});
	}
}
