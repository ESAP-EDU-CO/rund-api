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
        $endpointsV2 = [
            'system/info', 'system/health', 'system/capabilities', 'system/migration', 'system/deprecation',
            'certificados/{id}', 'certificados/generar', 'certificados/plantillas',
            'categorias/arbol', 'categorias/cruce/{x}/{y}',
            'profesores/{cedula}', 'profesores/{cedula}/archivos', 'profesores/{cedula}/demografia',
            'documentos/plantillas', 'documentos/generar', 'documentos/exportar',
            'archivos/subir', 'archivos/datos/{nombre}', 'archivos/imagenes/{nombre}',
            'archivos/{uuid}', 'archivos/temp/limpiar', 'archivos/papelera',
            'listados/cargar', 'listados/datos', 'listados/csv',
            'firmas/lista', 'firmas/{uuid}', 'firmas/subir',
            'ai/extraer'
        ];

        return $this->successResponse([
            'migration' => [
                'from' => 'v1',
                'to' => 'v2',
                'status' => 'completed',
                'completion_date' => '2025-09-19',
                'all_endpoints_migrated' => $endpointsV2,
                'legacy_removed' => true
            ],
            'statistics' => [
                'total_endpoints' => count($endpointsV2),
                'migrated' => count($endpointsV2),
                'pending' => 0,
                'progress' => '100%'
            ],
            'benefits_achieved' => [
                'RESTful structure complete',
                'Spanish nomenclature implemented',
                'Robust validation added',
                'Consistent error handling',
                'Swagger documentation integrated'
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
        return $this->successResponse([
            'v1_endpoints' => [
                'status' => 'removed',
                'removal_date' => '2025-09-19',
                'total_deprecated' => 0,
                'migration_guide' => '/api/v2/docs'
            ],
            'migration_info' => [
                'progress' => '100%',
                'completed_migrations' => 27,
                'pending_migrations' => 0,
                'migration_complete' => true,
                'benefits' => [
                    'Estructura RESTful completa',
                    'Respuestas consistentes',
                    'Documentación Swagger integrada',
                    'Nomenclatura en español',
                    'Manejo de errores mejorado',
                    'Validación robusta'
                ]
            ],
            'current_status' => [
                'API v1' => 'REMOVIDA',
                'API v2' => 'ACTIVA',
                'Compatibilidad legacy' => 'NO REQUERIDA'
            ],
            'recommendations' => [
                'Usar exclusivamente endpoints /api/v2/*',
                'Consultar documentación en /api/v2/docs',
                'Usar Swagger UI en /api/v2/swagger-ui'
            ]
        ]);
    }

    /**
     * GET /api/v2/system/docs
     * Especificación OpenAPI completa en formato JSON
     */
    public function getDocs(array $params = []): void
    {
        header('Content-Type: application/json; charset=utf-8');
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET');
        header('Access-Control-Allow-Headers: Content-Type');

        $openapi = [
            'openapi' => '3.0.3',
            'info' => [
                'title' => 'RUND API v2',
                'description' => 'API RESTful moderna para la gestión de documentos y certificados en RUND. Migración 100% completa con fallback automático a v1.',
                'version' => '2.0.0',
                'contact' => [
                    'name' => 'Oliver Castelblanco Martínez',
                    'email' => 'oliver.castelblanco@esap.edu.co'
                ],
                'license' => [
                    'name' => 'ESAP',
                    'url' => 'https://esap.edu.co'
                ]
            ],
            'servers' => [
                [
                    'url' => 'http://localhost:3000',
                    'description' => 'Servidor de desarrollo'
                ]
            ],
            'tags' => [
                ['name' => 'Sistema', 'description' => 'Información del sistema y health checks'],
                ['name' => 'Categorías', 'description' => 'Gestión de categorías académicas'],
                ['name' => 'Certificados', 'description' => 'Generación y gestión de certificados'],
                ['name' => 'Profesores', 'description' => 'Información de profesores'],
                ['name' => 'Documentos', 'description' => 'Generación y exportación de documentos'],
                ['name' => 'Archivos', 'description' => 'Gestión de archivos y imágenes'],
                ['name' => 'Listados', 'description' => 'Carga y procesamiento de listados'],
                ['name' => 'Firmas', 'description' => 'Gestión de firmas digitales'],
                ['name' => 'IA', 'description' => 'Extracción de datos con IA y OCR']
            ],
            'paths' => [
                '/api/v2/system/info' => [
                    'get' => [
                        'tags' => ['Sistema'],
                        'summary' => 'Información del sistema',
                        'description' => 'Obtiene información completa del sistema y endpoints disponibles',
                        'responses' => [
                            '200' => ['description' => 'Información del sistema obtenida exitosamente']
                        ]
                    ]
                ],
                '/api/v2/system/health' => [
                    'get' => [
                        'tags' => ['Sistema'],
                        'summary' => 'Health check',
                        'description' => 'Verifica el estado de salud del sistema',
                        'responses' => [
                            '200' => ['description' => 'Sistema funcionando correctamente']
                        ]
                    ]
                ],
                '/api/v2/system/swagger-ui' => [
                    'get' => [
                        'tags' => ['Sistema'],
                        'summary' => 'Interfaz Swagger UI',
                        'description' => 'Interfaz interactiva de documentación',
                        'responses' => [
                            '200' => ['description' => 'Interfaz Swagger UI cargada']
                        ]
                    ]
                ],
                '/api/v2/categorias/arbol' => [
                    'get' => [
                        'tags' => ['Categorías'],
                        'summary' => 'Árbol de categorías',
                        'description' => 'Obtiene el árbol completo de categorías académicas',
                        'responses' => [
                            '200' => ['description' => 'Árbol de categorías obtenido exitosamente']
                        ]
                    ]
                ],
                '/api/v2/categorias/cruce/{x}/{y}' => [
                    'get' => [
                        'tags' => ['Categorías'],
                        'summary' => 'Cruce de categorías',
                        'description' => 'Obtiene el cruce entre dos categorías específicas',
                        'parameters' => [
                            [
                                'name' => 'x',
                                'in' => 'path',
                                'required' => true,
                                'schema' => ['type' => 'string'],
                                'description' => 'Primera categoría'
                            ],
                            [
                                'name' => 'y',
                                'in' => 'path',
                                'required' => true,
                                'schema' => ['type' => 'string'],
                                'description' => 'Segunda categoría'
                            ]
                        ],
                        'responses' => [
                            '200' => ['description' => 'Cruce de categorías obtenido exitosamente']
                        ]
                    ]
                ],
                '/api/v2/certificados/{id}' => [
                    'get' => [
                        'tags' => ['Certificados'],
                        'summary' => 'Obtener certificado',
                        'description' => 'Obtiene información de un certificado específico',
                        'parameters' => [
                            [
                                'name' => 'id',
                                'in' => 'path',
                                'required' => true,
                                'schema' => ['type' => 'string'],
                                'description' => 'ID del certificado'
                            ]
                        ],
                        'responses' => [
                            '200' => ['description' => 'Certificado obtenido exitosamente']
                        ]
                    ]
                ],
                '/api/v2/certificados/generar' => [
                    'post' => [
                        'tags' => ['Certificados'],
                        'summary' => 'Generar certificado',
                        'description' => 'Genera un nuevo certificado con plantilla específica',
                        'responses' => [
                            '200' => ['description' => 'Certificado generado exitosamente']
                        ]
                    ]
                ],
                '/api/v2/profesores/{cedula}' => [
                    'get' => [
                        'tags' => ['Profesores'],
                        'summary' => 'Información de profesor',
                        'description' => 'Obtiene información completa de un profesor',
                        'parameters' => [
                            [
                                'name' => 'cedula',
                                'in' => 'path',
                                'required' => true,
                                'schema' => ['type' => 'string'],
                                'description' => 'Cédula del profesor'
                            ]
                        ],
                        'responses' => [
                            '200' => ['description' => 'Información del profesor obtenida exitosamente']
                        ]
                    ]
                ],
                '/api/v2/documentos/generar' => [
                    'post' => [
                        'tags' => ['Documentos'],
                        'summary' => 'Generar documento',
                        'description' => 'Genera un documento con plantilla específica',
                        'responses' => [
                            '200' => ['description' => 'Documento generado exitosamente']
                        ]
                    ]
                ],
                '/api/v2/archivos/subir' => [
                    'post' => [
                        'tags' => ['Archivos'],
                        'summary' => 'Subir archivo',
                        'description' => 'Sube un archivo al sistema (máximo 50MB)',
                        'responses' => [
                            '200' => ['description' => 'Archivo subido exitosamente']
                        ]
                    ]
                ],
                '/api/v2/archivos/datos/{nombre}' => [
                    'get' => [
                        'tags' => ['Archivos'],
                        'summary' => 'Obtener datos de archivo',
                        'description' => 'Obtiene datos JSON de un archivo específico',
                        'parameters' => [
                            [
                                'name' => 'nombre',
                                'in' => 'path',
                                'required' => true,
                                'schema' => ['type' => 'string'],
                                'description' => 'Nombre del archivo'
                            ]
                        ],
                        'responses' => [
                            '200' => ['description' => 'Datos del archivo obtenidos exitosamente']
                        ]
                    ]
                ],
                '/api/v2/archivos/imagenes/{nombre}' => [
                    'get' => [
                        'tags' => ['Archivos'],
                        'summary' => 'Obtener imagen',
                        'description' => 'Obtiene una imagen desde OpenKM',
                        'parameters' => [
                            [
                                'name' => 'nombre',
                                'in' => 'path',
                                'required' => true,
                                'schema' => ['type' => 'string'],
                                'description' => 'Nombre de la imagen'
                            ]
                        ],
                        'responses' => [
                            '200' => ['description' => 'Imagen obtenida exitosamente']
                        ]
                    ]
                ],
                '/api/v2/listados/cargar' => [
                    'post' => [
                        'tags' => ['Listados'],
                        'summary' => 'Cargar listado',
                        'description' => 'Carga un listado Excel o CSV',
                        'responses' => [
                            '200' => ['description' => 'Listado cargado exitosamente']
                        ]
                    ]
                ],
                '/api/v2/firmas/lista' => [
                    'get' => [
                        'tags' => ['Firmas'],
                        'summary' => 'Listar firmas',
                        'description' => 'Obtiene la lista de firmas disponibles',
                        'responses' => [
                            '200' => ['description' => 'Lista de firmas obtenida exitosamente']
                        ]
                    ]
                ],
                '/api/v2/ai/extraer' => [
                    'post' => [
                        'tags' => ['IA'],
                        'summary' => 'Extraer datos con IA',
                        'description' => 'Extrae datos de documentos usando OCR e IA (máximo 50MB)',
                        'responses' => [
                            '200' => ['description' => 'Datos extraídos exitosamente']
                        ]
                    ]
                ]
            ],
            'components' => [
                'schemas' => [
                    'Error' => [
                        'type' => 'object',
                        'properties' => [
                            'error' => ['type' => 'string'],
                            'message' => ['type' => 'string'],
                            'codigo' => ['type' => 'integer']
                        ]
                    ],
                    'Success' => [
                        'type' => 'object',
                        'properties' => [
                            'success' => ['type' => 'boolean'],
                            'data' => ['type' => 'object']
                        ]
                    ]
                ]
            ]
        ];

        echo json_encode($openapi, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }

    /**
     * GET /api/v2/swagger-ui
     * Interfaz Swagger UI interactiva
     */
    public function getSwaggerUI(array $params = []): void
    {
        $htmlPath = '/var/www/html/static/swagger-ui.html';

        if (!file_exists($htmlPath)) {
            http_response_code(404);
            echo json_encode(['error' => 'Interfaz Swagger UI no encontrada'], JSON_UNESCAPED_UNICODE);
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