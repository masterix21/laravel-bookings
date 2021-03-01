<?php
namespace Masterix21\Bookings\Tests\database;

use Illuminate\Support\Facades\Schema;
use Masterix21\Bookings\Tests\TestCase;

class MigrationTest extends TestCase
{
    /** @test */
    public function assert_migrations_are_up()
    {
        collect(config('bookings.models'))->each(function ($model) {
            $this->assertTrue(Schema::hasTable(resolve($model)->getTable()));
        });
    }
}
