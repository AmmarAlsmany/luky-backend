#!/bin/bash

# Luky Platform - Production Deployment Script
# Usage: ./deploy.sh

echo "🚀 Starting Luky Platform Deployment..."
echo "========================================="

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Check if we're in the right directory
if [ ! -f "artisan" ]; then
    echo -e "${RED}Error: artisan file not found. Are you in the Laravel root directory?${NC}"
    exit 1
fi

# 1. Pull latest code (if using Git)
echo -e "\n${YELLOW}📥 Pulling latest code...${NC}"
if [ -d ".git" ]; then
    git pull origin main
else
    echo "Not a git repository, skipping..."
fi

# 2. Install/Update Composer dependencies
echo -e "\n${YELLOW}📦 Installing Composer dependencies...${NC}"
composer install --optimize-autoloader --no-dev

# 3. Install/Update NPM dependencies
echo -e "\n${YELLOW}📦 Installing NPM dependencies...${NC}"
npm install

# 4. Build frontend assets
echo -e "\n${YELLOW}🏗️  Building frontend assets...${NC}"
npm run build

# 5. Run database migrations
echo -e "\n${YELLOW}🗄️  Running database migrations...${NC}"
php artisan migrate --force

# 6. Clear all caches
echo -e "\n${YELLOW}🧹 Clearing caches...${NC}"
php artisan config:clear
php artisan cache:clear
php artisan route:clear
php artisan view:clear

# 7. Cache for production
echo -e "\n${YELLOW}⚡ Caching for production...${NC}"
php artisan config:cache
php artisan route:cache
php artisan view:cache

# 8. Set correct permissions
echo -e "\n${YELLOW}🔐 Setting permissions...${NC}"
chmod -R 775 storage bootstrap/cache
chmod 600 storage/app/firebase/*.json 2>/dev/null || true

# 9. Restart queue workers (if using supervisor)
echo -e "\n${YELLOW}🔄 Restarting queue workers...${NC}"
if command -v supervisorctl &> /dev/null; then
    sudo supervisorctl restart luky-worker:* 2>/dev/null || echo "Supervisor not configured"
else
    echo "Supervisor not installed, skipping..."
fi

# 10. Reload web server
echo -e "\n${YELLOW}🌐 Reloading web server...${NC}"
if command -v systemctl &> /dev/null; then
    sudo systemctl reload nginx 2>/dev/null || sudo systemctl reload apache2 2>/dev/null || echo "Could not reload web server"
else
    echo "Systemctl not available, please reload web server manually"
fi

echo -e "\n${GREEN}=========================================${NC}"
echo -e "${GREEN}✅ Deployment completed successfully!${NC}"
echo -e "${GREEN}=========================================${NC}"
echo -e "\n${YELLOW}📝 Post-deployment checklist:${NC}"
echo "  1. Visit https://techspireksa.com"
echo "  2. Check application logs: tail -f storage/logs/laravel.log"
echo "  3. Test major features"
echo "  4. Monitor error logs"
echo ""
