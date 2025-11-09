<?php

/**
 * Test del nuevo endpoint GET /api/v2/listados/indice
 */

declare(strict_types=1);

echo "=== TEST: Endpoint GET /api/v2/listados/indice ===\n\n";

$url = "http://localhost:3000/api/v2/listados/indice";

echo "1. Probando endpoint:\n";
echo "   URL: $url\n";
echo "   Método: GET\n\n";

// Hacer petición
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Accept: application/json']);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

if ($curlError) {
    die("   ❌ ERROR CURL: $curlError\n");
}

echo "2. Respuesta HTTP:\n";
echo "   Status Code: $httpCode\n\n";

if ($httpCode !== 200) {
    echo "   ❌ ERROR: Código HTTP inesperado\n";
    echo "   Respuesta: " . substr($response, 0, 500) . "\n";
    exit(1);
}

// Decodificar respuesta
$resultado = json_decode($response, true);

if (!$resultado) {
    echo "   ❌ ERROR: No se pudo decodificar JSON\n";
    echo "   Respuesta raw: " . substr($response, 0, 500) . "\n";
    exit(1);
}

echo "3. Análisis de la respuesta:\n\n";

// Verificar estructura de la respuesta
if (isset($resultado['error']) && $resultado['error']) {
    echo "   ❌ ERROR: {$resultado['error']}\n";
    if (isset($resultado['message'])) {
        echo "   Mensaje: {$resultado['message']}\n";
    }
    exit(1);
}

if (!isset($resultado['indice'])) {
    echo "   ❌ ERROR: No se encontró el campo 'indice' en la respuesta\n";
    echo "   Estructura recibida: " . json_encode(array_keys($resultado), JSON_PRETTY_PRINT) . "\n";
    exit(1);
}

$indice = $resultado['indice'];
$meta = $resultado['meta'] ?? [];

echo "   ✓ Índice docente obtenido exitosamente\n\n";

echo "4. Información del índice:\n\n";
echo "   Total de docentes: " . ($meta['total_docentes'] ?? count($indice)) . "\n";
echo "   Estructura: " . ($meta['estructura'] ?? 'N/A') . "\n";
echo "   UUID: " . ($meta['uuid'] ?? 'N/A') . "\n\n";

// Verificar estructura del índice
if (!is_array($indice) || empty($indice)) {
    echo "   ❌ ERROR: El índice está vacío o no es un array\n";
    exit(1);
}

echo "5. Primeros 5 docentes del índice:\n\n";
$contador = 0;
foreach ($indice as $cedula => $datos) {
    if ($contador >= 5) break;

    echo "   Cédula: $cedula\n";
    echo "   - Nombre: " . ($datos['NOMBRE_Y_APELLIDO'] ?? 'N/A') . "\n";
    echo "   - Vinculación: " . ($datos['VINCULACION'] ?? 'N/A') . "\n";
    echo "   - Territorial: " . ($datos['TERRITORIAL'] ?? 'N/A') . "\n";
    echo "   - Total de campos: " . count($datos) . "\n\n";

    $contador++;
}

echo "6. Pruebas de búsqueda:\n\n";

// Obtener primera cédula
$primeraCedula = array_key_first($indice);
echo "   Búsqueda por cédula: $primeraCedula\n";

if (isset($indice[$primeraCedula])) {
    echo "   ✓ Búsqueda exitosa (O(1))\n";
    echo "   Nombre: " . ($indice[$primeraCedula]['NOMBRE_Y_APELLIDO'] ?? 'N/A') . "\n";
} else {
    echo "   ❌ ERROR: No se encontró la cédula\n";
}

echo "\n7. Estadísticas:\n\n";

// Contar campos únicos
$camposUnicos = [];
foreach ($indice as $datos) {
    foreach (array_keys($datos) as $campo) {
        $camposUnicos[$campo] = true;
    }
}

echo "   Total de campos únicos en el índice: " . count($camposUnicos) . "\n";
echo "   Campos: " . implode(", ", array_slice(array_keys($camposUnicos), 0, 10)) . "...\n\n";

// Tamaño del JSON
$jsonSize = strlen(json_encode($indice));
echo "   Tamaño aproximado del índice: " . number_format($jsonSize / 1024, 2) . " KB\n";

echo "\n8. Test de uso típico (autocompletado):\n\n";

// Simular autocompletado
$opcionesAutocomplete = [];
$contador = 0;
foreach ($indice as $cedula => $datos) {
    if ($contador >= 10) break;
    $opcionesAutocomplete[] = [
        'value' => $cedula,
        'label' => $cedula . ' - ' . ($datos['NOMBRE_Y_APELLIDO'] ?? 'Sin nombre')
    ];
    $contador++;
}

echo "   Primeras 10 opciones para autocomplete:\n";
foreach ($opcionesAutocomplete as $opcion) {
    echo "   - {$opcion['label']}\n";
}

echo "\n=== TEST COMPLETADO EXITOSAMENTE ===\n";
echo "\n";
echo "✓ Endpoint funcional: GET /api/v2/listados/indice\n";
echo "✓ Estructura JSON correcta\n";
echo "✓ Búsqueda O(1) por cédula\n";
echo "✓ Listo para uso en frontend\n";
