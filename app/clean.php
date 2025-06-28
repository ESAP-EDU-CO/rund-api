<?php
require_once("lib.inc.php");
cors();
$aBorrar = ["reporte.xlsx", "reporte.pdf"];
$borrados = [];
foreach ($aBorrar as $archivo) {
  if (file_exists($archivo)) {
    // Pequeña salvaguarda para evitar la limpieza en local
    if ($_SERVER["SERVER_NAME"] != 'localhost' && $_SERVER["[SERVER_PORT]"] != '80') unlink($archivo);
    $borrados[] = $archivo;
  }
}
print json_encode(["borrados" => $borrados, "aBorrar" => $aBorrar]);
