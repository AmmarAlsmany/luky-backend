# Production Deployment Steps

## Prerequisites

- ✅ Redis installed and running (`redis-cli ping` returns PONG)
- ✅ PHP redis extension installed (`php -m | grep redis`)
- ✅ PostgreSQL database ready
- ✅ Environment variables configured

---

## Step 1: Pull Latest Code

```bash
cd /path/to/luky-back-end

# Pull from GitHub
git pull origin main

# Or if you copied files directly, skip this
```

---

## Step 2: Install Dependencies

```bash
# Install PHP dependencies (production mode)
composer install --no-dev --optimize-autoloader

# Clear composer cache if needed
composer clear-cache
```

---

## Step 3: Configure Environment

```bash
# Verify .env file has Redis configured
cat .env | grep CACHE_STORE
# Should show: CACHE_STORE=redis

cat .env | grep QUEUE_CONNECTION
# Should show: QUEUE_CONNECTION=redis

# If not, update .env:
# CACHE_STORE=redis
# QUEUE_CONNECTION=redis
# REDIS_CLIENT=phpredis
# REDIS_HOST=127.0.0.1
# REDIS_PORT=6379
# REDIS_DB=0
# REDIS_CACHE_DB=1
# REDIS_QUEUE_DB=2
# REDIS_SESSION_DB=3
```

---

## Step 4: Verify Redis Connection

```bash
# Test Redis health
php artisan redis:health

# Expected output:
# ✓ Redis connection: OK
# ✓ Redis cache connection: OK
# ✓ Redis queue connection: OK

# If fails, check:
redis-cli ping                    # Should return PONG
php -m | grep redis              # Should show: redis
sudo systemctl status redis      # Should be active
```

---

## Step 5: Upload Firebase Credentials

```bash
# IMPORTANT: Push notifications require Firebase credentials

# 1. Create the firebase directory if it doesn't exist
mkdir -p storage/app/firebase

# 2. Upload your Firebase credentials file
# From your local machine, use scp:
scp storage/app/firebase/luky-96cae-firebase-adminsdk-fbsvc-96f53ee261.json \
    user@server:/path/to/luky-backend/storage/app/firebase/

# 3. Set proper permissions
chmod 600 storage/app/firebase/luky-96cae-firebase-adminsdk-fbsvc-96f53ee261.json

# 4. Verify the file exists
ls -la storage/app/firebase/

# Expected output:
# -rw------- 1 www-data www-data 2345 Dec 23 14:00 luky-96cae-firebase-adminsdk-fbsvc-96f53ee261.json
```

**Note:** Without this file, push notifications will not work. The deployment script will warn you if it's missing.

---

## Step 6: Run Database Migrations

```bash
# Backup database first (IMPORTANT!)
pg_dump -U postgres luky_production > backup_$(date +%Y%m%d_%H%M%S).sql

# Run migrations (adds indexes and new tables)
php artisan migrate --force

# Migrations will create:
# - 50+ performance indexes
# - wallet_transactions table
# - wallet_deposits table
# - withdrawal_requests table
# - provider_pending_changes table
# - Payment deadline column
# - Bank info columns
# - Review approval columns
```

---

## Step 7: Clear Old Caches

```bash
# Clear all Laravel caches
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear

# Clear compiled files
php artisan clear-compiled
```

---

## Step 8: Optimize Laravel

```bash
# Cache configuration
php artisan config:cache

# Cache routes
php artisan route:cache

# Cache views
php artisan view:cache

# Optimize autoloader
composer dump-autoload --optimize
```

---

## Step 9: Warm Up Redis Caches

```bash
# Warm up all application caches
php artisan cache:warmup

# Expected output:
# 🔥 Starting cache warm-up...
# ████████████████ 100%
# ✓ Service categories cached
# ✓ Cities cached
# ✓ App settings cached
# ✓ Banners cached
# ✓ Featured providers cached
# ✓ Trending services cached
# ✓ Cache warm-up completed
```

---

## Step 10: Restart Queue Workers

```bash
# Gracefully restart all queue workers
php artisan queue:restart

# If using Supervisor, restart:
sudo supervisorctl restart all

# Or if running manually, stop and restart:
# Ctrl+C to stop, then:
php artisan queue:work --tries=3 --timeout=90 &

# For production, use Supervisor or systemd service
```

---

## Step 11: Verify Deployment

```bash
# 1. Test Redis health with full diagnostics
php artisan redis:health --detailed

# 2. Check database connections
php artisan tinker
>>> DB::connection()->getPdo();
>>> exit

# 3. Test cache operations
php artisan tinker
>>> Cache::put('test', 'working', 60);
>>> Cache::get('test');
# Should return: "working"
>>> exit

# 4. Check queue is processing
php artisan queue:work --once

# 5. Test API endpoint
curl -I http://your-domain.com/api/v1/service-categories
# Should return: HTTP/1.1 200 OK (fast response)
```

---

## Step 12: Monitor Performance

```bash
# Monitor Redis in real-time
redis-cli MONITOR

# In another terminal, hit some API endpoints and watch Redis cache them

# Check cache hit ratio
php artisan redis:health --detailed
# Look for "Cache Hit Ratio" - should be >80% after warmup

# Monitor Laravel logs
tail -f storage/logs/laravel.log
```

---

## Step 13: Setup Process Monitoring (Production)

### Option A: Using Supervisor (Recommended)

```bash
# Install Supervisor
sudo apt install supervisor

# Create queue worker config
sudo nano /etc/supervisor/conf.d/luky-worker.conf
```

Paste this configuration:
```ini
[program:luky-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /path/to/luky-back-end/artisan queue:work redis --tries=3 --timeout=90
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=3
redirect_stderr=true
stdout_logfile=/path/to/luky-back-end/storage/logs/worker.log
stopwaitsecs=3600
```

```bash
# Reload Supervisor
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start luky-worker:*

# Check status
sudo supervisorctl status
```

### Option B: Using Systemd Service

```bash
# Create service file
sudo nano /etc/systemd/system/luky-worker.service
```

Paste this configuration:
```ini
[Unit]
Description=Luky Queue Worker
After=network.target redis.service

[Service]
Type=simple
User=www-data
WorkingDirectory=/path/to/luky-back-end
ExecStart=/usr/bin/php /path/to/luky-back-end/artisan queue:work redis --tries=3 --timeout=90
Restart=always
RestartSec=5

[Install]
WantedBy=multi-user.target
```

```bash
# Enable and start service
sudo systemctl enable luky-worker
sudo systemctl start luky-worker
sudo systemctl status luky-worker
```

---

## Rollback Plan (If Something Goes Wrong)

```bash
# 1. Restore database backup
psql -U postgres luky_production < backup_TIMESTAMP.sql

# 2. Checkout previous commit
git log --oneline -5  # Find previous commit hash
git checkout PREVIOUS_COMMIT_HASH

# 3. Run composer install
composer install --no-dev

# 4. Clear caches
php artisan cache:clear
php artisan config:clear

# 5. Restart services
php artisan queue:restart
```

---

## Post-Deployment Checklist

- [ ] Redis connection working (`php artisan redis:health`)
- [ ] Migrations applied successfully
- [ ] Caches warmed up
- [ ] Queue worker running (`sudo supervisorctl status`)
- [ ] API endpoints responding quickly (< 200ms)
- [ ] No errors in logs (`tail -f storage/logs/laravel.log`)
- [ ] Database queries using indexes (check slow query log)
- [ ] Redis memory usage normal (< 80%)
- [ ] Application accessible from browser/mobile app

---

## Performance Verification

### Before Deployment Baseline:
```bash
# Test an endpoint 10 times, measure average
for i in {1..10}; do
  curl -w "%{time_total}\n" -o /dev/null -s http://your-domain.com/api/v1/service-categories
done
```

### After Deployment Test:
```bash
# Same test - should be 80-97% faster
for i in {1..10}; do
  curl -w "%{time_total}\n" -o /dev/null -s http://your-domain.com/api/v1/service-categories
done
```

### Expected Results:
- **Before**: 0.2-0.5 seconds per request
- **After**: 0.01-0.05 seconds per request
- **Improvement**: 85-95% faster

---

## Monitoring & Alerts

### Set up monitoring for:
1. **Redis**: Memory usage, connection count, hit ratio
2. **Queue**: Failed jobs count, processing time
3. **Database**: Slow queries (> 100ms), connection pool
4. **API**: Response times, error rates
5. **Server**: CPU, Memory, Disk usage

### Recommended tools:
- Laravel Pulse (built-in monitoring)
- Redis monitoring: `redis-cli INFO stats`
- Database: PostgreSQL slow query log
- APM: New Relic, Datadog, or similar

---

## Support

If you encounter issues:

1. **Check logs**: `tail -f storage/logs/laravel.log`
2. **Test Redis**: `php artisan redis:health --detailed`
3. **Monitor queries**: `php artisan queries:monitor`
4. **Check supervisor**: `sudo supervisorctl status`
5. **Review documentation**: See README.md, PERFORMANCE_OPTIMIZATIONS.md

---

## Summary

**Deployment time**: ~10-15 minutes
**Downtime**: Minimal (< 1 minute during migration)
**Risk**: Low (rollback plan in place)
**Expected improvement**: 80-97% faster API responses

🚀 **Ready to deploy!**
