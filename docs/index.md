# Laravel Bookings Documentation

Welcome to the comprehensive documentation for Laravel Bookings, a powerful Laravel package that adds booking functionality to any Eloquent model. This guide will help you transform your models into bookable resources with advanced features like time-based reservations, capacity management, planning constraints, and event-driven architecture.

## Quick Navigation

### New to Laravel Bookings?

Start here for the fastest path to your first booking:

1. [Installation Guide](installation.md) - Get up and running in minutes
2. [Getting Started](getting-started.md) - Create your first booking with step-by-step tutorial
3. [Configuration](configuration.md) - Customize the package for your needs

### Looking for Something Specific?

**Core Features:**
- [API Reference](api-reference.md) - Complete API documentation
- [Models Guide](models.md) - Understanding model relationships
- [Actions](actions.md) - Working with booking operations
- [Events System](events.md) - Leverage the event-driven architecture

**Real-World Examples:**
- [Hotel Booking System](examples/hotel-booking.md)
- [Car Rental System](examples/car-rental.md)
- [Restaurant Reservations](examples/restaurant-reservations.md)
- [Service Appointments](examples/service-appointments.md)

## Documentation Structure

### 🚀 Getting Started

Perfect for developers new to Laravel Bookings. These guides walk you through installation, basic concepts, and your first implementation.

- **[Installation](installation.md)** - Step-by-step setup guide with requirements, database configuration, Docker setup, and production considerations. Essential first step for any new project.

- **[Getting Started](getting-started.md)** - Comprehensive tutorial covering your first bookable model, creating bookings, checking availability, handling conflicts, and testing. The perfect starting point after installation.

- **[Configuration](configuration.md)** - Complete configuration reference including model customization, code generators, caching strategies, validation rules, and performance tuning. Learn how to tailor the package to your specific needs.

### 📚 Core Concepts

Deep dive into the fundamental concepts and architecture that power Laravel Bookings.

- **[Architecture](architecture.md)** - Comprehensive overview of package design, architectural patterns (Repository, Factory, Observer, Strategy, Template Method), data flow, extension points, and performance considerations. Essential reading for understanding how everything fits together.

- **[Models](models.md)** - Detailed guide to all models (BookableResource, Booking, BookedPeriod, BookablePlanning, BookableRelation), their relationships, traits (IsBookable, HasBookings, UsesBookedPeriods), query scopes, and polymorphic relationships.

- **[Database Schema](database-schema.md)** - Complete database structure documentation including all tables, columns, indexes, foreign keys, constraints, and migration details. Valuable for understanding data storage and optimization.

### 🔧 Core Features

Master the essential features and functionality of the package.

- **[Actions](actions.md)** - In-depth documentation of the Action pattern, BookResource and CheckBookingOverlaps actions, transaction handling, custom action creation, validation strategies, and error handling. Learn how to execute and extend booking operations.

- **[Events](events.md)** - Complete event system guide covering all available events (BookingInProgress, BookingCompleted, BookingFailed, etc.), creating custom listeners, event-driven workflows, audit trails, and integration patterns.

- **[Synchronization](synchronization.md)** - Advanced guide to automatic resource and planning synchronization, implementing SyncBookableResource trait, planning source pattern with SyncBookablePlanning trait, and managing business logic separation.

- **[Related Bookings](related-bookings.md)** - Comprehensive guide to the parent-child booking relationship pattern (v1.2.0+). Learn how to link related bookings together, manage booking families, handle deletion behavior, and implement common use cases like hotel room + parking or appointments + follow-ups.

### 📖 Reference & API

Comprehensive reference documentation for developers.

- **[API Reference](api-reference.md)** - Complete API documentation covering all classes, methods, parameters, return types, exceptions, query scopes, traits, interfaces, and usage examples. Your go-to resource for detailed method signatures.

### 💡 Examples & Use Cases

Real-world implementation examples to inspire your projects.

- **[Hotel Booking System](examples/hotel-booking.md)** - Complete hotel reservation system with rooms, rates, guests, seasonal pricing, check-in/check-out flows, and housekeeping integration.

- **[Car Rental System](examples/car-rental.md)** - Vehicle rental management with fleet management, availability tracking, booking modifications, insurance options, and return processing.

- **[Restaurant Reservations](examples/restaurant-reservations.md)** - Table booking system with table management, party size handling, time slot management, special requests, and waitlist functionality.

- **[Service Appointments](examples/service-appointments.md)** - Appointment scheduling system with service provider management, time slot booking, recurring appointments, cancellations, and reminders.

### 🎯 Advanced Topics

Advanced patterns, testing strategies, and customization for experienced developers.

- **[Testing](testing.md)** - Comprehensive testing guide with unit test examples, integration testing strategies, feature testing patterns, factory usage, mocking techniques, test database configuration, and performance testing.

- **[Extending](extending.md)** - Advanced customization guide covering custom models, action creation, event system extension, validation rules, booking code generators, query scope development, and integration patterns.

### 🆘 Help & Support

Resources to help you solve problems and upgrade between versions.

- **[Troubleshooting](troubleshooting.md)** - Common issues and solutions covering booking conflicts, performance problems, database errors, event system debugging, cache issues, and error handling patterns.

- **[Migration Guide](migration-guide.md)** - Version upgrade guide with breaking changes, migration strategies, deprecated features, and step-by-step upgrade instructions for each major version.

## Recommended Reading Paths

### For Beginners

Follow this path to build a solid foundation:

1. [Installation](installation.md) - Setup and requirements
2. [Getting Started](getting-started.md) - First booking tutorial
3. [Models](models.md) - Understanding relationships
4. [Configuration](configuration.md) - Basic customization
5. Choose an [Example](examples/hotel-booking.md) matching your use case
6. [API Reference](api-reference.md) - Deep dive into available methods

### For Experienced Laravel Developers

Fast track to advanced features:

1. [Architecture](architecture.md) - System design overview
2. [API Reference](api-reference.md) - Complete method catalog
3. [Actions](actions.md) - Business logic patterns
4. [Events](events.md) - Event-driven workflows
5. [Synchronization](synchronization.md) - Advanced patterns
6. [Extending](extending.md) - Customization strategies

### For DevOps & Performance Optimization

Focus on production readiness:

1. [Installation](installation.md) - Production setup and Docker
2. [Configuration](configuration.md) - Performance tuning
3. [Database Schema](database-schema.md) - Indexing and optimization
4. [Testing](testing.md) - Performance testing
5. [Troubleshooting](troubleshooting.md) - Common production issues

## Common Tasks

Quick links to accomplish specific tasks:

**Setup & Configuration:**
- [Install the package](installation.md#installation-steps)
- [Publish configuration files](installation.md#publish-configuration-optional)
- [Run migrations](installation.md#publish-and-run-migrations)
- [Configure custom models](configuration.md#model-configuration)

**Basic Operations:**
- [Make a model bookable](getting-started.md#step-1-create-a-bookable-model)
- [Create your first booking](getting-started.md#step-5-make-your-first-booking)
- [Check availability](getting-started.md#check-availability)
- [Handle booking conflicts](getting-started.md#handle-booking-conflicts)

**Advanced Features:**
- [Create availability rules](getting-started.md#create-availability-rules)
- [Link related bookings](related-bookings.md)
- [Listen to booking events](events.md)
- [Implement custom actions](actions.md#custom-actions)
- [Synchronize resource data](synchronization.md)

**Development:**
- [Write booking tests](testing.md)
- [Create custom validators](extending.md#custom-validators)
- [Extend the event system](extending.md#custom-events)
- [Build custom booking code generators](extending.md#custom-booking-code-generators)

## Package Features at a Glance

- **Flexible Resource System**: Make any Eloquent model bookable with simple traits
- **Advanced Time Management**: Sophisticated period handling using Spatie Period library
- **Capacity Control**: Manage resource limits and concurrent bookings
- **Planning Constraints**: Define availability with weekday and time restrictions
- **Overlap Prevention**: Automatic conflict detection and prevention
- **Event-Driven**: Complete event system for audit trails and integrations
- **Polymorphic Relations**: Flexible booker and resource types
- **Related Bookings**: Parent-child relationships between bookings (v1.2.0+)
- **Well Tested**: Comprehensive test suite with 90%+ coverage
- **Performance Optimized**: Efficient queries with eager loading support
- **Transaction Safe**: Automatic rollback on failures
- **Auto-Sync**: Automatic synchronization with model events
- **Extensible**: Clean extension points for customization

## Getting Help

- **Issues & Bugs**: [GitHub Issues](https://github.com/masterix21/laravel-bookings/issues)
- **Discussions**: [GitHub Discussions](https://github.com/masterix21/laravel-bookings/discussions)
- **Security**: Review [Security Policy](https://github.com/masterix21/laravel-bookings/security/policy)
- **Contributing**: See [Contributing Guidelines](https://github.com/masterix21/laravel-bookings/blob/master/.github/CONTRIBUTING.md)

## Additional Resources

- **[README](../README.md)** - Package overview and quick reference
- **[CHANGELOG](../CHANGELOG.md)** - Version history and release notes
- **[LICENSE](../LICENSE.md)** - MIT License details

---

**Ready to get started?** Begin with the [Installation Guide](installation.md) or jump into the [Getting Started Tutorial](getting-started.md) to create your first booking in minutes!
