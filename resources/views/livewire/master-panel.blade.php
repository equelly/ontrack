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

        <!-- ВКЛАДКА: Обзор -->
        <div x-show="tab === 'dashboard'" x-cloak class="mt-4 space-y-6">
            
            <!-- Карточки KPI -->
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                <div class="bg-white p-4 rounded-xl border shadow-sm text-center">
                    <p class="text-xs text-gray-500 uppercase">Общий объем</p>
                    <p class="text-2xl font-bold text-emerald-600">{{ number_format($tripMetrics['total_volume'], 1) }} т</p>
                </div>
                <div class="bg-white p-4 rounded-xl border shadow-sm text-center">
                    <p class="text-xs text-gray-500 uppercase">Рейсов</p>
                    <p class="text-2xl font-bold text-blue-600">{{ $tripMetrics['total_trips'] }}</p>
                </div>
                <div class="bg-white p-4 rounded-xl border shadow-sm text-center">
                    <p class="text-xs text-gray-500 uppercase">Ср. скорость</p>
                    <p class="text-2xl font-bold text-purple-600">{{ $tripMetrics['avg_speed'] }} <span class="text-sm">@if($tripMetrics['avg_speed'] != '-') км/ч @endif</span></p>
                </div>
                <div class="bg-white p-4 rounded-xl border shadow-sm text-center">
                    <p class="text-xs text-gray-500 uppercase">Ср. расстояние</p>
                    <p class="text-2xl font-bold text-cyan-600">{{ $tripMetrics['avg_distance'] }} <span class="text-sm">@if($tripMetrics['avg_distance'] != '-') км @endif</span></p>
                </div>
            </div>

            <!-- Активные перевозки (в данный момент) -->
            <div class="bg-white rounded-xl border shadow-sm overflow-hidden">
                <div class="p-4 border-b font-bold text-gray-800 uppercase text-sm flex items-center gap-2">
                    <span class="w-3 h-3 bg-emerald-500 rounded-full animate-pulse"></span>
                    Активные перевозки (в данный момент)
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-slate-50 border-b">
                            <tr>
                                <th class="text-left p-3 font-semibold text-gray-600">Самосвал</th>
                                <th class="text-left p-3 font-semibold text-gray-600">Забой (откуда)</th>
                                <th class="text-left p-3 font-semibold text-gray-600">Порода</th>
                                <th class="text-left p-3 font-semibold text-gray-600">Направление (куда)</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($activeHauls as $haul)
                                <tr class="border-b hover:bg-slate-50">
                                    <td class="p-3 font-medium text-gray-800">{{ $haul->truck?->number ?? '—' }}</td>
                                    <td class="p-3 text-gray-600">{{ $haul->miner?->name_miner ?? '—' }}</td>
                                    <td class="p-3"><span class="px-2 py-0.5 text-xs rounded bg-cyan-100 text-cyan-700">{{ $haul->rock?->name_rock ?? '—' }}</span></td>
                                    <td class="p-3 text-gray-600">
                                        {{ $haul->zone?->dump?->name_dump ?? '—' }} / <span class="font-medium text-gray-800">{{ $haul->zone?->name_zone ?? 'Не назначена' }}</span>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="p-4 text-center text-gray-500">Нет активных перевозок</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Сводки за смену -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                
                <!-- Объемы по зонам -->
                <div class="bg-white rounded-xl border shadow-sm overflow-hidden">
                    <div class="p-4 border-b font-bold text-gray-800 uppercase text-sm">Объемы по зонам (за смену)</div>
                    <div class="p-4 space-y-3">
                        @forelse($zoneVolumes as $zv)
                            <div class="flex justify-between items-center pb-2 border-b last:border-0">
                                <span class="text-sm text-gray-700">
                                    {{ $zv->zone?->dump?->name_dump ?? '—' }} - <span class="font-medium">{{ $zv->zone?->name_zone ?? '—' }}</span>
                                </span>
                                <span class="px-3 py-1 bg-emerald-100 text-emerald-700 rounded font-bold text-sm">{{ number_format($zv->total_volume, 1) }} т</span>
                            </div>
                        @empty
                            <p class="text-gray-500 text-sm text-center py-4">Нет данных за смену</p>
                        @endforelse
                    </div>
                </div>

                <!-- Перевозки Забой -> Зона -->
                <div class="bg-white rounded-xl border shadow-sm overflow-hidden">
                    <div class="p-4 border-b font-bold text-gray-800 uppercase text-sm">Перевозки: Забой → Зона (за смену)</div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead class="bg-slate-50 border-b">
                                <tr>
                                    <th class="text-left p-3 font-semibold text-gray-600">Забой</th>
                                    <th class="text-left p-3 font-semibold text-gray-600">Зона</th>
                                    <th class="text-left p-3 font-semibold text-gray-600">Объем</th>
                                    <th class="text-left p-3 font-semibold text-gray-600">Рейсов</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($haulsSummary as $hs)
                                    <tr class="border-b hover:bg-slate-50">
                                        <td class="p-3 text-gray-700">{{ $hs->miner?->name_miner ?? '—' }}</td>
                                        <td class="p-3 text-gray-600">{{ $hs->zone?->name_zone ?? '—' }}</td>
                                        <td class="p-3 font-medium text-emerald-700">{{ number_format($hs->total_volume, 1) }} т</td>
                                        <td class="p-3 text-gray-500">{{ $hs->trips_count }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="4" class="p-4 text-center text-gray-500">Нет данных за смену</td></tr>
                                @endforelse
                            </tbody>
                        </table>
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