# 06. Flujos Críticos del Sistema RUND

Este documento detalla paso a paso los 3 flujos críticos más importantes del sistema RUND API v2, incluyendo diagramas Mermaid, ejemplos de código y payloads completos.

---

## 1. Generación de Certificado

**Endpoint:** `POST /api/v2/documentos/generar`
**Descripción:** Genera certificados personalizados en formato DOCX o PDF a partir de plantillas Word almacenadas en OpenKM.

### 1.1. Diagrama de Flujo

```mermaid
sequenceDiagram
    participant Client as Cliente
    participant DC as DocumentosController
    participant CH as CertificadosHandlers
    participant CS as CertificadosService
    participant QR as QRService
    participant FS as FirmasService
    participant LB as LBService
    participant OKM as OpenKM
    participant LO as LibreOffice

    Client->>DC: POST /api/v2/documentos/generar
    Note over Client,DC: {tipo: "certificado", plantilla, data, formato}

    DC->>DC: Valida parámetros (tipo, plantilla, data)
    DC->>CH: getCertificado(postData)

    CH->>CH: json_decode(data)
    CH->>CH: Genera ID único (16 chars) o usa existente

    CH->>OKM: findArchivo("expedidos.json")
    OKM-->>CH: UUID del archivo JSON

    CH->>OKM: getArchivo(uuid)
    OKM-->>CH: Contenido expedidos.json

    CH->>CH: Verifica si ID ya existe

    alt ID no existe
        CH->>CH: Utils::generaID()
        CH->>OKM: DocumentService::addToJSON()
        Note over CH,OKM: Registra certificado en expedidos.json
    end

    CH->>CS: creaCertificado(plantilla, estructura, id)

    CS->>OKM: search/find (busca plantilla DOCX)
    OKM-->>CS: UUID de plantilla

    CS->>OKM: getArchivo(uuid)
    OKM-->>CS: Contenido binario DOCX

    CS->>CS: file_put_contents(tmp/plantilla.docx)
    CS->>CS: new TemplateProcessor(plantilla)

    loop Para cada bloque en estructura
        alt tipo == "parrafo"
            CS->>CS: Verifica si es complejo (HTML)
            alt Es complejo
                CS->>CS: htmlToTextRun(texto)
                CS->>CS: setComplexValue(placeholder, textRun)
            else Es simple
                CS->>CS: setValue(placeholder, texto)
            end
        else tipo == "tabla"
            CS->>CS: Procesa encabezados y filas
            CS->>CS: cloneRow(primerEnc, numFilas)
            loop Para cada fila
                CS->>CS: setValue(placeholder#N, valor)
            end
        else tipo == "firma"
            CS->>FS: creaFirmaTemp(uuid)
            FS->>OKM: getArchivo(uuid firma)
            OKM-->>FS: Imagen firma PNG
            FS->>FS: file_put_contents(tmp/firma_uuid.png)
            FS-->>CS: ruta temporal firma
            CS->>CS: setImageValue('firma_imagen', ruta)
            CS->>CS: setValue('firma_nombre', nombre)
            CS->>CS: setValue('firma_cargo', cargo)
            CS->>CS: unlink(firma temporal)
        end
    end

    CS->>QR: creaQR(id)
    QR->>QR: Valida ID (16 chars alfanuméricos)
    QR->>QR: Construye URL validación
    QR->>QR: Genera QRCode (300x300px)
    QR->>QR: file_put_contents(tmp/qr_ID.png)
    QR-->>CS: {qr: ruta, url: url_validacion}

    CS->>CS: setImageValue('valida_qr', qr)
    CS->>CS: setValue('valida_url', url)
    CS->>CS: unlink(qr temporal)

    CS-->>CH: TemplateProcessor completo

    alt formato == "docx"
        CH->>CH: header('Content-Type: application/vnd.openxml...')
        CH->>CH: header('Content-Disposition: attachment...')
        CH->>CH: templateProcessor->saveAs("php://output")
        CH->>CH: unlink(tmp/plantilla.docx)
        CH-->>Client: Descarga certificado.docx
    else formato == "pdf"
        CH->>CH: nombreDOCX = "certificado_" + timestamp + ".docx"
        CH->>CH: templateProcessor->saveAs(tmp/nombreDOCX)

        CH->>LB: convierteWordToPDF(nombreDOCX)
        LB->>LB: Construye comando LibreOffice
        Note over LB: --headless --convert-to pdf:writer_pdf_Export
        LB->>LO: exec(comando)
        LO-->>LB: Conversión exitosa
        LB->>LB: Verifica file_exists(PDF)
        LB-->>CH: {error: null, salida: ruta_pdf}

        CH->>CH: header('Content-Type: application/pdf')
        CH->>CH: header('Content-Disposition: attachment...')
        CH->>CH: readfile(PDF)
        CH->>CH: unlink(PDF)
        CH->>CH: unlink(DOCX temporal)
        CH->>CH: unlink(plantilla.docx)
        CH->>CH: Utils::borrarMultiplesArchivos("certificado_*")
        CH-->>Client: Descarga certificado.pdf
    end
```

### 1.2. Clases y Métodos Involucrados

| Clase | Método | Responsabilidad |
|-------|--------|-----------------|
| `DocumentosController` | `generar()` | Punto de entrada, valida parámetros |
| `CertificadosHandlers` | `getCertificado()` | Orquesta el proceso completo |
| `CertificadosService` | `creaCertificado()` | Procesa plantilla DOCX |
| `CertificadosService` | `creaParrafo()` | Inserta texto simple |
| `CertificadosService` | `creaParrafoComplejo()` | Inserta texto con formato HTML |
| `CertificadosService` | `htmlToTextRun()` | Convierte HTML a TextRun de PhpWord |
| `CertificadosService` | `creaTabla()` | Clona filas y rellena tabla |
| `CertificadosService` | `creaFirma()` | Inserta firma e información |
| `QRService` | `creaQR()` | Genera código QR de validación |
| `FirmasService` | `creaFirmaTemp()` | Descarga firma desde OpenKM |
| `LBService` | `convierteWordToPDF()` | Convierte DOCX a PDF con LibreOffice |
| `DocumentService` | `addToJSON()` | Registra certificado en expedidos.json |
| `OpenKM` | `findArchivo()` | Busca archivo por nombre y ruta |
| `OpenKM` | `getArchivo()` | Descarga contenido por UUID |
| `Utils` | `generaID()` | Genera ID único de 16 caracteres |

### 1.3. Archivos Temporales Creados

Durante el proceso se crean archivos temporales en `/tmp/`:

1. **Plantilla descargada:** `plantilla.docx` (descargada de OpenKM)
2. **Firma temporal:** `firma_<uuid>.png` (descargada y eliminada inmediatamente)
3. **QR temporal:** `qr_<id>.png` (generado y eliminado inmediatamente)
4. **DOCX generado:** `certificado_YYYY-MM-DD-HH-mm-ss.docx` (solo para PDF)
5. **PDF final:** `certificado_YYYY-MM-DD-HH-mm-ss.pdf` (solo para PDF)

**Limpieza:** Todos los archivos temporales se eliminan después del envío al cliente.

### 1.4. Queries OpenKM Ejecutadas

```php
// 1. Buscar expedidos.json
"search/find?name=expedidos.json&path=/okm:root/RUND/DOCUMENTOS/CERTIFICADOS/"

// 2. Obtener contenido expedidos.json
"document/getContent?docId={uuid}"

// 3. Crear nueva versión de expedidos.json (si ID no existe)
"document/checkout?docId={uuid}"
"document/checkin?docId={uuid}"

// 4. Buscar plantilla DOCX
"search/find?name={plantilla}.docx&path=/okm:root/RUND/DOCUMENTOS/PLANTILLAS/CERTIFICADOS/"

// 5. Obtener contenido de plantilla
"document/getContent?docId={uuid_plantilla}"

// 6. Obtener firma PNG
"document/getContent?docId={uuid_firma}"
```

### 1.5. Ejemplo de Payload JSON (Entrada)

```json
{
  "tipo": "certificado",
  "plantilla": "certificado_laboral",
  "formato": "pdf",
  "id": "A1B2C3D4E5F6G7H8",
  "data": "[
    {
      \"tipo\": \"parrafo\",
      \"value\": \"La <strong>Escuela Superior de Administración Pública ESAP</strong> certifica que:\"
    },
    {
      \"tipo\": \"parrafo\",
      \"value\": \"<strong>JUAN CARLOS PÉREZ GARCÍA</strong>, identificado con cédula de ciudadanía No. <strong>1234567890</strong>, labora en esta institución desde el <em>15 de enero de 2020</em>.\"
    },
    {
      \"tipo\": \"tabla\",
      \"value\": {
        \"encabezados\": [\"CARGO\", \"FECHA_INICIO\", \"FECHA_FIN\"],
        \"filas\": [
          [\"Docente Catedrático\", \"2020-01-15\", \"2023-12-31\"],
          [\"Investigador\", \"2021-06-01\", \"Actualidad\"]
        ]
      }
    },
    {
      \"tipo\": \"parrafo\",
      \"value\": \"Se expide la presente certificación a solicitud del interesado para los fines que estime convenientes.\"
    },
    {
      \"tipo\": \"firma\",
      \"value\": {
        \"uuid\": \"e7f8g9h0-i1j2-k3l4-m5n6-o7p8q9r0s1t2\",
        \"nombre\": \"María Fernanda López\",
        \"cargo\": \"Directora de Talento Humano\"
      }
    }
  ]"
}
```

### 1.6. Ejemplo de Respuesta

**Formato DOCX:**
```
HTTP/1.1 200 OK
Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document
Content-Disposition: attachment; filename="certificado_laboral.docx"
Content-Transfer-Encoding: binary

[Contenido binario del archivo DOCX]
```

**Formato PDF:**
```
HTTP/1.1 200 OK
Content-Type: application/pdf
Content-Disposition: attachment; filename="certificado_2025-10-20-15-30-45.pdf"
Content-Length: 245678

[Contenido binario del archivo PDF]
```

### 1.7. Registro en expedidos.json

El sistema mantiene un registro de todos los certificados generados:

```json
[
  {
    "id": "A1B2C3D4E5F6G7H8",
    "tipo": "certificado",
    "plantilla": "certificado_laboral",
    "formato": "pdf",
    "data": "[...]",
    "timestamp": "2025-10-20T15:30:45-05:00"
  }
]
```

Este registro permite:
- Evitar duplicados (si se envía el mismo ID)
- Recuperar certificados previamente generados
- Validación en página web mediante QR

---

## 2. Subida de Archivo a OpenKM

**Endpoint:** `POST /api/v2/archivos/subir`
**Descripción:** Carga archivos al repositorio OpenKM con categorización automática y versionado.

### 2.1. Diagrama de Flujo General

```mermaid
flowchart TD
    Start([POST /api/v2/archivos/subir]) --> ValidaParams{Validar parámetros}
    ValidaParams -->|Falta accion| Error1[Error: accion requerido]
    ValidaParams -->|Falta archivo| Error2[Error: archivo requerido]
    ValidaParams -->|OK| CheckAccion{accion = ?}

    CheckAccion -->|cargaFirma| FlujoFirma[Flujo A: Carga de Firma]
    CheckAccion -->|cargaDocumento| FlujoDoc[Flujo B: Carga de Documento]

    FlujoFirma --> EndFirma([Respuesta con UUIDs PNG + JSON])
    FlujoDoc --> EndDoc([Respuesta con UUID y categorías])

    style FlujoFirma fill:#e1f5ff
    style FlujoDoc fill:#fff4e1
```

### 2.2. Flujo A: Carga de Firma (PNG + JSON Side-Car)

Las firmas se almacenan como dos archivos vinculados:
1. **PNG:** Imagen de la firma
2. **JSON:** Metadatos (cargo, nombres, apellidos, fecha)

```mermaid
sequenceDiagram
    participant Client as Cliente
    participant AC as ArchivosController
    participant FH as FileHandlers
    participant CS as CategoriasService
    participant DS as DocumentService
    participant OKM as OpenKM

    Client->>AC: POST /archivos/subir
    Note over Client,AC: accion: "cargaFirma"<br/>archivo: firma.png<br/>propiedades: JSON

    AC->>AC: getPostData() + getFiles()
    AC->>FH: postFile(params, files)

    FH->>FH: Extrae propiedades del JSON
    Note over FH: cargo, nombres, apellidos, fecha

    FH->>FH: path = /RUND/DOCUMENTOS/FIRMAS/
    FH->>OKM: yaExiste("firma.png", path)
    OKM-->>FH: boolean (duplicado)

    FH->>FH: Prepara categorías a crear
    Note over FH: CARGO/DIRECTOR<br/>TIPO/RUND_FIRMA<br/>TIPO/RUND_FIRMA_SIDE-CAR<br/>FORMATO/PNG<br/>FORMATO/CSV

    FH->>CS: creaCategorias(categorias[])
    loop Para cada categoría
        CS->>OKM: folder/isValid?fldId={path}
        alt No existe
            CS->>OKM: folder/create (crea carpeta categoría)
        end
    end
    CS-->>FH: Resultado creación categorías

    rect rgb(200, 230, 255)
        Note over FH,OKM: PASO 1: Carga PNG
        FH->>OKM: cargaArchivo(firma.png, propiedades, path, dupe)

        alt Archivo duplicado
            OKM->>OKM: document/checkout (bloquea documento)
            OKM->>OKM: document/checkin (nueva versión)
            Note over OKM: Versión: 1.1, 1.2, etc.
        else Archivo nuevo
            OKM->>OKM: document/createSimple
            Note over OKM: Versión: 1.0
        end

        OKM-->>FH: Respuesta carga PNG

        FH->>OKM: search/find?name=firma.png&path={path}
        OKM-->>FH: UUID del PNG

        FH->>FH: Prepara categorías PNG
        Note over FH: CARGO/DIRECTOR<br/>TIPO/RUND_FIRMA<br/>FORMATO/PNG

        FH->>OKM: document/setProperties (PUT)
        Note over FH,OKM: Asigna categorías al PNG
        OKM-->>FH: Confirmación categorías PNG
    end

    rect rgb(255, 240, 200)
        Note over FH,OKM: PASO 2: Genera y carga JSON side-car
        FH->>FH: nombreJSON = "firma.json"
        FH->>OKM: search/find?name=firma.json&path={path}
        OKM-->>FH: UUID JSON (si existe) o null

        FH->>FH: Construye dataJSON
        Note over FH: {cargo, nombres, apellidos,<br/>fecha, firma: "firma.png"}

        FH->>DS: cargaJSON(dataJSON, "firma.json", path, uuid)

        alt UUID existe (actualizar)
            DS->>OKM: document/checkout?docId={uuid}
            DS->>OKM: document/checkin (nueva versión JSON)
        else UUID null (crear)
            DS->>OKM: document/createSimple (JSON)
        end

        DS-->>FH: Resultado carga JSON

        FH->>OKM: search/find?name=firma.json&path={path}
        OKM-->>FH: UUID actualizado del JSON

        FH->>FH: Prepara categorías JSON
        Note over FH: CARGO/DIRECTOR<br/>TIPO/RUND_FIRMA_SIDE-CAR<br/>FORMATO/CSV

        FH->>OKM: document/setProperties (PUT)
        Note over FH,OKM: Asigna categorías al JSON
        OKM-->>FH: Confirmación categorías JSON
    end

    FH-->>AC: Resultado completo
    AC->>AC: successResponse()
    AC-->>Client: JSON con ambos UUIDs y categorías
```

#### 2.2.1. Ejemplo de Payload - Carga de Firma

```json
{
  "accion": "cargaFirma",
  "propiedades": "[
    {\"label\": \"cargo\", \"valor\": \"Director Académico\"},
    {\"label\": \"nombres\", \"valor\": \"Carlos Andrés\"},
    {\"label\": \"apellidos\", \"valor\": \"Rodríguez Pérez\"},
    {\"label\": \"fecha\", \"valor\": \"2025-10-20\"}
  ]",
  "archivo": "[Archivo binario firma.png]"
}
```

#### 2.2.2. Estructura en OpenKM - Firmas

```
/okm:root/RUND/DOCUMENTOS/FIRMAS/
├── firma_director_academico.png  [UUID: abc123...]
│   └── Categorías:
│       ├── /okm:categories/RUND/DOCUMENTOS/FIRMAS/CARGO/DIRECTOR_ACADEMICO
│       ├── /okm:categories/RUND/DOCUMENTOS/FIRMAS/TIPO/RUND_FIRMA
│       └── /okm:categories/RUND/DOCUMENTOS/FIRMAS/FORMATO/PNG
│
└── firma_director_academico.json [UUID: def456...]
    └── Categorías:
        ├── /okm:categories/RUND/DOCUMENTOS/FIRMAS/CARGO/DIRECTOR_ACADEMICO
        ├── /okm:categories/RUND/DOCUMENTOS/FIRMAS/TIPO/RUND_FIRMA_SIDE-CAR
        └── /okm:categories/RUND/DOCUMENTOS/FIRMAS/FORMATO/CSV
```

#### 2.2.3. Contenido JSON Side-Car

```json
[
  {"label": "cargo", "valor": "Director Académico"},
  {"label": "nombres", "valor": "Carlos Andrés"},
  {"label": "apellidos", "valor": "Rodríguez Pérez"},
  {"label": "fecha", "valor": "2025-10-20"},
  {"label": "firma", "valor": "firma_director_academico.png"}
]
```

### 2.3. Flujo B: Carga de Documento (Hoja de Vida)

Los documentos se organizan por cédula del profesor con estructura jerárquica.

```mermaid
sequenceDiagram
    participant Client as Cliente
    participant AC as ArchivosController
    participant FH as FileHandlers
    participant OKM as OpenKM

    Client->>AC: POST /archivos/subir
    Note over Client,AC: accion: "cargaDocumento"<br/>archivo: cedula.pdf<br/>propiedades: JSON

    AC->>FH: postFile(params, files)

    FH->>FH: Extrae propiedades
    Note over FH: cedula: "1234567890"<br/>taxonomia: "ACADEMICOS/DIPLOMAS"<br/>tipo: "DIPLOMA"<br/>formato: "PDF"<br/>origen: "ONEDRIVE"<br/>esCedula: false<br/>categorias: []

    FH->>FH: Valida cédula (regex)
    Note over FH: /^\d{4,20}$/

    FH->>FH: Construye ruta
    Note over FH: path = /RUND/DOCENTES/HOJAS_DE_VIDA/<br/>1234567890/ACADEMICOS/DIPLOMAS

    rect rgb(230, 255, 230)
        Note over FH,OKM: PASO 1: Crear taxonomía recursiva
        FH->>OKM: creaCarpetas(["1234567890/ACADEMICOS/DIPLOMAS"])

        loop Para cada nivel de carpeta
            OKM->>OKM: folder/isValid?fldId={path}
            alt No existe
                OKM->>OKM: folder/create (crea carpeta)
                Note over OKM: Crea: 1234567890<br/>Luego: ACADEMICOS<br/>Luego: DIPLOMAS
            end
        end

        OKM-->>FH: Taxonomía creada
    end

    rect rgb(255, 230, 230)
        Note over FH,OKM: PASO 2: Crear categorías
        FH->>FH: Construye rutas de categorías
        Note over FH: esCedula? Añade categorías demográficas<br/>Siempre: TIPO, FORMATO, ORIGEN

        FH->>FH: rutasCategorias[]
        Note over FH: /RUND/DOCUMENTOS/HOJAS_DE_VIDA/TIPO/DIPLOMA<br/>/RUND/DOCUMENTOS/HOJAS_DE_VIDA/FORMATO/PDF<br/>/RUND/DOCUMENTOS/HOJAS_DE_VIDA/ORIGEN/ONEDRIVE

        FH->>OKM: creaCarpetas(rutasCategorias)

        loop Para cada categoría
            OKM->>OKM: folder/isValid?fldId={path_cat}
            alt No existe
                OKM->>OKM: folder/create (categoría)
            end
        end

        OKM-->>FH: Categorías creadas
    end

    rect rgb(230, 230, 255)
        Note over FH,OKM: PASO 3: Cargar archivo
        FH->>OKM: yaExiste("diploma.pdf", path)
        OKM-->>FH: boolean duplicado

        FH->>OKM: cargaArchivo(diploma.pdf, props, path, dupe)

        alt Duplicado
            OKM->>OKM: document/checkout
            OKM->>OKM: document/checkin (nueva versión)
            Note over OKM: v1.1, v1.2, etc.
        else Nuevo
            OKM->>OKM: document/createSimple
            Note over OKM: v1.0
        end

        OKM-->>FH: Resultado carga
    end

    rect rgb(255, 255, 230)
        Note over FH,OKM: PASO 4: Categorizar documento
        FH->>OKM: search/find?name=diploma.pdf&path={path}
        OKM-->>FH: UUID del documento

        FH->>FH: Prepara categorías para aplicar
        Note over FH: [{path: .../TIPO/DIPLOMA},<br/>{path: .../FORMATO/PDF},<br/>{path: .../ORIGEN/ONEDRIVE}]

        FH->>OKM: document/setProperties (PUT)
        Note over FH,OKM: Asigna todas las categorías
        OKM-->>FH: Confirmación
    end

    FH-->>AC: Resultado completo
    AC->>AC: successResponse()
    AC-->>Client: JSON con UUID y categorías aplicadas
```

#### 2.3.1. Ejemplo de Payload - Carga de Cédula (Documento Demográfico)

```json
{
  "accion": "cargaDocumento",
  "propiedades": "[
    {\"label\": \"cedula\", \"valor\": \"1234567890\"},
    {\"label\": \"taxonomia\", \"valor\": \"\"},
    {\"label\": \"tipo\", \"valor\": \"CEDULA\"},
    {\"label\": \"formato\", \"valor\": \"PDF\"},
    {\"label\": \"origen\", \"valor\": \"SCANNER\"},
    {\"label\": \"esCedula\", \"valor\": true},
    {\"label\": \"categorias\", \"valor\": [
      \"GENERO/MASCULINO\",
      \"EDAD/30-40\",
      \"ESTADO_CIVIL/SOLTERO\",
      \"NIVEL_EDUCATIVO/MAESTRIA\"
    ]}
  ]",
  "archivo": "[Archivo binario cedula.pdf]"
}
```

#### 2.3.2. Ejemplo de Payload - Carga de Documento Regular

```json
{
  "accion": "cargaDocumento",
  "propiedades": "[
    {\"label\": \"cedula\", \"valor\": \"1234567890\"},
    {\"label\": \"taxonomia\", \"valor\": \"ACADEMICOS/DIPLOMAS\"},
    {\"label\": \"tipo\", \"valor\": \"DIPLOMA\"},
    {\"label\": \"formato\", \"valor\": \"PDF\"},
    {\"label\": \"origen\", \"valor\": \"ONEDRIVE\"},
    {\"label\": \"esCedula\", \"valor\": false}
  ]",
  "archivo": "[Archivo binario diploma_maestria.pdf]"
}
```

#### 2.3.3. Estructura en OpenKM - Documentos

```
/okm:root/RUND/DOCENTES/HOJAS_DE_VIDA/
└── 1234567890/                         [Carpeta por cédula]
    ├── cedula.pdf                      [Documento demográfico]
    │   └── Categorías:
    │       ├── /RUND/DOCENTES/GENERO/MASCULINO
    │       ├── /RUND/DOCENTES/EDAD/30-40
    │       ├── /RUND/DOCENTES/ESTADO_CIVIL/SOLTERO
    │       ├── /RUND/DOCENTES/NIVEL_EDUCATIVO/MAESTRIA
    │       ├── /RUND/DOCUMENTOS/HOJAS_DE_VIDA/TIPO/CEDULA
    │       ├── /RUND/DOCUMENTOS/HOJAS_DE_VIDA/FORMATO/PDF
    │       └── /RUND/DOCUMENTOS/HOJAS_DE_VIDA/ORIGEN/SCANNER
    │
    ├── ACADEMICOS/
    │   ├── DIPLOMAS/
    │   │   └── diploma_maestria.pdf
    │   │       └── Categorías:
    │   │           ├── /RUND/DOCUMENTOS/HOJAS_DE_VIDA/TIPO/DIPLOMA
    │   │           ├── /RUND/DOCUMENTOS/HOJAS_DE_VIDA/FORMATO/PDF
    │   │           └── /RUND/DOCUMENTOS/HOJAS_DE_VIDA/ORIGEN/ONEDRIVE
    │   │
    │   └── ACTAS/
    │       └── acta_grado.pdf
    │
    └── LABORALES/
        └── CONTRATOS/
            └── contrato_2024.pdf
```

### 2.4. Sistema de Versionado OpenKM

OpenKM maneja versiones automáticamente mediante checkout/checkin:

```mermaid
stateDiagram-v2
    [*] --> NoExiste
    NoExiste --> v1.0: document/createSimple
    v1.0 --> Bloqueado: document/checkout
    Bloqueado --> v1.1: document/checkin
    v1.1 --> Bloqueado: document/checkout
    Bloqueado --> v1.2: document/checkin
    v1.2 --> Bloqueado: document/checkout
    Bloqueado --> v2.0: document/checkin (major)
```

**Importante:**
- **Checkout:** Bloquea el documento para edición
- **Checkin:** Guarda nueva versión y desbloquea
- **Mensaje de versión:** Se registra con cada checkin
- **Historial completo:** OpenKM mantiene todas las versiones

### 2.5. Ejemplo de Respuesta Exitosa

```json
{
  "success": true,
  "data": {
    "archivo": {
      "creaTaxonomia": "OK",
      "creaCategorias": "OK",
      "carga": {
        "documento": {
          "uuid": "f7e6d5c4-b3a2-9180-7f6e-5d4c3b2a1098",
          "version": "1.0",
          "path": "/okm:root/RUND/DOCENTES/HOJAS_DE_VIDA/1234567890/ACADEMICOS/DIPLOMAS/diploma_maestria.pdf",
          "size": 245678
        }
      },
      "setProperties": "OK"
    },
    "meta": {
      "accion": "cargaDocumento",
      "nombre_original": "diploma_maestria.pdf",
      "tamaño": 245678,
      "version": "2.0"
    }
  }
}
```

---

## 3. Consulta de Información de Profesor

**Endpoint:** `GET /api/v2/profesores/{cedula}`
**Descripción:** Obtiene información completa del profesor incluyendo datos demográficos y archivos asociados.

### 3.1. Diagrama de Flujo

```mermaid
sequenceDiagram
    participant Client as Cliente
    participant PC as ProfesoresController
    participant DH as DataHandlers
    participant DS as DocumentService
    participant OKM as OpenKM

    Client->>PC: GET /api/v2/profesores/1234567890

    PC->>PC: Valida parámetro cedula
    PC->>DH: getInfoProfesor("1234567890")

    DH->>DH: Valida formato cédula
    Note over DH: regex: /^\d{4,20}$/

    rect rgb(255, 240, 240)
        Note over DH,OKM: PASO 1: Obtener archivos del profesor
        DH->>DS: getInfoArchivosProfesor(cedula, false)
        Note over DH,DS: false = TODOS los archivos

        DS->>DS: Construye query sin filtro de nombre
        Note over DS: path=/RUND/DOCENTES/HOJAS_DE_VIDA/1234567890

        DS->>OKM: search/find?path={path}
        Note over DS,OKM: Busca TODOS los documentos<br/>en la carpeta del profesor

        OKM-->>DS: queryResult[] (array de nodos)

        loop Para cada nodo en queryResult
            DS->>DS: extraeDatosDocumento(nodo)

            Note over DS: Extrae nombre del archivo
            DS->>DS: path.replace(TAX_HOJAS)
            DS->>DS: Divide por "/" y toma último elemento

            Note over DS: Extrae categorías del documento
            loop Para cada categoría en nodo.categories
                DS->>DS: Filtra por CTGR_DOCS_HOJAS
                DS->>DS: Divide path categoría en partes
                Note over DS: [TIPO, DIPLOMA]<br/>[FORMATO, PDF]<br/>[ORIGEN, ONEDRIVE]
            end

            DS->>DS: Agrega al array datos[]
        end

        DS-->>DH: Array con info de todos los archivos
    end

    rect rgb(240, 255, 240)
        Note over DH,OKM: PASO 2: Obtener datos demográficos
        DH->>DS: getInfoArchivosProfesor(cedula, true)
        Note over DH,DS: true = Solo cédula (datos demográficos)

        DS->>DS: Construye query con filtro
        Note over DS: path=.../1234567890<br/>name=cedula

        DS->>OKM: search/find?path={path}&name=cedula
        Note over DS,OKM: Busca específicamente<br/>archivo "cedula"

        OKM-->>DS: queryResult.node (único nodo)

        DS->>DS: extraeDatosDocumento(nodo)

        Note over DS: Extrae categorías demográficas
        loop Para cada categoría
            DS->>DS: Filtra por ROOT_CTG_PROF
            Note over DS: Solo categorías demográficas:<br/>GENERO, EDAD, ESTADO_CIVIL, etc.
            DS->>DS: Divide en partes [tipo, valor]
        end

        DS-->>DH: Datos demográficos con categorías
    end

    rect rgb(240, 240, 255)
        Note over DH,OKM: PASO 3: Estructurar categorías demográficas
        DH->>OKM: getDataFile("labels")
        Note over DH,OKM: Obtiene labels.json<br/>para traducir códigos

        OKM->>OKM: findArchivo("labels.json")
        OKM->>OKM: getArchivo(uuid)
        OKM-->>DH: Contenido labels.json

        DH->>DS: estructuraCategorias(categorias)

        loop Para cada categoría [tipo, valor]
            DS->>DS: labels[tipo] -> "Género"
            DS->>DS: labels[valor] -> "Masculino"

            alt Categoría de 3 niveles
                Note over DS: [PROGRAMAS, MAESTRIA, ADMINISTRACION]
                DS->>DS: Agrupa por tipo y subtipo
                Note over DS: resultado[Programas][Maestría] = "Administración"
            else Categoría de 2 niveles
                Note over DS: [GENERO, MASCULINO]
                DS->>DS: Agrupa por tipo
                Note over DS: resultado[Género][] = "Masculino"
            end
        end

        DS-->>DH: Categorías estructuradas jerárquicamente
    end

    DH->>DH: Construye respuesta final
    Note over DH: {archivosProfesor: [...],<br/>datosDemograficos: {...}}

    DH-->>PC: Información completa

    PC->>PC: successResponse()
    PC->>PC: Añade metadatos
    Note over PC: total_archivos<br/>incluye_demografia<br/>version: 2.0

    PC-->>Client: JSON con toda la información
```

### 3.2. Queries OpenKM Ejecutadas

```php
// 1. Buscar todos los archivos del profesor
"search/find?path=/okm:root/RUND/DOCENTES/HOJAS_DE_VIDA/1234567890"

// 2. Buscar específicamente el archivo de cédula
"search/find?path=/okm:root/RUND/DOCENTES/HOJAS_DE_VIDA/1234567890&name=cedula"

// 3. Obtener archivo labels.json (para traducción)
"search/find?name=labels.json&path=/okm:root/RUND/CONFIG/DATA/"
"document/getContent?docId={uuid_labels}"
```

### 3.3. Transformación de Categorías

El sistema transforma las categorías de OpenKM a una estructura legible:

```php
// INPUT: Categorías crudas de OpenKM
[
  ["GENERO", "MASCULINO"],
  ["EDAD", "30-40"],
  ["ESTADO_CIVIL", "SOLTERO"],
  ["PROGRAMAS", "MAESTRIA", "ADMINISTRACION"],
  ["PROGRAMAS", "MAESTRIA", "GESTION_PUBLICA"],
  ["NIVEL_EDUCATIVO", "MAESTRIA"]
]

// PROCESO: Lookup en labels.json
{
  "GENERO": "Género",
  "MASCULINO": "Masculino",
  "EDAD": "Edad",
  "30-40": "30 a 40 años",
  "ESTADO_CIVIL": "Estado Civil",
  "SOLTERO": "Soltero",
  "PROGRAMAS": "Programas",
  "MAESTRIA": "Maestría",
  "ADMINISTRACION": "Administración Pública",
  "GESTION_PUBLICA": "Gestión Pública",
  "NIVEL_EDUCATIVO": "Nivel Educativo"
}

// OUTPUT: Estructura jerárquica legible
{
  "Género": ["Masculino"],
  "Edad": ["30 a 40 años"],
  "Estado Civil": ["Soltero"],
  "Programas": {
    "Maestría": ["Administración Pública", "Gestión Pública"]
  },
  "Nivel Educativo": ["Maestría"]
}
```

### 3.4. Ejemplo de Respuesta Completa

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
            ["ORIGEN", "SCANNER"]
          ]
        },
        {
          "nombre": "diploma_maestria.pdf",
          "categorias": [
            ["TIPO", "DIPLOMA"],
            ["FORMATO", "PDF"],
            ["ORIGEN", "ONEDRIVE"]
          ]
        },
        {
          "nombre": "contrato_2024.pdf",
          "categorias": [
            ["TIPO", "CONTRATO"],
            ["FORMATO", "PDF"],
            ["ORIGEN", "SCANNER"]
          ]
        },
        {
          "nombre": "certificado_bancario.pdf",
          "categorias": [
            ["TIPO", "CERTIFICADO_BANCARIO"],
            ["FORMATO", "PDF"],
            ["ORIGEN", "EMAIL"]
          ]
        }
      ],
      "datosDemograficos": {
        "nombre": "cedula.pdf",
        "categorias": {
          "Género": ["Masculino"],
          "Edad": ["30 a 40 años"],
          "Estado Civil": ["Soltero"],
          "Nivel Educativo": ["Maestría"],
          "Programas": {
            "Maestría": [
              "Administración Pública",
              "Gestión Pública"
            ]
          },
          "Departamento": ["Cundinamarca"],
          "Ciudad": ["Bogotá D.C."],
          "Tipo Documento": ["Cédula de Ciudadanía"]
        }
      }
    },
    "cedula": "1234567890",
    "meta": {
      "total_archivos": 4,
      "incluye_demografia": true,
      "version": "2.0"
    }
  }
}
```

### 3.5. Procesamiento de Categorías Múltiples

Cuando un documento tiene múltiples valores para la misma categoría:

```php
// Categorías de entrada
[
  ["PROGRAMAS", "MAESTRIA", "ADMINISTRACION"],
  ["PROGRAMAS", "MAESTRIA", "GESTION_PUBLICA"],
  ["PROGRAMAS", "DOCTORADO", "CIENCIAS_POLITICAS"]
]

// Primera iteración: establece valor único
resultado["Programas"]["Maestría"] = "Administración Pública"

// Segunda iteración: detecta duplicado y convierte a array
resultado["Programas"]["Maestría"] = [
  "Administración Pública"  // valor anterior
]
resultado["Programas"]["Maestría"][] = "Gestión Pública"  // nuevo valor

// Tercera iteración: nueva subcategoría
resultado["Programas"]["Doctorado"] = "Ciencias Políticas"

// Resultado final
{
  "Programas": {
    "Maestría": [
      "Administración Pública",
      "Gestión Pública"
    ],
    "Doctorado": "Ciencias Políticas"
  }
}
```

### 3.6. Endpoints Relacionados

El sistema ofrece endpoints especializados para acceso parcial:

```http
# Información completa (datos + archivos)
GET /api/v2/profesores/1234567890

# Solo archivos (sin datos demográficos)
GET /api/v2/profesores/1234567890/archivos

# Solo datos demográficos
GET /api/v2/profesores/1234567890/demografia
```

**Respuesta solo archivos:**
```json
{
  "success": true,
  "data": {
    "archivos": [...],
    "cedula": "1234567890",
    "estadisticas": {
      "por_tipo": {
        "DIPLOMA": 2,
        "CONTRATO": 1,
        "CERTIFICADO_BANCARIO": 1
      },
      "por_formato": {
        "PDF": 4
      },
      "por_origen": {
        "ONEDRIVE": 2,
        "SCANNER": 1,
        "EMAIL": 1
      }
    },
    "meta": {
      "total": 4,
      "endpoint": "archivos",
      "version": "2.0"
    }
  }
}
```

---

## Resumen de Archivos Clave

| Archivo | Propósito | Ubicación |
|---------|-----------|-----------|
| `DocumentosController.php` | Maneja generación de documentos | `/app/src/Controllers/V2/` |
| `ArchivosController.php` | Maneja subida/descarga de archivos | `/app/src/Controllers/V2/` |
| `ProfesoresController.php` | Maneja consultas de profesores | `/app/src/Controllers/V2/` |
| `CertificadosHandlers.php` | Orquesta generación de certificados | `/app/src/Handlers/` |
| `FileHandlers.php` | Orquesta operaciones de archivos | `/app/src/Handlers/` |
| `DataHandlers.php` | Orquesta consultas de datos | `/app/src/Handlers/` |
| `CertificadosService.php` | Procesa plantillas DOCX | `/app/src/Services/` |
| `DocumentService.php` | Operaciones con documentos OpenKM | `/app/src/Services/` |
| `QRService.php` | Generación de códigos QR | `/app/src/Services/` |
| `FirmasService.php` | Gestión de firmas digitales | `/app/src/Services/` |
| `LBService.php` | Conversión DOCX/XLSX a PDF | `/app/src/Services/` |
| `OpenKM.php` | Cliente REST de OpenKM | `/app/src/Core/` |
| `Config.php` | Constantes y rutas del sistema | `/app/src/Config/` |

---

## Notas Importantes

1. **Temporales:** Todos los archivos en `/tmp/` se eliminan después de procesarse
2. **Versionado:** OpenKM mantiene historial completo de versiones
3. **Categorización:** Automática según propiedades del archivo
4. **Validación:** Cédulas deben ser numéricas (4-20 dígitos)
5. **Formato QR:** ID debe ser exactamente 16 caracteres alfanuméricos
6. **LibreOffice:** Conversión a PDF ejecuta en modo headless
7. **Side-car:** Firmas siempre tienen PNG + JSON vinculado
8. **Labels:** Sistema de traducción jerárquico para categorías
9. **Seguridad:** Autenticación básica con OpenKM (usuario/contraseña)
10. **Timeout:** Conexiones OpenKM con timeout de 30 segundos

---

**Documento generado:** 2025-10-20
**Versión API:** 2.0
**Autor:** ESAP Development Team
