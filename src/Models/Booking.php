<?php

namespace Masterix21\Bookings\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Kirschbaum\PowerJoins\PowerJoins;
use Masterix21\Bookings\Models\Concerns\Relationships\HasBookedPeriods;
use Masterix21\Bookings\Models\Concerns\UsesAddBookedResources;
use Masterix21\Bookings\Models\Concerns\UsesBookingPlanningPeriods;
use Masterix21\Bookings\Models\Concerns\UsesGenerateBookedPeriods;
use Masterix21\Bookings\Models\Concerns\UsesGenerateBookingPlannings;

class Booking extends Model
{
    use HasFactory;
    use PowerJoins;
    use HasBookedPeriods;
    use UsesAddBookedResources;
    use UsesGenerateBookingPlannings;
    use UsesGenerateBookedPeriods;
    use UsesBookingPlanningPeriods;

    protected $guarded = [];

    public function user(): BelongsTo
    {
        return $this->belongsTo(config('bookings.models.user'), 'user_id');
    }

    public function bookedResources(): HasMany
    {
        return $this->hasMany(config('bookings.models.booked_resource'));
    }

    public function bookingPlannings(): HasMany
    {
        return $this->hasMany(config('bookings.models.booking_planning'));
    }
}
