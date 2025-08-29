<?php
require_once("lib.inc.php");
cors(); // Maneja las cabeceras CORS

// --- 1. Definir la respuesta por defecto y las cabeceras ---
$respuesta = null;

// --- 2. Analizar la solicitud ---
$method = $_SERVER['REQUEST_METHOD'];
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$path = trim(str_replace('/index.php', '', $uri), '/'); // Limpiamos la ruta
$endpoint = explode('/', $path)[0]; // La primera parte de la ruta es nuestro endpoint

// --- 3. Leer el cuerpo de la solicitud (para POST, PUT) ---
$post_data = isset($_POST) ? $_POST : json_decode(file_get_contents("php://input"), true);

// --- 4. Enrutador (Router) ---
switch ($endpoint) {
  case 'getCategorias':
    if ($method == 'GET') {
      $respuesta = handleGetCategorias();
    }
    break;

  case 'getCruce':
    if ($method == 'GET') {
      $respuesta = handleGetCruce($_GET['x'], $_GET['y']);
    }
    break;

  case 'getCsvData':
    if ($method == 'GET') {
      $respuesta = handleGetCsvData($_GET);
    }
    break;

  case 'getCertificado':
    if ($method == 'POST' && $post_data) {
      // Esta función manejará los headers y la salida del archivo, por lo que no necesita devolver nada.
      handleGetCertificado($post_data);
      exit(); // La función manejadora se encarga de todo, salimos del script.
    } else {
      http_response_code(400);
      $respuesta = json_encode(["error" => "Payload incompleto o método incorrecto.", "postData" => $post_data, "POST" => $_POST]);
    }
    break;

  case 'getFirmas':
    if ($method == 'GET') {
      // Esta función puede devolver JSON o un archivo binario
      handleGetFirmas($_GET);
      exit(); // La función manejadora se encarga de todo.
    }
    break;

  case 'deleteFile':
    if ($method == 'DELETE' && isset($_GET['uuid'])) {
      $respuesta = json_encode(["error" => null, "salida" => borraArchivo($_GET["uuid"])]);
    } else {
      http_response_code(400);
      $respuesta = json_encode(["error" => "Se requiere método DELETE y parámetro 'uuid'"]);
    }
    break;

  case 'getConsultaFile':
    if ($method == 'POST' && $post_data) {
      // Esta función genera un archivo y maneja sus propios headers y salida.
      handleGetConsultaFile(json_decode($post_data['data'], true), $post_data['tipo']);
      exit();
    }
    break;

  case 'delReporte':
    if ($method == 'GET') {
      // Esta función limpia archivos temporales y devuelve un JSON.
      $respuesta = handleDeleteReport();
    }
    break;

  case 'loadList':
    if ($method == 'GET' && isset($_GET['accion']) && isset($_GET['propiedades'])) {
      $respuesta = handleLoadList($method, $_GET, $_FILES);
    } elseif ($method == 'POST' && isset($post_data['accion']) && isset($post_data['propiedades'])) {
      $respuesta = handleLoadList($method, $post_data, $_FILES);
    } else {
      http_response_code(400);
      $respuesta = ["error" => "Faltan los parámetros 'accion' y/o 'propiedades'"];
    }
    break;

  case 'postFile':
    if ($method == 'POST' && isset($_FILES['archivo']) && isset($post_data['accion'])) {
      $respuesta = handlePostFile($post_data, $_FILES);
    }
    break;
  case 'extraeDatos':
    if ($method == 'POST' && isset($_FILES['documento']) && isset($post_data)) {
      $respuesta = handleExtraeDatos($post_data, $_FILES);
    }
    break;
  case 'getFile':
    if ($method == "GET" && isset($_GET['tipo']) && isset($_GET['nombre'])) {
      $respuesta = handleGetFile($_GET["tipo"], $_GET["nombre"]);
      if ($respuesta === null) exit();
    } else {
      http_response_code(400);
      $respuesta = ["error" => "Falta el parámetro 'tipo' y/o 'nombre'"];
    }
    break;
  case 'getCertificadoInfo':
    if ($method == "GET" && isset($_GET["id"])) {
      $respuesta = handleGetCertificadoInfo($_GET["id"]);
    } else {
      http_response_code(400);
      $respuesta = ["error" => "Falta el parámetro 'id'"];
    }
    break;
  case 'imagen':
    if ($method == "GET" && isset($_GET['nombre']) && isset($_GET['ruta'])) {
      // Esta función genera un archivo y maneja sus propios headers y salida.
      handleGetImagen($_GET);
      exit();
    }
    break;
  case 'info':
    if ($method == "GET") {
      $respuesta = handleInfo();
    }
    break;
}

// --- 5. Enviar la respuesta ---
if ($respuesta !== null) {
  header('Content-Type: application/json; charset=utf-8');
  print json_encode($respuesta, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
} else {
  http_response_code(404);
  header('Content-Type: application/json; charset=utf-8');
  print json_encode(["error" => "Endpoint no encontrado"], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
}
