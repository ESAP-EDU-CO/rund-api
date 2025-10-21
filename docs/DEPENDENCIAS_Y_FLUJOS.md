# RUND-API: Dependencias y Flujos Críticos

**Versión:** 2.0
**Fecha:** 2025-10-19
**Autor:** ESAP Development Team / Oliver Castelblanco Martínez

---

## TABLA DE CONTENIDOS

1. [Dependencias y Librerías](#1-dependencias-y-librerías)
2. [Flujos Críticos](#2-flujos-críticos)

---

## 1. DEPENDENCIAS Y LIBRERÍAS

### 1.1 phpoffice/phpspreadsheet (v4.5.0)

**Versión instalada:** `^4.4` (instalado: 4.5.0)

**Propósito específico:**
Generación y manipulación de archivos Excel (.xlsx) para reportes dinámicos. Permite crear reportes complejos con tablas de datos, gráficos de barras y formatos personalizados.

**Dónde se utiliza:**
- **Service:** `ReportesService::generaReporte()`
- **Handler:** `FileHandlers::getConsultaFile()`
- **Endpoint:** `POST /api/v2/documentos/generar` (con `tipo=reporte` o `tipo=consulta` y `formato=xlsx`)
- **Endpoint:** `POST /api/v2/documentos/exportar` (con `tipo=xlsx`)

**Funcionalidades clave:**

1. **Lectura de plantillas Excel:**
   ```php
   use PhpOffice\PhpSpreadsheet\IOFactory;
   $spreadsheet = IOFactory::load($tempPlantilla);
   ```
   - Carga plantillas desde OpenKM
   - Soporta formato XLSX completo con estilos y gráficos

2. **Manipulación de hojas de cálculo:**
   ```php
   use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
   $hoja = $spreadsheet->getActiveSheet();
   $hoja->setTitle($nombreHoja);
   $hoja->setCellValue($posicion, $valor);
   $hoja->mergeCells($rango);
   ```
   - Modificación de celdas individuales
   - Fusión de celdas para encabezados
   - Auto-ajuste de columnas

3. **Tipos de datos y formato:**
   ```php
   use PhpOffice\PhpSpreadsheet\Cell\DataType;
   $hoja->getCell($posData)->setValueExplicit($data, DataType::TYPE_NUMERIC);
   ```
   - Tipado explícito de datos (numéricos, texto, fechas)
   - Aplicación de estilos (negrita, bordes, alineación)

4. **Generación de gráficos:**
   ```php
   use PhpOffice\PhpSpreadsheet\Chart\{Chart, DataSeries, PlotArea, Legend, Title};
   ```
   - Gráficos de barras agrupadas
   - Leyendas personalizadas
   - Integración con datos de la hoja

5. **Escritura y exportación:**
   ```php
   use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
   $writer = new Xlsx($spreadsheet);
   $writer->setIncludeCharts(true);
   $writer->save('php://output');
   ```
   - Streaming directo al cliente
   - Inclusión de gráficos en la exportación

**Sub-dependencias relevantes:**
- `phpoffice/math` (0.3.0): Procesamiento de fórmulas matemáticas
- `markbaker/complex` (3.0.2): Operaciones con números complejos
- `markbaker/matrix` (3.0.1): Operaciones matriciales
- `maennchen/zipstream-php` (3.2.0): Compresión en streaming de archivos XLSX
- `psr/simple-cache` (3.0.0): Sistema de caché para mejorar rendimiento

---

### 1.2 phpoffice/phpword (v1.4.0)

**Versión instalada:** `^1.4` (instalado: 1.4.0)

**Propósito específico:**
Generación de certificados personalizados en formato DOCX mediante plantillas. Soporta procesamiento de variables, tablas dinámicas, imágenes (firmas, QR) y texto con formato HTML simple.

**Dónde se utiliza:**
- **Service:** `CertificadosService::creaCertificado()`
- **Handler:** `CertificadosHandlers::getCertificado()`
- **Endpoint:** `POST /api/v2/documentos/generar` (con `tipo=certificado` y `formato=docx`)

**Funcionalidades clave:**

1. **Procesamiento de plantillas DOCX:**
   ```php
   use PhpOffice\PhpWord\TemplateProcessor;
   $templateProcessor = new TemplateProcessor($rutaPlantilla);
   ```
   - Carga plantillas .docx desde OpenKM
   - Reemplazo de placeholders `${variable}`

2. **Reemplazo de texto simple:**
   ```php
   $templateProcessor->setValue($placeholder, $texto);
   ```
   - Variables simples: `${parrafo1}`, `${firma_nombre}`
   - Decodificación de entidades HTML

3. **Reemplazo de texto complejo (con formato):**
   ```php
   use PhpOffice\PhpWord\Element\TextRun;
   $templateProcessor->setComplexValue($placeholder, $textRun);
   ```
   - Soporta HTML: `<strong>`, `<b>`, `<em>`, `<i>`, `<u>`, `<s>`, `<strike>`
   - Conversión automática a formato DOCX (negrita, cursiva, subrayado, tachado)

4. **Tablas dinámicas:**
   ```php
   $templateProcessor->cloneRow($primerEnc, $numFilas);
   $templateProcessor->setValue("$placeholder#$numPlace", $valor);
   ```
   - Clonación de filas de tabla
   - Relleno de celdas con índice: `${columna#1}`, `${columna#2}`

5. **Inserción de imágenes:**
   ```php
   $templateProcessor->setImageValue('firma_imagen', $rutaImagen);
   $templateProcessor->setImageValue('valida_qr', $rutaQR);
   ```
   - Firmas digitalizadas desde OpenKM
   - Códigos QR de validación generados dinámicamente

6. **Guardado y exportación:**
   ```php
   $templateProcessor->saveAs("php://output");
   $templateProcessor->saveAs(Config::TEMP_DIR . $nombreDOCX);
   ```
   - Streaming directo al navegador
   - Guardado temporal para conversión a PDF

**Sub-dependencias relevantes:**
- `dompdf/php-font-lib` (1.0.1): Análisis de fuentes en documentos
- Integración con LibreOffice para conversión DOCX → PDF

---

### 1.3 dompdf/dompdf (v3.1.0)

**Versión instalada:** `^3.1` (instalado: 3.1.0)

**Propósito específico:**
Conversión de HTML a PDF para reportes. **NOTA:** Actualmente no está siendo utilizado directamente en el código. La conversión PDF se realiza mediante LibreOffice.

**Dónde se utiliza:**
- **Potencialmente:** Conversión de HTML a PDF (no implementado actualmente)
- **Actualmente:** Las conversiones PDF se realizan mediante `LBService::convierteWordToPDF()` y `LBService::convierteExcelToPDF()` usando LibreOffice en modo headless

**Funcionalidades disponibles (no utilizadas actualmente):**
- Renderizado de HTML5 + CSS 2.1 a PDF
- Manejo de imágenes incrustadas
- Fuentes personalizadas
- Paginación automática

**Sub-dependencias relevantes:**
- `dompdf/php-font-lib` (1.0.1): Manipulación de fuentes TTF/OTF
- `dompdf/php-svg-lib` (1.0.0): Soporte para imágenes SVG
- `masterminds/html5` (2.10.0): Parser HTML5
- `sabberworm/php-css-parser` (v8.9.0): Parser CSS

**Recomendación:**
Evaluar si mantener esta dependencia o migrar completamente a LibreOffice. Si no se planea usar renderizado HTML → PDF directo, considerar eliminarla para reducir el tamaño de la aplicación.

---

### 1.4 endroid/qr-code (v6.0.9)

**Versión instalada:** `^6.0.9` (instalado: 6.0.9)

**Propósito específico:**
Generación de códigos QR para validación de certificados. Cada certificado expedido incluye un QR único que apunta a la URL de validación en el frontend.

**Dónde se utiliza:**
- **Service:** `QRService::creaQR()`
- **Handler:** `CertificadosHandlers::getCertificado()`
- **Plantillas:** Insertado automáticamente en certificados DOCX/PDF como `${valida_qr}`

**Funcionalidades clave:**

1. **Creación de códigos QR:**
   ```php
   use Endroid\QrCode\QrCode;
   use Endroid\QrCode\Encoding\Encoding;
   use Endroid\QrCode\ErrorCorrectionLevel;
   use Endroid\QrCode\RoundBlockSizeMode;
   ```
   - QR con codificación UTF-8
   - Nivel de corrección de errores: Low (suficiente para URLs cortas)
   - Tamaño: 300x300px con margen de 10px

2. **Colores personalizados:**
   ```php
   use Endroid\QrCode\Color\Color;
   foregroundColor: new Color(0, 0, 0),     // Negro
   backgroundColor: new Color(255, 255, 255) // Blanco
   ```

3. **Escritura a archivo PNG:**
   ```php
   use Endroid\QrCode\Writer\PngWriter;
   $writer = new PngWriter();
   $result = $writer->write($qrCode);
   file_put_contents($filepath, $result->getString());
   ```
   - Guardado en directorio temporal: `/tmp/qr_{id}.png`
   - Limpieza automática después de insertar en documento

4. **Generación de URL de validación:**
   ```php
   $url = "http://localhost:4000/validacion?certificado=" . $id;
   ```
   - URL dinámica basada en el entorno
   - ID alfanumérico de 16 caracteres

**Casos de uso:**
- **Certificados académicos:** Cada certificado generado incluye un QR único
- **Validación pública:** Los usuarios pueden escanear el QR para verificar autenticidad
- **Trazabilidad:** El ID del QR se almacena en `expedidos.json` en OpenKM

**Sub-dependencias relevantes:**
- `bacon/bacon-qr-code` (v3.0.1): Librería base de generación QR
- `dasprid/enum` (1.0.6): Enumeraciones PHP 7.1 para configuración QR

---

### 1.5 ext-gd

**Propósito específico:**
Extensión de PHP para manipulación de imágenes GD (Graphics Draw). Requerida para procesamiento de imágenes PNG/JPEG/GIF.

**Dónde se utiliza:**
- **Indirectamente:** Por `endroid/qr-code` para generar imágenes PNG de códigos QR
- **Potencialmente:** Redimensionamiento de firmas o imágenes de configuración

**Funcionalidades clave:**
- Creación de imágenes en memoria
- Conversión entre formatos (PNG, JPEG, GIF)
- Aplicación de colores y transparencias
- Renderizado de QR codes

---

### 1.6 ext-zip

**Propósito específico:**
Extensión de PHP para manipulación de archivos ZIP. Requerida para trabajar con archivos DOCX y XLSX (que internamente son archivos ZIP).

**Dónde se utiliza:**
- **Indirectamente:** Por `phpoffice/phpspreadsheet` y `phpoffice/phpword`
- Archivos DOCX/XLSX son estructuras ZIP con XML interno

**Funcionalidades clave:**
- Lectura/escritura de archivos ZIP
- Compresión/descompresión de archivos Office Open XML
- Streaming de archivos grandes

---

### 1.7 ext-intl

**Propósito específico:**
Extensión de PHP para internacionalización (i18n). Soporta formateo de fechas, números y textos según locale.

**Dónde se utiliza:**
- **Potencialmente:** Formateo de fechas en certificados y reportes
- **Locale:** Español colombiano (es_CO) para formatos de fecha/hora

**Funcionalidades clave:**
- Formateo de fechas según región: `IntlDateFormatter`
- Formateo de números: `NumberFormatter`
- Ordenamiento de texto con acentos

---

### 1.8 Dependencias de desarrollo

#### phpunit/phpunit (v10.5.55)

**Propósito:** Framework de testing unitario para PHP 8.3+

**Sub-dependencias:**
- `phpunit/php-code-coverage` (10.1.16): Análisis de cobertura de código
- `phpunit/php-invoker` (4.0.0): Ejecución con timeout
- `sebastian/*`: Suite de herramientas de testing (comparador, diff, exportador, etc.)
- `nikic/php-parser` (v5.6.1): Parser de código PHP para análisis estático
- `myclabs/deep-copy` (1.13.4): Clonación profunda de objetos

**Estado actual:** Configurado pero sin tests implementados en `/tests`

---

## 2. FLUJOS CRÍTICOS

### 2.1 Generación de un Certificado

**Endpoint:** `POST /api/v2/documentos/generar`

**Payload esperado:**
```json
{
  "tipo": "certificado",
  "formato": "docx|pdf",
  "plantilla": "nombre_plantilla",
  "data": "{estructura_json}",
  "id": "opcional_16_chars"
}
```

---

#### Diagrama de flujo completo

```mermaid
graph TD
    A[Cliente envía POST /api/v2/documentos/generar] --> B[DocumentosController::generar]
    B --> C{Validar payload}
    C -->|Falta 'tipo'| D[Error 400: tipo requerido]
    C -->|tipo != certificado| E[Error 400: tipo no soportado]
    C -->|Válido| F[Llamar CertificadosHandlers::getCertificado]

    F --> G[Decodificar JSON de 'data']
    G --> H[Buscar/generar ID único]

    H --> I{¿Existe ID en expedidos.json?}
    I -->|No existe| J[Generar nuevo ID aleatorio 16 chars]
    J --> K[Guardar en expedidos.json en OpenKM]
    I -->|Sí existe| L[Usar ID existente]

    K --> M[CertificadosService::creaCertificado]
    L --> M

    M --> N[Buscar plantilla DOCX en OpenKM]
    N --> O[Descargar plantilla a /tmp]
    O --> P[Crear TemplateProcessor]

    P --> Q{Iterar estructura}
    Q --> R{Tipo de bloque}

    R -->|parrafo| S{¿Es complejo?}
    S -->|Sí HTML tags| T[creaParrafoComplejo: htmlToTextRun]
    T --> U[setComplexValue con TextRun]
    S -->|No HTML tags| V[creaParrafo: setValue]

    R -->|tabla| W[Iterar filas y encabezados]
    W --> X[creaTabla: cloneRow]
    X --> Y[setValue para cada celda col#fila]

    R -->|firma| Z[creaFirma]
    Z --> AA[FirmasService::creaFirmaTemp]
    AA --> AB[Descargar firma PNG desde OpenKM]
    AB --> AC[Guardar en /tmp/firma_uuid.png]
    AC --> AD[setImageValue 'firma_imagen']
    AD --> AE[setValue 'firma_nombre' y 'firma_cargo']
    AE --> AF[unlink firma temporal]

    U --> AG[Continuar iteración]
    V --> AG
    Y --> AG
    AF --> AG

    AG --> AH[QRService::creaQR]
    AH --> AI[Validar ID 16 chars alfanumérico]
    AI --> AJ[Construir URL: localhost:4000/validacion?certificado=id]
    AJ --> AK[Crear QrCode con Endroid]
    AK --> AL[PngWriter::write]
    AL --> AM[Guardar en /tmp/qr_id.png]
    AM --> AN[setImageValue 'valida_qr']
    AN --> AO[setValue 'valida_url']
    AO --> AP[unlink QR temporal]

    AP --> AQ{formato solicitado}

    AQ -->|docx| AR[Enviar headers DOCX]
    AR --> AS[saveAs 'php://output']
    AS --> AT[unlink plantilla temporal]
    AT --> AU[Retornar archivo al cliente]

    AQ -->|pdf| AV[saveAs /tmp/certificado_YYYY-MM-DD-HH-ii-ss.docx]
    AV --> AW[LBService::convierteWordToPDF]
    AW --> AX[Ejecutar LibreOffice headless]
    AX --> AY[libreoffice --headless --convert-to pdf:writer_pdf_Export]
    AY --> AZ{¿Conversión exitosa?}

    AZ -->|Error| BA[Retornar error y log de LibreOffice]
    AZ -->|Éxito| BB[Verificar existencia de PDF en /tmp]
    BB --> BC[Enviar headers PDF]
    BC --> BD[readfile certificado.pdf]
    BD --> BE[unlink PDF temporal]
    BE --> BF[unlink DOCX temporal]
    BF --> BG[unlink plantilla temporal]
    BG --> BH[Utils::borrarMultiplesArchivos certificado_*]
    BH --> AU

    AU --> BI[FIN - Cliente descarga archivo]
```

---

#### Paso a paso detallado

**PASO 1: Request → Controller**

**Archivo:** `/app/src/Controllers/V2/DocumentosController.php`
**Método:** `DocumentosController::generar(array $params)`

```php
// Líneas 60-108
$postData = $this->getPostData(); // JSON del body
if (!$postData && !empty($_POST)) {
    $postData = $_POST; // Fallback a form-data
}

// Validaciones
if (!isset($postData['tipo'])) {
    return $this->errorResponse('Parámetro "tipo" es requerido', 400);
}

$tipoDocumento = $postData['tipo'];

if ($tipoDocumento === 'certificado') {
    return $this->fileResponse(function() use ($postData) {
        CertificadosHandlers::getCertificado($postData);
    });
}
```

**Variables recibidas:**
- `tipo`: "certificado"
- `formato` o `tipo`: "docx" | "pdf"
- `plantilla`: nombre sin extensión (ej: "certificado_laboral")
- `data`: JSON string con estructura del certificado
- `id` (opcional): ID de 16 caracteres para evitar duplicados

---

**PASO 2: Controller → Handler**

**Archivo:** `/app/src/Handlers/CertificadosHandlers.php`
**Método:** `CertificadosHandlers::getCertificado(array $postData)`

```php
// Líneas 35-81
$estructura = json_decode($postData["data"], true);
$plantilla = $postData["plantilla"];
$formato = $postData["formato"] ?? $postData["tipo"] ?? "docx";

// Buscar o generar ID único
$nomJson = "expedidos.json";
$uuidJSON = OpenKM::findArchivo($nomJson, Config::TAX_CERTIFICADOS);
$dataJson = $uuidJSON ? json_decode(OpenKM::getArchivo($uuidJSON), true) : [];

$crearNuevoId = true;
if (isset($postData["id"])) {
    $crearNuevoId = Utils::buscarPorId($dataJson, $postData["id"]) === null;
}

if ($crearNuevoId) {
    $nuevoID = Utils::generaID($dataJson); // 16 chars aleatorios
    $postData["id"] = $nuevoID;
    DocumentService::addToJSON($nomJson, Config::TAX_CERTIFICADOS, $postData, "Añadido certificado ID:" . $nuevoID);
}
```

**Archivos temporales creados:**
- **Ninguno aún** (solo consultas a OpenKM)

**Interacción con OpenKM:**
- `OpenKM::findArchivo("expedidos.json", Config::TAX_CERTIFICADOS)`
- `OpenKM::getArchivo($uuidJSON)` para leer el JSON
- `DocumentService::addToJSON()` para actualizar el JSON con el nuevo certificado

---

**PASO 3: Handler → Service**

**Archivo:** `/app/src/Services/CertificadosService.php`
**Método:** `CertificadosService::creaCertificado(string $nombrePlantilla, array $estructura, string $id)`

```php
// Líneas 32-83
$nombrePlantilla = "$plantilla.docx";
$phpTemplate = CertificadosService::creaCertificado($nombrePlantilla, $estructura, $postData["id"]);

// Dentro de creaCertificado:
$query = "search/find?name=" . urlencode($nombrePlantilla) . "&path=" . urlencode(Config::TAX_PLANTILLAS_CERTIFICADOS);
$uuid = json_decode(OpenKM::consulta($query), true)["queryResult"]["node"]["uuid"];
$respuesta = OpenKM::getArchivo($uuid);
$rutaPlantilla = Config::TEMP_DIR . $nombrePlantilla;
file_put_contents($rutaPlantilla, $respuesta);

$templateProcessor = new TemplateProcessor($rutaPlantilla);
```

**Archivos temporales creados:**
1. `/tmp/certificado_laboral.docx` (plantilla descargada de OpenKM)

**Interacción con OpenKM:**
- Búsqueda de plantilla en `okm:root/RUND/DOCUMENTOS/PLANTILLAS/CERTIFICADOS/`
- Descarga del contenido binario del DOCX

---

**PASO 4: Procesamiento (plantilla, QR, firmas)**

**Archivo:** `/app/src/Services/CertificadosService.php`
**Métodos múltiples:**

```php
// Iterar estructura (líneas 42-76)
foreach ($estructura as $bloque) {
    if ($bloque["tipo"] == "parrafo") {
        $numParrafo++;
        $placeholder = $bloque["tipo"] . $numParrafo; // "parrafo1", "parrafo2"...
        $texto = html_entity_decode($bloque["value"]);

        if (self::esComplejo($texto)) {
            // Contiene HTML: <strong>, <em>, <u>, <s>
            $templateProcessor = self::creaParrafoComplejo($templateProcessor, $texto, $placeholder);
        } else {
            $templateProcessor = self::creaParrafo($templateProcessor, $texto, $placeholder);
        }
    }

    if ($bloque["tipo"] == "tabla") {
        // Procesar encabezados y filas
        $valores = [];
        $encabezados = $bloque["value"]["encabezados"];
        $primerEnc = strtolower(explode("_", Utils::textoAnombreCarpeta($encabezados[0]))[0]);
        $filas = $bloque["value"]["filas"];

        foreach ($filas as $fila) {
            $linea = [];
            foreach ($encabezados as $numCol => $encabezado) {
                $placeholder = strtolower(explode("_", Utils::textoAnombreCarpeta($encabezado))[0]);
                $linea[] = [$placeholder => $fila[$numCol]];
            }
            $valores[] = $linea;
        }

        $templateProcessor = self::creaTabla($templateProcessor, $valores, $primerEnc);
    }

    if ($bloque["tipo"] == "firma") {
        $templateProcessor = self::creaFirma($templateProcessor, $bloque["value"], $ruta);
    }
}
```

**Sub-proceso: Creación de firma**

```php
// CertificadosService::creaFirma (líneas 109-117)
$rutaImagen = FirmasService::creaFirmaTemp($valores["uuid"]);
$templateProcessor->setImageValue('firma_imagen', $rutaImagen);
$templateProcessor->setValue('firma_nombre', $valores["nombre"]);
$templateProcessor->setValue('firma_cargo', $valores["cargo"]);
unlink($rutaImagen); // Borra la firma temporal
```

```php
// FirmasService::creaFirmaTemp (líneas 50-63)
$firma = OpenKM::getArchivo($uuid);
$nombre = "firma_" . $uuid . ".png";
$ruta = Config::TEMP_DIR . $nombre;
$file = fopen($ruta, 'w');
fwrite($file, $firma);
fclose($file);
return $ruta;
```

**Archivos temporales creados:**
2. `/tmp/firma_{uuid}.png` (descargada de OpenKM)
   - **Eliminado inmediatamente después de insertar en el DOCX**

**Sub-proceso: Creación de QR**

```php
// CertificadosService::creaCertificado (líneas 78-81)
$validacion = QRService::creaQR($id);
$templateProcessor->setImageValue('valida_qr', $validacion["qr"]);
$templateProcessor->setValue('valida_url', $validacion["url"]);
unlink($validacion["qr"]); // Borra la imagen QR temporal
```

```php
// QRService::creaQR (líneas 39-66)
if (!preg_match('/^[a-zA-Z0-9]{16}$/', $id)) {
    throw new InvalidArgumentException('El ID debe ser 16 caracteres');
}

$url = "http://localhost:4000/validacion?certificado=" . $id;

$qrCode = new QrCode(
    data: $url,
    encoding: new Encoding('UTF-8'),
    errorCorrectionLevel: ErrorCorrectionLevel::Low,
    size: 300,
    margin: 10,
    foregroundColor: new Color(0, 0, 0),
    backgroundColor: new Color(255, 255, 255)
);

$writer = new PngWriter();
$result = $writer->write($qrCode);
$filepath = Config::TEMP_DIR . "qr_" . $id . ".png";
file_put_contents($filepath, $result->getString());

return [
    "qr" => $filepath,
    "url" => $url,
    "id" => $id
];
```

**Archivos temporales creados:**
3. `/tmp/qr_{id}.png` (generado por Endroid\QrCode)
   - **Eliminado inmediatamente después de insertar en el DOCX**

---

**PASO 5: Generación archivo DOCX**

**De vuelta en:** `/app/src/Handlers/CertificadosHandlers.php`

```php
// Líneas 56-64
if ($formato == "docx") {
    header("Content-Description: File Transfer");
    header('Content-Disposition: attachment; filename="' . $nombrePlantilla . '"');
    header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
    header('Content-Transfer-Encoding: binary');
    header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
    header('Expires: 0');
    $phpTemplate->saveAs("php://output");
    unlink(Config::TEMP_DIR . $nombrePlantilla);
}
```

**Archivos eliminados:**
- `/tmp/certificado_laboral.docx` (plantilla original)

**Resultado:** Archivo DOCX streaming directo al cliente

---

**PASO 6: Conversión PDF (si aplica)**

```php
// Líneas 65-80
elseif ($formato == "pdf") {
    $nombreDOCX = "certificado_" . (new \DateTime())->format("Y-m-d-H-i-s") . ".docx";
    $phpTemplate->saveAs(Config::TEMP_DIR . $nombreDOCX);

    $resp = LBService::convierteWordToPDF($nombreDOCX);

    if (null == $resp["error"]) {
        $pdfFilePath = $resp["salida"];
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . basename($pdfFilePath) . '"');
        header('Content-Length: ' . filesize($pdfFilePath));
        readfile($pdfFilePath);

        // Limpieza
        unlink($pdfFilePath);
        unlink(Config::TEMP_DIR . $nombreDOCX);
        unlink(Config::TEMP_DIR . $nombrePlantilla);
        Utils::borrarMultiplesArchivos(Config::TEMP_DIR . "certificado_*");
    }
}
```

**Sub-proceso: LibreOffice conversión**

**Archivo:** `/app/src/Services/LBService.php`
**Método:** `LBService::convierteWordToPDF(string $word)`

```php
// Líneas 40-43, 52-67
public static function convierteWordToPDF(string $word = "certificado.docx", string $dir = Config::TEMP_DIR): array
{
    return self::convierteOfficeToPDF($word, "writer_pdf_Export", $dir);
}

private static function convierteOfficeToPDF(string $file, string $handler, string $dir): array
{
    $fileInfo = pathinfo($file);
    $nombrePDF = $dir . $fileInfo["filename"] . ".pdf";

    $comando = escapeshellcmd(
        $_ENV["LIBREOFFICE_EXECUTABLE"] . " --headless --convert-to pdf:$handler --outdir " .
        escapeshellarg($dir) . " " . escapeshellarg($dir . $file) . " 2>/dev/null"
    );

    $salida = exec($comando, $output, $rtn);

    if (file_exists($nombrePDF)) {
        return ["error" => null, "salida" => $nombrePDF];
    } else {
        return ["error" => "ERROR: no se pudo convertir", "salida" => $salida, "rtn" => $rtn];
    }
}
```

**Comando ejecutado:**
```bash
/usr/bin/libreoffice --headless --convert-to pdf:writer_pdf_Export \
  --outdir /tmp/ /tmp/certificado_2025-10-19-14-30-45.docx 2>/dev/null
```

**Archivos temporales creados:**
4. `/tmp/certificado_2025-10-19-14-30-45.docx` (versión procesada)
5. `/tmp/certificado_2025-10-19-14-30-45.pdf` (resultado de conversión)

**Archivos eliminados al finalizar:**
- `/tmp/certificado_2025-10-19-14-30-45.pdf`
- `/tmp/certificado_2025-10-19-14-30-45.docx`
- `/tmp/certificado_laboral.docx` (plantilla original)
- `/tmp/certificado_*` (todos los certificados temporales antiguos)

---

**PASO 7: Respuesta al cliente**

**Headers enviados (DOCX):**
```
Content-Description: File Transfer
Content-Disposition: attachment; filename="certificado_laboral.docx"
Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document
Content-Transfer-Encoding: binary
Cache-Control: must-revalidate, post-check=0, pre-check=0
Expires: 0
```

**Headers enviados (PDF):**
```
Content-Type: application/pdf
Content-Disposition: attachment; filename="certificado_2025-10-19-14-30-45.pdf"
Content-Length: {tamaño_en_bytes}
```

**Resultado:** El navegador descarga el archivo automáticamente.

---

#### Resumen de archivos temporales

| Archivo | Creado en | Eliminado en | Duración |
|---------|-----------|--------------|----------|
| `/tmp/{plantilla}.docx` | CertificadosService::creaCertificado | CertificadosHandlers::getCertificado | Hasta enviar respuesta |
| `/tmp/firma_{uuid}.png` | FirmasService::creaFirmaTemp | CertificadosService::creaFirma | Inmediatamente después de insertar |
| `/tmp/qr_{id}.png` | QRService::creaQR | CertificadosService::creaCertificado | Inmediatamente después de insertar |
| `/tmp/certificado_{datetime}.docx` | CertificadosHandlers::getCertificado | CertificadosHandlers::getCertificado (solo PDF) | Hasta conversión PDF |
| `/tmp/certificado_{datetime}.pdf` | LBService::convierteWordToPDF | CertificadosHandlers::getCertificado | Hasta enviar respuesta |

---

### 2.2 Subida de un Archivo

**Endpoint:** `POST /api/v2/archivos/subir`

**Payload esperado:**
```
Content-Type: multipart/form-data

accion: "cargaDocumento" | "cargaFirma"
archivo: {file}
propiedades: JSON string con metadatos
```

---

#### Diagrama de flujo completo

```mermaid
graph TD
    A[Cliente envía POST /api/v2/archivos/subir] --> B[ArchivosController::subir]
    B --> C[getPostData + getFiles]

    C --> D{Validaciones}
    D -->|Falta 'accion'| E[Error: accion requerida]
    D -->|Falta 'archivo'| F[Error: archivo requerido]
    D -->|Válido| G[FileHandlers::postFile]

    G --> H[Extraer propiedades JSON]
    H --> I[Decodificar HTML entities]
    I --> J[Extraer nombreArchivo del file]

    J --> K{¿Qué acción?}

    K -->|cargaFirma| L[FLUJO FIRMA]
    L --> L1[path = Config::TAX_FIRMAS]
    L1 --> L2[OpenKM::yaExiste para verificar duplicado]
    L2 --> L3[Extraer cargo de propiedades]
    L3 --> L4[Definir categorías OpenKM]
    L4 --> L5[CategoriasService::creaCategorias]
    L5 --> L6[OpenKM::cargaArchivo PNG]
    L6 --> L7[Buscar UUID del PNG recién cargado]
    L7 --> L8[OpenKM::consulta setProperties con categorías]

    L8 --> L9[Preparar JSON side-car]
    L9 --> L10[nombreJSON = replace .png con .json]
    L10 --> L11[Buscar si ya existe JSON]
    L11 --> L12{¿Existe JSON?}
    L12 -->|Sí| L13[Obtener UUID del JSON]
    L12 -->|No| L14[UUID = null]

    L13 --> L15[DocumentService::cargaJSON]
    L14 --> L15
    L15 --> L16[Generar JSON con metadata]
    L16 --> L17{¿Tiene UUID?}
    L17 -->|Sí| L18[OpenKM::nuevaVersion - checkout/checkin]
    L17 -->|No| L19[OpenKM::documentAction - createSimple]

    L18 --> L20[Buscar UUID del JSON actualizado]
    L19 --> L20
    L20 --> L21[OpenKM::consulta setProperties JSON]
    L21 --> L22[Retornar resultado completo]

    K -->|cargaDocumento| M[FLUJO DOCUMENTO]
    M --> M1[Extraer cédula de propiedades]
    M1 --> M2{¿Cédula válida 4-20 dígitos?}
    M2 -->|No| M3[Error: cédula inválida]
    M2 -->|Sí| M4[Extraer taxonomía de propiedades]

    M4 --> M5[Construir path completo]
    M5 --> M6[path = Config::TAX_HOJAS + cedula + taxonomia]
    M6 --> M7[OpenKM::yaExiste para verificar duplicado]
    M7 --> M8[Extraer tipo, formato, origen de propiedades]

    M8 --> M9{¿Es cédula?}
    M9 -->|Sí| M10[Extraer categorías demográficas]
    M10 --> M11[Añadir categorías de documento]
    M9 -->|No| M11

    M11 --> M12[Construir array de categorías]
    M12 --> M13[OpenKM::creaCarpetas para taxonomía]
    M13 --> M14[OpenKM::creaCarpetas para categorías]
    M14 --> M15[OpenKM::cargaArchivo]

    M15 --> M16{¿Archivo subió?}
    M16 -->|Error| M17[Retornar error de carga]
    M16 -->|Éxito| M18[Buscar UUID del archivo cargado]

    M18 --> M19[search/find con nombre y path]
    M19 --> M20[Extraer UUID del resultado]
    M20 --> M21[OpenKM::consulta setProperties con categorías]
    M21 --> M22[Retornar resultado completo]

    L22 --> N[ArchivosController::subir]
    M22 --> N
    M3 --> N
    M17 --> N

    N --> O[successResponse con metadata]
    O --> P[FIN - Retornar JSON al cliente]
```

---

#### Paso a paso detallado

**PASO 1: Request → Controller**

**Archivo:** `/app/src/Controllers/V2/ArchivosController.php`
**Método:** `ArchivosController::subir(array $params)`

```php
// Líneas 31-55
$postData = $this->getPostData(); // Datos JSON si existen
$files = $this->getFiles();        // $_FILES

if (!$postData || !isset($postData['accion'])) {
    return $this->errorResponse('Parámetro "accion" requerido');
}

if (!isset($files['archivo'])) {
    return $this->errorResponse('Archivo requerido');
}

$result = FileHandlers::postFile($postData, $files);

return $this->successResponse([
    'archivo' => $result,
    'meta' => [
        'accion' => $postData['accion'],
        'nombre_original' => $files['archivo']['name'],
        'tamaño' => $files['archivo']['size'],
        'version' => '2.0'
    ]
]);
```

**Variables recibidas:**
- `accion`: "cargaFirma" | "cargaDocumento"
- `archivo`: File upload
- `propiedades`: JSON string con metadata del archivo

---

**PASO 2: Validación inicial**

**Archivo:** `/app/src/Handlers/FileHandlers.php`
**Método:** `FileHandlers::postFile(array $params, array $files)`

```php
// Líneas 170-177
$salida = [];
$accion = $params["accion"];
$nombreArchivo = $files["archivo"]["name"];
$propiedades = json_decode(html_entity_decode($params["propiedades"], ENT_QUOTES | ENT_HTML5, 'UTF-8'), true);
array_push($propiedades, ["label" => "Nombre", "valor" => $nombreArchivo]);
```

**Decodificación de propiedades:**
- El JSON viene HTML-encoded desde el frontend
- Se decodifica con `html_entity_decode` para evitar problemas de encoding
- Se añade el nombre del archivo a las propiedades

---

**PASO 3A: Flujo carga de FIRMA**

**Caso:** `accion = "cargaFirma"`

```php
// Líneas 179-213
case "cargaFirma":
    $path = Config::TAX_FIRMAS; // okm:root/RUND/DOCUMENTOS/FIRMAS
    $dupe = OpenKM::yaExiste($nombreArchivo, $path);
    $cargo = Utils::textoAnombreCarpeta(Utils::extraeElemento($propiedades, "label", "cargo")["valor"]);

    // Definir categorías para la firma
    $categorias = [
        ["path" => Config::CTGR_FIRMAS . "CARGO/$cargo"],
        ["path" => Config::CTGR_FIRMAS . "TIPO/RUND_FIRMA"],
        ["path" => Config::CTGR_FIRMAS . "TIPO/RUND_FIRMA_SIDE-CAR"],
        ["path" => Config::CTGR_FIRMAS . "FORMATO/PNG"],
        ["path" => Config::CTGR_FIRMAS . "FORMATO/CSV"],
    ];

    $salida["creaCategorias"] = CategoriasService::creaCategorias($categorias);
    $salida["cargaPNG"] = OpenKM::cargaArchivo($files["archivo"], $propiedades, $path, $dupe);
```

**Interacción con OpenKM (PNG):**
1. `OpenKM::yaExiste($nombreArchivo, $path)` - Verificar duplicado
2. `CategoriasService::creaCategorias($categorias)` - Crear categorías si no existen
3. `OpenKM::cargaArchivo($files["archivo"], $propiedades, $path, $dupe)` - Subir PNG

**Sub-proceso: Carga de archivo PNG**

**Archivo:** `/app/src/Core/OpenKM.php`
**Método:** `OpenKM::cargaArchivo(array $archivo, array $propiedades, string $path, bool $version)`

```php
// Líneas 258-290
$nombre = Utils::extraeElemento($propiedades, "label", "Nombre")["valor"] ?? $archivo["name"];
$temp = $archivo["tmp_name"];
$type = mime_content_type($temp);
$fileData = new \CURLFile($temp, $type, $nombre);
$rutaDestino = $path . "/" . $nombre;

// Buscar UUID si ya existe
$query = "search/find?name=" . urlencode($nombre) . "&path=" . urlencode($path);
$uuid = json_decode(self::consulta($query), true)["queryResult"]["node"]["uuid"];

$postData = [
    "docPath" => $rutaDestino,
    "content" => $fileData
];

// Crear carpetas si no existen
$folderResp = self::creaCarpetas([$path], Config::ROOT_TAX);

// Subir archivo (nueva versión si existe, nuevo si no)
$resp = $version ?
    self::nuevaVersion($uuid, "Modificado " . date("Y-m-d H:i:s"), $postData) :
    self::documentAction("createSimple", $postData);

return self::verificaCarga($resp, $salida, $folderResp);
```

**CURL realizado:**
```bash
POST http://rund-core:8080/OpenKM/services/rest/document/createSimple
Authorization: Basic b2ttQWRtaW46YWRtaW4=
Content-Type: multipart/form-data

docPath: okm:root/RUND/DOCUMENTOS/FIRMAS/firma_director.png
content: {binary_file_data}
```

**Asignar categorías al PNG:**

```php
// Líneas 192-195
$queryPNG = "search/find?name=" . urlencode($nombreArchivo) . "&path=" . urlencode($path);
$uuidPNG = json_decode(OpenKM::consulta($queryPNG), true)["queryResult"]["node"]["uuid"];
$postDataPNG = ["uuid" => $uuidPNG, "categories" => [...]];
$salida["respCategoriasPNG"] = OpenKM::consulta("document/setProperties", "PUT", $postDataPNG);
```

**Sub-proceso: Crear JSON side-car**

```php
// Líneas 197-212
$nombreJSON = preg_replace('/\.png$/i', ".json", $nombreArchivo);
$queryJSON = "search/find?name=" . urlencode($nombreJSON) . "&path=" . urlencode($path);
$respQueryJSON = json_decode(OpenKM::consulta($queryJSON), true);
$dupeJSON = $respQueryJSON && count($respQueryJSON);
$uuidJSON = $dupeJSON ? $respQueryJSON["queryResult"]["node"]["uuid"] : null;

$dataJSON = [
    ["label" => "cargo", "valor" => Utils::extraeElemento($propiedades, "label", "cargo")["valor"]],
    ["label" => "nombres", "valor" => Utils::extraeElemento($propiedades, "label", "nombres")["valor"]],
    ["label" => "apellidos", "valor" => Utils::extraeElemento($propiedades, "label", "apellidos")["valor"]],
    ["label" => "fecha", "valor" => Utils::extraeElemento($propiedades, "label", "fecha")["valor"]],
    ["label" => "firma", "valor" => $nombreArchivo],
];

$salida["cargaJSON"] = DocumentService::cargaJSON($dataJSON, $nombreJSON, $path, $uuidJSON);
```

**Archivo JSON side-car creado:**
- `firma_director.json` con metadata de la firma
- Si ya existía, se crea nueva versión (checkout/checkin)

---

**PASO 3B: Flujo carga de DOCUMENTO**

**Caso:** `accion = "cargaDocumento"`

```php
// Líneas 214-258
case "cargaDocumento":
    $cedula = Utils::extraeElemento($propiedades, "label", "cedula")["valor"];

    // Validar cédula
    if (!preg_match('/^\d{4,20}$/', $cedula)) {
        $salida["error"] = "La cédula debe tener entre 4 y 20 dígitos.";
        break;
    }

    // Construir ruta
    $ruta = Utils::extraeElemento($propiedades, "label", "taxonomia")["valor"];
    $ruta = strlen($ruta) > 2 ? "/" . Utils::textoAnombreCarpeta($ruta) : '';
    $path = Config::TAX_HOJAS . $cedula . $ruta;
    // Ejemplo: okm:root/RUND/DOCUMENTOS/HOJAS_DE_VIDA/1234567890/ESTUDIOS_FORMALES

    $dupe = OpenKM::yaExiste($nombreArchivo, $path);
    $esCedula = Utils::extraeElemento($propiedades, "label", "esCedula")["valor"];
    $tipoDocumento = Utils::extraeElemento($propiedades, "label", "tipo")["valor"];
    $formatoDocumento = Utils::extraeElemento($propiedades, "label", "formato")["valor"];
    $origenDocumento = Utils::extraeElemento($propiedades, "label", "origen")["valor"];
```

**Construcción de categorías:**

```php
// Líneas 240-249
$categorias = [];
$rutasCategorias = [];

if ($esCedula) {
    // Categorías demográficas (edad, género, estado civil, etc.)
    $cats = Utils::extraeElemento($propiedades, "label", "categorias")["valor"];
    foreach ($cats as $cat) {
        $rutasCategorias[] = Config::ROOT_CTG_PROF . $cat;
    }
}

// Categorías de documento (siempre)
$rutasCategorias[] = Config::CTGR_DOCS_HOJAS . "TIPO/" . Utils::textoAnombreCarpeta($tipoDocumento);
$rutasCategorias[] = Config::CTGR_DOCS_HOJAS . "FORMATO/" . Utils::textoAnombreCarpeta($formatoDocumento);
$rutasCategorias[] = Config::CTGR_DOCS_HOJAS . "ORIGEN/" . Utils::textoAnombreCarpeta($origenDocumento);

foreach ($rutasCategorias as $rutaCategoria) {
    $categorias[] = ["path" => $rutaCategoria];
}
```

**Ejemplo de categorías para cédula:**
```
okm:categories/RUND/PROFESORES/EDAD/30-40
okm:categories/RUND/PROFESORES/GENERO/MASCULINO
okm:categories/RUND/PROFESORES/ESTADO_CIVIL/SOLTERO
okm:categories/RUND/DOCUMENTOS/HOJAS_DE_VIDA/TIPO/CEDULA
okm:categories/RUND/DOCUMENTOS/HOJAS_DE_VIDA/FORMATO/PDF
okm:categories/RUND/DOCUMENTOS/HOJAS_DE_VIDA/ORIGEN/ONEDRIVE
```

**Crear taxonomía y categorías:**

```php
// Líneas 250-252
$salida["creaTaxonomia"] = OpenKM::creaCarpetas([$cedula . $ruta], Config::TAX_HOJAS);
$salida["creaCategorias"] = OpenKM::creaCarpetas($rutasCategorias, Config::ROOT_CTG);
$salida["carga"] = OpenKM::cargaArchivo($files["archivo"], $propiedades, $path, $dupe);
```

**Sistema de versionado:**
- Si `$dupe = true`: Se crea nueva versión con `checkout → checkin`
- Si `$dupe = false`: Se crea documento nuevo con `createSimple`

**Sub-proceso: OpenKM creaCarpetas**

**Archivo:** `/app/src/Core/OpenKM.php`
**Método:** `OpenKM::creaCarpetas(array $rutas, string $prefijo)`

```php
// Líneas 298-327
foreach ($rutas as $ruta) {
    $path = explode("/", $ruta);
    $ruta = rtrim($prefijo, '/');

    foreach ($path as $part) {
        $ruta .= "/" . $part;
        $respGetNodeUuid = self::consulta("repository/getNodeUuid?nodePath=" . urlencode($ruta));

        if (strpos($respGetNodeUuid, "PathNotFoundException") !== false) {
            // La carpeta no existe, crearla
            $respCreateSimple = self::consulta("folder/createSimple", "POST", $ruta);
            $respuesta[] = [
                "accion" => "folder/createSimple",
                "ruta" => $ruta,
                "consulta" => $respCreateSimple,
            ];
        } else {
            // La carpeta ya existe
            $respuesta[] = [
                "accion" => null,
                "ruta" => $ruta,
                "getNodeUuid" => "Ya existe",
            ];
        }
    }
}
```

**Ejemplo de creación recursiva:**
```
1. Verificar: okm:root/RUND/DOCUMENTOS/HOJAS_DE_VIDA/1234567890
   → No existe → Crear carpeta

2. Verificar: okm:root/RUND/DOCUMENTOS/HOJAS_DE_VIDA/1234567890/ESTUDIOS_FORMALES
   → No existe → Crear carpeta

3. Continuar...
```

**Asignar categorías al documento:**

```php
// Líneas 254-257
$query = "search/find?name=" . urlencode($nombreArchivo) . "&path=" . urlencode($path);
$uuid = json_decode(OpenKM::consulta($query), true)["queryResult"]["node"]["uuid"];
$postData = ["uuid" => $uuid, "categories" => $categorias];
$salida["setProperties"] = OpenKM::consulta("document/setProperties", "PUT", $postData);
```

---

**PASO 4: Respuesta al cliente**

**Archivo:** `/app/src/Controllers/V2/ArchivosController.php`

```php
// Líneas 46-54
return $this->successResponse([
    'archivo' => $result,
    'meta' => [
        'accion' => $postData['accion'],
        'nombre_original' => $files['archivo']['name'],
        'tamaño' => $files['archivo']['size'],
        'version' => '2.0'
    ]
]);
```

**Ejemplo de respuesta JSON (cargaDocumento):**
```json
{
  "success": true,
  "data": {
    "archivo": {
      "creaTaxonomia": [
        {"accion": "folder/createSimple", "ruta": "okm:root/RUND/DOCUMENTOS/HOJAS_DE_VIDA/1234567890", "consulta": {...}},
        {"accion": null, "ruta": "okm:root/RUND/DOCUMENTOS/HOJAS_DE_VIDA/1234567890/ESTUDIOS_FORMALES", "getNodeUuid": "Ya existe"}
      ],
      "creaCategorias": [
        {"accion": "folder/createSimple", "ruta": "okm:categories/RUND/PROFESORES/EDAD/30-40", "consulta": {...}},
        {"accion": null, "ruta": "okm:categories/RUND/DOCUMENTOS/HOJAS_DE_VIDA/TIPO/CEDULA", "getNodeUuid": "Ya existe"}
      ],
      "carga": {
        "error": false,
        "postData": {
          "docPath": "okm:root/RUND/DOCUMENTOS/HOJAS_DE_VIDA/1234567890/cedula.pdf",
          "content": {...}
        },
        "respuesta": {
          "uuid": "abc123-def456-ghi789",
          "path": "okm:root/RUND/DOCUMENTOS/HOJAS_DE_VIDA/1234567890/cedula.pdf"
        }
      },
      "setProperties": "{\"uuid\":\"abc123-def456-ghi789\",\"categories\":[...]}"
    },
    "meta": {
      "accion": "cargaDocumento",
      "nombre_original": "cedula.pdf",
      "tamaño": 1048576,
      "version": "2.0"
    }
  }
}
```

---

#### Resumen de interacciones OpenKM

**Para cargaFirma:**

| Operación | Endpoint OpenKM | Método | Propósito |
|-----------|-----------------|--------|-----------|
| Verificar duplicado PNG | `search/find?name=firma.png&path=...` | GET | Comprobar si ya existe |
| Crear categorías | `folder/createSimple` | POST | Crear rutas de categorías |
| Subir PNG | `document/createSimple` | POST | Subir archivo de imagen |
| Buscar UUID PNG | `search/find?name=firma.png&path=...` | GET | Obtener UUID recién creado |
| Asignar categorías PNG | `document/setProperties` | PUT | Vincular categorías al PNG |
| Verificar duplicado JSON | `search/find?name=firma.json&path=...` | GET | Comprobar si ya existe metadata |
| Crear/actualizar JSON | `document/createSimple` o `checkout/checkin` | POST/PUT | Guardar metadata |
| Buscar UUID JSON | `search/find?name=firma.json&path=...` | GET | Obtener UUID del JSON |
| Asignar categorías JSON | `document/setProperties` | PUT | Vincular categorías al JSON |

**Para cargaDocumento:**

| Operación | Endpoint OpenKM | Método | Propósito |
|-----------|-----------------|--------|-----------|
| Verificar duplicado | `search/find?name=archivo.pdf&path=...` | GET | Comprobar si ya existe |
| Crear taxonomía recursiva | `folder/createSimple` (múltiples) | POST | Crear carpetas de hoja de vida |
| Crear categorías | `folder/createSimple` (múltiples) | POST | Crear rutas de categorías |
| Subir documento | `document/createSimple` | POST | Subir archivo nuevo |
| O actualizar documento | `checkout → checkin` | POST | Nueva versión si existe |
| Buscar UUID documento | `search/find?name=archivo.pdf&path=...` | GET | Obtener UUID recién creado |
| Asignar categorías | `document/setProperties` | PUT | Vincular categorías al documento |

---

### 2.3 Consulta de Información de Profesor

**Endpoint:** `GET /api/v2/profesores/{cedula}`

**Ejemplo de request:**
```
GET /api/v2/profesores/1234567890
```

---

#### Diagrama de flujo completo

```mermaid
graph TD
    A[Cliente envía GET /api/v2/profesores/cedula] --> B[ProfesoresController::show]
    B --> C{¿Existe parámetro cedula?}
    C -->|No| D[Error 400: Cédula requerida]
    C -->|Sí| E[DataHandlers::getInfoProfesor]

    E --> F{¿Cédula válida 4-20 dígitos?}
    F -->|No| G[Error: cédula inválida]
    F -->|Sí| H[OBTENER ARCHIVOS DEL PROFESOR]

    H --> H1[DocumentService::getInfoArchivosProfesor cedula, false]
    H1 --> H2[Construir query OpenKM]
    H2 --> H3[search/find?path=okm:root/RUND/DOCUMENTOS/HOJAS_DE_VIDA/cedula]
    H3 --> H4{¿Hay resultados?}
    H4 -->|No| H5[Retornar null]
    H4 -->|Sí| H6[Iterar nodos de documentos]

    H6 --> H7[Para cada documento:]
    H7 --> H8[extraeDatosDocumento]
    H8 --> H9[Extraer nombre del path]
    H9 --> H10[Extraer categorías del nodo]
    H10 --> H11{¿Categoría en CTGR_DOCS_HOJAS?}
    H11 -->|Sí| H12[Parsear path de categoría]
    H12 --> H13[Dividir en tipo/valor]
    H13 --> H14[Añadir a array categorias]
    H11 -->|No| H15[Ignorar categoría]

    H14 --> H16[Retornar array con nombre y categorias]
    H15 --> H16
    H16 --> H17[Continuar con siguiente documento]

    H17 --> I[OBTENER DATOS DEMOGRÁFICOS]
    I --> I1[DocumentService::getInfoArchivosProfesor cedula, true]
    I1 --> I2[Construir query OpenKM con filtro]
    I2 --> I3[search/find?path=.../cedula&name=cedula]
    I3 --> I4{¿Hay resultados?}
    I4 -->|No| I5[Retornar null]
    I4 -->|Sí| I6[Extraer nodo único de cédula]

    I6 --> I7[extraeDatosDocumento con ROOT_CTG_PROF]
    I7 --> I8[Extraer categorías demográficas]
    I8 --> I9{¿Categoría en ROOT_CTG_PROF?}
    I9 -->|Sí| I10[Parsear categoría demográfica]
    I10 --> I11[Dividir en tipo/subtipo/valor]
    I11 --> I12[Añadir a array categorias]
    I9 -->|No| I13[Ignorar categoría]

    I12 --> I14[DocumentService::estructuraCategorias]
    I13 --> I14
    I14 --> I15[Cargar labels.json de OpenKM]
    I15 --> I16[Convertir claves a labels legibles]
    I16 --> I17{¿Categoría tiene 2 o 3 niveles?}

    I17 -->|3 niveles| I18[resultado[categoria][subcategoria] = valor]
    I17 -->|2 niveles| I19[resultado[categoria][] = valor]

    I18 --> I20[Retornar estructura anidada]
    I19 --> I20

    I20 --> J{¿datosDemograficos es null?}
    J -->|Sí| K[Error: profesor sin datos]
    J -->|No| L[Construir respuesta completa]

    L --> M[successResponse con:]
    M --> M1[profesor.archivosProfesor]
    M --> M2[profesor.datosDemograficos]
    M --> M3[meta.total_archivos]
    M --> M4[meta.incluye_demografia]

    M1 --> N[Retornar JSON al cliente]
    M2 --> N
    M3 --> N
    M4 --> N
```

---

#### Paso a paso detallado

**PASO 1: Request → Controller**

**Archivo:** `/app/src/Controllers/V2/ProfesoresController.php`
**Método:** `ProfesoresController::show(array $params)`

```php
// Líneas 27-44
if (!isset($params['cedula'])) {
    return $this->errorResponse('Cédula es requerida', 400);
}

$info = DataHandlers::getInfoProfesor($params['cedula']);

return $this->successResponse([
    'profesor' => $info,
    'cedula' => $params['cedula'],
    'meta' => [
        'total_archivos' => count($info['archivosProfesor'] ?? []),
        'incluye_demografia' => isset($info['datosDemograficos']),
        'version' => '2.0'
    ]
]);
```

**Parámetros extraídos de la URL:**
- `cedula`: String numérico de 4-20 dígitos

---

**PASO 2: Handler → Service (búsqueda)**

**Archivo:** `/app/src/Handlers/DataHandlers.php`
**Método:** `DataHandlers::getInfoProfesor(string $cedula)`

```php
// Líneas 92-104
if (!preg_match('/^\d{4,20}$/', $cedula)) {
    return ["error" => "La cédula debe tener entre 4 y 20 dígitos."];
}

$archivosProfesor = DocumentService::getInfoArchivosProfesor($cedula, false);
$datosDemograficos = DocumentService::getInfoArchivosProfesor($cedula);

if (null !== $datosDemograficos) {
    $datosDemograficos["categorias"] = DocumentService::estructuraCategorias($datosDemograficos["categorias"]);
    return ["archivosProfesor" => $archivosProfesor, "datosDemograficos" => $datosDemograficos];
} else {
    return ["error" => null, "resultado" => "El profesor con cédula $cedula no tiene datos registrados en rund-core."];
}
```

---

**PASO 3: Búsqueda en OpenKM (archivos del profesor)**

**Archivo:** `/app/src/Services/DocumentService.php`
**Método:** `DocumentService::getInfoArchivosProfesor(string $cedula, bool $demografica = true)`

**Primera llamada (archivos generales):**

```php
// Líneas 71-86 con demografica = false
$buscaNombre = $demografica ? "&name=" . urlencode("cedula") : "";
$query = "search/find?path=" . urlencode(Config::TAX_HOJAS . $cedula) . $buscaNombre;
$resp = json_decode(OpenKM::consulta($query), true)["queryResult"];

if (!$resp) return null;

// demografica = false: Se solicitan TODOS los documentos
$datos = [];
foreach ($resp as $el) {
    $nodo = $el["node"];
    $datos[] = self::extraeDatosDocumento($nodo, Config::TAX_HOJAS, Config::CTGR_DOCS_HOJAS);
}
return $datos;
```

**Query ejecutada:**
```
GET http://rund-core:8080/OpenKM/services/rest/search/find?path=okm:root/RUND/DOCUMENTOS/HOJAS_DE_VIDA/1234567890
```

**Respuesta de OpenKM (ejemplo):**
```json
{
  "queryResult": [
    {
      "node": {
        "uuid": "abc-123",
        "path": "okm:root/RUND/DOCUMENTOS/HOJAS_DE_VIDA/1234567890/cedula.pdf",
        "categories": [
          {"path": "okm:categories/RUND/PROFESORES/EDAD/30-40"},
          {"path": "okm:categories/RUND/DOCUMENTOS/HOJAS_DE_VIDA/TIPO/CEDULA"}
        ]
      }
    },
    {
      "node": {
        "uuid": "def-456",
        "path": "okm:root/RUND/DOCUMENTOS/HOJAS_DE_VIDA/1234567890/ESTUDIOS_FORMALES/diploma.pdf",
        "categories": [
          {"path": "okm:categories/RUND/DOCUMENTOS/HOJAS_DE_VIDA/TIPO/DIPLOMA"}
        ]
      }
    }
  ]
}
```

---

**PASO 4: Procesamiento de categorías de documentos**

**Archivo:** `/app/src/Services/DocumentService.php`
**Método:** `DocumentService::extraeDatosDocumento(array $nodo, string $tax, string $cat)`

```php
// Líneas 95-117
// Extraer nombre del archivo
$path = $nodo["path"];
$path = preg_replace("#$tax#", "", $path);
$partes = explode("/", $path);
$nombre = array_pop($partes);

// Extraer categorías
$cates = $nodo["categories"];
$categorias = [];

foreach ($cates as $cate) {
    $pathCate = $cate["path"];

    // Solo procesar categorías que coincidan con el prefijo esperado
    if (preg_match("#$cat#", $pathCate)) {
        $ruta = preg_replace("#$cat#", "", $pathCate);
        $partes = explode("/", $ruta);
        $categorias[] = $partes;
    }
}

return [
    "nombre" => $nombre,
    "categorias" => $categorias,
];
```

**Ejemplo de procesamiento:**

**Input:**
```
path: "okm:root/RUND/DOCUMENTOS/HOJAS_DE_VIDA/1234567890/ESTUDIOS_FORMALES/diploma.pdf"
tax: "okm:root/RUND/DOCUMENTOS/HOJAS_DE_VIDA/"
cat: "okm:categories/RUND/DOCUMENTOS/HOJAS_DE_VIDA/"

categories: [
  {"path": "okm:categories/RUND/DOCUMENTOS/HOJAS_DE_VIDA/TIPO/DIPLOMA"},
  {"path": "okm:categories/RUND/DOCUMENTOS/HOJAS_DE_VIDA/FORMATO/PDF"},
  {"path": "okm:categories/RUND/DOCUMENTOS/HOJAS_DE_VIDA/ORIGEN/ONEDRIVE"},
  {"path": "okm:categories/RUND/PROFESORES/EDAD/30-40"} // Esta se ignora porque no coincide con cat
]
```

**Output:**
```php
[
  "nombre" => "diploma.pdf",
  "categorias" => [
    ["TIPO", "DIPLOMA"],
    ["FORMATO", "PDF"],
    ["ORIGEN", "ONEDRIVE"]
  ]
]
```

---

**PASO 5: Búsqueda en OpenKM (datos demográficos)**

**Segunda llamada (datos demográficos):**

```php
// Líneas 71-78 con demografica = true
$buscaNombre = $demografica ? "&name=" . urlencode("cedula") : "";
$query = "search/find?path=" . urlencode(Config::TAX_HOJAS . $cedula) . $buscaNombre;
$resp = json_decode(OpenKM::consulta($query), true)["queryResult"];

if (!$resp) return null;

// demografica = true: Solo el documento de cédula
return self::extraeDatosDocumento($resp["node"], Config::TAX_HOJAS, Config::ROOT_CTG_PROF);
```

**Query ejecutada:**
```
GET http://rund-core:8080/OpenKM/services/rest/search/find?path=okm:root/RUND/DOCUMENTOS/HOJAS_DE_VIDA/1234567890&name=cedula
```

**Diferencia clave:**
- Primera búsqueda: Sin filtro de nombre → Todos los archivos
- Segunda búsqueda: Con filtro `&name=cedula` → Solo la cédula
- Segunda búsqueda: Categorías filtradas por `Config::ROOT_CTG_PROF` (categorías demográficas)

**Respuesta de OpenKM (ejemplo):**
```json
{
  "queryResult": {
    "node": {
      "uuid": "abc-123",
      "path": "okm:root/RUND/DOCUMENTOS/HOJAS_DE_VIDA/1234567890/cedula.pdf",
      "categories": [
        {"path": "okm:categories/RUND/PROFESORES/EDAD/30-40"},
        {"path": "okm:categories/RUND/PROFESORES/GENERO/MASCULINO"},
        {"path": "okm:categories/RUND/PROFESORES/ESTADO_CIVIL/SOLTERO"},
        {"path": "okm:categories/RUND/PROFESORES/LUGAR_NACIMIENTO/BOGOTA/BOGOTA_DC"},
        {"path": "okm:categories/RUND/DOCUMENTOS/HOJAS_DE_VIDA/TIPO/CEDULA"} // Esta se ignora
      ]
    }
  }
}
```

---

**PASO 6: Estructuración de categorías demográficas**

**De vuelta en:** `/app/src/Handlers/DataHandlers.php`

```php
// Líneas 98-100
if (null !== $datosDemograficos) {
    $datosDemograficos["categorias"] = DocumentService::estructuraCategorias($datosDemograficos["categorias"]);
    return ["archivosProfesor" => $archivosProfesor, "datosDemograficos" => $datosDemograficos];
}
```

**Archivo:** `/app/src/Services/DocumentService.php`
**Método:** `DocumentService::estructuraCategorias(array $categorias)`

```php
// Líneas 124-157
$resultado = [];
$labels = OpenKM::getDataFile("labels"); // Cargar labels.json

foreach ($categorias as $item) {
    if (count($item) === 3) {
        // Caso de 3 niveles: [categoria][subcategoria] = valor
        // Ejemplo: ["LUGAR_NACIMIENTO", "BOGOTA", "BOGOTA_DC"]
        $categoria = $labels[$item[0]];    // "Lugar de Nacimiento"
        $subcategoria = $labels[$item[1]]; // "Bogotá"
        $valor = $labels[$item[2]];        // "Bogotá D.C."

        // Manejo de valores múltiples
        if (isset($resultado[$categoria][$subcategoria])) {
            if (!is_array($resultado[$categoria][$subcategoria])) {
                $resultado[$categoria][$subcategoria] = [$resultado[$categoria][$subcategoria]];
            }
            $resultado[$categoria][$subcategoria][] = $valor;
        } else {
            $resultado[$categoria][$subcategoria] = $valor;
        }
    } elseif (count($item) === 2) {
        // Caso de 2 niveles: [categoria][] = valor
        // Ejemplo: ["EDAD", "30-40"]
        $categoria = $labels[$item[0]]; // "Edad"
        $valor = $labels[$item[1]];     // "30-40 años"

        if (!isset($resultado[$categoria])) {
            $resultado[$categoria] = [];
        }
        $resultado[$categoria][] = $valor;
    }
}

return $resultado;
```

**Sub-proceso: Carga de labels.json**

```php
// OpenKM::getDataFile("labels")
$query = "search/find?name=" . urlencode("labels.json") . "&path=" . urlencode(Config::TAX_APP_DATA);
$uuid = json_decode(self::consulta($query), true)["queryResult"]["node"]["uuid"];
return json_decode(self::getArchivo($uuid), true);
```

**Ejemplo de labels.json:**
```json
{
  "EDAD": "Edad",
  "30-40": "30-40 años",
  "GENERO": "Género",
  "MASCULINO": "Masculino",
  "FEMENINO": "Femenino",
  "ESTADO_CIVIL": "Estado Civil",
  "SOLTERO": "Soltero/a",
  "CASADO": "Casado/a",
  "LUGAR_NACIMIENTO": "Lugar de Nacimiento",
  "BOGOTA": "Bogotá",
  "BOGOTA_DC": "Bogotá D.C."
}
```

**Ejemplo de transformación:**

**Input (categorias):**
```php
[
  ["EDAD", "30-40"],
  ["GENERO", "MASCULINO"],
  ["ESTADO_CIVIL", "SOLTERO"],
  ["LUGAR_NACIMIENTO", "BOGOTA", "BOGOTA_DC"]
]
```

**Output (estructurado con labels):**
```php
[
  "Edad" => ["30-40 años"],
  "Género" => ["Masculino"],
  "Estado Civil" => ["Soltero/a"],
  "Lugar de Nacimiento" => [
    "Bogotá" => "Bogotá D.C."
  ]
]
```

---

**PASO 7: Respuesta al cliente**

**De vuelta en:** `/app/src/Controllers/V2/ProfesoresController.php`

```php
// Líneas 35-44
return $this->successResponse([
    'profesor' => $info,
    'cedula' => $params['cedula'],
    'meta' => [
        'total_archivos' => count($info['archivosProfesor'] ?? []),
        'incluye_demografia' => isset($info['datosDemograficos']),
        'version' => '2.0'
    ]
]);
```

**Ejemplo de respuesta JSON completa:**
```json
{
  "success": true,
  "data": {
    "profesor": {
      "archivosProfesor": [
        {
          "nombre": "cedula.pdf",
          "categorias": [
            ["TIPO", "CEDULA"],
            ["FORMATO", "PDF"],
            ["ORIGEN", "ONEDRIVE"]
          ]
        },
        {
          "nombre": "diploma.pdf",
          "categorias": [
            ["TIPO", "DIPLOMA"],
            ["FORMATO", "PDF"],
            ["ORIGEN", "GOOGLE_DRIVE"]
          ]
        }
      ],
      "datosDemograficos": {
        "nombre": "cedula.pdf",
        "categorias": {
          "Edad": ["30-40 años"],
          "Género": ["Masculino"],
          "Estado Civil": ["Soltero/a"],
          "Lugar de Nacimiento": {
            "Bogotá": "Bogotá D.C."
          }
        }
      }
    },
    "cedula": "1234567890",
    "meta": {
      "total_archivos": 2,
      "incluye_demografia": true,
      "version": "2.0"
    }
  }
}
```

---

#### Resumen de interacciones OpenKM

| Operación | Query OpenKM | Propósito | Resultado |
|-----------|--------------|-----------|-----------|
| Buscar todos los archivos | `search/find?path=okm:root/RUND/DOCUMENTOS/HOJAS_DE_VIDA/{cedula}` | Listar documentos del profesor | Array de nodos con UUID, path, categories |
| Buscar cédula específica | `search/find?path=okm:root/RUND/DOCUMENTOS/HOJAS_DE_VIDA/{cedula}&name=cedula` | Obtener documento de cédula | Nodo único con categorías demográficas |
| Cargar labels.json | `search/find?name=labels.json&path=okm:root/RUND/CONFIG/DATA` | Traducir claves a etiquetas legibles | Diccionario clave→etiqueta |
| Obtener contenido archivo | `document/getContent?docId={uuid}` | Descargar archivo (si se solicita) | Contenido binario del archivo |

---

## CONCLUSIONES

### Resumen de uso de dependencias por flujo

| Flujo | Dependencias utilizadas |
|-------|------------------------|
| Generación de certificado DOCX | phpoffice/phpword, endroid/qr-code, ext-gd, ext-zip |
| Generación de certificado PDF | phpoffice/phpword, endroid/qr-code, LibreOffice, ext-gd, ext-zip |
| Generación de reporte XLSX | phpoffice/phpspreadsheet, ext-zip |
| Generación de reporte PDF | phpoffice/phpspreadsheet, LibreOffice, ext-zip |
| Subida de archivo | ext-zip (indirectamente para DOCX/XLSX) |
| Consulta de información | Ninguna (solo operaciones OpenKM) |

### Puntos críticos de rendimiento

1. **Conversión PDF con LibreOffice:**
   - Proceso bloqueante que ejecuta comando shell
   - Timeout de 30 segundos por defecto
   - Puede fallar si LibreOffice no está disponible

2. **Generación de QR codes:**
   - Archivos temporales que deben limpiarse
   - Generación síncrona por certificado

3. **Búsquedas en OpenKM:**
   - Queries recursivas pueden ser lentas con muchos archivos
   - Sin caché implementado

### Recomendaciones

1. **Eliminar dompdf/dompdf si no se usa**
2. **Implementar caché para labels.json** (evitar consultas repetidas)
3. **Considerar queue para conversiones PDF** (procesamiento asíncrono)
4. **Implementar limpieza automática de /tmp** (archivos huérfanos)
5. **Agregar tests unitarios para servicios críticos**

---

**FIN DEL REPORTE**
