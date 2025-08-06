#!/bin/bash

# Script de backup para contenedor Docker OpenKM
# Versión: 3.0
# Descripción: Realiza backup de volumen Docker con verificaciones de integridad

# ==================== CONFIGURACIÓN ====================
VOLUME_NAME="rund_openkm-data"
BACKUP_DIR="$HOME/backups/openkm" # Usar directorio del usuario en lugar de /opt
LOG_FILE="$BACKUP_DIR/backup.log"
MIN_SIZE_MB=500
RETENTION_DAYS=30 # Días para mantener backups antiguos

# ==================== FUNCIONES ====================

# Función para logging
log_message() {
  local level=$1
  local message=$2
  local timestamp=$(date '+%Y-%m-%d %H:%M:%S')
  echo "[$timestamp] [$level] $message" | tee -a "$LOG_FILE"
}

# Función para verificar tamaño del archivo
check_file_size() {
  local file_path=$1
  local min_size_bytes=$((MIN_SIZE_MB * 1024 * 1024))

  if [[ ! -f "$file_path" ]]; then
    log_message "ERROR" "El archivo $file_path no existe"
    return 1
  fi

  local file_size
  if [[ "$OSTYPE" == "darwin"* ]]; then
    file_size=$(stat -f %z "$file_path")
  else
    file_size=$(stat -c%s "$file_path")
  fi
  local file_size_mb=$((file_size / 1024 / 1024))

  log_message "INFO" "Tamaño del archivo: ${file_size_mb}MB"

  if [[ $file_size -lt $min_size_bytes ]]; then
    log_message "ERROR" "El archivo es demasiado pequeño (${file_size_mb}MB < ${MIN_SIZE_MB}MB)"
    return 1
  fi

  log_message "INFO" "Verificación de tamaño: EXITOSA"
  return 0
}

# Función para verificar integridad del TAR
check_tar_integrity() {
  local tar_file=$1

  log_message "INFO" "Verificando integridad del archivo TAR..."

  if tar -tf "$tar_file" >/dev/null 2>&1; then
    log_message "INFO" "Verificación de integridad: EXITOSA"
    return 0
  else
    log_message "ERROR" "El archivo TAR está corrupto o no es válido"
    return 1
  fi
}

# Función para limpiar backups antiguos
cleanup_old_backups() {
  log_message "INFO" "Limpiando backups antiguos (más de $RETENTION_DAYS días)..."

  local deleted_count=0
  while IFS= read -r -d '' file; do
    rm -f "$file"
    deleted_count=$((deleted_count + 1))
    log_message "INFO" "Eliminado: $(basename "$file")"
  done < <(find "$BACKUP_DIR" -name "rund_openkm-data-backup-*.tar" -type f -mtime +$RETENTION_DAYS -print0)

  if [[ $deleted_count -eq 0 ]]; then
    log_message "INFO" "No hay backups antiguos para eliminar"
  else
    log_message "INFO" "Eliminados $deleted_count backups antiguos"
  fi
}

# Función para enviar notificación (opcional)
send_notification() {
  local status=$1
  local message=$2

  # Descomenta y configura según tu método de notificación preferido
  # echo "$message" | mail -s "Backup OpenKM - $status" admin@example.com
  # curl -X POST -H 'Content-type: application/json' --data '{"text":"'"$message"'"}' YOUR_SLACK_WEBHOOK_URL
}

# Función para mostrar información final
show_final_info() {
  local final_size
  if [[ "$OSTYPE" == "darwin"* ]]; then
    final_size=$(stat -f %z "$BACKUP_FILE")
  else
    final_size=$(stat -c%s "$BACKUP_FILE")
  fi
  FINAL_SIZE_MB=$((final_size / 1024 / 1024))

  log_message "INFO" "==================== BACKUP COMPLETADO ===================="
  log_message "INFO" "Archivo: $BACKUP_FILE"
  log_message "INFO" "Tamaño final: ${FINAL_SIZE_MB}MB"
  log_message "INFO" "Tiempo total: ${DURATION} segundos"

  # Enviar notificación de éxito
  send_notification "SUCCESS" "Backup de OpenKM completado exitosamente: ${FINAL_SIZE_MB}MB"
}

# ==================== SCRIPT PRINCIPAL ====================

log_message "INFO" "==================== INICIANDO BACKUP ===================="

# Verificar que Docker esté disponible
if ! command -v docker &>/dev/null; then
  log_message "ERROR" "Docker no está instalado o no está en el PATH"
  exit 1
fi

# Verificar que el volumen existe
if ! docker volume ls | grep -q "$VOLUME_NAME"; then
  log_message "ERROR" "El volumen $VOLUME_NAME no existe"
  exit 1
fi

# Crear directorio de backup si no existe
if [[ ! -d "$BACKUP_DIR" ]]; then
  mkdir -p "$BACKUP_DIR"
  log_message "INFO" "Creado directorio de backup: $BACKUP_DIR"
fi

# Generar timestamp para el nombre del archivo
TIMESTAMP=$(date '+%Y%m%d_%H%M%S')
BACKUP_FILE="$BACKUP_DIR/rund_openkm-data-backup-$TIMESTAMP.tar"

log_message "INFO" "Iniciando backup del volumen: $VOLUME_NAME"
log_message "INFO" "Archivo de destino: $BACKUP_FILE"

# Realizar el backup
log_message "INFO" "Ejecutando comando de backup..."
START_TIME=$(date +%s)

if docker run --rm -v "$VOLUME_NAME":/data -v "$BACKUP_DIR":/backup ubuntu tar cvf "/backup/$(basename "$BACKUP_FILE")" /data >/dev/null 2>&1; then
  END_TIME=$(date +%s)
  DURATION=$((END_TIME - START_TIME))
  log_message "INFO" "Backup completado exitosamente en ${DURATION} segundos"
else
  log_message "ERROR" "Error al crear el backup"
  # Limpiar archivo parcial si existe
  [[ -f "$BACKUP_FILE" ]] && rm -f "$BACKUP_FILE"
  send_notification "ERROR" "Error al crear backup de OpenKM"
  exit 1
fi

# Verificar tamaño del archivo
if ! check_file_size "$BACKUP_FILE"; then
  log_message "ERROR" "Verificación de tamaño falló, eliminando archivo de backup"
  rm -f "$BACKUP_FILE"
  send_notification "ERROR" "Backup de OpenKM falló: archivo demasiado pequeño"
  exit 1
fi

# Verificar integridad del TAR
if ! check_tar_integrity "$BACKUP_FILE"; then
  log_message "ERROR" "Verificación de integridad falló, eliminando archivo de backup"
  rm -f "$BACKUP_FILE"
  send_notification "ERROR" "Backup de OpenKM falló: archivo corrupto"
  exit 1
fi

# Limpiar backups antiguos
cleanup_old_backups

# Mostrar información final
show_final_info

log_message "INFO" "Proceso finalizado correctamente"
exit 0
