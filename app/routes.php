<?php

/**
 * RUND API - Definición de Rutas
 *
 * Configuración modular de todas las rutas de la API.
 *
 * @author ESAP Development Team / Oliver Castelblanco Martínez
 * @version 3.0
 * @since PHP 8.3
 */

declare(strict_types=1);

use RUND\Core\Router;
use RUND\Controllers\{
	SystemController,
	CertificadosController,
	CategoriasController,
	DataController,
	FileController,
	FirmasController,
	AIController
};
use RUND\Middleware\{
	CorsMiddleware,
	ValidationMiddleware,
	AuthMiddleware
};

/**
 * Configura todas las rutas de la API
 */
function setupRoutes(Router $router): void
{
	// Middleware global
	$router->addGlobalMiddleware([CorsMiddleware::class, 'handle']);
	$router->addGlobalMiddleware(ValidationMiddleware::logRequest());

	// --- Rutas del sistema ---
	$router->get('/info', [SystemController::class, 'getInfo']);
	$router->get('/health', [SystemController::class, 'health']);
	$router->get('/files', [SystemController::class, 'listFiles']);

	// --- Rutas de certificados ---
	$router->post('/getCertificado', [CertificadosController::class, 'getCertificado']);
	$router->get('/getCertificadoInfo', [CertificadosController::class, 'getCertificadoInfo'], [
		ValidationMiddleware::requireQueryParams(['id'])
	]);

	// --- Rutas de categorías ---
	$router->get('/getCategorias', [CategoriasController::class, 'getCategorias']);
	$router->get('/getCruce', [CategoriasController::class, 'getCruce'], [
		ValidationMiddleware::requireQueryParams(['x', 'y'])
	]);

	// --- Rutas de datos ---
	$router->get('/getCsvData', [DataController::class, 'getCsvData']);
	$router->get('/imagen', [DataController::class, 'getImagen'], [
		ValidationMiddleware::requireQueryParams(['nombre', 'ruta'])
	]);
	$router->get('/getInfoProfesor', [DataController::class, 'getInfoProfesor'], [
		ValidationMiddleware::requireQueryParams(['cedula'])
	]);

	// --- Rutas de archivos ---
	$router->delete('/deleteFile', [FileController::class, 'deleteFile'], [
		ValidationMiddleware::requireQueryParams(['uuid'])
	]);

	$router->post('/getConsultaFile', [FileController::class, 'getConsultaFile']);
	$router->get('/delReporte', [FileController::class, 'deleteReport']);

	$router->get('/loadList', [FileController::class, 'loadList'], [
		ValidationMiddleware::requireQueryParams(['accion', 'propiedades'])
	]);
	$router->post('/loadList', [FileController::class, 'loadList']);

	$router->post('/postFile', [FileController::class, 'postFile'], [
		ValidationMiddleware::requireFiles(['archivo'])
	]);

	$router->get('/getFile', [FileController::class, 'getFile'], [
		ValidationMiddleware::requireQueryParams(['tipo', 'nombre'])
	]);

	// --- Rutas de firmas ---
	$router->get('/getFirmas', [FirmasController::class, 'getFirmas']);

	// --- Rutas de IA ---
	$router->post('/extraeDatos', [AIController::class, 'extraeDatos'], [
		ValidationMiddleware::requireFiles(['documento']),
		ValidationMiddleware::validateFileSize(50 * 1024 * 1024) // 50MB max
	]);

	// --- Rutas preparadas para RUND-PTA (ejemplos) ---
	$router->group('/v2', function (Router $router) {
		// Aquí se pueden agregar las rutas específicas para RUND-PTA
		// Ejemplo:
		// $router->get('/pta/datos', [PTAController::class, 'getDatos']);
		// $router->post('/pta/proceso', [PTAController::class, 'procesarDatos']);
	});

	// --- Rutas administrativas (con restricción de IP si es necesario) ---
	$router->group('/admin', function (Router $router) {
		// Rutas administrativas futuras
		// $router->get('/stats', [AdminController::class, 'getStats'], [
		//     AuthMiddleware::limitToIPs(['127.0.0.1', '::1'])
		// ]);
	});
}
