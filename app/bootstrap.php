<?php
/**
 * RUND API - Bootstrap Principal
 *
 * Sistema de arranque moderno con autoloader PSR-4
 * Reemplaza completamente lib.inc.php
 *
 * @author ESAP Development Team / Oliver Castelblanco
 * @version 3.0
 * @since PHP 8.3
 */

declare(strict_types=1);

// Configurar entorno PHP
ini_set('display_errors', '1');
error_reporting(E_ALL ^ (E_NOTICE | E_WARNING | E_DEPRECATED));
date_default_timezone_set('America/Bogota');

// Cargar autoloader de Composer (compatible con Docker y desarrollo local)
$autoloadPaths = [
    __DIR__ . '/vendor/autoload.php',     // Desarrollo local
    __DIR__ . '/../vendor/autoload.php',  // Docker
];

$autoloaderFound = false;
foreach ($autoloadPaths as $path) {
    if (file_exists($path)) {
        require_once $path;
        $autoloaderFound = true;
        break;
    }
}

// Fallback para entornos sin Composer (como contenedor Docker actual)
if (!$autoloaderFound) {
    // Autoloader manual para compatibilidad temporal
    spl_autoload_register(function ($class) {
        if (strpos($class, 'RUND\\') !== 0) {
            return;
        }

        $classPath = str_replace('RUND\\', '', $class);
        $classPath = str_replace('\\', '/', $classPath);
        $file = __DIR__ . '/src/' . $classPath . '.php';

        if (file_exists($file)) {
            require_once $file;
        }
    });
}

// Importar clases principales
use RUND\Core\{Utils, OpenKM};
use RUND\Config\Config;
use RUND\Handlers\{
    FileHandlers,
    DataHandlers,
    CategoriasHandlers,
    CertificadosHandlers,
    FirmasHandlers,
    AIHandlers
};

// Configurar CORS
Utils::cors();

/**
 * Registro de handlers globales para compatibilidad con index.php existente
 * Estos permiten que el router actual siga funcionando sin cambios
 */

// --- Handlers de información ---
function handleInfo(): array
{
    return [
        "version" => "3.0",
        "nombre" => "RUND API",
        "descripcion" => "API moderna para la gestión de documentos y certificados en RUND",
        "autor" => "Oliver Castelblanco Martínez",
        "email" => "oliver.castelblanco@esap.edu.co",
        "autoloader" => "PSR-4 via Composer",
        "namespace" => "RUND"
    ];
}

// --- Handlers de certificados ---
function handleGetCertificado(array $postData): void
{
    CertificadosHandlers::getCertificado($postData);
}

function handleGetCertificadoInfo(string $id): ?array
{
    return CertificadosHandlers::getCertificadoInfo($id);
}

// --- Handlers de datos ---
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

// --- Handlers de categorías ---
function handleGetCategorias(): array
{
    return CategoriasHandlers::getCategorias();
}

function handleGetCruce(string $x, string $y): array
{
    return CategoriasHandlers::getCruce($x, $y);
}

// --- Handlers de firmas ---
function handleGetFirmas(array $getParams): void
{
    FirmasHandlers::getFirmas($getParams);
}

// --- Handlers de archivos ---
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

function handleGetFile(string $tipo, string $nombre): ?array
{
    return FileHandlers::getFile($tipo, $nombre);
}

function handleGetConsultaFile(array $postData, string $tipo, string $nombrePlantilla = "plantilla_reporte.xlsx"): void
{
    FileHandlers::getConsultaFile($postData, $tipo, $nombrePlantilla);
}

// --- Handlers de IA ---
function handleExtraeDatos(array $params, array $files): array
{
    return AIHandlers::extraeDatos($params, $files);
}

// Bootstrap completado
if (defined('RUND_DEBUG') && RUND_DEBUG) {
    error_log("RUND API Bootstrap v3.0 loaded successfully");
}