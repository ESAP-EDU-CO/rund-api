# GENERAR UN JSON A PARTIR DEL LISTADO GENERAL DOCENTE
## INSTRUCCIONES
Crea las funciones necesarias, en el flujo actual de **rund-api**, que permita la generación de un archivo JSON llamado `indice_docente.json`, que contendrá la información general de los profesoras y profesores que se carguen a través de **rund-mgp**.

Se deben seguir los siguientes pasos:
1. Cuando el usuario cargue el archivo `ListadoGeneralDocente.csv` desde **rund-mgp**, en **rund-api** la función `loadList()` del archivo `FileHandlers.php` debe detectarlo con las condiciones:
  - `$nombreArchivo` === `ListadoGeneralDocente.csv`
  - `$tipo` === `LISTADO_DE_DOCENTES`
2. Se debe crear una función llamada `generaIndiceJson()` que recibirá el contenido del CSV y lo convertirá a un formato JSON.
3. El JSON resultante debe tener una estructura similar a:
```json
[
  {
    "479678": {
      "VINCULACION": "Ocasional",
      "NOMBRE_Y_APELLIDO": "ABEL ANTONIO ABELLA BELTRAN",
      "TERRITORIAL": "META",
      "CATEGORIA": "Asociado",
      "NUCLEO_TEMATICO": "Labores de docencia y las demás actividades propias de los docentes de tiempo completo establecidas en el Estatuto Profesoral de la ESAP",
      "NIVEL_DE_FORMACION": "Maestría",
      (...)
    }
  },
  {
    (...)
  }
]
```
En el se usará el valor de la columna **Documento de identidad** del CSV como elemento clave para estructurar el JSON.
4. Los encabezados del CSV se deben convertir (por ejemplo, de "Núcleo temático" a "NUCLEO_TEMATICO") usando el archivo `labels.json` que está en **rund-core**. Para obtener ese archivo, lee la documentación de **rund-api**, en `04_INTEGRACION_OPENKM.md` en la sección **B. CONFIG FILE GETTERS**.
**NOTA:** Para que puedas elaborar estas tareas, puedes encontrar los archivos `labels.json` y `ListadoGeneralDocente.csv` en `./pruebas/`.
5. Luego de que la nueva función `generaIndiceJson()` genera el JSON, se debe crear un flujo paralelo para almacenarlo como archivo llamado `indice_docente.json` en la ruta indicada por `Config::TAX_LISTADOS` en la carpeta `INDICE_DOCENTE`.
6. Si el archivo ya existe, se deberá actualizar el documento, usando el flujo de _checkin/checkout_ que usa OpenKM.

## RECOMENDACIONES FINALES
- Revisa la documentación necesaria en **rund-api** para realizar todos los cambios.
- Realiza las preguntas que requieras para tener todo claro, antes de modificar el código.
- Realiza los cambios de documentación necesarios para incluir esta funcionalidad.
- Finaliza con la propuesta de commit en Github del proceso.