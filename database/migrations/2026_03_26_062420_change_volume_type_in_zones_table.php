
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Изменяем тип volume с DOUBLE(3,1) на DECIMAL(12,2)
     * для хранения объёма в кубических метрах (до 999,999,999,999.99 м³)
     */
    public function up(): void
    {
        Schema::table('zones', function (Blueprint $table) {
            $table->decimal('volume', 10, 2)->default(0)->change();
        });
    }

    public function down(): void
    {
        Schema::table('zones', function (Blueprint $table) {
            $table->double('volume', 3, 1)->nullable()->change();
        });
    }
};
