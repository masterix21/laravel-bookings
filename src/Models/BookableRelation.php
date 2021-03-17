<?php

namespace Masterix21\Bookings\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Masterix21\Bookings\Models\Concerns\BelongsToBookableArea;
use Masterix21\Bookings\Models\Concerns\BelongsToBookableResource;
use Masterix21\Bookings\Models\Concerns\HasParentAndChildren;

class BookableRelation extends Model
{
    use HasFactory;
    use HasParentAndChildren;
    use BelongsToBookableArea;
    use BelongsToBookableResource;

    protected $guarded = [];
}
