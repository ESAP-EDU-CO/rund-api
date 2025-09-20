<?php

/**
 * RUND API - Sistema de Aliases de Compatibilidad
 *
 * Mapea endpoints v1 a v2 para mantener compatibilidad durante la migración.
 * Permite que ambas versiones funcionen simultáneamente.
 *
 * @author ESAP Development Team / Oliver Castelblanco Martínez
 * @version 2.0
 * @since PHP 8.3
 */

declare(strict_types=1);

namespace RUND\Core;

class CompatibilityAliases
{
    /**
     * Mapeo de rutas v1 a v2
     */
    private static array $aliases = [
        // Sistema
        'GET /info' => 'GET /api/v2/system/info',
        'GET /health' => 'GET /api/v2/system/health',
        'GET /files' => 'GET /api/v2/system/capabilities',

        // Categorías
        'GET /getCategorias' => 'GET /api/v2/categorias/arbol',
        'GET /getCruce' => 'GET /api/v2/categorias/cruce/{x}/{y}',

        // Profesores
        'GET /getInfoProfesor' => 'GET /api/v2/profesores/{cedula}',

        // Archivos
        'DELETE /deleteFile' => 'DELETE /api/v2/archivos/{uuid}',
        'GET /delReporte' => 'DELETE /api/v2/archivos/temp/limpiar',
        'POST /postFile' => 'POST /api/v2/archivos/subir',

        // Datos específicos
        'GET /getFile[tipo=data]' => 'GET /api/v2/archivos/datos/{nombre}',
        'GET /getFile[tipo=imagen]' => 'GET /api/v2/archivos/imagenes/{nombre}',
        'GET /imagen' => 'GET /api/v2/archivos/imagenes/{nombre}',

        // Certificados
        'GET /getCertificadoInfo' => 'GET /api/v2/certificados/{id}',
        'POST /getCertificado' => 'POST /api/v2/certificados/generar',

        // Pendientes (mantener v1 por ahora)
        'GET /getCsvData' => 'v1_only',
        'POST /getConsultaFile' => 'v1_only',
        'GET /loadList' => 'v1_only',
        'POST /loadList' => 'v1_only',
        'GET /getFirmas' => 'v1_only',
        'POST /extraeDatos' => 'v1_only',
    ];

    /**
     * Verifica si existe un alias para la ruta dada
     */
    public static function hasAlias(string $method, string $path): bool
    {
        $key = strtoupper($method) . ' ' . $path;
        return isset(self::$aliases[$key]);
    }

    /**
     * Obtiene el alias v2 para una ruta v1
     */
    public static function getAlias(string $method, string $path): ?string
    {
        $key = strtoupper($method) . ' ' . $path;
        return self::$aliases[$key] ?? null;
    }

    /**
     * Obtiene información sobre el estado de migración
     */
    public static function getMigrationStatus(): array
    {
        $total = count(self::$aliases);
        $migrated = count(array_filter(self::$aliases, fn($alias) => $alias !== 'v1_only'));
        $pending = $total - $migrated;

        return [
            'total_endpoints' => $total,
            'migrados_v2' => $migrated,
            'pendientes_v1' => $pending,
            'progreso' => round(($migrated / $total) * 100, 1) . '%'
        ];
    }

    /**
     * Lista todos los aliases disponibles
     */
    public static function getAllAliases(): array
    {
        return self::$aliases;
    }
}