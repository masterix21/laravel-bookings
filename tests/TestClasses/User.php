<?php

namespace Masterix21\Bookings\Tests\TestClasses;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;

class User extends \Illuminate\Foundation\Auth\User
{
    use HasFactory;

    public function bookings(): HasMany
    {
        return $this->hasMany(config('bookings.models.booking'), 'user_id');
    }
}
