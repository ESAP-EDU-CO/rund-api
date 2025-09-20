<?php

/**
 * RUND API v2 - Bootstrap Principal
 *
 * Sistema de arranque moderno con autoloader PSR-4
 * API RESTful completa - Migración v1→v2 finalizada
 *
 * @author ESAP Development Team / Oliver Castelblanco
 * @version 2.0
 * @since PHP 8.3
 */

declare(strict_types=1);

// Configurar entorno PHP
ini_set('display_errors', '1');
error_reporting(E_ALL ^ (E_NOTICE | E_WARNING | E_DEPRECATED));
date_default_timezone_set('America/Bogota');

// Cargar dependencias de Composer (PhpOffice, etc.)
$autoloadPaths = [
	__DIR__ . '/vendor/autoload.php',     // Desarrollo local (con vendor en app/)
	__DIR__ . '/../vendor/autoload.php',  // Desarrollo local (con vendor en raíz)
	'/var/www/html/vendor/autoload.php',  // Docker (ruta absoluta)
];

foreach ($autoloadPaths as $path) {
	if (file_exists($path)) {
		require_once $path;
		break;
	}
}

// Autoloader manual para nuestras clases RUND (siempre necesario)
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

// Bootstrap completado - RUND API v2
