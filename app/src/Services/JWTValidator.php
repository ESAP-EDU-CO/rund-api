<?php

/**
 * RUND API - Validador de JWT
 *
 * Servicio que valida JWT firmados con RS256 usando JWKS público de rund-auth.
 * Implementación nativa en PHP sin dependencias externas.
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

class JWTValidator
{
	/**
	 * Cache de JWKS público
	 */
	private static ?array $jwksCache = null;

	/**
	 * Timestamp de última actualización del cache
	 */
	private static ?int $jwksCacheTime = null;

	/**
	 * TTL del cache en segundos (5 minutos)
	 */
	private const CACHE_TTL = 300;

	/**
	 * Servicio de autenticación
	 */
	private AuthService $authService;

	public function __construct(?AuthService $authService = null)
	{
		$this->authService = $authService ?? new AuthService();
	}

	/**
	 * Valida un JWT y retorna sus claims
	 *
	 * @param string $token JWT a validar
	 * @param string $expectedIssuer Issuer esperado (default: rund-auth)
	 * @param string $expectedAudience Audience esperada (default: rund-api)
	 * @return array Claims del JWT si es válido
	 * @throws RuntimeException Si el token es inválido
	 */
	public function validate(
		string $token,
		string $expectedIssuer = 'rund-auth',
		string $expectedAudience = 'rund-api'
	): array {
		// 1. Decodificar header y payload sin verificar firma
		$parts = explode('.', $token);

		if (count($parts) !== 3) {
			throw new RuntimeException('JWT inválido: formato incorrecto');
		}

		[$headerB64, $payloadB64, $signatureB64] = $parts;

		// Decodificar header
		$header = $this->base64UrlDecode($headerB64);
		$headerData = json_decode($header, true);

		if (!$headerData || !isset($headerData['alg']) || !isset($headerData['kid'])) {
			throw new RuntimeException('JWT inválido: header incompleto');
		}

		// Verificar algoritmo
		if ($headerData['alg'] !== 'RS256') {
			throw new RuntimeException('JWT inválido: algoritmo no soportado (solo RS256)');
		}

		// Decodificar payload
		$payload = $this->base64UrlDecode($payloadB64);
		$claims = json_decode($payload, true);

		if (!$claims) {
			throw new RuntimeException('JWT inválido: payload incompleto');
		}

		// 2. Validar claims básicos
		$this->validateClaims($claims, $expectedIssuer, $expectedAudience);

		// 3. Obtener clave pública del JWKS
		$publicKey = $this->getPublicKey($headerData['kid']);

		// 4. Verificar firma
		$signature = $this->base64UrlDecode($signatureB64);
		$signedData = $headerB64 . '.' . $payloadB64;

		$verified = openssl_verify(
			$signedData,
			$signature,
			$publicKey,
			OPENSSL_ALGO_SHA256
		);

		if ($verified !== 1) {
			throw new RuntimeException('JWT inválido: firma incorrecta');
		}

		return $claims;
	}

	/**
	 * Valida los claims del JWT
	 *
	 * @param array $claims Claims del JWT
	 * @param string $expectedIssuer Issuer esperado
	 * @param string $expectedAudience Audience esperada
	 * @throws RuntimeException Si los claims son inválidos
	 */
	private function validateClaims(
		array $claims,
		string $expectedIssuer,
		string $expectedAudience
	): void {
		$now = time();

		// Validar issuer
		if (!isset($claims['iss']) || $claims['iss'] !== $expectedIssuer) {
			throw new RuntimeException('JWT inválido: issuer incorrecto');
		}

		// Validar audience (puede ser string o array)
		if (!isset($claims['aud'])) {
			throw new RuntimeException('JWT inválido: audience faltante');
		}

		$audiences = is_array($claims['aud']) ? $claims['aud'] : [$claims['aud']];
		if (!in_array($expectedAudience, $audiences)) {
			throw new RuntimeException('JWT inválido: audience incorrecto');
		}

		// Validar expiración
		if (!isset($claims['exp']) || $claims['exp'] < $now) {
			throw new RuntimeException('JWT expirado');
		}

		// Validar issued at (opcional pero recomendado)
		if (isset($claims['iat']) && $claims['iat'] > $now + 60) {
			// Permitir 60 segundos de clock skew
			throw new RuntimeException('JWT inválido: emitido en el futuro');
		}

		// Validar subject (debe existir)
		if (!isset($claims['sub']) || empty($claims['sub'])) {
			throw new RuntimeException('JWT inválido: subject faltante');
		}
	}

	/**
	 * Obtiene la clave pública del JWKS por kid
	 *
	 * @param string $kid Key ID
	 * @return string Clave pública en formato PEM
	 * @throws RuntimeException Si no se encuentra la clave
	 */
	private function getPublicKey(string $kid): string
	{
		$jwks = $this->getJWKS();

		if (!isset($jwks['keys']) || !is_array($jwks['keys'])) {
			throw new RuntimeException('JWKS inválido: formato incorrecto');
		}

		// Buscar la clave por kid
		foreach ($jwks['keys'] as $key) {
			if (isset($key['kid']) && $key['kid'] === $kid) {
				// Verificar que no esté retirada
				if (isset($key['retired']) && $key['retired'] === true) {
					throw new RuntimeException('JWT inválido: clave retirada');
				}

				return $this->jwkToPem($key);
			}
		}

		throw new RuntimeException('JWT inválido: clave no encontrada en JWKS');
	}

	/**
	 * Obtiene el JWKS público (con cache)
	 *
	 * @return array JWKS público
	 */
	private function getJWKS(): array
	{
		$now = time();

		// Verificar si el cache es válido
		if (
			self::$jwksCache !== null &&
			self::$jwksCacheTime !== null &&
			($now - self::$jwksCacheTime) < self::CACHE_TTL
		) {
			return self::$jwksCache;
		}

		// Obtener JWKS fresco
		self::$jwksCache = $this->authService->getPublicJWKS();
		self::$jwksCacheTime = $now;

		return self::$jwksCache;
	}

	/**
	 * Convierte un JWK a formato PEM
	 *
	 * @param array $jwk JWK
	 * @return string Clave pública en formato PEM
	 * @throws RuntimeException Si el JWK es inválido
	 */
	private function jwkToPem(array $jwk): string
	{
		if (!isset($jwk['kty']) || $jwk['kty'] !== 'RSA') {
			throw new RuntimeException('Solo se soportan claves RSA');
		}

		if (!isset($jwk['n']) || !isset($jwk['e'])) {
			throw new RuntimeException('JWK inválido: n o e faltantes');
		}

		// Decodificar n (modulus) y e (exponent)
		$n = $this->base64UrlDecode($jwk['n']);
		$e = $this->base64UrlDecode($jwk['e']);

		// Crear recurso de clave pública
		$rsa = openssl_pkey_new([
			'private_key_type' => OPENSSL_KEYTYPE_RSA,
			'private_key_bits' => strlen($n) * 8,
		]);

		if ($rsa === false) {
			throw new RuntimeException('No se pudo crear clave RSA');
		}

		// Construir la clave pública en formato PEM
		// Usar DER encoding para RSA public key
		$der = $this->rsaPublicKeyToDer($n, $e);

		$pem = "-----BEGIN PUBLIC KEY-----\n";
		$pem .= chunk_split(base64_encode($der), 64, "\n");
		$pem .= "-----END PUBLIC KEY-----\n";

		return $pem;
	}

	/**
	 * Convierte modulus y exponent a formato DER
	 *
	 * @param string $n Modulus
	 * @param string $e Exponent
	 * @return string DER encoding
	 */
	private function rsaPublicKeyToDer(string $n, string $e): string
	{
		// Construir SEQUENCE { n INTEGER, e INTEGER }
		$nDer = $this->integerToDer($n);
		$eDer = $this->integerToDer($e);

		$sequence = $nDer . $eDer;
		$sequenceLength = $this->encodeDerLength(strlen($sequence));

		$rsaSequence = "\x30" . $sequenceLength . $sequence;

		// Envolver en SEQUENCE con OID de RSA
		$oid = "\x30\x0d\x06\x09\x2a\x86\x48\x86\xf7\x0d\x01\x01\x01\x05\x00";
		$bitString = "\x03" . $this->encodeDerLength(strlen($rsaSequence) + 1) . "\x00" . $rsaSequence;

		$publicKey = $oid . $bitString;
		$publicKeyLength = $this->encodeDerLength(strlen($publicKey));

		return "\x30" . $publicKeyLength . $publicKey;
	}

	/**
	 * Convierte un integer a formato DER
	 */
	private function integerToDer(string $value): string
	{
		// Agregar 0x00 si el byte más significativo tiene el bit alto en 1
		if (ord($value[0]) > 0x7f) {
			$value = "\x00" . $value;
		}

		$length = $this->encodeDerLength(strlen($value));
		return "\x02" . $length . $value;
	}

	/**
	 * Codifica la longitud en formato DER
	 */
	private function encodeDerLength(int $length): string
	{
		if ($length <= 127) {
			return chr($length);
		}

		$encoded = '';
		$temp = $length;
		while ($temp > 0) {
			$encoded = chr($temp & 0xff) . $encoded;
			$temp >>= 8;
		}

		return chr(0x80 | strlen($encoded)) . $encoded;
	}

	/**
	 * Decodifica base64url
	 */
	private function base64UrlDecode(string $input): string
	{
		$remainder = strlen($input) % 4;
		if ($remainder) {
			$padlen = 4 - $remainder;
			$input .= str_repeat('=', $padlen);
		}

		return base64_decode(strtr($input, '-_', '+/'));
	}

	/**
	 * Limpia el cache de JWKS (útil para testing)
	 */
	public static function clearCache(): void
	{
		self::$jwksCache = null;
		self::$jwksCacheTime = null;
	}

	/**
	 * Extrae claims sin validar (solo para debugging/logging)
	 * NO usar para autenticación
	 *
	 * @param string $token JWT
	 * @return array|null Claims o null si es inválido
	 */
	public static function extractClaimsUnsafe(string $token): ?array
	{
		$parts = explode('.', $token);
		if (count($parts) !== 3) {
			return null;
		}

		try {
			$validator = new self();
			$payload = $validator->base64UrlDecode($parts[1]);
			return json_decode($payload, true);
		} catch (Exception $e) {
			return null;
		}
	}
}
