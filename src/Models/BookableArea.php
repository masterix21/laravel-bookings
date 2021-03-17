<?php

namespace Masterix21\Bookings\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BookableArea extends Model
{
    use HasFactory;

    protected $guarded = [];

    public function bookableResources(): HasMany
    {
        return $this->hasMany(config('bookings.models.bookable_resource'), 'bookable_area_id');
    }

    public function bookableTimetables(): HasMany
    {
        return $this->hasMany(config('bookings.models.bookable_timetable'), 'bookable_area_id');
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(config('bookings.models.booking'));
    }
}
