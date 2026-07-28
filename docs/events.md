# Events and Listeners

Laravel Bookings provides a comprehensive event system that allows you to hook into the booking lifecycle and implement custom business logic.

## Event Overview

The package fires events at key points during the booking process, allowing you to:

- Send notifications and confirmations
- Update external systems
- Implement audit trails
- Add custom validation logic
- Trigger workflows

All booking lifecycle events live under `Masterix21\Bookings\Events`. The signatures below are the real constructors — copy them, don't guess at extra properties.

## Booking Lifecycle Events

### BookingInProgress

Fired when a booking process starts, before overlap validation or any database changes. Dispatched from `BookResource::create()` only (not on update).

```php
namespace Masterix21\Bookings\Events;

class BookingInProgress
{
    public function __construct(
        public BookableResource $bookableResource,
        public PeriodCollection $periods,
    ) {}
}
```

**Use Cases:**
- Log booking attempts
- Rate limiting
- User activity tracking

**Example Listener:**

```php
<?php
// app/Listeners/LogBookingAttempt.php

namespace App\Listeners;

use Masterix21\Bookings\Events\BookingInProgress;
use Illuminate\Support\Facades\Log;

class LogBookingAttempt
{
    public function handle(BookingInProgress $event): void
    {
        Log::info('Booking attempt started', [
            'resource_id' => $event->bookableResource->id,
            'periods_count' => $event->periods->count(),
        ]);
    }
}
```

### BookingCompleted

Fired when a new booking is successfully created (after the transaction commits). For updates, see `BookingChanged` below.

```php
namespace Masterix21\Bookings\Events;

class BookingCompleted
{
    public function __construct(
        public Booking $booking,
        public PeriodCollection $periods,
    ) {}
}
```

There is no `$bookableResource` property on this event — resolve the resource from the booking's periods if you need it: `$event->booking->bookedPeriods->first()?->bookableResource`.

**Use Cases:**
- Send confirmation emails
- Update calendars
- Sync with external systems
- Generate invoices
- Send notifications

**Example Listener:**

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

### BookingFailed

Fired when creating a new booking fails — either an overlap was detected (`emitEvent: true` on `CheckBookingOverlaps`) or any `\Throwable` was raised and the transaction was rolled back. Also fired when an *update* fails after the booking's `save()` call has already happened during creation (see [Actions → Transaction Handling](actions.md#transaction-handling)).

```php
namespace Masterix21\Bookings\Events;

use Masterix21\Bookings\Enums\UnbookableReason;

class BookingFailed
{
    public function __construct(
        public UnbookableReason $reason,
        public BookableResource $bookableResource,
        public PeriodCollection $periods,
        public ?string $message = null,
        public ?string $stackTrace = null,
    ) {}
}
```

There is no `$booking`, `$booker`, or `$exception` property — use `$event->reason` (an `UnbookableReason` enum: `PERIOD_OVERLAP` or `EXCEPTION`) and `$event->message`/`$event->stackTrace` instead.

**Use Cases:**
- Error logging
- Send failure notifications
- Analytics and monitoring

**Example Listener:**

```php
<?php
// app/Listeners/HandleBookingFailure.php

namespace App\Listeners;

use Masterix21\Bookings\Enums\UnbookableReason;
use Masterix21\Bookings\Events\BookingFailed;
use Illuminate\Support\Facades\Log;

class HandleBookingFailure
{
    public function handle(BookingFailed $event): void
    {
        Log::error('Booking failed', [
            'resource_id' => $event->bookableResource->id,
            'reason' => $event->reason->value,
            'message' => $event->message,
        ]);

        if ($event->reason === UnbookableReason::EXCEPTION) {
            // Notify administrators for unexpected failures
        }
    }
}
```

### BookingChanging

Fired when an update to an existing booking starts, before overlap validation.

```php
namespace Masterix21\Bookings\Events;

class BookingChanging
{
    public function __construct(
        public Booking $booking,
        public BookableResource $bookableResource,
        public PeriodCollection $periods,
    ) {}
}
```

`$periods` is the new set of periods being applied — the event does not carry the previous periods. Read `$event->booking->bookedPeriods` *before* dispatching your own logic if you need the old state.

### BookingChanged

Fired after an existing booking is successfully updated (after the transaction commits).

```php
namespace Masterix21\Bookings\Events;

class BookingChanged
{
    public function __construct(
        public Booking $booking,
        public PeriodCollection $periods,
    ) {}
}
```

**Example Listener:**

```php
<?php
// app/Listeners/HandleBookingChange.php

namespace App\Listeners;

use Masterix21\Bookings\Events\BookingChanged;
use App\Services\CalendarService;
use App\Mail\BookingChangedMail;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class HandleBookingChange
{
    public function __construct(
        private CalendarService $calendarService
    ) {}

    public function handle(BookingChanged $event): void
    {
        $booking = $event->booking;

        // Update calendar events
        $this->calendarService->updateEvent($booking);

        // Send change notification
        if ($booking->booker && $booking->booker->email) {
            Mail::to($booking->booker->email)->send(
                new BookingChangedMail($booking, $event->periods)
            );
        }

        Log::info('Booking updated', [
            'booking_id' => $booking->id,
            'periods' => $event->periods->map(fn ($p) => [
                'start' => $p->start(),
                'end' => $p->end(),
            ]),
        ]);
    }
}
```

### BookingChangeFailed

Fired when updating an existing booking fails.

```php
namespace Masterix21\Bookings\Events;

use Masterix21\Bookings\Enums\UnbookableReason;

class BookingChangeFailed
{
    public function __construct(
        public Booking $booking,
        public UnbookableReason $reason,
        public BookableResource $bookableResource,
        public PeriodCollection $periods,
        public ?string $message = null,
        public ?string $stackTrace = null,
    ) {}
}
```

## Planning Validation Events

These are dispatched by `UsesBookablePlannings::validatePlanningAvailability()` (used internally by `ensureIsAvailable()`), not by `BookResource`.

### PlanningValidationStarted

Fired when planning validation begins.

```php
namespace Masterix21\Bookings\Events;

use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class PlanningValidationStarted
{
    public function __construct(
        public readonly Model $resource,
        public readonly Collection $dates,
        public readonly Collection|EloquentCollection|null $relations = null,
    ) {}
}
```

### PlanningValidationPassed

Fired when planning validation succeeds. Same constructor shape as `PlanningValidationStarted`.

```php
namespace Masterix21\Bookings\Events;

class PlanningValidationPassed
{
    public function __construct(
        public readonly Model $resource,
        public readonly Collection $dates,
        public readonly Collection|EloquentCollection|null $relations = null,
    ) {}
}
```

### PlanningValidationFailed

Fired when planning validation fails. Carries the exception that was raised (`OutOfPlanningsException` or `RelationsOutOfPlanningsException`).

```php
namespace Masterix21\Bookings\Events;

use Exception;

class PlanningValidationFailed
{
    public function __construct(
        public readonly Model $resource,
        public readonly Collection $dates,
        public readonly Collection|EloquentCollection|null $relations,
        public readonly Exception $exception,
    ) {}
}
```

## Registering Event Listeners

### Using Event Service Provider

```php
<?php
// app/Providers/EventServiceProvider.php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Masterix21\Bookings\Events\BookingCompleted;
use Masterix21\Bookings\Events\BookingFailed;
use Masterix21\Bookings\Events\BookingChanged;
use App\Listeners\SendBookingConfirmation;
use App\Listeners\HandleBookingFailure;
use App\Listeners\HandleBookingChange;

class EventServiceProvider extends ServiceProvider
{
    protected $listen = [
        BookingCompleted::class => [
            SendBookingConfirmation::class,
        ],

        BookingFailed::class => [
            HandleBookingFailure::class,
        ],

        BookingChanged::class => [
            HandleBookingChange::class,
        ],
    ];
}
```

### Using Closure Listeners

```php
<?php
// app/Providers/AppServiceProvider.php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Masterix21\Bookings\Events\BookingCompleted;
use Illuminate\Support\Facades\Event;

class AppServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Event::listen(BookingCompleted::class, function ($event) {
            // Quick inline logic
            cache()->forget("availability:{$event->booking->id}");
        });
    }
}
```

## Advanced Event Patterns

### Event Queuing

For time-consuming operations, queue event listeners:

```php
<?php
// app/Listeners/SendBookingConfirmation.php

namespace App\Listeners;

use Masterix21\Bookings\Events\BookingCompleted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class SendBookingConfirmation implements ShouldQueue
{
    use InteractsWithQueue;

    public $queue = 'bookings';
    public $delay = 30; // Delay 30 seconds

    public function handle(BookingCompleted $event): void
    {
        // Time-consuming email sending logic
    }

    public function failed(\Throwable $exception): void
    {
        // Handle failed job
        Log::error('Failed to send booking confirmation', [
            'exception' => $exception->getMessage(),
        ]);
    }
}
```

### Conditional Event Processing

```php
<?php
// app/Listeners/ConditionalBookingProcessor.php

namespace App\Listeners;

use Masterix21\Bookings\Events\BookingCompleted;

class ConditionalBookingProcessor
{
    public function handle(BookingCompleted $event): void
    {
        $booking = $event->booking;

        // Only process VIP bookings
        if ($booking->meta['vip'] ?? false) {
            $this->processVipBooking($booking);
        }

        // Only process bookings over certain value
        if (($booking->meta['total_amount'] ?? 0) > 1000) {
            $this->processHighValueBooking($booking);
        }

        // Resource-specific processing, resolved from the booked periods
        $resource = $booking->bookedPeriods->first()?->bookableResource?->resource;

        match ($resource ? get_class($resource) : null) {
            \App\Models\Room::class => $this->processRoomBooking($booking),
            \App\Models\Vehicle::class => $this->processVehicleBooking($booking),
            default => null,
        };
    }

    private function processVipBooking(Booking $booking): void
    {
        // VIP-specific logic
    }

    private function processHighValueBooking(Booking $booking): void
    {
        // High-value booking logic
    }

    private function processRoomBooking(Booking $booking): void
    {
        // Room-specific logic
    }

    private function processVehicleBooking(Booking $booking): void
    {
        // Vehicle-specific logic
    }
}
```

### Event Aggregation

Create aggregate events for complex workflows:

```php
<?php
// app/Events/BookingWorkflowCompleted.php

namespace App\Events;

use Masterix21\Bookings\Models\Booking;
use Illuminate\Foundation\Events\Dispatchable;

class BookingWorkflowCompleted
{
    use Dispatchable;

    public function __construct(
        public Booking $booking,
        public array $completedSteps,
        public ?string $workflowType = null
    ) {}
}
```

```php
<?php
// app/Listeners/CompleteBookingWorkflow.php

namespace App\Listeners;

use Masterix21\Bookings\Events\BookingCompleted;
use App\Events\BookingWorkflowCompleted;
use App\Services\BookingWorkflowService;

class CompleteBookingWorkflow
{
    public function __construct(
        private BookingWorkflowService $workflowService
    ) {}

    public function handle(BookingCompleted $event): void
    {
        $booking = $event->booking;
        $completedSteps = [];

        // Step 1: Send confirmation
        $this->workflowService->sendConfirmation($booking);
        $completedSteps[] = 'confirmation_sent';

        // Step 2: Update inventory
        $this->workflowService->updateInventory($booking);
        $completedSteps[] = 'inventory_updated';

        // Step 3: Schedule reminders
        $this->workflowService->scheduleReminders($booking);
        $completedSteps[] = 'reminders_scheduled';

        // Fire aggregate event
        event(new BookingWorkflowCompleted($booking, $completedSteps, 'standard'));
    }
}
```

## Custom Events

The events above are dispatched by the package itself. Nothing stops you from defining and dispatching your own application-level events from listeners or `onBookingSaving()`/`onBookingSaved()` callbacks — these are ordinary Laravel events, unrelated to the package's own classes:

```php
<?php
// app/Events/BookingCancelled.php

namespace App\Events;

use Masterix21\Bookings\Models\Booking;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class BookingCancelled
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Booking $booking,
        public ?string $reason = null,
        public ?string $cancelledBy = null
    ) {}
}
```

```php
<?php
// app/Events/ResourceMaintenanceScheduled.php

namespace App\Events;

use Masterix21\Bookings\Models\BookableResource;
use Carbon\Carbon;
use Illuminate\Foundation\Events\Dispatchable;

class ResourceMaintenanceScheduled
{
    use Dispatchable;

    public function __construct(
        public BookableResource $resource,
        public Carbon $maintenanceStart,
        public Carbon $maintenanceEnd,
        public string $maintenanceType
    ) {}
}
```

## Event Testing

### Testing Event Fire

```php
<?php
// tests/Feature/BookingEventsTest.php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Support\Facades\Event;
use Masterix21\Bookings\Events\BookingCompleted;
use Masterix21\Bookings\Actions\BookResource;

class BookingEventsTest extends TestCase
{
    public function test_booking_completed_event_is_fired(): void
    {
        Event::fake([BookingCompleted::class]);

        $booking = $this->createBooking();

        Event::assertDispatched(BookingCompleted::class, function ($event) use ($booking) {
            return $event->booking->id === $booking->id;
        });
    }

    public function test_booking_completed_event_has_correct_data(): void
    {
        Event::fake();

        $booking = $this->createBooking();

        Event::assertDispatched(BookingCompleted::class, function ($event) {
            return $event->booking instanceof Booking &&
                   $event->periods instanceof PeriodCollection;
        });
    }
}
```

### Testing Event Listeners

```php
<?php
// tests/Unit/Listeners/SendBookingConfirmationTest.php

namespace Tests\Unit\Listeners;

use Tests\TestCase;
use Illuminate\Support\Facades\Mail;
use Masterix21\Bookings\Events\BookingCompleted;
use App\Listeners\SendBookingConfirmation;
use App\Mail\BookingConfirmationMail;

class SendBookingConfirmationTest extends TestCase
{
    public function test_sends_confirmation_email(): void
    {
        Mail::fake();

        $booking = $this->createBooking();
        $event = new BookingCompleted($booking, $periods);

        $listener = new SendBookingConfirmation();
        $listener->handle($event);

        Mail::assertSent(BookingConfirmationMail::class, function ($mail) use ($booking) {
            return $mail->booking->id === $booking->id;
        });
    }
}
```

This comprehensive events guide enables you to build sophisticated booking workflows using Laravel Bookings' event system.
