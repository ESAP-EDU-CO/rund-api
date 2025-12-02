<?php

/**
 * RUND API - Definición de Rutas v2 (RESTful en Español)
 *
 * Estructura moderna y escalable para la API, preparada para RUND-PTA
 * y múltiples frontends. Rutas en español para mejor legibilidad.
 *
 * @author ESAP Development Team / Oliver Castelblanco Martínez
 * @version 2.0
 * @since PHP 8.3
 */

declare(strict_types=1);

use RUND\Core\Router;
use RUND\Controllers\V2\{
	SystemController,
	CertificadosController,
	CategoriasController,
	ProfesoresController,
	DocumentosController,
	DocumentosInternosController,
	ArchivosController,
	ListadosController,
	FirmasController,
	AIController
};
use RUND\Controllers\V2\SystemController as V2SystemController;
use RUND\Middleware\{
	CorsMiddleware,
	ValidationMiddleware,
	AuthMiddleware
};

/**
 * Configura todas las rutas v2 de la API
 */
function setupRoutesV2(Router $router): void
{
	// Grupo v2 - Nueva API RESTful
	$router->group('/api/v2', function (Router $router) {

		// --- Sistema ---
		$router->group('/system', function (Router $router) {
			$router->get('/info', [SystemController::class, 'getInfo']);
			$router->get('/health', [SystemController::class, 'getHealth']);
			$router->get('/capabilities', [SystemController::class, 'getCapabilities']);
			$router->get('/migration', [SystemController::class, 'getMigrationStatus']);
			$router->get('/deprecation', [SystemController::class, 'getDeprecationStatus']);
			$router->get('/docs', [SystemController::class, 'getDocs']);
			$router->get('/swagger-ui', [SystemController::class, 'getSwaggerUI']);
		});

		// --- Certificados ---
		$router->group('/certificados', function (Router $router) {
			$router->get('/{id}', [CertificadosController::class, 'show']);
			$router->post('/generar', [CertificadosController::class, 'generar']);
			$router->get('/plantillas', [CertificadosController::class, 'getPlantillas']);
		});

		// --- Categorías ---
		$router->group('/categorias', function (Router $router) {
			$router->get('/arbol', [CategoriasController::class, 'getArbol']);
			$router->get('/cruce/{x}/{y}', [CategoriasController::class, 'getCruce']);
		});

		// --- Profesores ---
		$router->group('/profesores', function (Router $router) {
			$router->get('/{cedula}', [ProfesoresController::class, 'show']);
			$router->get('/{cedula}/archivos', [ProfesoresController::class, 'getArchivos']);
			$router->get('/{cedula}/demografia', [ProfesoresController::class, 'getDemografia']);
			// Ruta específica con nombre de archivo debe ir después de rutas fijas
			$router->get('/{cedula}/{nombre_archivo}', [ProfesoresController::class, 'getArchivoUuid']);
		});

		// --- Documentos ---
		$router->group('/documentos', function (Router $router) {
			$router->get('/plantillas', [DocumentosController::class, 'getPlantillas']);
			$router->post('/generar', [DocumentosController::class, 'generar']);
			$router->post('/exportar', [DocumentosController::class, 'exportar']);
		});

		// --- Archivos ---
		$router->group('/archivos', function (Router $router) {
			$router->post('/subir', [ArchivosController::class, 'subir'], [
				ValidationMiddleware::requireFiles(['archivo'])
			]);
			// Rutas específicas DEBEN ir antes de las rutas con parámetros genéricos
			$router->get('/datos/{nombre}', [ArchivosController::class, 'getDatos']);
			$router->get('/imagenes/{nombre}', [ArchivosController::class, 'getImagen']);
			$router->delete('/temp/limpiar', [ArchivosController::class, 'limpiarTemp']);
			$router->delete('/papelera', [ArchivosController::class, 'vaciarPapelera']);
			// Rutas genéricas van al final
			$router->get('/{uuid}', [ArchivosController::class, 'show']);
			$router->post('/{uuid}/actualizar', [ArchivosController::class, 'update']);
			$router->delete('/{uuid}', [ArchivosController::class, 'delete']);
		});

		// --- Listados ---
		$router->group('/listados', function (Router $router) {
			$router->post('/cargar', [ListadosController::class, 'cargar'], [
				ValidationMiddleware::requireFiles(['archivo'])
			]);
			$router->get('/datos', [ListadosController::class, 'getDatos']);
			$router->get('/csv', [ListadosController::class, 'getCsv']);
			$router->get('/indice', [ListadosController::class, 'getIndice']);
		});

		// --- Firmas ---
		$router->group('/firmas', function (Router $router) {
			$router->get('/lista', [FirmasController::class, 'getLista']);
			$router->get('/{uuid}', [FirmasController::class, 'show']);
			$router->post('/subir', [FirmasController::class, 'subir'], [
				ValidationMiddleware::requireFiles(['archivo'])
			]);
		});

		// --- Inteligencia Artificial ---
		$router->group('/ai', function (Router $router) {
			$router->post('/extraer', [AIController::class, 'extraer'], [
				ValidationMiddleware::requireFiles(['documento']),
				ValidationMiddleware::validateFileSize(50 * 1024 * 1024) // 50MB max
			]);

			// Webhook para callbacks de rund-ai
			$router->post('/webhook/extraction-complete', [AIController::class, 'extractionComplete']);

			// Estadísticas de extracción
			$router->get('/extraction/statistics', [AIController::class, 'getExtractionStatistics']);
			$router->get('/extraction/professor/{cedula}', [AIController::class, 'getProfesorExtraction']);
		});

		// --- Documentos Internos (uso entre microservicios) ---
		// IMPORTANTE: No exponer públicamente - solo red interna Docker
		$router->group('/internos', function (Router $router) {
			$router->get('/health', [DocumentosInternosController::class, 'health']);

			$router->group('/documentos', function (Router $router) {
				$router->post('/obtener-uuid', [DocumentosInternosController::class, 'obtenerUuid']);
				$router->get('/descargar/{uuid}', [DocumentosInternosController::class, 'descargar']);
				$router->post('/subir-json', [DocumentosInternosController::class, 'subirJson']);
				$router->put('/categoria', [DocumentosInternosController::class, 'cambiarCategoria']);
			});
		});
	});

	// --- URLs cortas para componentes (compatibilidad) ---
	$router->get('/img/{nombre}', [ArchivosController::class, 'getImagen']);
	$router->post('/upload/archivos', [ArchivosController::class, 'subir']);
	$router->post('/upload/listados', [ListadosController::class, 'cargar']);
}
