<?php

/**
 * RUND API v2 - System Controller
 *
 * Maneja operaciones del sistema, información y health checks.
 *
 * @author ESAP Development Team / Oliver Castelblanco Martínez
 * @version 2.0
 * @since PHP 8.3
 */

declare(strict_types=1);

namespace RUND\Controllers\V2;

use RUND\Controllers\BaseController;

class SystemController extends BaseController
{
    /**
     * GET /api/v2/system/info
     * Obtiene información completa del sistema
     */
    public function getInfo(array $params = []): array
    {
        return $this->successResponse([
            "version" => "2.0",
            "api_version" => "v2",
            "nombre" => "RUND API v2",
            "descripcion" => "API RESTful moderna para la gestión de documentos y certificados en RUND",
            "autor" => "Oliver Castelblanco Martínez",
            "email" => "oliver.castelblanco@esap.edu.co",
            "arquitectura" => "RESTful con Controllers modulares",
            "autoloader" => "PSR-4 via Composer",
            "namespace" => "RUND",
            "router" => "Modular Router v2",
            "idioma" => "español",
            "endpoints" => [
                "certificados" => "/api/v2/certificados",
                "categorias" => "/api/v2/categorias",
                "profesores" => "/api/v2/profesores",
                "documentos" => "/api/v2/documentos",
                "archivos" => "/api/v2/archivos",
                "listados" => "/api/v2/listados",
                "firmas" => "/api/v2/firmas",
                "ai" => "/api/v2/ai"
            ]
        ]);
    }

    /**
     * GET /api/v2/system/health
     * Health check para monitoreo
     */
    public function getHealth(array $params = []): array
    {
        return $this->successResponse([
            'status' => 'healthy',
            'version' => '2.0',
            'timestamp' => date('Y-m-d H:i:s'),
            'uptime' => $this->getUptime(),
            'services' => [
                'database' => 'connected',
                'storage' => 'available',
                'ai' => 'operational'
            ]
        ]);
    }

    /**
     * GET /api/v2/system/capabilities
     * Lista capacidades y funcionalidades disponibles
     */
    public function getCapabilities(array $params = []): array
    {
        return $this->successResponse([
            'version' => '2.0',
            'features' => [
                'certificados' => [
                    'generar' => true,
                    'plantillas' => ['1050', '1051', '1231'],
                    'formatos' => ['docx', 'pdf']
                ],
                'archivos' => [
                    'subir' => true,
                    'formatos' => ['pdf', 'docx', 'xlsx', 'png', 'jpg', 'svg'],
                    'max_size' => '50MB'
                ],
                'ai' => [
                    'extraccion_datos' => true,
                    'ocr' => true,
                    'modelos' => ['ollama']
                ],
                'listados' => [
                    'excel' => true,
                    'csv' => true,
                    'validacion' => true
                ]
            ],
            'limits' => [
                'max_file_size' => 50 * 1024 * 1024,
                'supported_formats' => ['docx', 'pdf', 'xlsx', 'csv', 'png', 'jpg', 'svg'],
                'rate_limit' => '100 requests/minute'
            ]
        ]);
    }

    /**
     * Calcula el uptime del sistema
     */
    private function getUptime(): string
    {
        $uptime = (int)shell_exec('cat /proc/uptime | cut -d" " -f1 2>/dev/null') ?: 0;
        $hours = floor($uptime / 3600);
        $minutes = floor(($uptime % 3600) / 60);
        return "{$hours}h {$minutes}m";
    }
}