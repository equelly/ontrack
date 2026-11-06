        <div wire:key="tab-dashboard" class="mt-4 space-y-6">

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
                        <!-- Активные маршруты (Настроенные грузопотоки) -->
            <div class="bg-white rounded-xl border shadow-sm overflow-hidden">
                <div class="p-4 border-b font-bold text-gray-800 uppercase text-sm flex items-center gap-2">
                    <i class="fas fa-route text-blue-500"></i> Активные маршруты (Куда направлены грузопотоки)
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-slate-50 border-b">
                            <tr>
                                <th class="text-left p-3 font-semibold text-gray-600">Забой (Откуда)</th>
                                <th class="text-left p-3 font-semibold text-gray-600">Перегрузка (Куда)</th>
                                <th class="text-left p-3 font-semibold text-gray-600">Зона</th>
                                <th class="text-left p-3 font-semibold text-gray-600">Порода</th>
                                <th class="text-left p-3 font-semibold text-gray-600">Расстояние</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse(($activeRoutes ?? []) as $route)
                                <tr wire:key="route-{{ $loop->iteration }}" class="border-b hover:bg-slate-50">
                                    <td class="p-3 text-gray-700">{{ $route->miner?->name_miner ?? '—' }}</td>
                                    <td class="p-3 text-gray-600">{{ $route->dump?->name_dump ?? '—' }}</td>
                                    <td class="p-3 font-medium text-gray-800">{{ $route->zone?->name_zone ?? '—' }}</td>
                                    <td class="p-3">
                                        @if($route->rock)
                                            <span class="px-2 py-0.5 text-xs rounded bg-cyan-100 text-cyan-700">{{ $route->rock->name_rock }}</span>
                                        @else
                                            <span class="text-gray-400">—</span>
                                        @endif
                                    </td>
                                    <td class="p-3 text-gray-500">{{ $route->distance_km ?? '—' }} км</td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="p-4 text-center text-gray-500">Нет активных настроенных маршрутов</td></tr>
                            @endforelse
                        </tbody>
                    </table>
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
                            @forelse(($activeHauls ?? []) as $haul)
                                <tr wire:key="haul-{{ $loop->iteration }}" class="border-b hover:bg-slate-50">
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
                            <div wire:key="zv-{{ $loop->iteration }}" class="flex justify-between items-center pb-2 border-b last:border-0">
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
                                    <tr wire:key="hs-{{ $loop->iteration }}" class="border-b hover:bg-slate-50">
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
