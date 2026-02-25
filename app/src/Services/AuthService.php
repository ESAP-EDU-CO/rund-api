<?php

/**
 * RUND API - Servicio de Autenticación
 *
 * Servicio que maneja la comunicación con el módulo rund-auth.
 * Actúa como proxy/BFF entre los frontends y el servicio de autenticación.
 *
 * @author ESAP Development Team / Oliver Castelblanco Martínez
 * @version 1.0
 * @since PHP 8.3
 */

declare(strict_types=1);

namespace RUND\Services;

use RUND\Config\Config;
use Exception;
use RuntimeException;

class AuthService
{
	/**
	 * URL base del servicio rund-auth
	 */
	private string $authBaseUrl;

	/**
	 * Timeout para peticiones HTTP (en segundos)
	 */
	private int $timeout;

	public function __construct(?string $authBaseUrl = null, int $timeout = 10)
	{
		$this->authBaseUrl = $authBaseUrl ?? Config::RUND_AUTH_URL;
		$this->timeout = $timeout;
	}

	/**
	 * Autentica un usuario mediante LDAP
	 *
	 * @param string $username Nombre de usuario (sAMAccountName)
	 * @param string $password Contraseña del usuario
	 * @return array Datos del usuario autenticado y JWT interno
	 * @throws RuntimeException Si la autenticación falla
	 */
	public function loginWithLDAP(string $username, string $password): array
	{
		$url = $this->authBaseUrl . '/ldap/login';

		$payload = [
			'username' => $username,
			'password' => $password
		];

		try {
			$response = $this->makeRequest('POST', $url, $payload);

			if (!isset($response['user']) || !isset($response['internal_jwt'])) {
				throw new RuntimeException('Respuesta inválida del servicio de autenticación');
			}

			return [
				'user' => $response['user'],
				'jwt' => $response['internal_jwt']
			];
		} catch (Exception $e) {
			throw new RuntimeException(
				'Error en autenticación LDAP: ' . $e->getMessage(),
				$e->getCode(),
				$e
			);
		}
	}

	/**
	 * Autentica un usuario mediante OAuth 2.0 (Azure AD)
	 *
	 * @return string URL de redirección para iniciar flujo OAuth
	 */
	public function getOAuthLoginUrl(): string
	{
		return $this->authBaseUrl . '/oauth/login';
	}

	/**
	 * Obtiene información de sesión del usuario en rund-auth
	 *
	 * @param string $sessionCookie Cookie de sesión de rund-auth
	 * @return array|null Datos del usuario o null si no hay sesión
	 */
	public function getSession(string $sessionCookie): ?array
	{
		$url = $this->authBaseUrl . '/session';

		try {
			$response = $this->makeRequest('GET', $url, null, [
				'Cookie: ' . $sessionCookie
			]);

			return $response['user'] ?? null;
		} catch (Exception $e) {
			// Si no hay sesión válida, retornar null en lugar de lanzar excepción
			return null;
		}
	}

	/**
	 * Cierra la sesión del usuario en rund-auth
	 *
	 * @param string $sessionCookie Cookie de sesión de rund-auth
	 * @return bool True si se cerró exitosamente
	 */
	public function logout(string $sessionCookie): bool
	{
		$url = $this->authBaseUrl . '/logout';

		try {
			$this->makeRequest('POST', $url, null, [
				'Cookie: ' . $sessionCookie
			]);
			return true;
		} catch (Exception $e) {
			// Incluso si falla, consideramos que la sesión debe cerrarse localmente
			return false;
		}
	}

	/**
	 * Refresca el JWT antes de su expiración
	 *
	 * @param string $sessionCookie Cookie de sesión de rund-auth
	 * @return string Nuevo JWT
	 * @throws RuntimeException Si falla el refresh
	 */
	public function refreshJWT(string $sessionCookie): string
	{
		$url = $this->authBaseUrl . '/session';

		try {
			$response = $this->makeRequest('GET', $url, null, [
				'Cookie: ' . $sessionCookie
			]);

			if (!isset($response['internal_jwt'])) {
				throw new RuntimeException('No se pudo obtener nuevo JWT');
			}

			return $response['internal_jwt'];
		} catch (Exception $e) {
			throw new RuntimeException(
				'Error al refrescar JWT: ' . $e->getMessage(),
				$e->getCode(),
				$e
			);
		}
	}

	/**
	 * Verifica el health del servicio rund-auth
	 *
	 * @return bool True si el servicio está disponible
	 */
	public function checkHealth(): bool
	{
		$url = $this->authBaseUrl . '/healthz';

		try {
			$response = $this->makeRequest('GET', $url);
			return isset($response['ok']) && $response['ok'] === true;
		} catch (Exception $e) {
			return false;
		}
	}

	/**
	 * Obtiene el JWKS público para validación de JWT
	 *
	 * @return array JWKS público
	 * @throws RuntimeException Si no se puede obtener el JWKS
	 */
	public function getPublicJWKS(): array
	{
		$url = $this->authBaseUrl . '/.well-known/jwks.json';

		try {
			return $this->makeRequest('GET', $url);
		} catch (Exception $e) {
			throw new RuntimeException(
				'Error al obtener JWKS público: ' . $e->getMessage(),
				$e->getCode(),
				$e
			);
		}
	}

	/**
	 * Realiza una petición HTTP a rund-auth
	 *
	 * @param string $method Método HTTP (GET, POST, etc.)
	 * @param string $url URL completa
	 * @param array|null $data Datos a enviar (para POST)
	 * @param array $headers Headers adicionales
	 * @return array Respuesta decodificada
	 * @throws RuntimeException Si la petición falla
	 */
	private function makeRequest(
		string $method,
		string $url,
		?array $data = null,
		array $headers = []
	): array {
		$ch = curl_init($url);

		if ($ch === false) {
			throw new RuntimeException('No se pudo inicializar cURL');
		}

		// Headers por defecto
		$defaultHeaders = [
			'Content-Type: application/json',
			'Accept: application/json',
			'Connection: close', // Fuerza cierre de conexión para evitar timeout en HTTP/1.1 keep-alive
		];

		curl_setopt_array($ch, [
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_TIMEOUT => $this->timeout,
			CURLOPT_CUSTOMREQUEST => $method,
			CURLOPT_HTTPHEADER => array_merge($defaultHeaders, $headers),
			CURLOPT_FOLLOWLOCATION => false, // No seguir redirects automáticamente
			CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_0, // HTTP/1.0: servidor cierra conexión y hace flush del buffer TCP
		]);

		// Si hay datos, enviarlos como JSON
		if ($data !== null && in_array($method, ['POST', 'PUT', 'PATCH'])) {
			curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
		}

		$response = curl_exec($ch);
		$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		$error = curl_error($ch);

		curl_close($ch);

		if ($response === false) {
			throw new RuntimeException('Error en petición cURL: ' . $error);
		}

		// Decodificar respuesta JSON
		$decoded = json_decode($response, true);

		if (json_last_error() !== JSON_ERROR_NONE) {
			throw new RuntimeException('Respuesta no es JSON válido: ' . json_last_error_msg());
		}

		// Verificar código HTTP
		if ($httpCode < 200 || $httpCode >= 300) {
			$errorMsg = $decoded['error'] ?? $decoded['message'] ?? 'Error desconocido';
			throw new RuntimeException($errorMsg, $httpCode);
		}

		return $decoded;
	}

	/**
	 * Login de desarrollo (solo cuando DEV_FAKE_LOGIN=true en rund-auth)
	 *
	 * @param string $email Email del usuario de prueba
	 * @return array Datos del usuario autenticado y JWT
	 * @throws RuntimeException Si falla el login
	 */
	public function devLogin(string $email): array
	{
		$url = $this->authBaseUrl . '/dev/login';

		$payload = [
			'email' => $email
		];

		try {
			$response = $this->makeRequest('POST', $url, $payload);

			if (!isset($response['user']) || !isset($response['internal_jwt'])) {
				throw new RuntimeException('Respuesta inválida del servicio de autenticación');
			}

			return [
				'user' => $response['user'],
				'jwt' => $response['internal_jwt']
			];
		} catch (Exception $e) {
			throw new RuntimeException(
				'Error en autenticación dev: ' . $e->getMessage(),
				$e->getCode(),
				$e
			);
		}
	}
}
