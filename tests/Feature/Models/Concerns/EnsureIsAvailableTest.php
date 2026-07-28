<?php

use Masterix21\Bookings\Exceptions\NoFreeSizeException;
use Masterix21\Bookings\Exceptions\OutOfPlanningsException;
use Masterix21\Bookings\Exceptions\UnbookableException;
use Masterix21\Bookings\Models\BookablePlanning;
use Masterix21\Bookings\Models\BookableResource;
use Masterix21\Bookings\Models\BookedPeriod;
use Masterix21\Bookings\Models\Booking;
use Masterix21\Bookings\Tests\TestClasses\Product;
use Spatie\Period\Period;
use Spatie\Period\PeriodCollection;
use Spatie\TestTime\TestTime;

beforeEach(function () {
    TestTime::freeze();

    $this->periods = PeriodCollection::make(
        Period::make(
            now()->addDay()->format('Y-m-d'),
            now()->addDays(2)->format('Y-m-d')
        )
    );

    $this->makeResource = function (?Product $product = null, bool $withPlanning = true, int $size = 1): BookableResource {
        $product ??= Product::factory()->create();

        $resource = BookableResource::factory()->create([
            'resource_type' => Product::class,
            'resource_id' => $product->id,
            'size' => $size,
            'is_bookable' => true,
            'max' => null,
        ]);

        if ($withPlanning) {
            BookablePlanning::factory()->create([
                'bookable_resource_id' => $resource->id,
                'starts_at' => now()->startOfDay(),
                'ends_at' => now()->addDays(7)->endOfDay(),
            ]);
        }

        return $resource;
    };

    $this->saturate = function (BookableResource $resource): void {
        BookedPeriod::factory()->create([
            'bookable_resource_id' => $resource->id,
            'booking_id' => Booking::factory()->create()->id,
            'starts_at' => now()->addDay(),
            'ends_at' => now()->addDays(2),
        ]);
    };
});

it('passes on a resource with valid planning and free size', function () {
    $resource = ($this->makeResource)();

    expect(fn () => $resource->ensureIsAvailable($this->periods))
        ->not->toThrow(Exception::class);
});

it('throws OutOfPlanningsException when no planning covers the requested periods', function () {
    $resource = ($this->makeResource)(withPlanning: false);

    expect(fn () => $resource->ensureIsAvailable($this->periods))
        ->toThrow(OutOfPlanningsException::class);
});

it('throws UnbookableException when the resource is not bookable', function () {
    $resource = ($this->makeResource)();
    $resource->update(['is_bookable' => false]);

    expect(fn () => $resource->ensureIsAvailable($this->periods))
        ->toThrow(UnbookableException::class);
});

it('throws NoFreeSizeException when every size unit is taken', function () {
    $resource = ($this->makeResource)();
    ($this->saturate)($resource);

    expect(fn () => $resource->ensureIsAvailable($this->periods))
        ->toThrow(NoFreeSizeException::class);
});

it('passes when existing bookings occupy the window at different instants', function () {
    $resource = ($this->makeResource)(size: 2);

    BookedPeriod::factory()->create([
        'bookable_resource_id' => $resource->id,
        'booking_id' => Booking::factory()->create()->id,
        'starts_at' => now()->addDay()->setTime(10, 0),
        'ends_at' => now()->addDay()->setTime(11, 0),
    ]);

    BookedPeriod::factory()->create([
        'bookable_resource_id' => $resource->id,
        'booking_id' => Booking::factory()->create()->id,
        'starts_at' => now()->addDay()->setTime(14, 0),
        'ends_at' => now()->addDay()->setTime(15, 0),
    ]);

    // The two bookings never overlap each other: at any instant at most one
    // size unit is taken, so a resource with size 2 still has room.
    expect(fn () => $resource->ensureIsAvailable($this->periods))
        ->not->toThrow(Exception::class);
});

it('relations free size check uses per-instant concurrency', function () {
    $resource = ($this->makeResource)();
    $relatedResource = ($this->makeResource)(size: 2);

    BookedPeriod::factory()->create([
        'bookable_resource_id' => $relatedResource->id,
        'booking_id' => Booking::factory()->create()->id,
        'starts_at' => now()->addDay()->setTime(10, 0),
        'ends_at' => now()->addDay()->setTime(11, 0),
    ]);

    BookedPeriod::factory()->create([
        'bookable_resource_id' => $relatedResource->id,
        'booking_id' => Booking::factory()->create()->id,
        'starts_at' => now()->addDay()->setTime(14, 0),
        'ends_at' => now()->addDay()->setTime(15, 0),
    ]);

    expect(fn () => $resource->ensureIsAvailable($this->periods, collect([$relatedResource])))
        ->not->toThrow(Exception::class);
});

it('passes on a bookable model when at least one resource is available', function () {
    $product = Product::factory()->create();

    $saturatedResource = ($this->makeResource)($product);
    ($this->saturate)($saturatedResource);

    ($this->makeResource)($product);

    expect(fn () => $product->ensureIsAvailable($this->periods))
        ->not->toThrow(Exception::class);
});

it('throws on a bookable model when every resource is unavailable', function () {
    $product = Product::factory()->create();

    $firstResource = ($this->makeResource)($product);
    ($this->saturate)($firstResource);

    $secondResource = ($this->makeResource)($product);
    ($this->saturate)($secondResource);

    expect(fn () => $product->ensureIsAvailable($this->periods))
        ->toThrow(NoFreeSizeException::class);
});

it('throws UnbookableException on a bookable model without resources', function () {
    $product = Product::factory()->create();

    expect(fn () => $product->ensureIsAvailable($this->periods))
        ->toThrow(UnbookableException::class);
});
