# Dependencias y Librerías

## Información General

Este documento detalla todas las dependencias de RUND-API, tanto de PHP como externas, incluyendo versiones exactas instaladas, propósito, uso en el código y ejemplos.

### Versión de PHP
- **Requerida:** PHP 8.3+
- **Instalada:** PHP 8.3 (FPM Alpine 3.19)

---

## Dependencias Principales de Composer

### 1. phpoffice/phpspreadsheet (^4.4)

**Versión instalada:** 4.5.0

#### Propósito
Librería para crear, leer y manipular archivos de hojas de cálculo (Excel, ODS, CSV). En RUND se utiliza principalmente para generar reportes con gráficos dinámicos.

#### Usado en
- **ReportesService** (`/app/src/Services/ReportesService.php`)
- **FileHandlers** (`/app/src/Handlers/FileHandlers.php`)

#### Clases principales utilizadas

```php
use PhpOffice\PhpSpreadsheet\IOFactory;           // Cargar plantillas Excel
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet; // Manipular hojas
use PhpOffice\PhpSpreadsheet\Cell\DataType;       // Tipos de datos
use PhpOffice\PhpSpreadsheet\Chart\DataSeries;    // Series de datos
use PhpOffice\PhpSpreadsheet\Chart\PlotArea;      // Área de gráficos
use PhpOffice\PhpSpreadsheet\Chart\Legend;        // Leyendas
use PhpOffice\PhpSpreadsheet\Chart\Title;         // Títulos
use PhpOffice\PhpSpreadsheet\Chart\Chart;         // Gráficos
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;         // Escritor Excel
```

#### Sub-dependencias importantes
- **phpoffice/math** (0.3.0): Manipulación de fórmulas matemáticas
- **markbaker/complex** (^3.0): Operaciones con números complejos
- **markbaker/matrix** (^3.0): Operaciones con matrices
- **maennchen/zipstream-php** (^2.1||^3.0): Manejo de archivos ZIP
- **composer/pcre**: Expresiones regulares mejoradas

#### Extensiones PHP requeridas
```
ext-ctype, ext-dom, ext-fileinfo, ext-gd, ext-iconv,
ext-libxml, ext-mbstring, ext-simplexml, ext-xml,
ext-xmlreader, ext-xmlwriter, ext-zip, ext-zlib
```

#### Ejemplos de uso

##### Cargar plantilla y generar reporte
```php
// ReportesService::generaReporte()
$spreadsheet = IOFactory::load($tempPlantilla);
$hoja = $spreadsheet->getActiveSheet();
$hoja->setTitle($nombreHoja);
```

##### Insertar datos en celdas
```php
// Crear tabla de datos
$hoja->setCellValue($inicio, $dataNomFil);
$hoja->getCell($posData)->setValueExplicit($data, DataType::TYPE_NUMERIC);
```

##### Crear gráfico de barras
```php
$series = new DataSeries(
    DataSeries::TYPE_BARCHART,
    DataSeries::GROUPING_CLUSTERED,
    range(0, count($valores) - 1),
    $etiquetas,  // Labels para las series
    $categorias, // Labels del eje X
    $valores     // Valores de la serie
);

$plotArea = new PlotArea(null, [$series]);
$legend = new Legend(Legend::POSITION_RIGHT, null, false);
$title = new Title("Distribución por categoría");
$chart = new Chart('chart1', $title, $legend, $plotArea, true, 'gap', null, null);
$hoja->addChart($chart);
```

##### Escribir archivo con gráficos
```php
$writer = new Xlsx($spreadsheet);
$writer->setIncludeCharts(true); // IMPORTANTE: incluir gráficos
$writer->save($rutaArchivo);
```

---

### 2. phpoffice/phpword (^1.4)

**Versión instalada:** 1.4.0

#### Propósito
Librería para crear y manipular documentos de Word (DOCX, ODT, RTF). En RUND se utiliza para generar certificados a partir de plantillas.

#### Usado en
- **CertificadosService** (`/app/src/Services/CertificadosService.php`)
- **CertificadosHandlers** (`/app/src/Handlers/CertificadosHandlers.php`)

#### Clases principales utilizadas

```php
use PhpOffice\PhpWord\TemplateProcessor; // Procesar plantillas
use PhpOffice\PhpWord\Element\TextRun;   // Texto con formato
```

#### Sub-dependencias importantes
- **phpoffice/math** (^0.3): Fórmulas matemáticas compartida con PhpSpreadsheet

#### Extensiones PHP requeridas
```
ext-dom, ext-gd, ext-json, ext-xml, ext-zip
```

#### Ejemplos de uso

##### Cargar plantilla DOCX
```php
// CertificadosService::creaCertificado()
$rutaPlantilla = Config::TEMP_DIR . $nombrePlantilla;
file_put_contents($rutaPlantilla, $respuesta);
$templateProcessor = new TemplateProcessor($rutaPlantilla);
```

##### Reemplazar placeholders simples
```php
// Texto simple sin formato
$templateProcessor->setValue('parrafo1', $texto);
$templateProcessor->setValue('firma_nombre', $valores["nombre"]);
$templateProcessor->setValue('firma_cargo', $valores["cargo"]);
```

##### Texto con formato HTML (negrita, cursiva, subrayado)
```php
// CertificadosService::creaParrafoComplejo()
public static function htmlToTextRun(string $texto): TextRun
{
    $textRun = new TextRun();

    $patron = '/<(strong|b|em|i|u|s|strike)>(.*?)<\/\1>/i';
    preg_match_all($patron, $texto, $coincidencias, PREG_OFFSET_CAPTURE);

    foreach ($coincidencias[0] as $key => $match) {
        $etiqueta = strtolower($coincidencias[1][$key][0]);
        $contenidoEtiqueta = $coincidencias[2][$key][0];

        switch ($etiqueta) {
            case 'strong':
            case 'b':
                $textRun->addText($contenidoEtiqueta, ['bold' => true]);
                break;
            case 'em':
            case 'i':
                $textRun->addText($contenidoEtiqueta, ['italic' => true]);
                break;
            case 'u':
                $textRun->addText($contenidoEtiqueta, ['underline' => 'single']);
                break;
            case 's':
            case 'strike':
                $textRun->addText($contenidoEtiqueta, ['strikethrough' => true]);
                break;
        }
    }

    return $textRun;
}

// Uso
$templateProcessor->setComplexValue($placeholder, self::htmlToTextRun($texto));
```

##### Clonar filas en tablas
```php
// CertificadosService::creaTabla()
$numFilas = count($valores);
$templateProcessor->cloneRow($primerEnc, $numFilas);

foreach ($valores as $numFila => $fila) {
    foreach ($fila as $celda) {
        $numPlace = $numFila + 1;
        $placeholder = key($celda);
        $valor = current($celda);
        $templateProcessor->setValue("$placeholder#$numPlace", $valor);
    }
}
```

##### Insertar imágenes
```php
// Insertar QR y firmas
$templateProcessor->setImageValue('valida_qr', $validacion["qr"]);
$templateProcessor->setImageValue('firma_imagen', $rutaImagen);
```

##### Guardar documento
```php
$templateProcessor->saveAs($rutaDestino);
```

---

### 3. dompdf/dompdf (^3.1)

**Versión instalada:** 3.1.0

#### Estado
⚠️ **NO UTILIZADO ACTUALMENTE**

#### Análisis
Esta librería fue incluida originalmente para conversiones HTML a PDF, pero en la implementación actual:
- Las conversiones DOCX → PDF se realizan con **LibreOffice headless**
- Las conversiones XLSX → PDF se realizan con **LibreOffice headless**
- No hay código que importe o use clases de Dompdf

#### Recomendación
**Evaluar eliminación de esta dependencia** para reducir el tamaño de las imágenes Docker y simplificar las dependencias.

```bash
# Para remover (después de confirmar que no se usa):
composer remove dompdf/dompdf
```

#### Sub-dependencias que se eliminarían
- dompdf/php-font-lib (1.0.1)
- dompdf/php-svg-lib (1.0.0)

---

### 4. endroid/qr-code (^6.0.9)

**Versión instalada:** 6.0.9

#### Propósito
Generación de códigos QR para validación de certificados. Cada certificado incluye un QR que apunta a una URL de validación.

#### Usado en
- **QRService** (`/app/src/Services/QRService.php`)
- **CertificadosService** (`/app/src/Services/CertificadosService.php`)

#### Clases principales utilizadas

```php
use Endroid\QrCode\Color\Color;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\RoundBlockSizeMode;
use Endroid\QrCode\Writer\PngWriter;
```

#### Sub-dependencias importantes
- **bacon/bacon-qr-code** (v3.0.1): Motor de generación de QR
- **dasprid/enum** (^1.0.3): Enumeraciones tipo-safe

#### Extensiones PHP requeridas
```
ext-iconv (requerida por bacon/bacon-qr-code)
```

#### Configuración estándar

```php
// QRService::createQRCode()
return new QrCode(
    data: $data,                                    // URL o texto a codificar
    encoding: new Encoding('UTF-8'),                // UTF-8 para soportar español
    errorCorrectionLevel: ErrorCorrectionLevel::Low, // Baja corrección (más datos)
    size: 300,                                       // 300x300 px
    margin: 10,                                      // Margen de 10px
    roundBlockSizeMode: RoundBlockSizeMode::Margin,  // Redondeo en márgenes
    foregroundColor: new Color(0, 0, 0),            // Negro
    backgroundColor: new Color(255, 255, 255)        // Blanco
);
```

#### Ejemplos de uso

##### Generar QR para validación de certificado
```php
// QRService::creaQR()
public static function creaQR(string $id): array
{
    // Validar ID (16 caracteres alfanuméricos)
    if (!preg_match('/^[a-zA-Z0-9]{16}$/', $id)) {
        throw new InvalidArgumentException(
            'El ID debe ser una cadena alfanumérica de exactamente 16 caracteres'
        );
    }

    // Construir URL de validación
    $url = "http://localhost:4000/validacion?certificado=" . $id;

    // Generar QR
    $qrCode = self::createQRCode($url);
    $writer = new PngWriter();
    $result = $writer->write($qrCode);

    // Guardar archivo temporal
    $filepath = Config::TEMP_DIR . "qr_" . $id . ".png";
    file_put_contents($filepath, $result->getString());

    return [
        "qr" => $filepath,
        "url" => $url,
        "id" => $id
    ];
}
```

##### Uso en certificados
```php
// CertificadosService::creaCertificado()
$validacion = QRService::creaQR($id);
$templateProcessor->setImageValue('valida_qr', $validacion["qr"]);
$templateProcessor->setValue('valida_url', $validacion["url"]);
unlink($validacion["qr"]); // Borrar temporal después de insertar
```

##### Niveles de corrección de errores disponibles

```php
ErrorCorrectionLevel::Low;     // ~7%  - Más datos, menos corrección
ErrorCorrectionLevel::Medium;  // ~15% - Balance
ErrorCorrectionLevel::Quartile; // ~25% - Más corrección
ErrorCorrectionLevel::High;    // ~30% - Máxima corrección, menos datos
```

##### Limpieza de QRs antiguos (funcionalidad incluida)
```php
// QRService::limpiarQRsAntiguos()
public static function limpiarQRsAntiguos(int $dias = 7): int
{
    $pattern = __DIR__ . "/../../tmp/" . "qr_*.png";
    $files = glob($pattern);
    $eliminados = 0;
    $tiempoLimite = time() - ($dias * 24 * 60 * 60);

    foreach ($files as $file) {
        if (filemtime($file) < $tiempoLimite) {
            if (unlink($file)) {
                $eliminados++;
            }
        }
    }
    return $eliminados;
}
```

---

## Extensiones PHP Requeridas

### 5. ext-gd (Librería GD)

**Propósito:** Manipulación y procesamiento de imágenes

#### Usado por
- phpoffice/phpspreadsheet (gráficos en Excel)
- phpoffice/phpword (inserción de imágenes)
- endroid/qr-code (generación de códigos QR en PNG)

#### Configuración en Dockerfile
```dockerfile
RUN docker-php-ext-configure gd \
  --with-freetype \
  --with-jpeg \
  && docker-php-ext-install -j$(nproc) gd
```

#### Dependencias del sistema
```dockerfile
libpng-dev
libjpeg-turbo-dev
freetype-dev
```

#### Verificar instalación
```bash
php -m | grep gd
# o
php -i | grep -i gd
```

---

### 6. ext-zip

**Propósito:** Compresión y descompresión de archivos ZIP

#### Usado por
- phpoffice/phpspreadsheet (archivos XLSX son ZIP)
- phpoffice/phpword (archivos DOCX son ZIP)
- Lectura/escritura de formatos Office Open XML

#### Configuración en Dockerfile
```dockerfile
RUN apk add --no-cache libzip-dev \
  && docker-php-ext-install -j$(nproc) zip
```

#### Verificar instalación
```bash
php -m | grep zip
```

#### Formato OOXML
Los archivos modernos de Office (DOCX, XLSX) son realmente archivos ZIP que contienen XML:
```
archivo.xlsx
├── [Content_Types].xml
├── _rels/
├── docProps/
└── xl/
    ├── worksheets/
    ├── sharedStrings.xml
    └── workbook.xml
```

---

### 7. ext-intl (Internacionalización)

**Propósito:** Soporte para internacionalización (i18n) y localización (l10n)

#### Usado por
- phpoffice/phpspreadsheet (formatos de fecha, números, moneda según locale)
- Formateo de fechas y números en español
- Collation y sorting sensible a idioma

#### Configuración en Dockerfile
```dockerfile
RUN apk add --no-cache icu-dev \
  && docker-php-ext-install -j$(nproc) intl
```

#### Verificar instalación
```bash
php -m | grep intl
```

#### Uso típico
```php
// Formatear fechas en español
$fmt = new IntlDateFormatter(
    'es_ES',
    IntlDateFormatter::LONG,
    IntlDateFormatter::NONE
);
echo $fmt->format(time()); // "20 de octubre de 2025"

// Formatear números
$fmt = new NumberFormatter('es_ES', NumberFormatter::DECIMAL);
echo $fmt->format(1234567.89); // "1.234.567,89"
```

---

### 8. Extensiones integradas (built-in)

Estas extensiones vienen habilitadas por defecto en PHP 8.3:

- **ext-ctype**: Verificación de tipos de caracteres
- **ext-dom**: Procesamiento de XML/HTML con DOM
- **ext-fileinfo**: Detección de tipos MIME
- **ext-iconv**: Conversión entre codificaciones de caracteres
- **ext-json**: Codificación/decodificación JSON
- **ext-libxml**: Procesamiento XML base
- **ext-mbstring**: Manejo de strings multibyte (UTF-8)
- **ext-simplexml**: API simplificada para XML
- **ext-xml**: Parser XML
- **ext-xmlreader**: Lectura de XML tipo stream
- **ext-xmlwriter**: Escritura de XML
- **ext-zlib**: Compresión gzip

---

## Dependencias de Desarrollo (require-dev)

### 9. phpunit/phpunit (^10.0)

**Versión instalada:** 10.5.55

#### Estado
⚠️ **CONFIGURADO PERO SIN TESTS IMPLEMENTADOS**

#### Propósito
Framework de testing unitario para PHP.

#### Configuración actual

```json
// composer.json
"autoload-dev": {
    "psr-4": {
        "RUND\\Tests\\": "tests/"
    }
}
```

#### Estado de tests
- ❌ **Directorio `tests/` no existe**
- ❌ **Archivo `phpunit.xml` no configurado**
- ❌ **Sin tests implementados**

#### Recomendaciones para implementar testing

##### Crear estructura básica
```bash
mkdir -p tests/Unit/Services
mkdir -p tests/Integration
```

##### Crear phpunit.xml
```xml
<?xml version="1.0" encoding="UTF-8"?>
<phpunit bootstrap="vendor/autoload.php"
         colors="true"
         stopOnFailure="false">
    <testsuites>
        <testsuite name="Unit">
            <directory>tests/Unit</directory>
        </testsuite>
        <testsuite name="Integration">
            <directory>tests/Integration</directory>
        </testsuite>
    </testsuites>
    <coverage>
        <include>
            <directory>app/src</directory>
        </include>
    </coverage>
</phpunit>
```

##### Tests recomendados

**1. ReportesService**
```php
// tests/Unit/Services/ReportesServiceTest.php
namespace RUND\Tests\Unit\Services;

use PHPUnit\Framework\TestCase;
use RUND\Services\ReportesService;

class ReportesServiceTest extends TestCase
{
    public function testArrayToCellConversion(): void
    {
        $this->assertEquals('A1', ReportesService::arrayToCell([1, 1]));
        $this->assertEquals('$B$2', ReportesService::arrayToCell([2, 2], true, true));
    }

    public function testAutoFitColsNoThrowsException(): void
    {
        // Test que autoFitCols no lance excepciones
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        $this->expectNotToPerformAssertions();
        ReportesService::autoFitCols($sheet);
    }
}
```

**2. QRService**
```php
// tests/Unit/Services/QRServiceTest.php
namespace RUND\Tests\Unit\Services;

use PHPUnit\Framework\TestCase;
use RUND\Services\QRService;
use InvalidArgumentException;

class QRServiceTest extends TestCase
{
    public function testCreaQRWithValidId(): void
    {
        $id = 'abcd1234efgh5678';
        $result = QRService::creaQR($id);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('qr', $result);
        $this->assertArrayHasKey('url', $result);
        $this->assertArrayHasKey('id', $result);
        $this->assertFileExists($result['qr']);

        // Cleanup
        if (file_exists($result['qr'])) {
            unlink($result['qr']);
        }
    }

    public function testCreaQRThrowsExceptionForInvalidId(): void
    {
        $this->expectException(InvalidArgumentException::class);
        QRService::creaQR('invalid-id'); // Menos de 16 caracteres
    }
}
```

**3. CertificadosService**
```php
// tests/Unit/Services/CertificadosServiceTest.php
namespace RUND\Tests\Unit\Services;

use PHPUnit\Framework\TestCase;
use RUND\Services\CertificadosService;

class CertificadosServiceTest extends TestCase
{
    public function testEsComplejoDetectsBoldText(): void
    {
        $textoSimple = "Este es un texto simple";
        $textoComplejo = "Este es <strong>texto en negrita</strong>";

        $this->assertFalse(CertificadosService::esComplejo($textoSimple));
        $this->assertTrue(CertificadosService::esComplejo($textoComplejo));
    }

    public function testEsComplejoDetectsItalicText(): void
    {
        $texto = "Texto con <em>cursiva</em>";
        $this->assertTrue(CertificadosService::esComplejo($texto));
    }
}
```

##### Ejecutar tests
```bash
# Todos los tests
./vendor/bin/phpunit

# Tests específicos
./vendor/bin/phpunit tests/Unit/Services/QRServiceTest.php

# Con coverage (requiere ext-xdebug)
./vendor/bin/phpunit --coverage-html coverage/
```

---

## Herramientas Externas

### 10. LibreOffice Headless

**Versión instalada:** LibreOffice 24.8.2.1 (Alpine package)

#### Propósito
Conversión de documentos Office a PDF sin interfaz gráfica. Esta es la herramienta principal para conversiones DOCX→PDF y XLSX→PDF.

#### Instalación en Dockerfile
```dockerfile
RUN apk add --no-cache \
  libreoffice \
  libreoffice-writer \  # Para DOCX
  libreoffice-calc      # Para XLSX
```

#### Fuentes instaladas
```dockerfile
# Fuentes Microsoft Core Fonts (Arial, Times New Roman, etc.)
RUN apk add --no-cache fontconfig ttf-dejavu cabextract wget && \
  mkdir -p /usr/share/fonts/truetype/msttcorefonts && \
  cd /usr/share/fonts/truetype/msttcorefonts && \
  wget https://downloads.sourceforge.net/corefonts/arial32.exe && \
  cabextract arial32.exe && \
  rm arial32.exe && \
  fc-cache -f -v
```

#### Usado en
- **LBService** (`/app/src/Services/LBService.php`)

#### Comandos disponibles

##### Conversión Excel a PDF
```php
// LBService::convierteExcelToPDF()
$comando = escapeshellcmd(
    "soffice --headless --convert-to pdf:calc_pdf_Export " .
    "--outdir " . escapeshellarg($dir) . " " .
    escapeshellarg($dir . $file) . " 2>/dev/null"
);
exec($comando, $output, $rtn);
```

##### Conversión Word a PDF
```php
// LBService::convierteWordToPDF()
$comando = escapeshellcmd(
    "soffice --headless --convert-to pdf:writer_pdf_Export " .
    "--outdir " . escapeshellarg($dir) . " " .
    escapeshellarg($dir . $file) . " 2>/dev/null"
);
exec($comando, $output, $rtn);
```

#### Filtros de exportación disponibles

| Filtro | Descripción |
|--------|-------------|
| `calc_pdf_Export` | Excel/Calc → PDF |
| `writer_pdf_Export` | Word/Writer → PDF (preferido) |
| `writer_web_pdf_Export` | HTML → PDF |
| `impress_pdf_Export` | PowerPoint/Impress → PDF |
| `draw_pdf_Export` | Draw → PDF |

#### Configuración en .env

Actualmente **NO se usa variable de entorno** en el código. El path está hardcoded:

```php
// LBService.php - línea 57
$_ENV["LIBREOFFICE_EXECUTABLE"]
```

**PROBLEMA:** Esta variable no está definida en ningún archivo .env

**SOLUCIÓN RECOMENDADA:** Agregar a `.env`:
```bash
# LibreOffice configuration
LIBREOFFICE_EXECUTABLE=/usr/bin/soffice
```

O cambiar el código para usar un valor por defecto:
```php
$libreoffice = $_ENV["LIBREOFFICE_EXECUTABLE"] ?? "/usr/bin/soffice";
$comando = escapeshellcmd(
    "$libreoffice --headless --convert-to pdf:$handler " .
    "--outdir " . escapeshellarg($dir) . " " .
    escapeshellarg($dir . $file) . " 2>/dev/null"
);
```

#### Verificar instalación
```bash
# En el contenedor
docker exec rund-api soffice --version
# LibreOffice 24.8.2.1 0f794b6e29741098670a3b95d60478a65d05ef13

# Listar fuentes disponibles
docker exec rund-api fc-list | grep -i arial
```

#### Troubleshooting común

##### Error: "Application Error"
```bash
# Verificar que el archivo existe
ls -la /tmp/archivo.docx

# Verificar permisos
chmod 644 /tmp/archivo.docx

# Ejecutar conversión manual
soffice --headless --convert-to pdf /tmp/archivo.docx --outdir /tmp
```

##### Error: "Font not found"
```bash
# Reconstruir cache de fuentes
fc-cache -f -v

# Listar fuentes disponibles
fc-list
```

##### Timeout en conversiones grandes
```php
// Aumentar tiempo de ejecución
set_time_limit(300); // 5 minutos

// O configurar en php.ini
max_execution_time = 300
```

---

## Instalación y Configuración

### Comandos Composer

#### Instalar dependencias (producción)
```bash
composer install --no-dev --optimize-autoloader
```

#### Instalar dependencias (desarrollo)
```bash
composer install
```

#### Actualizar dependencias
```bash
# Actualizar todo
composer update

# Actualizar paquete específico
composer update phpoffice/phpspreadsheet

# Ver qué se actualizaría sin hacer cambios
composer update --dry-run
```

#### Verificar dependencias
```bash
# Listar todas las dependencias instaladas
composer show

# Ver árbol de dependencias
composer show --tree

# Buscar paquetes desactualizados
composer outdated

# Validar composer.json
composer validate
```

#### Limpiar cache
```bash
composer clear-cache
```

### Verificación de Extensiones PHP

#### En desarrollo (local)
```bash
# Listar todas las extensiones
php -m

# Verificar extensiones específicas
php -m | grep -E "(gd|zip|intl)"

# Ver configuración de GD
php -i | grep -i gd

# Ver configuración de intl
php -i | grep -i intl
```

#### En Docker
```bash
# Verificar extensiones en contenedor
docker exec rund-api php -m

# Verificar extensión específica
docker exec rund-api php -m | grep gd

# Ver información completa de PHP
docker exec rund-api php -i
```

### Verificación de LibreOffice

```bash
# Versión de LibreOffice
docker exec rund-api soffice --version

# Test de conversión DOCX → PDF
docker exec rund-api soffice --headless --convert-to pdf \
  /var/www/html/tmp/test.docx --outdir /var/www/html/tmp

# Test de conversión XLSX → PDF
docker exec rund-api soffice --headless --convert-to pdf:calc_pdf_Export \
  /var/www/html/tmp/test.xlsx --outdir /var/www/html/tmp
```

---

## Troubleshooting Común

### Problema: Error "Class not found" para PHPOffice

**Causa:** Autoloader no regenerado después de instalar dependencias

**Solución:**
```bash
composer dump-autoload -o
```

### Problema: Error al generar QR "Call to undefined function imagecreate()"

**Causa:** Extensión GD no instalada o no habilitada

**Solución:**
```bash
# Verificar
php -m | grep gd

# Instalar (Docker Alpine)
apk add libpng-dev libjpeg-turbo-dev freetype-dev
docker-php-ext-configure gd --with-freetype --with-jpeg
docker-php-ext-install gd

# Reiniciar PHP-FPM
docker exec rund-api supervisorctl restart php-fpm
```

### Problema: Excel generado sin gráficos

**Causa:** No se llamó `setIncludeCharts(true)` antes de guardar

**Solución:**
```php
$writer = new Xlsx($spreadsheet);
$writer->setIncludeCharts(true); // ← IMPORTANTE
$writer->save($filepath);
```

### Problema: Caracteres especiales incorrectos en DOCX/XLSX

**Causa:** Encoding incorrecto o falta extensión mbstring

**Solución:**
```bash
# Verificar mbstring
php -m | grep mbstring

# En código, asegurar UTF-8
$texto = mb_convert_encoding($texto, 'UTF-8', 'auto');
$texto = html_entity_decode($texto, ENT_QUOTES | ENT_HTML5, 'UTF-8');
```

### Problema: LibreOffice falla con "Application Error"

**Causa:** Archivo corrupto, permisos incorrectos, o falta de fuentes

**Solución:**
```bash
# Verificar permisos
chmod 644 /tmp/archivo.docx
chown www-data:www-data /tmp/archivo.docx

# Verificar fuentes
fc-cache -f -v
fc-list | grep Arial

# Probar conversión manual
soffice --headless --convert-to pdf /tmp/archivo.docx --outdir /tmp
```

### Problema: Timeout en conversión de archivos grandes

**Solución:**
```php
// En el controlador antes de llamar LBService
set_time_limit(300); // 5 minutos
ini_set('max_execution_time', 300);
```

```ini
; En docker/php.ini
max_execution_time = 300
memory_limit = 512M
```

### Problema: Composer muy lento al instalar

**Solución:**
```bash
# Limpiar cache
composer clear-cache

# Usar cache de Composer en Docker
docker run --rm -v $(pwd):/app -v ~/.composer:/tmp composer install

# Optimizar autoloader
composer install --optimize-autoloader --no-dev
```

---

## Resumen de Versiones Instaladas

| Paquete | Versión Instalada | Versión Requerida | Estado |
|---------|-------------------|-------------------|--------|
| **PHP** | 8.3 | ^8.3 | ✅ |
| **phpoffice/phpspreadsheet** | 4.5.0 | ^4.4 | ✅ Activo |
| **phpoffice/phpword** | 1.4.0 | ^1.4 | ✅ Activo |
| **dompdf/dompdf** | 3.1.0 | ^3.1 | ⚠️ No usado |
| **endroid/qr-code** | 6.0.9 | ^6.0.9 | ✅ Activo |
| **phpunit/phpunit** | 10.5.55 | ^10.0 | ⚠️ Sin tests |
| **LibreOffice** | 24.8.2.1 | 24.8+ | ✅ Activo |
| **ext-gd** | ✅ | * | ✅ Requerida |
| **ext-zip** | ✅ | * | ✅ Requerida |
| **ext-intl** | ✅ | * | ✅ Requerida |

---

## Recomendaciones

### Acciones Inmediatas

1. **✅ Eliminar dompdf** (no utilizado)
   ```bash
   composer remove dompdf/dompdf
   ```

2. **✅ Configurar LIBREOFFICE_EXECUTABLE** en `.env`
   ```bash
   LIBREOFFICE_EXECUTABLE=/usr/bin/soffice
   ```

3. **⚠️ Implementar tests básicos** para QRService y validaciones críticas

### Acciones de Medio Plazo

4. **📝 Documentar estrategia de testing** y crear estructura de tests
5. **🔍 Revisar dependencias desactualizadas** mensualmente
6. **📊 Monitorear tamaño de imágenes Docker** después de eliminar dompdf

### Mejoras Sugeridas

7. **Cache de conversiones PDF** para reducir uso de LibreOffice
8. **Validación de archivos** antes de conversión (tamaño, formato)
9. **Logs estructurados** para debugging de PHPOffice y LibreOffice
10. **Health checks** para verificar LibreOffice en cada deploy

---

## Referencias

### Documentación Oficial

- **PHPSpreadsheet:** https://phpspreadsheet.readthedocs.io/
- **PHPWord:** https://phpword.readthedocs.io/
- **Endroid QR Code:** https://github.com/endroid/qr-code
- **PHPUnit:** https://phpunit.de/documentation.html
- **LibreOffice Headless:** https://wiki.documentfoundation.org/Faq/General/004

### Ejemplos y Tutoriales

- **PHPSpreadsheet Charts:** https://phpspreadsheet.readthedocs.io/en/latest/topics/charts/
- **PHPWord Templates:** https://phpword.readthedocs.io/en/latest/templates-processing.html
- **QR Code Customization:** https://github.com/endroid/qr-code#customization

---

**Última actualización:** 2025-10-20
**Versión del documento:** 1.0
**Autor:** ESAP Development Team
