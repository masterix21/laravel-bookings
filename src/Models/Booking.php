<?php

namespace Masterix21\Bookings\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Booking extends Model
{
    use HasFactory;

    protected $guarded = [];

    public function user(): BelongsTo
    {
        return $this->belongsTo(config('bookings.models.user'), 'user_id');
    }

    public function bookedResources(): HasMany
    {
        return $this->hasMany(config('bookings.models.booked_resource'), 'booking_id');
    }

    public function bookedPeriods(): HasMany
    {
        return $this->hasMany(config('bookings.models.booked_period'), 'booking_id');
    }

    public function unbookedPeriods(): HasMany
    {
        return $this->hasMany(config('bookings.models.unbooked_period'), 'booking_id');
    }

    public function bookedDates(): HasMany
    {
        return $this->hasMany(config('bookings.models.booked_dates'), 'booking_id');
    }
}
