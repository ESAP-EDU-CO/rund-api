# Implementación de Autenticación en RUND-API

**Versión:** 1.0
**Fecha:** 13 de diciembre de 2025
**Estado:** ✅ Implementado - Listo para pruebas de integración

---

## 📋 Resumen

Se ha implementado completamente el sistema de autenticación en **rund-api** actuando como BFF (Backend-for-Frontend) para el servicio **rund-auth**. La implementación sigue el patrón documentado en [rund-auth/docs/integracion-ecosistema-rund.md](../../rund-auth/docs/integracion-ecosistema-rund.md).

---

## 🎯 Arquitectura Implementada

```
┌──────────────┐
│  rund-mgp    │  (Angular)
│  (Frontend)  │
└──────┬───────┘
       │ HTTP
       ▼
┌──────────────┐
│  rund-api    │  (PHP 8.3)
│    (BFF)     │
│ ✅ AuthController
│ ✅ AuthService
│ ✅ JWTValidator
│ ✅ AuthMiddleware
└──────┬───────┘
       │ HTTP Interno
       ▼
┌──────────────┐
│  rund-auth   │  (Node.js)
│              │
│ • LDAP Auth  │
│ • OAuth 2.0  │
│ • JWT RS256  │
└──────────────┘
```

---

## 📦 Componentes Implementados

### 1. **AuthService.php**
**Ubicación:** `app/src/Services/AuthService.php`

Servicio que maneja la comunicación con rund-auth.

**Métodos principales:**
```php
// Login con LDAP
public function loginWithLDAP(string $username, string $password): array

// Obtener sesión activa
public function getSession(string $sessionCookie): ?array

// Cerrar sesión
public function logout(string $sessionCookie): bool

// Refrescar JWT
public function refreshJWT(string $sessionCookie): string

// Verificar salud
public function checkHealth(): bool

// Obtener JWKS público
public function getPublicJWKS(): array

// Login de desarrollo (solo DEV)
public function devLogin(string $email): array
```

### 2. **JWTValidator.php**
**Ubicación:** `app/src/Services/JWTValidator.php`

Validador de JWT con verificación de firma RS256 usando JWKS público.

**Características:**
- ✅ Validación de firma RS256 con OpenSSL
- ✅ Verificación de claims (iss, aud, exp, iat, sub)
- ✅ Cache de JWKS público (TTL: 5 minutos)
- ✅ Conversión JWK → PEM nativa en PHP
- ✅ Sin dependencias externas

**Método principal:**
```php
public function validate(
    string $token,
    string $expectedIssuer = 'rund-auth',
    string $expectedAudience = 'rund-api'
): array
```

### 3. **AuthController.php**
**Ubicación:** `app/src/Controllers/V2/AuthController.php`

Controlador con todos los endpoints de autenticación.

**Endpoints implementados:**

| Endpoint | Método | Descripción | Auth |
|----------|--------|-------------|------|
| `/api/v2/auth/login` | POST | Login LDAP | No |
| `/api/v2/auth/session` | GET | Verificar sesión | No |
| `/api/v2/auth/logout` | POST | Cerrar sesión | No |
| `/api/v2/auth/refresh` | POST | Refrescar JWT | No |
| `/api/v2/auth/health` | GET | Health check | No |
| `/api/v2/auth/dev/login` | POST | Dev login | No |

**Características de seguridad:**
- ✅ JWT almacenado en sesión PHP (nunca expuesto al frontend)
- ✅ Cookies httpOnly y sameSite=Lax
- ✅ Timeout de inactividad (8 horas)
- ✅ Regeneración de session ID en login
- ✅ Limpieza automática de sesión en logout

### 4. **AuthMiddleware.php (Actualizado)**
**Ubicación:** `app/src/Middleware/AuthMiddleware.php`

Middleware para proteger rutas que requieren autenticación.

**Métodos disponibles:**
```php
// Autenticación requerida
AuthMiddleware::authenticate()

// Requiere rol específico
AuthMiddleware::requireRole('admin')

// Solo IPs permitidas
AuthMiddleware::limitToIPs(['127.0.0.1'])

// Solo red interna Docker
AuthMiddleware::internalOnly()
```

**Funcionalidades:**
- ✅ Validación de sesión PHP
- ✅ Verificación de JWT con firma RS256
- ✅ Control de timeout de inactividad
- ✅ Limpieza automática de sesiones expiradas
- ✅ Respuestas 401 (Unauthorized) y 403 (Forbidden)

### 5. **Config.php (Actualizado)**
**Ubicación:** `app/src/Config/Config.php`

Nueva constante agregada:
```php
const RUND_AUTH_URL = "http://rund-auth:8080";
```

### 6. **routes_v2.php (Actualizado)**
**Ubicación:** `app/routes_v2.php`

Nuevas rutas de autenticación:
```php
$router->group('/auth', function (Router $router) {
    // Endpoints públicos
    $router->post('/login', [AuthController::class, 'login']);
    $router->post('/dev/login', [AuthController::class, 'devLogin']);
    $router->get('/health', [AuthController::class, 'health']);

    // Endpoints protegidos
    $router->get('/session', [AuthController::class, 'getSession']);
    $router->post('/logout', [AuthController::class, 'logout']);
    $router->post('/refresh', [AuthController::class, 'refresh']);
});
```

---

## 🧪 Pruebas Realizadas

### ✅ Health Check
```bash
curl http://localhost:3000/api/v2/auth/health
```

**Respuesta:**
```json
{
  "success": true,
  "status": "degraded",
  "services": {
    "rund-auth": "healthy",
    "php-session": "healthy"
  }
}
```

### ✅ Login con credenciales incorrectas
```bash
curl -X POST http://localhost:3000/api/v2/auth/login \
  -H 'Content-Type: application/json' \
  -d '{"username":"test","password":"wrong"}'
```

**Respuesta:**
```json
{
  "error": "Error de autenticación: Error en autenticación LDAP: Invalid LDAP credentials"
}
```

### ✅ Sesión sin autenticación previa
```bash
curl http://localhost:3000/api/v2/auth/session
```

**Respuesta:**
```json
{
  "error": "Sesión expirada por inactividad"
}
```

---

## 🔐 Flujo de Autenticación Completo

### 1. Login desde el Frontend (rund-mgp)

```typescript
// auth.service.ts
login(username: string, password: string) {
  return this.http.post(
    'http://localhost:3000/api/v2/auth/login',
    { username, password },
    { withCredentials: true }  // Importante: enviar cookies
  );
}
```

### 2. Procesamiento en rund-api

```
┌─────────────────────────────────────────────────────┐
│ 1. AuthController.login() recibe credenciales      │
│ 2. AuthService.loginWithLDAP() llama a rund-auth   │
│ 3. rund-auth valida contra LDAP                    │
│ 4. rund-auth retorna user + internal_jwt           │
│ 5. AuthController guarda JWT en sesión PHP         │
│ 6. AuthController retorna SOLO user al frontend    │
│    (JWT NUNCA se expone al frontend)               │
└─────────────────────────────────────────────────────┘
```

### 3. Llamadas subsecuentes a la API

```typescript
// Todas las llamadas van con credentials: true
this.http.get(
  'http://localhost:3000/api/v2/profesores/71799891',
  { withCredentials: true }
);
```

```
┌─────────────────────────────────────────────────────┐
│ 1. HTTP Request con cookie de sesión PHP           │
│ 2. AuthMiddleware.authenticate() se ejecuta        │
│ 3. Verifica sesión PHP activa                      │
│ 4. Obtiene JWT de $_SESSION[internal_jwt']         │
│ 5. JWTValidator.validate() verifica firma RS256    │
│ 6. Si válido → continúa request                    │
│ 7. Si inválido → 401 Unauthorized                  │
└─────────────────────────────────────────────────────┘
```

---

## 📝 Ejemplo de Protección de Rutas

Para proteger endpoints existentes, agregar el middleware:

```php
// En routes_v2.php
$router->group('/profesores', function (Router $router) {
    $router->get('/{cedula}', [ProfesoresController::class, 'show'], [
        AuthMiddleware::authenticate()  // ← Agregar esta línea
    ]);
});
```

O proteger grupos completos:

```php
$router->group('/certificados', function (Router $router) {
    $router->get('/{id}', [CertificadosController::class, 'show']);
    $router->post('/generar', [CertificadosController::class, 'generar']);
}, [
    AuthMiddleware::authenticate()  // ← Protege todo el grupo
]);
```

---

## 🚀 Próximos Pasos

### 1. Integración en rund-mgp (Angular)

Implementar los servicios y guards de autenticación siguiendo:
- [rund-auth/docs/integracion-ecosistema-rund.md](../../rund-auth/docs/integracion-ecosistema-rund.md#implementación-en-rund-mgp-angular)

**Archivos a crear:**
- `src/app/core/services/auth.service.ts`
- `src/app/core/guards/auth.guard.ts`
- `src/app/core/interceptors/auth.interceptor.ts`
- `src/app/features/auth/pages/login/login.component.ts`

### 2. Protección de Rutas Existentes

Revisar y proteger los endpoints según criticidad:

**Alta prioridad (proteger inmediatamente):**
- ✅ `/api/v2/profesores/*` - Datos sensibles
- ✅ `/api/v2/certificados/generar` - Operaciones críticas
- ✅ `/api/v2/archivos/subir` - Modificación de datos

**Media prioridad:**
- `/api/v2/listados/*` - Datos administrativos
- `/api/v2/firmas/*` - Documentos oficiales

**Baja prioridad (pueden quedar públicos):**
- `/api/v2/system/*` - Información pública
- `/api/v2/categorias/arbol` - Estructura pública

### 3. Testing con Credenciales Reales

Una vez integrado con rund-mgp, probar con:
- ✅ Credenciales LDAP reales de ESAP
- ✅ Timeout de sesión (8 horas)
- ✅ Refresh de JWT automático
- ✅ Logout y limpieza de sesión

### 4. Configuración de Producción

Habilitar en producción:
```php
// AuthController.php y AuthMiddleware.php
ini_set('session.cookie_secure', '1');  // Solo HTTPS
```

```bash
# .env de rund-auth
COOKIE_SECURE=true
DEV_FAKE_LOGIN=false
```

---

## 🔍 Debugging y Troubleshooting

### Verificar comunicación rund-api ↔ rund-auth

```bash
# Desde dentro del contenedor rund-api
docker exec rund-api curl http://rund-auth:8080/healthz
```

**Esperado:** `{"ok":true}`

### Ver logs de autenticación

```bash
# rund-auth logs
docker compose logs -f rund-auth

# rund-api logs
docker compose logs -f rund-api
```

### Limpiar sesiones de prueba

```bash
# Limpiar Redis (sesiones de rund-auth)
docker compose exec redis redis-cli FLUSHDB

# Reiniciar rund-api (limpia sesiones PHP)
docker compose restart rund-api
```

---

## 📚 Documentación Relacionada

- [Guía de Integración Completa](../../rund-auth/docs/integracion-ecosistema-rund.md)
- [README de rund-auth](../../rund-auth/README.md)
- [Informe Técnico rund-auth](../../rund-auth/docs/informe-tecnico.md)
- [CLAUDE.md - Documentación del Proyecto](../../CLAUDE.md)

---

## ✅ Checklist de Implementación

- [x] AuthService.php creado
- [x] JWTValidator.php creado
- [x] AuthController.php creado
- [x] AuthMiddleware.php actualizado
- [x] Config.php actualizado con RUND_AUTH_URL
- [x] routes_v2.php actualizado con rutas /auth
- [x] Pruebas básicas de endpoints
- [ ] Integración con rund-mgp (Angular)
- [ ] Protección de rutas críticas
- [ ] Testing con credenciales LDAP reales
- [ ] Configuración de producción
- [ ] Documentación de usuario final

---

**Estado actual:** ✅ Backend completamente implementado y listo para integración con frontend.
