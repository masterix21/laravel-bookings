<?php

namespace Masterix21\Bookings\Models\Concerns;

use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;

interface Bookable
{
    public function bookableResources(): MorphMany;

    public function bookableResource(): MorphOne;
}
