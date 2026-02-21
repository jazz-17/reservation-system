<?php

namespace App\Console\Commands;

use App\Actions\Reservations\ReservationService;
use Illuminate\Console\Command;

class ExpirePendingReservations extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'reservations:expire-pending';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Expire pending reservations that have not been approved in time';

    /**
     * Execute the console command.
     */
    public function handle(ReservationService $reservations): int
    {
        $count = $reservations->expirePending();

        $this->info("Expired: {$count}");

        return self::SUCCESS;
    }
}
