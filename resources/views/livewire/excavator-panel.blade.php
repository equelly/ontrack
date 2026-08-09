<div class="min-h-screen flex flex-col bg-slate-50" x-data="{ tab: 'face' }">
    <!-- Toast контейнер для уведомлений -->
    <div id="global-toast-container" class="fixed top-0 right-0 p-3" style="z-index: 9999;"></div>

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
                <span>🚜</span> <span class="hidden sm:inline">Забой</span>
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
            <div class="bg-white rounded-xl border shadow-sm p-4 sm:p-6">
                <div class="flex flex-col sm:flex-row gap-4">
                    <div class="flex-1 flex items-center gap-2">
                        <select wire:model.live="selectedRockId" class="flex-1 border-gray-300 focus:border-emerald-500 focus:ring-emerald-500 rounded-md shadow-sm py-2 text-sm">
                            <option value="">-- Сменить породу --</option>
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
                    <p class="text-[10px] sm:text-xs text-gray-500 uppercase font-semibold mb-1">У забоя</p>
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
    <script>
        @if($miner)
        window.currentMinerId = {{ $miner->id }};
        @endif

        document.addEventListener('livewire:init', () => {
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
                    Livewire.dispatch('refresh-miner-data');
                })
                .listen('.loading.started', (data) => {
                    Livewire.dispatch('refresh-miner-data');
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
</div>