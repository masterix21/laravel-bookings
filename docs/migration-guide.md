# Migration and Upgrade Guide

This guide helps you migrate between versions of Laravel Bookings and upgrade your existing implementation.

## Version Compatibility

### Laravel Bookings Version Matrix

| Laravel Bookings | Laravel | PHP | Status |
|------------------|---------|-----|--------|
| 2.x | 12.x, 13.x | 8.4+ | Current |
| 1.x | 12.x (13.x added in 1.3.0) | 8.4+ | Previous |
| 0.x | 9.x, 10.x | 8.1+ | Legacy |

## Upgrading from 1.x

This release corrects overlap detection, availability scopes, and several database constraints. The behavior changes below take effect immediately after `composer update` — no migration required. The schema changes require publishing and running two new migrations, one of which has a manual prerequisite.

### Step 1: Update the package

```bash
composer update masterix21/laravel-bookings
```

### Step 2: Clean up NULL period dates (REQUIRED before migrating)

`booked_periods.starts_at` and `booked_periods.ends_at` become `NOT NULL`. The upgrade migration will **fail** if any existing row has a NULL value in either column. Check for offending rows first:

```sql
SELECT id FROM booked_periods WHERE starts_at IS NULL OR ends_at IS NULL;
```

Backfill or delete those rows before continuing — the package has no opinion on which is correct for your data.

### Step 3: Publish and run the new migrations

```bash
php artisan vendor:publish --tag="bookings-migrations"
php artisan migrate
```

Two new publishable migrations ship with this release:

| Migration | What it does |
|-----------|---------------|
| `update_booked_periods_constraints` | Makes `starts_at`/`ends_at` `NOT NULL`; swaps `booked_periods` foreign keys from cascading deletes to `booking_id` → `restrictOnDelete()` and `bookable_resource_id` → `nullOnDelete()`. |
| `update_bookable_plannings_add_indexes` | Adds the composite index `bookable_plannings_resource_dates_index` on `(bookable_resource_id, starts_at, ends_at)`. |

On **SQLite**, the foreign key swap in `update_booked_periods_constraints` is skipped (SQLite cannot drop foreign keys); the `NOT NULL` change still applies. Fresh installations get the correct constraints directly from `create_booked_periods_table` and need no update migration.

### Step 4: Review the foreign key behavior change

`booked_periods` foreign keys no longer cascade. This changes what happens when you delete a `Booking` or a `BookableResource` that still has periods:

| Deleting | Before | After |
|----------|--------|-------|
| A `Booking` with `booked_periods` rows | Periods were deleted along with the booking (`cascadeOnDelete`) | The delete fails with a foreign key violation (`restrictOnDelete`) until your application deletes or soft-deletes the periods first |
| A `BookableResource` with `booked_periods` rows | Periods were deleted along with the resource (`cascadeOnDelete`) | `booked_periods.bookable_resource_id` is set to `null` (`nullOnDelete`), so booking history survives resource deletion |

If your application deletes bookings or resources directly (rather than through `BookResource`), audit those code paths: a raw `Booking::destroy($id)` or `$resource->delete()` on a row with existing periods now behaves differently than before.

### Behavior changes reference

These apply as soon as the package is updated, independent of the schema migrations above:

| # | Area | Before | After |
|---|------|--------|-------|
| 1 | `CheckBookingOverlaps` | Summed every overlapping period row across all requested periods, over-blocking multi-period requests; multiple rows from the same booking counted separately | Measures the highest number of *distinct* bookings active at any single instant of each requested period; rows belonging to the same booking count once |
| 2 | Occupancy counting | `is_excluded` periods still counted toward occupancy in some paths | `is_excluded = true` periods never count as occupancy, in `CheckBookingOverlaps` and in the availability scopes |
| 3 | Boundary semantics | Availability scopes used strict comparisons (`<`/`>`) while the booking check used inclusive ones, so a resource could show as available and then fail to book | Both use inclusive comparisons (`starts_at <= end AND ends_at >= start`) everywhere; touching/back-to-back periods overlap consistently in booking checks and in `scopeAvailableSlotForPeriod`/`scopeWithBookingsInPeriod` |
| 4 | `BookableResource.max = null` | Resources with no `max` set never appeared in `scopeAvailableSlotForPeriod` | `null` consistently means unlimited capacity, including in that scope |
| 5 | `BookResource::run()` on update | Omitting `label`, `note`, or `meta` nulled the existing value | Omitting them (passing `null`) keeps the current value, matching the existing behavior of `code`, `booker`, and `parent` |
| 6 | `booker_type` / `relatable_type` storage | Always stored the fully qualified class name (`::class`) | Stored via `getMorphClass()`, honoring a configured `Relation::morphMap()` / `enforceMorphMap()` |
| 7 | Transaction failure handling | Only `\Exception` triggered a rollback; failure events fired inside the still-open, doomed transaction | Any `\Throwable` triggers a rollback; failure events fire *after* the rollback completes. A creation that fails after `save()` now emits `BookingFailed` (previously `BookingChangeFailed`) |
| 8 | `ensureIsAvailable()` | Broken since introduction: wrong trait placement and a `Collection` passed where a `PeriodCollection` was expected (guaranteed `TypeError`) | Repaired: `BookableResource::ensureIsAvailable()` works end-to-end; `IsBookable` models delegate to their resource(s) and pass if **at least one** is available, rethrowing the last failure otherwise. `ensureHasFreeSize()`/`ensureRelationsHaveFreeSize()` now take a `PeriodCollection $periods` parameter (was `Collection $dates`) |
| 9 | Abolished "Area" concept leftovers | `BookableResource::bookableRelations()` queried non-existent columns and threw an SQL error; `BookableRelation`'s fillable listed the same phantom columns; `vendor:publish` was broken by a reference to a non-existent `create_bookable_areas_table` migration | `bookableRelations()` is a working `hasMany` through `parent_bookable_resource_id`; `BookableRelation`'s fillable matches the real schema (`parent_bookable_resource_id`, `bookable_resource_id`, `is_required`, `min`, `max`); `vendor:publish` works again |
| 10 | `RandomBookingCode` exception message | Said "keep your prefix and suffix below 26 characters" (wrong threshold) | States the real constraint: combined prefix + suffix length must be below 38 characters |

If you call `ensureHasFreeSize()` or `ensureRelationsHaveFreeSize()` directly (outside of `ensureIsAvailable()`), update the call site to pass a `PeriodCollection` instead of a `Collection` of dates.

## Upgrading to Version 1.2.0

Version 1.2.0 introduces the Related Bookings feature, which adds a parent-child relationship pattern to bookings. This is a **non-breaking, opt-in feature** that requires an optional database migration.

### What's New in v1.2.0

- Parent-child booking relationships
- Ability to link related bookings together
- New `parent_booking_id` column in bookings table
- New `parentBooking()` and `childBookings()` relationship methods on Booking model
- Optional `$parent` parameter in `BookResource::run()` action

### Breaking Changes

**None.** This version is fully backward compatible with v1.1.x. All changes are opt-in.

### Migration Steps

#### Step 1: Update Package Version

```bash
composer update masterix21/laravel-bookings
```

#### Step 2: Publish and Run Migration (Optional)

This step is **only required if you want to use the related bookings feature**.

```bash
# Publish the migration file
php artisan vendor:publish --tag="bookings-migrations"

# Review the migration file
# database/migrations/xxxx_xx_xx_update_bookings_add_parent_booking_id.php

# Run the migration
php artisan migrate
```

The migration adds a single nullable column to the bookings table:

```php
Schema::table('bookings', function (Blueprint $table) {
    $table->foreignId('parent_booking_id')
        ->nullable()
        ->after('id')
        ->constrained('bookings')
        ->nullOnDelete();
});
```

#### Step 3: Start Using Related Bookings (Optional)

Once migrated, you can start creating parent-child booking relationships:

```php
use Masterix21\Bookings\Actions\BookResource;
use Spatie\Period\Period;
use Spatie\Period\PeriodCollection;

// Create a parent booking
$parentBooking = (new BookResource())->run(
    periods: PeriodCollection::make([
        Period::make('2024-12-25 14:00', '2024-12-25 16:00')
    ]),
    bookableResource: $room,
    booker: $user,
    label: 'Hotel Room'
);

// Create a child booking linked to the parent
$childBooking = (new BookResource())->run(
    periods: PeriodCollection::make([
        Period::make('2024-12-25 14:00', '2024-12-25 16:00')
    ]),
    bookableResource: $parkingSpot,
    booker: $user,
    parent: $parentBooking,
    label: 'Parking Spot'
);

// Access relationships
$children = $parentBooking->childBookings; // Collection of child bookings
$parent = $childBooking->parentBooking;    // Parent booking instance
```

### Behavior Notes

#### Cascade Deletion with `nullOnDelete()`

The migration uses `nullOnDelete()` for the foreign key constraint. This means:

- **When a parent booking is deleted**: Child bookings become independent (their `parent_booking_id` is set to `null`)
- **Child bookings are NOT deleted** when the parent is deleted
- This allows child bookings to survive parent deletion, maintaining booking integrity

**Example:**
```php
// Create parent and child
$parentBooking = (new BookResource())->run(/* ... */);
$childBooking = (new BookResource())->run(parent: $parentBooking, /* ... */);

// Delete parent
$parentBooking->delete();

// Child still exists but is now independent
$childBooking->refresh();
$childBooking->parent_booking_id; // null
$childBooking->exists; // true
```

#### Updating Parent Relationships

You can update a booking's parent relationship:

```php
// Change parent
$booking->update(['parent_booking_id' => $newParent->id]);

// Remove parent relationship
$booking->update(['parent_booking_id' => null]);

// Using BookResource action
(new BookResource())->run(
    booking: $existingBooking,
    parent: $newParent,
    periods: $periods,
    bookableResource: $resource,
    booker: $user
);
```

### Migration Without the Related Bookings Feature

If you don't need the related bookings feature:

1. **Do nothing** - Your installation will continue working as before
2. **Don't publish the migration** - The column won't be added
3. **Don't use the `$parent` parameter** - The feature is completely opt-in

The package remains fully functional without this migration.

### Rollback Plan

If you need to rollback the migration:

```bash
# Rollback the specific migration
php artisan migrate:rollback --step=1

# Or create a down migration
php artisan make:migration remove_parent_booking_id_from_bookings
```

**Down migration example:**
```php
Schema::table('bookings', function (Blueprint $table) {
    $table->dropForeign(['parent_booking_id']);
    $table->dropColumn('parent_booking_id');
});
```

**Note:** Before rolling back, ensure no bookings have parent relationships, or they will be broken.

### Testing the Migration

After migrating, verify the feature works correctly:

```php
// In tinker or test environment
php artisan tinker

// Create test parent booking
$parent = \Masterix21\Bookings\Models\Booking::factory()->create();

// Create test child booking
$child = \Masterix21\Bookings\Models\Booking::factory()->create([
    'parent_booking_id' => $parent->id
]);

// Test relationships
$parent->childBookings; // Should return collection with $child
$child->parentBooking; // Should return $parent

// Test cascade deletion
$parent->delete();
$child->refresh();
$child->parent_booking_id; // Should be null
```

## Upgrading to Version 1.0

### Breaking Changes

#### 1. Configuration File Structure

**Before (0.x):**
```php
// config/bookings.php
return [
    'user_model' => \App\Models\User::class,
    'booking_code_generator' => 'random',
];
```

**After (1.x):**
```php
// config/bookings.php
return [
    'models' => [
        'user' => \App\Models\User::class,
        // ... other models
    ],
    'generators' => [
        'booking_code' => \Masterix21\Bookings\Generators\RandomBookingCode::class,
    ],
];
```

**Migration Steps:**
```bash
# 1. Backup your current configuration
cp config/bookings.php config/bookings.php.backup

# 2. Republish the configuration
php artisan vendor:publish --tag="bookings-config" --force

# 3. Migrate your custom settings
```

#### 2. Model Namespace Changes

**Before (0.x):**
```php
use Masterix21\Bookings\BookableResource;
use Masterix21\Bookings\Booking;
```

**After (1.x):**
```php
use Masterix21\Bookings\Models\BookableResource;
use Masterix21\Bookings\Models\Booking;
```

**Migration Script:**
```bash
# Find and replace across your codebase
find . -name "*.php" -exec sed -i 's/use Masterix21\\Bookings\\BookableResource/use Masterix21\\Bookings\\Models\\BookableResource/g' {} +
find . -name "*.php" -exec sed -i 's/use Masterix21\\Bookings\\Booking/use Masterix21\\Bookings\\Models\\Booking/g' {} +
```

#### 3. Action Class Changes

**Before (0.x):**
```php
use Masterix21\Bookings\BookResource;

$booking = BookResource::make()->execute($data);
```

**After (1.x):**
```php
use Masterix21\Bookings\Actions\BookResource;

$booking = (new BookResource())->run(
    periods: $periods,
    bookableResource: $resource,
    booker: $user
);
```

#### 4. Event System Updates

**Before (0.x):**
```php
// Events were not standardized
Event::listen('booking.created', function ($booking) {
    // Handle event
});
```

**After (1.x):**
```php
use Masterix21\Bookings\Events\BookingCompleted;

Event::listen(BookingCompleted::class, function ($event) {
    $booking = $event->booking;
    // Handle event
});
```

### Database Migrations

#### Required Schema Updates

The exact schema diff between your pre-1.0 fork and the current package depends entirely on what your custom implementation looked like — there's no single migration that fits everyone, and reproducing one here would just invent columns that were never actually part of this package (there is, for instance, no `creator_id` and no `relatable_type`/`relatable_id` on `bookings` at any released version — `relatable` has only ever lived on `booked_periods`). Compare your existing tables against [Database Schema](database-schema.md) and the real migration stubs in `database/migrations/*.php.stub`, and write a migration that reconciles the two for your specific case. Check the [CHANGELOG](../CHANGELOG.md) for the column-level history of each table.

**2. Run the migration:**
```bash
php artisan migrate
```

### Code Updates

#### 1. Update Model Implementations

**Before (0.x):**
```php
class Room extends Model
{
    use Bookable;
}
```

**After (1.x):**
```php
use Masterix21\Bookings\Models\Concerns\IsBookable;
use Masterix21\Bookings\Models\Concerns\Bookable;

class Room extends Model implements Bookable
{
    use IsBookable;
}
```

#### 2. Update Booking Creation

**Before (0.x):**
```php
$booking = Booking::create([
    'resource_id' => $room->id,
    'resource_type' => Room::class,
    'start_date' => $startDate,
    'end_date' => $endDate,
    'user_id' => $user->id,
]);
```

**After (1.x):**
```php
use Masterix21\Bookings\Actions\BookResource;
use Spatie\Period\Period;
use Spatie\Period\PeriodCollection;

$periods = PeriodCollection::make([
    Period::make($startDate, $endDate)
]);

$booking = (new BookResource())->run(
    periods: $periods,
    bookableResource: $room->bookableResource,
    booker: $user
);
```

#### 3. Update Availability Checking

**Before (0.x):**
```php
$available = $room->isAvailable($startDate, $endDate);
```

**After (1.x):**
```php
use Masterix21\Bookings\Actions\CheckBookingOverlaps;

$periods = PeriodCollection::make([
    Period::make($startDate, $endDate)
]);

$available = (new CheckBookingOverlaps())->run(
    periods: $periods,
    bookableResource: $room->bookableResource,
    throw: false
);
```

### Event Listener Updates

**Before (0.x):**
```php
// app/Providers/EventServiceProvider.php
protected $listen = [
    'booking.created' => [
        SendBookingConfirmation::class,
    ],
];
```

**After (1.x):**
```php
use Masterix21\Bookings\Events\BookingCompleted;

protected $listen = [
    BookingCompleted::class => [
        SendBookingConfirmation::class,
    ],
];
```

**Update Listener Classes:**
```php
// Before
class SendBookingConfirmation
{
    public function handle($booking)
    {
        // Handle booking
    }
}

// After
use Masterix21\Bookings\Events\BookingCompleted;

class SendBookingConfirmation
{
    public function handle(BookingCompleted $event)
    {
        $booking = $event->booking;
        // Handle booking
    }
}
```

## Upgrading from Legacy Systems

### From Custom Booking Implementation

If you're migrating from a custom booking system:

#### 1. Data Migration Script

```php
<?php
// database/migrations/2024_01_01_migrate_legacy_bookings.php

use Illuminate\Database\Migrations\Migration;
use Masterix21\Bookings\Models\BookableResource;
use Masterix21\Bookings\Models\Booking;
use Masterix21\Bookings\Models\BookedPeriod;

return new class extends Migration
{
    public function up(): void
    {
        // Migrate your legacy booking data
        $this->migrateLegacyBookings();
    }

    private function migrateLegacyBookings(): void
    {
        // Example: Migrate from 'old_bookings' table
        DB::table('old_bookings')->chunk(100, function ($legacyBookings) {
            foreach ($legacyBookings as $legacyBooking) {
                $this->migrateSingleBooking($legacyBooking);
            }
        });
    }

    private function migrateSingleBooking($legacyBooking): void
    {
        // Create or find bookable resource
        $resource = BookableResource::firstOrCreate([
            'resource_type' => $legacyBooking->resource_type,
            'resource_id' => $legacyBooking->resource_id,
        ], [
            'max' => 1,
            'size' => 1,
            'is_bookable' => true,
            'is_visible' => true,
        ]);

        // Create new booking
        $booking = Booking::create([
            'code' => $legacyBooking->booking_code,
            'booker_type' => $legacyBooking->user_type ?? 'App\\Models\\User',
            'booker_id' => $legacyBooking->user_id,
            'label' => $legacyBooking->title,
            'note' => $legacyBooking->notes,
            'meta' => json_decode($legacyBooking->metadata ?? '{}', true),
            'created_at' => $legacyBooking->created_at,
            'updated_at' => $legacyBooking->updated_at,
        ]);

        // Create booked period
        BookedPeriod::create([
            'booking_id' => $booking->id,
            'bookable_resource_id' => $resource->id,
            'starts_at' => $legacyBooking->start_date,
            'ends_at' => $legacyBooking->end_date,
        ]);
    }
};
```

#### 2. Data Validation Script

```php
<?php
// app/Console/Commands/ValidateMigratedBookings.php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Masterix21\Bookings\Models\Booking;
use Masterix21\Bookings\Models\BookedPeriod;

class ValidateMigratedBookings extends Command
{
    protected $signature = 'bookings:validate-migration';
    protected $description = 'Validate migrated booking data';

    public function handle(): void
    {
        $this->info('Validating migrated bookings...');

        $issues = [];

        // Check for bookings without periods
        $bookingsWithoutPeriods = Booking::doesntHave('bookedPeriods')->count();
        if ($bookingsWithoutPeriods > 0) {
            $issues[] = "Found {$bookingsWithoutPeriods} bookings without periods";
        }

        // Check for periods without bookings
        $periodsWithoutBookings = BookedPeriod::doesntHave('booking')->count();
        if ($periodsWithoutBookings > 0) {
            $issues[] = "Found {$periodsWithoutBookings} periods without bookings";
        }

        // Check for invalid date ranges
        $invalidPeriods = BookedPeriod::whereRaw('starts_at >= ends_at')->count();
        if ($invalidPeriods > 0) {
            $issues[] = "Found {$invalidPeriods} periods with invalid date ranges";
        }

        if (empty($issues)) {
            $this->info('✅ All migrated bookings are valid');
        } else {
            $this->error('❌ Migration issues found:');
            foreach ($issues as $issue) {
                $this->line("  - {$issue}");
            }
        }
    }
}
```

### From Other Booking Packages

#### Common Migration Patterns

**From "laravel-booking" package:**
```php
// Old structure
$booking = Booking::create([
    'bookable_type' => Room::class,
    'bookable_id' => $room->id,
    'customer_id' => $user->id,
    'starts_at' => $startDate,
    'ends_at' => $endDate,
]);

// New structure
$resource = BookableResource::firstOrCreate([
    'resource_type' => Room::class,
    'resource_id' => $room->id,
]);

$booking = (new BookResource())->run(
    periods: PeriodCollection::make([Period::make($startDate, $endDate)]),
    bookableResource: $resource,
    booker: $user
);
```

## Automated Migration Tools

The package does not ship any Artisan commands of its own (there is no `bookings:*` command namespace). Everything below — `bookings:migrate-to-v1`, `bookings:validate-migration`, `bookings:optimize-database` — is an example command **you** would write in `app/Console/Commands` for your own one-off migration, following the same pattern shown here.

### Migration Command

```php
<?php
// app/Console/Commands/MigrateToBookingsV1.php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class MigrateToBookingsV1 extends Command
{
    protected $signature = 'bookings:migrate-to-v1 {--dry-run : Preview changes without executing}';
    protected $description = 'Migrate to Laravel Bookings v1.0';

    public function handle(): void
    {
        $this->info('Starting migration to Laravel Bookings v1.0...');

        $dryRun = $this->option('dry-run');

        // Step 1: Backup database
        if (!$dryRun) {
            $this->createDatabaseBackup();
        }

        // Step 2: Update configuration
        $this->updateConfiguration($dryRun);

        // Step 3: Run database migrations
        $this->runDatabaseMigrations($dryRun);

        // Step 4: Migrate data
        $this->migrateBookingData($dryRun);

        // Step 5: Validate migration
        $this->validateMigration();

        $this->info('✅ Migration completed successfully!');
    }

    private function createDatabaseBackup(): void
    {
        $this->info('Creating database backup...');
        
        $filename = 'backup_' . date('Y_m_d_His') . '.sql';
        $command = sprintf(
            'mysqldump -u%s -p%s %s > %s',
            config('database.connections.mysql.username'),
            config('database.connections.mysql.password'),
            config('database.connections.mysql.database'),
            storage_path("app/backups/{$filename}")
        );

        exec($command);
        $this->info("Backup created: {$filename}");
    }

    private function updateConfiguration(bool $dryRun): void
    {
        $this->info('Updating configuration...');
        
        if ($dryRun) {
            $this->line('  - Would republish configuration file');
            return;
        }

        $this->call('vendor:publish', [
            '--tag' => 'bookings-config',
            '--force' => true,
        ]);
    }

    private function runDatabaseMigrations(bool $dryRun): void
    {
        $this->info('Running database migrations...');
        
        if ($dryRun) {
            $this->line('  - Would run: php artisan migrate');
            return;
        }

        $this->call('migrate');
    }

    private function migrateBookingData(bool $dryRun): void
    {
        $this->info('Migrating booking data...');
        
        if ($dryRun) {
            $this->line('  - Would migrate legacy booking records');
            return;
        }

        // Run data migration logic here
    }

    private function validateMigration(): void
    {
        $this->info('Validating migration...');
        $this->call('bookings:validate-migration');
    }
}
```

### Testing Migration

```php
<?php
// tests/Feature/MigrationTest.php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class MigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_migration_preserves_existing_bookings(): void
    {
        // Create legacy booking data
        $legacyBooking = $this->createLegacyBooking();

        // Run migration
        $this->runMigration();

        // Verify data integrity
        $this->assertDatabaseHas('bookings', [
            'code' => $legacyBooking->booking_code,
        ]);

        $this->assertDatabaseHas('booked_periods', [
            'starts_at' => $legacyBooking->start_date,
            'ends_at' => $legacyBooking->end_date,
        ]);
    }

    private function createLegacyBooking(): object
    {
        return (object) [
            'booking_code' => 'LEGACY-001',
            'start_date' => '2024-01-01 10:00:00',
            'end_date' => '2024-01-01 12:00:00',
            'user_id' => 1,
            'resource_id' => 1,
            'resource_type' => 'App\\Models\\Room',
        ];
    }

    private function runMigration(): void
    {
        $this->artisan('bookings:migrate-to-v1');
    }
}
```

## Post-Migration Checklist

### 1. Verify Functionality

```bash
# Run tests
php artisan test

# Check for broken functionality
php artisan bookings:validate-migration

# Test booking creation
php artisan tinker
```

### 2. Update Dependencies

```bash
# Update composer dependencies
composer update

# Clear caches
php artisan config:clear
php artisan cache:clear
php artisan view:clear
```

### 3. Performance Optimization

There is no `bookings:optimize-database` command. Add any extra indexes you need by hand — see [Database Schema → Indexing Strategy](database-schema.md#indexing-strategy) for what's already created by the package's own migrations versus what you might want to add.

```bash
# Cache configuration
php artisan config:cache
php artisan route:cache
```

### 4. Monitor for Issues

```php
// Add monitoring to critical paths
Log::info('Booking created via migration', [
    'booking_id' => $booking->id,
    'migration_version' => '1.0',
]);
```

## Rollback Plan

### Emergency Rollback

```bash
# 1. Restore database backup
mysql -u username -p database_name < backup_file.sql

# 2. Downgrade package (the last pre-1.0 release was 0.0.2 — there was no 0.9.x)
composer require masterix21/laravel-bookings:0.0.2

# 3. Restore old configuration
cp config/bookings.php.backup config/bookings.php

# 4. Clear caches
php artisan config:clear
php artisan cache:clear
```

This migration guide provides a comprehensive path for upgrading to Laravel Bookings v1.0 while maintaining data integrity and minimizing downtime.