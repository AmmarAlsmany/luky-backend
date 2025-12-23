#!/bin/bash

# ==================================================
# LUKY BACKEND - AUTOMATED BACKUP SCRIPT
# ==================================================
# This script backs up database and important files
# Add to crontab: 0 2 * * * /var/www/luky-backend/backup.sh
# ==================================================

set -e

# Configuration
PROJECT_DIR="/var/www/luky-backend"
BACKUP_DIR="/var/backups/luky-backend"
DATE=$(date +%Y%m%d_%H%M%S)
RETENTION_DAYS=7

# Database Configuration
DB_NAME="luky_production"
DB_USER="luky_user"
DB_PASSWORD="YOUR_DB_PASSWORD"  # Better: Use .pgpass file

# Create backup directory
mkdir -p $BACKUP_DIR

echo "======================================"
echo "Starting Backup - $DATE"
echo "======================================"

# 1. Database Backup
echo "Backing up database..."
PGPASSWORD=$DB_PASSWORD pg_dump -U $DB_USER -h localhost $DB_NAME | gzip > $BACKUP_DIR/database_$DATE.sql.gz
echo "✓ Database backup completed"

# 2. Storage Files Backup
echo "Backing up storage files..."
tar -czf $BACKUP_DIR/storage_$DATE.tar.gz -C $PROJECT_DIR storage/app
echo "✓ Storage files backup completed"

# 3. Environment File Backup
echo "Backing up .env file..."
cp $PROJECT_DIR/.env $BACKUP_DIR/env_$DATE.txt
echo "✓ Environment file backup completed"

# 4. Public Uploads Backup (if any)
if [ -d "$PROJECT_DIR/public/uploads" ]; then
    echo "Backing up public uploads..."
    tar -czf $BACKUP_DIR/uploads_$DATE.tar.gz -C $PROJECT_DIR/public uploads
    echo "✓ Public uploads backup completed"
fi

# 5. Delete old backups (older than RETENTION_DAYS)
echo "Cleaning up old backups (keeping last $RETENTION_DAYS days)..."
find $BACKUP_DIR -name "database_*.sql.gz" -mtime +$RETENTION_DAYS -delete
find $BACKUP_DIR -name "storage_*.tar.gz" -mtime +$RETENTION_DAYS -delete
find $BACKUP_DIR -name "env_*.txt" -mtime +$RETENTION_DAYS -delete
find $BACKUP_DIR -name "uploads_*.tar.gz" -mtime +$RETENTION_DAYS -delete
echo "✓ Old backups cleaned"

# 6. Calculate backup sizes
echo ""
echo "Backup Summary:"
echo "======================================"
du -sh $BACKUP_DIR/database_$DATE.sql.gz
du -sh $BACKUP_DIR/storage_$DATE.tar.gz
du -sh $BACKUP_DIR/env_$DATE.txt
if [ -f "$BACKUP_DIR/uploads_$DATE.tar.gz" ]; then
    du -sh $BACKUP_DIR/uploads_$DATE.tar.gz
fi
echo "======================================"
echo "Total backup size:"
du -sh $BACKUP_DIR
echo ""

echo "✓ Backup completed successfully!"
echo "Backup location: $BACKUP_DIR"

# Optional: Send notification or upload to cloud storage
# aws s3 sync $BACKUP_DIR s3://your-bucket/backups/
