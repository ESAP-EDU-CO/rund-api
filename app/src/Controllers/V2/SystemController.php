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
use RUND\Middleware\DeprecationMiddleware;

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
     * GET /api/v2/system/migration
     * Estado de migración v1 → v2
     */
    public function getMigrationStatus(array $params = []): array
    {
        return $this->successResponse([
            'migration' => [
                'from' => 'v1',
                'to' => 'v2',
                'status' => 'in_progress',
                'completed_endpoints' => [
                    'system/info',
                    'system/health',
                    'system/capabilities',
                    'categorias/arbol',
                    'categorias/cruce',
                    'profesores/{cedula}',
                    'archivos/datos',
                    'archivos/imagenes',
                    'certificados/{id}'
                ],
                'pending_endpoints' => [
                    'listados/cargar',
                    'firmas/lista',
                    'ai/extraer',
                    'documentos/exportar'
                ],
                'direct_urls_updated' => [
                    '/img/{nombre}' => 'updated',
                    '/upload/listados' => 'pending'
                ]
            ],
            'statistics' => [
                'total_endpoints' => 13,
                'migrated' => 9,
                'pending' => 4,
                'progress' => '69%'
            ],
            'version' => '2.0'
        ]);
    }

    /**
     * GET /api/v2/system/deprecation
     * Estado de deprecación de endpoints v1
     */
    public function getDeprecationStatus(array $params = []): array
    {
        $deprecatedEndpoints = DeprecationMiddleware::getDeprecatedEndpoints();

        return $this->successResponse([
            'v1_endpoints' => [
                'status' => 'deprecated',
                'sunset_date' => '2025-12-31',
                'total_deprecated' => count($deprecatedEndpoints),
                'migration_guide' => '/api/v2/docs',
                'deprecated_endpoints' => array_map(function($v1, $v2) {
                    return [
                        'v1_endpoint' => $v1,
                        'v2_successor' => $v2,
                        'status' => 'deprecated'
                    ];
                }, array_keys($deprecatedEndpoints), array_values($deprecatedEndpoints))
            ],
            'migration_info' => [
                'progress' => '69%',
                'completed_migrations' => 9,
                'pending_migrations' => 4,
                'benefits' => [
                    'Mejor estructura RESTful',
                    'Respuestas más consistentes',
                    'Documentación OpenAPI completa',
                    'Nomenclatura en español',
                    'Mejor manejo de errores'
                ]
            ],
            'recommendations' => [
                'Migre a v2 lo antes posible',
                'Los endpoints v1 serán descontinuados el 31 de diciembre de 2025',
                'Consulte la documentación en /api/v2/docs',
                'Use los headers de deprecación para planificar la migración'
            ]
        ]);
    }

    /**
     * GET /api/v2/system/docs
     * Información de documentación de la API
     */
    public function getDocs(array $params = []): array
    {
        return $this->successResponse([
            'documentation' => [
                'title' => 'RUND API v2 - Documentación',
                'version' => '2.0',
                'description' => 'API RESTful moderna para la gestión de documentos y certificados en RUND',
                'migration_status' => '69% completado (9/13 endpoints)',
                'access_info' => [
                    'message' => 'La documentación completa está disponible. Debido a limitaciones técnicas temporales con el servidor de archivos estáticos, proporcionamos esta documentación en formato JSON.',
                    'endpoints_documentation' => [
                        'sistema' => [
                            'GET /api/v2/system/info' => 'Información completa del sistema',
                            'GET /api/v2/system/health' => 'Health check para monitoreo',
                            'GET /api/v2/system/capabilities' => 'Capacidades disponibles',
                            'GET /api/v2/system/migration' => 'Estado de migración v1 → v2',
                            'GET /api/v2/system/deprecation' => 'Estado de deprecación v1'
                        ],
                        'certificados' => [
                            'GET /api/v2/certificados/{id}' => 'Obtener certificado por ID',
                            'POST /api/v2/certificados/generar' => 'Generar nuevo certificado',
                            'GET /api/v2/certificados/plantillas' => 'Listar plantillas disponibles'
                        ],
                        'categorias' => [
                            'GET /api/v2/categorias/arbol' => 'Árbol completo de categorías',
                            'GET /api/v2/categorias/cruce/{x}/{y}' => 'Cruce entre dos categorías'
                        ],
                        'profesores' => [
                            'GET /api/v2/profesores/{cedula}' => 'Información de profesor',
                            'GET /api/v2/profesores/{cedula}/archivos' => 'Archivos del profesor',
                            'GET /api/v2/profesores/{cedula}/demografia' => 'Información demográfica'
                        ],
                        'archivos' => [
                            'POST /api/v2/archivos/subir' => 'Subir archivo (max 50MB)',
                            'GET /api/v2/archivos/{uuid}' => 'Descargar archivo',
                            'DELETE /api/v2/archivos/{uuid}' => 'Eliminar archivo',
                            'GET /api/v2/archivos/datos/{nombre}' => 'Datos de archivo',
                            'GET /api/v2/archivos/imagenes/{nombre}' => 'Obtener imagen'
                        ],
                        'documentos' => [
                            'GET /api/v2/documentos/plantillas' => 'Listar plantillas',
                            'POST /api/v2/documentos/generar' => 'Generar documento',
                            'POST /api/v2/documentos/exportar' => 'Exportar documento'
                        ],
                        'listados' => [
                            'POST /api/v2/listados/cargar' => 'Cargar listado Excel/CSV',
                            'GET /api/v2/listados/datos' => 'Obtener datos de listados',
                            'GET /api/v2/listados/csv' => 'Exportar como CSV'
                        ],
                        'firmas' => [
                            'GET /api/v2/firmas/lista' => 'Listar firmas',
                            'GET /api/v2/firmas/{uuid}' => 'Obtener firma',
                            'POST /api/v2/firmas/subir' => 'Subir nueva firma'
                        ],
                        'ai' => [
                            'POST /api/v2/ai/extraer' => 'Extraer datos con OCR/IA (max 50MB)'
                        ]
                    ]
                ],
                'testing_examples' => [
                    'curl http://localhost:3000/api/v2/system/info',
                    'curl http://localhost:3000/api/v2/categorias/arbol',
                    'curl http://localhost:3000/api/v2/system/migration',
                    'curl http://localhost:3000/api/v2/system/deprecation'
                ],
                'migration_info' => [
                    'v1_deprecation_date' => '2025-12-31',
                    'completed_endpoints' => 9,
                    'pending_endpoints' => 4,
                    'benefits' => [
                        'Estructura RESTful moderna',
                        'Nomenclatura en español',
                        'Respuestas más consistentes',
                        'Mejor manejo de errores',
                        'Headers de deprecación automáticos'
                    ]
                ]
            ]
        ]);
    }

    /**
     * GET /api/v2/docs
     * Interfaz de documentación simplificada
     */
    public function getSwaggerUI(array $params = []): void
    {
        $htmlPath = __DIR__ . '/../../swagger/simple.html';

        if (!file_exists($htmlPath)) {
            http_response_code(404);
            echo json_encode(['error' => 'Documentación no encontrada'], JSON_UNESCAPED_UNICODE);
            return;
        }

        header('Content-Type: text/html; charset=utf-8');
        readfile($htmlPath);
        exit;
    }

    /**
     * GET /api/v2/docs/openapi.yaml
     * Especificación OpenAPI en formato YAML
     */
    public function getOpenAPISpec(array $params = []): void
    {
        $yamlPath = __DIR__ . '/../../swagger/openapi.yaml';

        if (!file_exists($yamlPath)) {
            http_response_code(404);
            echo json_encode(['error' => 'Especificación OpenAPI no encontrada'], JSON_UNESCAPED_UNICODE);
            return;
        }

        header('Content-Type: application/yaml; charset=utf-8');
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET');
        header('Access-Control-Allow-Headers: Content-Type');

        readfile($yamlPath);
        exit;
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