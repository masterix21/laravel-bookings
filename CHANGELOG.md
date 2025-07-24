# Changelog

All notable changes to `laravel-bookings` will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added
- Comprehensive test suite with Pest framework
- Event-driven architecture for booking lifecycle
- Transaction safety with automatic rollback
- Support for complex multi-period bookings
- Advanced planning constraints and validation
- Resource capacity management
- Overlap detection and conflict prevention

### Changed
- Migrated from PHPUnit to Pest for testing
- Enhanced code styling with PHP CS Fixer
- Improved database schema design

### Fixed
- Various styling and code quality improvements
- Database relationship optimizations

## [0.0.2] - 2024-01-XX

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

## [0.0.1] - 2024-01-XX

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

[Unreleased]: https://github.com/masterix21/laravel-bookings/compare/0.0.2...HEAD
[0.0.2]: https://github.com/masterix21/laravel-bookings/compare/0.0.1...0.0.2
[0.0.1]: https://github.com/masterix21/laravel-bookings/releases/tag/0.0.1
