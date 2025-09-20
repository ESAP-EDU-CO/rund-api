<?php

/**
 * RUND API - Data Controller
 *
 * Maneja operaciones de datos, CSV, imágenes y información de profesores.
 *
 * @author ESAP Development Team / Oliver Castelblanco Martínez
 * @version 3.0
 * @since PHP 8.3
 */

declare(strict_types=1);

namespace RUND\Controllers;

use RUND\Handlers\DataHandlers;

class DataController extends BaseController
{
	/**
	 * Obtiene datos de CSV
	 */
	public function getCsvData(array $params = []): array
	{
		$queryParams = $this->getQueryParams();
		$csvData = DataHandlers::getCsvData($queryParams);
		return $this->successResponse($csvData);
	}

	/**
	 * Obtiene una imagen (respuesta binaria)
	 */
	public function getImagen(array $params = []): ?array
	{
		$queryParams = $this->getQueryParams();
		$validation = $this->validateRequired($queryParams, ['nombre', 'ruta']);

		if (!empty($validation)) {
			return $validation;
		}

		return $this->fileResponse(function () use ($queryParams) {
			DataHandlers::getImagen($queryParams);
		});
	}

	/**
	 * Obtiene información de un profesor por cédula
	 */
	public function getInfoProfesor(array $params = []): array
	{
		$queryParams = $this->getQueryParams();
		$validation = $this->validateRequired($queryParams, ['cedula']);

		if (!empty($validation)) {
			return $validation;
		}

		$info = DataHandlers::getInfoProfesor($queryParams['cedula']);
		return $this->successResponse($info);
	}
}
