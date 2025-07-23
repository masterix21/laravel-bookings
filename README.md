# Laravel Bookings

[![Latest Version on Packagist](https://img.shields.io/packagist/v/masterix21/laravel-bookings.svg?style=flat-square)](https://packagist.org/packages/masterix21/laravel-bookings)
[![GitHub Tests Action Status](https://img.shields.io/github/workflow/status/masterix21/laravel-bookings/run-tests?label=tests)](https://github.com/masterix21/laravel-bookings/actions?query=workflow%3ATests+branch%3Amaster)
[![GitHub Code Style Action Status](https://img.shields.io/github/workflow/status/masterix21/laravel-bookings/Check%20&%20fix%20styling?label=code%20style)](https://github.com/masterix21/laravel-bookings/actions?query=workflow%3A"Check+%26+fix+styling"+branch%3Amaster)
[![Total Downloads](https://img.shields.io/packagist/dt/masterix21/laravel-bookings.svg?style=flat-square)](https://packagist.org/packages/masterix21/laravel-bookings)

A comprehensive Laravel package that adds powerful booking functionality to any Eloquent model. Transform your models into bookable resources with advanced features like time-based reservations, capacity management, planning constraints, overlap detection, and event-driven architecture.

## Features

- 🚀 **Make any Eloquent model bookable** with simple traits
- 📅 **Advanced time period management** using Spatie Period library
- 🏢 **Resource capacity control** with configurable limits
- 📋 **Planning constraints** with weekday and time restrictions
- 🔍 **Overlap detection** and conflict prevention
- 🎯 **Event-driven architecture** for audit trails and integrations
- 🗂️ **Polymorphic relationships** for flexible booker and resource types
- 🧪 **100% test coverage** with comprehensive test suite
- ⚡ **Performance optimized** with efficient database queries
- 🛡️ **Transaction safety** with automatic rollback on failures

## Installation

Install the package via Composer:

```bash
composer require masterix21/laravel-bookings
```

Publish and run the migrations:

```bash
php artisan vendor:publish --tag="bookings-migrations"
php artisan migrate
```

Optionally, publish the config file:

```bash
php artisan vendor:publish --tag="bookings-config"
```

## Quick Start

### 1. Make a Model Bookable

Add the `IsBookable` trait to any model you want to make bookable:

```php
use Masterix21\Bookings\Models\Concerns\IsBookable;
use Masterix21\Bookings\Models\Concerns\Bookable;

class Room extends Model implements Bookable
{
    use IsBookable;
    
    protected $fillable = ['name', 'capacity'];
}
```

### 2. Create a Bookable Resource

```php
use Masterix21\Bookings\Models\BookableResource;

// Create a bookable resource for your room
$room = Room::create(['name' => 'Deluxe Suite', 'capacity' => 4]);

$bookableResource = BookableResource::create([
    'resource_type' => Room::class,
    'resource_id' => $room->id,
    'max' => 1, // Maximum concurrent bookings
    'size' => 4, // Resource capacity
    'is_bookable' => true,
    'is_visible' => true,
]);
```

### 3. Make a Booking

```php
use Masterix21\Bookings\Actions\BookResource;
use Spatie\Period\Period;
use Spatie\Period\PeriodCollection;

// Create booking periods
$periods = PeriodCollection::make([
    Period::make('2024-12-25', '2024-12-27'), // 2 nights
]);

// Book the resource
$booking = (new BookResource())->run(
    periods: $periods,
    bookableResource: $bookableResource,
    booker: auth()->user(), // The user making the booking
    label: 'Christmas Holiday',
    note: 'Special dietary requirements',
    meta: ['guests' => 2, 'payment_method' => 'credit_card']
);
```

## Core Concepts

### BookableResource
The central entity that represents a bookable item. It's linked to your actual model (Room, Car, etc.) via polymorphic relationships.

### Booking
Represents a reservation with metadata, booker information, and associated time periods.

### BookedPeriod
Individual time slots within a booking, supporting complex multi-period reservations.

### BookablePlanning
Defines availability rules, working hours, and constraints for resources.

## Advanced Features

### Planning Constraints

Define when resources are available:

```php
use Masterix21\Bookings\Models\BookablePlanning;

BookablePlanning::create([
    'bookable_resource_id' => $bookableResource->id,
    'monday' => true,
    'tuesday' => true,
    'wednesday' => true,
    'thursday' => true,
    'friday' => true,
    'saturday' => false, // Closed on weekends
    'sunday' => false,
    'starts_at' => '2024-01-01 09:00:00', // Available from 9 AM
    'ends_at' => '2024-12-31 18:00:00',   // Until 6 PM
]);
```

### Event System

Listen to booking lifecycle events:

```php
use Masterix21\Bookings\Events\BookingCompleted;
use Masterix21\Bookings\Events\BookingFailed;

// In your EventServiceProvider
protected $listen = [
    BookingCompleted::class => [
        SendBookingConfirmationEmail::class,
        UpdateInventory::class,
    ],
    BookingFailed::class => [
        LogBookingFailure::class,
        NotifyAdministrators::class,
    ],
];
```

### Checking Availability

```php
// Check if a resource is booked at a specific time
$isBooked = $room->isBookedAt(now());

// Get booked periods for a specific date
$bookedPeriods = $room->bookedPeriodsOfDate(today());

// Get all bookings for a resource
$bookings = $room->bookings;
```

### Overlap Detection

The package automatically prevents overlapping bookings:

```php
use Masterix21\Bookings\Exceptions\BookingResourceOverlappingException;

try {
    $booking = (new BookResource())->run(/* ... */);
} catch (BookingResourceOverlappingException $e) {
    // Handle booking conflict
    return response()->json(['error' => 'Time slot already booked'], 409);
}
```

## Practical Examples

### Hotel Booking System

Complete implementation example for a hotel booking application.

[See Hotel Booking Example →](#hotel-booking-example)

### Car Rental System

Complete implementation example for a car rental application.

[See Car Rental Example →](#car-rental-example)

## Configuration

The package is highly configurable. Here are the key configuration options:

```php
// config/bookings.php
return [
    'models' => [
        'user' => \App\Models\User::class,
        'bookable_resource' => \Masterix21\Bookings\Models\BookableResource::class,
        'booking' => \Masterix21\Bookings\Models\Booking::class,
        'booked_period' => \Masterix21\Bookings\Models\BookedPeriod::class,
        'bookable_planning' => \Masterix21\Bookings\Models\BookablePlanning::class,
        'bookable_relation' => \Masterix21\Bookings\Models\BookableRelation::class,
    ],
    
    'generators' => [
        'booking_code' => \Masterix21\Bookings\Generators\RandomBookingCode::class,
    ],
];
```

## Database Structure

The package creates several tables:

- `bookable_resources` - Main bookable entities
- `bookings` - Booking records with polymorphic booker relation
- `booked_periods` - Individual time slots
- `bookable_plannings` - Availability and constraint rules
- `bookable_relations` - Resource relationships

## API Reference

### Traits

#### IsBookable
Makes any model bookable.

**Methods:**
- `bookableResources()` - HasMany relationship to BookableResource
- `bookableResource()` - HasOne relationship to BookableResource
- `bookedPeriods()` - HasManyDeep relationship to BookedPeriod
- `bookings()` - HasManyDeep relationship to Booking
- `isBookedAt(Carbon $date): bool` - Check if booked at specific time
- `bookedPeriodsOfDate(Carbon $date): Collection` - Get periods for date

#### HasBookings
For models that can make bookings (users, organizations).

**Methods:**
- `bookings()` - MorphMany relationship to Booking
- `bookedPeriods()` - HasManyThrough relationship to BookedPeriod

### Actions

#### BookResource
Main action for creating and updating bookings.

**Parameters:**
- `PeriodCollection $periods` - Time periods to book
- `BookableResource $bookableResource` - Resource to book
- `?Model $booker` - Entity making the booking
- `?Booking $booking` - Existing booking for updates
- `?User $creator` - User creating the booking
- `?Model $relatable` - Related model
- `?string $code` - Custom booking code
- `?string $codePrefix` - Code prefix
- `?string $codeSuffix` - Code suffix
- `?string $label` - Booking label
- `?string $note` - Booking note
- `?array $meta` - Additional metadata

#### CheckBookingOverlaps
Validates booking conflicts.

**Parameters:**
- `PeriodCollection $periods` - Periods to check
- `BookableResource $bookableResource` - Resource to check
- `bool $emitEvent` - Whether to emit events
- `bool $throw` - Whether to throw on overlap
- `?Booking $ignoreBooking` - Booking to ignore in check

### Events

All events are fired automatically during the booking lifecycle:

- `BookingInProgress` - Booking process started
- `BookingCompleted` - Booking successfully created
- `BookingFailed` - Booking creation failed
- `BookingChanging` - Booking update started
- `BookingChanged` - Booking successfully updated
- `BookingChangeFailed` - Booking update failed

### Exceptions

- `BookingResourceOverlappingException` - Booking conflicts detected
- `OutOfPlanningsException` - No valid planning found
- `NoFreeSizeException` - Resource capacity exceeded
- `RelationsOutOfPlanningsException` - Related resources lack planning
- `RelationsHaveNoFreeSizeException` - Related resources at capacity

## Hotel Booking Example

This example demonstrates how to implement a complete hotel booking system using the Laravel Bookings package.

### Setup

First, create your hotel-specific models:

```php
// app/Models/Hotel.php
<?php

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
        'stars',
        'description',
    ];

    public function rooms(): HasMany
    {
        return $this->hasMany(Room::class);
    }
}
```

```php
// app/Models/Room.php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Masterix21\Bookings\Models\Concerns\IsBookable;
use Masterix21\Bookings\Models\Concerns\Bookable;

class Room extends Model implements Bookable
{
    use IsBookable;

    protected $fillable = [
        'hotel_id',
        'number',
        'type',
        'capacity',
        'price_per_night',
        'amenities',
        'description',
    ];

    protected $casts = [
        'amenities' => 'array',
        'price_per_night' => 'decimal:2',
    ];

    public function hotel(): BelongsTo
    {
        return $this->belongsTo(Hotel::class);
    }
}
```

```php
// app/Models/Guest.php
<?php

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
    ];

    protected $casts = [
        'date_of_birth' => 'date',
    ];

    public function getFullNameAttribute(): string
    {
        return "{$this->first_name} {$this->last_name}";
    }
}
```

### Service Layer

```php
// app/Services/HotelBookingService.php
<?php

namespace App\Services;

use App\Models\Guest;
use App\Models\Room;
use Carbon\Carbon;
use Masterix21\Bookings\Actions\BookResource;
use Masterix21\Bookings\Actions\CheckBookingOverlaps;
use Masterix21\Bookings\Exceptions\BookingResourceOverlappingException;
use Masterix21\Bookings\Models\BookableResource;
use Masterix21\Bookings\Models\Booking;
use Spatie\Period\Period;
use Spatie\Period\PeriodCollection;

class HotelBookingService
{
    public function findAvailableRooms(
        Carbon $checkIn,
        Carbon $checkOut,
        int $guests = 1,
        ?string $roomType = null
    ): \Illuminate\Support\Collection {
        $periods = PeriodCollection::make([
            Period::make($checkIn, $checkOut)
        ]);

        return Room::query()
            ->when($roomType, fn($q) => $q->where('type', $roomType))
            ->where('capacity', '>=', $guests)
            ->whereHas('bookableResource', function ($query) use ($periods) {
                $query->where('is_bookable', true)
                    ->where('is_visible', true);
            })
            ->get()
            ->filter(function (Room $room) use ($periods) {
                $bookableResource = $room->bookableResource;
                if (!$bookableResource) {
                    return false;
                }

                return (new CheckBookingOverlaps())->run(
                    periods: $periods,
                    bookableResource: $bookableResource,
                    emitEvent: false,
                    throw: false
                );
            });
    }

    public function createBooking(
        Room $room,
        Guest $guest,
        Carbon $checkIn,
        Carbon $checkOut,
        array $additionalData = []
    ): Booking {
        $bookableResource = $room->bookableResource;
        
        if (!$bookableResource) {
            throw new \Exception("Room {$room->number} is not bookable");
        }

        $periods = PeriodCollection::make([
            Period::make($checkIn, $checkOut)
        ]);

        $nights = $checkIn->diffInDays($checkOut);
        $totalAmount = $room->price_per_night * $nights;

        try {
            return (new BookResource())->run(
                periods: $periods,
                bookableResource: $bookableResource,
                booker: $guest,
                label: "Hotel Booking - Room {$room->number}",
                note: $additionalData['special_requests'] ?? null,
                meta: array_merge([
                    'check_in' => $checkIn->toDateString(),
                    'check_out' => $checkOut->toDateString(),
                    'nights' => $nights,
                    'room_type' => $room->type,
                    'guests_count' => $additionalData['guests_count'] ?? 1,
                    'total_amount' => $totalAmount,
                    'currency' => 'USD',
                ], $additionalData)
            );
        } catch (BookingResourceOverlappingException $e) {
            throw new \Exception("Room {$room->number} is not available for the selected dates");
        }
    }

    public function modifyBooking(
        Booking $booking,
        Carbon $newCheckIn,
        Carbon $newCheckOut,
        array $additionalData = []
    ): Booking {
        $room = $booking->bookedPeriods->first()->relatable;
        $bookableResource = $room->bookableResource;

        $periods = PeriodCollection::make([
            Period::make($newCheckIn, $newCheckOut)
        ]);

        $nights = $newCheckIn->diffInDays($newCheckOut);
        $totalAmount = $room->price_per_night * $nights;

        try {
            return (new BookResource())->run(
                periods: $periods,
                bookableResource: $bookableResource,
                booker: $booking->booker,
                booking: $booking,
                label: "Hotel Booking - Room {$room->number} (Modified)",
                note: $additionalData['special_requests'] ?? $booking->note,
                meta: array_merge($booking->meta->toArray(), [
                    'check_in' => $newCheckIn->toDateString(),
                    'check_out' => $newCheckOut->toDateString(),
                    'nights' => $nights,
                    'total_amount' => $totalAmount,
                    'modified_at' => now()->toISOString(),
                ], $additionalData)
            );
        } catch (BookingResourceOverlappingException $e) {
            throw new \Exception("Room {$room->number} is not available for the new dates");
        }
    }

    public function getBookingDetails(Booking $booking): array
    {
        $bookedPeriod = $booking->bookedPeriods->first();
        $room = $bookedPeriod->relatable;
        $guest = $booking->booker;

        return [
            'booking_code' => $booking->code,
            'guest' => [
                'name' => $guest->full_name,
                'email' => $guest->email,
                'phone' => $guest->phone,
            ],
            'room' => [
                'number' => $room->number,
                'type' => $room->type,
                'hotel' => $room->hotel->name,
            ],
            'dates' => [
                'check_in' => $bookedPeriod->starts_at->toDateString(),
                'check_out' => $bookedPeriod->ends_at->toDateString(),
                'nights' => $booking->meta['nights'] ?? 0,
            ],
            'amount' => [
                'total' => $booking->meta['total_amount'] ?? 0,
                'currency' => $booking->meta['currency'] ?? 'USD',
            ],
            'special_requests' => $booking->note,
            'status' => 'confirmed',
        ];
    }
}
```

### Usage Example

```php
// Example usage
$service = new HotelBookingService();

// Search for available rooms
$availableRooms = $service->findAvailableRooms(
    checkIn: Carbon::parse('2024-12-25'),
    checkOut: Carbon::parse('2024-12-27'),
    guests: 2,
    roomType: 'deluxe'
);

// Create a guest
$guest = Guest::create([
    'first_name' => 'John',
    'last_name' => 'Doe',
    'email' => 'john.doe@example.com',
    'phone' => '+1234567890',
]);

// Book the first available room
if ($availableRooms->isNotEmpty()) {
    $room = $availableRooms->first();
    
    $booking = $service->createBooking(
        room: $room,
        guest: $guest,
        checkIn: Carbon::parse('2024-12-25'),
        checkOut: Carbon::parse('2024-12-27'),
        additionalData: [
            'guests_count' => 2,
            'special_requests' => 'Late check-in required',
            'payment_method' => 'credit_card',
        ]
    );
    
    echo "Booking created with code: " . $booking->code;
}
```

## Car Rental Example

This example demonstrates how to implement a complete car rental system using the Laravel Bookings package.

### Setup

Create your car rental specific models:

```php
// app/Models/Vehicle.php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Masterix21\Bookings\Models\Concerns\IsBookable;
use Masterix21\Bookings\Models\Concerns\Bookable;

class Vehicle extends Model implements Bookable
{
    use IsBookable;

    protected $fillable = [
        'rental_location_id',
        'make',
        'model',
        'year',
        'license_plate',
        'category',
        'fuel_type',
        'transmission',
        'seats',
        'daily_rate',
        'features',
        'status',
    ];

    protected $casts = [
        'features' => 'array',
        'daily_rate' => 'decimal:2',
    ];

    public function currentLocation(): BelongsTo
    {
        return $this->belongsTo(RentalLocation::class, 'rental_location_id');
    }

    public function getFullNameAttribute(): string
    {
        return "{$this->year} {$this->make} {$this->model}";
    }

    public function isAvailable(): bool
    {
        return $this->status === 'available';
    }
}
```

```php
// app/Models/Customer.php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Masterix21\Bookings\Models\Concerns\HasBookings;

class Customer extends Model
{
    use HasBookings;

    protected $fillable = [
        'first_name',
        'last_name',
        'email',
        'phone',
        'license_number',
        'license_expiry',
        'address',
        'city',
        'country',
    ];

    protected $casts = [
        'license_expiry' => 'date',
    ];

    public function getFullNameAttribute(): string
    {
        return "{$this->first_name} {$this->last_name}";
    }

    public function hasValidLicense(): bool
    {
        return $this->license_expiry && $this->license_expiry->isFuture();
    }
}
```

### Service Layer

```php
// app/Services/CarRentalService.php
<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Vehicle;
use Carbon\Carbon;
use Masterix21\Bookings\Actions\BookResource;
use Masterix21\Bookings\Actions\CheckBookingOverlaps;
use Masterix21\Bookings\Exceptions\BookingResourceOverlappingException;
use Masterix21\Bookings\Models\Booking;
use Spatie\Period\Period;
use Spatie\Period\PeriodCollection;

class CarRentalService
{
    public function findAvailableVehicles(
        Carbon $pickupDate,
        Carbon $returnDate,
        ?string $category = null
    ): \Illuminate\Support\Collection {
        $periods = PeriodCollection::make([
            Period::make($pickupDate, $returnDate)
        ]);

        return Vehicle::query()
            ->where('status', 'available')
            ->when($category, fn($q) => $q->where('category', $category))
            ->whereHas('bookableResource', function ($query) {
                $query->where('is_bookable', true)
                    ->where('is_visible', true);
            })
            ->get()
            ->filter(function (Vehicle $vehicle) use ($periods) {
                $bookableResource = $vehicle->bookableResource;
                if (!$bookableResource) {
                    return false;
                }

                return (new CheckBookingOverlaps())->run(
                    periods: $periods,
                    bookableResource: $bookableResource,
                    emitEvent: false,
                    throw: false
                );
            });
    }

    public function createRental(
        Vehicle $vehicle,
        Customer $customer,
        Carbon $pickupDate,
        Carbon $returnDate,
        array $additionalData = []
    ): Booking {
        if (!$customer->hasValidLicense()) {
            throw new \Exception("Customer license is expired or invalid");
        }

        $bookableResource = $vehicle->bookableResource;
        
        if (!$bookableResource) {
            throw new \Exception("Vehicle {$vehicle->license_plate} is not available for rental");
        }

        if (!$vehicle->isAvailable()) {
            throw new \Exception("Vehicle {$vehicle->license_plate} is currently not available");
        }

        $periods = PeriodCollection::make([
            Period::make($pickupDate, $returnDate)
        ]);

        $days = $pickupDate->diffInDays($returnDate);
        $totalAmount = $vehicle->daily_rate * $days;

        // Calculate additional fees
        $fees = $this->calculateAdditionalFees($additionalData);
        $totalAmount += $fees['total'];

        try {
            $booking = (new BookResource())->run(
                periods: $periods,
                bookableResource: $bookableResource,
                booker: $customer,
                label: "Car Rental - {$vehicle->full_name}",
                note: $additionalData['special_instructions'] ?? null,
                meta: array_merge([
                    'pickup_date' => $pickupDate->toDateTimeString(),
                    'return_date' => $returnDate->toDateTimeString(),
                    'days' => $days,
                    'daily_rate' => $vehicle->daily_rate,
                    'base_amount' => $vehicle->daily_rate * $days,
                    'fees' => $fees,
                    'total_amount' => $totalAmount,
                    'currency' => 'USD',
                    'vehicle_details' => [
                        'make' => $vehicle->make,
                        'model' => $vehicle->model,
                        'year' => $vehicle->year,
                        'license_plate' => $vehicle->license_plate,
                        'category' => $vehicle->category,
                    ],
                ], $additionalData)
            );

            // Update vehicle status
            $vehicle->update(['status' => 'rented']);

            return $booking;

        } catch (BookingResourceOverlappingException $e) {
            throw new \Exception("Vehicle {$vehicle->license_plate} is not available for the selected dates");
        }
    }

    public function returnVehicle(Booking $booking, array $returnData = []): array
    {
        $vehicle = $this->getVehicleFromBooking($booking);
        
        // Update vehicle status
        $vehicle->update(['status' => 'available']);

        // Calculate final charges
        $finalCharges = $this->calculateFinalCharges($booking, $returnData);

        // Update booking with return information
        $booking->update([
            'meta' => array_merge($booking->meta->toArray(), [
                'returned_at' => now()->toISOString(),
                'return_condition' => $returnData['condition'] ?? 'good',
                'additional_charges' => $finalCharges,
            ])
        ]);

        // Mark as completed
        $booking->bookedPeriods()->delete();

        return [
            'booking_code' => $booking->code,
            'returned_at' => now()->toDateTimeString(),
            'final_charges' => $finalCharges,
            'status' => 'completed',
        ];
    }

    private function getVehicleFromBooking(Booking $booking): Vehicle
    {
        $bookedPeriod = $booking->bookedPeriods->first();
        $bookableResource = $bookedPeriod->bookableResource;
        return $bookableResource->resource;
    }

    private function calculateAdditionalFees(array $data): array
    {
        $fees = [];
        $total = 0;

        // GPS fee
        if ($data['gps'] ?? false) {
            $fees['gps'] = 10 * ($data['days'] ?? 1);
            $total += $fees['gps'];
        }

        // Insurance fee
        if ($data['full_insurance'] ?? false) {
            $fees['full_insurance'] = 30 * ($data['days'] ?? 1);
            $total += $fees['full_insurance'];
        }

        $fees['total'] = $total;
        return $fees;
    }

    private function calculateFinalCharges(Booking $booking, array $returnData): array
    {
        $charges = [];
        $total = 0;

        // Late return fee
        $scheduledReturn = Carbon::parse($booking->meta['return_date']);
        $actualReturn = now();
        if ($actualReturn->isAfter($scheduledReturn)) {
            $lateHours = $actualReturn->diffInHours($scheduledReturn);
            $charges['late_return'] = $lateHours * 25; // $25 per hour
            $total += $charges['late_return'];
        }

        // Damage charges
        if ($returnData['damage_cost'] ?? 0) {
            $charges['damage'] = $returnData['damage_cost'];
            $total += $charges['damage'];
        }

        $charges['total'] = $total;
        return $charges;
    }
}
```

### Usage Example

```php
// Example usage
$service = new CarRentalService();

// Search for available vehicles
$availableVehicles = $service->findAvailableVehicles(
    pickupDate: Carbon::parse('2024-12-25'),
    returnDate: Carbon::parse('2024-12-30'),
    category: 'luxury'
);

// Create a customer
$customer = Customer::create([
    'first_name' => 'Jane',
    'last_name' => 'Smith',
    'email' => 'jane.smith@example.com',
    'phone' => '+1-555-0123',
    'license_number' => 'D1234567',
    'license_expiry' => Carbon::parse('2026-12-31'),
    'address' => '789 Customer Street',
    'city' => 'Los Angeles',
    'country' => 'USA',
]);

// Rent the first available vehicle
if ($availableVehicles->isNotEmpty()) {
    $vehicle = $availableVehicles->first();
    
    $rental = $service->createRental(
        vehicle: $vehicle,
        customer: $customer,
        pickupDate: Carbon::parse('2024-12-25'),
        returnDate: Carbon::parse('2024-12-30'),
        additionalData: [
            'gps' => true,
            'full_insurance' => true,
            'special_instructions' => 'Need vehicle for road trip',
        ]
    );
    
    echo "Rental created with code: " . $rental->code;
}
```

## Testing

Run the package tests:

```bash
composer test
```

Run tests with coverage:

```bash
composer test-coverage
```

Run static analysis:

```bash
composer analyse
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Please see [CONTRIBUTING](.github/CONTRIBUTING.md) for details.

## Security Vulnerabilities

Please review [our security policy](../../security/policy) on how to report security vulnerabilities.

## Credits

- [Luca Longo](https://github.com/masterix21)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.