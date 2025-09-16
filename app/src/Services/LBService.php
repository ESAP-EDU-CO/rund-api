<?php

/**
 * RUND-API - Servicio de Gestión de Documentos
 * 
 * Maneja operaciones específicas de LibreOffice
 * 
 * @author ESAP Development Team / Oliver Castelblanco Martínez oliver.castelblanco@esap.edu.co
 * @version 2.0
 * @since PHP 8.3
 */

declare(strict_types=1);

namespace RUND\Services;

use RUND\Config\Constants as Config;

class LBService
{

  /**
   * Convierte un archivo de Excel o Word a PDF usando LibreOffice en modo headless.
   * @param string $excel Nombre del archivo Excel a convertir (debe estar en el directorio temporal)
   * @param string $word Nombre del archivo Word a convertir (debe estar en el directorio temporal)
   * @param string $dir Directorio donde se encuentran los archivos y donde se guardará el PDF (por defecto, el directorio temporal)
   * @return array Un array con 'error' (null si no hay error) y 'salida' (ruta del PDF generado)
   */
  public static function convierteExcelToPDF(string $excel, string $dir = Config::TEMP_DIR): array
  {
    return self::convierteOfficeToPDF($excel, "calc_pdf_Export", $dir);
  }

  /**
   * Convierte un archivo de Word a PDF usando LibreOffice en modo headless.
   * @param string $word Nombre del archivo Word a convertir (debe estar en el directorio temporal)
   * @param string $dir Directorio donde se encuentran los archivos y donde se guardará el PDF (por defecto, el directorio temporal)
   * @return array Un array con 'error' (null si no hay error) y 'salida' (ruta del PDF generado)
   */
  public static function convierteWordToPDF(string $word = "certificado.docx", string $dir = Config::TEMP_DIR): array
  {
    return self::convierteOfficeToPDF($word, "writer_pdf_Export", $dir);
  }

  /**
   * Función interna que maneja la conversión de archivos de Office a PDF usando LibreOffice en modo headless.
   * @param string $file Nombre del archivo a convertir (debe estar en el directorio temporal)
   * @param string $handler El handler de conversión específico para LibreOffice (calc_pdf_Export para Excel, writer_pdf_Export para Word)
   * @param string $dir Directorio donde se encuentran los archivos y donde se guardará el PDF (por defecto, el directorio temporal)
   * @return array Un array con 'error' (null si no hay error) y 'salida' (ruta del PDF generado)
   */
  public static function convierteOfficeToPDF(string $file, string $handler, string $dir = Config::TEMP_DIR): array
  {
    $fileInfo = pathinfo($file);
    $nombrePDF = $dir . $fileInfo["filename"] . ".pdf";
    $comando = escapeshellcmd(
      $_ENV["LIBREOFFICE_EXECUTABLE"] . " --headless --convert-to pdf:$handler --outdir " .
        escapeshellarg($dir) . " " . escapeshellarg($dir . $file) . " 2>/dev/null"
    );
    $salida = exec($comando, $output, $rtn);
    if (false === $salida || 0 !== $rtn) $err = implode("\n", $output);
    if (file_exists($nombrePDF)) {
      return ["error" => null, "salida" => $nombrePDF];
    } else {
      return ["error" => $err ?? "ERROR: no se pudo convertir el archivo.", "salida" => $salida, "rtn" => $rtn];
    }
  }
}
