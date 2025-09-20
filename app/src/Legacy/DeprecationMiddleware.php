<?php

/**
 * RUND API - Deprecation Middleware
 *
 * Middleware para marcar endpoints como deprecados y guiar hacia v2.
 *
 * @author ESAP Development Team / Oliver Castelblanco Martínez
 * @version 2.0
 * @since PHP 8.3
 */

declare(strict_types=1);

namespace RUND\Middleware;

class DeprecationMiddleware
{
    /**
     * Mapa de endpoints v1 deprecados y sus equivalentes v2
     */
    private const DEPRECATED_ENDPOINTS = [
        'getCategorias' => '/api/v2/categorias/arbol',
        'getCruce' => '/api/v2/categorias/cruce/{x}/{y}',
        'getInfoProfesor' => '/api/v2/profesores/{cedula}',
        'imagen' => '/api/v2/archivos/imagenes/{nombre}',
        'getCsvData' => '/api/v2/archivos/datos/{nombre}',
        'getCertificadoInfo' => '/api/v2/certificados/{id}',
        'getFirmas' => '/api/v2/firmas/lista',
        'extraeDatos' => '/api/v2/ai/extraer',
        'loadList' => '/api/v2/listados/cargar',
        'info' => '/api/v2/system/info',
        'health' => '/api/v2/system/health'
    ];

    /**
     * Middleware para agregar headers de deprecación
     */
    public static function markAsDeprecated(): callable
    {
        return function() {
            $requestUri = $_SERVER['REQUEST_URI'] ?? '';
            $path = parse_url($requestUri, PHP_URL_PATH);

            // Extraer el endpoint de la ruta
            $endpoint = '';
            if (preg_match('/\/([^\/\?]+)(?:\?|$)/', $path, $matches)) {
                $endpoint = $matches[1];
            }

            // Si el endpoint está en la lista de deprecados
            if (isset(self::DEPRECATED_ENDPOINTS[$endpoint])) {
                $v2Endpoint = self::DEPRECATED_ENDPOINTS[$endpoint];

                // Headers estándar de deprecación
                header('Deprecation: true');
                header('Sunset: 2025-12-31'); // Fecha de descontinuación
                header('Link: <' . $v2Endpoint . '>; rel="successor-version"');
                header('Warning: 299 - "Este endpoint está deprecado. Use la API v2."');

                // Header personalizado con información de migración
                header('X-RUND-Deprecation: v1-deprecated');
                header('X-RUND-Migration: ' . $v2Endpoint);
                header('X-RUND-Migration-Guide: /api/v2/docs');

                // Log de uso de endpoint deprecado
                error_log(sprintf(
                    'DEPRECATED ENDPOINT USED: %s (Client: %s, User-Agent: %s)',
                    $endpoint,
                    $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                    $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
                ));
            }
        };
    }

    /**
     * Obtiene información de deprecación para un endpoint
     */
    public static function getDeprecationInfo(string $endpoint): ?array
    {
        if (!isset(self::DEPRECATED_ENDPOINTS[$endpoint])) {
            return null;
        }

        return [
            'deprecated' => true,
            'sunset_date' => '2025-12-31',
            'successor' => self::DEPRECATED_ENDPOINTS[$endpoint],
            'migration_guide' => '/api/v2/docs',
            'message' => 'Este endpoint está deprecado. Migre a la API v2 para obtener mejores funcionalidades.'
        ];
    }

    /**
     * Lista todos los endpoints deprecados
     */
    public static function getDeprecatedEndpoints(): array
    {
        return self::DEPRECATED_ENDPOINTS;
    }

    /**
     * Verifica si un endpoint está deprecado
     */
    public static function isDeprecated(string $endpoint): bool
    {
        return isset(self::DEPRECATED_ENDPOINTS[$endpoint]);
    }
}