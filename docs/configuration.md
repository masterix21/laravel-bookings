# Configuration Guide

This guide covers all configuration options available in Laravel Bookings, from basic setup to advanced customization.

## Configuration File

The main configuration file is located at `config/bookings.php`. Publish it using:

```bash
php artisan vendor:publish --tag="bookings-config"
```

## Default Configuration

```php
<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Models
    |--------------------------------------------------------------------------
    |
    | Configure the models used by the booking system. You can extend or 
    | replace any of these models with your own implementations.
    |
    */
    'models' => [
        'user' => \Illuminate\Foundation\Auth\User::class,
        'bookable_resource' => \Masterix21\Bookings\Models\BookableResource::class,
        'bookable_planning' => \Masterix21\Bookings\Models\BookablePlanning::class,
        'bookable_relation' => \Masterix21\Bookings\Models\BookableRelation::class,
        'booking' => \Masterix21\Bookings\Models\Booking::class,
        'booked_period' => \Masterix21\Bookings\Models\BookedPeriod::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Generators
    |--------------------------------------------------------------------------
    |
    | Configure the generators used for creating booking codes and other
    | identifiers. You can create custom generators by implementing the
    | appropriate contracts.
    |
    */
    'generators' => [
        'booking_code' => \Masterix21\Bookings\Generators\RandomBookingCode::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Planning Validation
    |--------------------------------------------------------------------------
    |
    | Configure how planning validation is performed, including batch sizes
    | for processing large datasets.
    |
    */
    'planning_validation' => [
        'batch_size' => 100,
    ],
];
```

## Model Configuration

### Custom User Model

If you're using a custom User model:

```php
// config/bookings.php
'models' => [
    'user' => \App\Models\CustomUser::class,
    // ... other models
],
```

### Extending Core Models

You can extend the core models to add custom functionality:

```php
<?php
// app/Models/CustomBooking.php

namespace App\Models;

use Masterix21\Bookings\Models\Booking as BaseBooking;

class CustomBooking extends BaseBooking
{
    protected $fillable = [
        ...parent::getFillable(),
        'custom_field',
        'priority',
    ];

    protected $casts = [
        ...parent::getCasts(),
        'priority' => 'integer',
    ];

    public function isPriority(): bool
    {
        return $this->priority > 5;
    }
}
```

```php
// config/bookings.php
'models' => [
    'booking' => \App\Models\CustomBooking::class,
    // ... other models
],
```

### Model Relationships

Configure custom relationships:

```php
<?php
// app/Models/Organization.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Masterix21\Bookings\Models\Concerns\HasBookings;

class Organization extends Model
{
    use HasBookings;

    protected $fillable = ['name', 'email'];
}
```

## Generators Configuration

### Custom Booking Code Generator

Create a custom booking code generator:

```php
<?php
// app/Generators/CustomBookingCodeGenerator.php

namespace App\Generators;

use Masterix21\Bookings\Generators\Contracts\BookingCodeGenerator;
use Masterix21\Bookings\Models\Booking;

class CustomBookingCodeGenerator implements BookingCodeGenerator
{
    public function generate(?Booking $booking = null): string
    {
        $prefix = date('Y');
        $random = strtoupper(substr(md5(uniqid()), 0, 6));
        
        return "{$prefix}-{$random}";
    }
}
```

```php
// config/bookings.php
'generators' => [
    'booking_code' => \App\Generators\CustomBookingCodeGenerator::class,
],
```

### Advanced Code Generation

Create context-aware booking codes:

```php
<?php

namespace App\Generators;

use Masterix21\Bookings\Generators\Contracts\BookingCodeGenerator;
use Masterix21\Bookings\Models\Booking;

class ContextAwareBookingCodeGenerator implements BookingCodeGenerator
{
    public function generate(?Booking $booking = null): string
    {
        $prefix = 'BK';
        
        if ($booking && $booking->bookedPeriods->count() > 0) {
            $resource = $booking->bookedPeriods->first()->bookableResource->resource;
            
            // Add resource type prefix
            if ($resource instanceof \App\Models\Room) {
                $prefix = 'RM';
            } elseif ($resource instanceof \App\Models\Vehicle) {
                $prefix = 'VH';
            }
        }

        $timestamp = now()->format('ymdHis');
        $random = strtoupper(substr(uniqid(), -4));

        return "{$prefix}-{$timestamp}-{$random}";
    }
}
```

## Planning Validation Configuration

### Batch Processing

Configure batch sizes for large datasets:

```php
// config/bookings.php
'planning_validation' => [
    'batch_size' => env('BOOKINGS_VALIDATION_BATCH_SIZE', 100),
    'timeout' => env('BOOKINGS_VALIDATION_TIMEOUT', 300), // seconds
],
```

### Environment Variables

```env
# .env
BOOKINGS_VALIDATION_BATCH_SIZE=200
BOOKINGS_VALIDATION_TIMEOUT=600
```

## Database Configuration

### Connection Settings

For high-traffic applications, consider dedicated database connections:

```php
// config/database.php
'connections' => [
    'bookings' => [
        'driver' => 'mysql',
        'host' => env('BOOKINGS_DB_HOST', '127.0.0.1'),
        'port' => env('BOOKINGS_DB_PORT', '3306'),
        'database' => env('BOOKINGS_DB_DATABASE'),
        'username' => env('BOOKINGS_DB_USERNAME'),
        'password' => env('BOOKINGS_DB_PASSWORD'),
        'charset' => 'utf8mb4',
        'collation' => 'utf8mb4_unicode_ci',
        'options' => [
            PDO::ATTR_TIMEOUT => 60,
            PDO::ATTR_EMULATE_PREPARES => false,
        ],
    ],
],
```

### Model Connection Override

```php
<?php
// app/Models/CustomBookableResource.php

namespace App\Models;

use Masterix21\Bookings\Models\BookableResource as BaseBookableResource;

class CustomBookableResource extends BaseBookableResource
{
    protected $connection = 'bookings';
}
```

## Cache Configuration

### Enable Caching

```php
// config/cache.php
'stores' => [
    'bookings' => [
        'driver' => 'redis',
        'connection' => 'cache',
        'prefix' => env('CACHE_PREFIX', 'laravel_bookings'),
        'serializer' => 'igbinary',
    ],
],
```

### Custom Cache Keys

```php
<?php
// app/Services/BookingCacheService.php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Masterix21\Bookings\Models\BookableResource;
use Carbon\Carbon;

class BookingCacheService
{
    protected string $store = 'bookings';
    protected int $ttl = 3600; // 1 hour

    public function getAvailabilityKey(BookableResource $resource, Carbon $date): string
    {
        return "availability:{$resource->id}:{$date->format('Y-m-d')}";
    }

    public function cacheAvailability(BookableResource $resource, Carbon $date, array $data): void
    {
        $key = $this->getAvailabilityKey($resource, $date);
        Cache::store($this->store)->put($key, $data, $this->ttl);
    }

    public function getAvailability(BookableResource $resource, Carbon $date): ?array
    {
        $key = $this->getAvailabilityKey($resource, $date);
        return Cache::store($this->store)->get($key);
    }
}
```

## Queue Configuration

### Queue Settings for Bookings

```php
// config/queue.php
'connections' => [
    'bookings' => [
        'driver' => 'redis',
        'connection' => 'default',
        'queue' => env('BOOKINGS_QUEUE', 'bookings'),
        'retry_after' => 90,
        'block_for' => null,
        'after_commit' => false,
    ],
],
```

### Job Configuration

```php
<?php
// app/Jobs/ProcessBookingJob.php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class ProcessBookingJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $connection = 'bookings';
    public $queue = 'bookings';
    public $timeout = 300;
    public $tries = 3;

    // Job implementation...
}
```

## Event Configuration

### Event Listeners

```php
// config/bookings.php (extended)
return [
    // ... existing config

    'events' => [
        'listeners' => [
            \Masterix21\Bookings\Events\BookingCompleted::class => [
                \App\Listeners\SendBookingConfirmation::class,
                \App\Listeners\UpdateInventory::class,
            ],
            \Masterix21\Bookings\Events\BookingFailed::class => [
                \App\Listeners\LogBookingFailure::class,
                \App\Listeners\NotifyAdministrators::class,
            ],
        ],
    ],
];
```

### Custom Event Configuration

```php
<?php
// app/Providers/BookingServiceProvider.php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Masterix21\Bookings\Events\BookingCompleted;
use App\Listeners\CustomBookingListener;

class BookingServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->app['events']->listen(
            BookingCompleted::class,
            CustomBookingListener::class
        );
    }
}
```

## Validation Configuration

### Custom Validation Rules

```php
<?php
// app/Rules/BookingTimeSlotRule.php

namespace App\Rules;

use Illuminate\Contracts\Validation\Rule;
use Carbon\Carbon;

class BookingTimeSlotRule implements Rule
{
    public function passes($attribute, $value): bool
    {
        $startTime = Carbon::parse($value);
        
        // Business hours: 9 AM - 6 PM
        return $startTime->hour >= 9 && $startTime->hour < 18;
    }

    public function message(): string
    {
        return 'Bookings are only allowed during business hours (9 AM - 6 PM).';
    }
}
```

### Validation Service

```php
<?php
// app/Services/BookingValidationService.php

namespace App\Services;

use Masterix21\Bookings\Models\BookableResource;
use Spatie\Period\PeriodCollection;

class BookingValidationService
{
    public function validateBookingPeriods(
        BookableResource $resource,
        PeriodCollection $periods
    ): array {
        $errors = [];

        foreach ($periods as $period) {
            // Minimum booking duration
            if ($period->length()->totalMinutes() < 60) {
                $errors[] = 'Minimum booking duration is 1 hour';
            }

            // Maximum advance booking
            if ($period->start()->diffInDays(now()) > 365) {
                $errors[] = 'Cannot book more than 1 year in advance';
            }

            // Resource-specific validation
            if ($resource->resource_type === 'App\\Models\\Room') {
                $this->validateRoomBooking($resource, $period, $errors);
            }
        }

        return $errors;
    }

    protected function validateRoomBooking($resource, $period, &$errors): void
    {
        // Room-specific validation logic
        $room = $resource->resource;
        
        if ($room->requires_approval && !auth()->user()->can('book-without-approval')) {
            $errors[] = 'This room requires approval for booking';
        }
    }
}
```

## Localization Configuration

### Language Files

Create language files for internationalization:

```php
<?php
// resources/lang/en/bookings.php

return [
    'booking_confirmed' => 'Your booking has been confirmed',
    'booking_failed' => 'Booking failed: :reason',
    'overlap_detected' => 'The requested time slot conflicts with existing bookings',
    'out_of_planning' => 'The resource is not available during the requested time',
    'no_capacity' => 'The resource has reached its maximum capacity',
    
    'statuses' => [
        'pending' => 'Pending',
        'confirmed' => 'Confirmed',
        'cancelled' => 'Cancelled',
        'completed' => 'Completed',
    ],
];
```

```php
<?php
// resources/lang/it/bookings.php

return [
    'booking_confirmed' => 'La tua prenotazione è stata confermata',
    'booking_failed' => 'Prenotazione fallita: :reason',
    'overlap_detected' => 'L\'orario richiesto è in conflitto con prenotazioni esistenti',
    'out_of_planning' => 'La risorsa non è disponibile nell\'orario richiesto',
    'no_capacity' => 'La risorsa ha raggiunto la capacità massima',
    
    'statuses' => [
        'pending' => 'In attesa',
        'confirmed' => 'Confermata',
        'cancelled' => 'Cancellata',
        'completed' => 'Completata',
    ],
];
```

## Performance Configuration

### Optimization Settings

```php
// config/bookings.php (extended)
return [
    // ... existing config

    'performance' => [
        'cache_availability' => env('BOOKINGS_CACHE_AVAILABILITY', true),
        'cache_ttl' => env('BOOKINGS_CACHE_TTL', 3600),
        'eager_load_relations' => env('BOOKINGS_EAGER_LOAD', true),
        'chunk_size' => env('BOOKINGS_CHUNK_SIZE', 1000),
    ],
];
```

### Environment Variables

```env
# Performance settings
BOOKINGS_CACHE_AVAILABILITY=true
BOOKINGS_CACHE_TTL=7200
BOOKINGS_EAGER_LOAD=true
BOOKINGS_CHUNK_SIZE=500

# Database optimization
DB_SLOW_QUERY_LOG=true
DB_SLOW_QUERY_TIME=2
```

## Testing Configuration

### Test Environment

```php
// config/bookings.php (test environment)
return [
    'models' => [
        'user' => \Tests\Models\TestUser::class,
        'bookable_resource' => \Tests\Models\TestBookableResource::class,
        // ... other test models
    ],

    'generators' => [
        'booking_code' => \Tests\Generators\TestBookingCodeGenerator::class,
    ],

    'planning_validation' => [
        'batch_size' => 10, // Smaller batches for faster tests
    ],
];
```

This configuration guide provides comprehensive coverage of all customization options available in Laravel Bookings. Choose the configurations that best fit your application's needs.