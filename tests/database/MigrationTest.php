<?php
namespace Masterix21\Bookings\Tests\database;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Masterix21\Bookings\Tests\TestCase;

class MigrationTest extends TestCase
{
    /** @test */
    public function assert_migrations_are_up()
    {
        collect(File::files(__DIR__ .'/../../database/migrations'))
            ->each(function ($file) {
                include_once $file->getRealPath();
                $class = Str::of($file->getFilename())->before('.php.stub')->studly();
                resolve((string) $class)->up();
            });

        collect(config('bookings.models'))->each(function ($model) {
            $this->assertTrue(Schema::hasTable(resolve($model)->getTable()));
        });
    }
}
