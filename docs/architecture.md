# Architecture Overview

This document provides a comprehensive overview of Laravel Bookings' architecture, design patterns, and system organization.

## High-Level Architecture

### Core Principles

Laravel Bookings is built on several key architectural principles:

1. **Single Responsibility Principle**: Each class has a focused, well-defined purpose
2. **Open/Closed Principle**: Extensible through configuration and inheritance
3. **Dependency Inversion**: Depends on interfaces, not concrete implementations
4. **Event-Driven Architecture**: Loosely coupled components communicate through events
5. **Action-Based Logic**: Business logic encapsulated in dedicated Action classes

### System Overview

```
┌─────────────────────────────────────────────────────────────┐
│                    Laravel Application                      │
├─────────────────────────────────────────────────────────────┤
│                Laravel Bookings Package                     │
│  ┌───────────────┐  ┌──────────────┐  ┌─────────────────┐   │
│  │   Actions     │  │    Models    │  │     Events      │   │
│  │  BookResource │  │   Booking    │  │ BookingCompleted│   │
│  │CheckOverlaps  │  │   Resource   │  │ BookingFailed   │   │
│  └───────────────┘  │   Period     │  └─────────────────┘   │
│                     │   Planning   │                        │
│  ┌───────────────┐  └──────────────┘  ┌─────────────────┐   │
│  │ Generators    │                    │   Exceptions    │   │
│  │ BookingCode   │  ┌──────────────┐  │   Overlapping   │   │
│  │   Random      │  │   Concerns   │  │   NoFreeSize    │   │
│  └───────────────┘  │  IsBookable  │  └─────────────────┘   │
│                     │ HasBookings  │                        │
│                     └──────────────┘                        │
└─────────────────────────────────────────────────────────────┘
```

## Component Architecture

### 1. Models Layer

The model layer follows Laravel's Eloquent ORM patterns with additional bookings-specific functionality.

#### Core Models Hierarchy

```php
BookableResource  (Main bookable entity)
├── resource (polymorphic to any Model)
├── bookedPeriods (direct relationship)
├── bookablePlannings (availability rules)
└── bookableRelations (data-only, not enforced by the package)

Booking  (Reservation record)
├── booker (polymorphic - User, Organization, etc.)
├── bookedPeriods (time slots)
├── parentBooking / childBookings (self-referencing, optional)

BookedPeriod  (Individual time slot)
├── booking (parent booking)
├── bookableResource (what's being booked, nullable)
└── relatable (optional polymorphic relation)

BookablePlanning  (Availability rules)
├── bookableResource (resource it applies to)
└── source (optional polymorphic origin — the planning-source pattern)
```

There is no `bookings` relation directly on `BookableResource`, and `Booking` has no `creator` or `relatable` relation — the "related model" concept lives one level down, on `BookedPeriod`.

#### Model Responsibilities

- **BookableResource**: Resource metadata and availability
- **Booking**: Reservation information and business data
- **BookedPeriod**: Time-based booking slots
- **BookablePlanning**: Availability rules and constraints
- **BookableRelation**: Resource relationships and dependencies

### 2. Actions Layer

Actions encapsulate complex business logic and provide a clean API for booking operations.

#### Action Pattern Implementation

There is no abstract `Action`/`BaseBookingAction` base class — actions are plain classes with a public `run()` method and whatever protected helpers they need. `BookResource`, for example, has `run()`, plus protected `create()`, `update()`, `executeInTransaction()`, and `emitFailureEvent()` helpers — but nothing enforces that shape on other actions. `CheckBookingOverlaps` is a different plain class with just `run()` and its own protected helpers. If you write a custom action, you're free to structure it however fits — there's no interface or base class to extend.

#### Core Actions

1. **BookResource**
   - Primary booking creation/update logic
   - Transaction management
   - Event dispatching
   - Overlap validation

2. **CheckBookingOverlaps**
   - Availability validation
   - Conflict detection
   - Planning constraint checking

3. **Custom Actions** (extensible)
   - CancelBooking
   - ModifyBooking
   - BulkBooking

### 3. Event System

Events provide hooks for extending functionality without modifying core code.

#### Event Flow

```
Booking Request
       ↓
BookingInProgress (fired)
       ↓
   Validation
       ↓
  Database Transaction
       ↓
BookingCompleted (fired) / BookingFailed (fired)
       ↓
   Event Listeners
       ↓
  Side Effects (emails, notifications, etc.)
```

#### Event Types

- **Progress Events**: `BookingInProgress`, `BookingChanging`
- **Success Events**: `BookingCompleted`, `BookingChanged`
- **Failure Events**: `BookingFailed`, `BookingChangeFailed`

### 4. Configuration System

Centralized configuration allows customization without code changes.

#### Configuration Architecture

```php
config/bookings.php
├── models (customizable model classes)
├── generators (code generation strategies)
├── planning_validation (batch_size for relation checks)
└── booking_update (preserve_deleted_periods on update)
```

There is no `cache`, `validation`, or `events` key — see [Configuration](configuration.md) for the complete, real config file.

## Design Patterns

### 1. Repository Pattern (Implicit)

Eloquent models act as repositories with additional query scopes:

```php
// Model acts as repository
BookableResource::bookable()->visible()->where('resource_type', Room::class)->get();

// Real domain-specific scopes ship with the package
BookableResource::availableForPeriod($period)->get();
```

There is no `ofType()` scope, and `BookableResource` has no `bookings()` relation to call `forDateRange()` on.

### 2. Factory Pattern

Used for generating booking codes:

```php
interface BookingCodeGenerator
{
    public function run(?string $prefix = null, ?string $suffix = null): string;
}

class RandomBookingCode implements BookingCodeGenerator
{
    public function run(?string $prefix = null, ?string $suffix = null): string
    {
        // Builds a 64-character code from prefix + ULID + random chars + suffix
        // — see the RandomBookingCode source for the exact algorithm.
    }
}
```

### 3. Observer Pattern

Events and listeners implement the observer pattern:

```php
// Event publisher (inside BookResource, after the transaction commits)
Event::dispatch(new BookingCompleted($booking, $periods));

// Observers (listeners)
class SendBookingConfirmation
{
    public function handle(BookingCompleted $event): void
    {
        // Send confirmation email
    }
}
```

### 4. Strategy Pattern

Different booking strategies can be implemented:

```php
interface BookingStrategy
{
    public function book(PeriodCollection $periods, BookableResource $resource, $booker): Booking;
}

class StandardBookingStrategy implements BookingStrategy
{
    public function book(...): Booking
    {
        // Standard booking logic
    }
}

class RecurringBookingStrategy implements BookingStrategy
{
    public function book(...): Booking
    {
        // Recurring booking logic
    }
}
```

### 5. Callback Hooks (not Template Method)

`BookResource` doesn't use the Template Method pattern — there's no abstract base class with `validate()`/`execute()`/`afterExecution()` hooks to override. Instead it exposes two chainable callback setters, `onBookingSaving(callable $callback)` and `onBookingSaved(callable $callback)`, both invoked with the `Booking` instance from inside the same database transaction as the save itself:

```php
(new BookResource)
    ->onBookingSaving(fn (Booking $booking) => $booking->tenant_id = auth()->user()->tenant_id)
    ->onBookingSaved(fn (Booking $booking) => Log::info("Booking {$booking->code} created"))
    ->run(/* ... */);
```

See [Actions → Booking Lifecycle Callbacks](actions.md#booking-lifecycle-callbacks) for the full guide.

## Data Flow

### 1. Booking Creation Flow

```
1. Request Validation
   ├── Period validation (start < end)
   ├── Resource validation (exists, bookable)
   └── Booker validation (valid model)

2. Business Logic Validation
   ├── Overlap checking
   ├── Planning validation
   ├── Capacity checking
   └── Custom validation rules

3. Database Transaction
   ├── Create Booking record
   ├── Create BookedPeriod records
   ├── Update resource metadata
   └── Log booking history

4. Event Dispatching
   ├── BookingCompleted event
   ├── Listener notifications
   └── External integrations

5. Response Generation
   ├── Format booking data
   ├── Include relationships
   └── Return standardized response
```

### 2. Availability Checking Flow

```
1. Period Preparation
   ├── Parse requested periods
   ├── Normalize time zones
   └── Validate period format

2. Existing Bookings Query
   ├── Find overlapping periods
   ├── Apply resource filters
   └── Consider booking exceptions

3. Planning Validation
   ├── Check resource planning
   ├── Validate time constraints
   └── Apply business rules

4. Capacity Calculation
   ├── Count concurrent bookings
   ├── Check resource limits
   └── Calculate available slots

5. Result Compilation
   ├── Aggregate availability data
   ├── Format response
   └── Include conflict details
```

## Extension Points

### 1. Custom Models

Replace default models with custom implementations:

```php
// config/bookings.php
'models' => [
    'booking' => App\Models\CustomBooking::class,
    'bookable_resource' => App\Models\CustomResource::class,
],
```

### 2. Custom Actions

`BookResource` has no `execute()` hook to override — its create/update logic lives in protected `create()`/`update()` methods that are not designed for subclassing. Instead, wrap it and use the lifecycle callbacks:

```php
class CustomBookingAction
{
    public function __construct(private BookResource $bookResource) {}

    public function run(PeriodCollection $periods, BookableResource $resource, $booker, array $options = []): ?Booking
    {
        return $this->bookResource
            ->onBookingSaved(fn (Booking $booking) => $this->processCustomLogic($booking))
            ->run(periods: $periods, bookableResource: $resource, booker: $booker, ...$options);
    }

    private function processCustomLogic(Booking $booking): void
    {
        // Additional custom processing
    }
}
```

### 3. Custom Events

Create domain-specific events:

```php
class BookingApprovalRequired
{
    public function __construct(
        public Booking $booking,
        public User $approver
    ) {}
}

// Dispatch custom event
Event::dispatch(new BookingApprovalRequired($booking, $approver));
```

### 4. Custom Validators

Implement custom validation rules:

```php
class CustomBookingValidator
{
    public function validate(PeriodCollection $periods, BookableResource $resource): bool
    {
        // Custom validation logic
        return $this->checkCustomRules($periods, $resource);
    }
}
```

## Performance Considerations

### 1. Database Optimization

- **Indexes**: Critical indexes on foreign keys and date columns
- **Query Optimization**: Use of eager loading and query scopes
- **Caching**: Strategic caching of resource availability

### 2. Memory Management

- **Chunking**: Process large datasets in chunks
- **Lazy Loading**: Load relationships only when needed
- **Collection Optimization**: Use efficient collection methods

### 3. Concurrency Handling

- **Database Transactions**: Ensure data consistency
- **Locking**: Prevent race conditions in booking creation
- **Queue Processing**: Handle heavy operations asynchronously

## Security Architecture

### 1. Authorization

- **Policy-Based**: Use Laravel policies for resource access
- **Role-Based**: Implement role-based permissions
- **Resource-Level**: Control access to specific resources

### 2. Data Protection

- **Input Validation**: Sanitize all user inputs
- **SQL Injection Prevention**: Use parameterized queries
- **Mass Assignment Protection**: Define fillable/guarded properties

### 3. Audit Trail

- **Booking History**: Track all booking changes
- **User Actions**: Log user interactions
- **System Events**: Monitor system behavior

This architecture provides a solid foundation for building scalable, maintainable booking systems while remaining flexible enough to accommodate diverse business requirements.