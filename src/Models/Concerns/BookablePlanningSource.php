<?php

namespace Masterix21\Bookings\Models\Concerns;

use Illuminate\Database\Eloquent\Relations\MorphOne;

interface BookablePlanningSource
{
    public function planning(): MorphOne;

    public function syncBookablePlanning(): void;
}
