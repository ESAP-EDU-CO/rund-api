<?php

/**
 * RUND API v2 - Documentos Internos Controller
 *
 * Endpoints de uso interno para comunicación entre microservicios.
 * Especialmente diseñado para rund-ai que necesita acceder a documentos
 * en OpenKM sin conexión directa a rund-core.
 *
 * IMPORTANTE: Estos endpoints NO deben exponerse públicamente.
 * Solo deben ser accesibles desde la red interna Docker (rund-network).
 *
 * @author ESAP Development Team / Oliver Castelblanco Martínez
 * @version 2.0
 * @since PHP 8.3
 */

declare(strict_types=1);

namespace RUND\Controllers\V2;

use RUND\Controllers\BaseController;
use RUND\Core\OpenKM;
use RUND\Config\Config;

class DocumentosInternosController extends BaseController
{
	/**
	 * POST /api/v2/internos/documentos/obtener-uuid
	 * Obtiene el UUID de un documento a partir de su ruta completa
	 *
	 * Body JSON:
	 * {
	 *   "doc_path": "/okm:root/RUND/DOCENTES/HOJAS_DE_VIDA/71776491/1996_1_CEDULA_CIUDADANÍA.pdf"
	 * }
	 *
	 * Respuesta:
	 * {
	 *   "success": true,
	 *   "uuid": "uuid-del-documento",
	 *   "path": "ruta original",
	 *   "filename": "nombre del archivo"
	 * }
	 */
	public function obtenerUuid(array $params = []): array
	{
		$postData = $this->getPostData();

		if (!isset($postData['doc_path'])) {
			return $this->errorResponse('Parámetro "doc_path" es requerido', 400);
		}

		$docPath = $postData['doc_path'];

		// Extraer nombre del archivo y ruta padre
		$pathParts = explode('/', $docPath);
		$filename = array_pop($pathParts);
		$parentPath = implode('/', $pathParts);

		if (empty($filename) || empty($parentPath)) {
			return $this->errorResponse('Ruta inválida: no se pudo extraer nombre o ruta padre', 400);
		}

		try {
			// IMPORTANTE: El nombre puede venir URL-encoded desde rund-ai
			// Ejemplo: "CIUDADANI%CC%81A" debe convertirse a "CIUDADANÍA"
			// OpenKM almacena los nombres con Unicode NFD (decomposed form)
			$filenameDecoded = urldecode($filename);

			// Normalizar Unicode a NFD (decomposed form) para que coincida con OpenKM
			// Esto convierte "CIUDADANÍA" (Í = U+00CD) a "CIUDADANÍA" (I + ́ = U+0049 + U+0301)
			if (class_exists('\Normalizer')) {
				$filenameNormalized = \Normalizer::normalize($filenameDecoded, \Normalizer::FORM_D);
			} else {
				$filenameNormalized = $filenameDecoded;
			}

			// Buscar el UUID usando OpenKM::findArchivo
			$uuid = OpenKM::findArchivo($filenameNormalized, $parentPath);

			if (!$uuid) {
				return $this->errorResponse(
					"Documento no encontrado: $filename en $parentPath",
					404
				);
			}

			return $this->successResponse([
				'uuid' => $uuid,
				'path' => $docPath,
				'filename' => $filename,
				'parent_path' => $parentPath
			]);

		} catch (\Exception $e) {
			return $this->errorResponse(
				'Error al buscar UUID: ' . $e->getMessage(),
				500
			);
		}
	}

	/**
	 * GET /api/v2/internos/documentos/descargar/{uuid}
	 * Descarga el contenido de un documento por UUID
	 *
	 * Este endpoint devuelve el contenido binario del documento directamente,
	 * con los headers apropiados para su descarga.
	 */
	public function descargar(array $params = []): ?array
	{
		if (!isset($params['uuid'])) {
			return $this->errorResponse('UUID es requerido', 400);
		}

		$uuid = $params['uuid'];

		try {
			// Descargar usando OpenKM::getArchivo
			$content = OpenKM::getArchivo($uuid);

			// Este método retorna el contenido directamente con headers
			// No necesitamos JSON aquí, es contenido binario
			return $this->fileResponse(function () use ($content) {
				// OpenKM::getArchivo ya configura los headers apropiados
				echo $content;
			});

		} catch (\Exception $e) {
			return $this->errorResponse(
				'Error al descargar documento: ' . $e->getMessage(),
				500
			);
		}
	}

	/**
	 * POST /api/v2/internos/documentos/subir-json
	 * Sube un archivo JSON side-car a OpenKM
	 *
	 * Body JSON:
	 * {
	 *   "json_path": "/okm:root/RUND/DOCENTES/HOJAS_DE_VIDA/71776491/1996_1_CEDULA_CIUDADANÍA.json",
	 *   "data": { ... datos del JSON ... }
	 * }
	 */
	public function subirJson(array $params = []): array
	{
		$postData = $this->getPostData();

		if (!isset($postData['json_path']) || !isset($postData['data'])) {
			return $this->errorResponse('Parámetros "json_path" y "data" son requeridos', 400);
		}

		$jsonPath = $postData['json_path'];
		$data = $postData['data'];

		// Extraer nombre del archivo y ruta padre
		$pathParts = explode('/', $jsonPath);
		$filename = array_pop($pathParts);
		$parentPath = implode('/', $pathParts);

		if (empty($filename) || empty($parentPath)) {
			return $this->errorResponse('Ruta inválida', 400);
		}

		// Validar que sea un archivo .json
		if (!str_ends_with($filename, '.json')) {
			return $this->errorResponse('El archivo debe tener extensión .json', 400);
		}

		try {
			// Convertir data a JSON
			$jsonContent = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

			if ($jsonContent === false) {
				return $this->errorResponse('Error al serializar JSON', 400);
			}

			// Verificar si ya existe el archivo
			$uuid = OpenKM::findArchivo($filename, $parentPath);

			// Crear archivo temporal con el contenido JSON
			$tempFile = tempnam(sys_get_temp_dir(), 'rund_json_');
			file_put_contents($tempFile, $jsonContent);

			// Simular array $_FILES para usar cargaArchivo
			$archivo = [
				'error' => 0,
				'name' => $filename,
				'tmp_name' => $tempFile,
				'type' => 'application/json',
				'size' => strlen($jsonContent)
			];

			$propiedades = [
				['label' => 'Nombre', 'valor' => $filename]
			];

			if ($uuid) {
				$propiedades[] = ['label' => 'Uuid', 'valor' => $uuid];
			}

			// Subir usando OpenKM::cargaArchivo
			$result = OpenKM::cargaArchivo(
				$archivo,
				$propiedades,
				$parentPath,
				$uuid ? true : false  // version: true si ya existe
			);

			// Limpiar archivo temporal
			unlink($tempFile);

			if (isset($result['error']) && $result['error'] !== false) {
				return $this->errorResponse(
					'Error al subir JSON: ' . ($result['error'] ?? 'desconocido'),
					500
				);
			}

			return $this->successResponse([
				'uploaded' => true,
				'path' => $jsonPath,
				'filename' => $filename,
				'uuid' => $result['respuesta']['uuid'] ?? $uuid,
				'size' => strlen($jsonContent),
				'is_new_version' => $uuid ? true : false
			]);

		} catch (\Exception $e) {
			return $this->errorResponse(
				'Error al subir JSON: ' . $e->getMessage(),
				500
			);
		}
	}

	/**
	 * PUT /api/v2/internos/documentos/categoria
	 * Asigna o cambia la categoría de un documento
	 *
	 * Body JSON:
	 * {
	 *   "doc_path": "/okm:root/RUND/...",
	 *   "category": "procesando" | "completado" | "error"
	 * }
	 */
	public function cambiarCategoria(array $params = []): array
	{
		$postData = $this->getPostData();

		if (!isset($postData['doc_path']) || !isset($postData['category'])) {
			return $this->errorResponse('Parámetros "doc_path" y "category" son requeridos', 400);
		}

		$docPath = $postData['doc_path'];
		$category = $postData['category'];

		// Validar categorías permitidas
		$categoriasPermitidas = ['procesando', 'completado', 'error', 'pendiente'];
		if (!in_array($category, $categoriasPermitidas)) {
			return $this->errorResponse(
				'Categoría no válida. Permitidas: ' . implode(', ', $categoriasPermitidas),
				400
			);
		}

		try {
			// El método setCategory de OpenKM usa directamente el docPath
			// No necesitamos buscar el UUID primero
			$url = $_ENV["CORE_API_URL"] . Config::REST . "document/setCategory";

			$params = [
				'docPath' => $docPath,
				'catId' => $category
			];

			$curl = curl_init();
			curl_setopt($curl, CURLOPT_URL, $url . '?' . http_build_query($params));
			curl_setopt($curl, CURLOPT_CUSTOMREQUEST, 'PUT');
			curl_setopt($curl, CURLOPT_USERNAME, Config::USER);
			curl_setopt($curl, CURLOPT_PASSWORD, Config::PASSWORD);
			curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($curl, CURLOPT_TIMEOUT, 30);

			$response = curl_exec($curl);
			$httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
			curl_close($curl);

			if ($httpCode >= 400) {
				return $this->errorResponse(
					"Error al cambiar categoría: HTTP $httpCode - $response",
					$httpCode
				);
			}

			return $this->successResponse([
				'updated' => true,
				'doc_path' => $docPath,
				'category' => $category,
				'response' => $response
			]);

		} catch (\Exception $e) {
			return $this->errorResponse(
				'Error al cambiar categoría: ' . $e->getMessage(),
				500
			);
		}
	}

	/**
	 * GET /api/v2/internos/health
	 * Health check para verificar que los endpoints internos estén funcionando
	 */
	public function health(array $params = []): array
	{
		return $this->successResponse([
			'status' => 'ok',
			'service' => 'documentos-internos',
			'endpoints' => [
				'obtener-uuid' => 'POST /api/v2/internos/documentos/obtener-uuid',
				'descargar' => 'GET /api/v2/internos/documentos/descargar/{uuid}',
				'subir-json' => 'POST /api/v2/internos/documentos/subir-json',
				'cambiar-categoria' => 'PUT /api/v2/internos/documentos/categoria'
			]
		]);
	}
}
