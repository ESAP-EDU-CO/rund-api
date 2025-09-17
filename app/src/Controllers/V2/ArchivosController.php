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
     * Obtiene un archivo por UUID
     */
    public function show(array $params = []): ?array
    {
        if (!isset($params['uuid'])) {
            return $this->errorResponse('UUID es requerido', 400);
        }

        // Este endpoint normalmente sirve archivos binarios directamente
        // Por ahora mantenemos la lógica existente
        return $this->errorResponse('Endpoint en construcción - usar /api/v1/getFile por ahora', 501);
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
     * GET /api/v2/archivos/imagenes/{nombre}
     * GET /img/{nombre} (alias corto)
     * Sirve una imagen directamente desde OpenKM
     */
    public function getImagen(array $params = []): ?array
    {
        if (!isset($params['nombre'])) {
            return $this->errorResponse('Nombre es requerido', 400);
        }

        $nombre = $params['nombre'];

        // Para imágenes del sistema, usar la ruta de plantillas
        $queryParams = [
            'nombre' => $nombre,
            'ruta' => 'plantillas/certificados' // Ruta estándar para imágenes del sistema
        ];

        return $this->fileResponse(function() use ($queryParams) {
            DataHandlers::getImagen($queryParams);
        });
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
}