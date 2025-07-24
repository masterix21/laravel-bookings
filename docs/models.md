# Models and Relationships

This guide provides detailed information about the Laravel Bookings models, their relationships, and how to work with them effectively.

## Core Models Overview

Laravel Bookings uses five core models that work together to create a flexible booking system:

- **BookableResource** - Makes any model bookable
- **Booking** - Represents a reservation
- **BookedPeriod** - Individual time slots within bookings
- **BookablePlanning** - Defines availability rules
- **BookableRelation** - Manages resource relationships

## BookableResource

The central model that bridges your application models with the booking system.

### Database Schema

```sql
CREATE TABLE bookable_resources (
    id BIGINT UNSIGNED PRIMARY KEY,
    resource_type VARCHAR(255) NOT NULL,  -- Polymorphic type
    resource_id BIGINT UNSIGNED NOT NULL, -- Polymorphic ID
    max INT DEFAULT 1,                     -- Max concurrent bookings
    size INT DEFAULT 1,                    -- Resource capacity
    is_bookable BOOLEAN DEFAULT TRUE,      -- Can be booked
    is_visible BOOLEAN DEFAULT TRUE,       -- Visible in queries
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    
    INDEX idx_resource (resource_type, resource_id),
    INDEX idx_bookable (is_bookable, is_visible)
);
```

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

    // All bookings for this resource
    public function bookings(): HasManyDeep
    {
        return $this->hasManyDeep(
            config('bookings.models.booking'),
            config('bookings.models.booked_period'),
            'bookable_resource_id',
            'booking_id'
        );
    }

    // Direct booked periods
    public function bookedPeriods(): HasMany
    {
        return $this->hasMany(config('bookings.models.booked_period'));
    }

    // Availability planning rules
    public function bookablePlannings(): HasMany
    {
        return $this->hasMany(config('bookings.models.bookable_planning'));
    }

    // Related resources
    public function relations(): HasMany
    {
        return $this->hasMany(config('bookings.models.bookable_relation'));
    }
}
```

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

// Get all bookings
$bookings = $bookableResource->bookings;

// Check current availability
$isCurrentlyBooked = $bookableResource->bookedPeriods()
    ->where('starts_at', '<=', now())
    ->where('ends_at', '>', now())
    ->exists();
```

### Query Scopes

```php
// Only bookable resources
BookableResource::bookable()->get();

// Only visible resources
BookableResource::visible()->get();

// Resources of specific type
BookableResource::ofType(Room::class)->get();

// Available resources (not currently booked)
BookableResource::available()->get();

// Combined scopes
BookableResource::bookable()
    ->visible()
    ->ofType(Room::class)
    ->get();
```

## Booking

Represents a complete booking with metadata and relationships.

### Database Schema

```sql
CREATE TABLE bookings (
    id BIGINT UNSIGNED PRIMARY KEY,
    code VARCHAR(255) UNIQUE,              -- Booking reference code
    booker_type VARCHAR(255),              -- Polymorphic booker type
    booker_id BIGINT UNSIGNED,             -- Polymorphic booker ID
    creator_id BIGINT UNSIGNED,            -- User who created booking
    relatable_type VARCHAR(255),           -- Optional related model type
    relatable_id BIGINT UNSIGNED,          -- Optional related model ID
    label VARCHAR(255),                    -- Booking title/label
    note TEXT,                             -- Additional notes
    meta JSON,                             -- Additional metadata
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    
    INDEX idx_booker (booker_type, booker_id),
    INDEX idx_creator (creator_id),
    INDEX idx_relatable (relatable_type, relatable_id),
    INDEX idx_code (code)
);
```

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

    // User who created the booking
    public function creator(): BelongsTo
    {
        return $this->belongsTo(config('bookings.models.user'));
    }

    // Optional related model
    public function relatable(): MorphTo
    {
        return $this->morphTo();
    }

    // Time periods for this booking
    public function bookedPeriods(): HasMany
    {
        return $this->hasMany(config('bookings.models.booked_period'));
    }

    // Booked resources through periods
    public function bookableResources(): HasManyThrough
    {
        return $this->hasManyThrough(
            config('bookings.models.bookable_resource'),
            config('bookings.models.booked_period'),
            'booking_id',
            'id',
            'id',
            'bookable_resource_id'
        );
    }
}
```

### Usage Examples

```php
// Create a booking
$booking = Booking::create([
    'code' => 'BK-2024-001',
    'booker_type' => User::class,
    'booker_id' => $user->id,
    'creator_id' => auth()->id(),
    'label' => 'Team Meeting',
    'note' => 'Weekly standup meeting',
    'meta' => [
        'attendees' => 8,
        'equipment' => ['projector', 'whiteboard'],
        'catering' => true,
    ],
]);

// Access relationships
$booker = $booking->booker;           // User who made booking
$creator = $booking->creator;         // User who created booking
$periods = $booking->bookedPeriods;   // Time periods
$resources = $booking->bookableResources; // Booked resources

// Work with metadata
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
          ->where('ends_at', '>', now());
})->get();
```

## BookedPeriod

Individual time slots within a booking, linking bookings to resources.

### Database Schema

```sql
CREATE TABLE booked_periods (
    id BIGINT UNSIGNED PRIMARY KEY,
    booking_id BIGINT UNSIGNED NOT NULL,           -- Parent booking
    bookable_resource_id BIGINT UNSIGNED NOT NULL, -- Booked resource
    relatable_type VARCHAR(255),                   -- Optional related model
    relatable_id BIGINT UNSIGNED,                  -- Optional related model ID
    starts_at TIMESTAMP NOT NULL,                  -- Period start time
    ends_at TIMESTAMP NOT NULL,                    -- Period end time
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    
    INDEX idx_booking (booking_id),
    INDEX idx_resource (bookable_resource_id),
    INDEX idx_period (starts_at, ends_at),
    INDEX idx_relatable (relatable_type, relatable_id),
    
    FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE,
    FOREIGN KEY (bookable_resource_id) REFERENCES bookable_resources(id)
);
```

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

    // Original bookable model through resource
    public function resource(): HasOneThrough
    {
        // This relationship is dynamically resolved based on resource_type
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

// Get Spatie Period object
$period = $bookedPeriod->period(); // Returns Period instance

// Calculate duration
$duration = $bookedPeriod->duration(); // Returns Duration instance
$hours = $duration->totalHours();
$minutes = $duration->totalMinutes();

// Check if period is active
$isActive = $bookedPeriod->starts_at <= now() && $bookedPeriod->ends_at > now();

// Check for overlaps
$overlapping = BookedPeriod::where('bookable_resource_id', $resource->id)
    ->where('starts_at', '<', $bookedPeriod->ends_at)
    ->where('ends_at', '>', $bookedPeriod->starts_at)
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
    ->where('ends_at', '>', now())
    ->get();

// Future periods
BookedPeriod::where('starts_at', '>', now())->get();

// Periods within date range
BookedPeriod::whereBetween('starts_at', [$startDate, $endDate])->get();
```

## BookablePlanning

Defines when and how resources can be booked.

### Database Schema

```sql
CREATE TABLE bookable_plannings (
    id BIGINT UNSIGNED PRIMARY KEY,
    bookable_resource_id BIGINT UNSIGNED NOT NULL,
    monday BOOLEAN DEFAULT FALSE,
    tuesday BOOLEAN DEFAULT FALSE,
    wednesday BOOLEAN DEFAULT FALSE,
    thursday BOOLEAN DEFAULT FALSE,
    friday BOOLEAN DEFAULT FALSE,
    saturday BOOLEAN DEFAULT FALSE,
    sunday BOOLEAN DEFAULT FALSE,
    starts_at TIMESTAMP,                    -- Planning validity start
    ends_at TIMESTAMP,                      -- Planning validity end
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    
    INDEX idx_resource (bookable_resource_id),
    INDEX idx_validity (starts_at, ends_at),
    
    FOREIGN KEY (bookable_resource_id) REFERENCES bookable_resources(id)
);
```

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

// Check availability for specific date
$isAvailable = $planning->isAvailableOnDate(Carbon::today());

// Get planning for resource on date
$todayPlanning = BookablePlanning::where('bookable_resource_id', $resource->id)
    ->where('starts_at', '<=', now())
    ->where('ends_at', '>=', now())
    ->where(strtolower(now()->format('l')), true)
    ->first();
```

## BookableRelation

Manages relationships between bookable resources.

### Database Schema

```sql
CREATE TABLE bookable_relations (
    id BIGINT UNSIGNED PRIMARY KEY,
    bookable_resource_id BIGINT UNSIGNED NOT NULL,     -- Parent resource
    related_resource_id BIGINT UNSIGNED NOT NULL,      -- Related resource
    type VARCHAR(255) DEFAULT 'includes',              -- Relationship type
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    
    INDEX idx_resource (bookable_resource_id),
    INDEX idx_related (related_resource_id),
    
    FOREIGN KEY (bookable_resource_id) REFERENCES bookable_resources(id),
    FOREIGN KEY (related_resource_id) REFERENCES bookable_resources(id)
);
```

### Usage Examples

```php
use Masterix21\Bookings\Models\BookableRelation;

// Conference room includes projector
BookableRelation::create([
    'bookable_resource_id' => $conferenceRoom->id,
    'related_resource_id' => $projector->id,
    'type' => 'includes',
]);

// Hotel room requires cleaning service
BookableRelation::create([
    'bookable_resource_id' => $hotelRoom->id,
    'related_resource_id' => $cleaningService->id,
    'type' => 'requires',
]);

// Get all related resources
$relatedResources = $resource->relations()->with('relatedResource')->get();

// Check if booking affects related resources
$affectedResources = BookableRelation::where('bookable_resource_id', $resource->id)
    ->get()
    ->pluck('relatedResource');
```

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
$room->bookableResources(); // HasMany

// Get primary bookable resource
$room->bookableResource(); // HasOne

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

// Check general availability
$isAvailable = $room->isAvailable();
```

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

// Get all periods booked by this user
$user->bookedPeriods(); // HasManyThrough
```

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
    'creator',
    'bookedPeriods' => function ($query) {
        $query->with('bookableResource.resource');
    }
])->get();

// Load resource with all booking data
$resource = BookableResource::with([
    'resource',
    'bookings.booker',
    'bookedPeriods' => function ($query) {
        $query->where('starts_at', '>', now());
    }
])->find($id);
```

### Complex Aggregations

```php
// Booking statistics
$stats = BookableResource::withCount([
    'bookings',
    'bookedPeriods',
    'bookings as completed_bookings_count' => function ($query) {
        $query->whereHas('bookedPeriods', function ($q) {
            $q->where('ends_at', '<', now());
        });
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