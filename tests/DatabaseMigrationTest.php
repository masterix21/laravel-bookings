<?php
namespace Masterix21\Bookings\Tests;

use Illuminate\Support\Facades\Schema;

class DatabaseMigrationTest extends TestCase
{
    /** @test */
    public function assert_migrations_are_up()
    {
        collect(config('bookings.models'))->each(function ($model) {
            $this->assertTrue(Schema::hasTable(resolve($model)->getTable()));
        });
    }
}
