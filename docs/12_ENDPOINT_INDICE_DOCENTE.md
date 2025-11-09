# Endpoint: Índice Docente

## Información General

**Endpoint:** `GET /api/v2/listados/indice`
**Versión:** 2.0
**Autenticación:** No requerida (por ahora)
**Formato de respuesta:** JSON

---

## Descripción

Este endpoint retorna el índice completo de docentes en formato JSON, generado automáticamente desde el archivo `ListadoGeneralDocente.csv`. El índice utiliza una estructura plana con cédulas como claves, optimizada para búsqueda rápida O(1) y fácil consumo desde el frontend.

---

## Request

### Método HTTP
```
GET /api/v2/listados/indice
```

### Headers
```
Accept: application/json
```

### Query Parameters
Ninguno

### Ejemplo de Request
```bash
curl -X GET http://localhost:3000/api/v2/listados/indice \
  -H "Accept: application/json"
```

---

## Response

### Status Codes

| Código | Descripción |
|--------|-------------|
| 200 | Índice docente obtenido exitosamente |
| 404 | El índice docente no existe (no se ha cargado ListadoGeneralDocente.csv) |
| 500 | Error interno del servidor |

### Estructura de Respuesta Exitosa (200)

```json
{
  "indice": {
    "cedula1": {
      "DOCUMENTO_DE_IDENTIDAD": "cedula1",
      "VINCULACION": "...",
      "NOMBRE_Y_APELLIDO": "...",
      ...
    },
    "cedula2": {
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
```

### Estructura de Respuesta de Error (404)

```json
{
  "error": true,
  "message": "El índice docente no existe. Debe cargar primero el archivo ListadoGeneralDocente.csv",
  "code": 404
}
```

---

## Campos del Índice

Cada docente en el índice contiene los siguientes campos (basados en `labels.json`):

| Campo | Tipo | Descripción |
|-------|------|-------------|
| `DOCUMENTO_DE_IDENTIDAD` | string | Cédula del docente |
| `VINCULACION` | string | Tipo de vinculación (Ocasional, Carrera1, Carrera2, etc.) |
| `NOMBRE_Y_APELLIDO` | string | Nombre completo del docente |
| `TERRITORIAL` | string | Sede o territorial asignada |
| `CATEGORIA` | string | Categoría docente (Titular, Asociado, Asistente, Auxiliar) |
| `NUCLEO_TEMATICO` | string | Núcleo temático de desempeño |
| `NIVEL_DE_FORMACION` | string | Nivel de formación máximo |
| `PERFIL_ACADEMICO` | string | Descripción del perfil académico |
| `PREGRADO` | string | Título de pregrado |
| `ESPECIALIZACION` | string | Especializaciones |
| `MAESTRIA` | string | Maestrías |
| `DOCTORADO` | string | Doctorados |
| `POSDOCTORADO` | string | Posdoctorados |
| `INVESTIGACION_2024` | string | Investigaciones 2024 |
| `ORIGEN_DE_VINCULACION` | string | Origen de la vinculación |
| `ACTO_ADMINISTRATIVO_DE_VINCULACION` | string | Resolución de vinculación |
| `CORREO_INSTITUCIONAL` | string | Email institucional ESAP |
| `CORREO_PERSONAL` | string | Email personal |
| `TELEFONO` | string | Número de teléfono |
| `ULTIMA_EVALUACION` | string | Resultado última evaluación |
| `DEDICACION` | string | Tipo de dedicación |
| `SITUACION_ADMINISTRATIVA` | string | Situación administrativa actual |
| `INICIO_DE_VINCULACION` | string | Fecha de inicio de vinculación |
| `FIN_DE_VINCULACION` | string | Fecha de fin de vinculación |
| `PUNTAJE_SALARIAL` | string | Puntaje salarial |

---

## Ejemplos de Uso

### 1. Obtener el Índice Completo (JavaScript/TypeScript)

```typescript
async function obtenerIndiceDocente() {
  const response = await fetch('http://localhost:3000/api/v2/listados/indice');

  if (!response.ok) {
    throw new Error('No se pudo obtener el índice docente');
  }

  const data = await response.json();
  return data.indice;
}

// Uso
const indice = await obtenerIndiceDocente();
console.log(`Total de docentes: ${Object.keys(indice).length}`);
```

### 2. Búsqueda Rápida por Cédula (O(1))

```typescript
async function buscarDocentePorCedula(cedula: string) {
  const indice = await obtenerIndiceDocente();

  // Búsqueda instantánea
  const docente = indice[cedula];

  if (!docente) {
    throw new Error(`Docente con cédula ${cedula} no encontrado`);
  }

  return docente;
}

// Uso
const docente = await buscarDocentePorCedula('479678');
console.log(docente.NOMBRE_Y_APELLIDO);
// Output: ABEL ANTONIO ABELLA BELTRAN
```

### 3. Generar Lista para Autocomplete

```typescript
async function generarOpcionesAutocomplete() {
  const indice = await obtenerIndiceDocente();

  return Object.entries(indice).map(([cedula, datos]) => ({
    value: cedula,
    label: `${cedula} - ${datos.NOMBRE_Y_APELLIDO}`,
    territorial: datos.TERRITORIAL,
    vinculacion: datos.VINCULACION
  }));
}

// Uso en componente Angular
export class BusquedaDocenteComponent implements OnInit {
  opcionesAutocomplete: any[] = [];

  async ngOnInit() {
    this.opcionesAutocomplete = await generarOpcionesAutocomplete();
  }
}
```

### 4. Filtrar Docentes por Criterios

```typescript
async function filtrarDocentes(filtros: {
  territorial?: string;
  vinculacion?: string;
  nivelFormacion?: string;
}) {
  const indice = await obtenerIndiceDocente();

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

// Uso
const docentesOcasionalesMeta = await filtrarDocentes({
  territorial: 'META',
  vinculacion: 'Ocasional'
});
```

### 5. Servicio Angular con Caché

```typescript
import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable, of } from 'rxjs';
import { tap, map } from 'rxjs/operators';

interface IndiceDocente {
  [cedula: string]: any;
}

@Injectable({
  providedIn: 'root'
})
export class IndiceDocenteService {
  private cache: IndiceDocente | null = null;
  private cacheTimestamp: number = 0;
  private cacheDuration = 5 * 60 * 1000; // 5 minutos

  constructor(private http: HttpClient) {}

  getIndice(forceRefresh = false): Observable<IndiceDocente> {
    const now = Date.now();

    // Retornar cache si está vigente
    if (!forceRefresh &&
        this.cache &&
        (now - this.cacheTimestamp) < this.cacheDuration) {
      return of(this.cache);
    }

    // Obtener del servidor
    return this.http.get<any>('http://localhost:3000/api/v2/listados/indice')
      .pipe(
        map(response => response.indice),
        tap(indice => {
          this.cache = indice;
          this.cacheTimestamp = now;
        })
      );
  }

  buscarPorCedula(cedula: string): Observable<any> {
    return this.getIndice().pipe(
      map(indice => indice[cedula] || null)
    );
  }

  invalidarCache() {
    this.cache = null;
    this.cacheTimestamp = 0;
  }
}
```

### 6. Componente de Búsqueda Completo

```typescript
import { Component, OnInit } from '@angular/core';
import { FormControl } from '@angular/forms';
import { debounceTime, distinctUntilChanged, switchMap } from 'rxjs/operators';

@Component({
  selector: 'app-busqueda-docente',
  template: `
    <mat-form-field>
      <mat-label>Buscar Docente</mat-label>
      <input
        matInput
        [formControl]="searchControl"
        [matAutocomplete]="auto"
        placeholder="Ingrese cédula o nombre..."
      />
      <mat-autocomplete #auto="matAutocomplete" (optionSelected)="onSeleccion($event)">
        <mat-option *ngFor="let opcion of opcionesFiltradas" [value]="opcion.cedula">
          {{ opcion.label }}
        </mat-option>
      </mat-autocomplete>
    </mat-form-field>

    <div *ngIf="docenteSeleccionado" class="docente-info">
      <h3>{{ docenteSeleccionado.NOMBRE_Y_APELLIDO }}</h3>
      <p><strong>Cédula:</strong> {{ docenteSeleccionado.DOCUMENTO_DE_IDENTIDAD }}</p>
      <p><strong>Territorial:</strong> {{ docenteSeleccionado.TERRITORIAL }}</p>
      <p><strong>Vinculación:</strong> {{ docenteSeleccionado.VINCULACION }}</p>
      <p><strong>Categoría:</strong> {{ docenteSeleccionado.CATEGORIA }}</p>
    </div>
  `
})
export class BusquedaDocenteComponent implements OnInit {
  searchControl = new FormControl('');
  opcionesFiltradas: any[] = [];
  docenteSeleccionado: any = null;
  private indiceCompleto: any = {};

  constructor(private indiceService: IndiceDocenteService) {}

  ngOnInit() {
    // Cargar índice al iniciar
    this.indiceService.getIndice().subscribe(indice => {
      this.indiceCompleto = indice;
    });

    // Configurar búsqueda con debounce
    this.searchControl.valueChanges
      .pipe(
        debounceTime(300),
        distinctUntilChanged()
      )
      .subscribe(query => this.filtrarOpciones(query));
  }

  filtrarOpciones(query: string) {
    if (!query || query.length < 2) {
      this.opcionesFiltradas = [];
      return;
    }

    const queryLower = query.toLowerCase();

    this.opcionesFiltradas = Object.entries(this.indiceCompleto)
      .filter(([cedula, datos]: [string, any]) =>
        cedula.includes(query) ||
        datos.NOMBRE_Y_APELLIDO.toLowerCase().includes(queryLower)
      )
      .map(([cedula, datos]: [string, any]) => ({
        cedula,
        label: `${cedula} - ${datos.NOMBRE_Y_APELLIDO}`,
        datos
      }))
      .slice(0, 10); // Limitar a 10 resultados
  }

  onSeleccion(event: any) {
    const cedula = event.option.value;
    this.docenteSeleccionado = this.indiceCompleto[cedula];
  }
}
```

---

## Consideraciones de Rendimiento

### Tamaño del Payload
- **~256 docentes**: ~310 KB
- **Compresión gzip**: ~50-60 KB
- **Tiempo de descarga**: < 1 segundo en conexiones normales

### Caché Recomendado
- **Frontend**: 5-10 minutos en memoria
- **Service Worker**: Opcional para offline
- **Invalidación**: Al cargar nuevo CSV

### Optimizaciones
1. **Lazy loading**: Cargar solo al necesitarse
2. **Compresión**: El servidor ya envía con gzip
3. **Paginación**: No requerida (payload pequeño)
4. **Búsqueda local**: Usar el índice descargado

---

## Notas Importantes

1. **Actualización del Índice**: El índice se actualiza automáticamente cada vez que se carga `ListadoGeneralDocente.csv`
2. **Merge Inteligente**: Las cargas subsecuentes hacen merge preservando campos existentes
3. **Versionado**: OpenKM mantiene historial de versiones del JSON
4. **Performance**: Búsqueda O(1) por cédula gracias a la estructura plana

---

## Troubleshooting

### Error 404: Índice no existe
**Causa**: No se ha cargado `ListadoGeneralDocente.csv`
**Solución**: Cargar el CSV vía `POST /api/v2/listados/cargar`

### Payload muy grande
**Causa**: Más de 1000 docentes
**Solución**: Implementar paginación o endpoint de búsqueda específica

### Datos desactualizados
**Causa**: Caché del frontend
**Solución**: Invalidar caché o forzar refresh

---

## Relacionado

- [11_INDICE_DOCENTE.md](11_INDICE_DOCENTE.md): Documentación completa del índice docente
- [03_SERVICIOS_Y_HANDLERS.md](03_SERVICIOS_Y_HANDLERS.md): Documentación de handlers y services
- Endpoint de carga: `POST /api/v2/listados/cargar`
