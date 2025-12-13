<?php

/**
 * RUND API - Controlador de Autenticación
 *
 * Controlador que maneja todos los endpoints de autenticación.
 * Actúa como BFF (Backend-for-Frontend) entre los clientes y rund-auth.
 *
 * @author ESAP Development Team / Oliver Castelblanco Martínez
 * @version 1.0
 * @since PHP 8.3
 */

declare(strict_types=1);

namespace RUND\Controllers\V2;

use RUND\Controllers\BaseController;
use RUND\Services\AuthService;
use RUND\Services\JWTValidator;
use Exception;

class AuthController extends BaseController
{
	private AuthService $authService;
	private JWTValidator $jwtValidator;

	/**
	 * Nombre de la sesión PHP
	 */
	private const SESSION_NAME = 'RUND_SESSION';

	/**
	 * Clave para almacenar el JWT en la sesión
	 */
	private const SESSION_JWT_KEY = 'internal_jwt';

	/**
	 * Clave para almacenar los datos del usuario en la sesión
	 */
	private const SESSION_USER_KEY = 'user';

	/**
	 * Clave para timestamp de última actividad
	 */
	private const SESSION_LAST_ACTIVITY = 'last_activity';

	/**
	 * Tiempo de inactividad permitido (8 horas)
	 */
	private const SESSION_TIMEOUT = 28800;

	public function __construct()
	{
		$this->authService = new AuthService();
		$this->jwtValidator = new JWTValidator($this->authService);
		$this->initSession();
	}

	/**
	 * POST /api/v2/auth/login
	 *
	 * Autentica un usuario mediante LDAP
	 */
	public function login(): array
	{
		$data = $this->getPostData();

		// Validar parámetros requeridos
		$validation = $this->validateRequired($data, ['username', 'password']);
		if (!empty($validation)) {
			return $validation;
		}

		try {
			// Autenticar con rund-auth
			$authResult = $this->authService->loginWithLDAP(
				$data['username'],
				$data['password']
			);

			// Guardar en sesión PHP (nunca exponer JWT al frontend)
			$_SESSION[self::SESSION_JWT_KEY] = $authResult['jwt'];
			$_SESSION[self::SESSION_USER_KEY] = $authResult['user'];
			$_SESSION[self::SESSION_LAST_ACTIVITY] = time();

			// Regenerar session ID por seguridad
			session_regenerate_id(true);

			return $this->successResponse([
				'user' => $authResult['user'],
				'session_id' => session_id()
			], 'Autenticación exitosa');
		} catch (Exception $e) {
			return $this->errorResponse(
				'Error de autenticación: ' . $e->getMessage(),
				401
			);
		}
	}

	/**
	 * GET /api/v2/auth/session
	 *
	 * Verifica la sesión actual y retorna datos del usuario
	 */
	public function getSession(): array
	{
		// Verificar timeout de sesión
		if (!$this->checkSessionTimeout()) {
			return $this->errorResponse('Sesión expirada por inactividad', 401);
		}

		// Verificar que exista sesión
		if (!isset($_SESSION[self::SESSION_USER_KEY])) {
			return $this->errorResponse('No hay sesión activa', 401);
		}

		// Actualizar última actividad
		$_SESSION[self::SESSION_LAST_ACTIVITY] = time();

		// Verificar si el JWT está próximo a expirar (< 5 minutos)
		$jwt = $_SESSION[self::SESSION_JWT_KEY] ?? null;
		$shouldRefresh = false;

		if ($jwt) {
			$claims = JWTValidator::extractClaimsUnsafe($jwt);
			if ($claims && isset($claims['exp'])) {
				$expiresIn = $claims['exp'] - time();
				$shouldRefresh = $expiresIn < 300; // Menos de 5 minutos
			}
		}

		return $this->successResponse([
			'user' => $_SESSION[self::SESSION_USER_KEY],
			'session_id' => session_id(),
			'should_refresh' => $shouldRefresh,
			'last_activity' => $_SESSION[self::SESSION_LAST_ACTIVITY] ?? null
		]);
	}

	/**
	 * POST /api/v2/auth/logout
	 *
	 * Cierra la sesión del usuario
	 */
	public function logout(): array
	{
		try {
			// Intentar cerrar sesión en rund-auth (opcional, best effort)
			if (isset($_COOKIE['connect.sid'])) {
				$this->authService->logout($_COOKIE['connect.sid']);
			}
		} catch (Exception $e) {
			// Ignorar errores, cerrar sesión local de todas formas
		}

		// Limpiar sesión PHP
		$_SESSION = [];

		// Eliminar cookie de sesión
		if (ini_get("session.use_cookies")) {
			$params = session_get_cookie_params();
			setcookie(
				self::SESSION_NAME,
				'',
				time() - 42000,
				$params["path"],
				$params["domain"],
				$params["secure"],
				$params["httponly"]
			);
		}

		// Destruir sesión
		session_destroy();

		return $this->successResponse([], 'Sesión cerrada exitosamente');
	}

	/**
	 * POST /api/v2/auth/refresh
	 *
	 * Refresca el JWT antes de su expiración
	 */
	public function refresh(): array
	{
		// Verificar que exista sesión
		if (!isset($_SESSION[self::SESSION_USER_KEY])) {
			return $this->errorResponse('No hay sesión activa', 401);
		}

		try {
			// Intentar refrescar JWT desde rund-auth
			if (isset($_COOKIE['connect.sid'])) {
				$newJwt = $this->authService->refreshJWT($_COOKIE['connect.sid']);
				$_SESSION[self::SESSION_JWT_KEY] = $newJwt;
				$_SESSION[self::SESSION_LAST_ACTIVITY] = time();

				return $this->successResponse([], 'JWT refrescado exitosamente');
			}

			return $this->errorResponse('No se pudo refrescar JWT', 500);
		} catch (Exception $e) {
			return $this->errorResponse(
				'Error al refrescar JWT: ' . $e->getMessage(),
				500
			);
		}
	}

	/**
	 * GET /api/v2/auth/health
	 *
	 * Verifica la salud del sistema de autenticación
	 */
	public function health(): array
	{
		$rundAuthOk = $this->authService->checkHealth();

		return $this->successResponse([
			'status' => $rundAuthOk ? 'ok' : 'degraded',
			'services' => [
				'rund-auth' => $rundAuthOk ? 'healthy' : 'unhealthy',
				'php-session' => session_status() === PHP_SESSION_ACTIVE ? 'healthy' : 'unhealthy'
			]
		]);
	}

	/**
	 * POST /api/v2/auth/dev/login
	 *
	 * Login de desarrollo (solo cuando DEV_FAKE_LOGIN=true)
	 * IMPORTANTE: NO usar en producción
	 */
	public function devLogin(): array
	{
		$data = $this->getPostData();

		// Validar parámetros requeridos
		$validation = $this->validateRequired($data, ['email']);
		if (!empty($validation)) {
			return $validation;
		}

		try {
			// Login de desarrollo con rund-auth
			$authResult = $this->authService->devLogin($data['email']);

			// Guardar en sesión PHP
			$_SESSION[self::SESSION_JWT_KEY] = $authResult['jwt'];
			$_SESSION[self::SESSION_USER_KEY] = $authResult['user'];
			$_SESSION[self::SESSION_LAST_ACTIVITY] = time();

			// Regenerar session ID
			session_regenerate_id(true);

			return $this->successResponse([
				'user' => $authResult['user'],
				'session_id' => session_id(),
				'warning' => 'DEV MODE - NO usar en producción'
			], 'Login de desarrollo exitoso');
		} catch (Exception $e) {
			return $this->errorResponse(
				'Error en dev login: ' . $e->getMessage(),
				500
			);
		}
	}

	/**
	 * GET /api/v2/auth/validate-jwt
	 *
	 * Endpoint interno para validar JWT (usado por middleware)
	 * NO exponer públicamente
	 */
	public function validateJWT(): array
	{
		$jwt = $_SESSION[self::SESSION_JWT_KEY] ?? null;

		if (!$jwt) {
			return $this->errorResponse('No hay JWT en sesión', 401);
		}

		try {
			$claims = $this->jwtValidator->validate($jwt);

			return $this->successResponse([
				'valid' => true,
				'claims' => $claims
			]);
		} catch (Exception $e) {
			// JWT inválido o expirado, limpiar sesión
			$this->logout();

			return $this->errorResponse(
				'JWT inválido: ' . $e->getMessage(),
				401
			);
		}
	}

	/**
	 * Inicializa la sesión PHP con configuración segura
	 */
	private function initSession(): void
	{
		// Configurar parámetros de sesión seguros
		if (session_status() === PHP_SESSION_NONE) {
			ini_set('session.cookie_httponly', '1');
			ini_set('session.use_only_cookies', '1');
			ini_set('session.cookie_samesite', 'Lax');

			// En producción, habilitar:
			// ini_set('session.cookie_secure', '1'); // Solo HTTPS

			session_name(self::SESSION_NAME);
			session_start();
		}
	}

	/**
	 * Verifica el timeout de inactividad de la sesión
	 *
	 * @return bool True si la sesión es válida, false si expiró
	 */
	private function checkSessionTimeout(): bool
	{
		if (!isset($_SESSION[self::SESSION_LAST_ACTIVITY])) {
			return false;
		}

		$inactive = time() - $_SESSION[self::SESSION_LAST_ACTIVITY];

		if ($inactive > self::SESSION_TIMEOUT) {
			// Sesión expirada por inactividad
			$this->logout();
			return false;
		}

		return true;
	}

	/**
	 * Obtiene el JWT de la sesión actual (para uso interno)
	 *
	 * @return string|null JWT o null si no existe
	 */
	public function getInternalJWT(): ?string
	{
		return $_SESSION[self::SESSION_JWT_KEY] ?? null;
	}

	/**
	 * Obtiene el usuario de la sesión actual
	 *
	 * @return array|null Datos del usuario o null si no hay sesión
	 */
	public function getCurrentUser(): ?array
	{
		return $_SESSION[self::SESSION_USER_KEY] ?? null;
	}
}
