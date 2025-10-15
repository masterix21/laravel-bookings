# Related Bookings

Related Bookings is a powerful feature that allows you to create parent-child relationships between bookings. This enables you to group related bookings together while maintaining their individual properties and lifecycle.

## Overview

The Related Bookings feature implements a self-referential parent-child relationship pattern on the `Booking` model. This allows a booking to have:

- **One parent booking** - The main or primary booking
- **Multiple child bookings** - Related or dependent bookings

## Use Cases

Related bookings are useful in various scenarios:

### Hotel Reservations

Link room bookings with additional services:

```php
// Main room booking
$roomBooking = (new BookResource())->run(
    periods: PeriodCollection::make([Period::make('2024-12-25', '2024-12-27')]),
    bookableResource: $deluxeRoom,
    booker: $guest,
    label: 'Deluxe Suite'
);

// Parking space as child booking
$parkingBooking = (new BookResource())->run(
    periods: PeriodCollection::make([Period::make('2024-12-25', '2024-12-27')]),
    bookableResource: $parkingSpot,
    booker: $guest,
    parent: $roomBooking,
    label: 'Parking Spot'
);

// Spa appointment as child booking
$spaBooking = (new BookResource())->run(
    periods: PeriodCollection::make([Period::make('2024-12-26 14:00', '2024-12-26 15:30')]),
    bookableResource: $spaRoom,
    booker: $guest,
    parent: $roomBooking,
    label: 'Spa Treatment'
);
```

### Medical Appointments

Link initial consultation with follow-up appointments:

```php
// Initial consultation
$initialAppointment = (new BookResource())->run(
    periods: PeriodCollection::make([Period::make('2024-12-20 10:00', '2024-12-20 11:00')]),
    bookableResource: $doctor,
    booker: $patient,
    label: 'Initial Consultation'
);

// Follow-up appointment
$followUp = (new BookResource())->run(
    periods: PeriodCollection::make([Period::make('2024-12-27 10:00', '2024-12-27 10:30')]),
    bookableResource: $doctor,
    booker: $patient,
    parent: $initialAppointment,
    label: 'Follow-up Consultation'
);
```

### Event Management

Link main event booking with equipment rentals:

```php
// Conference room booking
$conferenceBooking = (new BookResource())->run(
    periods: PeriodCollection::make([Period::make('2024-12-15 09:00', '2024-12-15 17:00')]),
    bookableResource: $conferenceRoom,
    booker: $organizer,
    label: 'Annual Conference'
);

// Equipment bookings as children
$projectorBooking = (new BookResource())->run(
    periods: PeriodCollection::make([Period::make('2024-12-15 09:00', '2024-12-15 17:00')]),
    bookableResource: $projector,
    booker: $organizer,
    parent: $conferenceBooking,
    label: 'Projector Rental'
);

$soundSystemBooking = (new BookResource())->run(
    periods: PeriodCollection::make([Period::make('2024-12-15 09:00', '2024-12-15 17:00')]),
    bookableResource: $soundSystem,
    booker: $organizer,
    parent: $conferenceBooking,
    label: 'Sound System Rental'
);
```

### Car Rental

Link vehicle rental with optional extras:

```php
// Car rental booking
$carBooking = (new BookResource())->run(
    periods: PeriodCollection::make([Period::make('2024-12-20', '2024-12-25')]),
    bookableResource: $car,
    booker: $customer,
    label: 'BMW X5 Rental'
);

// GPS device as child booking
$gpsBooking = (new BookResource())->run(
    periods: PeriodCollection::make([Period::make('2024-12-20', '2024-12-25')]),
    bookableResource: $gpsDevice,
    booker: $customer,
    parent: $carBooking,
    label: 'GPS Navigation'
);

// Child seat as child booking
$childSeatBooking = (new BookResource())->run(
    periods: PeriodCollection::make([Period::make('2024-12-20', '2024-12-25')]),
    bookableResource: $childSeat,
    booker: $customer,
    parent: $carBooking,
    label: 'Child Safety Seat'
);
```

## Creating Related Bookings

### Creating a Parent Booking

Create a parent booking like any normal booking:

```php
use Masterix21\Bookings\Actions\BookResource;
use Spatie\Period\Period;
use Spatie\Period\PeriodCollection;

$parentBooking = (new BookResource())->run(
    periods: PeriodCollection::make([
        Period::make('2024-12-25 14:00', '2024-12-25 16:00')
    ]),
    bookableResource: $resource,
    booker: $user,
    label: 'Parent Booking',
    note: 'Main booking with children',
    meta: ['is_main' => true]
);
```

### Creating a Child Booking

To create a child booking, pass the parent booking to the `parent` parameter:

```php
$childBooking = (new BookResource())->run(
    periods: PeriodCollection::make([
        Period::make('2024-12-25 14:00', '2024-12-25 16:00')
    ]),
    bookableResource: $anotherResource,
    booker: $user,
    parent: $parentBooking,  // Link to parent
    label: 'Child Booking',
    note: 'Related to main booking'
);
```

### Creating Multiple Children

You can create multiple child bookings for the same parent:

```php
$parent = (new BookResource())->run(/* ... */);

// Create multiple children
$child1 = (new BookResource())->run(parent: $parent, /* ... */);
$child2 = (new BookResource())->run(parent: $parent, /* ... */);
$child3 = (new BookResource())->run(parent: $parent, /* ... */);

// Access all children
$parent->childBookings; // Collection with 3 bookings
```

## Accessing Relationships

### Get Parent Booking

Access the parent booking from a child:

```php
$parent = $childBooking->parentBooking;

if ($parent) {
    echo "Parent booking code: {$parent->code}";
    echo "Parent label: {$parent->label}";
}
```

### Get Child Bookings

Access all child bookings from a parent:

```php
$children = $parentBooking->childBookings;

foreach ($children as $child) {
    echo "Child booking: {$child->label}";
}

// Count children
$childCount = $parentBooking->childBookings()->count();
```

### Eager Loading

Use eager loading to avoid N+1 queries:

```php
// Load parent with children
$parent = Booking::with('childBookings')->find($id);

// Load child with parent
$child = Booking::with('parentBooking')->find($id);

// Load all bookings with relationships
$bookings = Booking::with(['parentBooking', 'childBookings'])->get();
```

## Querying Related Bookings

### Find Bookings with Children

Get all parent bookings that have children:

```php
$parentsWithChildren = Booking::has('childBookings')->get();
```

### Find Bookings without Parents

Get all independent bookings (no parent):

```php
$independentBookings = Booking::whereNull('parent_booking_id')->get();
```

### Find Child Bookings

Get all child bookings (have a parent):

```php
$childBookings = Booking::whereNotNull('parent_booking_id')->get();
```

### Find Bookings by Parent

Get all children of a specific parent:

```php
$children = Booking::where('parent_booking_id', $parentId)->get();
```

### Complex Queries

Combine relationship queries with other conditions:

```php
// Find all children of a parent for a specific resource
$children = Booking::where('parent_booking_id', $parentId)
    ->whereHas('bookedPeriods', function ($query) use ($resourceId) {
        $query->where('bookable_resource_id', $resourceId);
    })
    ->get();

// Find parents with more than 2 children
$parents = Booking::has('childBookings', '>', 2)->get();

// Find all bookings in a family tree
$familyTree = Booking::with('childBookings.childBookings')
    ->where('id', $rootParentId)
    ->first();
```

## Updating Related Bookings

### Changing Parent Relationship

You can change a booking's parent at any time:

```php
// Via direct update
$booking->update(['parent_booking_id' => $newParent->id]);

// Via BookResource action
(new BookResource())->run(
    booking: $existingBooking,
    parent: $newParent,
    periods: $periods,
    bookableResource: $resource,
    booker: $user
);
```

### Removing Parent Relationship

Make a child booking independent:

```php
// Set parent to null
$booking->update(['parent_booking_id' => null]);

// Or via BookResource
(new BookResource())->run(
    booking: $existingBooking,
    parent: null,  // Remove parent
    periods: $periods,
    bookableResource: $resource,
    booker: $user
);
```

### Updating Child Bookings

Update child bookings independently:

```php
// Each child can be updated independently
$child1->update(['label' => 'Updated Child 1']);
$child2->update(['note' => 'New notes for child 2']);

// Use BookResource for period updates
(new BookResource())->run(
    booking: $child1,
    parent: $parent,  // Keep same parent
    periods: $newPeriods,
    bookableResource: $resource,
    booker: $user
);
```

## Deletion Behavior

### Understanding `nullOnDelete()`

The related bookings feature uses `nullOnDelete()` for the foreign key constraint, which provides specific deletion behavior.

### When Parent is Deleted

Child bookings **survive** parent deletion and become independent:

```php
$parent = (new BookResource())->run(/* ... */);
$child = (new BookResource())->run(parent: $parent, /* ... */);

// Delete parent
$parent->delete();

// Child still exists but is now independent
$child->refresh();
echo $child->parent_booking_id; // null
echo $child->exists; // true
```

### When Child is Deleted

Deleting a child has no effect on the parent:

```php
$parent = (new BookResource())->run(/* ... */);
$child = (new BookResource())->run(parent: $parent, /* ... */);

// Delete child
$child->delete();

// Parent is unaffected
$parent->refresh();
echo $parent->exists; // true
```

### Cascade Delete Pattern

If you want to delete all children when parent is deleted, implement it manually:

```php
// Delete parent and all children
$parent->childBookings()->each(function ($child) {
    $child->delete();
});
$parent->delete();

// Or in a model event
class Booking extends Model
{
    protected static function booted(): void
    {
        static::deleting(function (Booking $booking) {
            // Delete all children before deleting parent
            $booking->childBookings()->each(function ($child) {
                $child->delete();
            });
        });
    }
}
```

## Best Practices

### When to Use Related Bookings

Use related bookings when:

- **Bookings are logically connected** but involve different resources
- **You need to track relationships** between bookings
- **Child bookings can exist independently** after parent deletion
- **You want to group bookings** for reporting or display purposes

### When NOT to Use Related Bookings

Don't use related bookings when:

- **Bookings must always stay together** - Consider using a single booking with multiple periods instead
- **Resources are always booked together** - Consider creating a composite resource
- **You need strict cascade deletion** - The feature doesn't support automatic child deletion

### Validation Considerations

Related bookings are independent for validation purposes:

```php
// Each booking is validated separately
$parent = (new BookResource())->run(/* ... */); // Validated independently

// Child validation is independent of parent
$child = (new BookResource())->run(parent: $parent, /* ... */); // Validated independently

// This means:
// - Child periods don't need to match parent periods
// - Child booker can be different from parent booker
// - Child can be on different resources
// - Overlap validation is per resource, not per parent
```

### Performance Tips

1. **Use eager loading** to avoid N+1 queries:
```php
// Good
$bookings = Booking::with(['parentBooking', 'childBookings'])->get();

// Bad
$bookings = Booking::all();
foreach ($bookings as $booking) {
    $booking->childBookings; // N+1 query
}
```

2. **Use exists() for checking relationships**:
```php
// Good
$hasChildren = $booking->childBookings()->exists();

// Bad
$hasChildren = $booking->childBookings->count() > 0;
```

3. **Count relationships efficiently**:
```php
// Good
$booking->childBookings()->count();

// Bad
count($booking->childBookings);
```

### Naming Conventions

Use clear naming to distinguish parent and child bookings:

```php
// Good
$roomBooking = (new BookResource())->run(label: 'Room 101', /* ... */);
$parkingBooking = (new BookResource())->run(parent: $roomBooking, label: 'Parking A-12', /* ... */);

// Clear meta information
$parent = (new BookResource())->run(
    label: 'Main Event',
    meta: ['type' => 'parent', 'category' => 'event']
);

$child = (new BookResource())->run(
    parent: $parent,
    label: 'Equipment',
    meta: ['type' => 'child', 'category' => 'equipment', 'parent_type' => 'event']
);
```

## UI/UX Patterns

### Displaying Related Bookings

Show parent-child relationships in your UI:

```php
// In a controller
public function show(Booking $booking)
{
    $booking->load(['parentBooking', 'childBookings']);

    return view('bookings.show', compact('booking'));
}
```

```blade
{{-- In Blade view --}}
@if($booking->parentBooking)
    <div class="alert alert-info">
        This booking is related to:
        <a href="{{ route('bookings.show', $booking->parentBooking) }}">
            {{ $booking->parentBooking->label }}
        </a>
    </div>
@endif

@if($booking->childBookings->count() > 0)
    <div class="card">
        <div class="card-header">Related Bookings</div>
        <div class="card-body">
            <ul>
                @foreach($booking->childBookings as $child)
                    <li>
                        <a href="{{ route('bookings.show', $child) }}">
                            {{ $child->label }}
                        </a>
                    </li>
                @endforeach
            </ul>
        </div>
    </div>
@endif
```

### Batch Operations

Process all related bookings together:

```php
// Cancel parent and all children
public function cancelBookingFamily(Booking $parent): void
{
    DB::transaction(function () use ($parent) {
        // Cancel all children
        $parent->childBookings()->each(function ($child) {
            $this->cancelBooking($child);
        });

        // Cancel parent
        $this->cancelBooking($parent);
    });
}

// Send confirmation emails for all related bookings
public function sendConfirmations(Booking $parent): void
{
    // Send parent confirmation
    Mail::to($parent->booker)->send(new BookingConfirmation($parent));

    // Send child confirmations
    $parent->childBookings()->each(function ($child) {
        Mail::to($child->booker)->send(new BookingConfirmation($child));
    });
}
```

## Events and Listeners

Related bookings work seamlessly with the event system:

```php
use Masterix21\Bookings\Events\BookingCompleted;

class NotifyRelatedBookings
{
    public function handle(BookingCompleted $event): void
    {
        $booking = $event->booking;

        // If this is a parent, notify children
        if ($booking->childBookings()->exists()) {
            $this->notifyChildren($booking);
        }

        // If this is a child, notify parent
        if ($booking->parentBooking) {
            $this->notifyParent($booking);
        }
    }

    protected function notifyChildren(Booking $parent): void
    {
        $parent->childBookings()->each(function ($child) {
            // Send notification about parent booking
            Notification::send($child->booker, new ParentBookingUpdated($parent));
        });
    }

    protected function notifyParent(Booking $child): void
    {
        // Send notification to parent booking's booker
        Notification::send($child->parentBooking->booker, new ChildBookingCreated($child));
    }
}
```

## Testing Related Bookings

### Unit Tests

```php
use Masterix21\Bookings\Models\Booking;
use Illuminate\Foundation\Testing\RefreshDatabase;

class RelatedBookingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_booking_can_have_parent(): void
    {
        $parent = Booking::factory()->create();
        $child = Booking::factory()->create(['parent_booking_id' => $parent->id]);

        $this->assertEquals($parent->id, $child->parentBooking->id);
    }

    public function test_booking_can_have_multiple_children(): void
    {
        $parent = Booking::factory()->create();
        $children = Booking::factory()->count(3)->create(['parent_booking_id' => $parent->id]);

        $this->assertCount(3, $parent->childBookings);
    }

    public function test_children_survive_parent_deletion(): void
    {
        $parent = Booking::factory()->create();
        $child = Booking::factory()->create(['parent_booking_id' => $parent->id]);

        $parent->delete();

        $child->refresh();
        $this->assertNull($child->parent_booking_id);
        $this->assertTrue($child->exists);
    }
}
```

### Feature Tests

```php
public function test_can_create_related_bookings_via_action(): void
{
    $resource1 = BookableResource::factory()->create();
    $resource2 = BookableResource::factory()->create();
    $user = User::factory()->create();

    $periods = PeriodCollection::make([Period::make('2024-12-25', '2024-12-27')]);

    $parent = (new BookResource())->run(
        periods: $periods,
        bookableResource: $resource1,
        booker: $user,
        label: 'Parent'
    );

    $child = (new BookResource())->run(
        periods: $periods,
        bookableResource: $resource2,
        booker: $user,
        parent: $parent,
        label: 'Child'
    );

    $this->assertEquals($parent->id, $child->parent_booking_id);
    $this->assertTrue($parent->childBookings->contains($child));
}
```

## Migration Requirements

To use the Related Bookings feature, you must run the optional migration:

```bash
php artisan vendor:publish --tag="bookings-migrations"
php artisan migrate
```

For complete migration instructions, see the [Migration Guide](migration-guide.md#upgrading-to-version-120).

## API Reference

For detailed API documentation of the related methods and parameters, see:

- [BookResource::run() - `$parent` parameter](api-reference.md#bookresource)
- [Booking::parentBooking() method](api-reference.md#booking-model)
- [Booking::childBookings() method](api-reference.md#booking-model)

## Troubleshooting

### Common Issues

**Issue:** Foreign key constraint error when creating child booking

**Solution:** Ensure the parent booking exists and has been saved to the database before creating children.

**Issue:** Children not appearing in `childBookings` collection

**Solution:** Refresh the parent model or use eager loading: `$parent->load('childBookings')`

**Issue:** Parent not accessible from child

**Solution:** The migration might not have been run. Verify the `parent_booking_id` column exists in the bookings table.

For more help, see the [Troubleshooting Guide](troubleshooting.md).
