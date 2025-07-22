<?php

namespace Masterix21\Bookings\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BookableRelation extends Model
{
    use HasFactory;

    protected $guarded = [];

    public function parentBookableResource(): BelongsTo
    {
        return $this->belongsTo(config('bookings.models.bookable_resource'), 'parent_bookable_resource_id');
    }

    public function bookableResource(): BelongsTo
    {
        return $this->belongsTo(config('bookings.models.bookable_resource'));
    }
}
