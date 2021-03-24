<?php

namespace Masterix21\Bookings\Models\Concerns\Relationships;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Masterix21\Bookings\Events\Booking\GeneratedBookedPeriods;
use Masterix21\Bookings\Events\Booking\GeneratingBookedPeriods;
use Masterix21\Bookings\Models\BookedPeriod;
use Masterix21\Bookings\Models\BookedResource;
use Masterix21\Bookings\Period;
use Spatie\Period\Period as SpatiePeriod;

/** @mixin Model */
trait HasBookedPeriods
{
    public function bookedPeriods(): HasMany
    {
        return $this->hasMany(config('bookings.models.booked_period'));
    }
}
