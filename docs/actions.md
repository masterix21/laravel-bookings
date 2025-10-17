# Actions Guide

Laravel Bookings uses the Action pattern to encapsulate complex business logic. This guide covers all available actions and how to use them effectively.

## Core Actions

### BookResource

The primary action for creating and updating bookings with full transaction safety.

#### Basic Usage

```php
use Masterix21\Bookings\Actions\BookResource;
use Spatie\Period\Period;
use Spatie\Period\PeriodCollection;

$action = new BookResource();

$booking = $action->run(
    periods: PeriodCollection::make([
        Period::make('2024-12-25 09:00', '2024-12-25 17:00')
    ]),
    bookableResource: $resource,
    booker: $user,
    label: 'Conference Room Booking'
);
```

#### Advanced Usage

```php
$booking = $action->run(
    periods: $periods,
    bookableResource: $resource,
    booker: $user,
    creator: auth()->user(),           // Different from booker
    relatable: $project,               // Related model
    code: 'CUSTOM-2024-001',          // Custom booking code
    codePrefix: 'CONF',               // Code prefix
    codeSuffix: 'VIP',                // Code suffix
    label: 'VIP Client Meeting',      // Booking title
    note: 'Requires special setup',   // Additional notes
    meta: [                           // Custom metadata
        'attendees' => 12,
        'equipment' => ['projector', 'microphone'],
        'catering' => true,
        'security_level' => 'high',
    ],
    codeGenerator: \App\Generators\CustomBookingCode::class, // Custom code generator
);
```

#### Updating Existing Bookings

```php
// Update an existing booking
$updatedBooking = $action->run(
    periods: $newPeriods,
    bookableResource: $resource,
    booker: $user,
    booking: $existingBooking,         // Pass existing booking to update
    label: 'Updated Meeting Time',
    meta: ['updated_reason' => 'Schedule conflict resolved']
);
```

#### Error Handling

```php
use Masterix21\Bookings\Exceptions\BookingResourceOverlappingException;
use Masterix21\Bookings\Exceptions\OutOfPlanningsException;
use Masterix21\Bookings\Exceptions\NoFreeSizeException;

try {
    $booking = $action->run(
        periods: $periods,
        bookableResource: $resource,
        booker: $user
    );
} catch (BookingResourceOverlappingException $e) {
    // Handle booking conflicts
    $conflictingBookings = $e->getConflictingBookings();
    return response()->json([
        'error' => 'Time slot unavailable',
        'conflicts' => $conflictingBookings->map(function ($booking) {
            return [
                'code' => $booking->code,
                'label' => $booking->label,
                'periods' => $booking->bookedPeriods->map(function ($period) {
                    return [
                        'starts_at' => $period->starts_at,
                        'ends_at' => $period->ends_at,
                    ];
                }),
            ];
        }),
    ], 409);
} catch (OutOfPlanningsException $e) {
    // Handle planning constraints
    return response()->json([
        'error' => 'Resource not available during requested time',
        'message' => 'Please check the resource availability schedule',
    ], 422);
} catch (NoFreeSizeException $e) {
    // Handle capacity limits
    return response()->json([
        'error' => 'Resource capacity exceeded',
        'message' => 'Maximum capacity reached for the requested time',
    ], 422);
}
```

#### Transaction Handling

The BookResource action automatically handles database transactions:

```php
// All operations are wrapped in a transaction
$booking = $action->run(/* parameters */);

// If any step fails, all changes are rolled back
// Events are only fired on successful completion
```

#### Event Flow

The action fires these events during execution:

1. `BookingInProgress` - When booking starts
2. `BookingCompleted` - On successful creation
3. `BookingFailed` - On failure
4. `BookingChanging` - When updating starts
5. `BookingChanged` - On successful update
6. `BookingChangeFailed` - On update failure

#### Custom Booking Code Generators

You can specify a custom booking code generator for individual bookings, overriding the default configured generator.

**Using a Generator Class:**

```php
use App\Generators\CustomBookingCode;

$booking = $action->run(
    periods: $periods,
    bookableResource: $resource,
    booker: $user,
    codeGenerator: CustomBookingCode::class, // Class string
);
```

**Using a Generator Instance:**

```php
use App\Generators\CustomBookingCode;

$generator = new CustomBookingCode(
    format: 'sequential',
    startNumber: 1000
);

$booking = $action->run(
    periods: $periods,
    bookableResource: $resource,
    booker: $user,
    codeGenerator: $generator, // Instance
);
```

**Default Behavior:**

If no `codeGenerator` is specified, the action uses the generator configured in `config/bookings.php`:

```php
// Uses config('bookings.generators.booking_code')
$booking = $action->run(
    periods: $periods,
    bookableResource: $resource,
    booker: $user,
);
```

This allows you to use different code generation strategies for different types of bookings while maintaining a default for standard bookings.

### CheckBookingOverlaps

Validates booking conflicts and resource availability.

#### Basic Availability Check

```php
use Masterix21\Bookings\Actions\CheckBookingOverlaps;

$checker = new CheckBookingOverlaps();

$isAvailable = $checker->run(
    periods: $periods,
    bookableResource: $resource,
    emitEvent: false,    // Don't emit validation events
    throw: false         // Return boolean instead of throwing
);

if ($isAvailable) {
    // Proceed with booking
} else {
    // Handle unavailability
}
```

#### Detailed Validation

```php
try {
    $checker->run(
        periods: $periods,
        bookableResource: $resource,
        emitEvent: true,     // Emit validation events
        throw: true          // Throw exceptions on conflicts
    );
    
    // If we reach here, the booking is valid
    echo "Booking periods are available";
    
} catch (BookingResourceOverlappingException $e) {
    $conflicts = $e->getConflictingBookings();
    echo "Found {$conflicts->count()} conflicting bookings";
}
```

#### Ignoring Specific Bookings

When updating bookings, ignore the current booking:

```php
$isAvailable = $checker->run(
    periods: $newPeriods,
    bookableResource: $resource,
    ignoreBooking: $currentBooking,  // Ignore this booking in validation
    throw: false
);
```

#### Batch Availability Checking

```php
$resources = BookableResource::bookable()->get();
$availableResources = [];

foreach ($resources as $resource) {
    $isAvailable = $checker->run(
        periods: $periods,
        bookableResource: $resource,
        emitEvent: false,
        throw: false
    );
    
    if ($isAvailable) {
        $availableResources[] = $resource;
    }
}
```

#### Performance Notes

The `CheckBookingOverlaps` action is optimized for multiple periods:

- **Single Query**: Uses one database query regardless of the number of periods
- **Efficient Counting**: Leverages database aggregation with `SUM` and `CASE` statements
- **Scalable**: Performance remains consistent whether checking 1 period or 100 periods
- **Resource-Aware**: Properly considers the `max` parameter of each BookableResource

This makes it ideal for complex booking scenarios like recurring appointments or multi-day reservations.

## Custom Actions

### Creating Custom Actions

You can create custom actions for specific business logic:

```php
<?php
// app/Actions/BookRoomWithSetup.php

namespace App\Actions;

use App\Models\Room;
use App\Models\Equipment;
use Masterix21\Bookings\Actions\BookResource;
use Masterix21\Bookings\Models\Booking;
use Spatie\Period\PeriodCollection;
use Illuminate\Support\Facades\DB;

class BookRoomWithSetup
{
    public function __construct(
        private BookResource $bookResource
    ) {}

    public function run(
        Room $room,
        PeriodCollection $periods,
        $booker,
        array $equipmentIds = [],
        array $metadata = []
    ): Booking {
        return DB::transaction(function () use ($room, $periods, $booker, $equipmentIds, $metadata) {
            // Create the main booking
            $booking = $this->bookResource->run(
                periods: $periods,
                bookableResource: $room->bookableResource,
                booker: $booker,
                meta: array_merge($metadata, [
                    'equipment_requested' => $equipmentIds,
                    'setup_required' => true,
                ])
            );

            // Book required equipment
            foreach ($equipmentIds as $equipmentId) {
                $equipment = Equipment::find($equipmentId);
                if ($equipment && $equipment->bookableResource) {
                    $this->bookResource->run(
                        periods: $periods,
                        bookableResource: $equipment->bookableResource,
                        booker: $booker,
                        relatable: $booking,
                        label: "Equipment for {$booking->label}",
                        meta: ['parent_booking_id' => $booking->id]
                    );
                }
            }

            // Schedule setup tasks
            $this->scheduleSetupTasks($booking, $equipmentIds);

            return $booking;
        });
    }

    private function scheduleSetupTasks(Booking $booking, array $equipmentIds): void
    {
        // Schedule setup 30 minutes before booking starts
        $setupTime = $booking->bookedPeriods->min('starts_at')->subMinutes(30);
        
        // Create setup job
        ScheduleRoomSetupJob::dispatch($booking, $equipmentIds)
            ->delay($setupTime);
    }
}
```

### Service Layer Integration

Integrate actions into service classes:

```php
<?php
// app/Services/BookingService.php

namespace App\Services;

use Masterix21\Bookings\Actions\BookResource;
use Masterix21\Bookings\Actions\CheckBookingOverlaps;
use App\Actions\BookRoomWithSetup;
use App\Models\User;
use App\Models\Room;
use Carbon\Carbon;
use Spatie\Period\Period;
use Spatie\Period\PeriodCollection;

class BookingService
{
    public function __construct(
        private BookResource $bookResource,
        private CheckBookingOverlaps $checkOverlaps,
        private BookRoomWithSetup $bookRoomWithSetup
    ) {}

    public function findAvailableRooms(
        Carbon $startTime,
        Carbon $endTime,
        int $capacity = 1
    ): Collection {
        $periods = PeriodCollection::make([
            Period::make($startTime, $endTime)
        ]);

        return Room::whereHas('bookableResource', function ($query) {
            $query->where('is_bookable', true)
                  ->where('is_visible', true);
        })
        ->where('capacity', '>=', $capacity)
        ->get()
        ->filter(function ($room) use ($periods) {
            return $this->checkOverlaps->run(
                periods: $periods,
                bookableResource: $room->bookableResource,
                emitEvent: false,
                throw: false
            );
        });
    }

    public function bookRoom(
        Room $room,
        User $user,
        Carbon $startTime,
        Carbon $endTime,
        array $options = []
    ): Booking {
        $periods = PeriodCollection::make([
            Period::make($startTime, $endTime)
        ]);

        if ($options['equipment'] ?? false) {
            return $this->bookRoomWithSetup->run(
                room: $room,
                periods: $periods,
                booker: $user,
                equipmentIds: $options['equipment'],
                metadata: $options
            );
        }

        return $this->bookResource->run(
            periods: $periods,
            bookableResource: $room->bookableResource,
            booker: $user,
            label: $options['label'] ?? 'Room Booking',
            note: $options['note'] ?? null,
            meta: $options
        );
    }

    public function rescheduleBooking(
        Booking $booking,
        Carbon $newStartTime,
        Carbon $newEndTime,
        string $reason = null
    ): Booking {
        $newPeriods = PeriodCollection::make([
            Period::make($newStartTime, $newEndTime)
        ]);

        // Get the resource from the booking
        $resource = $booking->bookedPeriods->first()->bookableResource;

        return $this->bookResource->run(
            periods: $newPeriods,
            bookableResource: $resource,
            booker: $booking->booker,
            booking: $booking,
            label: $booking->label . ' (Rescheduled)',
            note: $reason ? "Rescheduled: {$reason}" : $booking->note,
            meta: array_merge($booking->meta->toArray(), [
                'rescheduled_at' => now()->toISOString(),
                'reschedule_reason' => $reason,
                'original_periods' => $booking->bookedPeriods->map(function ($period) {
                    return [
                        'starts_at' => $period->starts_at,
                        'ends_at' => $period->ends_at,
                    ];
                })->toArray(),
            ])
        );
    }

    public function cancelBooking(Booking $booking, string $reason = null): void
    {
        // Add cancellation metadata
        $booking->update([
            'meta' => $booking->meta->merge([
                'cancelled_at' => now()->toISOString(),
                'cancellation_reason' => $reason,
                'status' => 'cancelled',
            ])
        ]);

        // Remove booked periods to free up the resource
        $booking->bookedPeriods()->delete();

        // Fire custom event
        event(new BookingCancelled($booking, $reason));
    }
}
```

## Action Patterns

### Validation Actions

Create actions specifically for validation:

```php
<?php
// app/Actions/ValidateBookingRequest.php

namespace App\Actions;

use App\Models\User;
use Masterix21\Bookings\Models\BookableResource;
use Spatie\Period\PeriodCollection;
use Illuminate\Validation\ValidationException;

class ValidateBookingRequest
{
    public function run(
        PeriodCollection $periods,
        BookableResource $resource,
        User $user,
        array $metadata = []
    ): void {
        $errors = [];

        // Validate user permissions
        if (!$user->can('book-resources')) {
            $errors[] = 'User does not have permission to make bookings';
        }

        // Validate booking advance time
        $firstPeriod = $periods->first();
        if ($firstPeriod->start()->diffInDays(now()) > 90) {
            $errors[] = 'Cannot book more than 90 days in advance';
        }

        // Validate minimum booking duration
        $totalDuration = $periods->reduce(function ($carry, $period) {
            return $carry + $period->length()->totalMinutes();
        }, 0);

        if ($totalDuration < 60) {
            $errors[] = 'Minimum booking duration is 1 hour';
        }

        // Validate business hours
        foreach ($periods as $period) {
            if ($period->start()->hour < 8 || $period->end()->hour > 20) {
                $errors[] = 'Bookings must be within business hours (8 AM - 8 PM)';
                break;
            }
        }

        // Resource-specific validation
        $this->validateResourceSpecificRules($resource, $periods, $metadata, $errors);

        if (!empty($errors)) {
            throw ValidationException::withMessages(['booking' => $errors]);
        }
    }

    private function validateResourceSpecificRules(
        BookableResource $resource,
        PeriodCollection $periods,
        array $metadata,
        array &$errors
    ): void {
        $resourceModel = $resource->resource;

        // Room-specific validation
        if ($resourceModel instanceof \App\Models\Room) {
            $attendees = $metadata['attendees'] ?? 1;
            if ($attendees > $resourceModel->capacity) {
                $errors[] = "Room capacity ({$resourceModel->capacity}) exceeded";
            }

            if ($resourceModel->requires_approval && !auth()->user()->can('book-without-approval')) {
                $errors[] = 'This room requires approval for booking';
            }
        }

        // Vehicle-specific validation
        if ($resourceModel instanceof \App\Models\Vehicle) {
            if (!$metadata['driver_license'] ?? false) {
                $errors[] = 'Driver license information required for vehicle booking';
            }
        }
    }
}
```

### Notification Actions

Handle notifications as separate actions:

```php
<?php
// app/Actions/SendBookingNotifications.php

namespace App\Actions;

use Masterix21\Bookings\Models\Booking;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use App\Mail\BookingConfirmationMail;
use App\Notifications\BookingCreatedNotification;

class SendBookingNotifications
{
    public function run(Booking $booking): void
    {
        // Send confirmation email to booker
        if ($booking->booker && $booking->booker->email) {
            Mail::to($booking->booker->email)
                ->send(new BookingConfirmationMail($booking));
        }

        // Notify administrators
        $admins = User::role('admin')->get();
        Notification::send($admins, new BookingCreatedNotification($booking));

        // Send calendar invites
        $this->sendCalendarInvite($booking);

        // Update external systems
        $this->updateExternalSystems($booking);
    }

    private function sendCalendarInvite(Booking $booking): void
    {
        // Generate calendar invite and send
        $calendarService = app(CalendarService::class);
        $calendarService->createEvent($booking);
    }

    private function updateExternalSystems(Booking $booking): void
    {
        // Update CRM, accounting systems, etc.
        dispatch(new UpdateExternalSystemsJob($booking));
    }
}
```

## Testing Actions

### Unit Testing

```php
<?php
// tests/Unit/Actions/BookResourceTest.php

namespace Tests\Unit\Actions;

use Tests\TestCase;
use Masterix21\Bookings\Actions\BookResource;
use Masterix21\Bookings\Models\BookableResource;
use Masterix21\Bookings\Models\Booking;
use App\Models\User;
use App\Models\Room;
use Spatie\Period\Period;
use Spatie\Period\PeriodCollection;

class BookResourceTest extends TestCase
{
    public function test_creates_booking_successfully(): void
    {
        $user = User::factory()->create();
        $room = Room::factory()->create();
        $resource = BookableResource::factory()->create([
            'resource_type' => Room::class,
            'resource_id' => $room->id,
        ]);

        $periods = PeriodCollection::make([
            Period::make('2024-12-25 09:00', '2024-12-25 17:00')
        ]);

        $action = new BookResource();
        $booking = $action->run(
            periods: $periods,
            bookableResource: $resource,
            booker: $user,
            label: 'Test Booking'
        );

        $this->assertInstanceOf(Booking::class, $booking);
        $this->assertEquals($user->id, $booking->booker_id);
        $this->assertEquals('Test Booking', $booking->label);
        $this->assertCount(1, $booking->bookedPeriods);
    }

    public function test_throws_exception_on_overlap(): void
    {
        // Create existing booking
        $existingBooking = $this->createBooking();
        
        // Try to create overlapping booking
        $this->expectException(BookingResourceOverlappingException::class);
        
        $action = new BookResource();
        $action->run(
            periods: $existingBooking->bookedPeriods->first()->period(),
            bookableResource: $existingBooking->bookedPeriods->first()->bookableResource,
            booker: User::factory()->create()
        );
    }
}
```

### Feature Testing

```php
<?php
// tests/Feature/BookingWorkflowTest.php

namespace Tests\Feature;

use Tests\TestCase;
use App\Services\BookingService;
use App\Models\User;
use App\Models\Room;

class BookingWorkflowTest extends TestCase
{
    public function test_complete_booking_workflow(): void
    {
        $user = User::factory()->create();
        $room = Room::factory()->create(['capacity' => 10]);
        
        $service = app(BookingService::class);
        
        // Find available rooms
        $availableRooms = $service->findAvailableRooms(
            startTime: now()->addDay()->setTime(9, 0),
            endTime: now()->addDay()->setTime(17, 0),
            capacity: 8
        );
        
        $this->assertContains($room, $availableRooms);
        
        // Make booking
        $booking = $service->bookRoom(
            room: $room,
            user: $user,
            startTime: now()->addDay()->setTime(9, 0),
            endTime: now()->addDay()->setTime(17, 0),
            options: [
                'label' => 'Team Meeting',
                'attendees' => 8,
            ]
        );
        
        $this->assertEquals('Team Meeting', $booking->label);
        $this->assertEquals(8, $booking->meta['attendees']);
        
        // Reschedule booking
        $rescheduled = $service->rescheduleBooking(
            booking: $booking,
            newStartTime: now()->addDay()->setTime(14, 0),
            newEndTime: now()->addDay()->setTime(18, 0),
            reason: 'Conflict resolved'
        );
        
        $this->assertNotEquals($booking->id, $rescheduled->id);
        $this->assertEquals('Conflict resolved', $rescheduled->meta['reschedule_reason']);
    }
}
```

This guide provides comprehensive coverage of using and extending the Laravel Bookings action system.