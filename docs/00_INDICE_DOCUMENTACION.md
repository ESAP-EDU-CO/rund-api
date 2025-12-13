# Documentación Técnica RUND-API v2.1

## 📚 Índice General de Documentación

**Proyecto:** RUND API - Sistema de Gestión Documental
**Institución:** ESAP (Escuela Superior de Administración Pública)
**Versión:** 2.1
**Fecha:** Diciembre 2025
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
   - Documentación completa de los 33 endpoints
   - Métodos HTTP, rutas, parámetros
   - Formato de respuestas y códigos de estado
   - Ejemplos de uso con cURL
   - Validaciones implementadas
   - Compatibilidad y aliases
   - **NUEVO:** Endpoints de autenticación (/api/v2/auth/*)

### 3. **03_SERVICIOS_Y_HANDLERS.md**
   - Todos los Services (10 archivos)
   - Todos los Handlers (6 archivos)
   - Métodos públicos con firmas completas
   - Lógica de negocio implementada
   - Dependencias entre componentes
   - **NUEVO:** AuthService y JWTValidator

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
   - **ACTUALIZADO:** Sistema de autenticación con JWT RS256
   - **ACTUALIZADO:** Middleware de autenticación implementado
   - Validación de JWT con JWKS público
   - Sesiones PHP con timeout (8 horas)
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
   - **NUEVO:** rund-auth (Autenticación centralizada)
   - rund-ai (Ollama con NuExtract y Gemma2)
   - rund-ocr (PaddleOCR)
   - AIService completo
   - Extracción de datos con IA
   - Tipos de documentos soportados

### 11. **11_INDICE_DOCENTE.md**
   - Sistema de índice de documentos por profesor
   - Estructura de datos y metadatos
   - Integración con OpenKM

### 12. **12_ENDPOINT_INDICE_DOCENTE.md**
   - Endpoint específico para índice docente
   - Casos de uso y ejemplos

### 13. **AUTENTICACION.md** ⭐ NUEVO
   - Implementación completa del sistema de autenticación
   - Arquitectura BFF (Backend-for-Frontend)
   - AuthService, JWTValidator, AuthController, AuthMiddleware
   - Endpoints de autenticación
   - Flujos de login, sesión, logout, refresh
   - Guía de integración con frontend
   - Testing y troubleshooting

---

## 🎯 Guías Rápidas

### Para Desarrolladores Nuevos
1. Leer: `01_ARQUITECTURA_Y_ESTRUCTURA.md`
2. Leer: `AUTENTICACION.md` ⭐ NUEVO
3. Leer: `02_ENDPOINTS_API.md`
4. Revisar: `09_CONFIGURACION_Y_UTILIDADES.md`

### Para Integración Frontend
1. **Leer primero:** `AUTENTICACION.md` (implementación de auth) ⭐
2. Leer: `02_ENDPOINTS_API.md`
3. Revisar: `08_MANEJO_DE_ERRORES.md` (formatos de respuesta)
4. Consultar: `07_SEGURIDAD_Y_VALIDACIONES.md` (CORS y JWT)

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
- **Autenticación:** JWT RS256 con rund-auth (BFF pattern) ⭐ NUEVO
- **Storage:** OpenKM (repositorio documental Java/Tomcat)
- **Librerías:** PHPOffice (Word/Excel), Endroid QR, LibreOffice
- **Servicios externos:** rund-auth (Auth), rund-ai (IA), rund-ocr (OCR)

### Estadísticas del Proyecto
- **Endpoints totales:** 33 (+6 de autenticación) ⭐
- **Controllers:** 10 (+AuthController) ⭐
- **Services:** 10 (+AuthService, JWTValidator) ⭐
- **Handlers:** 6
- **Middleware:** 3 (AuthMiddleware actualizado) ⭐
- **Core utilities:** 3 (Router, OpenKM, Utils)
- **Líneas de código:** ~10,000+ líneas PHP (+2,000 de auth) ⭐
- **Dependencias:** 6 principales + 30+ indirectas

### Estado Actual (v2.1)
- ✅ **Migración v1 → v2:** 100% completada
- ✅ **Endpoints funcionales:** 31 de 33 (2 en construcción)
- ✅ **Documentación API:** Swagger/OpenAPI integrado
- ✅ **Autenticación:** Implementada y funcional ⭐ NUEVO
- ✅ **JWT RS256:** Validación con JWKS público ⭐ NUEVO
- ✅ **Sesiones PHP:** Con timeout de 8 horas ⭐ NUEVO
- ⚠️ **Testing:** Configurado pero sin tests implementados
- ⚠️ **Producción:** Requiere configuración HTTPS y credenciales LDAP reales

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

**Última actualización:** Diciembre 13, 2025
**Versión documentación:** 2.1
**Cambios principales:**
- ⭐ Sistema de autenticación completo (AUTENTICACION.md)
- ⭐ 6 nuevos endpoints de autenticación
- ⭐ AuthService, JWTValidator, AuthController
- ⭐ AuthMiddleware con validación JWT RS256 real
- ⭐ Integración con rund-auth como BFF

**Generado por:** Claude Code (Anthropic)
**Fecha de análisis original:** 2025-10-20
