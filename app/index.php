<?php

/**
 * RUND API - Punto de entrada principal (Modular)
 *
 * Sistema moderno con Router, Controllers y Middleware.
 * Completamente escalable y preparado para RUND-PTA.
 *
 * @author ESAP Development Team / Oliver Castelblanco Martínez
 * @version 3.0
 * @since PHP 8.3
 */

declare(strict_types=1);

// Cargar sistema moderno
require_once __DIR__ . '/bootstrap.php';

// Cargar configuración de rutas
require_once __DIR__ . '/routes.php';

use RUND\Core\Router;

try {
	// Crear e inicializar el router
	$router = new Router();

	// Configurar todas las rutas
	setupRoutes($router);

	// Procesar la solicitud
	$router->dispatch();
} catch (Throwable $e) {
	// Manejo de errores global
	http_response_code(500);
	header('Content-Type: application/json; charset=utf-8');

	$errorResponse = [
		'error' => 'Error interno del servidor',
		'message' => 'Ha ocurrido un error inesperado'
	];

	// En desarrollo, mostrar detalles del error
	if (defined('RUND_DEBUG') && constant('RUND_DEBUG')) {
		$errorResponse['debug'] = [
			'exception' => get_class($e),
			'message' => $e->getMessage(),
			'file' => $e->getFile(),
			'line' => $e->getLine(),
			'trace' => $e->getTraceAsString()
		];
		error_log("RUND API Error: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine());
	}

	echo json_encode($errorResponse, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
}
