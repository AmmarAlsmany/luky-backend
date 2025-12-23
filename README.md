# Luky Backend API

Laravel 12 backend API for the Luky service provider booking platform.

## Features

- Multi-sided marketplace (Clients, Providers, Admins)
- Real-time booking management
- Integrated payment gateway (MyFatoorah)
- Wallet system
- Push notifications (Firebase FCM)
- Real-time chat (Pusher)
- Review and rating system
- Multi-language support (Arabic/English)

## Requirements

- PHP 8.2+
- PostgreSQL 13+
- Redis 6.0+
- Composer
- Node.js & NPM

## Quick Start

```bash
# Install dependencies
composer install
npm install

# Configure environment
cp .env.example .env
php artisan key:generate

# Run migrations
php artisan migrate

# Warm up caches
php artisan cache:warmup

# Start queue worker
php artisan queue:work

# Start development server
php artisan serve
```

## Performance Optimizations

This application includes comprehensive performance optimizations:

- **Redis Caching**: 80-97% faster API responses
- **Database Indexes**: 50+ strategic indexes for optimal query performance
- **Query Optimization**: Eliminated N+1 queries, optimized aggregations
- **Async Operations**: Background job processing with queue workers

See [PERFORMANCE_OPTIMIZATIONS.md](PERFORMANCE_OPTIMIZATIONS.md) for details.

## Redis Setup

Redis is **required** for optimal performance.

**Quick Setup:**
```bash
# Install Redis
# Windows: https://github.com/tporadowski/redis/releases
# Linux: sudo apt install redis-server

# Verify connection
redis-cli ping
php artisan redis:health
```

See [REDIS_SETUP.md](REDIS_SETUP.md) for detailed setup instructions.

## Deployment

```bash
# Pull latest code
git pull origin main

# Install dependencies
composer install --no-dev --optimize-autoloader

# Run migrations
php artisan migrate --force

# Clear and warm caches
php artisan cache:clear
php artisan config:cache
php artisan cache:warmup

# Restart services
php artisan queue:restart
```

## Documentation

- [Performance Optimizations](PERFORMANCE_OPTIMIZATIONS.md) - Complete performance guide
- [Redis Setup](REDIS_SETUP.md) - Redis installation and configuration
- [Redis Quick Start](REDIS_QUICK_START.md) - Quick reference guide

## Useful Commands

```bash
# Cache management
php artisan cache:warmup         # Warm up all caches
php artisan cache:clear          # Clear all caches

# Redis health check
php artisan redis:health         # Basic health check
php artisan redis:health --test  # Run comprehensive tests

# Query monitoring
php artisan queries:monitor      # Monitor database queries

# Queue management
php artisan queue:work           # Process queue jobs
php artisan queue:restart        # Restart all queue workers
```

## Tech Stack

- **Framework**: Laravel 12
- **Database**: PostgreSQL
- **Cache**: Redis
- **Queue**: Redis
- **Authentication**: Laravel Sanctum
- **Payments**: MyFatoorah
- **Push Notifications**: Firebase Cloud Messaging
- **Real-time**: Pusher
- **Media**: Spatie Media Library
- **Permissions**: Spatie Laravel Permission

## License

Proprietary - All rights reserved
