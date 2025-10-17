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
    ?Model $booker = null,
    ?Booking $booking = null,
    ?Booking $parent = null,
    ?Model $relatable = null,
    ?string $code = null,
    ?string $codePrefix = null,
    ?string $codeSuffix = null,
    ?string $label = null,
    ?string $note = null,
    ?array $meta = null,
    string|BookingCodeGenerator|null $codeGenerator = null
): Booking
```

**Parameters:**
- `$periods` (PeriodCollection) - Time periods to book
- `$bookableResource` (BookableResource) - Resource to book
- `$booker` (?Model) - Entity making the booking (polymorphic)
- `$booking` (?Booking) - Existing booking for updates
- `$parent` (?Booking) - Parent booking for creating related bookings (v1.2.0+)
- `$relatable` (?Model) - Related model for the booking
- `$code` (?string) - Custom booking code
- `$codePrefix` (?string) - Prefix for generated code
- `$codeSuffix` (?string) - Suffix for generated code
- `$label` (?string) - Booking label/title
- `$note` (?string) - Additional notes
- `$meta` (?array) - Additional metadata
- `$codeGenerator` (string|BookingCodeGenerator|null) - Custom booking code generator

**Returns:** `Booking` - The created or updated booking

**Throws:**
- `BookingResourceOverlappingException` - When booking conflicts exist
- `OutOfPlanningsException` - When no valid planning exists
- `NoFreeSizeException` - When resource capacity is exceeded
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

**Example with Related Bookings (v1.2.0+):**
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
    bool $emitEvent = true,
    bool $throw = true,
    ?Booking $ignoreBooking = null
): bool
```

**Parameters:**
- `$periods` (PeriodCollection) - Periods to check
- `$bookableResource` (BookableResource) - Resource to check
- `$emitEvent` (bool) - Whether to emit validation events
- `$throw` (bool) - Whether to throw exceptions on conflicts
- `$ignoreBooking` (?Booking) - Booking to ignore in validation

**Returns:** `bool` - True if no conflicts, false otherwise

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
    'resource_type',    // Polymorphic type
    'resource_id',      // Polymorphic ID
    'max',             // Maximum concurrent bookings
    'size',            // Resource capacity
    'is_bookable',     // Whether resource can be booked
    'is_visible',      // Whether resource is visible
];

protected $casts = [
    'is_bookable' => 'boolean',
    'is_visible' => 'boolean',
];
```

#### Relationships

##### `resource()`

Polymorphic relationship to the actual resource model.

```php
public function resource(): MorphTo
```

##### `bookings()`

All bookings for this resource.

```php
public function bookings(): HasManyDeep
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

#### Scopes

##### `bookable()`

Filter only bookable resources.

```php
BookableResource::bookable()->get();
```

##### `visible()`

Filter only visible resources.

```php
BookableResource::visible()->get();
```

##### `ofType()`

Filter resources by type.

```php
BookableResource::ofType(Room::class)->get();
```

#### Methods

##### `isBookable()`

Check if resource can be booked.

```php
public function isBookable(): bool
```

### Booking

Represents a booking with periods, booker, and metadata.

```php
use Masterix21\Bookings\Models\Booking;
```

#### Properties

```php
protected $fillable = [
    'parent_booking_id', // Parent booking ID (v1.2.0+)
    'code',              // Booking code
    'booker_type',       // Polymorphic booker type
    'booker_id',         // Polymorphic booker ID
    'label',             // Booking label
    'note',              // Additional notes
    'meta',              // JSON metadata
];

protected $casts = [
    'meta' => 'collection',
];
```

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

##### `bookedPeriods()`

Time periods for this booking.

```php
public function bookedPeriods(): HasMany
```

**Example:**
```php
$periods = $booking->bookedPeriods;
foreach ($periods as $period) {
    echo "{$period->starts_at} to {$period->ends_at}";
}
```

##### `parentBooking()` (v1.2.0+)

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

##### `childBookings()` (v1.2.0+)

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

// Filter children
$activeChildren = $booking->childBookings()
    ->whereHas('bookedPeriods', function ($query) {
        $query->where('ends_at', '>', now());
    })
    ->get();

// Eager load children
$bookings = Booking::with('childBookings')->get();
```

### BookedPeriod

Individual time periods within a booking.

```php
use Masterix21\Bookings\Models\BookedPeriod;
```

#### Properties

```php
protected $fillable = [
    'booking_id',           // Parent booking
    'bookable_resource_id', // Booked resource
    'relatable_type',       // Related model type
    'relatable_id',         // Related model ID
    'starts_at',           // Period start
    'ends_at',             // Period end
];

protected $casts = [
    'starts_at' => 'datetime',
    'ends_at' => 'datetime',
];
```

#### Methods

##### `period()`

Get Spatie Period object for this booked period.

```php
public function period(): Period
```

##### `duration()`

Get the duration of this period.

```php
public function duration(): Duration
```

### BookablePlanning

Defines availability rules and constraints for resources.

```php
use Masterix21\Bookings\Models\BookablePlanning;
```

#### Properties

```php
protected $fillable = [
    'bookable_resource_id', // Target resource
    'monday',               // Available on Monday
    'tuesday',              // Available on Tuesday
    'wednesday',            // Available on Wednesday
    'thursday',             // Available on Thursday
    'friday',               // Available on Friday
    'saturday',             // Available on Saturday
    'sunday',               // Available on Sunday
    'starts_at',           // Planning start date
    'ends_at',             // Planning end date
];

protected $casts = [
    'monday' => 'boolean',
    'tuesday' => 'boolean',
    'wednesday' => 'boolean',
    'thursday' => 'boolean',
    'friday' => 'boolean',
    'saturday' => 'boolean',
    'sunday' => 'boolean',
    'starts_at' => 'datetime',
    'ends_at' => 'datetime',
];
```

#### Methods

##### `isAvailableOnDate()`

Check if planning allows bookings on a specific date.

```php
public function isAvailableOnDate(Carbon $date): bool
```

##### `getAvailableDays()`

Get array of available weekdays.

```php
public function getAvailableDays(): array
```

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
public function bookableResources(): HasMany
```

##### `bookableResource()`

Get the primary bookable resource.

```php
public function bookableResource(): HasOne
```

##### `bookings()`

Get all bookings for this model.

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
public function isBookedAt(Carbon $dateTime): bool
```

##### `bookedPeriodsOfDate()`

Get booked periods for a specific date.

```php
public function bookedPeriodsOfDate(Carbon $date): Collection
```

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

##### `bookedPeriods()`

Get all periods booked by this entity.

```php
public function bookedPeriods(): HasManyThrough
```

## Events

### Booking Lifecycle Events

#### BookingInProgress

Fired when booking process starts.

```php
use Masterix21\Bookings\Events\BookingInProgress;

class BookingInProgress
{
    public function __construct(
        public PeriodCollection $periods,
        public BookableResource $bookableResource,
        public ?Model $booker = null,
        public ?Booking $booking = null
    ) {}
}
```

#### BookingCompleted

Fired when booking is successfully created.

```php
use Masterix21\Bookings\Events\BookingCompleted;

class BookingCompleted
{
    public function __construct(
        public Booking $booking,
        public PeriodCollection $periods,
        public BookableResource $bookableResource
    ) {}
}
```

#### BookingFailed

Fired when booking creation fails.

```php
use Masterix21\Bookings\Events\BookingFailed;

class BookingFailed
{
    public function __construct(
        public PeriodCollection $periods,
        public BookableResource $bookableResource,
        public \Exception $exception,
        public ?Model $booker = null,
        public ?Booking $booking = null
    ) {}
}
```

#### BookingChanging

Fired when booking update starts.

```php
use Masterix21\Bookings\Events\BookingChanging;

class BookingChanging
{
    public function __construct(
        public Booking $booking,
        public PeriodCollection $newPeriods,
        public PeriodCollection $oldPeriods
    ) {}
}
```

#### BookingChanged

Fired when booking is successfully updated.

```php
use Masterix21\Bookings\Events\BookingChanged;

class BookingChanged
{
    public function __construct(
        public Booking $booking,
        public PeriodCollection $newPeriods,
        public PeriodCollection $oldPeriods
    ) {}
}
```

#### BookingChangeFailed

Fired when booking update fails.

```php
use Masterix21\Bookings\Events\BookingChangeFailed;

class BookingChangeFailed
{
    public function __construct(
        public Booking $booking,
        public PeriodCollection $periods,
        public \Exception $exception
    ) {}
}
```

### Planning Validation Events

#### PlanningValidationStarted

Fired when planning validation begins.

```php
use Masterix21\Bookings\Events\PlanningValidationStarted;
```

#### PlanningValidationPassed

Fired when planning validation succeeds.

```php
use Masterix21\Bookings\Events\PlanningValidationPassed;
```

#### PlanningValidationFailed

Fired when planning validation fails.

```php
use Masterix21\Bookings\Events\PlanningValidationFailed;
```

## Exceptions

### BookingResourceOverlappingException

Thrown when booking conflicts with existing bookings.

```php
use Masterix21\Bookings\Exceptions\BookingResourceOverlappingException;

try {
    $booking = (new BookResource())->run(/* ... */);
} catch (BookingResourceOverlappingException $e) {
    $conflictingBookings = $e->getConflictingBookings();
    $message = $e->getMessage();
}
```

#### Methods

##### `getConflictingBookings()`

Get bookings that conflict with the requested periods.

```php
public function getConflictingBookings(): Collection
```

### OutOfPlanningsException

Thrown when no valid planning exists for the requested time.

```php
use Masterix21\Bookings\Exceptions\OutOfPlanningsException;
```

### NoFreeSizeException

Thrown when resource capacity is exceeded.

```php
use Masterix21\Bookings\Exceptions\NoFreeSizeException;
```

### UnbookableException

Thrown when attempting to book an unbookable resource.

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
    public function generate(?Booking $booking = null): string;
}
```

### RandomBookingCode

Default implementation that generates random booking codes.

```php
use Masterix21\Bookings\Generators\RandomBookingCode;

$generator = new RandomBookingCode();
$code = $generator->generate(); // Returns: "BK-A1B2C3D4"
```

## Enums

### UnbookableReason

Enum defining reasons why a resource cannot be booked.

```php
use Masterix21\Bookings\Enums\UnbookableReason;

enum UnbookableReason: string
{
    case NotBookable = 'not_bookable';
    case NotVisible = 'not_visible';
    case OutOfPlanning = 'out_of_planning';
    case NoCapacity = 'no_capacity';
    case Overlapping = 'overlapping';
}
```

## Facade

### Bookings Facade

Provides convenient access to package functionality.

```php
use Masterix21\Bookings\BookingsFacade as Bookings;

// Check availability
$available = Bookings::checkAvailability($resource, $periods);

// Create booking
$booking = Bookings::book($resource, $periods, $booker);
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

This allows you to extend any core model with custom functionality while maintaining compatibility with the package.