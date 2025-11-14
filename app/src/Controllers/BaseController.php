<?php

/**
 * RUND API - Controller Base
 *
 * Clase base para todos los controllers con utilidades comunes.
 *
 * @author ESAP Development Team / Oliver Castelblanco Martínez
 * @version 3.0
 * @since PHP 8.3
 */

declare(strict_types=1);

namespace RUND\Controllers;

abstract class BaseController
{
	/**
	 * Obtiene datos POST del cuerpo de la solicitud
	 */
	protected function getPostData(): ?array
	{
		if (!empty($_POST)) {
			return $_POST;
		}

		$input = file_get_contents("php://input");
		if (empty($input)) {
			return null;
		}

		$decoded = json_decode($input, true);
		return json_last_error() === JSON_ERROR_NONE ? $decoded : null;
	}

	/**
	 * Obtiene parámetros GET
	 */
	protected function getQueryParams(): array
	{
		return $_GET;
	}

	/**
	 * Obtiene archivos subidos
	 */
	protected function getFiles(): array
	{
		return $_FILES;
	}

	/**
	 * Valida que existan parámetros requeridos
	 */
	protected function validateRequired(array $data, array $required): array
	{
		$missing = [];
		foreach ($required as $field) {
			if (!isset($data[$field]) || empty($data[$field])) {
				$missing[] = $field;
			}
		}

		if (!empty($missing)) {
			http_response_code(400);
			return [
				'error' => 'Parámetros requeridos faltantes: ' . implode(', ', $missing)
			];
		}

		return [];
	}

	/**
	 * Respuesta de error con código HTTP
	 */
	protected function errorResponse(string $message, int $code = 400): array
	{
		http_response_code($code);
		return ['error' => $message];
	}

	/**
	 * Respuesta exitosa
	 */
	protected function successResponse(array $data = [], string $message = null): array
	{
		$response = ['success' => true] + $data;
		if ($message) {
			$response['message'] = $message;
		}
		return $response;
	}

	/**
	 * Maneja respuestas de archivos (para downloads, imágenes, etc.)
	 * Retorna null para indicar que el handler se encargó de la respuesta
	 */
	protected function fileResponse(callable $fileHandler): ?array
	{
		$fileHandler();
		return null;
	}
}
