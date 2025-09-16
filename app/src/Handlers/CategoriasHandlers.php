<?php

/**
 * RUND-API - Servicio de Gestión de Documentos
 * 
 * Gestiona peticiones relacionadas con categorías
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

class CategoriasHandlers
{

  /**
   * Devuelve un árbol de categorias (no Taxonomía) desde la categoría principal
   * @return array Un array representando el árbol de categorías o un array con 'error
   */
  public static function getCategorias(): array // Devuelve un árbol de categorias (no Taxonomía) desde la categoría principal
  {
    $respuesta = [];
    $respuesta["uuidCat_URL"] = $_ENV["CORE_API_URL"] . Config::REST . "repository/getCategoriesFolder";
    $uuidCat = json_decode(OpenKM::consulta("repository/getCategoriesFolder"), true)["uuid"];
    $respuesta["consulta_total"] = json_decode(OpenKM::consulta("repository/getCategoriesFolder"), true);
    $respuesta["uuidCat"] = $uuidCat;
    $respuesta["tieneHijos_URL"] = $_ENV["CORE_API_URL"] . Config::REST . "folder/getChildren?fldId=$uuidCat";
    $tieneHijos = json_decode(OpenKM::consulta("folder/getProperties?fldId=$uuidCat"), true)["hasChildren"];
    if ($tieneHijos) {
      $uuidRUND = json_decode(OpenKM::consulta("folder/getChildren?fldId=$uuidCat"), true)["folder"]["uuid"];
      $carpetas = CategoriasService::getArbolCarpetas($uuidRUND);
      return $carpetas;
    } else {
      $respuesta["error"] = "La categoría principal no tiene nodos hijos.";
      return $respuesta;
    }
  }

  /**
   * Cruza categorías y devuelve una matriz de dos dimensiones
   * @param string $x UUID de la categoría para las columnas
   * @param string $y UUID de la categoría para las filas
   * @return array Un array con 'nomCol' (nombre de la categoría X), 'nomFil' (nombre de la categoría Y), 'cols' (array de etiquetas de subcategorías X) y 'filas' (array de arrays con 'label' y 'data') o un array con 'error'
   */
  public static function getCruce(string $x, string $y): array // Cruza categorías y devuelve una matriz de dos dimensiones
  {
    if (!isset($_GET['x']) || !isset($_GET['y'])) {
      return ["error" => "Faltan parámetros para getCruce"];
    }
    $subCatX = OpenKM::getUUIDHijos($x);
    $subCatY = OpenKM::getUUIDHijos($y);
    $filas = [];
    $allDocsX = [];
    foreach ($subCatX as $catX) $allDocsX[] = CategoriasService::getDocsFromCat($catX["uuid"]);
    foreach ($subCatY as $catY) {
      $docsY = CategoriasService::getDocsFromCat($catY["uuid"]);
      $intData = [];
      foreach ($allDocsX as $docsX) {
        $intData[] = count(Utils::getCoincidencias($docsX, $docsY));
      }
      $fila = ["label" => $catY["label"], "data" => $intData];
      $filas[] = $fila;
    }
    $data = [
      "nomCol" => OpenKM::getPathGeneraLabel($x),
      "nomFil" => OpenKM::getPathGeneraLabel($y),
      "cols" => array_map("getLabels", $subCatX),
      "filas" => $filas
    ];
    return $data;
  }
}
