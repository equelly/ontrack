<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Добавить trucks.is_searching_route — флаг что грузовик ждёт доступный маршрут.
 *
 * Event-driven архитектура: когда забой освобождается (завершение погрузки),
 * TruckStatusService находит все грузовики с is_searching_route=true и
 * broadcast'ит им событие RouteAvailable. DriverPanel получает событие
 * и автоматически вызывает assignRoute().
 *
 * До этого фикса isSearchingRoute хранился только в памяти Livewire-компонента
 * DriverPanel — после перезагрузки страницы терялся. И сервер не знал кто ждёт.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trucks', function (Blueprint $table) {
            $table->boolean('is_searching_route')->default(false)->after('status');
            $table->index('is_searching_route');
        });
    }

    public function down(): void
    {
        Schema::table('trucks', function (Blueprint $table) {
            $table->dropIndex(['is_searching_route']);
            $table->dropColumn('is_searching_route');
        });
    }
};