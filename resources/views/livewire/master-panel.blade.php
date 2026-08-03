<div class="min-h-screen flex flex-col bg-slate-50" x-data="{ tab: 'zones' }">
    <!-- Dark Header -->
    <header class="bg-slate-900 text-white shadow-lg mb-4 rounded-xl">
        <div class="px-4 py-3 flex items-center justify-between">
            <h1 class="text-lg font-bold uppercase tracking-wider">Панель Мастера</h1>
            <div class="text-right text-sm">
                <p class="text-gray-400">Текущая смена</p>
                <p class="font-bold">Смена {{ $shift['shift_id'] }} ({{ $shift['shift_type'] === 'day' ? 'День' : 'Ночь' }})</p>
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
        <!-- ВКЛАДКА: Оборудование -->
        <div x-show="tab === 'equipment'" x-cloak class="mt-4 space-y-6">
            
            <!-- Самосвалы -->
            <div class="bg-white rounded-xl border shadow-sm overflow-hidden">
                <div class="p-4 border-b font-bold text-gray-800 uppercase text-sm">Самосвалы</div>
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
                                    <td class="p-3 font-bold text-gray-800">{{ $truck->number }}</td>
                                    <td class="p-3">
                                        <span class="px-2 py-0.5 text-xs font-medium rounded-md 
                                            {{ $truck->status === 'breakdown' ? 'bg-red-100 text-red-700' : 
                                               ($truck->status === 'free' ? 'bg-slate-200 text-slate-700' : 'bg-emerald-100 text-emerald-700') }}">
                                            {{ \App\Domain\TruckStatus::label($truck->status) }}
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
</div>