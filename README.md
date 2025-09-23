# RUND API

> API backend para el sistema RUND (Registro Único Nacional Docente) de la ESAP

[![PHP](https://img.shields.io/badge/PHP-8.3+-blue.svg)](https://php.net)
[![Composer](https://img.shields.io/badge/Composer-2.0+-orange.svg)](https://getcomposer.org)
[![Docker](https://img.shields.io/badge/Docker-Ready-green.svg)](https://docker.com)

## 🚀 Descripción

RUND-API es el servicio backend del RUND de la ESAP. Proporciona una API RESTful moderna para la generación de certificados, manejo de archivos, integración con OpenKM y servicios de inteligencia artificial.

## 📋 Características

- **🔧 API RESTful v2** - 27 endpoints completamente funcionales
- **📄 Generación de documentos** - Certificados en DOCX y PDF
- **🗃️ Integración OpenKM** - Gestión de repositorio documental
- **🤖 Inteligencia Artificial** - Extracción de datos con IA
- **📊 Procesamiento de archivos** - Excel, Word, PDF, imágenes
- **✅ Validación robusta** - Manejo de errores consistente
- **📚 Documentación Swagger** - API autodocumentada
- **🐳 Docker Ready** - Contenedorización completa

## 🛠️ Tecnologías

| Tecnología | Versión | Propósito |
|------------|---------|-----------|
| **PHP** | 8.3+ | Backend principal |
| **Composer** | 2.0+ | Gestión de dependencias |
| **PHPOffice** | 4.4+ | Procesamiento de documentos |
| **LibreOffice (headless)** | 25.8+ | Conversión DOCX a PDF |
| **Endroid QR** | 6.0+ | Códigos QR |
| **Docker** | Latest | Contenedorización |
| **Nginx** | Latest | Servidor web |

## 📦 Instalación

### Desarrollo Local

```bash
# Clonar repositorio
git clone [URL-REPO] rund-api
cd rund-api

# Instalar dependencias
composer install

# Configurar ambiente
cp .env.example .env

# Ejecutar con Docker
docker compose up -d
```

### Producción

```bash
# Usar imagen de Docker Hub
docker pull ocastelblanco/rund-api:latest

# O construir localmente
docker build -t rund-api .
```

## 🔧 Configuración

### Variables de Entorno

```bash
# OpenKM Configuration
OPENKM_HOST=rund-core
OPENKM_PORT=8080
OPENKM_USER=****
OPENKM_PASS=****

# AI Service
AI_SERVICE_URL=http://rund-ai:11434

# OCR Service
OCR_SERVICE_URL=http://rund-ocr:8000

# File Limits
MAX_FILE_SIZE=52428800  # 50MB
ALLOWED_EXTENSIONS=pdf,docx,xlsx,jpg,jpeg,png

# Logging
LOG_LEVEL=info
LOG_PATH=/var/www/html/logs
```

## 📡 API Endpoints

### 🏛️ Sistema
```http
GET    /api/v2/system/info           # Información del sistema
GET    /api/v2/system/health         # Estado de salud
GET    /api/v2/system/capabilities   # Capacidades disponibles
GET    /api/v2/system/docs          # Documentación OpenAPI
GET    /api/v2/system/swagger-ui    # Interfaz Swagger
```

### 📄 Certificados
```http
GET    /api/v2/certificados/{id}           # Información de certificado
POST   /api/v2/certificados/generar        # Generar certificado
GET    /api/v2/certificados/plantillas     # Plantillas disponibles
```

### 📂 Categorías
```http
GET    /api/v2/categorias/arbol         # Árbol de categorías
GET    /api/v2/categorias/cruce/{x}/{y} # Cruce de categorías
```

### 👥 Profesores
```http
GET    /api/v2/profesores/{cedula}            # Información de profesor
GET    /api/v2/profesores/{cedula}/archivos   # Archivos del profesor
GET    /api/v2/profesores/{cedula}/demografia # Datos demográficos
```

### 📑 Documentos
```http
GET    /api/v2/documentos/plantillas    # Plantillas de documentos
POST   /api/v2/documentos/generar       # Generar documento
POST   /api/v2/documentos/exportar      # Exportar consulta
```

### 📁 Archivos
```http
POST   /api/v2/archivos/subir               # Subir archivo
GET    /api/v2/archivos/datos/{nombre}      # Datos estructurados
GET    /api/v2/archivos/imagenes/{nombre}   # Imágenes
GET    /api/v2/archivos/{uuid}              # Archivo por UUID
DELETE /api/v2/archivos/{uuid}              # Eliminar archivo
DELETE /api/v2/archivos/temp/limpiar        # Limpiar temporales
DELETE /api/v2/archivos/papelera            # Vaciar papelera OpenKM
```

### 📋 Listados
```http
POST   /api/v2/listados/cargar    # Cargar listado
GET    /api/v2/listados/datos     # Datos de listados
GET    /api/v2/listados/csv       # Exportar CSV
```

### ✍️ Firmas
```http
GET    /api/v2/firmas/lista     # Lista de firmas
GET    /api/v2/firmas/{uuid}    # Firma por UUID
POST   /api/v2/firmas/subir     # Subir firma
```

### 🤖 Inteligencia Artificial
```http
POST   /api/v2/ai/extraer    # Extraer datos con IA
```

## 📖 Ejemplos de Uso

### Generar Certificado

```bash
curl -X POST http://localhost:3000/api/v2/documentos/generar \
  -H "Content-Type: application/json" \
  -d '{
    "tipo": "certificado",
    "formato": "docx",
    "plantilla": "1050",
    "data": "[{\"tipo\": \"parrafo\", \"value\": \"Certificado de participación\"}]"
  }'
```

### Obtener Información de Profesor

```bash
curl http://localhost:3000/api/v2/profesores/12345678
```

### Subir Archivo

```bash
curl -X POST http://localhost:3000/api/v2/archivos/subir \
  -F "file=@documento.pdf" \
  -F "descripcion=Documento importante"
```

### Extraer Datos con IA

```bash
curl -X POST http://localhost:3000/api/v2/ai/extraer \
  -H "Content-Type: application/json" \
  -d '{
    "archivo": "documento.pdf",
    "tipo": "certificado",
    "campos": ["nombre", "fecha", "programa"]
  }'
```

## 🏗️ Arquitectura

```
rund-api/
├── app/
│   ├── src/                  # Código fuente PSR-4
│   │   ├── Controllers/      # Controladores v2
│   │   ├── Services/         # Servicios de negocio
│   │   ├── Models/          # Modelos de datos
│   │   ├── Utils/           # Utilidades
│   │   └── Config/          # Configuración
│   ├── routes_v2.php        # Rutas API v2
│   ├── index.php           # Punto de entrada
│   └── bootstrap.php       # Inicialización
├── docker/                 # Configuración Docker
├── logs/                  # Archivos de log
├── tmp/                   # Archivos temporales
├── composer.json          # Dependencias PHP
├── Dockerfile            # Imagen Docker
└── nginx-fix.conf        # Configuración Nginx
```

## 🐳 Docker

### Desarrollo

```yaml
# docker-compose.yml
services:
  rund-api:
    build: .
    ports:
      - "3000:3000"
    volumes:
      - ./app:/var/www/html
    environment:
      - OPENKM_HOST=rund-core
      - AI_SERVICE_URL=http://rund-ai:11434
    networks:
      - rund-network
```

### Producción

```bash
# Construir imagen
docker build -t ocastelblanco/rund-api:v1.0.0 .

# Ejecutar contenedor
docker run -d \
  --name rund-api \
  -p 3000:3000 \
  --network rund-network \
  mundotecnologico/rund-api:v1.0.0
```

## 🧪 Testing

```bash
# Ejecutar tests unitarios
composer test

# Con PHPUnit directamente
./vendor/bin/phpunit tests/

# Test específico
./vendor/bin/phpunit tests/Controllers/DocumentosControllerTest.php
```

## 📊 Monitoreo

### Health Check

```bash
# Estado del servicio
curl http://localhost:3000/api/v2/system/health

# Información del sistema
curl http://localhost:3000/api/v2/system/info
```

### Logs

```bash
# Ver logs en tiempo real
docker logs -f rund-api

# Logs específicos
tail -f logs/api.log
tail -f logs/errors.log
```

## 🔐 Seguridad

- ✅ **Validación de entrada** - Todos los parámetros validados
- ✅ **Límites de archivo** - Máximo 50MB por archivo
- ✅ **Tipos permitidos** - Solo formatos seguros
- ✅ **Sanitización** - Limpieza de datos de entrada
- ✅ **Headers seguros** - CORS y headers de seguridad
- ✅ **Rate limiting** - Protección contra abuso

## 🚀 Desarrollo

### Añadir Nuevo Endpoint

1. **Crear controlador** en `app/src/Controllers/`
2. **Definir servicio** en `app/src/Services/`
3. **Agregar ruta** en `app/routes_v2.php`
4. **Documentar** en comentarios del controlador
5. **Probar** con tests unitarios

### Estándares de Código

```bash
# PSR-4 Autoloading
composer dump-autoload

# Verificar sintaxis
find app/src -name "*.php" -exec php -l {} \;

# Formatear código (si se instala PHP-CS-Fixer)
./vendor/bin/php-cs-fixer fix app/src
```

## 📚 Documentación

- **Swagger UI**: `http://localhost:3000/api/v2/system/swagger-ui`
- **OpenAPI JSON**: `http://localhost:3000/api/v2/system/docs`
- **Endpoints**: Documentados en código con comentarios
- **Ejemplos**: Incluidos en este README

## 🤝 Contribución

1. Fork el proyecto
2. Crear branch feature (`git checkout -b feature/nueva-funcionalidad`)
3. Commit cambios (`git commit -am 'Añadir nueva funcionalidad'`)
4. Push al branch (`git push origin feature/nueva-funcionalidad`)
5. Crear Pull Request

### Estándares

- ✅ **PSR-4** para autoloading
- ✅ **PSR-12** para estilo de código
- ✅ **Tests unitarios** para nuevas funciones
- ✅ **Documentación** en código y README
- ✅ **Validación** de entrada en todos los endpoints

## 🔧 Mantenimiento

### Limpieza Automática

```bash
# Limpiar archivos temporales
curl -X DELETE http://localhost:3000/api/v2/archivos/temp/limpiar

# Vaciar papelera OpenKM
curl -X DELETE http://localhost:3000/api/v2/archivos/papelera

# Limpiar logs antiguos
docker exec rund-api find /var/www/html/logs -name "*.log" -mtime +30 -delete
```

### Backup

```bash
# Backup del código
tar -czf rund-api-$(date +%Y%m%d).tar.gz app/

# Backup de logs
tar -czf rund-api-logs-$(date +%Y%m%d).tar.gz logs/
```

## 📞 Soporte

- **Desarrollador**: Oliver Castelblanco Martínez
- **Email**: oliver.castelblanco@esap.edu.co
- **Institución**: ESAP (Escuela Superior de Administración Pública)
- **Versión**: 2.0
- **Licencia**: Propietaria ESAP

## 🚀 Roadmap

- [ ] **GraphQL API** - Implementar endpoint GraphQL
- [ ] **Cache Redis** - Mejorar performance con cache
- [ ] **Rate Limiting** - Implementar límites por IP
- [ ] **Métricas** - Dashboard de monitoreo
- [ ] **Webhooks** - Notificaciones en tiempo real
- [ ] **API Gateway** - Centralizar autenticación

---

> 🎯 **RUND API v2** - Sistema de gestión documental moderno, escalable y robusto para la ESAP
