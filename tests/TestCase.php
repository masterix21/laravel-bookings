<?php

namespace Masterix21\Bookings\Tests;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Masterix21\Bookings\BookingsServiceProvider;
use Masterix21\Bookings\Tests\Database\Migrations\CreateProductsTable;
use Masterix21\Bookings\Tests\Database\Migrations\CreateUsersTable;
use Masterix21\Bookings\Tests\TestClasses\User;
use Orchestra\Testbench\TestCase as Orchestra;

class TestCase extends Orchestra
{
    public function setUp(): void
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
            \Spatie\LaravelRay\RayServiceProvider::class,
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

        collect(File::files(__DIR__ .'/../database/migrations'))
           ->each(function ($file) {
               include_once $file->getRealPath();
               $class = Str::of($file->getFilename())->before('.php')->studly();
               resolve((string) $class)->up();
           });

        include_once __DIR__ .'/database/migrations/2014_10_12_000000_create_users_table.php';
        (new CreateUsersTable())->up();

        include_once __DIR__ .'/database/migrations/2014_10_12_000001_create_products_table.php';
        (new CreateProductsTable())->up();

        Schema::enableForeignKeyConstraints();
    }
}
