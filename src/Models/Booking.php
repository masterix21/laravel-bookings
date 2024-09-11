<?php

namespace Masterix21\Bookings\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Kirschbaum\PowerJoins\PowerJoins;
use Masterix21\Bookings\Models\Concerns\Relationships\HasBookedPeriods;
use Masterix21\Bookings\Models\Concerns\UsesAddBookedResources;
use Masterix21\Bookings\Models\Concerns\UsesBookedPeriods;
use Masterix21\Bookings\Models\Concerns\UsesGenerateBookedPeriodChanges;
use Masterix21\Bookings\Models\Concerns\UsesGenerateBookedPeriods;

class Booking extends Model
{
    use HasFactory;
    use PowerJoins;
    use HasBookedPeriods;
    use UsesAddBookedResources;
    use UsesGenerateBookedPeriods;
    use UsesGenerateBookedPeriodChanges;
    use UsesBookedPeriods;

    protected $guarded = [];

    public function booker(): MorphTo
    {
        return $this->morphTo();
    }

    public function bookedResources(): HasMany
    {
        return $this->hasMany(config('bookings.models.booked_resource'));
    }
}
