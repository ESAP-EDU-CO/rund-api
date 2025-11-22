<?php

/**
 * RUND API v2 - Archivos Controller
 *
 * Maneja operaciones de archivos con estructura RESTful moderna.
 * Incluye subida, descarga, datos e imágenes.
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
use RUND\Core\OpenKM;
use RUND\Config\Config;
use RUND\Utils\Utils;

class ArchivosController extends BaseController
{
	/**
	 * POST /api/v2/archivos/subir
	 * Sube un archivo al sistema
	 */
	public function subir(array $params = []): array
	{
		$postData = $this->getPostData();
		$files = $this->getFiles();

		if (!$postData || !isset($postData['accion'])) {
			return $this->errorResponse('Parámetro "accion" requerido');
		}

		if (!isset($files['archivo'])) {
			return $this->errorResponse('Archivo requerido');
		}

		$result = FileHandlers::postFile($postData, $files);

		return $this->successResponse([
			'archivo' => $result,
			'meta' => [
				'accion' => $postData['accion'],
				'nombre_original' => $files['archivo']['name'],
				'tamaño' => $files['archivo']['size'],
				'version' => '2.0'
			]
		]);
	}

	/**
	 * GET /api/v2/archivos/{uuid}
	 * Obtiene un archivo por UUID y lo sirve como binario inline
	 *
	 * Este endpoint descarga el archivo desde OpenKM y lo retorna con el Content-Type
	 * correcto. El archivo se sirve inline (no como descarga) para permitir su
	 * visualización en el navegador y su conversión a blob en el frontend.
	 *
	 * @param array $params Parámetros de la ruta, debe incluir 'uuid'
	 * @return null Siempre retorna null porque sirve el archivo directamente y hace exit()
	 */
	public function show(array $params = []): ?array
	{
		if (!isset($params['uuid'])) {
			return $this->errorResponse('UUID es requerido', 400);
		}

		try {
			$uuid = $params['uuid'];

			// Obtener propiedades del documento para conocer el MIME type
			$propsResponse = OpenKM::consulta("document/getProperties?docId=" . urlencode($uuid));
			$properties = json_decode($propsResponse, true);

			if (!$properties || !isset($properties['mimeType'])) {
				http_response_code(404);
				header('Content-Type: application/json');
				echo json_encode([
					'success' => false,
					'error' => 'Archivo no encontrado en OpenKM',
					'codigo' => 404
				]);
				exit();
			}

			// Obtener el contenido del archivo
			$contenido = OpenKM::getArchivo($uuid);

			if (!$contenido) {
				http_response_code(404);
				header('Content-Type: application/json');
				echo json_encode([
					'success' => false,
					'error' => 'No se pudo obtener el contenido del archivo',
					'codigo' => 404
				]);
				exit();
			}

			// Servir el archivo con los headers apropiados
			$mimeType = $properties['mimeType'];
			$fileName = $properties['path'] ? basename($properties['path']) : 'archivo';
			$fileSize = strlen($contenido);

			// Headers estándar para respuesta de archivo
			header('Content-Type: ' . $mimeType);
			header('Content-Length: ' . $fileSize);
			header('Content-Disposition: inline; filename="' . $fileName . '"');

			// Headers adicionales opcionales
			if (isset($properties['lastModified'])) {
				header('Last-Modified: ' . date('D, d M Y H:i:s', strtotime($properties['lastModified'])) . ' GMT');
			}

			// Cache control para optimización
			header('Cache-Control: private, max-age=3600');

			// ETag para validación de caché (usando el UUID como base)
			$etag = md5($uuid . ($properties['versionLabel'] ?? ''));
			header('ETag: "' . $etag . '"');

			// Verificar si el cliente tiene una versión en caché
			$ifNoneMatch = $_SERVER['HTTP_IF_NONE_MATCH'] ?? null;
			if ($ifNoneMatch && trim($ifNoneMatch, '"') === $etag) {
				http_response_code(304); // Not Modified
				exit();
			}

			// Enviar el contenido del archivo
			echo $contenido;
			exit();

		} catch (\Throwable $e) {
			http_response_code(500);
			header('Content-Type: application/json');
			echo json_encode([
				'success' => false,
				'error' => 'Error al obtener archivo: ' . $e->getMessage(),
				'codigo' => 500
			]);
			exit();
		}
	}

	/**
	 * DELETE /api/v2/archivos/{uuid}
	 * Elimina un archivo por UUID
	 */
	public function delete(array $params = []): array
	{
		if (!isset($params['uuid'])) {
			return $this->errorResponse('UUID es requerido', 400);
		}

		$result = OpenKM::borraArchivo($params['uuid']);

		return $this->successResponse([
			'eliminado' => true,
			'uuid' => $params['uuid'],
			'resultado' => $result,
			'meta' => [
				'operacion' => 'eliminar',
				'version' => '2.0'
			]
		]);
	}

	/**
	 * GET /api/v2/archivos/datos/{nombre}
	 * Obtiene archivo de datos (JSON) por nombre
	 */
	public function getDatos(array $params = []): array
	{
		if (!isset($params['nombre'])) {
			return $this->errorResponse('Nombre es requerido', 400);
		}

		$result = OpenKM::getDataFile($params['nombre']);

		if (isset($result['error'])) {
			return $this->errorResponse($result['error'], 404);
		}

		return $this->successResponse([
			'datos' => $result,
			'nombre' => $params['nombre'],
			'meta' => [
				'tipo' => 'datos_json',
				'archivo' => $params['nombre'] . '.json',
				'version' => '2.0'
			]
		]);
	}

	/**
	 * GET /api/v2/archivos/imagenes/{nombre}?ruta=opcional
	 * GET /img/{nombre}?ruta=opcional (alias corto)
	 * Sirve una imagen directamente desde OpenKM
	 *
	 * Lógica de negocio:
	 * 1. Si es imagen de configuración (logos, íconos, fondos): automática en CONFIG/IMG
	 * 2. Si tiene parámetro 'ruta': usar esa ruta específica bajo DOCUMENTOS/
	 * 3. Las firmas deben usar el controlador FirmasController específico
	 */
	public function getImagen(array $params = []): ?array
	{
		if (!isset($params['nombre'])) {
			return $this->errorResponse('Nombre es requerido', 400);
		}

		$nombre = $params['nombre'];
		$queryParams = $this->getQueryParams();
		$rutaEspecifica = $queryParams['ruta'] ?? null;

		// Categoría 1: Imágenes de configuración (automática)
		if ($this->esImagenConfiguracion($nombre)) {
			return $this->fileResponse(function () use ($nombre) {
				$rutaCompleta = Config::ROOT_TAX_CONF . 'IMG/';
				OpenKM::getImageFile($nombre, $rutaCompleta);
			});
		}

		// Categoría 2: Imágenes específicas (frontend debe indicar ruta)
		if ($rutaEspecifica) {
			return $this->fileResponse(function () use ($nombre, $rutaEspecifica) {
				// Usar la ruta proporcionada por el frontend bajo DOCUMENTOS/
				$rutaCompleta = Config::ROOT_TAX_DOCS . strtoupper(str_replace(' ', '_', $rutaEspecifica)) . '/';
				OpenKM::getImageFile($nombre, $rutaCompleta);
			});
		}

		// Categoría 3: Firmas requieren controlador específico
		if ($this->esFirma($nombre)) {
			return $this->errorResponse('Las firmas deben usar /api/v2/firmas/{uuid} - contacte al administrador', 400);
		}

		// Casos legacy con rutas conocidas (mantener compatibilidad)
		$rutaLegacy = $this->obtenerRutaLegacy($nombre);
		if ($rutaLegacy) {
			return $this->fileResponse(function () use ($nombre, $rutaLegacy) {
				$rutaCompleta = Config::ROOT_TAX_DOCS . $rutaLegacy . '/';
				OpenKM::getImageFile($nombre, $rutaCompleta);
			});
		}

		// Si no se encuentra, sugerir el uso correcto
		return $this->errorResponse(
			"Imagen no encontrada. Para imágenes específicas use: ?ruta=RUTA_ESPECIFICA. Para firmas use: /api/v2/firmas/",
			404
		);
	}

	/**
	 * Determina si una imagen es de configuración (logos, íconos, fondos)
	 * Estas siempre están en CONFIG/IMG y no requieren ruta del frontend
	 */
	private function esImagenConfiguracion(string $nombre): bool
	{
		// Patrones específicos para imágenes de configuración
		$patronesConfig = [
			'/^logo/i',           // logoESAP.svg, logo_esap.png, etc.
			'/^icon/i',           // iconos diversos
			'/^fondo/i',          // fondos de página
			'/^textura/i',        // texturas
			'/^header/i',         // elementos de header
			'/^footer/i',         // elementos de footer
			'/^esap/i'            // archivos específicos de ESAP
		];

		// Archivos específicos conocidos de configuración
		$archivosConfig = [
			'logoESAP.svg',
			'logo_esap.png',
			'esap_logo.png',
			'favicon.ico'
		];

		// Verificar archivos específicos primero
		if (in_array($nombre, $archivosConfig)) {
			return true;
		}

		// Verificar patrones
		foreach ($patronesConfig as $patron) {
			if (preg_match($patron, $nombre)) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Determina si una imagen es una firma
	 * Las firmas deben manejarse por el controlador específico de firmas
	 */
	private function esFirma(string $nombre): bool
	{
		$patronesFirmas = [
			'/^firma_/i',
			'/^signature/i',
			'/firmas?\//i'
		];

		foreach ($patronesFirmas as $patron) {
			if (preg_match($patron, $nombre)) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Obtiene rutas legacy para mantener compatibilidad con imágenes existentes
	 * Este mapeo se mantendrá solo para archivos ya existentes
	 */
	private function obtenerRutaLegacy(string $nombre): ?string
	{
		$mapeoLegacy = [
			'base.jpg' => 'PLANTILLAS/CERTIFICADOS',
			// Agregar otros archivos legacy según sea necesario
		];

		return $mapeoLegacy[$nombre] ?? null;
	}


	/**
	 * DELETE /api/v2/archivos/temp/limpiar
	 * Limpia archivos temporales
	 */
	public function limpiarTemp(array $params = []): array
	{
		$result = FileHandlers::deleteReport();

		return $this->successResponse([
			'limpieza' => $result,
			'meta' => [
				'operacion' => 'limpiar_temporales',
				'version' => '2.0'
			]
		]);
	}

	/**
	 * DELETE /api/v2/archivos/papelera
	 * Vacía completamente la papelera de OpenKM
	 */
	public function vaciarPapelera(array $params = []): array
	{
		try {
			// Llamar al método que ya existe en OpenKM
			$result = OpenKM::borraPapelera();

			return $this->successResponse([
				'resultado' => $result,
				'mensaje' => 'Papelera de OpenKM vaciada exitosamente',
				'meta' => [
					'operacion' => 'vaciar_papelera',
					'sistema' => 'OpenKM',
					'version' => '2.0',
					'timestamp' => date('c')
				]
			]);
		} catch (\Exception $e) {
			return $this->errorResponse(
				'Error al vaciar la papelera: ' . $e->getMessage(),
				500
			);
		}
	}
}
