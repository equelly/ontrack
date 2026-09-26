<?php

namespace App\Console\Commands;

use App\Models\TruckTrip;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;


class CloseStaleTrips extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:close-stale-trips';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        // Найти рейсы, которые длятся больше 4 часов и не завершены
        $staleTrips = TruckTrip::whereNull('completed_at')
            ->where('started_at', '<', now()->subHours(4))
            ->get();

        foreach ($staleTrips as $trip) {
            $trip->update(['completed_at' => now()]);
            $trip->truck->update(['status' => 'completed']);
            Log::warning("Closed stale trip {$trip->id} for truck {$trip->truck_id}");
        }

        $this->info("Closed {$staleTrips->count()} stale trips");
    }
}
