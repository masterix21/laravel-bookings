# Getting Started

This tutorial will guide you through creating your first bookable resource and making a booking using Laravel Bookings.

## Prerequisites

- Completed [Installation](installation.md)
- Basic understanding of Laravel Eloquent
- Familiarity with Carbon dates

## Quick Start Tutorial

### Step 1: Create a Bookable Model

Let's create a simple `Room` model that can be booked:

```bash
php artisan make:model Room -m
```

```php
<?php
// app/Models/Room.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Masterix21\Bookings\Models\Concerns\IsBookable;
use Masterix21\Bookings\Models\Concerns\Bookable;

class Room extends Model implements Bookable
{
    use IsBookable;

    protected $fillable = [
        'name',
        'capacity',
        'hourly_rate',
        'description',
    ];

    protected $casts = [
        'hourly_rate' => 'decimal:2',
    ];
}
```

```php
<?php
// database/migrations/create_rooms_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rooms', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->integer('capacity');
            $table->decimal('hourly_rate', 8, 2);
            $table->text('description')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rooms');
    }
};
```

### Step 2: Create a Booker Model

Modify your User model to support bookings:

```php
<?php
// app/Models/User.php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Masterix21\Bookings\Models\Concerns\HasBookings;

class User extends Authenticatable
{
    use HasBookings;

    // Your existing user code...
}
```

### Step 3: Run Migrations

```bash
php artisan migrate
```

### Step 4: Create Sample Data

Create a room and user:

```php
<?php
// database/seeders/BookingSeeder.php

namespace Database\Seeders;

use App\Models\Room;
use App\Models\User;
use Illuminate\Database\Seeder;
use Masterix21\Bookings\Models\BookableResource;

class BookingSeeder extends Seeder
{
    public function run(): void
    {
        // Create a room
        $room = Room::create([
            'name' => 'Conference Room A',
            'capacity' => 8,
            'hourly_rate' => 50.00,
            'description' => 'Modern conference room with projector',
        ]);

        // Make it bookable
        BookableResource::create([
            'resource_type' => Room::class,
            'resource_id' => $room->id,
            'max' => 1, // Only one booking at a time
            'size' => 8, // Room capacity
            'is_bookable' => true,
            'is_visible' => true,
        ]);

        // Create a user
        User::create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => bcrypt('password'),
        ]);
    }
}
```

Run the seeder:

```bash
php artisan db:seed --class=BookingSeeder
```

### Step 5: Make Your First Booking

```php
<?php

use App\Models\Room;
use App\Models\User;
use Masterix21\Bookings\Actions\BookResource;
use Spatie\Period\Period;
use Spatie\Period\PeriodCollection;
use Carbon\Carbon;

// Get the room and user
$room = Room::first();
$user = User::first();
$bookableResource = $room->bookableResource;

// Define the booking period (tomorrow 2-4 PM)
$tomorrow = Carbon::tomorrow();
$periods = PeriodCollection::make([
    Period::make(
        $tomorrow->copy()->setTime(14, 0), // 2:00 PM
        $tomorrow->copy()->setTime(16, 0)  // 4:00 PM
    )
]);

// Create the booking
$booking = (new BookResource())->run(
    periods: $periods,
    bookableResource: $bookableResource,
    booker: $user,
    label: 'Team Meeting',
    note: 'Weekly team sync',
    meta: [
        'attendees' => 5,
        'equipment' => ['projector', 'whiteboard'],
        'catering' => false,
    ]
);

echo "Booking created with code: " . $booking->code;
```

## Understanding the Core Concepts

### BookableResource

The `BookableResource` is the central entity that makes any model bookable:

```php
$bookableResource = BookableResource::create([
    'resource_type' => Room::class,    // The model class
    'resource_id' => $room->id,        // The model ID
    'max' => 1,                        // Max concurrent bookings
    'size' => 8,                       // Resource capacity
    'is_bookable' => true,             // Can be booked
    'is_visible' => true,              // Visible in searches
]);
```

### Periods

Periods define when something is booked using the Spatie Period library:

```php
use Spatie\Period\Period;
use Carbon\Carbon;

// Single period
$period = Period::make('2024-12-25 09:00', '2024-12-25 17:00');

// Multiple periods (for complex bookings)
$periods = PeriodCollection::make([
    Period::make('2024-12-25 09:00', '2024-12-25 12:00'), // Morning
    Period::make('2024-12-25 13:00', '2024-12-25 17:00'), // Afternoon
]);
```

### Bookings

A booking contains:
- **Periods**: When the resource is reserved
- **Booker**: Who made the booking (polymorphic)
- **Resource**: What is being booked
- **Metadata**: Additional information

## Working with Bookings

### Check Availability

```php
// Check if room is available at specific time
$isAvailable = !$room->isBookedAt(Carbon::now());

// Get all bookings for today
$todayBookings = $room->bookedPeriodsOfDate(Carbon::today());

// Get all bookings for the room
$allBookings = $room->bookings;
```

### Query Available Resources

```php
use Masterix21\Bookings\Actions\CheckBookingOverlaps;

$availableRooms = Room::whereHas('bookableResource', function ($query) {
    $query->where('is_bookable', true);
})->get()->filter(function ($room) use ($periods) {
    return (new CheckBookingOverlaps())->run(
        periods: $periods,
        bookableResource: $room->bookableResource,
        emitEvent: false,
        throw: false
    );
});
```

### Handle Booking Conflicts

```php
use Masterix21\Bookings\Exceptions\BookingResourceOverlappingException;

try {
    $booking = (new BookResource())->run(
        periods: $periods,
        bookableResource: $bookableResource,
        booker: $user
    );
} catch (BookingResourceOverlappingException $e) {
    // Handle conflict
    return response()->json([
        'error' => 'The requested time slot is not available',
        'conflicting_bookings' => $e->getConflictingBookings(),
    ], 409);
}
```

## Planning and Constraints

### Create Availability Rules

```php
use Masterix21\Bookings\Models\BookablePlanning;

// Room available Monday-Friday, 9 AM - 6 PM
BookablePlanning::create([
    'bookable_resource_id' => $bookableResource->id,
    'monday' => true,
    'tuesday' => true,
    'wednesday' => true,
    'thursday' => true,
    'friday' => true,
    'saturday' => false,
    'sunday' => false,
    'starts_at' => '2024-01-01 09:00:00',
    'ends_at' => '2024-12-31 18:00:00',
]);
```

## Events and Listeners

Listen to booking events:

```php
// app/Providers/EventServiceProvider.php

use Masterix21\Bookings\Events\BookingCompleted;
use App\Listeners\SendBookingConfirmation;

protected $listen = [
    BookingCompleted::class => [
        SendBookingConfirmation::class,
    ],
];
```

```php
<?php
// app/Listeners/SendBookingConfirmation.php

namespace App\Listeners;

use Masterix21\Bookings\Events\BookingCompleted;
use Illuminate\Support\Facades\Mail;
use App\Mail\BookingConfirmationMail;

class SendBookingConfirmation
{
    public function handle(BookingCompleted $event): void
    {
        $booking = $event->booking;
        $booker = $booking->booker;

        if ($booker && $booker->email) {
            Mail::to($booker->email)->send(
                new BookingConfirmationMail($booking)
            );
        }
    }
}
```

## Testing Your Implementation

```php
<?php
// tests/Feature/RoomBookingTest.php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Room;
use App\Models\User;
use Masterix21\Bookings\Models\BookableResource;
use Masterix21\Bookings\Actions\BookResource;
use Spatie\Period\Period;
use Spatie\Period\PeriodCollection;
use Carbon\Carbon;

class RoomBookingTest extends TestCase
{
    public function test_can_book_available_room(): void
    {
        $room = Room::factory()->create();
        $user = User::factory()->create();
        
        $bookableResource = BookableResource::factory()->create([
            'resource_type' => Room::class,
            'resource_id' => $room->id,
        ]);

        $periods = PeriodCollection::make([
            Period::make(
                Carbon::tomorrow()->setTime(14, 0),
                Carbon::tomorrow()->setTime(16, 0)
            )
        ]);

        $booking = (new BookResource())->run(
            periods: $periods,
            bookableResource: $bookableResource,
            booker: $user
        );

        $this->assertNotNull($booking);
        $this->assertEquals($user->id, $booking->booker_id);
        $this->assertCount(1, $booking->bookedPeriods);
    }
}
```

## Next Steps

Now that you have a basic booking system:

1. Explore [API Reference](api-reference.md) for advanced features
2. Check [Examples](examples/) for real-world implementations
3. Learn about [Events](events.md) for custom workflows
4. Review [Models](models.md) for relationship details
5. See [Actions](actions.md) for complex operations

## Common Patterns

### Service Layer Pattern

```php
<?php
// app/Services/BookingService.php

namespace App\Services;

use App\Models\Room;
use App\Models\User;
use Masterix21\Bookings\Actions\BookResource;
use Carbon\Carbon;
use Spatie\Period\Period;
use Spatie\Period\PeriodCollection;

class BookingService
{
    public function bookRoom(
        Room $room,
        User $user,
        Carbon $startTime,
        Carbon $endTime,
        array $metadata = []
    ) {
        $periods = PeriodCollection::make([
            Period::make($startTime, $endTime)
        ]);

        return (new BookResource())->run(
            periods: $periods,
            bookableResource: $room->bookableResource,
            booker: $user,
            meta: $metadata
        );
    }
}
```

This completes your getting started guide! You now have a functional booking system with rooms and users.