# ==================== CONFIGURACIÓN ====================
VOLUME_NAME="rund_openkm-data"
BACKUP_DIR="./backups/"

# Generar timestamp para el nombre del archivo
TIMESTAMP=$(date '+%Y%m%d_%H%M%S')
BACKUP_FILE="$BACKUP_DIR/rund_openkm-data-backup-$TIMESTAMP.tar"

echo "INFO" "Iniciando backup del volumen: $VOLUME_NAME"
echo "INFO" "Archivo de destino: $BACKUP_FILE"

# Realizar el backup
echo "INFO" "Ejecutando comando de backup..."
START_TIME=$(date +%s)

if docker run --rm -v "$VOLUME_NAME":/data -v "$BACKUP_DIR":/backup ubuntu tar cvf "/backup/$(basename "$BACKUP_FILE")" /data >/dev/null 2>&1; then
  END_TIME=$(date +%s)
  DURATION=$((END_TIME - START_TIME))
  echo "INFO" "Backup completado exitosamente en ${DURATION} segundos"
else
  echo "ERROR" "Error al crear el backup"
  # Limpiar archivo parcial si existe
  [[ -f "$BACKUP_FILE" ]] && rm -f "$BACKUP_FILE"
  send_notification "ERROR" "Error al crear backup de OpenKM"
  exit 1
fi
