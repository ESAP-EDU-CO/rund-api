# 04. INTEGRACIÓN CON OPENKM

## Tabla de Contenidos

1. [Introducción](#1-introducción)
2. [Clase OpenKM.php Completa](#2-clase-openkmphp-completa)
3. [Estructura de OpenKM](#3-estructura-de-openkm)
4. [Operaciones CRUD](#4-operaciones-crud)
5. [Sistema de Versionado](#5-sistema-de-versionado)
6. [Búsquedas y Queries](#6-búsquedas-y-queries)
7. [Categorización](#7-categorización)
8. [Manejo de Errores](#8-manejo-de-errores)
9. [Ejemplos de Uso](#9-ejemplos-de-uso)

---

## 1. Introducción

OpenKM es el sistema de gestión documental (DMS) que utiliza RUND como repositorio central de documentos. La integración se realiza mediante la API REST de OpenKM, utilizando autenticación básica (Basic Auth).

### Características Principales

- **Gestión Documental:** Almacenamiento, versionado y recuperación de documentos
- **Taxonomías:** Estructura jerárquica de carpetas para organización lógica
- **Categorías:** Sistema de etiquetado transversal para clasificación
- **Versionado:** Control completo de versiones con checkout/checkin
- **Búsquedas:** Consultas por nombre, ruta, categorías y metadatos

### Archivo Principal

**Ubicación:** `/app/src/Core/OpenKM.php`

**Namespace:** `RUND\Core`

**Dependencias:**
- `RUND\Config\Config`: Configuración de rutas y credenciales
- `RUND\Core\Utils`: Utilidades generales

---

## 2. Clase OpenKM.php Completa

### 2.1 Configuración de Conexión

La conexión con OpenKM se configura mediante las siguientes constantes en `Config.php`:

```php
const USER = "okmAdmin";
const PASSWORD = "admin";
const REST = "/services/rest/";
```

La URL base se obtiene de la variable de entorno:
```php
$_ENV["CORE_API_URL"]  // Ejemplo: http://rund-core:8080/OpenKM
```

### 2.2 Autenticación y Timeouts

Todas las consultas utilizan **HTTP Basic Authentication**:

```php
curl_setopt($curl, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
curl_setopt($curl, CURLOPT_USERNAME, Config::USER);
curl_setopt($curl, CURLOPT_PASSWORD, Config::PASSWORD);
```

**Timeouts configurados:**
- **Connection Timeout:** 10 segundos
- **Request Timeout:** 30 segundos

**Opciones de Seguridad:**
```php
curl_setopt($curl, CURLOPT_FRESH_CONNECT, true);    // Nueva conexión
curl_setopt($curl, CURLOPT_FORBID_REUSE, true);     // No reutilizar
curl_setopt($curl, CURLOPT_COOKIEJAR, '');          // Sin cookies
```

---

### 2.3 Métodos Públicos Completos (22 métodos)

#### A. UTILIDADES GENÉRICAS

##### 1. `consulta()`
**Firma completa:**
```php
public static function consulta(
    string $consulta,
    string $tipo = "GET",
    array|string|null $postData = null,
    array $headers = []
): string
```

**Descripción:** Realiza consultas HTTP a la API REST de OpenKM.

**Parámetros:**
- `$consulta`: Endpoint relativo (ej: `"document/getContent?docId=123"`)
- `$tipo`: Método HTTP (`"GET"`, `"POST"`, `"PUT"`, `"DELETE"`)
- `$postData`: Datos para POST/PUT (array o string JSON)
- `$headers`: Headers adicionales HTTP

**Retorna:** Respuesta cruda de la API (string)

**Excepciones:**
- `Exception`: Si se solicita POST/PUT sin `postData`

**Ejemplo:**
```php
$response = OpenKM::consulta("folder/getChildren?fldId=abc123");
$data = json_decode($response, true);
```

---

##### 2. `getArchivo()`
**Firma completa:**
```php
public static function getArchivo(string $uuid): string
```

**Descripción:** Obtiene el contenido binario de un archivo.

**Parámetros:**
- `$uuid`: UUID del documento en OpenKM

**Retorna:** Contenido binario del archivo

**Headers especiales:**
```php
["Accept: application/octet-stream"]
```

**Ejemplo:**
```php
$contenido = OpenKM::getArchivo("e4b2c8d1-1234-5678-9abc-def012345678");
file_put_contents("descarga.pdf", $contenido);
```

---

##### 3. `findArchivo()`
**Firma completa:**
```php
public static function findArchivo(string $nombre, string $ruta): string | null
```

**Descripción:** Busca un archivo por nombre y ruta exactos.

**Parámetros:**
- `$nombre`: Nombre exacto del archivo con extensión (case-sensitive)
- `$ruta`: Ruta absoluta en OpenKM

**Retorna:** UUID del archivo o `null` si no existe

**Ejemplo:**
```php
$uuid = OpenKM::findArchivo(
    "certificado_123.pdf",
    "/okm:root/RUND/DOCUMENTOS/CERTIFICADOS"
);
if ($uuid) {
    $archivo = OpenKM::getArchivo($uuid);
}
```

---

##### 4. `borraArchivo()`
**Firma completa:**
```php
public static function borraArchivo(string $uuid): string
```

**Descripción:** Elimina un documento (lo mueve a la papelera).

**Parámetros:**
- `$uuid`: UUID del documento a eliminar

**Retorna:** Respuesta de la API

**Ejemplo:**
```php
$resultado = OpenKM::borraArchivo("abc-123-def-456");
```

---

##### 5. `borraPapelera()`
**Firma completa:**
```php
public static function borraPapelera(): string
```

**Descripción:** Vacía completamente la papelera de OpenKM.

**Retorna:** Respuesta de la API

**Ejemplo:**
```php
$resultado = OpenKM::borraPapelera();
```

---

##### 6. `yaExiste()`
**Firma completa:**
```php
public static function yaExiste(string $nombreArchivo, string $path): bool
```

**Descripción:** Verifica si un archivo existe en una ruta.

**Parámetros:**
- `$nombreArchivo`: Nombre del archivo
- `$path`: Ruta donde buscar

**Retorna:** `true` si existe, `false` si no

**Ejemplo:**
```php
if (OpenKM::yaExiste("firma.png", "/okm:root/RUND/DOCUMENTOS/FIRMAS")) {
    echo "El archivo ya existe";
}
```

---

##### 7. `getPathGeneraLabel()`
**Firma completa:**
```php
public static function getPathGeneraLabel(string $uuid): string
```

**Descripción:** Genera un label legible a partir del UUID de una carpeta, usando `labels.json`.

**Parámetros:**
- `$uuid`: UUID de la carpeta

**Retorna:** Label legible o el UUID si no se encuentra

**Ejemplo:**
```php
$label = OpenKM::getPathGeneraLabel("folder-uuid-123");
// Retorna: "Certificados de Estudio" en lugar del UUID
```

---

##### 8. `getID()`
**Firma completa:**
```php
public static function getID(array $obj): array
```

**Descripción:** Extrae label y UUID de un objeto devuelto por OpenKM.

**Parámetros:**
- `$obj`: Objeto con estructura OpenKM (debe tener `path` y `uuid`)

**Retorna:** Array con `["label" => "...", "uuid" => "..."]`

**Ejemplo:**
```php
$objeto = json_decode($respuesta, true);
$info = OpenKM::getID($objeto);
echo $info["label"];  // "CERTIFICADOS"
echo $info["uuid"];   // "abc-123..."
```

---

##### 9. `getUUIDHijos()`
**Firma completa:**
```php
public static function getUUIDHijos(string $padreUUID): array
```

**Descripción:** Obtiene los UUIDs de las carpetas hijas.

**Parámetros:**
- `$padreUUID`: UUID de la carpeta padre

**Retorna:** Array de UUIDs

**Ejemplo:**
```php
$hijos = OpenKM::getUUIDHijos("parent-folder-uuid");
// ["uuid-1", "uuid-2", "uuid-3"]
```

---

#### B. CONFIG FILE GETTERS

##### 10. `getDataFile()`
**Firma completa:**
```php
public static function getDataFile(
    string $nombre,
    string $ruta = Config::TAX_APP_DATA
): array
```

**Descripción:** Obtiene y parsea un archivo JSON de configuración.

**Parámetros:**
- `$nombre`: Nombre del archivo sin extensión `.json`
- `$ruta`: Ruta en OpenKM (default: `TAX_APP_DATA`)

**Retorna:** Array asociativo con el contenido del JSON

**Archivos comunes:**
- `labels.json`: Mapeo de IDs a nombres legibles
- `config.json`: Configuración general
- `permissions.json`: Permisos de usuario

**Ejemplo:**
```php
$labels = OpenKM::getDataFile("labels");
// ["FIRMAS" => "Firmas Digitales", "LISTADOS" => "Listados de Notas", ...]
```

---

##### 11. `getImageFile()`
**Firma completa:**
```php
public static function getImageFile(
    string $nombre,
    string $ruta = Config::TAX_APP_IMG
): void
```

**Descripción:** Devuelve una imagen directamente con el header MIME correcto y termina la ejecución.

**Parámetros:**
- `$nombre`: Nombre completo de la imagen con extensión
- `$ruta`: Ruta en OpenKM (default: `TAX_APP_IMG`)

**Comportamiento:**
- Detecta el MIME type automáticamente
- Establece el header `Content-Type`
- Imprime el contenido binario
- Ejecuta `exit()` inmediatamente

**Manejo de errores:**
Si la imagen no existe, devuelve JSON:
```json
{"error": "No se encontró la imagen logo.png en la ruta ..."}
```

**Ejemplo de uso:**
```php
// En un endpoint de API
OpenKM::getImageFile("logo.png");
// La función termina aquí con la imagen
```

---

#### C. CREACIÓN DE ARCHIVOS Y CARPETAS

##### 12. `cargaArchivo()`
**Firma completa:**
```php
public static function cargaArchivo(
    array $archivo,
    array $propiedades,
    string $path,
    bool | null $version = null
): array
```

**Descripción:** Gestiona la carga completa de archivos, incluyendo nuevas versiones.

**Parámetros:**

**`$archivo`** (estructura de `$_FILES`):
```php
[
    'error' => 0,                    // Código de error de PHP
    'name' => 'documento.pdf',       // Nombre original
    'tmp_name' => '/tmp/phpXXXXXX',  // Ruta temporal
    'type' => 'application/pdf',     // MIME type
    'size' => 1024000                // Tamaño en bytes
]
```

**`$propiedades`** (metadatos):
```php
[
    ['label' => 'Nombre', 'valor' => 'mi_archivo.pdf'],
    ['label' => 'Uuid', 'valor' => 'uuid-existente'],      // Opcional
    ['label' => 'Comentario', 'valor' => 'Nueva versión']  // Opcional
]
```

**`$path`**: Ruta destino en OpenKM (sin nombre de archivo)

**`$version`**:
- `true`: Actualizar versión existente
- `false`: Crear nuevo documento
- `null`: Auto-detectar

**Retorna:**
```php
[
    'error' => false,                // Estado del error
    'postData' => [...],             // Datos enviados
    'respuesta' => [...],            // Respuesta de OpenKM
    'folderResp' => [...]            // Resultado de creación de carpetas
]
```

**Flujo interno:**
1. Valida errores de carga
2. Extrae metadatos de `$propiedades`
3. Crea carpetas si no existen
4. Verifica si el documento ya existe
5. Ejecuta `nuevaVersion()` o `createSimple` según corresponda
6. Valida la respuesta

**Ejemplo:**
```php
$resultado = OpenKM::cargaArchivo(
    $_FILES['archivo'],
    [['label' => 'Nombre', 'valor' => 'certificado.pdf']],
    Config::TAX_CERTIFICADOS,
    false  // Nuevo documento
);
```

---

##### 13. `creaCarpetas()`
**Firma completa:**
```php
public static function creaCarpetas(array $rutas, string $prefijo): array
```

**Descripción:** Crea estructura de carpetas jerárquica en OpenKM.

**Parámetros:**
- `$rutas`: Array de rutas completas a crear
- `$prefijo`: Raíz base (`Config::ROOT_TAX` o `Config::ROOT_CTG`)

**Comportamiento:**
- Crea carpetas recursivamente nivel por nivel
- Omite carpetas que ya existen
- Retorna log detallado de cada operación

**Retorna:**
```php
[
    [
        'getNodeUuid' => 'PathNotFoundException...',  // o UUID si existe
        'ruta' => '/okm:root/RUND/DOCUMENTOS/NUEVAS',
        'accion' => 'folder/createSimple',
        'consulta' => '{...}'                         // Respuesta JSON
    ],
    // ... más entradas
]
```

**Ejemplo:**
```php
$resultado = OpenKM::creaCarpetas(
    [
        "/DOCUMENTOS/CERTIFICADOS/2025",
        "/DOCUMENTOS/FIRMAS/COORDINADORES"
    ],
    Config::ROOT_TAX
);
```

---

##### 14. `nuevaVersion()`
**Firma completa:**
```php
public static function nuevaVersion(
    string $uuid,
    string $comentario,
    array $postData
): string
```

**Descripción:** Crea una nueva versión de un documento mediante checkout/checkin.

**Parámetros:**
- `$uuid`: UUID del documento a actualizar
- `$comentario`: Comentario de la versión
- `$postData`: Datos del archivo (`docId`, `docPath`, `content`)

**Flujo:**
1. `checkout`: Bloquea el documento para edición
2. Verifica estado de checkout
3. `checkin`: Sube nueva versión y desbloquea

**Retorna:** Respuesta JSON de OpenKM o mensaje de error

**Ejemplo:**
```php
$postData = [
    'docPath' => '/okm:root/RUND/DOCUMENTOS/cert.pdf',
    'content' => new CURLFile($temp, 'application/pdf', 'cert.pdf')
];

$resultado = OpenKM::nuevaVersion(
    "uuid-del-documento",
    "Actualización de formato 2025",
    $postData
);
```

---

##### 15. `documentAction()`
**Firma completa:**
```php
public static function documentAction(string $action, array $postData): string
```

**Descripción:** Ejecuta acciones de documento con manejo de archivos binarios.

**Parámetros:**
- `$action`: Acción a ejecutar (`createSimple`, `checkin`, etc.)
- `$postData`: Datos incluyendo CURLFile para archivos

**Acciones comunes:**
- `createSimple`: Crear nuevo documento
- `checkin`: Subir nueva versión
- `update`: Actualizar metadatos

**Configuración especial:**
```php
// No establece Content-Type para permitir multipart/form-data
curl_setopt($curl, CURLOPT_POSTFIELDS, $postData);
```

**Ejemplo:**
```php
$data = [
    'docPath' => Config::TAX_FIRMAS . "/firma.png",
    'content' => new CURLFile($temp, 'image/png', 'firma.png')
];
$resp = OpenKM::documentAction("createSimple", $data);
```

---

##### 16. `verificaCarga()`
**Firma completa:**
```php
public static function verificaCarga(
    string $resp,
    array $salida,
    array | string | null $folderResp = null
): array
```

**Descripción:** Valida y procesa la respuesta de una carga/actualización.

**Parámetros:**
- `$resp`: Respuesta cruda de OpenKM
- `$salida`: Array base con información de la carga
- `$folderResp`: Respuesta de creación de carpetas (opcional)

**Lógica de validación:**
```php
if (substr($resp, 0, 1) == '{' || substr($resp, 0, 1) == '[' || substr($resp, 0, 1) == "1") {
    // Respuesta exitosa (JSON o boolean true)
    $salida["error"] = false;
} else {
    // Error (texto plano o excepción)
    $salida["error"] = "No se pudo crear el documento";
}
```

**Retorna:** Array actualizado con validación

---

### 2.4 Resumen de Métodos por Categoría

| Categoría | Métodos | Total |
|-----------|---------|-------|
| **Utilidades Genéricas** | `consulta`, `getArchivo`, `findArchivo`, `borraArchivo`, `borraPapelera`, `yaExiste`, `getPathGeneraLabel`, `getID`, `getUUIDHijos` | 9 |
| **Config File Getters** | `getDataFile`, `getImageFile` | 2 |
| **Creación Archivos/Carpetas** | `cargaArchivo`, `creaCarpetas`, `nuevaVersion`, `documentAction`, `verificaCarga` | 5 |
| **TOTAL** | | **16** |

---

## 3. Estructura de OpenKM

### 3.1 Diferencia entre Taxonomía y Categorías

OpenKM utiliza dos sistemas paralelos de organización:

#### A. TAXONOMÍA (`okm:root`)
**Concepto:** Jerarquía de carpetas tradicional (árbol de directorios).

**Características:**
- Organización lógica y jerárquica
- Un documento puede estar en UNA sola ubicación física
- Navegación por estructura de carpetas
- Similar a un sistema de archivos

**Ejemplo:**
```
/okm:root/RUND/
├── DOCUMENTOS/
│   ├── CERTIFICADOS/
│   ├── FIRMAS/
│   └── LISTADOS/
├── DOCENTES/
│   └── HOJAS_DE_VIDA/
└── CONFIG/
    ├── DATA/
    └── IMG/
```

---

#### B. CATEGORÍAS (`okm:categories`)
**Concepto:** Sistema de etiquetado transversal (tags/metadatos).

**Características:**
- Clasificación multidimensional
- Un documento puede tener MÚLTIPLES categorías
- Búsqueda y filtrado cruzado
- No afecta la ubicación física

**Ejemplo:**
```
/okm:categories/RUND/
├── DOCUMENTOS/
│   ├── LISTADOS/
│   ├── FIRMAS/
│   └── HOJAS_DE_VIDA/
└── CONFIG/
    ├── IMG/
    └── DATA/
```

**Relación:**
Un certificado puede estar en:
- **Taxonomía:** `/okm:root/RUND/DOCUMENTOS/CERTIFICADOS/cert_123.pdf`
- **Categorías:** `DOCUMENTOS`, `CERTIFICADOS`, `2025`

---

### 3.2 Todas las Rutas Definidas en Config.php

#### A. RUTAS BASE

```php
// Raíces principales
const ROOT_TAX = "/okm:root/RUND/";        // Taxonomía raíz
const ROOT_CTG = "/okm:categories/RUND/";  // Categorías raíz
```

---

#### B. RUTAS PRINCIPALES

```php
// Documentos
const ROOT_TAX_DOCS = "/okm:root/RUND/DOCUMENTOS/";
const ROOT_CTG_DOCS = "/okm:categories/RUND/DOCUMENTOS/";

// Docentes
const ROOT_TAX_PROF = "/okm:root/RUND/DOCENTES/";
const ROOT_CTG_PROF = "/okm:categories/RUND/DOCENTES/";

// Configuración
const ROOT_TAX_CONF = "/okm:root/RUND/CONFIG/";
const ROOT_CTG_CONF = "/okm:categories/RUND/CONFIG/";
```

---

#### C. CATEGORÍAS ESPECÍFICAS

```php
// Documentos
const CTGR_LISTADOS    = "/okm:categories/RUND/DOCUMENTOS/LISTADOS/";
const CTGR_FIRMAS      = "/okm:categories/RUND/DOCUMENTOS/FIRMAS/";
const CTGR_DOCS_HOJAS  = "/okm:categories/RUND/DOCUMENTOS/HOJAS_DE_VIDA/";

// Configuración
const CTGR_CONF_IMG    = "/okm:categories/RUND/CONFIG/IMG/";
const CTGR_CONF_DATA   = "/okm:categories/RUND/CONFIG/DATA/";
```

---

#### D. TAXONOMÍAS ESPECÍFICAS

```php
// Documentos
const TAX_FIRMAS       = "/okm:root/RUND/DOCUMENTOS/FIRMAS/";
const TAX_LISTADOS     = "/okm:root/RUND/DOCUMENTOS/LISTADOS/";
const TAX_CERTIFICADOS = "/okm:root/RUND/DOCUMENTOS/CERTIFICADOS/";

// Plantillas
const TAX_PLANTILLAS   = "/okm:root/RUND/DOCUMENTOS/PLANTILLAS/";
const TAX_PLANTILLAS_CERTIFICADOS = "/okm:root/RUND/DOCUMENTOS/PLANTILLAS/CERTIFICADOS/";
const TAX_PLANTILLAS_REPORTES     = "/okm:root/RUND/DOCUMENTOS/PLANTILLAS/REPORTES/";

// Docentes
const TAX_HOJAS        = "/okm:root/RUND/DOCENTES/HOJAS_DE_VIDA/";

// Configuración
const TAX_APP_DATA     = "/okm:root/RUND/CONFIG/DATA/";
const TAX_APP_IMG      = "/okm:root/RUND/CONFIG/IMG/";
```

---

### 3.3 Jerarquía de Carpetas Completa

```
/okm:root/RUND/                          [ROOT_TAX]
│
├── DOCUMENTOS/                          [ROOT_TAX_DOCS]
│   ├── CERTIFICADOS/                    [TAX_CERTIFICADOS]
│   ├── FIRMAS/                          [TAX_FIRMAS]
│   ├── LISTADOS/                        [TAX_LISTADOS]
│   └── PLANTILLAS/                      [TAX_PLANTILLAS]
│       ├── CERTIFICADOS/                [TAX_PLANTILLAS_CERTIFICADOS]
│       └── REPORTES/                    [TAX_PLANTILLAS_REPORTES]
│
├── DOCENTES/                            [ROOT_TAX_PROF]
│   └── HOJAS_DE_VIDA/                   [TAX_HOJAS]
│
└── CONFIG/                              [ROOT_TAX_CONF]
    ├── DATA/                            [TAX_APP_DATA]
    │   ├── labels.json
    │   ├── config.json
    │   └── permissions.json
    └── IMG/                             [TAX_APP_IMG]
        ├── logo.png
        └── firma_institucional.png
```

```
/okm:categories/RUND/                    [ROOT_CTG]
│
├── DOCUMENTOS/                          [ROOT_CTG_DOCS]
│   ├── LISTADOS/                        [CTGR_LISTADOS]
│   ├── FIRMAS/                          [CTGR_FIRMAS]
│   └── HOJAS_DE_VIDA/                   [CTGR_DOCS_HOJAS]
│
└── CONFIG/                              [ROOT_CTG_CONF]
    ├── IMG/                             [CTGR_CONF_IMG]
    └── DATA/                            [CTGR_CONF_DATA]
```

---

## 4. Operaciones CRUD

### 4.1 CREATE - Crear Documentos

#### A. Método Principal: `cargaArchivo()`

**Flujo completo:**

```php
// 1. Preparar archivo (desde formulario)
$archivo = $_FILES['documento'];

// 2. Definir propiedades
$propiedades = [
    ['label' => 'Nombre', 'valor' => 'certificado_123.pdf']
];

// 3. Ejecutar carga
$resultado = OpenKM::cargaArchivo(
    $archivo,
    $propiedades,
    Config::TAX_CERTIFICADOS,
    false  // Nuevo documento
);

// 4. Verificar resultado
if (!$resultado['error']) {
    echo "Documento creado: " . $resultado['respuesta']['uuid'];
}
```

**Proceso interno:**
1. Valida errores de carga de PHP
2. Extrae nombre de archivo
3. Crea carpetas de destino si no existen
4. Verifica si el documento ya existe
5. Prepara `CURLFile` con contenido binario
6. Ejecuta `documentAction("createSimple", ...)`
7. Valida y retorna resultado

---

#### B. Método Directo: `documentAction()`

**Para mayor control:**

```php
$temp = $_FILES['archivo']['tmp_name'];
$nombre = "mi_documento.pdf";
$tipo = mime_content_type($temp);

$postData = [
    'docPath' => Config::TAX_CERTIFICADOS . "/" . $nombre,
    'content' => new CURLFile($temp, $tipo, $nombre)
];

$respuesta = OpenKM::documentAction("createSimple", $postData);
$documento = json_decode($respuesta, true);
```

---

#### C. Creación de Carpetas: `creaCarpetas()`

**Crear estructura jerárquica:**

```php
$rutas = [
    "/DOCUMENTOS/CERTIFICADOS/2025/PREGRADO",
    "/DOCUMENTOS/CERTIFICADOS/2025/POSGRADO",
    "/DOCUMENTOS/FIRMAS/COORDINADORES"
];

$resultado = OpenKM::creaCarpetas($rutas, Config::ROOT_TAX);

// Verificar creación
foreach ($resultado as $item) {
    if ($item['accion']) {
        echo "Creada: " . $item['ruta'] . "\n";
    } else {
        echo "Ya existe: " . $item['ruta'] . "\n";
    }
}
```

---

### 4.2 READ - Leer Documentos

#### A. Buscar por Nombre y Ruta: `findArchivo()`

```php
$uuid = OpenKM::findArchivo(
    "certificado_2025_001.pdf",
    Config::TAX_CERTIFICADOS
);

if ($uuid) {
    echo "Documento encontrado: $uuid";
} else {
    echo "Documento no encontrado";
}
```

---

#### B. Obtener Contenido: `getArchivo()`

```php
// Obtener UUID primero
$uuid = "e4b2c8d1-1234-5678-9abc-def012345678";

// Descargar contenido
$contenido = OpenKM::getArchivo($uuid);

// Guardar o enviar al navegador
header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="certificado.pdf"');
echo $contenido;
```

---

#### C. Verificar Existencia: `yaExiste()`

```php
if (OpenKM::yaExiste("firma_rector.png", Config::TAX_FIRMAS)) {
    echo "La firma ya está registrada";
} else {
    echo "Puede cargar la firma";
}
```

---

#### D. Obtener Datos JSON: `getDataFile()`

```php
// Leer archivo de configuración
$labels = OpenKM::getDataFile("labels");
echo $labels["CERTIFICADOS"];  // "Certificados de Estudio"

// Leer desde ruta personalizada
$permisos = OpenKM::getDataFile("permissions", Config::TAX_APP_DATA);
```

---

#### E. Servir Imágenes: `getImageFile()`

```php
// En un endpoint de API
OpenKM::getImageFile("logo_esap.png");
// La función termina aquí enviando la imagen

// Con ruta personalizada
OpenKM::getImageFile("firma_coordinador.png", Config::TAX_FIRMAS);
```

---

### 4.3 UPDATE - Actualizar Documentos

#### A. Nueva Versión Completa: `cargaArchivo()` + `version=true`

```php
$archivo = $_FILES['nueva_version'];

$propiedades = [
    ['label' => 'Nombre', 'valor' => 'certificado_123.pdf'],
    ['label' => 'Uuid', 'valor' => 'uuid-del-documento-existente'],
    ['label' => 'Comentario', 'valor' => 'Corrección de fechas']
];

$resultado = OpenKM::cargaArchivo(
    $archivo,
    $propiedades,
    Config::TAX_CERTIFICADOS,
    true  // Nueva versión
);
```

---

#### B. Versionado Manual: `nuevaVersion()`

```php
// 1. Preparar archivo
$temp = $_FILES['archivo']['tmp_name'];
$nombre = "documento.pdf";
$uuid = "documento-uuid-existente";

// 2. Crear CURLFile
$fileData = new CURLFile(
    $temp,
    mime_content_type($temp),
    $nombre
);

// 3. Preparar postData
$postData = [
    'docPath' => Config::TAX_CERTIFICADOS . "/" . $nombre,
    'content' => $fileData
];

// 4. Ejecutar nueva versión
$resultado = OpenKM::nuevaVersion(
    $uuid,
    "Actualización 2025-10-20",
    $postData
);

// 5. Verificar
$respuesta = json_decode($resultado, true);
if (isset($respuesta['uuid'])) {
    echo "Nueva versión creada exitosamente";
}
```

---

### 4.4 DELETE - Eliminar Documentos

#### A. Eliminar Documento Individual: `borraArchivo()`

```php
$uuid = "documento-a-eliminar-uuid";
$resultado = OpenKM::borraArchivo($uuid);

// El documento se mueve a la papelera
echo "Documento movido a papelera";
```

---

#### B. Vaciar Papelera: `borraPapelera()`

```php
// ADVERTENCIA: Esta operación es irreversible
$resultado = OpenKM::borraPapelera();
echo "Papelera vaciada completamente";
```

---

#### C. Flujo Completo de Eliminación

```php
// 1. Buscar documento
$uuid = OpenKM::findArchivo(
    "documento_obsoleto.pdf",
    Config::TAX_CERTIFICADOS
);

// 2. Eliminar si existe
if ($uuid) {
    $resultado = OpenKM::borraArchivo($uuid);
    echo "Documento eliminado";
} else {
    echo "Documento no encontrado";
}

// 3. Opcionalmente, vaciar papelera después
// OpenKM::borraPapelera();
```

---

## 5. Sistema de Versionado

### 5.1 Flujo Checkout/Checkin Completo

OpenKM utiliza un sistema de bloqueo optimista para versionado:

```
┌─────────────────────────────────────────────────────────┐
│                  FLUJO DE VERSIONADO                    │
└─────────────────────────────────────────────────────────┘

1. CHECKOUT (Bloquear)
   ├─> Documento queda marcado como "en uso"
   ├─> Otros usuarios no pueden modificar
   └─> Se prepara para recibir nueva versión

2. VALIDAR CHECKOUT
   ├─> Verificar que el checkout fue exitoso
   ├─> document/isCheckedOut?docId=UUID
   └─> Debe retornar true

3. CHECKIN (Subir nueva versión)
   ├─> Se sube el nuevo contenido
   ├─> Se agrega comentario de versión
   ├─> Se desbloquea el documento
   └─> La versión anterior se mantiene en historial

4. VERIFICAR RESULTADO
   └─> Validar respuesta de OpenKM
```

---

### 5.2 Implementación del Método `nuevaVersion()`

```php
public static function nuevaVersion(
    string $uuid,
    string $comentario,
    array $postData
): string {
    // PASO 1: CHECKOUT
    self::consulta("document/checkout?docId=$uuid");

    // PASO 2: PREPARAR DATOS
    $postData["comment"] = $comentario;
    $postData["docId"] = $uuid;

    // PASO 3: VALIDAR CHECKOUT
    $respCheckOut = json_decode(
        self::consulta(
            "document/isCheckedOut?docId=$uuid",
            "GET",
            null,
            ["Accept: text/plain"]
        ),
        true
    );

    // PASO 4: CHECKIN O ERROR
    if ($respCheckOut) {
        return self::documentAction("checkin", $postData);
    } else {
        return json_encode([
            "error" => "No se pudo hacer checkout.",
            "respCheckOut" => $respCheckOut,
            "consulta" => "document/isCheckedOut?docId=$uuid"
        ]);
    }
}
```

---

### 5.3 Validaciones de Estado

#### A. Verificar si está en Checkout

```php
$uuid = "documento-uuid";
$respuesta = OpenKM::consulta(
    "document/isCheckedOut?docId=$uuid",
    "GET",
    null,
    ["Accept: text/plain"]
);

$enUso = json_decode($respuesta, true);
if ($enUso) {
    echo "Documento bloqueado para edición";
}
```

---

#### B. Cancelar Checkout

```php
// Si se necesita cancelar un checkout sin hacer checkin
$uuid = "documento-uuid";
OpenKM::consulta("document/cancelCheckout?docId=$uuid", "PUT");
```

---

### 5.4 Manejo de Duplicados

El sistema previene duplicados mediante:

1. **Búsqueda previa:**
```php
$uuid = OpenKM::findArchivo($nombre, $ruta);
if ($uuid) {
    // Ya existe, usar nuevaVersion()
} else {
    // No existe, usar createSimple()
}
```

2. **Validación en `cargaArchivo()`:**
```php
$uuid = Utils::extraeElemento($propiedades, "label", "Uuid") ?
    Utils::extraeElemento($propiedades, "label", "Uuid")["valor"] :
    json_decode(self::consulta($query), true)["queryResult"]["node"]["uuid"];

$resp = $version ?
    self::nuevaVersion($uuid, $comentarioNV, $postData) :
    self::documentAction("createSimple", $postData);
```

---

### 5.5 Ejemplo Completo de Versionado

```php
// ESCENARIO: Actualizar un certificado existente

// 1. BUSCAR DOCUMENTO
$uuid = OpenKM::findArchivo(
    "certificado_estudiante_123.pdf",
    Config::TAX_CERTIFICADOS
);

if (!$uuid) {
    die("Documento no encontrado");
}

// 2. PREPARAR NUEVA VERSIÓN
$archivo = $_FILES['archivo_actualizado'];
$temp = $archivo['tmp_name'];
$nombre = "certificado_estudiante_123.pdf";

// 3. CREAR POSTDATA
$postData = [
    'docPath' => Config::TAX_CERTIFICADOS . "/" . $nombre,
    'content' => new CURLFile(
        $temp,
        mime_content_type($temp),
        $nombre
    )
];

// 4. EJECUTAR VERSIONADO
$resultado = OpenKM::nuevaVersion(
    $uuid,
    "Actualización de calificaciones - 2025-10-20",
    $postData
);

// 5. VALIDAR RESULTADO
$respuesta = json_decode($resultado, true);
if (isset($respuesta['uuid'])) {
    echo "Nueva versión creada exitosamente\n";
    echo "UUID: " . $respuesta['uuid'] . "\n";
    echo "Versión: " . $respuesta['actualVersion'] . "\n";
} else {
    echo "Error: " . print_r($respuesta, true);
}
```

---

## 6. Búsquedas y Queries

### 6.1 Método `findArchivo()` - Búsqueda Básica

#### Estructura de Query

```php
// Formato de búsqueda
$query = "search/find?name=" . urlencode($nombre) . "&path=" . urlencode($ruta);

// Ejemplo real
$query = "search/find?name=certificado_123.pdf&path=/okm:root/RUND/DOCUMENTOS/CERTIFICADOS";
```

#### Respuesta de OpenKM

```json
{
  "queryResult": {
    "node": {
      "uuid": "e4b2c8d1-1234-5678-9abc-def012345678",
      "path": "/okm:root/RUND/DOCUMENTOS/CERTIFICADOS/certificado_123.pdf",
      "created": "2025-10-20T10:30:00.000Z",
      "author": "okmAdmin"
    }
  }
}
```

#### Implementación

```php
public static function findArchivo(string $nombre, string $ruta): string | null
{
    $query = "search/find?name=" . urlencode($nombre) . "&path=" . urlencode($ruta);
    $uuid = json_decode(self::consulta($query), true)["queryResult"]["node"]["uuid"];
    return $uuid ?? null;
}
```

---

### 6.2 Método `yaExiste()` - Verificación de Existencia

```php
public static function yaExiste(string $nombreArchivo, string $path): bool
{
    $query = "search/find?name=" . urlencode($nombreArchivo) .
             "&path=" . urlencode($path);
    $respQuery = json_decode(self::consulta($query), true);
    return $respQuery && count($respQuery);
}
```

**Uso:**

```php
if (OpenKM::yaExiste("firma_rector.png", Config::TAX_FIRMAS)) {
    echo "El archivo ya existe";
}
```

---

### 6.3 Búsquedas Avanzadas con `consulta()`

#### A. Búsqueda por Contenido

```php
// Buscar documentos que contengan texto específico
$query = "search/findByContent?content=" . urlencode("certificado de estudios");
$resultados = json_decode(OpenKM::consulta($query), true);

foreach ($resultados['queryResult'] as $documento) {
    echo $documento['path'] . "\n";
}
```

---

#### B. Búsqueda por Keywords

```php
// Buscar por palabras clave
$query = "search/findByKeywords?keywords=" . urlencode("2025 pregrado");
$resultados = json_decode(OpenKM::consulta($query), true);
```

---

#### C. Búsqueda en Carpeta

```php
// Obtener todos los documentos de una carpeta
$folderUuid = "carpeta-uuid";
$query = "document/getChildren?fldId=" . $folderUuid;
$documentos = json_decode(OpenKM::consulta($query), true);
```

---

### 6.4 Estructura de Queries REST Comunes

#### Tabla de Endpoints de Búsqueda

| Endpoint | Parámetros | Descripción |
|----------|-----------|-------------|
| `search/find` | `name`, `path` | Búsqueda exacta por nombre y ruta |
| `search/findByContent` | `content` | Búsqueda por contenido de texto |
| `search/findByKeywords` | `keywords` | Búsqueda por palabras clave |
| `document/getChildren` | `fldId` | Listar documentos de una carpeta |
| `folder/getChildren` | `fldId` | Listar subcarpetas |
| `repository/getNodeUuid` | `nodePath` | Obtener UUID de una ruta |

---

### 6.5 Ejemplo Completo de Búsqueda

```php
/**
 * Buscar todos los certificados de un estudiante
 */
function buscarCertificadosEstudiante(string $idEstudiante): array
{
    // 1. Buscar por patrón de nombre
    $patron = "certificado_{$idEstudiante}_";
    $ruta = Config::TAX_CERTIFICADOS;

    // 2. Obtener UUID de la carpeta
    $queryFolder = "repository/getNodeUuid?nodePath=" . urlencode($ruta);
    $folderUuid = json_decode(OpenKM::consulta($queryFolder), true);

    // 3. Obtener todos los documentos de la carpeta
    $queryDocs = "document/getChildren?fldId=" . $folderUuid;
    $documentos = json_decode(OpenKM::consulta($queryDocs), true);

    // 4. Filtrar por patrón
    $certificados = [];
    foreach ($documentos['document'] as $doc) {
        if (strpos($doc['path'], $patron) !== false) {
            $certificados[] = [
                'uuid' => $doc['uuid'],
                'nombre' => basename($doc['path']),
                'fecha' => $doc['created']
            ];
        }
    }

    return $certificados;
}

// USO
$certs = buscarCertificadosEstudiante("20251234");
foreach ($certs as $cert) {
    echo $cert['nombre'] . " - " . $cert['fecha'] . "\n";
}
```

---

## 7. Categorización

### 7.1 Asignación de Categorías a Documentos

OpenKM permite asignar múltiples categorías a un documento para clasificación transversal.

#### Endpoint de Categorización

```php
// Asignar categoría a documento
$docUuid = "documento-uuid";
$categoryUuid = "categoria-uuid";

$query = "document/addCategory?docId=$docUuid&catId=$categoryUuid";
$resultado = OpenKM::consulta($query, "PUT");
```

---

#### Ejemplo Completo

```php
// 1. Crear documento
$archivo = $_FILES['certificado'];
$resultado = OpenKM::cargaArchivo(
    $archivo,
    [['label' => 'Nombre', 'valor' => 'cert_2025_001.pdf']],
    Config::TAX_CERTIFICADOS,
    false
);

$docUuid = $resultado['respuesta']['uuid'];

// 2. Obtener UUIDs de categorías
$catCertificados = OpenKM::findArchivo(
    "CERTIFICADOS",
    Config::ROOT_CTG_DOCS
);

$catAnio2025 = OpenKM::findArchivo(
    "2025",
    Config::ROOT_CTG_DOCS . "CERTIFICADOS/"
);

// 3. Asignar categorías
OpenKM::consulta(
    "document/addCategory?docId=$docUuid&catId=$catCertificados",
    "PUT"
);

OpenKM::consulta(
    "document/addCategory?docId=$docUuid&catId=$catAnio2025",
    "PUT"
);
```

---

### 7.2 Método `getDocumentosCategorizados()`

**Nota:** Este método no está implementado en la clase actual, pero puede agregarse:

```php
/**
 * Obtiene documentos que pertenecen a una categoría
 */
public static function getDocumentosCategorizados(string $categoryUuid): array
{
    $query = "search/findByCategory?catId=" . $categoryUuid;
    $respuesta = self::consulta($query);
    return json_decode($respuesta, true)['queryResult'] ?? [];
}
```

**Uso:**
```php
$catUuid = OpenKM::findArchivo("CERTIFICADOS", Config::ROOT_CTG_DOCS);
$documentos = OpenKM::getDocumentosCategorizados($catUuid);

foreach ($documentos as $doc) {
    echo $doc['path'] . "\n";
}
```

---

### 7.3 Cruces de Categorías (Búsqueda Multidimensional)

```php
/**
 * Buscar documentos con múltiples categorías
 * Ejemplo: Certificados del 2025 de Pregrado
 */
function buscarPorMultiplesCategorias(array $categorias): array
{
    $resultados = [];

    // 1. Obtener documentos de la primera categoría
    $catPrimera = array_shift($categorias);
    $docs = OpenKM::getDocumentosCategorizados($catPrimera);

    // 2. Filtrar por las demás categorías
    foreach ($docs as $doc) {
        $docUuid = $doc['uuid'];

        // Obtener categorías del documento
        $query = "document/getCategoryAssignments?docId=$docUuid";
        $categoriasDoc = json_decode(OpenKM::consulta($query), true);

        // Verificar si tiene todas las categorías
        $uuidsCategoriasDoc = array_column($categoriasDoc, 'uuid');
        $tieneTodasCategorias = true;

        foreach ($categorias as $catRequerida) {
            if (!in_array($catRequerida, $uuidsCategoriasDoc)) {
                $tieneTodasCategorias = false;
                break;
            }
        }

        if ($tieneTodasCategorias) {
            $resultados[] = $doc;
        }
    }

    return $resultados;
}

// USO
$catCertificados = OpenKM::findArchivo("CERTIFICADOS", Config::ROOT_CTG_DOCS);
$cat2025 = OpenKM::findArchivo("2025", Config::ROOT_CTG_DOCS . "CERTIFICADOS/");
$catPregrado = OpenKM::findArchivo("PREGRADO", Config::ROOT_CTG_DOCS . "CERTIFICADOS/");

$certificados = buscarPorMultiplesCategorias([
    $catCertificados,
    $cat2025,
    $catPregrado
]);
```

---

### 7.4 Gestión Completa de Categorías

```php
/**
 * Clase helper para gestión de categorías
 */
class CategoriaManager
{
    /**
     * Asignar múltiples categorías a un documento
     */
    public static function asignarCategorias(string $docUuid, array $categoryUuids): void
    {
        foreach ($categoryUuids as $catUuid) {
            OpenKM::consulta(
                "document/addCategory?docId=$docUuid&catId=$catUuid",
                "PUT"
            );
        }
    }

    /**
     * Remover categoría de un documento
     */
    public static function removerCategoria(string $docUuid, string $catUuid): void
    {
        OpenKM::consulta(
            "document/removeCategory?docId=$docUuid&catId=$catUuid",
            "DELETE"
        );
    }

    /**
     * Obtener todas las categorías de un documento
     */
    public static function obtenerCategorias(string $docUuid): array
    {
        $query = "document/getCategoryAssignments?docId=$docUuid";
        return json_decode(OpenKM::consulta($query), true);
    }

    /**
     * Reemplazar todas las categorías de un documento
     */
    public static function reemplazarCategorias(
        string $docUuid,
        array $nuevasCategorias
    ): void {
        // 1. Obtener categorías actuales
        $actuales = self::obtenerCategorias($docUuid);

        // 2. Remover todas
        foreach ($actuales as $cat) {
            self::removerCategoria($docUuid, $cat['uuid']);
        }

        // 3. Asignar nuevas
        self::asignarCategorias($docUuid, $nuevasCategorias);
    }
}

// USO
$docUuid = "documento-uuid";
CategoriaManager::asignarCategorias($docUuid, [
    $catCertificados,
    $cat2025,
    $catPregrado
]);

$categorias = CategoriaManager::obtenerCategorias($docUuid);
print_r($categorias);
```

---

## 8. Manejo de Errores

### 8.1 Respuestas de OpenKM

OpenKM devuelve diferentes tipos de errores en texto plano:

#### A. RepositoryException

**Cuándo ocurre:** Errores generales del repositorio.

**Formato:**
```
RepositoryException: [mensaje de error]
```

**Ejemplo:**
```php
$response = OpenKM::consulta("document/getContent?docId=uuid-invalido");
if (substr($response, 0, 19) == "RepositoryException") {
    echo "Error de repositorio: $response";
}
```

---

#### B. PathNotFoundException

**Cuándo ocurre:** Ruta o documento no encontrado.

**Formato:**
```
PathNotFoundException: [ruta]
```

**Detección:**
```php
$response = OpenKM::consulta("repository/getNodeUuid?nodePath=/ruta/inexistente");
if (strpos($response, "PathNotFoundException") !== false) {
    echo "Ruta no encontrada";
}
```

**Uso en `creaCarpetas()`:**
```php
$respGetNodeUuid = self::consulta("repository/getNodeUuid?nodePath=" . urlencode($ruta));
if (strpos($respGetNodeUuid, "PathNotFoundException") !== false) {
    // La carpeta no existe, se debe crear
    $respCreateSimple = self::consulta("folder/createSimple", "POST", $ruta);
}
```

---

#### C. AccessDeniedException

**Cuándo ocurre:** Permisos insuficientes.

**Formato:**
```
AccessDeniedException: [mensaje]
```

---

#### D. LockException

**Cuándo ocurre:** Documento bloqueado por checkout.

**Formato:**
```
LockException: Document is locked
```

---

### 8.2 Validación de Respuestas

#### Método `verificaCarga()`

```php
public static function verificaCarga(
    string $resp,
    array $salida,
    array | string | null $folderResp = null
): array {
    // Detectar respuestas exitosas:
    // - JSON object: "{"uuid":"..."}"
    // - JSON array: "[{...}]"
    // - Boolean true: "1"
    if (substr($resp, 0, 1) == '{' ||
        substr($resp, 0, 1) == '[' ||
        substr($resp, 0, 1) == "1") {

        $salida["respuesta"] = json_decode($resp, true);
        $salida["error"] = false;
    } else {
        // Respuesta de error (texto plano)
        $salida["respuesta"] = $resp;
        $salida["error"] = "No se pudo crear el documento";
    }

    if ($folderResp) {
        $salida["folderResp"] = $folderResp;
    }

    return $salida;
}
```

---

### 8.3 Detección de Tipos de Error

```php
/**
 * Clase para manejo centralizado de errores de OpenKM
 */
class OpenKMErrorHandler
{
    /**
     * Detecta el tipo de error en una respuesta
     */
    public static function detectarError(string $response): ?array
    {
        $errores = [
            'RepositoryException' => 19,
            'PathNotFoundException' => 22,
            'AccessDeniedException' => 22,
            'LockException' => 13,
            'ItemExistsException' => 19,
            'VersionException' => 16
        ];

        foreach ($errores as $tipo => $longitud) {
            if (substr($response, 0, $longitud) == $tipo) {
                return [
                    'tipo' => $tipo,
                    'mensaje' => $response,
                    'codigo' => self::getCodigoError($tipo)
                ];
            }
        }

        return null; // No hay error
    }

    /**
     * Mapea tipos de error a códigos HTTP
     */
    private static function getCodigoError(string $tipo): int
    {
        return match($tipo) {
            'PathNotFoundException' => 404,
            'AccessDeniedException' => 403,
            'LockException' => 423,
            'ItemExistsException' => 409,
            default => 500
        };
    }

    /**
     * Valida y formatea respuesta
     */
    public static function validarRespuesta(string $response): array
    {
        $error = self::detectarError($response);

        if ($error) {
            return [
                'exito' => false,
                'error' => $error
            ];
        }

        // Intentar parsear como JSON
        $data = json_decode($response, true);

        return [
            'exito' => true,
            'data' => $data ?? $response
        ];
    }
}

// USO
$response = OpenKM::consulta("document/getContent?docId=$uuid");
$resultado = OpenKMErrorHandler::validarRespuesta($response);

if (!$resultado['exito']) {
    http_response_code($resultado['error']['codigo']);
    echo json_encode($resultado['error']);
} else {
    echo $resultado['data'];
}
```

---

### 8.4 Manejo de Errores en Operaciones Comunes

#### A. Carga de Archivos

```php
try {
    $resultado = OpenKM::cargaArchivo(
        $_FILES['archivo'],
        $propiedades,
        Config::TAX_CERTIFICADOS,
        false
    );

    if ($resultado['error']) {
        throw new Exception($resultado['error']);
    }

    echo json_encode([
        'exito' => true,
        'uuid' => $resultado['respuesta']['uuid']
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'exito' => false,
        'error' => $e->getMessage()
    ]);
}
```

---

#### B. Búsqueda de Archivos

```php
function buscarArchivoSeguro(string $nombre, string $ruta): ?string
{
    try {
        $uuid = OpenKM::findArchivo($nombre, $ruta);
        return $uuid;
    } catch (Exception $e) {
        error_log("Error buscando archivo: " . $e->getMessage());
        return null;
    }
}

$uuid = buscarArchivoSeguro("cert.pdf", Config::TAX_CERTIFICADOS);
if (!$uuid) {
    echo "Archivo no encontrado o error en la búsqueda";
}
```

---

#### C. Versionado

```php
function actualizarDocumento(string $uuid, array $archivo): array
{
    // 1. Verificar que no esté en checkout
    $enCheckout = json_decode(
        OpenKM::consulta(
            "document/isCheckedOut?docId=$uuid",
            "GET",
            null,
            ["Accept: text/plain"]
        ),
        true
    );

    if ($enCheckout) {
        return [
            'exito' => false,
            'error' => 'Documento en uso por otro usuario'
        ];
    }

    // 2. Preparar nueva versión
    $postData = [
        'docPath' => /* ... */,
        'content' => new CURLFile(/* ... */)
    ];

    // 3. Ejecutar
    $resultado = OpenKM::nuevaVersion(
        $uuid,
        "Actualización automática",
        $postData
    );

    // 4. Validar
    $respuesta = json_decode($resultado, true);

    if (isset($respuesta['error'])) {
        return [
            'exito' => false,
            'error' => $respuesta['error']
        ];
    }

    return [
        'exito' => true,
        'uuid' => $respuesta['uuid'],
        'version' => $respuesta['actualVersion']
    ];
}
```

---

## 9. Ejemplos de Uso

### 9.1 Subir Archivo Nuevo

```php
/**
 * EJEMPLO 1: Subir un certificado nuevo
 */

// Desde un formulario HTML
// <form method="POST" enctype="multipart/form-data">
//   <input type="file" name="certificado">
//   <input type="text" name="estudiante_id">
// </form>

// Procesar carga
$archivo = $_FILES['certificado'];
$estudianteId = $_POST['estudiante_id'];
$nombre = "certificado_estudiante_{$estudianteId}.pdf";

$propiedades = [
    ['label' => 'Nombre', 'valor' => $nombre]
];

$resultado = OpenKM::cargaArchivo(
    $archivo,
    $propiedades,
    Config::TAX_CERTIFICADOS,
    false  // Nuevo documento
);

if (!$resultado['error']) {
    // Éxito
    $uuid = $resultado['respuesta']['uuid'];

    // Opcionalmente, asignar categorías
    $catCertificados = OpenKM::findArchivo("CERTIFICADOS", Config::ROOT_CTG_DOCS);
    $cat2025 = OpenKM::findArchivo("2025", Config::ROOT_CTG_DOCS . "CERTIFICADOS/");

    OpenKM::consulta(
        "document/addCategory?docId=$uuid&catId=$catCertificados",
        "PUT"
    );
    OpenKM::consulta(
        "document/addCategory?docId=$uuid&catId=$cat2025",
        "PUT"
    );

    echo json_encode([
        'exito' => true,
        'mensaje' => 'Certificado cargado exitosamente',
        'uuid' => $uuid
    ]);
} else {
    // Error
    echo json_encode([
        'exito' => false,
        'error' => $resultado['error'],
        'detalles' => $resultado['respuesta']
    ]);
}
```

---

### 9.2 Actualizar Versión Existente

```php
/**
 * EJEMPLO 2: Actualizar un certificado existente
 */

$estudianteId = $_POST['estudiante_id'];
$archivo = $_FILES['certificado_actualizado'];
$nombre = "certificado_estudiante_{$estudianteId}.pdf";

// 1. Buscar documento existente
$uuid = OpenKM::findArchivo($nombre, Config::TAX_CERTIFICADOS);

if (!$uuid) {
    echo json_encode([
        'exito' => false,
        'error' => 'Certificado no encontrado'
    ]);
    exit;
}

// 2. Actualizar versión
$propiedades = [
    ['label' => 'Nombre', 'valor' => $nombre],
    ['label' => 'Uuid', 'valor' => $uuid],
    ['label' => 'Comentario', 'valor' => 'Actualización de notas finales']
];

$resultado = OpenKM::cargaArchivo(
    $archivo,
    $propiedades,
    Config::TAX_CERTIFICADOS,
    true  // Nueva versión
);

if (!$resultado['error']) {
    echo json_encode([
        'exito' => true,
        'mensaje' => 'Certificado actualizado exitosamente',
        'uuid' => $uuid,
        'version' => $resultado['respuesta']['actualVersion']
    ]);
} else {
    echo json_encode([
        'exito' => false,
        'error' => $resultado['error']
    ]);
}
```

---

### 9.3 Buscar y Descargar Archivo

```php
/**
 * EJEMPLO 3: Buscar y descargar un certificado
 */

$estudianteId = $_GET['estudiante_id'];
$nombre = "certificado_estudiante_{$estudianteId}.pdf";

// 1. Buscar documento
$uuid = OpenKM::findArchivo($nombre, Config::TAX_CERTIFICADOS);

if (!$uuid) {
    http_response_code(404);
    echo json_encode([
        'exito' => false,
        'error' => 'Certificado no encontrado'
    ]);
    exit;
}

// 2. Obtener contenido
$contenido = OpenKM::getArchivo($uuid);

// 3. Enviar al navegador
header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="' . $nombre . '"');
header('Content-Length: ' . strlen($contenido));
echo $contenido;
```

---

### 9.4 Crear Estructura de Carpetas

```php
/**
 * EJEMPLO 4: Crear estructura para nuevo año académico
 */

$anio = 2025;

$carpetas = [
    // Certificados por programa
    "/DOCUMENTOS/CERTIFICADOS/{$anio}/PREGRADO",
    "/DOCUMENTOS/CERTIFICADOS/{$anio}/POSGRADO",
    "/DOCUMENTOS/CERTIFICADOS/{$anio}/ESPECIALIZACION",

    // Firmas por cargo
    "/DOCUMENTOS/FIRMAS/{$anio}/COORDINADORES",
    "/DOCUMENTOS/FIRMAS/{$anio}/DIRECTORES",
    "/DOCUMENTOS/FIRMAS/{$anio}/RECTORIA",

    // Listados por periodo
    "/DOCUMENTOS/LISTADOS/{$anio}/PERIODO_1",
    "/DOCUMENTOS/LISTADOS/{$anio}/PERIODO_2",

    // Hojas de vida
    "/DOCENTES/HOJAS_DE_VIDA/{$anio}"
];

$resultado = OpenKM::creaCarpetas($carpetas, Config::ROOT_TAX);

$creadas = 0;
$existentes = 0;

foreach ($resultado as $item) {
    if ($item['accion']) {
        $creadas++;
        echo "✓ Creada: {$item['ruta']}\n";
    } else {
        $existentes++;
        echo "○ Ya existe: {$item['ruta']}\n";
    }
}

echo "\n";
echo "Resumen:\n";
echo "- Carpetas creadas: $creadas\n";
echo "- Carpetas existentes: $existentes\n";
```

---

### 9.5 Gestión Completa de Documento

```php
/**
 * EJEMPLO 5: Flujo completo de gestión documental
 */

class GestorCertificados
{
    /**
     * Crear nuevo certificado
     */
    public static function crear(array $archivo, string $estudianteId, array $metadata): array
    {
        // 1. Validar que no exista
        $nombre = "certificado_{$estudianteId}_" . date('Y') . ".pdf";

        if (OpenKM::yaExiste($nombre, Config::TAX_CERTIFICADOS)) {
            return [
                'exito' => false,
                'error' => 'Ya existe un certificado para este estudiante'
            ];
        }

        // 2. Crear carpeta del año si no existe
        $anio = date('Y');
        OpenKM::creaCarpetas(
            ["/DOCUMENTOS/CERTIFICADOS/{$anio}"],
            Config::ROOT_TAX
        );

        // 3. Subir archivo
        $propiedades = [
            ['label' => 'Nombre', 'valor' => $nombre]
        ];

        $resultado = OpenKM::cargaArchivo(
            $archivo,
            $propiedades,
            Config::TAX_CERTIFICADOS . "/{$anio}",
            false
        );

        if ($resultado['error']) {
            return ['exito' => false, 'error' => $resultado['error']];
        }

        $uuid = $resultado['respuesta']['uuid'];

        // 4. Asignar categorías
        self::asignarCategorias($uuid, $metadata);

        return [
            'exito' => true,
            'uuid' => $uuid,
            'nombre' => $nombre
        ];
    }

    /**
     * Actualizar certificado existente
     */
    public static function actualizar(
        string $estudianteId,
        array $archivo,
        string $comentario
    ): array {
        // 1. Buscar documento
        $nombre = "certificado_{$estudianteId}_" . date('Y') . ".pdf";
        $uuid = OpenKM::findArchivo($nombre, Config::TAX_CERTIFICADOS . "/" . date('Y'));

        if (!$uuid) {
            return ['exito' => false, 'error' => 'Certificado no encontrado'];
        }

        // 2. Actualizar versión
        $propiedades = [
            ['label' => 'Nombre', 'valor' => $nombre],
            ['label' => 'Uuid', 'valor' => $uuid],
            ['label' => 'Comentario', 'valor' => $comentario]
        ];

        $resultado = OpenKM::cargaArchivo(
            $archivo,
            $propiedades,
            Config::TAX_CERTIFICADOS . "/" . date('Y'),
            true
        );

        return [
            'exito' => !$resultado['error'],
            'uuid' => $uuid,
            'version' => $resultado['respuesta']['actualVersion'] ?? null
        ];
    }

    /**
     * Buscar certificado
     */
    public static function buscar(string $estudianteId): ?array
    {
        $nombre = "certificado_{$estudianteId}_" . date('Y') . ".pdf";
        $uuid = OpenKM::findArchivo($nombre, Config::TAX_CERTIFICADOS . "/" . date('Y'));

        if (!$uuid) {
            return null;
        }

        return [
            'uuid' => $uuid,
            'nombre' => $nombre,
            'ruta' => Config::TAX_CERTIFICADOS . "/" . date('Y') . "/" . $nombre
        ];
    }

    /**
     * Descargar certificado
     */
    public static function descargar(string $estudianteId): void
    {
        $cert = self::buscar($estudianteId);

        if (!$cert) {
            http_response_code(404);
            echo json_encode(['error' => 'Certificado no encontrado']);
            exit;
        }

        $contenido = OpenKM::getArchivo($cert['uuid']);

        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . $cert['nombre'] . '"');
        echo $contenido;
        exit;
    }

    /**
     * Eliminar certificado
     */
    public static function eliminar(string $estudianteId): array
    {
        $cert = self::buscar($estudianteId);

        if (!$cert) {
            return ['exito' => false, 'error' => 'Certificado no encontrado'];
        }

        OpenKM::borraArchivo($cert['uuid']);

        return ['exito' => true, 'mensaje' => 'Certificado eliminado'];
    }

    /**
     * Asignar categorías
     */
    private static function asignarCategorias(string $uuid, array $metadata): void
    {
        // Categoría base: CERTIFICADOS
        $catBase = OpenKM::findArchivo("CERTIFICADOS", Config::ROOT_CTG_DOCS);
        if ($catBase) {
            OpenKM::consulta("document/addCategory?docId=$uuid&catId=$catBase", "PUT");
        }

        // Categoría de año
        $anio = date('Y');
        $catAnio = OpenKM::findArchivo($anio, Config::ROOT_CTG_DOCS . "CERTIFICADOS/");
        if ($catAnio) {
            OpenKM::consulta("document/addCategory?docId=$uuid&catId=$catAnio", "PUT");
        }

        // Categorías adicionales según metadata
        if (isset($metadata['programa'])) {
            $catPrograma = OpenKM::findArchivo(
                $metadata['programa'],
                Config::ROOT_CTG_DOCS . "CERTIFICADOS/"
            );
            if ($catPrograma) {
                OpenKM::consulta("document/addCategory?docId=$uuid&catId=$catPrograma", "PUT");
            }
        }
    }
}

// USO DEL GESTOR
$archivo = $_FILES['certificado'];
$estudianteId = $_POST['estudiante_id'];
$metadata = [
    'programa' => 'PREGRADO'
];

$resultado = GestorCertificados::crear($archivo, $estudianteId, $metadata);

if ($resultado['exito']) {
    echo "Certificado creado: " . $resultado['uuid'];
} else {
    echo "Error: " . $resultado['error'];
}
```

---

## Diagramas de Flujo

### Diagrama 1: Flujo de Carga de Archivo

```
┌─────────────────────────────────────────────────────────────┐
│              FLUJO DE CARGA DE ARCHIVO                      │
└─────────────────────────────────────────────────────────────┘

    ┌──────────────┐
    │   INICIO     │
    └──────┬───────┘
           │
           ▼
    ┌──────────────┐
    │ Validar      │
    │ errores PHP  │
    └──────┬───────┘
           │
           ▼
    ┌──────────────────┐
    │ Extraer nombre   │◄─── $propiedades["Nombre"]
    │ y metadatos      │
    └──────┬───────────┘
           │
           ▼
    ┌──────────────────┐
    │ Crear carpetas   │
    │ si no existen    │
    └──────┬───────────┘
           │
           ▼
    ┌─────────────────────┐
    │ ¿UUID en            │
    │ propiedades?        │
    └──────┬──────────────┘
           │
      ┌────┴────┐
      │ Sí      │ No
      ▼         ▼
   ┌─────┐  ┌──────────────┐
   │UUID │  │ findArchivo()│
   └──┬──┘  └──────┬───────┘
      │            │
      └────┬───────┘
           │
           ▼
    ┌─────────────────┐
    │ ¿UUID existe?   │
    └──────┬──────────┘
           │
      ┌────┴────┐
      │ Sí      │ No
      ▼         ▼
┌──────────┐ ┌───────────────┐
│nuevaVer  │ │createSimple   │
│sion()    │ │               │
└──┬───────┘ └──────┬────────┘
   │                │
   └────┬───────────┘
        │
        ▼
 ┌──────────────┐
 │verificaCarga │
 └──────┬───────┘
        │
        ▼
 ┌──────────────┐
 │   RETORNO    │
 │  resultado   │
 └──────────────┘
```

---

### Diagrama 2: Sistema de Versionado

```
┌─────────────────────────────────────────────────────────────┐
│           SISTEMA DE VERSIONADO (checkout/checkin)          │
└─────────────────────────────────────────────────────────────┘

  ┌─────────────┐
  │  DOCUMENTO  │
  │  EXISTENTE  │
  └──────┬──────┘
         │
         ▼
  ┌──────────────────┐
  │  1. CHECKOUT     │
  │  Bloquear doc    │
  └──────┬───────────┘
         │
         ▼
  ┌─────────────────────┐
  │  2. isCheckedOut?   │
  │  Validar bloqueo    │
  └──────┬──────────────┘
         │
    ┌────┴────┐
    │ true    │ false
    ▼         ▼
┌─────────┐ ┌──────────┐
│Continuar│ │  ERROR   │
│         │ │ Retornar │
└────┬────┘ └────┬─────┘
     │           │
     │           └──────┐
     ▼                  │
┌──────────────┐        │
│ 3. CHECKIN   │        │
│ Subir nueva  │        │
│ versión      │        │
└──────┬───────┘        │
       │                │
       ▼                │
┌──────────────┐        │
│ Desbloquear  │        │
│ documento    │        │
└──────┬───────┘        │
       │                │
       ▼                │
┌──────────────┐        │
│  DOCUMENTO   │        │
│ ACTUALIZADO  │        │
└──────┬───────┘        │
       │                │
       └────┬───────────┘
            │
            ▼
     ┌──────────────┐
     │   RETORNO    │
     └──────────────┘
```

---

## Constantes de Config.php Relacionadas

### Resumen de Constantes

```php
// ============================================================================
// AUTENTICACIÓN
// ============================================================================
const USER = "okmAdmin";
const PASSWORD = "admin";

// ============================================================================
// API
// ============================================================================
const REST = "/services/rest/";

// ============================================================================
// ESTRUCTURA BASE
// ============================================================================

// Raíces
const ROOT_TAX = "/okm:root/RUND/";                 // Taxonomía
const ROOT_CTG = "/okm:categories/RUND/";           // Categorías

// Secciones principales - Taxonomía
const ROOT_TAX_DOCS = "/okm:root/RUND/DOCUMENTOS/";
const ROOT_TAX_PROF = "/okm:root/RUND/DOCENTES/";
const ROOT_TAX_CONF = "/okm:root/RUND/CONFIG/";

// Secciones principales - Categorías
const ROOT_CTG_DOCS = "/okm:categories/RUND/DOCUMENTOS/";
const ROOT_CTG_PROF = "/okm:categories/RUND/DOCENTES/";
const ROOT_CTG_CONF = "/okm:categories/RUND/CONFIG/";

// ============================================================================
// TAXONOMÍAS ESPECÍFICAS (Rutas físicas)
// ============================================================================
const TAX_FIRMAS                  = "/okm:root/RUND/DOCUMENTOS/FIRMAS/";
const TAX_LISTADOS                = "/okm:root/RUND/DOCUMENTOS/LISTADOS/";
const TAX_CERTIFICADOS            = "/okm:root/RUND/DOCUMENTOS/CERTIFICADOS/";
const TAX_PLANTILLAS              = "/okm:root/RUND/DOCUMENTOS/PLANTILLAS/";
const TAX_PLANTILLAS_CERTIFICADOS = "/okm:root/RUND/DOCUMENTOS/PLANTILLAS/CERTIFICADOS/";
const TAX_PLANTILLAS_REPORTES     = "/okm:root/RUND/DOCUMENTOS/PLANTILLAS/REPORTES/";
const TAX_HOJAS                   = "/okm:root/RUND/DOCENTES/HOJAS_DE_VIDA/";
const TAX_APP_DATA                = "/okm:root/RUND/CONFIG/DATA/";
const TAX_APP_IMG                 = "/okm:root/RUND/CONFIG/IMG/";

// ============================================================================
// CATEGORÍAS ESPECÍFICAS (Etiquetas)
// ============================================================================
const CTGR_LISTADOS    = "/okm:categories/RUND/DOCUMENTOS/LISTADOS/";
const CTGR_FIRMAS      = "/okm:categories/RUND/DOCUMENTOS/FIRMAS/";
const CTGR_DOCS_HOJAS  = "/okm:categories/RUND/DOCUMENTOS/HOJAS_DE_VIDA/";
const CTGR_CONF_IMG    = "/okm:categories/RUND/CONFIG/IMG/";
const CTGR_CONF_DATA   = "/okm:categories/RUND/CONFIG/DATA/";
```

---

## Conclusión

Esta documentación cubre la integración completa de RUND con OpenKM a través de la clase `OpenKM.php`. Los puntos clave son:

1. **Autenticación:** Basic Auth con timeouts configurados
2. **Estructura Dual:** Taxonomías (físicas) y Categorías (lógicas)
3. **CRUD Completo:** Crear, leer, actualizar y eliminar documentos
4. **Versionado:** Sistema checkout/checkin para control de versiones
5. **Búsquedas:** Múltiples métodos de búsqueda y filtrado
6. **Categorización:** Sistema transversal de clasificación
7. **Manejo de Errores:** Detección y manejo de excepciones de OpenKM
8. **Ejemplos Prácticos:** Casos de uso reales y código listo para usar

Para más información sobre OpenKM, consultar la documentación oficial en: https://docs.openkm.com/
