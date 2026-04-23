<?php

/**
 * RUND API - Servicio de Lista Blanca de Roles
 *
 * Gestiona los roles de usuarios por frontend (app) a partir de un archivo
 * whitelist.json almacenado en OpenKM. El archivo está particionado por
 * app_id (ej: "rund-mgp"), permitiendo que cada frontend tenga su propia
 * asignación de roles independiente.
 *
 * Estructura de whitelist.json:
 * {
 *   "version": 1,
 *   "apps": {
 *     "rund-mgp": {
 *       "usuarios": { "email@esap.edu.co": ["admin"] },
 *       "default_roles": ["usuario"]
 *     }
 *   }
 * }
 *
 * @author ESAP Development Team / Oliver Castelblanco Martínez
 * @version 1.0
 * @since PHP 8.3
 */

declare(strict_types=1);

namespace RUND\Services;

use RUND\Config\Config;
use RUND\Core\OpenKM;

class WhitelistService
{
    /**
     * Cache estática por request (se invalida al guardar)
     */
    private static ?array $cache = null;

    /**
     * Roles válidos reconocidos por el frontend
     */
    public const ROLES_VALIDOS = ['admin', 'gestor', 'directivo', 'usuario'];

    /**
     * Roles por defecto cuando no hay app_id o el usuario no está en la lista
     */
    private const DEFAULT_ROLES = ['usuario'];

    // =========================================================================
    // CONSULTA
    // =========================================================================

    /**
     * Devuelve los roles de un usuario para un frontend específico.
     *
     * @param string $email    Email del usuario (clave en la lista blanca)
     * @param string $appId    Identificador del frontend (ej: "rund-mgp"), enviado via X-App-Id
     * @return array           Array de roles, ej: ["admin"] o ["usuario"]
     */
    public static function getRolesForUser(string $email, string $appId): array
    {
        if (empty($appId)) {
            return self::DEFAULT_ROLES;
        }

        $whitelist = self::load();

        // App no registrada en la lista blanca → rol por defecto
        $appData = $whitelist['apps'][$appId] ?? null;
        if (!$appData) {
            return self::DEFAULT_ROLES;
        }

        // Usuario específico en la lista → sus roles
        $email = strtolower(trim($email));
        if (isset($appData['usuarios'][$email])) {
            return (array) $appData['usuarios'][$email];
        }

        // Usuario no en lista → default_roles del app, o global por defecto
        return (array) ($appData['default_roles'] ?? self::DEFAULT_ROLES);
    }

    // =========================================================================
    // LECTURA Y ESCRITURA
    // =========================================================================

    /**
     * Carga el whitelist.json desde OpenKM con cache por request.
     * Si el archivo no existe, devuelve estructura vacía (no bloquea el login).
     */
    public static function load(): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }

        try {
            $data = OpenKM::getDataFile(Config::WHITELIST_FILE);
            // getDataFile devuelve ["error" => "..."] si no encuentra el archivo
            if (isset($data['error'])) {
                self::$cache = ['version' => 1, 'apps' => []];
            } else {
                self::$cache = $data;
            }
        } catch (\Throwable $e) {
            // No interrumpir el login si OpenKM falla
            self::$cache = ['version' => 1, 'apps' => []];
        }

        return self::$cache;
    }

    /**
     * Guarda el whitelist.json en OpenKM e invalida la cache.
     *
     * @param array $whitelist  Estructura completa del whitelist
     * @throws \RuntimeException Si la escritura falla
     */
    public static function save(array $whitelist): void
    {
        $whitelist['last_updated'] = date('c'); // ISO 8601

        // Crear archivo temporal con el JSON
        $tmpFile = tempnam(Config::TEMP_DIR, 'whitelist_') . '.json';
        file_put_contents($tmpFile, json_encode($whitelist, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        $archivo = [
            'error'    => 0,
            'name'     => 'whitelist.json',
            'tmp_name' => $tmpFile,
            'type'     => 'application/json',
            'size'     => filesize($tmpFile),
        ];

        $propiedades = [
            ['label' => 'Nombre',     'valor' => 'whitelist.json'],
            ['label' => 'Comentario', 'valor' => 'Actualización lista blanca ' . date('Y-m-d H:i:s')],
        ];

        // Determinar si ya existe (versión) o es nuevo (crear)
        $existing = OpenKM::getDataFile(Config::WHITELIST_FILE);
        $esNuevaVersion = !isset($existing['error']);

        $resultado = OpenKM::cargaArchivo($archivo, $propiedades, Config::TAX_APP_DATA, $esNuevaVersion ?: null);

        @unlink($tmpFile);

        if (!empty($resultado['error'])) {
            throw new \RuntimeException('Error al guardar whitelist.json: ' . $resultado['error']);
        }

        // Invalidar cache para que el siguiente login lea los datos actualizados
        self::$cache = null;
    }

    // =========================================================================
    // HELPERS PARA EL CONTROLADOR
    // =========================================================================

    /**
     * Establece o actualiza los roles de un usuario en un app específico.
     */
    public static function setUsuario(string $appId, string $email, array $roles): void
    {
        $whitelist = self::load();
        $email = strtolower(trim($email));

        if (!isset($whitelist['apps'][$appId])) {
            $whitelist['apps'][$appId] = ['usuarios' => [], 'default_roles' => ['usuario']];
        }

        $whitelist['apps'][$appId]['usuarios'][$email] = $roles;
        self::save($whitelist);
    }

    /**
     * Elimina un usuario de un app específico.
     */
    public static function removeUsuario(string $appId, string $email): bool
    {
        $whitelist = self::load();
        $email = strtolower(trim($email));

        if (!isset($whitelist['apps'][$appId]['usuarios'][$email])) {
            return false;
        }

        unset($whitelist['apps'][$appId]['usuarios'][$email]);
        self::save($whitelist);
        return true;
    }

    /**
     * Valida que todos los roles del array sean válidos.
     */
    public static function validarRoles(array $roles): bool
    {
        foreach ($roles as $rol) {
            if (!in_array($rol, self::ROLES_VALIDOS, true)) {
                return false;
            }
        }
        return !empty($roles);
    }
}
