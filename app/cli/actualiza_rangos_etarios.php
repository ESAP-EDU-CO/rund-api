#!/usr/bin/env php
<?php
/**
 * Job nocturno: recalcula y actualiza la categoría RANGO_ETARIO en OpenKM
 * para todos los docentes que tienen FECHA_NACIMIENTO en indice_docente.json.
 *
 * Uso: php /var/www/html/cli/actualiza_rangos_etarios.php
 * Cron: 0 2 * * *
 */

require_once __DIR__ . '/../vendor/autoload.php';

use RUND\Config\Config;
use RUND\Core\OpenKM;
use RUND\Handlers\FileHandlers;

$log = function (string $msg): void {
    echo date('Y-m-d H:i:s') . ' ' . $msg . PHP_EOL;
};

$log('Iniciando actualización de rangos etarios.');

// 1. Leer indice_docente.json
$resultado = FileHandlers::getIndiceDocente();
if ($resultado['error']) {
    $log('ERROR al leer índice: ' . $resultado['error']);
    exit(1);
}
$indice = $resultado['indice'];
$log('Profesores en índice: ' . count($indice));

// 2. Obtener las subcategorías de RANGO_ETARIO desde OpenKM
$rangoBase = Config::ROOT_CTG_PROF . 'PERFIL_DOCENTE/RANGO_ETARIO';
$respRangos = json_decode(OpenKM::consulta('folder/getChildren?fldPath=' . urlencode($rangoBase)), true);
$rangos = $respRangos['folder'] ?? [];
if (empty($rangos)) {
    $log('ERROR: no se encontraron categorías en ' . $rangoBase);
    exit(1);
}
$log('Rangos etarios disponibles: ' . count($rangos));

// Helper: determina si una edad cae en el rango indicado por el label de la categoría
$edadEnRango = function (int $edad, string $path) use ($rangoBase): bool {
    $label = basename($path);
    preg_match_all('/\d+/', $label, $m);
    $nums = array_map('intval', $m[0]);
    if (count($nums) === 1) return $edad >= $nums[0];
    if (count($nums) >= 2) return $edad >= $nums[0] && $edad <= $nums[1];
    return false;
};

$actualizados = 0;
$sinFecha    = 0;
$sinDocumento = 0;
$errores     = 0;

foreach ($indice as $cedula => $datos) {
    $fechaNac = $datos['FECHA_NACIMIENTO'] ?? null;
    if (empty($fechaNac)) { $sinFecha++; continue; }

    // 3. Calcular edad actual
    $nac = DateTime::createFromFormat('Y-m-d', $fechaNac);
    if (!$nac) {
        $log("AVISO fecha inválida para cédula $cedula: $fechaNac");
        $errores++;
        continue;
    }
    $edad = (new DateTime())->diff($nac)->y;

    // 4. Buscar el rango esperado
    $rangoEsperado = null;
    foreach ($rangos as $rango) {
        if ($edadEnRango($edad, $rango['path'])) {
            $rangoEsperado = $rango['path'];
            break;
        }
    }
    if (!$rangoEsperado) {
        $log("AVISO sin rango para edad $edad (cédula $cedula)");
        $errores++;
        continue;
    }

    // 5. Encontrar el UUID del documento cédula del profesor
    //    La cédula está en la raíz de TAX_HOJAS/{cedula}/ (sin subcarpeta)
    $folderPath = Config::TAX_HOJAS . $cedula;
    $respFolder = json_decode(OpenKM::consulta('folder/getProperties?fldPath=' . urlencode($folderPath)), true);
    $folderUuid = $respFolder['uuid'] ?? null;
    if (!$folderUuid) { $sinDocumento++; continue; }

    $respDocs = json_decode(OpenKM::consulta('folder/getDocuments?fldId=' . urlencode($folderUuid)), true);
    $docs = $respDocs['document'] ?? [];
    if (empty($docs)) { $sinDocumento++; continue; }
    $docUuid = $docs[0]['uuid'];

    // 6. Leer categorías actuales del documento
    $respProps = json_decode(OpenKM::consulta('document/getProperties?docId=' . urlencode($docUuid)), true);
    $catsActuales = $respProps['categories'] ?? [];

    // 7. Reemplazar RANGO_ETARIO manteniendo el resto
    $nuevasCats = array_values(array_filter(
        $catsActuales,
        fn($c) => strpos($c['path'], $rangoBase) === false
    ));
    $nuevasCats[] = ['path' => $rangoEsperado];

    // 8. Actualizar propiedades en OpenKM
    $postData = ['uuid' => $docUuid, 'categories' => $nuevasCats];
    OpenKM::consulta('document/setProperties', 'PUT', $postData);
    $actualizados++;
}

$log("Completado. Actualizados: $actualizados | Sin fecha: $sinFecha | Sin documento: $sinDocumento | Errores: $errores");
