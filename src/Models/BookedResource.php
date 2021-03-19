<?php

namespace Masterix21\Bookings\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Masterix21\Bookings\Models\Concerns\BelongsToBookableArea;
use Masterix21\Bookings\Models\Concerns\BelongsToBookableResource;
use Masterix21\Bookings\Models\Concerns\BelongsToBooking;
use Masterix21\Bookings\Models\Concerns\HasParentAndChildren;

class BookedResource extends Model
{
    use HasFactory;
    use BelongsToBooking;
    use HasParentAndChildren;
    use BelongsToBookableArea;
    use BelongsToBookableResource;

    protected $guarded = [];

    public function bookedPeriods(): HasMany
    {
        return $this->hasMany(config('bookings.models.booked_period'), 'booked_resource_id');
    }

    public function unbookedPeriods(): HasMany
    {
        return $this->hasMany(config('bookings.models.unbooked_period'), 'booked_resource_id');
    }
}
