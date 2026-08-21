<div class="min-h-screen flex flex-col bg-slate-50" x-data="{ tab: 'zones' }">
    <!-- Dark Header -->
    <header class="bg-slate-900 text-white shadow-lg mb-4 rounded-xl">
        <div class="px-4 py-3 flex items-center justify-between">
            <h1 class="text-lg font-bold uppercase tracking-wider">Панель Мастера</h1> 
            
            <div class="flex items-center gap-4">
                <!-- Информация о пользователе и смене -->
                <div class="text-right text-sm hidden sm:block">
                    <p class="text-gray-400">{{ Auth::user()->name }}</p>
                    @if(isset($shift) && is_array($shift))
                        <p class="font-bold text-white shadow-lg">Смена {{ $shift['shift_id'] }} ({{ $shift['shift_type'] === 'day' ? 'День' : 'Ночь' }})</p>
                    @else
                        <p class="font-bold text-red-400">Смена не определена</p>
                    @endif
                </div>

                <!-- Кнопка выхода -->
                <form action="{{ route('logout') }}" method="POST">
                    @csrf
                    <button type="submit" class="bg-red-500 hover:bg-red-600 text-white px-4 py-2 rounded-md text-xs font-semibold uppercase tracking-wider transition">
                        Выйти
                    </button>
                </form>
            </div>
        </div>
    </header>
    <!-- Navigation Tabs -->
    <div class="bg-white border-b">
        <div class="mx-auto  p-1">
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
            <button @click="tab='miners'" 
            :class="tab === 'miners' ? 'bg-emerald-600 text-white' : 'text-gray-600 hover:bg-gray-100'" 
            class="px-4 py-2 rounded-md font-semibold uppercase">
                Забои
            </button>
            <button @click="tab='rocks'" 
                :class="tab === 'rocks' ? 'bg-emerald-600 text-white' : 'text-gray-600 hover:bg-gray-100'" 
                class="px-4 py-2 rounded-md font-semibold uppercase">
                Породы
            </button>
            <button @click="tab='dumps'" 
                :class="tab === 'dumps' ? 'bg-emerald-600 text-white' : 'text-gray-600 hover:bg-gray-100'" 
                class="px-4 py-2 rounded-md font-semibold uppercase">
                Перегрузки
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
                                <tr class="border-b hover:bg-slate-50">
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
                    <div class="p-4 border-b bg-slate-50">
                        <h3 class="font-bold text-gray-800 uppercase text-sm">Перегрузка: {{ $dump->name_dump }}</h3>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead class="bg-slate-50 border-b">
                                <tr>
                                    <th class="text-left p-3 font-semibold text-gray-600 w-1/4">Зона</th>
                                    <th class="text-left p-3 font-semibold text-gray-600">Порода</th>
                                    <th class="text-left p-3 font-semibold text-gray-600">Текущий объем</th>
                                    <th class="text-left p-3 font-semibold text-gray-600">Вместимость</th>
                                    <th class="text-left p-3 font-semibold text-gray-600">Заполнение</th>
                                    <th class="text-center p-3 font-semibold text-gray-600">Принимает</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($dump->zones as $zone)
                                    @php 
                                        $fillPercent = $zone->capacity > 0 ? min($zone->volume / $zone->capacity * 100, 100) : 0; 
                                        $currentRockId = $zone->rocks->first()?->id;
                                    @endphp
                                    <tr class="border-b {{ !$zone->delivery ? 'opacity-50 bg-slate-50' : '' }}">
                                        <td class="p-3 font-medium text-gray-800">{{ $zone->name_zone }}</td>
                                        <td class="p-3">
                                            <select wire:change="updateZoneField({{ $zone->id }}, 'rock_id', $event.target.value)" class="border-gray-300 rounded-md text-sm py-1 w-full">
                                                <option value="">Не указана</option>
                                                @foreach($rocks as $rock)
                                                    <option value="{{ $rock->id }}" @if($currentRockId == $rock->id) selected @endif>{{ $rock->name_rock }}</option>
                                                @endforeach
                                            </select>
                                        </td>
                                        <td class="p-3">
                                            <div class="flex items-center gap-2">
                                                <!-- Вводим количество вертушек, отправляем в базу кубометры (value * 380) -->
                                                <input type="number" 
                                                       wire:change="updateZoneField({{ $zone->id }}, 'volume', $event.target.value * 380)" 
                                                       value="{{ round($zone->volume / 380) }}" 
                                                       class="border-gray-300 rounded-md text-sm py-1 w-20 text-center" 
                                                       step="1" min="0">
                                                <span class="text-xs text-gray-500 whitespace-nowrap">({{ number_format($zone->volume, 0, '.', ' ') }} м³)</span>
                                            </div>
                                        </td>
                                        <td class="p-3">
                                            <div class="flex items-center gap-2">
                                                <!-- Вводим количество вертушек, отправляем в базу кубометры (value * 380) -->
                                                <input type="number" 
                                                       wire:change="updateZoneField({{ $zone->id }}, 'capacity', $event.target.value * 380)" 
                                                       value="{{ round($zone->capacity / 380) }}" 
                                                       class="border-gray-300 rounded-md text-sm py-1 w-20 text-center" 
                                                       step="1" min="0">
                                                <span class="text-xs text-gray-500 whitespace-nowrap">({{ number_format($zone->capacity, 0, '.', ' ') }} м³)</span>
                                            </div>
                                        </td>
                                        <td class="p-3">
                                            <div class="flex items-center gap-2">
                                                <div class="w-24 bg-gray-200 rounded-full h-2.5">
                                                    <div class="h-2.5 rounded-full {{ $fillPercent > 90 ? 'bg-red-500' : ($fillPercent > 70 ? 'bg-amber-500' : 'bg-emerald-500') }}" style="width: {{ $fillPercent }}%"></div>
                                                </div>
                                                <span class="text-xs text-gray-500">{{ round($fillPercent) }}%</span>
                                            </div>
                                        </td>
                                        <td class="p-3 text-center">
                                            <input type="checkbox" wire:change="updateZoneField({{ $zone->id }}, 'delivery', $event.target.checked)" {{ $zone->delivery ? 'checked' : '' }} class="rounded text-emerald-600 h-5 w-5 cursor-pointer">
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endforeach
        </div>
        <!-- ВКЛАДКА: Оборудование -->
        <div x-show="tab === 'equipment'" x-cloak class="mt-4 space-y-6">
            
            <!-- Самосвалы -->
            <div class="bg-white rounded-xl border shadow-sm overflow-hidden" x-data="{ showAddTruck: false }">
                <div class="p-4 border-b flex justify-between items-center">
                    <span class="font-bold text-gray-800 uppercase text-sm">Самосвалы</span>
                    <button @click="showAddTruck = !showAddTruck" class="text-emerald-600 hover:text-emerald-800 text-xs font-semibold uppercase">
                        + Добавить
                    </button>
                </div>

                <!-- Форма добавления самосвала -->
                <div x-show="showAddTruck" x-cloak class="p-4 bg-slate-50 border-b">
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-3 items-end">
                        
                        <!-- Номер -->
                        <div>
                            <label class="block text-xs text-gray-500 uppercase mb-1">Номер</label>
                            <input type="text" wire:model="newTruckNumber" placeholder="Напр. 120" class="w-full border-gray-300 rounded-md shadow-sm py-2 text-sm @error('newTruckNumber') border-red-500 bg-red-50 @enderror">
                            @error('newTruckNumber')
                                <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- Модель -->
                        <div>
                            <label class="block text-xs text-gray-500 uppercase mb-1">Модель</label>
                            <select wire:model="newTruckModelId" class="w-full border-gray-300 rounded-md shadow-sm py-2 text-sm @error('newTruckModelId') border-red-500 bg-red-50 @enderror">
                                <option value="">Выберите модель</option>
                                @foreach(\App\Models\TruckModel::all() as $model)
                                    <option value="{{ $model->id }}">{{ $model->name }}</option>
                                @endforeach
                            </select>
                            @error('newTruckModelId')
                                <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- Топливо -->
                        <div>
                            <label class="block text-xs text-gray-500 uppercase mb-1">Топливо (л)</label>
                            <input type="number" wire:model="newTruckFuel" placeholder="Напр. 150" class="w-full border-gray-300 rounded-md shadow-sm py-2 text-sm @error('newTruckFuel') border-red-500 bg-red-50 @enderror" min="0">
                            @error('newTruckFuel')
                                <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- Кнопка -->
                        <button wire:click="addTruck" class="px-4 py-2 bg-emerald-600 text-white rounded-md text-xs font-semibold uppercase hover:bg-emerald-700">
                            Создать
                        </button>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-slate-50 border-b">
                            <tr>
                                <th class="text-left p-3 font-semibold text-gray-600">Номер</th>
                                <th class="text-left p-3 font-semibold text-gray-600">Статус</th>
                                <th class="text-left p-3 font-semibold text-gray-600">В статусе</th>
                                <th class="text-left p-3 font-semibold text-gray-600">Топливо</th>
                                <th class="text-left p-3 font-semibold text-gray-600">Мото-часы</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($trucks as $truck)
                                <tr class="border-b hover:bg-slate-50">
                                    <td class="p-3 font-bold text-gray-800">
                                        <div class="flex items-center gap-2">
                                            <span>{{ $truck->number }}</span>
                                            <button wire:click="deleteTruck({{ $truck->id }})" wire:confirm="Удалить самосвал?" class="text-red-400 hover:text-red-600 text-xs">
                                                <i class="fas fa-trash-alt"></i>
                                            </button>
                                        </div>
                                    </td>
                                    <td class="p-3">
                                        <span class="px-2 py-0.5 text-xs font-medium rounded-md 
                                            {{ $truck->status === 'breakdown' ? 'bg-red-100 text-red-700' : 
                                            ($truck->status === 'free' ? 'bg-slate-200 text-slate-700' : 'bg-emerald-100 text-emerald-700') }}">
                                            {{ \App\Domain\TruckStatus::label($truck->status) }}
                                        </span>
                                    </td>
                                    <td class="p-3 text-gray-500 text-xs">{{ $truck->updated_at?->diffForHumans() }}</td>
                                    <td class="p-3 text-gray-600">{{ $truck->fuel_level ?? '—' }} л</td>
                                    <td class="p-3 text-gray-600">{{ round(($truck->moto_minutes ?? 0) / 60, 1) }} ч</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Экскаваторы (Забои) -->
            <div class="bg-white rounded-xl border shadow-sm overflow-hidden">
                <div class="p-4 border-b font-bold text-gray-800 uppercase text-sm">Экскаваторы (Забои)</div>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-slate-50 border-b">
                            <tr>
                                <th class="text-left p-3 font-semibold text-gray-600">Забой</th>
                                <th class="text-left p-3 font-semibold text-gray-600">Статус</th>
                                <th class="text-left p-3 font-semibold text-gray-600">Порода</th>
                                <th class="text-left p-3 font-semibold text-gray-600">Самосвалов у забоя</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($miners as $miner)
                                <tr class="border-b hover:bg-slate-50">
                                    <td class="p-3 font-bold text-gray-800">{{ $miner->name_miner }}</td>
                                    <td class="p-3">
                                        <span class="px-2 py-0.5 text-xs font-medium rounded-md 
                                            {{ $miner->status === 'breakdown' ? 'bg-red-100 text-red-700' : 
                                               ($miner->status === 'active' ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700') }}">
                                            {{ \App\Domain\MinerStatus::label($miner->status) }}
                                        </span>
                                    </td>
                                    <td class="p-3">
                                        @if($miner->currentRock)
                                            <span class="px-2 py-0.5 text-xs rounded bg-cyan-100 text-cyan-700">{{ $miner->currentRock->name_rock }}</span>
                                        @else
                                            <span class="text-gray-400">—</span>
                                        @endif
                                    </td>
                                    <td class="p-3">
                                        <span class="px-2 py-0.5 text-xs font-bold rounded-md bg-blue-100 text-blue-700">{{ $miner->active_trucks_count }}</span>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- ВКЛАДКА: Обслуживание -->
        <div x-show="tab === 'maintenance'" x-cloak class="mt-4 space-y-6">
            
            <!-- Что проводится сейчас -->
            <div class="bg-white rounded-xl border shadow-sm overflow-hidden">
                <div class="p-4 border-b font-bold text-gray-800 uppercase text-sm flex items-center gap-2">
                    <span class="w-3 h-3 bg-emerald-500 rounded-full animate-pulse"></span>
                    Что проводится сейчас (Занятые посты)
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-slate-50 border-b">
                            <tr>
                                <th class="text-left p-3 font-semibold text-gray-600">Грузовик</th>
                                <th class="text-left p-3 font-semibold text-gray-600">Тип обслуживания</th>
                                <th class="text-left p-3 font-semibold text-gray-600">Пост</th>
                                <th class="text-left p-3 font-semibold text-gray-600">Начало</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($activeServiceTasks as $task)
                                <tr class="border-b hover:bg-slate-50">
                                    <td class="p-3 font-bold text-gray-800">{{ $task->truck?->number ?? '—' }}</td>
                                    <td class="p-3 text-gray-600">{{ $task->getTypeLabel() }}</td>
                                    <td class="p-3">
                                        <span class="px-2 py-0.5 text-xs rounded bg-amber-100 text-amber-700">{{ $task->servicePost?->name ?? '—' }}</span>
                                    </td>
                                    <td class="p-3 text-gray-500">{{ $task->started_at?->format('H:i') }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="p-4 text-center text-gray-500">Нет активных обслуживаний</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Запланировано (Очередь) -->
            <div class="bg-white rounded-xl border shadow-sm overflow-hidden">
                <div class="p-4 border-b font-bold text-gray-800 uppercase text-sm">Запланировано (Очередь)</div>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-slate-50 border-b">
                            <tr>
                                <th class="text-left p-3 font-semibold text-gray-600">Грузовик</th>
                                <th class="text-left p-3 font-semibold text-gray-600">Тип обслуживания</th>
                                <th class="text-left p-3 font-semibold text-gray-600">Позиция в очереди</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($pendingServiceTasks as $task)
                                <tr class="border-b hover:bg-slate-50">
                                    <td class="p-3 font-bold text-gray-800">{{ $task->truck?->number ?? '—' }}</td>
                                    <td class="p-3 text-gray-600">{{ $task->getTypeLabel() }}</td>
                                    <td class="p-3">
                                        <span class="px-2 py-0.5 text-xs rounded bg-slate-200 text-slate-700">{{ $task->queue_position }}</span>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="3" class="p-4 text-center text-gray-500">Очередь пуста</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- ВКЛАДКА: Заявки -->
        <div x-show="tab === 'requests'" x-cloak class="mt-4 space-y-6">
            <!-- Панель фильтрации -->
            <div class="bg-white rounded-xl border shadow-sm p-4 mb-6">
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4">
                    <div>
                        <label class="block text-xs uppercase font-semibold text-gray-500 mb-1">Категория</label>
                        <select wire:model.live="categoryId" class="w-full border-gray-300 rounded-md shadow-sm py-2 text-sm">
                            <option value="">Все</option>
                            @foreach($categories as $category)
                                <option value="{{ $category->id }}">{{ $category->title }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs uppercase font-semibold text-gray-500 mb-1">Автор</label>
                        <select wire:model.live="userId" class="w-full border-gray-300 rounded-md shadow-sm py-2 text-sm">
                            <option value="">Все</option>
                            @foreach($users as $user)
                                <option value="{{ $user->id }}">{{ $user->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs uppercase font-semibold text-gray-500 mb-1">Оборудование</label>
                        <select wire:model.live="mashineId" class="w-full border-gray-300 rounded-md shadow-sm py-2 text-sm">
                            <option value="">Все</option>
                            @foreach($allMashines as $mashine)
                                <option value="{{ $mashine->id }}">ЭКГ №{{ $mashine->number }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs uppercase font-semibold text-gray-500 mb-1">Дата</label>
                        <input type="date" wire:model.live="createdAt" class="w-full border-gray-300 rounded-md shadow-sm py-2 text-sm">
                    </div>
                    <div>
                        <label class="block text-xs uppercase font-semibold text-gray-500 mb-1">Фрагмент заявки</label>
                        <input type="text" wire:model.live.debounce.300ms="contentSearch" placeholder="Поиск по тексту..." class="w-full border-gray-300 rounded-md shadow-sm py-2 text-sm">
                    </div>
                </div>
                <div class="flex justify-between items-center mt-4 pt-4 border-t">
                    <h3 class="font-bold text-gray-800 uppercase text-sm">Всего заявок: <span class="text-emerald-600">{{ $ordersCount }}</span></h3>
                    <a href="{{ route('order.create') }}" class="px-4 py-2 bg-emerald-600 text-white rounded-md font-semibold uppercase text-xs hover:bg-emerald-700">
                        <i class="fas fa-plus mr-1"></i> Создать заявку
                    </a>
                </div>
            </div>

            <!-- Карточки техники с заявками -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                @foreach($mashines as $mashine)
                    @if(count($mashine->sets) != 0 || count($mashine->orders) != 0)
                        <div class="bg-white rounded-xl border shadow-sm overflow-hidden flex flex-col sm:flex-row">
                            
                            <!-- Левая часть: Заявки -->
                            <div class="flex-1 p-4 border-b sm:border-b-0 sm:border-r">
                                <h5 class="font-bold text-gray-800 mb-3">ЭКГ №{{ $mashine->number }}</h5>
                                <div class="space-y-3">
                                    @foreach($mashine->orders as $order)
                                        <div class="bg-slate-50 p-3 rounded-md border border-slate-200">
                                            <small class="text-gray-500 block mb-1">
                                                {{ $order->created_at->translatedFormat('d F Y') }} ({{ $order->created_at->diffForHumans() }})
                                            </small>
                                            <p class="text-sm text-gray-700">{{ $order->content }}</p>
                                            <a href="{{ route('order.show', $order->id) }}" class="text-xs text-emerald-600 hover:text-emerald-800 mt-2 inline-block font-semibold">
                                                Смотреть подробнее <i class="fas fa-arrow-right ml-1"></i>
                                            </a>
                                        </div>
                                    @endforeach
                                    @if(count($mashine->orders) == 0)
                                        <p class="text-gray-400 text-sm">Нет активных заявок</p>
                                    @endif
                                </div>
                            </div>

                            <!-- Правая часть: Комплектация -->
                            @if(count($mashine->sets) != 0)
                                <div class="sm:w-1/3 p-4 bg-slate-50">
                                    <h6 class="font-bold text-gray-600 uppercase text-xs mb-2">Необходимо укомплектовать:</h6>
                                    <ul class="space-y-1">
                                        @foreach($mashine->sets as $set)
                                            <li class="text-sm text-gray-700 flex items-center gap-2">
                                                <i class="fas fa-circle text-[6px] text-emerald-500"></i> {{ $set->name }}
                                            </li>
                                        @endforeach
                                    </ul>
                                </div>
                            @endif
                            
                        </div>
                    @endif
                @endforeach
            </div>
        </div>
        <!-- ВКЛАДКА: Забои -->
        <div x-show="tab === 'miners'" x-cloak class="mt-4 space-y-4">
            <div class="bg-white p-4 rounded-xl border shadow-sm flex gap-2">
                <input type="text" wire:model="newMinerName" placeholder="Название нового забоя" class="flex-1 border-gray-300 rounded-md shadow-sm py-2">
                @error('newMinerName')
                    <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                @enderror
                <button wire:click="addMiner" class="px-4 py-2 bg-emerald-600 text-white rounded-md font-semibold uppercase text-sm hover:bg-emerald-700">Добавить</button>
            </div>
            <div class="bg-white rounded-xl border shadow-sm overflow-hidden">
                <div class="p-4 border-b font-bold text-gray-800 uppercase text-sm">Список забоев</div>
                <ul class="divide-y divide-gray-100">
                    @foreach($miners as $miner)
                        <li class="p-3 hover:bg-slate-50">
                            <div class="flex justify-between items-center">
                                <span class="font-medium text-gray-800">{{ $miner->name_miner }}</span>
                                <div class="flex gap-3 items-center">
                                    <button wire:click="editMinerDistances({{ $miner->id }})" class="text-blue-500 hover:text-blue-700 text-xs font-semibold uppercase">
                                        Расстояния <i class="fas fa-route ml-1"></i>
                                    </button>
                                    <button wire:click="deleteMiner({{ $miner->id }})" wire:confirm="Удалить забой?" class="text-red-400 hover:text-red-600 text-sm"><i class="fas fa-trash"></i></button>
                                </div>
                            </div>
                            
                            <!-- Раскрывающаяся панель расстояний -->
                            @if($editingMinerId === $miner->id)
                                <div class="mt-4 pt-4 border-t bg-slate-50 p-3 rounded-lg">
                                    <h6 class="text-xs font-bold text-gray-500 uppercase mb-3">Расстояние до мест разгрузки (км)</h6>
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                                        @foreach($dumps as $dump)
                                            @php
                                                $dist = \App\Models\MinerDumpDistance::where('miner_id', $miner->id)->where('dump_id', $dump->id)->first();
                                                $distVal = $dist ? $dist->distance_km : '';
                                            @endphp
                                            <div class="flex items-center gap-2">
                                                <span class="flex-1 text-sm text-gray-700">{{ $dump->name_dump }}</span>
                                                <input type="number" step="0.1" wire:change="saveDistance({{ $miner->id }}, {{ $dump->id }}, $event.target.value)" value="{{ $distVal }}" class="w-24 border-gray-300 rounded-md py-1 text-sm" placeholder="км">
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            @endif
                        </li>
                    @endforeach
                </ul>
            </div>
        </div>

        <!-- ВКЛАДКА: Породы -->
        <div x-show="tab === 'rocks'" x-cloak class="mt-4 space-y-4">
            <div class="bg-white p-4 rounded-xl border shadow-sm flex gap-2">
                <input type="text" wire:model="newRockName" placeholder="Название новой породы" class="flex-1 border-gray-300 rounded-md shadow-sm py-2">
                @error('newRockName')
                    <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                @enderror
                <button wire:click="addRock" class="px-4 py-2 bg-emerald-600 text-white rounded-md font-semibold uppercase text-sm hover:bg-emerald-700">Добавить</button>
            </div>
            <div class="bg-white rounded-xl border shadow-sm overflow-hidden">
                <div class="p-4 border-b font-bold text-gray-800 uppercase text-sm">Список пород</div>
                <ul class="divide-y divide-gray-100">
                    @foreach($rocks as $rock)
                        <li class="p-3 flex justify-between items-center hover:bg-slate-50">
                            <span class="font-medium text-gray-800">{{ $rock->name_rock }}</span>
                            <button wire:click="deleteRock({{ $rock->id }})" wire:confirm="Удалить породу?" class="text-red-400 hover:text-red-600 text-sm"><i class="fas fa-trash"></i></button>
                        </li>
                    @endforeach
                </ul>
            </div>
        </div>

        <!-- ВКЛАДКА: Перегрузки -->
        <div x-show="tab === 'dumps'" x-cloak class="mt-4 space-y-4">
            <div class="bg-white p-4 rounded-xl border shadow-sm flex gap-2">
                <input type="text" wire:model="newDumpName" placeholder="Название новой перегрузки" class="flex-1 border-gray-300 rounded-md shadow-sm py-2">
                @error('newDumpName')
                    <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                @enderror
                <button wire:click="addDump" class="px-4 py-2 bg-emerald-600 text-white rounded-md font-semibold uppercase text-sm hover:bg-emerald-700">Добавить</button>
            </div>

            @foreach($dumps as $dump)
                <div class="bg-white rounded-xl border shadow-sm overflow-hidden">
                    <div class="p-4 border-b bg-slate-50 flex justify-between items-center">
                        <h3 class="font-bold text-gray-800 uppercase text-sm">{{ $dump->name_dump }}</h3>
                        <div class="flex gap-3 items-center">
                            <button wire:click="toggleAddZone({{ $dump->id }})" class="text-blue-500 hover:text-blue-700 text-xs font-semibold uppercase">
                                + Добавить зону
                            </button>
                            <button wire:click="deleteDump({{ $dump->id }})" wire:confirm="Удалить перегрузку?" class="text-red-400 hover:text-red-600 text-sm"><i class="fas fa-trash"></i></button>
                        </div>
                    </div>

                    <!-- Форма добавления зоны -->
                    @if($addZoneDumpId === $dump->id)
                        <div class="p-4 bg-blue-50 border-b grid grid-cols-1 md:grid-cols-5 gap-3 items-end">
                            <div class="md:col-span-2">
                                <label class="text-xs text-gray-500 uppercase">Название зоны</label>
                                <input type="text" wire:model="newZoneName" placeholder="Зона 1" class="w-full border-gray-300 rounded-md text-sm py-1 @error('newZoneName') border-red-500 bg-red-50 @enderror">
                                @error('newZoneName') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label class="text-xs text-gray-500 uppercase">Порода</label>
                                <select wire:model="newZoneRockId" class="w-full border-gray-300 rounded-md text-sm py-1 @error('newZoneRockId') border-red-500 bg-red-50 @enderror">
                                    <option value="">Выберите</option>
                                    @foreach($rocks as $rock)<option value="{{ $rock->id }}">{{ $rock->name_rock }}</option>@endforeach
                                </select>
                                @error('newZoneRockId') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label class="text-xs text-gray-500 uppercase">Вместимость (м³)</label>
                                <input type="number" wire:model="newZoneCapacity" class="w-full border-gray-300 rounded-md text-sm py-1 @error('newZoneCapacity') border-red-500 bg-red-50 @enderror">
                                @error('newZoneCapacity') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                            </div>
                            <div class="flex gap-2">
                                <div class="flex-1">
                                    <label class="text-xs text-gray-500 uppercase">Текущ. объем (м³)</label>
                                    <input type="number" wire:model="newZoneVolume" class="w-full border-gray-300 rounded-md text-sm py-1 @error('newZoneVolume') border-red-500 bg-red-50 @enderror">
                                    @error('newZoneVolume') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                                </div>
                                <button wire:click="addZone({{ $dump->id }})" class="px-3 py-2 bg-emerald-600 text-white rounded-md text-xs uppercase font-bold whitespace-nowrap">Создать</button>
                            </div>
                        </div>
                    @endif

                    <!-- Список зон этой перегрузки с инлайн-редактированием -->
                    @if($dump->zones->isNotEmpty())
                    <table class="w-full text-sm">
                        <thead class="bg-slate-50 border-b">
                            <tr>
                                <th class="text-left p-2 font-semibold text-gray-600 w-1/4">Зона</th>
                                <th class="text-left p-2 font-semibold text-gray-600">Порода</th>
                                <th class="text-left p-2 font-semibold text-gray-600">Текущ. (верт.)</th>
                                <th class="text-left p-2 font-semibold text-gray-600">Вмест. (верт.)</th>
                                <th class="text-center p-2 font-semibold text-gray-600">Принимает</th>
                                <th class="text-center p-2 font-semibold text-gray-600">Удалить</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($dump->zones as $zone)
                                <tr class="border-b {{ !$zone->delivery ? 'opacity-50 bg-slate-50' : '' }}">
                                    <td class="p-2">
                                        <input type="text" wire:change="updateZoneField({{ $zone->id }}, 'name_zone', $event.target.value)" value="{{ $zone->name_zone }}" class="border-gray-300 rounded-md text-sm py-1 w-full">
                                    </td>
                                    <td class="p-2">
                                        <select wire:change="updateZoneField({{ $zone->id }}, 'rock_id', $event.target.value)" class="border-gray-300 rounded-md text-sm py-1 w-full">
                                            <option value="">Не указана</option>
                                            @foreach($rocks as $rock)
                                                <option value="{{ $rock->id }}" @if($zone->rocks->first()?->id == $rock->id) selected @endif>{{ $rock->name_rock }}</option>
                                            @endforeach
                                        </select>
                                    </td>
                                    <td class="p-2">
                                        <div class="flex items-center gap-1">
                                            <input type="number" wire:change="updateZoneField({{ $zone->id }}, 'volume', $event.target.value * 380)" value="{{ round($zone->volume / 380) }}" class="border-gray-300 rounded-md text-sm py-1 w-16 text-center" step="1">
                                            <span class="text-xs text-gray-400">({{ number_format($zone->volume, 0) }})</span>
                                        </div>
                                    </td>
                                    <td class="p-2">
                                        <div class="flex items-center gap-1">
                                            <input type="number" wire:change="updateZoneField({{ $zone->id }}, 'capacity', $event.target.value * 380)" value="{{ round($zone->capacity / 380) }}" class="border-gray-300 rounded-md text-sm py-1 w-16 text-center" step="1">
                                            <span class="text-xs text-gray-400">({{ number_format($zone->capacity, 0) }})</span>
                                        </div>
                                    </td>
                                    <td class="p-2 text-center">
                                        <input type="checkbox" wire:change="updateZoneField({{ $zone->id }}, 'delivery', $event.target.checked)" {{ $zone->delivery ? 'checked' : '' }} class="rounded text-emerald-600 h-5 w-5 cursor-pointer">
                                    </td>
                                    <td class="p-2 text-center">
                                        <button wire:click="deleteZone({{ $zone->id }})" wire:confirm="Удалить зону? Привязанные к ней маршруты станут неактивными." class="text-red-400 hover:text-red-600">
                                            <i class="fas fa-trash-alt"></i>
                                        </button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                    @else
                        <div class="p-4 text-center text-gray-500 text-sm">Нет зон. Нажмите "+ Добавить зону".</div>
                    @endif
                </div>
            @endforeach
        </div>
<!-- Модальное окно интерактивной карты -->
<div x-data="{ showMap: false, initMap() { 
        // Инициализируем карту только один раз
        if (!window.leafletMapInstance) {
            // Добавлен параметр { attributionControl: false } для отключения логотипов
            window.leafletMapInstance = L.map('leafletMap', { attributionControl: false }).setView([51.280247, 37.633032], 13); // Координаты вашего карьера
            
            // Спутниковый слой Esri
            L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {
                attribution: '© Esri',
                maxZoom: 19
            }).addTo(window.leafletMapInstance);

            // Обработчик клика
            window.leafletMapInstance.on('click', function(e) {
                L.popup()
                    .setLatLng(e.latlng)
                    .setContent(`<b>Координаты:</b><br>Широта: ${e.latlng.lat.toFixed(6)}<br>Долгота: ${e.latlng.lng.toFixed(6)}`)
                    .openOn(window.leafletMapInstance);
            });
        }
        // Лекарство от скрытого окна: заставляем карту перерисоваться через 100мс после открытия
        setTimeout(() => window.leafletMapInstance.invalidateSize(), 100);
    } 
    }">
    <!-- Кнопка открытия карты -->
    <button @click="showMap = true; initMap()" class="fixed bottom-4 right-4 bg-blue-600 text-white px-4 py-2 rounded-md text-xs font-semibold uppercase shadow-lg z-30">
        🗺️ Открыть карту
    </button>

    <!-- Само окно карты -->
    <div x-show="showMap" x-cloak class="fixed inset-0 z-[9999] bg-black/70 flex items-center justify-center p-4" style="display: none;">
        <div class="bg-white rounded-xl shadow-2xl w-full max-w-5xl h-[85vh] flex flex-col">
            <div class="p-4 border-b flex justify-between items-center">
                <h5 class="font-bold text-gray-800 uppercase">Интерактивная карта карьера</h5>
                <button @click="showMap = false" class="text-gray-500 hover:text-gray-700 text-2xl">&times;</button>
            </div>
            
            <!-- Контейнер карты. Добавлен h-full -->
            <div id="leafletMap" class="flex-1 w-full h-full bg-slate-200 rounded-b-xl overflow-hidden"></div>
            
            <div class="p-3 bg-slate-50 border-t text-xs text-gray-500">
                <i class="fas fa-info-circle"></i> Кликните по карте, чтобы поставить точку.
            </div>
        </div>
    </div>
</div>

<!-- Подключаем стили и скрипты Leaflet (если еще не подключены) -->
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
</div>