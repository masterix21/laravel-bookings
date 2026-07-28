# Models and Relationships

This guide provides detailed information about the Laravel Bookings models, their relationships, and how to work with them effectively.

## Core Models Overview

Laravel Bookings uses five core models that work together to create a flexible booking system:

- **BookableResource** - Makes any model bookable
- **Booking** - Represents a reservation
- **BookedPeriod** - Individual time slots within bookings
- **BookablePlanning** - Defines availability rules
- **BookableRelation** - Data-only relationships between resources

## BookableResource

The central model that bridges your application models with the booking system.

### Database Schema

```sql
CREATE TABLE bookable_resources (
    id BIGINT UNSIGNED PRIMARY KEY,
    code VARCHAR(60) NULL,                 -- Optional free-form identifier
    resource_type VARCHAR(255) NOT NULL,   -- Polymorphic type
    resource_id BIGINT UNSIGNED NOT NULL,  -- Polymorphic ID
    min INT UNSIGNED NULL,                 -- Not enforced by the package
    max INT UNSIGNED NULL,                 -- Max concurrent bookings; NULL = unlimited, 0 = never bookable
    max_nested INT UNSIGNED NULL,          -- Not enforced by the package
    size INT UNSIGNED DEFAULT 1,           -- Resource capacity
    is_visible BOOLEAN DEFAULT TRUE,       -- Visible in queries
    is_bookable BOOLEAN DEFAULT TRUE,      -- Can be booked
    created_at TIMESTAMP,
    updated_at TIMESTAMP,

    INDEX idx_resource (resource_type, resource_id)
);
```

See [Database Schema](database-schema.md#bookable_resources) for the full column reference.

### Relationships

```php
<?php

namespace Masterix21\Bookings\Models;

use Illuminate\Database\Eloquent\Model;

class BookableResource extends Model
{
    // Polymorphic relationship to any model
    public function resource(): MorphTo
    {
        return $this->morphTo();
    }

    // Direct booked periods
    public function bookedPeriods(): HasMany
    {
        return $this->hasMany(config('bookings.models.booked_period'))->chaperone();
    }

    // Availability planning rules
    public function bookablePlannings(): HasMany
    {
        return $this->hasMany(config('bookings.models.bookable_planning'));
    }

    // BookableRelation rows owned by this resource (data-only, see BookableRelation below)
    public function bookableRelations(): HasMany
    {
        return $this->hasMany(config('bookings.models.bookable_relation'), 'parent_bookable_resource_id');
    }
}
```

There is no `bookings()` relation on `BookableResource` itself. To list bookings for a resource, go through its periods (`$resource->bookedPeriods()->with('booking')->get()`), or use `IsBookable::bookings()` on the owning model (e.g. `$room->bookings`).

### Usage Examples

```php
// Create a bookable resource for a room
$room = Room::create(['name' => 'Conference Room A']);

$bookableResource = BookableResource::create([
    'resource_type' => Room::class,
    'resource_id' => $room->id,
    'max' => 1,        // Only one booking at a time
    'size' => 10,      // Capacity for 10 people
    'is_bookable' => true,
    'is_visible' => true,
]);

// Access the original model
$originalRoom = $bookableResource->resource;

// Get periods with their bookings for the resource
$periods = $bookableResource->bookedPeriods()->with('booking')->get();

// Check current availability
$isCurrentlyBooked = $bookableResource->bookedPeriods()
    ->where('is_excluded', false)
    ->where('starts_at', '<=', now())
    ->where('ends_at', '>=', now())
    ->exists();
```

### Query Scopes

```php
// Only bookable / unbookable resources
BookableResource::bookable()->get();
BookableResource::unbookable()->get();

// Only visible / hidden resources
BookableResource::visible()->get();
BookableResource::hidden()->get();

// Resources of a specific type — no dedicated scope, filter directly
BookableResource::where('resource_type', Room::class)->get();

// Resources with free capacity for a period (ignores planning)
BookableResource::availableSlotForPeriod($period)->get();

// Resources with free capacity AND a valid planning for the period
BookableResource::availableForPeriod($period)->get();

// Eager-load bookings overlapping a period, without filtering resources
BookableResource::withBookingsInPeriod($period)->get();

// Combined scopes
BookableResource::bookable()
    ->visible()
    ->where('resource_type', Room::class)
    ->get();
```

There is no `ofType()` or `available()` scope.

### Availability semantics

`scopeAvailableSlotForPeriod(Period $period)` and `scopeWithBookingsInPeriod(Period $period)` both use inclusive boundaries (`starts_at <= end AND ends_at >= start`), matching `CheckBookingOverlaps` — a resource that shows as available for a period will not then fail to book on a touching/back-to-back period. Both scopes ignore `is_excluded = true` periods when counting occupancy. `scopeAvailableSlotForPeriod` compares `max` against the peak number of *distinct* bookings active at any single instant of the requested period — the exact semantics `CheckBookingOverlaps` applies at booking time. `max = null` on a `BookableResource` means unlimited capacity, not "never available"; `max = 0` means zero capacity and the resource never appears available.

## Booking

Represents a complete booking with metadata and relationships.

### Database Schema

```sql
CREATE TABLE bookings (
    id BIGINT UNSIGNED PRIMARY KEY,
    parent_booking_id BIGINT UNSIGNED NULL, -- Self-referencing, for related bookings
    code VARCHAR(64) UNIQUE,               -- Booking reference code
    booker_type VARCHAR(255),              -- Polymorphic booker type
    booker_id BIGINT UNSIGNED,             -- Polymorphic booker ID
    label VARCHAR(255),                    -- Booking title/label
    note TEXT,                             -- Additional notes
    meta JSON,                             -- Additional metadata
    created_at TIMESTAMP,
    updated_at TIMESTAMP,

    INDEX idx_booker (booker_type, booker_id),
    INDEX idx_code (code)
);
```

There is **no** `creator_id` or `relatable_type`/`relatable_id` column on `bookings` — the "related model" concept (`relatable`) lives on `booked_periods`, one level down. See [Database Schema](database-schema.md#bookings).

### Relationships

```php
<?php

namespace Masterix21\Bookings\Models;

class Booking extends Model
{
    // Who made the booking (polymorphic)
    public function booker(): MorphTo
    {
        return $this->morphTo();
    }

    // First time period for this booking
    public function bookedPeriod(): HasOne
    {
        return $this->hasOne(config('bookings.models.booked_period'));
    }

    // All time periods for this booking
    public function bookedPeriods(): HasMany
    {
        return $this->hasMany(config('bookings.models.booked_period'));
    }

    // Parent booking, if this is a child booking
    public function parentBooking(): BelongsTo
    {
        return $this->belongsTo(config('bookings.models.booking'), 'parent_booking_id');
    }

    // Child bookings, if any
    public function childBookings(): HasMany
    {
        return $this->hasMany(config('bookings.models.booking'), 'parent_booking_id');
    }
}
```

There is no `creator()`, `relatable()`, or `bookableResources()` relation on `Booking`. To reach a booked resource, go through the periods: `$booking->bookedPeriods->first()?->bookableResource`.

### Usage Examples

```php
// Create a booking
$booking = Booking::create([
    'code' => 'BK-2024-001',
    'booker_type' => User::class,
    'booker_id' => $user->id,
    'label' => 'Team Meeting',
    'note' => 'Weekly standup meeting',
    'meta' => [
        'attendees' => 8,
        'equipment' => ['projector', 'whiteboard'],
        'catering' => true,
    ],
]);

// Access relationships
$booker = $booking->booker;           // User who made the booking
$periods = $booking->bookedPeriods;   // Time periods
$resource = $booking->bookedPeriods->first()?->bookableResource; // Booked resource, via the period

// Work with metadata (meta is cast to AsArrayObject, behaves like an array)
$attendeeCount = $booking->meta['attendees'];
$needsCatering = $booking->meta['catering'] ?? false;

// Add metadata
$booking->meta = $booking->meta->merge([
    'confirmed_at' => now()->toISOString(),
    'confirmation_email_sent' => true,
]);
$booking->save();
```

### Query Scopes and Methods

```php
// Bookings for specific booker
Booking::where('booker_type', User::class)
    ->where('booker_id', $user->id)
    ->get();

// Recent bookings
Booking::whereDate('created_at', '>=', now()->subDays(7))->get();

// Bookings with specific metadata
Booking::whereJsonContains('meta->catering', true)->get();

// Active bookings (currently in progress)
Booking::whereHas('bookedPeriods', function ($query) {
    $query->where('starts_at', '<=', now())
          ->where('ends_at', '>=', now());
})->get();
```

## BookedPeriod

Individual time slots within a booking, linking bookings to resources.

### Database Schema

```sql
CREATE TABLE booked_periods (
    id BIGINT UNSIGNED PRIMARY KEY,
    booking_id BIGINT UNSIGNED NOT NULL,            -- Parent booking
    parent_id BIGINT UNSIGNED NULL,                 -- Self-referencing, for grouping periods
    bookable_resource_id BIGINT UNSIGNED NULL,      -- Booked resource (nullable: survives resource deletion)
    relatable_type VARCHAR(255),                    -- Optional related model
    relatable_id BIGINT UNSIGNED,                   -- Optional related model ID
    is_excluded BOOLEAN DEFAULT FALSE,              -- Excluded from occupancy counting when true
    label VARCHAR(255),
    starts_at TIMESTAMP NOT NULL,                   -- Period start time
    ends_at TIMESTAMP NOT NULL,                     -- Period end time
    timezone VARCHAR(255) NULL,
    note TEXT,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    deleted_at TIMESTAMP NULL,                      -- SoftDeletes

    INDEX idx_booking (booking_id),
    INDEX idx_resource_dates (bookable_resource_id, starts_at, ends_at),
    INDEX idx_relatable (relatable_type, relatable_id),

    FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE RESTRICT,
    FOREIGN KEY (bookable_resource_id) REFERENCES bookable_resources(id) ON DELETE SET NULL
);
```

Deleting a `Booking` that still has `booked_periods` rows now fails (`RESTRICT`) until the application removes the periods first; deleting a `BookableResource` sets `bookable_resource_id` to `null` on its periods instead of deleting them, so booking history survives. See [Upgrading from 1.x](migration-guide.md#upgrading-from-1x).

### Relationships

```php
<?php

namespace Masterix21\Bookings\Models;

class BookedPeriod extends Model
{
    // Parent booking
    public function booking(): BelongsTo
    {
        return $this->belongsTo(config('bookings.models.booking'));
    }

    // Booked resource
    public function bookableResource(): BelongsTo
    {
        return $this->belongsTo(config('bookings.models.bookable_resource'));
    }

    // Optional related model
    public function relatable(): MorphTo
    {
        return $this->morphTo();
    }

    // Self-referencing grouping
    public function parent(): BelongsTo
    {
        return $this->belongsTo(static::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(static::class, 'parent_id');
    }
}
```

### Working with Periods

```php
use Spatie\Period\Period;
use Carbon\Carbon;

// Create booked periods
$bookedPeriod = BookedPeriod::create([
    'booking_id' => $booking->id,
    'bookable_resource_id' => $resource->id,
    'starts_at' => Carbon::parse('2024-12-25 09:00'),
    'ends_at' => Carbon::parse('2024-12-25 17:00'),
]);

// Get the Spatie Period object — `period` is an accessor, access it as a property, not a method call
$period = $bookedPeriod->period; // Spatie\Period\Period instance

// There is no duration() method — compute it yourself if needed
$minutes = $bookedPeriod->starts_at->diffInMinutes($bookedPeriod->ends_at);

// Check if period is active
$isActive = $bookedPeriod->starts_at <= now() && $bookedPeriod->ends_at >= now();

// Check for overlaps (matches the package's own inclusive-boundary semantics;
// for real overlap/capacity checks prefer Actions\CheckBookingOverlaps, which
// also ignores is_excluded periods and measures the peak per-instant
// concurrency of distinct bookings, not row counts)
$overlapping = BookedPeriod::where('bookable_resource_id', $resource->id)
    ->where('is_excluded', false)
    ->where('starts_at', '<=', $bookedPeriod->ends_at)
    ->where('ends_at', '>=', $bookedPeriod->starts_at)
    ->where('id', '!=', $bookedPeriod->id)
    ->exists();
```

### Query Examples

```php
// Periods for today
BookedPeriod::whereDate('starts_at', today())->get();

// Periods for specific resource
BookedPeriod::where('bookable_resource_id', $resource->id)->get();

// Currently active periods
BookedPeriod::where('starts_at', '<=', now())
    ->where('ends_at', '>=', now())
    ->get();

// Future periods
BookedPeriod::where('starts_at', '>', now())->get();

// Periods within date range
BookedPeriod::whereBetween('starts_at', [$startDate, $endDate])->get();
```

## BookablePlanning

Defines when a resource can be booked: which weekdays, and an optional validity window.

### Database Schema

```sql
CREATE TABLE bookable_plannings (
    id BIGINT UNSIGNED PRIMARY KEY,
    bookable_resource_id BIGINT UNSIGNED NULL,
    source_type VARCHAR(255) NULL,          -- Optional planning-source model
    source_id BIGINT UNSIGNED NULL,
    label VARCHAR(255) NULL,
    monday BOOLEAN DEFAULT TRUE,
    tuesday BOOLEAN DEFAULT TRUE,
    wednesday BOOLEAN DEFAULT TRUE,
    thursday BOOLEAN DEFAULT TRUE,
    friday BOOLEAN DEFAULT TRUE,
    saturday BOOLEAN DEFAULT TRUE,
    sunday BOOLEAN DEFAULT TRUE,
    matching_strategy VARCHAR(255) DEFAULT 'all', -- 'all' or 'any', see PlanningMatchingStrategy
    starts_at TIMESTAMP NULL,               -- Planning validity start
    ends_at TIMESTAMP NULL,                 -- Planning validity end
    created_at TIMESTAMP,
    updated_at TIMESTAMP,

    INDEX idx_resource_dates (bookable_resource_id, starts_at, ends_at),

    FOREIGN KEY (bookable_resource_id) REFERENCES bookable_resources(id) ON DELETE CASCADE
);
```

All seven weekday columns **default to `true`** — a fresh planning row with no explicit weekday values is open every day. There are no `available_from`, `available_to`, `available_on`, `unavailable_on`, `booking_starts_at`, `booking_ends_at`, or min/max advance-booking-time columns; the only constraints the package understands are the weekday flags (with `matching_strategy`) and the `starts_at`/`ends_at` window. See [Database Schema](database-schema.md#bookable_plannings).

### Usage Examples

```php
use Masterix21\Bookings\Models\BookablePlanning;

// Business hours: Monday-Friday, 9 AM - 6 PM
BookablePlanning::create([
    'bookable_resource_id' => $resource->id,
    'monday' => true,
    'tuesday' => true,
    'wednesday' => true,
    'thursday' => true,
    'friday' => true,
    'saturday' => false,
    'sunday' => false,
    'starts_at' => Carbon::create(2024, 1, 1, 9, 0),
    'ends_at' => Carbon::create(2024, 12, 31, 18, 0),
]);

// Weekend availability
BookablePlanning::create([
    'bookable_resource_id' => $resource->id,
    'saturday' => true,
    'sunday' => true,
    'starts_at' => Carbon::create(2024, 1, 1, 10, 0),
    'ends_at' => Carbon::create(2024, 12, 31, 16, 0),
]);

// Check whether a period matches any planning for a resource — there is no
// isAvailableOnDate()/getAvailableDays() method, use the wherePeriodIsValid scope instead
$hasValidPlanning = BookablePlanning::where('bookable_resource_id', $resource->id)
    ->wherePeriodIsValid($period)
    ->exists();

// Or reuse the resource-level scope, which combines this with capacity:
$isAvailable = BookableResource::whereKey($resource->id)
    ->availableForPeriod($period)
    ->exists();
```

## BookableRelation

Links a parent `BookableResource` to child resources it depends on or bundles together, with optional `is_required`/`min`/`max` metadata.

### Database Schema

```sql
CREATE TABLE bookable_relations (
    id BIGINT UNSIGNED PRIMARY KEY,
    parent_bookable_resource_id BIGINT UNSIGNED NULL,  -- Parent resource
    bookable_resource_id BIGINT UNSIGNED NULL,         -- Related (child) resource
    is_required BOOLEAN NOT NULL DEFAULT FALSE,        -- Data-only flag
    min INT UNSIGNED NULL,                             -- Data-only quantity bound
    max INT UNSIGNED NULL,                             -- Data-only quantity bound
    created_at TIMESTAMP,
    updated_at TIMESTAMP,

    INDEX idx_parent (parent_bookable_resource_id),
    INDEX idx_resource (bookable_resource_id),

    FOREIGN KEY (parent_bookable_resource_id) REFERENCES bookable_resources(id) ON DELETE CASCADE,
    FOREIGN KEY (bookable_resource_id) REFERENCES bookable_resources(id) ON DELETE CASCADE
);
```

> **`BookableRelation` is data-only.** `is_required`, `min`, and `max` are stored and available for your own application logic, but the package itself never reads them when booking, checking overlaps, or validating availability. If you need enforcement (e.g. "a conference room booking requires 1–2 available parking spots"), implement it yourself — typically inside `BookResource::onBookingSaving()` — by loading `bookableRelations()` and checking the related resources with `ensureIsAvailable()`.

### Usage Examples

```php
use Masterix21\Bookings\Models\BookableRelation;

// Conference room requires a projector (informational — not enforced by the package)
BookableRelation::create([
    'parent_bookable_resource_id' => $conferenceRoom->bookableResource->id,
    'bookable_resource_id' => $projector->bookableResource->id,
    'is_required' => true,
    'min' => 1,
    'max' => 1,
]);

// Get all relations declared by a resource
$relations = $conferenceRoom->bookableResource->bookableRelations()->with('bookableResource')->get();
```

`BookableResource::bookableRelations()` is a `hasMany` through `parent_bookable_resource_id`, returning the `BookableRelation` rows owned by that resource. Each `BookableRelation` also exposes `parentBookableResource()` and `bookableResource()` `belongsTo` relations.

## Model Traits

### IsBookable Trait

Makes any Eloquent model bookable by adding necessary relationships and methods.

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Masterix21\Bookings\Models\Concerns\IsBookable;
use Masterix21\Bookings\Models\Concerns\Bookable;

class Room extends Model implements Bookable
{
    use IsBookable;

    protected $fillable = ['name', 'capacity'];
}
```

#### Added Relationships

```php
// Get bookable resources for this model
$room->bookableResources(); // MorphMany

// Get primary bookable resource
$room->bookableResource(); // MorphOne

// Get all bookings through resources
$room->bookings(); // HasManyDeep

// Get all booked periods
$room->bookedPeriods(); // HasManyDeep
```

#### Added Methods

```php
// Check if booked at specific time
$isBooked = $room->isBookedAt(Carbon::now());

// Get booked periods for date
$periods = $room->bookedPeriodsOfDate(Carbon::today());

// Throw if the periods aren't available on any of the model's resources
$room->ensureIsAvailable($periods); // void, throws on failure
```

There is no `isAvailable()` boolean helper — wrap `ensureIsAvailable()` in a try/catch, or query `BookableResource::availableForPeriod()` directly if you just need a yes/no answer without exceptions.

`ensureIsAvailable(PeriodCollection $periods, $relations = null, bool $ignoresUnbookable = false): void` validates planning and capacity. On an `IsBookable` model it passes as soon as **at least one** of the model's `bookableResources` is available, and rethrows the last failure (`NoFreeSizeException`, `OutOfPlanningsException`, `RelationsHaveNoFreeSizeException`, `RelationsOutOfPlanningsException`, or `UnbookableException`) if none are. The same method exists directly on `BookableResource` for single-resource checks.

### HasBookings Trait

For models that can make bookings (users, organizations, etc.).

```php
<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Masterix21\Bookings\Models\Concerns\HasBookings;

class User extends Authenticatable
{
    use HasBookings;
}
```

#### Added Relationships

```php
// Get all bookings made by this user
$user->bookings(); // MorphMany
```

`HasBookings` adds exactly this one relation — there is no `bookedPeriods()` method on it. To reach periods, go through the bookings: `$user->bookings->flatMap->bookedPeriods`.

## Advanced Relationships

### Polymorphic Queries

```php
// Find all bookings by users
Booking::where('booker_type', User::class)->get();

// Find all bookable room resources
BookableResource::where('resource_type', Room::class)->get();

// Complex polymorphic query
$userBookings = Booking::where('booker_type', User::class)
    ->whereHas('bookedPeriods.bookableResource', function ($query) {
        $query->where('resource_type', Room::class);
    })
    ->with(['booker', 'bookedPeriods.bookableResource.resource'])
    ->get();
```

### Eager Loading

```php
// Efficiently load booking data
$bookings = Booking::with([
    'booker',
    'bookedPeriods' => function ($query) {
        $query->with('bookableResource.resource');
    }
])->get();

// Load resource with its booking data
$resource = BookableResource::with([
    'resource',
    'bookedPeriods' => function ($query) {
        $query->where('starts_at', '>', now())->with('booking');
    }
])->find($id);
```

### Complex Aggregations

```php
// Booking statistics
$stats = BookableResource::withCount([
    'bookedPeriods',
    'bookedPeriods as completed_periods_count' => function ($query) {
        $query->where('ends_at', '<', now());
    }
])->get();

// Resource utilization
$utilization = BookableResource::select([
    'id',
    'resource_type',
    'resource_id',
    DB::raw('COUNT(booked_periods.id) as total_periods'),
    DB::raw('SUM(TIMESTAMPDIFF(HOUR, booked_periods.starts_at, booked_periods.ends_at)) as total_hours')
])
->leftJoin('booked_periods', 'bookable_resources.id', '=', 'booked_periods.bookable_resource_id')
->groupBy('bookable_resources.id')
->get();
```

This comprehensive guide covers all aspects of working with Laravel Bookings models and their relationships.
