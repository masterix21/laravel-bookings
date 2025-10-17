# Changelog

All notable changes to `laravel-bookings` will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [1.2.1] - 2025-10-17

### Added
- **Booking Lifecycle Callbacks**: New fluent interface to hook into the booking save lifecycle
  - `onBookingSaving(callable $callback)`: Execute custom logic before booking is saved
  - `onBookingSaved(callable $callback)`: Execute custom logic after booking is saved
  - Supports method chaining for clean, readable code
  - Callbacks receive the Booking instance as parameter
  - Works with both create and update operations
  - Executes within database transaction for safety
  - Fully backward compatible - callbacks are optional
  - Use cases: multi-tenancy, data transformation, logging, cache invalidation, external integrations
- **Custom Booking Code Generators**: Per-booking code generator support
  - New `codeGenerator` parameter in `BookResource::run()` method
  - Accepts both generator class string and instance
  - Allows different code generation strategies per booking type
  - Falls back to configured default when not specified
  - Supports dependency injection when using class string

### Changed
- **Documentation Enhancements**:
  - Added comprehensive callback documentation with 7 practical examples
  - Added custom code generator documentation with usage patterns
  - Updated API reference with new method signatures
  - Enhanced extending guide with advanced usage patterns

### Fixed
- PHP type compatibility: Use `Closure` type for callback properties instead of `callable`
  - Callbacks automatically converted using `Closure::fromCallable()`
  - Public API still accepts any `callable` type for flexibility

## [1.2.0] - 2025-10-15

### Added
- **Parent-Child Relationship Support**: Added support for parent-child relationships between related bookings
  - Enables hierarchical booking structures for complex scenarios
  - Allows bookings to be linked as parent-child for dependency management
- **Planning Matching Strategies**: Enhanced resource availability with planning matching strategies
  - More flexible planning matching for complex availability scenarios
  - Improved resource availability scopes for better query optimization

### Changed
- **Performance Improvements**: Refactored codebase for better performance and maintainability
  - Optimized database queries and relationships
  - Improved code organization and structure
  - Enhanced overall package efficiency
- **Security & Quality**: Enhanced security, performance, and code quality across the package
  - Improved validation and error handling
  - Better security practices throughout the codebase
  - Code quality improvements following Laravel best practices

### Fixed
- Documentation consistency and accuracy improvements
- Code styling fixes throughout the package

## [1.1.1] - 2025-10-08

### Changed
- **Breaking Change - Synchronization is now opt-in**: Extracted synchronization logic into separate traits
  - New `SyncBookableResource` trait: Add this trait to enable automatic `syncBookableResource()` calls on model save
  - New `SyncBookablePlanning` trait: Add this trait to enable automatic `syncBookablePlanning()` calls on model save
  - `IsBookable` trait no longer automatically calls `syncBookableResource()` - this is now opt-in via the `SyncBookableResource` trait
  - `IsBookablePlanningSource` trait no longer automatically calls `syncBookablePlanning()` - this is now opt-in via the `SyncBookablePlanning` trait
  - Removed `syncBookableResource()` method requirement from `Bookable` interface
  - Removed `syncBookablePlanning()` method requirement from `BookablePlanningSource` interface
  - Better separation of concerns: base traits provide relations, sync traits provide automatic updates
- **Dependency Updates**:
  - Require PHP 8.4 (dropped PHP 8.3 support)
  - Require Laravel 12.* only (dropped Laravel 11 and earlier)
  - Updated all dev dependencies to latest compatible versions
- **Migration Improvements**:
  - Simplified `update_bookable_plannings_add_source_columns.php.stub` migration syntax
  - Now uses named parameters for better readability
- **Documentation**:
  - Updated `docs/synchronization.md` with new trait-based approach
  - Added `SyncBookableResource` and `SyncBookablePlanning` traits to all examples
  - Added synchronization documentation link to README.md Key Topics section
  - Clarified opt-in nature of synchronization in all documentation

### Migration Guide
If you were using the synchronization features from version 1.1.0:

1. **For models using resource synchronization**: Add `use SyncBookableResource;` trait
   ```php
   class Room extends Model implements Bookable
   {
       use IsBookable;
       use SyncBookableResource; // Add this line

       public function syncBookableResource(BookableResource $resource): void
       {
           // Your sync logic
       }
   }
   ```

2. **For models using planning synchronization**: Add `use SyncBookablePlanning;` trait
   ```php
   class Rate extends Model implements BookablePlanningSource
   {
       use IsBookablePlanningSource;
       use SyncBookablePlanning; // Add this line

       public function syncBookablePlanning(): void
       {
           // Your sync logic
       }
   }
   ```

3. If you don't need automatic synchronization, simply don't add the sync traits and remove the sync methods

## [1.1.0] - 2025-10-07

### Added
- **Custom Resource Synchronization**: `syncBookableResource()` method for automatic resource updates on model save
  - Automatically syncs data from bookable models to their BookableResource
  - Handles both single (`bookableResource`) and multiple resources (`bookableResources`)
  - N+1 query optimized with automatic relation loading
  - Called via model event hooks in `IsBookable` trait
- **Planning Source Pattern**: Link business models directly to planning with polymorphic relations
  - New `BookablePlanningSource` interface for models that generate planning
  - New `IsBookablePlanningSource` trait with automatic sync functionality
  - `syncBookablePlanning()` method called automatically on model save
  - Bidirectional navigation: `source->planning` and `planning->source`
  - Automatic planning deletion when source model is deleted
- **Polymorphic Planning Relations**: `BookablePlanning` now supports `source_type` and `source_id`
  - Allows rates, special offers, and other models to directly control planning
  - Multiple sources can create planning for the same resource
  - Optional migration for existing installations: `update_bookable_plannings_add_source_columns.php.stub`
- **Comprehensive Documentation**:
  - New `docs/synchronization.md` with detailed guides and examples
  - Updated `CLAUDE.md` with architecture details
  - Updated `README.md` with quick start examples
- **Test Coverage**:
  - Complete test suite for `IsBookable` sync functionality (23 tests, 73 assertions)
  - Complete test suite for `IsBookablePlanningSource` (6 tests, 15 assertions)
  - Test models: `Rate` for planning source testing
  - Factories and migrations for all test scenarios

### Changed
- `Bookable` interface now requires `syncBookableResource(BookableResource $resource): void` method
- `IsBookable` trait now automatically calls `syncBookableResource()` on model save
- `BookablePlanning` model now includes `source(): MorphTo` relation
- Test migrations now properly sorted alphabetically to ensure correct execution order
- `TestCase` improved to handle dynamic migration loading with proper ordering

### Migration Notes
- **Breaking Change**: Models implementing `Bookable` must now implement `syncBookableResource()` method
  - Add empty implementation if no sync needed: `public function syncBookableResource(BookableResource $resource): void {}`
- **Optional Feature**: Planning source columns are optional for existing installations
  - Run `update_bookable_plannings_add_source_columns.php` migration when ready to use the feature
  - No action needed if not using `BookablePlanningSource`

## [1.0.0]

### Added
- Comprehensive test suite with Pest framework
- Event-driven architecture for booking lifecycle
- Transaction safety with automatic rollback
- Support for complex multi-period bookings
- Advanced planning constraints and validation
- Resource capacity management
- Overlap detection and conflict prevention
- Size-based booking system with capacity management
- Custom exception handling for booking failures
- UnbookableReason enum for standardized rejection reasons
- Planning validation events (PlanningValidationStarted, PlanningValidationPassed, PlanningValidationFailed)
- Batch processing for planning validation with configurable batch sizes
- Scoping system for bookable and visible resources
- Period-based date validation and overlap detection
- Relational booking constraints with free size validation

### Changed
- Migrated from PHPUnit to Pest for testing
- Enhanced code styling with PHP CS Fixer
- Improved database schema design
- Restructured model concerns for better organization
- Enhanced booking validation with comprehensive exception handling
- Improved performance with optimized database queries

### Fixed
- Various styling and code quality improvements
- Database relationship optimizations
- Exception handling for edge cases in booking validation
- Performance issues with large datasets in planning validation
- Memory optimization for batch processing operations

## [0.0.2]

### Added
- Booking code generation system
- Meta data support for bookings
- Relatable concept for BookedPeriod
- Enhanced BookResource action
- RandomBookingCode generator

### Changed
- Updated composer configuration

### Fixed
- Code styling improvements
- Database column fixes

## [0.0.1]

### Added
- Initial package structure
- Core models (BookableResource, Booking, BookedPeriod, BookablePlanning, BookableRelation)
- Database migrations for all core tables
- IsBookable trait for making models bookable
- HasBookings trait for booker models
- BookResource action for creating bookings
- CheckBookingOverlaps action for validation
- Basic planning and constraint system
- Polymorphic relationships support
- Integration with Spatie Period library
- Comprehensive configuration system
- Service provider with auto-discovery
- Factory classes for testing
- Basic test suite structure

### Features
- **Core Booking System**: Complete booking functionality with time periods
- **Resource Management**: Make any Eloquent model bookable
- **Planning Constraints**: Define availability rules and working hours
- **Overlap Detection**: Prevent conflicting bookings automatically
- **Event System**: Comprehensive events for booking lifecycle
- **Polymorphic Relations**: Flexible booker and resource types
- **Transaction Safety**: Automatic rollback on booking failures
- **Test Coverage**: Extensive test suite with factories
- **Configuration**: Highly configurable models and generators

[Unreleased]: https://github.com/masterix21/laravel-bookings/compare/1.2.1...HEAD
[1.2.1]: https://github.com/masterix21/laravel-bookings/compare/1.2.0...1.2.1
[1.2.0]: https://github.com/masterix21/laravel-bookings/compare/1.1.1...1.2.0
[1.1.1]: https://github.com/masterix21/laravel-bookings/compare/1.1.0...1.1.1
[1.1.0]: https://github.com/masterix21/laravel-bookings/compare/1.0.0...1.1.0
[1.0.0]: https://github.com/masterix21/laravel-bookings/compare/0.0.2...1.0.0
[0.0.2]: https://github.com/masterix21/laravel-bookings/compare/0.0.1...0.0.2
[0.0.1]: https://github.com/masterix21/laravel-bookings/releases/tag/0.0.1
