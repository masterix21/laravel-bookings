# Resource and Planning Synchronization

This guide covers the automatic synchronization features that allow your business models to control their bookable resources and planning.

## Table of Contents

- [Custom Resource Synchronization](#custom-resource-synchronization)
- [Planning Source Pattern](#planning-source-pattern)
- [Use Cases](#use-cases)
- [Migration Guide](#migration-guide)
- [Best Practices](#best-practices)

## Custom Resource Synchronization

The `syncBookableResource()` method allows your models to automatically update their associated `BookableResource` when saved.

### Basic Implementation

```php
use Masterix21\Bookings\Models\Concerns\Bookable;
use Masterix21\Bookings\Models\Concerns\IsBookable;
use Masterix21\Bookings\Models\BookableResource;

class Room extends Model implements Bookable
{
    use IsBookable;

    protected $fillable = ['name', 'is_published', 'is_available', 'capacity'];

    /**
     * Synchronize room data to bookable resource
     * Called automatically when the room is saved
     */
    public function syncBookableResource(BookableResource $resource): void
    {
        $resource->update([
            'is_visible' => $this->is_published,
            'is_bookable' => $this->is_available,
            'size' => $this->capacity,
        ]);
    }
}
```

### How It Works

1. **Automatic Trigger**: Called automatically when your model is saved
2. **Receives Resource**: Gets the specific `BookableResource` instance
3. **Multiple Resources**: If your model has multiple resources (via `bookableResources()`), the method is called for each one
4. **N+1 Optimized**: The relation is loaded once if not already eager-loaded

### Example with Multiple Resources

```php
class Product extends Model implements Bookable
{
    use IsBookable;

    public function variants()
    {
        return $this->hasMany(ProductVariant::class);
    }

    public function syncBookableResource(BookableResource $resource): void
    {
        // Find which variant this resource belongs to
        $variant = $this->variants()
            ->where('bookable_resource_id', $resource->id)
            ->first();

        if ($variant) {
            $resource->update([
                'is_bookable' => $this->is_active && $variant->in_stock,
                'size' => $variant->stock_quantity,
            ]);
        }
    }
}
```

## Planning Source Pattern

The Planning Source pattern allows your business models (rates, special offers, seasonal rules) to directly control `BookablePlanning`.

### Setup

First, ensure you have the source columns migration:

```bash
php artisan vendor:publish --tag="bookings-migrations"
# Look for: update_bookable_plannings_add_source_columns.php
php artisan migrate
```

### Basic Implementation

```php
use Masterix21\Bookings\Models\Concerns\BookablePlanningSource;
use Masterix21\Bookings\Models\Concerns\IsBookablePlanningSource;

class Rate extends Model implements BookablePlanningSource
{
    use IsBookablePlanningSource;

    protected $fillable = [
        'room_id',
        'name',
        'price',
        'valid_from',
        'valid_to',
        'includes_weekend',
        'min_nights',
    ];

    protected $casts = [
        'valid_from' => 'datetime',
        'valid_to' => 'datetime',
        'includes_weekend' => 'boolean',
    ];

    public function room()
    {
        return $this->belongsTo(Room::class);
    }

    /**
     * Synchronize rate to planning
     * Called automatically when the rate is saved
     */
    public function syncBookablePlanning(): void
    {
        $this->planning()->updateOrCreate(
            ['bookable_resource_id' => $this->room->bookableResource->id],
            [
                'label' => $this->name,
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

### Accessing Related Data

```php
// From Rate to Planning
$rate = Rate::find(1);
$planning = $rate->planning; // MorphOne relation

// From Planning to Rate
$planning = BookablePlanning::find(1);
$rate = $planning->source; // MorphTo relation (returns Rate instance)

// Query plannings by source type
$ratePlannings = BookablePlanning::where('source_type', Rate::class)->get();
```

### Lifecycle Management

The planning is automatically managed:

```php
// Creating a rate automatically creates its planning
$rate = Rate::create([
    'room_id' => $room->id,
    'name' => 'Summer Rate',
    'valid_from' => '2024-06-01',
    'valid_to' => '2024-08-31',
    'includes_weekend' => true,
]);
// Planning is created automatically via syncBookablePlanning()

// Updating a rate updates its planning
$rate->update(['valid_to' => '2024-09-15']);
// Planning is updated automatically

// Deleting a rate deletes its planning
$rate->delete();
// Planning is deleted automatically via model event
```

## Use Cases

### Hotel Room Rates

```php
class RoomRate extends Model implements BookablePlanningSource
{
    use IsBookablePlanningSource;

    public function syncBookablePlanning(): void
    {
        $this->planning()->updateOrCreate(
            ['bookable_resource_id' => $this->room->bookableResource->id],
            [
                'label' => "{$this->season} - {$this->rate_type}",
                'starts_at' => $this->valid_from,
                'ends_at' => $this->valid_to,
                'monday' => $this->available_monday,
                'tuesday' => $this->available_tuesday,
                'wednesday' => $this->available_wednesday,
                'thursday' => $this->available_thursday,
                'friday' => $this->available_friday,
                'saturday' => $this->available_saturday,
                'sunday' => $this->available_sunday,
            ]
        );
    }
}
```

### Seasonal Special Offers

```php
class SpecialOffer extends Model implements BookablePlanningSource
{
    use IsBookablePlanningSource;

    public function syncBookablePlanning(): void
    {
        // Special offers might affect multiple resources
        $this->applicableRooms->each(function ($room) {
            BookablePlanning::create([
                'bookable_resource_id' => $room->bookableResource->id,
                'source_type' => self::class,
                'source_id' => $this->id,
                'label' => $this->offer_name,
                'starts_at' => $this->offer_start,
                'ends_at' => $this->offer_end,
                'monday' => true,
                'tuesday' => true,
                'wednesday' => true,
                'thursday' => true,
                'friday' => true,
                'saturday' => true,
                'sunday' => true,
            ]);
        });
    }
}
```

### Maintenance Schedules

```php
class MaintenanceSchedule extends Model implements BookablePlanningSource
{
    use IsBookablePlanningSource;

    public function syncBookablePlanning(): void
    {
        $this->planning()->updateOrCreate(
            ['bookable_resource_id' => $this->equipment->bookableResource->id],
            [
                'label' => "Maintenance: {$this->reason}",
                'starts_at' => $this->scheduled_start,
                'ends_at' => $this->scheduled_end,
                // Block all days during maintenance
                'monday' => false,
                'tuesday' => false,
                'wednesday' => false,
                'thursday' => false,
                'friday' => false,
                'saturday' => false,
                'sunday' => false,
            ]
        );
    }
}
```

## Migration Guide

### For Existing Installations

If you're upgrading from an older version:

1. **Publish migrations**:
   ```bash
   php artisan vendor:publish --tag="bookings-migrations"
   ```

2. **Run the update migration**:
   ```bash
   php artisan migrate
   ```
   This will add `source_type` and `source_id` columns to `bookable_plannings` table.

3. **Update your models**:
   - Implement `BookablePlanningSource` interface
   - Use `IsBookablePlanningSource` trait
   - Implement `syncBookablePlanning()` method

4. **Migrate existing data** (if needed):
   ```php
   // Example migration script
   use Masterix21\Bookings\Models\BookablePlanning;

   // Link existing plannings to their source models
   Rate::all()->each(function ($rate) {
       $planning = BookablePlanning::where('bookable_resource_id', $rate->room->bookableResource->id)
           ->whereBetween('starts_at', [$rate->valid_from, $rate->valid_to])
           ->first();

       if ($planning) {
           $planning->update([
               'source_type' => Rate::class,
               'source_id' => $rate->id,
           ]);
       }
   });
   ```

### For New Installations

Both migrations run automatically. Just implement the interfaces in your models.

## Best Practices

### 1. Handle Missing Resources Gracefully

```php
public function syncBookableResource(BookableResource $resource): void
{
    if (!$resource->exists) {
        return;
    }

    $resource->update([
        'is_bookable' => $this->is_available,
    ]);
}
```

### 2. Batch Updates for Performance

```php
public function syncBookablePlanning(): void
{
    // Use updateOrCreate to avoid multiple queries
    $this->planning()->updateOrCreate(
        ['bookable_resource_id' => $this->room->bookableResource->id],
        $this->getPlanningAttributes()
    );
}

private function getPlanningAttributes(): array
{
    return [
        'starts_at' => $this->valid_from,
        'ends_at' => $this->valid_to,
        // ... other attributes
    ];
}
```

### 3. Cleanup Orphaned Planning

```php
public function syncBookableResource(BookableResource $resource): void
{
    // Update resource
    $resource->update(['is_bookable' => $this->is_available]);

    // Clean up planning for inactive rates
    if (!$this->is_active) {
        $resource->plannings()
            ->where('source_type', Rate::class)
            ->where('source_id', $this->id)
            ->delete();
    }
}
```

### 4. Use Transactions for Complex Operations

```php
public function syncBookablePlanning(): void
{
    DB::transaction(function () {
        // Delete old planning
        $this->planning()->delete();

        // Create new planning
        $this->planning()->create([
            'bookable_resource_id' => $this->resource->id,
            'starts_at' => $this->valid_from,
            'ends_at' => $this->valid_to,
        ]);

        // Update related records
        $this->updateRelatedBookings();
    });
}
```

### 5. Validate Before Syncing

```php
public function syncBookablePlanning(): void
{
    // Validate dates
    if ($this->valid_from->isAfter($this->valid_to)) {
        throw new InvalidArgumentException('Invalid date range');
    }

    // Ensure resource exists
    if (!$this->room->bookableResource) {
        return;
    }

    $this->planning()->updateOrCreate(
        ['bookable_resource_id' => $this->room->bookableResource->id],
        [
            'starts_at' => $this->valid_from,
            'ends_at' => $this->valid_to,
        ]
    );
}
```

## Polymorphic Relations Flow

```
Business Model (Rate, Offer, etc.)
  └─> planning (morphOne)
        ├─> bookableResource (belongsTo)
        └─> source (morphTo) ─> back to Business Model
```

## Performance Considerations

### Eager Loading

```php
// Avoid N+1 queries
$rooms = Room::with('bookableResource')->get();
$rooms->each->save(); // Efficiently syncs all resources

// Planning sources
$rates = Rate::with('planning')->get();
```

### Conditional Syncing

```php
public function syncBookableResource(BookableResource $resource): void
{
    // Only sync if relevant attributes changed
    if (!$this->wasChanged(['is_available', 'capacity'])) {
        return;
    }

    $resource->update([
        'is_bookable' => $this->is_available,
        'size' => $this->capacity,
    ]);
}
```

## Troubleshooting

### Planning Not Created

Check if:
1. Migration has been run
2. `IsBookablePlanningSource` trait is used
3. Model implements `BookablePlanningSource` interface
4. `syncBookablePlanning()` method is implemented

### Resource Not Updated

Check if:
1. `IsBookable` trait is used
2. Model implements `Bookable` interface
3. `syncBookableResource()` method is implemented
4. `BookableResource` exists and is associated

### Multiple Planning for Same Resource

This is by design! Multiple sources (rates, offers) can create planning for the same resource:

```php
// Query all planning for a resource
$resource->plannings; // Returns all planning

// Query by source type
$resource->plannings()->where('source_type', Rate::class)->get();

// Get the source model
$planning->source; // Returns Rate, Offer, or null
```
