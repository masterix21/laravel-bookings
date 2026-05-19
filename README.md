# Laravel Bookings

[![Latest Version on Packagist](https://img.shields.io/packagist/v/masterix21/laravel-bookings.svg?style=flat-square)](https://packagist.org/packages/masterix21/laravel-bookings)
[![Tests](https://img.shields.io/github/actions/workflow/status/masterix21/laravel-bookings/run-tests.yml?branch=master&label=tests&style=flat-square)](https://github.com/masterix21/laravel-bookings/actions/workflows/run-tests.yml)
[![PHP Version](https://img.shields.io/packagist/php-v/masterix21/laravel-bookings?style=flat-square)](https://packagist.org/packages/masterix21/laravel-bookings)
[![Total Downloads](https://img.shields.io/packagist/dt/masterix21/laravel-bookings.svg?style=flat-square)](https://packagist.org/packages/masterix21/laravel-bookings)

Add booking functionality to any Eloquent model. Turn your models into bookable resources with time-based reservations, capacity management, planning constraints, overlap detection, and an event-driven architecture.

## Table of Contents

- [Features](#features)
- [Requirements](#requirements)
- [Installation](#installation)
- [Quick Start](#quick-start)
- [Core Concepts](#core-concepts)
- [Advanced Features](#advanced-features)
- [Documentation](#documentation)
- [Testing](#testing)
- [Contributing & Credits](#contributing--credits)

## Features

- 🚀 **Make any Eloquent model bookable** with a single trait
- 📅 **Advanced time period management** powered by [spatie/period](https://github.com/spatie/period)
- 🏢 **Resource capacity control** with configurable concurrency limits
- 📋 **Planning constraints** for weekday and time-window restrictions
- 🔍 **Overlap detection** and conflict prevention
- 🎯 **Event-driven architecture** for audit trails and integrations
- 🗂️ **Polymorphic relationships** for flexible booker and resource types
- 🔗 **Related bookings** with parent-child relationships
- 🔄 **Automatic synchronization** of resources and planning via model events
- 🛡️ **Transaction safety** with automatic rollback on failures

## Requirements

| Requirement | Version       |
|-------------|---------------|
| PHP         | 8.4+          |
| Laravel     | 12.x or 13.x  |

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

See the [installation guide](docs/installation.md) for optional migrations and upgrade notes.

## Quick Start

### 1. Make a model bookable

Add the `IsBookable` trait to any model. A `BookableResource` is created and kept in sync automatically.

```php
use Masterix21\Bookings\Models\Concerns\Bookable;
use Masterix21\Bookings\Models\Concerns\IsBookable;

class Room extends Model implements Bookable
{
    use IsBookable;

    protected $fillable = ['name', 'capacity'];
}
```

### 2. Configure the bookable resource

Each bookable model exposes its `BookableResource`, where you set availability and capacity:

```php
$room = Room::create(['name' => 'Deluxe Suite', 'capacity' => 4]);

$room->bookableResource->update([
    'max' => 1,            // Maximum concurrent bookings
    'size' => 4,           // Resource capacity
    'is_bookable' => true,
    'is_visible' => true,
]);
```

### 3. Make a booking

```php
use Masterix21\Bookings\Actions\BookResource;
use Spatie\Period\Period;
use Spatie\Period\PeriodCollection;

$periods = PeriodCollection::make([
    Period::make('2024-12-25', '2024-12-27'),
]);

$booking = (new BookResource())->run(
    periods: $periods,
    bookableResource: $room->bookableResource,
    booker: auth()->user(),
    label: 'Christmas Holiday',
    meta: ['guests' => 2],
);
```

Overlapping bookings are rejected automatically:

```php
use Masterix21\Bookings\Exceptions\BookingResourceOverlappingException;

try {
    $booking = (new BookResource())->run(/* ... */);
} catch (BookingResourceOverlappingException $e) {
    return response()->json(['error' => 'Time slot already booked'], 409);
}
```

See the [getting started guide](docs/getting-started.md) for a full walkthrough.

## Core Concepts

| Concept            | Description                                                                         |
|--------------------|-------------------------------------------------------------------------------------|
| `BookableResource` | The bookable item, linked to your model (Room, Car, …) via a polymorphic relation.  |
| `Booking`          | A reservation with booker, metadata, and one or more time periods.                  |
| `BookedPeriod`     | An individual time slot within a booking, enabling multi-period reservations.       |
| `BookablePlanning` | Availability rules: working days, time windows, and constraints for a resource.     |

## Advanced Features

Each feature below is summarized here and documented in full under [`docs/`](docs/).

### Booking lifecycle callbacks

Hook custom logic before and after a booking is persisted with `onBookingSaving()` and `onBookingSaved()` — useful for multi-tenancy, logging, or side effects. → [docs/actions.md](docs/actions.md#booking-lifecycle-callbacks)

### Custom resource synchronization

Add the `SyncBookableResource` trait and implement `syncBookableResource()` to push data from your model (visibility, capacity, availability) into its `BookableResource` automatically on save. → [docs/synchronization.md](docs/synchronization.md)

### Planning source pattern

Add the `SyncBookablePlanning` trait so a business model (rate, special offer, seasonal rule) becomes the single source of truth for a resource's availability, with bidirectional navigation between source and planning. → [docs/synchronization.md](docs/synchronization.md)

### Related bookings

Link bookings with parent-child relationships (room + parking, appointment + follow-up). Each booking keeps an independent lifecycle, and children survive parent deletion. Requires an optional migration. → [docs/related-bookings.md](docs/related-bookings.md)

### Planning constraints

Define when a resource can be booked through `BookablePlanning` records — available weekdays plus `starts_at`/`ends_at` windows. → [docs/models.md](docs/models.md)

### Events

The booking lifecycle emits events (`BookingInProgress`, `BookingCompleted`, `BookingFailed`, `BookingChanging`, `BookingChanged`, `BookingChangeFailed`) for audit trails and integrations. → [docs/events.md](docs/events.md)

### Checking availability

```php
$room->isBookedAt(now());            // bool
$room->bookedPeriodsOfDate(today()); // booked periods for a date
$room->bookings;                     // all bookings for the resource
```

## Documentation

| Guide                                              | Description                              |
|----------------------------------------------------|------------------------------------------|
| [Getting Started](docs/getting-started.md)         | Step-by-step quick start                 |
| [Installation](docs/installation.md)               | Installation and optional migrations     |
| [Configuration](docs/configuration.md)             | Configurable models and generators       |
| [Architecture](docs/architecture.md)               | Package design and structure             |
| [Models](docs/models.md)                           | Model relationships and usage            |
| [Actions](docs/actions.md)                         | Core booking operations                  |
| [Synchronization](docs/synchronization.md)         | Resource and planning synchronization    |
| [Events](docs/events.md)                           | Event system and listeners               |
| [Database Schema](docs/database-schema.md)         | Tables and migrations                    |
| [API Reference](docs/api-reference.md)             | Complete API documentation               |
| [Migration Guide](docs/migration-guide.md)         | Upgrading between versions               |
| [Extending](docs/extending.md)                     | Customization and extension points       |
| [Troubleshooting](docs/troubleshooting.md)         | Common issues and solutions              |

### Examples

- 🏨 [Hotel Booking System](docs/examples/hotel-booking.md)
- 🚗 [Car Rental System](docs/examples/car-rental.md)
- 🍽️ [Restaurant Reservations](docs/examples/restaurant-reservations.md)
- 📅 [Service Appointments](docs/examples/service-appointments.md)

## Testing

```bash
composer test           # run the test suite
composer test-coverage  # run with coverage
composer analyse        # run static analysis (PHPStan)
```

See [docs/testing.md](docs/testing.md) for testing strategies.

## Contributing & Credits

- Please see [CONTRIBUTING](.github/CONTRIBUTING.md) for contribution guidelines.
- Please see [CHANGELOG](CHANGELOG.md) for recent changes.
- Report security vulnerabilities via [our security policy](../../security/policy).

Created and maintained by [Luca Longo](https://github.com/masterix21) and [all contributors](../../contributors).

## License

The MIT License (MIT). See the [License File](LICENSE.md) for details.
