<?php

use Masterix21\Bookings\Models\BookablePlanning;
use Masterix21\Bookings\Models\BookableResource;
use Masterix21\Bookings\Tests\TestClasses\SoftDeletableProduct;
use Masterix21\Bookings\Tests\TestClasses\SoftDeletableRate;

it('keeps bookable resources when the bookable model is soft deleted', function () {
    $product = SoftDeletableProduct::create(['name' => 'Soft product']);

    $resource = $product->bookableResources()->create([
        'is_visible' => true,
        'is_bookable' => true,
    ]);

    $product->delete();

    expect($product->fresh()->trashed())->toBeTrue()
        ->and(BookableResource::find($resource->id))->not->toBeNull();

    $product->restore();

    expect($product->bookableResources()->count())->toBe(1);
});

it('deletes bookable resources when the bookable model is force deleted', function () {
    $product = SoftDeletableProduct::create(['name' => 'Soft product']);

    $resource = $product->bookableResources()->create([
        'is_visible' => true,
        'is_bookable' => true,
    ]);

    $product->forceDelete();

    expect(BookableResource::find($resource->id))->toBeNull();
});

it('keeps planning when the planning source is soft deleted', function () {
    $rate = SoftDeletableRate::create(['name' => 'Soft rate']);

    $planning = $rate->planning()->create([
        'starts_at' => now()->startOfDay(),
        'ends_at' => now()->addDays(7)->endOfDay(),
    ]);

    $rate->delete();

    expect($rate->fresh()->trashed())->toBeTrue()
        ->and(BookablePlanning::find($planning->id))->not->toBeNull();

    $rate->restore();

    expect($rate->planning()->count())->toBe(1);
});

it('deletes planning when the planning source is force deleted', function () {
    $rate = SoftDeletableRate::create(['name' => 'Soft rate']);

    $planning = $rate->planning()->create([
        'starts_at' => now()->startOfDay(),
        'ends_at' => now()->addDays(7)->endOfDay(),
    ]);

    $rate->forceDelete();

    expect(BookablePlanning::find($planning->id))->toBeNull();
});
