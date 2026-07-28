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

    /*
    |--------------------------------------------------------------------------
    | Booking Update
    |--------------------------------------------------------------------------
    |
    | Controls what happens to a booking's existing booked periods when it is
    | updated through BookResource::run(). When true, the old periods are soft
    | deleted; when false (the default) they are force-deleted.
    |
    */
    'booking_update' => [
        'preserve_deleted_periods' => false,
    ],
];
```

This is the complete, real `config/bookings.php` — there are no other top-level keys (no `cache`, `events`, `performance`, or `validation` sections shipped by the package).

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
    public function run(?string $prefix = null, ?string $suffix = null): string
    {
        $year = date('Y');
        $random = strtoupper(substr(md5(uniqid()), 0, 6));

        return ($prefix ?? '')."{$year}-{$random}".($suffix ?? '');
    }
}
```

`BookingCodeGenerator` only requires `run(?string $prefix = null, ?string $suffix = null): string` — there is no `generate()` method on the contract.

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

class ContextAwareBookingCodeGenerator implements BookingCodeGenerator
{
    public function run(?string $prefix = null, ?string $suffix = null): string
    {
        $prefix ??= 'BK';
        $timestamp = now()->format('ymdHis');
        $random = strtoupper(substr(uniqid(), -4));

        return "{$prefix}-{$timestamp}-{$random}" . ($suffix ? "-{$suffix}" : '');
    }
}
```

### Per-Booking Code Generators

While the configuration file sets the default generator, you can override it for specific bookings:

```php
use Masterix21\Bookings\Actions\BookResource;
use App\Generators\VipBookingCodeGenerator;
use App\Generators\StandardBookingCodeGenerator;

$bookResource = new BookResource();

// Use VIP generator for high-value bookings
$vipBooking = $bookResource->run(
    periods: $periods,
    bookableResource: $resource,
    booker: $user,
    codeGenerator: VipBookingCodeGenerator::class,
);

// Use standard generator for regular bookings
$standardBooking = $bookResource->run(
    periods: $periods,
    bookableResource: $resource,
    booker: $user,
    codeGenerator: StandardBookingCodeGenerator::class,
);

// Use default configured generator
$defaultBooking = $bookResource->run(
    periods: $periods,
    bookableResource: $resource,
    booker: $user,
    // No codeGenerator specified - uses config('bookings.generators.booking_code')
);
```

### Generator with Constructor Parameters

Create generators that accept configuration:

```php
<?php

namespace App\Generators;

use Masterix21\Bookings\Generators\Contracts\BookingCodeGenerator;

class SequentialBookingCodeGenerator implements BookingCodeGenerator
{
    public function __construct(
        private string $format = 'BK',
        private int $startNumber = 1,
    ) {}

    public function run(?string $prefix = null, ?string $suffix = null): string
    {
        $prefix ??= $this->format;
        $number = $this->getNextNumber();
        $paddedNumber = str_pad((string) $number, 6, '0', STR_PAD_LEFT);

        return "{$prefix}-{$paddedNumber}" . ($suffix ? "-{$suffix}" : '');
    }

    private function getNextNumber(): int
    {
        // Implementation to get next sequential number
        // Could use database, cache, or other storage
        return $this->startNumber + cache()->increment('booking_sequence');
    }
}
```

Use the generator with custom parameters:

```php
use App\Generators\SequentialBookingCodeGenerator;

$generator = new SequentialBookingCodeGenerator(
    format: 'CONF',
    startNumber: 1000
);

$booking = $bookResource->run(
    periods: $periods,
    bookableResource: $resource,
    booker: $user,
    codeGenerator: $generator, // Pass instance with custom config
);
```

## Planning Validation Configuration

### Batch Processing

`UsesBookablePlannings::validateRelationsWithBatching()` chunks related resources into batches of this size before validating their planning — this is the only key under `planning_validation`. The shipped default is a plain integer, not wrapped in `env()`:

```php
// config/bookings.php
'planning_validation' => [
    'batch_size' => 100,
],
```

If you want it configurable per environment, publish the config and change it yourself:

```php
'planning_validation' => [
    'batch_size' => env('BOOKINGS_VALIDATION_BATCH_SIZE', 100),
],
```

There is no `timeout` key — batching only affects how many resources are checked per query, it does not impose a time limit.

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

`config/bookings.php` has no `events` key — the package does not read listener configuration from its own config file. Register listeners the normal Laravel way: an `EventServiceProvider::$listen` array, or `Event::listen()`/`$this->app['events']->listen()` in any service provider's `boot()` method. See [Events and Listeners](events.md) for the full list of events and their real constructor signatures.

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

`config/bookings.php` has no `performance` key and the package does not cache availability results itself — every `CheckBookingOverlaps`/`availableForPeriod()` call hits the database. If you want to cache availability, layer it in your own application code (see the [Cache Configuration](#cache-configuration) example above) rather than expecting a package-level toggle. The only genuinely configurable knobs the package ships are `models`, `generators`, `planning_validation.batch_size`, and `booking_update.preserve_deleted_periods` — see [Default Configuration](#default-configuration).

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