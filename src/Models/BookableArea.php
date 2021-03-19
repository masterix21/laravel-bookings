<?php

namespace Masterix21\Bookings\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Masterix21\Bookings\Models\Concerns\HasBookedPeriods;

class BookableArea extends Model
{
    use HasFactory;
    use HasBookedPeriods;

    protected $guarded = [];

    public function bookableResources(): HasMany
    {
        return $this->hasMany(config('bookings.models.bookable_resource'));
    }

    public function bookablePlannings(): HasMany
    {
        return $this->hasMany(config('bookings.models.bookable_planning'));
    }
}
