# Guía de Testing - Sistema de Autenticación

**Proyecto:** RUND API v2.1
**Módulo:** Autenticación
**Fecha:** 13 de diciembre de 2025

---

## 📋 Índice

1. [Endpoints Disponibles](#endpoints-disponibles)
2. [Testing con cURL](#testing-con-curl)
3. [Testing con Postman](#testing-con-postman)
4. [Flujos de Prueba](#flujos-de-prueba)
5. [Casos de Error](#casos-de-error)
6. [Verificación de Seguridad](#verificación-de-seguridad)

---

## Endpoints Disponibles

### Endpoints Públicos (No requieren autenticación)

| Endpoint | Método | Descripción |
|----------|--------|-------------|
| `/api/v2/auth/health` | GET | Health check del sistema de auth |
| `/api/v2/auth/login` | POST | Login con LDAP |
| `/api/v2/auth/dev/login` | POST | Login de desarrollo (solo DEV) |

### Endpoints Protegidos (Requieren sesión activa)

| Endpoint | Método | Descripción |
|----------|--------|-------------|
| `/api/v2/auth/session` | GET | Obtener información de sesión |
| `/api/v2/auth/logout` | POST | Cerrar sesión |
| `/api/v2/auth/refresh` | POST | Refrescar JWT |

---

## Testing con cURL

### 1. Health Check

```bash
curl http://localhost:3000/api/v2/auth/health | jq .
```

**Respuesta esperada:**
```json
{
  "success": true,
  "status": "ok",
  "services": {
    "rund-auth": "healthy",
    "php-session": "healthy"
  }
}
```

### 2. Login con LDAP

**Con credenciales incorrectas (esperado: error):**
```bash
curl -X POST 'http://localhost:3000/api/v2/auth/login' \
  -H 'Content-Type: application/json' \
  -d '{"username":"test","password":"wrong"}' \
  -c /tmp/rund_cookies.txt | jq .
```

**Respuesta esperada:**
```json
{
  "error": "Error de autenticación: Error en autenticación LDAP: Invalid LDAP credentials"
}
```

**Con credenciales correctas:**
```bash
curl -X POST 'http://localhost:3000/api/v2/auth/login' \
  -H 'Content-Type: application/json' \
  -d '{"username":"USUARIO_LDAP","password":"PASSWORD_REAL"}' \
  -c /tmp/rund_cookies.txt | jq .
```

**Respuesta esperada:**
```json
{
  "success": true,
  "user": {
    "sub": "CN=Usuario Nombre,...",
    "name": "Usuario Nombre Completo",
    "email": "usuario@esap.edu.co",
    "tid": "ldap"
  },
  "session_id": "abc123...",
  "message": "Autenticación exitosa"
}
```

**NOTA:** Las cookies se guardan automáticamente en `/tmp/rund_cookies.txt`

### 3. Verificar Sesión

**Sin autenticación previa:**
```bash
curl 'http://localhost:3000/api/v2/auth/session' | jq .
```

**Respuesta esperada:**
```json
{
  "error": "Sesión expirada por inactividad"
}
```

**Con autenticación (usando cookies guardadas):**
```bash
curl 'http://localhost:3000/api/v2/auth/session' \
  -b /tmp/rund_cookies.txt | jq .
```

**Respuesta esperada:**
```json
{
  "success": true,
  "user": {
    "sub": "CN=Usuario...",
    "name": "Usuario Nombre",
    "email": "usuario@esap.edu.co",
    "tid": "ldap"
  },
  "session_id": "abc123...",
  "should_refresh": false,
  "last_activity": 1702477200
}
```

### 4. Logout

```bash
curl -X POST 'http://localhost:3000/api/v2/auth/logout' \
  -b /tmp/rund_cookies.txt | jq .
```

**Respuesta esperada:**
```json
{
  "success": true,
  "message": "Sesión cerrada exitosamente"
}
```

### 5. Refrescar JWT

```bash
curl -X POST 'http://localhost:3000/api/v2/auth/refresh' \
  -b /tmp/rund_cookies.txt | jq .
```

**Respuesta esperada:**
```json
{
  "success": true,
  "message": "JWT refrescado exitosamente"
}
```

---

## Testing con Postman

### Configuración Inicial

1. **Crear colección:** "RUND API - Auth"
2. **Configurar variables:**
   - `base_url`: `http://localhost:3000`
   - `api_version`: `v2`

### Request 1: Health Check

```
GET {{base_url}}/api/{{api_version}}/auth/health
```

**Headers:** Ninguno requerido

### Request 2: Login LDAP

```
POST {{base_url}}/api/{{api_version}}/auth/login
```

**Headers:**
```
Content-Type: application/json
```

**Body (raw JSON):**
```json
{
  "username": "usuario.ldap",
  "password": "password_real"
}
```

**Tests (Tab Tests):**
```javascript
pm.test("Status code is 200", function () {
    pm.response.to.have.status(200);
});

pm.test("Response has user data", function () {
    var jsonData = pm.response.json();
    pm.expect(jsonData.success).to.eql(true);
    pm.expect(jsonData.user).to.have.property('email');
});

// Guardar session_id para requests subsecuentes
if (pm.response.json().session_id) {
    pm.collectionVariables.set("session_id", pm.response.json().session_id);
}
```

**IMPORTANTE:** Habilitar "Automatically follow redirects" en Settings > General

### Request 3: Get Session

```
GET {{base_url}}/api/{{api_version}}/auth/session
```

**Headers:** Ninguno (las cookies se envían automáticamente)

**Tests:**
```javascript
pm.test("Session is active", function () {
    var jsonData = pm.response.json();
    pm.expect(jsonData.success).to.eql(true);
    pm.expect(jsonData.user).to.exist;
});
```

### Request 4: Logout

```
POST {{base_url}}/api/{{api_version}}/auth/logout
```

**Headers:** Ninguno

**Tests:**
```javascript
pm.test("Logout successful", function () {
    var jsonData = pm.response.json();
    pm.expect(jsonData.success).to.eql(true);
});
```

---

## Flujos de Prueba

### Flujo 1: Login → Verificar Sesión → Logout

```bash
# 1. Login
curl -X POST 'http://localhost:3000/api/v2/auth/login' \
  -H 'Content-Type: application/json' \
  -d '{"username":"USER","password":"PASS"}' \
  -c /tmp/cookies.txt

# 2. Verificar sesión
curl 'http://localhost:3000/api/v2/auth/session' \
  -b /tmp/cookies.txt

# 3. Logout
curl -X POST 'http://localhost:3000/api/v2/auth/logout' \
  -b /tmp/cookies.txt

# 4. Verificar que sesión fue cerrada
curl 'http://localhost:3000/api/v2/auth/session' \
  -b /tmp/cookies.txt
# Debería retornar error 401
```

### Flujo 2: Login → Llamada a Endpoint Protegido → Logout

```bash
# 1. Login
curl -X POST 'http://localhost:3000/api/v2/auth/login' \
  -H 'Content-Type: application/json' \
  -d '{"username":"USER","password":"PASS"}' \
  -c /tmp/cookies.txt

# 2. Llamar a endpoint protegido (ejemplo: profesores)
# NOTA: Primero se debe proteger el endpoint con AuthMiddleware
curl 'http://localhost:3000/api/v2/profesores/71799891' \
  -b /tmp/cookies.txt

# 3. Logout
curl -X POST 'http://localhost:3000/api/v2/auth/logout' \
  -b /tmp/cookies.txt
```

### Flujo 3: Refresh de JWT

```bash
# 1. Login
curl -X POST 'http://localhost:3000/api/v2/auth/login' \
  -H 'Content-Type: application/json' \
  -d '{"username":"USER","password":"PASS"}' \
  -c /tmp/cookies.txt

# 2. Esperar cerca de 15 minutos (TTL del JWT)
sleep 800

# 3. Verificar sesión (should_refresh debería ser true)
curl 'http://localhost:3000/api/v2/auth/session' \
  -b /tmp/cookies.txt

# 4. Refrescar JWT
curl -X POST 'http://localhost:3000/api/v2/auth/refresh' \
  -b /tmp/cookies.txt

# 5. Verificar que JWT fue refrescado
curl 'http://localhost:3000/api/v2/auth/session' \
  -b /tmp/cookies.txt
```

---

## Casos de Error

### Error 1: Login sin credenciales

```bash
curl -X POST 'http://localhost:3000/api/v2/auth/login' \
  -H 'Content-Type: application/json' \
  -d '{}'
```

**Respuesta esperada:**
```json
{
  "error": "Parámetros requeridos faltantes: username, password"
}
```

### Error 2: Sesión sin login previo

```bash
curl 'http://localhost:3000/api/v2/auth/session'
```

**Respuesta esperada:**
```json
{
  "error": "Sesión expirada por inactividad"
}
```

### Error 3: JWT expirado

**Flujo:**
1. Login exitoso
2. Esperar > 15 minutos (TTL del JWT)
3. Intentar acceder a endpoint protegido

**Respuesta esperada:**
```json
{
  "error": "JWT expirado",
  "code": "UNAUTHORIZED"
}
```

### Error 4: Credenciales LDAP incorrectas

```bash
curl -X POST 'http://localhost:3000/api/v2/auth/login' \
  -H 'Content-Type: application/json' \
  -d '{"username":"invalid","password":"wrong"}'
```

**Respuesta esperada:**
```json
{
  "error": "Error de autenticación: Error en autenticación LDAP: Invalid LDAP credentials"
}
```

---

## Verificación de Seguridad

### 1. JWT NO debe estar expuesto en la respuesta

✅ **Correcto:** Login retorna `{user, session_id}` - JWT NO está en la respuesta
❌ **Incorrecto:** Login retorna `{user, internal_jwt}` - JWT expuesto (VULNERABILIDAD)

### 2. Cookies deben tener httpOnly

```bash
# Verificar headers de respuesta
curl -v -X POST 'http://localhost:3000/api/v2/auth/login' \
  -H 'Content-Type: application/json' \
  -d '{"username":"USER","password":"PASS"}' 2>&1 | grep -i 'set-cookie'
```

**Verificar que contenga:**
- `HttpOnly`
- `SameSite=Lax`
- En producción: `Secure`

### 3. Sesión debe expirar por inactividad

```bash
# 1. Login
curl -X POST 'http://localhost:3000/api/v2/auth/login' \
  -H 'Content-Type: application/json' \
  -d '{"username":"USER","password":"PASS"}' \
  -c /tmp/cookies.txt

# 2. Esperar > 8 horas
sleep 28900

# 3. Verificar sesión (debería estar expirada)
curl 'http://localhost:3000/api/v2/auth/session' \
  -b /tmp/cookies.txt
```

**Respuesta esperada:**
```json
{
  "error": "Sesión expirada por inactividad"
}
```

### 4. Middleware debe proteger rutas

**Ejemplo de ruta protegida:**

```php
// En routes_v2.php
$router->get('/profesores/{cedula}', [ProfesoresController::class, 'show'], [
    AuthMiddleware::authenticate()
]);
```

**Testing:**
```bash
# Sin autenticación (debería fallar)
curl 'http://localhost:3000/api/v2/profesores/71799891'

# Respuesta esperada:
{
  "error": "Sesión no iniciada",
  "code": "UNAUTHORIZED"
}

# Con autenticación (debería funcionar)
curl 'http://localhost:3000/api/v2/profesores/71799891' \
  -b /tmp/cookies.txt

# Respuesta esperada: datos del profesor
```

### 5. JWKS Público debe ser accesible

```bash
# Verificar JWKS desde rund-auth
curl 'http://localhost:8081/.well-known/jwks.json' | jq .
```

**Respuesta esperada:**
```json
{
  "keys": [
    {
      "kty": "RSA",
      "kid": "key-id",
      "use": "sig",
      "alg": "RS256",
      "n": "...",
      "e": "AQAB",
      "retired": false
    }
  ]
}
```

---

## Troubleshooting

### Problema: "rund-auth: unhealthy" en health check

**Solución:**
```bash
# Verificar desde dentro del contenedor
docker exec rund-api curl http://rund-auth:8080/healthz
```

Si retorna `{"ok":true}`, el problema es menor (timing). El servicio funciona.

### Problema: "Session not found"

**Soluciones:**
1. Limpiar cookies: `rm /tmp/rund_cookies.txt`
2. Limpiar Redis: `docker compose exec redis redis-cli FLUSHDB`
3. Reiniciar rund-api: `docker compose restart rund-api`

### Problema: "LDAP connection failed"

**Soluciones:**
1. Verificar conectividad: `nc -zv esap.edu.int 389`
2. Verificar credenciales en `.env` de rund-auth
3. Verificar que LDAP esté habilitado: `LDAP_ENABLED=true`

### Problema: "JWT inválido o expirado"

**Causas posibles:**
1. JWT expiró (TTL: 15 minutos) → Refrescar con `/auth/refresh`
2. Clave JWKS cambió → Limpiar cache: `docker compose restart rund-api`
3. Clock skew entre servicios → Sincronizar hora del sistema

---

## Métricas de Rendimiento

### Latencias Esperadas

| Endpoint | Latencia típica | Latencia máxima |
|----------|----------------|-----------------|
| `/auth/health` | < 50ms | 200ms |
| `/auth/login` | 200-500ms | 2s (LDAP lento) |
| `/auth/session` | < 50ms | 200ms |
| `/auth/logout` | < 100ms | 500ms |
| `/auth/refresh` | 100-300ms | 1s |

### Testing de Carga

```bash
# Apache Bench - 100 requests, 10 concurrent
ab -n 100 -c 10 http://localhost:3000/api/v2/auth/health

# Verificar que:
# - Requests per second > 50
# - Time per request < 200ms
# - No failed requests
```

---

## Checklist de Testing Pre-Producción

- [ ] Health check retorna "healthy" para todos los servicios
- [ ] Login con credenciales LDAP reales funciona
- [ ] Sesión persiste después de reload de página
- [ ] Timeout de inactividad funciona (8 horas)
- [ ] JWT se refresca automáticamente antes de expirar
- [ ] Logout limpia sesión correctamente
- [ ] Cookies tienen flags httpOnly y sameSite
- [ ] Middleware protege rutas correctamente
- [ ] JWKS público es accesible
- [ ] Errores retornan códigos HTTP correctos (401, 403, etc.)
- [ ] No hay JWT expuesto en respuestas al frontend
- [ ] Logging de eventos de autenticación funciona
- [ ] Performance bajo carga es aceptable

---

**Última actualización:** 13 de diciembre de 2025
**Versión:** 1.0
