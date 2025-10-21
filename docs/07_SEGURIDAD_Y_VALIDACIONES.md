# 07 - Seguridad y Validaciones

## Tabla de Contenidos
- [Arquitectura de Seguridad](#arquitectura-de-seguridad)
- [Middleware de Seguridad](#middleware-de-seguridad)
- [Validaciones de Input](#validaciones-de-input)
- [Sanitización de Datos](#sanitización-de-datos)
- [Manejo de Archivos](#manejo-de-archivos)
- [Headers de Seguridad](#headers-de-seguridad)
- [Protección contra Inyecciones](#protección-contra-inyecciones)
- [Autenticación y Autorización](#autenticación-y-autorización)
- [Vulnerabilidades Identificadas](#vulnerabilidades-identificadas)
- [Recomendaciones de Seguridad](#recomendaciones-de-seguridad)

---

## Arquitectura de Seguridad

### Capas de Seguridad Actuales

```
┌─────────────────────────────────────────────┐
│         Cliente (Frontend Angular)          │
└──────────────────┬──────────────────────────┘
                   │ HTTP/HTTPS
┌──────────────────▼──────────────────────────┐
│         CorsMiddleware (PERMISIVO)          │  ⚠️ VULNERABLE
├─────────────────────────────────────────────┤
│      ValidationMiddleware (LIMITADO)        │  ⚠️ INCOMPLETO
├─────────────────────────────────────────────┤
│      AuthMiddleware (PLACEHOLDER)           │  ⚠️ NO IMPLEMENTADO
├─────────────────────────────────────────────┤
│         Router & Controllers                │
├─────────────────────────────────────────────┤
│      Validaciones en Controllers            │  ✓ PARCIAL
├─────────────────────────────────────────────┤
│         OpenKM Service Layer                │  ⚠️ CREDENCIALES HARDCODED
└─────────────────────────────────────────────┘
```

---

## Middleware de Seguridad

### 1. AuthMiddleware

**Ubicación:** `/app/src/Middleware/AuthMiddleware.php`

#### Estado Actual: PLACEHOLDER (No Implementado)

```php
/**
 * Middleware de autenticación - ACTUALMENTE PERMITE TODO
 */
public static function authenticate(): callable
{
    return function (string $method, string $path, array $params = []): bool {
        // TODO: Implementar autenticación real
        // Actualmente permite todas las solicitudes
        return true; // ⚠️ SIN PROTECCIÓN
    };
}
```

#### Métodos Disponibles

| Método | Estado | Descripción | Riesgo |
|--------|--------|-------------|--------|
| `authenticate()` | Placeholder | Permite todas las solicitudes | CRÍTICO |
| `requireRole($role)` | Placeholder | No valida roles | ALTO |
| `limitToIPs($ips)` | Implementado | Limita por IP (funcional) | ✓ OK |

#### Ejemplo de Uso de limitToIPs (Único Funcional)

```php
use RUND\Middleware\AuthMiddleware;

// Restringir endpoint administrativo a IPs específicas
$router->delete('/api/v2/archivos/papelera',
    [ArchivosController::class, 'vaciarPapelera'],
    [
        AuthMiddleware::limitToIPs(['192.168.1.100', '172.16.234.52'])
    ]
);
```

**Formato de Respuesta de Rechazo por IP:**
```json
{
    "error": "Acceso denegado desde esta IP",
    "client_ip": "203.0.113.45"
}
```

---

### 2. CorsMiddleware

**Ubicación:** `/app/src/Middleware/CorsMiddleware.php`

#### Configuración Actual: PERMISIVA (Vulnerable)

```php
public static function cors(): void
{
    if (isset($_SERVER['HTTP_ORIGIN'])) {
        // ⚠️ PERMITE CUALQUIER ORIGEN
        header("Access-Control-Allow-Origin: {$_SERVER['HTTP_ORIGIN']}");
        header('Access-Control-Allow-Credentials: true');
        header('Access-Control-Max-Age: 86400');
    }
    if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
        if (isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_METHOD']))
            header("Access-Control-Allow-Methods: GET, POST, OPTIONS, PUT, DELETE");
        if (isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS']))
            header("Access-Control-Allow-Headers: {$_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS']}");
        exit(0);
    }
}
```

#### Análisis de Riesgo

| Aspecto | Configuración Actual | Riesgo | Recomendación |
|---------|---------------------|--------|---------------|
| **Allow-Origin** | `$_SERVER['HTTP_ORIGIN']` (cualquiera) | CRÍTICO | Lista blanca específica |
| **Allow-Credentials** | `true` | ALTO | Solo con orígenes confiables |
| **Allow-Methods** | GET, POST, OPTIONS, PUT, DELETE | MEDIO | Limitar según necesidad |
| **Allow-Headers** | Todos los solicitados | MEDIO | Lista específica |
| **Max-Age** | 86400 (24h) | BAJO | Aceptable |

#### CORS Seguro Recomendado

```php
public static function cors(): void
{
    $allowedOrigins = [
        'http://localhost:4000',           // Desarrollo local
        'https://rund.esap.edu.co',       // Producción
        'https://staging.rund.esap.edu.co' // Staging
    ];

    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';

    if (in_array($origin, $allowedOrigins)) {
        header("Access-Control-Allow-Origin: $origin");
        header('Access-Control-Allow-Credentials: true');
        header('Access-Control-Max-Age: 86400');
    }

    if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
        header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE");
        header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
        exit(0);
    }
}
```

---

### 3. ValidationMiddleware

**Ubicación:** `/app/src/Middleware/ValidationMiddleware.php`

#### Métodos Implementados

##### 3.1. validateHttpMethod

Valida que el método HTTP sea uno de los permitidos.

```php
ValidationMiddleware::validateHttpMethod(['GET', 'POST'])
```

**Respuesta de Error:**
```json
{
    "error": "Método HTTP no permitido",
    "allowed": ["GET", "POST"],
    "received": "PUT"
}
```
**HTTP Status:** `405 Method Not Allowed`

##### 3.2. requireQueryParams

Valida que existan parámetros específicos en la query string.

```php
ValidationMiddleware::requireQueryParams(['nombre', 'tipo'])
```

**Ejemplo de Validación:**
```php
// Request: /api/v2/datos?nombre=test
// Falta 'tipo' → Error 400

GET /api/v2/datos?nombre=test&tipo=xlsx  // ✓ OK
GET /api/v2/datos?nombre=test           // ✗ FALLA
```

**Respuesta de Error:**
```json
{
    "error": "Parámetros de consulta requeridos faltantes",
    "missing": ["tipo"],
    "required": ["nombre", "tipo"]
}
```
**HTTP Status:** `400 Bad Request`

##### 3.3. requireFiles

Valida que existan archivos en `$_FILES` y que no tengan errores de subida.

```php
ValidationMiddleware::requireFiles(['archivo', 'documento'])
```

**Validaciones:**
- Archivo presente en `$_FILES`
- `$_FILES['archivo']['error'] === UPLOAD_ERR_OK`

**Respuesta de Error:**
```json
{
    "error": "Archivos requeridos faltantes o con errores",
    "missing": ["archivo"],
    "required": ["archivo", "documento"]
}
```
**HTTP Status:** `400 Bad Request`

##### 3.4. validateFileSize

Valida el tamaño máximo de archivos subidos (default: 50MB).

```php
ValidationMiddleware::validateFileSize(50 * 1024 * 1024) // 50MB
ValidationMiddleware::validateFileSize(10 * 1024 * 1024) // 10MB
```

**Respuesta de Error:**
```json
{
    "error": "Archivo demasiado grande",
    "file": "documento",
    "size": 52428800,
    "max_allowed": 52428800
}
```
**HTTP Status:** `413 Payload Too Large`

##### 3.5. logRequest

Registra información de las solicitudes en el log de errores de PHP.

```php
ValidationMiddleware::logRequest()
```

**Log generado:**
```
RUND API Request: {
    "timestamp": "2025-10-20 14:30:45",
    "method": "POST",
    "path": "/api/v2/archivos/subir",
    "ip": "192.168.1.100",
    "user_agent": "Mozilla/5.0..."
}
```

#### Uso en Rutas

```php
// Ejemplo: Subida de archivos con validaciones múltiples
$router->post('/api/v2/ai/extraer',
    [AIController::class, 'extraer'],
    [
        ValidationMiddleware::requireFiles(['documento']),
        ValidationMiddleware::validateFileSize(50 * 1024 * 1024),
        ValidationMiddleware::logRequest()
    ]
);
```

---

## Validaciones de Input

### 1. Validaciones en BaseController

**Ubicación:** `/app/src/Controllers/BaseController.php`

#### Método validateRequired

```php
protected function validateRequired(array $data, array $required): array
{
    $missing = [];
    foreach ($required as $field) {
        if (!isset($data[$field]) || empty($data[$field])) {
            $missing[] = $field;
        }
    }

    if (!empty($missing)) {
        http_response_code(400);
        return [
            'error' => 'Parámetros requeridos faltantes: ' . implode(', ', $missing)
        ];
    }

    return []; // Sin errores
}
```

**Uso en Controllers:**
```php
public function generar(array $params = []): array
{
    $postData = $this->getPostData();

    // Validar campos requeridos
    $validation = $this->validateRequired($postData, ['nombre', 'tipo', 'data']);
    if (!empty($validation)) {
        return $validation; // Retorna error 400
    }

    // Continuar con la lógica...
}
```

**Limitaciones:**
- Solo valida presencia y `empty()`
- No valida tipos de datos
- No valida formato (email, fecha, etc.)
- No valida rangos numéricos

---

### 2. Validaciones en Controllers

#### Ejemplos de Validación Manual

**Validación de Cédula (ProfesoresController):**
```php
// Archivo: FileHandlers.php línea 228
if (!preg_match('/^\d{4,20}$/', $cedula)) {
    $salida["error"] = "La cédula debe tener entre 4 y 20 dígitos.";
    break;
}
```

**Validación de UUID (ArchivosController):**
```php
public function show(array $params = []): ?array
{
    if (!isset($params['uuid'])) {
        return $this->errorResponse('UUID es requerido', 400);
    }
    // Continuar...
}
```

**Validación de Parámetros Específicos (DocumentosController):**
```php
if (!isset($postData['tipo'])) {
    return $this->errorResponse('Parámetro "tipo" es requerido', 400);
}

$tipoDocumento = $postData['tipo'];
$tiposValidos = ['certificado', 'reporte', 'consulta'];

if (!in_array($tipoDocumento, $tiposValidos)) {
    return $this->errorResponse(
        "Tipo de documento no soportado: {$tipoDocumento}. Tipos válidos: " .
        implode(', ', $tiposValidos),
        400
    );
}
```

#### Patrón de Validación JSON

```php
// Validación de JSON en POST body
$postData = $this->getPostData();
if (!$postData) {
    return $this->errorResponse('Datos POST requeridos');
}

// Validación de campo JSON específico
if (isset($postData['data'])) {
    $decoded = json_decode($postData['data'], true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        return $this->errorResponse('Datos JSON inválidos en "data"');
    }
}
```

---

### 3. Validaciones Faltantes (Recomendaciones)

#### Tipos de Datos

```php
// ⚠️ NO IMPLEMENTADO - Recomendación
class ValidatorHelper
{
    public static function validateInt(mixed $value, ?int $min = null, ?int $max = null): bool
    {
        if (!is_numeric($value) || intval($value) != $value) {
            return false;
        }

        $intVal = intval($value);
        if ($min !== null && $intVal < $min) return false;
        if ($max !== null && $intVal > $max) return false;

        return true;
    }

    public static function validateEmail(string $email): bool
    {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }

    public static function validateUUID(string $uuid): bool
    {
        return preg_match(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i',
            $uuid
        ) === 1;
    }

    public static function validateDate(string $date, string $format = 'Y-m-d'): bool
    {
        $d = DateTime::createFromFormat($format, $date);
        return $d && $d->format($format) === $date;
    }
}
```

---

## Sanitización de Datos

### 1. HTML Entity Decode

**Ubicación:** Múltiples archivos (FileHandlers, etc.)

```php
// En FileHandlers::loadList() línea 114
$propiedades = json_decode(
    html_entity_decode($params["propiedades"], ENT_QUOTES | ENT_HTML5, 'UTF-8'),
    true
);

// En FileHandlers::postFile() línea 175
$propiedades = json_decode(
    html_entity_decode($params["propiedades"], ENT_QUOTES | ENT_HTML5, 'UTF-8'),
    true
);
```

**Propósito:**
- Decodifica entidades HTML de datos JSON que vienen del frontend
- Asegura que comillas y caracteres especiales se procesen correctamente

**Uso Correcto:**
```php
// Entrada desde Angular:
// propiedades = '{"label":"Nombre","valor":"Juan &amp; Pedro"}'

$decoded = html_entity_decode($json, ENT_QUOTES | ENT_HTML5, 'UTF-8');
// Resultado: {"label":"Nombre","valor":"Juan & Pedro"}
```

---

### 2. URL Encoding

**Ubicación:** OpenKM.php (líneas 100, 118, 132, 192, 201, 219)

```php
// Búsqueda de archivos en OpenKM
$query = "search/find?name=" . urlencode($nombreArchivo) .
         "&path=" . urlencode($path);

// Ejemplos reales:
urlencode("archivo con espacios.pdf")  // archivo+con+espacios.pdf
urlencode("/okm:root/RUND/DOCS/")      // %2Fokm%3Aroot%2FRUND%2FDOCS%2F
```

**Protección:** Evita problemas con caracteres especiales en URLs.

---

### 3. textoAnombreCarpeta (Normalización)

**Ubicación:** `Utils.php` líneas 68-90

```php
public static function textoAnombreCarpeta(string $texto): string
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
```

**Ejemplos:**
```php
textoAnombreCarpeta("Hoja de Vida")           // HOJA_DE_VIDA
textoAnombreCarpeta("Título de Grado")        // TITULO_DE_GRADO
textoAnombreCarpeta("Cédula de Ciudadanía")   // CEDULA_DE_CIUDADANIA
```

**Uso:**
- Normaliza nombres de carpetas en OpenKM
- Normaliza nombres de categorías
- Evita problemas con acentos y espacios en rutas

---

### 4. Sanitización Faltante (Recomendaciones)

#### XSS Protection (No implementada)

```php
// ⚠️ NO IMPLEMENTADO - Recomendación
class Sanitizer
{
    /**
     * Sanitiza entrada HTML para prevenir XSS
     */
    public static function sanitizeHtml(string $input): string
    {
        return htmlspecialchars($input, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    /**
     * Sanitiza para uso en atributos HTML
     */
    public static function sanitizeAttribute(string $input): string
    {
        return htmlspecialchars($input, ENT_QUOTES, 'UTF-8');
    }

    /**
     * Limpia entrada de texto plano (nombres, descripciones)
     */
    public static function sanitizeText(string $input): string
    {
        $cleaned = strip_tags($input);
        $cleaned = trim($cleaned);
        return $cleaned;
    }

    /**
     * Sanitiza nombres de archivo
     */
    public static function sanitizeFilename(string $filename): string
    {
        // Eliminar path traversal
        $filename = basename($filename);

        // Eliminar caracteres peligrosos
        $filename = preg_replace('/[^a-zA-Z0-9._-]/', '_', $filename);

        // Evitar nombres reservados en Windows
        $reserved = ['CON', 'PRN', 'AUX', 'NUL', 'COM1', 'LPT1'];
        $name = pathinfo($filename, PATHINFO_FILENAME);
        if (in_array(strtoupper($name), $reserved)) {
            $filename = '_' . $filename;
        }

        return $filename;
    }
}
```

---

## Manejo de Archivos

### 1. Validación de Uploads

#### Validación de UPLOAD_ERR_OK

**Ubicación:** `ValidationMiddleware::requireFiles()` línea 77

```php
foreach ($requiredFiles as $fileKey) {
    if (!isset($_FILES[$fileKey]) || $_FILES[$fileKey]['error'] !== UPLOAD_ERR_OK) {
        $missing[] = $fileKey;
    }
}
```

**Códigos de Error PHP:**
| Código | Constante | Descripción |
|--------|-----------|-------------|
| 0 | UPLOAD_ERR_OK | Sin errores |
| 1 | UPLOAD_ERR_INI_SIZE | Excede upload_max_filesize |
| 2 | UPLOAD_ERR_FORM_SIZE | Excede MAX_FILE_SIZE del formulario |
| 3 | UPLOAD_ERR_PARTIAL | Subida parcial |
| 4 | UPLOAD_ERR_NO_FILE | No se subió archivo |
| 6 | UPLOAD_ERR_NO_TMP_DIR | Falta carpeta temporal |
| 7 | UPLOAD_ERR_CANT_WRITE | Error al escribir a disco |
| 8 | UPLOAD_ERR_EXTENSION | Extensión PHP detuvo la subida |

**Ejemplo de Uso:**
```php
$router->post('/api/v2/archivos/subir',
    [ArchivosController::class, 'subir'],
    [
        ValidationMiddleware::requireFiles(['archivo'])
    ]
);
```

---

### 2. Límites de Tamaño

#### Configuración Actual

**En Middleware:**
```php
// Default: 50MB
ValidationMiddleware::validateFileSize(50 * 1024 * 1024)

// Uso en rutas:
$router->post('/api/v2/ai/extraer', [AIController::class, 'extraer'], [
    ValidationMiddleware::validateFileSize(50 * 1024 * 1024)
]);
```

**En PHP.ini (Debe Configurarse):**
```ini
; ⚠️ Verificar configuración del servidor
upload_max_filesize = 50M
post_max_size = 52M
memory_limit = 256M
max_execution_time = 300
```

**Verificación en Producción:**
```php
// En SystemController::getInfo()
public function getInfo(array $params = []): array
{
    return [
        'php_info' => [
            'upload_max_filesize' => ini_get('upload_max_filesize'),
            'post_max_size' => ini_get('post_max_size'),
            'memory_limit' => ini_get('memory_limit'),
            'max_execution_time' => ini_get('max_execution_time')
        ]
    ];
}
```

---

### 3. Validación MIME (NO IMPLEMENTADA)

#### ⚠️ VULNERABILIDAD CRÍTICA

**Actualmente NO se valida el tipo MIME de archivos subidos.**

```php
// ⚠️ PROBLEMA ACTUAL en FileHandlers::postFile()
$nombreArchivo = $files["archivo"]["name"];  // Solo nombre
// NO SE VALIDA $files["archivo"]["type"]
// NO SE VALIDA contenido real del archivo
```

#### Implementación Recomendada

```php
class FileValidator
{
    private const ALLOWED_MIMES = [
        'application/pdf',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document', // .docx
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',       // .xlsx
        'image/jpeg',
        'image/png',
        'application/json',
        'text/csv'
    ];

    /**
     * Valida tipo MIME (verificación doble: declarado y real)
     */
    public static function validateMimeType(array $file): bool
    {
        // 1. Validar MIME declarado (no confiable solo)
        if (!in_array($file['type'], self::ALLOWED_MIMES)) {
            return false;
        }

        // 2. Validar MIME real usando fileinfo
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $realMime = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        return in_array($realMime, self::ALLOWED_MIMES);
    }

    /**
     * Valida extensión de archivo
     */
    public static function validateExtension(string $filename): bool
    {
        $allowedExtensions = ['pdf', 'docx', 'xlsx', 'jpg', 'jpeg', 'png', 'json', 'csv'];
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        return in_array($extension, $allowedExtensions);
    }

    /**
     * Validación completa de archivo
     */
    public static function validateFile(array $file): array
    {
        $errors = [];

        // Error de subida
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $errors[] = "Error de subida: código {$file['error']}";
        }

        // Tamaño
        if ($file['size'] > 50 * 1024 * 1024) {
            $errors[] = "Archivo demasiado grande (máx 50MB)";
        }

        if ($file['size'] === 0) {
            $errors[] = "Archivo vacío";
        }

        // Extensión
        if (!self::validateExtension($file['name'])) {
            $errors[] = "Extensión de archivo no permitida";
        }

        // MIME type
        if (!self::validateMimeType($file)) {
            $errors[] = "Tipo de archivo no permitido";
        }

        return $errors;
    }
}

// USO:
$errors = FileValidator::validateFile($_FILES['archivo']);
if (!empty($errors)) {
    return $this->errorResponse(implode(', ', $errors), 400);
}
```

---

### 4. Storage Seguro

#### Directorio Temporal

**Ubicación:** `Config.php` línea 34

```php
const TEMP_DIR = __DIR__ . "/../../tmp/";
```

**Archivos temporales gestionados:**
- `reporte.xlsx` - Reportes Excel temporales
- `reporte.pdf` - Reportes PDF temporales
- Archivos subidos antes de enviar a OpenKM

**Limpieza:**
```php
// FileHandlers::deleteReport()
public static function deleteReport(): array
{
    $aBorrar = ["reporte.xlsx", "reporte.pdf"];
    $borrados = [];
    foreach ($aBorrar as $archivo) {
        if (file_exists(Config::TEMP_DIR . $archivo)) {
            unlink(Config::TEMP_DIR . $archivo);
            $borrados[] = $archivo;
        }
    }
    return ["borrados" => $borrados, "aBorrar" => $aBorrar];
}
```

#### OpenKM Storage

**Credenciales Hardcodeadas (CRÍTICO):**

```php
// Config.php líneas 24-28
const USER = "okmAdmin";      // ⚠️ HARDCODED
const PASSWORD = "admin";     // ⚠️ HARDCODED
```

**Uso en OpenKM.php línea 45-46:**
```php
curl_setopt($curl, CURLOPT_USERNAME, Config::USER);
curl_setopt($curl, CURLOPT_PASSWORD, Config::PASSWORD);
```

**⚠️ VULNERABILIDAD CRÍTICA:** Credenciales en código fuente.

**Solución Recomendada:**
```php
// En Config.php
const USER = $_ENV['OPENKM_USER'] ?? 'okmAdmin';
const PASSWORD = $_ENV['OPENKM_PASSWORD'] ?? 'admin';

// En .env
OPENKM_USER=okmAdmin
OPENKM_PASSWORD=contraseña_segura_aquí

// En docker-compose.yml o .env
OPENKM_USER: ${OPENKM_USER}
OPENKM_PASSWORD: ${OPENKM_PASSWORD}
```

---

## Headers de Seguridad

### 1. Headers Actuales

#### CORS Headers (CorsMiddleware)

```php
Access-Control-Allow-Origin: {cualquier origen}     // ⚠️ VULNERABLE
Access-Control-Allow-Credentials: true              // ⚠️ CON PERMISIVO CORS
Access-Control-Max-Age: 86400
Access-Control-Allow-Methods: GET, POST, OPTIONS, PUT, DELETE
Access-Control-Allow-Headers: {todos los solicitados}  // ⚠️ PERMISIVO
```

#### Content-Type Headers (Router)

```php
// En Router::handleResponse() línea 251
header('Content-Type: application/json; charset=utf-8');

// En OpenKM::getImageFile() línea 229
header("Content-Type: " . $mimeType);

// En FileHandlers::getConsultaFile() línea 56
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
```

---

### 2. Headers de Seguridad Ausentes

#### ⚠️ NO IMPLEMENTADOS (Recomendación)

```php
class SecurityHeaders
{
    /**
     * Aplica todos los headers de seguridad recomendados
     */
    public static function apply(): void
    {
        // Prevenir MIME-sniffing
        header('X-Content-Type-Options: nosniff');

        // Prevenir clickjacking
        header('X-Frame-Options: DENY');
        // O para permitir mismo origen:
        // header('X-Frame-Options: SAMEORIGIN');

        // XSS Protection (legacy, pero útil)
        header('X-XSS-Protection: 1; mode=block');

        // Content Security Policy
        header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self' 'unsafe-inline'; img-src 'self' data:; font-src 'self'; connect-src 'self' https://api.ejemplo.com; frame-ancestors 'none'");

        // Referrer Policy
        header('Referrer-Policy: strict-origin-when-cross-origin');

        // Permissions Policy (antes Feature-Policy)
        header("Permissions-Policy: geolocation=(), microphone=(), camera=()");

        // HTTPS Strict Transport Security (solo en producción con HTTPS)
        if ($_SERVER['HTTPS'] ?? '' === 'on') {
            header('Strict-Transport-Security: max-age=31536000; includeSubDomains; preload');
        }
    }
}

// Uso en index.php o en middleware:
SecurityHeaders::apply();
```

#### CSP (Content Security Policy) Recomendado para RUND

```http
Content-Security-Policy:
    default-src 'self';
    script-src 'self' 'unsafe-inline' 'unsafe-eval';
    style-src 'self' 'unsafe-inline';
    img-src 'self' data: blob: https:;
    font-src 'self' data:;
    connect-src 'self' http://localhost:8080 http://172.16.234.52:8080 http://localhost:11434 http://localhost:8000;
    frame-ancestors 'self';
    base-uri 'self';
    form-action 'self'
```

---

## Protección contra Inyecciones

### 1. SQL Injection: N/A

**RUND no usa bases de datos SQL directamente.**

- Datos en OpenKM (repositorio documental)
- Datos en archivos JSON en OpenKM
- No hay consultas SQL en el código

**Estado:** ✓ No aplica vulnerabilidad SQL Injection.

---

### 2. Command Injection: SEGURO

**Búsqueda en código:**
```bash
# Buscar funciones peligrosas:
grep -r "exec\|shell_exec\|system\|passthru\|popen\|proc_open" app/src/
# Resultado: No se encontraron
```

**Estado:** ✓ No se usan funciones de ejecución de comandos.

---

### 3. Path Traversal: PROTECCIÓN BÁSICA

#### Protecciones Existentes

**1. basename() en Nombres de Archivo:**
```php
// NO IMPLEMENTADO actualmente, pero recomendado:
$filename = basename($_FILES['archivo']['name']);
```

**2. Rutas Fijas en Config:**
```php
// Config.php
const TAX_HOJAS = self::ROOT_TAX_PROF . "HOJAS_DE_VIDA/";
const TAX_FIRMAS = self::ROOT_TAX_DOCS . "FIRMAS/";
```

**3. Normalización de Rutas:**
```php
// Utils::textoAnombreCarpeta() sanitiza nombres
$tipo = Utils::textoAnombreCarpeta($tipoDocumento);
$path = Config::TAX_LISTADOS . $tipo;
```

#### ⚠️ Vulnerabilidad Potencial

**En ArchivosController::getImagen() línea 154:**
```php
if ($rutaEspecifica) {
    // ⚠️ Usuario puede pasar ruta arbitraria
    $rutaCompleta = Config::ROOT_TAX_DOCS .
                    strtoupper(str_replace(' ', '_', $rutaEspecifica)) . '/';
    OpenKM::getImageFile($nombre, $rutaCompleta);
}
```

**Exploit Potencial:**
```http
GET /api/v2/archivos/imagenes/secreto.png?ruta=../../../../../../okm:root/ADMIN
```

#### Protección Recomendada

```php
class PathValidator
{
    /**
     * Valida que la ruta no contenga path traversal
     */
    public static function validatePath(string $path): bool
    {
        // Detectar ../ o ..\
        if (preg_match('/\.\.[\\/]/', $path)) {
            return false;
        }

        // Detectar null bytes
        if (strpos($path, "\0") !== false) {
            return false;
        }

        // Normalizar y comparar
        $normalized = realpath($path);
        if ($normalized === false) {
            return false;
        }

        return true;
    }

    /**
     * Valida que la ruta esté dentro de un directorio permitido
     */
    public static function isPathAllowed(string $path, string $allowedBase): bool
    {
        $realPath = realpath($path);
        $realBase = realpath($allowedBase);

        if ($realPath === false || $realBase === false) {
            return false;
        }

        return strpos($realPath, $realBase) === 0;
    }

    /**
     * Sanitiza ruta de usuario para OpenKM
     */
    public static function sanitizeOkmPath(string $userPath): string
    {
        // Eliminar ../ y caracteres peligrosos
        $clean = str_replace(['../', '.\\', "\0"], '', $userPath);

        // Solo permitir alfanuméricos, guiones y barras
        $clean = preg_replace('/[^a-zA-Z0-9\/_-]/', '', $clean);

        return $clean;
    }
}

// USO en ArchivosController:
if ($rutaEspecifica) {
    $rutaSanitizada = PathValidator::sanitizeOkmPath($rutaEspecifica);
    $rutaCompleta = Config::ROOT_TAX_DOCS . strtoupper($rutaSanitizada) . '/';
    OpenKM::getImageFile($nombre, $rutaCompleta);
}
```

---

### 4. JSON Injection: VALIDACIÓN BÁSICA

#### Validación Actual

```php
// En BaseController::getPostData() línea 33
$decoded = json_decode($input, true);
return json_last_error() === JSON_ERROR_NONE ? $decoded : null;
```

**Protección:**
- Valida que sea JSON válido
- Retorna `null` si hay error de parseo

**Ejemplo de Uso:**
```php
$postData = $this->getPostData();
if (!$postData) {
    return $this->errorResponse('Datos POST requeridos');
}
```

#### Mejora Recomendada

```php
class JsonValidator
{
    /**
     * Valida y decodifica JSON con límite de profundidad
     */
    public static function decode(string $json, int $maxDepth = 512): ?array
    {
        $decoded = json_decode($json, true, $maxDepth);

        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log('JSON decode error: ' . json_last_error_msg());
            return null;
        }

        return $decoded;
    }

    /**
     * Valida estructura de JSON contra schema
     */
    public static function validateSchema(array $data, array $schema): array
    {
        $errors = [];

        foreach ($schema as $field => $rules) {
            // Verificar campo requerido
            if ($rules['required'] && !isset($data[$field])) {
                $errors[] = "Campo requerido: $field";
                continue;
            }

            if (!isset($data[$field])) {
                continue;
            }

            // Verificar tipo
            if (isset($rules['type'])) {
                $type = gettype($data[$field]);
                if ($type !== $rules['type']) {
                    $errors[] = "Campo $field debe ser {$rules['type']}, es $type";
                }
            }

            // Verificar longitud
            if (isset($rules['maxLength']) && is_string($data[$field])) {
                if (strlen($data[$field]) > $rules['maxLength']) {
                    $errors[] = "Campo $field excede longitud máxima de {$rules['maxLength']}";
                }
            }
        }

        return $errors;
    }
}

// USO:
$schema = [
    'nombre' => ['required' => true, 'type' => 'string', 'maxLength' => 100],
    'cedula' => ['required' => true, 'type' => 'string', 'maxLength' => 20],
    'edad' => ['required' => false, 'type' => 'integer']
];

$errors = JsonValidator::validateSchema($postData, $schema);
if (!empty($errors)) {
    return $this->errorResponse(implode(', ', $errors), 400);
}
```

---

## Autenticación y Autorización

### Estado Actual: NO IMPLEMENTADO

**AuthMiddleware es un placeholder completo.**

```php
// AuthMiddleware::authenticate() - línea 25
public static function authenticate(): callable
{
    return function (string $method, string $path, array $params = []): bool {
        // TODO: Implementar autenticación real
        return true;  // ⚠️ PERMITE TODO
    };
}
```

### Recomendaciones de Implementación

#### 1. JWT (JSON Web Tokens)

**Ventajas para RUND:**
- Stateless (no requiere sesiones en servidor)
- Funciona bien con Angular SPA
- Fácil de implementar con `firebase/php-jwt`

**Implementación Recomendada:**

```php
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class JwtAuth
{
    private const SECRET_KEY = ''; // Cargar desde .env
    private const ALGORITHM = 'HS256';
    private const TOKEN_EXPIRATION = 3600; // 1 hora

    /**
     * Genera un token JWT
     */
    public static function generateToken(array $payload): string
    {
        $issuedAt = time();
        $expire = $issuedAt + self::TOKEN_EXPIRATION;

        $tokenPayload = [
            'iat' => $issuedAt,
            'exp' => $expire,
            'data' => $payload
        ];

        return JWT::encode($tokenPayload, self::SECRET_KEY, self::ALGORITHM);
    }

    /**
     * Valida y decodifica un token JWT
     */
    public static function validateToken(string $token): ?array
    {
        try {
            $decoded = JWT::decode($token, new Key(self::SECRET_KEY, self::ALGORITHM));
            return (array) $decoded->data;
        } catch (\Exception $e) {
            error_log('JWT validation error: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Extrae token del header Authorization
     */
    public static function extractToken(): ?string
    {
        $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';

        if (empty($authHeader)) {
            return null;
        }

        // Formato: "Bearer TOKEN_AQUI"
        if (preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
            return $matches[1];
        }

        return null;
    }
}

// AuthMiddleware actualizado:
public static function authenticate(): callable
{
    return function (string $method, string $path, array $params = []): bool {
        $token = JwtAuth::extractToken();

        if (!$token) {
            http_response_code(401);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'error' => 'Token de autenticación requerido',
                'message' => 'Incluya el token en el header: Authorization: Bearer TOKEN'
            ]);
            return false;
        }

        $userData = JwtAuth::validateToken($token);

        if (!$userData) {
            http_response_code(401);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'error' => 'Token inválido o expirado'
            ]);
            return false;
        }

        // Guardar datos de usuario para uso en controllers
        $_SESSION['user'] = $userData;

        return true;
    };
}
```

**Ejemplo de Login Endpoint:**

```php
// AuthController (NUEVO)
class AuthController extends BaseController
{
    public function login(array $params = []): array
    {
        $postData = $this->getPostData();

        $validation = $this->validateRequired($postData, ['username', 'password']);
        if (!empty($validation)) {
            return $validation;
        }

        // Validar contra sistema de autenticación (LDAP, BD, etc.)
        $user = $this->validateCredentials($postData['username'], $postData['password']);

        if (!$user) {
            return $this->errorResponse('Credenciales inválidas', 401);
        }

        $token = JwtAuth::generateToken([
            'id' => $user['id'],
            'username' => $user['username'],
            'role' => $user['role']
        ]);

        return $this->successResponse([
            'token' => $token,
            'expires_in' => 3600,
            'user' => [
                'id' => $user['id'],
                'username' => $user['username'],
                'role' => $user['role']
            ]
        ]);
    }

    private function validateCredentials(string $username, string $password): ?array
    {
        // TODO: Implementar validación real
        // Opciones:
        // 1. LDAP de ESAP
        // 2. Base de datos de usuarios
        // 3. OAuth2 con proveedor externo

        return null;
    }
}
```

#### 2. OAuth2

**Ventajas:**
- Delegación de autenticación a proveedor externo
- Estándar de la industria
- Soporta SSO (Single Sign-On)

**Proveedores Recomendados:**
- Microsoft Azure AD (si ESAP usa Office 365)
- Google Workspace
- Keycloak (self-hosted)

**Flujo OAuth2:**
```
1. Usuario → Frontend: Click "Login"
2. Frontend → OAuth Provider: Redirect a login
3. Usuario → OAuth Provider: Ingresa credenciales
4. OAuth Provider → Frontend: Redirect con authorization code
5. Frontend → RUND API: POST /auth/callback con code
6. RUND API → OAuth Provider: Intercambia code por access token
7. RUND API → Frontend: Retorna JWT propio con datos de usuario
8. Frontend: Guarda JWT, incluye en headers subsecuentes
```

#### 3. Roles y Permisos

**Roles Recomendados para RUND:**

```php
enum UserRole: string {
    case ADMIN = 'admin';
    case DOCENTE = 'docente';
    case COORDINADOR = 'coordinador';
    case READONLY = 'readonly';
}

class RolePermissions
{
    private const PERMISSIONS = [
        'admin' => [
            'certificados:read',
            'certificados:create',
            'certificados:delete',
            'profesores:read',
            'profesores:write',
            'archivos:read',
            'archivos:write',
            'archivos:delete',
            'system:admin'
        ],
        'coordinador' => [
            'certificados:read',
            'certificados:create',
            'profesores:read',
            'archivos:read',
            'archivos:write'
        ],
        'docente' => [
            'certificados:read',
            'profesores:read-own',
            'archivos:read-own'
        ],
        'readonly' => [
            'certificados:read',
            'profesores:read'
        ]
    ];

    public static function hasPermission(string $role, string $permission): bool
    {
        return in_array($permission, self::PERMISSIONS[$role] ?? []);
    }
}

// AuthMiddleware::requirePermission()
public static function requirePermission(string $permission): callable
{
    return function (string $method, string $path, array $params = []) use ($permission): bool {
        $user = $_SESSION['user'] ?? null;

        if (!$user || !isset($user['role'])) {
            http_response_code(401);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['error' => 'No autenticado']);
            return false;
        }

        if (!RolePermissions::hasPermission($user['role'], $permission)) {
            http_response_code(403);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'error' => 'Permisos insuficientes',
                'required_permission' => $permission,
                'user_role' => $user['role']
            ]);
            return false;
        }

        return true;
    };
}

// Uso en rutas:
$router->delete('/api/v2/archivos/papelera',
    [ArchivosController::class, 'vaciarPapelera'],
    [
        AuthMiddleware::authenticate(),
        AuthMiddleware::requirePermission('system:admin')
    ]
);
```

---

## Vulnerabilidades Identificadas

### Tabla Resumen de Vulnerabilidades

| ID | Severidad | Vulnerabilidad | Ubicación | Impacto | Estado |
|----|-----------|----------------|-----------|---------|--------|
| V1 | CRÍTICA | CORS Permisivo | CorsMiddleware | CSRF, robo de datos | ⚠️ ACTIVO |
| V2 | CRÍTICA | Sin Autenticación | AuthMiddleware | Acceso no autorizado | ⚠️ ACTIVO |
| V3 | CRÍTICA | Credenciales Hardcoded | Config.php | Exposición de credenciales | ⚠️ ACTIVO |
| V4 | CRÍTICA | Sin Validación MIME | FileHandlers | Subida de malware | ⚠️ ACTIVO |
| V5 | ALTA | display_errors Activo | php.ini | Exposición de información | ⚠️ VERIFICAR |
| V6 | ALTA | Sin Rate Limiting | Global | DoS, brute force | ⚠️ ACTIVO |
| V7 | ALTA | Path Traversal Potencial | ArchivosController | Acceso a archivos no autorizados | ⚠️ ACTIVO |
| V8 | MEDIA | Sin Headers de Seguridad | Global | XSS, clickjacking | ⚠️ ACTIVO |
| V9 | MEDIA | Sin Validación de Tipos | Controllers | Errores, exploits | ⚠️ ACTIVO |
| V10 | BAJA | Log Insuficiente | Global | Dificulta auditoría | ⚠️ ACTIVO |

---

### Detalle de Vulnerabilidades

#### V1 - CORS Permisivo (CRÍTICA)

**Descripción:**
```php
// Utils::cors() línea 26
header("Access-Control-Allow-Origin: {$_SERVER['HTTP_ORIGIN']}");
```

**Impacto:**
- Cualquier sitio web puede hacer peticiones a la API
- Posible robo de sesión si se implementa autenticación
- CSRF (Cross-Site Request Forgery)

**Exploit:**
```html
<!-- Sitio malicioso: https://evil.com/attack.html -->
<script>
fetch('http://rund.esap.edu.co/api/v2/archivos/papelera', {
    method: 'DELETE',
    credentials: 'include' // Incluye cookies
})
.then(r => console.log('Papelera vaciada desde sitio externo'));
</script>
```

**Solución:**
```php
// Lista blanca de orígenes permitidos
$allowedOrigins = [
    'http://localhost:4000',
    'https://rund.esap.edu.co',
    'https://staging.rund.esap.edu.co'
];

$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if (in_array($origin, $allowedOrigins)) {
    header("Access-Control-Allow-Origin: $origin");
    header('Access-Control-Allow-Credentials: true');
}
```

---

#### V2 - Sin Autenticación (CRÍTICA)

**Descripción:**
AuthMiddleware no implementado, todos los endpoints accesibles sin autenticación.

**Impacto:**
- Acceso público a toda la API
- Modificación/borrado de datos por usuarios no autorizados
- Imposibilidad de auditoría

**Endpoints Sensibles Sin Protección:**
```php
DELETE /api/v2/archivos/papelera       // Vacía papelera completa
DELETE /api/v2/archivos/{uuid}         // Borra cualquier archivo
POST /api/v2/archivos/subir            // Sube archivos
POST /api/v2/certificados/generar      // Genera certificados
```

**Solución:**
Implementar JWT según [sección Autenticación](#autenticación-y-autorización).

---

#### V3 - Credenciales Hardcoded (CRÍTICA)

**Descripción:**
```php
// Config.php líneas 25-28
const USER = "okmAdmin";
const PASSWORD = "admin";
```

**Impacto:**
- Credenciales en repositorio Git
- Imposibilidad de rotación de credenciales
- Mismo password en desarrollo y producción

**Solución:**
```php
// Config.php
const USER = $_ENV['OPENKM_USER'] ?? 'okmAdmin';
const PASSWORD = $_ENV['OPENKM_PASSWORD'] ?? '';

// .env (no committed)
OPENKM_USER=okmAdmin
OPENKM_PASSWORD=P@ssw0rd_S3cur0

// .gitignore
.env
.env.local
.env.*.local
```

---

#### V4 - Sin Validación MIME (CRÍTICA)

**Descripción:**
No se valida el tipo MIME real de archivos subidos.

**Impacto:**
- Subida de archivos ejecutables (.exe, .sh)
- Subida de scripts maliciosos (.php, .js)
- Posible ejecución de código

**Exploit:**
```bash
# Crear archivo malicioso con extensión válida
echo '<?php system($_GET["cmd"]); ?>' > malware.pdf

# Subir a la API
curl -F 'archivo=@malware.pdf' \
     -F 'accion=cargaDocumento' \
     -F 'propiedades={...}' \
     http://rund.esap.edu.co/api/v2/archivos/subir
```

**Solución:**
Ver [sección Validación MIME](#3-validación-mime-no-implementada).

---

#### V5 - display_errors Activo (ALTA)

**Descripción:**
```bash
php -i | grep display_errors
display_errors => STDOUT => STDOUT
```

**Impacto:**
- Exposición de rutas del servidor
- Exposición de estructura de base de datos
- Ayuda a atacantes a mapear la aplicación

**Ejemplo de Exposición:**
```
Fatal error: Uncaught Exception: LDAP connection failed
in /var/www/rund-api/app/src/Auth/LdapAuth.php:45
Stack trace:
#0 /var/www/rund-api/app/src/Controllers/AuthController.php(23): ...
```

**Solución:**
```ini
; php.ini - PRODUCCIÓN
display_errors = Off
display_startup_errors = Off
error_reporting = E_ALL
log_errors = On
error_log = /var/log/php/error.log
```

---

#### V6 - Sin Rate Limiting (ALTA)

**Descripción:**
No hay límite de peticiones por IP/usuario.

**Impacto:**
- Ataques de fuerza bruta (cuando se implemente login)
- DoS (Denial of Service)
- Abuso de recursos (OCR/AI endpoints)

**Exploit:**
```bash
# Spam al endpoint de generación de certificados
for i in {1..10000}; do
    curl -X POST http://rund.esap.edu.co/api/v2/certificados/generar \
         -d '{"cedula":"123456"}' &
done
```

**Solución:**
```php
class RateLimiter
{
    private const MAX_REQUESTS = 100; // Por ventana
    private const TIME_WINDOW = 60; // Segundos

    public static function check(string $identifier): bool
    {
        $cacheKey = "rate_limit:$identifier";
        $requests = apcu_fetch($cacheKey) ?: 0;

        if ($requests >= self::MAX_REQUESTS) {
            return false;
        }

        apcu_store($cacheKey, $requests + 1, self::TIME_WINDOW);
        return true;
    }

    public static function middleware(): callable
    {
        return function (string $method, string $path, array $params = []): bool {
            $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

            if (!self::check($ip)) {
                http_response_code(429);
                header('Content-Type: application/json; charset=utf-8');
                header('Retry-After: 60');
                echo json_encode([
                    'error' => 'Demasiadas solicitudes',
                    'message' => 'Límite de ' . self::MAX_REQUESTS . ' peticiones por minuto excedido',
                    'retry_after' => 60
                ]);
                return false;
            }

            return true;
        };
    }
}

// Uso en rutas:
$router->addGlobalMiddleware(RateLimiter::middleware());
```

---

#### V7 - Path Traversal Potencial (ALTA)

Ver [sección Path Traversal](#3-path-traversal-protección-básica).

---

#### V8 - Sin Headers de Seguridad (MEDIA)

Ver [sección Headers de Seguridad Ausentes](#2-headers-de-seguridad-ausentes).

---

#### V9 - Sin Validación de Tipos (MEDIA)

**Descripción:**
No se validan tipos de datos en controllers (solo presencia).

**Ejemplo Vulnerable:**
```php
// Espera número, recibe string
$edad = $postData['edad']; // "treinta" en vez de 30
$resultado = $edad + 10;   // Error o comportamiento inesperado
```

**Solución:**
Ver [sección Validaciones Faltantes](#3-validaciones-faltantes-recomendaciones).

---

#### V10 - Log Insuficiente (BAJA)

**Descripción:**
Solo se registran errores PHP, no eventos de aplicación.

**Eventos Sin Registrar:**
- Intentos de acceso fallidos
- Modificación de archivos
- Generación de certificados
- Borrado de archivos

**Solución:**
```php
class AuditLogger
{
    public static function log(string $action, array $details): void
    {
        $logEntry = [
            'timestamp' => date('c'),
            'action' => $action,
            'user' => $_SESSION['user']['id'] ?? 'anonymous',
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'details' => $details
        ];

        error_log('AUDIT: ' . json_encode($logEntry));
    }
}

// Uso:
AuditLogger::log('archivo_borrado', [
    'uuid' => $uuid,
    'nombre' => $filename
]);
```

---

## Recomendaciones de Seguridad

### Para Desarrollo

#### 1. Configuración PHP Desarrollo

```ini
; php.ini - DESARROLLO
display_errors = On              ; Ver errores en pantalla
display_startup_errors = On      ; Ver errores de inicio
error_reporting = E_ALL          ; Reportar todo
log_errors = On                  ; Guardar log también
error_log = /var/log/php/dev-error.log
```

#### 2. Configuración CORS Desarrollo

```php
// Permitir localhost en múltiples puertos
$allowedOrigins = [
    'http://localhost:4000',
    'http://localhost:4200',
    'http://127.0.0.1:4000',
    'http://127.0.0.1:4200'
];
```

#### 3. Mocks de Autenticación

```php
// AuthMiddleware - modo desarrollo
public static function authenticate(): callable
{
    return function (string $method, string $path, array $params = []): bool {
        if ($_ENV['APP_ENV'] === 'development') {
            // Mock user en desarrollo
            $_SESSION['user'] = [
                'id' => 'dev_user_1',
                'username' => 'developer',
                'role' => 'admin'
            ];
            return true;
        }

        // Autenticación real en producción
        // ...
    };
}
```

---

### Para Staging

#### 1. Configuración PHP Staging

```ini
; php.ini - STAGING
display_errors = Off             ; No mostrar errores
display_startup_errors = Off
error_reporting = E_ALL
log_errors = On
error_log = /var/log/php/staging-error.log

; Límites más estrictos
upload_max_filesize = 50M
post_max_size = 52M
memory_limit = 256M
max_execution_time = 120
```

#### 2. CORS Staging

```php
$allowedOrigins = [
    'https://staging.rund.esap.edu.co',
    'http://localhost:4000' // Para pruebas locales contra staging
];
```

#### 3. Autenticación Staging

- Implementar JWT básico
- Usar credenciales de prueba
- Habilitar logging detallado

---

### Para Producción (Checklist)

#### Seguridad Crítica

- [ ] **Deshabilitar display_errors**
  ```ini
  display_errors = Off
  display_startup_errors = Off
  ```

- [ ] **CORS Restrictivo**
  ```php
  $allowedOrigins = ['https://rund.esap.edu.co'];
  ```

- [ ] **Credenciales en Variables de Entorno**
  ```php
  const USER = $_ENV['OPENKM_USER'];
  const PASSWORD = $_ENV['OPENKM_PASSWORD'];
  ```

- [ ] **Implementar Autenticación**
  - JWT con expiración
  - Refresh tokens
  - Blacklist de tokens

- [ ] **Validación MIME de Archivos**
  ```php
  FileValidator::validateMimeType($file);
  ```

#### Headers de Seguridad

- [ ] **X-Content-Type-Options**
  ```php
  header('X-Content-Type-Options: nosniff');
  ```

- [ ] **X-Frame-Options**
  ```php
  header('X-Frame-Options: DENY');
  ```

- [ ] **Content-Security-Policy**
  ```php
  header("Content-Security-Policy: default-src 'self'; ...");
  ```

- [ ] **Strict-Transport-Security (si HTTPS)**
  ```php
  header('Strict-Transport-Security: max-age=31536000');
  ```

#### Rate Limiting

- [ ] **Límite Global**
  - 100 req/min por IP

- [ ] **Límites Específicos**
  - Login: 5 intentos/min
  - Upload: 20 archivos/hora
  - AI/OCR: 10 solicitudes/hora

#### Logging y Monitoreo

- [ ] **Log de Auditoría**
  - Accesos
  - Modificaciones
  - Errores

- [ ] **Alertas**
  - Intentos fallidos múltiples
  - Archivos sospechosos
  - Errores 500 frecuentes

#### Validaciones

- [ ] **Input Validation**
  - Tipos de datos
  - Rangos numéricos
  - Formatos (email, fecha, etc.)

- [ ] **Output Encoding**
  - JSON escapado
  - HTML sanitizado

- [ ] **Path Traversal Protection**
  ```php
  PathValidator::sanitizeOkmPath($userPath);
  ```

#### Backups y Recuperación

- [ ] **Backups Regulares**
  - OpenKM diario
  - Configuración semanal

- [ ] **Plan de Recuperación**
  - Procedimiento documentado
  - Pruebas de restauración

#### SSL/TLS

- [ ] **Certificado SSL Válido**
  - Let's Encrypt o comercial
  - Renovación automática

- [ ] **Configuración Segura**
  - TLS 1.2+
  - Ciphers seguros

#### Firewall y Red

- [ ] **Firewall Configurado**
  - Solo puertos necesarios abiertos
  - Whitelist de IPs para admin

- [ ] **Aislamiento de Servicios**
  - Red interna para servicios
  - Proxy reverso (nginx)

---

### Resumen de Prioridades

#### Crítico (Implementar Inmediatamente)

1. Mover credenciales a variables de entorno
2. Implementar CORS restrictivo
3. Validación MIME de archivos
4. Deshabilitar display_errors en producción

#### Alto (Implementar Antes de Producción)

5. Sistema de autenticación JWT
6. Rate limiting básico
7. Headers de seguridad
8. Path traversal protection

#### Medio (Implementar en Siguiente Sprint)

9. Validación de tipos de datos
10. Sistema de logging completo
11. Auditoría de eventos
12. Monitoring y alertas

#### Bajo (Mejora Continua)

13. Pruebas de penetración
14. Análisis de vulnerabilidades automatizado
15. Documentación de seguridad para usuarios
16. Plan de respuesta a incidentes

---

## Recursos Adicionales

### Herramientas de Seguridad

- **OWASP ZAP** - Escáner de vulnerabilidades
- **PHPStan** - Análisis estático de código
- **Psalm** - Análisis estático avanzado
- **SonarQube** - Análisis de calidad y seguridad

### Referencias

- [OWASP Top 10](https://owasp.org/www-project-top-ten/)
- [PHP Security Cheat Sheet](https://cheatsheetseries.owasp.org/cheatsheets/PHP_Configuration_Cheat_Sheet.html)
- [JWT Best Practices](https://tools.ietf.org/html/rfc8725)
- [CORS Best Practices](https://developer.mozilla.org/en-US/docs/Web/HTTP/CORS)

---

**Última actualización:** 2025-10-20
**Versión del documento:** 1.0
**Autor:** Documentación generada para RUND API v2
