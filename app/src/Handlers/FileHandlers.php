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

use RUND\Config\Config as Config;
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

          // FLUJO ESPECIAL: Si es ListadoGeneralDocente.csv y tipo LISTADO_DE_DOCENTES
          // Se ejecuta tanto para archivos nuevos como para actualizaciones
          if ($nombreArchivo === "ListadoGeneralDocente.csv" && $tipo === "LISTADO_DE_DOCENTES") {
            // Generar el índice JSON a partir del CSV
            $rutaCSV = $files["archivo"]["tmp_name"];
            $resultadoJson = self::generaIndiceJson($rutaCSV);

            if ($resultadoJson["error"]) {
              $salida["indiceJson"] = ["error" => $resultadoJson["error"]];
            } else {
              // Almacenar el JSON en OpenKM
              $resultadoAlmacenamiento = self::almacenaIndiceJson($resultadoJson["json"]);
              $salida["indiceJson"] = [
                "error" => $resultadoAlmacenamiento["error"],
                "registros" => $resultadoJson["registros"],
                "estructura" => $resultadoJson["estructura"],
                "merge" => $resultadoAlmacenamiento["merge"] ?? false,
                "registrosAnteriores" => $resultadoAlmacenamiento["registrosAnteriores"] ?? 0,
                "registrosNuevos" => $resultadoAlmacenamiento["registrosNuevos"] ?? 0,
                "registrosFinales" => $resultadoAlmacenamiento["registrosFinales"] ?? $resultadoJson["registros"],
                "archivoExiste" => $resultadoAlmacenamiento["archivoExiste"] ?? false,
                "uuid" => $resultadoAlmacenamiento["uuid"] ?? null,
                "carga" => $resultadoAlmacenamiento["carga"] ?? null
              ];
            }
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

        // === NUEVO: Encolar para extracción asíncrona ===
        $filePath = $path . "/" . $nombreArchivo; // Construir ruta completa del archivo
        $salida["extraction_queued"] = self::queueExtraction($uuid, $filePath, $tipoDocumento);

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

  /**
   * Obtiene el índice docente JSON desde OpenKM
   * @return array Array con el índice docente o error si no existe
   */
  public static function getIndiceDocente(): array
  {
    $salida = ["error" => null, "indice" => null, "uuid" => null];
    $nombreArchivo = "indice_docente.json";
    $path = Config::TAX_LISTADOS . "INDICE_DOCENTE";

    try {
      // Buscar el archivo en OpenKM
      $uuid = OpenKM::findArchivo($nombreArchivo, $path);

      if (!$uuid) {
        $salida["error"] = "El índice docente no existe. Debe cargar primero el archivo ListadoGeneralDocente.csv";
        return $salida;
      }

      // Obtener el contenido del archivo
      $contenido = OpenKM::getArchivo($uuid);
      $indice = json_decode($contenido, true);

      if (!$indice || !is_array($indice)) {
        $salida["error"] = "El índice docente existe pero no es un JSON válido";
        return $salida;
      }

      $salida["indice"] = $indice;
      $salida["uuid"] = $uuid;

    } catch (\Exception $e) {
      $salida["error"] = "Error al obtener índice docente: " . $e->getMessage();
    }

    return $salida;
  }

  /**
   * Mapea un encabezado de CSV a su clave correspondiente en labels.json
   * @param string $encabezado El encabezado del CSV a mapear
   * @param array $labels El array de labels.json obtenido de OpenKM
   * @return string La clave mapeada (normalizada si no se encuentra en labels)
   */
  private static function mapearEncabezadoALabel(string $encabezado, array $labels): string
  {
    // Normalizar el encabezado
    $normalizado = Utils::textoAnombreCarpeta($encabezado);

    // Buscar en labels.json si existe una clave con este valor
    $claveEncontrada = array_search($encabezado, $labels, true);

    if ($claveEncontrada !== false) {
      return $claveEncontrada;
    }

    // Si no se encuentra, retornar el encabezado normalizado
    return $normalizado;
  }

  /**
   * Genera un archivo JSON (indice_docente.json) a partir del contenido de un CSV
   * @param string $rutaCSV Ruta temporal del archivo CSV cargado
   * @return array Resultado de la operación con el JSON generado o error
   */
  private static function generaIndiceJson(string $rutaCSV): array
  {
    $salida = ["error" => null, "json" => null, "registros" => 0];

    try {
      // 1. Leer labels.json desde OpenKM
      $labels = OpenKM::getDataFile("labels");
      if (!$labels || isset($labels["error"])) {
        $salida["error"] = "No se pudo obtener labels.json de OpenKM";
        return $salida;
      }

      // 2. Leer el archivo CSV
      if (!file_exists($rutaCSV)) {
        $salida["error"] = "El archivo CSV no existe en la ruta temporal";
        return $salida;
      }

      $csvFile = fopen($rutaCSV, 'r');
      if (!$csvFile) {
        $salida["error"] = "No se pudo abrir el archivo CSV";
        return $salida;
      }

      // 3. Leer encabezados (primera línea)
      $encabezados = fgetcsv($csvFile);
      if (!$encabezados) {
        fclose($csvFile);
        $salida["error"] = "El archivo CSV no tiene encabezados";
        return $salida;
      }

      // 4. Mapear encabezados a claves de labels.json
      $encabezadosMapeados = [];
      foreach ($encabezados as $encabezado) {
        $encabezadosMapeados[] = self::mapearEncabezadoALabel($encabezado, $labels);
      }

      // 5. Encontrar el índice de la columna "Documento de identidad"
      $indiceCedula = array_search("DOCUMENTO_DE_IDENTIDAD", $encabezadosMapeados);
      if ($indiceCedula === false) {
        // Intentar con el nombre original sin mapear
        $indiceCedula = array_search("Documento de identidad", $encabezados);
        if ($indiceCedula === false) {
          fclose($csvFile);
          $salida["error"] = "No se encontró la columna 'Documento de identidad' en el CSV";
          return $salida;
        }
      }

      // 6. Construir el JSON como objeto plano con cédulas como claves
      $indiceDocente = [];
      $registros = 0;

      while (($fila = fgetcsv($csvFile)) !== false) {
        // Saltar filas vacías
        if (empty(array_filter($fila))) {
          continue;
        }

        // Obtener la cédula (clave del objeto)
        $cedula = trim($fila[$indiceCedula]);
        if (empty($cedula)) {
          continue; // Saltar si no hay cédula
        }

        // Construir el objeto del docente
        $docente = [];
        foreach ($encabezadosMapeados as $index => $clave) {
          // Saltar la columna "#" si existe
          if ($clave === "#" || $clave === "N") {
            continue;
          }

          $valor = isset($fila[$index]) ? trim($fila[$index]) : "";
          $docente[$clave] = $valor;
        }

        // Añadir al índice
        $indiceDocente[$cedula] = $docente;
        $registros++;
      }

      fclose($csvFile);

      // 7. Generar JSON
      $salida["json"] = json_encode($indiceDocente, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
      $salida["registros"] = $registros;
      $salida["estructura"] = "objeto plano con cédulas como claves";

    } catch (\Exception $e) {
      $salida["error"] = "Error al generar índice JSON: " . $e->getMessage();
    }

    return $salida;
  }

  /**
   * Almacena o actualiza el archivo indice_docente.json en OpenKM, haciendo merge si ya existe
   * @param string $jsonNuevo El contenido JSON nuevo a almacenar/mergear
   * @return array Resultado de la operación
   */
  private static function almacenaIndiceJson(string $jsonNuevo): array
  {
    $salida = ["error" => null];
    $nombreArchivo = "indice_docente.json";
    $path = Config::TAX_LISTADOS . "INDICE_DOCENTE";

    try {
      // 1. Crear la carpeta INDICE_DOCENTE si no existe
      $salida["creaCarpeta"] = OpenKM::creaCarpetas(["INDICE_DOCENTE"], Config::TAX_LISTADOS);

      // 2. Verificar si el archivo ya existe
      $uuid = OpenKM::findArchivo($nombreArchivo, $path);

      // 3. Decodificar el nuevo JSON
      $datosNuevos = json_decode($jsonNuevo, true);
      if (!$datosNuevos) {
        $salida["error"] = "El JSON nuevo no es válido";
        return $salida;
      }

      $jsonFinal = $jsonNuevo;

      // 4. Si existe, hacer merge con el contenido actual
      if ($uuid) {
        $salida["archivoExiste"] = true;
        $salida["uuid"] = $uuid;

        // Obtener el contenido actual
        $contenidoActual = OpenKM::getArchivo($uuid);
        $datosActuales = json_decode($contenidoActual, true);

        if ($datosActuales && is_array($datosActuales)) {
          // Hacer merge: los nuevos datos sobrescriben/añaden a los existentes
          foreach ($datosNuevos as $cedula => $datosDocente) {
            if (isset($datosActuales[$cedula])) {
              // El docente ya existe: fusionar campos
              $datosActuales[$cedula] = array_merge($datosActuales[$cedula], $datosDocente);
            } else {
              // Docente nuevo: añadir completo
              $datosActuales[$cedula] = $datosDocente;
            }
          }

          // Generar el JSON final mergeado
          $jsonFinal = json_encode($datosActuales, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
          $salida["merge"] = true;
          $salida["registrosAnteriores"] = count($datosActuales) - count($datosNuevos);
          $salida["registrosNuevos"] = count($datosNuevos);
          $salida["registrosFinales"] = count($datosActuales);
        }
      } else {
        $salida["archivoExiste"] = false;
        $salida["merge"] = false;
        $salida["registrosNuevos"] = count($datosNuevos);
      }

      // 5. Guardar el archivo temporal con el JSON final
      $tempFile = Config::TEMP_DIR . $nombreArchivo;
      file_put_contents($tempFile, $jsonFinal);

      // 6. Preparar el array de archivo para cargaArchivo()
      $archivo = [
        'error' => 0,
        'name' => $nombreArchivo,
        'tmp_name' => $tempFile,
        'type' => 'application/json',
        'size' => filesize($tempFile)
      ];

      $propiedades = [
        ['label' => 'Nombre', 'valor' => $nombreArchivo]
      ];

      // 7. Si el archivo existe, añadir UUID y comentario para nueva versión
      if ($uuid) {
        $propiedades[] = ['label' => 'Uuid', 'valor' => $uuid];
        $propiedades[] = ['label' => 'Comentario', 'valor' => 'Actualización automática de índice docente - ' . date('Y-m-d H:i:s')];
      }

      // 8. Cargar/actualizar el archivo en OpenKM
      $resultadoCarga = OpenKM::cargaArchivo(
        $archivo,
        $propiedades,
        $path,
        $uuid ? true : false  // Si existe UUID, es nueva versión
      );

      $salida["carga"] = $resultadoCarga;

      // 9. Limpiar archivo temporal
      if (file_exists($tempFile)) {
        unlink($tempFile);
      }

    } catch (\Exception $e) {
      $salida["error"] = "Error al almacenar índice JSON: " . $e->getMessage();
    }

    return $salida;
  }

  /**
   * Encola un documento para extracción asíncrona en rund-ai
   *
   * Este método NO bloquea la carga del documento. Si falla el encolado,
   * se registra el error pero se permite que la carga continúe.
   *
   * @param string $uuid UUID del documento en OpenKM
   * @param string $path Ruta completa del documento en OpenKM (ej: /okm:root/RUND/DOCUMENTOS/HOJAS_VIDA/12345/cedula.pdf)
   * @param string $tipoDocumento Tipo de documento (cédula, certificado_laboral, etc.)
   * @return array Respuesta con información del encolado
   */
  private static function queueExtraction(string $uuid, string $path, string $tipoDocumento): array
  {
    try {
      // 1. Obtener categorías actuales del documento
      $query = "document/getProperties?docId=" . urlencode($uuid);
      $propiedades = json_decode(OpenKM::consulta($query), true);
      $categoriasActuales = $propiedades["categories"] ?? [];

      // Convertir categorías actuales al formato correcto si es necesario
      $categoriasArray = [];
      if (!empty($categoriasActuales)) {
        // Si es un solo elemento, convertir a array
        if (isset($categoriasActuales["path"])) {
          $categoriasArray = [["path" => $categoriasActuales["path"]]];
        } else {
          // Es un array, mantener formato
          foreach ($categoriasActuales as $cat) {
            if (isset($cat["path"])) {
              $categoriasArray[] = ["path" => $cat["path"]];
            }
          }
        }
      }

      // 2. Añadir categoría "pendiente" para extracción
      $categoriaPendiente = Config::CTGR_EXTRACTION . "pendiente";

      // Crear categoría si no existe
      OpenKM::creaCarpetas([$categoriaPendiente], Config::ROOT_CTG);

      // Añadir nueva categoría a las existentes
      $categoriasArray[] = ["path" => $categoriaPendiente];

      // 3. Actualizar TODAS las categorías (existentes + nueva)
      $postData = [
        "uuid" => $uuid,
        "categories" => $categoriasArray
      ];
      OpenKM::consulta("document/setProperties", "PUT", $postData);

      // 2. Preparar payload para rund-ai
      $payload = [
        "documents" => [[
          "document_id" => $uuid,
          "file_path" => $path,
          "tipo_documento" => $tipoDocumento
        ]],
        "callback_url" => Config::API_BASE_URL . "/api/v2/ai/webhook/extraction-complete"
      ];

      // 3. Enviar a cola de rund-ai
      $ch = curl_init(Config::RUND_AI_URL . "/queue/add-batch");
      curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
      curl_setopt($ch, CURLOPT_POST, true);
      curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
      curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
      curl_setopt($ch, CURLOPT_TIMEOUT, 5); // Timeout corto, debe responder inmediatamente

      $response = curl_exec($ch);
      $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
      $curlError = curl_error($ch);
      curl_close($ch);

      if ($httpCode === 202) {
        $responseData = json_decode($response, true);
        return [
          "success" => true,
          "queued" => true,
          "queue_size" => $responseData["queue_size"] ?? 0,
          "message" => "Documento encolado para extracción"
        ];
      } else {
        error_log("ERROR encolando extracción - HTTP $httpCode: $response");
        return [
          "success" => false,
          "queued" => false,
          "error" => "rund-ai no disponible o error en cola",
          "http_code" => $httpCode,
          "details" => $curlError ?: $response
        ];
      }

    } catch (\Exception $e) {
      // Si falla el encolado, documentar pero NO fallar la carga
      error_log("EXCEPTION encolando extracción: " . $e->getMessage());
      return [
        "success" => false,
        "queued" => false,
        "error" => $e->getMessage()
      ];
    }
  }
}
