<?php
ini_set('display_errors', 1);
error_reporting(E_ALL ^ (E_NOTICE | E_WARNING | E_DEPRECATED));
date_default_timezone_set('America/Bogota');
require_once __DIR__ . '/vendor/autoload.php';

require_once __DIR__ . "/src/Config/Constants.php";
require_once __DIR__ . "/src/Core/OpenKM.php";
require_once __DIR__ . "/src/Core/Utils.php";
require_once __DIR__ . "/src/Services/DocumentService.php";
require_once __DIR__ . "/src/Services/AIService.php";
require_once __DIR__ . "/src/Services/QRService.php";
require_once __DIR__ . "/src/Services/LBService.php";
require_once __DIR__ . "/src/Services/FirmasService.php";
require_once __DIR__ . "/src/Services/CategoriasService.php";
require_once __DIR__ . "/src/Services/ReportesService.php";
require_once __DIR__ . "/src/Services/CertificadosService.php";
require_once __DIR__ . "/src/Handlers/CertificadosHandlers.php";
require_once __DIR__ . "/src/Handlers/DataHandlers.php";
require_once __DIR__ . "/src/Handlers/CategoriasHandlers.php";
require_once __DIR__ . "/src/Handlers/FirmasHandlers.php";
require_once __DIR__ . "/src/Handlers/FileHandlers.php";
require_once __DIR__ . "/src/Handlers/AIHandlers.php";

use RUND\Handlers\CertificadosHandlers as CertificadosHandlers;
use RUND\Handlers\DataHandlers as DataHandlers;
use RUND\Handlers\CategoriasHandlers as CategoriasHandlers;
use RUND\Handlers\FileHandlers as FileHandlers;
use RUND\Handlers\FirmasHandlers as FirmasHandlers;
use RUND\Handlers\AIHandlers as AIHandlers;


// --- Handlers para el enrutador ---
function handleInfo(): array
{
  $respuesta = [
    "version" => "2.0",
    "nombre" => "RUND API",
    "descripcion" => "API para la gestión de documentos y certificados en RUND",
    "autor" => "Oliver Castelblanco Martínez",
    "email" => "oliver.castelblanco@esap.edu.co",
  ];
  return $respuesta;
}

// Handlers migrados

function handleGetCertificado(array $postData): void
{
  CertificadosHandlers::getCertificado($postData);
}
function handleGetCertificadoInfo(string $id): array | null
{
  return CertificadosHandlers::getCertificadoInfo($id);
}
function handleGetCsvData(array $params): array
{
  return DataHandlers::getCsvData($params);
}
function handleGetImagen(array $params): void
{
  DataHandlers::getImagen($params);
}
function handleGetInfoProfesor(string $cedula): array
{
  return DataHandlers::getInfoProfesor($cedula);
}
function handleGetCategorias(): array
{
  return CategoriasHandlers::getCategorias();
}
function handleGetCruce(string $x, string $y): array
{
  return CategoriasHandlers::getCruce($x, $y);
}
function handleGetFirmas(array $getParams): void
{
  FirmasHandlers::getFirmas($getParams);
}
function handleDeleteReport(): array
{
  return FileHandlers::deleteReport();
}
function handleLoadList(string $method, array $params, array $files): array
{
  return FileHandlers::loadList($method, $params, $files);
}
function handlePostFile(array $params, array $files): array
{
  return FileHandlers::postFile($params, $files);
}
function handleGetFile(string $tipo, string $nombre): array | null
{
  return FileHandlers::getFile($tipo, $nombre);
}
function handleGetConsultaFile(array $postData, string $tipo, string $nombrePlantilla = "plantilla_reporte.xlsx"): void
{
  FileHandlers::getConsultaFile($postData, $tipo, $nombrePlantilla);
}
function handleExtraeDatos(array $params, array $files): array
{
  return AIHandlers::extraeDatos($params, $files);
}
