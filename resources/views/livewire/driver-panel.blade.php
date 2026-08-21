<div class="min-h-screen flex flex-col bg-slate-50" x-data="{ tab: 'route' }">
    <!-- Toast контейнер для уведомлений -->
    <div id="global-toast-container" class="fixed top-0 right-0 p-3" style="z-index: 9999;"></div>

    <!-- ТЕМНАЯ ШАПКА С ВЫБОРОМ ГРУЗОВИКА (Адаптивная) -->
    <header class="bg-slate-900 text-white shadow-lg mb-4 rounded-xl">
        <div class="px-4 py-3 flex items-center justify-between">
            <h1 class="text-lg font-bold uppercase tracking-wider">Панель водителя</h1> 
            
            <!-- ПРАВЫЙ БЛОК: Обновить, Пользователь, Выход -->
            <div class="ml-auto flex items-center gap-4">
                <select wire:model.live="selectedTruckId" class="bg-slate-800 border-slate-700 text-white focus:border-emerald-500 focus:ring-emerald-500 rounded-md shadow-sm py-2 pl-3 pr-8 text-sm flex-1 min-w-0">
                    <option value="">-- Выберите грузовик --</option>
                    @foreach($trucks as $t)
                        <option value="{{ $t['id'] }}" {{ $t['id'] == $selectedTruckId ? 'selected' : '' }}>
                            {{ $t['number'] }}
                            @if($t['is_mine'] && $t['is_breakdown'])
                                (на ремонте)
                            @elseif(!$t['is_free'] && !$t['is_mine'])
                                ({{ $t['driver_name'] ?? 'занят' }})
                            @endif
                        </option>
                    @endforeach
                </select>
                <button wire:click="selectTruck" wire:loading.attr="disabled" class="inline-flex items-center justify-center px-4 py-2 bg-emerald-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-emerald-700 active:bg-emerald-900 transition ease-in-out duration-150 whitespace-nowrap">
                    <span wire:loading.remove>Выбрать</span>
                    <span wire:loading class="animate-spin">⏳</span>
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

    @if($truck)
    <!-- Навигация (Tabs) - Только иконки на мобильных -->
    <nav class="bg-white border-b shadow-sm sticky top-0 z-10">
        <div class="max-w-7xl mx-auto px-2 sm:px-4 flex overflow-x-auto gap-1 sm:gap-2 py-2 justify-around sm:justify-start">
            <button @click="tab='route'" :class="tab === 'route' ? 'bg-emerald-600 text-white shadow-sm' : 'text-gray-600 hover:bg-gray-100'" class="px-3 sm:px-4 py-2 rounded-lg text-sm font-medium whitespace-nowrap transition-all flex items-center gap-1.5">
                <span>🚛</span> <span class="hidden sm:inline">Маршрут</span>
            </button>
            <button @click="tab='fuel'" :class="tab === 'fuel' ? 'bg-emerald-600 text-white shadow-sm' : 'text-gray-600 hover:bg-gray-100'" class="px-3 sm:px-4 py-2 rounded-lg text-sm font-medium whitespace-nowrap transition-all flex items-center gap-1.5">
                <span>⛽</span> <span class="hidden sm:inline">Топливо</span>
            </button>
            <button @click="tab='restrictions'" :class="tab === 'restrictions' ? 'bg-emerald-600 text-white shadow-sm' : 'text-gray-600 hover:bg-gray-100'" class="px-3 sm:px-4 py-2 rounded-lg text-sm font-medium whitespace-nowrap transition-all flex items-center gap-1.5">
                <span>🚫</span> <span class="hidden sm:inline">Ограничения</span>
            </button>
            <button @click="tab='service'" :class="tab === 'service' ? 'bg-emerald-600 text-white shadow-sm' : 'text-gray-600 hover:bg-gray-100'" class="px-3 sm:px-4 py-2 rounded-lg text-sm font-medium whitespace-nowrap transition-all flex items-center gap-1.5 relative">
                <span>🔧</span> <span class="hidden sm:inline">Обслуживание</span>
                @if(count($pendingServiceTasks) > 0)
                    <span class="absolute top-1 right-1 sm:static sm:ml-2 bg-red-500 text-white text-[10px] font-bold rounded-full min-w-[18px] h-[18px] sm:min-w-[20px] sm:h-5 flex items-center justify-center px-1">{{ count($pendingServiceTasks) }}</span>
                @endif
            </button>
            <button @click="tab='stats'" :class="tab === 'stats' ? 'bg-emerald-600 text-white shadow-sm' : 'text-gray-600 hover:bg-gray-100'" class="px-3 sm:px-4 py-2 rounded-lg text-sm font-medium whitespace-nowrap transition-all flex items-center gap-1.5">
                <span>📊</span> <span class="hidden sm:inline">Статистика</span>
            </button>
            <button @click="tab='requests'" :class="tab === 'requests' ? 'bg-emerald-600 text-white shadow-sm' : 'text-gray-600 hover:bg-gray-100'" class="px-3 sm:px-4 py-2 rounded-lg text-sm font-medium whitespace-nowrap transition-all flex items-center gap-1.5">
                <span>📝</span> <span class="hidden sm:inline">Заявки</span>
            </button>
        </div>
    </nav>

    <!-- Основной контент -->
    <main class="flex-1 max-w-7xl mx-auto w-full px-3 sm:px-4 py-4 sm:py-6">
        
        <!-- ВКЛАДКА: МАРШРУТ -->
        <div x-show="tab === 'route'" x-cloak class="space-y-4 sm:space-y-6">
            <!-- Заголовок и статус -->
            <div class="flex items-center gap-2 sm:gap-4">
                <h2 class="text-base sm:text-lg font-bold text-gray-800 uppercase tracking-wider">Маршрут</h2>
                <span class="px-2 sm:px-3 py-1 text-xs sm:text-sm font-semibold rounded-md bg-{{ $statusColor }}-100 text-{{ $statusColor }}-700 border border-{{ $statusColor }}-300">{{ $statusLabel }}</span>
            </div>

            <!-- Информация о маршруте -->
            <div class="bg-white rounded-xl border shadow-sm p-4 sm:p-6" wire:key="route-info-{{ $currentTrip?->id ?? 'none' }}">
                @if($currentTrip)
                <div class="flex items-center justify-center sm:justify-start flex-wrap gap-2 sm:gap-4 mb-6">
                    <div class="text-center p-2 sm:p-3 bg-slate-50 rounded-lg border border-slate-200 flex-1 min-w-[80px] sm:min-w-[120px]">
                        <p class="text-[10px] sm:text-xs text-gray-500 uppercase font-semibold mb-1">Забой</p>
                        <p class="text-sm sm:text-lg font-bold text-gray-800 truncate">{{ $currentTrip->miner->name_miner ?? '-' }}</p>
                    </div>
                    <div class="text-gray-400 text-lg sm:text-2xl">→</div>
                    <div class="text-center p-2 sm:p-3 bg-slate-50 rounded-lg border border-slate-200 flex-1 min-w-[80px] sm:min-w-[120px]">
                        <p class="text-[10px] sm:text-xs text-gray-500 uppercase font-semibold mb-1">Перегрузка</p>
                        <p class="text-sm sm:text-lg font-bold text-gray-800 truncate">{{ $currentTrip->dump->name_dump ?? '-' }}</p>
                    </div>
                    <div class="text-gray-400 text-lg sm:text-2xl">→</div>
                    <div class="text-center p-2 sm:p-3 bg-slate-50 rounded-lg border border-slate-200 flex-1 min-w-[80px] sm:min-w-[120px]">
                        <p class="text-[10px] sm:text-xs text-gray-500 uppercase font-semibold mb-1">Зона</p>
                        @if($currentTrip->zone)
                            <p class="text-sm sm:text-lg font-bold text-emerald-600 truncate">{{ $currentTrip->zone->name_zone }}</p>
                        @else
                            <p class="text-sm sm:text-lg font-bold text-amber-500">Не назначена</p>
                        @endif
                    </div>
                    <div class="text-gray-400 text-lg sm:text-2xl">→</div>
                    <div class="text-center p-2 sm:p-3 bg-slate-50 rounded-lg border border-slate-200 flex-1 min-w-[80px] sm:min-w-[120px]">
                        <p class="text-[10px] sm:text-xs text-gray-500 uppercase font-semibold mb-1">Порода</p>
                        @php
                            $isLoaded = in_array($truck->status, ['transporting', 'unloading', 'waiting_unloading']);
                            $rock = $isLoaded && $currentTrip->rock_id ? $currentTrip->rock : $currentTrip->miningOrder?->rock;
                        @endphp
                        <p class="text-sm sm:text-lg font-bold text-gray-800 truncate">{{ $rock?->name_rock ?? '-' }}</p>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 sm:gap-4 pt-4 border-t">
                    <div>
                        <p class="text-[10px] sm:text-xs text-gray-500 uppercase font-semibold">Расстояние</p>
                        <p class="text-base sm:text-xl font-bold text-gray-800">{{ $currentTrip->miningOrder->distance_km ?? '-' }} км</p>
                    </div>
                    <div>
                        <p class="text-[10px] sm:text-xs text-gray-500 uppercase font-semibold">Время в пути</p>
                        <p class="text-base sm:text-xl font-bold text-gray-800 timer-display" id="trip-time"
                           data-started="{{ $tripStartedAt ?? '' }}"
                           data-pause-started="{{ $pauseStartedAt ?? '' }}"
                           data-pause-type="{{ $pauseType ?? '' }}"
                           data-total-pause="{{ $totalPauseSeconds }}"
                           data-truck-status="{{ $truck->status }}">-</p>
                    </div>
                    <div>
                        <p class="text-[10px] sm:text-xs text-gray-500 uppercase font-semibold">Объём</p>
                        <p class="text-base sm:text-xl font-bold text-gray-800">{{ $currentTrip->load_volume ?? $truck->load_capacity ?? '-' }} т</p>
                    </div>
                </div>
                @else
                <div class="text-center py-8 text-gray-500">
                    <p class="text-base sm:text-lg">Нет активного маршрута</p>
                </div>
                @endif
            </div>

            <!-- Управление статусами -->
            <div class="bg-white rounded-xl border shadow-sm p-4 sm:p-6" data-truck-status="{{ $truck->status }}">
                @php $status = $truck->status; @endphp

                {{-- Плашка автопоиска --}}
                @if($isSearchingRoute)
                    <div class="bg-amber-50 border-l-4 border-amber-500 text-amber-700 p-4 mb-4 rounded flex justify-between items-center">
                        <span class="text-sm font-medium">
                            <i class="fas fa-sync-alt fa-spin mr-2"></i>
                            Идет ожидание доступного маршрута...
                        </span>
                        <button wire:click="stopSearchingRoute" class="bg-red-500 hover:bg-red-600 text-white px-3 py-1 rounded text-xs font-bold uppercase">
                            Отменить
                        </button>
                    </div>
                @endif

                @if($status === 'free')
                    <div wire:key="status-free">
                        @if(!$currentTrip)
                            <button wire:click="assignRoute" wire:loading.attr="disabled" class="w-full inline-flex items-center justify-center px-4 sm:px-6 py-3 sm:py-4 bg-emerald-600 border border-transparent rounded-md font-semibold text-white uppercase tracking-widest hover:bg-emerald-700 transition text-sm sm:text-base">
                                <span wire:loading.remove>Получить маршрут</span>
                                <span wire:loading class="animate-spin">⏳ Получение...</span>
                            </button>
                        @endif
                    </div>
                @endif

                @if($status === 'completed')
                    <div wire:key="status-completed" class="space-y-3">
                        <div class="p-3 sm:p-4 bg-green-50 border-l-4 border-green-500 text-green-700 rounded text-sm">Рейс завершён. Запросите новый маршрут или уйдите в отстой.</div>
                        <button wire:click="assignRoute" class="w-full px-4 sm:px-6 py-3 bg-emerald-600 text-white rounded-md font-semibold uppercase hover:bg-emerald-700 text-sm sm:text-base">Запросить маршрут</button>
                        <button wire:click="goToStandby" class="w-full px-4 sm:px-6 py-3 bg-slate-200 text-slate-700 rounded-md font-semibold uppercase hover:bg-slate-300 text-sm sm:text-base">Уйти в отстой</button>
                    </div>
                @endif

                @if($status === 'to_miner')
                    <div wire:key="status-to_miner" class="space-y-3">
                        <button wire:click="startLoading" class="w-full px-4 sm:px-6 py-3 bg-emerald-600 text-white rounded-md font-semibold uppercase hover:bg-emerald-700 text-sm sm:text-base">Прибыл на погрузку</button>
                        <div class="flex flex-col sm:flex-row gap-3">
                            <button wire:click="openDelayModal" class="w-full sm:flex-1 px-4 py-2 bg-slate-200 text-slate-700 rounded-md font-semibold uppercase hover:bg-slate-300 text-sm">Задержка</button>
                            <button wire:click="reportBreakdown" wire:confirm="Сообщить о поломке?" class="w-full sm:flex-1 px-4 py-2 bg-red-100 text-red-700 border border-red-300 rounded-md font-semibold uppercase hover:bg-red-200 text-sm">Поломка</button>
                        </div>
                    </div>
                @endif

                @if($status === 'loading')
                    <div wire:key="status-loading" class="space-y-3">
                        <div class="p-3 sm:p-4 bg-amber-50 border-l-4 border-amber-500 text-amber-700 rounded text-center font-semibold text-sm">Ожидание завершения погрузки...</div>
                        <div class="flex flex-col sm:flex-row gap-3">
                            <button wire:click="openDelayModal" class="w-full sm:flex-1 px-4 py-2 bg-slate-200 text-slate-700 rounded-md font-semibold uppercase hover:bg-slate-300 text-sm">Задержка</button>
                            <button wire:click="reportBreakdown" wire:confirm="Сообщить о поломке?" class="w-full sm:flex-1 px-4 py-2 bg-red-100 text-red-700 border border-red-300 rounded-md font-semibold uppercase hover:bg-red-200 text-sm">Поломка</button>
                        </div>
                    </div>
                @endif

                @if($status === 'waiting_unloading')
                    <div wire:key="status-waiting_unloading" class="space-y-3">
                        <div class="p-3 sm:p-4 bg-amber-50 border-l-4 border-amber-500 text-amber-700 rounded text-center font-semibold text-sm">Ожидание назначения зоны разгрузки</div>
                        <button wire:click="reportBreakdown" wire:confirm="Сообщить о поломке?" class="w-full px-4 py-2 bg-red-100 text-red-700 border border-red-300 rounded-md font-semibold uppercase hover:bg-red-200 text-sm">Поломка</button>
                    </div>
                @endif

                @if($status === 'transporting')
                    <div wire:key="status-transporting" class="space-y-3">
                        <button wire:click="startUnloading" class="w-full px-4 sm:px-6 py-3 bg-emerald-600 text-white rounded-md font-semibold uppercase hover:bg-emerald-700 text-sm sm:text-base">Прибыл на выгрузку</button>
                        <div class="flex flex-col sm:flex-row gap-3">
                            <button wire:click="openDelayModal" class="w-full sm:flex-1 px-4 py-2 bg-slate-200 text-slate-700 rounded-md font-semibold uppercase hover:bg-slate-300 text-sm">Задержка</button>
                            <button wire:click="reportBreakdown" wire:confirm="Сообщить о поломке?" class="w-full sm:flex-1 px-4 py-2 bg-red-100 text-red-700 border border-red-300 rounded-md font-semibold uppercase hover:bg-red-200 text-sm">Поломка</button>
                        </div>
                    </div>
                @endif

                @if($status === 'unloading')
                    <div wire:key="status-unloading" class="space-y-3">
                        <button wire:click="completeTrip" class="w-full px-4 sm:px-6 py-3 bg-emerald-600 text-white rounded-md font-semibold uppercase hover:bg-emerald-700 text-sm sm:text-base">Завершить рейс</button>
                        <button wire:click="openZoneModal" class="w-full px-4 sm:px-6 py-3 bg-slate-200 text-slate-700 rounded-md font-semibold uppercase hover:bg-slate-300 text-sm sm:text-base">Сменить зону</button>
                    </div>
                @endif

                @if($status === 'delayed')
                    <div wire:key="status-delayed" class="space-y-3">
                        <div class="p-3 sm:p-4 bg-amber-50 border-l-4 border-amber-500 text-amber-700 rounded text-sm">Маршрут приостановлен: {{ \App\Models\TripPause::typeLabel($pauseType ?? 'other') }}</div>
                        <button wire:click="resumeFromDelay" class="w-full px-4 sm:px-6 py-3 bg-emerald-600 text-white rounded-md font-semibold uppercase hover:bg-emerald-700 text-sm sm:text-base">Задержка окончена</button>
                        <button wire:click="reportBreakdown" wire:confirm="Сообщить о поломке?" class="w-full px-4 py-2 bg-red-100 text-red-700 border border-red-300 rounded-md font-semibold uppercase hover:bg-red-200 text-sm">Поломка</button>
                    </div>
                @endif

                @if($status === 'breakdown')
                    <div wire:key="status-breakdown" class="space-y-3">
                        <div class="p-3 sm:p-4 bg-red-50 border-l-4 border-red-500 text-red-700 rounded text-sm">Поломка. После ремонта выберите действие.</div>
                        @if($currentTrip)
                            <button wire:click="resolveBreakdownContinue" class="w-full px-4 sm:px-6 py-3 bg-emerald-600 text-white rounded-md font-semibold uppercase hover:bg-emerald-700 text-sm sm:text-base">Продолжить рейс</button>
                            <button wire:click="resolveBreakdownCancel" wire:confirm="Отменить рейс?" class="w-full px-4 py-2 bg-red-100 text-red-700 border border-red-300 rounded-md font-semibold uppercase hover:bg-red-200 text-sm">Отменить рейс</button>
                        @else
                            <button wire:click="resolveBreakdownContinue" class="w-full px-4 sm:px-6 py-3 bg-emerald-600 text-white rounded-md font-semibold uppercase hover:bg-emerald-700 text-sm sm:text-base">Поломка устранена</button>
                        @endif
                    </div>
                @endif

                @if($status === 'service')
                    <div wire:key="status-service" class="space-y-3">
                        <div class="p-3 sm:p-4 bg-amber-50 border-l-4 border-amber-500 text-amber-700 rounded text-center text-sm">
                            <strong>{{ $currentServiceTask['type'] ?? 'Обслуживание' }}</strong>
                            @if(!empty($currentServiceTask['post_name']))<div class="mt-1 text-xs">Пост: {{ $currentServiceTask['post_name'] }}</div>@endif
                            @if(!empty($currentServiceTask['started_at']))<div class="text-xs text-gray-500 mt-1">Начало: {{ $currentServiceTask['started_at'] }}</div>@endif
                        </div>
                        <button wire:click="completeService" class="w-full px-4 sm:px-6 py-3 bg-emerald-600 text-white rounded-md font-semibold uppercase hover:bg-emerald-700 text-sm sm:text-base">Завершить обслуживание</button>
                    </div>
                @endif

                @if($status === 'fueling')
                    <div wire:key="status-fueling" class="space-y-3">
                        <div class="p-3 sm:p-4 bg-blue-50 border-l-4 border-blue-500 text-blue-700 rounded text-center text-sm">
                            <strong>Заправка</strong>
                            @if(!empty($currentServiceTask['post_name']))<div class="mt-1 text-xs">Пост: {{ $currentServiceTask['post_name'] }}</div>@endif
                            @if(!empty($currentServiceTask['started_at']))<div class="text-xs text-gray-500 mt-1">Начало: {{ $currentServiceTask['started_at'] }}</div>@endif
                        </div>
                        <button wire:click="completeService" class="w-full px-4 sm:px-6 py-3 bg-emerald-600 text-white rounded-md font-semibold uppercase hover:bg-emerald-700 text-sm sm:text-base">Завершить заправку</button>
                    </div>
                @endif

                @if($status === 'maintenance')
                    <div wire:key="status-maintenance" class="space-y-3">
                        <div class="p-3 sm:p-4 bg-amber-50 border-l-4 border-amber-500 text-amber-700 rounded text-center text-sm">
                            <strong>{{ $currentServiceTask['type'] ?? 'Техническое обслуживание' }}</strong>
                            @if(!empty($currentServiceTask['post_name']))<div class="mt-1 text-xs">Пост: {{ $currentServiceTask['post_name'] }}</div>@endif
                            @if(!empty($currentServiceTask['started_at']))<div class="text-xs text-gray-500 mt-1">Начало: {{ $currentServiceTask['started_at'] }}</div>@endif
                            @if(!empty($currentServiceTask['duration']))<div class="text-xs text-gray-500 mt-1">Плановая длительность: {{ $currentServiceTask['duration'] }} мин</div>@endif
                        </div>
                        <button wire:click="completeService" class="w-full px-4 sm:px-6 py-3 bg-emerald-600 text-white rounded-md font-semibold uppercase hover:bg-emerald-700 text-sm sm:text-base">Завершить ТО</button>
                    </div>
                @endif
            </div>

            <!-- Запланированные ТО -->
            @if(count($plannedShiftServices) > 0)
            <div class="bg-white rounded-xl border border-amber-300 shadow-sm p-4 sm:p-6">
                <h3 class="font-bold text-gray-800 uppercase tracking-wider mb-4 flex items-center gap-2 text-sm sm:text-base">
                    <span class="w-3 h-3 bg-amber-500 rounded-full"></span> Запланировано ТО
                </h3>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-slate-50 border-b">
                            <tr>
                                <th class="text-left p-2 font-semibold text-gray-600">Тип</th>
                                <th class="text-left p-2 font-semibold text-gray-600">Позиция</th>
                                <th class="text-left p-2 font-semibold text-gray-600">Время</th>
                                <th class="text-left p-2 font-semibold text-gray-600">Статус</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr class="border-b bg-green-50">
                                <td class="p-2 font-medium">Подкачка шин</td>
                                <td class="p-2"><span class="px-2 py-0.5 text-xs font-medium rounded-md bg-green-600 text-white">В работе</span></td>
                                <td class="p-2 text-gray-500">0 мин</td>
                                <td class="p-2 text-green-600 font-medium">Выполняется</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
            @endif
        </div> <!-- Конец вкладки Маршрут -->

        <!-- ВКЛАДКА: ТОПЛИВО -->
        <div x-show="tab === 'fuel'" x-cloak class="space-y-4 sm:space-y-6">
            <div class="bg-white rounded-xl border shadow-sm p-4 sm:p-6">
                <h3 class="font-bold text-gray-800 uppercase tracking-wider mb-4 text-sm sm:text-base">Топливо</h3>
                
                @if(isset($this->fuelStats['fuel_percent']) && is_numeric($this->fuelStats['fuel_percent'])) 
                    <div class="mb-6">
                        <div class="flex justify-between mb-1">
                            <span class="text-sm font-medium text-gray-700">Уровень топлива</span>
                            <span class="text-sm font-medium text-gray-700">{{ round($this->fuelStats['fuel_percent']) }}%</span>
                        </div>
                        <div class="w-full bg-gray-200 rounded-full h-4">
                            <div class="bg-emerald-500 h-4 rounded-full" style="width: {{ round($this->fuelStats['fuel_percent']) }}%"></div>
                        </div>
                    </div>
                @endif

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-6">
                    <div class="p-3 bg-slate-50 rounded-lg border border-slate-200">
                        <p class="text-[10px] sm:text-xs text-gray-500 uppercase font-semibold mb-1">Текущий остаток</p>
                        <p class="text-base sm:text-lg font-bold text-gray-800">{{ $truck->fuel_level }} л / {{ $truck->truckModel->fuel_capacity }} л</p>
                    </div>
                    <div class="p-3 bg-slate-50 rounded-lg border border-slate-200">
                        <p class="text-[10px] sm:text-xs text-gray-500 uppercase font-semibold mb-1">Примерно рейсов</p>
                        <p class="text-base sm:text-lg font-bold text-gray-800">{{ $this->fuelStats['estimated_trips'] ?? 0 }} <span class="text-sm font-normal text-gray-500">(ср. {{ $this->fuelStats['avg_distance'] ?? '-' }} км)</span></p>
                    </div>
                </div>

                @if($truck->status === 'fueling')
                    <div class="p-3 sm:p-4 bg-blue-50 border-l-4 border-blue-500 text-blue-700 rounded mb-4 text-sm">Обслуживание: Заправка</div>
                    <div class="flex flex-col sm:flex-row items-center gap-3">
                        <input type="number" wire:model.defer="addedFuel" placeholder="Литры" min="1" max="{{ $truck->truckModel->fuel_capacity - $truck->fuel_level }}" class="flex-1 w-full border-gray-300 rounded-md shadow-sm py-2">
                        <button wire:click="updateFuelLevel" class="px-6 py-2 bg-emerald-600 text-white rounded-md font-semibold uppercase hover:bg-emerald-700 w-full sm:w-auto text-sm">Заправлено</button>
                    </div>
                    <p class="text-xs text-gray-500 mt-2">Доступно для заправки: {{ $truck->truckModel->fuel_capacity - $truck->fuel_level }} л</p>
                @else
                    <button wire:click="requestFueling" class="w-full px-4 sm:px-6 py-3 bg-slate-200 text-slate-700 rounded-md font-semibold uppercase hover:bg-slate-300 text-sm sm:text-base">Запросить заправку</button>
                @endif
            </div>
        </div>

        <!-- ВКЛАДКА: ОГРАНИЧЕНИЯ -->
        <div x-show="tab === 'restrictions'" x-cloak class="space-y-4 sm:space-y-6">
            <div class="bg-white rounded-xl border shadow-sm p-4 sm:p-6">
                <h3 class="font-bold text-gray-800 uppercase tracking-wider mb-4 text-sm sm:text-base">Текущая грузоподъемность</h3>
                <div class="flex flex-col sm:flex-row sm:items-center gap-4 mb-6">
                    <div class="flex-1">
                        <p class="text-[10px] sm:text-xs text-gray-500 uppercase font-semibold mb-1">Паспортная</p>
                        <p class="text-base sm:text-lg font-bold text-gray-800">{{ $truck->truckModel->load_capacity }} т</p>
                    </div>
                    <div class="flex-1 flex items-center gap-2">
                        <input type="number" wire:model.defer="newLoadCapacity" min="1" max="{{ $truck->truckModel->load_capacity }}" class="border-gray-300 rounded-md shadow-sm py-2 w-24">
                        <button wire:click="updateLoadCapacity" class="px-4 py-2 bg-emerald-600 text-white rounded-md font-semibold uppercase text-sm hover:bg-emerald-700">Сохранить</button>
                    </div>
                </div>
            </div>
            <div class="bg-white rounded-xl border shadow-sm p-4 sm:p-6">
                <h3 class="font-bold text-gray-800 uppercase tracking-wider mb-4 text-sm sm:text-base">Запрет на перевозку пород</h3>
                <div class="grid grid-cols-2 md:grid-cols-3 gap-3">
                    @foreach($rocks as $rock)
                        <label class="flex items-center gap-2 p-2 border rounded-md cursor-pointer hover:bg-slate-50">
                            <input type="checkbox" wire:click="toggleRockRestriction({{ $rock['id'] }})" @if($truck->restrictions->contains('rock_id', $rock['id'])) checked @endif class="rounded text-emerald-600 focus:ring-emerald-500">
                            <span class="text-sm">{{ $rock['name_rock'] }}</span>
                        </label>
                    @endforeach
                </div>
            </div>
        </div>

        <!-- ВКЛАДКА: ОБСЛУЖИВАНИЕ -->
        <div x-show="tab === 'service'" x-cloak class="space-y-4 sm:space-y-6">
            <div class="bg-white rounded-xl border shadow-sm p-4 sm:p-6">
                <h3 class="font-bold text-gray-800 uppercase tracking-wider mb-4 text-sm sm:text-base">Показатели</h3>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-6">
                    <div class="p-3 bg-slate-50 rounded-lg border border-slate-200">
                        <p class="text-[10px] sm:text-xs text-gray-500 uppercase font-semibold mb-1">Пробег с заправки</p>
                        <p class="text-base sm:text-lg font-bold {{ $serviceStats['mileage_since_fuel'] >= $serviceStats['fueling_threshold'] ? 'text-red-600' : 'text-gray-800' }}">{{ $serviceStats['mileage_since_fuel'] }} / {{ $serviceStats['fueling_threshold'] }} км</p>
                    </div>
                    <div class="p-3 bg-slate-50 rounded-lg border border-slate-200">
                        <p class="text-[10px] sm:text-xs text-gray-500 uppercase font-semibold mb-1">Мото-часы с ТО</p>
                        <p class="text-base sm:text-lg font-bold text-gray-800">{{ $serviceStats['moto_hours_since_to'] }} ч <span class="text-sm font-normal text-gray-500">(след. {{ $serviceStats['next_to_type'] }})</span></p>
                    </div>
                </div>
            </div>

            @if(count($pendingServiceTasks) > 0)
            <div class="bg-white rounded-xl border shadow-sm p-4 sm:p-6">
                <h3 class="font-bold text-gray-800 uppercase tracking-wider mb-4 text-sm sm:text-base">Запланировано</h3>
                <div class="space-y-2">
                    @foreach($pendingServiceTasks as $task)
                        <div class="flex items-center justify-between p-2 border rounded-md">
                            <div class="text-sm">
                                <strong>{{ $task['type'] }}</strong>
                                @if($task['post_name'])<span class="ml-2 px-2 py-0.5 text-xs bg-blue-100 text-blue-700 rounded">{{ $task['post_name'] }}</span>@endif
                                @if($task['queue_position'])<span class="ml-2 px-2 py-0.5 text-xs bg-slate-200 text-slate-700 rounded">Очередь: {{ $task['queue_position'] }}</span>@endif
                            </div>
                            @if(!$task['started_at'])
                                <button wire:click="cancelServiceTask({{ $task['id'] }})" wire:confirm="Отменить заявку?" class="text-red-500 hover:text-red-700 text-xs font-semibold uppercase">Отменить</button>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
            @endif
            <div class="bg-white rounded-xl border shadow-sm p-4 sm:p-6">
                <h3 class="font-bold text-gray-800 uppercase tracking-wider mb-4 text-sm sm:text-base">Запросить обслуживание</h3>
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                    <button wire:click="requestTireInflation" class="px-4 py-3 bg-slate-200 text-slate-700 rounded-md font-semibold uppercase hover:bg-slate-300 text-sm">Подкачка шин</button>
                    <button wire:click="requestWheelTightening" class="px-4 py-3 bg-slate-200 text-slate-700 rounded-md font-semibold uppercase hover:bg-slate-300 text-sm">Обтяжка колёс</button>
                    <a href="{{ route('order.index') }}" class="px-4 py-3 bg-slate-200 text-slate-700 rounded-md font-semibold uppercase hover:bg-slate-300 text-center flex items-center justify-center text-sm">Заявки</a>
                </div>
            </div>
        </div>

        <!-- ВКЛАДКА: СТАТИСТИКА -->
        <div x-show="tab === 'stats'" x-cloak class="space-y-4 sm:space-y-6">
            <div class="bg-white rounded-xl border shadow-sm p-4 sm:p-6">
                <h3 class="font-bold text-gray-800 uppercase tracking-wider mb-4 text-sm sm:text-base">Статистика за смену ({{ $stats['shift_name'] ?? '-' }})</h3>
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                    <div class="p-3 sm:p-4 bg-emerald-50 rounded-lg border border-emerald-200 text-center">
                        <p class="text-[10px] sm:text-xs text-emerald-600 uppercase font-semibold mb-1">Рейсов</p>
                        <p class="text-xl sm:text-2xl font-bold text-emerald-700">{{ $stats['today_trips'] ?? 0 }}</p>
                    </div>
                    <div class="p-3 sm:p-4 bg-blue-50 rounded-lg border border-blue-200 text-center">
                        <p class="text-[10px] sm:text-xs text-blue-600 uppercase font-semibold mb-1">Объём</p>
                        <p class="text-xl sm:text-2xl font-bold text-blue-700">{{ number_format($stats['today_volume'] ?? 0, 1) }} т</p>
                    </div>
                    <div class="p-3 sm:p-4 bg-purple-50 rounded-lg border border-purple-200 text-center">
                        <p class="text-[10px] sm:text-xs text-purple-600 uppercase font-semibold mb-1">Ср. скорость</p>
                        <p class="text-xl sm:text-2xl font-bold text-purple-700">{{ $stats['avg_speed'] ?? '-' }} <span class="text-xs sm:text-sm">@if($stats['avg_speed']) км/ч @endif</span></p>
                    </div>
                    <div class="p-3 sm:p-4 bg-slate-50 rounded-lg border border-slate-200 text-center">
                        <p class="text-[10px] sm:text-xs text-slate-600 uppercase font-semibold mb-1">Всего рейсов</p>
                        <p class="text-xl sm:text-2xl font-bold text-slate-700">{{ $stats['total_trips'] ?? 0 }}</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- ВКЛАДКА: Заявки -->
        <div x-show="tab === 'requests'" x-cloak class="space-y-4 sm:space-y-6">
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

    </main>

    @else
    <main class="flex-1 flex items-center justify-center p-4">
        <div class="text-center py-8 text-gray-500 bg-white rounded-xl border shadow-sm p-8">
            <p class="text-base sm:text-lg">Выберите грузовик для начала работы</p>
        </div>
    </main>
    @endif

    <!-- Модальные окна (Tailwind + JS overlay) -->
    @if($showZoneModal)
    <div wire:key="zone-modal" class="fixed inset-0 z-[9999] flex items-center justify-center bg-black/70 p-4" style="display: flex;">
        <div class="bg-white rounded-xl shadow-2xl w-full max-w-lg">
            <div class="p-4 border-b flex justify-between items-center">
                <h5 class="font-bold text-gray-800 uppercase text-sm sm:text-base">Выбор зоны разгрузки</h5>
                <button wire:click="closeZoneModal" class="text-gray-500 hover:text-gray-700 text-2xl leading-none">&times;</button>
            </div>
            <div class="p-4 max-h-[60vh] overflow-y-auto">
                @forelse($availableZones as $zone)
                    <div wire:click="selectZone({{ $zone['id'] }})" class="p-3 mb-2 border rounded-md cursor-pointer hover:bg-slate-50">
                        <strong class="text-gray-800 text-sm">{{ $zone['name'] }}</strong>
                        <small class="block text-gray-500">{{ $zone['dump_name'] }} | Свободно: {{ $zone['available_capacity'] }} м³</small>
                    </div>
                @empty
                    <p class="text-gray-500">Нет доступных зон</p>
                @endforelse
            </div>
        </div>
    </div>
    @endif

    @if($showDelayModal)
    <div wire:key="delay-modal" class="fixed inset-0 z-[9999] flex items-center justify-center bg-black/70 p-4" style="display: flex;">
        <div class="bg-white rounded-xl shadow-2xl w-full max-w-lg">
            <div class="p-4 border-b flex justify-between items-center">
                <h5 class="font-bold text-gray-800 uppercase text-sm sm:text-base">Задержка</h5>
                <button wire:click="closeDelayModal" class="text-gray-500 hover:text-gray-700 text-2xl leading-none">&times;</button>
            </div>
            <div class="p-4">
                <div class="mb-4">
                    <label class="block text-xs uppercase font-semibold text-gray-500 mb-1">Причина</label>
                    <select wire:model="delayReason" class="w-full border-gray-300 rounded-md shadow-sm py-2 text-sm">
                        <option value="traffic">Пробки</option>
                        <option value="road_works">Дорожные работы</option>
                        <option value="waiting_loading">Ожидание погрузки</option>
                        <option value="waiting_unloading">Очередь на выгрузку</option>
                        <option value="weather">Погодные условия</option>
                        <option value="other">Другое</option>
                    </select>
                </div>
                <div class="mb-4">
                    <label class="block text-xs uppercase font-semibold text-gray-500 mb-1">Ожидаемое время (мин)</label>
                    <input type="number" wire:model="delayMinutes" min="1" max="120" class="w-full border-gray-300 rounded-md shadow-sm py-2 text-sm">
                </div>
            </div>
            <div class="p-4 border-t flex flex-col sm:flex-row justify-end gap-2">
                <button wire:click="closeDelayModal" class="w-full sm:w-auto px-4 py-2 bg-slate-200 text-slate-700 rounded-md font-semibold uppercase hover:bg-slate-300 text-sm">Отмена</button>
                <button wire:click="confirmDelay" class="w-full sm:w-auto px-4 py-2 bg-emerald-600 text-white rounded-md font-semibold uppercase hover:bg-emerald-700 text-sm">Подтвердить</button>
            </div>
        </div>
    </div>
    @endif

    <script>
        @if($truck)
        window.truckId = {{ $truck->id }};
        window.currentTruckId = {{ $truck->id }};
        @else
        window.currentTruckId = null;
        @endif

        let timerInterval = null;

        function formatTime(seconds, prefix = '') {
            if (seconds === null || seconds < 0) return '-';
            const hours = Math.floor(seconds / 3600);
            const min = Math.floor((seconds % 3600) / 60);
            const sec = seconds % 60;
            if (hours > 0) {
                return prefix + hours + ':' + String(min).padStart(2, '0') + ':' + String(sec).padStart(2, '0');
            }
            return prefix + min + ':' + String(sec).padStart(2, '0');
        }

        function getCurrentStatus() {
            const el = document.querySelector('[data-truck-status]');
            return el ? el.getAttribute('data-truck-status') : 'free';
        }

        function calculateTripSeconds() {
            const el = document.getElementById('trip-time');
            if (!el) return null;
            const startedAtStr = el.getAttribute('data-started');
            if (!startedAtStr) return null;
            const startedAt = new Date(startedAtStr);
            if (isNaN(startedAt.getTime())) return null;
            const now = new Date();
            let totalSeconds = Math.floor((now - startedAt) / 1000);
            const totalPause = parseInt(el.getAttribute('data-total-pause') || '0', 10);
            totalSeconds -= totalPause;
            const pauseStartedStr = el.getAttribute('data-pause-started');
            if (pauseStartedStr) {
                const pauseStarted = new Date(pauseStartedStr);
                if (!isNaN(pauseStarted.getTime())) {
                    totalSeconds -= Math.floor((now - pauseStarted) / 1000);
                }
            }
            return totalSeconds;
        }

        function calculateFrozenSeconds() {
            const el = document.getElementById('trip-time');
            if (!el) return null;
            const startedAtStr = el.getAttribute('data-started');
            const pauseStartedStr = el.getAttribute('data-pause-started');
            if (!startedAtStr || !pauseStartedStr) return null;
            const startedAt = new Date(startedAtStr);
            const pauseStarted = new Date(pauseStartedStr);
            if (isNaN(startedAt.getTime()) || isNaN(pauseStarted.getTime())) return null;
            let frozenSeconds = Math.floor((pauseStarted - startedAt) / 1000);
            const totalPause = parseInt(el.getAttribute('data-total-pause') || '0', 10);
            frozenSeconds -= totalPause;
            return frozenSeconds;
        }

        function updateTimer() {
            const el = document.getElementById('trip-time');
            if (!el) return;
            const status = getCurrentStatus();
            const pauseType = el.getAttribute('data-pause-type');
            let seconds;
            let prefix = '';

            if (status === 'breakdown' || status === 'delayed') {
                seconds = calculateFrozenSeconds();
                prefix = '⏸ ';
            } else if (status === 'free') {
                el.innerText = '-';
                return;
            } else {
                seconds = calculateTripSeconds();
            }
            el.innerText = formatTime(seconds, prefix);
        }

        function startTimer() {
            const el = document.getElementById('trip-time');
            if (!el) return;
            if (timerInterval) {
                clearInterval(timerInterval);
                timerInterval = null;
            }
            const status = getCurrentStatus();
            const started = el.getAttribute('data-started');
            if (status === 'free' && !started) {
                el.innerText = '-';
                return;
            }
            updateTimer();
            timerInterval = setInterval(updateTimer, 1000);
        }

        let echoChannels = [];

        function subscribeToTruckChannels(truckId) {
            echoChannels.forEach(ch => {
                if (window.Echo) window.Echo.leave(ch);
            });
            echoChannels = [];
            if (!truckId || !window.Echo) return;

            window.Echo.private(`driver.${truckId}`)
                .listen('.route.updated', (eventData) => {
                    Livewire.dispatch('route-updated', { data: { ...eventData, truck_id: truckId } });
                })
                .listen('.zone.changed', (eventData) => {
                    Livewire.dispatch('zone-changed');
                });
            echoChannels.push(`driver.${truckId}`);

            window.Echo.private(`truck.${truckId}`)
                .listen('.loading.completed', (eventData) => {
                    Livewire.dispatch('loading-completed', { data: { ...eventData, truck_id: truckId } });
                });
            echoChannels.push(`truck.${truckId}`);
        }

        document.addEventListener('livewire:init', () => {
            startTimer();
            @if($truck)
            subscribeToTruckChannels({{ $truck->id }});
            @endif

            Livewire.on('notify', (data) => {
                const event = Array.isArray(data) ? data[0] : data;
                if (!event || !event.message) return;

                const container = document.getElementById('global-toast-container');
                const toast = document.createElement('div');

                const bgClass = event.type === 'success' ? 'bg-emerald-500' :
                               event.type === 'error' ? 'bg-red-500' :
                               event.type === 'warning' ? 'bg-amber-500' :
                               'bg-blue-500';

                toast.className = `${bgClass} text-white px-4 py-2 rounded-md shadow-lg mb-2 flex justify-between items-center text-sm max-w-xs`;
                toast.innerHTML = `
                    <span>${event.message}</span>
                    <button onclick="this.parentElement.remove()" class="ml-4 text-xl leading-none">&times;</button>
                `;
                container.appendChild(toast);

                setTimeout(() => {
                    toast.style.transition = 'opacity 0.5s';
                    toast.style.opacity = '0';
                    setTimeout(() => toast.remove(), 500);
                }, 5000);
            });

            Livewire.on('restart-timer', () => setTimeout(startTimer, 50));
            Livewire.on('set-cookie', (data) => {
                const event = Array.isArray(data) ? data[0] : data;
                if (!event || !event.name) return;
                const date = new Date();
                date.setTime(date.getTime() + (event.days * 24 * 60 * 60 * 1000));
                document.cookie = `${event.name}=${event.value};expires=${date.toUTCString()};path=/`;
            });
            Livewire.on('truck-selected', (data) => {
                const event = Array.isArray(data) ? data[0] : data;
                if (event && event.truck_id) subscribeToTruckChannels(event.truck_id);
            });
        });
    </script>
</div>