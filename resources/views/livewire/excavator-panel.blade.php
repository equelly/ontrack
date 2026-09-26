<div class="min-h-screen flex flex-col bg-slate-50" x-data="{ tab: 'face' }">
    <!-- ТЕМНАЯ ШАПКА С ВЫБОРОМ ЭКСКАВАТОРА -->
    <header class="bg-slate-900 text-white shadow-lg mb-4 rounded-xl">
        <div class="px-4 py-3 flex items-center justify-between">
            <h1 class="text-lg font-bold uppercase tracking-wider">Панель экскаваторщика</h1> 
            <!-- ПРАВЫЙ БЛОК: Обновить, Пользователь, Выход -->
            <div class="ml-auto flex items-center gap-4">
                <div class="flex items-center gap-2 w-full">
                    <select wire:model.live="selectedMinerId" class="bg-slate-800 border-slate-700 text-white focus:border-emerald-500 focus:ring-emerald-500 rounded-md shadow-sm py-2 pl-3 pr-8 text-sm flex-1 min-w-0">
                        <option value="">-- Выберите экскаватор --</option>
                        @foreach($miners as $m)
                            <option value="{{ $m->id }}">{{ $m->name_miner }}</option>
                        @endforeach
                    </select>
                <button wire:click="selectMiner" wire:loading.attr="disabled" class="inline-flex items-center justify-center px-4 py-2 bg-emerald-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-emerald-700 active:bg-emerald-900 transition ease-in-out duration-150 whitespace-nowrap">
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

    @if($miner)
    <!-- Навигация (Tabs) -->
    <nav class="bg-white border-b shadow-sm sticky top-0 z-10">
        <div class="max-w-7xl mx-auto px-2 sm:px-4 flex overflow-x-auto gap-1 sm:gap-2 py-2 justify-around sm:justify-start">
            <button @click="tab='face'" :class="tab === 'face' ? 'bg-emerald-600 text-white shadow-sm' : 'text-gray-600 hover:bg-gray-100'" class="px-3 sm:px-4 py-2 rounded-lg text-sm font-medium whitespace-nowrap transition-all flex items-center gap-1.5">
                <span></span> <span class="hidden sm:inline">Забой</span>
            </button>
            <button @click="tab='stats'" :class="tab === 'stats' ? 'bg-emerald-600 text-white shadow-sm' : 'text-gray-600 hover:bg-gray-100'" class="px-3 sm:px-4 py-2 rounded-lg text-sm font-medium whitespace-nowrap transition-all flex items-center gap-1.5">
                <span>📊</span> <span class="hidden sm:inline">Статистика</span>
            </button>
            <button @click="tab='requests'"
                :class="{ 'bg-emerald-600 text-white': tab === 'requests', 'text-gray-600 hover:bg-gray-100': tab !== 'requests' }"
                class="px-4 py-2 rounded-md font-semibold uppercase">
                <span>📝</span> <span class="hidden sm:inline">Заявки</span>
            </button>
        </div>
    </nav>

    <!-- Основной контент -->
    <main class="flex-1 max-w-7xl mx-auto w-full px-3 sm:px-4 py-4 sm:py-6">
        
        @if($miner->isBreakdown())
        <div class="p-4 bg-red-50 border-l-4 border-red-500 text-red-700 rounded mb-4 text-sm">
            <strong>Внимание!</strong> Грузовики будут перенаправлены на другие забои.
        </div>
        @elseif($miner->isPlannedDelay())
        <div class="p-4 bg-amber-50 border-l-4 border-amber-500 text-amber-700 rounded mb-4 text-sm">
            Грузовики в пути доедут до забоя, новые назначаться не будут.
        </div>
        @endif

        <!-- ВКЛАДКА: ЗАБОЙ -->
        <div x-show="tab === 'face'" class="space-y-4 sm:space-y-6">
            
            <!-- Настройки забоя (Порода и Норма) -->
            <div class="bg-white rounded-xl border shadow-sm p-4 sm:p-6 {{ !$miner->current_rock_id ? 'border-amber-400 border-2 bg-amber-50' : '' }}">
                @if(!$miner->current_rock_id)
                    <div class="mb-3 px-3 py-2 bg-amber-100 border border-amber-300 rounded-md text-amber-800 text-sm flex items-center gap-2">
                        <i class="fas fa-exclamation-triangle"></i>
                        <span><strong>Порода не выбрана!</strong> Самосвалы не смогут получить маршрут в этот забой. Выберите породу и нажмите «Сменить».</span>
                    </div>
                @endif
                <div class="flex flex-col sm:flex-row gap-4">
                    <div class="flex-1 flex items-center gap-2">
                        <select wire:model.live="selectedRockId" class="flex-1 border-gray-300 focus:border-emerald-500 focus:ring-emerald-500 rounded-md shadow-sm py-2 text-sm {{ !$miner->current_rock_id ? 'border-amber-400' : '' }}">
                            <option value="">-- Выберите породу --</option>
                            @foreach($rocks as $rock)
                                <option value="{{ $rock->id }}">{{ $rock->name_rock }}</option>
                            @endforeach
                        </select>
                        <button wire:click="setRock" wire:loading.attr="disabled" class="px-4 py-2 bg-emerald-600 text-white rounded-md font-semibold uppercase text-xs hover:bg-emerald-700 whitespace-nowrap">Сменить</button>
                    </div>
                    <div class="flex-1 flex items-center gap-2">
                        <input type="number" wire:model.defer="targetLoadTime" class="w-24 border-gray-300 rounded-md shadow-sm py-2 text-sm" min="25" max="3600">
                        <span class="text-xs text-gray-500">сек (Норма)</span>
                        <button wire:click="setTargetLoadTime" wire:loading.attr="disabled" class="px-4 py-2 bg-slate-200 text-slate-700 rounded-md font-semibold uppercase text-xs hover:bg-slate-300 whitespace-nowrap">OK</button>
                    </div>
                </div>
                @if($miner->current_rock_id)
                    <div class="mt-3 text-xs text-emerald-700 flex items-center gap-1">
                        <i class="fas fa-check-circle"></i>
                        Текущая порода: <strong>{{ $miner->currentRock?->name_rock }}</strong>
                    </div>
                @endif
            </div>

            <!-- Статус забоя -->
            <div class="bg-white rounded-xl border shadow-sm p-4 sm:p-6">
                <div class="flex items-center gap-2 mb-4">
                    <span class="text-xs sm:text-sm font-medium text-gray-500 uppercase">Статус забоя</span>
                    @php
                        $statusColors = [
                            'active' => 'bg-emerald-500',
                            'breakdown' => 'bg-red-500',
                            'maintenance' => 'bg-amber-400',
                            'face_dismantling' => 'bg-cyan-400',
                            'access_setup' => 'bg-slate-500',
                            'relocation' => 'bg-blue-500',
                        ];
                    @endphp
                    <span class="px-3 py-1 text-xs sm:text-sm font-semibold rounded-md text-white {{ $statusColors[$miner->status] ?? 'bg-slate-500' }}">{{ $miner->getStatusLabel() }}</span>
                    @if($miner->isDelayed() && $miner->status_changed_at)
                        <span class="text-xs text-gray-400">({{ $miner->getStatusDurationMinutes() }} мин)</span>
                    @endif
                </div>
                <div class="flex flex-wrap gap-2">
                    @if($miner->status !== 'active')
                        <button wire:click="setStatus('active')" class="px-4 py-2 rounded-md text-white text-xs font-semibold uppercase bg-emerald-600 hover:bg-emerald-700">В работе</button>
                    @endif
                    @if($miner->status !== 'breakdown')
                        <button wire:click="setStatus('breakdown')" class="px-4 py-2 rounded-md text-white text-xs font-semibold uppercase bg-red-600 hover:bg-red-700">Поломка</button>
                    @endif
                    @if($miner->status !== 'maintenance')
                        <button wire:click="setStatus('maintenance')" class="px-4 py-2 rounded-md text-gray-800 text-xs font-semibold uppercase bg-amber-400 hover:bg-amber-500">Обслуживание</button>
                    @endif
                    @if($miner->status !== 'face_dismantling')
                        <button wire:click="setStatus('face_dismantling')" class="px-4 py-2 rounded-md text-white text-xs font-semibold uppercase bg-cyan-400 hover:bg-cyan-500">Разбор забоя</button>
                    @endif
                    @if($miner->status !== 'access_setup')
                        <button wire:click="setStatus('access_setup')" class="px-4 py-2 rounded-md text-white text-xs font-semibold uppercase bg-slate-500 hover:bg-slate-600">Устр. подъезда</button>
                    @endif
                    @if($miner->status !== 'relocation')
                        <button wire:click="setStatus('relocation')" class="px-4 py-2 rounded-md text-white text-xs font-semibold uppercase bg-blue-600 hover:bg-blue-700">Переезд</button>
                    @endif
                </div>
            </div>

            <!-- Показатели производительности -->
            <div class="grid grid-cols-2 md:grid-cols-4 gap-3 sm:gap-4">
                <div class="bg-white p-4 rounded-xl border shadow-sm text-center">
                    <p class="text-[10px] sm:text-xs text-gray-500 uppercase font-semibold mb-1">К забою</p>
                    <p class="text-xl sm:text-2xl font-bold text-blue-600">{{ $productivityStats['current_trucks'] ?? 0 }}</p>
                </div>
                <div class="bg-white p-4 rounded-xl border shadow-sm text-center">
                    <p class="text-[10px] sm:text-xs text-gray-500 uppercase font-semibold mb-1">Ожидают</p>
                    <p class="text-xl sm:text-2xl font-bold text-amber-500">{{ $productivityStats['waiting_trucks'] ?? 0 }}</p>
                </div>
                <div class="bg-white p-4 rounded-xl border shadow-sm text-center">
                    <p class="text-[10px] sm:text-xs text-gray-500 uppercase font-semibold mb-1">На погрузке</p>
                    <p class="text-xl sm:text-2xl font-bold text-emerald-600">{{ $productivityStats['loading_trucks'] ?? 0 }}</p>
                </div>
                <div class="bg-white p-4 rounded-xl border shadow-sm text-center">
                    @php
                        $targetForCompare = ($productivityStats['target_load_time'] ?? 0) / 60;
                        $avgLoadTime = $productivityStats['avg_load_time'] ?? 999;
                    @endphp
                    <p class="text-[10px] sm:text-xs text-gray-500 uppercase font-semibold mb-1">Ср. погрузка</p>
                    <p class="text-xl sm:text-2xl font-bold {{ $avgLoadTime > $targetForCompare && $targetForCompare > 0 ? 'text-red-600' : 'text-emerald-600' }}">{{ $avgLoadTime == 999 ? '-' : $avgLoadTime }} <span class="text-sm font-normal text-gray-400">мин</span></p>
                </div>
            </div>

            <!-- Таблица самосвалов -->
            <div class="bg-white rounded-xl border shadow-sm overflow-hidden">
                <div class="p-4 border-b flex justify-between items-center">
                    <h3 class="font-bold text-gray-800 uppercase tracking-wider text-sm sm:text-base">Самосвалы в направлении забоя</h3>
                    <button wire:click="loadMinerData" class="text-gray-500 hover:text-emerald-600">
                        <svg wire:loading.class="animate-spin" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg>
                    </button>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-slate-50 border-b">
                            <tr>
                                <th class="text-left p-3 font-semibold text-gray-600">Номер</th>
                                <th class="text-left p-3 font-semibold text-gray-600">Действия</th>
                                <th class="text-left p-3 font-semibold text-gray-600">Груз.</th>
                                <th class="text-left p-3 font-semibold text-gray-600">Перегрузка / Зона</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($trucks as $truck)
                                @php $trip = $truck->trips->first(); @endphp
                                <tr class="border-b hover:bg-slate-50 {{ $truck->status === 'loading' ? 'bg-amber-50' : '' }}">
                                    <td class="p-3 font-bold text-gray-800">{{ $truck->number }}</td>
                                    <td class="p-3">
                                        @if($truck->status === 'loading')
                                            <div class="flex items-center gap-2">
                                                <input type="number" wire:model="volumes.{{ $truck->id }}" class="w-20 border-gray-300 rounded-md shadow-sm py-1 text-sm" min="0" step="0.1">
                                                <span class="text-gray-500 text-xs">т</span>
                                                <button wire:click="completeLoading({{ $truck->id }})" class="px-3 py-1 bg-emerald-600 text-white rounded-md text-xs font-semibold uppercase hover:bg-emerald-700">
                                                    <span wire:loading.remove>Загружен</span>
                                                    <span wire:loading class="animate-spin">⏳</span>
                                                </button>
                                            </div>
                                        @elseif($truck->status === 'to_miner')
                                            <button wire:click="confirmArrival({{ $truck->id }})" class="px-3 py-1 bg-emerald-600 text-white rounded-md text-xs font-semibold uppercase hover:bg-emerald-700 w-full sm:w-auto">
                                                <span wire:loading.remove>Прибыл</span>
                                                <span wire:loading class="animate-spin">⏳</span>
                                            </button>
                                        @elseif($truck->status === 'waiting_loading')
                                            <button wire:click="confirmArrival({{ $truck->id }})" class="px-3 py-1 bg-amber-500 text-white rounded-md text-xs font-semibold uppercase hover:bg-amber-600 w-full sm:w-auto">
                                                <span wire:loading.remove>Начать погрузку</span>
                                                <span wire:loading class="animate-spin">⏳</span>
                                            </button>
                                        @else
                                            <span class="text-gray-400">—</span>
                                        @endif
                                    </td>
                                    <td class="p-3 text-gray-600">{{ $truck->load_capacity }} т</td>
                                    <td class="p-3 text-gray-600">
                                        @if($trip)
                                            {{ $trip->dump?->name_dump ?? $trip->miningOrder?->dump?->name_dump ?? '-' }}
                                            @if($trip->zone)
                                                / <span class="text-emerald-600 font-semibold">{{ $trip->zone->name_zone }}</span>
                                            @elseif($trip->miningOrder?->zone)
                                                / <span class="text-emerald-600">{{ $trip->miningOrder->zone->name_zone }}</span>
                                            @else
                                                / <span class="text-amber-500">Не назначена</span>
                                            @endif
                                        @else
                                            <span class="text-gray-400">—</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="p-8 text-center text-gray-500">Нет самосвалов в направлении забоя</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div> <!-- Конец вкладки Забой -->

        <!-- ВКЛАДКА: СТАТИСТИКА -->
        <div x-show="tab === 'stats'" class="space-y-4 sm:space-y-6" style="display: none;">
            <div class="bg-white rounded-xl border shadow-sm p-4 sm:p-6">
                <h3 class="font-bold text-gray-800 uppercase tracking-wider mb-4 text-sm sm:text-base">Статистика за смену ({{ $stats['shift_name'] ?? '-' }})</h3>
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                    <div class="p-3 sm:p-4 bg-emerald-50 rounded-lg border border-emerald-200 text-center">
                        <p class="text-[10px] sm:text-xs text-emerald-600 uppercase font-semibold mb-1">Рейсов</p>
                        <p class="text-xl sm:text-2xl font-bold text-emerald-700">{{ $stats['trips_count'] ?? 0 }}</p>
                    </div>
                    <div class="p-3 sm:p-4 bg-blue-50 rounded-lg border border-blue-200 text-center">
                        <p class="text-[10px] sm:text-xs text-blue-600 uppercase font-semibold mb-1">Добыто</p>
                        <p class="text-xl sm:text-2xl font-bold text-blue-700">{{ number_format($stats['total_volume'] ?? 0, 1) }} т</p>
                    </div>
                    <div class="p-3 sm:p-4 bg-purple-50 rounded-lg border border-purple-200 text-center">
                        <p class="text-[10px] sm:text-xs text-purple-600 uppercase font-semibold mb-1">Ср. время погрузки</p>
                        <p class="text-xl sm:text-2xl font-bold text-purple-700">{{ $stats['avg_loading_time'] ?? '-' }} мин</p>
                    </div>
                    <div class="p-3 sm:p-4 bg-slate-50 rounded-lg border border-slate-200 text-center">
                        <p class="text-[10px] sm:text-xs text-slate-600 uppercase font-semibold mb-1">Начало смены</p>
                        <p class="text-xl sm:text-2xl font-bold text-slate-700">{{ $stats['shift_start'] ?? '-' }}</p>
                    </div>
                </div>
            </div>
        </div>

    </main>

    @else
    <main class="flex-1 flex items-center justify-center p-4">
        <div class="text-center py-8 text-gray-500 bg-white rounded-xl border shadow-sm p-8">
            <p class="text-base sm:text-lg">Выберите экскаватор для начала работы</p>
        </div>
    </main>
    @endif
            <!-- ВКЛАДКА: Заявки -->
        <div x-show="tab === 'requests'" x-cloak class="mt-4 space-y-6">
            <!-- Панель фильтрации -->
            <div class="bg-white rounded-xl border shadow-sm p-4 mb-6">
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
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
                    <button wire:click="openCreateOrderModal" class="px-4 py-2 bg-emerald-600 text-white rounded-md font-semibold uppercase text-xs hover:bg-emerald-700">
                        <i class="fas fa-plus mr-1"></i> Создать заявку
                    </button>
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
                                            <a href="javascript:void(0)" wire:click="viewOrder({{ $order->id }})" class="text-xs text-emerald-600 hover:text-emerald-800 mt-2 inline-block font-semibold">
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
    <script>
        @if($miner)
        window.currentMinerId = {{ $miner->id }};
        @endif

        document.addEventListener('livewire:init', () => {
            // notify обрабатывается в layout (components/layouts/app.blade.php)
            // Здесь НЕ регистрируем — чтобы не было дублей

            Livewire.on('set-cookie', (data) => {
                const event = Array.isArray(data) ? data[0] : data;
                if (!event || !event.name) return;
                const date = new Date();
                date.setTime(date.getTime() + (event.days * 24 * 60 * 60 * 1000));
                document.cookie = `${event.name}=${event.value};expires=${date.toUTCString()};path=/`;
            });
        });

        // =========================================
        // Echo подписка на канал экскаватора
        // =========================================
        let currentMinerChannel = null;

        function subscribeToMinerChannel(minerId) {
            if (!minerId || !window.Echo) return;

            if (currentMinerChannel) {
                window.Echo.leave(`private-miner.${currentMinerChannel}`);
                currentMinerChannel = null;
            }

            currentMinerChannel = minerId;

            window.Echo.private(`miner.${minerId}`)
                .listen('.excavator.notification', (data) => {
                    console.log('[Echo] Получено .excavator.notification', data);
                    Livewire.dispatch('refresh-miner-data');

                    // Показываем toast напрямую — нативный Livewire Echo listener
                    // может не сработать, если $this->miner ещё null в момент
                    // построения getListeners() (miner.0 канал — мимо).
                    const payload = data?.payload ?? {};
                    const message = payload.message ?? data?.message ?? 'Новое уведомление';
                    const type = (data?.type === 'truck_assigned' || payload.type === 'success')
                        ? 'success'
                        : 'info';
                    Livewire.dispatch('notify', [{ type, message }]);
                })
                .listen('.loading.started', (data) => {
                    console.log('[Echo] Получено .loading.started', data);
                    Livewire.dispatch('refresh-miner-data');

                    // Дублируем toast — нативный listener может не сработать
                    const message = data?.message ?? `Самосвал ${data?.truck_number ?? ''} начал погрузку`;
                    Livewire.dispatch('notify', [{ type: 'info', message }]);
                });
        }

        @if($miner)
        document.addEventListener('DOMContentLoaded', () => {
            subscribeToMinerChannel({{ $miner->id }});
        });
        @endif

        document.addEventListener('livewire:init', () => {
            Livewire.on('miner-selected', (data) => {
                const event = Array.isArray(data) ? data[0] : data;
                if (event && event.miner_id) {
                    subscribeToMinerChannel(event.miner_id);
                }
            });
        });
    </script>

    {{-- Модальное окно «Создать заявку» --}}
    <div
        x-data="{ open: @entangle('showCreateOrderModal') }"
        x-show="open" x-cloak x-transition.opacity
        style="display: none;"
        class="fixed inset-0 z-[99999] flex items-center justify-center p-4"
        x-on:keydown.escape.window="open = false"
    >
        <div x-show="open" x-transition.opacity class="absolute inset-0 bg-black/60 backdrop-blur-sm" x-on:click="open = false"></div>
        <div x-show="open" x-transition class="relative w-full max-w-lg bg-white rounded-2xl shadow-2xl overflow-hidden">
            <div class="px-6 py-4 bg-gradient-to-r from-emerald-500 to-teal-500 text-white">
                <div class="flex items-center gap-3">
                    <i class="fas {{ $editingOrderId ? 'fa-edit' : 'fa-clipboard-list' }} text-2xl"></i>
                    <div>
                        <h3 class="text-lg font-semibold">{{ $editingOrderId ? 'Редактировать заявку #' . $editingOrderId : 'Новая заявка' }}</h3>
                        <p class="text-sm text-white/90">Оборудование: {{ $minerMashineNumber ?? '—' }}</p>
                    </div>
                </div>
            </div>
            <form wire:submit.prevent="saveOrder" class="px-6 py-5 space-y-4 max-h-[70vh] overflow-y-auto">
                @php $allSets = \App\Models\Set::all(); @endphp
                @if($allSets->isNotEmpty())
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Комплектация (расходники)</label>
                    <div class="grid grid-cols-2 gap-2 p-3 bg-slate-50 rounded-md border border-slate-200">
                        @foreach($allSets as $set)
                            <label class="flex items-center gap-2 text-sm text-gray-700 cursor-pointer">
                                <input type="checkbox" value="{{ $set->id }}" wire:model="newOrderSets" class="rounded text-emerald-600 border-gray-300 focus:ring-emerald-500">
                                {{ $set->name }}
                            </label>
                        @endforeach
                    </div>
                </div>
                @endif
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Описание заявки *</label>
                    <textarea wire:model.live="newOrderContent" rows="4" placeholder="Опишите неисправность..." class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-emerald-500 @error('newOrderContent') border-red-500 @enderror" maxlength="2000"></textarea>
                    @error('newOrderContent') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Фото (необязательно)</label>
                    <div class="flex items-center gap-3">
                        <input type="file" wire:model="newOrderImage" accept="image/jpeg,image/png,image/webp" class="block w-full text-sm text-gray-500" />
                        @if($newOrderImage)
                            <img src="{{ $newOrderImage->temporaryUrl() }}" alt="preview" class="w-20 h-20 object-cover rounded-md border border-gray-300" />
                            <button type="button" wire:click="$set('newOrderImage', null)" class="text-red-500 hover:text-red-700 text-xs"><i class="fas fa-times"></i></button>
                        @endif
                    </div>
                    @error('newOrderImage') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                    <p class="mt-1 text-xs text-gray-500">JPG, PNG, WebP — до 5 МБ</p>
                    <div wire:loading wire:target="newOrderImage" class="text-xs text-emerald-600 mt-1"><i class="fas fa-spinner fa-spin mr-1"></i> Загрузка фото...</div>
                </div>
                <div class="bg-blue-50 border border-blue-200 rounded-md p-2 text-xs text-blue-700">
                    <i class="fas fa-info-circle mr-1"></i> Заявка автоматически попадёт в категорию «текущие».
                </div>
                <div class="flex gap-2 pt-2">
                    <button type="submit" wire:loading.attr="disabled" class="flex-1 px-4 py-2 bg-emerald-600 hover:bg-emerald-700 disabled:bg-emerald-300 text-white rounded-md font-medium transition">
                        <span wire:loading.remove><i class="fas {{ $editingOrderId ? 'fa-save' : 'fa-paper-plane' }} mr-1"></i> {{ $editingOrderId ? 'Сохранить' : 'Создать заявку' }}</span>
                        <span wire:loading><i class="fas fa-spinner fa-spin mr-1"></i> {{ $editingOrderId ? 'Сохранение...' : 'Создание...' }}</span>
                    </button>
                    <button type="button" wire:click="closeCreateOrderModal" class="px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-md font-medium transition">Отмена</button>
                </div>
            </form>
        </div>
    </div>

    {{-- Модальное окно «Детали заявки» --}}
    <div
        x-data="{ open: @entangle('showOrderDetailsModal') }"
        x-show="open" x-cloak x-transition.opacity
        style="display: none;"
        class="fixed inset-0 z-[99999] flex items-center justify-center p-4"
        x-on:keydown.escape.window="open = false"
    >
        <div x-show="open" x-transition.opacity class="absolute inset-0 bg-black/60 backdrop-blur-sm" x-on:click="open = false"></div>
        <div x-show="open" x-transition class="relative w-full max-w-lg bg-white rounded-2xl shadow-2xl overflow-hidden">
            <div class="px-6 py-4 bg-gradient-to-r from-blue-500 to-indigo-500 text-white">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <i class="fas fa-file-alt text-2xl"></i>
                        <div>
                            <h3 class="text-lg font-semibold">Заявка #{{ $viewingOrderId }}</h3>
                            <p class="text-sm text-white/90">Детали заявки</p>
                        </div>
                    </div>
                    <button type="button" x-on:click="open = false" class="text-white/80 hover:text-white text-2xl leading-none">&times;</button>
                </div>
            </div>
            @if($viewingOrder)
            <div class="p-6 space-y-4 max-h-[70vh] overflow-y-auto">
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <p class="text-xs text-gray-500 uppercase font-semibold mb-1">Оборудование</p>
                        <p class="text-sm font-medium text-gray-800">ЭКГ №{{ $viewingOrder->mashine?->number ?? '—' }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-500 uppercase font-semibold mb-1">Автор</p>
                        <p class="text-sm font-medium text-gray-800">{{ $viewingOrder->user?->name ?? '—' }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-500 uppercase font-semibold mb-1">Создана</p>
                        <p class="text-sm font-medium text-gray-800">{{ $viewingOrder->created_at?->translatedFormat('d F Y, H:i') }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-500 uppercase font-semibold mb-1">Исполнитель</p>
                        @if($viewingOrder->user_exec)
                            <p class="text-sm font-medium text-emerald-600">{{ $viewingOrder->userExec?->name ?? '—' }}</p>
                        @else
                            <p class="text-sm text-gray-400">Не назначен</p>
                        @endif
                    </div>
                </div>
                @php $allSets = \App\Models\Set::all(); @endphp
                @if($allSets->isNotEmpty())
                <div>
                    <p class="text-xs text-gray-500 uppercase font-semibold mb-2">Комплектация</p>
                    <div class="grid grid-cols-2 gap-2 p-3 bg-slate-50 rounded-md border border-slate-200">
                        @php $equipmentSets = $viewingOrder->mashine?->sets?->pluck('id')?->toArray() ?? []; @endphp
                        @foreach($allSets as $set)
                            @php $isChecked = in_array($set->id, $equipmentSets); @endphp
                            <label class="flex items-center gap-2 text-sm text-gray-700 cursor-pointer rounded-md px-2 py-1 {{ $isChecked ? 'bg-emerald-50 border border-emerald-200' : 'border border-transparent' }} hover:bg-slate-100 transition">
                                <input type="checkbox"
                                    @if($viewingOrder->mashine_id) wire:click="toggleSet({{ $viewingOrder->mashine_id }}, {{ $set->id }})" @endif
                                    {{ $isChecked ? 'checked' : '' }}
                                    class="rounded text-emerald-600 border-gray-300 focus:ring-emerald-500 cursor-pointer"
                                />
                                <span class="{{ $isChecked ? 'text-emerald-700 font-medium' : 'text-gray-500' }}">{{ $set->name }}</span>
                            </label>
                        @endforeach
                    </div>
                    <p class="mt-1 text-xs text-gray-400">Отмечено = требуется завезти. Снять галочку = завезли / не требуется.</p>
                </div>
                @endif
                <div>
                    <p class="text-xs text-gray-500 uppercase font-semibold mb-2">Описание</p>
                    <div class="bg-slate-50 p-4 rounded-md border border-slate-200">
                        <p class="text-sm text-gray-700 whitespace-pre-wrap">{{ $viewingOrder->content }}</p>
                    </div>
                </div>
                @if($viewingOrder->image)
                <div x-data="{ zoomed: false }">
                    <p class="text-xs text-gray-500 uppercase font-semibold mb-2">Фото</p>
                    <div class="rounded-md border border-slate-200 overflow-hidden cursor-zoom-in hover:opacity-80 transition" x-on:click="zoomed = true">
                        <img src="{{ asset('storage/' . $viewingOrder->image) }}" alt="Фото заявки" class="w-full max-h-48 object-cover" />
                    </div>
                    <p class="mt-1 text-xs text-gray-400"><i class="fas fa-search-plus mr-1"></i>Кликните для увеличения</p>
                    <template x-if="zoomed">
                        <div class="fixed inset-0 z-[100000] flex items-center justify-center bg-black/80 backdrop-blur-sm p-4" x-on:click="zoomed = false" x-on:keydown.escape.window="zoomed = false" style="display: flex;">
                            <div class="relative max-w-4xl max-h-[90vh]" x-on:click.stop>
                                <img src="{{ asset('storage/' . $viewingOrder->image) }}" alt="Фото заявки" class="max-w-full max-h-[90vh] object-contain rounded-lg shadow-2xl" />
                                <button type="button" x-on:click="zoomed = false" class="absolute -top-3 -right-3 w-8 h-8 bg-white text-gray-800 rounded-full shadow-lg flex items-center justify-center hover:bg-gray-200 transition"><i class="fas fa-times text-sm"></i></button>
                            </div>
                        </div>
                    </template>
                </div>
                @endif
                <div class="flex gap-2 pt-2 border-t">
                    @if($viewingOrder->user_id_req === auth()->id() || auth()->user()?->role === 'admin')
                        <button wire:click="deleteOrder({{ $viewingOrder->id }})" wire:confirm="Удалить заявку?" class="px-4 py-2 bg-red-500 hover:bg-red-600 text-white rounded-md font-medium text-sm transition">
                            <i class="fas fa-trash mr-1"></i> Удалить
                        </button>
                    @else
                        <span class="px-4 py-2 bg-gray-100 text-gray-400 rounded-md font-medium text-sm cursor-not-allowed" title="Удалить может только автор заявки">
                            <i class="fas fa-lock mr-1"></i> Только автор
                        </span>
                    @endif
                    {{-- Редактировать — только автору --}}
                    @if($viewingOrder->user_id_req === auth()->id() || auth()->user()?->role === 'admin')
                        <button wire:click="editOrder({{ $viewingOrder->id }})" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-md font-medium text-sm transition">
                            <i class="fas fa-edit mr-1"></i> Редактировать
                        </button>
                    @endif
                </div>
            </div>
            @endif
        </div>
    </div>

    {{-- Локальный toast-контейнер — внутри компонента, после модалок.
         z-index выше модалок (99999), гарантированно поверх.
         Обработчик notify — в layout (components/layouts/app.blade.php),
         он найдёт этот контейнер через global-toast-container. --}}
    <div id="excavator-toast-container" class="fixed top-4 right-4 p-4 flex flex-col items-end gap-2 pointer-events-none" style="z-index: 100000 !important;"></div>
</div>