# API Reference

This comprehensive API reference covers all classes, methods, and interfaces provided by Laravel Bookings.

## Core Actions

### BookResource

The main action for creating and updating bookings.

```php
use Masterix21\Bookings\Actions\BookResource;

$action = new BookResource();
```

#### Methods

##### `onBookingSaving()`

Register a callback to be executed before the booking is saved.

```php
public function onBookingSaving(callable $callback): self
```

**Parameters:**
- `$callback` (callable) - Callback function receiving the `Booking` instance before save

**Returns:** `self` - For method chaining

**Example:**
```php
$booking = (new BookResource())
    ->onBookingSaving(function (Booking $booking) {
        $booking->tenant_id = auth()->user()->tenant_id;
    })
    ->run(/* ... */);
```

##### `onBookingSaved()`

Register a callback to be executed after the booking is saved.

```php
public function onBookingSaved(callable $callback): self
```

**Parameters:**
- `$callback` (callable) - Callback function receiving the `Booking` instance after save

**Returns:** `self` - For method chaining

**Example:**
```php
$booking = (new BookResource())
    ->onBookingSaved(function (Booking $booking) {
        Log::info("Booking {$booking->code} created");
    })
    ->run(/* ... */);
```

##### `run()`

Creates or updates a booking with the specified parameters.

```php
public function run(
    PeriodCollection $periods,
    BookableResource $bookableResource,
    ?Model $booker,
    ?Booking $booking = null,
    ?Booking $parent = null,
    ?Model $relatable = null,
    ?string $code = null,
    ?string $codePrefix = null,
    ?string $codeSuffix = null,
    ?string $label = null,
    ?string $note = null,
    ?array $meta = null,
    string|BookingCodeGenerator|null $codeGenerator = null,
): ?Booking
```

**Parameters:**
- `$periods` (PeriodCollection) - Time periods to book
- `$bookableResource` (BookableResource) - Resource to book
- `$booker` (?Model) - Entity making the booking (polymorphic)
- `$booking` (?Booking) - Existing booking for updates
- `$parent` (?Booking) - Parent booking for creating related bookings
- `$relatable` (?Model) - Related model for the booked periods
- `$code` (?string) - Custom booking code
- `$codePrefix` (?string) - Prefix for generated code
- `$codeSuffix` (?string) - Suffix for generated code
- `$label` (?string) - Booking label/title
- `$note` (?string) - Additional notes
- `$meta` (?array) - Additional metadata
- `$codeGenerator` (string|BookingCodeGenerator|null) - Custom booking code generator

**Update semantics:** when `$booking` is an existing booking, `$code`, `$booker`, `$parent`, `$label`, `$note`, and `$meta` are all "null keeps the current value" — passing `null` (or omitting the argument) leaves the existing column untouched instead of clearing it. `$booker->getMorphClass()` is used to store `booker_type`, so a configured `Relation::morphMap()` is honored.

**Returns:** `?Booking` - The created or updated booking

**Throws:**
- `BookingResourceOverlappingException` - When booking conflicts exist
- `OutOfPlanningsException` - When no valid planning exists (only if you call `ensureIsAvailable()` yourself first — `run()` does not validate planning)
- `NoFreeSizeException` - When resource capacity is exceeded (same caveat)
- Any exception thrown by `onBookingSaving` or `onBookingSaved` callbacks

**Example:**
```php
use Spatie\Period\Period;
use Spatie\Period\PeriodCollection;

$periods = PeriodCollection::make([
    Period::make('2024-12-25 09:00', '2024-12-25 17:00')
]);

$booking = (new BookResource())->run(
    periods: $periods,
    bookableResource: $resource,
    booker: $user,
    label: 'Conference Room Booking',
    meta: ['attendees' => 10, 'equipment' => 'projector']
);
```

**Example with Related Bookings:**
```php
// Create parent booking
$parentBooking = (new BookResource())->run(
    periods: PeriodCollection::make([
        Period::make('2024-12-25 14:00', '2024-12-25 16:00')
    ]),
    bookableResource: $room,
    booker: $user,
    label: 'Hotel Room'
);

// Create child booking linked to parent
$childBooking = (new BookResource())->run(
    periods: PeriodCollection::make([
        Period::make('2024-12-25 14:00', '2024-12-25 16:00')
    ]),
    bookableResource: $parkingSpot,
    booker: $user,
    parent: $parentBooking,
    label: 'Parking Spot'
);
```

### CheckBookingOverlaps

Validates booking conflicts and availability.

```php
use Masterix21\Bookings\Actions\CheckBookingOverlaps;

$action = new CheckBookingOverlaps();
```

#### Methods

##### `run()`

Checks for booking overlaps and validates availability.

```php
public function run(
    PeriodCollection $periods,
    BookableResource $bookableResource,
    bool $emitEvent = false,
    bool $throw = false,
    ?Booking $ignoreBooking = null,
): bool
```

**Parameters:**
- `$periods` (PeriodCollection) - Periods to check
- `$bookableResource` (BookableResource) - Resource to check
- `$emitEvent` (bool) - Whether to emit `BookingFailed`/`BookingChangeFailed` on conflict. Defaults to `false`
- `$throw` (bool) - Whether to throw `BookingResourceOverlappingException` on conflict. Defaults to `false`
- `$ignoreBooking` (?Booking) - Booking to ignore in validation (used on updates)

**Returns:** `bool` - True if no conflicts (or `$bookableResource->max` is `null`/blank), false otherwise

**Semantics:** counts the highest number of *distinct* bookings (by `booking_id`) active at any single instant of each requested period — not a sum of overlapping rows — under a row lock on the resource and the candidate periods. `is_excluded = true` periods never count, boundaries are inclusive (`starts_at <= end AND ends_at >= start`), and `max = null` on the resource means unlimited capacity. See [Actions → Concurrency Semantics](actions.md#concurrency-semantics) for details.

**Example:**
```php
$isAvailable = (new CheckBookingOverlaps())->run(
    periods: $periods,
    bookableResource: $resource,
    emitEvent: false,
    throw: false
);

if (!$isAvailable) {
    // Handle conflict
}
```

## Core Models

### BookableResource

Represents a bookable entity linked to your models via polymorphic relationships.

```php
use Masterix21\Bookings\Models\BookableResource;
```

#### Properties

```php
protected $fillable = [
    'code',
    'resource_type',    // Polymorphic type
    'resource_id',      // Polymorphic ID
    'min',              // Minimum concurrent bookings
    'max',              // Maximum concurrent bookings (null = unlimited)
    'max_nested',       // Maximum nested resources
    'size',             // Resource capacity
    'is_visible',       // Whether resource is visible
    'is_bookable',      // Whether resource can be booked
];

protected $casts = [
    'is_visible' => 'bool',
    'is_bookable' => 'bool',
];
```

#### Relationships

##### `resource()`

Polymorphic relationship to the actual resource model.

```php
public function resource(): MorphTo
```

##### `bookedPeriods()`

All booked periods for this resource.

```php
public function bookedPeriods(): HasMany
```

##### `bookablePlannings()`

Availability plannings for this resource.

```php
public function bookablePlannings(): HasMany
```

##### `bookableRelations()`

`BookableRelation` rows owned by this resource (via `parent_bookable_resource_id`).

```php
public function bookableRelations(): HasMany
```

There is no `bookings()` relation on `BookableResource` itself — go through `bookedPeriods()->with('booking')`, or use `IsBookable`'s `bookings()` on the resource's owning model.

#### Scopes

##### `bookable()` / `unbookable()`

Filter by `is_bookable`.

```php
BookableResource::bookable()->get();
BookableResource::unbookable()->get();
```

##### `visible()` / `hidden()`

Filter by `is_visible`.

```php
BookableResource::visible()->get();
BookableResource::hidden()->get();
```

##### `availableSlotForPeriod(Period $period)`

`is_bookable = true` resources whose peak concurrency stays below `max` at every instant of the given period (distinct bookings, `is_excluded = false`, inclusive boundaries) — the same per-instant semantics `CheckBookingOverlaps` applies at booking time. `max = null` means unlimited and always matches; `max = 0` means zero capacity and never matches.

```php
BookableResource::availableSlotForPeriod($period)->get();
```

##### `availableForPeriod(Period $period)`

`availableSlotForPeriod()` combined with a check that at least one `bookablePlannings` row is valid for the period (`wherePeriodIsValid`).

```php
BookableResource::availableForPeriod($period)->get();
```

##### `withBookingsInPeriod(Period $period)`

Eager-loads `bookedPeriods` (with their `booking`) that overlap the given period. Does not filter which resources are returned.

```php
BookableResource::withBookingsInPeriod($period)->get();
```

There is no `ofType()` or `available()` scope — filter by `resource_type` with a normal `where()`, and use `availableSlotForPeriod()`/`availableForPeriod()` for availability.

#### Methods

##### `size()`

Returns the resource's `size`, or `0` if `is_bookable` is `false` (unless `$ignoresUnbookable` is `true`).

```php
public function size(bool $ignoresUnbookable = false): int
```

##### `book()`

Shortcut for `(new BookResource())->run(..., bookableResource: $this, ...)`. Added by the `ImplementsBook` trait.

```php
public function book(
    PeriodCollection $periods,
    ?Booking $booking = null,
    ?Model $booker = null,
    ?Model $relatable = null,
    ?string $code = null,
    ?string $codePrefix = null,
    ?string $codeSuffix = null,
    ?string $label = null,
    ?string $note = null,
    ?array $meta = null,
): ?Booking
```

##### `ensureIsAvailable()`

Validates planning and capacity for this resource. See [IsBookable → `ensureIsAvailable()`](#ensureisavailable) below — the signature is identical.

```php
public function ensureIsAvailable(
    PeriodCollection $periods,
    Collection|EloquentCollection|null $relations = null,
    bool $ignoresUnbookable = false
): void
```

### Booking

Represents a booking with periods, booker, and metadata.

```php
use Masterix21\Bookings\Models\Booking;
```

#### Properties

```php
protected $fillable = [
    'parent_booking_id', // Parent booking ID
    'code',               // Booking code
    'booker_type',        // Polymorphic booker type
    'booker_id',          // Polymorphic booker ID
    'label',               // Booking label
    'note',                 // Additional notes
    'meta',                  // JSON metadata
];

protected function casts(): array
{
    return [
        'meta' => \Illuminate\Database\Eloquent\Casts\AsArrayObject::class,
    ];
}
```

`meta` is cast to `AsArrayObject`, so it behaves like an array (`$booking->meta['attendees']`) and supports `->merge()`, not `Illuminate\Support\Collection`.

#### Relationships

##### `booker()`

Polymorphic relationship to the booking entity.

```php
public function booker(): MorphTo
```

**Example:**
```php
$booking = Booking::find(1);
$user = $booking->booker; // Returns User instance
```

##### `bookedPeriod()` / `bookedPeriods()`

The (first, via `HasOne`) or all (via `HasMany`) time periods for this booking.

```php
public function bookedPeriod(): HasOne
public function bookedPeriods(): HasMany
```

**Example:**
```php
$periods = $booking->bookedPeriods;
foreach ($periods as $period) {
    echo "{$period->starts_at} to {$period->ends_at}";
}
```

##### `parentBooking()`

Get the parent booking if this is a child booking.

```php
public function parentBooking(): BelongsTo
```

**Returns:** `Booking|null` - Parent booking or null if this is an independent booking

**Example:**
```php
$childBooking = Booking::find(1);
$parentBooking = $childBooking->parentBooking;

if ($parentBooking) {
    echo "This is a child of booking: {$parentBooking->code}";
} else {
    echo "This is an independent booking";
}
```

##### `childBookings()`

Get all child bookings if this booking has children.

```php
public function childBookings(): HasMany
```

**Returns:** `Collection<Booking>` - Collection of child bookings

**Example:**
```php
$parentBooking = Booking::find(1);
$children = $parentBooking->childBookings;

echo "This booking has {$children->count()} related bookings";

foreach ($children as $child) {
    echo "Child: {$child->label}";
}
```

**Query Examples:**
```php
// Check if booking has children
if ($booking->childBookings()->exists()) {
    echo "This booking has children";
}

// Count children
$childCount = $booking->childBookings()->count();

// Eager load children
$bookings = Booking::with('childBookings')->get();
```

There is no `creator()`, `relatable()`, or `bookableResources()` relation on `Booking` — resources are reached through `bookedPeriods()->bookableResource`.

### BookedPeriod

Individual time periods within a booking.

```php
use Masterix21\Bookings\Models\BookedPeriod;
```

#### Properties

```php
protected $fillable = [
    'booking_id',           // Parent booking
    'bookable_resource_id', // Booked resource (nullable)
    'relatable_type',       // Related model type
    'relatable_id',         // Related model ID
    'parent_id',            // Self-referencing parent period
    'starts_at',             // Period start
    'ends_at',                // Period end
    'is_excluded',            // Excludes this period from occupancy counting
    'label',
    'note',
];

protected $casts = [
    'is_excluded' => 'boolean',
    'starts_at' => 'datetime',
    'ends_at' => 'datetime',
];
```

`BookedPeriod` uses `SoftDeletes`.

#### Relationships

```php
public function booking(): BelongsTo
public function bookableResource(): BelongsTo
public function relatable(): MorphTo
public function parent(): BelongsTo   // self-referencing
public function children(): HasMany   // self-referencing
```

#### Methods

##### `period()`

Get a Spatie `Period` object for this booked period (an accessor, not a plain method call).

```php
public function period(): Attribute // $bookedPeriod->period returns Spatie\Period\Period
```

There is no `duration()` method — compute it yourself from `$bookedPeriod->period`, e.g. `Carbon::instance($bookedPeriod->starts_at)->diffInMinutes($bookedPeriod->ends_at)`.

### BookablePlanning

Defines availability rules and constraints for resources.

```php
use Masterix21\Bookings\Models\BookablePlanning;
```

#### Properties

```php
protected $fillable = [
    'bookable_resource_id', // Target resource
    'source_type',          // Optional polymorphic source (planning source pattern)
    'source_id',
    'starts_at',             // Planning validity window start
    'ends_at',                // Planning validity window end
    'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday',
    'matching_strategy',       // PlanningMatchingStrategy::All (default) or ::Any
];

protected $casts = [
    'monday' => 'bool', 'tuesday' => 'bool', 'wednesday' => 'bool',
    'thursday' => 'bool', 'friday' => 'bool', 'saturday' => 'bool', 'sunday' => 'bool',
    'starts_at' => 'datetime',
    'ends_at' => 'datetime',
    'matching_strategy' => \Masterix21\Bookings\Enums\PlanningMatchingStrategy::class,
];
```

All seven weekday columns default to `true` at the database level (a fresh planning row with no explicit weekday values is open every day).

#### Relationships

```php
public function bookableResource(): BelongsTo
public function source(): MorphTo // the planning-source model, if any
```

#### Scopes

```php
public function scopeWhereWeekdaysDates(Builder $builder, Collection|array|string $dates): Builder
public function scopeWhereAllDatesAreWithinPeriods(Builder $builder, Collection|array|string $dates): Builder
public function scopeWhereDatesAreWithinPeriods(Builder $builder, Collection|array|string $dates): Builder
public function scopeWhereDatesAreValids(Builder $builder, Collection|array|string $dates): Builder // weekdays + within-period, combined
public function scopeWherePeriodIsValid(Builder $builder, Period $period): Builder // used by scopeAvailableForPeriod
```

There is no `isAvailableOnDate()` or `getAvailableDays()` method on `BookablePlanning` — use the `wherePeriodIsValid()`/`whereDatesAreValids()` scopes instead.

## Traits

### IsBookable

Makes any Eloquent model bookable.

```php
use Masterix21\Bookings\Models\Concerns\IsBookable;
use Masterix21\Bookings\Models\Concerns\Bookable;

class Room extends Model implements Bookable
{
    use IsBookable;
}
```

#### Methods

##### `bookableResources()`

Get all bookable resources for this model.

```php
public function bookableResources(): MorphMany
```

##### `bookableResource()`

Get the primary bookable resource.

```php
public function bookableResource(): MorphOne
```

##### `bookings()`

Get all bookings for this model, resolved through its bookable resource(s).

```php
public function bookings(): HasManyDeep
```

##### `bookedPeriods()`

Get all booked periods for this model.

```php
public function bookedPeriods(): HasManyDeep
```

##### `isBookedAt()`

Check if model is booked at specific time.

```php
public function isBookedAt(Carbon $date): bool
```

##### `bookedPeriodsOfDate()`

Get booked periods for a specific date.

```php
public function bookedPeriodsOfDate(Carbon $date): Collection
```

There is no `isAvailable()` method on `IsBookable` — use `ensureIsAvailable()` (below) inside a try/catch, or `BookableResource::availableForPeriod()` for a query-level check.

##### `ensureIsAvailable()`

Throws unless the periods are available. Delegates to the model's `bookableResources`: passes as soon as at least one resource is available, and rethrows the last failure if none are.

```php
public function ensureIsAvailable(
    PeriodCollection $periods,
    Collection|EloquentCollection|null $relations = null,
    bool $ignoresUnbookable = false
): void
```

**Throws:** `UnbookableException` (no resources at all), or whichever exception the last checked resource raised — `NoFreeSizeException`, `OutOfPlanningsException`, `RelationsHaveNoFreeSizeException`, `RelationsOutOfPlanningsException`.

The same method exists on `BookableResource` for a single-resource check, with an identical signature.

### SyncBookableResource

Enables automatic synchronization of model data to BookableResource on model save events.

```php
use Masterix21\Bookings\Models\BookableResource;
use Masterix21\Bookings\Models\Concerns\Bookable;
use Masterix21\Bookings\Models\Concerns\IsBookable;
use Masterix21\Bookings\Models\Concerns\SyncBookableResource;

class Room extends Model implements Bookable
{
    use IsBookable;
    use SyncBookableResource;

    /**
     * Called automatically when the room is saved
     */
    public function syncBookableResource(BookableResource $resource): void
    {
        $resource->update([
            'is_visible' => $this->is_published,
            'is_bookable' => $this->is_available && $this->is_clean,
            'max' => $this->max_concurrent_bookings,
            'size' => $this->capacity,
        ]);
    }
}
```

**Key Features:**
- Opt-in with `SyncBookableResource` trait
- Automatically called on model save events
- Handles both single resource (`bookableResource`) and multiple resources (`bookableResources`)
- N+1 query optimized - relation loaded once if not already eager-loaded
- Custom logic via `syncBookableResource(BookableResource $resource)` method

#### Methods

##### `syncBookableResource()`

Override this method to define custom synchronization logic.

```php
public function syncBookableResource(BookableResource $resource): void
```

**Parameters:**
- `$resource` (BookableResource) - The bookable resource to sync with

### SyncBookablePlanning

Enables automatic synchronization of planning data from source models on model save events.

```php
use Masterix21\Bookings\Models\Concerns\BookablePlanningSource;
use Masterix21\Bookings\Models\Concerns\IsBookablePlanningSource;
use Masterix21\Bookings\Models\Concerns\SyncBookablePlanning;

class Rate extends Model implements BookablePlanningSource
{
    use IsBookablePlanningSource;
    use SyncBookablePlanning;

    /**
     * Called automatically when the rate is saved
     */
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

**Key Features:**
- Opt-in with `SyncBookablePlanning` trait
- Single source of truth - business model controls planning
- Automatic synchronization on save events
- Bidirectional navigation: `$rate->planning` and `$planning->source`
- Planning auto-deleted when source is deleted
- Multiple sources can create planning for same resource

**Use Cases:**
- Hotel rates that define room availability periods
- Seasonal pricing with availability constraints
- Special offers with booking rules
- Maintenance schedules that block availability

#### Methods

##### `syncBookablePlanning()`

Override this method to define custom planning synchronization logic.

```php
public function syncBookablePlanning(): void
```

### HasBookings

For models that can make bookings (users, organizations).

```php
use Masterix21\Bookings\Models\Concerns\HasBookings;

class User extends Model
{
    use HasBookings;
}
```

#### Methods

##### `bookings()`

Get all bookings made by this entity.

```php
public function bookings(): MorphMany
```

`HasBookings` only adds this one relation — there is no `bookedPeriods()` method on it. To reach periods, go through the bookings: `$user->bookings->flatMap->bookedPeriods`.

## Events

See [Events and Listeners](events.md) for the full guide with real constructor signatures and example listeners. Quick reference:

| Event | Constructor parameters |
|---|---|
| `BookingInProgress` | `BookableResource $bookableResource, PeriodCollection $periods` |
| `BookingCompleted` | `Booking $booking, PeriodCollection $periods` |
| `BookingFailed` | `UnbookableReason $reason, BookableResource $bookableResource, PeriodCollection $periods, ?string $message = null, ?string $stackTrace = null` |
| `BookingChanging` | `Booking $booking, BookableResource $bookableResource, PeriodCollection $periods` |
| `BookingChanged` | `Booking $booking, PeriodCollection $periods` |
| `BookingChangeFailed` | `Booking $booking, UnbookableReason $reason, BookableResource $bookableResource, PeriodCollection $periods, ?string $message = null, ?string $stackTrace = null` |
| `PlanningValidationStarted` | `Model $resource, Collection $dates, Collection\|EloquentCollection\|null $relations = null` |
| `PlanningValidationPassed` | `Model $resource, Collection $dates, Collection\|EloquentCollection\|null $relations = null` |
| `PlanningValidationFailed` | `Model $resource, Collection $dates, Collection\|EloquentCollection\|null $relations, Exception $exception` |

None of these events expose a `booker`, `exception` (except `PlanningValidationFailed`), `newPeriods`, `oldPeriods`, or `bookableResource` (except where listed) property — check the table above before referencing an event property.

## Exceptions

All exceptions below are plain `\Exception` subclasses with no additional properties or methods — there is no `getConflictingBookings()` or similar accessor. Use `$e->getMessage()`, or inspect the request state yourself (resource, periods) to build a response.

### BookingResourceOverlappingException

Thrown by `CheckBookingOverlaps::run()` (and therefore `BookResource::run()`) when the booking would exceed the resource's concurrency limit.

```php
use Masterix21\Bookings\Exceptions\BookingResourceOverlappingException;

try {
    $booking = (new BookResource())->run(/* ... */);
} catch (BookingResourceOverlappingException $e) {
    $message = $e->getMessage();
}
```

### OutOfPlanningsException

Thrown by `ensureIsAvailable()`/`validatePlanningAvailability()` when no valid planning exists for the requested dates.

```php
use Masterix21\Bookings\Exceptions\OutOfPlanningsException;
```

### RelationsOutOfPlanningsException

Thrown by `validateRelationsPlanningAvailability()` when one or more related `BookableResource` instances lack valid planning for the requested dates.

```php
use Masterix21\Bookings\Exceptions\RelationsOutOfPlanningsException;
```

### NoFreeSizeException

Thrown by `ensureHasFreeSize()` when resource capacity is exceeded.

```php
use Masterix21\Bookings\Exceptions\NoFreeSizeException;
```

### RelationsHaveNoFreeSizeException

Thrown by `ensureRelationsHaveFreeSize()` when none of the related resources have free capacity.

```php
use Masterix21\Bookings\Exceptions\RelationsHaveNoFreeSizeException;
```

### UnbookableException

Thrown when attempting to book a resource whose effective `size()` is `0` (not bookable), or when an `IsBookable` model has no bookable resources at all.

```php
use Masterix21\Bookings\Exceptions\UnbookableException;
```

## Generators

### BookingCodeGenerator Contract

Interface for custom booking code generators.

```php
use Masterix21\Bookings\Generators\Contracts\BookingCodeGenerator;

interface BookingCodeGenerator
{
    public function run(?string $prefix = null, ?string $suffix = null): string;
}
```

### RandomBookingCode

Default implementation. Builds a 64-character uppercase code from an optional prefix, a ULID, random characters, and an optional suffix.

```php
use Masterix21\Bookings\Generators\RandomBookingCode;

$generator = new RandomBookingCode();
$code = $generator->run(); // e.g. "01ARZ3NDEKTSV4RRFFQ69G5FAV..." (64 chars, uppercase)
$code = $generator->run(prefix: 'BK-', suffix: '-VIP');
```

Throws `\Exception` if the combined `$prefix` + `$suffix` length is 38 characters or more.

## Enums

### UnbookableReason

Enum describing why a booking attempt failed, carried on `BookingFailed`/`BookingChangeFailed`.

```php
use Masterix21\Bookings\Enums\UnbookableReason;

enum UnbookableReason: string
{
    case PERIOD_OVERLAP = 'period_overlap';
    case EXCEPTION = 'exception';
}
```

### PlanningMatchingStrategy

Controls how a `BookablePlanning`'s weekday flags are matched against a period's weekdays in `scopeWherePeriodIsValid()`.

```php
use Masterix21\Bookings\Enums\PlanningMatchingStrategy;

enum PlanningMatchingStrategy: string
{
    case All = 'all';  // every weekday in the period must be enabled on the planning
    case Any = 'any';  // at least one weekday in the period must be enabled on the planning
}
```

### Weekday

Maps Carbon's numeric day-of-week to the `bookable_plannings` boolean columns.

```php
use Masterix21\Bookings\Enums\Weekday;

enum Weekday: string
{
    case Monday = 'monday';
    case Tuesday = 'tuesday';
    case Wednesday = 'wednesday';
    case Thursday = 'thursday';
    case Friday = 'friday';
    case Saturday = 'saturday';
    case Sunday = 'sunday';
}
```

## Facade

### Bookings Facade

```php
use Masterix21\Bookings\BookingsFacade as Bookings;
```

The `Bookings` class (bound as the `bookings` singleton) exposes two helper methods — there is no `checkAvailability()` or `book()` on the facade. Use `CheckBookingOverlaps`/`BookResource` directly for those.

##### `checksum()`

Deterministic hash of a resource + period set, useful for optimistic-concurrency checks in your own code.

```php
public function checksum(BookableResource $bookableResource, PeriodCollection $periods): string
```

##### `periodsToDates()`

Expands a `PeriodCollection` into a flat `Collection` of individual dates.

```php
public function periodsToDates(PeriodCollection $periods, bool $removeDuplicates = true): Collection
```

```php
$checksum = Bookings::checksum($resource, $periods);
$dates = Bookings::periodsToDates($periods);
```

## Configuration Models

All model classes can be configured in `config/bookings.php`:

```php
'models' => [
    'user' => \App\Models\User::class,
    'bookable_resource' => \App\Models\CustomBookableResource::class,
    'booking' => \App\Models\CustomBooking::class,
    'booked_period' => \App\Models\CustomBookedPeriod::class,
    'bookable_planning' => \App\Models\CustomBookablePlanning::class,
    'bookable_relation' => \App\Models\CustomBookableRelation::class,
],
```

This allows you to extend any core model with custom functionality while maintaining compatibility with the package. See [Configuration](configuration.md) for the complete config file, including `generators`, `planning_validation`, and `booking_update`.
