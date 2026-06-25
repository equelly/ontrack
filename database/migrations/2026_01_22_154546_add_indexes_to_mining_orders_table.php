<?php



use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mining_orders', function (Blueprint $table) {
    // Для сортировки по score среди active
    $table->index(['active', 'score'], 'mining_orders_active_score_idx');
    
    // Для поиска по truck + active
    $table->index(['truck_id', 'active'], 'mining_orders_truck_active_idx');
});

    }

    public function down(): void
    {
        Schema::table('mining_orders', function (Blueprint $table) {
            $table->dropIndex('mining_orders_active_truck_idx');
            $table->dropIndex('mining_orders_miner_round_idx');
        });
    }
};
