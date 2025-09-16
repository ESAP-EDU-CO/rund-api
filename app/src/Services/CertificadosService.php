<?php

/**
 * RUND-API - Servicio de Gestión de Documentos
 * 
 * Maneja operaciones específicas de gestión de reportes e informes
 * 
 * @author ESAP Development Team / Oliver Castelblanco Martínez oliver.castelblanco@esap.edu.co
 * @version 2.0
 * @since PHP 8.3
 */

declare(strict_types=1);

namespace RUND\Services;

use RUND\Config\Config as Config;
use RUND\Core\OpenKM as OpenKM;
use RUND\Core\Utils as Utils;

use PhpOffice\PhpWord\TemplateProcessor as TemplateProcessor;
use PhpOffice\PhpWord\Element\TextRun as TextRun;

class CertificadosService
{

  /**
   * 
   */
  public static function creaCertificado(string $nombrePlantilla, array $estructura, string $id, string $ruta = Config::TAX_PLANTILLAS_CERTIFICADOS): TemplateProcessor
  {
    $query = "search/find?name=" . urlencode($nombrePlantilla) . "&path=" . urlencode($ruta);
    $rawResp = OpenKM::consulta($query);
    $uuid = json_decode($rawResp, true)["queryResult"]["node"]["uuid"];
    $respuesta = OpenKM::getArchivo($uuid);
    $rutaPlantilla = Config::TEMP_DIR . $nombrePlantilla;
    file_put_contents($rutaPlantilla, $respuesta);
    $templateProcessor = new TemplateProcessor($rutaPlantilla);
    $numParrafo = 0;
    foreach ($estructura as $bloque) {
      if ($bloque["tipo"] == "parrafo") {
        $numParrafo++;
        $placeholder = $bloque["tipo"] . $numParrafo;
        $texto = html_entity_decode($bloque["value"]);
        if (self::esComplejo($texto)) {
          $templateProcessor = self::creaParrafoComplejo($templateProcessor, $texto, $placeholder);
        } else {
          $templateProcessor = self::creaParrafo($templateProcessor, $texto, $placeholder);
        }
      }
      if ($bloque["tipo"] == "tabla") {
        $valores = [];
        $encabezados = $bloque["value"]["encabezados"];
        $primerEnc = strtolower(explode("_", Utils::textoAnombreCarpeta($encabezados[0]))[0]);
        $filas = $bloque["value"]["filas"];
        foreach ($filas as $fila) {
          $linea = [];
          foreach ($encabezados as $numCol => $encabezado) {
            $placeholder = strtolower(explode("_", Utils::textoAnombreCarpeta($encabezado))[0]);
            $linea[] = [$placeholder => $fila[$numCol]];
          }
          $valores[] = $linea;
        }
        $templateProcessor = self::creaTabla($templateProcessor, $valores, $primerEnc);
      }
      if ($bloque["tipo"] == "firma") {
        $templateProcessor = self::creaFirma($templateProcessor, $bloque["value"], $ruta);
      }
    }
    // Crea la URL y el QR del validador
    $validacion = QRService::creaQR($id);
    $templateProcessor->setImageValue('valida_qr', $validacion["qr"]);
    $templateProcessor->setValue('valida_url', $validacion["url"]);
    unlink($validacion["qr"]); // Borra la imagen QR temporal
    return $templateProcessor;
  }
  public static function creaParrafoComplejo(TemplateProcessor $templateProcessor, string $texto, string $placeholder): TemplateProcessor
  {
    $templateProcessor->setComplexValue($placeholder, self::htmlToTextRun($texto));
    return $templateProcessor;
  }
  public static function creaParrafo(TemplateProcessor $templateProcessor, string $texto, string $placeholder): TemplateProcessor
  {
    $templateProcessor->setValue($placeholder, $texto);
    return $templateProcessor;
  }
  public static function creaTabla(TemplateProcessor $templateProcessor, array $valores, string $primerEnc): TemplateProcessor
  {
    $numFilas = count($valores);
    $templateProcessor->cloneRow($primerEnc, $numFilas);
    foreach ($valores as $numFila => $fila) {
      foreach ($fila as $celda) {
        $numPlace = $numFila + 1;
        $placeholder = key($celda);
        $valor = current($celda);
        $templateProcessor->setValue("$placeholder#$numPlace", $valor);
      }
    }
    //$templateProcessor->cloneRowAndSetValues($primerEnc, $valores);
    return $templateProcessor;
  }
  public static function creaFirma(TemplateProcessor $templateProcessor, array $valores, string $ruta): TemplateProcessor
  {
    $rutaImagen = FirmasService::creaFirmaTemp($valores["uuid"]);
    $templateProcessor->setImageValue('firma_imagen', $rutaImagen);
    $templateProcessor->setValue('firma_nombre', $valores["nombre"]);
    $templateProcessor->setValue('firma_cargo', $valores["cargo"]);
    unlink($rutaImagen); // Borra la firma temporal
    return $templateProcessor;
  }
  public static function esComplejo(string $texto): bool
  {
    $patron = '/<(em|strong|b|u|i|s|strike)>.*?<\/\1>/i';
    return preg_match($patron, $texto) === 1;
  }
  public static function htmlToTextRun(string $texto): TextRun
  {
    $textRun = new TextRun();
    $posicion = 0;
    $patron = '/<(strong|b|em|i|u|s|strike)>(.*?)<\/\1>/i';
    preg_match_all($patron, $texto, $coincidencias, PREG_OFFSET_CAPTURE);
    foreach ($coincidencias[0] as $key => $match) {
      $inicioEtiqueta = $match[1];
      $longitudEtiqueta = strlen($match[0]);
      $textoAntes = substr($texto, $posicion, $inicioEtiqueta - $posicion);
      if (!empty($textoAntes)) {
        $textRun->addText($textoAntes);
      }
      $etiqueta = strtolower($coincidencias[1][$key][0]);
      $contenidoEtiqueta = $coincidencias[2][$key][0];
      switch ($etiqueta) {
        case 'strong':
        case 'b':
          $textRun->addText($contenidoEtiqueta, ['bold' => true]);
          break;
        case 'em':
        case 'i':
          $textRun->addText($contenidoEtiqueta, ['italic' => true]);
          break;
        case 'u':
          $textRun->addText($contenidoEtiqueta, ['underline' => 'single']);
          break;
        case 's':
        case 'strike':
          $textRun->addText($contenidoEtiqueta, ['strikethrough' => true]);
          break;
      }
      $posicion = $inicioEtiqueta + $longitudEtiqueta;
    }
    $textoRestante = substr($texto, $posicion);
    if (!empty($textoRestante)) {
      $textRun->addText($textoRestante);
    }
    return $textRun;
  }
}
