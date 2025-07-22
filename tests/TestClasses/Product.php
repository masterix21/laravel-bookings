<?php

namespace Masterix21\Bookings\Tests\TestClasses;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Masterix21\Bookings\Models\Concerns\ImplementsBook;

class Product extends Model
{
    use HasFactory;
    use ImplementsBook;
}
