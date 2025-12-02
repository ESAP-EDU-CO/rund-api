# Documentación Completa de Endpoints - RUND API v2

> **Versión:** 2.0
> **Autor:** Oliver Castelblanco Martínez (oliver.castelblanco@esap.edu.co)
> **Total de Endpoints:** 30
> **Arquitectura:** RESTful con Controllers modulares (PSR-4)

---

## Índice de Categorías

1. [Sistema (7 endpoints)](#1-sistema-7-endpoints)
2. [Certificados (3 endpoints)](#2-certificados-3-endpoints)
3. [Categorías (2 endpoints)](#3-categorías-2-endpoints)
4. [Profesores (4 endpoints)](#4-profesores-4-endpoints)
5. [Documentos (3 endpoints)](#5-documentos-3-endpoints)
6. [Archivos (8 endpoints)](#6-archivos-8-endpoints)
7. [Listados (4 endpoints)](#7-listados-4-endpoints)
8. [Firmas (3 endpoints)](#8-firmas-3-endpoints)
9. [AI (1 endpoint)](#9-ai-1-endpoint)
10. [Tabla Resumen](#tabla-resumen-de-todos-los-endpoints)

---

## 1. Sistema (7 endpoints)

### 1.1 GET /api/v2/system/info

**Descripción:** Obtiene información completa del sistema, versión, arquitectura y endpoints disponibles.

**Controller:** `SystemController::getInfo()`

**Parámetros de entrada:** Ninguno

**Validaciones:** Ninguna (endpoint público)

**Respuesta exitosa (200):**
```json
{
  "success": true,
  "data": {
    "version": "2.0",
    "api_version": "v2",
    "nombre": "RUND API v2",
    "descripcion": "API RESTful moderna para la gestión de documentos y certificados en RUND",
    "autor": "Oliver Castelblanco Martínez",
    "email": "oliver.castelblanco@esap.edu.co",
    "arquitectura": "RESTful con Controllers modulares",
    "autoloader": "PSR-4 via Composer",
    "namespace": "RUND",
    "router": "Modular Router v2",
    "idioma": "español",
    "endpoints": {
      "certificados": "/api/v2/certificados",
      "categorias": "/api/v2/categorias",
      "profesores": "/api/v2/profesores",
      "documentos": "/api/v2/documentos",
      "archivos": "/api/v2/archivos",
      "listados": "/api/v2/listados",
      "firmas": "/api/v2/firmas",
      "ai": "/api/v2/ai"
    }
  }
}
```

**Códigos de error:** Ninguno (siempre retorna 200)

**Ejemplo cURL:**
```bash
curl -X GET http://localhost:3000/api/v2/system/info
```

**Handlers/Services:** Ninguno (respuesta estática)

---

### 1.2 GET /api/v2/system/health

**Descripción:** Health check para monitoreo del sistema y sus servicios dependientes.

**Controller:** `SystemController::getHealth()`

**Parámetros de entrada:** Ninguno

**Validaciones:** Ninguna

**Respuesta exitosa (200):**
```json
{
  "success": true,
  "data": {
    "status": "healthy",
    "version": "2.0",
    "timestamp": "2025-10-20 14:30:45",
    "uptime": "72h 15m",
    "services": {
      "database": "connected",
      "storage": "available",
      "ai": "operational"
    }
  }
}
```

**Códigos de error:**
- `500`: Error interno del sistema

**Ejemplo cURL:**
```bash
curl -X GET http://localhost:3000/api/v2/system/health
```

**Handlers/Services:** `shell_exec('cat /proc/uptime')` para obtener uptime

---

### 1.3 GET /api/v2/system/capabilities

**Descripción:** Lista todas las capacidades y funcionalidades disponibles en la API.

**Controller:** `SystemController::getCapabilities()`

**Parámetros de entrada:** Ninguno

**Validaciones:** Ninguna

**Respuesta exitosa (200):**
```json
{
  "success": true,
  "data": {
    "version": "2.0",
    "features": {
      "certificados": {
        "generar": true,
        "plantillas": ["1050", "1051", "1231"],
        "formatos": ["docx", "pdf"]
      },
      "archivos": {
        "subir": true,
        "formatos": ["pdf", "docx", "xlsx", "png", "jpg", "svg"],
        "max_size": "50MB"
      },
      "ai": {
        "extraccion_datos": true,
        "ocr": true,
        "modelos": ["ollama"]
      },
      "listados": {
        "excel": true,
        "csv": true,
        "validacion": true
      }
    },
    "limits": {
      "max_file_size": 52428800,
      "supported_formats": ["docx", "pdf", "xlsx", "csv", "png", "jpg", "svg"],
      "rate_limit": "100 requests/minute"
    }
  }
}
```

**Códigos de error:** Ninguno

**Ejemplo cURL:**
```bash
curl -X GET http://localhost:3000/api/v2/system/capabilities
```

**Handlers/Services:** Ninguno (respuesta estática)

---

### 1.4 GET /api/v2/system/migration

**Descripción:** Obtiene el estado de la migración de v1 a v2.

**Controller:** `SystemController::getMigrationStatus()`

**Parámetros de entrada:** Ninguno

**Validaciones:** Ninguna

**Respuesta exitosa (200):**
```json
{
  "success": true,
  "data": {
    "migration": {
      "from": "v1",
      "to": "v2",
      "status": "completed",
      "completion_date": "2025-09-19",
      "all_endpoints_migrated": ["system/info", "system/health", "..."],
      "legacy_removed": true
    },
    "statistics": {
      "total_endpoints": 27,
      "migrated": 27,
      "pending": 0,
      "progress": "100%"
    },
    "benefits_achieved": [
      "RESTful structure complete",
      "Spanish nomenclature implemented",
      "Robust validation added",
      "Consistent error handling",
      "Swagger documentation integrated"
    ],
    "version": "2.0"
  }
}
```

**Códigos de error:** Ninguno

**Ejemplo cURL:**
```bash
curl -X GET http://localhost:3000/api/v2/system/migration
```

**Handlers/Services:** Ninguno

---

### 1.5 GET /api/v2/system/deprecation

**Descripción:** Obtiene información sobre endpoints deprecados de v1.

**Controller:** `SystemController::getDeprecationStatus()`

**Parámetros de entrada:** Ninguno

**Validaciones:** Ninguna

**Respuesta exitosa (200):**
```json
{
  "success": true,
  "data": {
    "v1_endpoints": {
      "status": "removed",
      "removal_date": "2025-09-19",
      "total_deprecated": 0,
      "migration_guide": "/api/v2/docs"
    },
    "migration_info": {
      "progress": "100%",
      "completed_migrations": 27,
      "pending_migrations": 0,
      "migration_complete": true,
      "benefits": [
        "Estructura RESTful completa",
        "Respuestas consistentes",
        "Documentación Swagger integrada",
        "Nomenclatura en español",
        "Manejo de errores mejorado",
        "Validación robusta"
      ]
    },
    "current_status": {
      "API v1": "REMOVIDA",
      "API v2": "ACTIVA",
      "Compatibilidad legacy": "NO REQUERIDA"
    },
    "recommendations": [
      "Usar exclusivamente endpoints /api/v2/*",
      "Consultar documentación en /api/v2/docs",
      "Usar Swagger UI en /api/v2/swagger-ui"
    ]
  }
}
```

**Códigos de error:** Ninguno

**Ejemplo cURL:**
```bash
curl -X GET http://localhost:3000/api/v2/system/deprecation
```

**Handlers/Services:** Ninguno

---

### 1.6 GET /api/v2/system/docs

**Descripción:** Especificación OpenAPI 3.0 completa en formato JSON.

**Controller:** `SystemController::getDocs()`

**Parámetros de entrada:** Ninguno

**Validaciones:** Ninguna

**Respuesta exitosa (200):**
```json
{
  "openapi": "3.0.3",
  "info": {
    "title": "RUND API v2",
    "description": "API RESTful moderna para la gestión de documentos y certificados en RUND",
    "version": "2.0.0",
    "contact": {
      "name": "Oliver Castelblanco Martínez",
      "email": "oliver.castelblanco@esap.edu.co"
    }
  },
  "servers": [
    {
      "url": "http://localhost:3000",
      "description": "Servidor de desarrollo"
    }
  ],
  "paths": { ... }
}
```

**Códigos de error:** Ninguno

**Ejemplo cURL:**
```bash
curl -X GET http://localhost:3000/api/v2/system/docs \
  -H "Accept: application/json"
```

**Handlers/Services:** Ninguno (respuesta estática OpenAPI)

---

### 1.7 GET /api/v2/system/swagger-ui

**Descripción:** Interfaz Swagger UI interactiva para explorar la API.

**Controller:** `SystemController::getSwaggerUI()`

**Parámetros de entrada:** Ninguno

**Validaciones:** Verifica existencia de archivo `/var/www/html/static/swagger-ui.html`

**Respuesta exitosa (200):**
```html
<!DOCTYPE html>
<html>
  <!-- Interfaz Swagger UI completa -->
</html>
```

**Códigos de error:**
- `404`: Archivo swagger-ui.html no encontrado

**Ejemplo cURL:**
```bash
# Abrir en navegador
open http://localhost:3000/api/v2/system/swagger-ui
```

**Handlers/Services:** `readfile()` del HTML estático

---

## 2. Certificados (3 endpoints)

### 2.1 GET /api/v2/certificados/{id}

**Descripción:** Obtiene información específica de un certificado generado previamente.

**Controller:** `CertificadosController::show()`

**Parámetros de entrada:**
- **Path parameters:**
  - `id` (string, requerido): ID único del certificado

**Validaciones:**
- ID es requerido
- ID debe existir en `expedidos.json`

**Respuesta exitosa (200):**
```json
{
  "success": true,
  "data": {
    "certificado": {
      "id": "CERT-20251020-ABC123",
      "plantilla": "1050",
      "data": {
        "cedula": "1234567890",
        "nombre": "Juan Pérez",
        "cargo": "Profesor Asociado"
      },
      "fecha_generacion": "2025-10-20T14:30:00Z",
      "formato": "pdf"
    },
    "id": "CERT-20251020-ABC123",
    "meta": {
      "tipo": "certificado",
      "plantilla": "1050",
      "version": "2.0"
    }
  }
}
```

**Códigos de error:**
- `400`: ID es requerido
- `404`: Certificado no encontrado

**Ejemplo cURL:**
```bash
curl -X GET http://localhost:3000/api/v2/certificados/CERT-20251020-ABC123
```

**Handlers/Services:**
- `CertificadosHandlers::getCertificadoInfo()`
- `OpenKM::findArchivo()` para buscar en `expedidos.json`
- `Utils::buscarPorId()` para localizar el certificado

---

### 2.2 POST /api/v2/certificados/generar

**Descripción:** Genera un nuevo certificado en formato DOCX o PDF a partir de una plantilla.

**Controller:** `CertificadosController::generar()`

**Parámetros de entrada:**
- **Body (JSON o form-data):**
  - `data` (JSON string, requerido): Estructura de datos para rellenar la plantilla
  - `plantilla` (string, requerido): ID de plantilla ("1050", "1051", "1231")
  - `formato` o `tipo` (string, opcional): "docx" o "pdf" (default: "docx")
  - `id` (string, opcional): ID personalizado para evitar duplicados

**Validaciones:**
- Datos de certificado requeridos
- Plantilla debe existir en OpenKM

**Respuesta exitosa (200):**
```
Content-Type: application/pdf (o application/vnd.openxmlformats-officedocument.wordprocessingml.document)
Content-Disposition: attachment; filename="1050.docx"

[Binary file content]
```

**Códigos de error:**
- `400`: Datos requeridos faltantes
- `404`: Plantilla no encontrada
- `500`: Error al generar certificado

**Ejemplo cURL:**
```bash
curl -X POST http://localhost:3000/api/v2/certificados/generar \
  -H "Content-Type: application/json" \
  -d '{
    "plantilla": "1050",
    "formato": "pdf",
    "data": "{\"cedula\":\"1234567890\",\"nombre\":\"Juan Pérez\",\"cargo\":\"Profesor Asociado\"}"
  }' \
  --output certificado.pdf
```

**Handlers/Services:**
- `CertificadosHandlers::getCertificado()`
- `CertificadosService::creaCertificado()` para generar DOCX
- `LBService::convierteWordToPDF()` para conversión a PDF
- `DocumentService::addToJSON()` para registrar en `expedidos.json`
- `Utils::generaID()` para crear ID único

---

### 2.3 GET /api/v2/certificados/plantillas

**Descripción:** Obtiene lista de plantillas de certificados disponibles.

**Controller:** `CertificadosController::getPlantillas()`

**Parámetros de entrada:** Ninguno

**Validaciones:** Ninguna

**Respuesta exitosa (200):**
```json
{
  "success": true,
  "data": {
    "plantillas": [
      {
        "id": "1050",
        "nombre": "Certificación de categorización y evaluación",
        "descripcion": "Certificado estándar para categorización docente",
        "formatos": ["docx", "pdf"]
      },
      {
        "id": "1051",
        "nombre": "Certificación de vinculación",
        "descripcion": "Certificado de vinculación docente",
        "formatos": ["docx", "pdf"]
      },
      {
        "id": "1231",
        "nombre": "Certificación de puntos por bonificación",
        "descripcion": "Certificado de puntos por bonificación académica",
        "formatos": ["docx", "pdf"]
      }
    ],
    "meta": {
      "total": 3,
      "formatos_disponibles": ["docx", "pdf"],
      "version": "2.0"
    }
  }
}
```

**Códigos de error:** Ninguno

**Ejemplo cURL:**
```bash
curl -X GET http://localhost:3000/api/v2/certificados/plantillas
```

**Handlers/Services:** Ninguno (respuesta estática)

---

## 3. Categorías (2 endpoints)

### 3.1 GET /api/v2/categorias/arbol

**Descripción:** Obtiene el árbol completo de categorías académicas en estructura jerárquica.

**Controller:** `CategoriasController::getArbol()`

**Parámetros de entrada:** Ninguno

**Validaciones:** Ninguna

**Respuesta exitosa (200):**
```json
{
  "success": true,
  "data": {
    "arbol": [
      {
        "uuid": "abc-123-def-456",
        "label": "Formación Académica",
        "path": "/okm:categories/RUND/FORMACION_ACADEMICA",
        "children": [
          {
            "uuid": "xyz-789-uvw-012",
            "label": "Pregrado",
            "path": "/okm:categories/RUND/FORMACION_ACADEMICA/PREGRADO",
            "children": []
          }
        ]
      }
    ],
    "total_categorias": 45,
    "meta": {
      "estructura": "jerárquica",
      "formato": "árbol",
      "version": "2.0"
    }
  }
}
```

**Códigos de error:**
- `500`: Error al obtener categorías de OpenKM

**Ejemplo cURL:**
```bash
curl -X GET http://localhost:3000/api/v2/categorias/arbol
```

**Handlers/Services:**
- `CategoriasHandlers::getCategorias()`
- `CategoriasService::getArbolCarpetas()`
- `OpenKM::consulta("repository/getCategoriesFolder")`
- `OpenKM::consulta("folder/getChildren")`

---

### 3.2 GET /api/v2/categorias/cruce/{x}/{y}

**Descripción:** Obtiene el cruce matricial entre dos categorías específicas (ej: Formación vs Experiencia).

**Controller:** `CategoriasController::getCruce()`

**Parámetros de entrada:**
- **Path parameters:**
  - `x` (string, requerido): UUID de la primera categoría (columnas)
  - `y` (string, requerido): UUID de la segunda categoría (filas)

**Validaciones:**
- Parámetros x e y son requeridos
- UUIDs deben existir en OpenKM

**Respuesta exitosa (200):**
```json
{
  "success": true,
  "data": {
    "cruce": {
      "nomCol": "Formación Académica",
      "nomFil": "Experiencia Docente",
      "cols": ["Pregrado", "Especialización", "Maestría", "Doctorado"],
      "filas": [
        {
          "label": "0-5 años",
          "data": [12, 8, 15, 3]
        },
        {
          "label": "6-10 años",
          "data": [5, 12, 20, 8]
        },
        {
          "label": "11+ años",
          "data": [2, 7, 18, 15]
        }
      ]
    },
    "parametros": {
      "categoria_x": "abc-123-def-456",
      "categoria_y": "xyz-789-uvw-012"
    },
    "meta": {
      "tipo": "cruce_categorias",
      "version": "2.0"
    }
  }
}
```

**Códigos de error:**
- `400`: Parámetros x e y son requeridos
- `404`: Categoría no encontrada
- `500`: Error al calcular cruce

**Ejemplo cURL:**
```bash
curl -X GET "http://localhost:3000/api/v2/categorias/cruce/abc-123-def-456/xyz-789-uvw-012"
```

**Handlers/Services:**
- `CategoriasHandlers::getCruce()`
- `CategoriasService::getDocsFromCat()` para obtener documentos por categoría
- `Utils::getCoincidencias()` para calcular intersecciones
- `OpenKM::getPathGeneraLabel()` para nombres de categorías

---

## 4. Profesores (4 endpoints)

### 4.1 GET /api/v2/profesores/{cedula}

**Descripción:** Obtiene información completa del profesor: archivos y datos demográficos.

**Controller:** `ProfesoresController::show()`

**Parámetros de entrada:**
- **Path parameters:**
  - `cedula` (string, requerido): Cédula del profesor (4-20 dígitos)

**Validaciones:**
- Cédula es requerida
- Cédula debe tener entre 4 y 20 dígitos numéricos

**Respuesta exitosa (200):**
```json
{
  "success": true,
  "data": {
    "profesor": {
      "archivosProfesor": [
        {
          "uuid": "file-uuid-123",
          "nombre": "Diploma_Pregrado.pdf",
          "path": "/okm:root/HOJAS_DE_VIDA/1234567890/FORMACION/PREGRADO",
          "categorias": [
            ["TIPO", "DIPLOMA"],
            ["FORMATO", "PDF"],
            ["ORIGEN", "ONEDRIVE"]
          ]
        }
      ],
      "datosDemograficos": {
        "FORMACION_ACADEMICA": ["PREGRADO", "MAESTRIA"],
        "EXPERIENCIA_DOCENTE": ["6-10_AÑOS"],
        "IDIOMAS": ["INGLES_B2", "FRANCES_A1"]
      }
    },
    "cedula": "1234567890",
    "meta": {
      "total_archivos": 15,
      "incluye_demografia": true,
      "version": "2.0"
    }
  }
}
```

**Códigos de error:**
- `400`: Cédula es requerida o formato inválido
- `404`: Profesor no encontrado

**Ejemplo cURL:**
```bash
curl -X GET http://localhost:3000/api/v2/profesores/1234567890
```

**Handlers/Services:**
- `DataHandlers::getInfoProfesor()`
- `DocumentService::getInfoArchivosProfesor()` para archivos
- `DocumentService::estructuraCategorias()` para demografía

---

### 4.2 GET /api/v2/profesores/{cedula}/archivos

**Descripción:** Obtiene solo los archivos del profesor con estadísticas por categoría.

**Controller:** `ProfesoresController::getArchivos()`

**Parámetros de entrada:**
- **Path parameters:**
  - `cedula` (string, requerido): Cédula del profesor (4-20 dígitos)

**Validaciones:**
- Cédula es requerida
- Cédula debe tener formato válido

**Respuesta exitosa (200):**
```json
{
  "success": true,
  "data": {
    "archivos": [
      {
        "uuid": "file-uuid-123",
        "nombre": "Diploma_Pregrado.pdf",
        "path": "/okm:root/HOJAS_DE_VIDA/1234567890/FORMACION/PREGRADO",
        "categorias": [
          ["TIPO", "DIPLOMA"],
          ["FORMATO", "PDF"]
        ]
      }
    ],
    "cedula": "1234567890",
    "estadisticas": {
      "por_tipo": {
        "DIPLOMA": 5,
        "CERTIFICADO": 8,
        "ACTA": 2
      },
      "por_formato": {
        "PDF": 12,
        "DOCX": 3
      },
      "por_origen": {
        "ONEDRIVE": 10,
        "LOCAL": 5
      }
    },
    "meta": {
      "total": 15,
      "endpoint": "archivos",
      "version": "2.0"
    }
  }
}
```

**Códigos de error:**
- `400`: Cédula es requerida o inválida
- `404`: Profesor no encontrado

**Ejemplo cURL:**
```bash
curl -X GET http://localhost:3000/api/v2/profesores/1234567890/archivos
```

**Handlers/Services:**
- `DataHandlers::getInfoProfesor()`
- `calcularEstadisticasArchivos()` (método privado del controller)

---

### 4.3 GET /api/v2/profesores/{cedula}/{nombre_archivo}

**Descripción:** Busca un archivo específico dentro de la carpeta del profesor y retorna su UUID junto con sus propiedades. Este endpoint permite obtener rápidamente el UUID de un archivo conociendo solo su nombre y la cédula del profesor.

**Controller:** `ProfesoresController::getArchivoUuid()`

**Parámetros de entrada:**
- **Path parameters:**
  - `cedula` (string, requerido): Cédula del profesor (4-20 dígitos)
  - `nombre_archivo` (string, requerido): Nombre completo del archivo con extensión

**Validaciones:**
- Cédula es requerida y debe tener entre 4-20 dígitos
- Nombre de archivo es requerido
- El archivo debe existir en la carpeta del profesor en OpenKM

**Respuesta exitosa (200):**
```json
{
  "success": true,
  "uuid": "16acbc5c-4d9d-4152-a39a-9783a1536943",
  "nombre_archivo": "1990_1_ESAP.pdf",
  "cedula": "4080160",
  "propiedades": {
    "path": "/okm:root/RUND/DOCENTES/HOJAS_DE_VIDA/4080160/EXPERIENCIA_INVESTIGATIVA/1990_1_ESAP.pdf",
    "mimeType": "application/pdf",
    "size": 36868,
    "created": "2025-11-20T12:18:15.792-05:00",
    "lastModified": "2025-11-20T12:18:15.792-05:00"
  },
  "meta": {
    "endpoint": "archivo_uuid",
    "version": "2.0"
  }
}
```

**Códigos de error:**
- `400`: Cédula o nombre de archivo faltante, o cédula inválida
- `404`: Archivo no encontrado en la carpeta del profesor
- `500`: Error al buscar el archivo

**Ejemplo cURL:**
```bash
curl -X GET "http://localhost:3000/api/v2/profesores/4080160/1990_1_ESAP.pdf"
```

**Ejemplo JavaScript/TypeScript:**
```typescript
async function obtenerUuidArchivo(cedula: string, nombreArchivo: string): Promise<string> {
  const response = await fetch(
    `/api/v2/profesores/${cedula}/${encodeURIComponent(nombreArchivo)}`
  );

  if (!response.ok) {
    throw new Error('Archivo no encontrado');
  }

  const data = await response.json();
  return data.uuid;
}

// Uso con archivo sin espacios
const uuid1 = await obtenerUuidArchivo('4080160', '1990_1_ESAP.pdf');
console.log('UUID:', uuid1); // 16acbc5c-4d9d-4152-a39a-9783a1536943

// Uso con archivo con espacios y caracteres especiales
const uuid2 = await obtenerUuidArchivo(
  '4080160',
  '1.4.1 DOCTORADO_UNIVERSIDAD_DE_SANTIAGO_DE_COMPOSTELA.pdf'
);
console.log('UUID:', uuid2); // 52a08ebd-7d9b-4c4f-a596-b5392061ffec

// Ahora puedes descargar el archivo usando el endpoint de archivos
const archivoBlob = await fetch(`/api/v2/archivos/${uuid1}`).then(r => r.blob());
```

**Handlers/Services:**
- `OpenKM::findArchivo()` para buscar el archivo por nombre
- `OpenKM::consulta("document/getProperties")` para obtener propiedades

**Ruta de búsqueda en OpenKM:**
- Base: `/okm:root/RUND/DOCENTES/HOJAS_DE_VIDA/{cedula}/`
- Búsqueda recursiva: Sí (encuentra archivos en subcarpetas)

**Características:**
- **Búsqueda recursiva**: Encuentra el archivo incluso si está en subcarpetas de categorías
- **Información completa**: Retorna UUID y propiedades del archivo (path, mimeType, size, fechas)
- **Integración perfecta**: El UUID puede usarse directamente con `GET /api/v2/archivos/{uuid}`
- **Case-sensitive**: El nombre del archivo debe coincidir exactamente

**Casos de uso:**
1. **Descarga directa por nombre**: Obtener UUID y luego descargar el archivo
2. **Verificación de existencia**: Comprobar si un archivo existe en la carpeta del profesor
3. **Obtener metadatos**: Conocer tamaño, fecha de creación, tipo MIME
4. **Integración con sistemas externos**: Facilita el acceso a archivos específicos

**Flujo de trabajo típico:**
```typescript
// 1. Obtener UUID del archivo por nombre
const data = await fetch(
  '/api/v2/profesores/4080160/1990_1_ESAP.pdf'
).then(r => r.json());

const uuid = data.uuid;
const mimeType = data.propiedades.mimeType;
const size = data.propiedades.size;

// 2. Descargar el archivo usando el UUID
const blob = await fetch(`/api/v2/archivos/${uuid}`).then(r => r.blob());

// 3. Usar el archivo (mostrar, descargar, etc.)
const url = URL.createObjectURL(blob);
window.open(url, '_blank');
```

**Notas importantes:**
1. El nombre del archivo debe incluir la extensión completa
2. La búsqueda es sensible a mayúsculas/minúsculas
3. Si hay múltiples archivos con el mismo nombre, retorna el primero encontrado
4. El archivo puede estar en cualquier subcarpeta dentro de la carpeta del profesor
5. **Codificación URL**: Los nombres de archivo con espacios o caracteres especiales deben codificarse con `encodeURIComponent()`. El router decodifica automáticamente los parámetros

---

### 4.4 GET /api/v2/profesores/{cedula}/demografia

**Descripción:** Obtiene solo los datos demográficos del profesor (categorías asignadas).

**Controller:** `ProfesoresController::getDemografia()`

**Parámetros de entrada:**
- **Path parameters:**
  - `cedula` (string, requerido): Cédula del profesor (4-20 dígitos)

**Validaciones:**
- Cédula es requerida
- Cédula debe tener formato válido

**Respuesta exitosa (200):**
```json
{
  "success": true,
  "data": {
    "demografia": {
      "FORMACION_ACADEMICA": ["PREGRADO", "MAESTRIA", "DOCTORADO"],
      "EXPERIENCIA_DOCENTE": ["11+_AÑOS"],
      "IDIOMAS": ["INGLES_C1", "PORTUGUES_B1"],
      "PUBLICACIONES": ["ARTICULOS_INDEXADOS", "LIBROS"],
      "INVESTIGACION": ["GRUPOS_CATEGORIA_A"]
    },
    "cedula": "1234567890",
    "meta": {
      "categorias_disponibles": [
        "FORMACION_ACADEMICA",
        "EXPERIENCIA_DOCENTE",
        "IDIOMAS",
        "PUBLICACIONES",
        "INVESTIGACION"
      ],
      "endpoint": "demografia",
      "version": "2.0"
    }
  }
}
```

**Códigos de error:**
- `400`: Cédula es requerida o inválida
- `404`: Profesor no encontrado

**Ejemplo cURL:**
```bash
curl -X GET http://localhost:3000/api/v2/profesores/1234567890/demografia
```

**Handlers/Services:**
- `DataHandlers::getInfoProfesor()`
- `DocumentService::estructuraCategorias()`

---

## 5. Documentos (3 endpoints)

### 5.1 GET /api/v2/documentos/plantillas

**Descripción:** Obtiene plantillas de documentos disponibles para generación.

**Controller:** `DocumentosController::getPlantillas()`

**Parámetros de entrada:** Ninguno

**Validaciones:** Ninguna

**Respuesta exitosa (200):**
```json
{
  "success": true,
  "data": {
    "plantillas": [
      {
        "id": "reporte_xlsx",
        "nombre": "Reporte Excel",
        "descripcion": "Plantilla para reportes en formato Excel",
        "formato": "xlsx"
      },
      {
        "id": "reporte_pdf",
        "nombre": "Reporte PDF",
        "descripcion": "Plantilla para reportes en formato PDF",
        "formato": "pdf"
      }
    ],
    "meta": {
      "total": 2,
      "formatos": ["xlsx", "pdf"],
      "version": "2.0"
    }
  }
}
```

**Códigos de error:** Ninguno

**Ejemplo cURL:**
```bash
curl -X GET http://localhost:3000/api/v2/documentos/plantillas
```

**Handlers/Services:** Ninguno (respuesta estática)

---

### 5.2 POST /api/v2/documentos/generar

**Descripción:** Genera un documento personalizado (certificados, reportes, consultas) según el tipo.

**Controller:** `DocumentosController::generar()`

**Parámetros de entrada:**
- **Body (JSON o form-data):**
  - `tipo` (string, requerido): Tipo de documento ("certificado", "reporte", "consulta")
  - `data` (JSON string, requerido para reportes): Datos para rellenar el documento
  - `formato` (string, opcional): "xlsx" o "pdf" (default: "xlsx" para reportes)
  - Para certificados: mismo formato que `/api/v2/certificados/generar`

**Validaciones:**
- Parámetro "tipo" es requerido
- Para reportes: "data" es requerido
- JSON debe ser válido

**Respuesta exitosa (200):**
```
Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet
Content-Disposition: attachment; filename="reporte.xls"

[Binary file content]
```

**Códigos de error:**
- `400`: Tipo no especificado, datos faltantes o JSON inválido
- `500`: Error al generar documento

**Ejemplo cURL (Reporte):**
```bash
curl -X POST http://localhost:3000/api/v2/documentos/generar \
  -H "Content-Type: application/json" \
  -d '{
    "tipo": "reporte",
    "formato": "xlsx",
    "data": "{\"cols\":[\"Enero\",\"Febrero\"],\"filas\":[[100,200],[150,250]],\"nomCol\":\"Meses\",\"nomFil\":\"Ventas\"}"
  }' \
  --output reporte.xlsx
```

**Ejemplo cURL (Certificado):**
```bash
curl -X POST http://localhost:3000/api/v2/documentos/generar \
  -H "Content-Type: application/json" \
  -d '{
    "tipo": "certificado",
    "plantilla": "1050",
    "formato": "pdf",
    "data": "{\"cedula\":\"1234567890\",\"nombre\":\"Juan Pérez\"}"
  }' \
  --output certificado.pdf
```

**Handlers/Services:**
- Tipo "certificado": `CertificadosHandlers::getCertificado()`
- Tipo "reporte/consulta": `FileHandlers::getConsultaFile()`
- `ReportesService::generaReporte()` para Excel
- `LBService::convierteExcelToPDF()` para PDF

---

### 5.3 POST /api/v2/documentos/exportar

**Descripción:** Exporta consulta como archivo Excel o PDF.

**Controller:** `DocumentosController::exportar()`

**Parámetros de entrada:**
- **Body (JSON):**
  - `data` (JSON string, requerido): Estructura de datos con cols, filas, nomCol, nomFil
  - `tipo` (string, requerido): "xlsx" o "pdf"

**Validaciones:**
- Parámetros "data" y "tipo" son requeridos
- JSON debe ser válido

**Respuesta exitosa (200):**
```
Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet
Content-Disposition: attachment; filename="reporte.xls"

[Binary file content]
```

**Códigos de error:**
- `400`: Parámetros requeridos faltantes o JSON inválido
- `500`: Error al exportar

**Ejemplo cURL:**
```bash
curl -X POST http://localhost:3000/api/v2/documentos/exportar \
  -H "Content-Type: application/json" \
  -d '{
    "tipo": "xlsx",
    "data": "{\"cols\":[\"Q1\",\"Q2\",\"Q3\",\"Q4\"],\"filas\":[[100,200,150,300]],\"nomCol\":\"Trimestres\",\"nomFil\":\"Ingresos\"}"
  }' \
  --output consulta.xlsx
```

**Handlers/Services:**
- `FileHandlers::getConsultaFile()`
- `ReportesService::generaReporte()`
- `LBService::convierteExcelToPDF()`

---

## 6. Archivos (8 endpoints)

### 6.1 POST /api/v2/archivos/subir

**Descripción:** Sube un archivo al sistema OpenKM (firma o documento).

**Controller:** `ArchivosController::subir()`

**Parámetros de entrada:**
- **Body (multipart/form-data):**
  - `archivo` (file, requerido): Archivo a subir (max 50MB)
  - `accion` (string, requerido): "cargaFirma" o "cargaDocumento"
  - `propiedades` (JSON string, requerido): Propiedades del archivo

**Para cargaFirma:**
```json
{
  "propiedades": "[
    {\"label\":\"cargo\",\"valor\":\"Director\"},
    {\"label\":\"nombres\",\"valor\":\"Juan\"},
    {\"label\":\"apellidos\",\"valor\":\"Pérez\"},
    {\"label\":\"fecha\",\"valor\":\"2025-10-20\"}
  ]"
}
```

**Para cargaDocumento:**
```json
{
  "propiedades": "[
    {\"label\":\"cedula\",\"valor\":\"1234567890\"},
    {\"label\":\"taxonomia\",\"valor\":\"FORMACION/PREGRADO\"},
    {\"label\":\"tipo\",\"valor\":\"DIPLOMA\"},
    {\"label\":\"formato\",\"valor\":\"PDF\"},
    {\"label\":\"origen\",\"valor\":\"ONEDRIVE\"},
    {\"label\":\"esCedula\",\"valor\":false},
    {\"label\":\"categorias\",\"valor\":[\"FORMACION_ACADEMICA/PREGRADO\"]}
  ]"
}
```

**Validaciones:**
- Parámetro "accion" requerido
- Archivo requerido
- Para documentos: cédula debe tener 4-20 dígitos
- Tamaño máximo: 50MB

**Respuesta exitosa (200):**
```json
{
  "success": true,
  "data": {
    "archivo": {
      "carga": {
        "uuid": "uploaded-file-uuid",
        "path": "/okm:root/HOJAS_DE_VIDA/1234567890/FORMACION/PREGRADO/Diploma.pdf"
      },
      "creaTaxonomia": {...},
      "creaCategorias": {...},
      "setProperties": {...}
    },
    "meta": {
      "accion": "cargaDocumento",
      "nombre_original": "Diploma.pdf",
      "tamaño": 2048576,
      "version": "2.0"
    }
  }
}
```

**Códigos de error:**
- `400`: Parámetros faltantes o cédula inválida
- `413`: Archivo muy grande (>50MB)
- `500`: Error al subir archivo

**Ejemplo cURL (Documento):**
```bash
curl -X POST http://localhost:3000/api/v2/archivos/subir \
  -F "archivo=@diploma.pdf" \
  -F "accion=cargaDocumento" \
  -F 'propiedades=[{"label":"cedula","valor":"1234567890"},{"label":"taxonomia","valor":"FORMACION/PREGRADO"},{"label":"tipo","valor":"DIPLOMA"},{"label":"formato","valor":"PDF"},{"label":"origen","valor":"LOCAL"},{"label":"esCedula","valor":false}]'
```

**Ejemplo cURL (Firma):**
```bash
curl -X POST http://localhost:3000/api/v2/archivos/subir \
  -F "archivo=@firma_director.png" \
  -F "accion=cargaFirma" \
  -F 'propiedades=[{"label":"cargo","valor":"Director Académico"},{"label":"nombres","valor":"Juan"},{"label":"apellidos","valor":"Pérez García"},{"label":"fecha","valor":"2025-10-20"}]'
```

**Handlers/Services:**
- `FileHandlers::postFile()`
- `OpenKM::cargaArchivo()` para subir archivo
- `OpenKM::creaCarpetas()` para crear taxonomía
- `CategoriasService::creaCategorias()` para categorías
- `DocumentService::cargaJSON()` para side-car de firmas

---

### 6.2 GET /api/v2/archivos/datos/{nombre}

**Descripción:** Obtiene archivo de datos JSON por nombre.

**Controller:** `ArchivosController::getDatos()`

**Parámetros de entrada:**
- **Path parameters:**
  - `nombre` (string, requerido): Nombre del archivo sin extensión .json

**Validaciones:**
- Nombre es requerido
- Archivo debe existir en OpenKM

**Respuesta exitosa (200):**
```json
{
  "success": true,
  "data": {
    "datos": {
      "key1": "value1",
      "key2": "value2",
      "array": [1, 2, 3]
    },
    "nombre": "expedidos",
    "meta": {
      "tipo": "datos_json",
      "archivo": "expedidos.json",
      "version": "2.0"
    }
  }
}
```

**Códigos de error:**
- `400`: Nombre es requerido
- `404`: Archivo no encontrado

**Ejemplo cURL:**
```bash
curl -X GET http://localhost:3000/api/v2/archivos/datos/expedidos
```

**Handlers/Services:**
- `OpenKM::getDataFile()`

---

### 6.3 GET /api/v2/archivos/imagenes/{nombre}

**Descripción:** Sirve una imagen directamente desde OpenKM. Soporta imágenes de configuración automáticas y rutas específicas.

**Controller:** `ArchivosController::getImagen()`

**Parámetros de entrada:**
- **Path parameters:**
  - `nombre` (string, requerido): Nombre de la imagen con extensión
- **Query parameters:**
  - `ruta` (string, opcional): Ruta específica bajo DOCUMENTOS/ (ej: "PLANTILLAS/CERTIFICADOS")

**Validaciones:**
- Nombre es requerido
- Imágenes de configuración automáticas: logos, íconos, fondos
- Firmas deben usar endpoint `/api/v2/firmas/{uuid}`

**Respuesta exitosa (200):**
```
Content-Type: image/png (o image/jpeg, image/svg+xml)

[Binary image content]
```

**Códigos de error:**
- `400`: Nombre requerido o firma debe usar endpoint específico
- `404`: Imagen no encontrada

**Ejemplo cURL (Imagen de configuración):**
```bash
curl -X GET http://localhost:3000/api/v2/archivos/imagenes/logoESAP.svg \
  --output logo.svg
```

**Ejemplo cURL (Imagen con ruta específica):**
```bash
curl -X GET "http://localhost:3000/api/v2/archivos/imagenes/base.jpg?ruta=PLANTILLAS/CERTIFICADOS" \
  --output base.jpg
```

**Handlers/Services:**
- `OpenKM::getImageFile()`
- `esImagenConfiguracion()` (método privado)
- `esFirma()` (método privado)
- `obtenerRutaLegacy()` (método privado)

---

### 6.4 GET /api/v2/archivos/{uuid}

**Descripción:** Descarga un archivo desde OpenKM por su UUID y lo sirve como binario inline. El archivo se retorna con el Content-Type correcto detectado automáticamente desde OpenKM, permitiendo su visualización en el navegador o conversión a blob en el frontend.

**Controller:** `ArchivosController::show()`

**Parámetros de entrada:**
- **Path parameters:**
  - `uuid` (string, requerido): UUID del archivo en OpenKM

**Validaciones:**
- UUID es requerido
- El archivo debe existir en OpenKM

**Respuesta exitosa (200):**
```
HTTP/1.1 200 OK
Content-Type: application/pdf (o image/png, image/jpeg, application/vnd.openxmlformats-officedocument.wordprocessingml.document, etc.)
Content-Length: 2048576
Content-Disposition: inline; filename="documento.pdf"
Last-Modified: Mon, 20 Oct 2025 14:30:00 GMT
Cache-Control: private, max-age=3600
ETag: "d41d8cd98f00b204e9800998ecf8427e"

[Binary file content]
```

**Respuesta 304 (Not Modified):**
Si el cliente envía un header `If-None-Match` con el ETag actual:
```
HTTP/1.1 304 Not Modified
```

**Códigos de error:**
- `400`: UUID es requerido
- `404`: Archivo no encontrado en OpenKM
- `500`: Error al obtener el archivo

**Ejemplo cURL (Descargar archivo):**
```bash
curl -X GET http://localhost:3000/api/v2/archivos/abc-123-def-456 \
  --output documento.pdf
```

**Ejemplo cURL (Ver headers):**
```bash
curl -I http://localhost:3000/api/v2/archivos/abc-123-def-456
```

**Ejemplo cURL (Con validación de caché):**
```bash
# Primera petición
curl -i http://localhost:3000/api/v2/archivos/abc-123-def-456 \
  --output documento.pdf

# Segunda petición con ETag (retorna 304 si no cambió)
curl -i http://localhost:3000/api/v2/archivos/abc-123-def-456 \
  -H 'If-None-Match: "d41d8cd98f00b204e9800998ecf8427e"'
```

**Uso desde Frontend (TypeScript/JavaScript):**
```typescript
// Obtener archivo como blob
async function descargarArchivo(uuid: string): Promise<Blob> {
  const response = await fetch(`/api/v2/archivos/${uuid}`);

  if (!response.ok) {
    throw new Error('Error al descargar archivo');
  }

  const blob = await response.blob();
  return blob;
}

// Descargar archivo con file-saver
import { saveAs } from 'file-saver';

async function descargarYGuardar(uuid: string, nombreArchivo: string) {
  const blob = await descargarArchivo(uuid);
  saveAs(blob, nombreArchivo);
}

// Mostrar imagen en el DOM
async function mostrarImagen(uuid: string) {
  const blob = await descargarArchivo(uuid);
  const url = URL.createObjectURL(blob);

  const img = document.createElement('img');
  img.src = url;
  document.body.appendChild(img);

  // Liberar memoria cuando ya no se necesite
  // URL.revokeObjectURL(url);
}

// Abrir PDF en nueva pestaña
async function abrirPDF(uuid: string) {
  const blob = await descargarArchivo(uuid);
  const url = URL.createObjectURL(blob);
  window.open(url, '_blank');
}
```

**Uso desde Angular:**
```typescript
import { HttpClient } from '@angular/common/http';
import { Observable } from 'rxjs';

@Injectable({
  providedIn: 'root'
})
export class ArchivosService {
  constructor(private http: HttpClient) {}

  descargarArchivo(uuid: string): Observable<Blob> {
    return this.http.get(`/api/v2/archivos/${uuid}`, {
      responseType: 'blob'
    });
  }

  descargarYGuardar(uuid: string, nombreArchivo: string): void {
    this.descargarArchivo(uuid).subscribe(blob => {
      const url = window.URL.createObjectURL(blob);
      const a = document.createElement('a');
      a.href = url;
      a.download = nombreArchivo;
      a.click();
      window.URL.revokeObjectURL(url);
    });
  }
}
```

**Headers de Respuesta:**

| Header | Descripción | Ejemplo |
|--------|-------------|---------|
| `Content-Type` | MIME type del archivo (auto-detectado) | `application/pdf` |
| `Content-Length` | Tamaño del archivo en bytes | `2048576` |
| `Content-Disposition` | Modo de visualización (inline) y nombre | `inline; filename="documento.pdf"` |
| `Last-Modified` | Fecha de última modificación | `Mon, 20 Oct 2025 14:30:00 GMT` |
| `Cache-Control` | Política de caché | `private, max-age=3600` |
| `ETag` | Identificador de versión para caché | `"d41d8cd98f00b204e9800998ecf8427e"` |

**Handlers/Services:**
- `OpenKM::consulta("document/getProperties")` para obtener MIME type y propiedades
- `OpenKM::getArchivo()` para descargar el contenido binario

**Características:**
- **Auto-detección de Content-Type**: Detecta automáticamente el MIME type desde OpenKM
- **Inline serving**: Sirve archivos inline (no como descarga) para visualización en navegador
- **Caché HTTP**: Soporta validación de caché con ETag y Last-Modified
- **Optimización**: Retorna 304 Not Modified cuando el cliente tiene la versión actual
- **Compatibilidad**: Funciona con todos los tipos de archivo en OpenKM (PDF, DOCX, imágenes, etc.)

**Migración desde v1:**
```typescript
// Antes (v1)
const response = await fetch('/api/v1/getFile?tipo=data&nombre=archivo');

// Ahora (v2)
const response = await fetch(`/api/v2/archivos/${uuid}`);
```

**Notas importantes:**
1. El archivo se sirve inline (no attachment) para permitir visualización en navegador
2. El Content-Type se detecta automáticamente desde OpenKM
3. Incluye headers de caché para optimizar peticiones repetidas
4. Compatible con file-saver, blob URLs y descarga directa
5. Soporta validación HTTP con ETag y If-None-Match

---

### 6.5 POST /api/v2/archivos/{uuid}/actualizar

**Descripción:** Reemplaza un archivo existente con una nueva versión usando el sistema de versionamiento de OpenKM (checkout/checkin). El archivo original se mantiene con el mismo nombre y path, pero su contenido se actualiza y se guarda el comentario en el historial de versiones.

**Controller:** `ArchivosController::update()`

**Método HTTP:** `POST` (se usa POST en lugar de PUT porque PHP no soporta `$_FILES` con PUT)

**Parámetros de entrada:**
- **Path parameters:**
  - `uuid` (string, requerido): UUID del archivo a actualizar
- **Form data (multipart/form-data):**
  - `file` (File, requerido): El nuevo archivo que reemplazará al existente
  - `nombre_archivo` (string, requerido): Nombre del archivo original (debe coincidir)
  - `comment` (string, opcional): Comentario para el historial de versiones. Por defecto: "Actualización YYYY-MM-DD HH:MM:SS"

**Validaciones:**
- UUID es requerido
- El archivo debe ser válido (sin errores de upload)
- El nombre_archivo es requerido
- El documento debe existir en OpenKM

**Flujo de operación:**
1. Valida parámetros (uuid, file, nombre_archivo)
2. Obtiene propiedades del documento original desde OpenKM
3. Hace checkout del documento (lo bloquea para edición)
4. Sube el nuevo contenido
5. Hace checkin con el comentario (guarda nueva versión y desbloquea)
6. Obtiene propiedades actualizadas del documento
7. Retorna respuesta con información completa

**Respuesta exitosa (200):**
```json
{
  "success": true,
  "uuid": "16acbc5c-4d9d-4152-a39a-9783a1536943",
  "nombre_archivo": "1990_1_ESAP.pdf",
  "comentario": "Actualización de documento con versión de alta calidad",
  "version": "1.2",
  "propiedades": {
    "path": "/okm:root/RUND/DOCENTES/HOJAS_DE_VIDA/4080160/EXPERIENCIA_INVESTIGATIVA/1990_1_ESAP.pdf",
    "mimeType": "application/pdf",
    "size": 2048576,
    "created": "2025-11-20T12:18:15.792-05:00",
    "lastModified": "2025-12-02T17:46:02.079-05:00",
    "versionLabel": "1.2",
    "author": "okmAdmin"
  },
  "meta": {
    "endpoint": "archivo_update",
    "operacion": "reemplazar_version",
    "version": "2.0",
    "timestamp": "2025-12-02T17:46:03-05:00"
  }
}
```

**Códigos de error:**
- `400`: UUID faltante, archivo faltante, o nombre_archivo faltante
- `404`: Documento no encontrado en OpenKM
- `500`: Error al hacer checkout/checkin o al procesar actualización

**Ejemplo cURL:**
```bash
curl -X POST "http://localhost:3000/api/v2/archivos/16acbc5c-4d9d-4152-a39a-9783a1536943/actualizar" \
  -F "file=@documento_actualizado.pdf" \
  -F "nombre_archivo=1990_1_ESAP.pdf" \
  -F "comment=Reemplazar con versión de alta calidad"
```

**Ejemplo JavaScript/TypeScript:**
```typescript
async function actualizarArchivo(
  uuid: string,
  archivo: File,
  nombreArchivo: string,
  comentario?: string
): Promise<any> {
  const formData = new FormData();
  formData.append('file', archivo);
  formData.append('nombre_archivo', nombreArchivo);
  if (comentario) {
    formData.append('comment', comentario);
  }

  const response = await fetch(
    `/api/v2/archivos/${uuid}/actualizar`,
    {
      method: 'POST',
      body: formData
    }
  );

  if (!response.ok) {
    throw new Error('Error al actualizar archivo');
  }

  return await response.json();
}

// Uso
const archivoInput = document.getElementById('fileInput') as HTMLInputElement;
const archivo = archivoInput.files[0];

const resultado = await actualizarArchivo(
  '16acbc5c-4d9d-4152-a39a-9783a1536943',
  archivo,
  '1990_1_ESAP.pdf',
  'Reemplazar con versión escaneada en alta resolución'
);

console.log('Nueva versión:', resultado.version);
console.log('Fecha de modificación:', resultado.propiedades.lastModified);
```

**Uso desde Angular:**
```typescript
import { HttpClient } from '@angular/common/http';
import { Observable } from 'rxjs';

@Injectable({
  providedIn: 'root'
})
export class ArchivosService {
  constructor(private http: HttpClient) {}

  actualizarArchivo(
    uuid: string,
    archivo: File,
    nombreArchivo: string,
    comentario?: string
  ): Observable<any> {
    const formData = new FormData();
    formData.append('file', archivo);
    formData.append('nombre_archivo', nombreArchivo);
    if (comentario) {
      formData.append('comment', comentario);
    }

    return this.http.post(
      `/api/v2/archivos/${uuid}/actualizar`,
      formData
    );
  }
}

// Componente
export class DocumentoComponent {
  constructor(private archivosService: ArchivosService) {}

  onFileSelected(event: any, uuid: string, nombreArchivo: string) {
    const archivo: File = event.target.files[0];

    this.archivosService
      .actualizarArchivo(uuid, archivo, nombreArchivo, 'Actualización manual')
      .subscribe({
        next: (resultado) => {
          console.log('Archivo actualizado:', resultado);
          this.mostrarMensaje('Archivo actualizado exitosamente');
        },
        error: (error) => {
          console.error('Error:', error);
          this.mostrarError('Error al actualizar archivo');
        }
      });
  }
}
```

**Handlers/Services:**
- `OpenKM::consulta("document/getProperties")` para obtener path original
- `OpenKM::nuevaVersion()` que internamente ejecuta:
  - `OpenKM::consulta("document/checkout")` para bloquear el documento
  - `OpenKM::consulta("document/isCheckedOut")` para verificar el checkout
  - `OpenKM::documentAction("checkin")` para subir nueva versión y desbloquear

**Características:**
- **Versionamiento automático**: Usa el sistema de versiones de OpenKM (checkout/checkin)
- **Historial completo**: El comentario se guarda en el historial de versiones
- **Sin cambio de nombre**: Mantiene el mismo nombre y path del archivo original
- **Reemplazo de tipo MIME**: Permite reemplazar un JPG con un PDF, por ejemplo
- **Sin validación de tamaño**: No valida tamaño (se podría agregar en el futuro)
- **Bloqueo automático**: El documento se bloquea durante la actualización

**Casos de uso:**
1. **Mejorar calidad**: Reemplazar un escaneo de baja calidad con uno de alta calidad
2. **Cambiar formato**: Sustituir una imagen JPG por un PDF vectorial
3. **Corregir errores**: Actualizar un documento que tenía errores o información incompleta
4. **Actualizar información**: Renovar certificados o documentos con fechas de vencimiento

**Flujo de trabajo típico:**
```typescript
// 1. Usuario selecciona archivo existente y sube uno nuevo
const uuidExistente = '16acbc5c-4d9d-4152-a39a-9783a1536943';
const nombreOriginal = '1990_1_ESAP.pdf';
const archivoNuevo = inputFile.files[0]; // Archivo del <input type="file">

// 2. Actualizar con comentario descriptivo
const resultado = await actualizarArchivo(
  uuidExistente,
  archivoNuevo,
  nombreOriginal,
  'Reemplazar escaneo de baja calidad con PDF de alta resolución'
);

// 3. Verificar el resultado
console.log('Versión anterior → nueva:', resultado.version);
console.log('Tamaño actualizado:', resultado.propiedades.size, 'bytes');
console.log('Última modificación:', resultado.propiedades.lastModified);

// 4. Opcionalmente, recargar el documento en la interfaz
const blob = await fetch(`/api/v2/archivos/${uuidExistente}`).then(r => r.blob());
mostrarDocumentoEnVisor(blob);
```

**Notas importantes:**
1. Se usa POST en lugar de PUT por limitaciones de PHP con $_FILES
2. El nombre del archivo debe coincidir con el original para mantener la consistencia
3. OpenKM mantiene un historial completo de versiones con comentarios
4. El documento se bloquea automáticamente durante la actualización (checkout)
5. No hay validación de tipo MIME, permitiendo cambios de formato (JPG → PDF)
6. El autor de la nueva versión será el usuario configurado en OpenKM (típicamente okmAdmin)

---

### 6.6 DELETE /api/v2/archivos/{uuid}

**Descripción:** Elimina un archivo por UUID de OpenKM.

**Controller:** `ArchivosController::delete()`

**Parámetros de entrada:**
- **Path parameters:**
  - `uuid` (string, requerido): UUID del archivo en OpenKM

**Validaciones:**
- UUID es requerido
- Archivo debe existir

**Respuesta exitosa (200):**
```json
{
  "success": true,
  "data": {
    "eliminado": true,
    "uuid": "abc-123-def-456",
    "resultado": {
      "status": "deleted"
    },
    "meta": {
      "operacion": "eliminar",
      "version": "2.0"
    }
  }
}
```

**Códigos de error:**
- `400`: UUID es requerido
- `404`: Archivo no encontrado
- `500`: Error al eliminar

**Ejemplo cURL:**
```bash
curl -X DELETE http://localhost:3000/api/v2/archivos/abc-123-def-456
```

**Handlers/Services:**
- `OpenKM::borraArchivo()`

---

### 6.6 DELETE /api/v2/archivos/temp/limpiar

**Descripción:** Limpia archivos temporales de reportes (reporte.xlsx, reporte.pdf).

**Controller:** `ArchivosController::limpiarTemp()`

**Parámetros de entrada:** Ninguno

**Validaciones:** Ninguna

**Respuesta exitosa (200):**
```json
{
  "success": true,
  "data": {
    "limpieza": {
      "borrados": ["reporte.xlsx", "reporte.pdf"],
      "aBorrar": ["reporte.xlsx", "reporte.pdf"]
    },
    "meta": {
      "operacion": "limpiar_temporales",
      "version": "2.0"
    }
  }
}
```

**Códigos de error:** Ninguno (siempre retorna 200)

**Ejemplo cURL:**
```bash
curl -X DELETE http://localhost:3000/api/v2/archivos/temp/limpiar
```

**Handlers/Services:**
- `FileHandlers::deleteReport()`
- `unlink()` para borrar archivos temporales

---

### 6.7 DELETE /api/v2/archivos/papelera

**Descripción:** Vacía completamente la papelera de OpenKM.

**Controller:** `ArchivosController::vaciarPapelera()`

**Parámetros de entrada:** Ninguno

**Validaciones:** Ninguna

**Respuesta exitosa (200):**
```json
{
  "success": true,
  "data": {
    "resultado": {
      "status": "purged",
      "items_deleted": 25
    },
    "mensaje": "Papelera de OpenKM vaciada exitosamente",
    "meta": {
      "operacion": "vaciar_papelera",
      "sistema": "OpenKM",
      "version": "2.0",
      "timestamp": "2025-10-20T14:30:00+00:00"
    }
  }
}
```

**Códigos de error:**
- `500`: Error al vaciar la papelera

**Ejemplo cURL:**
```bash
curl -X DELETE http://localhost:3000/api/v2/archivos/papelera
```

**Handlers/Services:**
- `OpenKM::borraPapelera()`

---

## 7. Listados (4 endpoints)

### 7.1 POST /api/v2/listados/cargar

**Descripción:** Carga y procesa un listado Excel/CSV de administración de profesores.

**Controller:** `ListadosController::cargar()`

**Parámetros de entrada:**
- **Body (multipart/form-data):**
  - `archivo` (file, requerido): Archivo Excel o CSV
  - `accion` (string, requerido): "cargar" o "duplicado"
  - `propiedades` (JSON string, requerido): Propiedades del listado

**Propiedades requeridas:**
```json
{
  "propiedades": "[
    {\"label\":\"Nombre\",\"valor\":\"listado_profesores_2025.xlsx\"},
    {\"label\":\"Tipo\",\"valor\":\"Administración\"},
    {\"label\":\"Origen\",\"valor\":\"OneDrive\"},
    {\"label\":\"Formato\",\"valor\":\"Excel\"},
    {\"label\":\"Size\",\"valor\":204800},
    {\"label\":\"Duplicado\",\"valor\":false}
  ]"
}
```

**Validaciones:**
- Acción y propiedades son requeridos
- Para "cargar": archivo es requerido
- Propiedades debe ser JSON válido

**Respuesta exitosa (200) - Cargar:**
```json
{
  "success": true,
  "data": {
    "listado": {
      "carga": {
        "uuid": "listado-uuid-123",
        "path": "/okm:root/DOCUMENTOS/LISTADOS/ADMINISTRACION/listado_profesores_2025.xlsx"
      },
      "creaCategorias": {...},
      "respCategorias": {...}
    },
    "meta": {
      "accion": "cargar",
      "archivo": "listado_profesores_2025.xlsx",
      "metodo": "POST",
      "version": "2.0"
    }
  }
}
```

**Respuesta exitosa (200) - Duplicado:**
```json
{
  "success": true,
  "data": {
    "listado": {
      "duplicado": {
        "nombre": true,
        "ruta": true,
        "size": true,
        "creado": "2025-10-15T10:30:00Z",
        "uuid": "existing-uuid-456"
      }
    },
    "meta": {
      "accion": "duplicado",
      "archivo": null,
      "metodo": "GET",
      "version": "2.0"
    }
  }
}
```

**Códigos de error:**
- `400`: Datos o parámetros faltantes
- `413`: Archivo muy grande
- `500`: Error al cargar

**Ejemplo cURL (Cargar):**
```bash
curl -X POST http://localhost:3000/api/v2/listados/cargar \
  -F "archivo=@listado_profesores.xlsx" \
  -F "accion=cargar" \
  -F 'propiedades=[{"label":"Nombre","valor":"listado_profesores.xlsx"},{"label":"Tipo","valor":"Administración"},{"label":"Origen","valor":"Local"},{"label":"Formato","valor":"Excel"},{"label":"Size","valor":204800},{"label":"Duplicado","valor":false}]'
```

**Ejemplo cURL (Verificar duplicado):**
```bash
curl -X POST http://localhost:3000/api/v2/listados/cargar \
  -F "accion=duplicado" \
  -F 'propiedades=[{"label":"Nombre","valor":"listado_profesores.xlsx"},{"label":"Tipo","valor":"Administración"},{"label":"Origen","valor":"Local"},{"label":"Formato","valor":"Excel"},{"label":"Size","valor":204800}]'
```

**Handlers/Services:**
- `FileHandlers::loadList()`
- `OpenKM::cargaArchivo()` para subir archivo
- `CategoriasService::creaCategorias()` para categorizar
- `OpenKM::consulta("document/setProperties")` para propiedades

---

### 7.2 GET /api/v2/listados/datos

**Descripción:** Obtiene datos de listados procesados o verifica duplicados. Soporta consultas CSV y acciones con propiedades.

**Controller:** `ListadosController::getDatos()`

**Parámetros de entrada:**
- **Query parameters (Acción con propiedades):**
  - `accion` (string): "duplicado"
  - `propiedades` (JSON string): Propiedades del listado

- **Query parameters (CSV):**
  - `categoria` (string): Categoría del CSV
  - `tipo` (string): Tipo de CSV
  - `nombre` (string): Nombre del archivo
  - `extension` (string): Extensión (.csv)

**Validaciones:**
- Para acciones: accion y propiedades requeridos
- Para CSV: categoria, tipo, nombre, extension requeridos

**Respuesta exitosa (200) - Acción:**
```json
{
  "success": true,
  "data": {
    "datos": {
      "duplicado": {
        "nombre": false,
        "ruta": false,
        "size": false,
        "creado": false,
        "uuid": ""
      }
    },
    "parametros": {
      "accion": "duplicado",
      "propiedades": "[...]"
    },
    "meta": {
      "tipo": "accion_listado",
      "accion": "duplicado",
      "version": "2.0"
    }
  }
}
```

**Respuesta exitosa (200) - CSV:**
```json
{
  "success": true,
  "data": {
    "datos": {
      "arrayCSV": [
        ["Cedula", "Nombre", "Categoria"],
        ["1234567890", "Juan Pérez", "Asociado"],
        ["9876543210", "María García", "Titular"]
      ],
      "columnasCSV": [
        {"Cedula": ["1234567890", "9876543210"]},
        {"Nombre": ["Juan Pérez", "María García"]},
        {"Categoria": ["Asociado", "Titular"]}
      ],
      "rawCSV": "Cedula,Nombre,Categoria\n1234567890,Juan Pérez,Asociado\n..."
    },
    "parametros": {
      "categoria": "LISTADOS",
      "tipo": "PROFESORES",
      "nombre": "datos_2025",
      "extension": ".csv"
    },
    "meta": {
      "tipo": "datos_csv",
      "version": "2.0"
    }
  }
}
```

**Códigos de error:**
- `400`: Parámetros requeridos faltantes
- `404`: Archivo CSV no encontrado
- `500`: Error al procesar

**Ejemplo cURL (Verificar duplicado):**
```bash
curl -X GET "http://localhost:3000/api/v2/listados/datos?accion=duplicado&propiedades=%5B%7B%22label%22%3A%22Nombre%22%2C%22valor%22%3A%22listado.xlsx%22%7D%5D"
```

**Ejemplo cURL (Obtener CSV):**
```bash
curl -X GET "http://localhost:3000/api/v2/listados/datos?categoria=LISTADOS&tipo=PROFESORES&nombre=datos_2025&extension=.csv"
```

**Handlers/Services:**
- `FileHandlers::loadList()` para acciones
- `DataHandlers::getCsvData()` para CSV
- `Utils::csvToJsonByColumns()` para conversión

---

### 7.3 GET /api/v2/listados/csv

**Descripción:** Obtiene datos CSV específicos con metadatos adicionales.

**Controller:** `ListadosController::getCsv()`

**Parámetros de entrada:**
- **Query parameters:**
  - `categoria` (string, requerido): Categoría del CSV
  - `tipo` (string, requerido): Tipo de CSV
  - `nombre` (string, requerido): Nombre del archivo sin extensión
  - `extension` (string, requerido): Extensión del archivo (.csv)

**Validaciones:**
- Todos los parámetros son requeridos
- Archivo debe existir en OpenKM

**Respuesta exitosa (200):**
```json
{
  "success": true,
  "data": {
    "csv": {
      "arrayCSV": [
        ["Cedula", "Nombre", "Email", "Categoria"],
        ["1234567890", "Juan Pérez", "juan@esap.edu.co", "Asociado"],
        ["9876543210", "María García", "maria@esap.edu.co", "Titular"]
      ],
      "columnasCSV": [
        {
          "Cedula": ["1234567890", "9876543210"]
        },
        {
          "Nombre": ["Juan Pérez", "María García"]
        },
        {
          "Email": ["juan@esap.edu.co", "maria@esap.edu.co"]
        },
        {
          "Categoria": ["Asociado", "Titular"]
        }
      ],
      "rawCSV": "Cedula,Nombre,Email,Categoria\n1234567890,Juan Pérez,juan@esap.edu.co,Asociado\n..."
    },
    "parametros": {
      "categoria": "LISTADOS",
      "tipo": "PROFESORES",
      "nombre": "profesores_activos",
      "extension": ".csv"
    },
    "meta": {
      "formato": "csv",
      "filas": 2,
      "columnas": 4,
      "version": "2.0"
    }
  }
}
```

**Códigos de error:**
- `400`: Faltan parámetros requeridos
- `404`: CSV no encontrado
- `500`: Error al procesar CSV

**Ejemplo cURL:**
```bash
curl -X GET "http://localhost:3000/api/v2/listados/csv?categoria=LISTADOS&tipo=PROFESORES&nombre=profesores_activos&extension=.csv"
```

**Handlers/Services:**
- `DataHandlers::getCsvData()`
- `OpenKM::consulta("search/find")` para buscar archivo
- `OpenKM::consulta("document/getContent")` para obtener contenido
- `Utils::csvToJsonByColumns()` para conversión

---

### 7.4 GET /api/v2/listados/indice

**Descripción:** Obtiene el índice docente completo en formato JSON. El índice es generado automáticamente al cargar `ListadoGeneralDocente.csv` y proporciona acceso rápido a la información de todos los profesores.

**Controller:** `ListadosController::getIndice()`

**Parámetros de entrada:** Ninguno

**Validaciones:** Ninguna

**Respuesta exitosa (200):**
```json
{
  "success": true,
  "data": {
    "indice": {
      "479678": {
        "DOCUMENTO_DE_IDENTIDAD": "479678",
        "VINCULACION": "Ocasional",
        "NOMBRE_Y_APELLIDO": "ABEL ANTONIO ABELLA BELTRAN",
        "TERRITORIAL": "META",
        "CATEGORIA": "Asociado",
        "NUCLEO_TEMATICO": "Labores de docencia...",
        "NIVEL_DE_FORMACION": "Maestría",
        "PERFIL_ACADEMICO": "Administrador Publico...",
        "PREGRADO": "Administrador Publico",
        "ESPECIALIZACION": "Especialista en Proyectos...",
        "MAESTRIA": "Magister en Paz Desarrollo...",
        "DOCTORADO": "N/A",
        "POSDOCTORADO": "N/A",
        "INVESTIGACION_2024": "N/A",
        "ORIGEN_DE_VINCULACION": "Parágrafo 2...",
        "ACTO_ADMINISTRATIVO_DE_VINCULACION": "Resolución DT-11-010...",
        "CORREO_INSTITUCIONAL": "abelabel@esap.edu.co",
        "CORREO_PERSONAL": "abelantonio98@gmail.com",
        "TELEFONO": "6671750",
        "ULTIMA_EVALUACION": "Excelente 2024-1",
        "DEDICACION": "Tiempo Completo",
        "SITUACION_ADMINISTRATIVA": "No Aplica",
        "INICIO_DE_VINCULACION": "8/2/2024",
        "FIN_DE_VINCULACION": "20/12/2024",
        "PUNTAJE_SALARIAL": "351.85"
      },
      "5711867": {
        "DOCUMENTO_DE_IDENTIDAD": "5711867",
        "NOMBRE_Y_APELLIDO": "ADRIANA MARCELA OSORIO LOPEZ",
        ...
      }
    },
    "meta": {
      "total_docentes": 256,
      "estructura": "objeto plano con cédulas como claves",
      "uuid": "9dc980df-40a3-42b7-a4c8-3d2e231e9aa0",
      "version": "2.0"
    }
  }
}
```

**Códigos de error:**
- `404`: Índice docente no existe (debe cargar primero `ListadoGeneralDocente.csv`)
- `500`: Error al obtener el índice

**Ejemplo cURL:**
```bash
curl -X GET http://localhost:3000/api/v2/listados/indice
```

**Handlers/Services:**
- `FileHandlers::getIndiceDocente()`
- `OpenKM::findArchivo()` para buscar archivo
- `OpenKM::getArchivo()` para obtener contenido

**Ubicación en OpenKM:**
- Ruta: `/okm:root/RUND/DOCUMENTOS/LISTADOS/INDICE_DOCENTE/indice_docente.json`

**Características:**
- Estructura plana con cédulas como claves para búsqueda O(1)
- Generación automática al cargar `ListadoGeneralDocente.csv`
- Merge inteligente que preserva datos existentes en actualizaciones
- Versionado automático en OpenKM

**Uso desde el Frontend:**
```typescript
// Obtener índice completo
const response = await fetch('/api/v2/listados/indice');
const { data } = await response.json();
const indice = data.indice;

// Búsqueda rápida por cédula (O(1))
const docente = indice['479678'];

// Generar opciones para autocomplete
const opciones = Object.entries(indice).map(([cedula, datos]) => ({
  value: cedula,
  label: `${cedula} - ${datos.NOMBRE_Y_APELLIDO}`
}));
```

**Documentación relacionada:**
- Ver [11_INDICE_DOCENTE.md](11_INDICE_DOCENTE.md) para documentación completa del índice docente
- Ver [12_ENDPOINT_INDICE_DOCENTE.md](12_ENDPOINT_INDICE_DOCENTE.md) para ejemplos de uso detallados

---

## 8. Firmas (3 endpoints)

### 8.1 GET /api/v2/firmas/lista

**Descripción:** Obtiene lista completa de firmas disponibles en OpenKM.

**Controller:** `FirmasController::getLista()`

**Parámetros de entrada:**
- **Query parameters (opcionales):**
  - `uuid` (string): UUID de firma específica
  - `mimeType` (string): Tipo MIME (ej: "image/png")

**Validaciones:**
- Si se proporciona uuid, mimeType es requerido

**Respuesta exitosa (200) - Lista completa:**
```json
[
  {
    "uuid": "firma-uuid-123",
    "nombre": "firma_director.png",
    "cargo": "Director Académico",
    "nombres": "Juan",
    "apellidos": "Pérez García",
    "fecha": "2025-10-20",
    "mimeType": "image/png",
    "path": "/okm:root/DOCUMENTOS/FIRMAS/firma_director.png"
  },
  {
    "uuid": "firma-uuid-456",
    "nombre": "firma_secretaria.png",
    "cargo": "Secretaria General",
    "nombres": "María",
    "apellidos": "González López",
    "fecha": "2025-10-15",
    "mimeType": "image/png",
    "path": "/okm:root/DOCUMENTOS/FIRMAS/firma_secretaria.png"
  }
]
```

**Respuesta exitosa (200) - Firma específica:**
```
Content-Type: image/png

[Binary image content]
```

**Códigos de error:**
- `404`: Firma no encontrada
- `500`: Error al obtener firmas

**Ejemplo cURL (Lista completa):**
```bash
curl -X GET http://localhost:3000/api/v2/firmas/lista
```

**Ejemplo cURL (Firma específica):**
```bash
curl -X GET "http://localhost:3000/api/v2/firmas/lista?uuid=firma-uuid-123&mimeType=image/png" \
  --output firma.png
```

**Handlers/Services:**
- `FirmasHandlers::getFirmas()`
- `FirmasService::getFirmas()` para listar
- `OpenKM::getArchivo()` para descargar

---

### 8.2 GET /api/v2/firmas/{uuid}

**Descripción:** Obtiene una firma específica por UUID (endpoint en construcción).

**Controller:** `FirmasController::show()`

**Parámetros de entrada:**
- **Path parameters:**
  - `uuid` (string, requerido): UUID de la firma

**Validaciones:**
- UUID es requerido

**Respuesta exitosa (501):**
```json
{
  "success": false,
  "error": "Endpoint en construcción - usar /api/v1/getFirmas por ahora",
  "codigo": 501
}
```

**Códigos de error:**
- `400`: UUID es requerido
- `501`: Endpoint no implementado

**Ejemplo cURL:**
```bash
curl -X GET http://localhost:3000/api/v2/firmas/firma-uuid-123
```

**Handlers/Services:** Ninguno (endpoint en construcción)

---

### 8.3 POST /api/v2/firmas/subir

**Descripción:** Sube una nueva firma al sistema (PNG + JSON side-car).

**Controller:** `FirmasController::subir()`

**Parámetros de entrada:**
- **Body (multipart/form-data):**
  - `archivo` (file, requerido): Imagen PNG de la firma
  - `accion` (string, requerido): "cargaFirma"
  - `propiedades` (JSON string, requerido): Datos de la firma

**Propiedades requeridas:**
```json
{
  "propiedades": "[
    {\"label\":\"cargo\",\"valor\":\"Director Académico\"},
    {\"label\":\"nombres\",\"valor\":\"Juan\"},
    {\"label\":\"apellidos\",\"valor\":\"Pérez García\"},
    {\"label\":\"fecha\",\"valor\":\"2025-10-20\"}
  ]"
}
```

**Validaciones:**
- Archivo de firma requerido
- Parámetro "accion" requerido
- Propiedades debe ser JSON válido
- Formato debe ser PNG

**Respuesta exitosa (200):**
```json
{
  "success": true,
  "data": {
    "resultado": {
      "creaCategorias": [...],
      "cargaPNG": {
        "uuid": "firma-png-uuid-789",
        "path": "/okm:root/DOCUMENTOS/FIRMAS/firma_director.png"
      },
      "respCategoriasPNG": {...},
      "cargaJSON": {
        "uuid": "firma-json-uuid-012",
        "path": "/okm:root/DOCUMENTOS/FIRMAS/firma_director.json"
      },
      "respCategoriasJSON": {...}
    },
    "mensaje": "Firma subida exitosamente",
    "meta": {
      "endpoint": "v2",
      "tipo": "firma",
      "version": "2.0"
    }
  }
}
```

**Códigos de error:**
- `400`: Parámetros requeridos faltantes
- `413`: Archivo muy grande
- `500`: Error al subir firma

**Ejemplo cURL:**
```bash
curl -X POST http://localhost:3000/api/v2/firmas/subir \
  -F "archivo=@firma_director.png" \
  -F "accion=cargaFirma" \
  -F 'propiedades=[{"label":"cargo","valor":"Director Académico"},{"label":"nombres","valor":"Juan"},{"label":"apellidos","valor":"Pérez García"},{"label":"fecha","valor":"2025-10-20"}]'
```

**Handlers/Services:**
- `FileHandlers::postFile()`
- `OpenKM::cargaArchivo()` para PNG
- `DocumentService::cargaJSON()` para side-car JSON
- `CategoriasService::creaCategorias()` para categorizar

---

## 9. AI (1 endpoint)

### 9.1 POST /api/v2/ai/extraer

**Descripción:** Extrae datos de documentos usando IA y OCR (servicio rund-ocr + rund-ai).

**Controller:** `AIController::extraer()`

**Parámetros de entrada:**
- **Body (multipart/form-data):**
  - `documento` (file, requerido): Archivo a analizar (PDF, DOCX, imagen) - Max 50MB
  - `accion` (string, requerido): "documento"
  - `tipoDocumento` (string, requerido): Tipo de documento a analizar
  - `datosExtraer` (JSON string, requerido): Lista de campos a extraer

**Ejemplo de datosExtraer:**
```json
{
  "datosExtraer": "[
    \"nombre\",
    \"cedula\",
    \"fecha_expedicion\",
    \"lugar_expedicion\"
  ]"
}
```

**Validaciones:**
- Archivo "documento" requerido
- Parámetros "accion", "tipoDocumento", "datosExtraer" requeridos
- Tamaño máximo: 50MB
- Formato soportado: PDF, DOCX, PNG, JPG

**Respuesta exitosa (200):**
```json
{
  "success": true,
  "data": {
    "extraccion": {
      "texto_extraido": "REPÚBLICA DE COLOMBIA\nCÉDULA DE CIUDADANÍA\nNombre: JUAN CARLOS PÉREZ GARCÍA\nNo. 1234567890\nExpedida: Bogotá D.C.\nFecha: 15/03/1985",
      "datos": {
        "nombre": "JUAN CARLOS PÉREZ GARCÍA",
        "cedula": "1234567890",
        "fecha_expedicion": "15/03/1985",
        "lugar_expedicion": "Bogotá D.C."
      },
      "confianza": 0.95,
      "modelo_usado": "phi3:mini",
      "ocr_usado": "PaddleOCR"
    },
    "documento": "cedula_123.pdf",
    "meta": {
      "accion": "extraer",
      "tipo_documento": "cedula",
      "tamaño_archivo": 2048576,
      "version": "2.0"
    }
  }
}
```

**Códigos de error:**
- `400`: Parámetros faltantes o formato no soportado
- `413`: Archivo muy grande (>50MB)
- `500`: Error al procesar documento
- `503`: Servicio AI/OCR no disponible
- `504`: Timeout del servicio (>60s)

**Ejemplo cURL:**
```bash
curl -X POST http://localhost:3000/api/v2/ai/extraer \
  -F "documento=@cedula.pdf" \
  -F "accion=documento" \
  -F "tipoDocumento=cedula" \
  -F 'datosExtraer=["nombre","cedula","fecha_expedicion","lugar_expedicion"]'
```

**Handlers/Services:**
- `AIHandlers::extraeDatos()`
- `AIService::analizaDocumento()` para procesamiento
- Servicio `rund-ocr` (http://rund-ocr:8000) para OCR con PaddleOCR
- Servicio `rund-ai` (http://rund-ai:11434) para análisis con Ollama (phi3:mini)

**Notas importantes:**
- Timeout: 60 segundos
- El documento se copia temporalmente a `Config::TEMP_DIR`
- Soporta español e inglés
- Modelo AI: phi3:mini (auto-descargado en primera ejecución)

---

## Tabla Resumen de Todos los Endpoints

| # | Método | Endpoint | Controller | Handler/Service | Descripción |
|---|--------|----------|------------|-----------------|-------------|
| **SISTEMA** |
| 1 | GET | `/api/v2/system/info` | SystemController::getInfo | - | Información del sistema |
| 2 | GET | `/api/v2/system/health` | SystemController::getHealth | shell_exec | Health check |
| 3 | GET | `/api/v2/system/capabilities` | SystemController::getCapabilities | - | Capacidades de la API |
| 4 | GET | `/api/v2/system/migration` | SystemController::getMigrationStatus | - | Estado de migración v1→v2 |
| 5 | GET | `/api/v2/system/deprecation` | SystemController::getDeprecationStatus | - | Endpoints deprecados |
| 6 | GET | `/api/v2/system/docs` | SystemController::getDocs | - | Especificación OpenAPI JSON |
| 7 | GET | `/api/v2/system/swagger-ui` | SystemController::getSwaggerUI | readfile | Interfaz Swagger UI |
| **CERTIFICADOS** |
| 8 | GET | `/api/v2/certificados/{id}` | CertificadosController::show | CertificadosHandlers::getCertificadoInfo | Obtener certificado por ID |
| 9 | POST | `/api/v2/certificados/generar` | CertificadosController::generar | CertificadosHandlers::getCertificado | Generar certificado DOCX/PDF |
| 10 | GET | `/api/v2/certificados/plantillas` | CertificadosController::getPlantillas | - | Listar plantillas disponibles |
| **CATEGORÍAS** |
| 11 | GET | `/api/v2/categorias/arbol` | CategoriasController::getArbol | CategoriasHandlers::getCategorias | Árbol de categorías |
| 12 | GET | `/api/v2/categorias/cruce/{x}/{y}` | CategoriasController::getCruce | CategoriasHandlers::getCruce | Cruce matricial de categorías |
| **PROFESORES** |
| 13 | GET | `/api/v2/profesores/{cedula}` | ProfesoresController::show | DataHandlers::getInfoProfesor | Información completa del profesor |
| 14 | GET | `/api/v2/profesores/{cedula}/archivos` | ProfesoresController::getArchivos | DataHandlers::getInfoProfesor | Solo archivos del profesor |
| 15 | GET | `/api/v2/profesores/{cedula}/{nombre_archivo}` | ProfesoresController::getArchivoUuid | OpenKM::findArchivo | Obtener UUID de archivo por nombre |
| 16 | GET | `/api/v2/profesores/{cedula}/demografia` | ProfesoresController::getDemografia | DataHandlers::getInfoProfesor | Solo datos demográficos |
| **DOCUMENTOS** |
| 17 | GET | `/api/v2/documentos/plantillas` | DocumentosController::getPlantillas | - | Plantillas de documentos |
| 18 | POST | `/api/v2/documentos/generar` | DocumentosController::generar | FileHandlers::getConsultaFile | Generar documento personalizado |
| 19 | POST | `/api/v2/documentos/exportar` | DocumentosController::exportar | FileHandlers::getConsultaFile | Exportar consulta XLSX/PDF |
| **ARCHIVOS** |
| 20 | POST | `/api/v2/archivos/subir` | ArchivosController::subir | FileHandlers::postFile | Subir archivo (firma/documento) |
| 21 | GET | `/api/v2/archivos/datos/{nombre}` | ArchivosController::getDatos | OpenKM::getDataFile | Obtener archivo JSON |
| 22 | GET | `/api/v2/archivos/imagenes/{nombre}` | ArchivosController::getImagen | OpenKM::getImageFile | Obtener imagen |
| 23 | GET | `/api/v2/archivos/{uuid}` | ArchivosController::show | OpenKM::getArchivo | Descargar archivo por UUID (inline) |
| 24 | POST | `/api/v2/archivos/{uuid}/actualizar` | ArchivosController::update | OpenKM::nuevaVersion | Actualizar archivo (nueva versión) |
| 25 | DELETE | `/api/v2/archivos/{uuid}` | ArchivosController::delete | OpenKM::borraArchivo | Eliminar archivo |
| 26 | DELETE | `/api/v2/archivos/temp/limpiar` | ArchivosController::limpiarTemp | FileHandlers::deleteReport | Limpiar archivos temporales |
| 27 | DELETE | `/api/v2/archivos/papelera` | ArchivosController::vaciarPapelera | OpenKM::borraPapelera | Vaciar papelera OpenKM |
| **LISTADOS** |
| 28 | POST | `/api/v2/listados/cargar` | ListadosController::cargar | FileHandlers::loadList | Cargar listado Excel/CSV |
| 29 | GET | `/api/v2/listados/datos` | ListadosController::getDatos | DataHandlers::getCsvData | Obtener datos de listados |
| 30 | GET | `/api/v2/listados/csv` | ListadosController::getCsv | DataHandlers::getCsvData | Obtener CSV específico |
| 31 | GET | `/api/v2/listados/indice` | ListadosController::getIndice | FileHandlers::getIndiceDocente | Obtener índice docente completo |
| **FIRMAS** |
| 32 | GET | `/api/v2/firmas/lista` | FirmasController::getLista | FirmasHandlers::getFirmas | Listar firmas disponibles |
| 33 | GET | `/api/v2/firmas/{uuid}` | FirmasController::show | - | Obtener firma por UUID (501) |
| 34 | POST | `/api/v2/firmas/subir` | FirmasController::subir | FileHandlers::postFile | Subir nueva firma |
| **AI** |
| 35 | POST | `/api/v2/ai/extraer` | AIController::extraer | AIHandlers::extraeDatos | Extraer datos con IA/OCR |

---

## Convenciones y Formatos

### Formato de Respuesta Estándar

**Éxito:**
```json
{
  "success": true,
  "data": { ... }
}
```

**Error:**
```json
{
  "success": false,
  "error": "Mensaje de error descriptivo",
  "codigo": 400
}
```

### Códigos HTTP Utilizados

- `200`: Operación exitosa
- `400`: Petición incorrecta (parámetros faltantes/inválidos)
- `404`: Recurso no encontrado
- `413`: Archivo muy grande (>50MB)
- `500`: Error interno del servidor
- `501`: Endpoint no implementado
- `503`: Servicio no disponible
- `504`: Timeout del servicio

### Límites y Restricciones

- **Tamaño máximo de archivo:** 50MB
- **Formatos soportados:** PDF, DOCX, XLSX, CSV, PNG, JPG, SVG
- **Rate limit:** 100 requests/minute
- **Timeout AI/OCR:** 60 segundos
- **Cédula:** 4-20 dígitos numéricos

### Servicios Externos

| Servicio | URL Interna | Propósito |
|----------|-------------|-----------|
| OpenKM | http://rund-core:8080 | Repositorio de documentos |
| AI (Ollama) | http://rund-ai:11434 | Análisis con phi3:mini |
| OCR (PaddleOCR) | http://rund-ocr:8000 | Extracción de texto |

---

## Notas de Desarrollo

1. **PSR-4 Autoloading:** Namespace `RUND\Controllers\V2`
2. **Base Controller:** Todos los controllers extienden `BaseController`
3. **Handlers:** Capa intermedia entre controllers y services
4. **Services:** Lógica de negocio específica
5. **OpenKM:** Core de almacenamiento de documentos
6. **Middleware:** Validación y autenticación centralizada

---

**Documentación generada:** 2025-10-20
**Versión API:** 2.0
**Autor:** Oliver Castelblanco Martínez
**Email:** oliver.castelblanco@esap.edu.co
