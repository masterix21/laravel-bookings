<?php

namespace Masterix21\Bookings\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Masterix21\Bookings\Models\Concerns\BelongsToBookableArea;
use Masterix21\Bookings\Models\Concerns\BelongsToBookableResource;
use Masterix21\Bookings\Models\Concerns\BelongsToBooking;
use Masterix21\Bookings\Models\Concerns\HasBookedPeriods;
use Masterix21\Bookings\Models\Concerns\HasParentAndChildren;

class BookedResource extends Model
{
    use HasFactory;
    use BelongsToBooking;
    use HasParentAndChildren;
    use BelongsToBookableArea;
    use BelongsToBookableResource;
    use HasBookedPeriods;

    protected $guarded = [];

    public function bookingPlannings(): HasMany
    {
        return $this->hasMany(config('bookings.models.booking_planning'));
    }
}
