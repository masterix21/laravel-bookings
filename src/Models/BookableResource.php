<?php

namespace Masterix21\Bookings\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Masterix21\Bookings\Models\Concerns\BelongsToBookableArea;

class BookableResource extends Model
{
    use HasFactory;
    use BelongsToBookableArea;

    protected $guarded = [];

    public function model(): MorphTo
    {
        return $this->morphTo();
    }

    public function bookableTimetables(): HasMany
    {
        return $this->hasMany(config('bookings.models.bookable_timetable'), 'bookable_resource_id');
    }

    public function bookedPeriods(): HasManyThrough
    {
        return $this->hasManyThrough(
            related: config('bookings.models.booked_period'),
            through: config('bookings.models.booked_resource'),
            firstKey: 'bookable_resource_id',
            secondKey: 'booking_id',
            localKey: 'id',
            secondLocalKey: 'booking_id'
        );
    }
}
