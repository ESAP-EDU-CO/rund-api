<?php

/**
 * RUND-API - Utilidades Generales
 * 
 * Funciones de utilidad común para todo el sistema.
 * 
 * @author ESAP Development Team / Oliver Castelblanco Martínez oliver.castelblanco@esap.edu.co
 * @version 2.0
 * @since PHP 8.3
 */

declare(strict_types=1);

namespace RUND\Core;

class Utils
{
  /**
   * Habilita CORS según la lista blanca de dominios permitidos.
   * @return void
   */
  public static function cors(): void // TODO: Limitar a dominios específicos con una lista blanca
  {
    if (isset($_SERVER['HTTP_ORIGIN'])) {
      header("Access-Control-Allow-Origin: {$_SERVER['HTTP_ORIGIN']}");
      header('Access-Control-Allow-Credentials: true');
      header('Access-Control-Max-Age: 86400');
    }
    if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
      if (isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_METHOD']))
        header("Access-Control-Allow-Methods: GET, POST, OPTIONS, PUT, DELETE");
      if (isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS']))
        header("Access-Control-Allow-Headers: {$_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS']}");
      exit(0);
    }
  }

  /**
   * Devuelve los elementos que coinciden entre dos arrays
   * @param array $array1 Primer array
   * @param array $array2 Segundo array
   * @return array Un array con los elementos que coinciden en ambos arrays
   */
  public static function getCoincidencias(array $array1, array $array2): array
  {
    $array1 = array_unique($array1);
    $array2 = array_unique($array2);
    $matches = array_intersect($array1, $array2);
    return $matches;
  }

  /**
   * Determina si un array es simple (no asociativo ni stdClass)
   * @param array $array El array a evaluar
   * @return bool TRUE si es un array simple, FALSE en caso contrario
   */
  public static function esArraySimple(array $array): bool
  {
    return is_array($array) && array_key_first($array) === 0;
  }

  /**
   * Transforma cualquier texto en NOMBRE_DE_CARPETA
   * @param string $texto El texto a transformar
   * @return string El texto transformado en mayúsculas, sin acentos y con espacios reemplazados por guiones bajos
   */
  public static function textoAnombreCarpeta(string $texto): string
  {
    $reemplazos = [
      'á' => 'a',
      'é' => 'e',
      'í' => 'i',
      'ó' => 'o',
      'ú' => 'u',
      'Á' => 'A',
      'É' => 'E',
      'Í' => 'I',
      'Ó' => 'O',
      'Ú' => 'U',
      'ñ' => 'n',
      'Ñ' => 'N',
      'ü' => 'u',
      'Ü' => 'U'
    ];
    $texto = strtr($texto, $reemplazos);
    $texto = strtoupper($texto);
    $texto = str_replace(' ', '_', $texto);
    return $texto;
  }

  /**
   * Extrae un elemento de un array de arrays asociativos, buscando por un campo específico
   * @param array $array El array de arrays asociativos
   * @param string $campo El campo por el cual buscar
   * @param string $busqueda El valor a buscar en el campo especificado
   * @return array|null El array asociativo que coincide o null si no se encuentra
   */
  public static function extraeElemento(array $array, string $campo, string $busqueda): array | null
  {
    foreach ($array as $elemento) {
      if (isset($elemento[$campo]) && $elemento[$campo] === $busqueda) {
        return $elemento;
      }
    }
    return null; // Devuelve null si no se encuentra el label
  }

  /**
   * Verifica si algún elemento de un array contiene un fragmento de texto específico
   * @param array $array El array a verificar
   * @param string $fragmento El fragmento de texto a buscar
   * @return bool TRUE si algún elemento contiene el fragmento, FALSE en caso contrario
   */
  public static function arrayMatch(array $array, string $fragmento): bool
  {
    if (count($array) < 0) return false;
    foreach ($array as $elemento) {
      if (str_contains($elemento, $fragmento)) return true;
    }
    return false;
  }

  /**
   * Convierte un array CSV (array de arrays) en un JSON estructurado por columnas
   * @param array $csvArray El array CSV a convertir
   * @return string Un string JSON con la estructura por columnas
   */
  public static function csvToJsonByColumns(array $csvArray): string
  {
    if (count($csvArray) < 2) {
      return json_encode([]);
    }
    $headers = $csvArray[0];
    $jsonResult = [];
    foreach ($headers as $header) $jsonResult[$header] = [];
    for ($i = 1; $i < count($csvArray); $i++) {
      $row = $csvArray[$i];
      foreach ($headers as $index => $header) {
        if (isset($row[$index])) {
          $jsonResult[$header][] = $row[$index];
        } else {
          $jsonResult[$header][] = null;
        }
      }
    }
    return json_encode($jsonResult, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
  }

  /**
   * Genera un ID aleatorio de 16 caracteres a partir de una función SHA-256
   * @param array $actualData Un array asociativo con objetos que contengan una key 'id', para evitar duplicados.
   * @return string Una cadena alfanumérica ASCII de 16 caracteres con un ID único.
   */
  public static function generaID(array $actualData = []): string
  {
    $idsExistentes = array_flip(array_column($actualData, 'id'));
    do {
      $bytes = random_bytes(8); // 8 bytes = 16 caracteres hex
      $id = bin2hex($bytes);
    } while (isset($idsExistentes[$id]));
    return $id;
  }

  /**
   * Elimina múltiples archivos del filesystem, usando patrones, tales como *, ?, {txt, log} y demás patrones de la función glob()
   * @param string $nombre El nombre, incluyendo patrones o comodines, de los archivos que se quieren borrar
   * @return array Un array asociativo con la llave "error". Si el valor de dicha llave es null, se ha ejecutado la acción sin problemas
   */
  public static function borrarMultiplesArchivos(string $nombre): array
  {
    // Obtener la lista de archivos que coinciden con el patrón
    $archivos = glob($nombre);
    // Verificar si glob tuvo un error (retorna false)
    if ($archivos === false) {
      return ['error' => 'Error al procesar el patrón.'];
    }
    // Si no hay archivos coincidentes, no hay acción
    if (empty($archivos)) {
      return ['error' => null];
    }
    // Intentar eliminar cada archivo
    foreach ($archivos as $archivo) {
      // Saltar directorios (unlink falla con directorios)
      if (is_dir($archivo)) {
        continue;
      }
      // Intentar borrar el archivo y capturar errores
      if (!@unlink($archivo)) {
        $error = error_get_last();
        return ['error' => $error['message'] ?? "Error desconocido al borrar: $archivo"];
      }
    }
    return ['error' => null];
  }

  /**
   * Busca en un array de arrays asociativos por el campo 'id'
   * @param array $array El array de arrays asociativos
   * @param string $id El valor 'id' que se busca
   * @return array El objeto que tiene un campo 'id' como el buscado o null si no existe
   */
  public static function buscarPorId(array $array, string $id): array | null
  {
    foreach ($array as $item) {
      if ($item['id'] === $id) {
        return $item;
      }
    }
    return null;
  }

  /**
   * Extrae el campo "label" de un objeto QUE YA LO TIENE
   * @param array $obj El objeto del cual extraer el label
   * @return string El valor del campo "label"
   */
  public static function getLabels(array $obj): string
  {
    return $obj["label"];
  }
}
