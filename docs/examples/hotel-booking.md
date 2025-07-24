# Hotel Booking System Example

This comprehensive example demonstrates how to build a complete hotel booking system using Laravel Bookings, including rooms, guests, services, and advanced features.

## Overview

This example covers:
- Hotel and room management
- Guest registration and management
- Multi-room bookings
- Service bookings (spa, restaurant)
- Pricing and availability
- Check-in/check-out workflow
- Payment integration
- Reporting and analytics

## Database Models

### Hotel Model

```php
<?php
// app/Models/Hotel.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Masterix21\Bookings\Models\Concerns\IsBookable;
use Masterix21\Bookings\Models\Concerns\Bookable;

class Hotel extends Model implements Bookable
{
    use IsBookable;

    protected $fillable = [
        'name',
        'address',
        'city',
        'country',
        'postal_code',
        'phone',
        'email',
        'website',
        'stars',
        'description',
        'check_in_time',
        'check_out_time',
        'currency',
        'tax_rate',
    ];

    protected $casts = [
        'stars' => 'integer',
        'tax_rate' => 'decimal:4',
        'check_in_time' => 'datetime:H:i',
        'check_out_time' => 'datetime:H:i',
    ];

    public function rooms(): HasMany
    {
        return $this->hasMany(Room::class);
    }

    public function services(): HasMany
    {
        return $this->hasMany(HotelService::class);
    }

    public function getAvailableRooms(Carbon $checkIn, Carbon $checkOut): Collection
    {
        return $this->rooms()
            ->whereHas('bookableResource', function ($query) {
                $query->where('is_bookable', true)->where('is_visible', true);
            })
            ->get()
            ->filter(function ($room) use ($checkIn, $checkOut) {
                return $room->isAvailableForPeriod($checkIn, $checkOut);
            });
    }
}
```

### Room Model

```php
<?php
// app/Models/Room.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Masterix21\Bookings\Models\Concerns\IsBookable;
use Masterix21\Bookings\Models\Concerns\Bookable;
use Carbon\Carbon;

class Room extends Model implements Bookable
{
    use IsBookable;

    protected $fillable = [
        'hotel_id',
        'number',
        'floor',
        'type',
        'capacity',
        'beds',
        'bathroom_type',
        'size_sqm',
        'base_price_per_night',
        'amenities',
        'description',
        'status',
        'last_maintenance',
    ];

    protected $casts = [
        'capacity' => 'integer',
        'beds' => 'integer',
        'size_sqm' => 'decimal:2',
        'base_price_per_night' => 'decimal:2',
        'amenities' => 'array',
        'last_maintenance' => 'datetime',
    ];

    public function hotel(): BelongsTo
    {
        return $this->belongsTo(Hotel::class);
    }

    public function getPriceForPeriod(Carbon $checkIn, Carbon $checkOut): float
    {
        $nights = $checkIn->diffInDays($checkOut);
        $basePrice = $this->base_price_per_night * $nights;
        
        // Apply seasonal pricing
        $seasonalMultiplier = $this->getSeasonalMultiplier($checkIn, $checkOut);
        
        return $basePrice * $seasonalMultiplier;
    }

    public function isAvailableForPeriod(Carbon $checkIn, Carbon $checkOut): bool
    {
        if (!$this->bookableResource) {
            return false;
        }

        $periods = PeriodCollection::make([
            Period::make($checkIn, $checkOut)
        ]);

        return (new CheckBookingOverlaps())->run(
            periods: $periods,
            bookableResource: $this->bookableResource,
            emitEvent: false,
            throw: false
        );
    }

    private function getSeasonalMultiplier(Carbon $checkIn, Carbon $checkOut): float
    {
        // Peak season: July-August, December
        $peakMonths = [7, 8, 12];
        
        $avgMonth = $checkIn->copy()->addDays($checkIn->diffInDays($checkOut) / 2)->month;
        
        if (in_array($avgMonth, $peakMonths)) {
            return 1.5; // 50% increase
        }
        
        // Shoulder season: May-June, September-October
        $shoulderMonths = [5, 6, 9, 10];
        if (in_array($avgMonth, $shoulderMonths)) {
            return 1.2; // 20% increase
        }
        
        return 1.0; // Base price
    }
}
```

### Guest Model

```php
<?php
// app/Models/Guest.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Masterix21\Bookings\Models\Concerns\HasBookings;

class Guest extends Model
{
    use HasBookings;

    protected $fillable = [
        'first_name',
        'last_name',
        'email',
        'phone',
        'date_of_birth',
        'nationality',
        'passport_number',
        'address',
        'city',
        'country',
        'postal_code',
        'preferences',
        'vip_status',
        'loyalty_points',
    ];

    protected $casts = [
        'date_of_birth' => 'date',
        'preferences' => 'array',
        'vip_status' => 'boolean',
        'loyalty_points' => 'integer',
    ];

    public function getFullNameAttribute(): string
    {
        return "{$this->first_name} {$this->last_name}";
    }

    public function isVip(): bool
    {
        return $this->vip_status || $this->loyalty_points >= 10000;
    }

    public function getBookingHistory(): Collection
    {
        return $this->bookings()
            ->with(['bookedPeriods.bookableResource.resource'])
            ->orderBy('created_at', 'desc')
            ->get();
    }
}
```

## Service Layer

### Hotel Booking Service

```php
<?php
// app/Services/HotelBookingService.php

namespace App\Services;

use App\Models\Hotel;
use App\Models\Room;
use App\Models\Guest;
use App\Models\HotelBooking;
use Masterix21\Bookings\Actions\BookResource;
use Masterix21\Bookings\Models\Booking;
use Carbon\Carbon;
use Spatie\Period\Period;
use Spatie\Period\PeriodCollection;
use Illuminate\Support\Facades\DB;

class HotelBookingService
{
    public function __construct(
        private BookResource $bookResource,
        private PricingService $pricingService,
        private AvailabilityService $availabilityService
    ) {}

    public function searchAvailability(
        Hotel $hotel,
        Carbon $checkIn,
        Carbon $checkOut,
        int $guests = 1,
        ?string $roomType = null,
        array $filters = []
    ): Collection {
        return $hotel->getAvailableRooms($checkIn, $checkOut)
            ->when($roomType, fn($rooms) => $rooms->where('type', $roomType))
            ->where('capacity', '>=', $guests)
            ->when($filters['min_price'] ?? null, fn($rooms) => 
                $rooms->filter(fn($room) => 
                    $room->getPriceForPeriod($checkIn, $checkOut) >= $filters['min_price']
                )
            )
            ->when($filters['max_price'] ?? null, fn($rooms) => 
                $rooms->filter(fn($room) => 
                    $room->getPriceForPeriod($checkIn, $checkOut) <= $filters['max_price']
                )
            )
            ->when($filters['amenities'] ?? null, fn($rooms) => 
                $rooms->filter(fn($room) => 
                    count(array_intersect($room->amenities, $filters['amenities'])) > 0
                )
            );
    }

    public function createReservation(
        Guest $guest,
        array $roomSelections,
        Carbon $checkIn,
        Carbon $checkOut,
        array $services = [],
        array $guestDetails = []
    ): HotelBooking {
        return DB::transaction(function () use ($guest, $roomSelections, $checkIn, $checkOut, $services, $guestDetails) {
            $hotelBooking = HotelBooking::create([
                'guest_id' => $guest->id,
                'hotel_id' => $roomSelections[0]['room']->hotel_id,
                'check_in' => $checkIn,
                'check_out' => $checkOut,
                'status' => 'confirmed',
                'total_amount' => 0,
                'currency' => 'USD',
                'guest_details' => $guestDetails,
            ]);

            $totalAmount = 0;
            $periods = PeriodCollection::make([Period::make($checkIn, $checkOut)]);

            // Book each room
            foreach ($roomSelections as $selection) {
                $room = $selection['room'];
                $roomPrice = $room->getPriceForPeriod($checkIn, $checkOut);
                
                $booking = $this->bookResource->run(
                    periods: $periods,
                    bookableResource: $room->bookableResource,
                    booker: $guest,
                    relatable: $hotelBooking,
                    label: "Hotel Stay - Room {$room->number}",
                    note: $selection['special_requests'] ?? null,
                    meta: [
                        'hotel_booking_id' => $hotelBooking->id,
                        'room_number' => $room->number,
                        'room_type' => $room->type,
                        'guests_count' => $selection['guests'],
                        'nights' => $checkIn->diffInDays($checkOut),
                        'room_price' => $roomPrice,
                        'special_requests' => $selection['special_requests'] ?? null,
                    ]
                );

                $totalAmount += $roomPrice;
            }

            // Book additional services
            foreach ($services as $serviceBooking) {
                $servicePrice = $this->bookService($serviceBooking, $hotelBooking, $guest);
                $totalAmount += $servicePrice;
            }

            // Apply discounts and taxes
            $totalAmount = $this->pricingService->applyDiscountsAndTaxes(
                $totalAmount,
                $guest,
                $hotelBooking
            );

            $hotelBooking->update(['total_amount' => $totalAmount]);

            return $hotelBooking;
        });
    }

    public function modifyReservation(
        HotelBooking $hotelBooking,
        ?Carbon $newCheckIn = null,
        ?Carbon $newCheckOut = null,
        array $roomChanges = [],
        array $serviceChanges = []
    ): HotelBooking {
        return DB::transaction(function () use ($hotelBooking, $newCheckIn, $newCheckOut, $roomChanges, $serviceChanges) {
            $checkIn = $newCheckIn ?? $hotelBooking->check_in;
            $checkOut = $newCheckOut ?? $hotelBooking->check_out;

            // Update date if changed
            if ($newCheckIn || $newCheckOut) {
                $hotelBooking->update([
                    'check_in' => $checkIn,
                    'check_out' => $checkOut,
                ]);

                // Update all room bookings with new dates
                $newPeriods = PeriodCollection::make([Period::make($checkIn, $checkOut)]);
                
                foreach ($hotelBooking->roomBookings as $roomBooking) {
                    $this->bookResource->run(
                        periods: $newPeriods,
                        bookableResource: $roomBooking->bookedPeriods->first()->bookableResource,
                        booker: $hotelBooking->guest,
                        booking: $roomBooking,
                        meta: array_merge($roomBooking->meta->toArray(), [
                            'modified_at' => now()->toISOString(),
                            'modification_reason' => 'Date change',
                        ])
                    );
                }
            }

            // Handle room changes
            foreach ($roomChanges as $change) {
                $this->handleRoomChange($change, $hotelBooking);
            }

            // Recalculate total
            $this->recalculateTotal($hotelBooking);

            return $hotelBooking->fresh();
        });
    }

    public function cancelReservation(
        HotelBooking $hotelBooking,
        string $reason = null,
        bool $applyPenalty = true
    ): array {
        return DB::transaction(function () use ($hotelBooking, $reason, $applyPenalty) {
            $cancellationFee = 0;

            if ($applyPenalty) {
                $cancellationFee = $this->calculateCancellationFee($hotelBooking);
            }

            // Cancel all related bookings
            foreach ($hotelBooking->allBookings as $booking) {
                $booking->bookedPeriods()->delete();
                $booking->update([
                    'meta' => $booking->meta->merge([
                        'cancelled_at' => now()->toISOString(),
                        'cancellation_reason' => $reason,
                        'status' => 'cancelled',
                    ])
                ]);
            }

            // Update hotel booking status
            $hotelBooking->update([
                'status' => 'cancelled',
                'cancelled_at' => now(),
                'cancellation_reason' => $reason,
                'cancellation_fee' => $cancellationFee,
            ]);

            return [
                'hotel_booking' => $hotelBooking,
                'cancellation_fee' => $cancellationFee,
                'refund_amount' => $hotelBooking->total_amount - $cancellationFee,
            ];
        });
    }

    public function checkIn(HotelBooking $hotelBooking, array $checkInData = []): void
    {
        $hotelBooking->update([
            'status' => 'checked_in',
            'actual_check_in' => now(),
            'check_in_notes' => $checkInData['notes'] ?? null,
        ]);

        // Update room status
        foreach ($hotelBooking->rooms as $room) {
            $room->update(['status' => 'occupied']);
        }

        event(new GuestCheckedIn($hotelBooking, $checkInData));
    }

    public function checkOut(HotelBooking $hotelBooking, array $checkOutData = []): array
    {
        $charges = $this->calculateAdditionalCharges($hotelBooking, $checkOutData);
        
        $hotelBooking->update([
            'status' => 'checked_out',
            'actual_check_out' => now(),
            'check_out_notes' => $checkOutData['notes'] ?? null,
            'additional_charges' => $charges['total'],
        ]);

        // Update room status
        foreach ($hotelBooking->rooms as $room) {
            $room->update(['status' => 'cleaning_required']);
        }

        event(new GuestCheckedOut($hotelBooking, $charges));

        return [
            'hotel_booking' => $hotelBooking,
            'additional_charges' => $charges,
            'final_amount' => $hotelBooking->total_amount + $charges['total'],
        ];
    }

    private function bookService(array $serviceData, HotelBooking $hotelBooking, Guest $guest): float
    {
        $service = HotelService::find($serviceData['service_id']);
        $serviceDate = Carbon::parse($serviceData['date']);
        $duration = $serviceData['duration'] ?? 60; // minutes

        $periods = PeriodCollection::make([
            Period::make(
                $serviceDate,
                $serviceDate->copy()->addMinutes($duration)
            )
        ]);

        $this->bookResource->run(
            periods: $periods,
            bookableResource: $service->bookableResource,
            booker: $guest,
            relatable: $hotelBooking,
            label: "Hotel Service - {$service->name}",
            meta: [
                'hotel_booking_id' => $hotelBooking->id,
                'service_type' => $service->type,
                'duration' => $duration,
                'price' => $service->price,
            ]
        );

        return $service->price;
    }

    private function calculateCancellationFee(HotelBooking $hotelBooking): float
    {
        $daysUntilCheckIn = now()->diffInDays($hotelBooking->check_in);
        
        return match (true) {
            $daysUntilCheckIn < 1 => $hotelBooking->total_amount, // 100% penalty
            $daysUntilCheckIn < 7 => $hotelBooking->total_amount * 0.5, // 50% penalty
            $daysUntilCheckIn < 14 => $hotelBooking->total_amount * 0.25, // 25% penalty
            default => 0, // Free cancellation
        };
    }

    private function calculateAdditionalCharges(HotelBooking $hotelBooking, array $checkOutData): array
    {
        $charges = [];
        $total = 0;

        // Minibar charges
        if ($minibarItems = $checkOutData['minibar'] ?? []) {
            $minibarTotal = collect($minibarItems)->sum('price');
            $charges['minibar'] = $minibarTotal;
            $total += $minibarTotal;
        }

        // Damage charges
        if ($damageCharges = $checkOutData['damages'] ?? []) {
            $damageTotal = collect($damageCharges)->sum('cost');
            $charges['damages'] = $damageTotal;
            $total += $damageTotal;
        }

        // Late checkout fee
        $checkOutTime = $hotelBooking->hotel->check_out_time;
        if (now()->format('H:i') > $checkOutTime) {
            $lateHours = now()->diffInHours($checkOutTime);
            $lateFee = $lateHours * 25; // $25 per hour
            $charges['late_checkout'] = $lateFee;
            $total += $lateFee;
        }

        $charges['total'] = $total;
        return $charges;
    }
}
```

## Frontend Integration

### Room Search API

```php
<?php
// app/Http/Controllers/Api/HotelController.php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Hotel;
use App\Services\HotelBookingService;
use Carbon\Carbon;
use Illuminate\Http\Request;

class HotelController extends Controller
{
    public function __construct(
        private HotelBookingService $bookingService
    ) {}

    public function searchRooms(Request $request, Hotel $hotel)
    {
        $request->validate([
            'check_in' => 'required|date|after:today',
            'check_out' => 'required|date|after:check_in',
            'guests' => 'integer|min:1|max:10',
            'room_type' => 'nullable|string',
            'min_price' => 'nullable|numeric|min:0',
            'max_price' => 'nullable|numeric|min:0',
            'amenities' => 'nullable|array',
        ]);

        $checkIn = Carbon::parse($request->check_in);
        $checkOut = Carbon::parse($request->check_out);

        $availableRooms = $this->bookingService->searchAvailability(
            hotel: $hotel,
            checkIn: $checkIn,
            checkOut: $checkOut,
            guests: $request->guests ?? 1,
            roomType: $request->room_type,
            filters: $request->only(['min_price', 'max_price', 'amenities'])
        );

        return response()->json([
            'hotel' => $hotel,
            'search_criteria' => [
                'check_in' => $checkIn->toDateString(),
                'check_out' => $checkOut->toDateString(),
                'nights' => $checkIn->diffInDays($checkOut),
                'guests' => $request->guests ?? 1,
            ],
            'available_rooms' => $availableRooms->map(function ($room) use ($checkIn, $checkOut) {
                return [
                    'id' => $room->id,
                    'number' => $room->number,
                    'type' => $room->type,
                    'capacity' => $room->capacity,
                    'beds' => $room->beds,
                    'size_sqm' => $room->size_sqm,
                    'amenities' => $room->amenities,
                    'description' => $room->description,
                    'price_per_night' => $room->base_price_per_night,
                    'total_price' => $room->getPriceForPeriod($checkIn, $checkOut),
                    'images' => $room->images ?? [],
                ];
            }),
            'total_available' => $availableRooms->count(),
        ]);
    }

    public function createBooking(Request $request, Hotel $hotel)
    {
        $request->validate([
            'guest' => 'required|array',
            'guest.first_name' => 'required|string|max:255',
            'guest.last_name' => 'required|string|max:255',
            'guest.email' => 'required|email',
            'guest.phone' => 'required|string',
            'rooms' => 'required|array|min:1',
            'rooms.*.room_id' => 'required|exists:rooms,id',
            'rooms.*.guests' => 'required|integer|min:1',
            'rooms.*.special_requests' => 'nullable|string',
            'check_in' => 'required|date|after:today',
            'check_out' => 'required|date|after:check_in',
            'services' => 'nullable|array',
        ]);

        $guest = Guest::firstOrCreate(
            ['email' => $request->guest['email']],
            $request->guest
        );

        $checkIn = Carbon::parse($request->check_in);
        $checkOut = Carbon::parse($request->check_out);

        $roomSelections = collect($request->rooms)->map(function ($roomData) {
            return [
                'room' => Room::findOrFail($roomData['room_id']),
                'guests' => $roomData['guests'],
                'special_requests' => $roomData['special_requests'] ?? null,
            ];
        })->toArray();

        try {
            $hotelBooking = $this->bookingService->createReservation(
                guest: $guest,
                roomSelections: $roomSelections,
                checkIn: $checkIn,
                checkOut: $checkOut,
                services: $request->services ?? [],
                guestDetails: $request->guest
            );

            return response()->json([
                'success' => true,
                'booking' => [
                    'id' => $hotelBooking->id,
                    'confirmation_code' => $hotelBooking->confirmation_code,
                    'guest_name' => $guest->full_name,
                    'hotel_name' => $hotel->name,
                    'check_in' => $hotelBooking->check_in->toDateString(),
                    'check_out' => $hotelBooking->check_out->toDateString(),
                    'nights' => $hotelBooking->check_in->diffInDays($hotelBooking->check_out),
                    'rooms_count' => count($roomSelections),
                    'total_amount' => $hotelBooking->total_amount,
                    'currency' => $hotelBooking->currency,
                    'status' => $hotelBooking->status,
                ],
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Booking could not be completed',
                'error' => $e->getMessage(),
            ], 422);
        }
    }
}
```

### Vue.js Room Search Component

```vue
<template>
  <div class="hotel-booking">
    <!-- Search Form -->
    <div class="search-form">
      <h2>Search Available Rooms</h2>
      <form @submit.prevent="searchRooms">
        <div class="form-group">
          <label>Check-in Date</label>
          <input v-model="searchForm.check_in" type="date" required>
        </div>
        
        <div class="form-group">
          <label>Check-out Date</label>
          <input v-model="searchForm.check_out" type="date" required>
        </div>
        
        <div class="form-group">
          <label>Guests</label>
          <select v-model="searchForm.guests">
            <option v-for="n in 10" :key="n" :value="n">{{ n }}</option>
          </select>
        </div>
        
        <button type="submit" :disabled="searching">
          {{ searching ? 'Searching...' : 'Search Rooms' }}
        </button>
      </form>
    </div>

    <!-- Available Rooms -->
    <div v-if="availableRooms.length > 0" class="available-rooms">
      <h3>Available Rooms ({{ availableRooms.length }})</h3>
      
      <div class="room-grid">
        <div 
          v-for="room in availableRooms" 
          :key="room.id" 
          class="room-card"
        >
          <div class="room-info">
            <h4>Room {{ room.number }} - {{ room.type }}</h4>
            <p>{{ room.description }}</p>
            <div class="room-details">
              <span>Capacity: {{ room.capacity }} guests</span>
              <span>Size: {{ room.size_sqm }}m²</span>
              <span>Beds: {{ room.beds }}</span>
            </div>
            <div class="amenities">
              <span 
                v-for="amenity in room.amenities" 
                :key="amenity"
                class="amenity-tag"
              >
                {{ amenity }}
              </span>
            </div>
          </div>
          
          <div class="pricing">
            <div class="price">
              <span class="total">${{ room.total_price }}</span>
              <span class="per-night">${{ room.price_per_night }}/night</span>
            </div>
            <button @click="selectRoom(room)" class="book-btn">
              Select Room
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Booking Form -->
    <div v-if="selectedRooms.length > 0" class="booking-form">
      <h3>Complete Your Booking</h3>
      
      <div class="selected-rooms">
        <h4>Selected Rooms</h4>
        <div v-for="room in selectedRooms" :key="room.id" class="selected-room">
          <span>Room {{ room.number }} - {{ room.type }}</span>
          <span>${{ room.total_price }}</span>
          <button @click="removeRoom(room)" class="remove-btn">Remove</button>
        </div>
      </div>

      <form @submit.prevent="createBooking" class="guest-form">
        <h4>Guest Information</h4>
        
        <div class="form-row">
          <div class="form-group">
            <label>First Name</label>
            <input v-model="bookingForm.guest.first_name" type="text" required>
          </div>
          <div class="form-group">
            <label>Last Name</label>
            <input v-model="bookingForm.guest.last_name" type="text" required>
          </div>
        </div>
        
        <div class="form-row">
          <div class="form-group">
            <label>Email</label>
            <input v-model="bookingForm.guest.email" type="email" required>
          </div>
          <div class="form-group">
            <label>Phone</label>
            <input v-model="bookingForm.guest.phone" type="tel" required>
          </div>
        </div>

        <div class="booking-summary">
          <h4>Booking Summary</h4>
          <div class="summary-line">
            <span>Check-in:</span>
            <span>{{ searchForm.check_in }}</span>
          </div>
          <div class="summary-line">
            <span>Check-out:</span>
            <span>{{ searchForm.check_out }}</span>
          </div>
          <div class="summary-line">
            <span>Nights:</span>
            <span>{{ nights }}</span>
          </div>
          <div class="summary-line">
            <span>Rooms:</span>
            <span>{{ selectedRooms.length }}</span>
          </div>
          <div class="summary-line total">
            <span>Total:</span>
            <span>${{ totalAmount }}</span>
          </div>
        </div>

        <button type="submit" :disabled="booking" class="confirm-btn">
          {{ booking ? 'Processing...' : 'Confirm Booking' }}
        </button>
      </form>
    </div>

    <!-- Booking Confirmation -->
    <div v-if="bookingConfirmation" class="booking-confirmation">
      <h3>Booking Confirmed!</h3>
      <div class="confirmation-details">
        <p><strong>Confirmation Code:</strong> {{ bookingConfirmation.confirmation_code }}</p>
        <p><strong>Guest:</strong> {{ bookingConfirmation.guest_name }}</p>
        <p><strong>Hotel:</strong> {{ bookingConfirmation.hotel_name }}</p>
        <p><strong>Dates:</strong> {{ bookingConfirmation.check_in }} to {{ bookingConfirmation.check_out }}</p>
        <p><strong>Total:</strong> ${{ bookingConfirmation.total_amount }}</p>
      </div>
    </div>
  </div>
</template>

<script>
export default {
  name: 'HotelBooking',
  
  props: {
    hotelId: {
      type: Number,
      required: true
    }
  },

  data() {
    return {
      searching: false,
      booking: false,
      availableRooms: [],
      selectedRooms: [],
      bookingConfirmation: null,
      
      searchForm: {
        check_in: '',
        check_out: '',
        guests: 1
      },
      
      bookingForm: {
        guest: {
          first_name: '',
          last_name: '',
          email: '',
          phone: ''
        }
      }
    };
  },

  computed: {
    nights() {
      if (!this.searchForm.check_in || !this.searchForm.check_out) return 0;
      const checkIn = new Date(this.searchForm.check_in);
      const checkOut = new Date(this.searchForm.check_out);
      return Math.ceil((checkOut - checkIn) / (1000 * 60 * 60 * 24));
    },
    
    totalAmount() {
      return this.selectedRooms.reduce((sum, room) => sum + room.total_price, 0);
    }
  },

  methods: {
    async searchRooms() {
      this.searching = true;
      
      try {
        const response = await fetch(`/api/hotels/${this.hotelId}/search-rooms`, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
          },
          body: JSON.stringify(this.searchForm)
        });
        
        const data = await response.json();
        this.availableRooms = data.available_rooms;
        
      } catch (error) {
        console.error('Search failed:', error);
        alert('Failed to search rooms. Please try again.');
      } finally {
        this.searching = false;
      }
    },

    selectRoom(room) {
      if (!this.selectedRooms.find(r => r.id === room.id)) {
        this.selectedRooms.push(room);
      }
    },

    removeRoom(room) {
      this.selectedRooms = this.selectedRooms.filter(r => r.id !== room.id);
    },

    async createBooking() {
      this.booking = true;
      
      const bookingData = {
        guest: this.bookingForm.guest,
        check_in: this.searchForm.check_in,
        check_out: this.searchForm.check_out,
        rooms: this.selectedRooms.map(room => ({
          room_id: room.id,
          guests: this.searchForm.guests,
          special_requests: ''
        }))
      };

      try {
        const response = await fetch(`/api/hotels/${this.hotelId}/bookings`, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
          },
          body: JSON.stringify(bookingData)
        });
        
        const data = await response.json();
        
        if (data.success) {
          this.bookingConfirmation = data.booking;
          this.selectedRooms = [];
          this.availableRooms = [];
        } else {
          alert(data.message || 'Booking failed. Please try again.');
        }
        
      } catch (error) {
        console.error('Booking failed:', error);
        alert('Booking failed. Please try again.');
      } finally {
        this.booking = false;
      }
    }
  }
};
</script>
```

This comprehensive hotel booking example demonstrates advanced features like multi-room bookings, pricing calculations, service integration, and a complete frontend interface.