<?php

/**
 * RUND API - Auth Middleware
 *
 * Maneja autenticación y autorización mediante JWT y sesiones PHP.
 * Integrado con rund-auth para autenticación centralizada.
 *
 * @author ESAP Development Team / Oliver Castelblanco Martínez
 * @version 4.0
 * @since PHP 8.3
 */

declare(strict_types=1);

namespace RUND\Middleware;

use RUND\Services\JWTValidator;
use RUND\Services\AuthService;

class AuthMiddleware
{
	/**
	 * Nombre de la sesión PHP (debe coincidir con AuthController)
	 */
	private const SESSION_NAME = 'RUND_SESSION';

	/**
	 * Clave para JWT en sesión
	 */
	private const SESSION_JWT_KEY = 'internal_jwt';

	/**
	 * Clave para datos de usuario en sesión
	 */
	private const SESSION_USER_KEY = 'user';

	/**
	 * Clave para última actividad
	 */
	private const SESSION_LAST_ACTIVITY = 'last_activity';

	/**
	 * Timeout de sesión (8 horas)
	 */
	private const SESSION_TIMEOUT = 28800;

	/**
	 * Middleware de autenticación mediante sesión PHP
	 * Verifica que exista una sesión válida con JWT
	 */
	public static function authenticate(): callable
	{
		return function (string $method, string $path, array $params = []): bool {
			// Iniciar sesión si no está activa
			self::initSession();

			// Verificar timeout de inactividad
			if (!self::checkSessionTimeout()) {
				self::sendUnauthorized('Sesión expirada por inactividad');
				return false;
			}

			// Verificar que exista usuario en sesión
			if (!isset($_SESSION[self::SESSION_USER_KEY])) {
				self::sendUnauthorized('Sesión no iniciada');
				return false;
			}

			// Verificar que exista JWT en sesión
			$jwt = $_SESSION[self::SESSION_JWT_KEY] ?? null;
			if (!$jwt) {
				self::sendUnauthorized('JWT no encontrado en sesión');
				return false;
			}

			// Validar JWT
			try {
				$validator = new JWTValidator(new AuthService());
				$validator->validate($jwt);

				// Actualizar última actividad
				$_SESSION[self::SESSION_LAST_ACTIVITY] = time();

				return true;
			} catch (\Exception $e) {
				// JWT inválido o expirado, limpiar sesión
				self::clearSession();
				self::sendUnauthorized('JWT inválido o expirado: ' . $e->getMessage());
				return false;
			}
		};
	}

	/**
	 * Middleware de autorización por roles
	 * Verifica que el usuario tenga el rol requerido
	 */
	public static function requireRole(string $requiredRole): callable
	{
		return function (string $method, string $path, array $params = []) use ($requiredRole): bool {
			self::initSession();

			$user = $_SESSION[self::SESSION_USER_KEY] ?? null;

			if (!$user) {
				self::sendForbidden('Usuario no autenticado');
				return false;
			}

			$userRoles = $user['roles'] ?? [];

			// Si no tiene el rol requerido
			if (!in_array($requiredRole, $userRoles)) {
				self::sendForbidden("Se requiere el rol: $requiredRole");
				return false;
			}

			return true;
		};
	}

	/**
	 * Middleware para limitar por IP (útil para endpoints administrativos)
	 */
	public static function limitToIPs(array $allowedIPs): callable
	{
		return function (string $method, string $path, array $params = []) use ($allowedIPs): bool {
			$clientIP = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

			if (!in_array($clientIP, $allowedIPs)) {
				http_response_code(403);
				header('Content-Type: application/json; charset=utf-8');
				echo json_encode([
					'error' => 'Acceso denegado desde esta IP',
					'client_ip' => $clientIP
				], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
				return false;
			}

			return true;
		};
	}

	/**
	 * Middleware para endpoints internos (solo red Docker)
	 * Verifica que la IP sea de la red interna
	 */
	public static function internalOnly(): callable
	{
		return function (string $method, string $path, array $params = []): bool {
			$clientIP = $_SERVER['REMOTE_ADDR'] ?? '';

			// Permitir localhost y redes Docker privadas
			$allowedPatterns = [
				'/^127\./',           // localhost
				'/^172\.(1[6-9]|2[0-9]|3[0-1])\./', // Docker default bridge
				'/^10\./',            // Docker custom networks
				'/^192\.168\./'       // Local networks
			];

			foreach ($allowedPatterns as $pattern) {
				if (preg_match($pattern, $clientIP)) {
					return true;
				}
			}

			http_response_code(403);
			header('Content-Type: application/json; charset=utf-8');
			echo json_encode([
				'error' => 'Este endpoint solo es accesible desde la red interna',
				'client_ip' => $clientIP
			], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
			return false;
		};
	}

	/**
	 * Inicializa la sesión PHP si no está activa
	 */
	private static function initSession(): void
	{
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
	 * Verifica el timeout de inactividad
	 */
	private static function checkSessionTimeout(): bool
	{
		if (!isset($_SESSION[self::SESSION_LAST_ACTIVITY])) {
			return false;
		}

		$inactive = time() - $_SESSION[self::SESSION_LAST_ACTIVITY];

		if ($inactive > self::SESSION_TIMEOUT) {
			self::clearSession();
			return false;
		}

		return true;
	}

	/**
	 * Limpia la sesión actual
	 */
	private static function clearSession(): void
	{
		$_SESSION = [];

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

		session_destroy();
	}

	/**
	 * Envía respuesta 401 Unauthorized
	 */
	private static function sendUnauthorized(string $message): void
	{
		http_response_code(401);
		header('Content-Type: application/json; charset=utf-8');
		echo json_encode([
			'error' => $message,
			'code' => 'UNAUTHORIZED'
		], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
	}

	/**
	 * Envía respuesta 403 Forbidden
	 */
	private static function sendForbidden(string $message): void
	{
		http_response_code(403);
		header('Content-Type: application/json; charset=utf-8');
		echo json_encode([
			'error' => $message,
			'code' => 'FORBIDDEN'
		], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
	}

	/**
	 * Obtiene el usuario actual de la sesión
	 */
	public static function getCurrentUser(): ?array
	{
		self::initSession();
		return $_SESSION[self::SESSION_USER_KEY] ?? null;
	}

	/**
	 * Obtiene el JWT actual de la sesión
	 */
	public static function getCurrentJWT(): ?string
	{
		self::initSession();
		return $_SESSION[self::SESSION_JWT_KEY] ?? null;
	}
}
