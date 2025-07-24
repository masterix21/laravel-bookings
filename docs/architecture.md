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
├── bookings (through BookedPeriods)
├── bookedPeriods (direct relationship)
└── bookablePlannings (availability rules)

Booking  (Reservation record)
├── booker (polymorphic - User, Organization, etc.)
├── creator (optional - who created the booking)
├── bookedPeriods (time slots)
└── relatable (optional polymorphic relation)

BookedPeriod  (Individual time slot)
├── booking (parent booking)
├── bookableResource (what's being booked)
└── relatable (optional polymorphic relation)

BookablePlanning  (Availability rules)
├── bookableResource (resource it applies to)
└── periods (available time periods)
```

#### Model Responsibilities

- **BookableResource**: Resource metadata and availability
- **Booking**: Reservation information and business data
- **BookedPeriod**: Time-based booking slots
- **BookablePlanning**: Availability rules and constraints
- **BookableRelation**: Resource relationships and dependencies

### 2. Actions Layer

Actions encapsulate complex business logic and provide a clean API for booking operations.

#### Action Pattern Implementation

```php
abstract class Action
{
    abstract public function run(...$parameters);
    
    protected function validate(...$parameters): void
    {
        // Validation logic
    }
    
    protected function execute(...$parameters)
    {
        // Core business logic
    }
    
    protected function afterExecution($result): void
    {
        // Post-processing hooks
    }
}
```

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
├── cache (performance settings)
├── validation (business rules)
└── events (event system configuration)
```

## Design Patterns

### 1. Repository Pattern (Implicit)

Eloquent models act as repositories with additional query scopes:

```php
// Model acts as repository
BookableResource::bookable()->visible()->ofType(Room::class)->get();

// Custom scopes provide domain-specific queries
$resource->bookings()->forDateRange($start, $end);
```

### 2. Factory Pattern

Used for generating booking codes and creating model instances:

```php
interface BookingCodeGenerator
{
    public function generate(): string;
}

class RandomBookingCode implements BookingCodeGenerator
{
    public function generate(): string
    {
        return Str::random(8);
    }
}
```

### 3. Observer Pattern

Events and listeners implement the observer pattern:

```php
// Event publisher
Event::dispatch(new BookingCompleted($booking, $periods, $resource));

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

### 5. Template Method Pattern

Actions use template method pattern:

```php
abstract class BaseBookingAction
{
    public function run(...$params)
    {
        $this->validate($params);
        $result = $this->execute($params);
        $this->afterExecution($result);
        return $result;
    }
    
    abstract protected function execute(...$params);
    
    protected function validate(...$params): void
    {
        // Default validation
    }
    
    protected function afterExecution($result): void
    {
        // Default post-processing
    }
}
```

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

Implement custom booking logic:

```php
class CustomBookingAction extends BookResource
{
    protected function execute($periods, $resource, $booker, ...$options)
    {
        // Custom booking logic
        $booking = parent::execute($periods, $resource, $booker, ...$options);
        
        // Additional custom processing
        $this->processCustomLogic($booking);
        
        return $booking;
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