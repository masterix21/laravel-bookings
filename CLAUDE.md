# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

This is `masterix21/laravel-bookings`, a Laravel package that adds booking functionality to any Eloquent model. The package allows models to be made "bookable" through traits and provides a flexible system for managing time-based resource reservations with periods, planning constraints, and overlap detection.

## Development Commands

### Testing
- `composer test` - Run all tests using Pest
- `vendor/bin/pest` - Direct Pest execution
- `composer test-coverage` - Run tests with coverage report
- `pest-coverage`: An alias for "herd coverage ./vendor/bin/pest --coverage"

### Code Quality
- `composer analyse` - Run PHPStan static analysis
- `composer format` - Format code using Laravel Pint

### Package Development
- `composer build` - Build the package (prepare + testbench build)
- `composer start` - Build and start testbench server for development

### Single Test Execution
- `vendor/bin/pest tests/Feature/Actions/BookResourceTest.php` - Run specific test file
- `vendor/bin/pest --filter="test_name"` - Run specific test by name

## Architecture Overview

### Core Models
- **BookableResource**: The main entity that can be booked, linked to any model via polymorphic relation
- **Booking**: Represents a reservation with code, booker, periods, and metadata
- **BookedPeriod**: Individual time periods within a booking
- **BookablePlanning**: Defines availability rules and constraints for resources
- **BookableRelation**: Manages relationships between bookable resources

### Key Traits & Concerns
- `Bookable` interface: Contract for models that can be booked
- `IsBookable` trait: Adds booking functionality to any Eloquent model
- `HasBookings` trait: For models that can make bookings (users, etc.)
- `UsesBookedPeriods` trait: Handles period-related functionality
- `UsesBookablePlannings` trait: Manages planning and availability logic

### Actions Pattern
The package uses dedicated Action classes:
- `BookResource`: Main booking creation/update logic with transaction safety
- `CheckBookingOverlaps`: Validates booking conflicts and availability

### Event System
Comprehensive event system for booking lifecycle:
- `BookingInProgress`, `BookingCompleted`, `BookingFailed`
- `BookingChanging`, `BookingChanged`, `BookingChangeFailed`

### Period Management
Uses `spatie/period` package for sophisticated time period handling:
- Period collections for multi-slot bookings
- Overlap detection and validation
- Planning constraint enforcement

## Configuration

### Models Configuration
All models are configurable via `config/bookings.php`:

```php
'models' => [
    'user' => \Illuminate\Foundation\Auth\User::class,
    'bookable_resource' => \Masterix21\Bookings\Models\BookableResource::class,
    // ... other models
],
```

### Booking Code Generation
Customizable booking code generators:
```php
'generators' => [
    'booking_code' => \Masterix21\Bookings\Generators\RandomBookingCode::class,
],
```

## Database Structure

### Migrations
The package provides migrations for core tables:
- `bookable_resources` - Main bookable entities
- `bookings` - Booking records with polymorphic booker relation
- `booked_periods` - Individual time slots
- `bookable_plannings` - Availability and constraint rules
- `bookable_relations` - Resource relationships

## Testing Structure

### Test Organization
- **Feature tests**: End-to-end booking scenarios in `tests/Feature/`
- **Unit tests**: Isolated component testing in `tests/Unit/`
- **Test factories**: Database factories in `tests/database/factories/`
- **Test concerns**: Shared test utilities in `tests/Concerns/`

### Test Database
- Uses SQLite in-memory by default
- MySQL configuration available in `phpunit.xml.dist` (commented)
- Factories for all major models available

## Package Dependencies

### Key Dependencies
- `spatie/period` - Time period management
- `spatie/laravel-package-tools` - Package development utilities
- `kirschbaum-development/eloquent-power-joins` - Advanced query joining
- `staudenmeir/eloquent-has-many-deep` - Deep relationship queries
- `staudenmeir/belongs-to-through` - Complex relationship support

## Development Notes

### Polymorphic Relations
Heavy use of polymorphic relationships allows any model to be bookable. When adding new bookable model types, ensure proper morphMap configuration.

### Transaction Safety
All booking operations use database transactions with proper rollback on exceptions. The `BookResource` action handles this automatically.

### Time Zone Considerations
The package works with Carbon/PHP DateTime objects. Ensure proper timezone handling when working with periods across different time zones.

### Event Listeners
When extending functionality, leverage the comprehensive event system rather than modifying core actions directly.
## Coding Standards
When working on this Laravel/PHP project, first read the coding guidelines at @laravel-php-guidelines.md

## Development Principles

### Date and Time Management
- We must use Carbon for date and datetime.

## Communication Guidelines
- Also if we talk in other languages, you must use english only for code and documentations.