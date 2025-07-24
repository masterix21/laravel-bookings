# Troubleshooting Guide

This guide helps you diagnose and solve common issues with Laravel Bookings.

## Installation Issues

### Migration Errors

**Problem:** Migration fails with foreign key constraint errors.

```bash
SQLSTATE[HY000]: General error: 1215 Cannot add foreign key constraint
```

**Solutions:**

1. **Check database engine:**
   ```sql
   -- Ensure using InnoDB for foreign key support
   ALTER TABLE bookable_resources ENGINE=InnoDB;
   ```

2. **Run migrations in correct order:**
   ```bash
   php artisan migrate:reset
   php artisan migrate
   ```

3. **Check column types match:**
   ```php
   // Ensure ID columns are consistent
   $table->bigIncrements('id');           // Parent table
   $table->bigInteger('parent_id');       // Foreign key table
   ```

**Problem:** "Class not found" errors during migration.

**Solution:**
```bash
# Clear and regenerate autoload files
composer dump-autoload

# Clear Laravel caches
php artisan cache:clear
php artisan config:clear
php artisan route:clear
```

### Package Discovery Issues

**Problem:** Service provider not auto-discovered.

**Solution:**
```php
// Manually register in config/app.php
'providers' => [
    // Other providers...
    Masterix21\Bookings\BookingsServiceProvider::class,
],
```

## Booking Creation Issues

### Overlap Detection Problems

**Problem:** Bookings created despite existing overlaps.

**Debugging Steps:**

1. **Check BookableResource configuration:**
   ```php
   $resource = BookableResource::find($id);
   
   // Verify resource is properly configured
   dump([
       'is_bookable' => $resource->is_bookable,
       'is_visible' => $resource->is_visible,
       'max' => $resource->max,
       'size' => $resource->size,
   ]);
   ```

2. **Verify periods format:**
   ```php
   use Spatie\Period\Period;
   use Carbon\Carbon;
   
   // Ensure proper period creation
   $start = Carbon::parse('2024-12-25 09:00:00');
   $end = Carbon::parse('2024-12-25 17:00:00');
   
   // This should not throw errors
   $period = Period::make($start, $end);
   ```

3. **Check existing bookings:**
   ```php
   $existingPeriods = $resource->bookedPeriods()
       ->whereBetween('starts_at', [$searchStart, $searchEnd])
       ->get();
       
   foreach ($existingPeriods as $period) {
       dump("Existing: {$period->starts_at} - {$period->ends_at}");
   }
   ```

**Problem:** CheckBookingOverlaps returns incorrect results.

**Solution:**
```php
// Debug the overlap checker
$checker = new CheckBookingOverlaps();

try {
    $result = $checker->run(
        periods: $periods,
        bookableResource: $resource,
        emitEvent: true,
        throw: false  // Don't throw, just return boolean
    );
    
    Log::info('Overlap check result', [
        'result' => $result,
        'periods' => $periods->map(fn($p) => [
            'start' => $p->start()->toISOString(),
            'end' => $p->end()->toISOString(),
        ]),
        'resource_id' => $resource->id,
    ]);
} catch (\Exception $e) {
    Log::error('Overlap check failed', [
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString(),
    ]);
}
```

### Transaction Rollback Issues

**Problem:** Partial booking data remains after transaction failure.

**Debugging:**
```php
// Enable database query logging
DB::enableQueryLog();

try {
    $booking = (new BookResource())->run(/* ... */);
} catch (\Exception $e) {
    // Check what queries were executed
    $queries = DB::getQueryLog();
    Log::error('Booking transaction failed', [
        'error' => $e->getMessage(),
        'queries' => $queries,
    ]);
    
    throw $e;
}
```

**Solution:**
```php
// Ensure proper transaction handling
DB::transaction(function () {
    // Your booking logic here
    // Exceptions will automatically rollback
});
```

## Performance Issues

### Slow Booking Queries

**Problem:** Booking creation or availability checks are slow.

**Solutions:**

1. **Add database indexes:**
   ```sql
   -- Essential indexes for performance
   CREATE INDEX idx_bookable_resources_type_id ON bookable_resources(resource_type, resource_id);
   CREATE INDEX idx_booked_periods_resource_dates ON booked_periods(bookable_resource_id, starts_at, ends_at);
   CREATE INDEX idx_bookings_booker ON bookings(booker_type, booker_id);
   CREATE INDEX idx_bookings_created_at ON bookings(created_at);
   ```

2. **Optimize eager loading:**
   ```php
   // Load relationships efficiently
   $bookings = Booking::with([
       'bookedPeriods.bookableResource.resource',
       'booker'
   ])->get();
   
   // Instead of multiple queries
   $bookings = Booking::all();
   foreach ($bookings as $booking) {
       $resource = $booking->bookedPeriods->first()->bookableResource->resource; // N+1 query
   }
   ```

3. **Use query scopes:**
   ```php
   // Create efficient scopes
   public function scopeForResource($query, $resourceType, $resourceId)
   {
       return $query->whereHas('bookedPeriods.bookableResource', function ($q) use ($resourceType, $resourceId) {
           $q->where('resource_type', $resourceType)
             ->where('resource_id', $resourceId);
       });
   }
   ```

### Memory Issues

**Problem:** Out of memory errors when processing large datasets.

**Solutions:**

1. **Use chunking for large operations:**
   ```php
   // Process bookings in chunks
   Booking::whereDate('created_at', today())
       ->chunk(100, function ($bookings) {
           foreach ($bookings as $booking) {
               // Process individual booking
           }
       });
   ```

2. **Optimize period collections:**
   ```php
   // Don't load all periods at once
   $periods = $booking->bookedPeriods()
       ->select(['starts_at', 'ends_at', 'booking_id'])
       ->get();
   ```

## Event System Issues

### Events Not Firing

**Problem:** Booking events are not triggered.

**Debugging:**
```php
// Check if events are registered
$listeners = Event::getListeners(BookingCompleted::class);
dump('Registered listeners:', $listeners);

// Add debug logging to events
Event::listen(BookingCompleted::class, function ($event) {
    Log::info('BookingCompleted event fired', [
        'booking_id' => $event->booking->id,
        'resource_id' => $event->bookableResource->id,
    ]);
});
```

**Solutions:**

1. **Ensure EventServiceProvider is registered:**
   ```php
   // app/Providers/EventServiceProvider.php
   protected $listen = [
       BookingCompleted::class => [
           YourListener::class,
       ],
   ];
   ```

2. **Check event discovery:**
   ```bash
   php artisan event:list
   ```

### Queue Issues with Events

**Problem:** Queued event listeners fail or timeout.

**Solutions:**

1. **Configure queue timeouts:**
   ```php
   // config/queue.php
   'connections' => [
       'redis' => [
           'retry_after' => 300,  // 5 minutes
           'block_for' => null,
       ],
   ],
   ```

2. **Handle failed jobs:**
   ```php
   // In your listener
   public function failed(\Exception $exception)
   {
       Log::error('Event listener failed', [
           'listener' => static::class,
           'error' => $exception->getMessage(),
       ]);
   }
   ```

## Model Relationship Issues

### Polymorphic Relationship Problems

**Problem:** Polymorphic relationships return null or incorrect models.

**Debugging:**
```php
// Check morphMap configuration
dump(Relation::morphMap());

// Verify polymorphic data
$booking = Booking::find($id);
dump([
    'booker_type' => $booking->booker_type,
    'booker_id' => $booking->booker_id,
    'booker_exists' => $booking->booker !== null,
]);
```

**Solutions:**

1. **Configure morphMap:**
   ```php
   // In AppServiceProvider::boot()
   use Illuminate\Database\Eloquent\Relations\Relation;
   
   Relation::morphMap([
       'user' => \App\Models\User::class,
       'organization' => \App\Models\Organization::class,
   ]);
   ```

2. **Check database data:**
   ```sql
   -- Verify polymorphic columns have correct values
   SELECT booker_type, booker_id, COUNT(*) 
   FROM bookings 
   GROUP BY booker_type, booker_id;
   ```

### Missing Relationships

**Problem:** Relationships return empty collections when data exists.

**Solution:**
```php
// Check foreign key constraints
Schema::table('booked_periods', function (Blueprint $table) {
    // Ensure foreign keys exist
    $table->foreign('booking_id')->references('id')->on('bookings');
    $table->foreign('bookable_resource_id')->references('id')->on('bookable_resources');
});
```

## Configuration Issues

### Timezone Problems

**Problem:** Booking times are incorrect due to timezone issues.

**Solutions:**

1. **Configure application timezone:**
   ```php
   // config/app.php
   'timezone' => 'UTC', // Use UTC for database storage
   ```

2. **Handle user timezones:**
   ```php
   // Convert to user timezone for display
   $userTimezone = auth()->user()->timezone ?? 'UTC';
   $localTime = $booking->starts_at->setTimezone($userTimezone);
   ```

3. **Consistent Carbon usage:**
   ```php
   // Always use Carbon for dates
   use Carbon\Carbon;
   
   $start = Carbon::parse($request->start_time, $userTimezone)
                  ->utc(); // Convert to UTC for storage
   ```

### Configuration Cache Issues

**Problem:** Configuration changes not taking effect.

**Solution:**
```bash
# Clear all caches
php artisan config:clear
php artisan cache:clear
php artisan view:clear
php artisan route:clear

# In production, rebuild caches
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

## Testing Issues

### Factory Relationship Problems

**Problem:** Test factories fail to create proper relationships.

**Solution:**
```php
// Create proper factory relationships
BookableResource::factory()
    ->has(BookedPeriod::factory()->count(3))
    ->create([
        'resource_type' => Room::class,
        'resource_id' => Room::factory(),
    ]);
```

### Database State Issues

**Problem:** Tests interfere with each other.

**Solutions:**

1. **Use RefreshDatabase trait:**
   ```php
   use Illuminate\Foundation\Testing\RefreshDatabase;
   
   class BookingTest extends TestCase
   {
       use RefreshDatabase;
   }
   ```

2. **Reset database state between tests:**
   ```php
   protected function setUp(): void
   {
       parent::setUp();
       
       // Clear any cached data
       app()->forgetInstance('bookings.cache');
   }
   ```

## Common Error Messages

### "BookingResourceOverlappingException"

**Cause:** Attempting to book when resource is already reserved.

**Debug:**
```php
try {
    $booking = (new BookResource())->run(/* ... */);
} catch (BookingResourceOverlappingException $e) {
    $conflicts = $e->getConflictingBookings();
    
    Log::error('Booking overlap detected', [
        'conflicting_bookings' => $conflicts->pluck('id'),
        'requested_periods' => $periods->map(fn($p) => [
            'start' => $p->start(),
            'end' => $p->end(),
        ]),
    ]);
}
```

### "OutOfPlanningsException"

**Cause:** No valid planning exists for the requested time.

**Debug:**
```php
$plannings = $resource->bookablePlannings()
    ->where('starts_at', '<=', $requestedDate)
    ->where('ends_at', '>=', $requestedDate)
    ->get();
    
if ($plannings->isEmpty()) {
    Log::warning('No plannings found', [
        'resource_id' => $resource->id,
        'requested_date' => $requestedDate,
    ]);
}
```

### "NoFreeSizeException"

**Cause:** Resource capacity exceeded.

**Debug:**
```php
$currentBookings = $resource->bookedPeriods()
    ->where('starts_at', '<=', $requestedEnd)
    ->where('ends_at', '>=', $requestedStart)
    ->count();
    
Log::info('Capacity check', [
    'resource_max' => $resource->max,
    'current_bookings' => $currentBookings,
    'requested_size' => $requestedSize,
]);
```

## Debugging Tools

### Enable Debug Mode

```php
// .env
APP_DEBUG=true
LOG_LEVEL=debug

// Log all booking operations
LOG_CHANNEL=single
```

### Custom Debug Helper

```php
// Create a debug helper
class BookingDebugger
{
    public static function dumpBookingState(BookableResource $resource, PeriodCollection $periods): void
    {
        dump([
            'resource' => [
                'id' => $resource->id,
                'type' => $resource->resource_type,
                'is_bookable' => $resource->is_bookable,
                'max' => $resource->max,
                'size' => $resource->size,
            ],
            'requested_periods' => $periods->map(fn($p) => [
                'start' => $p->start()->toISOString(),
                'end' => $p->end()->toISOString(),
            ]),
            'existing_periods' => $resource->bookedPeriods()
                ->select(['starts_at', 'ends_at', 'booking_id'])
                ->get()
                ->map(fn($p) => [
                    'start' => $p->starts_at->toISOString(),
                    'end' => $p->ends_at->toISOString(),
                    'booking_id' => $p->booking_id,
                ]),
        ]);
    }
}
```

### Performance Profiling

```php
// Add to your booking code
$start = microtime(true);

$booking = (new BookResource())->run(/* ... */);

$duration = microtime(true) - $start;
Log::info('Booking performance', [
    'duration_ms' => round($duration * 1000, 2),
    'memory_peak' => memory_get_peak_usage(true),
]);
```

## Getting Help

### Information to Include

When seeking help, provide:

1. **Laravel and PHP versions:**
   ```bash
   php artisan --version
   php --version
   ```

2. **Package version:**
   ```bash
   composer show masterix21/laravel-bookings
   ```

3. **Database information:**
   ```sql
   SELECT VERSION(); -- Database version
   SHOW ENGINE INNODB STATUS; -- InnoDB status
   ```

4. **Error logs:**
   ```bash
   tail -f storage/logs/laravel.log
   ```

5. **Relevant code snippets and stack traces**

### Community Resources

- **GitHub Issues:** Report bugs and feature requests
- **Discussions:** Ask questions and share solutions
- **Stack Overflow:** Tag questions with `laravel-bookings`

This troubleshooting guide covers the most common issues. For complex problems, enable debug logging and use the debugging tools provided.