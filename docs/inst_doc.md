# Instrucciones para Claude Code: Análisis Técnico de RUND-API

## Contexto
Necesito que analices el proyecto RUND-API refactorizado para generar información técnica detallada que será utilizada para crear documentación oficial del proyecto.

## Objetivos del Análisis

### 1. ESTRUCTURA Y ARQUITECTURA
Analiza y documenta:
- Estructura completa de directorios (`app/src/` con Controllers, Services, Models, Utils, Config)
- Listado de TODOS los archivos PHP principales con su propósito
- Diagrama de dependencias entre componentes (Controllers → Services → Models)
- Patrón arquitectónico implementado (MVC, capas, etc.)

**Formato de salida:**
```markdown
## Estructura del Proyecto
[Árbol de directorios completo]

## Componentes Principales
### Controllers
- Archivo: nombre.php
  - Propósito: [breve descripción]
  - Endpoints que maneja: [lista]
  
### Services
- Archivo: nombre.php
  - Propósito: [breve descripción]
  - Métodos principales: [lista]
```

---

### 2. ENDPOINTS IMPLEMENTADOS
Para CADA uno de los 27 endpoints mencionados en README_API.md, extrae:
- Ruta exacta
- Método HTTP
- Archivo del Controller que lo implementa
- Parámetros de entrada (path, query, body)
- Formato de respuesta (estructura JSON)
- Códigos de estado HTTP que puede retornar
- Validaciones implementadas
- Servicios/modelos que utiliza

**Formato de salida:**
```markdown
## Endpoint: GET /api/v2/system/info
- **Controller:** `SystemController.php`
- **Método:** `getInfo()`
- **Parámetros:** Ninguno
- **Respuesta exitosa (200):**
  ```json
  {
    "datos": { ... },
    "meta": { ... }
  }
  ```
- **Errores posibles:** 500
- **Validaciones:** [lista]
- **Dependencias:** [Services/Models utilizados]
```

---

### 3. SERVICIOS Y LÓGICA DE NEGOCIO
Para cada archivo en `app/src/Services/`:
- Nombre del servicio
- Propósito y responsabilidad
- Métodos públicos con sus firmas
- Dependencias externas (OpenKM, librerías, etc.)
- Lógica de negocio clave implementada

**Formato de salida:**
```markdown
## Servicio: DocumentosService
- **Archivo:** `app/src/Services/DocumentosService.php`
- **Propósito:** [descripción]
- **Métodos públicos:**
  - `generarDocumento($tipo, $datos)`: [descripción]
  - `subirDocumento($archivo)`: [descripción]
- **Dependencias:**
  - PHPOffice para procesamiento DOCX
  - OpenKM para almacenamiento
- **Lógica clave:**
  - [puntos importantes]
```

---

### 4. MODELOS DE DATOS
Para cada archivo en `app/src/Models/`:
- Nombre del modelo
- Propósito
- Propiedades/atributos
- Métodos de validación o transformación
- Relaciones con otros modelos

---

### 5. CONFIGURACIÓN Y UTILIDADES
Analiza `app/src/Config/` y `app/src/Utils/`:
- Archivos de configuración y su propósito
- Variables de entorno utilizadas
- Clases de utilidad y sus funciones principales

**Formato de salida:**
```markdown
## Configuración
### Variables de Entorno (.env)
- `OPENKM_HOST`: [propósito]
- `OPENKM_PORT`: [propósito]
[etc.]

## Utilidades
### Clase: ValidacionUtil
- `validarCedula($cedula)`: [descripción]
- `sanitizarInput($input)`: [descripción]
```

---

### 6. INTEGRACIÓN CON OPENKM
Documenta específicamente:
- Cómo se conecta con OpenKM (clase, método)
- Operaciones implementadas (subir, descargar, eliminar, etc.)
- Estructura de carpetas en OpenKM que gestiona
- Manejo de errores de conexión

---

### 7. MANEJO DE ERRORES Y VALIDACIONES
Analiza:
- Sistema de manejo de errores implementado
- Validaciones de entrada en endpoints
- Mensajes de error estandarizados
- Logging (si existe)

**Formato de salida:**
```markdown
## Manejo de Errores
- **Estrategia:** [descripción]
- **Validaciones comunes:**
  - Validación de tipos
  - Validación de rangos
  - Sanitización de inputs
- **Formato de respuesta de error:**
  ```json
  {
    "datos": null,
    "meta": {
      "status": 400,
      "mensaje": "...",
      "errores": [...]
    }
  }
  ```
```

---

### 8. DEPENDENCIAS Y LIBRERÍAS
Lista TODAS las dependencias de `composer.json`:
- Nombre de la librería
- Versión
- Propósito en el proyecto
- Dónde se utiliza principalmente

---

### 9. SEGURIDAD IMPLEMENTADA
Busca e identifica:
- Validación de inputs (dónde y cómo)
- Sanitización de datos
- Headers de seguridad configurados
- Manejo de archivos (validación de tipos, tamaños)
- Protección contra inyecciones
- Cualquier mecanismo de autenticación/autorización presente

---

### 10. FLUJOS CRÍTICOS
Documenta el flujo completo de:
1. **Generación de un certificado:** Desde que llega la petición hasta que se retorna el PDF
2. **Subida de un archivo:** Validación → Procesamiento → Almacenamiento en OpenKM
3. **Consulta de información de profesor:** Request → Validación → Búsqueda → Respuesta

---

## Instrucciones Generales
- **Sé exhaustivo:** Necesito TODA la información técnica disponible
- **Sé preciso:** Usa nombres exactos de archivos, clases y métodos
- **Incluye código:** Cuando sea relevante, incluye fragmentos de código clave con comentarios
- **Identifica gaps:** Si encuentras código sin documentar o implementaciones incompletas, señálalas
- **Prioriza claridad:** La información será usada por personal técnico de OTIC

## Formato de Entrega
Genera un documento markdown estructurado con:
1. Índice al inicio
2. Secciones claramente delimitadas
3. Código formateado con syntax highlighting
4. Tablas donde sea apropiado
5. Diagramas en formato Mermaid si es posible

---

**IMPORTANTE:** Este análisis es para documentación oficial de la ESAP. La precisión y completitud son críticas.
