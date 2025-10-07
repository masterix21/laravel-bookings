<?php

use Masterix21\Bookings\Models\BookablePlanning;
use Masterix21\Bookings\Tests\TestClasses\Rate;

it('has planning morphOne relationship', function () {
    $rate = Rate::factory()->create();

    expect($rate->planning())->toBeInstanceOf(\Illuminate\Database\Eloquent\Relations\MorphOne::class);
});

it('can create planning through morphOne relationship', function () {
    $rate = Rate::factory()->create();

    $planning = $rate->planning()->create([
        'bookable_resource_id' => null,
        'monday' => true,
        'tuesday' => true,
        'wednesday' => true,
        'thursday' => true,
        'friday' => true,
        'saturday' => true,
        'sunday' => true,
        'starts_at' => $rate->valid_from,
        'ends_at' => $rate->valid_to,
    ]);

    expect($planning)->toBeInstanceOf(BookablePlanning::class)
        ->and($planning->source_type)->toBe(Rate::class)
        ->and($planning->source_id)->toBe($rate->id)
        ->and($planning->starts_at->equalTo($rate->valid_from))->toBeTrue()
        ->and($planning->ends_at->equalTo($rate->valid_to))->toBeTrue();
});

it('can access planning source through morphTo relationship', function () {
    $rate = Rate::factory()->create();

    $planning = $rate->planning()->create([
        'bookable_resource_id' => null,
        'starts_at' => $rate->valid_from,
        'ends_at' => $rate->valid_to,
    ]);

    $retrievedPlanning = BookablePlanning::find($planning->id);

    expect($retrievedPlanning->source)->toBeInstanceOf(Rate::class)
        ->and($retrievedPlanning->source->id)->toBe($rate->id)
        ->and($retrievedPlanning->source->name)->toBe($rate->name);
});

it('calls syncBookablePlanning when model is saved', function () {
    $rate = Rate::factory()->create();

    $rate->syncCallCount = 0;
    $rate->name = 'Updated Rate';
    $rate->save();

    expect($rate->syncCallCount)->toBe(1);
});

it('deletes associated planning when model is deleted', function () {
    $rate = Rate::factory()->create();

    $planning = $rate->planning()->create([
        'bookable_resource_id' => null,
        'starts_at' => $rate->valid_from,
        'ends_at' => $rate->valid_to,
    ]);

    $rateId = $rate->id;
    $planningId = $planning->id;

    expect(BookablePlanning::find($planningId))->not->toBeNull();

    $rate->delete();

    expect(Rate::find($rateId))->toBeNull()
        ->and(BookablePlanning::find($planningId))->toBeNull();
});

it('maintains relationship after multiple saves', function () {
    $rate = Rate::factory()->create();

    $planning = $rate->planning()->create([
        'bookable_resource_id' => null,
        'starts_at' => $rate->valid_from,
        'ends_at' => $rate->valid_to,
    ]);

    $rate->price = 199.99;
    $rate->save();

    $rate->name = 'Updated Again';
    $rate->save();

    $rate->refresh();

    expect($rate->planning)->not->toBeNull()
        ->and($rate->planning->id)->toBe($planning->id);
});
