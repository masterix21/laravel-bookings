<?php

namespace Masterix21\Bookings\Tests\TestClasses;

use Illuminate\Database\Eloquent\SoftDeletes;

class SoftDeletableRate extends Rate
{
    use SoftDeletes;

    protected $table = 'rates';
}
