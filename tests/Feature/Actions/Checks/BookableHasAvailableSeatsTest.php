<?php

namespace Masterix21\Bookings\Tests\Feature\Actions\Checks;

use Masterix21\Bookings\Tests\Concerns\CreatesAreasAndResources;
use Masterix21\Bookings\Tests\TestCase;

class BookableHasAvailableSeatsTest extends TestCase
{
    use CreatesAreasAndResources;

    /** @test */
    public function it_works_without_exceptions()
    {
        $this->createsAreasAndResources();
    }
}
