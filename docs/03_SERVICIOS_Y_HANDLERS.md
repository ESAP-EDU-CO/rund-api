# Servicios y Handlers - RUND API

## Tabla de Contenido

1. [Introducción](#introducción)
2. [Services](#services)
   - [AIService](#aiservice)
   - [CategoriasService](#categoriasservice)
   - [CertificadosService](#certificadosservice)
   - [DocumentService](#documentservice)
   - [FirmasService](#firmasservice)
   - [LBService](#lbservice)
   - [QRService](#qrservice)
   - [ReportesService](#reportesservice)
3. [Handlers](#handlers)
   - [AIHandlers](#aihandlers)
   - [CategoriasHandlers](#categoriashandlers)
   - [CertificadosHandlers](#certificadoshandlers)
   - [DataHandlers](#datahandlers)
   - [FileHandlers](#filehandlers)
   - [FirmasHandlers](#firmashandlers)

---

## Introducción

Este documento proporciona la documentación completa de todos los **Services** y **Handlers** del sistema RUND API v2.0. Los Services contienen la lógica de negocio central, mientras que los Handlers gestionan las peticiones HTTP y coordinan el uso de los Services.

**Arquitectura:**
- **Services**: Capa de lógica de negocio independiente del transporte HTTP
- **Handlers**: Capa de presentación que procesa peticiones HTTP y respuestas
- **Comunicación**: Handlers invocan Services, nunca al revés

---

## Services

### AIService

**Ubicación:** `/app/src/Services/AIService.php`
**Namespace:** `RUND\Services`
**Propósito:** Maneja operaciones de inteligencia artificial y OCR mediante servicios externos (rund-ai y rund-ocr)

#### Métodos Públicos

##### 1. analizaDocumento()

**Línea:** 30

**Firma:**
```php
public static function analizaDocumento(
    string $filePath,
    string $tipoDocumento,
    array $datosExtraer
): array
```

**Descripción:**
Analiza un documento completo usando OCR para extraer texto y luego IA para estructurar la información. Es el método principal del flujo de análisis documental.

**Parámetros:**
- `$filePath` (string): Ruta del archivo del documento a procesar
- `$tipoDocumento` (string): Tipo de documento (ej. "certificado", "contrato", "documento de identidad", "hoja de vida")
- `$datosExtraer` (array): Estructura de datos a extraer del documento

**Retorna:**
`array` - Resultado del análisis con campos:
- `text`: Texto extraído por OCR
- `data`: Datos estructurados extraídos por IA
- `ocr_result`: Resultado completo del OCR
- `success`: Boolean indicando éxito de la operación

**Excepciones:**
- Ninguna explícita (maneja errores internamente)

**Dependencias externas:**
- rund-ocr (servicio de extracción de texto)
- rund-ai (servicio de análisis con IA)

**Ejemplo de uso:**
```php
$filePath = '/tmp/cedula.pdf';
$tipoDocumento = 'documento_identidad';
$datosExtraer = [
    'nombres' => null,
    'apellidos' => null,
    'numero_documento' => null
];

$resultado = AIService::analizaDocumento($filePath, $tipoDocumento, $datosExtraer);
```

**Notas:**
- Elimina automáticamente el archivo temporal después de procesar
- Combina OCR y análisis IA en un solo flujo

---

##### 2. extraerTextoDeDocumento()

**Línea:** 50

**Firma:**
```php
public static function extraerTextoDeDocumento(string $filePath): array
```

**Descripción:**
Extrae texto de un documento mediante el servicio de OCR (rund-ocr). Soporta PDF e imágenes.

**Parámetros:**
- `$filePath` (string): Ruta del archivo del documento a procesar

**Retorna:**
`array` - Resultado del OCR con campos:
- `text`: Texto extraído
- `confidence`: Nivel de confianza (opcional)
- `error`: Mensaje de error si falla

**Excepciones:**
- Ninguna (retorna array con campo 'error' en caso de fallo)

**Dependencias externas:**
- rund-ocr (PaddleOCR en puerto 8000)

**Ejemplo de uso:**
```php
$resultado = AIService::extraerTextoDeDocumento('/tmp/documento.pdf');
if (!isset($resultado['error'])) {
    echo $resultado['text'];
}
```

**Notas:**
- Timeout de 60 segundos
- Soporta español e inglés
- Límite de 50MB por archivo

---

##### 3. requestAI()

**Línea:** 77

**Firma:**
```php
public static function requestAI(array $aiPayload): array
```

**Descripción:**
Realiza petición al servicio rund-ai con manejo robusto de errores, validaciones y timeouts.

**Parámetros:**
- `$aiPayload` (array): Payload para rund-ai con campos requeridos:
  - `model`: Nombre del modelo (ej. 'mistral', 'phi3:mini')
  - `prompt`: Texto del prompt
  - `stream`: Boolean para streaming
  - `options`: Configuraciones adicionales

**Retorna:**
`array` - Respuesta procesada con campos:
- `success`: Boolean indicando éxito
- `data`: Datos de respuesta si exitoso
- `error`: Mensaje de error si falla
- `http_code`: Código HTTP de respuesta

**Excepciones:**
- `InvalidArgumentException`: Si el payload es inválido o faltan campos requeridos
- `Exception`: Si hay errores de codificación JSON o comunicación

**Dependencias externas:**
- rund-ai (Ollama en puerto 11434)

**Ejemplo de uso:**
```php
$payload = [
    'model' => 'mistral',
    'prompt' => 'Analiza este texto: ...',
    'stream' => false,
    'options' => ['temperature' => 0.1]
];

$respuesta = AIService::requestAI($payload);
if ($respuesta['success']) {
    $datos = $respuesta['data'];
}
```

**Notas:**
- Timeout de 480 segundos para documentos complejos
- Valida estructura del payload antes de enviar
- Maneja encoding UTF-8 correctamente

---

##### 4. extraerDatosIA()

**Línea:** 153

**Firma:**
```php
public static function extraerDatosIA(
    string $tipoDocumento,
    array $datosExtraer,
    string $extractedText,
    int $maxReintentos = 3
): array
```

**Descripción:**
Función específica para extracción de datos con sistema de reintentos automáticos en caso de fallo.

**Parámetros:**
- `$tipoDocumento` (string): Tipo de documento a procesar
- `$datosExtraer` (array): Estructura de datos esperada
- `$extractedText` (string): Texto extraído por OCR
- `$maxReintentos` (int): Número máximo de reintentos (default: 3)

**Retorna:**
`array` - Resultado con campos:
- `success`: Boolean
- `data`: Datos extraídos si exitoso
- `error`: Mensaje de error si falla
- `intento_fallido`: Número de intento donde falló

**Excepciones:**
- Ninguna (maneja internamente)

**Dependencias externas:**
- rund-ai (vía requestAI)

**Ejemplo de uso:**
```php
$resultado = AIService::extraerDatosIA(
    'documento_identidad',
    ['nombres' => null, 'apellidos' => null],
    'NOMBRES: JUAN\nAPELLIDOS: PEREZ',
    3
);
```

**Notas:**
- Espera 2 segundos entre reintentos
- Útil para respuestas inconsistentes del modelo IA
- Registra logs de cada intento

---

##### 5. validarConexionIA()

**Línea:** 205

**Firma:**
```php
public static function validarConexionIA(): array
```

**Descripción:**
Valida la conexión con el servicio rund-ai y lista los modelos disponibles.

**Parámetros:**
Ninguno

**Retorna:**
`array` - Estado de conexión con campos:
- `success`: Boolean
- `models`: Array de modelos disponibles
- `message`: Mensaje descriptivo
- `error`: Mensaje de error si falla

**Excepciones:**
- Ninguna

**Dependencias externas:**
- rund-ai endpoint `/api/tags`

**Ejemplo de uso:**
```php
$estado = AIService::validarConexionIA();
if ($estado['success']) {
    foreach ($estado['models'] as $modelo) {
        echo $modelo['name'] . "\n";
    }
}
```

**Notas:**
- Timeout de 10 segundos
- Útil para health checks

---

##### 6. construyeAiPayload()

**Línea:** 246

**Firma:**
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

**Descripción:**
Construye el payload completo para rund-ai con prompt estructurado, validaciones y configuraciones óptimas.

**Parámetros:**
- `$tipoDocumento` (string): Tipo de documento
- `$datosExtraer` (array): Estructura de datos esperada
- `$extractedText` (string): Texto extraído por OCR
- `$temp` (float): Temperatura para generación (0.0-1.0, default: 0.1)
- `$top` (float): Top-p para generación (0.0-1.0, default: 0.9)
- `$numPredict` (int): Número máximo de tokens (default: 2048)
- `$stopTokens` (array): Tokens de parada opcionales

**Retorna:**
`array` - Payload completo para rund-ai

**Excepciones:**
- `InvalidArgumentException`: Si los parámetros no son válidos

**Dependencias externas:**
- Ninguna

**Ejemplo de uso:**
```php
$payload = AIService::construyeAiPayload(
    'documento_identidad',
    ['nombres' => null],
    'NOMBRES: JUAN PEREZ',
    0.1,
    0.9,
    2048
);
```

**Notas:**
- Limpia el texto OCR antes de procesarlo
- Genera prompts específicos por tipo de documento
- Usa modelo 'mistral' por defecto

---

##### 7. obtenerStopTokensPorTipo()

**Línea:** 303

**Firma:**
```php
public static function obtenerStopTokensPorTipo(string $tipoDocumento): array
```

**Descripción:**
Genera tokens de parada específicos según el tipo de documento para mejorar la precisión de la extracción.

**Parámetros:**
- `$tipoDocumento` (string): Tipo de documento

**Retorna:**
`array` - Array de tokens de parada

**Excepciones:**
- Ninguna

**Dependencias externas:**
- Ninguna

**Ejemplo de uso:**
```php
$stops = AIService::obtenerStopTokensPorTipo('documento_identidad');
```

**Notas:**
- Combina tokens genéricos y específicos
- Mejora la calidad de las respuestas JSON

---

##### 8. construirPromptEstructurado()

**Línea:** 330

**Firma:**
```php
public static function construirPromptEstructurado(
    string $tipoDocumento,
    string $cleanedText,
    string $fieldsDescription,
    array $datosExtraer
): string
```

**Descripción:**
Construye el prompt estructurado para la extracción de datos, incluyendo instrucciones específicas y formato JSON esperado.

**Parámetros:**
- `$tipoDocumento` (string): Tipo de documento
- `$cleanedText` (string): Texto limpio del OCR
- `$fieldsDescription` (string): Descripción de campos a extraer
- `$datosExtraer` (array): Estructura JSON esperada

**Retorna:**
`string` - Prompt completo estructurado

**Excepciones:**
- Ninguna

**Dependencias externas:**
- Ninguna

**Ejemplo de uso:**
```php
$prompt = AIService::construirPromptEstructurado(
    'documento_identidad',
    'NOMBRES: JUAN...',
    'nombres: Nombres completos...',
    ['nombres' => null]
);
```

**Notas:**
- Incluye instrucciones críticas para JSON válido
- Específico para documentos colombianos

---

##### 9. obtenerDescripcionCampos()

**Línea:** 380

**Firma:**
```php
public static function obtenerDescripcionCampos(
    string $tipoDocumento,
    array $datosExtraer
): string
```

**Descripción:**
Obtiene la descripción específica de campos según el tipo de documento para guiar la extracción de IA.

**Parámetros:**
- `$tipoDocumento` (string): Tipo de documento
- `$datosExtraer` (array): Datos a extraer

**Retorna:**
`string` - Descripción de campos en texto plano

**Excepciones:**
- Ninguna

**Dependencias externas:**
- Ninguna

**Ejemplo de uso:**
```php
$descripcion = AIService::obtenerDescripcionCampos(
    'documento_identidad',
    ['nombres' => null]
);
```

**Notas:**
- Soporta 8 tipos de documentos diferentes
- Devuelve descripción genérica si no encuentra el tipo

---

##### 10. limpiarTextoOCR()

**Línea:** 453

**Firma:**
```php
public static function limpiarTextoOCR(string $texto): string
```

**Descripción:**
Limpia y normaliza el texto extraído por OCR, corrigiendo errores comunes de interpretación.

**Parámetros:**
- `$texto` (string): Texto crudo del OCR

**Retorna:**
`string` - Texto limpio y normalizado

**Excepciones:**
- Ninguna

**Dependencias externas:**
- Ninguna

**Ejemplo de uso:**
```php
$textoLimpio = AIService::limpiarTextoOCR($textoOCR);
```

**Notas:**
- Corrige caracteres mal interpretados (l→1, O→0, etc.)
- Elimina caracteres de control
- Normaliza espacios y saltos de línea

---

##### 11. procesarRespuestaIA()

**Línea:** 480

**Firma:**
```php
public static function procesarRespuestaIA(array $response): array
```

**Descripción:**
Procesa la respuesta de rund-ai, extrayendo y validando el JSON estructurado.

**Parámetros:**
- `$response` (array): Respuesta cruda de rund-ai

**Retorna:**
`array` - Resultado procesado con campos:
- `success`: Boolean
- `data`: Datos JSON extraídos
- `error`: Mensaje de error si falla
- `raw_response`: Respuesta cruda

**Excepciones:**
- Ninguna (maneja internamente)

**Dependencias externas:**
- Ninguna

**Ejemplo de uso:**
```php
$resultado = AIService::procesarRespuestaIA($responseIA);
if ($resultado['success']) {
    $datos = $resultado['data'];
}
```

**Notas:**
- Valida estructura JSON básica
- Extrae JSON de texto circundante

---

#### Métodos Privados

##### extraerJsonDeRespuesta()

**Línea:** 511

**Firma:**
```php
public static function extraerJsonDeRespuesta(string $response): array
```

**Descripción:**
Extrae JSON válido de la respuesta del modelo IA, que puede contener texto adicional antes o después del JSON.

**Parámetros:**
- `$response` (string): Respuesta cruda del modelo

**Retorna:**
`array` - Datos JSON decodificados

**Excepciones:**
- `Exception`: Si no se encuentra JSON válido

**Dependencias externas:**
- Ninguna

---

### CategoriasService

**Ubicación:** `/app/src/Services/CategoriasService.php`
**Namespace:** `RUND\Services`
**Propósito:** Maneja operaciones de gestión de categorías en OpenKM

#### Métodos Públicos

##### 1. creaCategorias()

**Línea:** 28

**Firma:**
```php
public static function creaCategorias(array $categorias): array
```

**Descripción:**
Crea categorías en OpenKM si no existen, a partir de un array de rutas completas.

**Parámetros:**
- `$categorias` (array): Array de arrays con la clave 'path' conteniendo la ruta completa de la categoría (ej. `[["path" => "/okm:categories/RUTA/A/LA/CATEGORIA"], ...]`)

**Retorna:**
`array` - Array con los resultados de las operaciones realizadas

**Excepciones:**
- Ninguna

**Dependencias externas:**
- OpenKM API

**Ejemplo de uso:**
```php
$categorias = [
    ["path" => "/okm:categories/PROFESORES/ACTIVOS"],
    ["path" => "/okm:categories/PROFESORES/INACTIVOS"]
];
$resultado = CategoriasService::creaCategorias($categorias);
```

**Notas:**
- Verifica existencia antes de crear
- No genera error si la categoría ya existe

---

##### 2. getArbolCarpetas()

**Línea:** 64

**Firma:**
```php
public static function getArbolCarpetas(
    string $uuid,
    string|int|null $key = null
)
```

**Descripción:**
Genera un árbol de carpetas (sirve para Taxonomía o Categorías) a partir de un UUID. Construye keys únicos si se proporciona un prefijo.

**Parámetros:**
- `$uuid` (string): UUID de la carpeta raíz desde donde se generará el árbol
- `$key` (string|int|null): Prefijo para las keys del árbol (default: null)

**Retorna:**
`array` - Array representando el árbol de carpetas y documentos con estructura:
  - `key`: Identificador único
  - `label`: Nombre legible
  - `uuid`: UUID de OpenKM
  - `path`: Ruta completa
  - `children`: Sub-carpetas (si existen)
  - `documentos`: Documentos asociados (si existen)
  - `numDocs`: Cantidad de documentos

**Excepciones:**
- Ninguna

**Dependencias externas:**
- OpenKM API

**Ejemplo de uso:**
```php
$arbol = CategoriasService::getArbolCarpetas($uuid, '0');
// Resultado: árbol navegable con keys como '0-0', '0-1', etc.
```

**Notas:**
- Recursivo para sub-carpetas
- Útil para componentes Tree en Angular
- Incluye información de documentos categorizados

---

##### 3. getDocumentosCategorizados()

**Línea:** 133

**Firma:**
```php
public static function getDocumentosCategorizados($uuid)
```

**Descripción:**
Retorna los documentos que han sido "marcados" en una categoría dada.

**Parámetros:**
- `$uuid` (string): UUID de la categoría

**Retorna:**
`array` - Array con los documentos que tienen asignada la categoría dada

**Excepciones:**
- Ninguna

**Dependencias externas:**
- OpenKM API

**Ejemplo de uso:**
```php
$docs = CategoriasService::getDocumentosCategorizados($catUUID);
```

**Notas:**
- Devuelve información completa de cada documento
- Incluye categorías asignadas a cada documento

---

##### 4. simplePath()

**Línea:** 144

**Firma:**
```php
public static function simplePath($path)
```

**Descripción:**
Extrae la última parte de un path de categorías o taxonomía: el nombre de la carpeta.

**Parámetros:**
- `$path` (string): Ruta completa de la categoría

**Retorna:**
`string` - Nombre de la carpeta (última parte del path)

**Excepciones:**
- Ninguna

**Dependencias externas:**
- Ninguna

**Ejemplo de uso:**
```php
$nombre = CategoriasService::simplePath('/okm:categories/PROFESORES/ACTIVOS');
// Resultado: 'ACTIVOS'
```

**Notas:**
- Elimina prefijos /okm:categories/ o /okm:root/
- Útil para obtener labels

---

##### 5. getDocsFromCat()

**Línea:** 157

**Firma:**
```php
public static function getDocsFromCat($catUUID): array
```

**Descripción:**
Extrae los UUIDs de documentos de una categoría dada.

**Parámetros:**
- `$catUUID` (string): UUID de la categoría

**Retorna:**
`array` - Array con los UUIDs de los documentos que tienen asignada la categoría

**Excepciones:**
- Ninguna

**Dependencias externas:**
- OpenKM API

**Ejemplo de uso:**
```php
$docUUIDs = CategoriasService::getDocsFromCat($catUUID);
```

**Notas:**
- Maneja tanto un solo documento como múltiples
- Útil para operaciones de cruce de categorías

---

### CertificadosService

**Ubicación:** `/app/src/Services/CertificadosService.php`
**Namespace:** `RUND\Services`
**Propósito:** Maneja operaciones de generación de certificados usando plantillas DOCX

**Dependencias:**
- PhpOffice\PhpWord

#### Métodos Públicos

##### 1. creaCertificado()

**Línea:** 32

**Firma:**
```php
public static function creaCertificado(
    string $nombrePlantilla,
    array $estructura,
    string $id,
    string $ruta = Config::TAX_PLANTILLAS_CERTIFICADOS
): TemplateProcessor
```

**Descripción:**
Crea un certificado a partir de una plantilla DOCX, rellenando párrafos, tablas y firmas, y añadiendo QR de validación.

**Parámetros:**
- `$nombrePlantilla` (string): Nombre del archivo de plantilla en OpenKM
- `$estructura` (array): Array de bloques con estructura del certificado
- `$id` (string): Identificador único del certificado
- `$ruta` (string): Ruta en OpenKM donde se encuentra la plantilla (default: Config::TAX_PLANTILLAS_CERTIFICADOS)

**Retorna:**
`TemplateProcessor` - Objeto procesador de plantilla listo para guardar

**Excepciones:**
- Ninguna explícita

**Dependencias externas:**
- OpenKM (obtención de plantilla)
- QRService (generación de código QR)
- FirmasService (obtención de firmas)

**Ejemplo de uso:**
```php
$estructura = [
    ["tipo" => "parrafo", "value" => "Este es un certificado..."],
    ["tipo" => "tabla", "value" => ["encabezados" => [...], "filas" => [...]]],
    ["tipo" => "firma", "value" => ["uuid" => "...", "nombre" => "...", "cargo" => "..."]]
];

$template = CertificadosService::creaCertificado(
    'plantilla_certificado.docx',
    $estructura,
    'ABC123XYZ4567890'
);
$template->saveAs('certificado_final.docx');
```

**Notas:**
- Soporta 3 tipos de bloques: párrafo, tabla, firma
- Genera automáticamente QR de validación
- Elimina imágenes temporales después de usarlas

---

##### 2. creaParrafoComplejo()

**Línea:** 84

**Firma:**
```php
public static function creaParrafoComplejo(
    TemplateProcessor $templateProcessor,
    string $texto,
    string $placeholder
): TemplateProcessor
```

**Descripción:**
Crea un párrafo con formato complejo (negrita, cursiva, subrayado) a partir de HTML simple.

**Parámetros:**
- `$templateProcessor` (TemplateProcessor): Objeto procesador de plantilla
- `$texto` (string): Texto HTML con etiquetas de formato
- `$placeholder` (string): Placeholder en la plantilla a reemplazar

**Retorna:**
`TemplateProcessor` - Objeto procesador modificado

**Excepciones:**
- Ninguna

**Dependencias externas:**
- Ninguna

**Ejemplo de uso:**
```php
$texto = 'Este es un texto con <strong>negrita</strong> y <em>cursiva</em>';
$template = CertificadosService::creaParrafoComplejo($template, $texto, 'parrafo1');
```

**Notas:**
- Soporta etiquetas: strong, b, em, i, u, s, strike
- Convierte HTML a TextRun de PhpWord

---

##### 3. creaParrafo()

**Línea:** 89

**Firma:**
```php
public static function creaParrafo(
    TemplateProcessor $templateProcessor,
    string $texto,
    string $placeholder
): TemplateProcessor
```

**Descripción:**
Crea un párrafo simple sin formato especial.

**Parámetros:**
- `$templateProcessor` (TemplateProcessor): Objeto procesador de plantilla
- `$texto` (string): Texto plano
- `$placeholder` (string): Placeholder en la plantilla

**Retorna:**
`TemplateProcessor` - Objeto procesador modificado

**Excepciones:**
- Ninguna

**Dependencias externas:**
- Ninguna

**Ejemplo de uso:**
```php
$template = CertificadosService::creaParrafo($template, 'Texto simple', 'parrafo1');
```

---

##### 4. creaTabla()

**Línea:** 94

**Firma:**
```php
public static function creaTabla(
    TemplateProcessor $templateProcessor,
    array $valores,
    string $primerEnc
): TemplateProcessor
```

**Descripción:**
Crea una tabla dinámica en el certificado, clonando filas según la cantidad de datos.

**Parámetros:**
- `$templateProcessor` (TemplateProcessor): Objeto procesador de plantilla
- `$valores` (array): Array de arrays con los datos de cada fila
- `$primerEnc` (string): Nombre del primer encabezado (para identificar la fila a clonar)

**Retorna:**
`TemplateProcessor` - Objeto procesador modificado

**Excepciones:**
- Ninguna

**Dependencias externas:**
- Ninguna

**Ejemplo de uso:**
```php
$valores = [
    [["col1" => "A"], ["col2" => "1"]],
    [["col1" => "B"], ["col2" => "2"]]
];
$template = CertificadosService::creaTabla($template, $valores, 'col1');
```

**Notas:**
- Clona automáticamente filas según datos
- Los placeholders deben usar formato: `{columna#N}`

---

##### 5. creaFirma()

**Línea:** 109

**Firma:**
```php
public static function creaFirma(
    TemplateProcessor $templateProcessor,
    array $valores,
    string $ruta
): TemplateProcessor
```

**Descripción:**
Inserta una firma digitalizada en el certificado con nombre y cargo.

**Parámetros:**
- `$templateProcessor` (TemplateProcessor): Objeto procesador de plantilla
- `$valores` (array): Array con campos 'uuid', 'nombre', 'cargo'
- `$ruta` (string): Ruta en OpenKM donde se almacenan las firmas

**Retorna:**
`TemplateProcessor` - Objeto procesador modificado

**Excepciones:**
- Ninguna

**Dependencias externas:**
- FirmasService (obtención de imagen de firma)

**Ejemplo de uso:**
```php
$valores = [
    'uuid' => 'firma-uuid-123',
    'nombre' => 'Juan Pérez',
    'cargo' => 'Director'
];
$template = CertificadosService::creaFirma($template, $valores, Config::TAX_FIRMAS);
```

**Notas:**
- Elimina archivo temporal de firma después de insertar
- Los placeholders en la plantilla deben ser: `{firma_imagen}`, `{firma_nombre}`, `{firma_cargo}`

---

##### 6. esComplejo()

**Línea:** 118

**Firma:**
```php
public static function esComplejo(string $texto): bool
```

**Descripción:**
Determina si un texto contiene etiquetas HTML de formato (negrita, cursiva, etc.).

**Parámetros:**
- `$texto` (string): Texto a verificar

**Retorna:**
`bool` - True si contiene etiquetas de formato

**Excepciones:**
- Ninguna

**Dependencias externas:**
- Ninguna

**Ejemplo de uso:**
```php
$complejo = CertificadosService::esComplejo('<strong>Texto</strong>'); // true
$simple = CertificadosService::esComplejo('Texto simple'); // false
```

**Notas:**
- Usa regex para detectar etiquetas: em, strong, b, u, i, s, strike

---

##### 7. htmlToTextRun()

**Línea:** 123

**Firma:**
```php
public static function htmlToTextRun(string $texto): TextRun
```

**Descripción:**
Convierte HTML simple a un objeto TextRun de PhpWord con formatos aplicados.

**Parámetros:**
- `$texto` (string): Texto HTML con etiquetas de formato

**Retorna:**
`TextRun` - Objeto TextRun con formatos aplicados

**Excepciones:**
- Ninguna

**Dependencias externas:**
- PhpOffice\PhpWord

**Ejemplo de uso:**
```php
$textRun = CertificadosService::htmlToTextRun('Texto <strong>negrita</strong>');
```

**Notas:**
- Soporta etiquetas: strong/b (negrita), em/i (cursiva), u (subrayado), s/strike (tachado)
- Procesa múltiples etiquetas en un mismo texto

---

### DocumentService

**Ubicación:** `/app/src/Services/DocumentService.php`
**Namespace:** `RUND\Services`
**Propósito:** Maneja operaciones de documentos de profesores y archivos JSON

#### Métodos Públicos

##### 1. cargaJSON()

**Línea:** 31

**Firma:**
```php
public static function cargaJSON(
    array $dataJSON,
    string $nombreJSON,
    string $path,
    ?string $uuid = null,
    ?string $mensaje = null
): array
```

**Descripción:**
Genera un JSON a partir de un array asociativo y lo carga en OpenKM. Si el archivo ya existe, crea una nueva versión.

**Parámetros:**
- `$dataJSON` (array): Array asociativo con la información que se convertirá a JSON
- `$nombreJSON` (string): Nombre del archivo JSON
- `$path` (string): Ruta completa en la taxonomía de OpenKM
- `$uuid` (string|null): UUID del archivo JSON si ya existe, null si es nuevo (default: null)
- `$mensaje` (string|null): Mensaje para el checkin/checkout cuando se crea una nueva versión (default: null)

**Retorna:**
`array` - Respuesta del proceso de creación o nueva versión del archivo

**Excepciones:**
- `JsonException`: Si hay error en la codificación JSON

**Dependencias externas:**
- OpenKM API

**Ejemplo de uso:**
```php
$data = ['nombre' => 'Juan', 'apellido' => 'Pérez'];
$resultado = DocumentService::cargaJSON(
    $data,
    'profesor_123.json',
    '/okm:root/PROFESORES',
    null,
    'Primera carga'
);
```

**Notas:**
- Usa JSON_PRETTY_PRINT para legibilidad
- Maneja versiones automáticamente con checkout/checkin
- UTF-8 sin escape de caracteres especiales

---

##### 2. addToJSON()

**Línea:** 57

**Firma:**
```php
public static function addToJSON(
    string $nombre,
    string $ruta,
    array $data,
    string $mensaje
): array
```

**Descripción:**
Añade un objeto a un documento JSON que DEBE ser un array de objetos. Si no existe, lo crea.

**Parámetros:**
- `$nombre` (string): Nombre completo del JSON con extensión
- `$ruta` (string): Ruta completa al archivo
- `$data` (array): Objeto (array asociativa) que se añadirá al array
- `$mensaje` (string): Mensaje para el checkin/checkout

**Retorna:**
`array` - Array asociativo generado por OpenKM::verificaCarga()

**Excepciones:**
- Ninguna explícita

**Dependencias externas:**
- OpenKM API

**Ejemplo de uso:**
```php
$nuevaEntrada = ['id' => 'ABC123', 'fecha' => '2024-01-15'];
$resultado = DocumentService::addToJSON(
    'expedidos.json',
    '/okm:root/CERTIFICADOS',
    $nuevaEntrada,
    'Añadido certificado ABC123'
);
```

**Notas:**
- Si el archivo no existe, lo crea con un array conteniendo el primer elemento
- Útil para logs y registros incrementales

---

##### 3. getInfoArchivosProfesor()

**Línea:** 71

**Firma:**
```php
public static function getInfoArchivosProfesor(
    string $cedula,
    bool $demografica = true
): array|null
```

**Descripción:**
Obtiene información a partir de los documentos del profesor (demográfica o de todos los documentos).

**Parámetros:**
- `$cedula` (string): Cédula del profesor (nombre de la carpeta de su hoja de vida)
- `$demografica` (bool): True para información demográfica (solo cédula), false para todos los documentos (default: true)

**Retorna:**
`array|null` - Array con información del documento y categorías, o null si no se encuentra

**Excepciones:**
- Ninguna

**Dependencias externas:**
- OpenKM API

**Ejemplo de uso:**
```php
// Obtener solo datos demográficos
$datosDemo = DocumentService::getInfoArchivosProfesor('12345678', true);

// Obtener todos los documentos
$todosLosDocs = DocumentService::getInfoArchivosProfesor('12345678', false);
```

**Notas:**
- La información demográfica se extrae solo de la cédula
- Para todos los documentos, devuelve array de arrays
- Incluye categorías asignadas

---

##### 4. extraeDatosDocumento()

**Línea:** 95

**Firma:**
```php
public static function extraeDatosDocumento(
    array $nodo,
    string $tax,
    string $cat
): array
```

**Descripción:**
Extrae la información de un documento (nombre y categorías) a partir de un nodo de OpenKM.

**Parámetros:**
- `$nodo` (array): Nodo del documento tal como lo devuelve la API de OpenKM
- `$tax` (string): Ruta base de la taxonomía donde se encuentra el documento
- `$cat` (string): Ruta base de las categorías

**Retorna:**
`array` - Array con campos:
  - `nombre`: Nombre del documento
  - `categorias`: Array de arrays con las categorías asignadas

**Excepciones:**
- Ninguna

**Dependencias externas:**
- Ninguna

**Ejemplo de uso:**
```php
$datos = DocumentService::extraeDatosDocumento(
    $nodo,
    Config::TAX_HOJAS,
    Config::ROOT_CTG_PROF
);
// Resultado: ['nombre' => 'cedula.pdf', 'categorias' => [['PAIS', 'COLOMBIA'], ...]]
```

**Notas:**
- Filtra solo categorías que coincidan con la ruta base especificada
- Retorna categorías como arrays de partes del path

---

##### 5. estructuraCategorias()

**Línea:** 124

**Firma:**
```php
public static function estructuraCategorias(array $categorias): array
```

**Descripción:**
Estructura un array de categorías en un array multidimensional, cambiando los nombres por los labels correspondientes.

**Parámetros:**
- `$categorias` (array): Array de categorías, cada una con 2 o 3 niveles

**Retorna:**
`array` - Array multidimensional con las categorías estructuradas y labels

**Excepciones:**
- Ninguna

**Dependencias externas:**
- OpenKM (archivo labels.json)

**Ejemplo de uso:**
```php
$categoriasPlanas = [
    ['PAIS', 'COLOMBIA'],
    ['CIUDAD', 'BOGOTA'],
    ['CIUDAD', 'MEDELLIN']
];
$estructuradas = DocumentService::estructuraCategorias($categoriasPlanas);
// Resultado: ['País' => ['Colombia'], 'Ciudad' => ['Bogotá', 'Medellín']]
```

**Notas:**
- Convierte códigos a labels legibles
- Maneja categorías de 2 y 3 niveles
- Agrupa valores múltiples en arrays

---

### FirmasService

**Ubicación:** `/app/src/Services/FirmasService.php`
**Namespace:** `RUND\Services`
**Propósito:** Maneja operaciones de gestión de firmas digitalizadas

#### Métodos Públicos

##### 1. getFirmas()

**Línea:** 28

**Firma:**
```php
public static function getFirmas(string $rutaFirmas): array|string
```

**Descripción:**
Obtiene todos los archivos de firma que hay en la ruta correspondiente en OpenKM, sean PNG o JSON.

**Parámetros:**
- `$rutaFirmas` (string): Ruta en OpenKM donde se almacenan las firmas

**Retorna:**
`array|string` - Array con los datos de las firmas encontradas o string con error

**Excepciones:**
- Ninguna explícita

**Dependencias externas:**
- OpenKM API

**Ejemplo de uso:**
```php
$firmas = FirmasService::getFirmas(Config::TAX_FIRMAS);
// Resultado: [
//   ['uuid' => '...', 'mimeType' => 'image/png', 'nombre' => 'firma_director.png'],
//   ['uuid' => '...', 'mimeType' => 'application/json', 'nombre' => 'firma_director.json']
// ]
```

**Notas:**
- Devuelve tanto archivos PNG como JSON side-car
- Cada elemento incluye UUID, mimeType y nombre

---

##### 2. creaFirmaTemp()

**Línea:** 50

**Firma:**
```php
public static function creaFirmaTemp(string $uuid): string
```

**Descripción:**
Descarga de OpenKM un archivo de firma y lo guarda temporalmente en disco.

**Parámetros:**
- `$uuid` (string): UUID del archivo de firma en OpenKM

**Retorna:**
`string` - Ruta completa del archivo temporal generado o string con error

**Excepciones:**
- Ninguna explícita

**Dependencias externas:**
- OpenKM API

**Ejemplo de uso:**
```php
$rutaTemp = FirmasService::creaFirmaTemp('firma-uuid-123');
// Resultado: '/tmp/firma_firma-uuid-123.png'
```

**Notas:**
- Archivo temporal debe eliminarse después de usar
- Siempre guarda como PNG
- Prefijo 'firma_' en el nombre del archivo

---

### LBService

**Ubicación:** `/app/src/Services/LBService.php`
**Namespace:** `RUND\Services`
**Propósito:** Maneja operaciones de conversión de archivos Office a PDF usando LibreOffice

#### Métodos Públicos

##### 1. convierteExcelToPDF()

**Línea:** 29

**Firma:**
```php
public static function convierteExcelToPDF(
    string $excel,
    string $dir = Config::TEMP_DIR
): array
```

**Descripción:**
Convierte un archivo de Excel a PDF usando LibreOffice en modo headless.

**Parámetros:**
- `$excel` (string): Nombre del archivo Excel a convertir (debe estar en el directorio temporal)
- `$dir` (string): Directorio donde se encuentra el archivo y donde se guardará el PDF (default: Config::TEMP_DIR)

**Retorna:**
`array` - Array con campos:
  - `error`: null si no hay error, mensaje si falla
  - `salida`: Ruta del PDF generado

**Excepciones:**
- Ninguna explícita

**Dependencias externas:**
- LibreOffice (ejecutable)

**Ejemplo de uso:**
```php
$resultado = LBService::convierteExcelToPDF('reporte.xlsx');
if ($resultado['error'] === null) {
    $pdfPath = $resultado['salida'];
    // Usar PDF...
}
```

**Notas:**
- Usa filtro 'calc_pdf_Export' de LibreOffice
- Requiere LibreOffice instalado en el servidor

---

##### 2. convierteWordToPDF()

**Línea:** 40

**Firma:**
```php
public static function convierteWordToPDF(
    string $word = "certificado.docx",
    string $dir = Config::TEMP_DIR
): array
```

**Descripción:**
Convierte un archivo de Word a PDF usando LibreOffice en modo headless.

**Parámetros:**
- `$word` (string): Nombre del archivo Word a convertir (default: 'certificado.docx')
- `$dir` (string): Directorio donde se encuentra el archivo (default: Config::TEMP_DIR)

**Retorna:**
`array` - Array con campos:
  - `error`: null si no hay error, mensaje si falla
  - `salida`: Ruta del PDF generado

**Excepciones:**
- Ninguna explícita

**Dependencias externas:**
- LibreOffice (ejecutable)

**Ejemplo de uso:**
```php
$resultado = LBService::convierteWordToPDF('certificado.docx');
if ($resultado['error'] === null) {
    header('Content-Type: application/pdf');
    readfile($resultado['salida']);
}
```

**Notas:**
- Usa filtro 'writer_pdf_Export' de LibreOffice
- El PDF resultante tendrá el mismo nombre que el archivo original

---

##### 3. convierteOfficeToPDF()

**Línea:** 52

**Firma:**
```php
public static function convierteOfficeToPDF(
    string $file,
    string $handler,
    string $dir = Config::TEMP_DIR
): array
```

**Descripción:**
Función interna que maneja la conversión de archivos de Office a PDF usando LibreOffice en modo headless.

**Parámetros:**
- `$file` (string): Nombre del archivo a convertir
- `$handler` (string): Handler de conversión específico para LibreOffice ('calc_pdf_Export' para Excel, 'writer_pdf_Export' para Word)
- `$dir` (string): Directorio de trabajo (default: Config::TEMP_DIR)

**Retorna:**
`array` - Array con campos:
  - `error`: null si no hay error
  - `salida`: Ruta del PDF generado
  - `rtn`: Código de retorno del comando (si hay error)

**Excepciones:**
- Ninguna explícita

**Dependencias externas:**
- LibreOffice (ejecutable definido en $_ENV["LIBREOFFICE_EXECUTABLE"])

**Ejemplo de uso:**
```php
$resultado = LBService::convierteOfficeToPDF(
    'documento.xlsx',
    'calc_pdf_Export',
    '/tmp/'
);
```

**Notas:**
- Método privado usado internamente
- Usa escapeshellcmd y escapeshellarg para seguridad
- Suprime stderr con 2>/dev/null

---

### QRService

**Ubicación:** `/app/src/Services/QRService.php`
**Namespace:** `RUND\Services`
**Propósito:** Maneja la generación de códigos QR para validación de certificados

**Dependencias:**
- Endroid\QrCode

#### Métodos Públicos

##### 1. creaQR()

**Línea:** 39

**Firma:**
```php
public static function creaQR(string $id): array
```

**Descripción:**
Genera un código QR para validación de certificados con un ID alfanumérico de 16 caracteres.

**Parámetros:**
- `$id` (string): Cadena alfanumérica de exactamente 16 caracteres

**Retorna:**
`array` - Array con campos:
  - `qr`: Ruta del archivo PNG generado
  - `url`: URL de validación completa
  - `id`: ID del certificado

**Excepciones:**
- `InvalidArgumentException`: Si el ID no tiene el formato correcto
- `RuntimeException`: Si hay problemas al generar o guardar el QR

**Dependencias externas:**
- Ninguna

**Ejemplo de uso:**
```php
$resultado = QRService::creaQR('ABC123XYZ4567890');
// Resultado: [
//   'qr' => '/tmp/qr_ABC123XYZ4567890.png',
//   'url' => 'http://localhost:4000/validacion?certificado=ABC123XYZ4567890',
//   'id' => 'ABC123XYZ4567890'
// ]
```

**Notas:**
- Valida formato de ID con regex: `/^[a-zA-Z0-9]{16}$/`
- Genera imagen PNG de 300x300px
- Margen de 10px
- URL fija en desarrollo (configurable)

---

##### 2. creaQRConLogo()

**Línea:** 108

**Firma:**
```php
public static function creaQRConLogo(string $id, string $logoPath): array
```

**Descripción:**
Genera QR con logo personalizado (funcionalidad futura, actualmente sin implementar el logo).

**Parámetros:**
- `$id` (string): Cadena alfanumérica de 16 caracteres
- `$logoPath` (string): Ruta del archivo de logo

**Retorna:**
`array` - Array con campos:
  - `qr`: Ruta del archivo PNG
  - `url`: URL de validación
  - `id`: ID del certificado
  - `logo`: Ruta del logo usado

**Excepciones:**
- `InvalidArgumentException`: Si el ID no es válido o el logo no existe

**Dependencias externas:**
- Ninguna

**Ejemplo de uso:**
```php
$resultado = QRService::creaQRConLogo(
    'ABC123XYZ4567890',
    '/path/to/logo.png'
);
```

**Notas:**
- Funcionalidad en desarrollo
- Actualmente genera QR sin logo
- Valida existencia del archivo de logo

---

##### 3. validarQR()

**Línea:** 148

**Firma:**
```php
public static function validarQR(string $id): bool
```

**Descripción:**
Valida si un código QR existe y es válido verificando el archivo en disco.

**Parámetros:**
- `$id` (string): ID del certificado a validar

**Retorna:**
`bool` - True si el QR existe y es válido

**Excepciones:**
- Ninguna

**Dependencias externas:**
- Ninguna

**Ejemplo de uso:**
```php
$esValido = QRService::validarQR('ABC123XYZ4567890');
if ($esValido) {
    echo "Certificado válido";
}
```

**Notas:**
- Verifica formato del ID
- Verifica existencia y tamaño del archivo
- Útil para endpoints de validación

---

##### 4. limpiarQRsAntiguos()

**Línea:** 161

**Firma:**
```php
public static function limpiarQRsAntiguos(int $dias = 7): int
```

**Descripción:**
Limpia códigos QR temporales antiguos basándose en la fecha de modificación.

**Parámetros:**
- `$dias` (int): Número de días de antigüedad para considerar un QR como antiguo (default: 7)

**Retorna:**
`int` - Cantidad de archivos eliminados

**Excepciones:**
- Ninguna

**Dependencias externas:**
- Ninguna

**Ejemplo de uso:**
```php
$eliminados = QRService::limpiarQRsAntiguos(7);
echo "Se eliminaron $eliminados códigos QR antiguos";
```

**Notas:**
- Usa glob para buscar archivos qr_*.png
- Compara filemtime con tiempo límite
- Útil para tareas de mantenimiento/cron

---

#### Métodos Privados

##### buildValidationUrl()

**Línea:** 72

**Firma:**
```php
private static function buildValidationUrl(string $id): string
```

**Descripción:**
Construye la URL de validación del certificado con detección automática de protocolo y host.

**Parámetros:**
- `$id` (string): ID del certificado

**Retorna:**
`string` - URL completa de validación

**Notas:**
- Actualmente usa URL fija para desarrollo
- Puede cambiar a URL dinámica en producción

---

##### createQRCode()

**Línea:** 91

**Firma:**
```php
private static function createQRCode(string $data): QrCode
```

**Descripción:**
Crea el objeto QRCode con configuración estándar.

**Parámetros:**
- `$data` (string): Datos a codificar en el QR

**Retorna:**
`QrCode` - Objeto QRCode configurado

**Notas:**
- Encoding UTF-8
- Error correction: Low
- Tamaño: 300x300px
- Colores: negro sobre blanco

---

### ReportesService

**Ubicación:** `/app/src/Services/ReportesService.php`
**Namespace:** `RUND\Services`
**Propósito:** Maneja operaciones de generación de reportes Excel con gráficos

**Dependencias:**
- PhpOffice\PhpSpreadsheet

#### Métodos Públicos

##### 1. generaReporte()

**Línea:** 46

**Firma:**
```php
public static function generaReporte(
    string $nombrePlantilla,
    string $nombreHoja,
    int $numTotalCols,
    string $dataNomFil,
    string $dataNomCol,
    array $dataCols,
    array $dataFilas,
    int $numTotalFilas
): array
```

**Descripción:**
Genera un reporte en Excel a partir de una plantilla, incluyendo tabla de datos y gráfico de barras.

**Parámetros:**
- `$nombrePlantilla` (string): Nombre del archivo de plantilla en OpenKM
- `$nombreHoja` (string): Nombre de la hoja donde se colocarán los datos
- `$numTotalCols` (int): Número total de columnas de datos
- `$dataNomFil` (string): Nombre de la fila de datos (encabezado principal)
- `$dataNomCol` (string): Nombre de la columna de datos (encabezado secundario)
- `$dataCols` (array): Array con los nombres de las columnas
- `$dataFilas` (array): Array de arrays con las filas de datos (cada fila tiene 'label' y 'data')
- `$numTotalFilas` (int): Número total de filas de datos

**Retorna:**
`array` - Array con dos elementos:
  - `[0]`: Objeto Writer (Xlsx) listo para guardar
  - `[1]`: Ruta de la plantilla temporal utilizada

**Excepciones:**
- Ninguna explícita

**Dependencias externas:**
- OpenKM API (obtención de plantilla)
- PhpSpreadsheet

**Ejemplo de uso:**
```php
[$writer, $tempPath] = ReportesService::generaReporte(
    'plantilla_reporte.xlsx',
    'Informe 2024',
    3,
    'Género',
    'Nivel Académico',
    ['Pregrado', 'Maestría', 'Doctorado'],
    [
        ['label' => 'Masculino', 'data' => [10, 5, 2]],
        ['label' => 'Femenino', 'data' => [12, 8, 3]]
    ],
    2
);

$writer->save('reporte_final.xlsx');
unlink($tempPath);
```

**Notas:**
- Crea automáticamente gráfico de barras
- Aplica formato automático (negrita, bordes, alineación)
- Ajusta automáticamente ancho de columnas
- Configura página para impresión (fit to width)

---

##### 2. autoFitCols()

**Línea:** 118

**Firma:**
```php
public static function autoFitCols(Worksheet $hoja): void
```

**Descripción:**
Autoajusta el ancho de todas las columnas en una hoja de cálculo.

**Parámetros:**
- `$hoja` (Worksheet): Hoja de cálculo donde se ajustarán las columnas

**Retorna:**
`void`

**Excepciones:**
- Ninguna

**Dependencias externas:**
- PhpSpreadsheet

**Ejemplo de uso:**
```php
ReportesService::autoFitCols($spreadsheet->getActiveSheet());
```

**Notas:**
- Itera por todas las columnas existentes
- Útil para mejorar legibilidad de reportes

---

##### 3. creaTablaDatos()

**Línea:** 137

**Firma:**
```php
public static function creaTablaDatos(
    array $inicio,
    Worksheet $hoja,
    string $dataNomFil,
    array $posIniCol,
    array $posFinCol,
    string $dataNomCol,
    array $dataCols,
    array $dataFilas
): array
```

**Descripción:**
Crea una tabla de datos en una hoja de cálculo a partir de los datos proporcionados.

**Parámetros:**
- `$inicio` (array): Posición inicial [columna, fila] donde se colocará la tabla
- `$hoja` (Worksheet): Hoja de cálculo donde se creará la tabla
- `$dataNomFil` (string): Nombre de la fila de datos (encabezado principal)
- `$posIniCol` (array): Posición [columna, fila] de la primera columna de datos
- `$posFinCol` (array): Posición [columna, fila] de la última columna de datos
- `$dataNomCol` (string): Nombre de la columna de datos (encabezado secundario)
- `$dataCols` (array): Array con los nombres de las columnas
- `$dataFilas` (array): Array de arrays con las filas de datos

**Retorna:**
`array` - Array con las posiciones finales:
  - `posNomCol`: Posición de la última celda de nombre de columna
  - `posLabel`: Posición de la última celda de label de fila
  - `posData`: Posición de la última celda de datos

**Excepciones:**
- Ninguna

**Dependencias externas:**
- PhpSpreadsheet

**Ejemplo de uso:**
```php
$posiciones = ReportesService::creaTablaDatos(
    [1, 1],
    $hoja,
    'Género',
    [2, 1],
    [4, 1],
    'Nivel Académico',
    ['Pregrado', 'Maestría', 'Doctorado'],
    [['label' => 'M', 'data' => [10, 5, 2]]]
);
```

**Notas:**
- Maneja merge de celdas para encabezados
- Usa DataType::TYPE_NUMERIC para valores numéricos
- Las posiciones son arrays [columna, fila] basados en 1

---

##### 4. creaGraficoBarras()

**Línea:** 188

**Firma:**
```php
public static function creaGraficoBarras(
    array $valores,
    array $categorias,
    array $etiquetas,
    string $titulo,
    Worksheet $hoja,
    array $posGrafico
): void
```

**Descripción:**
Crea un gráfico de barras en una hoja de cálculo a partir de los datos proporcionados.

**Parámetros:**
- `$valores` (array): Array de DataSeriesValues con los valores de las series
- `$categorias` (array): Array de DataSeriesValues con las categorías del eje X
- `$etiquetas` (array): Array de DataSeriesValues con las etiquetas de las series
- `$titulo` (string): Título del gráfico
- `$hoja` (Worksheet): Hoja de cálculo donde se creará el gráfico
- `$posGrafico` (array): Array con dos elementos: [celda superior izquierda, celda inferior derecha] (ej. ['E1', 'M15'])

**Retorna:**
`void`

**Excepciones:**
- Ninguna

**Dependencias externas:**
- PhpSpreadsheet

**Ejemplo de uso:**
```php
ReportesService::creaGraficoBarras(
    $valores,
    $categorias,
    $etiquetas,
    'Distribución por Nivel Académico según Género',
    $hoja,
    ['E10', 'M22']
);
```

**Notas:**
- Tipo: Gráfico de barras agrupadas (clustered)
- Leyenda posicionada a la derecha
- Plot visible con dirección de columna

---

##### 5. arrayToCell()

**Línea:** 231

**Firma:**
```php
public static function arrayToCell(
    array $array,
    bool $colFija = false,
    bool $linFija = false
)
```

**Descripción:**
Convierte un array de posición [column, row] en una referencia de celda A1.

**Parámetros:**
- `$array` (array): Array con la posición [columna, fila] (ej. [1, 1] para A1)
- `$colFija` (bool): Indica si la columna debe ser fija con $ (default: false)
- `$linFija` (bool): Indica si la fila debe ser fija con $ (default: false)

**Retorna:**
`string` - Celda en formato A1 (ej. 'A1', '$A$1', 'B$5')

**Excepciones:**
- Ninguna

**Dependencias externas:**
- Ninguna

**Ejemplo de uso:**
```php
$celda = ReportesService::arrayToCell([1, 1]); // 'A1'
$celdaFija = ReportesService::arrayToCell([1, 1], true, true); // '$A$1'
$celdaMixta = ReportesService::arrayToCell([2, 5], false, true); // 'B$5'
```

**Notas:**
- Columnas basadas en ASCII (A=65)
- Útil para referencias de rangos en fórmulas
- Soporta referencias absolutas y relativas

---

## Handlers

### AIHandlers

**Ubicación:** `/app/src/Handlers/AIHandlers.php`
**Namespace:** `RUND\Handlers`
**Propósito:** Gestiona peticiones HTTP relacionadas con AI y OCR

#### Métodos Públicos

##### 1. extraeDatos()

**Línea:** 21

**Firma:**
```php
public static function extraeDatos(array $params, array $files): array
```

**Descripción:**
Gestiona la extracción de datos de documentos usando AI/OCR según la acción especificada.

**Parámetros:**
- `$params` (array): Parámetros POST con campos:
  - `accion`: Tipo de acción ('documento')
  - `tipoDocumento`: Tipo de documento a procesar
  - `datosExtraer`: JSON con estructura de datos a extraer
- `$files` (array): Archivos enviados con campo 'documento'

**Retorna:**
`array` - Resultado del análisis o error

**Excepciones:**
- Ninguna (retorna array con campo 'error')

**Dependencias externas:**
- AIService

**Ejemplo de uso:**
```php
$params = [
    'accion' => 'documento',
    'tipoDocumento' => 'documento_identidad',
    'datosExtraer' => json_encode(['nombres' => null, 'apellidos' => null])
];
$files = ['documento' => ['tmp_name' => '/tmp/upload123', 'name' => 'cedula.pdf']];

$resultado = AIHandlers::extraeDatos($params, $files);
```

**Notas:**
- Valida presencia de parámetros requeridos
- Copia archivo temporal antes de procesar
- Actualmente solo soporta acción 'documento'

---

### CategoriasHandlers

**Ubicación:** `/app/src/Handlers/CategoriasHandlers.php`
**Namespace:** `RUND\Handlers`
**Propósito:** Gestiona peticiones HTTP relacionadas con categorías

#### Métodos Públicos

##### 1. getCategorias()

**Línea:** 28

**Firma:**
```php
public static function getCategorias(): array
```

**Descripción:**
Devuelve un árbol de categorías (no Taxonomía) desde la categoría principal de OpenKM.

**Parámetros:**
Ninguno

**Retorna:**
`array` - Array representando el árbol de categorías o array con 'error'

**Excepciones:**
- Ninguna

**Dependencias externas:**
- OpenKM API
- CategoriasService

**Ejemplo de uso:**
```php
$arbolCategorias = CategoriasHandlers::getCategorias();
if (!isset($arbolCategorias['error'])) {
    // Procesar árbol...
}
```

**Notas:**
- Obtiene UUID de carpeta raíz de categorías
- Genera árbol completo recursivamente
- Incluye URLs de consulta para debug

---

##### 2. getCruce()

**Línea:** 53

**Firma:**
```php
public static function getCruce(string $x, string $y): array
```

**Descripción:**
Cruza dos categorías y devuelve una matriz de dos dimensiones con coincidencias de documentos.

**Parámetros:**
- `$x` (string): UUID de la categoría para las columnas
- `$y` (string): UUID de la categoría para las filas

**Retorna:**
`array` - Array con campos:
  - `nomCol`: Nombre de la categoría X
  - `nomFil`: Nombre de la categoría Y
  - `cols`: Array de etiquetas de subcategorías X
  - `filas`: Array de arrays con 'label' y 'data' (conteo de coincidencias)

**Excepciones:**
- Ninguna (retorna array con 'error' si faltan parámetros)

**Dependencias externas:**
- OpenKM API
- CategoriasService
- Utils

**Ejemplo de uso:**
```php
$cruce = CategoriasHandlers::getCruce($uuidGenero, $uuidNivel);
// Resultado: matriz de conteo de profesores por género y nivel académico
```

**Notas:**
- Útil para análisis estadísticos
- Cuenta documentos que coinciden en ambas categorías
- Genera estructura lista para tablas y gráficos

---

#### Métodos Privados

##### getHijosCompletos()

**Línea:** 89

**Firma:**
```php
private static function getHijosCompletos(string $padreUUID): array
```

**Descripción:**
Obtiene los hijos completos (con uuid, label, path) de una categoría.

**Parámetros:**
- `$padreUUID` (string): UUID de la categoría padre

**Retorna:**
`array` - Array de objetos completos de categorías hijas

**Notas:**
- Extrae labels de archivo labels.json
- Maneja tanto un solo hijo como múltiples

---

### CertificadosHandlers

**Ubicación:** `/app/src/Handlers/CertificadosHandlers.php`
**Namespace:** `RUND\Handlers`
**Propósito:** Gestiona peticiones HTTP relacionadas con certificados

#### Métodos Públicos

##### 1. getCertificado()

**Línea:** 35

**Firma:**
```php
public static function getCertificado(array $postData): void
```

**Descripción:**
Maneja la generación y descarga de certificados en formato DOCX o PDF.

**Parámetros:**
- `$postData` (array): Datos enviados en la petición POST con campos:
  - `data`: JSON con la estructura para rellenar la plantilla
  - `plantilla`: Nombre de la plantilla (sin extensión)
  - `tipo` o `formato`: 'docx' o 'pdf' para el formato de salida
  - `id` (opcional): ID del certificado para evitar duplicados

**Retorna:**
`void` - Envía el archivo generado al navegador para descarga

**Excepciones:**
- Ninguna explícita

**Dependencias externas:**
- OpenKM API
- CertificadosService
- DocumentService
- LBService
- Utils

**Ejemplo de uso:**
```php
$postData = [
    'data' => json_encode([
        ['tipo' => 'parrafo', 'value' => 'Certificado de...'],
        ['tipo' => 'firma', 'value' => ['uuid' => '...', 'nombre' => '...', 'cargo' => '...']]
    ]),
    'plantilla' => 'certificado_laboral',
    'formato' => 'pdf'
];

CertificadosHandlers::getCertificado($postData);
```

**Notas:**
- Genera ID único si no se proporciona
- Almacena certificado en expedidos.json
- Limpia archivos temporales después de enviar
- Establece headers HTTP apropiados para descarga

---

##### 2. getCertificadoInfo()

**Línea:** 88

**Firma:**
```php
public static function getCertificadoInfo(string $id): array|null
```

**Descripción:**
Maneja la obtención de la información completa de un certificado por su ID.

**Parámetros:**
- `$id` (string): ID del certificado a buscar

**Retorna:**
`array|null` - Array con la información del certificado o array con 'error' si no se encuentra

**Excepciones:**
- Ninguna

**Dependencias externas:**
- OpenKM API
- Utils

**Ejemplo de uso:**
```php
$info = CertificadosHandlers::getCertificadoInfo('ABC123XYZ4567890');
if (!isset($info['error'])) {
    $plantilla = $info['plantilla'];
    $data = $info['data'];
}
```

**Notas:**
- Busca en archivo expedidos.json
- Retorna null si no existe el archivo
- Útil para endpoint de validación de certificados

---

### DataHandlers

**Ubicación:** `/app/src/Handlers/DataHandlers.php`
**Namespace:** `RUND\Handlers`
**Propósito:** Gestiona peticiones HTTP de obtención de datos planos e imágenes

#### Métodos Públicos

##### 1. getCsvData()

**Línea:** 30

**Firma:**
```php
public static function getCsvData(array $params): array
```

**Descripción:**
Maneja la obtención de datos CSV desde OpenKM y su conversión a JSON.

**Parámetros:**
- `$params` (array): Parámetros necesarios con campos:
  - `categoria`: Categoría del documento
  - `tipo`: Tipo de documento
  - `nombre`: Nombre del archivo (sin extensión)
  - `extension`: Extensión del archivo (ej. '.csv')

**Retorna:**
`array` - Datos CSV procesados con campos:
  - `arrayCSV`: Array de arrays con los datos
  - `columnasCSV`: Array de objetos (JSON por columnas)
  - `rawCSV`: Contenido original del CSV
  - `error`: Mensaje de error si falla

**Excepciones:**
- Ninguna (retorna array con 'error')

**Dependencias externas:**
- OpenKM API
- Utils

**Ejemplo de uso:**
```php
$params = [
    'categoria' => 'PROFESORES',
    'tipo' => 'ACTIVOS',
    'nombre' => 'listado_2024',
    'extension' => '.csv'
];

$resultado = DataHandlers::getCsvData($params);
if (!isset($resultado['error'])) {
    $datos = $resultado['columnasCSV'];
}
```

**Notas:**
- Limpia saltos de línea internos del CSV
- Convierte CSV a formato JSON por columnas
- Valida que la ruta coincida exactamente

---

##### 2. getImagen()

**Línea:** 80

**Firma:**
```php
public static function getImagen(array $params): void
```

**Descripción:**
Maneja la obtención y envío de una imagen almacenada en OpenKM.

**Parámetros:**
- `$params` (array): Parámetros necesarios con campos:
  - `ruta`: Ruta dentro de la taxonomía de documentos (ej. 'FOTOS/2023/PROFESORES')
  - `nombre`: Nombre del archivo de imagen con extensión (ej. 'foto.png')

**Retorna:**
`void` - Envía la imagen directamente al navegador

**Excepciones:**
- Ninguna explícita

**Dependencias externas:**
- OpenKM API
- Utils

**Ejemplo de uso:**
```php
$params = [
    'ruta' => 'FOTOS/2023/PROFESORES',
    'nombre' => 'foto_12345678.png'
];

DataHandlers::getImagen($params);
```

**Notas:**
- Establece headers Content-Type apropiados
- No retorna valor, envía imagen directamente
- Útil para endpoints de imágenes

---

##### 3. getInfoProfesor()

**Línea:** 92

**Firma:**
```php
public static function getInfoProfesor(string $cedula): array
```

**Descripción:**
Devuelve la información demográfica y de documentos de un profesor a partir de su cédula.

**Parámetros:**
- `$cedula` (string): Cédula del profesor (4-20 dígitos numéricos)

**Retorna:**
`array` - Objeto con campos:
  - `archivosProfesor`: Array de documentos almacenados
  - `datosDemograficos`: Información demográfica extraída
  - `error`: Mensaje de error si falla

**Excepciones:**
- Ninguna (retorna array con 'error')

**Dependencias externas:**
- DocumentService

**Ejemplo de uso:**
```php
$info = DataHandlers::getInfoProfesor('12345678');
if (!isset($info['error'])) {
    $archivos = $info['archivosProfesor'];
    $demograficos = $info['datosDemograficos'];
}
```

**Notas:**
- Valida formato de cédula con regex
- Estructura las categorías en formato legible
- Retorna mensaje si no hay datos registrados

---

### FileHandlers

**Ubicación:** `/app/src/Handlers/FileHandlers.php`
**Namespace:** `RUND\Handlers`
**Propósito:** Gestiona peticiones HTTP relacionadas con archivos (carga, descarga, reportes)

#### Métodos Públicos

##### 1. getConsultaFile()

**Línea:** 38

**Firma:**
```php
public static function getConsultaFile(
    array $postData,
    string $tipo,
    string $nombrePlantilla = "plantilla_reporte.xlsx"
): void
```

**Descripción:**
Genera un archivo de consulta (Excel o PDF) a partir de los datos proporcionados.

**Parámetros:**
- `$postData` (array): Datos de la consulta con campos:
  - `cols`: Array de nombres de columnas
  - `filas`: Array de arrays representando filas de datos
  - `nomCol`: Nombre de la categoría para las columnas
  - `nomFil`: Nombre de la categoría para las filas
- `$tipo` (string): Tipo de archivo a generar ('xlsx' o 'pdf')
- `$nombrePlantilla` (string): Nombre del archivo de plantilla (default: 'plantilla_reporte.xlsx')

**Retorna:**
`void` - Imprime directamente la respuesta (archivo) con headers HTTP apropiados

**Excepciones:**
- Ninguna explícita

**Dependencias externas:**
- ReportesService
- LBService

**Ejemplo de uso:**
```php
$postData = [
    'cols' => ['Pregrado', 'Maestría', 'Doctorado'],
    'filas' => [
        ['label' => 'M', 'data' => [10, 5, 2]],
        ['label' => 'F', 'data' => [12, 8, 3]]
    ],
    'nomCol' => 'Nivel Académico',
    'nomFil' => 'Género'
];

FileHandlers::getConsultaFile($postData, 'pdf');
```

**Notas:**
- Genera archivo y lo envía directamente al navegador
- Limpia archivos temporales después de enviar
- Establece headers para descarga automática

---

##### 2. deleteReport()

**Línea:** 88

**Firma:**
```php
public static function deleteReport(): array
```

**Descripción:**
Borra los archivos temporales generados para los reportes (reporte.xlsx y reporte.pdf).

**Parámetros:**
Ninguno

**Retorna:**
`array` - Array con campos:
  - `borrados`: Archivos que se han borrado exitosamente
  - `aBorrar`: Archivos que se intentaron borrar

**Excepciones:**
- Ninguna

**Dependencias externas:**
- Ninguna

**Ejemplo de uso:**
```php
$resultado = FileHandlers::deleteReport();
echo "Borrados: " . count($resultado['borrados']);
```

**Notas:**
- Útil para limpieza después de generar reportes
- Solo borra si los archivos existen
- No genera error si los archivos no existen

---

##### 3. loadList()

**Línea:** 110

**Firma:**
```php
public static function loadList(
    string $method,
    array $params,
    array $files
): array
```

**Descripción:**
Carga un listado de administración de profesores o identifica el UUID si ya está cargado.

**Parámetros:**
- `$method` (string): Método HTTP usado (debe ser POST para cargar el archivo)
- `$params` (array): Parámetros con campos:
  - `accion`: 'cargar' para cargar el archivo o 'duplicado' para verificar si ya existe
  - `propiedades`: JSON con las propiedades del archivo (Nombre, Tipo, Origen, Formato, Size, etc.)
- `$files` (array): Archivos enviados (debe incluir 'archivo' si la acción es 'cargar')

**Retorna:**
`array` - Array con la respuesta de la operación

**Excepciones:**
- Ninguna (retorna array con 'error')

**Dependencias externas:**
- OpenKM API
- CategoriasService
- Utils

**Ejemplo de uso:**
```php
$params = [
    'accion' => 'cargar',
    'propiedades' => json_encode([
        ['label' => 'Nombre', 'valor' => 'listado_2024.csv'],
        ['label' => 'Tipo', 'valor' => 'Profesores Activos'],
        ['label' => 'Origen', 'valor' => 'Excel']
    ])
];
$files = ['archivo' => ['tmp_name' => '/tmp/upload', 'name' => 'listado.csv']];

$resultado = FileHandlers::loadList('POST', $params, $files);
```

**Notas:**
- Verifica duplicados antes de cargar
- Crea categorías automáticamente
- Actualiza propiedades del documento en OpenKM

---

##### 4. postFile()

**Línea:** 170

**Firma:**
```php
public static function postFile(array $params, array $files): array
```

**Descripción:**
Maneja la carga de archivos en OpenKM. Pueden ser firmas (PNG y JSON side-car) o documentos (Cédula, etc.).

**Parámetros:**
- `$params` (array): Parámetros con campos:
  - `accion`: 'cargaFirma' para cargar una firma o 'cargaDocumento' para cargar un documento
  - `propiedades`: JSON con las propiedades del archivo
- `$files` (array): Archivos enviados (debe incluir 'archivo')

**Retorna:**
`array` - Array con la respuesta de la operación

**Excepciones:**
- Ninguna (retorna array con 'error')

**Dependencias externas:**
- OpenKM API
- CategoriasService
- DocumentService
- Utils

**Ejemplo de uso:**
```php
// Cargar firma
$params = [
    'accion' => 'cargaFirma',
    'propiedades' => json_encode([
        ['label' => 'cargo', 'valor' => 'Director'],
        ['label' => 'nombres', 'valor' => 'Juan'],
        ['label' => 'apellidos', 'valor' => 'Pérez']
    ])
];
$files = ['archivo' => ['name' => 'firma_director.png', 'tmp_name' => '/tmp/upload']];

$resultado = FileHandlers::postFile($params, $files);
```

**Notas:**
- Para firmas, carga PNG y genera JSON side-car automáticamente
- Para documentos, valida cédula y crea taxonomía recursivamente
- Asigna categorías automáticamente según tipo, formato y origen

---

##### 5. getFile()

**Línea:** 271

**Firma:**
```php
public static function getFile(string $tipo, string $nombre): array|null
```

**Descripción:**
Maneja la obtención de archivos desde OpenKM. Pueden ser archivos de datos (Excel, CSV) o imágenes.

**Parámetros:**
- `$tipo` (string): Tipo de archivo a obtener ('data' para archivos de datos o 'imagen' para imágenes)
- `$nombre` (string): Nombre del archivo a obtener

**Retorna:**
`array|null` - Array con el contenido en base64 si es un archivo de datos, o null si es una imagen (se envía directamente al navegador)

**Excepciones:**
- Ninguna (retorna array con 'error' si el tipo no existe)

**Dependencias externas:**
- OpenKM API

**Ejemplo de uso:**
```php
// Obtener archivo de datos
$datos = FileHandlers::getFile('data', 'labels.json');

// Obtener imagen (se envía directamente)
FileHandlers::getFile('imagen', 'logo.png');
```

**Notas:**
- Para 'data' retorna contenido base64
- Para 'imagen' establece headers y envía contenido directamente
- Útil para endpoints de descarga de archivos

---

### FirmasHandlers

**Ubicación:** `/app/src/Handlers/FirmasHandlers.php`
**Namespace:** `RUND\Handlers`
**Propósito:** Gestiona peticiones HTTP relacionadas con firmas digitalizadas

#### Métodos Públicos

##### 1. getFirmas()

**Línea:** 32

**Firma:**
```php
public static function getFirmas(array $getParams): void
```

**Descripción:**
Gestiona la obtención de firmas digitalizadas desde OpenKM. Si no se proporcionan parámetros, devuelve un listado de todas las firmas. Si se proporcionan UUID y mimeType, descarga y devuelve el archivo específico.

**Parámetros:**
- `$getParams` (array): Parámetros GET de la petición:
  - `uuid` (opcional): UUID del archivo de firma en OpenKM
  - `mimeType` (opcional): Tipo MIME del archivo de firma (ej. 'image/png')

**Retorna:**
`void` - Imprime directamente la respuesta (JSON o archivo) con headers HTTP apropiados

**Excepciones:**
- Ninguna explícita

**Dependencias externas:**
- OpenKM API
- FirmasService

**Ejemplo de uso:**
```php
// Obtener listado de firmas
FirmasHandlers::getFirmas([]);
// Output: JSON con array de firmas

// Descargar firma específica
FirmasHandlers::getFirmas([
    'uuid' => 'firma-uuid-123',
    'mimeType' => 'image/png'
]);
// Output: Archivo PNG
```

**Notas:**
- Sin parámetros: retorna JSON con listado
- Con parámetros: envía archivo binario
- Establece headers Content-Type apropiados según el caso

---

## Resumen de Dependencias

### Services - Dependencias Externas

| Service | Dependencias Externas |
|---------|----------------------|
| AIService | rund-ocr (puerto 8000), rund-ai (puerto 11434) |
| CategoriasService | OpenKM API |
| CertificadosService | OpenKM API, PhpOffice\PhpWord |
| DocumentService | OpenKM API |
| FirmasService | OpenKM API |
| LBService | LibreOffice (ejecutable) |
| QRService | Endroid\QrCode |
| ReportesService | OpenKM API, PhpOffice\PhpSpreadsheet |

### Handlers - Dependencias de Services

| Handler | Services Utilizados |
|---------|-------------------|
| AIHandlers | AIService |
| CategoriasHandlers | CategoriasService, OpenKM (directo) |
| CertificadosHandlers | CertificadosService, DocumentService, LBService, Utils |
| DataHandlers | DocumentService, OpenKM (directo), Utils |
| FileHandlers | ReportesService, LBService, CategoriasService, DocumentService, OpenKM (directo), Utils |
| FirmasHandlers | FirmasService, OpenKM (directo) |

---

## Notas de Implementación

### Convenciones de Código

1. **Métodos estáticos:** Todos los métodos de Services y Handlers son estáticos
2. **Tipos estrictos:** Todos los archivos usan `declare(strict_types=1);`
3. **Namespace:** Estructura consistente `RUND\Services` y `RUND\Handlers`
4. **Documentación:** PHPDoc completo en encabezados de archivo
5. **Manejo de errores:** Services retornan arrays con campo 'error' en caso de fallo

### Patrones de Diseño

1. **Service Layer Pattern:** Lógica de negocio separada de presentación
2. **Static Methods:** No requieren instanciación
3. **Dependency Injection:** Via parámetros de métodos
4. **Error Handling:** Estructurado con arrays de respuesta

### Mejores Prácticas

1. **Validación:** Siempre validar parámetros antes de procesarlos
2. **Limpieza:** Eliminar archivos temporales después de usarlos
3. **Headers HTTP:** Establecer headers apropiados antes de enviar respuestas
4. **Logging:** Usar error_log() para debugging en métodos críticos
5. **Seguridad:** Usar escapeshellcmd/escapeshellarg para comandos de sistema

---

**Versión:** 2.0
**Fecha:** 2025-10-20
**Autor:** ESAP Development Team / Oliver Castelblanco Martínez
**Contacto:** oliver.castelblanco@esap.edu.co
