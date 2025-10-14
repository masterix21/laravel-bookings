# Installation Guide

This guide provides detailed installation instructions for Laravel Bookings, including advanced configuration options and troubleshooting steps.

## Requirements

- PHP 8.4 or higher
- Laravel 12.x
- Composer
- MySQL 8.0+ or PostgreSQL 13+ (recommended)
- SQLite 3.8+ (for development/testing)

## Installation Steps

### 1. Install via Composer

```bash
composer require masterix21/laravel-bookings
```

### 2. Publish and Run Migrations

```bash
# Publish migration files
php artisan vendor:publish --tag="bookings-migrations"

# Run migrations
php artisan migrate
```

### 3. Publish Configuration (Optional)

```bash
php artisan vendor:publish --tag="bookings-config"
```

This creates `config/bookings.php` where you can customize model classes and generators.

## Configuration

### Database Configuration

The package works with all Laravel-supported databases. For optimal performance:

**MySQL:**
```sql
-- Recommended MySQL settings
SET innodb_lock_wait_timeout = 120;
SET transaction_isolation = 'READ-COMMITTED';
```

**PostgreSQL:**
```sql
-- Recommended PostgreSQL settings
SET lock_timeout = '120s';
SET default_transaction_isolation = 'read committed';
```

### Environment Variables

Add these to your `.env` file for advanced configuration:

```env
# Booking configuration
BOOKINGS_DEFAULT_BATCH_SIZE=100

# Performance tuning (use Laravel's standard configuration)
DB_CONNECTION=mysql
QUEUE_CONNECTION=redis
CACHE_DRIVER=redis
```

### Model Configuration

Customize the models used by the package in `config/bookings.php`:

```php
<?php

return [
    'models' => [
        'user' => \App\Models\User::class,
        'bookable_resource' => \Masterix21\Bookings\Models\BookableResource::class,
        'booking' => \Masterix21\Bookings\Models\Booking::class,
        'booked_period' => \Masterix21\Bookings\Models\BookedPeriod::class,
        'bookable_planning' => \Masterix21\Bookings\Models\BookablePlanning::class,
        'bookable_relation' => \Masterix21\Bookings\Models\BookableRelation::class,
    ],

    'generators' => [
        'booking_code' => \Masterix21\Bookings\Generators\RandomBookingCode::class,
    ],

    'planning_validation' => [
        'batch_size' => env('BOOKINGS_DEFAULT_BATCH_SIZE', 100),
    ],
];
```

## Advanced Setup

### Queue Configuration

For production environments, configure queues for heavy operations:

```php
// config/queue.php
'connections' => [
    'redis' => [
        'driver' => 'redis',
        'connection' => 'default',
        'queue' => env('REDIS_QUEUE', 'bookings'),
        'retry_after' => 90,
        'block_for' => null,
    ],
],
```

### Cache Configuration

Configure caching for better performance:

```php
// config/cache.php
'stores' => [
    'bookings' => [
        'driver' => 'redis',
        'connection' => 'cache',
        'prefix' => env('CACHE_PREFIX', 'laravel_bookings'),
    ],
],
```

### Service Provider Registration

The package uses Laravel's auto-discovery. If you need manual registration:

```php
// config/app.php
'providers' => [
    // Other providers...
    Masterix21\Bookings\BookingsServiceProvider::class,
],

'aliases' => [
    // Other aliases...
    'Bookings' => Masterix21\Bookings\BookingsFacade::class,
],
```

## Database Indexes

For large datasets, add these indexes for better performance:

```sql
-- Performance indexes
CREATE INDEX idx_bookings_booker ON bookings(booker_type, booker_id);
CREATE INDEX idx_bookings_created_at ON bookings(created_at);
CREATE INDEX idx_booked_periods_dates ON booked_periods(starts_at, ends_at);
CREATE INDEX idx_bookable_resources_bookable ON bookable_resources(is_bookable, is_visible);
CREATE INDEX idx_bookable_plannings_active ON bookable_plannings(starts_at, ends_at);
```

## Validation

Verify your installation:

```bash
# Test database connection
php artisan migrate:status

# Verify package is loaded
php artisan route:list | grep booking

# Run package tests (if in development)
php artisan test --filter=BookingTest
```

## Docker Setup

For Docker environments:

```dockerfile
# Dockerfile
FROM php:8.4-fpm

# Install required extensions
RUN docker-php-ext-install pdo pdo_mysql

# Install Redis extension for caching/queues
RUN pecl install redis && docker-php-ext-enable redis
```

```yaml
# docker-compose.yml
version: '3.8'
services:
  app:
    build: .
    depends_on:
      - mysql
      - redis
    
  mysql:
    image: mysql:8.0
    environment:
      MYSQL_DATABASE: bookings
      MYSQL_ROOT_PASSWORD: secret
    
  redis:
    image: redis:7-alpine
```

## Production Considerations

### Memory Limits

For large booking operations:

```ini
; php.ini
memory_limit = 256M
max_execution_time = 300
```

### Logging

Configure detailed logging:

```php
// config/logging.php
'channels' => [
    'bookings' => [
        'driver' => 'daily',
        'path' => storage_path('logs/bookings.log'),
        'level' => env('LOG_LEVEL', 'info'),
        'days' => 14,
    ],
],
```

### Monitoring

Set up monitoring for booking operations:

```php
// AppServiceProvider.php
use Masterix21\Bookings\Events\BookingFailed;

public function boot()
{
    Event::listen(BookingFailed::class, function ($event) {
        Log::channel('bookings')->error('Booking failed', [
            'booking_id' => $event->booking?->id,
            'error' => $event->exception->getMessage(),
        ]);
    });
}
```

## Troubleshooting

### Common Issues

**Migration errors:**
```bash
# If migrations fail, try:
php artisan migrate:reset
php artisan migrate:fresh
```

**Permission errors:**
```bash
# Fix storage permissions
chmod -R 775 storage/
chown -R www-data:www-data storage/
```

**Memory issues:**
```bash
# Increase PHP memory limit temporarily
php -d memory_limit=512M artisan migrate
```

### Support

- Check [Troubleshooting Guide](troubleshooting.md)
- Review [GitHub Issues](https://github.com/masterix21/laravel-bookings/issues)
- See [Configuration Guide](configuration.md) for advanced options

## Next Steps

After installation:

1. Read the [Getting Started Guide](getting-started.md)
2. Review [API Reference](api-reference.md)
3. Check out [Examples](examples/) for implementation ideas
4. Configure [Events and Listeners](events.md) for your needs