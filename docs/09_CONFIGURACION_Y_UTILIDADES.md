# 09 - Configuración y Utilidades del Sistema

## Tabla de Contenidos
1. [Config.php - Constantes del Sistema](#configphp---constantes-del-sistema)
2. [Utils.php - Funciones de Utilidad](#utilsphp---funciones-de-utilidad)
3. [Router.php - Sistema de Enrutamiento](#routerphp---sistema-de-enrutamiento)
4. [BaseController.php - Clase Base de Controllers](#basecontrollerphp---clase-base-de-controllers)
5. [Variables de Entorno](#variables-de-entorno)

---

## Config.php - Constantes del Sistema

**Ubicación**: `/app/src/Config/Config.php`

Archivo central que define todas las constantes del sistema RUND, organizadas en módulos para facilitar su gestión y mantenimiento.

### Configuración Principal

```php
namespace RUND\Config;

class Config
{
  // Autenticación OpenKM
  const USER = "okmAdmin";
  const PASSWORD = "admin";

  // API REST de OpenKM
  const REST = "/services/rest/";

  // Directorio temporal
  const TEMP_DIR = __DIR__ . "/../../tmp/";
}
```

#### Descripción de Constantes Principales

| Constante | Tipo | Descripción | Ejemplo |
|-----------|------|-------------|---------|
| `USER` | string | Usuario de autenticación para OpenKM | "okmAdmin" |
| `PASSWORD` | string | Contraseña de autenticación para OpenKM | "admin" |
| `REST` | string | Endpoint base de la API REST de OpenKM | "/services/rest/" |
| `TEMP_DIR` | string | Directorio temporal para archivos | "/app/tmp/" |

### Estructura de Rutas en OpenKM

El sistema organiza las rutas en OpenKM en dos jerarquías principales: **Taxonomía** y **Categorías**.

#### Rutas Base (2 constantes)

```php
// Raíz de taxonomía
const ROOT_TAX = "/okm:root/RUND/";

// Raíz de categorías
const ROOT_CTG = "/okm:categories/RUND/";
```

| Constante | Descripción | Valor |
|-----------|-------------|-------|
| `ROOT_TAX` | Ruta raíz de taxonomía en OpenKM | "/okm:root/RUND/" |
| `ROOT_CTG` | Ruta raíz de categorías en OpenKM | "/okm:categories/RUND/" |

#### Rutas Principales (6 constantes)

Combinan las rutas base con los módulos principales del sistema:

```php
// Documentos
const ROOT_TAX_DOCS = self::ROOT_TAX . "DOCUMENTOS/";
const ROOT_CTG_DOCS = self::ROOT_CTG . "DOCUMENTOS/";

// Docentes
const ROOT_TAX_PROF = self::ROOT_TAX . "DOCENTES/";
const ROOT_CTG_PROF = self::ROOT_CTG . "DOCENTES/";

// Configuración
const ROOT_TAX_CONF = self::ROOT_TAX . "CONFIG/";
const ROOT_CTG_CONF = self::ROOT_CTG . "CONFIG/";
```

| Constante | Tipo | Descripción | Ruta Completa |
|-----------|------|-------------|---------------|
| `ROOT_TAX_DOCS` | string | Documentos en taxonomía | "/okm:root/RUND/DOCUMENTOS/" |
| `ROOT_CTG_DOCS` | string | Documentos en categorías | "/okm:categories/RUND/DOCUMENTOS/" |
| `ROOT_TAX_PROF` | string | Docentes en taxonomía | "/okm:root/RUND/DOCENTES/" |
| `ROOT_CTG_PROF` | string | Docentes en categorías | "/okm:categories/RUND/DOCENTES/" |
| `ROOT_TAX_CONF` | string | Configuración en taxonomía | "/okm:root/RUND/CONFIG/" |
| `ROOT_CTG_CONF` | string | Configuración en categorías | "/okm:categories/RUND/CONFIG/" |

#### Categorías Específicas (5 constantes)

Rutas de categorías para organización funcional de documentos:

```php
// Categoría de listados
const CTGR_LISTADOS = self::ROOT_CTG_DOCS . "LISTADOS/";

// Categoría de firmas
const CTGR_FIRMAS = self::ROOT_CTG_DOCS . "FIRMAS/";

// Categoría de hojas de vida
const CTGR_DOCS_HOJAS = self::ROOT_CTG_DOCS . "HOJAS_DE_VIDA/";

// Categoría de imágenes de aplicación
const CTGR_CONF_IMG = self::ROOT_CTG_CONF . "IMG/";

// Categoría de datos de aplicación
const CTGR_CONF_DATA = self::ROOT_CTG_CONF . "DATA/";
```

| Constante | Uso | Ruta Completa |
|-----------|-----|---------------|
| `CTGR_LISTADOS` | Almacena listados de docentes y estudiantes | "/okm:categories/RUND/DOCUMENTOS/LISTADOS/" |
| `CTGR_FIRMAS` | Almacena firmas digitales | "/okm:categories/RUND/DOCUMENTOS/FIRMAS/" |
| `CTGR_DOCS_HOJAS` | Almacena hojas de vida de docentes | "/okm:categories/RUND/DOCUMENTOS/HOJAS_DE_VIDA/" |
| `CTGR_CONF_IMG` | Almacena imágenes de configuración (logos, banners) | "/okm:categories/RUND/CONFIG/IMG/" |
| `CTGR_CONF_DATA` | Almacena datos JSON de configuración | "/okm:categories/RUND/CONFIG/DATA/" |

#### Taxonomías Específicas (8 constantes)

Rutas de taxonomía para la estructura de archivos:

```php
// Firmas
const TAX_FIRMAS = self::ROOT_TAX_DOCS . "FIRMAS/";

// Listados
const TAX_LISTADOS = self::ROOT_TAX_DOCS . "LISTADOS/";

// Hojas de vida
const TAX_HOJAS = self::ROOT_TAX_PROF . "HOJAS_DE_VIDA/";

// Certificados
const TAX_CERTIFICADOS = self::ROOT_TAX_DOCS . "CERTIFICADOS/";

// Plantillas base
const TAX_PLANTILLAS = self::ROOT_TAX_DOCS . "PLANTILLAS/";

// Plantillas de certificados
const TAX_PLANTILLAS_CERTIFICADOS = self::TAX_PLANTILLAS . "CERTIFICADOS/";

// Plantillas de reportes
const TAX_PLANTILLAS_REPORTES = self::TAX_PLANTILLAS . "REPORTES/";

// Datos de aplicación
const TAX_APP_DATA = self::ROOT_TAX_CONF . "DATA/";

// Imágenes de aplicación
const TAX_APP_IMG = self::ROOT_TAX_CONF . "IMG/";
```

| Constante | Propósito | Ruta Completa |
|-----------|-----------|---------------|
| `TAX_FIRMAS` | Archivos de firmas en taxonomía | "/okm:root/RUND/DOCUMENTOS/FIRMAS/" |
| `TAX_LISTADOS` | Archivos de listados en taxonomía | "/okm:root/RUND/DOCUMENTOS/LISTADOS/" |
| `TAX_HOJAS` | Hojas de vida de docentes | "/okm:root/RUND/DOCENTES/HOJAS_DE_VIDA/" |
| `TAX_CERTIFICADOS` | Certificados generados | "/okm:root/RUND/DOCUMENTOS/CERTIFICADOS/" |
| `TAX_PLANTILLAS` | Plantillas base del sistema | "/okm:root/RUND/DOCUMENTOS/PLANTILLAS/" |
| `TAX_PLANTILLAS_CERTIFICADOS` | Plantillas para certificados | "/okm:root/RUND/DOCUMENTOS/PLANTILLAS/CERTIFICADOS/" |
| `TAX_PLANTILLAS_REPORTES` | Plantillas para reportes | "/okm:root/RUND/DOCUMENTOS/PLANTILLAS/REPORTES/" |
| `TAX_APP_DATA` | Datos de configuración JSON | "/okm:root/RUND/CONFIG/DATA/" |
| `TAX_APP_IMG` | Imágenes (logos, banners, etc.) | "/okm:root/RUND/CONFIG/IMG/" |

### Resumen de Constantes por Categoría

| Categoría | Cantidad | Propósito |
|-----------|----------|-----------|
| Configuración Principal | 4 | Autenticación y configuración base |
| Rutas Base | 2 | Raíces de taxonomía y categorías |
| Rutas Principales | 6 | Módulos principales (Documentos, Docentes, Config) |
| Categorías Específicas | 5 | Organización funcional |
| Taxonomías Específicas | 8 | Estructura de archivos |
| **TOTAL** | **25** | **Todas las constantes del sistema** |

### Uso en el Código

```php
use RUND\Config\Config;

// Acceder a constantes
$userOKM = Config::USER;
$pathFirmas = Config::TAX_FIRMAS;
$tempDir = Config::TEMP_DIR;

// Construir rutas dinámicamente
$rutaCertificado = Config::TAX_CERTIFICADOS . $numeroCertificado . ".pdf";
$rutaPlantilla = Config::TAX_PLANTILLAS_CERTIFICADOS . "plantilla_base.docx";
```

---

## Utils.php - Funciones de Utilidad

**Ubicación**: `/app/src/Core/Utils.php`

Clase estática con 11 métodos de utilidad general usados en todo el sistema.

### 1. cors()

Habilita CORS (Cross-Origin Resource Sharing) para permitir solicitudes desde dominios externos.

```php
public static function cors(): void
```

**Parámetros**: Ninguno

**Retorno**: `void`

**Descripción**:
- Configura headers CORS basándose en el origen de la solicitud
- Permite credenciales (cookies, autenticación)
- Maneja solicitudes OPTIONS (preflight)
- Configura métodos permitidos: GET, POST, PUT, DELETE, OPTIONS

**Ejemplo de uso**:
```php
use RUND\Core\Utils;

// En index.php o punto de entrada
Utils::cors();
```

**Comportamiento**:
```php
// Si viene una solicitud de http://example.com:
Access-Control-Allow-Origin: http://example.com
Access-Control-Allow-Credentials: true
Access-Control-Max-Age: 86400

// Si es OPTIONS:
Access-Control-Allow-Methods: GET, POST, OPTIONS, PUT, DELETE
Access-Control-Allow-Headers: [headers solicitados]
```

### 2. getCoincidencias()

Devuelve los elementos que coinciden entre dos arrays.

```php
public static function getCoincidencias(array $array1, array $array2): array
```

**Parámetros**:
- `$array1` (array): Primer array
- `$array2` (array): Segundo array

**Retorno**: `array` - Array con elementos que aparecen en ambos arrays

**Descripción**:
- Elimina duplicados en ambos arrays
- Encuentra la intersección
- Útil para comparar listas

**Ejemplo de uso**:
```php
$docentes1 = ["12345", "67890", "11111", "12345"];
$docentes2 = ["67890", "22222", "11111"];

$coincidencias = Utils::getCoincidencias($docentes1, $docentes2);
// Resultado: ["67890", "11111"]
```

### 3. esArraySimple()

Determina si un array es simple (indexado numéricamente desde 0).

```php
public static function esArraySimple(array|null $array): bool
```

**Parámetros**:
- `$array` (array|null): Array a evaluar

**Retorno**: `bool` - TRUE si es array simple, FALSE si es asociativo o null

**Descripción**:
- Verifica que sea array
- Verifica que no esté vacío
- Verifica que la primera clave sea 0

**Ejemplo de uso**:
```php
$simple = [1, 2, 3, 4];
$asociativo = ["nombre" => "Juan", "edad" => 30];

Utils::esArraySimple($simple);      // true
Utils::esArraySimple($asociativo);  // false
Utils::esArraySimple([]);           // false
Utils::esArraySimple(null);         // false
```

### 4. textoAnombreCarpeta()

Transforma texto en formato válido para nombres de carpetas en OpenKM.

```php
public static function textoAnombreCarpeta(string $texto): string
```

**Parámetros**:
- `$texto` (string): Texto a transformar

**Retorno**: `string` - Texto en mayúsculas, sin acentos, espacios reemplazados por guiones bajos

**Descripción**:
- Elimina acentos (á→a, é→e, etc.)
- Convierte ñ→n
- Convierte a mayúsculas
- Reemplaza espacios por guiones bajos

**Ejemplo de uso**:
```php
$nombre = "José María Pérez Niño";
$carpeta = Utils::textoAnombreCarpeta($nombre);
// Resultado: "JOSE_MARIA_PEREZ_NINO"

$titulo = "Certificación en Administración Pública";
$carpeta = Utils::textoAnombreCarpeta($titulo);
// Resultado: "CERTIFICACION_EN_ADMINISTRACION_PUBLICA"
```

### 5. extraeElemento()

Extrae un elemento de un array de arrays asociativos buscando por un campo específico.

```php
public static function extraeElemento(
  array $array,
  string $campo,
  string $busqueda
): array|null
```

**Parámetros**:
- `$array` (array): Array de arrays asociativos
- `$campo` (string): Nombre del campo por el cual buscar
- `$busqueda` (string): Valor a buscar

**Retorno**: `array|null` - El elemento encontrado o null

**Descripción**:
- Busca en un array de objetos
- Retorna el primer elemento que coincida
- Retorna null si no encuentra coincidencia

**Ejemplo de uso**:
```php
$docentes = [
  ["id" => "123", "nombre" => "Juan Pérez"],
  ["id" => "456", "nombre" => "María García"],
  ["id" => "789", "nombre" => "Carlos López"]
];

$docente = Utils::extraeElemento($docentes, "id", "456");
// Resultado: ["id" => "456", "nombre" => "María García"]

$noExiste = Utils::extraeElemento($docentes, "id", "999");
// Resultado: null
```

### 6. arrayMatch()

Verifica si algún elemento de un array contiene un fragmento de texto.

```php
public static function arrayMatch(array $array, string $fragmento): bool
```

**Parámetros**:
- `$array` (array): Array a verificar
- `$fragmento` (string): Fragmento de texto a buscar

**Retorno**: `bool` - TRUE si encuentra coincidencia, FALSE en caso contrario

**Descripción**:
- Busca coincidencia parcial en elementos
- Sensible a mayúsculas/minúsculas
- Útil para filtros y búsquedas

**Ejemplo de uso**:
```php
$categorias = [
  "/okm:categories/RUND/DOCUMENTOS/",
  "/okm:categories/RUND/DOCENTES/",
  "/okm:categories/RUND/CONFIG/"
];

Utils::arrayMatch($categorias, "DOCENTES");  // true
Utils::arrayMatch($categorias, "ESTUDIANTES");  // false
Utils::arrayMatch($categorias, "/RUND/");  // true
```

### 7. csvToJsonByColumns()

Convierte un array CSV en JSON estructurado por columnas.

```php
public static function csvToJsonByColumns(array $csvArray): string
```

**Parámetros**:
- `$csvArray` (array): Array bidimensional donde la primera fila son los encabezados

**Retorno**: `string` - JSON estructurado por columnas

**Descripción**:
- Primera fila = encabezados
- Resto de filas = datos
- Organiza por columnas (no por filas)

**Ejemplo de uso**:
```php
$csv = [
  ["nombre", "documento", "email"],
  ["Juan Pérez", "12345", "juan@esap.edu.co"],
  ["María García", "67890", "maria@esap.edu.co"]
];

$json = Utils::csvToJsonByColumns($csv);
```

**Resultado**:
```json
{
  "nombre": ["Juan Pérez", "María García"],
  "documento": ["12345", "67890"],
  "email": ["juan@esap.edu.co", "maria@esap.edu.co"]
}
```

### 8. generaID()

Genera un ID único aleatorio de 16 caracteres hexadecimales.

```php
public static function generaID(array $actualData = []): string
```

**Parámetros**:
- `$actualData` (array): Array opcional de objetos existentes con campo 'id'

**Retorno**: `string` - ID único de 16 caracteres hexadecimales

**Descripción**:
- Genera IDs criptográficamente seguros
- Verifica que no exista en `$actualData`
- Basado en `random_bytes()`

**Ejemplo de uso**:
```php
// ID simple
$id = Utils::generaID();
// Resultado: "a3f2c9d8e1b4567f"

// ID garantizado único respecto a lista existente
$documentos = [
  ["id" => "abc123def456", "nombre" => "Doc1"],
  ["id" => "def789ghi012", "nombre" => "Doc2"]
];

$nuevoId = Utils::generaID($documentos);
// Resultado: Un ID que NO está en la lista
```

### 9. borrarMultiplesArchivos()

Elimina múltiples archivos usando patrones glob.

```php
public static function borrarMultiplesArchivos(string $nombre): array
```

**Parámetros**:
- `$nombre` (string): Patrón glob de archivos a borrar

**Retorno**: `array` - `['error' => null]` si éxito, `['error' => 'mensaje']` si falla

**Descripción**:
- Soporta patrones glob: `*`, `?`, `{ext1,ext2}`
- Ignora directorios
- Retorna error solo si falla

**Ejemplo de uso**:
```php
use RUND\Config\Config;

// Borrar todos los PDFs temporales
$resultado = Utils::borrarMultiplesArchivos(Config::TEMP_DIR . "*.pdf");

// Borrar archivos específicos
$resultado = Utils::borrarMultiplesArchivos(Config::TEMP_DIR . "certificado_*");

// Borrar múltiples extensiones
$resultado = Utils::borrarMultiplesArchivos(Config::TEMP_DIR . "temp.{pdf,docx,xlsx}");

if ($resultado['error'] === null) {
  echo "Archivos eliminados correctamente";
} else {
  echo "Error: " . $resultado['error'];
}
```

### 10. buscarPorId()

Busca un elemento en un array de objetos por su campo 'id'.

```php
public static function buscarPorId(array $array, string $id): array|null
```

**Parámetros**:
- `$array` (array): Array de arrays asociativos
- `$id` (string): ID a buscar

**Retorno**: `array|null` - Objeto encontrado o null

**Descripción**:
- Busca por el campo 'id' específicamente
- Retorna el primer elemento que coincida
- Optimizado para búsquedas por ID

**Ejemplo de uso**:
```php
$certificados = [
  ["id" => "cert_001", "tipo" => "Laboral", "estado" => "Aprobado"],
  ["id" => "cert_002", "tipo" => "Estudio", "estado" => "Pendiente"],
  ["id" => "cert_003", "tipo" => "Docente", "estado" => "Aprobado"]
];

$cert = Utils::buscarPorId($certificados, "cert_002");
// Resultado: ["id" => "cert_002", "tipo" => "Estudio", "estado" => "Pendiente"]

$noExiste = Utils::buscarPorId($certificados, "cert_999");
// Resultado: null
```

### 11. getLabels()

Extrae el campo "label" de un objeto.

```php
public static function getLabels(array $obj): string
```

**Parámetros**:
- `$obj` (array): Objeto que contiene un campo "label"

**Retorno**: `string` - Valor del campo "label"

**Descripción**:
- Función auxiliar para `array_map()`
- Asume que el campo "label" existe
- Útil para extraer etiquetas de listas

**Ejemplo de uso**:
```php
$categorias = [
  ["id" => "cat1", "label" => "Certificados"],
  ["id" => "cat2", "label" => "Reportes"],
  ["id" => "cat3", "label" => "Plantillas"]
];

$labels = array_map([Utils::class, 'getLabels'], $categorias);
// Resultado: ["Certificados", "Reportes", "Plantillas"]

// Uso práctico en dropdown
foreach ($categorias as $cat) {
  $label = Utils::getLabels($cat);
  echo "<option value='{$cat['id']}'>$label</option>";
}
```

---

## Router.php - Sistema de Enrutamiento

**Ubicación**: `/app/src/Core/Router.php`

Sistema moderno de enrutamiento con soporte para:
- Parámetros dinámicos en rutas
- Middleware global y por ruta
- Agrupación de rutas con prefijos
- Controllers y métodos separados

### Métodos Principales

#### 1. Constructor

```php
public function __construct(string $basePath = '')
```

**Parámetros**:
- `$basePath` (string): Prefijo base para todas las rutas

**Ejemplo**:
```php
$router = new Router('/api/v2');
```

#### 2. get()

Registra una ruta para solicitudes GET.

```php
public function get(
  string $path,
  callable|array $handler,
  array $middleware = []
): self
```

**Parámetros**:
- `$path` (string): Ruta del endpoint (puede incluir `{param}`)
- `$handler` (callable|array): Función o `[Controller::class, 'method']`
- `$middleware` (array): Array de funciones middleware opcionales

**Retorno**: `self` (fluent interface)

**Ejemplo**:
```php
use RUND\Controllers\V2\DocentesController;

// Con función anónima
$router->get('/test', function($params) {
  return ['mensaje' => 'Test OK'];
});

// Con controller
$router->get('/docentes', [DocentesController::class, 'listarTodos']);

// Con parámetros dinámicos
$router->get('/docentes/{id}', [DocentesController::class, 'obtenerPorId']);

// Con middleware
$router->get('/admin/stats',
  [AdminController::class, 'stats'],
  [
    function($method, $path, $params) {
      // Validar autenticación
      if (!isset($_SESSION['admin'])) {
        http_response_code(401);
        echo json_encode(['error' => 'No autorizado']);
        return false; // Detiene ejecución
      }
      return true; // Continúa
    }
  ]
);
```

#### 3. post()

Registra una ruta para solicitudes POST.

```php
public function post(
  string $path,
  callable|array $handler,
  array $middleware = []
): self
```

**Parámetros**: Idénticos a `get()`

**Ejemplo**:
```php
// Crear nuevo docente
$router->post('/docentes', [DocentesController::class, 'crear']);

// Upload con parámetro
$router->post('/archivos/{tipo}', [ArchivosController::class, 'subir']);
```

#### 4. put()

Registra una ruta para solicitudes PUT.

```php
public function put(
  string $path,
  callable|array $handler,
  array $middleware = []
): self
```

**Ejemplo**:
```php
// Actualizar docente
$router->put('/docentes/{id}', [DocentesController::class, 'actualizar']);
```

#### 5. delete()

Registra una ruta para solicitudes DELETE.

```php
public function delete(
  string $path,
  callable|array $handler,
  array $middleware = []
): self
```

**Ejemplo**:
```php
// Eliminar docente
$router->delete('/docentes/{id}', [DocentesController::class, 'eliminar']);
```

#### 6. any()

Registra una ruta para cualquier método HTTP.

```php
public function any(
  string $path,
  callable|array $handler,
  array $middleware = []
): self
```

**Descripción**: Registra la ruta para GET, POST, PUT, DELETE, PATCH, OPTIONS

**Ejemplo**:
```php
// Health check accesible por cualquier método
$router->any('/health', function($params) {
  return ['status' => 'OK', 'timestamp' => time()];
});
```

#### 7. group()

Agrupa rutas bajo un prefijo común.

```php
public function group(string $prefix, callable $callback): self
```

**Parámetros**:
- `$prefix` (string): Prefijo para las rutas del grupo
- `$callback` (callable): Función que define las rutas del grupo

**Retorno**: `self`

**Ejemplo**:
```php
// Agrupar rutas de docentes
$router->group('/docentes', function($router) {
  $router->get('/', [DocentesController::class, 'listarTodos']);
  $router->get('/{id}', [DocentesController::class, 'obtenerPorId']);
  $router->post('/', [DocentesController::class, 'crear']);
  $router->put('/{id}', [DocentesController::class, 'actualizar']);
  $router->delete('/{id}', [DocentesController::class, 'eliminar']);
});
// Genera: GET /docentes, GET /docentes/{id}, POST /docentes, etc.

// Agrupar versión de API
$router->group('/v2', function($router) {
  $router->group('/certificados', function($router) {
    $router->get('/', [CertificadosController::class, 'listar']);
    $router->post('/', [CertificadosController::class, 'generar']);
  });
});
// Genera: GET /v2/certificados, POST /v2/certificados
```

#### 8. addGlobalMiddleware()

Añade middleware que se ejecuta en todas las rutas.

```php
public function addGlobalMiddleware(callable $middleware): self
```

**Parámetros**:
- `$middleware` (callable): Función middleware con firma `function($method, $path): bool`

**Retorno**: `self`

**Ejemplo**:
```php
// Logging global
$router->addGlobalMiddleware(function($method, $path) {
  error_log("[$method] $path - " . date('Y-m-d H:i:s'));
  return true; // Continuar
});

// Rate limiting global
$router->addGlobalMiddleware(function($method, $path) {
  $ip = $_SERVER['REMOTE_ADDR'];
  if (RateLimiter::excedeLimite($ip)) {
    http_response_code(429);
    echo json_encode(['error' => 'Demasiadas solicitudes']);
    return false; // Detener
  }
  return true;
});
```

#### 9. dispatch()

Procesa la solicitud HTTP actual y ejecuta el handler correspondiente.

```php
public function dispatch(): void
```

**Parámetros**: Ninguno (lee de `$_SERVER`)

**Retorno**: `void`

**Descripción**:
1. Extrae método y ruta de `$_SERVER`
2. Ejecuta middleware global
3. Busca ruta coincidente
4. Ejecuta middleware de ruta
5. Ejecuta handler
6. Envía respuesta

**Ejemplo**:
```php
// En index.php
$router = new Router('/api/v2');

// Registrar rutas...
$router->get('/test', function() { return ['ok' => true]; });

// Despachar
$router->dispatch();
```

### Métodos Privados Internos

#### pathToRegex()

Convierte una ruta con parámetros en expresión regular.

```php
private function pathToRegex(string $path): string
```

**Descripción**:
- Convierte `/docentes/{id}` en `#^/docentes/([^/]+)$#`
- Permite matching con parámetros dinámicos

**Ejemplo interno**:
```php
"/docentes/{id}/certificados/{tipo}"
// Se convierte en:
"#^/docentes/([^/]+)/certificados/([^/]+)$#"
```

#### findRoute()

Busca una ruta que coincida con el método y path actual.

```php
private function findRoute(string $method, string $path): ?array
```

**Retorno**: Array con información de la ruta o null si no encuentra

**Estructura de retorno**:
```php
[
  'method' => 'GET',
  'path' => '/docentes/{id}',
  'pattern' => '#^/docentes/([^/]+)$#',
  'handler' => [DocentesController::class, 'obtenerPorId'],
  'middleware' => [],
  'params' => ['id' => '12345']
]
```

### Sistema de Middleware

Los middleware son funciones que se ejecutan antes del handler principal.

**Firma de middleware**:
```php
function(string $method, string $path, array $params = []): bool
```

**Retorno**:
- `true`: Continuar con siguiente middleware/handler
- `false`: Detener ejecución (ya se envió respuesta)

**Ejemplo completo de middleware**:
```php
// Middleware de autenticación
$authMiddleware = function($method, $path, $params) {
  $token = $_SERVER['HTTP_AUTHORIZATION'] ?? null;

  if (!$token) {
    http_response_code(401);
    echo json_encode(['error' => 'Token requerido']);
    return false;
  }

  if (!TokenValidator::validate($token)) {
    http_response_code(403);
    echo json_encode(['error' => 'Token inválido']);
    return false;
  }

  // Guardar usuario en sesión
  $_SESSION['user'] = TokenValidator::getUser($token);
  return true;
};

// Usar en ruta
$router->get('/admin/dashboard',
  [AdminController::class, 'dashboard'],
  [$authMiddleware]
);
```

### Manejo de Parámetros Dinámicos

El router extrae automáticamente parámetros de la URL.

**Ejemplo completo**:
```php
// Definir ruta
$router->get('/docentes/{id}/certificados/{tipo}',
  function($params) {
    return [
      'docente_id' => $params['id'],
      'tipo_certificado' => $params['tipo']
    ];
  }
);

// Solicitud: GET /docentes/12345/certificados/laboral
// $params = ['id' => '12345', 'tipo' => 'laboral']
```

### Manejo de Respuestas

El router maneja automáticamente diferentes tipos de respuestas:

#### Respuestas JSON (arrays)

```php
$router->get('/test', function() {
  return ['mensaje' => 'OK', 'timestamp' => time()];
});
// Automáticamente envía como JSON con headers correctos
```

#### Respuestas de texto

```php
$router->get('/version', function() {
  return "RUND API v2.0";
});
// Envía como text/plain
```

#### Respuestas manuales (archivos, redirects)

```php
$router->get('/download/{id}', function($params) {
  $file = obtenerArchivo($params['id']);

  header('Content-Type: application/pdf');
  header('Content-Disposition: attachment; filename="archivo.pdf"');
  readfile($file);

  return null; // null indica que ya se manejó la respuesta
});
```

---

## BaseController.php - Clase Base de Controllers

**Ubicación**: `/app/src/Controllers/BaseController.php`

Clase abstracta que proporciona métodos comunes para todos los controllers.

### Métodos Protegidos

#### 1. getPostData()

Obtiene datos POST del cuerpo de la solicitud (JSON o form-data).

```php
protected function getPostData(): ?array
```

**Retorno**: `array|null` - Datos decodificados o null si está vacío

**Descripción**:
- Primero verifica `$_POST`
- Si está vacío, lee `php://input`
- Decodifica JSON automáticamente
- Retorna null si no hay datos

**Ejemplo de uso**:
```php
class DocentesController extends BaseController
{
  public function crear($params): array
  {
    $data = $this->getPostData();

    if (!$data) {
      return $this->errorResponse('No se recibieron datos', 400);
    }

    // Procesar $data...
  }
}
```

#### 2. getQueryParams()

Obtiene parámetros GET de la URL.

```php
protected function getQueryParams(): array
```

**Retorno**: `array` - Parámetros GET (`$_GET`)

**Ejemplo de uso**:
```php
public function listar($params): array
{
  $query = $this->getQueryParams();

  $page = $query['page'] ?? 1;
  $limit = $query['limit'] ?? 20;
  $search = $query['search'] ?? null;

  // Usar parámetros para filtrar...
}
```

#### 3. getFiles()

Obtiene archivos subidos.

```php
protected function getFiles(): array
```

**Retorno**: `array` - Archivos subidos (`$_FILES`)

**Ejemplo de uso**:
```php
public function subirFoto($params): array
{
  $files = $this->getFiles();

  if (!isset($files['foto'])) {
    return $this->errorResponse('Archivo no enviado', 400);
  }

  $foto = $files['foto'];

  if ($foto['error'] !== UPLOAD_ERR_OK) {
    return $this->errorResponse('Error al subir archivo', 500);
  }

  // Procesar archivo...
}
```

#### 4. validateRequired()

Valida que existan campos requeridos en los datos.

```php
protected function validateRequired(array $data, array $required): array
```

**Parámetros**:
- `$data` (array): Datos a validar
- `$required` (array): Array con nombres de campos requeridos

**Retorno**:
- `[]` (array vacío) si todo está OK
- `['error' => 'mensaje']` si faltan campos

**Descripción**:
- Verifica que cada campo requerido exista
- Verifica que no esté vacío
- Establece código HTTP 400 si falla

**Ejemplo de uso**:
```php
public function crear($params): array
{
  $data = $this->getPostData();

  $validacion = $this->validateRequired($data, [
    'nombre',
    'documento',
    'email'
  ]);

  if (!empty($validacion)) {
    return $validacion; // Ya tiene el error
  }

  // Procesar datos validados...
}
```

**Respuesta de error**:
```json
{
  "error": "Parámetros requeridos faltantes: nombre, email"
}
```

#### 5. errorResponse()

Genera una respuesta de error con código HTTP.

```php
protected function errorResponse(string $message, int $code = 400): array
```

**Parámetros**:
- `$message` (string): Mensaje de error
- `$code` (int): Código HTTP (default: 400)

**Retorno**: `array` - `['error' => $message]`

**Descripción**:
- Establece código HTTP automáticamente
- Retorna estructura consistente de error

**Ejemplo de uso**:
```php
public function obtenerPorId($params): array
{
  $id = $params['id'] ?? null;

  if (!$id) {
    return $this->errorResponse('ID no proporcionado', 400);
  }

  $docente = DocentesService::buscar($id);

  if (!$docente) {
    return $this->errorResponse('Docente no encontrado', 404);
  }

  return $this->successResponse($docente);
}
```

#### 6. successResponse()

Genera una respuesta exitosa.

```php
protected function successResponse(
  array $data = [],
  string $message = null
): array
```

**Parámetros**:
- `$data` (array): Datos a retornar
- `$message` (string|null): Mensaje opcional de éxito

**Retorno**: `array` - Datos con mensaje opcional

**Ejemplo de uso**:
```php
public function crear($params): array
{
  $data = $this->getPostData();

  // Validar, procesar...
  $docente = DocentesService::crear($data);

  return $this->successResponse(
    ['docente' => $docente],
    'Docente creado correctamente'
  );
}
```

**Respuesta**:
```json
{
  "docente": {
    "id": "12345",
    "nombre": "Juan Pérez"
  },
  "message": "Docente creado correctamente"
}
```

#### 7. fileResponse()

Maneja respuestas de archivos (downloads, imágenes).

```php
protected function fileResponse(callable $fileHandler): ?array
```

**Parámetros**:
- `$fileHandler` (callable): Función que maneja el envío del archivo

**Retorno**: `null` (indica que el handler ya envió la respuesta)

**Descripción**:
- Ejecuta el handler proporcionado
- El handler debe enviar headers y contenido
- Retorna null para indicar al router que ya se manejó la respuesta

**Ejemplo de uso**:
```php
public function descargar($params): ?array
{
  return $this->fileResponse(function() use ($params) {
    $id = $params['id'];
    $archivo = ArchivosService::obtener($id);

    if (!$archivo) {
      http_response_code(404);
      echo json_encode(['error' => 'Archivo no encontrado']);
      return;
    }

    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . $archivo['nombre'] . '"');
    header('Content-Length: ' . filesize($archivo['ruta']));

    readfile($archivo['ruta']);
  });
}
```

### Patrón de Uso Completo

```php
namespace RUND\Controllers\V2;

use RUND\Controllers\BaseController;
use RUND\Services\DocentesService;

class DocentesController extends BaseController
{
  /**
   * GET /docentes
   */
  public function listarTodos($params): array
  {
    $query = $this->getQueryParams();
    $page = $query['page'] ?? 1;

    $docentes = DocentesService::listar($page);

    return $this->successResponse(['docentes' => $docentes]);
  }

  /**
   * GET /docentes/{id}
   */
  public function obtenerPorId($params): array
  {
    $id = $params['id'];
    $docente = DocentesService::buscar($id);

    if (!$docente) {
      return $this->errorResponse('Docente no encontrado', 404);
    }

    return $this->successResponse(['docente' => $docente]);
  }

  /**
   * POST /docentes
   */
  public function crear($params): array
  {
    $data = $this->getPostData();

    $validacion = $this->validateRequired($data, [
      'nombre', 'documento', 'email'
    ]);

    if (!empty($validacion)) {
      return $validacion;
    }

    $docente = DocentesService::crear($data);

    return $this->successResponse(
      ['docente' => $docente],
      'Docente creado correctamente'
    );
  }

  /**
   * PUT /docentes/{id}
   */
  public function actualizar($params): array
  {
    $id = $params['id'];
    $data = $this->getPostData();

    $docente = DocentesService::actualizar($id, $data);

    if (!$docente) {
      return $this->errorResponse('Docente no encontrado', 404);
    }

    return $this->successResponse(
      ['docente' => $docente],
      'Docente actualizado correctamente'
    );
  }

  /**
   * DELETE /docentes/{id}
   */
  public function eliminar($params): array
  {
    $id = $params['id'];
    $resultado = DocentesService::eliminar($id);

    if (!$resultado) {
      return $this->errorResponse('Docente no encontrado', 404);
    }

    return $this->successResponse([], 'Docente eliminado correctamente');
  }
}
```

---

## Variables de Entorno

**Ubicación**: `/.env` (raíz del proyecto)

El archivo `.env` contiene las configuraciones específicas del entorno (desarrollo, producción, etc.).

### Variables Requeridas

#### Servicios Externos

```bash
# URL de OpenKM (rund-core)
CORE_API_URL=http://rund-core:8080/OpenKM

# URL de servicio AI (rund-ai)
AI_API_URL=http://rund-ai:11434

# URL de servicio OCR (rund-ocr)
OCR_API_URL=http://rund-ocr:8000
```

#### LibreOffice

```bash
# Ejecutable de LibreOffice para conversiones PDF
LIBREOFFICE_EXECUTABLE=/usr/bin/libreoffice
```

### Variables Opcionales

```bash
# Nombre del proyecto (para Docker Compose)
COMPOSE_PROJECT_NAME=rund

# Entorno de ejecución
APP_ENV=development

# Debug mode
DEBUG=true
```

### Estructura del Archivo .env

```bash
# ============================================================================
# RUND API - Variables de Entorno
# ============================================================================

# Proyecto
COMPOSE_PROJECT_NAME=rund
APP_ENV=development
DEBUG=true

# OpenKM (rund-core)
CORE_API_URL=http://rund-core:8080/OpenKM

# Servicios AI/OCR
AI_API_URL=http://rund-ai:11434
OCR_API_URL=http://rund-ocr:8000

# LibreOffice
LIBREOFFICE_EXECUTABLE=/usr/bin/libreoffice

# Configuración PHP (opcional)
PHP_MEMORY_LIMIT=512M
PHP_MAX_EXECUTION_TIME=300
PHP_UPLOAD_MAX_FILESIZE=50M
PHP_POST_MAX_SIZE=50M
```

### Uso en el Código

```php
// Acceder a variables de entorno
$coreUrl = $_ENV['CORE_API_URL'];
$aiUrl = $_ENV['AI_API_URL'];
$ocrUrl = $_ENV['OCR_API_URL'];
$libreOffice = $_ENV['LIBREOFFICE_EXECUTABLE'];

// Verificar si existe
if (!isset($_ENV['CORE_API_URL'])) {
  throw new Exception('CORE_API_URL no está definida');
}

// Con valor por defecto
$debug = $_ENV['DEBUG'] ?? 'false';
$env = $_ENV['APP_ENV'] ?? 'production';
```

### Diferencias por Entorno

#### Desarrollo (Local)

```bash
CORE_API_URL=http://localhost:8080/OpenKM
AI_API_URL=http://localhost:11434
OCR_API_URL=http://localhost:8000
DEBUG=true
```

#### Producción (Docker)

```bash
CORE_API_URL=http://rund-core:8080/OpenKM
AI_API_URL=http://rund-ai:11434
OCR_API_URL=http://rund-ocr:8000
DEBUG=false
```

### Seguridad

- **NUNCA** commitear `.env` al repositorio
- Usar `.env.example` como plantilla
- Cada entorno debe tener su propio `.env`
- Proteger credenciales sensibles

```bash
# .env.example (SÍ se commitea)
CORE_API_URL=
AI_API_URL=
OCR_API_URL=
LIBREOFFICE_EXECUTABLE=/usr/bin/libreoffice

# .env (NO se commitea, en .gitignore)
CORE_API_URL=http://rund-core:8080/OpenKM
AI_API_URL=http://rund-ai:11434
OCR_API_URL=http://rund-ocr:8000
```

---

## Resumen

Este documento cubre:

1. **Config.php**: 25 constantes organizadas en 5 categorías para configuración del sistema
2. **Utils.php**: 11 métodos estáticos de utilidad general
3. **Router.php**: Sistema completo de enrutamiento con middleware y parámetros dinámicos
4. **BaseController.php**: 7 métodos protegidos para facilitar desarrollo de controllers
5. **Variables de Entorno**: Configuración de servicios externos y ajustes del sistema

Estos componentes forman la base de configuración y utilidades del sistema RUND API.
