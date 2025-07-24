# Service Appointments System Example

This example demonstrates how to build a comprehensive service appointments system using Laravel Bookings, including service provider management, recurring appointments, staff scheduling, and multi-location operations.

## Overview

This example covers:
- Service provider and staff management
- Service catalog with duration and pricing
- Client booking with recurring appointments
- Staff scheduling and availability
- Multi-location service centers
- Appointment reminders and notifications
- Payment integration and invoicing
- Equipment and resource allocation

## Database Models

### Service Provider Model

```php
<?php
// app/Models/ServiceProvider.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Masterix21\Bookings\Models\Concerns\IsBookable;
use Masterix21\Bookings\Models\Concerns\Bookable;

class ServiceProvider extends Model implements Bookable
{
    use IsBookable;

    protected $fillable = [
        'business_name',
        'contact_name',
        'email',
        'phone',
        'address',
        'city',
        'state',
        'country',
        'postal_code',
        'business_type',
        'description',
        'website',
        'operating_hours',
        'time_zone',
        'booking_buffer_time',
        'advance_booking_days',
        'cancellation_policy',
        'payment_methods',
        'is_active',
        'auto_confirm_bookings',
        'requires_deposit',
        'deposit_percentage',
    ];

    protected $casts = [
        'operating_hours' => 'array',
        'booking_buffer_time' => 'integer',
        'advance_booking_days' => 'integer',
        'cancellation_policy' => 'array',
        'payment_methods' => 'array',
        'is_active' => 'boolean',
        'auto_confirm_bookings' => 'boolean',
        'requires_deposit' => 'boolean',
        'deposit_percentage' => 'decimal:2',
    ];

    public function locations(): HasMany
    {
        return $this->hasMany(ServiceLocation::class);
    }

    public function staff(): HasMany
    {
        return $this->hasMany(ServiceStaff::class);
    }

    public function services(): HasMany
    {
        return $this->hasMany(Service::class);
    }

    public function appointments(): HasMany
    {
        return $this->hasMany(ServiceAppointment::class);
    }

    public function isOperatingAt(Carbon $dateTime): bool
    {
        $dayOfWeek = strtolower($dateTime->format('l'));
        $timeString = $dateTime->format('H:i');
        
        if (!isset($this->operating_hours[$dayOfWeek])) {
            return false;
        }

        $hours = $this->operating_hours[$dayOfWeek];
        
        if ($hours['closed'] ?? false) {
            return false;
        }

        return $timeString >= $hours['start'] && $timeString <= $hours['end'];
    }

    public function getAvailableStaff(Service $service, Carbon $dateTime, int $duration): Collection
    {
        return $this->staff()
            ->where('is_active', true)
            ->whereHas('services', function ($query) use ($service) {
                $query->where('service_id', $service->id);
            })
            ->get()
            ->filter(function ($staffMember) use ($dateTime, $duration) {
                return $staffMember->isAvailableAt($dateTime, $duration);
            });
    }
}
```

### Service Model

```php
<?php
// app/Models/Service.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Masterix21\Bookings\Models\Concerns\IsBookable;
use Masterix21\Bookings\Models\Concerns\Bookable;

class Service extends Model implements Bookable
{
    use IsBookable;

    protected $fillable = [
        'service_provider_id',
        'name',
        'slug',
        'description',
        'category',
        'duration_minutes',
        'buffer_time_before',
        'buffer_time_after',
        'base_price',
        'currency',
        'is_active',
        'requires_consultation',
        'max_advance_booking_days',
        'min_advance_booking_hours',
        'cancellation_hours',
        'preparation_instructions',
        'aftercare_instructions',
        'required_equipment',
        'staff_requirements',
    ];

    protected $casts = [
        'duration_minutes' => 'integer',
        'buffer_time_before' => 'integer',
        'buffer_time_after' => 'integer',
        'base_price' => 'decimal:2',
        'is_active' => 'boolean',
        'requires_consultation' => 'boolean',
        'max_advance_booking_days' => 'integer',
        'min_advance_booking_hours' => 'integer',
        'cancellation_hours' => 'integer',
        'required_equipment' => 'array',
        'staff_requirements' => 'array',
    ];

    public function serviceProvider(): BelongsTo
    {
        return $this->belongsTo(ServiceProvider::class);
    }

    public function staff(): BelongsToMany
    {
        return $this->belongsToMany(ServiceStaff::class, 'service_staff_services')
                    ->withPivot(['hourly_rate', 'commission_rate'])
                    ->withTimestamps();
    }

    public function addOns(): HasMany
    {
        return $this->hasMany(ServiceAddOn::class);
    }

    public function getTotalDurationAttribute(): int
    {
        return $this->duration_minutes + 
               ($this->buffer_time_before ?? 0) + 
               ($this->buffer_time_after ?? 0);
    }

    public function calculatePrice(array $addOns = [], ?ServiceStaff $staffMember = null): array
    {
        $pricing = [
            'base_price' => $this->base_price,
            'add_ons' => [],
            'staff_premium' => 0,
            'subtotal' => $this->base_price,
            'tax' => 0,
            'total' => $this->base_price,
        ];

        // Add-ons pricing
        if (!empty($addOns)) {
            foreach ($addOns as $addOnId) {
                $addOn = $this->addOns()->find($addOnId);
                if ($addOn) {
                    $pricing['add_ons'][$addOn->name] = $addOn->price;
                    $pricing['subtotal'] += $addOn->price;
                }
            }
        }

        // Staff premium pricing
        if ($staffMember && $staffMember->premium_rate > 0) {
            $premium = $pricing['subtotal'] * ($staffMember->premium_rate / 100);
            $pricing['staff_premium'] = $premium;
            $pricing['subtotal'] += $premium;
        }

        // Tax calculation (example: 10%)
        $pricing['tax'] = $pricing['subtotal'] * 0.10;
        $pricing['total'] = $pricing['subtotal'] + $pricing['tax'];

        return $pricing;
    }

    public function isBookableAt(Carbon $dateTime, ?ServiceStaff $staffMember = null): bool
    {
        // Check minimum advance booking time
        if ($this->min_advance_booking_hours) {
            $minTime = now()->addHours($this->min_advance_booking_hours);
            if ($dateTime->isBefore($minTime)) {
                return false;
            }
        }

        // Check maximum advance booking time
        if ($this->max_advance_booking_days) {
            $maxTime = now()->addDays($this->max_advance_booking_days);
            if ($dateTime->isAfter($maxTime)) {
                return false;
            }
        }

        // Check service provider operating hours
        if (!$this->serviceProvider->isOperatingAt($dateTime)) {
            return false;
        }

        // Check staff availability if specified
        if ($staffMember) {
            return $staffMember->isAvailableAt($dateTime, $this->total_duration);
        }

        return true;
    }
}
```

### Service Staff Model

```php
<?php
// app/Models/ServiceStaff.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Masterix21\Bookings\Models\Concerns\IsBookable;
use Masterix21\Bookings\Models\Concerns\Bookable;
use Masterix21\Bookings\Actions\CheckBookingOverlaps;
use Carbon\Carbon;
use Spatie\Period\Period;
use Spatie\Period\PeriodCollection;

class ServiceStaff extends Model implements Bookable
{
    use IsBookable;

    protected $fillable = [
        'service_provider_id',
        'user_id',
        'first_name',
        'last_name',
        'email',
        'phone',
        'title',
        'bio',
        'specializations',
        'certifications',
        'experience_years',
        'hourly_rate',
        'commission_rate',
        'premium_rate',
        'working_hours',
        'time_zone',
        'is_active',
        'accepts_online_booking',
        'profile_image',
        'languages',
    ];

    protected $casts = [
        'specializations' => 'array',
        'certifications' => 'array',
        'experience_years' => 'integer',
        'hourly_rate' => 'decimal:2',
        'commission_rate' => 'decimal:2',
        'premium_rate' => 'decimal:2',
        'working_hours' => 'array',
        'is_active' => 'boolean',
        'accepts_online_booking' => 'boolean',
        'languages' => 'array',
    ];

    public function serviceProvider(): BelongsTo
    {
        return $this->belongsTo(ServiceProvider::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function services(): BelongsToMany
    {
        return $this->belongsToMany(Service::class, 'service_staff_services')
                    ->withPivot(['hourly_rate', 'commission_rate'])
                    ->withTimestamps();
    }

    public function appointments(): HasMany
    {
        return $this->hasMany(ServiceAppointment::class);
    }

    public function getFullNameAttribute(): string
    {
        return "{$this->first_name} {$this->last_name}";
    }

    public function isWorkingAt(Carbon $dateTime): bool
    {
        $dayOfWeek = strtolower($dateTime->format('l'));
        $timeString = $dateTime->format('H:i');
        
        if (!isset($this->working_hours[$dayOfWeek])) {
            return false;
        }

        $hours = $this->working_hours[$dayOfWeek];
        
        if ($hours['off'] ?? false) {
            return false;
        }

        return $timeString >= $hours['start'] && $timeString <= $hours['end'];
    }

    public function isAvailableAt(Carbon $dateTime, int $durationMinutes): bool
    {
        if (!$this->is_active || !$this->accepts_online_booking) {
            return false;
        }

        if (!$this->isWorkingAt($dateTime)) {
            return false;
        }

        if (!$this->bookableResource) {
            return false;
        }

        $endTime = $dateTime->copy()->addMinutes($durationMinutes);
        $periods = PeriodCollection::make([
            Period::make($dateTime, $endTime)
        ]);

        return (new CheckBookingOverlaps())->run(
            periods: $periods,
            bookableResource: $this->bookableResource,
            emitEvent: false,
            throw: false
        );
    }

    public function getAvailableSlots(Carbon $date, Service $service): array
    {
        $slots = [];
        $dayOfWeek = strtolower($date->format('l'));
        
        if (!isset($this->working_hours[$dayOfWeek]) || ($this->working_hours[$dayOfWeek]['off'] ?? false)) {
            return $slots;
        }

        $workingHours = $this->working_hours[$dayOfWeek];
        $startTime = Carbon::parse($date->format('Y-m-d') . ' ' . $workingHours['start']);
        $endTime = Carbon::parse($date->format('Y-m-d') . ' ' . $workingHours['end']);
        
        // Account for lunch break if defined
        $lunchBreak = $workingHours['lunch_break'] ?? null;
        
        $currentTime = $startTime->copy();
        $slotDuration = 30; // 30-minute slots
        
        while ($currentTime->addMinutes($slotDuration)->lessThanOrEqualTo($endTime)) {
            // Skip lunch break
            if ($lunchBreak && 
                $currentTime->format('H:i') >= $lunchBreak['start'] && 
                $currentTime->format('H:i') < $lunchBreak['end']) {
                continue;
            }

            if ($this->isAvailableAt($currentTime, $service->total_duration)) {
                $slots[] = [
                    'time' => $currentTime->format('H:i'),
                    'datetime' => $currentTime->toISOString(),
                    'available' => true,
                ];
            }
        }

        return $slots;
    }
}
```

### Client Model

```php
<?php
// app/Models/Client.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Masterix21\Bookings\Models\Concerns\HasBookings;

class Client extends Model
{
    use HasBookings;

    protected $fillable = [
        'first_name',
        'last_name',
        'email',
        'phone',
        'date_of_birth',
        'gender',
        'address',
        'city',
        'state',
        'country',
        'postal_code',
        'emergency_contact_name',
        'emergency_contact_phone',
        'medical_conditions',
        'allergies',
        'medications',
        'preferences',
        'marketing_consent',
        'notes',
        'client_since',
        'total_appointments',
        'total_spent',
        'loyalty_points',
        'referral_source',
    ];

    protected $casts = [
        'date_of_birth' => 'date',
        'medical_conditions' => 'array',
        'allergies' => 'array',
        'medications' => 'array',
        'preferences' => 'array',
        'marketing_consent' => 'boolean',
        'client_since' => 'date',
        'total_appointments' => 'integer',
        'total_spent' => 'decimal:2',
        'loyalty_points' => 'integer',
    ];

    public function appointments(): HasMany
    {
        return $this->hasMany(ServiceAppointment::class);
    }

    public function getFullNameAttribute(): string
    {
        return "{$this->first_name} {$this->last_name}";
    }

    public function getAgeAttribute(): ?int
    {
        return $this->date_of_birth?->age;
    }

    public function isVipClient(): bool
    {
        return $this->total_appointments >= 20 || 
               $this->total_spent >= 2000 || 
               $this->loyalty_points >= 5000;
    }

    public function getAppointmentHistory(): Collection
    {
        return $this->appointments()
            ->with(['service', 'serviceProvider', 'staff'])
            ->orderBy('appointment_date', 'desc')
            ->limit(10)
            ->get();
    }

    public function hasRecentNoShow(): bool
    {
        return $this->appointments()
            ->where('status', 'no_show')
            ->where('appointment_date', '>=', now()->subDays(30))
            ->exists();
    }

    public function getPreferredStaff(): ?ServiceStaff
    {
        $staffId = $this->preferences['preferred_staff_id'] ?? null;
        return $staffId ? ServiceStaff::find($staffId) : null;
    }
}
```

## Service Layer

### Service Appointment Service

```php
<?php
// app/Services/ServiceAppointmentService.php

namespace App\Services;

use App\Models\ServiceProvider;
use App\Models\Service;
use App\Models\ServiceStaff;
use App\Models\Client;
use App\Models\ServiceAppointment;
use Masterix21\Bookings\Actions\BookResource;
use Masterix21\Bookings\Models\Booking;
use Carbon\Carbon;
use Spatie\Period\Period;
use Spatie\Period\PeriodCollection;
use Illuminate\Support\Facades\DB;

class ServiceAppointmentService
{
    public function __construct(
        private BookResource $bookResource,
        private NotificationService $notificationService,
        private PaymentService $paymentService
    ) {}

    public function searchAvailability(
        ServiceProvider $serviceProvider,
        Service $service,
        Carbon $date,
        ?ServiceStaff $preferredStaff = null
    ): array {
        if (!$service->isBookableAt(Carbon::parse($date->format('Y-m-d') . ' 09:00'))) {
            return [
                'available' => false,
                'reason' => 'Service not available on this date',
                'alternative_dates' => $this->suggestAlternativeDates($service, $date),
            ];
        }

        $availableStaff = $preferredStaff 
            ? collect([$preferredStaff])
            : $serviceProvider->getAvailableStaff($service, $date, $service->total_duration);

        if ($availableStaff->isEmpty()) {
            return [
                'available' => false,
                'reason' => 'No staff available on this date',
                'alternative_dates' => $this->suggestAlternativeDates($service, $date),
            ];
        }

        $availabilityByStaff = [];

        foreach ($availableStaff as $staff) {
            $slots = $staff->getAvailableSlots($date, $service);
            
            if (!empty($slots)) {
                $availabilityByStaff[] = [
                    'staff' => [
                        'id' => $staff->id,
                        'name' => $staff->full_name,
                        'title' => $staff->title,
                        'experience_years' => $staff->experience_years,
                        'specializations' => $staff->specializations,
                        'premium_rate' => $staff->premium_rate,
                        'profile_image' => $staff->profile_image,
                    ],
                    'available_slots' => $slots,
                    'pricing' => $service->calculatePrice([], $staff),
                ];
            }
        }

        return [
            'available' => !empty($availabilityByStaff),
            'service' => [
                'id' => $service->id,
                'name' => $service->name,
                'duration_minutes' => $service->duration_minutes,
                'base_price' => $service->base_price,
                'description' => $service->description,
            ],
            'availability_by_staff' => $availabilityByStaff,
            'add_ons' => $service->addOns->map(function ($addOn) {
                return [
                    'id' => $addOn->id,
                    'name' => $addOn->name,
                    'description' => $addOn->description,
                    'price' => $addOn->price,
                    'duration_minutes' => $addOn->duration_minutes,
                ];
            }),
        ];
    }

    public function createAppointment(
        Client $client,
        Service $service,
        ServiceStaff $staff,
        Carbon $appointmentDateTime,
        array $appointmentData = []
    ): ServiceAppointment {
        return DB::transaction(function () use ($client, $service, $staff, $appointmentDateTime, $appointmentData) {
            // Validate staff availability
            if (!$staff->isAvailableAt($appointmentDateTime, $service->total_duration)) {
                throw new \Exception('Staff member is not available at the requested time');
            }

            // Calculate pricing
            $addOns = $appointmentData['add_ons'] ?? [];
            $pricing = $service->calculatePrice($addOns, $staff);

            // Create appointment record
            $appointment = ServiceAppointment::create([
                'service_provider_id' => $service->service_provider_id,
                'client_id' => $client->id,
                'service_id' => $service->id,
                'service_staff_id' => $staff->id,
                'appointment_date' => $appointmentDateTime,
                'duration_minutes' => $service->duration_minutes,
                'buffer_time_before' => $service->buffer_time_before ?? 0,
                'buffer_time_after' => $service->buffer_time_after ?? 0,
                'status' => $service->serviceProvider->auto_confirm_bookings ? 'confirmed' : 'pending',
                'base_price' => $pricing['base_price'],
                'add_ons_price' => array_sum($pricing['add_ons']),
                'staff_premium' => $pricing['staff_premium'],
                'tax_amount' => $pricing['tax'],
                'total_amount' => $pricing['total'],
                'currency' => $service->currency,
                'special_instructions' => $appointmentData['special_instructions'] ?? null,
                'client_notes' => $appointmentData['client_notes'] ?? null,
                'add_ons' => $addOns,
                'recurring_pattern' => $appointmentData['recurring_pattern'] ?? null,
                'created_via' => 'online_booking',
            ]);

            // Create booking for staff schedule
            $totalDuration = $service->total_duration;
            $startTime = $appointmentDateTime->copy();
            if ($service->buffer_time_before) {
                $startTime->subMinutes($service->buffer_time_before);
                $totalDuration += $service->buffer_time_before;
            }
            
            $endTime = $startTime->copy()->addMinutes($totalDuration);
            $periods = PeriodCollection::make([
                Period::make($startTime, $endTime)
            ]);

            $booking = $this->bookResource->run(
                periods: $periods,
                bookableResource: $staff->bookableResource,
                booker: $client,
                relatable: $appointment,
                label: "Appointment - {$service->name}",
                note: $appointmentData['special_instructions'] ?? null,
                meta: [
                    'appointment_id' => $appointment->id,
                    'service_name' => $service->name,
                    'client_name' => $client->full_name,
                    'staff_name' => $staff->full_name,
                    'duration_minutes' => $service->duration_minutes,
                    'total_amount' => $pricing['total'],
                    'add_ons' => $addOns,
                ]
            );

            $appointment->update(['booking_id' => $booking->id]);

            // Handle payment if required
            if ($service->serviceProvider->requires_deposit) {
                $depositAmount = $pricing['total'] * ($service->serviceProvider->deposit_percentage / 100);
                $this->processDeposit($appointment, $client, $depositAmount, $appointmentData);
            }

            // Create recurring appointments if specified
            if ($appointmentData['recurring_pattern'] ?? false) {
                $this->createRecurringAppointments($appointment, $appointmentData['recurring_pattern']);
            }

            // Send confirmation
            $this->sendAppointmentConfirmation($appointment);

            // Update client statistics
            $this->updateClientStatistics($client);

            return $appointment;
        });
    }

    public function rescheduleAppointment(
        ServiceAppointment $appointment,
        Carbon $newDateTime,
        ?ServiceStaff $newStaff = null,
        array $changes = []
    ): ServiceAppointment {
        return DB::transaction(function () use ($appointment, $newDateTime, $newStaff, $changes) {
            $currentStaff = $appointment->staff;
            $targetStaff = $newStaff ?? $currentStaff;

            // Validate new time availability
            if (!$targetStaff->isAvailableAt($newDateTime, $appointment->service->total_duration)) {
                throw new \Exception('Staff member is not available at the new requested time');
            }

            // Update appointment
            $appointment->update([
                'appointment_date' => $newDateTime,
                'service_staff_id' => $targetStaff->id,
                'special_instructions' => $changes['special_instructions'] ?? $appointment->special_instructions,
                'rescheduled_at' => now(),
                'reschedule_reason' => $changes['reason'] ?? null,
            ]);

            // Update booking
            $service = $appointment->service;
            $totalDuration = $service->total_duration;
            $startTime = $newDateTime->copy();
            
            if ($service->buffer_time_before) {
                $startTime->subMinutes($service->buffer_time_before);
            }
            
            $endTime = $startTime->copy()->addMinutes($totalDuration);
            $periods = PeriodCollection::make([
                Period::make($startTime, $endTime)
            ]);

            $this->bookResource->run(
                periods: $periods,
                bookableResource: $targetStaff->bookableResource,
                booker: $appointment->client,
                booking: $appointment->booking,
                meta: array_merge($appointment->booking->meta->toArray(), [
                    'rescheduled_at' => now()->toISOString(),
                    'reschedule_reason' => $changes['reason'],
                    'previous_datetime' => $appointment->getOriginal('appointment_date'),
                    'previous_staff' => $currentStaff->full_name,
                ])
            );

            // Send reschedule notification
            $this->sendRescheduleNotification($appointment, $changes['reason'] ?? null);

            return $appointment->fresh();
        });
    }

    public function cancelAppointment(
        ServiceAppointment $appointment,
        string $reason = null,
        bool $clientInitiated = true
    ): array {
        return DB::transaction(function () use ($appointment, $reason, $clientInitiated) {
            $cancellationFee = 0;
            $refundAmount = 0;

            // Calculate cancellation fees
            if ($clientInitiated) {
                $hoursUntilAppointment = now()->diffInHours($appointment->appointment_date);
                $cancellationHours = $appointment->service->cancellation_hours ?? 24;

                if ($hoursUntilAppointment < $cancellationHours) {
                    // Apply cancellation fee
                    $cancellationFee = $appointment->total_amount * 0.5; // 50% fee
                    $refundAmount = $appointment->total_amount - $cancellationFee;
                } else {
                    // Full refund
                    $refundAmount = $appointment->total_amount;
                }
            } else {
                // Provider cancelled - full refund
                $refundAmount = $appointment->total_amount;
            }

            // Update appointment
            $appointment->update([
                'status' => 'cancelled',
                'cancelled_at' => now(),
                'cancellation_reason' => $reason,
                'cancelled_by_client' => $clientInitiated,
                'cancellation_fee' => $cancellationFee,
                'refund_amount' => $refundAmount,
            ]);

            // Free up staff schedule
            $appointment->booking->bookedPeriods()->delete();

            // Process refund if applicable
            if ($refundAmount > 0 && $appointment->payment_status === 'paid') {
                $refundResult = $this->paymentService->processRefund($appointment, $refundAmount);
                $appointment->update([
                    'refund_status' => $refundResult['status'],
                    'refund_reference' => $refundResult['reference'],
                ]);
            }

            // Send cancellation notification
            $this->sendCancellationNotification($appointment, $clientInitiated);

            return [
                'appointment' => $appointment->fresh(),
                'cancellation_fee' => $cancellationFee,
                'refund_amount' => $refundAmount,
            ];
        });
    }

    public function checkInAppointment(ServiceAppointment $appointment, array $checkInData = []): void
    {
        $appointment->update([
            'status' => 'in_progress',
            'checked_in_at' => now(),
            'check_in_notes' => $checkInData['notes'] ?? null,
        ]);

        event(new ClientCheckedIn($appointment, $checkInData));
    }

    public function completeAppointment(ServiceAppointment $appointment, array $completionData = []): void
    {
        $appointment->update([
            'status' => 'completed',
            'completed_at' => now(),
            'service_notes' => $completionData['service_notes'] ?? null,
            'client_satisfaction' => $completionData['satisfaction_rating'] ?? null,
            'follow_up_required' => $completionData['follow_up_required'] ?? false,
            'follow_up_date' => $completionData['follow_up_date'] ?? null,
            'next_recommended_appointment' => $completionData['next_appointment_date'] ?? null,
        ]);

        // Update client statistics
        $client = $appointment->client;
        $client->update([
            'total_appointments' => $client->total_appointments + 1,
            'total_spent' => $client->total_spent + $appointment->total_amount,
        ]);

        // Award loyalty points (1 point per dollar spent)
        $client->increment('loyalty_points', (int) $appointment->total_amount);

        // Free up staff schedule
        $appointment->booking->bookedPeriods()->delete();

        // Send completion notification and follow-up
        $this->sendCompletionNotification($appointment);
        
        if ($completionData['follow_up_required'] ?? false) {
            $this->scheduleFollowUp($appointment, $completionData['follow_up_date']);
        }

        event(new AppointmentCompleted($appointment, $completionData));
    }

    public function handleNoShow(ServiceAppointment $appointment): void
    {
        $appointment->update([
            'status' => 'no_show',
            'no_show_time' => now(),
        ]);

        // Apply no-show fee (typically full charge)
        $noShowFee = $appointment->total_amount;
        
        if ($appointment->payment_status !== 'paid') {
            $this->paymentService->chargeNoShowFee($appointment->client, $noShowFee, $appointment);
        }

        // Free up staff schedule
        $appointment->booking->bookedPeriods()->delete();

        // Update client record
        $this->applyNoShowPenalty($appointment->client);

        event(new ClientNoShow($appointment));
    }

    private function createRecurringAppointments(ServiceAppointment $baseAppointment, array $pattern): void
    {
        $frequency = $pattern['frequency']; // weekly, monthly
        $interval = $pattern['interval'] ?? 1; // every X weeks/months
        $occurrences = $pattern['occurrences'] ?? 4; // number of future appointments
        $endDate = $pattern['end_date'] ?? null;

        $currentDate = $baseAppointment->appointment_date->copy();

        for ($i = 1; $i <= $occurrences; $i++) {
            switch ($frequency) {
                case 'weekly':
                    $nextDate = $currentDate->copy()->addWeeks($interval);
                    break;
                case 'monthly':
                    $nextDate = $currentDate->copy()->addMonths($interval);
                    break;
                default:
                    continue 2;
            }

            if ($endDate && $nextDate->isAfter(Carbon::parse($endDate))) {
                break;
            }

            // Check if staff is available at the recurring time
            if ($baseAppointment->staff->isAvailableAt($nextDate, $baseAppointment->service->total_duration)) {
                $this->createAppointment(
                    $baseAppointment->client,
                    $baseAppointment->service,
                    $baseAppointment->staff,
                    $nextDate,
                    [
                        'special_instructions' => $baseAppointment->special_instructions,
                        'add_ons' => $baseAppointment->add_ons,
                        'parent_appointment_id' => $baseAppointment->id,
                        'is_recurring' => true,
                    ]
                );
            }

            $currentDate = $nextDate;
        }
    }

    private function suggestAlternativeDates(Service $service, Carbon $requestedDate): array
    {
        $alternatives = [];
        
        // Check next 7 days
        for ($i = 1; $i <= 7; $i++) {
            $altDate = $requestedDate->copy()->addDays($i);
            
            if ($service->isBookableAt($altDate)) {
                $availableStaff = $service->serviceProvider->getAvailableStaff(
                    $service, 
                    $altDate, 
                    $service->total_duration
                );
                
                if ($availableStaff->count() > 0) {
                    $alternatives[] = [
                        'date' => $altDate->toDateString(),
                        'available_staff_count' => $availableStaff->count(),
                    ];
                }
            }
        }

        return array_slice($alternatives, 0, 3);
    }

    private function processDeposit(ServiceAppointment $appointment, Client $client, float $amount, array $paymentData): void
    {
        $paymentResult = $this->paymentService->processPayment(
            $client,
            $amount,
            $appointment,
            $paymentData['payment_method'] ?? 'card',
            'deposit'
        );

        $appointment->update([
            'deposit_amount' => $amount,
            'deposit_status' => $paymentResult['status'],
            'deposit_reference' => $paymentResult['reference'],
        ]);
    }

    private function sendAppointmentConfirmation(ServiceAppointment $appointment): void
    {
        $this->notificationService->sendAppointmentConfirmation($appointment);
        $appointment->update(['confirmation_sent' => true]);
    }

    private function updateClientStatistics(Client $client): void
    {
        // Update client statistics like average appointment value, frequency, etc.
        $avgValue = $client->appointments()->avg('total_amount');
        $client->update(['average_appointment_value' => $avgValue]);
    }

    private function applyNoShowPenalty(Client $client): void
    {
        $recentNoShows = $client->appointments()
            ->where('status', 'no_show')
            ->where('appointment_date', '>=', now()->subDays(90))
            ->count();

        if ($recentNoShows >= 2) {
            $client->update([
                'notes' => 'Multiple recent no-shows - requires deposit for future appointments',
                'requires_deposit' => true,
            ]);
        }
    }
}
```

## Frontend Integration

### Appointment Booking API

```php
<?php
// app/Http/Controllers/Api/ServiceAppointmentController.php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ServiceProvider;
use App\Models\Service;
use App\Models\ServiceStaff;
use App\Models\Client;
use App\Services\ServiceAppointmentService;
use Carbon\Carbon;
use Illuminate\Http\Request;

class ServiceAppointmentController extends Controller
{
    public function __construct(
        private ServiceAppointmentService $appointmentService
    ) {}

    public function searchAvailability(Request $request, ServiceProvider $provider, Service $service)
    {
        $request->validate([
            'date' => 'required|date|after:today',
            'preferred_staff_id' => 'nullable|exists:service_staff,id',
        ]);

        $date = Carbon::parse($request->date);
        $preferredStaff = $request->preferred_staff_id 
            ? ServiceStaff::find($request->preferred_staff_id)
            : null;

        $availability = $this->appointmentService->searchAvailability(
            serviceProvider: $provider,
            service: $service,
            date: $date,
            preferredStaff: $preferredStaff
        );

        return response()->json([
            'search_criteria' => [
                'service_provider' => $provider->business_name,
                'service' => $service->name,
                'date' => $date->toDateString(),
                'preferred_staff' => $preferredStaff?->full_name,
            ],
            'availability' => $availability,
        ]);
    }

    public function createAppointment(Request $request, ServiceProvider $provider, Service $service)
    {
        $request->validate([
            'client' => 'required|array',
            'client.first_name' => 'required|string|max:255',
            'client.last_name' => 'required|string|max:255',
            'client.email' => 'required|email',
            'client.phone' => 'required|string',
            'staff_id' => 'required|exists:service_staff,id',
            'appointment_datetime' => 'required|date|after:now',
            'add_ons' => 'nullable|array',
            'add_ons.*' => 'exists:service_add_ons,id',
            'special_instructions' => 'nullable|string|max:1000',
            'recurring_pattern' => 'nullable|array',
            'payment_method' => 'required|string',
        ]);

        $client = Client::firstOrCreate(
            ['email' => $request->client['email']],
            $request->client
        );

        $staff = ServiceStaff::findOrFail($request->staff_id);
        $appointmentDateTime = Carbon::parse($request->appointment_datetime);

        try {
            $appointment = $this->appointmentService->createAppointment(
                client: $client,
                service: $service,
                staff: $staff,
                appointmentDateTime: $appointmentDateTime,
                appointmentData: [
                    'add_ons' => $request->add_ons ?? [],
                    'special_instructions' => $request->special_instructions,
                    'client_notes' => $request->client_notes,
                    'recurring_pattern' => $request->recurring_pattern,
                    'payment_method' => $request->payment_method,
                ]
            );

            return response()->json([
                'success' => true,
                'appointment' => [
                    'id' => $appointment->id,
                    'confirmation_number' => $appointment->confirmation_number,
                    'client_name' => $client->full_name,
                    'service_name' => $service->name,
                    'staff_name' => $staff->full_name,
                    'appointment_date' => $appointment->appointment_date->toISOString(),
                    'duration_minutes' => $appointment->duration_minutes,
                    'total_amount' => $appointment->total_amount,
                    'currency' => $appointment->currency,
                    'status' => $appointment->status,
                ],
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Appointment could not be created',
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    public function getAppointment(ServiceAppointment $appointment)
    {
        return response()->json([
            'appointment' => [
                'id' => $appointment->id,
                'confirmation_number' => $appointment->confirmation_number,
                'status' => $appointment->status,
                'client' => [
                    'name' => $appointment->client->full_name,
                    'email' => $appointment->client->email,
                    'phone' => $appointment->client->phone,
                ],
                'service' => [
                    'name' => $appointment->service->name,
                    'description' => $appointment->service->description,
                    'duration_minutes' => $appointment->duration_minutes,
                ],
                'staff' => [
                    'name' => $appointment->staff->full_name,
                    'title' => $appointment->staff->title,
                    'profile_image' => $appointment->staff->profile_image,
                ],
                'appointment_details' => [
                    'date' => $appointment->appointment_date->toDateString(),
                    'time' => $appointment->appointment_date->format('H:i'),
                    'datetime' => $appointment->appointment_date->toISOString(),
                    'duration_minutes' => $appointment->duration_minutes,
                ],
                'pricing' => [
                    'base_price' => $appointment->base_price,
                    'add_ons_price' => $appointment->add_ons_price,
                    'staff_premium' => $appointment->staff_premium,
                    'tax_amount' => $appointment->tax_amount,
                    'total_amount' => $appointment->total_amount,
                    'currency' => $appointment->currency,
                ],
                'special_instructions' => $appointment->special_instructions,
                'add_ons' => $appointment->add_ons,
            ],
        ]);
    }

    public function rescheduleAppointment(Request $request, ServiceAppointment $appointment)
    {
        $request->validate([
            'new_datetime' => 'required|date|after:now',
            'new_staff_id' => 'nullable|exists:service_staff,id',
            'reason' => 'nullable|string|max:500',
        ]);

        $newStaff = $request->new_staff_id 
            ? ServiceStaff::findOrFail($request->new_staff_id)
            : null;

        try {
            $rescheduledAppointment = $this->appointmentService->rescheduleAppointment(
                appointment: $appointment,
                newDateTime: Carbon::parse($request->new_datetime),
                newStaff: $newStaff,
                changes: [
                    'reason' => $request->reason,
                    'special_instructions' => $request->special_instructions,
                ]
            );

            return response()->json([
                'success' => true,
                'message' => 'Appointment rescheduled successfully',
                'appointment' => [
                    'id' => $rescheduledAppointment->id,
                    'new_datetime' => $rescheduledAppointment->appointment_date->toISOString(),
                    'new_staff' => $rescheduledAppointment->staff->full_name,
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Could not reschedule appointment',
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    public function cancelAppointment(Request $request, ServiceAppointment $appointment)
    {
        $request->validate([
            'reason' => 'nullable|string|max:500',
        ]);

        try {
            $cancellationResult = $this->appointmentService->cancelAppointment(
                appointment: $appointment,
                reason: $request->reason,
                clientInitiated: true
            );

            return response()->json([
                'success' => true,
                'message' => 'Appointment cancelled successfully',
                'cancellation_details' => [
                    'cancellation_fee' => $cancellationResult['cancellation_fee'],
                    'refund_amount' => $cancellationResult['refund_amount'],
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Could not cancel appointment',
                'error' => $e->getMessage(),
            ], 422);
        }
    }
}
```

This comprehensive service appointments example demonstrates advanced features like staff scheduling, recurring appointments, payment processing, client management, and a complete booking workflow with various appointment statuses and business rules.