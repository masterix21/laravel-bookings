# Car Rental System Example

This comprehensive example demonstrates how to build a complete car rental system using Laravel Bookings, including vehicle management, customer handling, pricing, and fleet operations.

## Overview

This example covers:
- Vehicle fleet management
- Customer registration and validation
- Rental bookings with insurance options
- Pricing with dynamic rates
- Vehicle maintenance scheduling
- Return processing and damage assessment
- Multi-location operations
- Reporting and analytics

## Database Models

### Vehicle Model

```php
<?php
// app/Models/Vehicle.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Masterix21\Bookings\Models\Concerns\IsBookable;
use Masterix21\Bookings\Models\Concerns\Bookable;
use Carbon\Carbon;

class Vehicle extends Model implements Bookable
{
    use IsBookable;

    protected $fillable = [
        'location_id',
        'make',
        'model',
        'year',
        'color',
        'license_plate',
        'vin',
        'category',
        'fuel_type',
        'transmission',
        'seats',
        'doors',
        'engine_size',
        'daily_rate',
        'weekly_rate',
        'monthly_rate',
        'deposit_required',
        'mileage',
        'fuel_level',
        'status',
        'features',
        'insurance_included',
        'last_service_date',
        'next_service_due',
        'images',
    ];

    protected $casts = [
        'year' => 'integer',
        'seats' => 'integer',
        'doors' => 'integer',
        'engine_size' => 'decimal:1',
        'daily_rate' => 'decimal:2',
        'weekly_rate' => 'decimal:2',
        'monthly_rate' => 'decimal:2',
        'deposit_required' => 'decimal:2',
        'mileage' => 'integer',
        'fuel_level' => 'integer',
        'features' => 'array',
        'insurance_included' => 'boolean',
        'last_service_date' => 'date',
        'next_service_due' => 'date',
        'images' => 'array',
    ];

    public function location(): BelongsTo
    {
        return $this->belongsTo(RentalLocation::class, 'location_id');
    }

    public function maintenanceRecords(): HasMany
    {
        return $this->hasMany(VehicleMaintenanceRecord::class);
    }

    public function getFullNameAttribute(): string
    {
        return "{$this->year} {$this->make} {$this->model}";
    }

    public function isAvailable(): bool
    {
        return in_array($this->status, ['available', 'cleaned']);
    }

    public function requiresMaintenance(): bool
    {
        return $this->next_service_due <= now()->addDays(7) || $this->mileage >= 10000;
    }

    public function calculateRentalPrice(Carbon $startDate, Carbon $endDate, array $options = []): array
    {
        $days = $startDate->diffInDays($endDate);
        
        // Determine rate based on rental duration
        $baseRate = match (true) {
            $days >= 30 => $this->monthly_rate * ceil($days / 30),
            $days >= 7 => $this->weekly_rate * ceil($days / 7),
            default => $this->daily_rate * $days,
        };

        $pricing = [
            'base_rate' => $baseRate,
            'days' => $days,
            'additional_fees' => [],
            'total' => $baseRate,
        ];

        // Additional services
        if ($options['gps'] ?? false) {
            $gpsFee = 10 * $days;
            $pricing['additional_fees']['gps'] = $gpsFee;
            $pricing['total'] += $gpsFee;
        }

        if ($options['child_seat'] ?? false) {
            $childSeatFee = 5 * $days;
            $pricing['additional_fees']['child_seat'] = $childSeatFee;
            $pricing['total'] += $childSeatFee;
        }

        if ($options['additional_driver'] ?? false) {
            $additionalDriverFee = 15 * $days;
            $pricing['additional_fees']['additional_driver'] = $additionalDriverFee;
            $pricing['total'] += $additionalDriverFee;
        }

        // Insurance options
        if ($options['full_coverage'] ?? false) {
            $insuranceFee = 25 * $days;
            $pricing['additional_fees']['full_coverage'] = $insuranceFee;
            $pricing['total'] += $insuranceFee;
        }

        // Seasonal adjustments
        $seasonalMultiplier = $this->getSeasonalMultiplier($startDate, $endDate);
        $pricing['seasonal_adjustment'] = ($pricing['total'] * $seasonalMultiplier) - $pricing['total'];
        $pricing['total'] *= $seasonalMultiplier;

        // Taxes
        $taxRate = 0.08; // 8% tax
        $pricing['taxes'] = $pricing['total'] * $taxRate;
        $pricing['total'] += $pricing['taxes'];

        return $pricing;
    }

    private function getSeasonalMultiplier(Carbon $startDate, Carbon $endDate): float
    {
        $avgMonth = $startDate->copy()->addDays($startDate->diffInDays($endDate) / 2)->month;
        
        // Summer peak season
        if (in_array($avgMonth, [6, 7, 8])) {
            return 1.3;
        }
        
        // Holiday seasons
        if (in_array($avgMonth, [12, 1])) {
            return 1.2;
        }
        
        return 1.0;
    }

    public function isAvailableForPeriod(Carbon $startDate, Carbon $endDate): bool
    {
        if (!$this->isAvailable()) {
            return false;
        }

        $periods = PeriodCollection::make([
            Period::make($startDate, $endDate)
        ]);

        return (new CheckBookingOverlaps())->run(
            periods: $periods,
            bookableResource: $this->bookableResource,
            emitEvent: false,
            throw: false
        );
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
use Carbon\Carbon;

class Customer extends Model
{
    use HasBookings;

    protected $fillable = [
        'first_name',
        'last_name',
        'email',
        'phone',
        'date_of_birth',
        'license_number',
        'license_country',
        'license_expiry',
        'address',
        'city',
        'state',
        'country',
        'postal_code',
        'emergency_contact_name',
        'emergency_contact_phone',
        'preferred_payment_method',
        'credit_card_token',
        'loyalty_points',
        'status',
        'notes',
    ];

    protected $casts = [
        'date_of_birth' => 'date',
        'license_expiry' => 'date',
        'loyalty_points' => 'integer',
    ];

    public function rentals(): HasMany
    {
        return $this->hasMany(VehicleRental::class);
    }

    public function getFullNameAttribute(): string
    {
        return "{$this->first_name} {$this->last_name}";
    }

    public function getAgeAttribute(): int
    {
        return $this->date_of_birth->age;
    }

    public function hasValidLicense(): bool
    {
        return $this->license_expiry && $this->license_expiry->isFuture();
    }

    public function meetsAgeRequirement(string $vehicleCategory): bool
    {
        $minimumAge = match ($vehicleCategory) {
            'luxury', 'sports' => 25,
            'truck', 'van' => 23,
            default => 21,
        };

        return $this->age >= $minimumAge;
    }

    public function isEligibleToRent(Vehicle $vehicle): array
    {
        $errors = [];

        if (!$this->hasValidLicense()) {
            $errors[] = 'Driver license is expired or invalid';
        }

        if (!$this->meetsAgeRequirement($vehicle->category)) {
            $minimumAge = match ($vehicle->category) {
                'luxury', 'sports' => 25,
                'truck', 'van' => 23,
                default => 21,
            };
            $errors[] = "Minimum age requirement ({$minimumAge} years) not met for this vehicle category";
        }

        if ($this->status === 'blocked') {
            $errors[] = 'Customer account is blocked';
        }

        // Check rental history for issues
        $recentIssues = $this->rentals()
            ->where('created_at', '>=', now()->subMonths(12))
            ->where('status', 'completed')
            ->where('final_charges', '>', 0)
            ->count();

        if ($recentIssues >= 3) {
            $errors[] = 'Customer has multiple recent issues in rental history';
        }

        return [
            'eligible' => empty($errors),
            'errors' => $errors,
        ];
    }
}
```

### Rental Location Model

```php
<?php
// app/Models/RentalLocation.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RentalLocation extends Model
{
    protected $fillable = [
        'name',
        'code',
        'address',
        'city',
        'state',
        'country',
        'postal_code',
        'phone',
        'email',
        'latitude',
        'longitude',
        'operating_hours',
        'is_active',
        'is_pickup_location',
        'is_dropoff_location',
        'airport_code',
    ];

    protected $casts = [
        'latitude' => 'decimal:8',
        'longitude' => 'decimal:8',
        'operating_hours' => 'array',
        'is_active' => 'boolean',
        'is_pickup_location' => 'boolean',
        'is_dropoff_location' => 'boolean',
    ];

    public function vehicles(): HasMany
    {
        return $this->hasMany(Vehicle::class, 'location_id');
    }

    public function getAvailableVehicles(Carbon $startDate, Carbon $endDate): Collection
    {
        return $this->vehicles()
            ->where('status', 'available')
            ->whereHas('bookableResource', function ($query) {
                $query->where('is_bookable', true);
            })
            ->get()
            ->filter(function ($vehicle) use ($startDate, $endDate) {
                return $vehicle->isAvailableForPeriod($startDate, $endDate);
            });
    }
}
```

## Service Layer

### Car Rental Service

```php
<?php
// app/Services/CarRentalService.php

namespace App\Services;

use App\Models\Vehicle;
use App\Models\Customer;
use App\Models\VehicleRental;
use App\Models\RentalLocation;
use Masterix21\Bookings\Actions\BookResource;
use Masterix21\Bookings\Models\Booking;
use Carbon\Carbon;
use Spatie\Period\Period;
use Spatie\Period\PeriodCollection;
use Illuminate\Support\Facades\DB;

class CarRentalService
{
    public function __construct(
        private BookResource $bookResource,
        private PaymentService $paymentService,
        private InsuranceService $insuranceService
    ) {}

    public function searchAvailableVehicles(
        RentalLocation $pickupLocation,
        Carbon $pickupDate,
        Carbon $returnDate,
        ?string $category = null,
        array $filters = []
    ): Collection {
        return $pickupLocation->getAvailableVehicles($pickupDate, $returnDate)
            ->when($category, fn($vehicles) => $vehicles->where('category', $category))
            ->when($filters['transmission'] ?? null, fn($vehicles) => 
                $vehicles->where('transmission', $filters['transmission'])
            )
            ->when($filters['fuel_type'] ?? null, fn($vehicles) => 
                $vehicles->where('fuel_type', $filters['fuel_type'])
            )
            ->when($filters['min_seats'] ?? null, fn($vehicles) => 
                $vehicles->where('seats', '>=', $filters['min_seats'])
            )
            ->when($filters['features'] ?? null, fn($vehicles) => 
                $vehicles->filter(fn($vehicle) => 
                    count(array_intersect($vehicle->features, $filters['features'])) > 0
                )
            )
            ->sortBy('daily_rate');
    }

    public function createRental(
        Customer $customer,
        Vehicle $vehicle,
        Carbon $pickupDate,
        Carbon $returnDate,
        RentalLocation $pickupLocation,
        ?RentalLocation $dropoffLocation = null,
        array $options = []
    ): VehicleRental {
        // Validate customer eligibility
        $eligibility = $customer->isEligibleToRent($vehicle);
        if (!$eligibility['eligible']) {
            throw new \Exception('Customer not eligible: ' . implode(', ', $eligibility['errors']));
        }

        return DB::transaction(function () use (
            $customer, $vehicle, $pickupDate, $returnDate, 
            $pickupLocation, $dropoffLocation, $options
        ) {
            $dropoffLocation = $dropoffLocation ?? $pickupLocation;
            $pricing = $vehicle->calculateRentalPrice($pickupDate, $returnDate, $options);

            // Create rental record
            $rental = VehicleRental::create([
                'customer_id' => $customer->id,
                'vehicle_id' => $vehicle->id,
                'pickup_location_id' => $pickupLocation->id,
                'dropoff_location_id' => $dropoffLocation->id,
                'pickup_date' => $pickupDate,
                'return_date' => $returnDate,
                'status' => 'confirmed',
                'daily_rate' => $vehicle->daily_rate,
                'total_days' => $pickupDate->diffInDays($returnDate),
                'base_amount' => $pricing['base_rate'],
                'additional_fees' => $pricing['additional_fees'],
                'taxes' => $pricing['taxes'],
                'total_amount' => $pricing['total'],
                'deposit_amount' => $vehicle->deposit_required,
                'currency' => 'USD',
                'rental_options' => $options,
                'pickup_mileage' => $vehicle->mileage,
                'pickup_fuel_level' => $vehicle->fuel_level,
            ]);

            // Create booking
            $periods = PeriodCollection::make([
                Period::make($pickupDate, $returnDate)
            ]);

            $booking = $this->bookResource->run(
                periods: $periods,
                bookableResource: $vehicle->bookableResource,
                booker: $customer,
                relatable: $rental,
                label: "Car Rental - {$vehicle->full_name}",
                note: $options['special_instructions'] ?? null,
                meta: [
                    'rental_id' => $rental->id,
                    'pickup_location' => $pickupLocation->name,
                    'dropoff_location' => $dropoffLocation->name,
                    'customer_name' => $customer->full_name,
                    'vehicle_details' => [
                        'make' => $vehicle->make,
                        'model' => $vehicle->model,
                        'year' => $vehicle->year,
                        'license_plate' => $vehicle->license_plate,
                        'category' => $vehicle->category,
                    ],
                    'pricing' => $pricing,
                    'options' => $options,
                ]
            );

            $rental->update(['booking_id' => $booking->id]);

            // Process payment authorization
            if ($options['process_payment'] ?? true) {
                $paymentResult = $this->paymentService->authorizePayment(
                    $customer,
                    $pricing['total'] + $vehicle->deposit_required,
                    $rental
                );

                $rental->update([
                    'payment_status' => $paymentResult['status'],
                    'payment_reference' => $paymentResult['reference'],
                ]);
            }

            // Update vehicle status
            $vehicle->update(['status' => 'reserved']);

            return $rental;
        });
    }

    public function pickupRental(VehicleRental $rental, array $pickupData = []): array
    {
        return DB::transaction(function () use ($rental, $pickupData) {
            // Verify vehicle condition
            $vehicleCondition = $this->assessVehicleCondition($rental->vehicle, $pickupData);

            // Update rental
            $rental->update([
                'status' => 'active',
                'actual_pickup_date' => now(),
                'pickup_condition' => $vehicleCondition,
                'pickup_notes' => $pickupData['notes'] ?? null,
                'pickup_staff_id' => auth()->id(),
            ]);

            // Update vehicle
            $rental->vehicle->update([
                'status' => 'rented',
                'current_renter_id' => $rental->customer_id,
            ]);

            // Capture payment
            if ($rental->payment_status === 'authorized') {
                $captureResult = $this->paymentService->capturePayment(
                    $rental->payment_reference,
                    $rental->total_amount
                );

                $rental->update([
                    'payment_status' => $captureResult['status'],
                    'payment_captured_at' => now(),
                ]);
            }

            event(new VehiclePickedUp($rental, $vehicleCondition));

            return [
                'rental' => $rental->fresh(),
                'vehicle_condition' => $vehicleCondition,
                'pickup_time' => now(),
            ];
        });
    }

    public function returnRental(VehicleRental $rental, array $returnData = []): array
    {
        return DB::transaction(function () use ($rental, $returnData) {
            $returnCondition = $this->assessVehicleCondition($rental->vehicle, $returnData);
            $additionalCharges = $this->calculateAdditionalCharges($rental, $returnData, $returnCondition);

            // Update rental
            $rental->update([
                'status' => 'completed',
                'actual_return_date' => now(),
                'return_mileage' => $returnData['mileage'] ?? $rental->vehicle->mileage,
                'return_fuel_level' => $returnData['fuel_level'] ?? $rental->vehicle->fuel_level,
                'return_condition' => $returnCondition,
                'return_notes' => $returnData['notes'] ?? null,
                'additional_charges' => $additionalCharges['total'],
                'final_charges' => $additionalCharges['total'],
                'return_staff_id' => auth()->id(),
            ]);

            // Update vehicle
            $rental->vehicle->update([
                'status' => $this->determineVehicleStatus($returnCondition),
                'current_renter_id' => null,
                'mileage' => $returnData['mileage'] ?? $rental->vehicle->mileage,
                'fuel_level' => $returnData['fuel_level'] ?? $rental->vehicle->fuel_level,
            ]);

            // Process additional charges
            if ($additionalCharges['total'] > 0) {
                $chargeResult = $this->paymentService->chargeAdditionalFees(
                    $rental->customer,
                    $additionalCharges['total'],
                    $rental,
                    $additionalCharges['breakdown']
                );

                $rental->update([
                    'additional_payment_status' => $chargeResult['status'],
                    'additional_payment_reference' => $chargeResult['reference'],
                ]);
            }

            // Release deposit (minus any charges)
            $depositRelease = $rental->deposit_amount - $additionalCharges['total'];
            if ($depositRelease > 0) {
                $this->paymentService->releaseDeposit($rental, $depositRelease);
            }

            // Remove booking periods to free up the vehicle
            $rental->booking->bookedPeriods()->delete();

            event(new VehicleReturned($rental, $returnCondition, $additionalCharges));

            return [
                'rental' => $rental->fresh(),
                'return_condition' => $returnCondition,
                'additional_charges' => $additionalCharges,
                'deposit_released' => $depositRelease,
                'return_time' => now(),
            ];
        });
    }

    public function extendRental(
        VehicleRental $rental,
        Carbon $newReturnDate,
        string $reason = null
    ): VehicleRental {
        return DB::transaction(function () use ($rental, $newReturnDate, $reason) {
            $originalReturnDate = $rental->return_date;
            $additionalDays = $originalReturnDate->diffInDays($newReturnDate);
            
            // Calculate additional cost
            $additionalCost = $rental->vehicle->daily_rate * $additionalDays;
            $taxes = $additionalCost * 0.08; // 8% tax
            $totalAdditionalCost = $additionalCost + $taxes;

            // Update rental
            $rental->update([
                'return_date' => $newReturnDate,
                'total_days' => $rental->pickup_date->diffInDays($newReturnDate),
                'extension_fee' => $totalAdditionalCost,
                'total_amount' => $rental->total_amount + $totalAdditionalCost,
                'extension_reason' => $reason,
                'extended_at' => now(),
            ]);

            // Update booking
            $newPeriods = PeriodCollection::make([
                Period::make($rental->pickup_date, $newReturnDate)
            ]);

            $this->bookResource->run(
                periods: $newPeriods,
                bookableResource: $rental->vehicle->bookableResource,
                booker: $rental->customer,
                booking: $rental->booking,
                meta: array_merge($rental->booking->meta->toArray(), [
                    'extended_at' => now()->toISOString(),
                    'extension_reason' => $reason,
                    'original_return_date' => $originalReturnDate->toISOString(),
                    'additional_cost' => $totalAdditionalCost,
                ])
            );

            // Charge additional amount
            if ($totalAdditionalCost > 0) {
                $paymentResult = $this->paymentService->chargeAdditionalFees(
                    $rental->customer,
                    $totalAdditionalCost,
                    $rental,
                    ['extension_fee' => $totalAdditionalCost]
                );

                $rental->update([
                    'extension_payment_status' => $paymentResult['status'],
                    'extension_payment_reference' => $paymentResult['reference'],
                ]);
            }

            event(new RentalExtended($rental, $originalReturnDate, $newReturnDate));

            return $rental->fresh();
        });
    }

    private function assessVehicleCondition(Vehicle $vehicle, array $inspectionData): array
    {
        return [
            'exterior' => $inspectionData['exterior_condition'] ?? 'good',
            'interior' => $inspectionData['interior_condition'] ?? 'good',
            'mechanical' => $inspectionData['mechanical_condition'] ?? 'good',
            'damages' => $inspectionData['damages'] ?? [],
            'cleanliness' => $inspectionData['cleanliness'] ?? 'clean',
            'fuel_level' => $inspectionData['fuel_level'] ?? $vehicle->fuel_level,
            'mileage' => $inspectionData['mileage'] ?? $vehicle->mileage,
            'inspection_date' => now()->toISOString(),
            'inspector_id' => auth()->id(),
            'photos' => $inspectionData['photos'] ?? [],
        ];
    }

    private function calculateAdditionalCharges(
        VehicleRental $rental,
        array $returnData,
        array $returnCondition
    ): array {
        $charges = [];
        $total = 0;

        // Late return fee
        if (now()->isAfter($rental->return_date)) {
            $lateHours = now()->diffInHours($rental->return_date);
            $lateFee = ceil($lateHours / 24) * $rental->daily_rate * 1.5; // 150% of daily rate
            $charges['late_return'] = $lateFee;
            $total += $lateFee;
        }

        // Fuel charges
        $fuelLevelDifference = $rental->pickup_fuel_level - ($returnData['fuel_level'] ?? 100);
        if ($fuelLevelDifference > 5) { // 5% tolerance
            $fuelCharge = $fuelLevelDifference * 3; // $3 per percentage point
            $charges['fuel'] = $fuelCharge;
            $total += $fuelCharge;
        }

        // Damage charges
        if (!empty($returnCondition['damages'])) {
            $damageTotal = collect($returnCondition['damages'])->sum('estimated_cost');
            $charges['damages'] = $damageTotal;
            $total += $damageTotal;
        }

        // Cleaning fee
        if (($returnCondition['cleanliness'] ?? 'clean') === 'dirty') {
            $cleaningFee = 75;
            $charges['cleaning'] = $cleaningFee;
            $total += $cleaningFee;
        }

        // Excess mileage
        $expectedMileage = $rental->pickup_mileage + ($rental->total_days * 150); // 150 miles per day
        $actualMileage = $returnData['mileage'] ?? $rental->vehicle->mileage;
        $excessMiles = max(0, $actualMileage - $expectedMileage);
        if ($excessMiles > 0) {
            $mileageFee = $excessMiles * 0.25; // $0.25 per excess mile
            $charges['excess_mileage'] = $mileageFee;
            $total += $mileageFee;
        }

        return [
            'breakdown' => $charges,
            'total' => $total,
        ];
    }

    private function determineVehicleStatus(array $condition): string
    {
        if (!empty($condition['damages'])) {
            return 'maintenance_required';
        }

        if (($condition['cleanliness'] ?? 'clean') === 'dirty') {
            return 'cleaning_required';
        }

        return 'available';
    }
}
```

## Frontend Integration

### Vehicle Search API

```php
<?php
// app/Http/Controllers/Api/CarRentalController.php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\RentalLocation;
use App\Services\CarRentalService;
use Carbon\Carbon;
use Illuminate\Http\Request;

class CarRentalController extends Controller
{
    public function __construct(
        private CarRentalService $rentalService
    ) {}

    public function searchVehicles(Request $request)
    {
        $request->validate([
            'pickup_location_id' => 'required|exists:rental_locations,id',
            'pickup_date' => 'required|date|after:today',
            'return_date' => 'required|date|after:pickup_date',
            'category' => 'nullable|string',
            'transmission' => 'nullable|in:manual,automatic',
            'fuel_type' => 'nullable|in:gasoline,diesel,electric,hybrid',
            'min_seats' => 'nullable|integer|min:2|max:8',
            'features' => 'nullable|array',
        ]);

        $pickupLocation = RentalLocation::findOrFail($request->pickup_location_id);
        $pickupDate = Carbon::parse($request->pickup_date);
        $returnDate = Carbon::parse($request->return_date);

        $availableVehicles = $this->rentalService->searchAvailableVehicles(
            pickupLocation: $pickupLocation,
            pickupDate: $pickupDate,
            returnDate: $returnDate,
            category: $request->category,
            filters: $request->only(['transmission', 'fuel_type', 'min_seats', 'features'])
        );

        return response()->json([
            'search_criteria' => [
                'pickup_location' => $pickupLocation->name,
                'pickup_date' => $pickupDate->toDateString(),
                'return_date' => $returnDate->toDateString(),
                'days' => $pickupDate->diffInDays($returnDate),
            ],
            'available_vehicles' => $availableVehicles->map(function ($vehicle) use ($pickupDate, $returnDate) {
                $pricing = $vehicle->calculateRentalPrice($pickupDate, $returnDate);
                
                return [
                    'id' => $vehicle->id,
                    'make' => $vehicle->make,
                    'model' => $vehicle->model,
                    'year' => $vehicle->year,
                    'category' => $vehicle->category,
                    'transmission' => $vehicle->transmission,
                    'fuel_type' => $vehicle->fuel_type,
                    'seats' => $vehicle->seats,
                    'doors' => $vehicle->doors,
                    'features' => $vehicle->features,
                    'images' => $vehicle->images,
                    'daily_rate' => $vehicle->daily_rate,
                    'total_price' => $pricing['total'],
                    'pricing_breakdown' => $pricing,
                    'deposit_required' => $vehicle->deposit_required,
                ];
            }),
            'total_available' => $availableVehicles->count(),
        ]);
    }

    public function createRental(Request $request)
    {
        $request->validate([
            'customer' => 'required|array',
            'customer.first_name' => 'required|string|max:255',
            'customer.last_name' => 'required|string|max:255',
            'customer.email' => 'required|email',
            'customer.phone' => 'required|string',
            'customer.date_of_birth' => 'required|date',
            'customer.license_number' => 'required|string',
            'customer.license_country' => 'required|string',
            'customer.license_expiry' => 'required|date|after:today',
            'vehicle_id' => 'required|exists:vehicles,id',
            'pickup_location_id' => 'required|exists:rental_locations,id',
            'dropoff_location_id' => 'nullable|exists:rental_locations,id',
            'pickup_date' => 'required|date|after:today',
            'return_date' => 'required|date|after:pickup_date',
            'options' => 'nullable|array',
            'payment_method' => 'required|string',
        ]);

        $customer = Customer::firstOrCreate(
            ['email' => $request->customer['email']],
            $request->customer
        );

        $vehicle = Vehicle::findOrFail($request->vehicle_id);
        $pickupLocation = RentalLocation::findOrFail($request->pickup_location_id);
        $dropoffLocation = $request->dropoff_location_id 
            ? RentalLocation::findOrFail($request->dropoff_location_id)
            : null;

        try {
            $rental = $this->rentalService->createRental(
                customer: $customer,
                vehicle: $vehicle,
                pickupDate: Carbon::parse($request->pickup_date),
                returnDate: Carbon::parse($request->return_date),
                pickupLocation: $pickupLocation,
                dropoffLocation: $dropoffLocation,
                options: array_merge($request->options ?? [], [
                    'payment_method' => $request->payment_method,
                    'process_payment' => true,
                ])
            );

            return response()->json([
                'success' => true,
                'rental' => [
                    'id' => $rental->id,
                    'confirmation_code' => $rental->confirmation_code,
                    'customer_name' => $customer->full_name,
                    'vehicle' => $vehicle->full_name,
                    'pickup_location' => $pickupLocation->name,
                    'dropoff_location' => $dropoffLocation?->name ?? $pickupLocation->name,
                    'pickup_date' => $rental->pickup_date->toDateString(),
                    'return_date' => $rental->return_date->toDateString(),
                    'total_amount' => $rental->total_amount,
                    'deposit_amount' => $rental->deposit_amount,
                    'status' => $rental->status,
                ],
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Rental could not be completed',
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    public function getRental(VehicleRental $rental)
    {
        return response()->json([
            'rental' => [
                'id' => $rental->id,
                'confirmation_code' => $rental->confirmation_code,
                'status' => $rental->status,
                'customer' => [
                    'name' => $rental->customer->full_name,
                    'email' => $rental->customer->email,
                    'phone' => $rental->customer->phone,
                ],
                'vehicle' => [
                    'make' => $rental->vehicle->make,
                    'model' => $rental->vehicle->model,
                    'year' => $rental->vehicle->year,
                    'license_plate' => $rental->vehicle->license_plate,
                    'category' => $rental->vehicle->category,
                ],
                'dates' => [
                    'pickup_date' => $rental->pickup_date->toDateString(),
                    'return_date' => $rental->return_date->toDateString(),
                    'actual_pickup_date' => $rental->actual_pickup_date?->toDateString(),
                    'actual_return_date' => $rental->actual_return_date?->toDateString(),
                ],
                'locations' => [
                    'pickup' => $rental->pickupLocation->name,
                    'dropoff' => $rental->dropoffLocation->name,
                ],
                'pricing' => [
                    'base_amount' => $rental->base_amount,
                    'additional_fees' => $rental->additional_fees,
                    'taxes' => $rental->taxes,
                    'extension_fee' => $rental->extension_fee,
                    'additional_charges' => $rental->additional_charges,
                    'total_amount' => $rental->total_amount,
                    'deposit_amount' => $rental->deposit_amount,
                ],
                'payment_status' => $rental->payment_status,
            ],
        ]);
    }

    public function extendRental(Request $request, VehicleRental $rental)
    {
        $request->validate([
            'new_return_date' => 'required|date|after:' . $rental->return_date->toDateString(),
            'reason' => 'nullable|string|max:500',
        ]);

        try {
            $extendedRental = $this->rentalService->extendRental(
                rental: $rental,
                newReturnDate: Carbon::parse($request->new_return_date),
                reason: $request->reason
            );

            return response()->json([
                'success' => true,
                'message' => 'Rental extended successfully',
                'rental' => [
                    'id' => $extendedRental->id,
                    'new_return_date' => $extendedRental->return_date->toDateString(),
                    'extension_fee' => $extendedRental->extension_fee,
                    'new_total_amount' => $extendedRental->total_amount,
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Could not extend rental',
                'error' => $e->getMessage(),
            ], 422);
        }
    }
}
```

This comprehensive car rental example demonstrates advanced features like dynamic pricing, vehicle condition assessment, payment processing, extensions, and damage handling.