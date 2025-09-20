<?php

/**
 * RUND API v2 - Punto de entrada principal
 *
 * API RESTful moderna con estructura escalable.
 * Migración completa v1 → v2 finalizada.
 *
 * @author ESAP Development Team / Oliver Castelblanco Martínez
 * @version 2.0
 * @since PHP 8.3
 */

declare(strict_types=1);

// Cargar sistema moderno
require_once __DIR__ . '/bootstrap.php';

// Cargar configuración de rutas v2
require_once __DIR__ . '/routes_v2.php';

use RUND\Core\Router;

try {
	// Crear e inicializar el router
	$router = new Router();

	// Configurar rutas v2
	setupRoutesV2($router);

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

	// Log del error para debugging
	error_log("RUND API Error: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine());

	echo json_encode($errorResponse, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
}
