<?php

/**
 * RUND API - CORS Middleware
 *
 * Maneja las políticas CORS de la API.
 *
 * @author ESAP Development Team / Oliver Castelblanco Martínez
 * @version 3.0
 * @since PHP 8.3
 */

declare(strict_types=1);

namespace RUND\Middleware;

use RUND\Core\Utils;

class CorsMiddleware
{
	/**
	 * Ejecuta el middleware CORS
	 *
	 * @param string $method Método HTTP
	 * @param string $path Ruta solicitada
	 * @param array $params Parámetros adicionaless
	 * @return bool|null true para continuar, false para detener
	 */
	public function __invoke(string $method, string $path, array $params = []): ?bool
	{
		// Usar la implementación existente de Utils
		Utils::cors();

		// Si es una petición OPTIONS, ya se manejó en Utils::cors()
		// que hace exit(0) para OPTIONS
		return true;
	}

	/**
	 * Versión estática para compatibilidad
	 */
	public static function handle(string $method, string $path, array $params = []): ?bool
	{
		$instance = new self();
		return $instance($method, $path, $params);
	}
}
