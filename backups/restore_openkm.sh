#!/bin/bash

# Script de restauración para contenedor Docker OpenKM
# Versión: 2.0
# Descripción: Restaura backup de OpenKM con verificaciones y logging

# ==================== CONFIGURACIÓN ====================
CONTAINER_NAME="rund-core"
COMPOSE_FILE="docker-compose.yml" # Cambia si tu archivo tiene otro nombre
BACKUP_DIR="$HOME/backups/openkm"
LOG_FILE="$BACKUP_DIR/restore.log"
TOMCAT_PATH="/opt/tomcat"
TEMP_PATH="/tmp"

# ==================== FUNCIONES ====================

# Función para mostrar ayuda
show_help() {
  echo "Uso: $0 [OPCIONES] <archivo_backup>"
  echo ""
  echo "Opciones:"
  echo "  -h, --help          Mostrar esta ayuda"
  echo "  -f, --file         Especificar archivo de backup"
  echo "  -c, --container    Nombre del contenedor (default: $CONTAINER_NAME)"
  echo "  -d, --compose-dir  Directorio donde está docker-compose.yml"
  echo "  --dry-run          Simular restauración sin ejecutar"
  echo ""
  echo "Ejemplos:"
  echo "  $0 rund_openkm-data-backup-20250711_143052.tar"
  echo "  $0 -f ~/backups/openkm/rund_openkm-data-backup-20250711_143052.tar"
  echo "  $0 --dry-run rund_openkm-data-backup-20250711_143052.tar"
}

# Función para logging
log_message() {
  local level=$1
  local message=$2
  local timestamp=$(date '+%Y-%m-%d %H:%M:%S')
  echo "[$timestamp] [$level] $message" | tee -a "$LOG_FILE"
}

# Función para verificar prerrequisitos
check_prerequisites() {
  log_message "INFO" "Verificando prerrequisitos..."

  # Verificar Docker
  if ! command -v docker &>/dev/null; then
    log_message "ERROR" "Docker no está instalado o no está en el PATH"
    return 1
  fi

  # Verificar Docker Compose
  if ! command -v docker-compose &>/dev/null && ! docker compose version &>/dev/null; then
    log_message "ERROR" "Docker Compose no está disponible"
    return 1
  fi

  # Verificar que el contenedor existe
  if ! docker ps -a --format "table {{.Names}}" | grep -q "^$CONTAINER_NAME$"; then
    log_message "ERROR" "El contenedor '$CONTAINER_NAME' no existe"
    return 1
  fi

  log_message "INFO" "Prerrequisitos verificados correctamente"
  return 0
}

# Función para verificar archivo de backup
verify_backup_file() {
  local backup_file=$1

  log_message "INFO" "Verificando archivo de backup: $backup_file"

  # Verificar que el archivo existe
  if [[ ! -f "$backup_file" ]]; then
    log_message "ERROR" "El archivo de backup no existe: $backup_file"
    return 1
  fi

  # Verificar que es un archivo TAR válido
  if ! tar -tf "$backup_file" >/dev/null 2>&1; then
    log_message "ERROR" "El archivo de backup no es un TAR válido o está corrupto"
    return 1
  fi

  # Mostrar información del archivo (compatible con macOS y Linux)
  local file_size
  if [[ "$OSTYPE" == "darwin"* ]]; then
    file_size=$(stat -f %z "$backup_file")
  else
    file_size=$(stat -c%s "$backup_file")
  fi
  local file_size_mb=$((file_size / 1024 / 1024))
  log_message "INFO" "Tamaño del archivo: ${file_size_mb}MB"

  # Verificar contenido del TAR
  local tar_content=$(tar -tf "$backup_file" | head -5)
  log_message "INFO" "Primeros archivos en el backup:"
  echo "$tar_content" | while IFS= read -r line; do
    log_message "INFO" "  $line"
  done

  log_message "INFO" "Archivo de backup verificado correctamente"
  return 0
}

# Función para crear backup de seguridad antes de restaurar
create_safety_backup() {
  log_message "INFO" "Creando backup de seguridad antes de restaurar..."

  local safety_backup_name="safety-backup-$(date '+%Y%m%d_%H%M%S').tar"
  local safety_backup_path="$BACKUP_DIR/$safety_backup_name"

  if docker run --rm -v rund_openkm-data:/data -v "$BACKUP_DIR":/backup ubuntu tar cvf "/backup/$safety_backup_name" /data >/dev/null 2>&1; then
    log_message "INFO" "Backup de seguridad creado: $safety_backup_path"
    echo "$safety_backup_path" # Retornar la ruta para uso posterior
    return 0
  else
    log_message "WARN" "No se pudo crear el backup de seguridad"
    return 1
  fi
}

# Función para detener servicios
stop_services() {
  log_message "INFO" "Deteniendo servicios Docker Compose..."

  if command -v docker-compose &>/dev/null; then
    COMPOSE_CMD="docker-compose"
  else
    COMPOSE_CMD="docker compose"
  fi

  if $COMPOSE_CMD down; then
    log_message "INFO" "Servicios detenidos correctamente"
    return 0
  else
    log_message "ERROR" "Error al detener servicios"
    return 1
  fi
}

# Función para iniciar servicios
start_services() {
  log_message "INFO" "Iniciando servicios Docker Compose..."

  if command -v docker-compose &>/dev/null; then
    COMPOSE_CMD="docker-compose"
  else
    COMPOSE_CMD="docker compose"
  fi

  if $COMPOSE_CMD up -d; then
    log_message "INFO" "Servicios iniciados correctamente"
    return 0
  else
    log_message "ERROR" "Error al iniciar servicios"
    return 1
  fi
}

# Función para restaurar backup
restore_backup() {
  local backup_file=$1
  local backup_filename=$(basename "$backup_file")

  log_message "INFO" "Iniciando proceso de restauración..."

  # Verificar que el contenedor está ejecutándose
  if ! docker ps --format "table {{.Names}}" | grep -q "^$CONTAINER_NAME$"; then
    log_message "ERROR" "El contenedor '$CONTAINER_NAME' no está ejecutándose"
    return 1
  fi

  # Copiar archivo al contenedor
  log_message "INFO" "Copiando archivo de backup al contenedor..."
  if ! docker cp "$backup_file" "$CONTAINER_NAME:$TEMP_PATH/$backup_filename"; then
    log_message "ERROR" "Error al copiar archivo al contenedor"
    return 1
  fi

  # Ejecutar restauración en el contenedor (unificado en un solo comando)
  log_message "INFO" "Ejecutando restauración en el contenedor..."
  if docker exec "$CONTAINER_NAME" bash -c "
        echo 'Extrayendo archivo de backup...' &&
        tar xvf $TEMP_PATH/$backup_filename -C $TOMCAT_PATH --strip 1 &&
        echo 'Limpiando archivo temporal...' &&
        rm $TEMP_PATH/$backup_filename &&
        echo 'Restauración completada en el contenedor'
    "; then
    log_message "INFO" "Restauración ejecutada correctamente en el contenedor"
    return 0
  else
    log_message "ERROR" "Error durante la restauración en el contenedor"
    return 1
  fi
}

# Función para verificar estado post-restauración
verify_restoration() {
  log_message "INFO" "Verificando estado post-restauración..."

  # Esperar un poco para que los servicios se estabilicen
  sleep 10

  # Verificar que los contenedores están corriendo
  if docker ps --format "table {{.Names}}" | grep -q "^$CONTAINER_NAME$"; then
    log_message "INFO" "Contenedor principal está ejecutándose"
  else
    log_message "WARN" "El contenedor principal no está ejecutándose"
  fi

  # Verificar logs del contenedor (últimas 10 líneas)
  log_message "INFO" "Últimas líneas del log del contenedor:"
  docker logs --tail 10 "$CONTAINER_NAME" 2>&1 | while IFS= read -r line; do
    log_message "INFO" "  $line"
  done

  return 0
}

# ==================== SCRIPT PRINCIPAL ====================

# Variables para opciones
DRY_RUN=false
COMPOSE_DIR=""
BACKUP_FILE=""

# Procesar argumentos
while [[ $# -gt 0 ]]; do
  case $1 in
  -h | --help)
    show_help
    exit 0
    ;;
  -f | --file)
    BACKUP_FILE="$2"
    shift 2
    ;;
  -c | --container)
    CONTAINER_NAME="$2"
    shift 2
    ;;
  -d | --compose-dir)
    COMPOSE_DIR="$2"
    shift 2
    ;;
  --dry-run)
    DRY_RUN=true
    shift
    ;;
  -*)
    log_message "ERROR" "Opción desconocida: $1"
    show_help
    exit 1
    ;;
  *)
    if [[ -z "$BACKUP_FILE" ]]; then
      BACKUP_FILE="$1"
    fi
    shift
    ;;
  esac
done

# Verificar que se proporcionó archivo de backup
if [[ -z "$BACKUP_FILE" ]]; then
  log_message "ERROR" "Debe especificar un archivo de backup"
  show_help
  exit 1
fi

# Cambiar al directorio de compose si se especificó
if [[ -n "$COMPOSE_DIR" ]]; then
  cd "$COMPOSE_DIR" || {
    log_message "ERROR" "No se puede cambiar al directorio: $COMPOSE_DIR"
    exit 1
  }
fi

# Crear directorio de backup si no existe
mkdir -p "$BACKUP_DIR"

# Convertir ruta relativa a absoluta
if [[ ! "$BACKUP_FILE" = /* ]]; then
  # Si no es ruta absoluta, buscar en directorio de backups
  if [[ -f "$BACKUP_DIR/$BACKUP_FILE" ]]; then
    BACKUP_FILE="$BACKUP_DIR/$BACKUP_FILE"
  elif [[ -f "$BACKUP_FILE" ]]; then
    BACKUP_FILE="$(pwd)/$BACKUP_FILE"
  fi
fi

log_message "INFO" "==================== INICIANDO RESTAURACIÓN ===================="
log_message "INFO" "Archivo de backup: $BACKUP_FILE"
log_message "INFO" "Contenedor: $CONTAINER_NAME"
log_message "INFO" "Modo dry-run: $DRY_RUN"

# Verificar prerrequisitos
if ! check_prerequisites; then
  exit 1
fi

# Verificar archivo de backup
if ! verify_backup_file "$BACKUP_FILE"; then
  exit 1
fi

if [[ "$DRY_RUN" == "true" ]]; then
  log_message "INFO" "MODO DRY-RUN: La restauración sería exitosa"
  log_message "INFO" "Comandos que se ejecutarían:"
  log_message "INFO" "  1. Crear backup de seguridad"
  log_message "INFO" "  2. Detener servicios con docker-compose down"
  log_message "INFO" "  3. Iniciar servicios con docker-compose up -d"
  log_message "INFO" "  4. Copiar $BACKUP_FILE al contenedor"
  log_message "INFO" "  5. Extraer backup en $TOMCAT_PATH"
  log_message "INFO" "  6. Limpiar archivo temporal"
  log_message "INFO" "  7. Verificar estado de servicios"
  exit 0
fi

# Confirmación antes de proceder
echo ""
echo "⚠️  ADVERTENCIA: Esta operación sobrescribirá los datos actuales de OpenKM"
echo "📁 Archivo de backup: $BACKUP_FILE"
echo "🐳 Contenedor: $CONTAINER_NAME"
echo ""
read -p "¿Está seguro de que desea continuar? (y/N): " -n 1 -r
echo ""
if [[ ! $REPLY =~ ^[Yy]$ ]]; then
  log_message "INFO" "Restauración cancelada por el usuario"
  exit 0
fi

# Crear backup de seguridad
SAFETY_BACKUP=$(create_safety_backup)
if [[ $? -eq 0 ]]; then
  log_message "INFO" "Backup de seguridad disponible en: $SAFETY_BACKUP"
fi

# Detener servicios
if ! stop_services; then
  log_message "ERROR" "Error crítico: No se pudieron detener los servicios"
  exit 1
fi

# Iniciar servicios
if ! start_services; then
  log_message "ERROR" "Error crítico: No se pudieron iniciar los servicios"
  exit 1
fi

# Esperar a que el contenedor esté listo
log_message "INFO" "Esperando a que el contenedor esté listo..."
sleep 15

# Ejecutar restauración
if ! restore_backup "$BACKUP_FILE"; then
  log_message "ERROR" "Error en la restauración"
  if [[ -n "$SAFETY_BACKUP" ]]; then
    log_message "INFO" "Backup de seguridad disponible en: $SAFETY_BACKUP"
  fi
  exit 1
fi

# Reiniciar servicios para aplicar cambios
log_message "INFO" "Reiniciando servicios para aplicar cambios..."
stop_services
start_services

# Verificar estado post-restauración
verify_restoration

log_message "INFO" "==================== RESTAURACIÓN COMPLETADA ===================="
log_message "INFO" "La restauración se completó exitosamente"
log_message "INFO" "Archivo restaurado: $BACKUP_FILE"
if [[ -n "$SAFETY_BACKUP" ]]; then
  log_message "INFO" "Backup de seguridad: $SAFETY_BACKUP"
fi

echo ""
echo "✅ Restauración completada exitosamente"
echo "📝 Revisa los logs en: $LOG_FILE"
echo "🔍 Verifica que la aplicación funcione correctamente"
if [[ -n "$SAFETY_BACKUP" ]]; then
  echo "🛡️  Backup de seguridad: $SAFETY_BACKUP"
fi

exit 0
