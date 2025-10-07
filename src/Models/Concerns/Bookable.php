<?php

namespace Masterix21\Bookings\Models\Concerns;

use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Masterix21\Bookings\Models\BookableResource;

interface Bookable
{
    public function bookableResources(): MorphMany;

    public function bookableResource(): MorphOne;

    public function syncBookableResource(BookableResource $resource): void;
}
