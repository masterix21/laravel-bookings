# Restaurant Reservation System Example

This example demonstrates how to build a sophisticated restaurant reservation system using Laravel Bookings, including table management, menu integration, special events, and waitlist functionality.

## Overview

This example covers:
- Table and dining area management
- Customer reservations with party size handling
- Menu integration and pre-ordering
- Special events and private dining
- Waitlist and availability notifications
- Staff management and seating optimization
- Walk-in handling and table turnover
- Multi-location restaurant chains

## Database Models

### Restaurant Model

```php
<?php
// app/Models/Restaurant.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Masterix21\Bookings\Models\Concerns\IsBookable;
use Masterix21\Bookings\Models\Concerns\Bookable;

class Restaurant extends Model implements Bookable
{
    use IsBookable;

    protected $fillable = [
        'name',
        'slug',
        'address',
        'city',
        'state',
        'country',
        'postal_code',
        'phone',
        'email',
        'website',
        'cuisine_type',
        'price_range',
        'description',
        'opening_hours',
        'reservation_policy',
        'cancellation_policy',
        'dress_code',
        'max_party_size',
        'advance_booking_days',
        'is_active',
        'accepts_reservations',
        'accepts_walk_ins',
        'features',
    ];

    protected $casts = [
        'opening_hours' => 'array',
        'reservation_policy' => 'array',
        'cancellation_policy' => 'array',
        'max_party_size' => 'integer',
        'advance_booking_days' => 'integer',
        'is_active' => 'boolean',
        'accepts_reservations' => 'boolean',
        'accepts_walk_ins' => 'boolean',
        'features' => 'array',
    ];

    public function tables(): HasMany
    {
        return $this->hasMany(RestaurantTable::class);
    }

    public function diningAreas(): HasMany
    {
        return $this->hasMany(DiningArea::class);
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(RestaurantReservation::class);
    }

    public function menuItems(): HasMany
    {
        return $this->hasMany(MenuItem::class);
    }

    public function getAvailableTables(Carbon $dateTime, int $partySize, int $duration = 120): Collection
    {
        return $this->tables()
            ->where('capacity', '>=', $partySize)
            ->where('is_active', true)
            ->whereHas('bookableResource', function ($query) {
                $query->where('is_bookable', true);
            })
            ->get()
            ->filter(function ($table) use ($dateTime, $duration) {
                return $table->isAvailableAt($dateTime, $duration);
            })
            ->sortBy(['capacity', 'preference_order']);
    }

    public function isOpenAt(Carbon $dateTime): bool
    {
        $dayOfWeek = strtolower($dateTime->format('l'));
        $timeString = $dateTime->format('H:i');
        
        if (!isset($this->opening_hours[$dayOfWeek])) {
            return false;
        }

        $hours = $this->opening_hours[$dayOfWeek];
        
        if ($hours['closed'] ?? false) {
            return false;
        }

        return $timeString >= $hours['open'] && $timeString <= $hours['close'];
    }

    public function getReservationSlots(Carbon $date, int $partySize): array
    {
        $slots = [];
        $dayOfWeek = strtolower($date->format('l'));
        
        if (!$this->isOpenAt($date) || !isset($this->opening_hours[$dayOfWeek])) {
            return $slots;
        }

        $hours = $this->opening_hours[$dayOfWeek];
        $openTime = Carbon::parse($date->format('Y-m-d') . ' ' . $hours['open']);
        $closeTime = Carbon::parse($date->format('Y-m-d') . ' ' . $hours['close']);
        
        // Generate 30-minute slots
        $currentTime = $openTime->copy();
        while ($currentTime->lessThan($closeTime->subMinutes(120))) { // 2 hours before closing
            $availableTables = $this->getAvailableTables($currentTime, $partySize);
            
            if ($availableTables->count() > 0) {
                $slots[] = [
                    'time' => $currentTime->format('H:i'),
                    'datetime' => $currentTime->toISOString(),
                    'available_tables' => $availableTables->count(),
                    'can_book' => true,
                ];
            } else {
                $slots[] = [
                    'time' => $currentTime->format('H:i'),
                    'datetime' => $currentTime->toISOString(),
                    'available_tables' => 0,
                    'can_book' => false,
                ];
            }
            
            $currentTime->addMinutes(30);
        }

        return $slots;
    }
}
```

### Restaurant Table Model

```php
<?php
// app/Models/RestaurantTable.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Masterix21\Bookings\Models\Concerns\IsBookable;
use Masterix21\Bookings\Models\Concerns\Bookable;
use Masterix21\Bookings\Actions\CheckBookingOverlaps;
use Carbon\Carbon;
use Spatie\Period\Period;
use Spatie\Period\PeriodCollection;

class RestaurantTable extends Model implements Bookable
{
    use IsBookable;

    protected $fillable = [
        'restaurant_id',
        'dining_area_id',
        'number',
        'capacity',
        'min_capacity',
        'table_type',
        'location_description',
        'features',
        'preference_order',
        'is_active',
        'is_combinable',
        'combined_with_table_ids',
        'shape',
        'notes',
    ];

    protected $casts = [
        'capacity' => 'integer',
        'min_capacity' => 'integer',
        'preference_order' => 'integer',
        'is_active' => 'boolean',
        'is_combinable' => 'boolean',
        'combined_with_table_ids' => 'array',
        'features' => 'array',
    ];

    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }

    public function diningArea(): BelongsTo
    {
        return $this->belongsTo(DiningArea::class);
    }

    public function isAvailableAt(Carbon $dateTime, int $duration = 120): bool
    {
        if (!$this->is_active || !$this->bookableResource) {
            return false;
        }

        $endTime = $dateTime->copy()->addMinutes($duration);
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

    public function isSuitableForParty(int $partySize): bool
    {
        return $partySize >= ($this->min_capacity ?? 1) && 
               $partySize <= $this->capacity;
    }

    public function getDisplayNameAttribute(): string
    {
        $name = "Table {$this->number}";
        
        if ($this->diningArea) {
            $name .= " ({$this->diningArea->name})";
        }
        
        return $name;
    }

    public function getCurrentReservation(): ?RestaurantReservation
    {
        return $this->restaurant->reservations()
            ->whereHas('bookedPeriods', function ($query) {
                $query->where('bookable_resource_id', $this->bookableResource->id)
                      ->where('starts_at', '<=', now())
                      ->where('ends_at', '>', now());
            })
            ->first();
    }
}
```

### Customer Model

```php
<?php
// app/Models/Customer.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Masterix21\Bookings\Models\Concerns\HasBookings;

class Customer extends Model
{
    use HasBookings;

    protected $fillable = [
        'first_name',
        'last_name',
        'email',
        'phone',
        'date_of_birth',
        'dietary_restrictions',
        'preferences',
        'special_occasions',
        'loyalty_tier',
        'total_visits',
        'total_spent',
        'average_party_size',
        'preferred_seating',
        'notes',
        'marketing_opt_in',
        'last_visit_date',
    ];

    protected $casts = [
        'date_of_birth' => 'date',
        'dietary_restrictions' => 'array',
        'preferences' => 'array',
        'special_occasions' => 'array',
        'total_visits' => 'integer',
        'total_spent' => 'decimal:2',
        'average_party_size' => 'decimal:1',
        'marketing_opt_in' => 'boolean',
        'last_visit_date' => 'datetime',
    ];

    public function reservations(): HasMany
    {
        return $this->hasMany(RestaurantReservation::class);
    }

    public function waitlistEntries(): HasMany
    {
        return $this->hasMany(WaitlistEntry::class);
    }

    public function getFullNameAttribute(): string
    {
        return "{$this->first_name} {$this->last_name}";
    }

    public function isVip(): bool
    {
        return in_array($this->loyalty_tier, ['gold', 'platinum']) || 
               $this->total_visits >= 50 || 
               $this->total_spent >= 5000;
    }

    public function getReservationHistory(): Collection
    {
        return $this->reservations()
            ->with(['restaurant', 'table'])
            ->orderBy('reservation_date', 'desc')
            ->limit(10)
            ->get();
    }

    public function hasRecentNoShow(): bool
    {
        return $this->reservations()
            ->where('status', 'no_show')
            ->where('reservation_date', '>=', now()->subDays(30))
            ->exists();
    }
}
```

## Service Layer

### Restaurant Reservation Service

```php
<?php
// app/Services/RestaurantReservationService.php

namespace App\Services;

use App\Models\Restaurant;
use App\Models\RestaurantTable;
use App\Models\Customer;
use App\Models\RestaurantReservation;
use App\Models\WaitlistEntry;
use Masterix21\Bookings\Actions\BookResource;
use Masterix21\Bookings\Models\Booking;
use Carbon\Carbon;
use Spatie\Period\Period;
use Spatie\Period\PeriodCollection;
use Illuminate\Support\Facades\DB;

class RestaurantReservationService
{
    public function __construct(
        private BookResource $bookResource,
        private NotificationService $notificationService,
        private TableOptimizationService $tableOptimizer
    ) {}

    public function searchAvailability(
        Restaurant $restaurant,
        Carbon $dateTime,
        int $partySize,
        int $duration = 120,
        array $preferences = []
    ): array {
        if (!$restaurant->isOpenAt($dateTime)) {
            return [
                'available' => false,
                'reason' => 'Restaurant is closed at the requested time',
                'alternative_times' => $this->suggestAlternativeTimes($restaurant, $dateTime, $partySize),
            ];
        }

        if ($partySize > $restaurant->max_party_size) {
            return [
                'available' => false,
                'reason' => "Party size exceeds maximum of {$restaurant->max_party_size}",
                'suggestion' => 'Please contact the restaurant directly for large parties',
            ];
        }

        $availableTables = $restaurant->getAvailableTables($dateTime, $partySize, $duration);

        if ($availableTables->isEmpty()) {
            return [
                'available' => false,
                'reason' => 'No tables available at the requested time',
                'waitlist_available' => true,
                'alternative_times' => $this->suggestAlternativeTimes($restaurant, $dateTime, $partySize),
            ];
        }

        // Optimize table selection based on preferences
        $recommendedTable = $this->tableOptimizer->selectBestTable(
            $availableTables,
            $partySize,
            $preferences
        );

        return [
            'available' => true,
            'recommended_table' => $recommendedTable,
            'alternative_tables' => $availableTables->where('id', '!=', $recommendedTable->id)->values(),
            'estimated_duration' => $duration,
        ];
    }

    public function createReservation(
        Restaurant $restaurant,
        Customer $customer,
        Carbon $dateTime,
        int $partySize,
        ?RestaurantTable $specificTable = null,
        array $reservationData = []
    ): RestaurantReservation {
        return DB::transaction(function () use (
            $restaurant, $customer, $dateTime, $partySize, $specificTable, $reservationData
        ) {
            $duration = $reservationData['duration'] ?? 120;
            
            // Find table if not specified
            if (!$specificTable) {
                $availableTables = $restaurant->getAvailableTables($dateTime, $partySize, $duration);
                
                if ($availableTables->isEmpty()) {
                    throw new \Exception('No tables available at the requested time');
                }

                $specificTable = $this->tableOptimizer->selectBestTable(
                    $availableTables,
                    $partySize,
                    $reservationData['preferences'] ?? []
                );
            }

            // Create reservation record
            $reservation = RestaurantReservation::create([
                'restaurant_id' => $restaurant->id,
                'customer_id' => $customer->id,
                'table_id' => $specificTable->id,
                'reservation_date' => $dateTime,
                'party_size' => $partySize,
                'duration' => $duration,
                'status' => 'confirmed',
                'special_requests' => $reservationData['special_requests'] ?? null,
                'dietary_restrictions' => $reservationData['dietary_restrictions'] ?? [],
                'occasion' => $reservationData['occasion'] ?? null,
                'seating_preference' => $reservationData['seating_preference'] ?? null,
                'high_chair_needed' => $reservationData['high_chair_needed'] ?? false,
                'accessibility_needs' => $reservationData['accessibility_needs'] ?? null,
                'marketing_source' => $reservationData['marketing_source'] ?? 'direct',
                'confirmation_sent' => false,
            ]);

            // Create booking
            $endTime = $dateTime->copy()->addMinutes($duration);
            $periods = PeriodCollection::make([
                Period::make($dateTime, $endTime)
            ]);

            $booking = $this->bookResource->run(
                periods: $periods,
                bookableResource: $specificTable->bookableResource,
                booker: $customer,
                relatable: $reservation,
                label: "Table Reservation - {$customer->full_name}",
                note: $reservationData['special_requests'] ?? null,
                meta: [
                    'reservation_id' => $reservation->id,
                    'party_size' => $partySize,
                    'table_number' => $specificTable->number,
                    'customer_name' => $customer->full_name,
                    'customer_phone' => $customer->phone,
                    'occasion' => $reservationData['occasion'],
                    'dietary_restrictions' => $reservationData['dietary_restrictions'] ?? [],
                    'special_requests' => $reservationData['special_requests'],
                ]
            );

            $reservation->update(['booking_id' => $booking->id]);

            // Handle pre-orders if provided
            if (!empty($reservationData['pre_orders'])) {
                $this->processPreOrders($reservation, $reservationData['pre_orders']);
            }

            // Send confirmation
            $this->sendReservationConfirmation($reservation);

            // Update customer stats
            $this->updateCustomerStats($customer, $partySize);

            return $reservation;
        });
    }

    public function modifyReservation(
        RestaurantReservation $reservation,
        ?Carbon $newDateTime = null,
        ?int $newPartySize = null,
        ?RestaurantTable $newTable = null,
        array $changes = []
    ): RestaurantReservation {
        return DB::transaction(function () use ($reservation, $newDateTime, $newPartySize, $newTable, $changes) {
            $currentDateTime = $reservation->reservation_date;
            $currentPartySize = $reservation->party_size;
            $currentTable = $reservation->table;

            // Check if we need to find a new table
            $targetDateTime = $newDateTime ?? $currentDateTime;
            $targetPartySize = $newPartySize ?? $currentPartySize;
            $targetTable = $newTable;

            if (!$targetTable && ($newDateTime || $newPartySize)) {
                // Need to find a new table
                $availableTables = $reservation->restaurant->getAvailableTables(
                    $targetDateTime,
                    $targetPartySize,
                    $reservation->duration
                );

                if ($availableTables->isEmpty()) {
                    throw new \Exception('No tables available for the requested changes');
                }

                $targetTable = $this->tableOptimizer->selectBestTable(
                    $availableTables,
                    $targetPartySize,
                    $changes['preferences'] ?? []
                );
            } else {
                $targetTable = $targetTable ?? $currentTable;
            }

            // Update reservation
            $reservation->update([
                'reservation_date' => $targetDateTime,
                'party_size' => $targetPartySize,
                'table_id' => $targetTable->id,
                'special_requests' => $changes['special_requests'] ?? $reservation->special_requests,
                'dietary_restrictions' => $changes['dietary_restrictions'] ?? $reservation->dietary_restrictions,
                'modified_at' => now(),
            ]);

            // Update booking if time or table changed
            if ($newDateTime || $newTable) {
                $endTime = $targetDateTime->copy()->addMinutes($reservation->duration);
                $periods = PeriodCollection::make([
                    Period::make($targetDateTime, $endTime)
                ]);

                $this->bookResource->run(
                    periods: $periods,
                    bookableResource: $targetTable->bookableResource,
                    booker: $reservation->customer,
                    booking: $reservation->booking,
                    meta: array_merge($reservation->booking->meta->toArray(), [
                        'modified_at' => now()->toISOString(),
                        'modification_reason' => 'Customer requested change',
                        'previous_table' => $currentTable->number,
                        'previous_datetime' => $currentDateTime->toISOString(),
                    ])
                );
            }

            // Send modification notification
            $this->sendReservationModificationNotification($reservation, [
                'datetime_changed' => $newDateTime !== null,
                'party_size_changed' => $newPartySize !== null,
                'table_changed' => $newTable !== null,
            ]);

            return $reservation->fresh();
        });
    }

    public function cancelReservation(
        RestaurantReservation $reservation,
        string $reason = null,
        bool $customerInitiated = true
    ): void {
        DB::transaction(function () use ($reservation, $reason, $customerInitiated) {
            // Update reservation status
            $reservation->update([
                'status' => 'cancelled',
                'cancellation_reason' => $reason,
                'cancelled_at' => now(),
                'cancelled_by_customer' => $customerInitiated,
            ]);

            // Remove booking periods to free up the table
            $reservation->booking->bookedPeriods()->delete();

            // Notify waitlisted customers if there's a waitlist
            $this->notifyWaitlist($reservation->restaurant, $reservation->reservation_date, $reservation->party_size);

            // Send cancellation confirmation
            if ($customerInitiated) {
                $this->sendCancellationConfirmation($reservation);
            }

            event(new ReservationCancelled($reservation, $reason, $customerInitiated));
        });
    }

    public function addToWaitlist(
        Restaurant $restaurant,
        Customer $customer,
        Carbon $requestedDateTime,
        int $partySize,
        array $preferences = []
    ): WaitlistEntry {
        return WaitlistEntry::create([
            'restaurant_id' => $restaurant->id,
            'customer_id' => $customer->id,
            'requested_date' => $requestedDateTime,
            'party_size' => $partySize,
            'preferences' => $preferences,
            'status' => 'waiting',
            'priority_score' => $this->calculateWaitlistPriority($customer, $restaurant),
            'notification_methods' => ['email', 'sms'],
            'expires_at' => now()->addHours(24), // Waitlist entry expires after 24 hours
        ]);
    }

    public function checkInReservation(RestaurantReservation $reservation, array $checkInData = []): void
    {
        $reservation->update([
            'status' => 'seated',
            'actual_arrival_time' => now(),
            'check_in_notes' => $checkInData['notes'] ?? null,
            'table_ready_time' => $checkInData['table_ready_time'] ?? now(),
        ]);

        // Update table status
        $reservation->table->update(['current_status' => 'occupied']);

        event(new CustomerSeated($reservation, $checkInData));
    }

    public function completeReservation(RestaurantReservation $reservation, array $completionData = []): void
    {
        $reservation->update([
            'status' => 'completed',
            'actual_departure_time' => now(),
            'bill_amount' => $completionData['bill_amount'] ?? null,
            'tip_amount' => $completionData['tip_amount'] ?? null,
            'payment_method' => $completionData['payment_method'] ?? null,
            'customer_satisfaction' => $completionData['satisfaction_rating'] ?? null,
            'staff_notes' => $completionData['staff_notes'] ?? null,
        ]);

        // Free up the table
        $reservation->table->update(['current_status' => 'cleaning']);

        // Update customer stats
        $customer = $reservation->customer;
        $customer->update([
            'last_visit_date' => now(),
            'total_visits' => $customer->total_visits + 1,
            'total_spent' => $customer->total_spent + ($completionData['bill_amount'] ?? 0),
        ]);

        // Remove booking periods
        $reservation->booking->bookedPeriods()->delete();

        event(new ReservationCompleted($reservation, $completionData));
    }

    public function handleNoShow(RestaurantReservation $reservation): void
    {
        $reservation->update([
            'status' => 'no_show',
            'no_show_time' => now(),
        ]);

        // Free up the table
        $reservation->booking->bookedPeriods()->delete();

        // Apply no-show penalty to customer (if applicable)
        $this->applyNoShowPenalty($reservation->customer);

        // Notify waitlisted customers
        $this->notifyWaitlist(
            $reservation->restaurant,
            $reservation->reservation_date,
            $reservation->party_size
        );

        event(new CustomerNoShow($reservation));
    }

    private function suggestAlternativeTimes(Restaurant $restaurant, Carbon $requestedTime, int $partySize): array
    {
        $alternatives = [];
        $date = $requestedTime->copy()->startOfDay();

        // Check earlier times on the same day
        for ($i = 1; $i <= 3; $i++) {
            $altTime = $requestedTime->copy()->subMinutes(30 * $i);
            if ($altTime->isAfter(now()) && $restaurant->isOpenAt($altTime)) {
                $tables = $restaurant->getAvailableTables($altTime, $partySize);
                if ($tables->count() > 0) {
                    $alternatives[] = [
                        'datetime' => $altTime,
                        'available_tables' => $tables->count(),
                        'suggestion_type' => 'earlier_same_day',
                    ];
                }
            }
        }

        // Check later times on the same day
        for ($i = 1; $i <= 3; $i++) {
            $altTime = $requestedTime->copy()->addMinutes(30 * $i);
            if ($restaurant->isOpenAt($altTime)) {
                $tables = $restaurant->getAvailableTables($altTime, $partySize);
                if ($tables->count() > 0) {
                    $alternatives[] = [
                        'datetime' => $altTime,
                        'available_tables' => $tables->count(),
                        'suggestion_type' => 'later_same_day',
                    ];
                }
            }
        }

        // Check same time on adjacent days
        for ($i = 1; $i <= 3; $i++) {
            $altDate = $requestedTime->copy()->addDays($i);
            if ($restaurant->isOpenAt($altDate)) {
                $tables = $restaurant->getAvailableTables($altDate, $partySize);
                if ($tables->count() > 0) {
                    $alternatives[] = [
                        'datetime' => $altDate,
                        'available_tables' => $tables->count(),
                        'suggestion_type' => 'next_days',
                    ];
                }
            }
        }

        return collect($alternatives)->take(5)->toArray();
    }

    private function calculateWaitlistPriority(Customer $customer, Restaurant $restaurant): int
    {
        $score = 0;

        // VIP customers get higher priority
        if ($customer->isVip()) {
            $score += 100;
        }

        // Loyal customers
        $score += min($customer->total_visits * 2, 50);

        // Recent customers
        if ($customer->last_visit_date && $customer->last_visit_date->diffInDays(now()) <= 30) {
            $score += 25;
        }

        // Random factor to ensure fairness
        $score += rand(1, 20);

        return $score;
    }

    private function processPreOrders(RestaurantReservation $reservation, array $preOrders): void
    {
        foreach ($preOrders as $item) {
            ReservationPreOrder::create([
                'reservation_id' => $reservation->id,
                'menu_item_id' => $item['menu_item_id'],
                'quantity' => $item['quantity'],
                'special_instructions' => $item['special_instructions'] ?? null,
                'price' => $item['price'],
            ]);
        }
    }

    private function sendReservationConfirmation(RestaurantReservation $reservation): void
    {
        $this->notificationService->sendReservationConfirmation($reservation);
        $reservation->update(['confirmation_sent' => true]);
    }

    private function updateCustomerStats(Customer $customer, int $partySize): void
    {
        $totalReservations = $customer->reservations()->count();
        $averagePartySize = $customer->reservations()->avg('party_size');

        $customer->update([
            'average_party_size' => round($averagePartySize, 1),
        ]);
    }

    private function notifyWaitlist(Restaurant $restaurant, Carbon $dateTime, int $partySize): void
    {
        $waitlistEntries = WaitlistEntry::where('restaurant_id', $restaurant->id)
            ->where('requested_date', '>=', $dateTime->subHours(2))
            ->where('requested_date', '<=', $dateTime->addHours(2))
            ->where('party_size', '<=', $partySize + 2) // Allow some flexibility
            ->where('status', 'waiting')
            ->orderBy('priority_score', 'desc')
            ->limit(3)
            ->get();

        foreach ($waitlistEntries as $entry) {
            $this->notificationService->notifyWaitlistAvailability($entry);
            $entry->update(['notified_at' => now()]);
        }
    }

    private function applyNoShowPenalty(Customer $customer): void
    {
        // Simple penalty system - could be more sophisticated
        $recentNoShows = $customer->reservations()
            ->where('status', 'no_show')
            ->where('reservation_date', '>=', now()->subDays(90))
            ->count();

        if ($recentNoShows >= 3) {
            $customer->update(['notes' => 'Multiple recent no-shows - requires deposit for future reservations']);
        }
    }
}
```

## Frontend Integration

### Reservation Search Component

```vue
<template>
  <div class="restaurant-reservations">
    <!-- Search Form -->
    <div class="search-form">
      <h2>Make a Reservation</h2>
      <form @submit.prevent="searchAvailability">
        <div class="form-row">
          <div class="form-group">
            <label>Date</label>
            <input 
              v-model="searchForm.date" 
              type="date" 
              :min="minDate"
              :max="maxDate"
              required
            >
          </div>
          <div class="form-group">
            <label>Time</label>
            <select v-model="searchForm.time" required>
              <option value="">Select time</option>
              <option v-for="slot in timeSlots" :key="slot" :value="slot">
                {{ slot }}
              </option>
            </select>
          </div>
          <div class="form-group">
            <label>Party Size</label>
            <select v-model="searchForm.partySize" required>
              <option v-for="n in 12" :key="n" :value="n">
                {{ n }} {{ n === 1 ? 'guest' : 'guests' }}
              </option>
            </select>
          </div>
        </div>
        
        <div class="preferences">
          <h4>Seating Preferences (Optional)</h4>
          <div class="preference-options">
            <label class="checkbox">
              <input v-model="searchForm.preferences.window" type="checkbox">
              Window seating
            </label>
            <label class="checkbox">
              <input v-model="searchForm.preferences.quiet" type="checkbox">
              Quiet area
            </label>
            <label class="checkbox">
              <input v-model="searchForm.preferences.accessible" type="checkbox">
              Wheelchair accessible
            </label>
          </div>
        </div>

        <button type="submit" :disabled="searching" class="search-btn">
          {{ searching ? 'Searching...' : 'Find Tables' }}
        </button>
      </form>
    </div>

    <!-- Availability Results -->
    <div v-if="searchResults" class="availability-results">
      <div v-if="searchResults.available" class="available">
        <h3>Tables Available</h3>
        
        <div class="recommended-table" v-if="searchResults.recommended_table">
          <h4>Recommended Table</h4>
          <div class="table-card recommended">
            <div class="table-info">
              <span class="table-name">{{ searchResults.recommended_table.display_name }}</span>
              <span class="table-capacity">Seats {{ searchResults.recommended_table.capacity }}</span>
              <span class="table-location">{{ searchResults.recommended_table.location_description }}</span>
            </div>
            <div class="table-features">
              <span 
                v-for="feature in searchResults.recommended_table.features" 
                :key="feature"
                class="feature-tag"
              >
                {{ feature }}
              </span>
            </div>
            <button @click="selectTable(searchResults.recommended_table)" class="select-btn">
              Select This Table
            </button>
          </div>
        </div>

        <div v-if="searchResults.alternative_tables.length > 0" class="alternative-tables">
          <h4>Other Available Tables</h4>
          <div class="table-grid">
            <div 
              v-for="table in searchResults.alternative_tables" 
              :key="table.id"
              class="table-card"
            >
              <div class="table-info">
                <span class="table-name">{{ table.display_name }}</span>
                <span class="table-capacity">Seats {{ table.capacity }}</span>
                <span class="table-location">{{ table.location_description }}</span>
              </div>
              <div class="table-features">
                <span 
                  v-for="feature in table.features" 
                  :key="feature"
                  class="feature-tag"
                >
                  {{ feature }}
                </span>
              </div>
              <button @click="selectTable(table)" class="select-btn">
                Select
              </button>
            </div>
          </div>
        </div>
      </div>

      <div v-else class="unavailable">
        <h3>No Tables Available</h3>
        <p>{{ searchResults.reason }}</p>
        
        <div v-if="searchResults.waitlist_available" class="waitlist-option">
          <p>Would you like to join our waitlist? We'll notify you if a table becomes available.</p>
          <button @click="showWaitlistForm = true" class="waitlist-btn">
            Join Waitlist
          </button>
        </div>

        <div v-if="searchResults.alternative_times.length > 0" class="alternative-times">
          <h4>Available Times</h4>
          <div class="time-alternatives">
            <button 
              v-for="alt in searchResults.alternative_times" 
              :key="alt.datetime"
              @click="selectAlternativeTime(alt)"
              class="alt-time-btn"
            >
              {{ formatDateTime(alt.datetime) }}
              <small>({{ alt.available_tables }} tables)</small>
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Reservation Form -->
    <div v-if="selectedTable" class="reservation-form">
      <h3>Complete Your Reservation</h3>
      
      <div class="reservation-summary">
        <h4>Reservation Details</h4>
        <div class="summary-item">
          <span>Date & Time:</span>
          <span>{{ formatDateTime(reservationDateTime) }}</span>
        </div>
        <div class="summary-item">
          <span>Party Size:</span>
          <span>{{ searchForm.partySize }} guests</span>
        </div>
        <div class="summary-item">
          <span>Table:</span>
          <span>{{ selectedTable.display_name }}</span>
        </div>
        <div class="summary-item">
          <span>Duration:</span>
          <span>2 hours</span>
        </div>
      </div>

      <form @submit.prevent="createReservation" class="customer-form">
        <h4>Contact Information</h4>
        <div class="form-row">
          <div class="form-group">
            <label>First Name *</label>
            <input v-model="reservationForm.customer.first_name" type="text" required>
          </div>
          <div class="form-group">
            <label>Last Name *</label>
            <input v-model="reservationForm.customer.last_name" type="text" required>
          </div>
        </div>
        
        <div class="form-row">
          <div class="form-group">
            <label>Email *</label>
            <input v-model="reservationForm.customer.email" type="email" required>
          </div>
          <div class="form-group">
            <label>Phone *</label>
            <input v-model="reservationForm.customer.phone" type="tel" required>
          </div>
        </div>

        <div class="special-requests">
          <h4>Special Requests</h4>
          <div class="form-group">
            <label>Occasion (Optional)</label>
            <select v-model="reservationForm.occasion">
              <option value="">None</option>
              <option value="birthday">Birthday</option>
              <option value="anniversary">Anniversary</option>
              <option value="business">Business dinner</option>
              <option value="date">Date night</option>
              <option value="celebration">Celebration</option>
            </select>
          </div>
          
          <div class="form-group">
            <label>Dietary Restrictions</label>
            <div class="checkbox-group">
              <label class="checkbox">
                <input v-model="reservationForm.dietary_restrictions" value="vegetarian" type="checkbox">
                Vegetarian
              </label>
              <label class="checkbox">
                <input v-model="reservationForm.dietary_restrictions" value="vegan" type="checkbox">
                Vegan
              </label>
              <label class="checkbox">
                <input v-model="reservationForm.dietary_restrictions" value="gluten_free" type="checkbox">
                Gluten-free
              </label>
              <label class="checkbox">
                <input v-model="reservationForm.dietary_restrictions" value="nut_allergy" type="checkbox">
                Nut allergy
              </label>
            </div>
          </div>

          <div class="form-group">
            <label>Special Requests</label>
            <textarea 
              v-model="reservationForm.special_requests"
              placeholder="Any special requests or notes..."
              rows="3"
            ></textarea>
          </div>

          <div class="additional-options">
            <label class="checkbox">
              <input v-model="reservationForm.high_chair_needed" type="checkbox">
              High chair needed
            </label>
            <label class="checkbox">
              <input v-model="reservationForm.accessibility_needs" type="checkbox">
              Wheelchair accessibility required
            </label>
          </div>
        </div>

        <button type="submit" :disabled="booking" class="confirm-btn">
          {{ booking ? 'Creating Reservation...' : 'Confirm Reservation' }}
        </button>
      </form>
    </div>

    <!-- Waitlist Form -->
    <div v-if="showWaitlistForm" class="waitlist-form modal">
      <div class="modal-content">
        <h3>Join Waitlist</h3>
        <form @submit.prevent="joinWaitlist">
          <div class="form-group">
            <label>Name *</label>
            <input v-model="waitlistForm.name" type="text" required>
          </div>
          <div class="form-group">
            <label>Phone *</label>
            <input v-model="waitlistForm.phone" type="tel" required>
          </div>
          <div class="form-group">
            <label>Email</label>
            <input v-model="waitlistForm.email" type="email">
          </div>
          <div class="notification-preferences">
            <h4>How would you like to be notified?</h4>
            <label class="checkbox">
              <input v-model="waitlistForm.notifications" value="sms" type="checkbox">
              Text message
            </label>
            <label class="checkbox">
              <input v-model="waitlistForm.notifications" value="email" type="checkbox">
              Email
            </label>
          </div>
          <div class="modal-actions">
            <button type="button" @click="showWaitlistForm = false" class="cancel-btn">
              Cancel
            </button>
            <button type="submit" class="confirm-btn">
              Join Waitlist
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- Confirmation -->
    <div v-if="reservationConfirmation" class="confirmation">
      <div class="confirmation-card">
        <h3>✓ Reservation Confirmed!</h3>
        <div class="confirmation-details">
          <p><strong>Confirmation Number:</strong> {{ reservationConfirmation.confirmation_number }}</p>
          <p><strong>Restaurant:</strong> {{ restaurant.name }}</p>
          <p><strong>Date:</strong> {{ formatDate(reservationConfirmation.date) }}</p>
          <p><strong>Time:</strong> {{ reservationConfirmation.time }}</p>
          <p><strong>Party Size:</strong> {{ reservationConfirmation.party_size }} guests</p>
          <p><strong>Table:</strong> {{ reservationConfirmation.table_name }}</p>
        </div>
        <div class="confirmation-actions">
          <button @click="addToCalendar" class="add-calendar-btn">
            Add to Calendar
          </button>
          <button @click="resetForm" class="new-reservation-btn">
            Make Another Reservation
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
export default {
  name: 'RestaurantReservations',
  
  props: {
    restaurant: {
      type: Object,
      required: true
    }
  },

  data() {
    return {
      searching: false,
      booking: false,
      showWaitlistForm: false,
      selectedTable: null,
      searchResults: null,
      reservationConfirmation: null,
      
      searchForm: {
        date: '',
        time: '',
        partySize: 2,
        preferences: {
          window: false,
          quiet: false,
          accessible: false,
        }
      },
      
      reservationForm: {
        customer: {
          first_name: '',
          last_name: '',
          email: '',
          phone: ''
        },
        occasion: '',
        dietary_restrictions: [],
        special_requests: '',
        high_chair_needed: false,
        accessibility_needs: false,
      },

      waitlistForm: {
        name: '',
        phone: '',
        email: '',
        notifications: ['sms']
      }
    };
  },

  computed: {
    minDate() {
      return new Date().toISOString().split('T')[0];
    },
    
    maxDate() {
      const maxDate = new Date();
      maxDate.setDate(maxDate.getDate() + (this.restaurant.advance_booking_days || 60));
      return maxDate.toISOString().split('T')[0];
    },
    
    timeSlots() {
      // Generate time slots based on restaurant hours
      const slots = [];
      for (let hour = 17; hour <= 21; hour++) {
        for (let minute = 0; minute < 60; minute += 30) {
          slots.push(`${hour.toString().padStart(2, '0')}:${minute.toString().padStart(2, '0')}`);
        }
      }
      return slots;
    },
    
    reservationDateTime() {
      if (!this.searchForm.date || !this.searchForm.time) return null;
      return `${this.searchForm.date}T${this.searchForm.time}:00`;
    }
  },

  methods: {
    async searchAvailability() {
      this.searching = true;
      this.searchResults = null;
      this.selectedTable = null;
      
      try {
        const response = await fetch(`/api/restaurants/${this.restaurant.id}/availability`, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
          },
          body: JSON.stringify({
            datetime: this.reservationDateTime,
            party_size: this.searchForm.partySize,
            preferences: this.searchForm.preferences
          })
        });
        
        const data = await response.json();
        this.searchResults = data;
        
      } catch (error) {
        console.error('Search failed:', error);
        alert('Failed to search availability. Please try again.');
      } finally {
        this.searching = false;
      }
    },

    selectTable(table) {
      this.selectedTable = table;
      this.scrollToReservationForm();
    },

    selectAlternativeTime(alternative) {
      const dateTime = new Date(alternative.datetime);
      this.searchForm.date = dateTime.toISOString().split('T')[0];
      this.searchForm.time = dateTime.toTimeString().slice(0, 5);
      this.searchAvailability();
    },

    async createReservation() {
      this.booking = true;
      
      const reservationData = {
        customer: this.reservationForm.customer,
        datetime: this.reservationDateTime,
        party_size: this.searchForm.partySize,
        table_id: this.selectedTable.id,
        occasion: this.reservationForm.occasion,
        dietary_restrictions: this.reservationForm.dietary_restrictions,
        special_requests: this.reservationForm.special_requests,
        high_chair_needed: this.reservationForm.high_chair_needed,
        accessibility_needs: this.reservationForm.accessibility_needs,
        preferences: this.searchForm.preferences,
      };

      try {
        const response = await fetch(`/api/restaurants/${this.restaurant.id}/reservations`, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
          },
          body: JSON.stringify(reservationData)
        });
        
        const data = await response.json();
        
        if (data.success) {
          this.reservationConfirmation = data.reservation;
          this.scrollToConfirmation();
        } else {
          alert(data.message || 'Reservation failed. Please try again.');
        }
        
      } catch (error) {
        console.error('Booking failed:', error);
        alert('Reservation failed. Please try again.');
      } finally {
        this.booking = false;
      }
    },

    async joinWaitlist() {
      try {
        const response = await fetch(`/api/restaurants/${this.restaurant.id}/waitlist`, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
          },
          body: JSON.stringify({
            ...this.waitlistForm,
            datetime: this.reservationDateTime,
            party_size: this.searchForm.partySize,
          })
        });
        
        const data = await response.json();
        
        if (data.success) {
          alert('You have been added to the waitlist. We will notify you if a table becomes available.');
          this.showWaitlistForm = false;
        } else {
          alert('Failed to join waitlist. Please try again.');
        }
        
      } catch (error) {
        console.error('Waitlist failed:', error);
        alert('Failed to join waitlist. Please try again.');
      }
    },

    formatDateTime(datetime) {
      const date = new Date(datetime);
      return date.toLocaleDateString() + ' at ' + date.toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});
    },

    formatDate(dateString) {
      return new Date(dateString).toLocaleDateString();
    },

    addToCalendar() {
      // Generate calendar event
      const event = {
        title: `Dinner at ${this.restaurant.name}`,
        start: new Date(this.reservationConfirmation.datetime),
        duration: 120, // 2 hours
        description: `Reservation for ${this.reservationConfirmation.party_size} at ${this.restaurant.name}`,
      };
      
      // Create calendar link (simplified)
      const startDate = event.start.toISOString().replace(/[:.]/g, '').slice(0, -4) + 'Z';
      const endDate = new Date(event.start.getTime() + event.duration * 60000).toISOString().replace(/[:.]/g, '').slice(0, -4) + 'Z';
      
      const calendarUrl = `https://calendar.google.com/calendar/render?action=TEMPLATE&text=${encodeURIComponent(event.title)}&dates=${startDate}/${endDate}&details=${encodeURIComponent(event.description)}`;
      
      window.open(calendarUrl, '_blank');
    },

    resetForm() {
      this.searchForm = {
        date: '',
        time: '',
        partySize: 2,
        preferences: { window: false, quiet: false, accessible: false }
      };
      this.reservationForm = {
        customer: { first_name: '', last_name: '', email: '', phone: '' },
        occasion: '',
        dietary_restrictions: [],
        special_requests: '',
        high_chair_needed: false,
        accessibility_needs: false,
      };
      this.selectedTable = null;
      this.searchResults = null;
      this.reservationConfirmation = null;
    },

    scrollToReservationForm() {
      this.$nextTick(() => {
        document.querySelector('.reservation-form').scrollIntoView({ behavior: 'smooth' });
      });
    },

    scrollToConfirmation() {
      this.$nextTick(() => {
        document.querySelector('.confirmation').scrollIntoView({ behavior: 'smooth' });
      });
    }
  }
};
</script>
```

This comprehensive restaurant reservation example demonstrates sophisticated features like table optimization, waitlist management, customer preferences, dietary restrictions, and a complete booking workflow with alternative time suggestions.