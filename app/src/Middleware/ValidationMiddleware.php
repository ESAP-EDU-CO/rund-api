<?php

/**
 * RUND API - Validation Middleware
 *
 * Maneja validaciones comunes antes de llegar a los controllers.
 *
 * @author ESAP Development Team / Oliver Castelblanco Martínez
 * @version 3.0
 * @since PHP 8.3
 */

declare(strict_types=1);

namespace RUND\Middleware;

class ValidationMiddleware
{
	/**
	 * Valida que el método HTTP sea válido
	 */
	public static function validateHttpMethod(array $allowedMethods = ['GET', 'POST', 'PUT', 'DELETE']): callable
	{
		return function (string $method, string $path, array $params = []) use ($allowedMethods): bool {
			if (!in_array($method, $allowedMethods)) {
				http_response_code(405);
				header('Content-Type: application/json; charset=utf-8');
				echo json_encode([
					'error' => 'Método HTTP no permitido',
					'allowed' => $allowedMethods,
					'received' => $method
				], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
				return false;
			}
			return true;
		};
	}

	/**
	 * Valida que existan parámetros específicos en la query string
	 */
	public static function requireQueryParams(array $requiredParams): callable
	{
		return function (string $method, string $path, array $params = []) use ($requiredParams): bool {
			$missing = [];

			foreach ($requiredParams as $param) {
				if (!isset($_GET[$param]) || empty($_GET[$param])) {
					$missing[] = $param;
				}
			}

			if (!empty($missing)) {
				http_response_code(400);
				header('Content-Type: application/json; charset=utf-8');
				echo json_encode([
					'error' => 'Parámetros de consulta requeridos faltantes',
					'missing' => $missing,
					'required' => $requiredParams
				], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
				return false;
			}

			return true;
		};
	}

	/**
	 * Valida que existan archivos en $_FILES
	 */
	public static function requireFiles(array $requiredFiles): callable
	{
		return function (string $method, string $path, array $params = []) use ($requiredFiles): bool {
			$missing = [];

			foreach ($requiredFiles as $fileKey) {
				if (!isset($_FILES[$fileKey]) || $_FILES[$fileKey]['error'] !== UPLOAD_ERR_OK) {
					$missing[] = $fileKey;
				}
			}

			if (!empty($missing)) {
				http_response_code(400);
				header('Content-Type: application/json; charset=utf-8');
				echo json_encode([
					'error' => 'Archivos requeridos faltantes o con errores',
					'missing' => $missing,
					'required' => $requiredFiles
				], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
				return false;
			}

			return true;
		};
	}

	/**
	 * Valida el tamaño máximo de archivos subidos
	 */
	public static function validateFileSize(int $maxSizeBytes = 50 * 1024 * 1024): callable
	{
		return function (string $method, string $path, array $params = []) use ($maxSizeBytes): bool {
			foreach ($_FILES as $key => $file) {
				if ($file['error'] === UPLOAD_ERR_OK && $file['size'] > $maxSizeBytes) {
					http_response_code(413);
					header('Content-Type: application/json; charset=utf-8');
					echo json_encode([
						'error' => 'Archivo demasiado grande',
						'file' => $key,
						'size' => $file['size'],
						'max_allowed' => $maxSizeBytes
					], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
					return false;
				}
			}
			return true;
		};
	}

	/**
	 * Middleware de logging básico
	 */
	public static function logRequest(): callable
	{
		return function (string $method, string $path, array $params = []): bool {
			$logData = [
				'timestamp' => date('Y-m-d H:i:s'),
				'method' => $method,
				'path' => $path,
				'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
				'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
			];

			error_log('RUND API Request: ' . json_encode($logData));
			return true;
		};
	}
}
