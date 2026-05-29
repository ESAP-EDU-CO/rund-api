#!/usr/bin/env php
<?php
/**
 * Scheduler asíncrono de extracción de datos.
 *
 * Lee documentos en estado "pendiente" del índice y los encola en rund-ai
 * para procesamiento. Respeta el rango horario configurado en scheduler_state.json.
 *
 * Uso: php /var/www/html/cli/scheduler_extraccion.php
 * Cron: cada 30 min en horas muertas (22:00–06:00)
 */

$STATE_FILE = __DIR__ . '/scheduler_state.json';
$AI_URL     = getenv('RUND_AI_URL') ?: 'http://rund-ai:8001';
$API_URL    = 'http://rund-api:3000';

$log = function (string $msg): void {
    echo date('Y-m-d H:i:s') . ' [scheduler] ' . $msg . PHP_EOL;
};

// --- 1. Leer estado ---
$state = [];
if (file_exists($STATE_FILE)) {
    $state = json_decode(file_get_contents($STATE_FILE), true) ?? [];
}

$habilitado = $state['habilitado']  ?? false;
$horaInicio = (int)($state['hora_inicio'] ?? 22);
$horaFin    = (int)($state['hora_fin']    ?? 6);

if (!$habilitado) {
    $log("Scheduler deshabilitado. Saliendo.");
    exit(0);
}

// --- 2. Verificar rango horario ---
$horaActual = (int) date('G'); // 0-23

// El rango puede cruzar medianoche (ej. 22-06)
$enRango = ($horaInicio > $horaFin)
    ? ($horaActual >= $horaInicio || $horaActual < $horaFin)
    : ($horaActual >= $horaInicio && $horaActual < $horaFin);

if (!$enRango) {
    $log("Fuera del rango {$horaInicio}:00–{$horaFin}:00 (hora actual: {$horaActual}:xx). Saliendo.");
    exit(0);
}

$log("Dentro del rango {$horaInicio}:00–{$horaFin}:00. Encolando documentos pendientes…");

// --- 3. Llamar a rund-ai: POST /queue/enqueue-pending ---
$ch = curl_init("$AI_URL/queue/enqueue-pending");
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 30,
    CURLOPT_POST           => true,
    CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
    CURLOPT_POSTFIELDS     => json_encode([
        'callback_url' => "$API_URL/api/v2/ai/webhook/extraction-complete",
    ]),
]);
$response = curl_exec($ch);
$httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode !== 202 && $httpCode !== 200) {
    $log("ERROR al llamar rund-ai (HTTP $httpCode): $response");
    $state['ultimo_run']       = date('Y-m-d\TH:i:s');
    $state['ultimo_resultado'] = ['error' => "HTTP $httpCode"];
    file_put_contents($STATE_FILE, json_encode($state, JSON_PRETTY_PRINT));
    exit(1);
}

$data      = json_decode($response, true);
$encolados = $data['enqueued']    ?? 0;
$colaSize  = $data['queue_size']  ?? '?';
$mensaje   = $data['message']     ?? '';

if ($mensaje) {
    $log($mensaje);
} else {
    $log("Encolados: $encolados documento(s). Cola activa: $colaSize.");
}

// --- 4. Persistir estado ---
$state['ultimo_run']       = date('Y-m-d\TH:i:s');
$state['ultimo_resultado'] = ['encolados' => $encolados, 'cola_size' => $colaSize];
$state['habilitado']       = $habilitado;
$state['hora_inicio']      = $horaInicio;
$state['hora_fin']         = $horaFin;
$state['actualizado_en']   = date('Y-m-d\TH:i:s');
file_put_contents($STATE_FILE, json_encode($state, JSON_PRETTY_PRINT));

$log("Completado.");
