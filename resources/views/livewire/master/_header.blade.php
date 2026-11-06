<div class="min-h-screen flex flex-col bg-slate-50" x-data="{}">
    <!-- Dark Header -->
    <header class="bg-slate-900 text-white shadow-lg mb-4 rounded-xl">
        <div class="px-4 py-3 flex items-center justify-between">
            <h1 class="text-lg font-bold uppercase tracking-wider">Панель Мастера</h1>

            <div class="flex items-center gap-4">
                {{-- AI Алерты — виджет (как у диспетчера) --}}
                @php $newAlertsCount = $this->new_alerts_count; @endphp
                <div x-data="{ showAlerts: false, openFilter: null }" class="relative">
                    {{-- relative ВАЖЕН на кнопке, чтобы точка -top-1 -right-1
                         позиционировалась относительно кнопки, а не относительно
                         родительского div --}}
                    <button @click="showAlerts = !showAlerts"
                            class="relative flex items-center gap-1.5 px-3 py-1.5 {{ $newAlertsCount > 0 ? 'bg-red-600 hover:bg-red-700' : 'bg-slate-700 hover:bg-slate-600' }} text-white rounded-md text-xs font-semibold uppercase transition">
                        <i class="fas fa-bell"></i>
                        <span>{{ $newAlertsCount }}</span>
                        @if($newAlertsCount > 0)
                            <span class="absolute -top-1 -right-1 w-3 h-3 bg-amber-400 rounded-full animate-pulse"></span>
                        @endif
                    </button>

                    {{-- Выпадающий список алертов --}}
                    {{-- Ширина: растягивается по доступной ширине строки, но не больше 480px
                              и не больше ширины экрана (с запасом 2rem по бокам). Минимум 320px. --}}
                    <div x-show="showAlerts" x-cloak x-transition
                         @click.outside="showAlerts = false"
                         class="absolute right-0 top-full mt-1 min-w-[320px] max-w-[480px] w-[calc(100vw-2rem)] bg-white rounded-xl shadow-2xl border border-gray-200 z-50 max-h-[85vh] overflow-y-auto">

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
                            $filterCounts = [
                                'type'     => count($this->alertFilters['type'] ?? []),
                                'severity' => count($this->alertFilters['severity'] ?? []),
                                'entity'   => count($this->alertFilters['entity'] ?? []),
                                'status'   => count($this->alertFilters['status'] ?? []),
                            ];
                        @endphp
                        <div class="bg-slate-50 border-b border-gray-200 z-10">
                            {{-- Заголовок панели фильтров --}}
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

                            {{-- Тело фильтров --}}
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
            <button wire:click="setTab('dumps')"
                class="px-4 py-2 rounded-md font-semibold uppercase @if($activeTab === 'dumps') bg-emerald-600 text-white @else text-gray-600 hover:bg-gray-100 @endif">
                Перегрузки
            </button>
            <button wire:click="setTab('dashboard')"
                class="px-4 py-2 rounded-md font-semibold uppercase @if($activeTab === 'dashboard') bg-emerald-600 text-white @else text-gray-600 hover:bg-gray-100 @endif">
                Обзор
            </button>
            <button wire:click="setTab('equipment')"
                class="px-4 py-2 rounded-md font-semibold uppercase @if($activeTab === 'equipment') bg-emerald-600 text-white @else text-gray-600 hover:bg-gray-100 @endif">
                Оборудование
            </button>
            <button wire:click="setTab('maintenance')"
                class="px-4 py-2 rounded-md font-semibold uppercase @if($activeTab === 'maintenance') bg-emerald-600 text-white @else text-gray-600 hover:bg-gray-100 @endif">
                Обслуживание
            </button>
            <button wire:click="setTab('requests')"
                class="px-4 py-2 rounded-md font-semibold uppercase @if($activeTab === 'requests') bg-emerald-600 text-white @else text-gray-600 hover:bg-gray-100 @endif">
                Заявки
            </button>
            <button wire:click="setTab('miners')"
            class="px-4 py-2 rounded-md font-semibold uppercase @if($activeTab === 'miners') bg-emerald-600 text-white @else text-gray-600 hover:bg-gray-100 @endif">
                Забои
            </button>
            <button wire:click="setTab('rocks')"
                class="px-4 py-2 rounded-md font-semibold uppercase @if($activeTab === 'rocks') bg-emerald-600 text-white @else text-gray-600 hover:bg-gray-100 @endif">
                Породы
            </button>
        </div>
    </div>

        <!-- ВКЛАДКА: Обзор -->
