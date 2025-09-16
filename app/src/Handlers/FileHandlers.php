<?php

/**
 * RUND-API - Servicio de Gestión de Documentos
 * 
 * Gestiona peticiones relacionadas con archivos
 * @author ESAP Development Team / Oliver Castelblanco Martínez oliver.castelblanco@esap.edu.co
 * @version 2.0
 * @since PHP 8.3
 */

declare(strict_types=1);

namespace RUND\Handlers;

use RUND\Config\Constants as Config;
use RUND\Core\OpenKM as OpenKM;
use RUND\Core\Utils as Utils;
use RUND\Services\CategoriasService as CategoriasService;
use RUND\Services\DocumentService as DocumentService;
use RUND\Services\LBService as LBService;
use RUND\Services\ReportesService;

class FileHandlers
{

  /**
   * Genera un archivo de consulta (Excel o PDF) a partir de los datos proporcionados
   * @param array $postData Datos de la consulta, con las siguientes claves:
   * - 'cols': Array de nombres de columnas
   * - 'filas': Array de arrays, cada uno representando una fila de datos
   * - 'nomCol': Nombre de la categoría para las columnas
   * - 'nomFil': Nombre de la categoría para las filas
   * @param string $tipo Tipo de archivo a generar ('xlsx' o 'pdf')
   * @param string $nombrePlantilla Nombre del archivo de plantilla a usar (debe estar en OpenKM en la ruta de plantillas)
   * @return void Imprime directamente la respuesta (archivo) y establece las cabeceras HTTP adecuadas
   */
  public static function getConsultaFile(array $postData, string $tipo, string $nombrePlantilla = "plantilla_reporte.xlsx"): void
  {
    $nombreHoja = "ConsultaRUND";

    // Distribuye las variables que entraron como $postData;
    $dataCols = $postData["cols"];
    $numTotalCols = count($dataCols);
    $dataFilas = $postData["filas"];
    $numTotalFilas = count($dataFilas);
    $dataNomFil = $postData["nomFil"];
    $dataNomCol = $postData["nomCol"];

    $resp = ReportesService::generaReporte($nombrePlantilla, $nombreHoja, $numTotalCols, $dataNomFil, $dataNomCol, $dataCols, $dataFilas, $numTotalFilas);
    $writer = $resp[0];
    $tempPlantilla = $resp[1];

    switch ($tipo) {
      case "xlsx": // Si la salida es un Excel
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="reporte.xls"');
        header('Cache-Control: max-age=0');
        $writer->save('php://output'); // Se envía directamente al cliente como Blob
        if (file_exists($tempPlantilla)) unlink($tempPlantilla);
        exit();
        break;
      case "pdf": // Si la salida es un PDF
        // Limpiamos la carpeta tmp de archivos con los nombres que vamos a usar
        if (file_exists(Config::TEMP_DIR . "reporte.xlsx")) unlink(Config::TEMP_DIR . "reporte.xlsx");
        if (file_exists(Config::TEMP_DIR . "reporte.pdf")) unlink(Config::TEMP_DIR . "reporte.pdf");
        $writer->save(Config::TEMP_DIR . "reporte.xlsx");
        $resp = LBService::convierteExcelToPDF("reporte.xlsx");
        if ($resp["error"] == null) {
          $pdfFilePath = $resp["salida"];
          header('Content-Type: application/pdf');
          header('Content-Disposition: attachment; filename="' . basename($pdfFilePath) . '"');
          header('Content-Length: ' . filesize($pdfFilePath));
          readfile($pdfFilePath);
          if (file_exists($tempPlantilla)) unlink($tempPlantilla);
          exit();
        } else {
          print(json_encode($resp));
        }
        break;
    }
  }

  /**
   * Borra los archivos temporales generados para los reportes (reporte.xlsx y reporte.pdf)
   * @return array Un array con 'borrados' (archivos que se han borrado) y 'aBorrar' (archivos que se intentaron borrar)
   */
  public static function deleteReport(): array
  {
    $aBorrar = ["reporte.xlsx", "reporte.pdf"];
    $borrados = [];
    foreach ($aBorrar as $archivo) {
      if (file_exists(Config::TEMP_DIR . $archivo)) {
        unlink(Config::TEMP_DIR . $archivo);
        $borrados[] = $archivo;
      }
    }
    return ["borrados" => $borrados, "aBorrar" => $aBorrar];
  }

  /**
   * Carga un listado de administración de profesores o identifica el uuid de ese listado si ya está cargado
   * @param string $method El método HTTP usado (debe ser POST para cargar el archivo)
   * @param array $params Parámetros adicionales enviados en la petición
   *   - 'accion': 'cargar' para cargar el archivo o 'duplicado' para verificar si ya existe
   *   - 'propiedades': JSON con las propiedades del archivo (Nombre, Tipo, Origen, Formato, Size, etc.)
   * @param array $files Archivos enviados en la petición (debe incluir 'archivo' si la acción es 'cargar')
   * @return array Un array con la respuesta de la operación, que puede incluir errores o información sobre duplicados
   */
  public static function loadList(string $method, array $params, array $files): array
  {
    $salida = [];
    $accion = $params["accion"];
    $propiedades = json_decode(html_entity_decode($params["propiedades"], ENT_QUOTES | ENT_HTML5, 'UTF-8'), true);
    $nombreArchivo = Utils::extraeElemento($propiedades, "label", "Nombre")["valor"];
    $tipo = Utils::textoAnombreCarpeta(Utils::extraeElemento($propiedades, "label", "Tipo")["valor"]);
    $path = Config::TAX_LISTADOS . $tipo;
    $query = "search/find?name=" . urlencode($nombreArchivo) . "&path=" . urlencode($path);

    switch ($accion) {
      case "cargar":
        if ($method == 'POST' && isset($files['archivo'])) {
          $dupe = Utils::extraeElemento($propiedades, "label", "Duplicado")["valor"];
          $salida = OpenKM::cargaArchivo($files["archivo"], $propiedades, $path, $dupe);
          if (!$dupe) {
            $uuid = json_decode(OpenKM::consulta($query), true)["queryResult"]["node"]["uuid"];
            $tipo = Utils::textoAnombreCarpeta(Utils::extraeElemento($propiedades, "label", "Tipo")["valor"]);
            $origen = Utils::textoAnombreCarpeta(Utils::extraeElemento($propiedades, "label", "Origen")["valor"]);
            $formato = Utils::textoAnombreCarpeta(Utils::extraeElemento($propiedades, "label", "Formato")["valor"]);
            $categorias = [
              ["path" => Config::CTGR_LISTADOS . "TIPO/$tipo"],
              ["path" => Config::CTGR_LISTADOS . "ORIGEN/$origen"],
              ["path" => Config::CTGR_LISTADOS . "FORMATO/$formato"],
            ];
            $salida["creaCategorias"] = CategoriasService::creaCategorias($categorias);
            $postData = ["uuid" => $uuid, "categories" => $categorias];
            $salida["respCategorias"] = OpenKM::consulta("document/setProperties", "PUT", $postData);
          }
        } else {
          $salida["error"] = "Para la acción 'cargar' se requiere método POST y un archivo.";
        }
        break;
      case "duplicado":
        $respQuery = json_decode(OpenKM::consulta($query), true);
        $duplicado = ["nombre" => false, "ruta" => false, "size" => false, "creado" => false, "uuid" => ""];
        if ($respQuery && count($respQuery)) {
          $duplicado["nombre"] = true;
          $nodo = Utils::esArraySimple($respQuery["queryResult"]) ? $respQuery["queryResult"][0]["node"] : $respQuery["queryResult"]["node"];
          if ($nodo["path"] == "$path/$nombreArchivo") $duplicado["ruta"] = true;
          if ($nodo["actualVersion"]["size"] == Utils::extraeElemento($propiedades, "label", "Size")["valor"]) $duplicado["size"] = true;
          $duplicado["creado"] = $nodo["created"];
          $duplicado["uuid"] = $nodo["uuid"];
        }
        $salida["duplicado"] = $duplicado;
        break;
    }
    return $salida;
  }

  /**
   * Maneja la carga de archivos en OpenKM. Pueden ser:
   * - Firmas (PNG y JSON side-car)
   * - Documentos (Cédula, etc.)
   * @param array $params Parámetros adicionales enviados en la petición
   *   - 'accion': 'cargaFirma' para cargar una firma o 'cargaDocumento' para cargar un documento
   *   - 'propiedades': JSON con las propiedades del archivo (Nombre, Tipo, Origen, Formato, Size, etc.)
   * @param array $files Archivos enviados en la petición (debe incluir 'archivo')
   * @return array Un array con la respuesta de la operación, que puede incluir errores o información sobre duplicados
   */
  public static function postFile(array $params, array $files): array
  {
    $salida = [];
    $accion = $params["accion"];
    $nombreArchivo = $files["archivo"]["name"];
    $propiedades = json_decode(html_entity_decode($params["propiedades"], ENT_QUOTES | ENT_HTML5, 'UTF-8'), true);
    array_push($propiedades, ["label" => "Nombre", "valor" => $nombreArchivo]);

    switch ($accion) {
      case "cargaFirma":
        $path = Config::TAX_FIRMAS;
        $dupe = OpenKM::yaExiste($nombreArchivo, $path);
        $cargo = Utils::textoAnombreCarpeta(Utils::extraeElemento($propiedades, "label", "cargo")["valor"]);
        $categorias = [
          ["path" => Config::CTGR_FIRMAS . "CARGO/$cargo"],
          ["path" => Config::CTGR_FIRMAS . "TIPO/RUND_FIRMA"],
          ["path" => Config::CTGR_FIRMAS . "TIPO/RUND_FIRMA_SIDE-CAR"],
          ["path" => Config::CTGR_FIRMAS . "FORMATO/PNG"],
          ["path" => Config::CTGR_FIRMAS . "FORMATO/CSV"],
        ];
        $salida["creaCategorias"] = CategoriasService::creaCategorias($categorias);
        $salida["cargaPNG"] = OpenKM::cargaArchivo($files["archivo"], $propiedades, $path, $dupe);
        $queryPNG = "search/find?name=" . urlencode($nombreArchivo) . "&path=" . urlencode($path);
        $uuidPNG = json_decode(OpenKM::consulta($queryPNG), true)["queryResult"]["node"]["uuid"];
        $postDataPNG = ["uuid" => $uuidPNG, "categories" => [["path" => Config::CTGR_FIRMAS . "CARGO/$cargo"], ["path" => Config::CTGR_FIRMAS . "TIPO/RUND_FIRMA"], ["path" => Config::CTGR_FIRMAS . "FORMATO/PNG"]]];
        $salida["respCategoriasPNG"] = OpenKM::consulta("document/setProperties", "PUT", $postDataPNG);

        $nombreJSON = preg_replace('/\.png$/i', ".json", $nombreArchivo);
        $queryJSON = "search/find?name=" . urlencode($nombreJSON) . "&path=" . urlencode($path);
        $respQueryJSON = json_decode(OpenKM::consulta($queryJSON), true);
        $dupeJSON = $respQueryJSON && count($respQueryJSON);
        $uuidJSON = $dupeJSON ? $respQueryJSON["queryResult"]["node"]["uuid"] : null;
        $dataJSON = [
          ["label" => "cargo", "valor" => Utils::extraeElemento($propiedades, "label", "cargo")["valor"]],
          ["label" => "nombres", "valor" => Utils::extraeElemento($propiedades, "label", "nombres")["valor"]],
          ["label" => "apellidos", "valor" => Utils::extraeElemento($propiedades, "label", "apellidos")["valor"]],
          ["label" => "fecha", "valor" => Utils::extraeElemento($propiedades, "label", "fecha")["valor"]],
          ["label" => "firma", "valor" => $nombreArchivo],
        ];
        $salida["cargaJSON"] = DocumentService::cargaJSON($dataJSON, $nombreJSON, $path, $uuidJSON);
        $uuidJSON_actualizado = json_decode(OpenKM::consulta($queryJSON), true)["queryResult"]["node"]["uuid"];
        $postDataJSON = ["uuid" => $uuidJSON_actualizado, "categories" => [["path" => Config::CTGR_FIRMAS . "CARGO/$cargo"], ["path" => Config::CTGR_FIRMAS . "TIPO/RUND_FIRMA_SIDE-CAR"], ["path" => Config::CTGR_FIRMAS . "FORMATO/CSV"]]];
        $salida["respCategoriasJSON"] = OpenKM::consulta("document/setProperties", "PUT", $postDataJSON);
        break;
      case "cargaDocumento":
        /**
         * Carga un documento en OpenKM y lo categoriza según las propiedades
         * El objeto $propiedades (que es el nodo 'propiedades' del JSON que viene del frontend) debe contener:
         * - label: Nombre del documento (se infiere del nombre del archivo, no viene en el payload)
         * - taxonomia: Ruta de la carpeta del documento (vacía si es Cédula)
         * - tipo: Tipo de documento (Cédula, etc.)
         * - formato: Formato del documento (pdf, docx, etc.)
         * - origen: Origen del documento (OneDrive, etc.)
         * - categorias: Array de categorías a las que pertenece el documento, siempre y cuando sea Cédula, de resto no es obligatoria
         * - esCedula: Booleano que indica si el documento es una cédula o no
         * - cedula: El valor string de la cédula del profesor, que será la carpeta raíz donde se almacenará el documento
         */
        $cedula = Utils::extraeElemento($propiedades, "label", "cedula")["valor"]; // Cédula del profesor, que será la carpeta raíz donde se almacenará el documento
        if (!preg_match('/^\d{4,20}$/', $cedula)) { // Verifica que la cédula sea válida
          $salida["error"] = "La cédula debe tener entre 4 y 20 dígitos.";
          break;
        }
        $ruta = Utils::extraeElemento($propiedades, "label", "taxonomia")["valor"]; // Ruta de la carpeta del documento, como viene desde el frontend
        $ruta = strlen($ruta) > 2 ? "/" . Utils::textoAnombreCarpeta($ruta) : '';
        $path = Config::TAX_HOJAS . $cedula . $ruta; // Ruta de la carpeta del documento, es decir, la taxonomía
        $dupe = OpenKM::yaExiste($nombreArchivo, $path); // Verifica si ya existe un archivo con el mismo nombre en la ruta
        $esCedula = Utils::extraeElemento($propiedades, "label", "esCedula")["valor"]; // Verifica si el documento es una cédula
        $tipoDocumento = Utils::extraeElemento($propiedades, "label", "tipo")["valor"]; // Extrae el tipo de documento (cédula o la carpeta de la tax)
        $formatoDocumento = Utils::extraeElemento($propiedades, "label", "formato")["valor"]; // Extrae el formato del documento (pdf, docx, etc.)
        $origenDocumento = Utils::extraeElemento($propiedades, "label", "origen")["valor"]; // Extrae el origen del documento (OneDrive, etc.)
        $categorias = [];
        $rutasCategorias = [];
        if ($esCedula) { // Genera las categorías a las que pertenece el documento si es la cédula
          $cats = Utils::extraeElemento($propiedades, "label", "categorias")["valor"];
          foreach ($cats as $cat) $rutasCategorias[] = Config::ROOT_CTG_PROF . $cat; // Guarda las rutas de las categorías
        }
        $rutasCategorias[] = Config::CTGR_DOCS_HOJAS . "TIPO/" . Utils::textoAnombreCarpeta($tipoDocumento); // Añade la categoría del tipo de documento
        $rutasCategorias[] = Config::CTGR_DOCS_HOJAS . "FORMATO/" . Utils::textoAnombreCarpeta($formatoDocumento); // Añade la categoría del formato del documento
        $rutasCategorias[] = Config::CTGR_DOCS_HOJAS . "ORIGEN/" . Utils::textoAnombreCarpeta($origenDocumento); // Añade la categoría del origen del documento
        foreach ($rutasCategorias as $rutaCategoria) $categorias[] = ["path" => $rutaCategoria]; // Genera el array de categorías a las que pertenece el documento
        $salida = [];
        $salida["creaTaxonomia"] = OpenKM::creaCarpetas([$cedula . $ruta], Config::TAX_HOJAS); // Crea las carpetas de forma recursiva de la taxonomía en OpenKM, si no existe
        $salida["creaCategorias"] = OpenKM::creaCarpetas($rutasCategorias, Config::ROOT_CTG); // Crea las categorías en OpenKM si no existen
        $salida["carga"] = OpenKM::cargaArchivo($files["archivo"], $propiedades, $path, $dupe); // Carga el archivo en OpenKM
        $query = "search/find?name=" . urlencode($nombreArchivo) . "&path=" . urlencode($path); // Genera la query para buscar el UUID del archivo cargado
        $uuid = json_decode(OpenKM::consulta($query), true)["queryResult"]["node"]["uuid"]; // Extrae el UUID del archivo cargado
        $postData = ["uuid" => $uuid, "categories" => $categorias]; // Genera el array de datos para actualizar las propiedades del documento
        $salida["setProperties"] = OpenKM::consulta("document/setProperties", "PUT", $postData); // Actualiza las propiedades del documento en OpenKM
        break;
    }
    return $salida;
  }

  /**
   * Maneja la obtención de archivos desde OpenKM. Pueden ser:
   * - Archivos de datos (Excel, CSV, etc.) que se devuelven como base64 en un array
   * - Imágenes (PNG, JPG, etc.) que se envían directamente al navegador con las cabeceras adecuadas
   * @param string $tipo Tipo de archivo a obtener ('data' para archivos de datos o 'imagen' para imágenes)
   * @param string $nombre Nombre del archivo a obtener
   * @return array|null Un array con el contenido en base64 si es un archivo de datos, o null si es una imagen (se envía directamente al navegador)
   */
  public static function getFile(string $tipo, string $nombre): array | null
  {
    switch ($tipo) {
      case "data":
        return OpenKM::getDataFile($nombre);
        break;
      case "imagen":
        OpenKM::getImageFile($nombre);
        return null;
        break;
      default:
        return ["error" => "No existe el parámetro 'accion' con valor $tipo"];
    }
  }
}
