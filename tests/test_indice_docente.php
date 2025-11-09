<?php

/**
 * Script de prueba para la funcionalidad de generación de indice_docente.json
 *
 * Este script prueba:
 * 1. La lectura y mapeo de encabezados del CSV
 * 2. La generación del JSON con estructura plana (cédula como clave)
 * 3. El almacenamiento en el sistema de archivos local (simulando OpenKM)
 *
 * @author ESAP Development Team
 */

declare(strict_types=1);

// Simular estructura de archivo cargado
$csvPath = __DIR__ . "/../../pruebas/ListadoGeneralDocente.csv";

if (!file_exists($csvPath)) {
    die("ERROR: No se encuentra el archivo CSV en: $csvPath\n");
}

echo "=== TEST: Generación de índice_docente.json ===\n\n";
echo "1. Leyendo archivo CSV...\n";
echo "   Ruta: $csvPath\n\n";

// Simular labels.json
$labels = [
    "DOCUMENTO_DE_IDENTIDAD" => "Documento de identidad",
    "VINCULACION" => "Vinculación",
    "NOMBRE_Y_APELLIDO" => "Nombre completo",
    "TERRITORIAL" => "Territorial",
    "CATEGORIA" => "Categoría",
    "NUCLEO_TEMATICO" => "Núcleo Temático",
    "NIVEL_DE_FORMACION" => "Nivel de Formación",
    "PERFIL_ACADEMICO" => "Perfil académico"
];

// Función de normalización (copiada de Utils::textoAnombreCarpeta)
function normalizar(string $texto): string
{
    $reemplazos = [
        'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u',
        'Á' => 'A', 'É' => 'E', 'Í' => 'I', 'Ó' => 'O', 'Ú' => 'U',
        'ñ' => 'n', 'Ñ' => 'N', 'ü' => 'u', 'Ü' => 'U'
    ];
    $texto = strtr($texto, $reemplazos);
    $texto = strtoupper($texto);
    $texto = str_replace(' ', '_', $texto);
    return $texto;
}

// Función de mapeo
function mapearEncabezado(string $encabezado, array $labels): string
{
    $normalizado = normalizar($encabezado);
    $claveEncontrada = array_search($encabezado, $labels, true);

    if ($claveEncontrada !== false) {
        return $claveEncontrada;
    }

    return $normalizado;
}

// Leer CSV
$csvFile = fopen($csvPath, 'r');
if (!$csvFile) {
    die("ERROR: No se pudo abrir el archivo CSV\n");
}

// Leer encabezados
$encabezados = fgetcsv($csvFile);
if (!$encabezados) {
    die("ERROR: El archivo CSV no tiene encabezados\n");
}

echo "2. Encabezados encontrados en el CSV:\n";
foreach ($encabezados as $i => $enc) {
    echo "   [$i] $enc\n";
}
echo "\n";

// Mapear encabezados
$encabezadosMapeados = [];
foreach ($encabezados as $encabezado) {
    $encabezadosMapeados[] = mapearEncabezado($encabezado, $labels);
}

echo "3. Encabezados mapeados:\n";
foreach ($encabezadosMapeados as $i => $mapeado) {
    echo "   [$i] {$encabezados[$i]} => $mapeado\n";
}
echo "\n";

// Encontrar índice de cédula
$indiceCedula = array_search("DOCUMENTO_DE_IDENTIDAD", $encabezadosMapeados);
if ($indiceCedula === false) {
    $indiceCedula = array_search("Documento de identidad", $encabezados);
}

if ($indiceCedula === false) {
    die("ERROR: No se encontró la columna 'Documento de identidad'\n");
}

echo "4. Columna de cédula encontrada en índice: $indiceCedula\n\n";

// Construir JSON
$indiceDocente = [];
$registros = 0;

echo "5. Procesando registros...\n";

while (($fila = fgetcsv($csvFile)) !== false) {
    if (empty(array_filter($fila))) {
        continue;
    }

    $cedula = trim($fila[$indiceCedula]);
    if (empty($cedula)) {
        continue;
    }

    $docente = [];
    foreach ($encabezadosMapeados as $index => $clave) {
        if ($clave === "#" || $clave === "N") {
            continue;
        }

        $valor = isset($fila[$index]) ? trim($fila[$index]) : "";
        $docente[$clave] = $valor;
    }

    $indiceDocente[$cedula] = $docente;
    $registros++;

    // Mostrar primeros 3 registros
    if ($registros <= 3) {
        echo "   Registro $registros - Cédula: $cedula - Nombre: {$docente['NOMBRE_Y_APELLIDO']}\n";
    }
}

fclose($csvFile);

echo "   ...\n";
echo "   Total de registros procesados: $registros\n\n";

// Generar JSON
$json = json_encode($indiceDocente, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

echo "6. JSON generado:\n";
echo "   Tamaño: " . strlen($json) . " bytes\n";
echo "   Estructura: objeto plano con cédulas como claves\n";
echo "   Registros: $registros\n\n";

// Guardar en archivo temporal
$outputPath = __DIR__ . "/../tmp/indice_docente_test.json";
$outputDir = dirname($outputPath);

if (!is_dir($outputDir)) {
    mkdir($outputDir, 0777, true);
}

file_put_contents($outputPath, $json);

echo "7. Archivo guardado en: $outputPath\n\n";

// Mostrar muestra del JSON
echo "8. Muestra del JSON (primeros 2 registros):\n";
$muestra = array_slice($indiceDocente, 0, 2, true);
echo json_encode($muestra, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";

// Prueba de acceso rápido por cédula
echo "9. Prueba de acceso rápido:\n";
$cedulaPrueba = array_key_first($indiceDocente);
echo "   Buscando cédula: $cedulaPrueba\n";
if (isset($indiceDocente[$cedulaPrueba])) {
    echo "   ✓ Encontrado en O(1)\n";
    echo "   Nombre: {$indiceDocente[$cedulaPrueba]['NOMBRE_Y_APELLIDO']}\n";
} else {
    echo "   ✗ No encontrado\n";
}
echo "\n";

// Prueba de generación de lista para autocomplete
echo "10. Prueba de generación de lista para autocomplete:\n";
$listaCedulas = array_keys($indiceDocente);
echo "   Total de cédulas: " . count($listaCedulas) . "\n";
echo "   Primeras 5 cédulas: " . implode(", ", array_slice($listaCedulas, 0, 5)) . "\n\n";

// Prueba de merge
echo "11. Prueba de MERGE:\n";
echo "   Simulando carga de nuevo CSV con datos adicionales...\n";

// Crear datos simulados para merge
$datosNuevos = [
    $cedulaPrueba => [
        "NOMBRE_Y_APELLIDO" => $indiceDocente[$cedulaPrueba]['NOMBRE_Y_APELLIDO'],
        "VINCULACION" => "ACTUALIZADO",
        "CAMPO_NUEVO" => "Valor nuevo"
    ],
    "9999999" => [
        "NOMBRE_Y_APELLIDO" => "DOCENTE NUEVO",
        "VINCULACION" => "Carrera1"
    ]
];

// Simular merge
$indiceActual = $indiceDocente;
foreach ($datosNuevos as $cedula => $datos) {
    if (isset($indiceActual[$cedula])) {
        echo "   - Cédula $cedula ya existe: fusionando campos...\n";
        $indiceActual[$cedula] = array_merge($indiceActual[$cedula], $datos);
    } else {
        echo "   - Cédula $cedula es nueva: añadiendo...\n";
        $indiceActual[$cedula] = $datos;
    }
}

echo "   Registros antes del merge: $registros\n";
echo "   Registros después del merge: " . count($indiceActual) . "\n";
echo "   Registro mergeado (cédula $cedulaPrueba):\n";
echo "     - VINCULACION: {$indiceActual[$cedulaPrueba]['VINCULACION']}\n";
echo "     - CAMPO_NUEVO: {$indiceActual[$cedulaPrueba]['CAMPO_NUEVO']}\n";
echo "     - Campos originales preservados: " . (isset($indiceActual[$cedulaPrueba]['TERRITORIAL']) ? 'Sí' : 'No') . "\n\n";

echo "=== TEST COMPLETADO EXITOSAMENTE ===\n";
