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
	 * Obtiene datos de listados procesados o verifica duplicados
	 */
	public function getDatos(array $params = []): array
	{
		$queryParams = $this->getQueryParams();

		// Verificar si es una consulta CSV (con parámetros específicos) o acción con propiedades
		if (isset($queryParams['accion']) && isset($queryParams['propiedades'])) {
			// Procesar acción con propiedades (ej: duplicado)
			$validation = $this->validateRequired($queryParams, ['accion', 'propiedades']);
			if (!empty($validation)) {
				return $validation;
			}

			$result = FileHandlers::loadList('GET', $queryParams, []);

			return $this->successResponse([
				'datos' => $result,
				'parametros' => $queryParams,
				'meta' => [
					'tipo' => 'accion_listado',
					'accion' => $queryParams['accion'],
					'version' => '2.0'
				]
			]);
		} else {
			// Consulta CSV tradicional
			$result = DataHandlers::getCsvData($queryParams);

			return $this->successResponse([
				'datos' => $result,
				'parametros' => $queryParams,
				'meta' => [
					'tipo' => 'datos_csv',
					'version' => '2.0'
				]
			]);
		}
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

	/**
	 * GET /api/v2/listados/indice
	 * Obtiene el índice docente JSON (indice_docente.json)
	 *
	 * Este endpoint retorna el índice completo de docentes en formato JSON,
	 * generado automáticamente desde ListadoGeneralDocente.csv.
	 *
	 * Estructura del JSON retornado:
	 * {
	 *   "cedula1": { datos_docente },
	 *   "cedula2": { datos_docente },
	 *   ...
	 * }
	 *
	 * @return array Respuesta con el índice docente o error si no existe
	 */
	public function getIndice(array $params = []): array
	{
		$result = FileHandlers::getIndiceDocente();

		if (isset($result['error']) && $result['error']) {
			return $this->errorResponse($result['error'], 404);
		}

		return $this->successResponse([
			'indice' => $result['indice'],
			'meta' => [
				'total_docentes' => count($result['indice']),
				'estructura' => 'objeto plano con cédulas como claves',
				'uuid' => $result['uuid'] ?? null,
				'version' => '2.0'
			]
		]);
	}
}
