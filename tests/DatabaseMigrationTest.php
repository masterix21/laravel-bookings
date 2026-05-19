<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

it('asserts migrations are up', function () {
    collect(config('bookings.models'))->each(function ($model) {
        expect(Schema::hasTable(resolve($model)->getTable()))->toBeTrue();
    });
});
