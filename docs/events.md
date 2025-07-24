# Events and Listeners

Laravel Bookings provides a comprehensive event system that allows you to hook into the booking lifecycle and implement custom business logic.

## Event Overview

The package fires events at key points during the booking process, allowing you to:

- Send notifications and confirmations
- Update external systems
- Implement audit trails
- Add custom validation logic
- Trigger workflows

## Booking Lifecycle Events

### BookingInProgress

Fired when a booking process starts, before any validation or database changes.

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

**Use Cases:**
- Log booking attempts
- Pre-validation checks
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
            'user_id' => $event->booker?->id,
            'resource_id' => $event->bookableResource->id,
            'periods_count' => $event->periods->count(),
            'is_update' => $event->booking !== null,
        ]);
    }
}
```

### BookingCompleted

Fired when a booking is successfully created or updated.

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

        // Send to resource managers
        $resource = $event->bookableResource->resource;
        if ($resource->manager_email) {
            Mail::to($resource->manager_email)->send(
                new BookingNotificationMail($booking)
            );
        }
    }
}
```

### BookingFailed

Fired when a booking creation or update fails.

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

**Use Cases:**
- Error logging
- Send failure notifications
- Trigger fallback processes
- Analytics and monitoring

**Example Listener:**

```php
<?php
// app/Listeners/HandleBookingFailure.php

namespace App\Listeners;

use Masterix21\Bookings\Events\BookingFailed;
use Illuminate\Support\Facades\Log;
use App\Services\NotificationService;

class HandleBookingFailure
{
    public function __construct(
        private NotificationService $notificationService
    ) {}

    public function handle(BookingFailed $event): void
    {
        // Log the failure
        Log::error('Booking failed', [
            'user_id' => $event->booker?->id,
            'resource_id' => $event->bookableResource->id,
            'exception' => $event->exception->getMessage(),
            'trace' => $event->exception->getTraceAsString(),
        ]);

        // Notify administrators for critical failures
        if ($this->isCriticalFailure($event->exception)) {
            $this->notificationService->notifyAdmins(
                'Critical booking failure',
                $event->exception->getMessage()
            );
        }

        // Send user-friendly notification
        if ($event->booker) {
            $this->notificationService->notifyUser(
                $event->booker,
                'Booking could not be completed',
                $this->getUserFriendlyMessage($event->exception)
            );
        }
    }

    private function isCriticalFailure(\Exception $exception): bool
    {
        return !($exception instanceof BookingResourceOverlappingException);
    }

    private function getUserFriendlyMessage(\Exception $exception): string
    {
        return match (get_class($exception)) {
            BookingResourceOverlappingException::class => 
                'The requested time slot is no longer available.',
            OutOfPlanningsException::class => 
                'The resource is not available during the requested time.',
            NoFreeSizeException::class => 
                'The resource has reached its maximum capacity.',
            default => 'An error occurred while processing your booking.'
        };
    }
}
```

### BookingChanging

Fired when a booking update process starts.

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

### BookingChanged

Fired when a booking is successfully updated.

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

**Example Listener:**

```php
<?php
// app/Listeners/HandleBookingChange.php

namespace App\Listeners;

use Masterix21\Bookings\Events\BookingChanged;
use App\Services\CalendarService;
use App\Mail\BookingChangedMail;
use Illuminate\Support\Facades\Mail;

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
                new BookingChangedMail($booking, $event->oldPeriods, $event->newPeriods)
            );
        }

        // Log the change
        Log::info('Booking updated', [
            'booking_id' => $booking->id,
            'old_periods' => $event->oldPeriods->map(fn($p) => [
                'start' => $p->start(),
                'end' => $p->end(),
            ]),
            'new_periods' => $event->newPeriods->map(fn($p) => [
                'start' => $p->start(),
                'end' => $p->end(),
            ]),
        ]);
    }
}
```

### BookingChangeFailed

Fired when a booking update fails.

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

## Planning Validation Events

### PlanningValidationStarted

Fired when planning validation begins.

```php
use Masterix21\Bookings\Events\PlanningValidationStarted;

class PlanningValidationStarted
{
    public function __construct(
        public BookableResource $bookableResource,
        public PeriodCollection $periods
    ) {}
}
```

### PlanningValidationPassed

Fired when planning validation succeeds.

```php
use Masterix21\Bookings\Events\PlanningValidationPassed;

class PlanningValidationPassed
{
    public function __construct(
        public BookableResource $bookableResource,
        public PeriodCollection $periods
    ) {}
}
```

### PlanningValidationFailed

Fired when planning validation fails.

```php
use Masterix21\Bookings\Events\PlanningValidationFailed;

class PlanningValidationFailed
{
    public function __construct(
        public BookableResource $bookableResource,
        public PeriodCollection $periods,
        public string $reason
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
use App\Listeners\SendBookingConfirmation;
use App\Listeners\HandleBookingFailure;

class EventServiceProvider extends ServiceProvider
{
    protected $listen = [
        BookingCompleted::class => [
            SendBookingConfirmation::class,
            UpdateInventory::class,
            SyncWithExternalCalendar::class,
            GenerateInvoice::class,
        ],
        
        BookingFailed::class => [
            HandleBookingFailure::class,
            NotifyAdministrators::class,
        ],
        
        BookingChanged::class => [
            HandleBookingChange::class,
            UpdateRelatedBookings::class,
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
            cache()->forget("availability:{$event->bookableResource->id}");
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

class SendBookingConfirmation implements ShouldQueue
{
    use InteractsWithQueue;

    public $queue = 'bookings';
    public $delay = 30; // Delay 30 seconds

    public function handle(BookingCompleted $event): void
    {
        // Time-consuming email sending logic
    }

    public function failed(\Exception $exception): void
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

        // Resource-specific processing
        $resource = $event->bookableResource->resource;
        match (get_class($resource)) {
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

Create custom events for your specific business logic:

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
                   $event->periods instanceof PeriodCollection &&
                   $event->bookableResource instanceof BookableResource;
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
        $event = new BookingCompleted($booking, $periods, $resource);

        $listener = new SendBookingConfirmation();
        $listener->handle($event);

        Mail::assertSent(BookingConfirmationMail::class, function ($mail) use ($booking) {
            return $mail->booking->id === $booking->id;
        });
    }
}
```

This comprehensive events guide enables you to build sophisticated booking workflows using Laravel Bookings' event system.