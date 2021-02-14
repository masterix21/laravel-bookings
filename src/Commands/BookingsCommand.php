<?php

namespace LucaLongo\Bookings\Commands;

use Illuminate\Console\Command;

class BookingsCommand extends Command
{
    public $signature = 'laravel-bookings';

    public $description = 'My command';

    public function handle()
    {
        $this->comment('All done');
    }
}
