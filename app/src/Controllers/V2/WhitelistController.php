<?php

/**
 * RUND API - Controlador de Lista Blanca de Roles
 *
 * Endpoints para gestionar la whitelist.json almacenada en OpenKM.
 * Rutas protegidas por rol 'admin', excepto /seed (internalOnly).
 *
 * @author ESAP Development Team / Oliver Castelblanco Martínez
 * @version 1.0
 * @since PHP 8.3
 */

declare(strict_types=1);

namespace RUND\Controllers\V2;

use RUND\Controllers\BaseController;
use RUND\Services\WhitelistService;

class WhitelistController extends BaseController
{
    /**
     * GET /api/v2/admin/whitelist
     * Devuelve la whitelist completa (todos los apps).
     */
    public function getWhitelist(): array
    {
        $whitelist = WhitelistService::load();
        return $this->successResponse($whitelist);
    }

    /**
     * GET /api/v2/admin/whitelist/{app_id}
     * Devuelve la whitelist de un app específico.
     */
    public function getApp(string $appId): array
    {
        $whitelist = WhitelistService::load();

        $appData = $whitelist['apps'][$appId] ?? null;
        if ($appData === null) {
            return $this->errorResponse("App '$appId' no encontrado en la lista blanca", 404);
        }

        return $this->successResponse([
            'app_id'  => $appId,
            'data'    => $appData,
            'roles_validos' => WhitelistService::ROLES_VALIDOS,
        ]);
    }

    /**
     * PUT /api/v2/admin/whitelist/{app_id}/usuario
     * Crea o actualiza los roles de un usuario en un app específico.
     *
     * Body: { "email": "usuario@esap.edu.co", "roles": ["admin"] }
     */
    public function setUsuario(string $appId): array
    {
        $data = $this->getPostData();

        $validation = $this->validateRequired($data, ['email', 'roles']);
        if (!empty($validation)) {
            return $validation;
        }

        $email = trim((string) $data['email']);
        $roles = (array) $data['roles'];

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $this->errorResponse('El campo email no es válido', 400);
        }

        if (!WhitelistService::validarRoles($roles)) {
            return $this->errorResponse(
                'Roles inválidos. Valores permitidos: ' . implode(', ', WhitelistService::ROLES_VALIDOS),
                400
            );
        }

        try {
            WhitelistService::setUsuario($appId, $email, $roles);
        } catch (\RuntimeException $e) {
            return $this->errorResponse('Error al guardar: ' . $e->getMessage(), 500);
        }

        return $this->successResponse(
            ['app_id' => $appId, 'email' => $email, 'roles' => $roles],
            "Usuario '$email' actualizado en '$appId'"
        );
    }

    /**
     * DELETE /api/v2/admin/whitelist/{app_id}/usuario/{email}
     * Elimina un usuario de un app específico.
     */
    public function removeUsuario(string $appId, string $email): array
    {
        $email = urldecode($email);

        try {
            $removed = WhitelistService::removeUsuario($appId, $email);
        } catch (\RuntimeException $e) {
            return $this->errorResponse('Error al guardar: ' . $e->getMessage(), 500);
        }

        if (!$removed) {
            return $this->errorResponse("Usuario '$email' no encontrado en '$appId'", 404);
        }

        return $this->successResponse(
            ['app_id' => $appId, 'email' => $email],
            "Usuario '$email' eliminado de '$appId'"
        );
    }

    /**
     * POST /api/v2/admin/whitelist/seed
     * Crea el whitelist.json inicial en OpenKM.
     * Solo accesible desde la red interna Docker (internalOnly).
     *
     * Body: {
     *   "apps": {
     *     "rund-mgp": {
     *       "usuarios": { "email@esap.edu.co": ["admin"] },
     *       "default_roles": ["usuario"]
     *     }
     *   }
     * }
     */
    public function seed(): array
    {
        $data = $this->getPostData();

        // Validar estructura mínima
        if (empty($data['apps']) || !is_array($data['apps'])) {
            return $this->errorResponse('Se requiere el campo "apps" con al menos una entrada', 400);
        }

        // Validar roles en cada app
        foreach ($data['apps'] as $appId => $appData) {
            foreach ($appData['usuarios'] ?? [] as $email => $roles) {
                if (!WhitelistService::validarRoles((array) $roles)) {
                    return $this->errorResponse(
                        "Roles inválidos para '$email' en '$appId'. Permitidos: " . implode(', ', WhitelistService::ROLES_VALIDOS),
                        400
                    );
                }
                // Normalizar emails a minúsculas
                unset($data['apps'][$appId]['usuarios'][$email]);
                $data['apps'][$appId]['usuarios'][strtolower(trim($email))] = (array) $roles;
            }
        }

        $whitelist = [
            'version' => 1,
            'apps'    => $data['apps'],
        ];

        try {
            WhitelistService::save($whitelist);
        } catch (\RuntimeException $e) {
            return $this->errorResponse('Error al crear whitelist.json: ' . $e->getMessage(), 500);
        }

        return $this->successResponse(
            $whitelist,
            'whitelist.json creado exitosamente en OpenKM'
        );
    }
}
