<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('miners', 'active')) {
            Schema::table('miners', function (Blueprint $table) {
                $table->dropColumn('active');
            });
        }
    }

    public function down(): void
    {
        if (!Schema::hasColumn('miners', 'active')) {
            Schema::table('miners', function (Blueprint $table) {
                $table->boolean('active')->default(true)->after('name_miner');
            });
            \Illuminate\Support\Facades\DB::table('miners')
                ->where('status', 'active')->update(['active' => true]);
            \Illuminate\Support\Facades\DB::table('miners')
                ->where('status', '!=', 'active')->update(['active' => false]);
        }
    }
};
