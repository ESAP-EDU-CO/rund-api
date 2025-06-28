<?php
require_once("lib.inc.php");
cors();
header('Content-Type: application/json; charset=utf-8');

const RUTA_LISTADOS = "/okm:root/RUND/DOCUMENTOS/LISTADOS/";
const CTGR_LISTADOS = "/okm:categories/RUND/DOCUMENTOS/LISTADOS/";

$salida = [];
if (isset($_GET) && isset($_GET["accion"]) && isset($_GET["propiedades"])) {
  $salida["getRequest"] = $_GET;
  $accion = $_GET["accion"];
  $propiedades = json_decode(html_entity_decode($_GET["propiedades"], ENT_QUOTES | ENT_HTML5, 'UTF-8'), true);
  $nombreArchivo = extraeElemento($propiedades, "label", "Nombre")["valor"];
  $tipo = textoAnombreCarpeta(extraeElemento($propiedades, "label", "Tipo")["valor"]);
  $path = RUTA_LISTADOS . $tipo;
  $query = "search/find?name=" . urlencode($nombreArchivo) . "&path=" . urlencode($path); // Cadena de búsqueda a partir del nombre y ruta
  switch ($accion) {
    case "cargar":
      if (isset($_FILES['archivo'])) {
        $dupe = extraeElemento($propiedades, "label", "Duplicado")["valor"];
        $salida = cargaArchivo($_FILES["archivo"], $propiedades, $path, $dupe);
        if (!$dupe) { // No es un documento duplicado, por lo tanto hay que asignarle categorías según el tipo de documento
          $uuid = json_decode(consulta($query), true)["queryResult"]["node"]["uuid"];
          $tipo = textoAnombreCarpeta(extraeElemento($propiedades, "label", "Tipo")["valor"]);
          $origen = textoAnombreCarpeta(extraeElemento($propiedades, "label", "Origen")["valor"]);
          $formato = textoAnombreCarpeta(extraeElemento($propiedades, "label", "Formato")["valor"]); //------------
          $categorias = [
            ["path" => CTGR_LISTADOS . "TIPO/$tipo"],
            ["path" => CTGR_LISTADOS . "ORIGEN/$origen"],
            ["path" => CTGR_LISTADOS . "FORMATO/$formato"],
          ];
          $salida["creaCategorias"] = creaCategorias($categorias); // Revisa que cada categoría exista en OpenKM; si no es así, la crea.
          $postData = [
            "uuid" => $uuid,
            "categories" => $categorias,
          ];
          $respCat = consulta("document/setProperties", "PUT", $postData);
          $salida["respCategorias"] = $respCat;
        }
      } else {
        $salida["error"] = "No se encuentra el archivo enviado";
      }
      break;
    case "duplicado":
      // Verifica si el documento está duplicado y determina si tiene el mismo nombre (true por defecto si hay coincidencia parcial)
      // la misma ruta en la Taxonomía y si tiene el mismo tamaño en bytes (en este caso, se marcará como "idéntico" en el front-end)
      // Retorna la fecha de creación del documento
      $respQuery = json_decode(consulta($query), true);
      $duplicado = [
        "nombre" => false,
        "ruta" => false,
        "size" => false,
        "creado" => false,
        "uuid" => ""
      ];
      if ($respQuery && count($respQuery)) {
        $duplicado["nombre"] = true;
        $nodo = esArraySimple($respQuery["queryResult"]) ? $respQuery["queryResult"][0]["node"] : $respQuery["queryResult"]["node"];
        if ($nodo["path"] == "$path/$nombreArchivo") $duplicado["ruta"] = true;
        if ($nodo["actualVersion"]["size"] == extraeElemento($propiedades, "label", "Size")["valor"]) $duplicado["size"] = true;
        $duplicado["creado"] = $nodo["created"];
        $duplicado["uuid"] = $nodo["uuid"];
      }
      $salida["duplicado"] = $duplicado;
      $salida["queryResult"] = $respQuery["queryResult"][0];
      $salida["size"] = $nodo;
      break;
  }
} else {
  $salida["error"] = "No se envió 'propiedades' como GET";
}
print json_encode($salida, JSON_PRETTY_PRINT);
