<?php

namespace LucaLongo\Bookings;

use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use LucaLongo\Bookings\Commands\BookingsCommand;

class BookingsServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        /*
         * This class is a Package Service Provider
         *
         * More info: https://github.com/spatie/laravel-package-tools
         */
        $package
            ->name('laravel-bookings')
            ->hasConfigFile()
            ->hasViews()
            ->hasMigration('create_laravel_bookings_table')
            ->hasCommand(BookingsCommand::class);
    }
}
