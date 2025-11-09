<?php

/**
 * Script de prueba para la carga del CSV y generación del JSON
 * Simula exactamente lo que hace el frontend
 */

declare(strict_types=1);

// Simular la carga desde el contenedor
$csvPath = "/Users/ocastelblanco/Documents/ESAP/RUND/rund-deployment/pruebas/ListadoGeneralDocente.csv";

if (!file_exists($csvPath)) {
    die("ERROR: No se encuentra el archivo CSV en: $csvPath\n");
}

echo "=== TEST: Carga de CSV y Generación de JSON ===\n\n";

// Preparar los datos como lo hace el frontend
$propiedades = [
    ["label" => "Nombre", "valor" => "ListadoGeneralDocente.csv"],
    ["label" => "Tipo", "valor" => "Listado de docentes"],
    ["label" => "Origen", "valor" => "RUND Side-car"],
    ["label" => "Formato", "valor" => "CSV"],
    ["label" => "Tamaño", "valor" => "176.473 KB"],
    ["label" => "Size", "valor" => 180708],
    ["label" => "Uuid", "valor" => "d4a39873-1546-421c-884b-38a3f9d65434"],
    ["label" => "Duplicado", "valor" => true],
    ["label" => "Comentario", "valor" => "Prueba a JSON - III"]
];

// Construir la petición cURL
$url = "http://localhost:3000/api/v2/listados/cargar";

// Preparar el archivo
$cFile = new CURLFile($csvPath, 'text/csv', 'ListadoGeneralDocente.csv');

// Preparar los datos del POST
$postData = [
    'archivo' => $cFile,
    'accion' => 'cargar',
    'propiedades' => json_encode($propiedades)
];

echo "1. Configuración de la petición:\n";
echo "   URL: $url\n";
echo "   Archivo: $csvPath\n";
echo "   Tamaño: " . filesize($csvPath) . " bytes\n";
echo "   Duplicado: true (actualización)\n\n";

echo "2. Propiedades enviadas:\n";
foreach ($propiedades as $prop) {
    echo "   - {$prop['label']}: {$prop['valor']}\n";
}
echo "\n";

// Inicializar cURL
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Accept: application/json'
]);

echo "3. Enviando petición...\n";
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

if ($curlError) {
    die("ERROR CURL: $curlError\n");
}

echo "   HTTP Status: $httpCode\n\n";

echo "4. Respuesta del servidor:\n";
echo "   Longitud de respuesta: " . strlen($response) . " bytes\n\n";

// Decodificar respuesta
$resultado = json_decode($response, true);

if (!$resultado) {
    echo "   ERROR: No se pudo decodificar la respuesta JSON\n";
    echo "   Respuesta raw:\n";
    echo "   " . substr($response, 0, 500) . "...\n";
    exit(1);
}

echo "5. Análisis de la respuesta:\n\n";

// Verificar si hay error general
if (isset($resultado['error']) && $resultado['error']) {
    echo "   ❌ ERROR GENERAL: {$resultado['error']}\n";
} else {
    echo "   ✓ Carga del CSV: OK\n";
}

// Verificar si se generó el índice JSON
if (isset($resultado['indiceJson'])) {
    echo "\n6. Información del índice JSON generado:\n\n";

    $indiceInfo = $resultado['indiceJson'];

    if (isset($indiceInfo['error']) && $indiceInfo['error']) {
        echo "   ❌ ERROR al generar índice: {$indiceInfo['error']}\n";
    } else {
        echo "   ✓ Índice generado exitosamente\n\n";
        echo "   Detalles:\n";
        echo "   - Registros procesados: " . ($indiceInfo['registros'] ?? 'N/A') . "\n";
        echo "   - Estructura: " . ($indiceInfo['estructura'] ?? 'N/A') . "\n";
        echo "   - Merge realizado: " . ($indiceInfo['merge'] ? 'Sí' : 'No') . "\n";

        if ($indiceInfo['merge']) {
            echo "   - Registros anteriores: " . ($indiceInfo['registrosAnteriores'] ?? 'N/A') . "\n";
            echo "   - Registros nuevos: " . ($indiceInfo['registrosNuevos'] ?? 'N/A') . "\n";
            echo "   - Registros finales: " . ($indiceInfo['registrosFinales'] ?? 'N/A') . "\n";
        }

        echo "   - Archivo existía: " . ($indiceInfo['archivoExiste'] ? 'Sí' : 'No') . "\n";

        if (isset($indiceInfo['uuid'])) {
            echo "   - UUID del archivo JSON: " . $indiceInfo['uuid'] . "\n";
        }

        if (isset($indiceInfo['carga'])) {
            echo "\n   Resultado de carga en OpenKM:\n";
            if (isset($indiceInfo['carga']['error']) && $indiceInfo['carga']['error']) {
                echo "   ❌ Error: " . $indiceInfo['carga']['error'] . "\n";
            } else {
                echo "   ✓ Archivo guardado en OpenKM\n";
            }
        }
    }
} else {
    echo "\n❌ NO SE GENERÓ EL ÍNDICE JSON\n";
    echo "   Posibles causas:\n";
    echo "   - El nombre del archivo no es 'ListadoGeneralDocente.csv'\n";
    echo "   - El tipo no es 'LISTADO_DE_DOCENTES'\n";
    echo "   - La condición del flujo especial no se cumplió\n";
}

echo "\n7. Respuesta completa (JSON):\n";
echo json_encode($resultado, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";

// Intentar obtener el JSON generado
echo "\n8. Verificando si el JSON está disponible en OpenKM:\n";
$ch2 = curl_init();
curl_setopt($ch2, CURLOPT_URL, "http://localhost:3000/api/v2/file?accion=data&nombre=indice_docente");
curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);
$jsonResponse = curl_exec($ch2);
$httpCode2 = curl_getinfo($ch2, CURLINFO_HTTP_CODE);
curl_close($ch2);

if ($httpCode2 === 200) {
    $jsonData = json_decode($jsonResponse, true);
    if ($jsonData && count($jsonData) > 0) {
        echo "   ✓ JSON disponible en OpenKM\n";
        echo "   - Total de docentes en el índice: " . count($jsonData) . "\n";
        echo "   - Primeras 3 cédulas: " . implode(", ", array_slice(array_keys($jsonData), 0, 3)) . "\n";
    } else {
        echo "   ❌ JSON vacío o no válido\n";
    }
} else {
    echo "   ❌ No se pudo obtener el JSON (HTTP $httpCode2)\n";
}

echo "\n=== TEST COMPLETADO ===\n";
