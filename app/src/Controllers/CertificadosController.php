<?php

/**
 * RUND API - Certificados Controller
 *
 * Maneja todas las operaciones relacionadas con certificados.
 *
 * @author ESAP Development Team / Oliver Castelblanco Martínez
 * @version 3.0
 * @since PHP 8.3
 */

declare(strict_types=1);

namespace RUND\Controllers;

use RUND\Handlers\CertificadosHandlers;

class CertificadosController extends BaseController
{
	/**
	 * Obtiene un certificado (genera archivo)
	 */
	public function getCertificado(array $params = []): ?array
	{
		$postData = $this->getPostData();

		if (!$postData) {
			return $this->errorResponse('Payload requerido para generar certificado');
		}

		return $this->fileResponse(function () use ($postData) {
			CertificadosHandlers::getCertificado($postData);
		});
	}

	/**
	 * Obtiene información de un certificado
	 */
	public function getCertificadoInfo(array $params = []): array
	{
		$queryParams = $this->getQueryParams();
		$validation = $this->validateRequired($queryParams, ['id']);

		if (!empty($validation)) {
			return $validation;
		}

		$info = CertificadosHandlers::getCertificadoInfo($queryParams['id']);

		if ($info === null) {
			return $this->errorResponse('Certificado no encontrado', 404);
		}

		return $this->successResponse($info);
	}
}
