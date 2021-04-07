<?php

namespace Masterix21\Bookings\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Kirschbaum\PowerJoins\PowerJoins;
use Masterix21\Bookings\Models\Concerns\Relationships\BelongsToBookableArea;
use Masterix21\Bookings\Models\Concerns\Relationships\BelongsToBookableResource;

class BookableRelation extends Model
{
    use HasFactory;
    use PowerJoins;
    use BelongsToBookableArea;
    use BelongsToBookableResource;

    protected $guarded = [];

    public function parentBookableArea(): BelongsTo
    {
        return $this->belongsTo(config('bookings.models.bookable_area'), 'parent_bookable_area_id');
    }

    public function parentBookableResource(): BelongsTo
    {
        return $this->belongsTo(config('bookings.models.bookable_resource'), 'parent_bookable_resource_id');
    }
}
