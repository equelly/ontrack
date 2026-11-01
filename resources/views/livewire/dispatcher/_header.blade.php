    {{-- Глобальный CSS: предотвращает дёргание кнопок при Livewire-загрузке --}}
    <style>
        /* Все кнопки: nowrap — текст не переносится, кнопка не меняет размер при загрузке */
        .dispatcher-panel-wrapper button { white-space: nowrap; }
        /* Кнопки в состоянии disabled (идёт загрузка): фиксируем курсор */
        .dispatcher-panel-wrapper button:disabled { cursor: not-allowed; }
        /* Иконные кнопки с wire:loading: фиксированная ширина чтобы символ→спиннер не дёргал */
        .dispatcher-panel-wrapper button:has(span[wire\:loading]) { min-width: 32px; }
    </style>
    
    <!-- Глобальный индикатор загрузки — тонкая полоска наверху при любом Livewire-запросе -->
    <div wire:loading.delay class="fixed top-0 left-0 right-0 z-[99999] h-1 bg-blue-600 animate-pulse"></div>
    
    <!-- Toast контейнер для уведомлений -->
    <div id="global-toast-container" class="position-fixed top-0 end-0 p-3" style="z-index: 9999;"></div>
    
    <!-- ТЕМНАЯ ШАПКА СО СТАТИСТИКОЙ -->
    <header class="bg-slate-900 text-white shadow-lg mb-2 rounded-xl">
        {{-- СТРОКА 1: ТОЛЬКО СТАТИСТИКА --}}
        <div class="px-4 py-3 flex flex-wrap items-center gap-x-6 gap-y-3">
            @php $queueStats = $this->queue_stats; @endphp
            <!-- Статистика — только ключевая -->
            <div class="flex flex-wrap items-center gap-x-5 gap-y-2 text-sm">
                {{-- АВТО --}}
                <div class="flex items-center gap-2">
                    <i class="fas fa-truck text-blue-400"></i>
                    <div><span class="text-gray-400 block text-[10px] uppercase">Авто в работе</span><strong class="text-blue-400">{{ $this->working_trucks_count }}</strong></div>
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
                    <i class="fas fa-tools text-amber-400"></i>
                    <div class="text-[10px] text-gray-400 uppercase leading-tight">
                        Очереди обсл.<br>
                        <span class="text-gray-200 normal-case">ТО: {{ $queueStats['maintenance']['waiting'] }}/{{ $queueStats['maintenance']['in_progress'] }}, Запр: {{ $queueStats['fueling']['waiting'] }}/{{ $queueStats['fueling']['in_progress'] }}</span>
                    </div>
                </div>
                {{-- ЗАБОИ --}}
                <div class="flex items-center gap-2 border-l border-slate-700 pl-5">
                    <i class="fas fa-mountain text-emerald-400"></i>
                    <div><span class="text-gray-400 block text-[10px] uppercase">Забои в работе</span><strong class="text-emerald-400">{{ $this->active_miners_count }}</strong></div>
                </div>
                <div class="flex items-center gap-2">
                    <i class="fas fa-pause-circle text-amber-400"></i>
                    <div><span class="text-gray-400 block text-[10px] uppercase">Задержки забоев</span><strong class="text-amber-400">{{ $this->miner_delayed_count }}</strong></div>
                </div>
                <div class="flex items-center gap-2">
                    <i class="fas fa-mountain text-red-400"></i>
                    <div><span class="text-gray-400 block text-[10px] uppercase">Поломки забоев</span><strong class="text-red-400">{{ $this->miner_breakdown_count }}</strong></div>
                </div>
            </div>
        </div>

        {{-- СТРОКА 2: АЛЕРТЫ СЛЕВА + КНОПКИ СПРАВА --}}
        <div class="px-4 py-2 border-t border-slate-700 flex flex-wrap items-center justify-between gap-3">
            {{-- AI Алерты — виджет (слева) --}}
            @php $newAlertsCount = $this->new_alerts_count; @endphp
            @if($newAlertsCount > 0)
            <div x-data="{ showAlerts: false, openFilter: null }" class="relative">
                <button @click="showAlerts = !showAlerts"
                        class="relative flex items-center gap-1.5 px-3 py-1.5 bg-red-600 hover:bg-red-700 text-white rounded-md text-xs font-semibold uppercase transition">
                    <i class="fas fa-bell"></i>
                    <span>{{ $newAlertsCount }}</span>
                    <span class="absolute -top-1 -right-1 w-3 h-3 bg-amber-400 rounded-full animate-pulse"></span>
                </button>

                {{-- Выпадающий список алертов --}}
                {{-- ВАЖНО: left-0 (а не right-0), т.к. алерты слева — иначе блок уходит за левый край экрана.
                          Ширина увеличена до 480-800px (много инфы в рекомендациях). --}}
                <div x-show="showAlerts" x-cloak x-transition
                     @click.outside="showAlerts = false"
                     class="absolute left-0 top-full mt-1 min-w-[480px] max-w-[800px] w-[calc(100vw-2rem)] bg-white rounded-xl shadow-2xl border border-gray-200 z-50 max-h-[85vh] overflow-y-auto">

                    <div class="sticky top-0 bg-slate-800 text-white px-4 py-2.5 flex justify-between items-center rounded-t-xl z-20">
                        <div class="flex items-center gap-2">
                            <i class="fas fa-bell text-amber-400"></i>
                            <span class="font-semibold text-sm uppercase">AI Алерты</span>
                            @if($newAlertsCount > 0)
                                <span class="px-2 py-0.5 bg-red-500 text-white text-xs font-bold rounded-full">{{ $newAlertsCount }} новых</span>
                            @endif
                        </div>
                        <button wire:click="acknowledgeAllAlerts"
                                wire:loading.attr="disabled"
                                class="text-xs text-emerald-400 hover:text-emerald-300 disabled:opacity-50">
                            <span wire:loading.remove>Подтвердить все</span>
                            <span wire:loading>...</span>
                        </button>
                    </div>

                    {{-- Панель фильтров — аккордеон --}}
                    @php
                        $filterOptions = $this->alert_filter_options;
                        $filtersActive = $this->alert_filters_active;
                        // Считаем активные фильтры в каждой категории для бейджа
                        $filterCounts = [
                            'type'     => count($this->alertFilters['type'] ?? []),
                            'severity' => count($this->alertFilters['severity'] ?? []),
                            'entity'   => count($this->alertFilters['entity'] ?? []),
                            'status'   => count($this->alertFilters['status'] ?? []),
                        ];
                    @endphp
                    <div class="bg-slate-50 border-b border-gray-200 z-10">
                        {{-- Заголовок панели фильтров: Показать/Скрыть + Сбросить --}}
                        <div class="flex items-center justify-between px-3 py-2">
                            <button @click="openFilter = (openFilter === 'all' ? null : 'all')"
                                    class="text-xs font-semibold text-gray-700 hover:text-emerald-600 flex items-center gap-1.5">
                                <i class="fas fa-filter"></i>
                                <span>Фильтры</span>
                                @if($filtersActive)
                                    <span class="px-1.5 py-0.5 bg-emerald-100 text-emerald-700 rounded text-[10px]">
                                        {{ array_sum($filterCounts) }} акт.
                                    </span>
                                @endif
                                <i class="fas fa-chevron-down text-[10px] transition-transform"
                                   :class="openFilter === 'all' ? 'rotate-180' : ''"></i>
                            </button>
                            <div class="flex items-center gap-2">
                                <span class="text-[10px] text-gray-500">
                                    Показано: <strong class="text-gray-700">{{ $this->recent_alerts->count() }}</strong>
                                </span>
                                @if($filtersActive)
                                    <button wire:click="resetAlertFilters"
                                            class="px-2 py-0.5 text-[11px] rounded-full bg-red-100 text-red-700 hover:bg-red-200 transition">
                                        <i class="fas fa-times mr-0.5"></i>Сбросить
                                    </button>
                                @endif
                            </div>
                        </div>

                        {{-- Тело фильтров — раскрывается аккордеоном --}}
                        <div x-show="openFilter === 'all'" x-cloak x-collapse class="px-3 pb-2.5 space-y-1.5">

                            {{-- Категория: Тип --}}
                            <div class="flex items-center gap-1.5 flex-wrap">
                                <span class="text-[10px] uppercase font-semibold text-gray-500 w-12">Тип:</span>
                                @foreach($filterOptions['type'] as $opt)
                                    @php $isActive = $this->isAlertFilterActive('type', $opt['value']); @endphp
                                    <button wire:click="toggleAlertFilter('type', '{{ $opt['value'] }}')"
                                            class="px-2 py-0.5 text-[11px] rounded-full transition {{ $isActive ? 'bg-emerald-600 text-white' : 'bg-white border border-gray-300 text-gray-600 hover:bg-gray-100' }}">
                                        <i class="fas {{ $opt['icon'] }} mr-0.5"></i>{{ $opt['label'] }}
                                    </button>
                                @endforeach
                            </div>

                            {{-- Категория: Severity --}}
                            <div class="flex items-center gap-1.5 flex-wrap">
                                <span class="text-[10px] uppercase font-semibold text-gray-500 w-12">Важн:</span>
                                @foreach($filterOptions['severity'] as $opt)
                                    @php $isActive = $this->isAlertFilterActive('severity', $opt['value']); @endphp
                                    <button wire:click="toggleAlertFilter('severity', '{{ $opt['value'] }}')"
                                            class="px-2 py-0.5 text-[11px] rounded-full transition {{ $isActive ? 'bg-emerald-600 text-white' : 'bg-white border border-gray-300 text-gray-600 hover:bg-gray-100' }}">
                                        <i class="fas {{ $opt['icon'] }} mr-0.5"></i>{{ $opt['label'] }}
                                    </button>
                                @endforeach
                            </div>

                            {{-- Категория: Сущность --}}
                            <div class="flex items-center gap-1.5 flex-wrap">
                                <span class="text-[10px] uppercase font-semibold text-gray-500 w-12">Сущн:</span>
                                @foreach($filterOptions['entity'] as $opt)
                                    @php $isActive = $this->isAlertFilterActive('entity', $opt['value']); @endphp
                                    <button wire:click="toggleAlertFilter('entity', '{{ $opt['value'] }}')"
                                            class="px-2 py-0.5 text-[11px] rounded-full transition {{ $isActive ? 'bg-emerald-600 text-white' : 'bg-white border border-gray-300 text-gray-600 hover:bg-gray-100' }}">
                                        <i class="fas {{ $opt['icon'] }} mr-0.5"></i>{{ $opt['label'] }}
                                    </button>
                                @endforeach
                            </div>

                            {{-- Категория: Статус --}}
                            <div class="flex items-center gap-1.5 flex-wrap">
                                <span class="text-[10px] uppercase font-semibold text-gray-500 w-12">Стат:</span>
                                @foreach($filterOptions['status'] as $opt)
                                    @php $isActive = $this->isAlertFilterActive('status', $opt['value']); @endphp
                                    <button wire:click="toggleAlertFilter('status', '{{ $opt['value'] }}')"
                                            class="px-2 py-0.5 text-[11px] rounded-full transition {{ $isActive ? 'bg-emerald-600 text-white' : 'bg-white border border-gray-300 text-gray-600 hover:bg-gray-100' }}">
                                        <i class="fas {{ $opt['icon'] }} mr-0.5"></i>{{ $opt['label'] }}
                                    </button>
                                @endforeach
                            </div>
                        </div>
                    </div>

                    <div class="divide-y divide-gray-100">
                        @php $alerts = $this->recent_alerts; @endphp
                        @foreach($alerts as $alert)
                            @include('components.ai-alert-card', ['alert' => $alert])
                        @endforeach
                        @if($alerts->isEmpty())
                            <div class="p-8 text-center text-gray-400 text-sm">
                                <i class="fas {{ $filtersActive ? 'fa-filter' : 'fa-check-circle' }} text-3xl {{ $filtersActive ? 'text-gray-400' : 'text-emerald-400' }} mb-2"></i>
                                <p>{{ $filtersActive ? 'Нет алертов под фильтрами' : 'Нет активных алертов' }}</p>
                                <p class="text-xs mt-1">{{ $filtersActive ? 'Измените фильтры или сбросьте их' : 'Система работает нормально' }}</p>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
            @endif

            <!-- ПРАВЫЙ БЛОК: Планировать смену, Обновить, Пользователь, Выход -->
            <div class="flex items-center gap-4">
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
        <!-- Самосвалы -->
