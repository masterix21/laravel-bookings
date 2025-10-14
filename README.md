# Laravel Bookings

[![Latest Version on Packagist](https://img.shields.io/packagist/v/masterix21/laravel-bookings.svg?style=flat-square)](https://packagist.org/packages/masterix21/laravel-bookings)
[![GitHub Actions Workflow Status](https://img.shields.io/github/actions/workflow/status/masterix21/laravel-bookings/run-tests.yml)](https://img.shields.io/github/actions/workflow/status/masterix21/laravel-bookings/run-tests.yml)
[![Total Downloads](https://img.shields.io/packagist/dt/masterix21/laravel-bookings.svg?style=flat-square)](https://packagist.org/packages/masterix21/laravel-bookings)

A comprehensive Laravel package that adds powerful booking functionality to any Eloquent model. Transform your models into bookable resources with advanced features like time-based reservations, capacity management, planning constraints, overlap detection, and event-driven architecture.

## Features

- 🚀 **Make any Eloquent model bookable** with simple traits
- 📅 **Advanced time period management** using Spatie Period library
- 🏢 **Resource capacity control** with configurable limits
- 📋 **Planning constraints** with weekday and time restrictions
- 🔍 **Overlap detection** and conflict prevention
- 🎯 **Event-driven architecture** for audit trails and integrations
- 🗂️ **Polymorphic relationships** for flexible booker and resource types
- 🧪 **Well tested** with comprehensive test suite
- ⚡ **Performance optimized** with efficient database queries
- 🛡️ **Transaction safety** with automatic rollback on failures
- 🔄 **Automatic synchronization** with model events
- 🔗 **Planning source pattern** for business logic separation

## Installation

Install the package via Composer:

```bash
composer require masterix21/laravel-bookings
```

Publish and run the migrations:

```bash
php artisan vendor:publish --tag="bookings-migrations"
php artisan migrate
```

Optionally, publish the config file:

```bash
php artisan vendor:publish --tag="bookings-config"
```

## Quick Start

### 1. Make a Model Bookable

Add the `IsBookable` trait to any model you want to make bookable:

```php
use Masterix21\Bookings\Models\Concerns\Bookable;
use Masterix21\Bookings\Models\Concerns\IsBookable;

class Room extends Model implements Bookable
{
    use IsBookable;

    protected $fillable = ['name', 'capacity'];
}
```

### 2. Create a Bookable Resource

```php
use Masterix21\Bookings\Models\BookableResource;

// Create a bookable resource for your room
$room = Room::create(['name' => 'Deluxe Suite', 'capacity' => 4]);

$bookableResource = BookableResource::create([
    'resource_type' => Room::class,
    'resource_id' => $room->id,
    'max' => 1, // Maximum concurrent bookings
    'size' => 4, // Resource capacity
    'is_bookable' => true,
    'is_visible' => true,
]);
```

### 3. Make a Booking

```php
use Masterix21\Bookings\Actions\BookResource;
use Spatie\Period\Period;
use Spatie\Period\PeriodCollection;

// Create booking periods
$periods = PeriodCollection::make([
    Period::make('2024-12-25', '2024-12-27'), // 2 nights
]);

// Book the resource
$booking = (new BookResource())->run(
    periods: $periods,
    bookableResource: $bookableResource,
    booker: auth()->user(), // The user making the booking
    label: 'Christmas Holiday',
    note: 'Special dietary requirements',
    meta: ['guests' => 2, 'payment_method' => 'credit_card']
);
```

## Core Concepts

### BookableResource
The central entity that represents a bookable item. It's linked to your actual model (Room, Car, etc.) via polymorphic relationships.

### Booking
Represents a reservation with metadata, booker information, and associated time periods.

### BookedPeriod
Individual time slots within a booking, supporting complex multi-period reservations.

### BookablePlanning
Defines availability rules, working hours, and constraints for resources.

## Advanced Features

### Custom Resource Synchronization

Automatically sync data from your models to bookable resources:

```php
use Masterix21\Bookings\Models\BookableResource;
use Masterix21\Bookings\Models\Concerns\Bookable;
use Masterix21\Bookings\Models\Concerns\IsBookable;
use Masterix21\Bookings\Models\Concerns\SyncBookableResource;

class Room extends Model implements Bookable
{
    use IsBookable;
    use SyncBookableResource;

    /**
     * Called automatically when the room is saved
     */
    public function syncBookableResource(BookableResource $resource): void
    {
        $resource->update([
            'is_visible' => $this->is_published,
            'is_bookable' => $this->is_available && $this->is_clean,
            'max' => $this->max_concurrent_bookings,
            'size' => $this->capacity,
        ]);
    }
}
```

**Key Features:**
- Opt-in with `SyncBookableResource` trait
- Automatically called on model save
- Handles both single resource (`bookableResource`) and multiple resources (`bookableResources`)
- N+1 query optimized

*For complete documentation, see [docs/synchronization.md](docs/synchronization.md)*

### Planning Source Pattern

Link business models (rates, special offers) directly to planning:

```php
use Masterix21\Bookings\Models\Concerns\BookablePlanningSource;
use Masterix21\Bookings\Models\Concerns\IsBookablePlanningSource;
use Masterix21\Bookings\Models\Concerns\SyncBookablePlanning;

class Rate extends Model implements BookablePlanningSource
{
    use IsBookablePlanningSource;
    use SyncBookablePlanning;

    /**
     * Called automatically when the rate is saved
     */
    public function syncBookablePlanning(): void
    {
        $this->planning()->updateOrCreate(
            ['bookable_resource_id' => $this->room->bookableResource->id],
            [
                'starts_at' => $this->valid_from,
                'ends_at' => $this->valid_to,
                'monday' => true,
                'tuesday' => true,
                'wednesday' => true,
                'thursday' => true,
                'friday' => true,
                'saturday' => $this->includes_weekend,
                'sunday' => $this->includes_weekend,
            ]
        );
    }
}
```

**Benefits:**
- Opt-in with `SyncBookablePlanning` trait
- Single source of truth: your business model controls availability
- Automatic synchronization on save
- Bidirectional navigation: `$rate->planning` and `$planning->source`
- Planning auto-deleted when source is deleted

**Migration Required:**
If you're upgrading from an older version, run this additional migration:

```bash
php artisan vendor:publish --tag="bookings-migrations"
# Then manually run: update_bookable_plannings_add_source_columns.php
php artisan migrate
```

*For complete documentation, see [docs/synchronization.md](docs/synchronization.md)*

### Planning Constraints

Define when resources are available:

```php
use Masterix21\Bookings\Models\BookablePlanning;

BookablePlanning::create([
    'bookable_resource_id' => $bookableResource->id,
    'monday' => true,
    'tuesday' => true,
    'wednesday' => true,
    'thursday' => true,
    'friday' => true,
    'saturday' => false, // Closed on weekends
    'sunday' => false,
    'starts_at' => '2024-01-01 09:00:00', // Available from 9 AM
    'ends_at' => '2024-12-31 18:00:00',   // Until 6 PM
]);
```

### Event System

Listen to booking lifecycle events:

```php
use Masterix21\Bookings\Events\BookingCompleted;
use Masterix21\Bookings\Events\BookingFailed;

// In your EventServiceProvider
protected $listen = [
    BookingCompleted::class => [
        SendBookingConfirmationEmail::class,
        UpdateInventory::class,
    ],
    BookingFailed::class => [
        LogBookingFailure::class,
        NotifyAdministrators::class,
    ],
];
```

### Checking Availability

```php
// Check if a resource is booked at a specific time
$isBooked = $room->isBookedAt(now());

// Get booked periods for a specific date
$bookedPeriods = $room->bookedPeriodsOfDate(today());

// Get all bookings for a resource
$bookings = $room->bookings;
```

### Overlap Detection

The package automatically prevents overlapping bookings:

```php
use Masterix21\Bookings\Exceptions\BookingResourceOverlappingException;

try {
    $booking = (new BookResource())->run(/* ... */);
} catch (BookingResourceOverlappingException $e) {
    // Handle booking conflict
    return response()->json(['error' => 'Time slot already booked'], 409);
}
```

## Complete Examples

For comprehensive implementation examples, see:

- 📨 [Hotel Booking System](docs/examples/hotel-booking.md) - Complete hotel reservation system
- 🚗 [Car Rental System](docs/examples/car-rental.md) - Vehicle rental management 
- 🍽️ [Restaurant Reservations](docs/examples/restaurant-reservations.md) - Table booking system
- 📅 [Service Appointments](docs/examples/service-appointments.md) - Appointment scheduling

## Documentation

For detailed documentation, see:

- ⚙️ [Configuration](docs/configuration.md) - Package configuration options
- 🏗️ [Database Schema](docs/database-schema.md) - Database structure and migrations
- 📖 [API Reference](docs/api-reference.md) - Complete API documentation

### Key Topics

- 🏛️ [Architecture](docs/architecture.md) - Package design and structure
- 🚀 [Getting Started](docs/getting-started.md) - Quick start guide
- 📊 [Models](docs/models.md) - Model relationships and usage
- ⚡ [Actions](docs/actions.md) - Core booking operations
- 🔄 [Synchronization](docs/synchronization.md) - Resource and planning synchronization
- 🎯 [Events](docs/events.md) - Event system and listeners
- 🧪 [Testing](docs/testing.md) - Testing strategies and examples
- 🔧 [Extending](docs/extending.md) - Customization and extensions
- 🚨 [Troubleshooting](docs/troubleshooting.md) - Common issues and solutions

## Legacy Quick Reference

*For complete API documentation, see [docs/api-reference.md](docs/api-reference.md)*

The package provides the following core traits and actions:

- `IsBookable` trait - Makes any model bookable
- `HasBookings` trait - For entities that can make bookings
- `BookResource` action - Creates and updates bookings
- `CheckBookingOverlaps` action - Validates booking conflicts

Events are automatically fired during the booking lifecycle for audit trails and integrations.

## Testing

Run the package tests:

```bash
composer test
```

Run tests with coverage:

```bash
composer test-coverage
```

Run static analysis:

```bash
composer analyse
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Please see [CONTRIBUTING](.github/CONTRIBUTING.md) for details.

## Security Vulnerabilities

Please review [our security policy](../../security/policy) on how to report security vulnerabilities.

## Credits

- [Luca Longo](https://github.com/masterix21)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
