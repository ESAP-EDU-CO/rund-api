# 10 - Integración con Servicios Externos

## Tabla de Contenidos
1. [Servicios Externos del Ecosistema RUND](#servicios-externos-del-ecosistema-rund)
2. [rund-ai (Ollama) - Servicio de Inteligencia Artificial](#rund-ai-ollama---servicio-de-inteligencia-artificial)
3. [rund-ocr (PaddleOCR) - Servicio de Reconocimiento Óptico](#rund-ocr-paddleocr---servicio-de-reconocimiento-óptico)
4. [LibreOffice Headless - Conversión de Documentos](#libreoffice-headless---conversión-de-documentos)
5. [Pipeline AI Completo](#pipeline-ai-completo)

---

## Servicios Externos del Ecosistema RUND

El sistema RUND API se integra con tres servicios externos principales:

| Servicio | Tecnología | Puerto | URL Interna | Propósito |
|----------|-----------|--------|-------------|-----------|
| **rund-ai** | Ollama + Mistral | 11434 | http://rund-ai:11434 | Procesamiento de lenguaje natural y extracción de datos |
| **rund-ocr** | PaddleOCR | 8000 | http://rund-ocr:8000 | Extracción de texto de imágenes y PDFs |
| **LibreOffice** | LibreOffice 25.8+ | - | Local | Conversión de documentos Office a PDF |

### Comunicación entre Servicios

```
┌─────────────┐
│  rund-mgp   │ (Frontend Angular SSR)
│  Port: 4000 │
└──────┬──────┘
       │ HTTP
       ▼
┌─────────────┐
│  rund-api   │ (Backend PHP)
│  Port: 3000 │
└──────┬──────┘
       │
       ├─────────────► rund-core (OpenKM)  :8080
       │
       ├─────────────► rund-ai (Ollama)    :11434
       │
       ├─────────────► rund-ocr (PaddleOCR):8000
       │
       └─────────────► LibreOffice (local)
```

Todos los servicios están conectados a través de la red Docker `rund-network`.

---

## rund-ai (Ollama) - Servicio de Inteligencia Artificial

### Información General

**Servicio**: Ollama con modelo Mistral
**URL Base**: `http://rund-ai:11434`
**Variable de Entorno**: `AI_API_URL=http://rund-ai:11434`
**Modelo Utilizado**: `mistral` (anteriormente `phi3:mini`)
**Timeout**: 480 segundos (8 minutos)

### Endpoints Disponibles

| Endpoint | Método | Propósito |
|----------|--------|-----------|
| `/api/generate` | POST | Generar texto con IA |
| `/api/tags` | GET | Listar modelos disponibles |
| `/api/pull` | POST | Descargar nuevo modelo |
| `/api/delete` | DELETE | Eliminar modelo |

### AIService.php - Servicio Completo

**Ubicación**: `/app/src/Services/AIService.php`

El servicio AIService proporciona 11 métodos estáticos para integración con rund-ai.

#### Métodos del AIService

##### 1. analizaDocumento()

Analiza un documento completo usando OCR + AI.

```php
public static function analizaDocumento(
  string $filePath,
  string $tipoDocumento,
  array $datosExtraer
): array
```

**Parámetros**:
- `$filePath` (string): Ruta del archivo a procesar
- `$tipoDocumento` (string): Tipo de documento (ver tipos soportados)
- `$datosExtraer` (array): Estructura de datos a extraer

**Retorno**: Array con resultados de OCR y AI

**Flujo**:
1. Extrae texto con OCR
2. Elimina archivo temporal
3. Construye payload para AI
4. Envía a rund-ai
5. Procesa respuesta
6. Retorna datos extraídos

**Ejemplo de uso**:
```php
use RUND\Services\AIService;

$resultado = AIService::analizaDocumento(
  '/tmp/documento.pdf',
  'documento_identidad',
  [
    'nombres' => null,
    'apellidos' => null,
    'numero_documento' => null,
    'fecha_nacimiento' => null
  ]
);

// Resultado:
// [
//   'success' => true,
//   'data' => [
//     'status' => 'success',
//     'tipo_documento' => 'documento_identidad',
//     'datos_extraidos' => [
//       'nombres' => 'JUAN CARLOS',
//       'apellidos' => 'PÉREZ GARCÍA',
//       'numero_documento' => '12345678',
//       'fecha_nacimiento' => '15/03/1985'
//     ],
//     'confianza' => 'alta',
//     'observaciones' => ''
//   ],
//   'ocr_result' => [...]
// ]
```

##### 2. extraerTextoDeDocumento()

Extrae texto de un documento usando OCR.

```php
public static function extraerTextoDeDocumento(string $filePath): array
```

**Parámetros**:
- `$filePath` (string): Ruta del archivo

**Retorno**: Array con texto extraído

**Detalles técnicos**:
- URL: `$_ENV['OCR_API_URL'] . '/extract-text'`
- Método: POST multipart/form-data
- Timeout: 60 segundos
- Acepta: application/json

**Ejemplo de uso**:
```php
$resultado = AIService::extraerTextoDeDocumento('/tmp/cedula.pdf');

// Resultado:
// [
//   'text' => 'REPÚBLICA DE COLOMBIA\nCÉDULA DE CIUDADANÍA...',
//   'page_count' => 1,
//   'processing_time' => 2.3
// ]
```

**Manejo de errores**:
```php
if (isset($resultado['error'])) {
  error_log("Error OCR: " . $resultado['error']);
}
```

##### 3. requestAI()

Realiza petición a rund-ai con manejo robusto de errores.

```php
public static function requestAI(array $aiPayload): array
```

**Parámetros**:
- `$aiPayload` (array): Payload completo para Ollama

**Retorno**: Array con respuesta procesada

**Validaciones**:
1. Verifica que el payload sea array
2. Valida campos requeridos: `model`, `prompt`
3. Verifica encoding JSON
4. Valida timeout
5. Maneja errores de cURL

**Estructura de respuesta exitosa**:
```php
[
  'success' => true,
  'data' => [...],  // Respuesta de Ollama
  'http_code' => 200
]
```

**Estructura de respuesta con error**:
```php
[
  'success' => false,
  'error' => 'Error HTTP 500',
  'message' => 'Internal Server Error',
  'http_code' => 500
]
```

**Detalles técnicos**:
```php
// Configuración cURL
$curlOptions = [
  CURLOPT_URL => $aiUrl,
  CURLOPT_RETURNTRANSFER => true,
  CURLOPT_POST => true,
  CURLOPT_TIMEOUT => 480,          // 8 minutos
  CURLOPT_CONNECTTIMEOUT => 30,    // 30 segundos para conectar
  CURLOPT_POSTFIELDS => $jsonPayload,
  CURLOPT_HTTPHEADER => [
    'Content-Type: application/json',
    'Content-Length: ' . strlen($jsonPayload),
    'Accept: application/json'
  ],
  CURLOPT_SSL_VERIFYPEER => false,
  CURLOPT_FOLLOWLOCATION => true,
  CURLOPT_MAXREDIRS => 3
];
```

**Ejemplo de uso**:
```php
$payload = AIService::construyeAiPayload(
  'documento_identidad',
  ['nombres' => null, 'apellidos' => null],
  $textoOCR
);

$response = AIService::requestAI($payload);

if ($response['success']) {
  $datos = $response['data'];
} else {
  error_log("Error AI: " . $response['error']);
}
```

##### 4. extraerDatosIA()

Extrae datos con sistema de reintentos automáticos.

```php
public static function extraerDatosIA(
  string $tipoDocumento,
  array $datosExtraer,
  string $extractedText,
  int $maxReintentos = 3
): array
```

**Parámetros**:
- `$tipoDocumento` (string): Tipo de documento
- `$datosExtraer` (array): Estructura de datos
- `$extractedText` (string): Texto de OCR
- `$maxReintentos` (int): Número máximo de reintentos (default: 3)

**Retorno**: Array con resultado o error

**Flujo con reintentos**:
1. Construye payload
2. Hace petición a AI
3. Si falla, espera 2 segundos
4. Reintenta hasta máximo de intentos
5. Retorna resultado o error final

**Ejemplo de uso**:
```php
$resultado = AIService::extraerDatosIA(
  'hoja_vida',
  [
    'datos_personales' => [
      'nombres' => null,
      'apellidos' => null
    ],
    'formacion_academica' => []
  ],
  $textoOCR,
  5  // Máximo 5 reintentos
);

if ($resultado['success']) {
  $datos = $resultado['data']['datos_extraidos'];
} else {
  // Falló después de 5 intentos
  error_log($resultado['error']);
}
```

##### 5. validarConexionIA()

Valida conexión con rund-ai.

```php
public static function validarConexionIA(): array
```

**Parámetros**: Ninguno

**Retorno**: Array con estado de conexión y modelos disponibles

**Ejemplo de uso**:
```php
$conexion = AIService::validarConexionIA();

if ($conexion['success']) {
  echo "Conexión OK\n";
  print_r($conexion['models']);
  // [
  //   ['name' => 'mistral', 'size' => 4.1GB],
  //   ['name' => 'phi3:mini', 'size' => 2.3GB]
  // ]
} else {
  echo "Error: " . $conexion['error'] . "\n";
}
```

##### 6. construyeAiPayload()

Construye el payload completo para rund-ai.

```php
public static function construyeAiPayload(
  string $tipoDocumento,
  array $datosExtraer,
  string $extractedText,
  float $temp = 0.1,
  float $top = 0.9,
  int $numPredict = 2048,
  array $stopTokens = []
): array
```

**Parámetros**:
- `$tipoDocumento` (string): Tipo de documento
- `$datosExtraer` (array): Estructura de datos a extraer
- `$extractedText` (string): Texto de OCR limpio
- `$temp` (float): Temperatura (0.0-1.0, default: 0.1)
- `$top` (float): Top-p sampling (0.0-1.0, default: 0.9)
- `$numPredict` (int): Máximo de tokens (default: 2048)
- `$stopTokens` (array): Tokens de parada opcionales

**Retorno**: Array con payload completo

**Validaciones**:
- Tipo de documento no vacío
- Datos a extraer no vacíos
- Texto extraído no vacío
- Temperatura entre 0.0 y 1.0
- Top-p entre 0.0 y 1.0
- Num predict mayor a 0

**Estructura del payload**:
```php
[
  'model' => 'mistral',
  'prompt' => '...',  // Prompt estructurado
  'stream' => false,
  'options' => [
    'temperature' => 0.1,      // Baja = más determinístico
    'top_p' => 0.9,            // Sampling
    'num_predict' => 2048,     // Tokens máximos
    'repeat_penalty' => 1.1,   // Penalizar repeticiones
    'top_k' => 40,             // Top-k sampling
    'stop' => [...]            // Stop tokens
  ]
]
```

**Parámetros de generación explicados**:

| Parámetro | Rango | Descripción | Valor en RUND |
|-----------|-------|-------------|---------------|
| `temperature` | 0.0-1.0 | Aleatoriedad. 0 = determinístico, 1 = aleatorio | 0.1 (muy determinístico) |
| `top_p` | 0.0-1.0 | Nucleus sampling. Considera top % de tokens | 0.9 (considera top 90%) |
| `num_predict` | >0 | Máximo de tokens a generar | 2048 |
| `repeat_penalty` | >0 | Penaliza repeticiones. >1 = menos repetición | 1.1 |
| `top_k` | >0 | Considera solo top K tokens más probables | 40 |

**Ejemplo de uso**:
```php
$payload = AIService::construyeAiPayload(
  'certificado_experiencia_laboral',
  [
    'empleado' => [
      'nombres' => null,
      'apellidos' => null
    ],
    'empresa' => [
      'nombre' => null,
      'nit' => null
    ]
  ],
  $textoLimpio,
  0.1,    // Baja temperatura para precisión
  0.9,    // Top-p estándar
  2048    // Suficiente para certificados
);

$response = AIService::requestAI($payload);
```

##### 7. obtenerStopTokensPorTipo()

Genera tokens de parada según tipo de documento.

```php
public static function obtenerStopTokensPorTipo(string $tipoDocumento): array
```

**Parámetros**:
- `$tipoDocumento` (string): Tipo de documento

**Retorno**: Array de stop tokens

**Stop tokens genéricos**:
```php
[
  '\n\nNota:',
  '\n\nImportante:',
  'Análisis adicional:',
  'Comentarios extra:'
]
```

**Stop tokens específicos por tipo**:
```php
'documento_identidad' => [
  'Verificación adicional:',
  'Datos complementarios:'
],
'hoja_vida' => [
  'Recomendaciones:',
  'Sugerencias:'
]
```

**Ejemplo de uso**:
```php
$stopTokens = AIService::obtenerStopTokensPorTipo('documento_identidad');
// Retorna: Tokens genéricos + tokens específicos
```

##### 8. construirPromptEstructurado()

Construye el prompt para la IA.

```php
public static function construirPromptEstructurado(
  string $tipoDocumento,
  string $cleanedText,
  string $fieldsDescription,
  array $datosExtraer
): string
```

**Parámetros**:
- `$tipoDocumento` (string): Tipo de documento
- `$cleanedText` (string): Texto limpio del OCR
- `$fieldsDescription` (string): Descripción de campos a extraer
- `$datosExtraer` (array): Estructura JSON esperada

**Retorno**: String con prompt completo

**Estructura del prompt**:
```text
Eres un experto en análisis de documentos académicos colombianos.
Tu tarea es extraer información específica de textos obtenidos mediante OCR.

**TIPO DE DOCUMENTO:** {tipoDocumento}

**TEXTO DEL DOCUMENTO:**
{cleanedText}

**DATOS A EXTRAER:**
{fieldsDescription}

**ESTRUCTURA JSON ESPERADA:**
{jsonStructure}

**INSTRUCCIONES CRÍTICAS:**
1. Analiza cuidadosamente el texto proporcionado
2. Extrae únicamente los datos solicitados de la estructura JSON
3. Si un dato no se encuentra en el texto, usa null (no string "null")
4. Para fechas, usa formato DD/MM/AAAA cuando sea posible
5. Para arrays, incluye todos los elementos encontrados en el texto
6. Mantén la precisión en nombres, números de documento y fechas
7. Si encuentras abreviaciones comunes (ej: "Nal" = "Nacional"), expándelas
8. Responde ÚNICAMENTE con un JSON válido, sin texto adicional antes o después
9. No incluyas anotaciones o comentarios en el JSON

**FORMATO DE RESPUESTA EXACTO:**
```json
{
  "status": "success",
  "tipo_documento": "{tipoDocumento}",
  "datos_extraidos": {
    // Estructura completa con los datos extraídos
  },
  "confianza": "alta|media|baja",
  "observaciones": "comentarios si hay inconsistencias o datos poco claros"
}
```

Responde ahora con el JSON:
```

**Ejemplo de prompt generado**:
```text
Eres un experto en análisis de documentos académicos colombianos.

**TIPO DE DOCUMENTO:** documento_identidad

**TEXTO DEL DOCUMENTO:**
REPÚBLICA DE COLOMBIA
CÉDULA DE CIUDADANÍA
NOMBRES: JUAN CARLOS
APELLIDOS: PÉREZ GARCÍA
No. 12345678
FECHA DE NACIMIENTO: 15/03/1985

**DATOS A EXTRAER:**
- nombres: Nombres completos de la persona (solo nombres, no apellidos)
- apellidos: Apellidos completos de la persona (solo apellidos, no nombres)
- numero_documento: Número de identificación (solo números, sin puntos ni espacios)
- fecha_nacimiento: Fecha de nacimiento en formato DD/MM/AAAA

**ESTRUCTURA JSON ESPERADA:**
{
  "nombres": null,
  "apellidos": null,
  "numero_documento": null,
  "fecha_nacimiento": null
}

[... instrucciones ...]
```

##### 9. obtenerDescripcionCampos()

Obtiene la descripción específica de campos según el tipo de documento.

```php
public static function obtenerDescripcionCampos(
  string $tipoDocumento,
  array $datosExtraer
): string
```

**Parámetros**:
- `$tipoDocumento` (string): Tipo de documento
- `$datosExtraer` (array): Datos a extraer (no se usa actualmente)

**Retorno**: String con descripción de campos

**Descripciones disponibles** (8 tipos):

1. **documento_identidad**
```php
'nombres' => 'Nombres completos de la persona (solo nombres, no apellidos)'
'apellidos' => 'Apellidos completos de la persona (solo apellidos, no nombres)'
'numero_documento' => 'Número de identificación (solo números, sin puntos ni espacios)'
'fecha_nacimiento' => 'Fecha de nacimiento en formato DD/MM/AAAA'
'fecha_expedicion' => 'Fecha de expedición del documento en formato DD/MM/AAAA'
'lugar_nacimiento' => 'Ciudad y/o departamento de nacimiento'
'lugar_expedicion' => 'Ciudad y/o departamento donde se expidió el documento'
```

2. **hoja_vida**
```php
'datos_personales' => 'Información personal: nombres, apellidos, documento, teléfono, email, dirección'
'formacion_academica' => 'Array con estudios: título, institución, año de graduación, nivel académico'
'experiencia_laboral' => 'Array con trabajos: cargo, empresa, fecha inicio, fecha fin, funciones principales'
'idiomas' => 'Array con idiomas: idioma, nivel (básico/intermedio/avanzado/nativo)'
'referencias' => 'Array con referencias: nombre completo, cargo, teléfono, email, empresa'
```

3. **certificado_experiencia_laboral**
```php
'empleado' => 'Datos del empleado: nombres, apellidos, número de documento'
'empresa' => 'Datos de la empresa: nombre/razón social, NIT, representante legal'
'experiencia' => 'Detalles del empleo: cargo, fechas de inicio y fin, duración, tipo de contrato, funciones realizadas'
```

4. **certificado_experiencia_docente**
```php
'docente' => 'Datos del docente: nombres, apellidos, documento'
'institucion' => 'Datos de la institución: nombre, tipo (universidad/colegio/instituto), NIT'
'experiencia_docente' => 'Detalles docentes: asignaturas, programas, fechas, modalidad, nivel educativo'
```

5. **articulo_productividad**
```php
'articulo' => 'Datos del artículo: título, autores, revista, ISSN, año, volumen, número, páginas, DOI'
'clasificacion' => 'Clasificación académica: categoría Minciencias, área de conocimiento, tipo de artículo'
```

6. **certificado_investigacion**
```php
'investigador' => 'Datos del investigador: nombres, apellidos, documento'
'proyecto' => 'Datos del proyecto: título, código, entidad financiadora, fechas, presupuesto, rol'
'clasificacion' => 'Clasificación: área de conocimiento, grupo de investigación, línea de investigación'
```

7. **certificado_idiomas**
```php
'estudiante' => 'Datos del estudiante: nombres, apellidos, documento'
'certificacion' => 'Datos del certificado: idioma, nivel, marco de referencia, puntaje, fechas, institución'
'habilidades' => 'Niveles por habilidad: lectura, escritura, escucha, habla'
```

8. **certificado_estudios_no_formales**
```php
'participante' => 'Datos del participante: nombres, apellidos, documento'
'curso' => 'Datos del curso: nombre, tipo, área, intensidad horaria, fechas, modalidad'
'institucion' => 'Datos de la institución: nombre, tipo, registro oficial'
'certificacion' => 'Datos del certificado: número, fecha expedición, vigencia'
```

##### 10. limpiarTextoOCR()

Limpia y normaliza el texto extraído por OCR.

```php
public static function limpiarTextoOCR(string $texto): string
```

**Parámetros**:
- `$texto` (string): Texto sin limpiar del OCR

**Retorno**: String con texto limpio

**Operaciones de limpieza**:

1. Elimina caracteres de control (0x00-0x1F, 0x7F)
2. Normaliza espacios múltiples a uno solo
3. Corrige caracteres mal interpretados:
   - `l` → `1` (en contextos numéricos)
   - `O` → `0` (en contextos numéricos)
   - `|` → `1` (pipes como 1)
   - `º` → `°` (símbolos de grado)
   - `№` → `No.` (número)
   - `С` → `C` (C cirílica a latina)
4. Normaliza saltos de línea dobles
5. Trim de espacios inicial/final

**Ejemplo de uso**:
```php
$textoSucio = "CÉDULA  DE   CIUDADANÍA\nNº   l2345678\n\n\nFECHA:   15/О3/1985";
$textoLimpio = AIService::limpiarTextoOCR($textoSucio);

// Resultado:
// "CÉDULA DE CIUDADANÍA
// No. 12345678
//
// FECHA: 15/03/1985"
```

##### 11. procesarRespuestaIA() y extraerJsonDeRespuesta()

Procesa la respuesta de rund-ai.

```php
public static function procesarRespuestaIA(array $response): array

public static function extraerJsonDeRespuesta(string $response): array
```

**procesarRespuestaIA() - Parámetros**:
- `$response` (array): Respuesta completa de Ollama

**procesarRespuestaIA() - Retorno**:
```php
// Éxito
[
  'success' => true,
  'error' => null,
  'data' => [...],  // JSON parseado
  'raw_response' => '...'
]

// Error
[
  'success' => false,
  'error' => 'mensaje de error',
  'raw_response' => '...'
]
```

**extraerJsonDeRespuesta() - Parámetros**:
- `$response` (string): Texto de respuesta que contiene JSON

**extraerJsonDeRespuesta() - Lógica**:
1. Busca el primer `{`
2. Busca el último `}`
3. Extrae substring entre ellos
4. Decodifica JSON
5. Valida errores

**Ejemplo de uso**:
```php
$responseAI = AIService::requestAI($payload);

if ($responseAI['success']) {
  $resultado = AIService::procesarRespuestaIA($responseAI['data']);

  if ($resultado['success']) {
    $datosExtraidos = $resultado['data']['datos_extraidos'];
  }
}
```

### Tipos de Documentos Soportados

| Tipo | Clave | Campos Principales |
|------|-------|-------------------|
| Documento de Identidad | `documento_identidad` | nombres, apellidos, numero_documento, fecha_nacimiento |
| Hoja de Vida | `hoja_vida` | datos_personales, formacion_academica, experiencia_laboral |
| Certificado Laboral | `certificado_experiencia_laboral` | empleado, empresa, experiencia |
| Certificado Docente | `certificado_experiencia_docente` | docente, institucion, experiencia_docente |
| Artículo de Investigación | `articulo_productividad` | articulo, clasificacion |
| Certificado de Investigación | `certificado_investigacion` | investigador, proyecto, clasificacion |
| Certificado de Idiomas | `certificado_idiomas` | estudiante, certificacion, habilidades |
| Estudios No Formales | `certificado_estudios_no_formales` | participante, curso, institucion |

### Configuración de Parámetros

**Valores recomendados según caso de uso**:

```php
// Alta precisión (certificados oficiales)
$payload = AIService::construyeAiPayload(
  $tipo, $datos, $texto,
  0.1,    // Temperatura muy baja
  0.9,    // Top-p estándar
  2048    // Tokens suficientes
);

// Balance precisión/creatividad (hojas de vida)
$payload = AIService::construyeAiPayload(
  $tipo, $datos, $texto,
  0.3,    // Temperatura moderada
  0.85,   // Top-p ligeramente bajo
  3072    // Más tokens para documentos largos
);

// Textos muy largos (tesis, artículos)
$payload = AIService::construyeAiPayload(
  $tipo, $datos, $texto,
  0.2,    // Temperatura baja-moderada
  0.9,    // Top-p estándar
  4096    // Muchos tokens
);
```

### Ejemplos de Payloads Completos

#### Documento de Identidad

```php
$payload = [
  'model' => 'mistral',
  'prompt' => 'Eres un experto en análisis de documentos académicos colombianos...',
  'stream' => false,
  'options' => [
    'temperature' => 0.1,
    'top_p' => 0.9,
    'num_predict' => 2048,
    'repeat_penalty' => 1.1,
    'top_k' => 40,
    'stop' => [
      '\n\nNota:',
      '\n\nImportante:',
      'Verificación adicional:',
      'Datos complementarios:'
    ]
  ]
];

$response = AIService::requestAI($payload);
```

#### Ejemplo de Respuesta de Mistral

```json
{
  "model": "mistral",
  "created_at": "2025-10-20T10:30:45.123456Z",
  "response": "```json\n{\n  \"status\": \"success\",\n  \"tipo_documento\": \"documento_identidad\",\n  \"datos_extraidos\": {\n    \"nombres\": \"JUAN CARLOS\",\n    \"apellidos\": \"PÉREZ GARCÍA\",\n    \"numero_documento\": \"12345678\",\n    \"fecha_nacimiento\": \"15/03/1985\",\n    \"fecha_expedicion\": \"20/05/2005\",\n    \"lugar_nacimiento\": \"BOGOTÁ D.C.\",\n    \"lugar_expedicion\": \"BOGOTÁ D.C.\"\n  },\n  \"confianza\": \"alta\",\n  \"observaciones\": \"Todos los datos se encontraron claramente en el texto\"\n}\n```",
  "done": true,
  "context": [...],
  "total_duration": 4523491000,
  "load_duration": 1234567,
  "prompt_eval_count": 234,
  "prompt_eval_duration": 1234567000,
  "eval_count": 156,
  "eval_duration": 2123456000
}
```

---

## rund-ocr (PaddleOCR) - Servicio de Reconocimiento Óptico

### Información General

**Servicio**: PaddleOCR (Python)
**URL Base**: `http://rund-ocr:8000`
**Variable de Entorno**: `OCR_API_URL=http://rund-ocr:8000`
**Idiomas**: Español, Inglés
**Límite de Archivo**: 50MB
**Timeout**: 60 segundos

### Endpoints Disponibles

| Endpoint | Método | Propósito | Parámetros |
|----------|--------|-----------|-----------|
| `/extract-text` | POST | Extraer texto de imagen/PDF | `file` (multipart) |
| `/health` | GET | Health check del servicio | Ninguno |
| `/info` | GET | Información del servicio | Ninguno |

### Endpoint /extract-text

**Método**: POST
**Content-Type**: multipart/form-data
**Parámetros**:
- `file`: Archivo (imagen o PDF)

**Formatos soportados**:
- Imágenes: JPG, JPEG, PNG, BMP, TIFF
- Documentos: PDF (múltiples páginas)

**Respuesta exitosa**:
```json
{
  "text": "Texto extraído del documento...",
  "page_count": 1,
  "processing_time": 2.3,
  "confidence": 0.95,
  "language": "es"
}
```

**Respuesta con error**:
```json
{
  "error": "File too large. Maximum size is 50MB"
}
```

### Endpoint /health

**Método**: GET

**Respuesta**:
```json
{
  "status": "healthy",
  "service": "rund-ocr",
  "version": "1.0.0",
  "timestamp": "2025-10-20T10:30:45Z"
}
```

### Endpoint /info

**Método**: GET

**Respuesta**:
```json
{
  "service": "RUND OCR Service",
  "version": "1.0.0",
  "engine": "PaddleOCR",
  "languages": ["es", "en"],
  "max_file_size": "50MB",
  "supported_formats": ["jpg", "jpeg", "png", "bmp", "tiff", "pdf"]
}
```

### Integración con AIService

El servicio OCR se integra con AIService a través del método `extraerTextoDeDocumento()`:

```php
// En AIService.php
public static function extraerTextoDeDocumento(string $filePath): array
{
  $ocrUrl = $_ENV['OCR_API_URL'] . '/extract-text';

  $curl = curl_init();
  curl_setopt($curl, CURLOPT_URL, $ocrUrl);
  curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($curl, CURLOPT_POST, true);
  curl_setopt($curl, CURLOPT_TIMEOUT, 60);  // 60 segundos
  curl_setopt($curl, CURLOPT_POSTFIELDS, [
    'file' => new \CURLFile($filePath)
  ]);
  curl_setopt($curl, CURLOPT_HTTPHEADER, [
    "Accept: application/json"
  ]);

  $resp = curl_exec($curl);
  $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
  curl_close($curl);

  if ($httpCode === 200) {
    return json_decode($resp, true);
  } else {
    return ["error" => "Error OCR: " . $resp];
  }
}
```

### Ejemplo de Uso Completo

```php
use RUND\Services\AIService;

// 1. Extraer texto del documento
$resultado = AIService::extraerTextoDeDocumento('/tmp/documento.pdf');

if (isset($resultado['error'])) {
  die("Error OCR: " . $resultado['error']);
}

// 2. Obtener texto limpio
$textoExtraido = $resultado['text'];
$textoLimpio = AIService::limpiarTextoOCR($textoExtraido);

echo "Texto extraído:\n";
echo $textoLimpio . "\n";
echo "\nPáginas procesadas: " . $resultado['page_count'] . "\n";
echo "Tiempo: " . $resultado['processing_time'] . "s\n";
echo "Confianza: " . ($resultado['confidence'] * 100) . "%\n";
```

### Limpieza de Texto OCR

Después de extraer texto, se recomienda limpiarlo:

```php
$textoSucio = $resultadoOCR['text'];
$textoLimpio = AIService::limpiarTextoOCR($textoSucio);
```

**Limpieza realizada**:
1. Elimina caracteres de control
2. Normaliza espacios
3. Corrige errores comunes (l→1, O→0, etc.)
4. Normaliza saltos de línea

### Troubleshooting OCR

**Error: Timeout**
```php
// Aumentar timeout para documentos grandes
curl_setopt($curl, CURLOPT_TIMEOUT, 120);  // 2 minutos
```

**Error: File too large**
```php
// Verificar tamaño antes de enviar
$fileSize = filesize($filePath);
if ($fileSize > 50 * 1024 * 1024) {  // 50MB
  throw new Exception("Archivo muy grande: " . ($fileSize / 1024 / 1024) . "MB");
}
```

**Error: Invalid format**
```php
// Validar extensión
$extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
$allowed = ['jpg', 'jpeg', 'png', 'pdf'];

if (!in_array($extension, $allowed)) {
  throw new Exception("Formato no soportado: $extension");
}
```

---

## LibreOffice Headless - Conversión de Documentos

### Información General

**Versión**: LibreOffice 25.8+
**Modo**: Headless (sin interfaz gráfica)
**Variable de Entorno**: `LIBREOFFICE_EXECUTABLE=/usr/bin/libreoffice`
**Formatos de Entrada**: DOCX, DOC, XLSX, XLS, ODT, ODS
**Formato de Salida**: PDF

### LBService.php - Servicio de Conversión

**Ubicación**: `/app/src/Services/LBService.php`

El servicio LBService proporciona 3 métodos para conversión de documentos Office a PDF.

#### Métodos del LBService

##### 1. convierteExcelToPDF()

Convierte archivos Excel (XLS, XLSX) a PDF.

```php
public static function convierteExcelToPDF(
  string $excel,
  string $dir = Config::TEMP_DIR
): array
```

**Parámetros**:
- `$excel` (string): Nombre del archivo Excel
- `$dir` (string): Directorio de trabajo (default: TEMP_DIR)

**Retorno**:
```php
// Éxito
['error' => null, 'salida' => '/path/to/archivo.pdf']

// Error
['error' => 'mensaje de error', 'salida' => '...', 'rtn' => código]
```

**Handler utilizado**: `calc_pdf_Export`

**Ejemplo de uso**:
```php
use RUND\Services\LBService;
use RUND\Config\Config;

// Convertir Excel a PDF
$resultado = LBService::convierteExcelToPDF('reporte.xlsx');

if ($resultado['error'] === null) {
  $pdfPath = $resultado['salida'];
  echo "PDF generado: $pdfPath\n";
} else {
  echo "Error: " . $resultado['error'] . "\n";
}
```

##### 2. convierteWordToPDF()

Convierte archivos Word (DOC, DOCX) a PDF.

```php
public static function convierteWordToPDF(
  string $word = "certificado.docx",
  string $dir = Config::TEMP_DIR
): array
```

**Parámetros**:
- `$word` (string): Nombre del archivo Word (default: "certificado.docx")
- `$dir` (string): Directorio de trabajo (default: TEMP_DIR)

**Retorno**: Igual que `convierteExcelToPDF()`

**Handler utilizado**: `writer_pdf_Export`

**Ejemplo de uso**:
```php
// Convertir Word a PDF
$resultado = LBService::convierteWordToPDF('certificado.docx');

if ($resultado['error'] === null) {
  $pdfPath = $resultado['salida'];

  // Enviar como respuesta HTTP
  header('Content-Type: application/pdf');
  header('Content-Disposition: attachment; filename="certificado.pdf"');
  readfile($pdfPath);

  // Limpiar archivo temporal
  unlink($pdfPath);
}
```

##### 3. convierteOfficeToPDF()

Método interno genérico para conversión.

```php
public static function convierteOfficeToPDF(
  string $file,
  string $handler,
  string $dir = Config::TEMP_DIR
): array
```

**Parámetros**:
- `$file` (string): Nombre del archivo
- `$handler` (string): Handler de LibreOffice
- `$dir` (string): Directorio de trabajo

**Handlers disponibles**:
- `calc_pdf_Export`: Para Excel/Calc
- `writer_pdf_Export`: Para Word/Writer
- `impress_pdf_Export`: Para PowerPoint/Impress (no implementado)
- `draw_pdf_Export`: Para Draw (no implementado)

**Comando generado**:
```bash
/usr/bin/libreoffice \
  --headless \
  --convert-to pdf:writer_pdf_Export \
  --outdir /app/tmp/ \
  /app/tmp/certificado.docx \
  2>/dev/null
```

**Ejemplo de uso directo**:
```php
// Convertir presentación a PDF (si se implementa)
$resultado = LBService::convierteOfficeToPDF(
  'presentacion.pptx',
  'impress_pdf_Export'
);
```

### Handlers de LibreOffice

Los handlers determinan cómo se exporta el documento:

| Handler | Aplicación | Formatos de Entrada | Configuración de Salida |
|---------|-----------|---------------------|------------------------|
| `calc_pdf_Export` | Calc (Excel) | XLS, XLSX, ODS | Ajusta celdas, mantiene formato |
| `writer_pdf_Export` | Writer (Word) | DOC, DOCX, ODT, RTF | Mantiene estilos, fuentes, imágenes |
| `impress_pdf_Export` | Impress (PowerPoint) | PPT, PPTX, ODP | Diapositivas a páginas PDF |
| `draw_pdf_Export` | Draw (Diagramas) | ODG | Vectorial a PDF |

### Configuración del Comando

**Opciones del comando**:

| Opción | Descripción |
|--------|-------------|
| `--headless` | Ejecuta sin interfaz gráfica |
| `--convert-to pdf:handler` | Convierte usando handler específico |
| `--outdir /path/` | Directorio de salida |
| `2>/dev/null` | Suprime errores stderr |

**Ejemplo completo del comando**:
```bash
/usr/bin/libreoffice \
  --headless \
  --convert-to pdf:writer_pdf_Export \
  --outdir /app/tmp/ \
  /app/tmp/certificado.docx \
  2>/dev/null
```

### Flujo de Conversión

```
1. Archivo Office en TEMP_DIR
   ↓
2. Construir comando libreoffice
   ↓
3. Ejecutar conversión
   ↓
4. Verificar si PDF existe
   ↓
5. Retornar ruta o error
```

### Ejemplo Completo de Uso

```php
use RUND\Services\LBService;
use RUND\Config\Config;

// 1. Generar documento Word desde plantilla
$plantilla = Config::TAX_PLANTILLAS_CERTIFICADOS . 'plantilla_laboral.docx';
$archivoTemp = Config::TEMP_DIR . 'certificado_temp.docx';

// Copiar plantilla
copy($plantilla, $archivoTemp);

// 2. Reemplazar marcadores en Word
// (lógica de reemplazo de texto)

// 3. Convertir a PDF
$resultado = LBService::convierteWordToPDF('certificado_temp.docx');

if ($resultado['error'] !== null) {
  die("Error al generar PDF: " . $resultado['error']);
}

$pdfPath = $resultado['salida'];

// 4. Subir a OpenKM
$uuid = OpenKM::subirDocumento($pdfPath, Config::TAX_CERTIFICADOS);

// 5. Limpiar archivos temporales
unlink($archivoTemp);
unlink($pdfPath);

echo "Certificado generado: $uuid\n";
```

### Troubleshooting LibreOffice

#### Error: LibreOffice not found

```php
// Verificar instalación
if (!file_exists($_ENV['LIBREOFFICE_EXECUTABLE'])) {
  throw new Exception('LibreOffice no está instalado');
}
```

#### Error: Conversion failed

```php
// Verificar permisos de directorio
if (!is_writable(Config::TEMP_DIR)) {
  throw new Exception('Directorio temporal no tiene permisos de escritura');
}
```

#### Error: PDF not generated

```php
// Verificar que el archivo existe antes de convertir
if (!file_exists($archivoWord)) {
  throw new Exception("Archivo no encontrado: $archivoWord");
}

// Verificar extensión
$ext = strtolower(pathinfo($archivoWord, PATHINFO_EXTENSION));
if (!in_array($ext, ['doc', 'docx', 'odt'])) {
  throw new Exception("Formato no soportado: $ext");
}
```

#### Timeout en conversión

```php
// Para documentos muy grandes, aumentar tiempo de espera
set_time_limit(120);  // 2 minutos

$resultado = LBService::convierteWordToPDF('documento_grande.docx');
```

---

## Pipeline AI Completo

El pipeline completo integra OCR → Limpieza → AI → Validación para procesamiento de documentos.

### Flujo del Pipeline

```
┌────────────────┐
│ Documento PDF  │
│  o Imagen      │
└────────┬───────┘
         │
         ▼
┌────────────────┐
│   rund-ocr     │ ← Extrae texto
│  (PaddleOCR)   │
└────────┬───────┘
         │
         ▼
┌────────────────┐
│ Limpieza OCR   │ ← Corrige errores comunes
└────────┬───────┘
         │
         ▼
┌────────────────┐
│ Construcción   │ ← Genera prompt estructurado
│   de Prompt    │
└────────┬───────┘
         │
         ▼
┌────────────────┐
│   rund-ai      │ ← Extrae datos
│   (Mistral)    │
└────────┬───────┘
         │
         ▼
┌────────────────┐
│  Validación    │ ← Verifica JSON
│     JSON       │
└────────┬───────┘
         │
         ▼
┌────────────────┐
│ Datos Finales  │
└────────────────┘
```

### Implementación del Pipeline

```php
use RUND\Services\AIService;

/**
 * Pipeline completo de análisis de documentos
 */
function analizarDocumento(string $filePath, string $tipoDocumento, array $estructura): array
{
  // PASO 1: Extracción de texto con OCR
  echo "1. Extrayendo texto con OCR...\n";
  $resultadoOCR = AIService::extraerTextoDeDocumento($filePath);

  if (isset($resultadoOCR['error'])) {
    return [
      'success' => false,
      'error' => 'Error en OCR: ' . $resultadoOCR['error'],
      'etapa' => 'ocr'
    ];
  }

  echo "   ✓ Texto extraído: " . strlen($resultadoOCR['text']) . " caracteres\n";

  // PASO 2: Limpieza de texto
  echo "2. Limpiando texto OCR...\n";
  $textoLimpio = AIService::limpiarTextoOCR($resultadoOCR['text']);
  echo "   ✓ Texto limpio: " . strlen($textoLimpio) . " caracteres\n";

  // PASO 3: Construcción de payload AI
  echo "3. Construyendo payload para AI...\n";
  $payload = AIService::construyeAiPayload(
    $tipoDocumento,
    $estructura,
    $textoLimpio,
    0.1,    // Temperatura baja para precisión
    0.9,    // Top-p estándar
    2048    // Tokens suficientes
  );
  echo "   ✓ Payload construido\n";

  // PASO 4: Extracción con AI (con reintentos)
  echo "4. Extrayendo datos con AI (máx 3 reintentos)...\n";
  $resultadoAI = AIService::extraerDatosIA(
    $tipoDocumento,
    $estructura,
    $textoLimpio,
    3  // 3 reintentos
  );

  if (!$resultadoAI['success']) {
    return [
      'success' => false,
      'error' => 'Error en AI: ' . $resultadoAI['error'],
      'etapa' => 'ai',
      'ocr_result' => $resultadoOCR
    ];
  }

  echo "   ✓ Datos extraídos correctamente\n";

  // PASO 5: Validación de datos
  echo "5. Validando datos extraídos...\n";
  $datosExtraidos = $resultadoAI['data']['datos_extraidos'];

  if (empty($datosExtraidos)) {
    return [
      'success' => false,
      'error' => 'No se extrajeron datos',
      'etapa' => 'validacion',
      'ocr_result' => $resultadoOCR,
      'ai_result' => $resultadoAI
    ];
  }

  echo "   ✓ Datos validados\n";

  // PASO 6: Retornar resultado completo
  return [
    'success' => true,
    'tipo_documento' => $tipoDocumento,
    'datos_extraidos' => $datosExtraidos,
    'confianza' => $resultadoAI['data']['confianza'] ?? 'desconocida',
    'observaciones' => $resultadoAI['data']['observaciones'] ?? '',
    'metadata' => [
      'paginas_ocr' => $resultadoOCR['page_count'] ?? 1,
      'tiempo_ocr' => $resultadoOCR['processing_time'] ?? 0,
      'confianza_ocr' => $resultadoOCR['confidence'] ?? 0,
      'caracteres_extraidos' => strlen($textoLimpio)
    ]
  ];
}
```

### Ejemplo de Uso del Pipeline

```php
// Analizar documento de identidad
$resultado = analizarDocumento(
  '/tmp/cedula_12345678.pdf',
  'documento_identidad',
  [
    'nombres' => null,
    'apellidos' => null,
    'numero_documento' => null,
    'fecha_nacimiento' => null,
    'fecha_expedicion' => null,
    'lugar_nacimiento' => null,
    'lugar_expedicion' => null
  ]
);

if ($resultado['success']) {
  echo "\n✓ Análisis completado exitosamente\n\n";

  $datos = $resultado['datos_extraidos'];
  echo "Datos extraídos:\n";
  echo "  Nombres: " . $datos['nombres'] . "\n";
  echo "  Apellidos: " . $datos['apellidos'] . "\n";
  echo "  Documento: " . $datos['numero_documento'] . "\n";
  echo "  Nacimiento: " . $datos['fecha_nacimiento'] . "\n";
  echo "\n";
  echo "Confianza: " . $resultado['confianza'] . "\n";
  echo "Observaciones: " . $resultado['observaciones'] . "\n";
  echo "\n";
  echo "Metadata:\n";
  echo "  Páginas: " . $resultado['metadata']['paginas_ocr'] . "\n";
  echo "  Tiempo OCR: " . $resultado['metadata']['tiempo_ocr'] . "s\n";
  echo "  Confianza OCR: " . ($resultado['metadata']['confianza_ocr'] * 100) . "%\n";
} else {
  echo "\n✗ Error en etapa: " . $resultado['etapa'] . "\n";
  echo "  " . $resultado['error'] . "\n";
}
```

### Reintentos Automáticos

El sistema implementa reintentos automáticos en caso de fallos:

```php
public static function extraerDatosIA(
  string $tipoDocumento,
  array $datosExtraer,
  string $extractedText,
  int $maxReintentos = 3
): array {
  $intento = 1;

  while ($intento <= $maxReintentos) {
    try {
      error_log("Intento {$intento} de extracción para: {$tipoDocumento}");

      // Construir payload
      $payload = self::construyeAiPayload($tipoDocumento, $datosExtraer, $extractedText);

      // Hacer petición
      $response = self::requestAI($payload);

      if (!$response['success']) {
        throw new \Exception("Error en petición: " . $response['error']);
      }

      // Procesar respuesta
      $resultado = self::procesarRespuestaIA($response['data']);

      if ($resultado['success']) {
        error_log("Extracción exitosa en intento {$intento}");
        return $resultado;
      }

      // Si no fue exitoso, reintenta
      error_log("Fallo en procesamiento, reintentando... " . $resultado['error']);
      $intento++;

      // Esperar antes del siguiente intento
      if ($intento <= $maxReintentos) {
        sleep(2);  // Espera 2 segundos
      }

    } catch (\Exception $e) {
      error_log("Error en intento {$intento}: " . $e->getMessage());

      if ($intento === $maxReintentos) {
        return [
          'success' => false,
          'error' => "Falló después de {$maxReintentos} intentos: " . $e->getMessage(),
          'intento_fallido' => $intento
        ];
      }

      $intento++;
      sleep(2);
    }
  }

  return [
    'success' => false,
    'error' => "Agotados todos los reintentos ({$maxReintentos})"
  ];
}
```

### Manejo de Errores por Etapa

```php
// Error en OCR
if ($resultado['etapa'] === 'ocr') {
  // Posibles causas:
  // - Archivo corrupto
  // - Formato no soportado
  // - Timeout del servicio
  // - Servicio rund-ocr no disponible

  error_log("Error OCR: " . $resultado['error']);
  // Reintentar o notificar al usuario
}

// Error en AI
if ($resultado['etapa'] === 'ai') {
  // Posibles causas:
  // - Timeout de rund-ai
  // - Modelo no cargado
  // - Texto muy largo
  // - Servicio rund-ai no disponible

  error_log("Error AI: " . $resultado['error']);

  // Verificar OCR
  if (isset($resultado['ocr_result'])) {
    error_log("Texto OCR disponible, problema solo en AI");
  }
}

// Error en validación
if ($resultado['etapa'] === 'validacion') {
  // Posibles causas:
  // - Datos insuficientes en documento
  // - Formato de documento incorrecto
  // - JSON malformado de la AI

  error_log("Error validación: " . $resultado['error']);

  // Revisar respuesta AI
  if (isset($resultado['ai_result'])) {
    error_log("Respuesta AI: " . json_encode($resultado['ai_result']));
  }
}
```

### Ejemplo End-to-End Completo

```php
use RUND\Services\AIService;
use RUND\Services\LBService;
use RUND\Core\OpenKM;
use RUND\Config\Config;

/**
 * Procesa una hoja de vida completa:
 * 1. Descarga de OpenKM
 * 2. OCR + AI para extracción
 * 3. Almacena datos en JSON
 * 4. Sube JSON a OpenKM
 */
function procesarHojaDeVida(string $uuidDocumento): array
{
  echo "Iniciando procesamiento de hoja de vida...\n\n";

  // PASO 1: Descargar de OpenKM
  echo "1. Descargando documento de OpenKM...\n";
  $tempFile = Config::TEMP_DIR . 'hoja_vida_temp.pdf';

  $descarga = OpenKM::descargarDocumento($uuidDocumento, $tempFile);
  if (isset($descarga['error'])) {
    return ['success' => false, 'error' => 'Error al descargar: ' . $descarga['error']];
  }
  echo "   ✓ Documento descargado\n";

  // PASO 2: Analizar con pipeline AI
  echo "2. Analizando documento...\n";
  $resultado = analizarDocumento(
    $tempFile,
    'hoja_vida',
    [
      'datos_personales' => [
        'nombres' => null,
        'apellidos' => null,
        'documento' => null,
        'telefono' => null,
        'email' => null,
        'direccion' => null
      ],
      'formacion_academica' => [],
      'experiencia_laboral' => [],
      'idiomas' => [],
      'referencias' => []
    ]
  );

  if (!$resultado['success']) {
    unlink($tempFile);
    return $resultado;
  }

  echo "   ✓ Documento analizado\n";

  // PASO 3: Guardar datos extraídos
  echo "3. Guardando datos extraídos...\n";
  $jsonFile = Config::TEMP_DIR . 'hoja_vida_datos.json';

  file_put_contents(
    $jsonFile,
    json_encode($resultado['datos_extraidos'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
  );

  echo "   ✓ Datos guardados en JSON\n";

  // PASO 4: Subir JSON a OpenKM
  echo "4. Subiendo JSON a OpenKM...\n";
  $rutaJSON = Config::TAX_APP_DATA . 'hojas_vida/' . basename($jsonFile);

  $uuidJSON = OpenKM::subirDocumento($jsonFile, dirname($rutaJSON));
  if (isset($uuidJSON['error'])) {
    unlink($tempFile);
    unlink($jsonFile);
    return ['success' => false, 'error' => 'Error al subir JSON: ' . $uuidJSON['error']];
  }

  echo "   ✓ JSON subido a OpenKM\n";

  // PASO 5: Limpiar archivos temporales
  echo "5. Limpiando archivos temporales...\n";
  unlink($tempFile);
  unlink($jsonFile);
  echo "   ✓ Archivos temporales eliminados\n";

  // Retornar resultado
  return [
    'success' => true,
    'uuid_original' => $uuidDocumento,
    'uuid_json' => $uuidJSON,
    'datos' => $resultado['datos_extraidos'],
    'metadata' => $resultado['metadata']
  ];
}

// Uso
$resultado = procesarHojaDeVida('abcd1234-5678-90ef-ghij-klmnopqrstuv');

if ($resultado['success']) {
  echo "\n✓ Procesamiento completado\n";
  echo "UUID JSON: " . $resultado['uuid_json'] . "\n";
  print_r($resultado['datos']);
} else {
  echo "\n✗ Error: " . $resultado['error'] . "\n";
}
```

---

## Resumen

Este documento cubre:

1. **rund-ai (Ollama)**: 11 métodos del AIService, configuración de Mistral, tipos de documentos soportados, y ejemplos de payloads
2. **rund-ocr (PaddleOCR)**: 3 endpoints, integración con AIService, limpieza de texto OCR
3. **LibreOffice Headless**: 3 métodos del LBService, handlers de conversión, troubleshooting
4. **Pipeline AI Completo**: Flujo OCR → Limpieza → AI → Validación, reintentos automáticos, manejo de errores, ejemplos end-to-end

Estos servicios forman el núcleo de procesamiento inteligente de documentos del sistema RUND.
