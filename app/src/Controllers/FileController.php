<?php

/**
 * RUND API - File Controller
 *
 * Maneja operaciones de archivos: subida, descarga, eliminación y reportes.
 *
 * @author ESAP Development Team / Oliver Castelblanco Martínez
 * @version 3.0
 * @since PHP 8.3
 */

declare(strict_types=1);

namespace RUND\Controllers;

use RUND\Handlers\FileHandlers;
use RUND\Core\OpenKM;

class FileController extends BaseController
{
	/**
	 * Elimina un archivo por UUID
	 */
	public function deleteFile(array $params = []): array
	{
		$queryParams = $this->getQueryParams();
		$validation = $this->validateRequired($queryParams, ['uuid']);

		if (!empty($validation)) {
			return $validation;
		}

		$result = OpenKM::borraArchivo($queryParams['uuid']);
		return $this->successResponse(['salida' => $result]);
	}

	/**
	 * Genera archivo de consulta (Excel/PDF)
	 */
	public function getConsultaFile(array $params = []): ?array
	{
		$postData = $this->getPostData();

		if (!$postData || !isset($postData['data']) || !isset($postData['tipo'])) {
			return $this->errorResponse('Se requieren parámetros "data" y "tipo"');
		}

		$data = json_decode($postData['data'], true);
		if (json_last_error() !== JSON_ERROR_NONE) {
			return $this->errorResponse('Datos JSON inválidos');
		}

		return $this->fileResponse(function () use ($data, $postData) {
			FileHandlers::getConsultaFile($data, $postData['tipo']);
		});
	}

	/**
	 * Elimina reportes temporales
	 */
	public function deleteReport(array $params = []): array
	{
		$result = FileHandlers::deleteReport();
		return $this->successResponse($result);
	}

	/**
	 * Carga lista de archivos
	 */
	public function loadList(array $params = []): array
	{
		$method = $_SERVER['REQUEST_METHOD'];
		$data = $method === 'GET' ? $this->getQueryParams() : $this->getPostData();
		$files = $this->getFiles();

		if (!$data) {
			return $this->errorResponse('Datos requeridos');
		}

		$validation = $this->validateRequired($data, ['accion', 'propiedades']);
		if (!empty($validation)) {
			return $validation;
		}

		$result = FileHandlers::loadList($method, $data, $files);
		return $this->successResponse($result);
	}

	/**
	 * Sube un archivo
	 */
	public function postFile(array $params = []): array
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
		return $this->successResponse($result);
	}

	/**
	 * Obtiene un archivo por tipo y nombre
	 */
	public function getFile(array $params = []): ?array
	{
		$queryParams = $this->getQueryParams();
		$validation = $this->validateRequired($queryParams, ['tipo', 'nombre']);

		if (!empty($validation)) {
			return $validation;
		}

		try {
			$result = FileHandlers::getFile($queryParams['tipo'], $queryParams['nombre']);

			if ($result === null) {
				// El handler se encargó de servir el archivo directamente
				// Para el caso de imágenes, getImageFile() ya hizo exit() si encontró el archivo
				// Si llegamos aquí con null, significa que era una imagen exitosa
				return null;
			}

			return $this->successResponse($result);
		} catch (\Throwable $e) {
			// Si getImageFile() encontró la imagen, hizo exit() y no llega aquí
			// Si hay error, lo manejamos apropiadamente
			return $this->errorResponse('Archivo no encontrado: ' . $e->getMessage(), 404);
		}
	}
}
