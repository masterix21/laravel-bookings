# Extending Laravel Bookings

This guide covers how to extend and customize Laravel Bookings to meet specific business requirements through custom models, actions, events, and integrations.

## Custom Models

### Extending Core Models

You can extend the core models to add custom functionality while maintaining compatibility with the package.

#### Custom Booking Model

```php
<?php
// app/Models/CustomBooking.php

namespace App\Models;

use Masterix21\Bookings\Models\Booking as BaseBooking;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\Department;
use App\Models\ApprovalWorkflow;

class CustomBooking extends BaseBooking
{
    protected $fillable = [
        ...parent::$fillable,
        'department_id',
        'approval_status',
        'approval_notes',
        'billing_code',
    ];

    protected $casts = [
        ...parent::$casts,
        'approval_status' => 'string',
        'requires_approval' => 'boolean',
    ];

    // Custom relationships
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function approvalWorkflow(): BelongsTo
    {
        return $this->belongsTo(ApprovalWorkflow::class);
    }

    // Custom scopes
    public function scopePendingApproval($query)
    {
        return $query->where('approval_status', 'pending');
    }

    public function scopeByDepartment($query, Department $department)
    {
        return $query->where('department_id', $department->id);
    }

    // Custom methods
    public function requiresApproval(): bool
    {
        return $this->meta['requires_approval'] ?? false;
    }

    public function approve(User $approver, string $notes = ''): void
    {
        $this->update([
            'approval_status' => 'approved',
            'approval_notes' => $notes,
            'meta' => array_merge($this->meta ?? [], [
                'approved_by' => $approver->id,
                'approved_at' => now()->toISOString(),
            ]),
        ]);

        // Dispatch custom event
        event(new BookingApproved($this, $approver));
    }

    public function reject(User $approver, string $reason): void
    {
        $this->update([
            'approval_status' => 'rejected',
            'approval_notes' => $reason,
            'meta' => array_merge($this->meta ?? [], [
                'rejected_by' => $approver->id,
                'rejected_at' => now()->toISOString(),
            ]),
        ]);

        event(new BookingRejected($this, $approver, $reason));
    }
}
```

**Register the custom model:**

```php
// config/bookings.php
'models' => [
    'booking' => \App\Models\CustomBooking::class,
    // other models...
],
```

#### Custom BookableResource Model

```php
<?php
// app/Models/CustomBookableResource.php

namespace App\Models;

use Masterix21\Bookings\Models\BookableResource as BaseBookableResource;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\ResourceAttribute;
use App\Models\MaintenanceSchedule;

class CustomBookableResource extends BaseBookableResource
{
    protected $fillable = [
        ...parent::$fillable,
        'location_id',
        'category_id',
        'hourly_rate',
        'maintenance_status',
    ];

    protected $casts = [
        ...parent::$casts,
        'hourly_rate' => 'decimal:2',
        'maintenance_status' => 'string',
        'features' => 'array',
    ];

    // Custom relationships
    public function attributes(): HasMany
    {
        return $this->hasMany(ResourceAttribute::class);
    }

    public function maintenanceSchedules(): HasMany
    {
        return $this->hasMany(MaintenanceSchedule::class);
    }

    // Custom scopes
    public function scopeByCategory($query, $categoryId)
    {
        return $query->where('category_id', $categoryId);
    }

    public function scopeInMaintenance($query)
    {
        return $query->where('maintenance_status', 'in_maintenance');
    }

    public function scopeByFeatures($query, array $features)
    {
        return $query->whereJsonContains('features', $features);
    }

    // Custom methods
    public function calculateCost(int $durationMinutes): float
    {
        $hours = $durationMinutes / 60;
        return $hours * $this->hourly_rate;
    }

    public function isUnderMaintenance(): bool
    {
        return $this->maintenance_status === 'in_maintenance';
    }

    public function hasFeature(string $feature): bool
    {
        return in_array($feature, $this->features ?? []);
    }

    public function getAvailabilityScore(Carbon $date): float
    {
        // Custom availability scoring logic
        $dayBookings = $this->bookedPeriods()
            ->whereDate('starts_at', $date)
            ->count();
            
        $maxBookings = $this->max * 8; // 8 hours per day
        
        return max(0, 1 - ($dayBookings / $maxBookings));
    }
}
```

### Creating Composite Models

For complex scenarios, create models that combine multiple bookable resources:

```php
<?php
// app/Models/BookingPackage.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Masterix21\Bookings\Models\BookableResource;
use Masterix21\Bookings\Models\Booking;

class BookingPackage extends Model
{
    protected $fillable = [
        'name',
        'description',
        'base_price',
        'is_active',
        'requires_all_resources',
    ];

    protected $casts = [
        'base_price' => 'decimal:2',
        'is_active' => 'boolean',
        'requires_all_resources' => 'boolean',
    ];

    public function resources(): BelongsToMany
    {
        return $this->belongsToMany(BookableResource::class, 'package_resources')
                    ->withPivot(['quantity', 'is_required', 'discount_percentage'])
                    ->withTimestamps();
    }

    public function bookings(): BelongsToMany
    {
        return $this->belongsToMany(Booking::class, 'package_bookings')
                    ->withTimestamps();
    }

    public function calculatePrice(int $durationMinutes): float
    {
        $basePrice = $this->base_price;
        
        // Add resource costs with package discounts
        $resourceCost = $this->resources->sum(function ($resource) use ($durationMinutes) {
            $cost = $resource->calculateCost($durationMinutes);
            $discount = $resource->pivot->discount_percentage / 100;
            return $cost * (1 - $discount) * $resource->pivot->quantity;
        });

        return $basePrice + $resourceCost;
    }

    public function checkAvailability(PeriodCollection $periods): bool
    {
        foreach ($this->resources as $resource) {
            $quantity = $resource->pivot->quantity;
            $isRequired = $resource->pivot->is_required;
            
            $available = $this->checkResourceAvailability($resource, $periods, $quantity);
            
            if ($isRequired && !$available) {
                return false;
            }
            
            if ($this->requires_all_resources && !$available) {
                return false;
            }
        }

        return true;
    }
}
```

## Custom Actions

### Extending Core Actions

Create custom actions by extending the base classes:

```php
<?php
// app/Actions/CustomBookResource.php

namespace App\Actions;

use Masterix21\Bookings\Actions\BookResource;
use Masterix21\Bookings\Models\Booking;
use Masterix21\Bookings\Models\BookableResource;
use Spatie\Period\PeriodCollection;
use App\Events\BookingApprovalRequired;
use App\Services\BillingService;
use App\Services\NotificationService;

class CustomBookResource extends BookResource
{
    public function __construct(
        private BillingService $billingService,
        private NotificationService $notificationService
    ) {}

    protected function beforeExecution(
        PeriodCollection $periods,
        BookableResource $bookableResource,
        $booker,
        array $options = []
    ): void {
        // Custom pre-booking validation
        $this->validateBusinessRules($periods, $bookableResource, $booker, $options);
        
        // Check department budget
        if (isset($options['department_id'])) {
            $this->validateDepartmentBudget($options['department_id'], $periods, $bookableResource);
        }
    }

    protected function afterExecution(Booking $booking): void
    {
        parent::afterExecution($booking);
        
        // Calculate and store billing information
        $this->processBilling($booking);
        
        // Send custom notifications
        $this->sendCustomNotifications($booking);
        
        // Check if approval is required
        if ($this->requiresApproval($booking)) {
            event(new BookingApprovalRequired($booking));
        }
    }

    private function validateBusinessRules(
        PeriodCollection $periods,
        BookableResource $bookableResource,
        $booker,
        array $options
    ): void {
        // Custom business logic validation
        if ($bookableResource->category === 'executive' && !$booker->hasRole('manager')) {
            throw new UnauthorizedBookingException('Executive resources require manager role');
        }

        // Check advance booking limits
        $advanceTime = $periods->first()->start()->diffInHours(now());
        if ($advanceTime > 24 * 30) { // 30 days
            throw new BookingTooEarlyException('Cannot book more than 30 days in advance');
        }
    }

    private function validateDepartmentBudget(int $departmentId, PeriodCollection $periods, BookableResource $resource): void
    {
        $cost = $this->calculateBookingCost($periods, $resource);
        $department = Department::find($departmentId);
        
        if ($department->remaining_budget < $cost) {
            throw new InsufficientBudgetException('Department budget exceeded');
        }
    }

    private function processBilling(Booking $booking): void
    {
        $totalCost = $this->billingService->calculateBookingCost($booking);
        
        $booking->update([
            'meta' => array_merge($booking->meta ?? [], [
                'billing' => [
                    'total_cost' => $totalCost,
                    'currency' => 'USD',
                    'calculated_at' => now()->toISOString(),
                ],
            ]),
        ]);
    }

    private function requiresApproval(Booking $booking): bool
    {
        // Custom approval logic
        $cost = $booking->meta['billing']['total_cost'] ?? 0;
        return $cost > 500 || $booking->department->requires_approval;
    }
}
```

### Creating New Actions

Build entirely new actions for specific business logic:

```php
<?php
// app/Actions/BulkBookResource.php

namespace App\Actions;

use Illuminate\Support\Facades\DB;
use Masterix21\Bookings\Models\BookableResource;
use Masterix21\Bookings\Actions\BookResource;
use Masterix21\Bookings\Generators\Contracts\BookingCodeGenerator;
use Spatie\Period\PeriodCollection;
use App\Events\BulkBookingCompleted;

class BulkBookResource
{
    public function __construct(
        private BookResource $bookResource
    ) {}

    public function run(
        PeriodCollection $periods,
        array $bookableResourceIds,
        $booker,
        string $label = '',
        string $note = '',
        array $meta = [],
        string|BookingCodeGenerator|null $codeGenerator = null,
    ): array {
        $bookings = [];
        $failed = [];

        DB::transaction(function () use (
            $periods, $bookableResourceIds, $booker, $label, $note, $meta, $codeGenerator,
            &$bookings, &$failed
        ) {
            foreach ($bookableResourceIds as $resourceId) {
                try {
                    $resource = BookableResource::findOrFail($resourceId);

                    $booking = $this->bookResource->run(
                        periods: $periods,
                        bookableResource: $resource,
                        booker: $booker,
                        label: $label,
                        note: $note,
                        meta: array_merge($meta, ['bulk_booking' => true]),
                        codeGenerator: $codeGenerator, // Pass custom generator to all bookings
                    );

                    $bookings[] = $booking;
                } catch (\Exception $e) {
                    $failed[] = [
                        'resource_id' => $resourceId,
                        'error' => $e->getMessage(),
                    ];
                }
            }

            // If any bookings failed and strict mode is enabled, rollback all
            if (!empty($failed) && ($meta['strict_mode'] ?? false)) {
                throw new BulkBookingException('Some bookings failed in strict mode', $failed);
            }
        });

        event(new BulkBookingCompleted($bookings, $failed, $booker));

        return [
            'successful' => $bookings,
            'failed' => $failed,
        ];
    }
}
```

Use the bulk booking action with custom code generator:

```php
use App\Actions\BulkBookResource;
use App\Generators\BulkBookingCodeGenerator;

$bulkBooker = new BulkBookResource(new BookResource());

$result = $bulkBooker->run(
    periods: $periods,
    bookableResourceIds: [1, 2, 3, 4, 5],
    booker: $user,
    label: 'Team Training Session',
    codeGenerator: BulkBookingCodeGenerator::class, // All bookings use same generator
);

// All successful bookings will have codes generated by BulkBookingCodeGenerator
foreach ($result['successful'] as $booking) {
    echo "Created booking: {$booking->code}\n";
}
```

### Recurring Booking Action

```php
<?php
// app/Actions/CreateRecurringBooking.php

namespace App\Actions;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Masterix21\Bookings\Actions\BookResource;
use Masterix21\Bookings\Models\BookableResource;
use Spatie\Period\Period;
use Spatie\Period\PeriodCollection;
use App\Models\RecurringBookingRule;

class CreateRecurringBooking
{
    public function __construct(
        private BookResource $bookResource
    ) {}

    public function run(
        Period $basePeriod,
        BookableResource $resource,
        $booker,
        RecurringBookingRule $rule,
        Carbon $until,
        array $options = []
    ): Collection {
        $bookings = collect();
        $currentDate = $basePeriod->start()->copy();

        while ($currentDate->lte($until)) {
            if ($this->shouldCreateBooking($currentDate, $rule)) {
                try {
                    $periods = $this->createPeriodsForDate($currentDate, $basePeriod, $rule);
                    
                    $booking = $this->bookResource->run(
                        periods: $periods,
                        bookableResource: $resource,
                        booker: $booker,
                        label: $options['label'] ?? "Recurring: {$rule->name}",
                        note: $options['note'] ?? '',
                        meta: array_merge($options['meta'] ?? [], [
                            'recurring_rule_id' => $rule->id,
                            'recurring_instance' => true,
                        ])
                    );
                    
                    $bookings->push($booking);
                } catch (\Exception $e) {
                    // Log failed booking but continue with others
                    logger()->warning('Failed to create recurring booking', [
                        'date' => $currentDate->toDateString(),
                        'rule_id' => $rule->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            $currentDate = $this->getNextDate($currentDate, $rule);
        }

        return $bookings;
    }

    private function shouldCreateBooking(Carbon $date, RecurringBookingRule $rule): bool
    {
        // Check if date falls on correct day of week/month
        return match ($rule->frequency) {
            'daily' => true,
            'weekly' => in_array($date->dayOfWeek, $rule->days_of_week),
            'monthly' => $date->day === $rule->day_of_month,
            default => false,
        };
    }

    private function createPeriodsForDate(Carbon $date, Period $basePeriod, RecurringBookingRule $rule): PeriodCollection
    {
        $duration = $basePeriod->length();
        $startTime = $basePeriod->start()->format('H:i:s');
        
        $start = $date->copy()->setTimeFromTimeString($startTime);
        $end = $start->copy()->add($duration);

        return PeriodCollection::make([
            Period::make($start, $end)
        ]);
    }
}
```

## Custom Events and Listeners

### Creating Custom Events

```php
<?php
// app/Events/BookingApprovalRequired.php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use App\Models\CustomBooking;

class BookingApprovalRequired
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public CustomBooking $booking
    ) {}
}
```

```php
<?php
// app/Events/ResourceMaintenanceScheduled.php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use App\Models\CustomBookableResource;
use Carbon\Carbon;

class ResourceMaintenanceScheduled
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public CustomBookableResource $resource,
        public Carbon $scheduledDate,
        public string $maintenanceType,
        public array $affectedBookings = []
    ) {}
}
```

### Custom Event Listeners

```php
<?php
// app/Listeners/ProcessApprovalWorkflow.php

namespace App\Listeners;

use App\Events\BookingApprovalRequired;
use App\Services\ApprovalWorkflowService;
use App\Notifications\BookingApprovalRequest;

class ProcessApprovalWorkflow
{
    public function __construct(
        private ApprovalWorkflowService $workflowService
    ) {}

    public function handle(BookingApprovalRequired $event): void
    {
        $booking = $event->booking;
        
        // Find appropriate approvers
        $approvers = $this->workflowService->getApproversFor($booking);
        
        // Create approval workflow
        $workflow = $this->workflowService->createWorkflow($booking, $approvers);
        
        // Send notifications to approvers
        foreach ($approvers as $approver) {
            $approver->notify(new BookingApprovalRequest($booking, $workflow));
        }
        
        // Update booking status
        $booking->update([
            'approval_status' => 'pending',
            'meta' => array_merge($booking->meta ?? [], [
                'workflow_id' => $workflow->id,
                'approval_requested_at' => now()->toISOString(),
            ]),
        ]);
    }
}
```

```php
<?php
// app/Listeners/HandleResourceMaintenance.php

namespace App\Listeners;

use App\Events\ResourceMaintenanceScheduled;
use App\Services\BookingService;
use App\Notifications\MaintenanceNotification;

class HandleResourceMaintenance
{
    public function __construct(
        private BookingService $bookingService
    ) {}

    public function handle(ResourceMaintenanceScheduled $event): void
    {
        $resource = $event->resource;
        
        // Mark resource as under maintenance
        $resource->update(['maintenance_status' => 'scheduled']);
        
        // Handle affected bookings
        foreach ($event->affectedBookings as $booking) {
            $this->handleAffectedBooking($booking, $event);
        }
        
        // Send notifications to stakeholders
        $this->notifyStakeholders($event);
    }

    private function handleAffectedBooking($booking, ResourceMaintenanceScheduled $event): void
    {
        // Offer rebooking options
        $alternatives = $this->bookingService->findAlternativeResources(
            $booking->bookedPeriods->first()->starts_at,
            $booking->bookedPeriods->first()->ends_at,
            $event->resource->resource_type
        );
        
        // Notify booking owner
        $booking->booker->notify(new MaintenanceNotification($booking, $event, $alternatives));
    }
}
```

## Custom Validation Rules

### Resource-Specific Validation

```php
<?php
// app/Rules/ValidResourceCapacity.php

namespace App\Rules;

use Illuminate\Contracts\Validation\Rule;
use Masterix21\Bookings\Models\BookableResource;

class ValidResourceCapacity implements Rule
{
    public function __construct(
        private BookableResource $resource,
        private int $requestedSize
    ) {}

    public function passes($attribute, $value): bool
    {
        // Check if requested size exceeds resource capacity
        if ($this->requestedSize > $this->resource->size) {
            return false;
        }

        // Check if resource has enough concurrent availability
        return $this->requestedSize <= $this->resource->max;
    }

    public function message(): string
    {
        return 'The requested capacity exceeds the resource limits.';
    }
}
```

### Time-Based Validation

```php
<?php
// app/Rules/ValidBookingWindow.php

namespace App\Rules;

use Carbon\Carbon;
use Illuminate\Contracts\Validation\Rule;
use Masterix21\Bookings\Models\BookableResource;

class ValidBookingWindow implements Rule
{
    public function __construct(
        private BookableResource $resource,
        private Carbon $requestedStart
    ) {}

    public function passes($attribute, $value): bool
    {
        $planning = $this->resource->bookablePlannings()
            ->where('starts_at', '<=', $this->requestedStart)
            ->where('ends_at', '>=', $this->requestedStart)
            ->first();

        if (!$planning) {
            return false;
        }

        // Check advance booking window
        $minAdvance = $planning->min_time_before_booking ?? 0;
        $maxAdvance = $planning->max_time_before_booking ?? PHP_INT_MAX;
        
        $advanceMinutes = now()->diffInMinutes($this->requestedStart);
        
        return $advanceMinutes >= $minAdvance && $advanceMinutes <= $maxAdvance;
    }

    public function message(): string
    {
        return 'The booking time is outside the allowed booking window.';
    }
}
```

## Service Integrations

### Payment Processing

```php
<?php
// app/Services/BookingPaymentService.php

namespace App\Services;

use App\Models\CustomBooking;
use Stripe\PaymentIntent;
use Stripe\Stripe;

class BookingPaymentService
{
    public function __construct()
    {
        Stripe::setApiKey(config('services.stripe.secret'));
    }

    public function createPaymentIntent(CustomBooking $booking): PaymentIntent
    {
        $amount = $this->calculateBookingAmount($booking);
        
        return PaymentIntent::create([
            'amount' => $amount * 100, // Convert to cents
            'currency' => 'usd',
            'metadata' => [
                'booking_id' => $booking->id,
                'booking_code' => $booking->code,
            ],
        ]);
    }

    public function processPayment(CustomBooking $booking, string $paymentMethodId): bool
    {
        try {
            $paymentIntent = $this->createPaymentIntent($booking);
            
            $paymentIntent->confirm([
                'payment_method' => $paymentMethodId,
            ]);

            // Update booking with payment information
            $booking->update([
                'meta' => array_merge($booking->meta ?? [], [
                    'payment' => [
                        'intent_id' => $paymentIntent->id,
                        'status' => $paymentIntent->status,
                        'amount' => $paymentIntent->amount / 100,
                        'processed_at' => now()->toISOString(),
                    ],
                ]),
            ]);

            return $paymentIntent->status === 'succeeded';
        } catch (\Exception $e) {
            logger()->error('Payment processing failed', [
                'booking_id' => $booking->id,
                'error' => $e->getMessage(),
            ]);
            
            return false;
        }
    }

    private function calculateBookingAmount(CustomBooking $booking): float
    {
        $baseAmount = $booking->meta['billing']['total_cost'] ?? 0;
        $taxes = $baseAmount * 0.08; // 8% tax
        $processingFee = max(2.50, $baseAmount * 0.029); // Stripe fee

        return $baseAmount + $taxes + $processingFee;
    }
}
```

### External Calendar Integration

```php
<?php
// app/Services/CalendarIntegrationService.php

namespace App\Services;

use App\Models\CustomBooking;
use Google\Client;
use Google\Service\Calendar;
use Google\Service\Calendar\Event;

class CalendarIntegrationService
{
    private Client $client;
    private Calendar $service;

    public function __construct()
    {
        $this->client = new Client();
        $this->client->setAuthConfig(config('services.google.credentials_path'));
        $this->client->addScope(Calendar::CALENDAR);
        $this->service = new Calendar($this->client);
    }

    public function createCalendarEvent(CustomBooking $booking): ?string
    {
        try {
            $period = $booking->bookedPeriods->first();
            
            $event = new Event([
                'summary' => $booking->label,
                'description' => $booking->note,
                'start' => [
                    'dateTime' => $period->starts_at->toISOString(),
                    'timeZone' => config('app.timezone'),
                ],
                'end' => [
                    'dateTime' => $period->ends_at->toISOString(),
                    'timeZone' => config('app.timezone'),
                ],
                'attendees' => $this->getAttendees($booking),
                'extendedProperties' => [
                    'private' => [
                        'booking_id' => $booking->id,
                        'booking_code' => $booking->code,
                    ],
                ],
            ]);

            $calendarId = $this->getCalendarIdForResource($booking->bookedPeriods->first()->bookableResource);
            $createdEvent = $this->service->events->insert($calendarId, $event);

            // Store calendar event ID in booking
            $booking->update([
                'meta' => array_merge($booking->meta ?? [], [
                    'calendar_event_id' => $createdEvent->getId(),
                ]),
            ]);

            return $createdEvent->getId();
        } catch (\Exception $e) {
            logger()->error('Calendar event creation failed', [
                'booking_id' => $booking->id,
                'error' => $e->getMessage(),
            ]);
            
            return null;
        }
    }

    private function getAttendees(CustomBooking $booking): array
    {
        $attendees = [];
        
        // Add booker
        if ($booking->booker->email) {
            $attendees[] = ['email' => $booking->booker->email];
        }
        
        // Add additional attendees from metadata
        $additionalAttendees = $booking->meta['attendee_emails'] ?? [];
        foreach ($additionalAttendees as $email) {
            $attendees[] = ['email' => $email];
        }
        
        return $attendees;
    }
}
```

## Testing Extensions

### Custom Model Tests

```php
<?php
// tests/Unit/Models/CustomBookingTest.php

namespace Tests\Unit\Models;

use Tests\TestCase;
use App\Models\CustomBooking;
use App\Models\Department;
use App\Models\User;

class CustomBookingTest extends TestCase
{
    public function test_booking_can_be_approved(): void
    {
        $booking = CustomBooking::factory()->create([
            'approval_status' => 'pending',
        ]);
        
        $approver = User::factory()->create();
        
        $booking->approve($approver, 'Approved for test');
        
        $this->assertEquals('approved', $booking->approval_status);
        $this->assertEquals('Approved for test', $booking->approval_notes);
        $this->assertEquals($approver->id, $booking->meta['approved_by']);
    }

    public function test_booking_requires_approval_based_on_cost(): void
    {
        $booking = CustomBooking::factory()->create([
            'meta' => ['billing' => ['total_cost' => 600]],
        ]);
        
        $this->assertTrue($booking->requiresApproval());
        
        $booking->update([
            'meta' => ['billing' => ['total_cost' => 300]],
        ]);
        
        $this->assertFalse($booking->requiresApproval());
    }
}
```

### Custom Action Tests

```php
<?php
// tests/Unit/Actions/CustomBookResourceTest.php

namespace Tests\Unit\Actions;

use Tests\TestCase;
use App\Actions\CustomBookResource;
use App\Models\CustomBookableResource;
use App\Models\Department;
use App\Models\User;
use App\Exceptions\InsufficientBudgetException;

class CustomBookResourceTest extends TestCase
{
    public function test_validates_department_budget(): void
    {
        $department = Department::factory()->create(['remaining_budget' => 100]);
        $user = User::factory()->create(['department_id' => $department->id]);
        $resource = CustomBookableResource::factory()->create(['hourly_rate' => 50]);
        
        $action = new CustomBookResource();
        
        // This should fail due to insufficient budget
        $this->expectException(InsufficientBudgetException::class);
        
        $action->run(
            periods: $this->createPeriods('2024-01-01 09:00', '2024-01-01 12:00'), // 3 hours = $150
            bookableResource: $resource,
            booker: $user,
            options: ['department_id' => $department->id]
        );
    }
}
```

This extension guide provides a comprehensive foundation for customizing Laravel Bookings to meet specific business requirements while maintaining compatibility with the core package functionality.