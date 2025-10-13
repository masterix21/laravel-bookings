<?php

use Illuminate\Support\Facades\Schema;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

it('asserts migrations are up', function () {
    collect(config('bookings.models'))->each(function ($model) {
        expect(Schema::hasTable(resolve($model)->getTable()))->toBeTrue();
    });
});
