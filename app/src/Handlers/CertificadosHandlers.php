<?php

/**
 * RUND-API - Servicio de Gestión de Documentos
 * 
 * Gestiona peticiones relacionadas con certificados
 * 
 * @author ESAP Development Team / Oliver Castelblanco Martínez oliver.castelblanco@esap.edu.co
 * @version 2.0
 * @since PHP 8.3
 */

declare(strict_types=1);

namespace RUND\Handlers;

use RUND\Core\Utils as Utils;
use RUND\Config\Config as Config;
use RUND\Core\OpenKM as OpenKM;
use RUND\Services\DocumentService as DocumentService;
use RUND\Services\CertificadosService as CertificadosService;
use RUND\Services\LBService as LBService;

class CertificadosHandlers
{
  /**
   * Maneja la generación y descarga de certificados en formato DOCX o PDF
   * @param array $postData Datos enviados en la petición POST
   *   - 'data': JSON con la estructura para rellenar la plantilla
   *   - 'plantilla': Nombre de la plantilla (sin extensión)
   *   - 'tipo': 'docx' o 'pdf' para el formato de salida
   *   - 'id' (opcional): ID del certificado para evitar duplicados
   * @return void Envía el archivo generado al navegador para descarga
   */
  public static function getCertificado(array $postData): void
  {
    $estructura = json_decode($postData["data"], true);
    $plantilla = $postData["plantilla"];
    $tipo = $postData["tipo"];
    // Genera un ID aleatorio para la solicitud y permitir que se pueda recuperar después, a menos que se envíe en el payload
    $nomJson = "expedidos.json";
    $uuidJSON = OpenKM::findArchivo($nomJson, Config::TAX_CERTIFICADOS);
    $dataJson = $uuidJSON ? json_decode(OpenKM::getArchivo($uuidJSON), true) : [];
    // Verifica si es necesario crear un nuevo ID
    $crearNuevoId = true;
    if (isset($postData["id"])) $crearNuevoId = Utils::buscarPorId($dataJson, $postData["id"]) === null; // El id no existe en "expedidos.json"
    if ($crearNuevoId) {
      $nuevoID = Utils::generaID($dataJson);
      $postData["id"] = $nuevoID;
      // Añade el objeto al JSON 'expedidos.json' para su posterior recuperación
      $resp = DocumentService::addToJSON($nomJson, Config::TAX_CERTIFICADOS, $postData, "Añadido certificado ID:" . $nuevoID);
    }
    $nombrePlantilla = "$plantilla.docx";
    $phpTemplate = CertificadosService::creaCertificado($nombrePlantilla, $estructura, $postData["id"]);
    if ($tipo == "docx") {
      header("Content-Description: File Transfer");
      header('Content-Disposition: attachment; filename="' . $nombrePlantilla . '"');
      header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
      header('Content-Transfer-Encoding: binary');
      header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
      header('Expires: 0');
      $phpTemplate->saveAs("php://output");
      unlink(Config::TEMP_DIR . $nombrePlantilla);
    } elseif ($tipo == "pdf") {
      $nombreDOCX = "certificado_" . (new \DateTime())->format("Y-m-d-H-i-s") . ".docx";
      $phpTemplate->saveAs(Config::TEMP_DIR . $nombreDOCX);
      $resp = LBService::convierteWordToPDF($nombreDOCX);
      if (null == $resp["error"]) {
        $pdfFilePath = $resp["salida"];
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . basename($pdfFilePath) . '"');
        header('Content-Length: ' . filesize($pdfFilePath));
        readfile($pdfFilePath);
        unlink($pdfFilePath);
        unlink(Config::TEMP_DIR . $nombreDOCX);
        unlink(Config::TEMP_DIR . $nombrePlantilla);
        Utils::borrarMultiplesArchivos(Config::TEMP_DIR . "certificado_*"); // Intenta borrar todos los archivos de certificados en la carpeta tmp/
      }
    }
  }

  /**
   * Maneja la obtención de la información demográfica y del documento de un certificado por su ID
   * @param string $id El ID del certificado a buscar
   * @return array|null Un array con la información del certificado o null si no se encuentra
   */
  public static function getCertificadoInfo(string $id): array | null
  {
    $nomJson = "expedidos.json";
    $uuidJSON = OpenKM::findArchivo($nomJson, Config::TAX_CERTIFICADOS);
    $dataJson = $uuidJSON ? json_decode(OpenKM::getArchivo($uuidJSON), true) : [];
    return Utils::buscarPorId($dataJson, $id) ?? ["error" => "No existe el id buscado"];
  }
}
