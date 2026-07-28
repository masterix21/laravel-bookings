<?php

namespace Masterix21\Bookings\Tests\TestClasses;

use Illuminate\Database\Eloquent\SoftDeletes;

class SoftDeletableProduct extends Product
{
    use SoftDeletes;

    protected $table = 'products';
}
