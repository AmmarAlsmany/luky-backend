#!/bin/bash

# ==================================================
# LUKY BACKEND - PRODUCTION DEPLOYMENT SCRIPT
# ==================================================
# This script automates the deployment process
# Run this script on your production server
# ==================================================

set -e  # Exit on error

echo "======================================"
echo "LUKY Backend - Production Deployment"
echo "======================================"
echo ""

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Configuration
PROJECT_DIR="/var/www/luky-backend"
WEB_USER="www-data"
PHP_VERSION="8.3"

echo -e "${YELLOW}Step 1: Pulling latest code from repository...${NC}"
git pull origin main

echo -e "${YELLOW}Step 2: Installing/Updating Composer dependencies...${NC}"
composer install --no-dev --optimize-autoloader --no-interaction

echo -e "${YELLOW}Step 3: Installing/Updating NPM dependencies...${NC}"
npm ci --production

echo -e "${YELLOW}Step 4: Building frontend assets...${NC}"
npm run build

echo -e "${YELLOW}Step 5: Setting up environment...${NC}"
if [ ! -f .env ]; then
    echo -e "${RED}ERROR: .env file not found!${NC}"
    echo "Please copy .env.production to .env and configure it."
    exit 1
fi

echo -e "${YELLOW}Step 6: Generating application key (if needed)...${NC}"
php artisan key:generate --force

echo -e "${YELLOW}Step 7: Running database migrations...${NC}"
php artisan migrate --force

echo -e "${YELLOW}Step 8: Seeding production data (roles, cities, categories, super admin)...${NC}"
php artisan db:seed --class=ProductionSeeder --force

echo -e "${YELLOW}Step 8: Clearing and optimizing caches...${NC}"
php artisan config:clear
php artisan route:clear
php artisan view:clear
php artisan cache:clear

php artisan config:cache
php artisan route:cache
php artisan view:cache

echo -e "${YELLOW}Step 9: Optimizing autoloader...${NC}"
composer dump-autoload --optimize

echo -e "${YELLOW}Step 10: Creating storage links...${NC}"
php artisan storage:link

echo -e "${YELLOW}Step 11: Setting proper file permissions...${NC}"
chown -R $WEB_USER:$WEB_USER $PROJECT_DIR
chmod -R 755 $PROJECT_DIR
chmod -R 775 $PROJECT_DIR/storage
chmod -R 775 $PROJECT_DIR/bootstrap/cache
chmod 600 $PROJECT_DIR/.env

echo -e "${YELLOW}Step 12: Restarting services...${NC}"
# Restart PHP-FPM
sudo systemctl restart php${PHP_VERSION}-fpm

# Restart queue workers
php artisan queue:restart

# Restart supervisor (if using)
if command -v supervisorctl &> /dev/null; then
    sudo supervisorctl reread
    sudo supervisorctl update
    sudo supervisorctl restart all
fi

echo ""
echo -e "${GREEN}======================================"
echo "Deployment completed successfully! ✓"
echo "======================================${NC}"
echo ""
echo "Next steps:"
echo "1. Verify application is running: curl https://your-domain.com"
echo "2. Check logs: tail -f storage/logs/laravel.log"
echo "3. Monitor queue workers: php artisan queue:work --daemon"
echo ""
