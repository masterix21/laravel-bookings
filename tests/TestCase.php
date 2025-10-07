<?php

namespace Masterix21\Bookings\Tests;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Masterix21\Bookings\BookingsServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        Factory::guessFactoryNamesUsing(
            fn (string $modelName) => 'Masterix21\\Bookings\\Tests\\database\\factories\\'.class_basename($modelName).'Factory'
        );

        $this->setUpDatabase($this->app);
    }

    protected function getPackageProviders($app)
    {
        return [
            BookingsServiceProvider::class,
        ];
    }

    protected function setUpDatabase($app)
    {
        $app['config']->set('database.default', 'sqlite');
        $app['config']->set('database.connections.sqlite', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        Schema::disableForeignKeyConstraints();

        collect(File::files(__DIR__.'/../database/migrations'))
            ->sortBy(fn ($file) => $file->getFilename())
            ->each(function ($file) {
                $migration = include $file;
                $migration->up();
            });

        $migration = include __DIR__.'/database/migrations/2014_10_12_000000_create_users_table.php';
        ($migration)->up();

        $migration = include __DIR__.'/database/migrations/2014_10_12_000001_create_products_table.php';
        ($migration)->up();

        $migration = include __DIR__.'/database/migrations/2014_10_12_000002_create_rates_table.php';
        ($migration)->up();

        Schema::enableForeignKeyConstraints();
    }
}
