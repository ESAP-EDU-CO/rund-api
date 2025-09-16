<?php

/**
 * RUND API - AI Controller
 *
 * Maneja operaciones de inteligencia artificial y extracción de datos.
 *
 * @author ESAP Development Team / Oliver Castelblanco Martínez
 * @version 3.0
 * @since PHP 8.3
 */

declare(strict_types=1);

namespace RUND\Controllers;

use RUND\Handlers\AIHandlers;

class AIController extends BaseController
{
	/**
	 * Extrae datos de documentos usando IA
	 */
	public function extraeDatos(array $params = []): array
	{
		$postData = $this->getPostData();
		$files = $this->getFiles();

		if (!$postData) {
			return $this->errorResponse('Datos POST requeridos');
		}

		if (!isset($files['documento'])) {
			return $this->errorResponse('Archivo "documento" requerido');
		}

		$result = AIHandlers::extraeDatos($postData, $files);
		return $this->successResponse($result);
	}
}
