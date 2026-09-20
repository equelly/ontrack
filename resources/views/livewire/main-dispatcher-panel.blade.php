<div class="dispatcher-panel-wrapper" x-data="{ tab: @entangle('activeTab') }">
    <!-- Toast контейнер для уведомлений -->
    <div id="global-toast-container" class="position-fixed top-0 end-0 p-3" style="z-index: 9999;"></div>
    
    <!-- ТЕМНАЯ ШАПКА СО СТАТИСТИКОЙ -->
    <header class="bg-slate-900 text-white shadow-lg mb-2 rounded-xl">
        <div class="px-4 py-3 flex flex-wrap items-center gap-x-6 gap-y-3">
            <!-- Статистика -->
            <div class="flex flex-wrap items-center gap-x-6 gap-y-2 text-sm">
                <div class="flex items-center gap-2">
                    <i class="fas fa-check-circle text-emerald-400"></i>
                    <div><span class="text-gray-200 block text-[10px] uppercase">Доступны ТС</span><strong class="text-emerald-400">{{ $this->free_trucks_count }}</strong></div>
                </div>
                <div class="flex items-center gap-2">
                    <i class="fas fa-truck text-blue-400"></i>
                    <div><span class="text-gray-400 block text-[10px] uppercase">Авто работе</span><strong class="text-blue-400">{{ $this->working_trucks_count }}</strong></div>
                </div>
                <div class="flex items-center gap-2">
                    <i class="fas fa-clock text-amber-400"></i>
                    <div><span class="text-gray-400 block text-[10px] uppercase">Задержки ТС</span><strong class="text-amber-400">{{ $trucks->whereIn('status', ['delayed', 'waiting_unloading'])->count() }}</strong></div>
                </div>
                <div class="flex items-center gap-2">
                    <i class="fas fa-wrench text-red-400"></i>
                    <div><span class="text-gray-400 block text-[10px] uppercase">Поломки ТС</span><strong class="text-red-400">{{ $this->breakdown_count }}</strong></div>
                </div>
                <div class="flex items-center gap-2">
                    <i class="fas fa-mountain text-red-400"></i>
                    <div><span class="text-gray-400 block text-[10px] uppercase">Поломки забоев</span><strong class="text-red-400">{{ $this->miner_breakdown_count }}</strong></div>
                </div>
                <div class="flex items-center gap-2">
                    <i class="fas fa-mountain text-emerald-400"></i>
                    <div><span class="text-gray-400 block text-[10px] uppercase">Забои в работе</span><strong class="text-emerald-400">{{ $this->active_miners_count }}</strong></div>
                </div>
                @php $overloadedZonesCount = $this->overloaded_zones_count; @endphp
                <div class="flex items-center gap-2">
                    <i class="fas fa-map-marker-alt text-{{ $overloadedZonesCount > 0 ? 'red' : 'gray' }}-400"></i>
                    <div><span class="text-gray-400 block text-[10px] uppercase">Перегруз зон</span><strong class="text-{{ $overloadedZonesCount > 0 ? 'red' : 'gray' }}-400">{{ $overloadedZonesCount }}</strong></div>
                </div>
                @php $distStats = $this->planned_distance_stats; @endphp
                <div class="flex items-center gap-2">
                    <i class="fas fa-route text-cyan-400"></i>
                    <div><span class="text-gray-400 block text-[10px] uppercase">Ср. расстояние</span><strong class="text-cyan-400">{{ $distStats['avg_distance'] }} км</strong></div>
                </div>
                @php $emptyRunStats = $this->empty_run_stats; @endphp
                <div class="flex items-center gap-2">
                    <i class="fas fa-arrow-left text-amber-400"></i>
                    <div><span class="text-gray-400 block text-[10px] uppercase">Холостой</span><strong class="text-amber-400">{{ $emptyRunStats['total_empty_run'] }} км</strong></div>
                </div>
                <div class="flex items-center gap-2">
                    <i class="fas fa-balance-scale text-emerald-400"></i>
                    <div><span class="text-gray-400 block text-[10px] uppercase">Эффективность</span><strong class="text-emerald-400">{{ $emptyRunStats['efficiency'] }}%</strong></div>
                </div>
                @php $queueStats = $this->queue_stats; @endphp
                <div class="flex items-center gap-2">
                    <i class="fas fa-tools text-emerald-400"></i>
                    <div class="text-[10px] text-gray-400 uppercase leading-tight">
                        Очереди обсл.<br>
                        <span class="text-gray-200 normal-case">ТО: {{ $queueStats['maintenance']['waiting'] }}/{{ $queueStats['maintenance']['in_progress'] }}, Запр: {{ $queueStats['fueling']['waiting'] }}/{{ $queueStats['fueling']['in_progress'] }}</span>
                    </div>
                </div>
            </div>

            <!-- ПРАВЫЙ БЛОК: Обновить, Пользователь, Выход -->
            <div class="ml-auto flex items-center gap-4">
                <button wire:click="runShiftPlanning" wire:loading.attr="disabled" class="px-4 py-2 bg-emerald-600 rounded-md font-semibold uppercase text-xs tracking-wider hover:bg-emerald-700 whitespace-nowrap">
                    <span wire:loading.remove><i class="fas fa-calendar-check mr-1"></i> Планировать смену</span>
                    <span wire:loading><i class="fas fa-spinner fa-spin mr-1"></i> Планирование...</span>
                </button>
                <!-- Кнопка обновления данных (только для диспетчера, у остальных можно убрать) -->
                <button wire:click="loadData" wire:loading.attr="disabled" class="text-gray-400 hover:text-white" title="Обновить данные">
                    <i class="fas fa-sync-alt" wire:loading.class="fa-spin"></i>
                </button>

                <!-- Информация о пользователе (скрывается на малых экранах) -->
                <div class="text-right text-sm hidden md:block">
                    <p class="text-gray-400">{{ Auth::user()->name }}</p>
                    @php $currentShift = app(\App\Services\ShiftService::class)->getCurrentShift(); @endphp
                    @if(is_array($currentShift))
                        <p class="font-bold text-white">Смена {{ $currentShift['shift_id'] }} ({{ $currentShift['shift_type'] === 'day' ? 'День' : 'Ночь' }})</p>
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

    <!-- Навигация (Tabs) -->
    <nav class="bg-white border shadow-sm sticky top-0 z-10 mb-4 rounded-xl">
        <div class="px-4 flex overflow-x-auto gap-1 py-2">
            <button @click="tab='trucksTab'" :class="tab === 'trucksTab' ? 'bg-emerald-600 text-white' : 'text-gray-600 hover:bg-gray-100'" class="px-4 py-2 rounded-lg text-sm font-medium whitespace-nowrap transition-all">Самосвалы</button>
            <button @click="tab='minersTab'" :class="tab === 'minersTab' ? 'bg-emerald-600 text-white' : 'text-gray-600 hover:bg-gray-100'" class="px-4 py-2 rounded-lg text-sm font-medium whitespace-nowrap transition-all">Забои</button>
            <button @click="tab='routesTab'" :class="tab === 'routesTab' ? 'bg-emerald-600 text-white' : 'text-gray-600 hover:bg-gray-100'" class="px-4 py-2 rounded-lg text-sm font-medium whitespace-nowrap transition-all">Маршруты</button>
            <button @click="tab='assignTab'" :class="tab === 'assignTab' ? 'bg-emerald-600 text-white' : 'text-gray-600 hover:bg-gray-100'" class="px-4 py-2 rounded-lg text-sm font-medium whitespace-nowrap transition-all">Назначить</button>
            <button @click="tab='zonesTab'" :class="tab === 'zonesTab' ? 'bg-emerald-600 text-white' : 'text-gray-600 hover:bg-gray-100'" class="px-4 py-2 rounded-lg text-sm font-medium whitespace-nowrap transition-all flex items-center">
                Зоны разгрузки
                @php $overloadedZonesCount = $this->overloaded_zones_count; @endphp
                @if($overloadedZonesCount > 0)<span class="ml-2 bg-red-500 text-white text-[10px] font-bold rounded-full min-w-[20px] h-5 flex items-center justify-center px-1">{{ $overloadedZonesCount }}</span>@endif
            </button>
            <button @click="tab='pausesTab'" :class="tab === 'pausesTab' ? 'bg-emerald-600 text-white' : 'text-gray-600 hover:bg-gray-100'" class="px-4 py-2 rounded-lg text-sm font-medium whitespace-nowrap transition-all flex items-center">
                Простои
                @php $waitingUnloadingCount = $trucks->where('status', 'waiting_unloading')->count(); $totalDelays = $this->pause_stats['active_count'] + $this->miner_delays['total_count'] + $waitingUnloadingCount; @endphp
                @if($totalDelays > 0)<span class="ml-2 bg-red-500 text-white text-[10px] font-bold rounded-full min-w-[20px] h-5 flex items-center justify-center px-1">{{ $totalDelays }}</span>@endif
            </button>
            <button @click="tab='analyticsTab'" :class="tab === 'analyticsTab' ? 'bg-emerald-600 text-white' : 'text-gray-600 hover:bg-gray-100'" class="px-4 py-2 rounded-lg text-sm font-medium whitespace-nowrap transition-all">Аналитика</button>
            <button @click="tab='settingsTab'" :class="tab === 'settingsTab' ? 'bg-emerald-600 text-white' : 'text-gray-600 hover:bg-gray-100'" class="px-4 py-2 rounded-lg text-sm font-medium whitespace-nowrap transition-all">Настройки</button>
            <a href="{{ route('order.index') }}" class="px-4 py-2 rounded-lg text-sm font-medium whitespace-nowrap transition-all text-gray-600 hover:bg-gray-100 flex items-center">Заявки</a>
        </div>
    </nav>
    <div class="tab-content">
        <!-- Самосвалы -->
        <div x-show="tab === 'trucksTab'" x-cloak class="mt-4" id="trucksTab">
            <div class="bg-white rounded-xl border shadow-sm overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-slate-50 border-b">
                            <tr>
                                <th class="text-left p-3 font-semibold text-gray-600">Номер</th>
                                <th class="text-left p-3 font-semibold text-gray-600">Груз.</th>
                                <th class="text-left p-3 font-semibold text-gray-600">Статус</th>
                                <th class="text-left p-3 font-semibold text-gray-600">Порода</th>
                                <th class="text-left p-3 font-semibold text-gray-600">Маршрут</th>
                                <th class="text-left p-3 font-semibold text-gray-600">Время</th>
                                <th class="text-left p-3 font-semibold text-gray-600">Задержка</th>
                                <th class="text-left p-3 font-semibold text-gray-600"></th>
                            </tr>
                        </thead>
                        <tbody>
                            @php
                                $statusLabels = [
                                    'free' => ['label' => 'В отстое', 'color' => 'slate', 'icon' => 'fa-parking'],
                                    'completed' => ['label' => 'Ожидает', 'color' => 'emerald', 'icon' => 'fa-check-circle'],
                                    'to_miner' => ['label' => 'К забою', 'color' => 'cyan', 'icon' => 'fa-arrow-right'],
                                    'loading' => ['label' => 'Погрузка', 'color' => 'amber', 'icon' => 'fa-truck-loading'],
                                    'transporting' => ['label' => 'Перевозка', 'color' => 'blue', 'icon' => 'fa-truck'],
                                    'unloading' => ['label' => 'Разгрузка', 'color' => 'slate', 'icon' => 'fa-truck-unload'],
                                    'breakdown' => ['label' => 'Поломка', 'color' => 'red', 'icon' => 'fa-wrench'],
                                    'delayed' => ['label' => 'Задержка', 'color' => 'amber', 'icon' => 'fa-clock'],
                                    'waiting_loading' => ['label' => 'Ожид. погрузки', 'color' => 'amber', 'icon' => 'fa-hourglass-half'],
                                    'waiting_unloading' => ['label' => 'Ожид. разгрузки', 'color' => 'red', 'icon' => 'fa-exclamation-triangle'],
                                ];
                            @endphp
                            @foreach($trucks as $truck)
                                @php
                                    $status = $statusLabels[$truck->status] ?? ['label' => $truck->status, 'color' => 'slate', 'icon' => 'fa-question'];
                                    $trip = $truck->trips->first();
                                    
                                    $truckRock = null;
                                    $truckRockLabel = '';
                                    if ($trip) {
                                        if (in_array($truck->status, ['transporting', 'unloading', 'waiting_unloading']) && $trip->rock) {
                                            $truckRock = $trip->rock;
                                            $truckRockLabel = 'Загружена';
                                        } elseif ($trip->rock_id) {
                                            $truckRock = $trip->rock;
                                            $truckRockLabel = 'В рейсе';
                                        } elseif ($trip->miningOrder && $trip->miningOrder->rock) {
                                            $truckRock = $trip->miningOrder->rock;
                                            $truckRockLabel = 'По маршруту';
                                        } elseif ($trip->miner && $trip->miner->currentRock) {
                                            $truckRock = $trip->miner->currentRock;
                                            $truckRockLabel = 'В забое';
                                        }
                                    }
                                    
                                    $activePause = null;
                                    if (in_array($truck->status, ['delayed', 'breakdown']) && $trip) {
                                        $activePause = $trip->pauses->first();
                                    }
                                @endphp
                                <tr class="border-b hover:bg-slate-50 {{ $truck->status === 'breakdown' ? 'bg-red-50' : ($truck->status === 'delayed' ? 'bg-amber-50' : ($truck->status === 'waiting_unloading' ? 'bg-red-50' : '')) }}">
                                    <td class="p-3 font-bold text-gray-800">{{ $truck->number }}</td>
                                    <td class="p-3 text-gray-500">{{ $truck->load_capacity }}т</td>
                                    <td class="p-3">
                                        <span class="px-2 py-0.5 text-xs font-medium rounded-md bg-{{ $status['color'] }}-100 text-{{ $status['color'] }}-700">
                                            <i class="fas {{ $status['icon'] }} mr-1"></i>{{ $status['label'] }}
                                        </span>
                                        {{-- Для свободных самосвалов без маршрута показываем точную причину --}}
                                        @if(in_array($truck->status, ['free', 'completed']))
                                            @php
                                                $truckDiag = $this->truckDiagnostics[$truck->id] ?? null;
                                            @endphp
                                            @if($truckDiag && $truckDiag['primary_reason'])
                                                @php
                                                    $reasonCode = $truckDiag['primary_reason'];
                                                    $reasonLabel = \App\Domain\RouteBlockReason::shortLabel($reasonCode);
                                                    $reasonColor = \App\Domain\RouteBlockReason::color($reasonCode);
                                                    $reasonIcon = \App\Domain\RouteBlockReason::icon($reasonCode);
                                                    $reasonAction = \App\Domain\RouteBlockReason::action($reasonCode);
                                                    $reasonCount = $truckDiag['summary'][$reasonCode] ?? 0;
                                                @endphp
                                                <div class="mt-1 group relative inline-block">
                                                    <span class="px-2 py-0.5 text-[10px] font-medium rounded-md bg-{{ $reasonColor }}-100 text-{{ $reasonColor }}-700 cursor-help"
                                                          title="{{ \App\Domain\RouteBlockReason::label($reasonCode) }}. {{ $reasonAction }}">
                                                        <i class="fas {{ $reasonIcon }} mr-0.5"></i>{{ $reasonLabel }}@if($reasonCount > 1) ×{{ $reasonCount }}@endif
                                                    </span>
                                                    {{-- Тултип с детальным описанием --}}
                                                    <div class="hidden group-hover:block absolute z-50 left-0 top-full mt-1 w-64 bg-slate-800 text-white text-xs rounded-lg shadow-xl p-3">
                                                        <div class="font-semibold mb-1">{{ \App\Domain\RouteBlockReason::label($reasonCode) }}</div>
                                                        <div class="text-slate-300 mb-2">{{ $reasonAction }}</div>
                                                        @if(!empty($truckDiag['orders']))
                                                            <div class="border-t border-slate-600 pt-2 mt-2">
                                                                <div class="text-slate-400 mb-1">По маршрутам:</div>
                                                                @foreach(collect($truckDiag['orders'])->take(5) as $orderDiag)
                                                                    <div class="text-slate-200 text-[10px]">
                                                                        <span class="font-mono">#{{ $orderDiag['order_id'] }}</span>
                                                                        {{ $orderDiag['miner_name'] }}
                                                                        @if($orderDiag['rock_name'])
                                                                            <span class="text-slate-400">({{ $orderDiag['rock_name'] }})</span>
                                                                        @endif
                                                                        — {{ $orderDiag['reason_label'] }}
                                                                    </div>
                                                                @endforeach
                                                                @if(count($truckDiag['orders']) > 5)
                                                                    <div class="text-slate-400 text-[10px] mt-1">…и ещё {{ count($truckDiag['orders']) - 5 }}</div>
                                                                @endif
                                                            </div>
                                                        @endif
                                                    </div>
                                                </div>
                                            @else
                                                <span class="ml-1 px-2 py-0.5 text-[10px] font-medium rounded-md bg-emerald-50 text-emerald-600">
                                                    <i class="fas fa-check mr-0.5"></i>Готов к назначению
                                                </span>
                                            @endif
                                        @endif
                                    </td>
                                    <td class="p-3">
                                        @if($truckRock)
                                            <span class="px-2 py-0.5 text-xs font-medium rounded-md bg-cyan-100 text-cyan-700">{{ $truckRock->name_rock }}</span>
                                        @else
                                            <span class="text-gray-400">—</span>
                                        @endif
                                    </td>
                                    <td class="p-3 text-gray-600">
                                        @if($trip)
                                            <small>
                                                <i class="fas fa-route text-gray-400 mr-1"></i>
                                                {{ $trip->miner?->name_miner ?? '-' }} → {{ $trip->dump?->name_dump ?? '-' }}
                                                @if($trip->zone)
                                                    <span class="ml-1 px-2 py-0.5 text-xs bg-slate-200 rounded">{{ $trip->zone->name_zone }}</span>
                                                @endif
                                            </small>
                                        @else
                                            <span class="text-gray-400">—</span>
                                        @endif
                                    </td>
                                    <td class="p-3">
                                        @if($trip && $trip->started_at)
                                            @php
                                                $completedPauseSeconds = 0;
                                                $activePauseStart = null;
                                                foreach ($trip->pauses as $pause) {
                                                    if ($pause->ended_at) {
                                                        $completedPauseSeconds += $pause->duration_seconds ?? 0;
                                                    } else {
                                                        $activePauseStart = $pause->started_at;
                                                    }
                                                }
                                            @endphp
                                            <small class="font-monospace trip-timer {{ $activePause ? 'text-amber-600' : 'text-gray-800' }}"
                                                   data-started-at="{{ $trip->started_at->toISOString() }}"
                                                   data-pause-seconds="{{ $completedPauseSeconds }}"
                                                   @if($activePauseStart)
                                                   data-active-pause-start="{{ $activePauseStart->toISOString() }}"
                                                   @endif
                                                   data-truck-id="{{ $truck->id }}">
                                                {{ $trip->getFormattedTripDuration() }}
                                            </small>
                                        @else
                                            <span class="text-gray-400">—</span>
                                        @endif
                                    </td>
                                    <td class="p-3">
                                        @if($activePause)
                                            <span class="px-2 py-0.5 text-xs font-medium rounded-md {{ $truck->status === 'breakdown' ? 'bg-red-100 text-red-700' : 'bg-amber-100 text-amber-700' }}">
                                                {{ \App\Models\TripPause::typeLabel($activePause->type) }}
                                            </span>
                                        @else
                                            <span class="text-gray-400">—</span>
                                        @endif
                                    </td>
                                    <td class="p-3">
                                        <button class="text-gray-400 hover:text-emerald-600" wire:click="openForceStatusModal({{ $truck->id }})" title="Изменить статус">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Забои -->
        <div x-show="tab === 'minersTab'" x-cloak class="mt-4" id="minersTab">
            <div class="bg-white rounded-xl border shadow-sm overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-slate-50 border-b">
                            <tr>
                                <th class="text-left p-3 font-semibold text-gray-600">Забой</th>
                                <th class="text-left p-3 font-semibold text-gray-600">Порода</th>
                                <th class="text-left p-3 font-semibold text-gray-600">Цель (сек)</th>
                                <th class="text-left p-3 font-semibold text-gray-600">Факт (мин)</th>
                                <th class="text-left p-3 font-semibold text-gray-600">Ожидание</th>
                                <th class="text-left p-3 font-semibold text-gray-600">В работе</th>
                                <th class="text-left p-3 font-semibold text-gray-600">Рекомендация</th>
                                <th class="text-left p-3 font-semibold text-gray-600">Статус</th>
                                <th class="text-left p-3 font-semibold text-gray-600"></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($miners as $miner)
                                @php
                                    // Считаем грузовики в работе у этого забоя
                                    $trucksAtMiner = $trucks->filter(function($truck) use ($miner) {
                                        $trip = $truck->trips->first();
                                        return $trip && $trip->miner_id === $miner->id && 
                                               in_array($truck->status, ['to_miner', 'loading', 'waiting_loading']);
                                    });
                                    
                                    // Грузовики в ожидании назначения для разгрузки (нет зоны для породы)
                                    $trucksWaitingUnloading = $trucks->filter(function($truck) use ($miner) {
                                        $trip = $truck->trips->first();
                                        return $trip && $trip->miner_id === $miner->id && 
                                               $truck->status === 'waiting_unloading';
                                    });
                                    
                                    // Рекомендации по производительности
                                    $recommendations = $miner->getRecommendedTruckCount();

                                    // Маппинг цветов Bootstrap на Tailwind
                                    $bsToTailwind = [
                                        'success' => 'emerald',
                                        'danger' => 'red',
                                        'warning' => 'amber',
                                        'secondary' => 'slate',
                                        'info' => 'cyan',
                                        'primary' => 'blue',
                                        'dark' => 'gray'
                                    ];
                                    $statusColor = $bsToTailwind[$miner->getStatusClass()] ?? 'slate';
                                @endphp
                                <tr class="border-b hover:bg-slate-50">
                                    <td class="p-3">
                                        <span class="font-bold text-gray-800">{{ $miner->name_miner }}</span>
                                        @if($miner->status !== 'active' && $miner->status_changed_at)
                                            @php
                                                $statusLabel = \App\Domain\MinerStatus::label($miner->status);
                                                $duration = $miner->status_changed_at->locale('ru')->diffForHumans(null, true);
                                            @endphp
                                            <br><small class="text-gray-500">
                                                {{ $statusLabel }}: {{ $duration }}
                                            </small>
                                        @endif
                                    </td>
                                    <td class="p-3">
                                        @if($miner->currentRock)
                                            <span class="px-2 py-0.5 text-xs font-medium rounded-md bg-cyan-100 text-cyan-700">{{ $miner->currentRock->name_rock }}</span>
                                        @elseif($miner->rocks->first())
                                            <span class="px-2 py-0.5 text-xs font-medium rounded-md bg-slate-200 text-slate-700">{{ $miner->rocks->first()->name_rock }}</span>
                                            <small class="text-gray-400">(истор.)</small>
                                        @else
                                            <span class="text-gray-400">—</span>
                                        @endif
                                    </td>
                                    <td class="p-3">
                                        @if($miner->target_load_time)
                                            <span class="px-2 py-0.5 text-xs font-medium rounded-md bg-blue-100 text-blue-700">{{ $miner->target_load_time }}с</span>
                                        @else
                                            <span class="text-gray-400 text-xs">Не задано</span>
                                        @endif
                                    </td>
                                    <td class="p-3">
                                        @php $avgLoadTime = $miner->getAvgLoadTime(5); @endphp
                                        @if($avgLoadTime)
                                            @if($miner->target_load_time)
                                                @php 
                                                    // target_load_time в секундах, конвертируем в минуты
                                                    $targetInMinutes = $miner->target_load_time / 60;
                                                    $diff = $avgLoadTime - $targetInMinutes;
                                                    $percent = round(($avgLoadTime / $targetInMinutes) * 100);
                                                @endphp
                                                @if($diff <= 0)
                                                    <span class="px-2 py-0.5 text-xs font-medium rounded-md bg-emerald-100 text-emerald-700">{{ $avgLoadTime }} мин</span>
                                                @else
                                                    <span class="px-2 py-0.5 text-xs font-medium rounded-md bg-amber-100 text-amber-700" title="Превышение на {{ round($diff, 1) }} мин">
                                                        {{ $avgLoadTime }} мин ({{ $percent }}%)
                                                    </span>
                                                @endif
                                            @else
                                                <span class="px-2 py-0.5 text-xs font-medium rounded-md bg-slate-200 text-slate-700">{{ $avgLoadTime }} мин</span>
                                            @endif
                                        @else
                                            <span class="text-gray-400">—</span>
                                        @endif
                                    </td>
                                    <td class="p-3">
                                        @php $avgWaitTime = $miner->getAvgWaitTime(5); @endphp
                                        @if($avgWaitTime && $avgWaitTime > 0)
                                            <span class="px-2 py-0.5 text-xs font-medium rounded-md {{ $avgWaitTime > 3 ? 'bg-amber-100 text-amber-700' : 'bg-slate-200 text-slate-700' }}">
                                                {{ $avgWaitTime }} мин
                                            </span>
                                        @else
                                            <span class="text-gray-400">—</span>
                                        @endif
                                    </td>
                                    <td class="p-3">
                                        @if($trucksAtMiner->count() > 0)
                                            <span class="px-2 py-0.5 text-xs font-medium rounded-md bg-blue-100 text-blue-700">{{ $trucksAtMiner->count() }}</span>
                                            <small class="text-gray-500 ml-1">
                                                {{ $trucksAtMiner->map(fn($t) => $t->number)->join(', ') }}
                                            </small>
                                        @else
                                            <span class="text-gray-400">—</span>
                                        @endif
                                        @if($trucksWaitingUnloading->count() > 0)
                                            <br>
                                            <span class="px-2 py-0.5 text-xs font-medium rounded-md bg-red-100 text-red-700 mt-1 inline-block">
                                                <i class="fas fa-exclamation-triangle mr-1"></i>
                                                {{ $trucksWaitingUnloading->count() }} ожидает!
                                            </span>
                                            <small class="text-gray-500 ml-1">
                                                {{ $trucksWaitingUnloading->map(fn($t) => $t->number)->join(', ') }}
                                            </small>
                                        @endif
                                    </td>
                                    <td class="p-3">
                                        @if($recommendations)
                                            <div class="flex items-center gap-2">
                                                <span class="font-bold text-gray-800">{{ $recommendations['current'] }}</span>
                                                <span class="text-gray-400">/</span>
                                                <span class="text-cyan-600">{{ $recommendations['recommended'] }}</span>
                                                @php
                                                    $balanceLabels = [
                                                        'underloaded' => ['label' => 'Недогружен', 'class' => 'amber'],
                                                        'balanced' => ['label' => 'ОК', 'class' => 'emerald'],
                                                        'overloaded' => ['label' => 'Перегруз', 'class' => 'red'],
                                                    ];
                                                    $balanceInfo = $balanceLabels[$recommendations['balance']] ?? $balanceLabels['balanced'];
                                                @endphp
                                                <span class="px-2 py-0.5 text-xs font-medium rounded-md bg-{{ $balanceInfo['class'] }}-100 text-{{ $balanceInfo['class'] }}-700">
                                                    {{ $balanceInfo['label'] }}
                                                </span>
                                            </div>
                                            <small class="text-gray-400 block mt-1">
                                                необходимо: {{ $recommendations['recommended'] }} самосвалов
                                            </small>
                                        @else
                                            <span class="text-gray-400 text-xs">Данных нет</span>
                                        @endif
                                    </td>
                                    <td class="p-3">
                                        <span class="px-2 py-0.5 text-xs font-medium rounded-md bg-{{ $statusColor }}-100 text-{{ $statusColor }}-700">
                                            {{ $miner->getStatusLabel() }}
                                        </span>
                                        @if($miner->isDelayed())
                                            <small class="text-gray-400 block mt-1">
                                                {{ $miner->getStatusDurationMinutes() }} мин
                                            </small>
                                        @endif
                                    </td>
                                    <td class="p-3">
                                        <button class="text-gray-400 hover:text-emerald-600" wire:click="openMinerStatusModal({{ $miner->id }})" wire:loading.attr="disabled" title="Изменить статус">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Маршруты (управление mining_orders) -->
        <div x-show="tab === 'routesTab'" x-cloak class="mt-4" id="routesTab">  
            <!-- Панель управления режимами -->
            <div class="bg-white rounded-xl border shadow-sm p-4 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
                <div class="flex items-center gap-4">
                    <div class="flex gap-1 bg-slate-100 p-1 rounded-lg">
                        <button type="button" wire:click="setRouteMode('auto')" wire:loading.attr="disabled" class="px-3 py-1 rounded-md text-xs font-semibold uppercase transition {{ $this->routeMode === 'auto' ? 'bg-emerald-600 text-white' : 'text-gray-600 hover:bg-gray-200' }}">
                            <i class="fas fa-robot mr-1"></i> Авто
                        </button>
                        <button type="button" wire:click="setRouteMode('manual')" wire:loading.attr="disabled" class="px-3 py-1 rounded-md text-xs font-semibold uppercase transition {{ $this->routeMode === 'manual' ? 'bg-amber-500 text-white' : 'text-gray-600 hover:bg-gray-200' }}">
                            <i class="fas fa-hand-paper mr-1"></i> Ручной
                        </button>
                    </div>
                    <small class="text-gray-500 hidden sm:block">
                        @if($this->routeMode === 'auto')
                            <i class="fas fa-info-circle"></i> Система автоматически выбирает маршруты
                        @else
                            <i class="fas fa-exclamation-triangle"></i> Управление маршрутами вручную
                        @endif
                    </small>
                </div>
                <div class="flex gap-2 w-full sm:w-auto flex-wrap">
                    <button class="px-4 py-2 bg-emerald-600 text-white rounded-md text-xs font-semibold uppercase hover:bg-emerald-700 w-full sm:w-auto" wire:click="generateAllPairs" wire:loading.attr="disabled" wire:confirm="Сгенерировать маршруты для всех пар забой-отвал, которых ещё нет в БД? Существующие маршруты с пустым distance_km будут обновлены.">
                        <span wire:loading.remove><i class="fas fa-layer-group mr-1"></i> Сгенерировать все пары</span>
                        <span wire:loading><i class="fas fa-spinner fa-spin mr-1"></i> Генерация...</span>
                    </button>
                    <button class="px-4 py-2 bg-blue-600 text-white rounded-md text-xs font-semibold uppercase hover:bg-blue-700 w-full sm:w-auto" wire:click="openCreateOrderModal">
                        <i class="fas fa-plus mr-1"></i> Добавить маршрут
                    </button>
                    @if($this->routeMode === 'auto')
                        <button class="px-4 py-2 bg-emerald-600 text-white rounded-md text-xs font-semibold uppercase hover:bg-emerald-700 w-full sm:w-auto" wire:click="optimizeRoutes" wire:loading.attr="disabled">
                            <span wire:loading.remove><i class="fas fa-magic mr-1"></i> Оптимизировать</span>
                            <span wire:loading><i class="fas fa-spinner fa-spin mr-1"></i> Оптимизация...</span>
                        </button>
                    @else
                        <button class="px-4 py-2 bg-slate-200 text-slate-700 rounded-md text-xs font-semibold uppercase hover:bg-slate-300 w-full sm:w-auto" wire:click="optimizeRoutes" wire:loading.attr="disabled" title="Принудительная оптимизация (ручной режим)">
                            <i class="fas fa-magic mr-1"></i> Оптимизировать
                        </button>
                    @endif
                    {{-- Кнопка справки --}}
                    <button type="button" @click="$dispatch('open-routes-help')" class="px-3 py-2 bg-slate-100 text-slate-600 rounded-md text-xs font-semibold uppercase hover:bg-slate-200 hover:text-slate-800 transition" title="Шпаргалка по вкладке «Маршруты»">
                        <i class="fas fa-question-circle"></i>
                    </button>
                </div>
            </div>

            {{-- Модальное окно «Шпаргалка по вкладке Маршруты» --}}
            <div
                x-data="{ open: false }"
                x-on:open-routes-help.window="open = true"
                x-on:keydown.escape.window="open = false"
                x-show="open"
                x-cloak
                x-transition.opacity
                style="display: none;"
                class="fixed inset-0 z-[99999] flex items-center justify-center p-4"
            >
                <div x-show="open" x-transition.opacity class="absolute inset-0 bg-black/60 backdrop-blur-sm" x-on:click="open = false"></div>
                <div x-show="open" x-transition class="relative w-full max-w-3xl max-h-[90vh] overflow-y-auto bg-white rounded-2xl shadow-2xl">
                    <div class="sticky top-0 z-10 px-6 py-4 bg-gradient-to-r from-blue-500 to-cyan-500 text-white flex justify-between items-center">
                        <div class="flex items-center gap-3">
                            <i class="fas fa-route text-2xl"></i>
                            <div>
                                <h3 class="text-lg font-semibold">Шпаргалка: Маршруты</h3>
                                <p class="text-sm text-white/90">Управление грузопотоками карьера</p>
                            </div>
                        </div>
                        <button type="button" x-on:click="open = false" class="text-white/80 hover:text-white text-2xl leading-none">&times;</button>
                    </div>
                    <div class="p-6 space-y-5 text-sm text-gray-700">
                        <div>
                            <h4 class="font-bold text-gray-800 mb-2 flex items-center gap-2"><i class="fas fa-toggle-on text-emerald-500"></i> Режимы</h4>
                            <div class="space-y-1.5 pl-6">
                                <div><span class="font-mono bg-emerald-100 text-emerald-700 px-1.5 py-0.5 rounded text-xs">🤖 Авто</span> — система сама оптимизирует при начале смены и добавлении/удалении забоев/отвалов</div>
                                <div><span class="font-mono bg-amber-100 text-amber-700 px-1.5 py-0.5 rounded text-xs">✋ Ручной</span> — диспетчер сам активирует маршруты кнопкой ▶️</div>
                            </div>
                        </div>
                        <div>
                            <h4 class="font-bold text-gray-800 mb-2 flex items-center gap-2"><i class="fas fa-mouse-pointer text-blue-500"></i> Кнопки</h4>
                            <div class="space-y-1.5 pl-6">
                                <div><strong>🟢 Сгенерировать все пары</strong> — создаёт маршруты забой→отвал для всех возможных комбинаций. Безопасно, дубликаты пропускаются.</div>
                                <div><strong>🔵 Добавить маршрут</strong> — создать один конкретный маршрут с указанием породы и расстояния.</div>
                                <div><strong>✨ Оптимизировать</strong> — полный пересчёт: сбрасывает WRR-курсоры, выбирает лучшие отвалы для каждого забоя, активирует раунды. Запускать в начале смены или после структурных изменений.</div>
                            </div>
                        </div>
                        <div>
                            <h4 class="font-bold text-gray-800 mb-2 flex items-center gap-2"><i class="fas fa-tags text-purple-500"></i> Статусы маршрутов</h4>
                            <div class="space-y-1.5 pl-6">
                                <div><span class="font-mono bg-emerald-100 text-emerald-700 px-1.5 py-0.5 rounded text-xs">✓ Раунд 1</span> — основной маршрут забоя (лучший). WRR направляет сюда самосвалы.</div>
                                <div><span class="font-mono bg-blue-100 text-blue-700 px-1.5 py-0.5 rounded text-xs">↻ Раунд 2</span> — резервный. Если основной недоступен — самосвалы переключаются сюда автоматически.</div>
                                <div><span class="font-mono bg-slate-200 text-slate-700 px-1.5 py-0.5 rounded text-xs">Резерв</span> — неактивен, ждёт ручной активации или резервирования.</div>
                                <div><span class="font-mono bg-amber-100 text-amber-700 px-1.5 py-0.5 rounded text-xs">⚠ Нет зон</span> — активен, но нет доступных зон (закрыты/переполнены).</div>
                            </div>
                        </div>
                        <div>
                            <h4 class="font-bold text-gray-800 mb-2 flex items-center gap-2"><i class="fas fa-palette text-pink-500"></i> Цвета строк</h4>
                            <div class="space-y-1.5 pl-6">
                                <div class="bg-white border-l-4 border-emerald-400 pl-2">Белая — маршрут активен, зоны доступны ✅</div>
                                <div class="bg-amber-50 border-l-4 border-amber-400 pl-2">Жёлтая — порода забоя не принимается на отвал (проверьте зоны в Панели Мастера)</div>
                                <div class="bg-red-50 border-l-4 border-red-400 pl-2">Красная — все зоны отвала закрыты (откройте зону или активируйте другой отвал)</div>
                                <div class="bg-slate-50 border-l-4 border-slate-300 pl-2 opacity-60">Серая — неактивный маршрут (в резерве)</div>
                            </div>
                        </div>
                        <div>
                            <h4 class="font-bold text-gray-800 mb-2 flex items-center gap-2"><i class="fas fa-balance-scale text-orange-500"></i> Вес маршрута (WRR)</h4>
                            <div class="pl-6 space-y-1.5">
                                <div>Кнопками <span class="font-mono bg-slate-100 px-1.5 rounded">−</span> и <span class="font-mono bg-slate-100 px-1.5 rounded">+</span> меняется на ±10.</div>
                                <div><span class="font-mono bg-blue-100 text-blue-700 px-1.5 rounded text-xs">100</span> — стандарт (по умолчанию)</div>
                                <div><span class="font-mono bg-blue-100 text-blue-700 px-1.5 rounded text-xs">200</span> — в 2 раза чаще самосвалы</div>
                                <div><span class="font-mono bg-blue-100 text-blue-700 px-1.5 rounded text-xs">50</span> — в 2 раза реже</div>
                                <div class="text-xs text-gray-500 mt-1">Повышай вес ближайшего отвала, чтобы направить туда больше самосвалов. Снижай, чтобы разгрузить.</div>
                            </div>
                        </div>
                        {{-- АДАПТИВНАЯ БАЛАНСИРОВКА --}}
                        <div>
                            <h4 class="font-bold text-gray-800 mb-2 flex items-center gap-2"><i class="fas fa-balance-scale-right text-orange-500"></i> Адаптивная балансировка</h4>
                            <div class="pl-6 space-y-1.5 text-xs">
                                <div>Алгоритм балансирует между двумя целями:</div>
                                <div class="bg-orange-50 p-2 rounded border-l-2 border-orange-400">
                                    <strong>Эксклюзивный режим</strong> (один отвал — один забой) → предотвращает «жадный захват», равномерное заполнение зон.
                                </div>
                                <div class="bg-emerald-50 p-2 rounded border-l-2 border-emerald-400">
                                    <strong>Разделение отвала</strong> (несколько забоев на один отвал) → минимизирует среднее расстояние перевозки.
                                </div>
                                <div class="mt-2">
                                    Решение принимается по <strong>порогу разделения зон</strong> (в Настройках, по умолчанию 30%):
                                </div>
                                <div class="pl-2">→ Зона ≤ 30% заполнения → <strong>разрешаем делиться</strong> отвалом (минимизация расстояния)</div>
                                <div class="pl-2">→ Зона > 30% заполнения → <strong>эксклюзив</strong> (защита от перегрузки)</div>
                                <div class="text-gray-500 mt-1">
                                    Пример: у забоя А ближайший отвал X занят забоем Б. Если зона X пустая (≤30%) — А всё равно работает на X. Если заполнена (>30%) — А переключается на дальний Y.
                                </div>
                            </div>
                        </div>
                        <div>
                            <h4 class="font-bold text-gray-800 mb-2 flex items-center gap-2"><i class="fas fa-exclamation-circle text-red-500"></i> Важные правила</h4>
                            <div class="pl-6 space-y-1.5 text-xs">
                                <div>• Зона принимает только <strong>ОДНУ породу</strong> — нельзя смешивать (кроме fallback: руда_ЦПТ → руда → руда_S)</div>
                                <div>• <strong>Нельзя сменить породу в открытой зоне</strong> — сначала закройте зону для завозки и отгрузите остатки</div>
                                <div>• Без <strong>current_rock_id</strong> у забоя маршруты не назначаются</div>
                                <div>• Без <strong>расстояний</strong> маршрут не участвует в оптимизации (задаются в Панели Мастера → Забои → «Расстояния»)</div>
                                <div>• <strong>Аварийный режим</strong>: если все активные маршруты забоя недоступны — система сама активирует первый доступный резервный</div>
                                <div>• <strong>Порог разделения зон</strong> настраивается в Настройках (0-100%, по умолчанию 30%)</div>
                            </div>
                        </div>
                        <div>
                            <h4 class="font-bold text-gray-800 mb-2 flex items-center gap-2"><i class="fas fa-lightbulb text-yellow-500"></i> Типичные сценарии</h4>
                            <div class="pl-6 space-y-2 text-xs">
                                <div class="bg-slate-50 p-2 rounded">
                                    <strong>Начало смены:</strong> авто-режим → «Планировать смену» → проверь, что у каждого забоя есть «Раунд 1» и «Раунд 2»
                                </div>
                                <div class="bg-slate-50 p-2 rounded">
                                    <strong>Закрылась зона:</strong> система сама деактивирует маршрут и переключит самосвалы на резерв. Проверь, что резерв есть.
                                </div>
                                <div class="bg-slate-50 p-2 rounded">
                                    <strong>Зона переполнилась:</strong> система закроет её, уведомит мастера об обваловке, переключит самосвалы на резерв.
                                </div>
                                <div class="bg-slate-50 p-2 rounded">
                                    <strong>Добавлен забой:</strong> в авто-режиме оптимизация запустится автоматически. Проверь, что у забоя задана порода и расстояния.
                                </div>
                                <div class="bg-slate-50 p-2 rounded">
                                    <strong>Хочу направить больше самосвалов на отвал:</strong> повысь вес маршрута кнопкой <span class="font-mono bg-slate-200 px-1 rounded">+</span> до 200-300. Не забудь вернуть обратно.
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="sticky bottom-0 px-6 py-3 bg-slate-50 border-t flex justify-end">
                        <button type="button" x-on:click="open = false" class="px-4 py-2 bg-slate-600 text-white rounded-md text-xs font-semibold uppercase hover:bg-slate-700">
                            Понятно
                        </button>
                    </div>
                </div>
            </div>

            <!-- Подсказки -->
            <div class="bg-cyan-50 border-l-4 border-cyan-400 text-cyan-700 p-3 text-xs rounded">
                <i class="fas fa-info-circle"></i>
                <strong>Подсказки:</strong>
                <span class="px-2 py-0.5 ml-1 bg-cyan-100 text-cyan-700 rounded">Порода в забое</span> — текущая добываемая порода.
                <span class="px-2 py-0.5 ml-1 bg-emerald-100 text-emerald-700 rounded">Зелёная</span> — порода в зоне совместима с породой забоя.
                <span class="px-2 py-0.5 ml-1 bg-amber-100 text-amber-700 rounded">Жёлтая строка</span> — порода забоя не принимается на отвал.
                <span class="px-2 py-0.5 ml-1 bg-red-100 text-red-700 rounded">Красная строка</span> — все зоны отвала закрыты.
            </div>

            @php $ordersGrouped = $this->ordersGroupedByMiner; @endphp

            @foreach($ordersGrouped as $minerName => $orders)
                @php
                    $firstOrder = $orders->first();
                    $currentRock = $firstOrder?->current_rock;
                    $minerId = $firstOrder?->miner?->id;
                    $miner = $firstOrder?->miner;
                    $activeCount = $orders->where('active', true)->count();
                    $minerStatus = $miner?->status ?? 'unknown';
                    $minerIsActive = $minerStatus === \App\Domain\MinerStatus::ACTIVE;
                @endphp
                <div class="bg-white rounded-xl border shadow-sm overflow-hidden">
                    <div class="p-4 border-b bg-slate-50 flex justify-between items-center">
                        <div class="flex items-center gap-2 flex-wrap">
                            <strong class="text-gray-800">
                                <i class="fas fa-mountain mr-1 text-gray-400"></i>{{ $minerName }}
                            </strong>
                            {{-- Бейдж статуса забоя --}}
                            @if($miner)
                                @php
                                    $statusLabel = \App\Domain\MinerStatus::label($minerStatus);
                                    $statusColor = $minerIsActive
                                        ? 'bg-emerald-100 text-emerald-700 border-emerald-200'
                                        : 'bg-amber-100 text-amber-700 border-amber-300';
                                @endphp
                                <span class="px-2 py-0.5 text-xs font-medium rounded-md border {{ $statusColor }}" title="Статус забоя: {{ $statusLabel }}">
                                    @if($minerIsActive)
                                        <i class="fas fa-check-circle mr-0.5"></i>{{ $statusLabel }}
                                    @else
                                        <i class="fas fa-exclamation-triangle mr-0.5"></i>{{ $statusLabel }}
                                    @endif
                                </span>
                            @endif
                            @if($currentRock)
                                <span class="px-2 py-0.5 text-xs font-medium rounded-md bg-cyan-100 text-cyan-700">
                                    <i class="fas fa-gem mr-1"></i>{{ $currentRock->name_rock }}
                                </span>
                            @else
                                <span class="px-2 py-0.5 text-xs font-medium rounded-md bg-amber-100 text-amber-700">
                                    <i class="fas fa-exclamation-triangle mr-1"></i>Нет породы
                                </span>
                            @endif
                            @if(!$minerIsActive && $miner)
                                <span class="text-xs text-amber-700">
                                    <i class="fas fa-info-circle"></i> Маршруты в резерве — забой не активен
                                </span>
                            @endif
                        </div>
                        <div class="text-xs text-gray-500">
                            <span class="mr-2">{{ $orders->count() }} маршрутов</span>
                            @if($activeCount > 0)
                                <span class="px-2 py-0.5 text-xs font-medium rounded-md bg-emerald-100 text-emerald-700">{{ $activeCount }} активен</span>
                            @endif
                        </div>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead class="bg-slate-50 border-b">
                                <tr>
                                    <th class="text-left p-3 font-semibold text-gray-600">Перегрузка</th>
                                    <th class="text-left p-3 font-semibold text-gray-600">Породы в зонах</th>
                                    <th class="text-left p-3 font-semibold text-gray-600">Расст.</th>
                                    <th class="text-left p-3 font-semibold text-gray-600">Вес</th>
                                    <th class="text-left p-3 font-semibold text-gray-600">Доступные зоны</th>
                                    <th class="text-left p-3 font-semibold text-gray-600">Статус</th>
                                    <th class="text-left p-3 font-semibold text-gray-600">Действия</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($orders as $order)
                                    @php
                                        $openZones = $order->dump?->zones?->filter(fn($z) => $z->delivery);
                                        $dumpRocks = $openZones?->flatMap(fn($z) => $z->rocks)->unique('id');
                                        $isCompatible = $currentRock && $dumpRocks?->contains('id', $currentRock->id);
                                    @endphp
                                    <tr class="border-b {{ $order->active ? '' : 'opacity-60 bg-slate-50' }} {{ !$isCompatible ? 'bg-amber-50' : '' }} {{ $openZones?->isEmpty() ? 'bg-red-50' : '' }}">
                                        <td class="p-3">
                                            <strong class="text-gray-800">{{ $order->dump?->name_dump ?? '—' }}</strong>
                                            @if($openZones?->isEmpty())
                                                <br><small class="text-red-600"><i class="fas fa-exclamation-triangle"></i> Все зоны закрыты</small>
                                            @endif
                                        </td>
                                        <td class="p-3">
                                            @if($dumpRocks && $dumpRocks->count() > 0)
                                                @foreach($dumpRocks->take(3) as $rock)
                                                    <span class="px-2 py-0.5 text-xs font-medium rounded-md mr-1 {{ $currentRock && $rock->id === $currentRock->id ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-200 text-slate-700' }}">
                                                        {{ $rock->name_rock }}
                                                    </span>
                                                @endforeach
                                                @if($dumpRocks->count() > 3)
                                                    <small class="text-gray-400">+{{ $dumpRocks->count() - 3 }}</small>
                                                @endif
                                            @else
                                                <small class="text-gray-400">Не указаны</small>
                                            @endif
                                        </td>
                                        <td class="p-3 text-gray-600">{{ $order->distance_km ?? '—' }} км</td>
                                        <td class="p-3">
                                            <div class="flex items-center gap-1">
                                                <button class="text-gray-400 hover:text-gray-600 px-1" wire:click="adjustWeight({{ $order->id }}, -10)" title="Уменьшить вес">
                                                    <i class="fas fa-minus text-[10px]"></i>
                                                </button>
                                                <span class="px-2 py-0.5 text-xs font-bold rounded-md {{ $order->active ? 'bg-blue-100 text-blue-700' : 'bg-slate-200 text-slate-700' }}" style="min-width: 35px; text-align: center;">{{ $order->weight }}</span>
                                                <button class="text-gray-400 hover:text-gray-600 px-1" wire:click="adjustWeight({{ $order->id }}, 10)" title="Увеличить вес">
                                                    <i class="fas fa-plus text-[10px]"></i>
                                                </button>
                                            </div>
                                        </td>
                                        <td class="p-3">
                                            @if($order->available_zones->count() > 0)
                                                @foreach($order->available_zones->take(2) as $zone)
                                                    <span class="px-2 py-0.5 text-xs font-medium rounded-md bg-cyan-100 text-cyan-700 mr-1" title="Заполнено: {{ $zone['fill'] }}%">
                                                        {{ $zone['name'] }}
                                                    </span>
                                                @endforeach
                                                @if($order->available_zones->count() > 2)
                                                    <small class="text-gray-400">+{{ $order->available_zones->count() - 2 }}</small>
                                                @endif
                                            @elseif($openZones->isNotEmpty())
                                                @if(!$isCompatible)
                                                    <small class="text-amber-600" title="Нет зон для породы: {{ $currentRock?->name_rock }}">
                                                        <i class="fas fa-exclamation-triangle"></i> Порода не принимается
                                                    </small>
                                                @else
                                                    <small class="text-gray-400" title="Все зоны переполнены">
                                                        <i class="fas fa-database"></i> Зоны заполнены
                                                    </small>
                                                @endif
                                            @else
                                                <small class="text-red-600"><i class="fas fa-times-circle"></i> Нет открытых зон</small>
                                            @endif
                                        </td>
                                        <td class="p-3">
                                            @if($order->active && $order->has_zones)
                                                @if($order->round === 1)
                                                    <span class="px-2 py-0.5 text-xs font-medium rounded-md bg-emerald-100 text-emerald-700" title="Основной маршрут (раунд 1)">
                                                        ✓ Раунд 1
                                                    </span>
                                                @elseif($order->round)
                                                    <span class="px-2 py-0.5 text-xs font-medium rounded-md bg-blue-100 text-blue-700" title="Резервный маршрут (раунд {{ $order->round }})">
                                                        ↻ Раунд {{ $order->round }}
                                                    </span>
                                                @else
                                                    <span class="px-2 py-0.5 text-xs font-medium rounded-md bg-emerald-100 text-emerald-700">✓ Активен</span>
                                                @endif
                                            @elseif($order->active)
                                                <span class="px-2 py-0.5 text-xs font-medium rounded-md bg-amber-100 text-amber-700">⚠ Нет зон</span>
                                            @else
                                                <span class="px-2 py-0.5 text-xs font-medium rounded-md bg-slate-200 text-slate-700">Резерв</span>
                                            @endif
                                        </td>
                                        <td class="p-3">
                                            <div class="flex gap-2">
                                                @if($order->active)
                                                    <button class="text-gray-400 hover:text-amber-600" wire:click="deactivateOrder({{ $order->id }})" title="Деактивировать">
                                                        <i class="fas fa-pause"></i>
                                                    </button>
                                                @else
                                                    <button class="text-gray-400 hover:text-emerald-600" wire:click="activateOrder({{ $order->id }})" title="Активировать">
                                                        <i class="fas fa-play"></i>
                                                    </button>
                                                @endif
                                                <button class="text-gray-400 hover:text-blue-600" wire:click="openEditOrder({{ $order->id }})" title="Редактировать">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <button class="text-gray-400 hover:text-red-600" wire:click="deleteOrder({{ $order->id }})" wire:confirm="Удалить маршрут?" title="Удалить">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endforeach

            @if($ordersGrouped->isEmpty())
                <div class="bg-white rounded-xl border shadow-sm p-6 text-center text-gray-500">
                    <i class="fas fa-exclamation-triangle text-amber-500 fa-2x mb-2"></i>
                    <h6 class="text-gray-700">Нет настроенных маршрутов</h6>
                    <p class="text-sm">Создайте маршруты для забоев.</p>
                </div>
            @endif
        </div>

        {{-- Модальное окно «Создать маршрут» --}}
        <div
            x-data="{ open: @entangle('showCreateOrderModal') }"
            x-show="open"
            x-cloak
            x-transition.opacity
            style="display: none;"
            class="fixed inset-0 z-[99999] flex items-center justify-center"
            x-on:keydown.escape.window="open = false"
        >
            <div x-show="open" x-transition.opacity class="absolute inset-0 bg-black/60 backdrop-blur-sm" x-on:click="open = false"></div>
            <div x-show="open" x-transition class="relative w-full max-w-lg mx-4 bg-white rounded-2xl shadow-2xl overflow-hidden">
                <div class="px-6 py-4 bg-gradient-to-r from-blue-500 to-cyan-500 text-white">
                    <div class="flex items-center gap-3">
                        <i class="fas fa-route text-2xl"></i>
                        <div>
                            <h3 class="text-lg font-semibold">Новый маршрут</h3>
                            <p class="text-sm text-white/90">Забой → Отвал</p>
                        </div>
                    </div>
                </div>
                <form wire:submit.prevent="createNewOrder" class="px-6 py-5 space-y-4">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Забой *</label>
                            <select wire:model.live="newOrderMinerId" class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error('newOrderMinerId') border-red-500 @enderror">
                                <option value="">— Выберите забой —</option>
                                @foreach(\App\Models\Miner::orderBy('name_miner')->get() as $miner)
                                    <option value="{{ $miner->id }}">{{ $miner->name_miner }}</option>
                                @endforeach
                            </select>
                            @error('newOrderMinerId') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Отвал *</label>
                            <select wire:model.live="newOrderDumpId" class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error('newOrderDumpId') border-red-500 @enderror">
                                <option value="">— Выберите отвал —</option>
                                @foreach(\App\Models\Dump::orderBy('name_dump')->get() as $dump)
                                    <option value="{{ $dump->id }}">{{ $dump->name_dump }}</option>
                                @endforeach
                            </select>
                            @error('newOrderDumpId') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                        </div>
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Порода (необязательно)</label>
                            <select wire:model.live="newOrderRockId" class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                <option value="">— Авто (текущая порода забоя) —</option>
                                @foreach(\App\Models\Rock::all() as $rock)
                                    <option value="{{ $rock->id }}">{{ $rock->name_rock }}</option>
                                @endforeach
                            </select>
                            <p class="mt-1 text-xs text-gray-500">Если не указать — будет браться текущая порода из забоя</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Расстояние (км)</label>
                            <input type="number" step="0.01" wire:model.live="newOrderDistanceKm" placeholder="Напр. 2.5" class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500" min="0" max="500">
                            <p class="mt-1 text-xs text-gray-500">Если есть в технологическом паспорте — введите</p>
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Вес маршрута (для WRR)</label>
                        <input type="number" wire:model.live="newOrderWeight" class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500" min="1" max="1000">
                        <p class="mt-1 text-xs text-gray-500">Чем выше вес — тем больше самосвалов будет направляться на этот маршрут</p>
                    </div>
                    <div class="bg-blue-50 border border-blue-200 rounded-md p-3 text-xs text-blue-800">
                        <i class="fas fa-info-circle mr-1"></i>
                        Маршрут создаётся <strong>неактивным</strong>. После создания запустите «Оптимизировать» (в авто-режиме) или активируйте вручную.
                    </div>
                    <div class="flex gap-2 pt-2">
                        <button type="submit" wire:loading.attr="disabled" class="flex-1 px-4 py-2 bg-blue-600 hover:bg-blue-700 disabled:bg-blue-300 text-white rounded-md font-medium transition">
                            <span wire:loading.remove><i class="fas fa-plus mr-1"></i> Создать маршрут</span>
                            <span wire:loading><i class="fas fa-spinner fa-spin mr-1"></i> Создание...</span>
                        </button>
                        <button type="button" wire:click="closeCreateOrderModal" class="px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-md font-medium transition">
                            Отмена
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Назначение маршрута -->
        <div x-show="tab === 'assignTab'" x-cloak class="mt-4" id="assignTab">
            <div class="bg-white rounded-xl border shadow-sm p-6">
                @php
                    $isSelectedTruckLoaded = $this->isSelectedTruckLoaded();
                    $loadedTruckInfo = $this->loaded_truck_info;
                @endphp

                @if($isSelectedTruckLoaded && $loadedTruckInfo)
                    <div class="p-4 bg-amber-50 border-l-4 border-amber-500 text-amber-700 rounded mb-4 text-sm">
                        <i class="fas fa-info-circle mr-1"></i>
                        <strong>Грузовик уже загружен</strong> — выберите только зону разгрузки.
                        <br>
                        <span class="mr-3">Забой: <strong>{{ $loadedTruckInfo['miner_name'] }}</strong></span>
                        <span>Загрузка: <strong>{{ $loadedTruckInfo['load_volume'] ?? '?' }} т</strong></span>
                    </div>
                @endif
                
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
                    <div>
                        <label class="block text-xs uppercase font-semibold text-gray-500 mb-1">Самосвал</label>
                        <select wire:model.live="selectedTruckId" class="w-full border-gray-300 rounded-md shadow-sm py-2 text-sm">
                            <option value="">Выберите</option>
                            @foreach($this->free_trucks as $truck)
                                <option value="{{ $truck->id }}">{{ $truck->number }} ({{ $truck->load_capacity }}т) — {{ $truck->getStatusLabel() }}</option>
                            @endforeach
                        </select>
                        @if($selectedTruckId && !$isSelectedTruckLoaded)
                            @php $selectedTruck = $this->free_trucks->firstWhere('id', $selectedTruckId); @endphp
                            @if($selectedTruck && !in_array($selectedTruck->status, ['free', 'completed']))
                                <small class="text-amber-600 text-xs mt-1 block"><i class="fas fa-exclamation-triangle"></i> Переназначение (текущий будет отменён)</small>
                            @endif
                        @endif
                    </div>

                    @if(!$isSelectedTruckLoaded)
                        <div>
                            <label class="block text-xs uppercase font-semibold text-gray-500 mb-1">Забой</label>
                            <select wire:model.live="selectedMinerId" class="w-full border-gray-300 rounded-md shadow-sm py-2 text-sm">
                                <option value="">Выберите</option>
                                @foreach($this->active_miners_with_rock as $miner)
                                    <option value="{{ $miner->id }}">{{ $miner->name_miner }} (@if($miner->currentRock){{ $miner->currentRock->name_rock }}@elseif($miner->rocks->first()){{ $miner->rocks->first()->name_rock }}@else—@endif)</option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label class="block text-xs uppercase font-semibold text-gray-500 mb-1">Маршрут</label>
                            <select wire:model.live="selectedOrderId" class="w-full border-gray-300 rounded-md shadow-sm py-2 text-sm" @if(!$selectedMinerId) disabled @endif>
                                <option value="">Выберите забой сначала</option>
                                @foreach($availableOrders as $order)
                                    <option value="{{ $order['id'] }}">{{ $order['dump_name'] }} ({{ $order['distance'] }} км)</option>
                                @endforeach
                            </select>
                            @if($availableOrders && count($availableOrders) === 0 && $selectedMinerId)
                                <small class="text-amber-600 text-xs mt-1 block">Нет маршрутов с доступными зонами</small>
                            @endif
                        </div>
                    @else
                        <div>
                            <label class="block text-xs uppercase font-semibold text-gray-500 mb-1">Забой (авто)</label>
                            <input type="text" class="w-full border-gray-300 rounded-md shadow-sm py-2 text-sm bg-slate-100" value="{{ $loadedTruckInfo['miner_name'] ?? '—' }}" disabled>
                        </div>
                        <div>
                            <label class="block text-xs uppercase font-semibold text-gray-500 mb-1">Порода <span class="text-gray-400 normal-case">(можно изменить)</span></label>
                            <select wire:model.live="loadedTruckRockId" class="w-full border-gray-300 rounded-md shadow-sm py-2 text-sm">
                                <option value="">Выберите породу</option>
                                @foreach($rocks as $rock)
                                    <option value="{{ $rock->id }}">{{ $rock->name_rock }}</option>
                                @endforeach
                            </select>
                        </div>
                    @endif

                    <div>
                        <label class="block text-xs uppercase font-semibold text-gray-500 mb-1">Зона разгрузки</label>
                        <select wire:model.live="selectedZoneId" class="w-full border-gray-300 rounded-md shadow-sm py-2 text-sm" @if(!$isSelectedTruckLoaded && !$selectedOrderId) disabled @endif>
                            <option value="">Автоматически (наименее заполненная)</option>
                            @foreach($availableZones as $zone)
                                <option value="{{ $zone['id'] }}">{{ $zone['dump_name'] ?? '' }} - {{ $zone['name'] }} ({{ $zone['fill'] }}%)</option>
                            @endforeach
                        </select>
                        @if($isSelectedTruckLoaded && count($availableZones) === 0)
                            <small class="text-red-600 text-xs mt-1 block"><i class="fas fa-exclamation-triangle"></i> Нет доступных зон для породы!</small>
                        @endif
                    </div>
                </div>

                <div class="flex gap-3">
                    <button wire:click="assignRoute" wire:loading.attr="disabled" class="px-6 py-2 bg-emerald-600 text-white rounded-md font-semibold uppercase text-sm hover:bg-emerald-700" @if(!$selectedTruckId) disabled @endif>
                        <span wire:loading.remove><i class="fas fa-check-circle mr-1"></i> Назначить</span>
                        <span wire:loading><i class="fas fa-spinner fa-spin mr-1"></i> Назначение...</span>
                    </button>
                    <button wire:click="assignAllFree" wire:loading.attr="disabled" class="px-6 py-2 bg-slate-200 text-slate-700 rounded-md font-semibold uppercase text-sm hover:bg-slate-300">
                        <span wire:loading.remove><i class="fas fa-sync mr-1"></i> Назначить всем</span>
                        <span wire:loading><i class="fas fa-spinner fa-spin mr-1"></i> Назначение...</span>
                    </button>
                </div>
            </div>
        </div>

        <!-- Зоны разгрузки -->
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
                                <div class="h-2.5 rounded-full {{ $fillPercent > 90 ? 'bg-red-500' : ($fillPercent > 70 ? 'bg-amber-500' : 'bg-emerald-500') }}" style="width: {{ $fillPercent }}%"></div>
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
        <div x-show="tab === 'pausesTab'" x-cloak class="mt-4" id="pausesTab">
            @php
                $pauseStats = $this->pause_stats;
                $minerDelays = $this->miner_delays;
            @endphp

            <!-- Фильтры -->
            <div class="bg-white rounded-xl border shadow-sm p-4">
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <div>
                        <label class="block text-xs uppercase font-semibold text-gray-500 mb-1">Период:</label>
                        <select wire:model.live="pausePeriod" class="w-full border-gray-300 rounded-md py-2 text-sm">
                            <option value="shift">За смену</option>
                            <option value="today">За сегодня</option>
                            <option value="week">За неделю</option>
                            <option value="month">За месяц</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs uppercase font-semibold text-gray-500 mb-1"><i class="fas fa-truck mr-1"></i>Тип простоев ТС:</label>
                        <div class="border rounded p-2 bg-slate-50 max-h-[120px] overflow-y-auto text-sm">
                            @foreach(\App\Models\TripPause::types() as $typeKey => $typeLabel)
                                <label class="flex items-center gap-2 mb-1">
                                    <input type="checkbox" value="{{ $typeKey }}" wire:model.live="pauseTypes" class="rounded text-emerald-600">
                                    {{ $typeLabel }}
                                </label>
                            @endforeach
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs uppercase font-semibold text-gray-500 mb-1"><i class="fas fa-mountain mr-1"></i>Тип простоев забоев:</label>
                        <div class="border rounded p-2 bg-slate-50 max-h-[120px] overflow-y-auto text-sm">
                            @foreach(\App\Models\MinerPause::types() as $typeKey => $typeLabel)
                                <label class="flex items-center gap-2 mb-1">
                                    <input type="checkbox" value="{{ $typeKey }}" wire:model.live="minerPauseTypes" class="rounded text-emerald-600">
                                    {{ $typeLabel }}
                                </label>
                            @endforeach
                        </div>
                    </div>
                    <div class="text-xs text-gray-500 self-end">
                        {{ $pauseStats['period_label'] }}: <strong class="text-gray-800">{{ $pauseStats['total_count'] }}</strong> инц. ТС (<strong class="text-{{ $pauseStats['total_seconds'] > 0 ? 'red' : 'gray' }}-600">{{ $pauseStats['total_formatted'] }}</strong>)<br>
                        <strong class="text-gray-800">{{ $minerDelays['total_count'] }}</strong> задержек забоев (<strong class="text-{{ $minerDelays['total_minutes'] > 0 ? 'red' : 'gray' }}-600">{{ $minerDelays['total_formatted'] }}</strong>)
                        @php $waitingUnloadingCount = $trucks->where('status', 'waiting_unloading')->count(); @endphp
                        @if($waitingUnloadingCount > 0)<br><strong class="text-red-600">{{ $waitingUnloadingCount }}</strong> ожидают назначения!@endif
                    </div>
                </div>
            </div>

            <!-- Ожидают назначения зоны -->
            @php $trucksWaitingUnloading = $trucks->where('status', 'waiting_unloading'); @endphp
            @if($trucksWaitingUnloading->count() > 0)
            <div class="bg-white rounded-xl border shadow-sm p-4">
                <h6 class="text-red-600 font-bold uppercase text-sm mb-3"><i class="fas fa-exclamation-triangle mr-1"></i> Ожидают назначения зоны разгрузки</h6>
                <div class="flex flex-wrap gap-2">
                    @foreach($trucksWaitingUnloading as $truck)
                        @php $trip = $truck->trips->first(); @endphp
                        <div class="px-3 py-1 border border-red-300 bg-red-50 rounded-md text-sm">
                            <strong>{{ $truck->number }}</strong> <span class="text-gray-500">({{ $trip?->rock?->name_rock ?? '—' }})</span>
                        </div>
                    @endforeach
                </div>
            </div>
            @endif

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                <!-- Таблица простоев ТС -->
                <div class="bg-white rounded-xl border shadow-sm overflow-hidden">
                    <div class="p-4 border-b font-bold text-gray-800 uppercase text-sm flex justify-between items-center">
                        <span><i class="fas fa-truck mr-1"></i> Самосвалы</span>
                        @if($pauseStats['active_count'] > 0)<span class="px-2 py-0.5 bg-red-100 text-red-700 rounded text-xs">{{ $pauseStats['active_count'] }} активн.</span>@endif
                    </div>
                    <div class="overflow-x-auto max-h-[400px] overflow-y-auto">
                        <table class="w-full text-sm">
                            <thead class="bg-slate-50 border-b sticky top-0">
                                <tr><th class="p-2 text-left">Время</th><th class="p-2 text-left">ТС</th><th class="p-2 text-left">Тип</th><th class="p-2 text-left">Длит.</th></tr>
                            </thead>
                            <tbody>
                                @foreach($pauseStats['pauses']->take(20) as $pause)
                                    <tr class="border-b {{ $pause->ended_at ? '' : 'bg-amber-50' }}">
                                        <td class="p-2 text-gray-500">{{ $pause->started_at->format('H:i') }}</td>
                                        <td class="p-2 font-medium">{{ $pause->truck?->number ?? '—' }}</td>
                                        <td class="p-2"><span class="px-2 py-0.5 text-xs rounded {{ $pause->type === 'breakdown' ? 'bg-red-100 text-red-700' : 'bg-amber-100 text-amber-700' }}">{{ \App\Models\TripPause::typeLabel($pause->type) }}</span></td>
                                        <td class="p-2">{{ $pause->getFormattedDuration() }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Таблица простоев забоев -->
                <div class="bg-white rounded-xl border shadow-sm overflow-hidden">
                    <div class="p-4 border-b font-bold text-gray-800 uppercase text-sm flex justify-between items-center">
                        <span><i class="fas fa-mountain mr-1"></i> Забои</span>
                        @if($minerDelays['total_count'] > 0)<span class="px-2 py-0.5 bg-red-100 text-red-700 rounded text-xs">{{ $minerDelays['total_count'] }}</span>@endif
                    </div>
                    <div class="overflow-x-auto max-h-[400px] overflow-y-auto">
                        <table class="w-full text-sm">
                            <thead class="bg-slate-50 border-b sticky top-0">
                                <tr><th class="p-2 text-left">Забой</th><th class="p-2 text-left">Тип</th><th class="p-2 text-left">Начало</th><th class="p-2 text-left">Длит.</th></tr>
                            </thead>
                            <tbody>
                                @foreach($minerDelays['pauses']->take(20) as $pause)
                                    <tr class="border-b {{ $pause->type === 'breakdown' ? 'bg-red-50' : ($pause->ended_at ? '' : 'bg-amber-50') }}">
                                        <td class="p-2 font-medium">{{ $pause->miner?->name_miner ?? '—' }}</td>
                                        <td class="p-2"><span class="px-2 py-0.5 text-xs rounded {{ $pause->type === 'breakdown' ? 'bg-red-100 text-red-700' : 'bg-amber-100 text-amber-700' }}">{{ $pause->getTypeLabel() }}</span></td>
                                        <td class="p-2 text-gray-500">{{ $pause->started_at->format('d.m H:i') }}</td>
                                        <td class="p-2">{{ $pause->getFormattedDuration() }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Аналитика скоростей по маршрутам -->
        <div x-show="tab === 'analyticsTab'" x-cloak class="mt-4" id="analyticsTab">
            @php $routeSpeeds = $this->route_speeds; @endphp
            <div class="bg-white rounded-xl border shadow-sm overflow-hidden">
                <div class="p-4 border-b font-bold text-gray-800 uppercase text-sm">Средняя скорость по маршрутам <span class="text-gray-400 normal-case font-normal">(за смену)</span></div>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-slate-50 border-b">
                            <tr><th class="p-3 text-left">Маршрут</th><th class="p-3 text-left">Ср. скорость</th><th class="p-3 text-left">Рейсов</th><th class="p-3 text-left">Расстояние</th></tr>
                        </thead>
                        <tbody>
                            @if(empty($routeSpeeds))
                                <tr><td colspan="4" class="p-8 text-center text-gray-500">Нет данных за текущую смену. Данные появятся после завершения рейсов.</td></tr>
                            @else
                                @foreach($routeSpeeds as $route)
                                    <tr class="border-b {{ $route['avg_speed'] > 0 && $route['avg_speed'] < 20 ? 'bg-red-50' : '' }}">
                                        <td class="p-3 font-medium text-gray-800">
                                            {{ $route['route'] }}
                                            @if($route['avg_speed'] > 0 && $route['avg_speed'] < 20)
                                                <small class="text-red-500 block"><i class="fas fa-exclamation-triangle"></i> Низкая скорость</small>
                                            @elseif($route['avg_speed'] > 0 && $route['avg_speed'] < 25)
                                                <small class="text-amber-500 block"><i class="fas fa-clock"></i> Средняя скорость</small>
                                            @endif
                                        </td>
                                        <td class="p-3">
                                            <span class="px-2 py-0.5 text-xs rounded {{ $route['avg_speed'] < 20 ? 'bg-red-100 text-red-700' : ($route['avg_speed'] < 25 ? 'bg-amber-100 text-amber-700' : 'bg-emerald-100 text-emerald-700') }}">{{ $route['avg_speed'] }} км/ч</span>
                                        </td>
                                        <td class="p-3 text-gray-600">{{ $route['trips_count'] }}</td>
                                        <td class="p-3 text-gray-500">{{ $route['total_distance'] }} км</td>
                                    </tr>
                                @endforeach
                            @endif
                        </tbody>
                    </table>
                </div>
                <div class="p-3 bg-slate-50 text-xs text-gray-500 border-t">
                    <i class="fas fa-info-circle"></i> Маршруты отсортированы по скорости (проблемные вверху). Низкая: &lt; 20 км/ч, Средняя: 20-25 км/ч, Высокая: &ge; 25 км/ч.
                </div>
            </div>
        </div>

        <!-- Настройки порогов и сервисных постов -->
        <div x-show="tab === 'settingsTab'" x-cloak class="mt-4 space-y-4" id="settingsTab">
            
            <!-- Алгоритм WRR -->
            <div class="bg-white rounded-xl border shadow-sm p-6">
                <h3 class="font-bold text-gray-800 uppercase text-sm mb-4 flex items-center gap-2"><i class="fas fa-route text-blue-500"></i> Алгоритм распределения маршрутов (WRR)</h3>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div>
                        <label class="block text-xs uppercase text-gray-500 mb-2">Количество раундов</label>
                        <div class="p-3 bg-blue-50 rounded-md text-blue-700 text-sm">
                            <i class="fas fa-info-circle mr-1"></i>
                            <strong>Динамическое</strong> (до 5)
                        </div>
                        <small class="text-gray-400 block mt-2">
                            Система автоматически определяет количество раундов: Раунд 1 = основной, Раунд 2+ = резервные.
                        </small>
                    </div>
                    <div>
                        <label class="block text-xs uppercase text-gray-500 mb-2">Порог разделения зон</label>
                        <div class="flex items-center gap-4">
                            <input type="range" min="0" max="100" step="5" wire:model.live="zoneSharingThreshold" class="flex-1 text-emerald-600">
                            <span class="px-3 py-1 bg-orange-100 text-orange-700 rounded font-bold">{{ $zoneSharingThreshold }}%</span>
                        </div>
                        <small class="text-gray-400 block mt-2">
                            Если зона отвалa заполнена ≤ порога — несколько забоев могут работать на этот отвал (минимизация расстояния).
                            Если > порога — эксклюзивный режим (один отвал — один забой, защита от «жадного захвата»).
                        </small>
                    </div>
                    <div>
                        <label class="block text-xs uppercase text-gray-500 mb-2">Текущий режим</label>
                        <div class="flex items-center gap-2 p-3 rounded-md {{ $this->routeMode === 'auto' ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-700' }}">
                            <i class="fas {{ $this->routeMode === 'auto' ? 'fa-robot' : 'fa-hand-paper' }}"></i>
                            <span class="font-semibold uppercase text-sm">{{ $this->routeMode === 'auto' ? 'Авто' : 'Ручной' }}</span>
                        </div>
                        <small class="text-gray-400 block mt-2">
                            @if($this->routeMode === 'auto')
                                Оптимизация при начале смены, структурных изменениях и по кнопке.
                            @else
                                Только ручное управление. syncAllOrders при событиях работает.
                            @endif
                        </small>
                    </div>
                </div>
            </div>

            <!-- Пороги перегруженности -->
            <div class="bg-white rounded-xl border shadow-sm p-6">
                <h3 class="font-bold text-gray-800 uppercase text-sm mb-4 flex items-center gap-2"><i class="fas fa-sliders-h text-emerald-500"></i> Пороги перегруженности</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-xs uppercase text-gray-500 mb-2">Порог ожидания на забое</label>
                        <div class="flex items-center gap-4">
                            <input type="range" min="1" max="10" wire:model.live="minerThreshold" class="flex-1 text-emerald-600">
                            <span class="px-3 py-1 bg-blue-100 text-blue-700 rounded font-bold">{{ $minerThreshold }}</span>
                        </div>
                        <small class="text-gray-400 block mt-2">Количество самосвалов в статусе "Ожидание погрузки" для признания забоя перегруженным.</small>
                    </div>
                    <div>
                        <label class="block text-xs uppercase text-gray-500 mb-2">Порог ожидания в зоне разгрузки</label>
                        <div class="flex items-center gap-4">
                            <input type="range" min="1" max="10" wire:model.live="zoneThreshold" class="flex-1 text-emerald-600">
                            <span class="px-3 py-1 bg-emerald-100 text-emerald-700 rounded font-bold">{{ $zoneThreshold }}</span>
                        </div>
                        <small class="text-gray-400 block mt-2">Количество самосвалов в статусе "Ожидание разгрузки" для признания зоны перегруженной.</small>
                    </div>
                </div>
            </div>

            <!-- Сервисные посты -->
            <div class="bg-white rounded-xl border shadow-sm p-6">
                <h3 class="font-bold text-gray-800 uppercase text-sm mb-4 flex items-center gap-2"><i class="fas fa-tools text-amber-500"></i> Сервисные посты</h3>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div>
                        <label class="block text-xs uppercase text-gray-500 mb-2"><i class="fas fa-gas-pump text-cyan-500 mr-1"></i> Посты заправки</label>
                        <div class="flex items-center gap-4 mb-2">
                            <input type="range" min="1" max="5" wire:model.live="fuelingPostsCount" class="flex-1 text-emerald-600">
                            <span class="px-3 py-1 bg-cyan-100 text-cyan-700 rounded font-bold">{{ $fuelingPostsCount }}</span>
                        </div>
                        @php $postsStatus = $this->servicePostsStatus; @endphp
                        @if(!empty($postsStatus['fueling']))
                            <div class="flex flex-wrap gap-1">
                                @foreach($postsStatus['fueling'] as $post)
                                    <span class="px-2 py-0.5 text-xs rounded {{ $post['is_occupied'] ? 'bg-amber-100 text-amber-700' : 'bg-emerald-100 text-emerald-700' }}">{{ $post['name'] }} @if($post['truck'])({{ $post['truck'] }})@endif</span>
                                @endforeach
                            </div>
                        @endif
                    </div>
                    <div>
                        <label class="block text-xs uppercase text-gray-500 mb-2"><i class="fas fa-wrench text-amber-500 mr-1"></i> Посты ТО</label>
                        <div class="flex items-center gap-4 mb-2">
                            <input type="range" min="1" max="5" wire:model.live="maintenancePostsCount" class="flex-1 text-emerald-600">
                            <span class="px-3 py-1 bg-amber-100 text-amber-700 rounded font-bold">{{ $maintenancePostsCount }}</span>
                        </div>
                        @if(!empty($postsStatus['maintenance']))
                            <div class="flex flex-wrap gap-1">
                                @foreach($postsStatus['maintenance'] as $post)
                                    <span class="px-2 py-0.5 text-xs rounded {{ $post['is_occupied'] ? 'bg-amber-100 text-amber-700' : 'bg-emerald-100 text-emerald-700' }}">{{ $post['name'] }} @if($post['truck'])({{ $post['truck'] }})@endif</span>
                                @endforeach
                            </div>
                        @endif
                    </div>
                    <div>
                        <label class="block text-xs uppercase text-gray-500 mb-2"><i class="fas fa-truck-loading text-slate-500 mr-1"></i> Шиномонтаж</label>
                        <div class="flex items-center gap-4 mb-2">
                            <input type="range" min="1" max="5" wire:model.live="tireServicePostsCount" class="flex-1 text-emerald-600">
                            <span class="px-3 py-1 bg-slate-200 text-slate-700 rounded font-bold">{{ $tireServicePostsCount }}</span>
                        </div>
                        @if(!empty($postsStatus['tire_service']))
                            <div class="flex flex-wrap gap-1">
                                @foreach($postsStatus['tire_service'] as $post)
                                    <span class="px-2 py-0.5 text-xs rounded {{ $post['is_occupied'] ? 'bg-amber-100 text-amber-700' : 'bg-emerald-100 text-emerald-700' }}">{{ $post['name'] }} @if($post['truck'])({{ $post['truck'] }})@endif</span>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            <!-- Очередь на обслуживание -->
            @php $serviceQueue = $this->serviceQueue; @endphp
            @if(!empty($serviceQueue['fueling']) || !empty($serviceQueue['maintenance']) || !empty($serviceQueue['tire_inflation']) || !empty($serviceQueue['wheel_tightening']))
            <div class="bg-white rounded-xl border shadow-sm overflow-hidden">
                <div class="p-4 border-b font-bold text-gray-800 uppercase text-sm">Очередь на обслуживание</div>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-slate-50 border-b">
                            <tr>
                                <th class="p-3 text-left">Тип</th>
                                <th class="p-3 text-left">Грузовик</th>
                                <th class="p-3 text-left">Позиция</th>
                                <th class="p-3 text-left">Статус</th>
                                <th class="p-3 text-left">Действия</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach(['fueling' => 'Заправка', 'maintenance' => 'ТО', 'tire_inflation' => 'Подкачка шин', 'wheel_tightening' => 'Обтяжка колёс'] as $type => $typeLabel)
                                @foreach($serviceQueue[$type] ?? [] as $task)
                                    <tr class="border-b">
                                        <td class="p-3">{{ $typeLabel }} @if($task['to_type'])({{ $task['to_type'] }})@endif</td>
                                        <td class="p-3 font-medium">{{ $task['truck'] }}</td>
                                        <td class="p-3">@if($task['started_at'])<span class="px-2 py-0.5 text-xs rounded bg-emerald-100 text-emerald-700">В процессе</span>@else<span class="px-2 py-0.5 text-xs rounded bg-slate-200 text-slate-700">{{ $task['position'] }}</span>@endif</td>
                                        <td class="p-3 text-gray-500">@if($task['started_at'])Начало: {{ $task['started_at'] }}@else Ожидание @endif</td>
                                        <td class="p-3">
                                            @if($task['started_at'])
                                                <button wire:click="completeServiceTask({{ $task['id'] }})" wire:confirm="Завершить?" class="text-emerald-600 hover:text-emerald-800"><i class="fas fa-check"></i></button>
                                            @else
                                                <button wire:click="cancelServiceTask({{ $task['id'] }})" wire:confirm="Отменить?" class="text-red-500 hover:text-red-700"><i class="fas fa-times"></i></button>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
            @endif
        </div>
</div>

    <!-- Модальное окно принудительной смены статуса -->
    @if($forceStatusTruckId)
        @php
            $truckToEdit = \App\Models\Truck::find($forceStatusTruckId);
            $statusLabels = [
                'free' => ['label' => 'Свободен', 'color' => 'emerald'],
                'to_miner' => ['label' => 'К забою', 'color' => 'cyan'],
                'loading' => ['label' => 'Погрузка', 'color' => 'amber'],
                'transporting' => ['label' => 'Перевозка', 'color' => 'blue'],
                'unloading' => ['label' => 'Разгрузка', 'color' => 'slate'],
                'breakdown' => ['label' => 'Поломка', 'color' => 'red'],
                'delayed' => ['label' => 'Задержка', 'color' => 'amber'],
            ];
            $currentStatus = $truckToEdit ? ($statusLabels[$truckToEdit->status] ?? ['label' => $truckToEdit->status, 'color' => 'slate']) : null;
            $previousStatusKey = $truckToEdit?->before_breakdown;
            $previousStatus = $previousStatusKey ? ($statusLabels[$previousStatusKey] ?? null) : null;
        @endphp
        <div wire:key="force-modal" class="fixed inset-0 z-[9999] flex items-center justify-center bg-black/70 p-4" style="display: flex;">
            <div class="bg-white rounded-xl shadow-2xl w-full max-w-md">
                <div class="p-4 border-b flex justify-between items-center">
                    <h5 class="font-bold text-gray-800 uppercase"><i class="fas fa-exclamation-triangle text-amber-500 mr-2"></i> Изменить статус: {{ $truckToEdit?->number }}</h5>
                    <button wire:click="closeForceStatusModal" class="text-gray-500 hover:text-gray-700 text-2xl leading-none">&times;</button>
                </div>
                <div class="p-4">
                    <select wire:model.live="forceStatusNew" class="w-full border-gray-300 rounded-md py-2 text-sm">
                        @if($previousStatus)<option value="{{ $previousStatusKey }}">Вернуться к "{{ $previousStatus['label'] }}"</option>@endif
                        <option value="">Оставить "{{ $currentStatus['label'] ?? '' }}"</option>
                        @foreach($this->available_statuses as $statusKey => $statusLabel)<option value="{{ $statusKey }}">{{ $statusLabel }}</option>@endforeach
                    </select>
                </div>
                <div class="p-4 border-t flex justify-end gap-2">
                    <button wire:click="closeForceStatusModal" class="px-4 py-2 bg-slate-200 text-slate-700 rounded-md uppercase text-sm">Отмена</button>
                    <button wire:click="forceChangeStatus" wire:loading.attr="disabled" class="px-4 py-2 bg-amber-500 text-white rounded-md uppercase text-sm hover:bg-amber-600" @if(!$forceStatusNew) disabled @endif>Изменить</button>
                </div>
            </div>
        </div>
    @endif

    <!-- Модальное окно редактирования маршрута -->
    @if($editOrderId)
        @php $orderToEdit = \App\Models\MiningOrder::with(['miner.rocks', 'dump'])->find($editOrderId); $currentDistance = $editDistances[$editDumpId] ?? $orderToEdit?->distance_km; @endphp
        <div wire:key="edit-order-modal" class="fixed inset-0 z-[9999] flex items-center justify-center bg-black/70 p-4" style="display: flex;">
            <div class="bg-white rounded-xl shadow-2xl w-full max-w-md">
                <div class="p-4 border-b flex justify-between items-center">
                    <h5 class="font-bold text-gray-800 uppercase">Редактирование #{{ $editOrderId }}</h5>
                    <button wire:click="closeEditOrder" class="text-gray-500 hover:text-gray-700 text-2xl leading-none">&times;</button>
                </div>
                <div class="p-4 space-y-3">
                    <div><label class="block text-xs uppercase text-gray-500 mb-1">Перегрузка</label><select wire:model.live="editDumpId" class="w-full border-gray-300 rounded-md py-2 text-sm">@foreach($dumps as $dump)<option value="{{ $dump->id }}">{{ $dump->name_dump }} ({{ $editDistances[$dump->id] ?? '—' }} км)</option>@endforeach</select></div>
                    <div><label class="block text-xs uppercase text-gray-500 mb-1">Статус</label><label class="flex items-center gap-2"><input type="checkbox" wire:model.live="editActive" class="rounded text-emerald-600"> <span class="text-sm">{{ $editActive ? 'Активен' : 'Неактивен' }}</span></label></div>
                </div>
                <div class="p-4 border-t flex justify-end gap-2"><button wire:click="closeEditOrder" class="px-4 py-2 bg-slate-200 text-slate-700 rounded-md uppercase text-sm">Отмена</button><button wire:click="saveOrder" class="px-4 py-2 bg-emerald-600 text-white rounded-md uppercase text-sm hover:bg-emerald-700">Сохранить</button></div>
            </div>
        </div>
    @endif

    <!-- Модальное окно смены статуса забоя -->
    @if($editMinerStatusId)
        @php $currentMiner = \App\Models\Miner::find($editMinerStatusId); @endphp
        <div wire:key="miner-status-modal" class="fixed inset-0 z-[9999] flex items-center justify-center bg-black/70 p-4" style="display: flex;">
            <div class="bg-white rounded-xl shadow-2xl w-full {{ $showMinerWarning ? 'max-w-3xl' : 'max-w-md' }}">
                <div class="p-4 border-b flex justify-between items-center {{ $showMinerWarning ? 'bg-red-50' : '' }}"><h5 class="font-bold text-gray-800 uppercase">Статус забоя: {{ $currentMiner?->name_miner }}</h5><button wire:click="closeMinerStatusModal" class="text-gray-500 hover:text-gray-700 text-2xl leading-none">&times;</button></div>
                <div class="p-4">
                    @if($showMinerWarning)
                        <div class="p-4 bg-red-50 border-l-4 border-red-500 text-red-700 rounded mb-4">{{ $minerStatusWarning }}</div>
                        @if($minerSafetyCheck && !empty($minerSafetyCheck['alternatives']))
                            <div class="overflow-x-auto"><table class="w-full text-sm border"><thead class="bg-slate-50"><tr><th class="p-2 text-left">Забой</th><th class="p-2 text-left">Оптим.</th><th class="p-2 text-left">Текущ.</th><th class="p-2 text-left">Вмест.</th></tr></thead><tbody>@foreach($minerSafetyCheck['alternatives'] as $alt)<tr class="border-b"><td class="p-2">{{ $alt['name'] }}</td><td class="p-2">{{ $alt['recommended'] }}</td><td class="p-2">{{ $alt['current'] }}</td><td class="p-2 {{ $alt['capacity'] > 0 ? 'text-emerald-600' : 'text-red-600' }}">{{ $alt['capacity'] }}</td></tr>@endforeach</tbody></table></div>
                        @endif
                        <div class="flex justify-end gap-2 mt-4"><button wire:click="cancelMinerStatusChange" class="px-4 py-2 bg-slate-200 text-slate-700 rounded-md uppercase text-sm">Отменить</button><button wire:click="forceMinerStatus" class="px-4 py-2 bg-red-600 text-white rounded-md uppercase text-sm hover:bg-red-700">Всё равно остановить</button></div>
                    @else
                        <div class="mb-4"><label class="block text-xs uppercase text-gray-500 mb-1">Новый статус</label><select wire:model.live="editMinerStatusNew" class="w-full border-gray-300 rounded-md py-2 text-sm">@foreach($this->miner_statuses as $status => $label)<option value="{{ $status }}">{{ $label }}</option>@endforeach</select></div>
                        <div class="flex justify-end gap-2"><button wire:click="closeMinerStatusModal" class="px-4 py-2 bg-slate-200 text-slate-700 rounded-md uppercase text-sm">Отмена</button><button wire:click="setMinerStatus" class="px-4 py-2 bg-emerald-600 text-white rounded-md uppercase text-sm hover:bg-emerald-700">Подтвердить</button></div>
                    @endif
                </div>
            </div>
        </div>
    @endif

    <script>
        function updateTripTimers() {
            document.querySelectorAll('.trip-timer').forEach(el => {
                const startedAtStr = el.dataset.startedAt;
                if (!startedAtStr) return;
                const startedAt = new Date(startedAtStr);
                const now = new Date();
                const totalSeconds = Math.floor((now - startedAt) / 1000);
                if (totalSeconds < 0) return;
                const completedPauseSeconds = parseInt(el.dataset.pauseSeconds) || 0;
                let activePauseSeconds = 0;
                const activePauseStartStr = el.dataset.activePauseStart;
                if (activePauseStartStr) {
                    const activePauseStart = new Date(activePauseStartStr);
                    activePauseSeconds = Math.floor((now - activePauseStart) / 1000);
                }
                const pauseSeconds = completedPauseSeconds + activePauseSeconds;
                const netSeconds = Math.max(0, totalSeconds - pauseSeconds);
                const hours = Math.floor(netSeconds / 3600);
                const minutes = Math.floor((netSeconds % 3600) / 60);
                const seconds = netSeconds % 60;
                let timeStr;
                if (hours > 0) { timeStr = `${hours}:${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`; } 
                else { timeStr = `${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`; }
                el.textContent = timeStr;
            });
        }
        setInterval(updateTripTimers, 1000);
        document.addEventListener('livewire:update', updateTripTimers);

        document.addEventListener('livewire:init', () => {
            updateTripTimers();

            
            if (window.Echo) {
                window.Echo.channel('dispatcher')
                    .listen('.truck-updated', (data) => { Livewire.dispatch('refresh-dispatcher-data'); })
                    .listen('.miner-productivity-updated', (eventData) => { Livewire.dispatch('miner-productivity-updated', { data: eventData }); });
            }
        });
    </script>
</div>
