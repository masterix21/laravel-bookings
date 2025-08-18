# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.  
Claude must always act as a **top senior Laravel developer**, applying best practices, clean code, and production-ready standards.

## Project Overview

This is `masterix21/laravel-bookings`, a Laravel 12+ package that adds booking functionality to any Eloquent model.  
It allows models to be made *bookable* through traits and provides a flexible system for managing time-based resource reservations with periods, planning constraints, and overlap detection.

The package is fully compatible with:
- **Laravel 12+**
- **PHP 8.2+**
- **PestPHP for testing**

## Development Commands

### Testing
- `composer test` — Run all tests with Pest
- `vendor/bin/pest` — Direct Pest execution
- `composer test-coverage` — Run tests with coverage report
- `pest-coverage` — Shortcut for `"herd coverage ./vendor/bin/pest --coverage"`

### Code Quality
- `composer analyse` — Run PHPStan static analysis
- `composer format` — Format code using Laravel Pint

### Package Development
- `composer build` — Build the package (prepare + testbench build)
- `composer start` — Build and start Laravel Testbench server for development

### Single Test Execution
- `vendor/bin/pest tests/Feature/Actions/BookResourceTest.php` — Run a specific test file
- `vendor/bin/pest --filter="test_name"` — Run a specific test by name

## Architecture Overview

### Core Models
- **BookableResource** — Entity that can be booked, linked to any model via polymorphic relation
- **Booking** — Reservation with code, booker, periods, and metadata
- **BookedPeriod** — Individual time periods within a booking
- **BookablePlanning** — Defines availability rules and constraints
- **BookableRelation** — Manages relationships between bookable resources

### Traits & Concerns
- `Bookable` (interface) — Contract for models that can be booked
- `IsBookable` — Adds booking functionality to models
- `HasBookings` — For models that can make bookings (users, etc.)
- `UsesBookedPeriods` — Handles booked periods
- `UsesBookablePlannings` — Manages planning/availability logic

### Actions Pattern
Dedicated Action classes:
- `BookResource` — Booking creation/update logic with transaction safety
- `CheckBookingOverlaps` — Validates conflicts and availability

### Event System
Booking lifecycle events:
- `BookingInProgress`, `BookingCompleted`, `BookingFailed`
- `BookingChanging`, `BookingChanged`, `BookingChangeFailed`

### Period Management
Based on `spatie/period`:
- Period collections for multi-slot bookings
- Overlap detection & validation
- Planning constraint enforcement

## Configuration

### Models
Configurable in `config/bookings.php`:
```php
'models' => [
    'user' => \Illuminate\Foundation\Auth\User::class,
    'bookable_resource' => \Masterix21\Bookings\Models\BookableResource::class,
],
```

### Booking Code Generation
Customizable booking code generator:
```php
'generators' => [
    'booking_code' => \Masterix21\Bookings\Generators\RandomBookingCode::class,
],
```

## Database Structure

### Core Tables
- `bookable_resources` — Bookable entities
- `bookings` — Booking records (with polymorphic booker relation)
- `booked_periods` — Time slots
- `bookable_plannings` — Availability/constraints
- `bookable_relations` — Resource relationships

## Testing Structure

### Organization
- `tests/Feature/` — End-to-end booking scenarios
- `tests/Unit/` — Isolated component tests
- `tests/database/factories/` — Model factories
- `tests/Concerns/` — Shared test utilities

### Database
- Uses SQLite in-memory by default
- MySQL setup available in `phpunit.xml.dist` (commented)
- Factories for all core models available

## Dependencies

### Core Packages
- `spatie/period` — Time period management
- `spatie/laravel-package-tools` — Package scaffolding
- `kirschbaum-development/eloquent-power-joins` — Advanced joins
- `staudenmeir/eloquent-has-many-deep` — Deep relationships
- `staudenmeir/belongs-to-through` — Complex relationship support

## Development Notes

### Polymorphic Relations
Polymorphic relations enable any model to be bookable.  
When adding new bookable models, update the morphMap.

### Transaction Safety
All booking operations run inside database transactions with rollback support.  
The `BookResource` action enforces this.

### Time Zone Handling
The package uses Carbon/PHP DateTime. Always ensure proper timezone handling when dealing with cross-timezone bookings.

### Event Listeners
Extend via events instead of modifying core actions.

## Coding Standards

- Always work as a **top senior Laravel developer**.
- Follow [Laravel coding guidelines](./laravel-php-guidelines.md).
- Comments in **code must be written in English**.
- Documentation, commit messages, and tests must be written in **English**.
- Use **Carbon** for all date/datetime operations.
- Prefer **typed properties and return types** (PHP 8.2+).
- Use **Enums** instead of constants when possible (Laravel 12 feature).
- Apply **modern Laravel 12 conventions** (Records, improved Eloquent relations, `lazy()` collections).

## Communication Guidelines
Even if we speak in other languages, all **code, documentation, and commits must remain in English**.

---

## Laravel 12 Upgrade Checklist

When working on this package, always take advantage of Laravel 12 features and conventions:

- ✅ **Eloquent Records** — Prefer the new Record classes over traditional model factories for cleaner testing and seeding.  
- ✅ **Native Type Safety** — Always use typed properties and method signatures (return types + parameter types).  
- ✅ **Migration Improvements** — Use native type-safe migration columns (e.g., `->integer()` with defaults, enums).  
- ✅ **Enums** — Replace string-based status/constant fields with native PHP Enums.  
- ✅ **Lazy Collections Enhancements** — Use `lazy()` and `cursor()` for memory-efficient queries.  
- ✅ **Query Builder Improvements** — Use Laravel 12’s new `whenEmpty()` and `whenNotEmpty()` helpers for clean conditional queries.  
- ✅ **New Validation Pipeline** — Use the new validation rules pipeline for cleaner validation logic.  
- ✅ **Improved Morph Relations** — Apply Laravel 12’s refined morph class mapping (`morphMap()`) for polymorphic relations.  
- ✅ **Testing Enhancements** — Prefer PestPHP’s expectations API and Laravel 12’s `assertModelExists()` / `assertModelMissing()` helpers.  
- ✅ **Job Batching & Queues** — When dealing with long-running booking operations, prefer batch jobs with failover handling.  

Claude must always check this list when implementing or refactoring code, ensuring the package stays aligned with **modern Laravel 12 best practices**.
