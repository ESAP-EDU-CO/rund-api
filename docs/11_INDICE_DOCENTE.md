# 11. ÍNDICE DOCENTE JSON

## Tabla de Contenidos

1. [Introducción](#1-introducción)
2. [Flujo de Generación](#2-flujo-de-generación)
3. [Estructura del JSON](#3-estructura-del-json)
4. [Mapeo de Encabezados](#4-mapeo-de-encabezados)
5. [Merge Inteligente](#5-merge-inteligente)
6. [Almacenamiento en OpenKM](#6-almacenamiento-en-openkm)
7. [Uso desde el Frontend](#7-uso-desde-el-frontend)
8. [Ejemplos de Código](#8-ejemplos-de-código)

---

## 1. Introducción

El **índice docente** (`indice_docente.json`) es un archivo JSON que funciona como base de datos NoSQL para acceso rápido a la información de profesores de la ESAP. Este archivo se genera automáticamente cuando se carga el archivo `ListadoGeneralDocente.csv` a través del sistema.

### Características Principales

- **Estructura plana**: Usa cédulas como claves para búsqueda O(1)
- **Mapeo automático**: Convierte encabezados CSV a nomenclatura estándar
- **Merge inteligente**: Preserva datos existentes al cargar nuevos CSV
- **Versionado**: Mantiene historial de cambios en OpenKM
- **Escalable**: Permite añadir nuevos campos sin modificar código

### Ubicación

- **Ruta en OpenKM**: `/okm:root/RUND/DOCUMENTOS/LISTADOS/INDICE_DOCENTE/indice_docente.json`
- **Constante PHP**: `Config::TAX_LISTADOS . "INDICE_DOCENTE"`

---

## 2. Flujo de Generación

### Diagrama de Flujo

```
┌─────────────────────────────────────────────────────────────┐
│         FLUJO DE GENERACIÓN DE ÍNDICE DOCENTE               │
└─────────────────────────────────────────────────────────────┘

  ┌──────────────────┐
  │ Usuario carga    │
  │ ListadoGeneral   │
  │ Docente.csv      │
  └────────┬─────────┘
           │
           ▼
  ┌─────────────────────┐
  │ FileHandlers::      │
  │ loadList()          │
  │                     │
  │ Detecta:            │
  │ - nombre = ListadoGeneralDocente.csv
  │ - tipo = LISTADO_DE_DOCENTES
  └────────┬────────────┘
           │
           ▼
  ┌─────────────────────────┐
  │ generaIndiceJson()      │
  │                         │
  │ 1. Lee labels.json      │
  │ 2. Lee CSV              │
  │ 3. Mapea encabezados    │
  │ 4. Construye JSON       │
  └────────┬────────────────┘
           │
           ▼
  ┌─────────────────────────┐
  │ almacenaIndiceJson()    │
  │                         │
  │ 1. Busca archivo existente
  │ 2. Hace merge si existe │
  │ 3. Guarda/actualiza     │
  │ 4. Versiona en OpenKM   │
  └────────┬────────────────┘
           │
           ▼
  ┌─────────────────────────┐
  │ JSON disponible para    │
  │ consumo en frontend     │
  └─────────────────────────┘
```

### Condiciones de Activación

La generación automática se activa cuando:

1. **Nombre de archivo**: exactamente `ListadoGeneralDocente.csv`
2. **Tipo de documento**: `LISTADO_DE_DOCENTES`
3. **Acción**: carga (no duplicado)

---

## 3. Estructura del JSON

### Formato

El JSON generado tiene una **estructura plana** con cédulas como claves:

```json
{
  "CEDULA1": {
    "CAMPO1": "valor1",
    "CAMPO2": "valor2",
    ...
  },
  "CEDULA2": {
    "CAMPO1": "valor1",
    "CAMPO2": "valor2",
    ...
  }
}
```

### Ejemplo Real

```json
{
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
    ...
  }
}
```

### Ventajas de esta Estructura

1. **Búsqueda O(1)**: Acceso directo por cédula
   ```javascript
   const docente = indiceDocente["479678"]; // Instantáneo
   ```

2. **Fácil generación de listas**:
   ```javascript
   const cedulas = Object.keys(indiceDocente);
   const nombres = Object.values(indiceDocente).map(d => d.NOMBRE_Y_APELLIDO);
   ```

3. **Merge natural**:
   ```javascript
   // Añadir/actualizar docente
   indiceDocente["479678"] = {...indiceDocente["479678"], ...nuevosDatos};
   ```

4. **Tamaño optimizado**: Más compacto que un array de objetos

---

## 4. Mapeo de Encabezados

### Proceso Automático

El sistema mapea automáticamente los encabezados del CSV a claves estándar usando `labels.json`:

```
CSV Header          →  Búsqueda en labels.json  →  Clave Final
───────────────────────────────────────────────────────────────
"Vinculación"       →  Encontrado               →  "VINCULACION"
"Nombre completo"   →  Encontrado               →  "NOMBRE_Y_APELLIDO"
"Territorial"       →  Encontrado               →  "TERRITORIAL"
"Campo Nuevo"       →  No encontrado            →  "CAMPO_NUEVO" (normalizado)
```

### Normalización

Si un encabezado no existe en `labels.json`, se normaliza automáticamente:

1. Elimina tildes: `á` → `a`, `é` → `e`, etc.
2. Convierte a mayúsculas
3. Reemplaza espacios por `_`

**Ejemplo:**
```
"Núcleo Temático"  →  "NUCLEO_TEMATICO"
"Correo personal"  →  "CORREO_PERSONAL"
```

### Columnas Excluidas

La columna `#` (número de fila) se excluye automáticamente del JSON final.

---

## 5. Merge Inteligente

### Comportamiento

Cuando se carga un nuevo CSV:

1. **Si el archivo `indice_docente.json` NO existe**:
   - Se crea nuevo con todos los registros del CSV

2. **Si el archivo YA existe**:
   - Se descarga el contenido actual
   - Se hace **merge** registro por registro:
     - Si la cédula existe: fusiona campos (nuevos sobrescriben, existentes se preservan)
     - Si la cédula no existe: añade el registro completo

### Ejemplo de Merge

**JSON Existente:**
```json
{
  "479678": {
    "VINCULACION": "Ocasional",
    "NOMBRE_Y_APELLIDO": "ABEL ANTONIO ABELLA BELTRAN",
    "CAMPO_ANTIGUO": "valor_antiguo"
  }
}
```

**Nuevo CSV tiene:**
```json
{
  "479678": {
    "VINCULACION": "Carrera1",  // Actualiza
    "TERRITORIAL": "META",       // Añade nuevo
    "CAMPO_NUEVO": "valor_nuevo" // Añade nuevo
  },
  "999999": {
    "VINCULACION": "Cátedra",
    "NOMBRE_Y_APELLIDO": "DOCENTE NUEVO"
  }
}
```

**JSON Resultante:**
```json
{
  "479678": {
    "VINCULACION": "Carrera1",          // ← Actualizado
    "NOMBRE_Y_APELLIDO": "ABEL ANTONIO ABELLA BELTRAN",
    "CAMPO_ANTIGUO": "valor_antiguo",   // ← Preservado
    "TERRITORIAL": "META",              // ← Añadido
    "CAMPO_NUEVO": "valor_nuevo"        // ← Añadido
  },
  "999999": {                           // ← Docente nuevo
    "VINCULACION": "Cátedra",
    "NOMBRE_Y_APELLIDO": "DOCENTE NUEVO"
  }
}
```

### Ventajas del Merge

- ✅ Permite cargas incrementales de datos
- ✅ Preserva campos históricos
- ✅ Actualiza solo lo necesario
- ✅ Añade nuevos docentes sin afectar existentes
- ✅ Mantiene versionado en OpenKM

---

## 6. Almacenamiento en OpenKM

### Ruta y Estructura

```
/okm:root/RUND/
└── DOCUMENTOS/
    └── LISTADOS/
        └── INDICE_DOCENTE/          ← Carpeta creada automáticamente
            └── indice_docente.json  ← Archivo JSON
```

### Versionado

El sistema usa **checkout/checkin** de OpenKM para mantener historial:

1. **Primera carga**:
   - Crea archivo nuevo
   - Versión: 1.0

2. **Cargas subsecuentes**:
   - Hace checkout (bloquea)
   - Actualiza contenido (merge)
   - Hace checkin (nueva versión)
   - Comentario automático: "Actualización automática de índice docente - YYYY-MM-DD HH:MM:SS"

### Respuesta de la API

Cuando se carga el CSV, la respuesta incluye información del índice:

```json
{
  "error": false,
  "respuesta": {...},
  "indiceJson": {
    "error": null,
    "registros": 150,
    "estructura": "objeto plano con cédulas como claves",
    "merge": true,
    "registrosAnteriores": 120,
    "registrosNuevos": 150,
    "registrosFinales": 180,
    "archivoExiste": true,
    "uuid": "e4b2c8d1-1234-5678-9abc-def012345678",
    "carga": {...}
  }
}
```

---

## 7. Uso desde el Frontend

### Obtener el Índice

**Endpoint:**
```
GET /api/file?accion=data&nombre=indice_docente
```

**Código Angular/TypeScript:**
```typescript
interface Docente {
  DOCUMENTO_DE_IDENTIDAD: string;
  VINCULACION: string;
  NOMBRE_Y_APELLIDO: string;
  TERRITORIAL: string;
  // ... otros campos
}

interface IndiceDocente {
  [cedula: string]: Docente;
}

// Obtener el índice
async obtenerIndiceDocente(): Promise<IndiceDocente> {
  const response = await fetch('/api/file?accion=data&nombre=indice_docente');
  const data = await response.json();
  return data;
}
```

### Select Autocomplete de Cédulas

```typescript
async cargarCedulasParaAutocomplete() {
  const indice = await this.obtenerIndiceDocente();

  // Opción 1: Solo cédulas
  const cedulas = Object.keys(indice);

  // Opción 2: Cédula + Nombre
  const opciones = Object.entries(indice).map(([cedula, datos]) => ({
    value: cedula,
    label: `${cedula} - ${datos.NOMBRE_Y_APELLIDO}`
  }));

  this.autocompleteCedulas = opciones;
}
```

### Búsqueda Rápida por Cédula

```typescript
async buscarDocentePorCedula(cedula: string): Promise<Docente | null> {
  const indice = await this.obtenerIndiceDocente();

  // Búsqueda O(1) - instantánea
  const docente = indice[cedula];

  return docente || null;
}
```

### Filtrado y Búsqueda Avanzada

```typescript
async filtrarDocentes(filtros: {
  territorial?: string;
  vinculacion?: string;
  nivelFormacion?: string;
}): Promise<Docente[]> {
  const indice = await this.obtenerIndiceDocente();

  return Object.values(indice).filter(docente => {
    if (filtros.territorial && docente.TERRITORIAL !== filtros.territorial) {
      return false;
    }
    if (filtros.vinculacion && docente.VINCULACION !== filtros.vinculacion) {
      return false;
    }
    if (filtros.nivelFormacion && docente.NIVEL_DE_FORMACION !== filtros.nivelFormacion) {
      return false;
    }
    return true;
  });
}
```

### Caché en el Frontend

```typescript
class IndiceDocenteService {
  private cache: IndiceDocente | null = null;
  private cacheTimestamp: number = 0;
  private cacheDuration = 5 * 60 * 1000; // 5 minutos

  async getIndice(forceRefresh = false): Promise<IndiceDocente> {
    const now = Date.now();

    if (!forceRefresh && this.cache && (now - this.cacheTimestamp) < this.cacheDuration) {
      return this.cache;
    }

    const response = await fetch('/api/file?accion=data&nombre=indice_docente');
    this.cache = await response.json();
    this.cacheTimestamp = now;

    return this.cache;
  }

  invalidateCache() {
    this.cache = null;
    this.cacheTimestamp = 0;
  }
}
```

---

## 8. Ejemplos de Código

### Backend PHP

#### Leer el Índice

```php
use RUND\Core\OpenKM;

// Obtener el índice completo
$indiceDocente = OpenKM::getDataFile("indice_docente", Config::TAX_LISTADOS . "INDICE_DOCENTE");

// Buscar un docente específico
$cedula = "479678";
if (isset($indiceDocente[$cedula])) {
    $docente = $indiceDocente[$cedula];
    echo "Docente encontrado: " . $docente["NOMBRE_Y_APELLIDO"];
} else {
    echo "Docente no encontrado";
}
```

#### Añadir Campos Manualmente

```php
// Obtener índice actual
$indice = OpenKM::getDataFile("indice_docente", Config::TAX_LISTADOS . "INDICE_DOCENTE");

// Añadir un nuevo campo a todos los docentes
foreach ($indice as $cedula => &$docente) {
    $docente["CAMPO_NUEVO"] = "valor_default";
}

// Guardar (esto creará una nueva versión)
$jsonActualizado = json_encode($indice, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
FileHandlers::almacenaIndiceJson($jsonActualizado);
```

### Frontend Angular

#### Componente de Búsqueda

```typescript
import { Component, OnInit } from '@angular/core';

@Component({
  selector: 'app-busqueda-docente',
  template: `
    <input
      type="text"
      [(ngModel)]="cedulaBusqueda"
      (input)="onBuscar()"
      placeholder="Buscar por cédula..."
      [matAutocomplete]="auto"
    />
    <mat-autocomplete #auto="matAutocomplete">
      <mat-option *ngFor="let opcion of opcionesFiltradas" [value]="opcion.cedula">
        {{ opcion.label }}
      </mat-option>
    </mat-autocomplete>

    <div *ngIf="docenteSeleccionado">
      <h3>{{ docenteSeleccionado.NOMBRE_Y_APELLIDO }}</h3>
      <p>Territorial: {{ docenteSeleccionado.TERRITORIAL }}</p>
      <p>Vinculación: {{ docenteSeleccionado.VINCULACION }}</p>
      <!-- ... más campos -->
    </div>
  `
})
export class BusquedaDocenteComponent implements OnInit {
  cedulaBusqueda = '';
  indiceDocente: any = {};
  opcionesFiltradas: any[] = [];
  docenteSeleccionado: any = null;

  async ngOnInit() {
    // Cargar índice al iniciar
    this.indiceDocente = await this.cargarIndice();
  }

  async cargarIndice() {
    const response = await fetch('/api/file?accion=data&nombre=indice_docente');
    return await response.json();
  }

  onBuscar() {
    const busqueda = this.cedulaBusqueda.toLowerCase();

    this.opcionesFiltradas = Object.entries(this.indiceDocente)
      .filter(([cedula, datos]: [string, any]) =>
        cedula.includes(busqueda) ||
        datos.NOMBRE_Y_APELLIDO.toLowerCase().includes(busqueda)
      )
      .map(([cedula, datos]: [string, any]) => ({
        cedula,
        label: `${cedula} - ${datos.NOMBRE_Y_APELLIDO}`
      }))
      .slice(0, 10); // Limitar a 10 resultados

    // Si hay coincidencia exacta, mostrar docente
    if (this.indiceDocente[this.cedulaBusqueda]) {
      this.docenteSeleccionado = this.indiceDocente[this.cedulaBusqueda];
    }
  }
}
```

---

## Resumen

El sistema de índice docente JSON proporciona:

✅ **Generación automática** desde CSV
✅ **Mapeo inteligente** de encabezados
✅ **Merge preservador** de datos existentes
✅ **Versionado** en OpenKM
✅ **Búsqueda O(1)** por cédula
✅ **Escalabilidad** para nuevos campos
✅ **Fácil consumo** desde frontend

Esta funcionalidad permite que el frontend tenga acceso rápido y eficiente a la información de profesores sin necesidad de consultas complejas o procesamiento de CSV en el cliente.
