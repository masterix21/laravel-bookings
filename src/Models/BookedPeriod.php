<?php
namespace Masterix21\Bookings\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Masterix21\Bookings\Models\Concerns\BelongsToBookableArea;
use Masterix21\Bookings\Models\Concerns\BelongsToBookableResource;
use Masterix21\Bookings\Models\Concerns\BelongsToBooking;
use Masterix21\Bookings\Models\Concerns\Scopes\HasWherePeriodFromDatesScope;

class BookedPeriod extends Model
{
    use HasFactory;
    use BelongsToBooking;
    use BelongsToBookableArea;
    use BelongsToBookableResource;
    use HasWherePeriodFromDatesScope;

    protected $guarded = [];
}
