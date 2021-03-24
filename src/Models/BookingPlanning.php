<?php

namespace Masterix21\Bookings\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Masterix21\Bookings\Models\Concerns\Relationships\BelongsToBookedResource;
use Masterix21\Bookings\Models\Concerns\Relationships\BelongsToBooking;
use Masterix21\Bookings\Models\Concerns\Scopes\HasWherePeriodFromDatesScope;

class BookingPlanning extends Model
{
    use HasFactory;
    use BelongsToBooking;
    use BelongsToBookedResource;
    use HasWherePeriodFromDatesScope;

    protected $guarded = [];

    protected $casts = [
        'is_excluded' => 'boolean',
    ];
}
