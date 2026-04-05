#!/bin/bash
# Backup MySQL diario do Conecta CRM
DATE=$(date +%Y%m%d_%H%M%S)
BACKUP_DIR=/var/www/hux-crm-association/backups
mkdir -p $BACKUP_DIR

# Le credenciais do .env
source <(grep -E '^(DB_NAME|DB_USER|DB_PASS|DB_HOST)=' /var/www/hux-crm-association/.env)
DB_HOST=${DB_HOST:-127.0.0.1}

# Dump comprimido
mysqldump -h $DB_HOST -u $DB_USER -p"$DB_PASS" --single-transaction --quick --lock-tables=false $DB_NAME 2>/dev/null | gzip > $BACKUP_DIR/conecta_crm_$DATE.sql.gz

if [ $? -eq 0 ] && [ -s $BACKUP_DIR/conecta_crm_$DATE.sql.gz ]; then
  SIZE=$(du -h $BACKUP_DIR/conecta_crm_$DATE.sql.gz | cut -f1)
  echo "[$(date +'%Y-%m-%d %H:%M:%S')] Backup OK: conecta_crm_$DATE.sql.gz ($SIZE)" >> $BACKUP_DIR/backup.log
else
  echo "[$(date +'%Y-%m-%d %H:%M:%S')] Backup FALHOU" >> $BACKUP_DIR/backup.log
  exit 1
fi

# Rotaciona: mantem apenas os 7 ultimos
ls -t $BACKUP_DIR/*.sql.gz 2>/dev/null | tail -n +8 | xargs rm -f 2>/dev/null
