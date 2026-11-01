        <div x-show="tab === 'zonesTab'" x-cloak class="mt-4" id="zonesTab">
            @php
                $zonesLoad = $this->zones_load;
                $overloadedZones = $this->overloaded_zones;
            @endphp

            <div class="flex justify-between items-center mb-4">
                <h5 class="font-bold text-gray-800 uppercase text-sm">Мониторинг зон разгрузки</h5>
                            <!-- Плашка пакетного применения -->
                        <div class="bg-amber-50 border-l-4 border-amber-400 p-4 mb-6 rounded flex flex-col sm:flex-row justify-between items-center gap-4">
                            <div class="text-amber-700 text-sm">
                                <i class="fas fa-exclamation-triangle mr-2"></i>
                                <strong>Внимание!</strong> Изменения статусов зон вступят в силу для самосвалов только после нажатия кнопки.
                            </div>
                                <button wire:click="applyZoneChanges" wire:loading.attr="disabled" class="px-6 py-2 bg-emerald-600 text-white rounded-md font-semibold uppercase text-sm hover:bg-emerald-700 whitespace-nowrap w-full sm:w-auto">
                                    <span wire:loading.remove wire:target="applyZoneChanges"><i class="fas fa-truck mr-1"></i> Перенаправить технику</span>
                                    <span wire:loading wire:target="applyZoneChanges"><i class="fas fa-spinner fa-spin mr-1"></i> Идет расчет...</span>
                                </button>
                        </div>
                <div class="flex gap-2">
                    @if(count($overloadedZones) > 0)
                        <button class="px-4 py-2 bg-amber-500 text-white rounded-md text-xs font-semibold uppercase hover:bg-amber-600" wire:click="balanceZones" wire:loading.attr="disabled">
                            <span wire:loading.remove><i class="fas fa-balance-scale mr-1"></i> Балансировка</span>
                            <span wire:loading><i class="fas fa-spinner fa-spin mr-1"></i> Балансировка...</span>
                        </button>
                    @endif
                </div>
            </div>

            <!-- Статистика по зонам -->
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
                <div class="bg-white p-4 rounded-xl border shadow-sm text-center">
                    <p class="text-xs text-gray-500 uppercase">Всего зон</p>
                    <p class="text-2xl font-bold text-gray-800">{{ count($zonesLoad) }}</p>
                </div>
                <div class="bg-white p-4 rounded-xl border shadow-sm text-center">
                    <p class="text-xs text-gray-500 uppercase">Доступны</p>
                    <p class="text-2xl font-bold text-emerald-600">{{ count(array_filter($zonesLoad, fn($z) => $z['status'] === 'available')) }}</p>
                </div>
                <div class="bg-white p-4 rounded-xl border shadow-sm text-center">
                    <p class="text-xs text-gray-500 uppercase">Заняты</p>
                    <p class="text-2xl font-bold text-amber-500">{{ count(array_filter($zonesLoad, fn($z) => $z['status'] === 'busy')) }}</p>
                </div>
                <div class="bg-white p-4 rounded-xl border shadow-sm text-center">
                    <p class="text-xs text-gray-500 uppercase">Перегружены</p>
                    <p class="text-2xl font-bold text-red-600">{{ count(array_filter($zonesLoad, fn($z) => $z['status'] === 'overloaded')) }}</p>
                </div>
            </div>

            <!-- Карточки зон -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                @foreach($zones as $zone)
                    @php
                        $fillPercent = $zone->capacity > 0 ? min($zone->volume / $zone->capacity * 100, 100) : 0;
                        $loadStats = $zone->getLoadStats();
                        $isOverloaded = $loadStats['is_overloaded'];
                        $statusColors = ['available' => 'emerald', 'busy' => 'amber', 'overloaded' => 'red', 'full' => 'slate'];
                        $statusLabels = ['available' => 'Доступна', 'busy' => 'Занята', 'overloaded' => 'ПЕРЕГРУЖЕНА', 'full' => 'Заполнена'];
                        $statusColor = $statusColors[$loadStats['status']] ?? 'slate';
                        $statusLabel = $statusLabels[$loadStats['status']] ?? $loadStats['status'];
                    @endphp
                    <div class="bg-white rounded-xl border shadow-sm p-4 relative {{ !$zone->delivery ? 'opacity-60' : '' }} {{ $isOverloaded ? 'border-red-500' : '' }}">
    
                        <!-- Индикатор загрузки ТОЛЬКО для этой зоны -->
                        <div wire:loading wire:target="toggleZone({{ $zone->id }}, true) wire:target="toggleZone({{ $zone->id }}, false)" class="absolute inset-0 bg-white/70 rounded-xl flex items-center justify-center z-10">
                            <i class="fas fa-spinner fa-spin text-emerald-600 text-2xl"></i>
                        </div>

                        <div class="flex justify-between items-center mb-3">
                        <h5 class="font-bold text-gray-800">{{ $zone->dump?->name_dump }} - {{ $zone->name_zone }}</h5>
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="checkbox" 
                                {{ $zone->delivery ? 'checked' : '' }} 
                                wire:change="toggleZone({{ $zone->id }}, {{ $zone->delivery ? 'false' : 'true' }})"
                                wire:loading.attr="disabled"
                                wire:target="toggleZone({{ $zone->id }}, true) wire:target="toggleZone({{ $zone->id }}, false)" 
                                class="rounded text-emerald-600 focus:ring-emerald-500">
                            <span class="text-xs">{{ $zone->delivery ? 'Открыта' : 'Закрыта' }}</span>
                        </label>
                    </div>

                        <div class="flex justify-between items-center mb-2">
                            <span class="px-2 py-0.5 text-xs font-medium rounded-md bg-{{ $statusColor }}-100 text-{{ $statusColor }}-700">{{ $statusLabel }}</span>
                            @if($loadStats['total_trucks'] > 0)
                                <span class="px-2 py-0.5 text-xs font-medium rounded-md bg-blue-100 text-blue-700"><i class="fas fa-truck mr-1"></i>{{ $loadStats['total_trucks'] }} ТС</span>
                            @endif
                        </div>

                        @if($loadStats['total_trucks'] > 0)
                            <div class="grid grid-cols-3 gap-2 mb-2 text-center text-xs">
                                <div class="bg-slate-50 rounded p-1">
                                    <div class="font-bold text-cyan-600">{{ $loadStats['transporting_count'] }}</div>
                                    <div class="text-gray-500">В пути</div>
                                </div>
                                <div class="bg-slate-50 rounded p-1">
                                    <div class="font-bold text-amber-600">{{ $loadStats['unloading_count'] }}</div>
                                    <div class="text-gray-500">Разгрузка</div>
                                </div>
                                <div class="{{ $loadStats['waiting_count'] > 0 ? 'bg-red-100' : 'bg-slate-50' }} rounded p-1">
                                    <div class="font-bold {{ $loadStats['waiting_count'] > 0 ? 'text-red-600' : 'text-gray-500' }}">{{ $loadStats['waiting_count'] }}</div>
                                    <div class="text-gray-500">Ожидание</div>
                                </div>
                            </div>
                        @endif

                        <small class="text-gray-500 block mb-2">
                            <i class="fas fa-gem mr-1"></i>
                            {{ $zone->rocks->pluck('name_rock')->join(', ') ?: 'Не указаны' }}
                        </small>

                        <div class="mt-2">
                            <div class="flex justify-between text-xs mb-1">
                                <span class="text-gray-500">Заполнение</span>
                                <span class="font-medium">{{ number_format($zone->volume, 0) }} / {{ number_format($zone->capacity, 0) }}</span>
                            </div>
                            <div class="w-full bg-gray-200 rounded-full h-2.5">
                                <div 
                                    x-data="{ fillPercent: 75 }" 
                                    class="h-2.5 rounded-full"
                                    :class="{
                                        'bg-red-500': fillPercent < 40,
                                        'bg-amber-500': fillPercent >= 40 && fillPercent < 80,
                                        'bg-emerald-500': fillPercent >= 80
                                    }"
                                    :style="`width: ${fillPercent}%`"
                                    >
                                </div>

                            </div>
                        </div>

                        @if($isOverloaded && $loadStats['waiting_count'] > 0)
                            <div class="mt-4">
                                <button wire:click="redirectFromZone({{ $zone->id }})" wire:loading.attr="disabled" class="w-full px-4 py-2 bg-amber-500 text-white rounded-md text-xs font-semibold uppercase hover:bg-amber-600">
                                    <span wire:loading.remove><i class="fas fa-route mr-1"></i> Перенаправить ТС</span>
                                    <span wire:loading><i class="fas fa-spinner fa-spin mr-1"></i> Перенаправление...</span>
                                </button>
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>
