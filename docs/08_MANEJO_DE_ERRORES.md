# 08 - Manejo de Errores

## Tabla de Contenidos
- [Estrategia Global](#estrategia-global)
- [Formato de Respuestas de Error](#formato-de-respuestas-de-error)
- [Códigos HTTP](#códigos-http)
- [Logging y Trazabilidad](#logging-y-trazabilidad)
- [Manejo de Errores OpenKM](#manejo-de-errores-openkm)
- [Timeouts y Límites](#timeouts-y-límites)
- [Debugging y Troubleshooting](#debugging-y-troubleshooting)
- [Mejores Prácticas](#mejores-prácticas)

---

## Estrategia Global

### Arquitectura de Manejo de Errores

```
┌─────────────────────────────────────────────┐
│            Cliente (Request)                 │
└──────────────────┬──────────────────────────┘
                   │
┌──────────────────▼──────────────────────────┐
│         index.php - Try/Catch Global        │ ← Nivel 1: Errores fatales
├─────────────────────────────────────────────┤
│         Router::dispatch()                  │
├─────────────────────────────────────────────┤
│         Router::findRoute()                 │ ← Nivel 2: Ruta no encontrada
├─────────────────────────────────────────────┤
│         Middleware (ValidationMiddleware)   │ ← Nivel 3: Validación input
├─────────────────────────────────────────────┤
│         Controller Methods                  │ ← Nivel 4: Lógica de negocio
├─────────────────────────────────────────────┤
│         BaseController::errorResponse()     │ ← Nivel 5: Errores específicos
├─────────────────────────────────────────────┤
│         OpenKM Service                      │ ← Nivel 6: Errores externos
└─────────────────────────────────────────────┘
                   │
                   ▼
         Log de Errores PHP
```

---

### 1. Try-Catch Global (index.php)

**Ubicación:** `/app/index.php` líneas 24-47

```php
try {
    // Crear e inicializar el router
    $router = new Router();

    // Configurar rutas v2
    setupRoutesV2($router);

    // Procesar la solicitud
    $router->dispatch();
} catch (Throwable $e) {
    // Manejo de errores global
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');

    $errorResponse = [
        'error' => 'Error interno del servidor',
        'message' => 'Ha ocurrido un error inesperado'
    ];

    // Log del error para debugging
    error_log("RUND API Error: " . $e->getMessage() .
              " in " . $e->getFile() . ":" . $e->getLine());

    echo json_encode($errorResponse, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
}
```

#### Características

| Aspecto | Implementación | Propósito |
|---------|----------------|-----------|
| **Captura** | `Throwable` | Captura Exceptions y Errors |
| **HTTP Status** | `500` | Error interno del servidor |
| **Mensaje Público** | Genérico | No expone detalles técnicos |
| **Logging** | `error_log()` | Registra detalles completos |
| **Formato** | JSON | Consistente con toda la API |

#### Tipos de Errores Capturados

```php
// Throwable incluye:
interface Throwable {
    // Exception: Errores esperados y manejables
    // - RuntimeException
    // - LogicException
    // - InvalidArgumentException
    // etc.

    // Error: Errores graves de PHP
    // - TypeError
    // - ParseError
    // - ArithmeticError
    // etc.
}
```

#### Ejemplo de Log Generado

```
[20-Oct-2025 14:30:45 America/Bogota] RUND API Error: Call to undefined method OpenKM::nonExistentMethod() in /var/www/rund-api/app/src/Controllers/V2/ArchivosController.php:85
```

---

### 2. Router::handleNotFound()

**Ubicación:** `/app/src/Core/Router.php` líneas 268-277

```php
private function handleNotFound(): void
{
    http_response_code(404);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'error' => 'Endpoint no encontrado',
        'method' => $_SERVER['REQUEST_METHOD'],
        'path' => parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH)
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
}
```

#### Cuándo Se Activa

1. **Ruta inexistente**
   ```http
   GET /api/v2/endpoint-que-no-existe
   → 404 Not Found
   ```

2. **Método no permitido en ruta existente**
   ```http
   PUT /api/v2/system/info  (solo GET permitido)
   → 404 Not Found
   ```

3. **Parámetros de ruta incorrectos**
   ```http
   GET /api/v2/profesores/  (falta cédula)
   → 404 Not Found
   ```

#### Ejemplo de Respuesta

```json
{
    "error": "Endpoint no encontrado",
    "method": "GET",
    "path": "/api/v2/no-existe"
}
```

**HTTP Status:** `404 Not Found`

---

### 3. BaseController::errorResponse()

**Ubicación:** `/app/src/Controllers/BaseController.php` líneas 78-82

```php
protected function errorResponse(string $message, int $code = 400): array
{
    http_response_code($code);
    return ['error' => $message];
}
```

#### Uso en Controllers

```php
// Ejemplo 1: Validación de entrada
public function show(array $params = []): ?array
{
    if (!isset($params['uuid'])) {
        return $this->errorResponse('UUID es requerido', 400);
    }
    // ...
}

// Ejemplo 2: Recurso no encontrado
public function getCertificado(array $params = []): array
{
    $cert = CertificadosService::obtener($params['id']);

    if (!$cert) {
        return $this->errorResponse('Certificado no encontrado', 404);
    }

    return $this->successResponse($cert);
}

// Ejemplo 3: Operación no permitida
public function delete(array $params = []): array
{
    if (!$this->canDelete($params['uuid'])) {
        return $this->errorResponse('No tiene permisos para borrar este archivo', 403);
    }
    // ...
}
```

#### Ventajas

- Establece automáticamente el código HTTP
- Formato consistente con toda la API
- Extensible para agregar más detalles

---

## Formato de Respuestas de Error

### 1. Error Estándar (Simple)

**Estructura:**
```json
{
    "error": "Mensaje de error descriptivo"
}
```

**Ejemplos:**
```json
{
    "error": "UUID es requerido"
}

{
    "error": "Certificado no encontrado"
}

{
    "error": "Archivo requerido"
}
```

**HTTP Status:** Variable según el error (400, 403, 404, 500, etc.)

---

### 2. Error con Detalles (ValidationMiddleware)

**Estructura:**
```json
{
    "error": "Descripción general del error",
    "missing": ["campo1", "campo2"],
    "required": ["campo1", "campo2", "campo3"]
}
```

**Ejemplo - Parámetros Faltantes:**
```json
{
    "error": "Parámetros de consulta requeridos faltantes",
    "missing": ["tipo", "formato"],
    "required": ["nombre", "tipo", "formato"]
}
```

**HTTP Status:** `400 Bad Request`

**Ejemplo - Archivos Faltantes:**
```json
{
    "error": "Archivos requeridos faltantes o con errores",
    "missing": ["archivo"],
    "required": ["archivo"]
}
```

**HTTP Status:** `400 Bad Request`

**Ejemplo - Archivo Demasiado Grande:**
```json
{
    "error": "Archivo demasiado grande",
    "file": "documento",
    "size": 52428800,
    "max_allowed": 52428800
}
```

**HTTP Status:** `413 Payload Too Large`

---

### 3. Error con Contexto (AuthMiddleware)

**Estructura:**
```json
{
    "error": "Mensaje principal",
    "contexto_adicional": "valor"
}
```

**Ejemplo - Acceso Denegado por IP:**
```json
{
    "error": "Acceso denegado desde esta IP",
    "client_ip": "203.0.113.45"
}
```

**HTTP Status:** `403 Forbidden`

**Ejemplo - Método HTTP No Permitido:**
```json
{
    "error": "Método HTTP no permitido",
    "allowed": ["GET", "POST"],
    "received": "PUT"
}
```

**HTTP Status:** `405 Method Not Allowed`

---

### 4. Error 404 (Endpoint No Encontrado)

**Estructura:**
```json
{
    "error": "Endpoint no encontrado",
    "method": "GET",
    "path": "/api/v2/ruta/inexistente"
}
```

**HTTP Status:** `404 Not Found`

---

### 5. Error 500 (Error Interno)

**Estructura:**
```json
{
    "error": "Error interno del servidor",
    "message": "Ha ocurrido un error inesperado"
}
```

**HTTP Status:** `500 Internal Server Error`

**Nota:** Detalles técnicos solo en logs, no en respuesta al cliente.

---

### 6. Errores de OpenKM

**Estructura:**
```json
{
    "error": "Mensaje específico de OpenKM"
}
```

**Ejemplos:**

```json
{
    "error": "No se encontró la imagen logo.png en la ruta /okm:root/RUND/CONFIG/IMG/"
}
```

```json
{
    "error": "No se pudo obtener datos.json"
}
```

**HTTP Status:** Variable (404 para no encontrado, 500 para otros)

---

## Códigos HTTP

### Tabla Completa de Códigos Utilizados

| Código | Nombre | Uso en RUND | Ubicación | Ejemplo |
|--------|--------|-------------|-----------|---------|
| **200** | OK | Respuesta exitosa | Router, Controllers | Datos obtenidos correctamente |
| **400** | Bad Request | Validación fallida | BaseController, ValidationMiddleware | Parámetros faltantes |
| **401** | Unauthorized | Sin autenticación | AuthMiddleware (futuro) | Token inválido |
| **403** | Forbidden | Sin permisos | AuthMiddleware | IP bloqueada |
| **404** | Not Found | Recurso no existe | Router, Controllers | Endpoint o archivo no encontrado |
| **405** | Method Not Allowed | Método HTTP incorrecto | ValidationMiddleware | PUT en endpoint GET-only |
| **413** | Payload Too Large | Archivo muy grande | ValidationMiddleware | Archivo > 50MB |
| **500** | Internal Server Error | Error del servidor | index.php try-catch | Exception no manejada |
| **501** | Not Implemented | Endpoint en desarrollo | Controllers | Funcionalidad pendiente |

---

### 1. 200 OK

**Uso:**
- Respuestas exitosas por defecto
- GET, POST, PUT, DELETE exitosos

**Dónde:**
- `Router::handleResponse()` - No establece código, usa default 200
- Controllers - Respuestas de `successResponse()`

**Ejemplos:**
```http
GET /api/v2/system/info
HTTP/1.1 200 OK
Content-Type: application/json

{
    "nombre": "RUND API",
    "version": "2.0"
}
```

```http
POST /api/v2/certificados/generar
HTTP/1.1 200 OK
Content-Type: application/json

{
    "certificado": { ... },
    "meta": { ... }
}
```

---

### 2. 400 Bad Request

**Uso:**
- Parámetros faltantes o inválidos
- JSON malformado
- Datos de entrada incorrectos

**Dónde:**
- `BaseController::errorResponse()` - default
- `ValidationMiddleware::requireQueryParams()`
- `ValidationMiddleware::requireFiles()`
- Controllers (validaciones específicas)

**Ejemplos:**
```http
POST /api/v2/certificados/generar
(sin body)

HTTP/1.1 400 Bad Request
Content-Type: application/json

{
    "error": "Datos requeridos para generar certificado"
}
```

```http
GET /api/v2/archivos/datos/
(falta nombre)

HTTP/1.1 400 Bad Request
Content-Type: application/json

{
    "error": "Nombre es requerido"
}
```

---

### 3. 401 Unauthorized

**Uso:**
- Token de autenticación faltante
- Token inválido o expirado

**Dónde:**
- `AuthMiddleware::authenticate()` (cuando se implemente)

**Ejemplo:**
```http
GET /api/v2/archivos/{uuid}
(sin header Authorization)

HTTP/1.1 401 Unauthorized
Content-Type: application/json

{
    "error": "Token de autenticación requerido",
    "message": "Incluya el token en el header: Authorization: Bearer TOKEN"
}
```

**Nota:** Actualmente no implementado (AuthMiddleware es placeholder).

---

### 4. 403 Forbidden

**Uso:**
- Permisos insuficientes
- IP no autorizada
- Recurso protegido

**Dónde:**
- `AuthMiddleware::limitToIPs()` - Único implementado
- `AuthMiddleware::requireRole()` (cuando se implemente)

**Ejemplo:**
```http
DELETE /api/v2/archivos/papelera
From: 203.0.113.45

HTTP/1.1 403 Forbidden
Content-Type: application/json

{
    "error": "Acceso denegado desde esta IP",
    "client_ip": "203.0.113.45"
}
```

---

### 5. 404 Not Found

**Uso:**
- Endpoint no existe
- Recurso específico no encontrado (archivo, certificado, etc.)

**Dónde:**
- `Router::handleNotFound()`
- Controllers (cuando recurso no existe)

**Ejemplos:**
```http
GET /api/v2/endpoint-inexistente

HTTP/1.1 404 Not Found
Content-Type: application/json

{
    "error": "Endpoint no encontrado",
    "method": "GET",
    "path": "/api/v2/endpoint-inexistente"
}
```

```http
GET /api/v2/certificados/9999

HTTP/1.1 404 Not Found
Content-Type: application/json

{
    "error": "Certificado no encontrado"
}
```

---

### 6. 405 Method Not Allowed

**Uso:**
- Método HTTP no permitido en endpoint

**Dónde:**
- `ValidationMiddleware::validateHttpMethod()`

**Ejemplo:**
```http
PUT /api/v2/system/info
(solo GET permitido)

HTTP/1.1 405 Method Not Allowed
Content-Type: application/json

{
    "error": "Método HTTP no permitido",
    "allowed": ["GET"],
    "received": "PUT"
}
```

---

### 7. 413 Payload Too Large

**Uso:**
- Archivo subido excede límite de tamaño

**Dónde:**
- `ValidationMiddleware::validateFileSize()`

**Ejemplo:**
```http
POST /api/v2/archivos/subir
Content-Length: 52428900
(archivo de 50.1MB, límite 50MB)

HTTP/1.1 413 Payload Too Large
Content-Type: application/json

{
    "error": "Archivo demasiado grande",
    "file": "archivo",
    "size": 52428900,
    "max_allowed": 52428800
}
```

---

### 8. 500 Internal Server Error

**Uso:**
- Exception no manejada
- Error en lógica de negocio
- Error de servicios externos (OpenKM, AI, OCR)

**Dónde:**
- `index.php` try-catch global

**Ejemplo:**
```http
GET /api/v2/certificados/generar

HTTP/1.1 500 Internal Server Error
Content-Type: application/json

{
    "error": "Error interno del servidor",
    "message": "Ha ocurrido un error inesperado"
}
```

**Log Correspondiente:**
```
[20-Oct-2025 14:30:45] RUND API Error: Call to undefined method in /var/www/rund-api/app/src/Services/CertificadosService.php:123
```

---

### 9. 501 Not Implemented

**Uso:**
- Endpoint definido pero no implementado
- Funcionalidad en desarrollo

**Dónde:**
- Controllers (endpoints en construcción)

**Ejemplos:**
```http
GET /api/v2/archivos/{uuid}

HTTP/1.1 501 Not Implemented
Content-Type: application/json

{
    "error": "Endpoint en construcción - usar /api/v1/getFile por ahora"
}
```

```http
GET /api/v2/firmas/{uuid}

HTTP/1.1 501 Not Implemented
Content-Type: application/json

{
    "error": "Endpoint en construcción - usar /api/v1/getFirmas por ahora"
}
```

---

## Logging y Trazabilidad

### 1. Configuración PHP

#### Desarrollo

```ini
; php.ini - DESARROLLO
display_errors = On
display_startup_errors = On
error_reporting = E_ALL
log_errors = On
error_log = /var/log/php/dev-error.log
```

#### Producción

```ini
; php.ini - PRODUCCIÓN
display_errors = Off
display_startup_errors = Off
error_reporting = E_ALL
log_errors = On
error_log = /var/log/php/prod-error.log
```

**Verificar Configuración:**
```php
// En SystemController::getInfo()
'php_config' => [
    'display_errors' => ini_get('display_errors'),
    'error_reporting' => error_reporting(),
    'log_errors' => ini_get('log_errors'),
    'error_log' => ini_get('error_log')
]
```

---

### 2. error_log en index.php

**Ubicación:** `/app/index.php` línea 44

```php
error_log("RUND API Error: " . $e->getMessage() .
          " in " . $e->getFile() . ":" . $e->getLine());
```

#### Formato de Log

```
[Fecha Hora Timezone] RUND API Error: MENSAJE in ARCHIVO:LINEA
```

#### Ejemplos Reales

```
[20-Oct-2025 14:30:45 America/Bogota] RUND API Error: Call to undefined method OpenKM::nonExistent() in /var/www/rund-api/app/src/Controllers/V2/ArchivosController.php:85

[20-Oct-2025 15:22:10 America/Bogota] RUND API Error: Uncaught TypeError: Return value must be of type array, null returned in /var/www/rund-api/app/src/Services/CategoriasService.php:45

[20-Oct-2025 16:45:33 America/Bogota] RUND API Error: cURL error 28: Operation timed out after 30000 milliseconds in /var/www/rund-api/app/src/Core/OpenKM.php:74
```

---

### 3. ValidationMiddleware::logRequest()

**Ubicación:** `/app/src/Middleware/ValidationMiddleware.php` líneas 123-137

```php
public static function logRequest(): callable
{
    return function (string $method, string $path, array $params = []): bool {
        $logData = [
            'timestamp' => date('Y-m-d H:i:s'),
            'method' => $method,
            'path' => $path,
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
        ];

        error_log('RUND API Request: ' . json_encode($logData));
        return true;
    };
}
```

#### Uso en Rutas

```php
$router->post('/api/v2/archivos/subir',
    [ArchivosController::class, 'subir'],
    [
        ValidationMiddleware::logRequest(),
        ValidationMiddleware::requireFiles(['archivo'])
    ]
);
```

#### Formato de Log

```json
[20-Oct-2025 14:30:45] RUND API Request: {"timestamp":"2025-10-20 14:30:45","method":"POST","path":"/api/v2/archivos/subir","ip":"192.168.1.100","user_agent":"Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36"}
```

---

### 4. Logging Recomendado (No Implementado)

#### Sistema de Logging Estructurado

```php
class Logger
{
    private const LOG_DIR = '/var/log/rund/';

    /**
     * Niveles de log según PSR-3
     */
    public static function emergency(string $message, array $context = []): void
    {
        self::log('EMERGENCY', $message, $context);
    }

    public static function alert(string $message, array $context = []): void
    {
        self::log('ALERT', $message, $context);
    }

    public static function critical(string $message, array $context = []): void
    {
        self::log('CRITICAL', $message, $context);
    }

    public static function error(string $message, array $context = []): void
    {
        self::log('ERROR', $message, $context);
    }

    public static function warning(string $message, array $context = []): void
    {
        self::log('WARNING', $message, $context);
    }

    public static function notice(string $message, array $context = []): void
    {
        self::log('NOTICE', $message, $context);
    }

    public static function info(string $message, array $context = []): void
    {
        self::log('INFO', $message, $context);
    }

    public static function debug(string $message, array $context = []): void
    {
        if ($_ENV['APP_ENV'] !== 'production') {
            self::log('DEBUG', $message, $context);
        }
    }

    private static function log(string $level, string $message, array $context): void
    {
        $logEntry = [
            'timestamp' => date('c'),
            'level' => $level,
            'message' => $message,
            'context' => $context,
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'user' => $_SESSION['user']['id'] ?? 'anonymous'
        ];

        // Log a archivo
        $filename = self::LOG_DIR . strtolower($level) . '-' . date('Y-m-d') . '.log';
        file_put_contents(
            $filename,
            json_encode($logEntry, JSON_UNESCAPED_UNICODE) . PHP_EOL,
            FILE_APPEND
        );

        // Log críticos también a error_log
        if (in_array($level, ['EMERGENCY', 'ALERT', 'CRITICAL', 'ERROR'])) {
            error_log("[$level] $message | " . json_encode($context));
        }
    }
}

// Uso:
Logger::info('Certificado generado', ['id' => $certId, 'cedula' => $cedula]);
Logger::error('Error al conectar con OpenKM', ['error' => $e->getMessage()]);
Logger::warning('Archivo muy grande detectado', ['size' => $fileSize]);
```

#### Logs por Categoría

```
/var/log/rund/
├── access-2025-10-20.log          # Todas las peticiones
├── error-2025-10-20.log           # Errores de aplicación
├── security-2025-10-20.log        # Eventos de seguridad
├── audit-2025-10-20.log           # Auditoría de operaciones
└── debug-2025-10-20.log           # Debug (solo dev/staging)
```

---

## Manejo de Errores OpenKM

### 1. Detección de Errores por Substring

**Ubicación:** `/app/src/Core/OpenKM.php` línea 221

```php
public static function getImageFile(string $nombre, string $ruta = Config::TAX_APP_IMG): void
{
    $query = "search/find?name=" . urlencode($nombre) . "&path=" . urlencode($ruta);
    $rawResp = self::consulta($query);

    // Detección de error por substring
    if (substr($rawResp, 0, 19) == "RepositoryException") {
        header("Content-Type: application/json");
        echo json_encode([
            "error" => "No se encontró la imagen $nombre en la ruta $ruta"
        ]);
        exit();
    }

    // Continuar si no hay error...
}
```

#### Tipos de Excepciones de OpenKM

| Excepción | Significado | Acción en RUND |
|-----------|-------------|----------------|
| `RepositoryException` | Archivo/carpeta no encontrado | Retornar 404 con mensaje |
| `AccessDeniedException` | Sin permisos | Retornar 403 |
| `PathNotFoundException` | Ruta no existe | Retornar 404 |
| `ItemExistsException` | Archivo ya existe | Actualizar versión |
| `LockException` | Archivo bloqueado | Retornar 409 Conflict |
| `VersionException` | Error de versionado | Retornar 500 |

#### Ejemplo de Respuesta OpenKM en Error

```
RepositoryException: [PathNotFoundException] -> /okm:root/RUND/CONFIG/IMG/logo-inexistente.png
```

---

### 2. Manejo en getDataFile()

**Ubicación:** `/app/src/Core/OpenKM.php` líneas 199-207

```php
public static function getDataFile(string $nombre, string $ruta = Config::TAX_APP_DATA): array
{
    $query = "search/find?name=" . urlencode("$nombre.json") . "&path=" . urlencode($ruta);
    $uuid = json_decode(self::consulta($query), true)["queryResult"]["node"]["uuid"];

    if ($uuid) {
        return json_decode(self::getArchivo($uuid), true);
    }

    return ["error" => "No se pudo obtener $nombre.json"];
}
```

**Problemas:**
- No diferencia entre archivo no encontrado y error de red
- No valida que el JSON sea válido

**Mejora Recomendada:**

```php
public static function getDataFile(string $nombre, string $ruta = Config::TAX_APP_DATA): array
{
    try {
        $query = "search/find?name=" . urlencode("$nombre.json") .
                 "&path=" . urlencode($ruta);
        $rawResp = self::consulta($query);

        // Detectar errores de OpenKM
        if (strpos($rawResp, 'Exception') !== false) {
            error_log("OpenKM Error: $rawResp");
            return ["error" => "No se pudo obtener $nombre.json"];
        }

        $respDecoded = json_decode($rawResp, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log("JSON Error: " . json_last_error_msg());
            return ["error" => "Respuesta inválida de OpenKM"];
        }

        $uuid = $respDecoded["queryResult"]["node"]["uuid"] ?? null;
        if (!$uuid) {
            return ["error" => "Archivo $nombre.json no encontrado"];
        }

        $contenido = self::getArchivo($uuid);
        $datos = json_decode($contenido, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log("JSON Error en $nombre.json: " . json_last_error_msg());
            return ["error" => "Archivo JSON corrupto: $nombre.json"];
        }

        return $datos;
    } catch (Exception $e) {
        error_log("Error obteniendo $nombre.json: " . $e->getMessage());
        return ["error" => "Error al obtener archivo de configuración"];
    }
}
```

---

### 3. Errores CURL

**Ubicación:** `/app/src/Core/OpenKM.php` líneas 74-76

```php
$resp = curl_exec($curl);
if (curl_errno($curl)) {
    $resp = curl_error($curl);
}
curl_close($curl);
return $resp;
```

#### Tipos de Errores CURL Comunes

| Código | Error | Causa | Solución |
|--------|-------|-------|----------|
| 6 | `Couldn't resolve host` | OpenKM no accesible | Verificar CORE_API_URL |
| 7 | `Failed to connect` | Servicio caído | Verificar docker/servicio |
| 28 | `Operation timed out` | Timeout | Aumentar CURLOPT_TIMEOUT |
| 52 | `Empty reply from server` | OpenKM reiniciando | Reintentar |
| 56 | `Connection reset` | Red inestable | Implementar retry |

#### Ejemplo de Manejo

```php
public static function consulta(string $consulta, string $tipo = "GET", ...$args): string
{
    $maxRetries = 3;
    $attempt = 0;

    while ($attempt < $maxRetries) {
        $curl = curl_init();
        // ... configuración CURL ...

        $resp = curl_exec($curl);
        $error = curl_errno($curl);
        $errorMsg = curl_error($curl);
        curl_close($curl);

        if (!$error) {
            return $resp; // Éxito
        }

        // Errores que vale la pena reintentar
        $retryableErrors = [6, 7, 28, 52, 56];
        if (in_array($error, $retryableErrors) && $attempt < $maxRetries - 1) {
            $attempt++;
            error_log("OpenKM CURL Error $error (intento $attempt/$maxRetries): $errorMsg");
            usleep(500000); // Esperar 0.5s antes de reintentar
            continue;
        }

        // Error no recuperable
        error_log("OpenKM CURL Error $error: $errorMsg");
        throw new \RuntimeException("Error de conexión con OpenKM: $errorMsg");
    }

    throw new \RuntimeException("OpenKM no disponible después de $maxRetries intentos");
}
```

---

## Timeouts y Límites

### 1. Configuración CURL (OpenKM)

**Ubicación:** `/app/src/Core/OpenKM.php` líneas 50-51

```php
curl_setopt($curl, CURLOPT_TIMEOUT, 30);         // Timeout total: 30s
curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 10);  // Timeout conexión: 10s
```

#### Explicación

| Configuración | Valor | Descripción | Cuándo Se Alcanza |
|---------------|-------|-------------|-------------------|
| `CURLOPT_TIMEOUT` | 30s | Tiempo máximo para toda la operación | Transferencia de archivos grandes |
| `CURLOPT_CONNECTTIMEOUT` | 10s | Tiempo máximo para establecer conexión | OpenKM no responde |

#### Ajustes Recomendados por Operación

```php
class OpenKMTimeouts
{
    const QUICK_QUERY = 5;      // Búsquedas, metadata
    const STANDARD = 30;        // Subida/descarga normal
    const LARGE_FILE = 120;     // Archivos > 10MB
    const BATCH = 300;          // Operaciones masivas

    public static function setTimeouts($curl, int $timeout): void
    {
        curl_setopt($curl, CURLOPT_TIMEOUT, $timeout);
        curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, min(10, $timeout / 3));
    }
}

// Uso:
// Para búsquedas rápidas
OpenKMTimeouts::setTimeouts($curl, OpenKMTimeouts::QUICK_QUERY);

// Para subida de archivos grandes
OpenKMTimeouts::setTimeouts($curl, OpenKMTimeouts::LARGE_FILE);
```

---

### 2. Configuración PHP

**Valores Recomendados:**

```ini
; php.ini - PRODUCCIÓN
max_execution_time = 300        ; 5 minutos para operaciones largas
max_input_time = 120            ; 2 minutos para recibir datos POST
memory_limit = 256M             ; Suficiente para archivos grandes
upload_max_filesize = 50M       ; Límite por archivo
post_max_size = 52M             ; Ligeramente mayor que upload_max
```

#### Timeouts por Tipo de Operación

| Operación | max_execution_time | Justificación |
|-----------|-------------------|---------------|
| GET simple | 30s | Respuestas rápidas |
| POST archivo | 120s | Subida de 50MB |
| Generación PDF | 180s | LibreOffice conversion |
| OCR | 240s | Procesamiento pesado |
| Batch operations | 300s | Múltiples archivos |

---

### 3. Timeout en Servicios Externos

#### AI Service (Ollama)

```php
class AIService
{
    private const TIMEOUT = 60; // 1 minuto para generación

    public static function generate(string $prompt): array
    {
        $ch = curl_init($_ENV['AI_API_URL'] . '/api/generate');
        curl_setopt($ch, CURLOPT_TIMEOUT, self::TIMEOUT);
        // ...
    }
}
```

#### OCR Service (PaddleOCR)

```php
class OCRService
{
    private const TIMEOUT = 60; // 1 minuto para extracción

    public static function extractText(string $filePath): array
    {
        $ch = curl_init($_ENV['OCR_API_URL'] . '/extract-text');
        curl_setopt($ch, CURLOPT_TIMEOUT, self::TIMEOUT);
        // ...
    }
}
```

---

## Debugging y Troubleshooting

### 1. Habilitar Debug en Desarrollo

```php
// bootstrap.php
if ($_ENV['APP_ENV'] === 'development') {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
    ini_set('display_startup_errors', '1');
}
```

---

### 2. Endpoint de Debug

```php
class SystemController extends BaseController
{
    /**
     * GET /api/v2/system/debug
     * Solo en desarrollo
     */
    public function debug(array $params = []): array
    {
        if ($_ENV['APP_ENV'] === 'production') {
            return $this->errorResponse('Debug no disponible en producción', 403);
        }

        return [
            'php' => [
                'version' => phpversion(),
                'extensions' => get_loaded_extensions(),
                'ini' => [
                    'display_errors' => ini_get('display_errors'),
                    'error_reporting' => error_reporting(),
                    'memory_limit' => ini_get('memory_limit'),
                    'max_execution_time' => ini_get('max_execution_time')
                ]
            ],
            'environment' => [
                'APP_ENV' => $_ENV['APP_ENV'] ?? 'unknown',
                'CORE_API_URL' => $_ENV['CORE_API_URL'] ?? 'not set',
                'AI_API_URL' => $_ENV['AI_API_URL'] ?? 'not set',
                'OCR_API_URL' => $_ENV['OCR_API_URL'] ?? 'not set'
            ],
            'server' => [
                'software' => $_SERVER['SERVER_SOFTWARE'] ?? 'unknown',
                'protocol' => $_SERVER['SERVER_PROTOCOL'] ?? 'unknown',
                'method' => $_SERVER['REQUEST_METHOD'] ?? 'unknown'
            ],
            'logs' => [
                'error_log' => ini_get('error_log'),
                'log_errors' => ini_get('log_errors')
            ]
        ];
    }
}
```

---

### 3. Headers de Debug

```php
class DebugMiddleware
{
    public static function addDebugHeaders(): callable
    {
        return function (string $method, string $path, array $params = []): bool {
            if ($_ENV['APP_ENV'] !== 'production') {
                header('X-Debug-Timestamp: ' . microtime(true));
                header('X-Debug-Memory: ' . memory_get_usage(true));
                header('X-Debug-Peak-Memory: ' . memory_get_peak_usage(true));
            }
            return true;
        };
    }
}

// Uso:
if ($_ENV['APP_ENV'] === 'development') {
    $router->addGlobalMiddleware(DebugMiddleware::addDebugHeaders());
}
```

---

### 4. Debugging de Errores Comunes

#### Error: "Endpoint no encontrado"

**Causa:** Ruta mal definida o método HTTP incorrecto

**Debug:**
```php
// Ver todas las rutas registradas
class Router
{
    public function debugRoutes(): array
    {
        return array_map(function($route) {
            return [
                'method' => $route['method'],
                'path' => $route['path']
            ];
        }, $this->routes);
    }
}

// GET /api/v2/system/routes (solo dev)
public function getRoutes(): array
{
    return $router->debugRoutes();
}
```

---

#### Error: "Call to undefined method OpenKM::..."

**Causa:** Método no existe en clase OpenKM

**Debug:**
```php
// Verificar métodos disponibles
$methods = get_class_methods(OpenKM::class);
var_dump($methods);
```

---

#### Error: "cURL error 28: Operation timed out"

**Causa:** OpenKM no responde en 30 segundos

**Debug:**
```bash
# Verificar conectividad
curl -v http://localhost:8080/OpenKM/services/rest/repository/getRoot

# Verificar logs de OpenKM
docker logs rund-core

# Verificar recursos
docker stats rund-core
```

---

#### Error: "File upload error code 1"

**Causa:** Archivo excede upload_max_filesize

**Debug:**
```php
// Verificar configuración
$maxUpload = ini_get('upload_max_filesize');
$maxPost = ini_get('post_max_size');

if ($fileSize > $maxUpload) {
    error_log("Archivo $fileSize excede límite $maxUpload");
}
```

---

## Mejores Prácticas

### 1. Jerarquía de Manejo de Errores

```
1. Prevenir errores (validación de entrada)
   ↓
2. Capturar errores específicos (try-catch local)
   ↓
3. Propagar con contexto (throw con mensaje claro)
   ↓
4. Capturar globalmente (try-catch en index.php)
   ↓
5. Registrar en logs (error_log)
   ↓
6. Responder al cliente (JSON con HTTP status adecuado)
```

---

### 2. Validar Antes de Procesar

```php
// ✓ BIEN: Validar primero
public function subir(array $params = []): array
{
    // Validación
    $postData = $this->getPostData();
    if (!$postData || !isset($postData['accion'])) {
        return $this->errorResponse('Parámetro "accion" requerido');
    }

    $files = $this->getFiles();
    if (!isset($files['archivo'])) {
        return $this->errorResponse('Archivo requerido');
    }

    // Procesamiento solo si validación pasa
    $result = FileHandlers::postFile($postData, $files);
    return $this->successResponse(['archivo' => $result]);
}

// ✗ MAL: Procesar sin validar
public function subirMal(array $params = []): array
{
    $result = FileHandlers::postFile($_POST, $_FILES);
    // Exception si $_POST o $_FILES vacíos
    return $this->successResponse(['archivo' => $result]);
}
```

---

### 3. Mensajes de Error Claros

```php
// ✓ BIEN: Específico y accionable
return $this->errorResponse(
    'Parámetro "tipo" es requerido. Valores válidos: certificado, reporte, consulta',
    400
);

// ✗ MAL: Vago
return $this->errorResponse('Error de validación');
```

---

### 4. Separar Logs Públicos de Privados

```php
// ✓ BIEN: Detalles en logs, genérico en respuesta
try {
    $result = OpenKM::consulta($query);
} catch (Exception $e) {
    // Log con detalles completos
    error_log("OpenKM Error en consulta '$query': " . $e->getMessage() .
              " | Stack: " . $e->getTraceAsString());

    // Respuesta genérica al cliente
    return $this->errorResponse('Error al consultar OpenKM', 500);
}

// ✗ MAL: Exponer stack trace al cliente
return $this->errorResponse($e->getMessage() . "\n" . $e->getTraceAsString());
```

---

### 5. Códigos HTTP Consistentes

```php
// ✓ BIEN: Códigos según RFC
if (!isset($params['id'])) {
    return $this->errorResponse('ID requerido', 400);        // Bad Request
}

$item = Service::find($params['id']);
if (!$item) {
    return $this->errorResponse('Item no encontrado', 404);  // Not Found
}

if (!$this->canAccess($item)) {
    return $this->errorResponse('Acceso denegado', 403);     // Forbidden
}

// ✗ MAL: Siempre 400
return $this->errorResponse('Error', 400);
```

---

### 6. Try-Catch Específico

```php
// ✓ BIEN: Capturar errores específicos
try {
    $data = json_decode($input, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new InvalidArgumentException('JSON inválido');
    }
} catch (InvalidArgumentException $e) {
    return $this->errorResponse($e->getMessage(), 400);
} catch (Exception $e) {
    error_log("Error inesperado: " . $e->getMessage());
    return $this->errorResponse('Error interno', 500);
}

// ✗ MAL: Catch genérico
try {
    // ...
} catch (Exception $e) {
    return $this->errorResponse($e->getMessage());
}
```

---

### 7. Timeout Apropiados

```php
// ✓ BIEN: Timeout según operación
class TimeoutManager
{
    public static function setForOperation(string $operation): void
    {
        $timeouts = [
            'quick_query' => 10,
            'file_upload' => 60,
            'pdf_generation' => 120,
            'ocr_extraction' => 180
        ];

        $timeout = $timeouts[$operation] ?? 30;
        set_time_limit($timeout);
    }
}

// Uso:
TimeoutManager::setForOperation('pdf_generation');
$pdf = $generator->create($data);

// ✗ MAL: Sin timeout o muy corto
// Operación pesada con timeout de 30s por default
```

---

### 8. Logging Estructurado

```php
// ✓ BIEN: Log con contexto
Logger::error('Error al generar certificado', [
    'certificado_id' => $id,
    'cedula' => $cedula,
    'error' => $e->getMessage(),
    'user' => $_SESSION['user']['id'] ?? 'anonymous',
    'timestamp' => date('c')
]);

// ✗ MAL: Log sin contexto
error_log('Error en certificado');
```

---

## Checklist de Manejo de Errores

### Para Cada Endpoint

- [ ] Validar parámetros requeridos
- [ ] Validar tipos de datos
- [ ] Manejar archivos con try-catch
- [ ] Usar códigos HTTP correctos
- [ ] Mensajes de error claros
- [ ] Logging de errores importantes
- [ ] No exponer stack traces al cliente
- [ ] Timeout apropiado para operación

### Para Servicios Externos

- [ ] Timeout configurado
- [ ] Manejo de errores de conexión
- [ ] Retry para errores transitorios
- [ ] Fallback si servicio no disponible
- [ ] Logging de errores de integración

### Para Producción

- [ ] display_errors = Off
- [ ] log_errors = On
- [ ] Error log rotación configurada
- [ ] Monitoreo de logs activo
- [ ] Alertas para errores críticos

---

**Última actualización:** 2025-10-20
**Versión del documento:** 1.0
**Autor:** Documentación generada para RUND API v2
