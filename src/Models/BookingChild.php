<?php

namespace Masterix21\Bookings\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BookingChild extends Model
{
    use HasFactory;

    public function booking(): BelongsTo
    {
        return $this->belongsTo(config('bookings.models.booking'));
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(config('bookings.models.booking_child'));
    }

    public function resource(): BelongsTo
    {
        return $this->belongsTo(config('bookings.models.resource'));
    }
}
