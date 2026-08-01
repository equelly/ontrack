<div class="min-h-screen flex flex-col bg-slate-50" x-data="{ tab: 'zones' }">
    <!-- Dark Header -->
    <header class="bg-slate-900 text-white shadow-lg p-4">
        <div class="container mx-auto flex justify-between items-center">
            <h1 class="text-2xl font-bold">Панель Мастера</h1>
            <div>
                <span class="font-semibold">Смена:</span> {{ $shift->name ?? 'Не назначена' }}
            </div>
            <div class="flex space-x-4">
                <div>
                    <span class="font-semibold">Самосвалы:</span> {{ $trucksSummary['total'] }}
                    <span class="text-emerald-400">(Активных: {{ $trucksSummary['active'] }})</span>
                    <span class="text-red-400">(В поломке: {{ $trucksSummary['broken'] }})</span>
                </div>
            </div>
        </div>
    </header>

    <!-- Navigation Tabs -->
    <div class="bg-white border-b">
        <div class="container mx-auto flex space-x-2 p-2">
            <button @click="tab='zones'" 
                :class="tab === 'zones' ? 'bg-emerald-600 text-white' : 'text-gray-600 hover:bg-gray-100'" 
                class="px-4 py-2 rounded-md font-semibold uppercase">
                Зоны</button>
            <button @click="tab='dashboard'"
                :class="{ 'bg-emerald-600 text-white': tab === 'dashboard', 'text-gray-600 hover:bg-gray-100': tab !== 'dashboard' }"
                class="px-4 py-2 rounded-md font-semibold uppercase">
                Обзор
            </button>
            <button @click="tab='equipment'"
                :class="{ 'bg-emerald-600 text-white': tab === 'equipment', 'text-gray-600 hover:bg-gray-100': tab !== 'equipment' }"
                class="px-4 py-2 rounded-md font-semibold uppercase">
                Оборудование
            </button>
            <button @click="tab='maintenance'"
                :class="{ 'bg-emerald-600 text-white': tab === 'maintenance', 'text-gray-600 hover:bg-gray-100': tab !== 'maintenance' }"
                class="px-4 py-2 rounded-md font-semibold uppercase">
                Обслуживание
            </button>
            <button @click="tab='requests'"
                :class="{ 'bg-emerald-600 text-white': tab === 'requests', 'text-gray-600 hover:bg-gray-100': tab !== 'requests' }"
                class="px-4 py-2 rounded-md font-semibold uppercase">
                Заявки
            </button>
        </div>
    </div>

    <!-- Dashboard Tab -->
    <div class="container mx-auto p-4" x-show="tab === 'dashboard'" x-cloak>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
            <div class="bg-white rounded-xl border shadow-sm p-4">
                <h3 class="font-semibold text-gray-700 mb-2">Общий объем</h3>
                <p class="text-2xl font-bold text-emerald-600">{{ $tripMetrics['total_volume'] ?? 0 }} <span class="text-sm">тонн</span></p>
            </div>
            <div class="bg-white rounded-xl border shadow-sm p-4">
                <h3 class="font-semibold text-gray-700 mb-2">Рейсов всего</h3>
                <p class="text-2xl font-bold text-emerald-600">{{ $tripMetrics['total_trips'] ?? 0 }}</p>
            </div>
            <div class="bg-white rounded-xl border shadow-sm p-4">
                <h3 class="font-semibold text-gray-700 mb-2">Средняя скорость</h3>
                <p class="text-2xl font-bold text-emerald-600">
                    @if($tripMetrics['avg_speed'] !== null)
                        {{ round($tripMetrics['avg_speed'], 1) }}
                    @else
                        -
                    @endif
                    <span class="text-sm">км/ч</span>
                </p>

            </div>
            <div class="bg-white rounded-xl border shadow-sm p-4">
                <h3 class="font-semibold text-gray-700 mb-2">Среднее расстояние</h3>
                <p class="text-2xl font-bold text-emerald-600">{{ round($tripMetrics['avg_distance'] ?? 0, 1) }} <span class="text-sm">км</span></p>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div class="bg-white rounded-xl border shadow-sm p-4">
                <h3 class="font-semibold text-gray-700 mb-2">Проблемы</h3>
                <div class="space-y-2">
                    <div class="flex justify-between">
                        <span>Поломки техники:</span>
                        <span class="font-semibold text-red-500">{{ $issueSummary['breakdowns'] ?? 0 }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span>Задержки:</span>
                        <span class="font-semibold text-yellow-500">{{ $issueSummary['delays'] ?? 0 }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span>Забои в простое:</span>
                        <span class="font-semibold text-blue-500">{{ $issueSummary['idle'] ?? 0 }}</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
        <!-- ВКЛАДКА: Зоны разгрузки -->
        <div x-show="tab === 'zones'" x-cloak class="mt-4 space-y-4">
            @foreach($dumps as $dump)
                <div class="bg-white rounded-xl border shadow-sm overflow-hidden">
                    <div class="p-4 border-b bg-slate-50 flex justify-between items-center">
                        <h3 class="font-bold text-gray-800 uppercase text-sm"> {{ $dump->name_dump }}</h3>
                        <a href="{{ route('dump.edit', $dump->id) }}" target="_blank" class="text-xs text-emerald-600 hover:text-emerald-800 font-semibold uppercase">
                            Открыть настройки <i class="fas fa-external-link-alt ml-1"></i>
                        </a>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead class="bg-slate-50 border-b">
                                <tr>
                                    <th class="text-left p-3 font-semibold text-gray-600">Зона</th>
                                    <th class="text-left p-3 font-semibold text-gray-600">Порода</th>
                                    <th class="text-left p-3 font-semibold text-gray-600">Заполнение</th>
                                    <th class="text-left p-3 font-semibold text-gray-600">Статус</th>
                                    <th class="text-left p-3 font-semibold text-gray-600">Действие</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($dump->zones as $zone)
                                    @php $fillPercent = $zone->capacity > 0 ? min($zone->volume / $zone->capacity * 100, 100) : 0; @endphp
                                    <tr class="border-b {{ !$zone->delivery ? 'opacity-50' : '' }}">
                                        <td class="p-3 font-medium text-gray-800">{{ $zone->name_zone }}</td>
                                        <td class="p-3 text-gray-600">{{ $zone->rocks->pluck('name_rock')->join(', ') ?: 'Не указана' }}</td>
                                        <td class="p-3">
                                            <div class="flex items-center gap-2">
                                                <div class="w-24 bg-gray-200 rounded-full h-2.5">
                                                    <div class="h-2.5 rounded-full {{ $fillPercent > 90 ? 'bg-red-500' : ($fillPercent > 70 ? 'bg-amber-500' : 'bg-emerald-500') }}" style="width: {{ $fillPercent }}%"></div>
                                                </div>
                                                <span class="text-xs text-gray-500">{{ number_format($zone->volume, 0) }} / {{ number_format($zone->capacity, 0) }}</span>
                                            </div>
                                        </td>
                                        <td class="p-3">
                                            @if($zone->delivery)
                                                <span class="px-2 py-0.5 text-xs font-medium rounded-md bg-emerald-100 text-emerald-700">Принимает</span>
                                            @else
                                                <span class="px-2 py-0.5 text-xs font-medium rounded-md bg-slate-200 text-slate-700">Закрыта</span>
                                            @endif
                                        </td>
                                        <td class="p-3">
                                            <a href="{{ route('dump.edit', $dump->id) }}" target="_blank" class="text-gray-400 hover:text-emerald-600" title="Редактировать зону">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endforeach
        </div>
    <!-- Equipment Tab -->
    <div class="container mx-auto p-4" x-show="tab === 'equipment'" x-cloak>
        <div class="bg-white rounded-xl border shadow-sm p-4">
            <p>В разработке</p>
        </div>
    </div>

    <!-- Maintenance Tab -->
    <div class="container mx-auto p-4" x-show="tab === 'maintenance'" x-cloak>
        <div class="bg-white rounded-xl border shadow-sm p-4">
            <p>В разработке</p>
        </div>
    </div>

    <!-- Requests Tab -->
    <div class="container mx-auto p-4" x-show="tab === 'requests'" x-cloak>
        <div class="bg-white rounded-xl border shadow-sm p-4">
            <p>В разработке</p>
        </div>
    </div>
</div>