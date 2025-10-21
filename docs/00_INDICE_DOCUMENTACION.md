# Documentación Técnica RUND-API v2.0

## 📚 Índice General de Documentación

**Proyecto:** RUND API - Sistema de Gestión Documental
**Institución:** ESAP (Escuela Superior de Administración Pública)
**Versión:** 2.0
**Fecha:** Octubre 2025
**Autor Técnico:** Oliver Castelblanco Martínez

---

## 📖 Documentos Disponibles

### 1. **01_ARQUITECTURA_Y_ESTRUCTURA.md**
   - Estructura completa del proyecto
   - Árbol de directorios y archivos
   - Patrón arquitectónico (MVC con capas)
   - Componentes principales (Controllers, Services, Handlers, Core)
   - Flujo de ejecución de requests
   - PSR-4 Autoloading

### 2. **02_ENDPOINTS_API.md**
   - Documentación completa de los 27 endpoints
   - Métodos HTTP, rutas, parámetros
   - Formato de respuestas y códigos de estado
   - Ejemplos de uso con cURL
   - Validaciones implementadas
   - Compatibilidad y aliases

### 3. **03_SERVICIOS_Y_HANDLERS.md**
   - Todos los Services (8 archivos)
   - Todos los Handlers (6 archivos)
   - Métodos públicos con firmas completas
   - Lógica de negocio implementada
   - Dependencias entre componentes

### 4. **04_INTEGRACION_OPENKM.md**
   - Clase OpenKM.php completa
   - Operaciones CRUD en repositorio
   - Sistema de versionado (checkout/checkin)
   - Estructura de carpetas y categorías
   - Manejo de archivos y metadatos
   - Queries y respuestas típicas

### 5. **05_DEPENDENCIAS_Y_LIBRERIAS.md**
   - Análisis de composer.json
   - PHPOffice (PhpWord, PhpSpreadsheet)
   - Endroid QR Code
   - LibreOffice headless
   - Extensiones PHP requeridas

### 6. **06_FLUJOS_CRITICOS.md**
   - Generación de certificados (paso a paso)
   - Subida de archivos a OpenKM
   - Consulta de información de profesores
   - Diagramas de flujo en Mermaid
   - Archivos temporales y limpieza

### 7. **07_SEGURIDAD_Y_VALIDACIONES.md**
   - Middleware de autenticación/autorización
   - Validaciones de input
   - Sanitización de datos
   - Headers de seguridad (CORS)
   - Manejo de archivos
   - Protección contra inyecciones
   - Vulnerabilidades identificadas
   - Recomendaciones de producción

### 8. **08_MANEJO_DE_ERRORES.md**
   - Estrategia global de errores
   - Formato de respuestas de error
   - Códigos de estado HTTP
   - Logging y debugging
   - Try-catch patterns

### 9. **09_CONFIGURACION_Y_UTILIDADES.md**
   - Config.php (constantes y rutas)
   - Utils.php (utilidades globales)
   - Variables de entorno
   - Router y sistema de rutas
   - BaseController

### 10. **10_INTEGRACION_SERVICIOS_EXTERNOS.md**
   - rund-ai (Ollama con Mistral)
   - rund-ocr (PaddleOCR)
   - AIService completo
   - Extracción de datos con IA
   - Tipos de documentos soportados

---

## 🎯 Guías Rápidas

### Para Desarrolladores Nuevos
1. Leer: `01_ARQUITECTURA_Y_ESTRUCTURA.md`
2. Leer: `02_ENDPOINTS_API.md`
3. Revisar: `09_CONFIGURACION_Y_UTILIDADES.md`

### Para Integración Frontend
1. Leer: `02_ENDPOINTS_API.md`
2. Revisar: `08_MANEJO_DE_ERRORES.md` (formatos de respuesta)
3. Consultar: `07_SEGURIDAD_Y_VALIDACIONES.md` (CORS)

### Para DevOps/Deployment
1. Leer: `05_DEPENDENCIAS_Y_LIBRERIAS.md`
2. Revisar: `09_CONFIGURACION_Y_UTILIDADES.md`
3. Consultar: `07_SEGURIDAD_Y_VALIDACIONES.md` (producción)

### Para Auditoría de Seguridad
1. Leer: `07_SEGURIDAD_Y_VALIDACIONES.md`
2. Revisar: `04_INTEGRACION_OPENKM.md` (credenciales)
3. Consultar: `08_MANEJO_DE_ERRORES.md`

---

## 📊 Resumen Ejecutivo

### Tecnologías Principales
- **Backend:** PHP 8.3+ con strict types
- **Arquitectura:** RESTful API con patrón MVC en capas
- **Storage:** OpenKM (repositorio documental Java/Tomcat)
- **Librerías:** PHPOffice (Word/Excel), Endroid QR, LibreOffice
- **Servicios externos:** rund-ai (IA), rund-ocr (OCR)

### Estadísticas del Proyecto
- **Endpoints totales:** 27
- **Controllers:** 9 (8 de V2 + 1 Base)
- **Services:** 8
- **Handlers:** 6
- **Middleware:** 3
- **Core utilities:** 3 (Router, OpenKM, Utils)
- **Líneas de código:** ~8,000+ líneas PHP
- **Dependencias:** 6 principales + 30+ indirectas

### Estado Actual
- ✅ **Migración v1 → v2:** 100% completada
- ✅ **Endpoints funcionales:** 25 de 27 (2 en construcción)
- ✅ **Documentación API:** Swagger/OpenAPI integrado
- ⚠️ **Autenticación:** Preparada pero no activa
- ⚠️ **Testing:** Configurado pero sin tests implementados
- ⚠️ **Producción:** Requiere hardening de seguridad

---

## 🔗 Enlaces Útiles

- **Repositorio:** (URL del repositorio Git)
- **Swagger UI:** `http://localhost:3000/api/v2/system/swagger-ui`
- **OpenAPI JSON:** `http://localhost:3000/api/v2/system/docs`
- **Health Check:** `http://localhost:3000/api/v2/system/health`

---

## 📝 Notas de Uso

Esta documentación fue generada mediante análisis exhaustivo del código fuente en octubre de 2025. Incluye:
- ✅ Firmas completas de métodos con tipos
- ✅ Números de línea de código relevante
- ✅ Diagramas de flujo en Mermaid
- ✅ Ejemplos de código y payloads
- ✅ Recomendaciones de seguridad y mejores prácticas

**Mantenimiento:** Se recomienda actualizar esta documentación ante cambios significativos en la arquitectura, endpoints o dependencias del proyecto.

---

**Generado por:** Claude Code (Anthropic)
**Fecha de análisis:** 2025-10-20
