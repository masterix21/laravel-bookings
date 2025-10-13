<?php

declare(strict_types=1);

namespace Masterix21\Bookings\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int|null $parent_bookable_resource_id
 * @property int|null $bookable_resource_id
 * @property int|null $parent_bookable_area_id
 * @property int|null $bookable_area_id
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 */
class BookableRelation extends Model
{
    use HasFactory;

    protected $fillable = [
        'parent_bookable_resource_id',
        'bookable_resource_id',
        'parent_bookable_area_id',
        'bookable_area_id',
    ];

    public function parentBookableResource(): BelongsTo
    {
        return $this->belongsTo(config('bookings.models.bookable_resource'), 'parent_bookable_resource_id');
    }

    public function bookableResource(): BelongsTo
    {
        return $this->belongsTo(config('bookings.models.bookable_resource'));
    }
}
