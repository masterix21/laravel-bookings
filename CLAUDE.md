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

#### Bookable Resources
- `Bookable` interface: Contract for models that can be booked
- `IsBookable` trait: Adds booking functionality to any Eloquent model
  - Automatic `syncBookableResource()` call on model save
  - Manages polymorphic relation with `BookableResource`
  - Auto-deletes associated resources on model deletion
- `HasBookings` trait: For models that can make bookings (users, etc.)
- `UsesBookedPeriods` trait: Handles period-related functionality
- `UsesBookablePlannings` trait: Manages planning and availability logic

#### Planning Sources (NEW)
- `BookablePlanningSource` interface: Contract for models that generate planning
- `IsBookablePlanningSource` trait: Adds planning source functionality
  - Automatic `syncBookablePlanning()` call on model save
  - Manages polymorphic relation with `BookablePlanning`
  - Auto-deletes associated planning on model deletion

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
- `create_bookable_resources_table.php.stub` - Main bookable entities
- `create_bookings_table.php.stub` - Booking records with polymorphic booker relation
- `create_booked_periods_table.php.stub` - Individual time slots
- `create_bookable_plannings_table.php.stub` - Availability and constraint rules
- `create_bookable_relations_table.php.stub` - Resource relationships
- `update_bookable_plannings_add_source_columns.php.stub` - Adds polymorphic source relation to plannings (optional)

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

## Advanced Features

### Custom Resource Synchronization

Models implementing `Bookable` can customize how they sync data to their `BookableResource`:

```php
class Room extends Model implements Bookable
{
    use IsBookable;

    public function rates()
    {
        return $this->hasMany(Rate::class);
    }

    // Called automatically on save
    public function syncBookableResource(BookableResource $resource): void
    {
        $resource->update([
            'is_visible' => $this->is_published,
            'is_bookable' => $this->is_available,
        ]);

        // Sync planning from rates
        $this->syncPlanningsFromRates($resource);
    }
}
```

**Key Points:**
- `syncBookableResource(BookableResource $resource)` is called automatically when the model is saved
- The method receives each associated `BookableResource` (handles both single and multiple resources)
- N+1 query optimization: relation is loaded once if not already eager-loaded
- Works with both `bookableResource()` (morphOne) and `bookableResources()` (morphMany)

### Planning Source Pattern

Use `BookablePlanningSource` to link business models (like rates, seasonal rules) to planning:

```php
class Rate extends Model implements BookablePlanningSource
{
    use IsBookablePlanningSource;

    public function room()
    {
        return $this->belongsTo(Room::class);
    }

    // Called automatically on save
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
- Single source of truth: your business model (Rate) controls the planning
- Automatic sync on save via model event
- Bidirectional navigation: `rate->planning` and `planning->source`
- Planning auto-deleted when source is deleted
- Multiple sources can create planning for same resource

**Use Cases:**
- Hotel rates that define room availability periods
- Seasonal pricing with availability constraints
- Special offers with booking rules
- Maintenance schedules that block availability

### Polymorphic Relations Flow

```
Room (implements Bookable)
  └─> bookableResource (morphOne)
        └─> plannings (hasMany)
              ├─> source: Rate (morphTo) - Links back to business model
              ├─> source: SeasonalRule (morphTo)
              └─> source: null - Manual planning
```

### Migration Strategy for Existing Users

The `update_bookable_plannings_add_source_columns.php.stub` migration is **optional**:

1. **New installations**: Both migrations run automatically
2. **Existing installations**:
   - Can continue without the source columns
   - Run the update migration when ready to use `BookablePlanningSource`
   - No breaking changes to existing code

## Coding Standards
When working on this Laravel/PHP project, first read the coding guidelines at @laravel-php-guidelines.md

## Development Principles

### Date and Time Management
- We must use Carbon for date and datetime.

## Communication Guidelines
- Also if we talk in other languages, you must use english only for code and documentations.

## Code Writing Guidelines
- When adding comments in the code, these must be written in English.