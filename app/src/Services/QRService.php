<?php

/**
 * RUND API - Servicio de Códigos QR
 * 
 * Maneja la generación de códigos QR para validación de certificados
 * 
 * @author ESAP Development Team / Oliver Castelblanco Martínez oliver.castelblanco@esap.edu.co
 * @version 2.0
 * @since PHP 8.3
 */

declare(strict_types=1);

namespace RUND\Services;

use RUND\Config\Config as Config;

// No usar use const, usar la clase Config
use Endroid\QrCode\Color\Color;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\RoundBlockSizeMode;
use Endroid\QrCode\Writer\PngWriter;
use InvalidArgumentException;
use RuntimeException;

class QRService
{
  /**
   * Genera un código QR para validación de certificados
   * 
   * @param string $id Cadena alfanumérica de 16 caracteres
   * @return array Array con ruta del archivo y URL de validación
   * @throws InvalidArgumentException Si el ID no tiene el formato correcto
   * @throws RuntimeException Si hay problemas al generar o guardar el QR
   */
  public static function creaQR(string $id): array
  {
    // Validar ID
    if (!preg_match('/^[a-zA-Z0-9]{16}$/', $id)) {
      throw new InvalidArgumentException('El ID debe ser una cadena alfanumérica de exactamente 16 caracteres');
    }

    // Construir URL de validación
    $url = self::buildValidationUrl($id);

    // Generar código QR
    $qrCode = self::createQRCode($url);

    // Escribir archivo
    $writer = new PngWriter();
    $result = $writer->write($qrCode);

    $filepath = Config::TEMP_DIR . "qr_" . $id . ".png";

    if (file_put_contents($filepath, $result->getString()) === false) {
      throw new RuntimeException("No se pudo guardar el código QR en: $filepath");
    }

    return [
      "qr" => $filepath,
      "url" => $url,
      "id" => $id
    ];
  }

  /**
   * Construye la URL de validación del certificado
   */
  private static function buildValidationUrl(string $id): string
  {
    // Detectar protocolo y host
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'localhost';

    // URL dinámica basada en el entorno actual
    $dynamicUrl = $protocol . '://' . $host . "/validacion?certificado=" . $id;

    // URL fija para desarrollo (puedes cambiar esto según el entorno)
    $fixedUrl = "http://localhost:4000/validacion?certificado=" . $id;

    // Usar URL fija por ahora (como en el código original)
    return $fixedUrl;
  }

  /**
   * Crea el objeto QRCode con configuración estándar
   */
  private static function createQRCode(string $data): QrCode
  {
    return new QrCode(
      data: $data,
      encoding: new Encoding('UTF-8'),
      errorCorrectionLevel: ErrorCorrectionLevel::Low,
      size: 300,
      margin: 10,
      roundBlockSizeMode: RoundBlockSizeMode::Margin,
      foregroundColor: new Color(0, 0, 0),
      backgroundColor: new Color(255, 255, 255)
    );
  }

  /**
   * Genera QR con logo personalizado (funcionalidad futura)
   */
  public static function creaQRConLogo(string $id, string $logoPath): array
  {
    // Validar ID
    if (!preg_match('/^[a-zA-Z0-9]{16}$/', $id)) {
      throw new InvalidArgumentException('El ID debe ser una cadena alfanumérica de exactamente 16 caracteres');
    }

    if (!file_exists($logoPath)) {
      throw new InvalidArgumentException("El archivo de logo no existe: $logoPath");
    }

    $url = self::buildValidationUrl($id);
    $qrCode = self::createQRCode($url);

    // Aquí se podría agregar el logo cuando sea necesario
    /*
        $logo = new Logo(
            path: $logoPath,
            resizeToWidth: 50,
            punchoutBackground: true
        );
        */

    $writer = new PngWriter();
    $result = $writer->write($qrCode); // Agregar $logo cuando esté implementado

    $filepath = __DIR__ . "/../../tmp/" . "qr_" . $id . "_logo.png";
    file_put_contents($filepath, $result->getString());

    return [
      "qr" => $filepath,
      "url" => $url,
      "id" => $id,
      "logo" => $logoPath
    ];
  }

  /**
   * Valida si un código QR existe y es válido
   */
  public static function validarQR(string $id): bool
  {
    if (!preg_match('/^[a-zA-Z0-9]{16}$/', $id)) {
      return false;
    }

    $filepath = __DIR__ . "/../../tmp/" . "qr_" . $id . ".png";
    return file_exists($filepath) && filesize($filepath) > 0;
  }

  /**
   * Limpia códigos QR temporales antiguos
   */
  public static function limpiarQRsAntiguos(int $dias = 7): int
  {
    $pattern = __DIR__ . "/../../tmp/" . "qr_*.png";
    $files = glob($pattern);
    $eliminados = 0;

    $tiempoLimite = time() - ($dias * 24 * 60 * 60);

    foreach ($files as $file) {
      if (filemtime($file) < $tiempoLimite) {
        if (unlink($file)) {
          $eliminados++;
        }
      }
    }

    return $eliminados;
  }
}
