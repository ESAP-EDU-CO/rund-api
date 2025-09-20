<?php

/**
 * RUND API - System Controller
 *
 * Maneja operaciones del sistema como información y health checks.
 *
 * @author ESAP Development Team / Oliver Castelblanco Martínez
 * @version 3.0
 * @since PHP 8.3
 */

declare(strict_types=1);

namespace RUND\Controllers;

class SystemController extends BaseController
{
	/**
	 * Obtiene información del sistema
	 */
	public function getInfo(array $params = []): array
	{
		return $this->successResponse([
			"version" => "3.0",
			"nombre" => "RUND API",
			"descripcion" => "API moderna para la gestión de documentos y certificados en RUND",
			"autor" => "Oliver Castelblanco Martínez",
			"email" => "oliver.castelblanco@esap.edu.co",
			"autoloader" => "PSR-4 via Composer",
			"namespace" => "RUND",
			"router" => "Modular Router v3.0"
		]);
	}

	/**
	 * Health check para monitoreo
	 */
	public function health(array $params = []): array
	{
		return $this->successResponse([
			'status' => 'healthy',
			'timestamp' => date('Y-m-d H:i:s'),
			'version' => '3.0'
		]);
	}

	/**
	 * Lista archivos disponibles por tipo (para testing)
	 */
	public function listFiles(array $params = []): array
	{
		return $this->successResponse([
			'data_files' => [
				'labels' // Sabemos que este existe
			],
			'image_files' => [
				'Nota: Las imágenes deben existir en OpenKM. Usa el endpoint getFile con nombres reales.'
			],
			'usage' => [
				'data' => '/getFile?tipo=data&nombre=labels',
				'imagen' => '/getFile?tipo=imagen&nombre=archivo.png'
			]
		]);
	}
}
