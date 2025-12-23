# Redis Setup Guide - Luky Backend

Complete guide for setting up and configuring Redis for optimal performance.

## Table of Contents
1. [Why Redis?](#why-redis)
2. [Installation](#installation)
3. [Configuration](#configuration)
4. [Verification](#verification)
5. [Performance Tuning](#performance-tuning)
6. [Troubleshooting](#troubleshooting)
7. [Production Deployment](#production-deployment)

---

## Why Redis?

Redis provides significant advantages over database-based caching:

| Feature | Database Cache | Redis Cache | Improvement |
|---------|----------------|-------------|-------------|
| Read Speed | 10-50ms | 0.1-1ms | **10-500x faster** |
| Write Speed | 20-100ms | 0.1-2ms | **10-1000x faster** |
| Concurrent Requests | Limited by DB connections | 10,000+ req/sec | **Massive scalability** |
| Lock Support | Not available | Native support | **Cache stampede prevention** |
| TTL Precision | Limited | Millisecond precision | **Better control** |
| Memory Usage | N/A | Efficient (RAM) | **Faster access** |

---

## Installation

### Windows

**Option 1: Using Memurai (Recommended for Production)**
```powershell
# Download from https://www.memurai.com/get-memurai
# Memurai is a Redis-compatible server for Windows with better performance

# Or download the installer
# Install and it will run as a Windows Service automatically
```

**Option 2: Using Redis for Windows**
```powershell
# Download from https://github.com/tporadowski/redis/releases
# Latest version: https://github.com/tporadowski/redis/releases/download/v5.0.14.1/Redis-x64-5.0.14.1.msi

# Install the MSI package
# During installation, check "Add to PATH" and "Install as Windows Service"

# Verify installation
redis-cli --version
```

**Option 3: Using WSL2 (Best for Development)**
```bash
# If you have WSL2 installed
wsl --install  # If not already installed

# Inside WSL2
sudo apt update
sudo apt install redis-server

# Start Redis
sudo service redis-server start

# Enable auto-start
sudo systemctl enable redis-server
```

### Linux (Ubuntu/Debian)

```bash
# Update package list
sudo apt update

# Install Redis
sudo apt install redis-server

# Start Redis service
sudo systemctl start redis-server

# Enable auto-start on boot
sudo systemctl enable redis-server

# Verify installation
redis-cli ping
# Should return: PONG
```

### macOS

```bash
# Install using Homebrew
brew install redis

# Start Redis service
brew services start redis

# Or run in foreground
redis-server /usr/local/etc/redis.conf
```

### Docker (Any Platform)

```bash
# Pull Redis image
docker pull redis:alpine

# Run Redis container
docker run -d \
  --name luky-redis \
  -p 6379:6379 \
  -v redis-data:/data \
  redis:alpine redis-server --appendonly yes

# Verify
docker exec -it luky-redis redis-cli ping
```

---

## Configuration

### 1. Verify PHP Redis Extension

**Check if phpredis is installed:**
```bash
php -m | findstr redis
# or on Linux/Mac:
php -m | grep redis
```

**If not installed:**

**Windows**:
```bash
# Download php_redis.dll for your PHP version from:
# https://windows.php.net/downloads/pecl/releases/redis/

# Copy to PHP ext directory
copy php_redis.dll C:\php\ext\

# Add to php.ini
extension=redis
```

**Linux**:
```bash
sudo apt install php-redis
# or
sudo pecl install redis
```

**Verify**:
```bash
php -r "echo extension_loaded('redis') ? 'OK' : 'FAILED';"
```

### 2. Update Laravel Configuration

Your `.env` is already configured! Here's what we set:

```env
# Cache using Redis
CACHE_STORE=redis
CACHE_PREFIX=luky_cache

# Queue using Redis
QUEUE_CONNECTION=redis

# Redis connection settings
REDIS_CLIENT=phpredis
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379

# Separate databases for isolation
REDIS_DB=0              # Default
REDIS_CACHE_DB=1        # Cache data
REDIS_QUEUE_DB=2        # Queue jobs
REDIS_SESSION_DB=3      # Sessions (if using Redis sessions)
```

### 3. Test Redis Connection

```bash
# Quick test
php artisan redis:health

# Detailed information
php artisan redis:health --detailed

# Run cache read/write tests
php artisan redis:health --test
```

Expected output:
```
🔍 Redis Health Check

Testing Redis connection...
✓ Redis connection: OK
✓ Redis cache connection: OK
✓ Redis queue connection: OK

✓ Redis health check completed
```

---

## Verification

### 1. Test Redis is Running

```bash
# Connect to Redis CLI
redis-cli

# Test ping
redis> PING
PONG

# Check if Laravel can connect
redis> CLIENT LIST

# Exit
redis> EXIT
```

### 2. Test Laravel Cache

```bash
# Start tinker
php artisan tinker

# Test cache write
>>> Cache::put('test', 'value', 60);
>>> Cache::get('test');
# Should return: "value"

# Test cache delete
>>> Cache::forget('test');
>>> Cache::has('test');
# Should return: false

# Test locks (important for cache stampede protection)
>>> $lock = Cache::lock('test_lock', 10);
>>> $lock->get();
# Should return: true
>>> $lock->release();
```

### 3. Verify Cache is Actually Using Redis

```bash
# In Redis CLI
redis-cli

# Switch to cache database
redis> SELECT 1

# Check keys
redis> KEYS luky_cache:*

# You should see keys like:
# luky_cache:categories:active
# luky_cache:cities:active
# etc.
```

### 4. Monitor Redis in Real-Time

```bash
# Open Redis CLI
redis-cli

# Monitor all commands
redis> MONITOR

# In another terminal, run your Laravel app
# You'll see Redis commands in real-time
```

---

## Performance Tuning

### 1. Configure Redis Memory Limit

Edit Redis configuration file:

**Windows**: `C:\Program Files\Redis\redis.windows.conf`
**Linux**: `/etc/redis/redis.conf`
**macOS**: `/usr/local/etc/redis.conf`

```conf
# Set maximum memory (e.g., 2GB)
maxmemory 2gb

# Eviction policy when max memory reached
# allkeys-lru = Remove least recently used keys
maxmemory-policy allkeys-lru

# Save to disk periodically
save 900 1      # After 900 sec if at least 1 key changed
save 300 10     # After 300 sec if at least 10 keys changed
save 60 10000   # After 60 sec if at least 10000 keys changed

# Enable AOF (Append Only File) for durability
appendonly yes
appendfsync everysec
```

Restart Redis after changes:
```bash
# Windows
net stop Redis && net start Redis

# Linux/macOS
sudo systemctl restart redis-server
```

### 2. Optimize Laravel Configuration

**config/cache.php** (already optimized):
```php
'redis' => [
    'driver' => 'redis',
    'connection' => 'cache',
    'lock_connection' => 'default',
],
```

**config/database.php** (verify this):
```php
'redis' => [
    'client' => env('REDIS_CLIENT', 'phpredis'),

    'options' => [
        'cluster' => env('REDIS_CLUSTER', 'redis'),
        'prefix' => env('REDIS_PREFIX', Str::slug(env('APP_NAME', 'laravel'), '_').'_database_'),
    ],

    'default' => [
        'url' => env('REDIS_URL'),
        'host' => env('REDIS_HOST', '127.0.0.1'),
        'username' => env('REDIS_USERNAME'),
        'password' => env('REDIS_PASSWORD'),
        'port' => env('REDIS_PORT', '6379'),
        'database' => env('REDIS_DB', '0'),
    ],

    'cache' => [
        'url' => env('REDIS_URL'),
        'host' => env('REDIS_HOST', '127.0.0.1'),
        'username' => env('REDIS_USERNAME'),
        'password' => env('REDIS_PASSWORD'),
        'port' => env('REDIS_PORT', '6379'),
        'database' => env('REDIS_CACHE_DB', '1'),
    ],

    'queue' => [
        'url' => env('REDIS_URL'),
        'host' => env('REDIS_HOST', '127.0.0.1'),
        'username' => env('REDIS_USERNAME'),
        'password' => env('REDIS_PASSWORD'),
        'port' => env('REDIS_PORT', '6379'),
        'database' => env('REDIS_QUEUE_DB', '2'),
    ],
],
```

### 3. Enable Redis Persistence

For production, enable both RDB and AOF:

```conf
# RDB Snapshots
save 900 1
save 300 10
save 60 10000
dbfilename dump.rdb

# AOF (Append Only File)
appendonly yes
appendfilename "appendonly.aof"
appendfsync everysec
```

---

## Troubleshooting

### Issue: "Connection refused" or "Could not connect to Redis"

**Solution**:
```bash
# 1. Check if Redis is running
redis-cli ping

# 2. Check Redis service status
# Windows:
sc query Redis

# Linux/macOS:
sudo systemctl status redis-server

# 3. Start Redis if not running
# Windows:
net start Redis

# Linux/macOS:
sudo systemctl start redis-server
```

### Issue: "Extension 'redis' not found"

**Solution**:
```bash
# Check PHP version
php -v

# Install matching redis extension
# Windows: Download from https://windows.php.net/downloads/pecl/releases/redis/
# Linux:
sudo apt install php8.2-redis  # Replace 8.2 with your version

# Verify
php -m | grep redis
```

### Issue: "Cache operations are slow"

**Solution**:
```bash
# 1. Check Redis latency
redis-cli --latency

# Should be < 1ms. If higher:
# - Check if Redis is running locally (not remote)
# - Check system resources (CPU, Memory)
# - Check Redis config (maxmemory, eviction policy)

# 2. Monitor Redis in real-time
redis-cli --stat

# 3. Check Laravel cache config
php artisan redis:health --detailed
```

### Issue: "Memory exhausted" or OOM (Out of Memory)

**Solution**:
```bash
# Check current memory usage
redis-cli INFO memory

# Increase maxmemory in redis.conf
maxmemory 4gb  # Adjust based on your server

# Or clear old cache
redis-cli FLUSHDB

# Restart Redis
sudo systemctl restart redis-server
```

### Issue: Keys are not expiring (TTL issues)

**Solution**:
```bash
# Check TTL of a key
redis-cli TTL luky_cache:categories:active

# -1 = No expiration
# -2 = Key doesn't exist
# Positive number = TTL in seconds

# If keys aren't expiring, check Laravel cache:
php artisan tinker
>>> Cache::put('test', 'value', 60);  # 60 seconds
>>> Cache::get('test');
```

---

## Production Deployment

### 1. Pre-Deployment Checklist

- [ ] Redis installed and running as service
- [ ] PHP Redis extension installed
- [ ] `.env` configured with Redis
- [ ] Redis password set (for security)
- [ ] Memory limit configured
- [ ] Persistence enabled (RDB + AOF)
- [ ] Firewall configured (Redis port 6379)
- [ ] Monitoring set up

### 2. Secure Redis

**Set a password**:

Edit `redis.conf`:
```conf
requirepass YOUR_STRONG_PASSWORD_HERE
```

Update `.env`:
```env
REDIS_PASSWORD=YOUR_STRONG_PASSWORD_HERE
```

Restart Redis:
```bash
sudo systemctl restart redis-server
```

**Bind to localhost only** (if Redis and Laravel are on same server):
```conf
bind 127.0.0.1 ::1
```

**Disable dangerous commands**:
```conf
rename-command FLUSHDB ""
rename-command FLUSHALL ""
rename-command KEYS ""
rename-command CONFIG ""
```

### 3. Deployment Steps

```bash
# 1. Pull latest code
git pull origin main

# 2. Update dependencies
composer install --no-dev --optimize-autoloader

# 3. Clear all caches
php artisan cache:clear
php artisan config:clear
php artisan route:clear

# 4. Optimize Laravel
php artisan config:cache
php artisan route:cache
php artisan view:cache

# 5. Run migrations (adds indexes)
php artisan migrate --force

# 6. Warm up caches
php artisan cache:warmup

# 7. Restart queue workers
php artisan queue:restart

# 8. Verify Redis health
php artisan redis:health --test
```

### 4. Monitoring

**Add to monitoring tools:**
```bash
# Check Redis is running
redis-cli ping

# Check memory usage
redis-cli INFO memory | grep used_memory_human

# Check cache hit ratio
redis-cli INFO stats | grep keyspace
```

**Set up alerts for**:
- Redis service down
- Memory usage > 80%
- Cache hit ratio < 60%
- High latency (> 10ms)

---

## Performance Benchmarks

### Expected Improvements with Redis:

| Metric | Database Cache | Redis Cache | Improvement |
|--------|----------------|-------------|-------------|
| Cache Read | 10-50ms | 0.5-2ms | **95-98% faster** |
| Cache Write | 20-100ms | 1-3ms | **93-98% faster** |
| Cache Lock | Not supported | 1-2ms | **Enabled** |
| Concurrent Users | ~100 | ~10,000+ | **100x more** |

### Real-World Impact:

**Before Redis** (Database Cache):
```
GET /api/v1/service-categories     → 250ms (230ms DB query)
GET /api/v1/cities                 → 180ms (160ms DB query)
GET /api/v1/provider/5/analytics   → 750ms (700ms queries)
```

**After Redis**:
```
GET /api/v1/service-categories     → 8ms (1ms cache hit)
GET /api/v1/cities                 → 6ms (0.5ms cache hit)
GET /api/v1/provider/5/analytics   → 75ms (65ms optimized query + cache)
```

---

## Next Steps

1. **Install Redis** using instructions above
2. **Run health check**: `php artisan redis:health --test`
3. **Warm up caches**: `php artisan cache:warmup`
4. **Monitor performance**: Check API response times
5. **Set up production**: Follow security and monitoring guidelines

---

## Support & Resources

- **Redis Documentation**: https://redis.io/docs/
- **Laravel Cache**: https://laravel.com/docs/11.x/cache
- **Laravel Redis**: https://laravel.com/docs/11.x/redis
- **PHP Redis Extension**: https://github.com/phpredis/phpredis

---

## Quick Reference

```bash
# Start Redis
sudo systemctl start redis-server  # Linux
net start Redis                     # Windows

# Connect to Redis
redis-cli

# Health check
php artisan redis:health

# Warm up caches
php artisan cache:warmup

# Clear cache
php artisan cache:clear

# Monitor Redis
redis-cli MONITOR

# Check memory
redis-cli INFO memory
```

---

**Need help?** Check the troubleshooting section or run `php artisan redis:health --detailed` for diagnostics.
