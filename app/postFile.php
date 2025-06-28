<?php
date_default_timezone_set('America/Bogota');
require_once("lib.inc.php");
header('Content-Type: application/json; charset=utf-8');
cors();
error_reporting(E_ALL ^ (E_NOTICE | E_WARNING | E_DEPRECATED));

const RUTA_FIRMAS = "/okm:root/RUND/DOCUMENTOS/FIRMAS";
const CTGR_FIRMAS = "/okm:categories/RUND/DOCUMENTOS/FIRMAS/";

$salida = [];
if (isset($_FILES['archivo']) && isset($_GET)) {
  $salida["getRequest"] = $_GET;
  $accion = $_GET["accion"];
  $nombreArchivo = $_FILES["archivo"]["name"];
  $propiedades = json_decode(html_entity_decode($_GET["propiedades"], ENT_QUOTES | ENT_HTML5, 'UTF-8'), true);
  array_push($propiedades, ["label" => "Nombre", "valor" => $nombreArchivo]); // Añade el nombre del FILE como "Nombre" en propiedades
  switch ($accion) {
    case "cargaFirma":
      $path = RUTA_FIRMAS;
      $dupe = yaExiste($nombreArchivo, $path); // Revisa si el archivo ya existe en la carpeta de firmas
      /**************************************************************** Crea las categorías que no existan */
      $cargo = textoAnombreCarpeta(extraeElemento($propiedades, "label", "cargo")["valor"]);
      $nombres = textoAnombreCarpeta(extraeElemento($propiedades, "label", "nombres")["valor"]);
      $apellidos = textoAnombreCarpeta(extraeElemento($propiedades, "label", "apellidos")["valor"]);
      $categorias = [
        ["path" => CTGR_FIRMAS . "CARGO/$cargo"],
        ["path" => CTGR_FIRMAS . "TIPO/RUND_FIRMA"],
        ["path" => CTGR_FIRMAS . "TIPO/RUND_FIRMA_SIDE-CAR"],
        ["path" => CTGR_FIRMAS . "FORMATO/PNG"],
        ["path" => CTGR_FIRMAS . "FORMATO/CSV"],
      ];
      $salida["creaCategorias"] = creaCategorias($categorias); // Revisa que cada categoría exista en OpenKM; si no es así, la crea.
      /******************************************************** Carga la firma PNG y le añade las categorías */
      $salida["cargaPNG"] = cargaArchivo($_FILES["archivo"], $propiedades, $path, $dupe); // Carga el PNG con la firma (reemplaza duplicados)
      // Añade categorías al PNG
      $query = "search/find?name=" . urlencode($nombreArchivo) . "&path=" . urlencode($path); // Cadena de búsqueda a partir del nombre y ruta
      $uuid = json_decode(consulta($query), true)["queryResult"]["node"]["uuid"];
      $postData = [
        "uuid" => $uuid,
        "categories" => [
          ["path" => CTGR_FIRMAS . "CARGO/$cargo"],
          ["path" => CTGR_FIRMAS . "TIPO/RUND_FIRMA"],
          ["path" => CTGR_FIRMAS . "FORMATO/PNG"],
        ],
      ];
      $salida["respCategoriasPNG"] = consulta("document/setProperties", "PUT", $postData);
      /*************************************************** Carga el JSON y le añade las categorías como side-car */
      $nombreJSON = preg_replace('/\.png$/i', ".json", extraeElemento($propiedades, "label", "Nombre")["valor"]); // Usa el mismo nombre de la firma, pero con .json
      $query = "search/find?name=" . urlencode($nombreJSON) . "&path=" . urlencode($path); // Cadena de búsqueda a partir del nombre y ruta     
      $respQuery = json_decode(consulta($query), true); // Revisa si el JSON ya existe
      $dupe = $respQuery && count($respQuery); // El JSON existe o no en esa carpeta de la Taxonomía
      $uuid = $dupe ? $respQuery["queryResult"]["node"]["uuid"] : null;
      $dataJSON = [
        ["label" => "cargo", "valor" => extraeElemento($propiedades, "label", "cargo")["valor"]],
        ["label" => "nombres", "valor" => extraeElemento($propiedades, "label", "nombres")["valor"]],
        ["label" => "apellidos", "valor" => extraeElemento($propiedades, "label", "apellidos")["valor"]],
        ["label" => "fecha", "valor" => extraeElemento($propiedades, "label", "fecha")["valor"]],
        ["label" => "firma", "valor" => $nombreArchivo],
      ];
      $salida["cargaJSON"] = cargaJSON($dataJSON, $nombreJSON, $path, $uuid); // Carga el JSON, sea nuevo o duplicado
      // Añade categorías al JSON
      $uuid = json_decode(consulta($query), true)["queryResult"]["node"]["uuid"];
      $postData = [
        "uuid" => $uuid,
        "categories" => [
          ["path" => CTGR_FIRMAS . "CARGO/$cargo"],
          ["path" => CTGR_FIRMAS . "TIPO/RUND_FIRMA_SIDE-CAR"],
          ["path" => CTGR_FIRMAS . "FORMATO/CSV"],
        ],
      ];
      $salida["respCategoriasJSON"] = consulta("document/setProperties", "PUT", $postData);
      break;
  }
}
print json_encode($salida, JSON_PRETTY_PRINT);
