<div class="maintenance-panel-wrapper" x-data="{ tab: 'orders' }">
    {{-- Хедер --}}
    <header class="bg-slate-800 text-white shadow-lg mb-2 rounded-xl">
        <div class="px-4 py-3 flex flex-wrap items-center justify-between gap-3">
            <div class="flex items-center gap-3">
                <i class="fas fa-tools text-amber-400 text-xl"></i>
                <div>
                    <h1 class="text-lg font-bold uppercase tracking-wider">Панель обслуживания</h1>
                    <p class="text-xs text-gray-400">{{ auth()->user()->positionLabel() }} — {{ auth()->user()->name }}</p>
                </div>
            </div>
            <button wire:click="openCreateOrderModal" class="px-4 py-2 bg-amber-600 hover:bg-amber-700 text-white rounded-md text-sm font-semibold uppercase whitespace-nowrap">
                <i class="fas fa-plus mr-1"></i> Создать заявку
            </button>
        </div>
    </header>

    {{-- Навигация по вкладкам --}}
    <nav class="bg-white border-b shadow-sm sticky top-0 z-10 mb-4 rounded-xl">
        <div class="px-2 sm:px-4 flex gap-1 py-2 justify-around sm:justify-start">
            <button @click="tab='orders'" :class="tab === 'orders' ? 'bg-amber-600 text-white shadow-sm' : 'text-gray-600 hover:bg-gray-100'"
                    class="px-3 sm:px-4 py-2 rounded-lg text-sm font-medium whitespace-nowrap transition-all flex items-center gap-1.5">
                <span><i class="fas fa-clipboard-list"></i></span>
                <span class="hidden sm:inline">Заявки</span>
                @if($this->summary['new'] > 0)
                    <span class="ml-1 bg-red-500 text-white text-[10px] font-bold rounded-full min-w-[18px] h-[18px] flex items-center justify-center px-1">{{ $this->summary['new'] }}</span>
                @endif
            </button>
            <button @click="tab='kits'" :class="tab === 'kits' ? 'bg-amber-600 text-white shadow-sm' : 'text-gray-600 hover:bg-gray-100'"
                    class="px-3 sm:px-4 py-2 rounded-lg text-sm font-medium whitespace-nowrap transition-all flex items-center gap-1.5">
                <span><i class="fas fa-boxes-stacked"></i></span>
                <span class="hidden sm:inline">Комплектация</span>
                @if($this->kits_count > 0)
                    <span class="ml-1 bg-amber-500 text-white text-[10px] font-bold rounded-full min-w-[18px] h-[18px] flex items-center justify-center px-1">{{ $this->kits_count }}</span>
                @endif
            </button>
        </div>
    </nav>

    {{-- KPI-сводка --}}
    @php $summary = $this->summary; @endphp
    <div class="grid grid-cols-2 md:grid-cols-5 gap-3 mb-4" x-show="tab === 'orders'" x-cloak>
        <div class="bg-white p-3 rounded-xl border shadow-sm text-center cursor-pointer hover:border-amber-400"
             wire:click="$set('filterStatus', 'new')">
            <p class="text-[10px] text-gray-500 uppercase font-semibold">Новые</p>
            <p class="text-xl font-bold text-amber-600">{{ $summary['new'] }}</p>
        </div>
        <div class="bg-white p-3 rounded-xl border shadow-sm text-center cursor-pointer hover:border-blue-400"
             wire:click="$set('filterStatus', 'in_progress')">
            <p class="text-[10px] text-gray-500 uppercase font-semibold">В работе</p>
            <p class="text-xl font-bold text-blue-600">{{ $summary['in_progress'] }}</p>
        </div>
        <div class="bg-white p-3 rounded-xl border shadow-sm text-center cursor-pointer hover:border-orange-400"
             wire:click="$set('filterStatus', 'paused')">
            <p class="text-[10px] text-gray-500 uppercase font-semibold">Паузы</p>
            <p class="text-xl font-bold text-orange-600">{{ $summary['paused'] }}</p>
        </div>
        <div class="bg-white p-3 rounded-xl border shadow-sm text-center">
            <p class="text-[10px] text-gray-500 uppercase font-semibold">Выполнено сегодня</p>
            <p class="text-xl font-bold text-emerald-600">{{ $summary['completed'] }}</p>
        </div>
        <div class="bg-white p-3 rounded-xl border shadow-sm text-center @if($summary['critical'] > 0) border-red-400 @endif">
            <p class="text-[10px] text-gray-500 uppercase font-semibold">Критические</p>
            <p class="text-xl font-bold @if($summary['critical'] > 0) text-red-600 animate-pulse @else text-slate-400 @endif">{{ $summary['critical'] }}</p>
        </div>
    </div>

    {{-- Фильтры --}}
    <div class="bg-white rounded-xl border shadow-sm p-4 mb-4" x-show="tab === 'orders'" x-cloak>
        <div class="flex flex-wrap items-center gap-2">
            <select wire:model.live="filterStatus" class="text-xs border-gray-300 rounded-md py-1.5 px-2">
                <option value="all">Все статусы</option>
                @foreach (\App\Models\Order::STATUS_LABELS as $value => $label)
                    <option value="{{ $value }}">{{ $label }}</option>
                @endforeach
            </select>

            <select wire:model.live="filterWorkType" class="text-xs border-gray-300 rounded-md py-1.5 px-2">
                <option value="all">Все типы работ</option>
                @foreach (\App\Models\Order::WORK_TYPE_LABELS as $value => $label)
                    <option value="{{ $value }}">{{ $label }}</option>
                @endforeach
            </select>

            <select wire:model.live="filterPriority" class="text-xs border-gray-300 rounded-md py-1.5 px-2">
                <option value="all">Все приоритеты</option>
                @foreach (\App\Models\Order::PRIORITY_LABELS as $value => $label)
                    <option value="{{ $value }}">{{ $label }}</option>
                @endforeach
            </select>

            <select wire:model.live="filterMashineId" class="text-xs border-gray-300 rounded-md py-1.5 px-2">
                <option value="">Всё оборудование</option>
                @foreach ($mashines as $m)
                    <option value="{{ $m->id }}">{{ $m->number }}</option>
                @endforeach
            </select>

            <select wire:model.live="filterPeriod" class="text-xs border-gray-300 rounded-md py-1.5 px-2">
                <option value="all">За всё время</option>
                <option value="today">За сегодня</option>
                <option value="week">За неделю</option>
                <option value="month">За месяц</option>
            </select>

            <label class="flex items-center gap-1.5 text-xs text-gray-700 cursor-pointer">
                <input type="checkbox" wire:model.live="showOnlyMine" class="rounded text-emerald-600">
                <span>Только мои</span>
            </label>
            <button wire:click="resetFilters" class="ml-auto px-3 py-1.5 text-xs bg-slate-100 hover:bg-slate-200 rounded-md text-gray-700">
                <i class="fas fa-times mr-1"></i> Сбросить
            </button>
        </div>
    </div>

    {{-- Лента заявок --}}
    <div class="bg-white rounded-xl border shadow-sm overflow-hidden" x-show="tab === 'orders'" x-cloak>
        @if($orders->isNotEmpty())
            <div class="divide-y divide-gray-100">
                @foreach ($orders as $order)
                    <div class="p-4 hover:bg-slate-50 transition {{ $order->status === 'new' ? 'bg-amber-50/50 border-l-4 border-amber-400' : '' }}">
                        <div class="flex items-start gap-3">
                            {{-- Иконка приоритета --}}
                            <div class="flex-shrink-0">
                                <div class="w-10 h-10 rounded-full flex items-center justify-center bg-{{ $order->priorityColor() }}-100 text-{{ $order->priorityColor() }}-600">
                                    @if($order->priority === 'critical')
                                        <i class="fas fa-exclamation-triangle"></i>
                                    @elseif($order->priority === 'high')
                                        <i class="fas fa-arrow-up"></i>
                                    @elseif($order->priority === 'medium')
                                        <i class="fas fa-equals"></i>
                                    @else
                                        <i class="fas fa-arrow-down"></i>
                                    @endif
                                </div>
                            </div>

                            {{-- Контент --}}
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center gap-2 mb-1">
                                    <span class="text-xs font-mono text-gray-500">#{{ $order->id }}</span>
                                    <span class="px-2 py-0.5 text-[10px] font-bold rounded bg-{{ $order->statusColor() }}-100 text-{{ $order->statusColor() }}-700">
                                        {{ $order->statusLabel() }}
                                    </span>
                                    <span class="px-2 py-0.5 text-[10px] font-semibold rounded bg-slate-100 text-slate-700">
                                        {{ $order->workTypeLabel() }}
                                    </span>
                                    <span class="px-2 py-0.5 text-[10px] font-semibold rounded bg-{{ $order->priorityColor() }}-100 text-{{ $order->priorityColor() }}-700">
                                        {{ $order->priorityLabel() }}
                                    </span>
                                    <span class="text-xs text-gray-400 ml-auto">
                                        {{ $order->created_at->diffForHumans() }}
                                    </span>
                                </div>

                                <p class="text-sm text-gray-800 mb-2">{{ $order->content }}</p>

                                <div class="flex flex-wrap items-center gap-3 text-xs text-gray-500">
                                    @if($order->mashine)
                                        <span class="font-mono font-bold text-slate-700">
                                            <i class="fas fa-truck-loading mr-1"></i> {{ $order->mashine->number }}
                                        </span>
                                    @endif
                                    <span><i class="fas fa-user mr-1"></i> Автор: {{ $order->user?->name ?? '—' }}</span>
                                    @if($order->assigned_to)
                                        <span><i class="fas fa-wrench mr-1"></i> Исполнитель: {{ $order->userExec?->name ?? '—' }}</span>
                                    @endif
                                    <span class="ml-auto">
                                        <i class="fas fa-clock mr-1"></i> {{ $order->created_at->format('d.m H:i') }}
                                    </span>
                                </div>

                                {{-- Кнопки действий --}}
                                <div class="mt-3 flex flex-wrap gap-2">
                                    @if($order->status === 'new' || $order->status === 'paused')
                                        @if($order->canBeTakenBy(auth()->user()))
                                            <button wire:click="takeOrder({{ $order->id }})"
                                                    wire:confirm="Взять заявку в работу?"
                                                    class="px-3 py-1.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded-md text-xs font-semibold uppercase">
                                                <i class="fas fa-play mr-1"></i> Взять в работу
                                            </button>
                                        @endif
                                    @endif

                                    @if($order->status === 'in_progress')
                                        <button wire:click="pauseOrder({{ $order->id }})"
                                                class="px-3 py-1.5 bg-orange-100 hover:bg-orange-200 text-orange-700 rounded-md text-xs font-semibold uppercase">
                                            <i class="fas fa-pause mr-1"></i> Пауза
                                        </button>
                                        <button wire:click="completeOrder({{ $order->id }})"
                                                wire:confirm="Завершить заявку?"
                                                class="px-3 py-1.5 bg-emerald-100 hover:bg-emerald-200 text-emerald-700 rounded-md text-xs font-semibold uppercase">
                                            <i class="fas fa-check mr-1"></i> Завершить
                                        </button>
                                    @endif

                                    @if($order->status === 'paused')
                                        <button wire:click="resumeOrder({{ $order->id }})"
                                                class="px-3 py-1.5 bg-blue-100 hover:bg-blue-200 text-blue-700 rounded-md text-xs font-semibold uppercase">
                                            <i class="fas fa-play mr-1"></i> Возобновить
                                        </button>
                                    @endif

                                    @if($order->status !== 'completed' && $order->status !== 'cancelled')
                                        <button wire:click="cancelOrder({{ $order->id }})"
                                                class="px-3 py-1.5 bg-red-100 hover:bg-red-200 text-red-700 rounded-md text-xs font-semibold uppercase">
                                            <i class="fas fa-times mr-1"></i> Отменить
                                        </button>
                                    @endif

                                    @if(auth()->user()->canManageMaintenanceOrders() && $order->status !== 'completed' && $order->status !== 'cancelled')
                                        <button wire:click="openAssignModal({{ $order->id }})"
                                                class="px-3 py-1.5 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-md text-xs font-semibold uppercase">
                                            <i class="fas fa-user-friends mr-1"></i> Назначить
                                        </button>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @else
            <div class="p-8 text-center text-gray-400 text-sm">
                <i class="fas fa-inbox text-3xl text-gray-300 mb-2"></i>
                <p>Нет заявок по выбранным фильтрам</p>
            </div>
        @endif
    </div>

    {{-- ========================================== --}}
    {{-- ВКЛАДКА: КОМПЛЕКТАЦИЯ ДЛЯ ЗАВОЗКИ          --}}
    {{-- ========================================== --}}
    <div x-show="tab === 'kits'" x-cloak class="space-y-4">
        <div class="flex items-center gap-2">
            <h2 class="text-base sm:text-lg font-bold text-gray-800 uppercase tracking-wider">Комплектация</h2>
            <span class="text-xs text-gray-500">— расходники, которые нужно завезти на оборудование</span>
        </div>

        @if($this->kits_count > 0)
            <div class="bg-amber-50 border border-amber-200 rounded-md p-3 text-sm text-amber-800">
                <i class="fas fa-info-circle mr-1"></i>
                Отметьте галочкой расходник, который уже завезён — он будет убран из списка.
                Источник: мастер / водитель / экскаваторщик отметил нужные расходники в заявке → их нужно завезти.
            </div>

            <div class="space-y-3">
                @foreach ($this->kits_data as $kit)
                    <div class="bg-white rounded-xl border shadow-sm overflow-hidden">
                        <div class="p-3 bg-slate-50 border-b flex items-center gap-2">
                            <i class="fas fa-truck-loading text-slate-600"></i>
                            <span class="font-mono font-bold text-slate-800">{{ $kit['mashine_number'] }}</span>
                            <span class="text-xs text-gray-500">— {{ $kit['sets_count'] }} поз.</span>
                        </div>
                        <div class="p-3 space-y-1.5">
                            @foreach ($kit['sets'] as $set)
                                <label class="flex items-center gap-3 p-2 rounded-md hover:bg-amber-50 cursor-pointer transition">
                                    <input type="checkbox"
                                           wire:click="toggleSetDelivered({{ $kit['mashine_id'] }}, {{ $set->id }})"
                                           class="w-5 h-5 rounded text-emerald-600 border-gray-300 focus:ring-emerald-500"
                                           title="Отметить как завезённое">
                                    <i class="fas fa-box text-amber-500"></i>
                                    <span class="flex-1 text-sm text-gray-800">{{ $set->name }}</span>
                                    <span class="text-xs text-gray-400">завезено? →</span>
                                </label>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>
        @else
            <div class="bg-white rounded-xl border shadow-sm p-8 text-center text-gray-400 text-sm">
                <i class="fas fa-check-circle text-3xl text-emerald-400 mb-2"></i>
                <p>Нет расходников для завозки</p>
                <p class="text-xs mt-1">Все комплектации либо отсутствуют, либо уже завезены.</p>
            </div>
        @endif
    </div>

    {{-- Модалка создания заявки --}}
    @if($showCreateOrderModal)
        <div class="fixed inset-0 z-[9999] flex items-center justify-center bg-black/70 p-4" style="display: flex;">
            <div class="bg-white rounded-xl shadow-2xl w-full max-w-md">
                <div class="p-4 border-b flex justify-between items-center bg-amber-500 text-white rounded-t-xl">
                    <h5 class="font-bold uppercase"><i class="fas fa-plus mr-2"></i> Новая заявка</h5>
                    <button wire:click="closeCreateOrderModal" class="text-2xl leading-none">&times;</button>
                </div>
                <div class="p-4 space-y-3">
                    <div>
                        <label class="block text-xs uppercase text-gray-500 mb-1">Оборудование</label>
                        <select wire:model="newOrderMashineId" class="w-full border-gray-300 rounded-md py-2 text-sm">
                            <option value="">Выберите оборудование</option>
                            @foreach ($mashines as $m)
                                <option value="{{ $m->id }}">{{ $m->number }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs uppercase text-gray-500 mb-1">Тип работ</label>
                        <select wire:model="newOrderWorkType" class="w-full border-gray-300 rounded-md py-2 text-sm">
                            @foreach (\App\Models\Order::WORK_TYPE_LABELS as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs uppercase text-gray-500 mb-1">Приоритет</label>
                        <select wire:model="newOrderPriority" class="w-full border-gray-300 rounded-md py-2 text-sm">
                            @foreach (\App\Models\Order::PRIORITY_LABELS as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs uppercase text-gray-500 mb-1">Описание</label>
                        <textarea wire:model="newOrderContent" rows="3" class="w-full border-gray-300 rounded-md py-2 text-sm" placeholder="Опишите неисправность..."></textarea>
                    </div>
                </div>
                <div class="p-4 border-t flex justify-end gap-2">
                    <button wire:click="closeCreateOrderModal" class="px-4 py-2 bg-slate-200 text-slate-700 rounded-md uppercase text-sm">Отмена</button>
                    <button wire:click="createOrder" class="px-4 py-2 bg-amber-500 text-white rounded-md uppercase text-sm hover:bg-amber-600">Создать</button>
                </div>
            </div>
        </div>
    @endif

    {{-- Модалка переназначения --}}
    @if($showAssignModal)
        <div class="fixed inset-0 z-[9999] flex items-center justify-center bg-black/70 p-4" style="display: flex;">
            <div class="bg-white rounded-xl shadow-2xl w-full max-w-md">
                <div class="p-4 border-b flex justify-between items-center bg-slate-700 text-white rounded-t-xl">
                    <h5 class="font-bold uppercase"><i class="fas fa-user-friends mr-2"></i> Назначить исполнителя</h5>
                    <button wire:click="closeAssignModal" class="text-2xl leading-none">&times;</button>
                </div>
                <div class="p-4">
                    <label class="block text-xs uppercase text-gray-500 mb-1">Исполнитель</label>
                    <select wire:model="assignToUserId" class="w-full border-gray-300 rounded-md py-2 text-sm">
                        <option value="">Выберите исполнителя</option>
                        @foreach ($executors as $executor)
                            <option value="{{ $executor->id }}">{{ $executor->name }} ({{ $executor->positionLabel() }})</option>
                        @endforeach
                    </select>
                </div>
                <div class="p-4 border-t flex justify-end gap-2">
                    <button wire:click="closeAssignModal" class="px-4 py-2 bg-slate-200 text-slate-700 rounded-md uppercase text-sm">Отмена</button>
                    <button wire:click="confirmAssign" class="px-4 py-2 bg-emerald-600 text-white rounded-md uppercase text-sm hover:bg-emerald-700">Назначить</button>
                </div>
            </div>
        </div>
    @endif

    {{-- Модалка паузы --}}
    @if($pauseOrderId)
        <div class="fixed inset-0 z-[9999] flex items-center justify-center bg-black/70 p-4" style="display: flex;">
            <div class="bg-white rounded-xl shadow-2xl w-full max-w-md">
                <div class="p-4 border-b flex justify-between items-center bg-orange-500 text-white rounded-t-xl">
                    <h5 class="font-bold uppercase"><i class="fas fa-pause mr-2"></i> Приостановить заявку</h5>
                    <button wire:click="$set('pauseOrderId', null)" class="text-2xl leading-none">&times;</button>
                </div>
                <div class="p-4">
                    <label class="block text-xs uppercase text-gray-500 mb-1">Причина паузы</label>
                    <textarea wire:model="pauseReason" rows="3" class="w-full border-gray-300 rounded-md py-2 text-sm"
                              placeholder="Например: ждём запчасти, ждём разрешения, нет доступа..."></textarea>
                </div>
                <div class="p-4 border-t flex justify-end gap-2">
                    <button wire:click="$set('pauseOrderId', null)" class="px-4 py-2 bg-slate-200 text-slate-700 rounded-md uppercase text-sm">Отмена</button>
                    <button wire:click="confirmPause" class="px-4 py-2 bg-orange-500 text-white rounded-md uppercase text-sm hover:bg-orange-600">Приостановить</button>
                </div>
            </div>
        </div>
    @endif

    {{-- Модалка отмены --}}
    @if($cancelOrderId)
        <div class="fixed inset-0 z-[9999] flex items-center justify-center bg-black/70 p-4" style="display: flex;">
            <div class="bg-white rounded-xl shadow-2xl w-full max-w-md">
                <div class="p-4 border-b flex justify-between items-center bg-red-500 text-white rounded-t-xl">
                    <h5 class="font-bold uppercase"><i class="fas fa-times mr-2"></i> Отменить заявку</h5>
                    <button wire:click="$set('cancelOrderId', null)" class="text-2xl leading-none">&times;</button>
                </div>
                <div class="p-4">
                    <label class="block text-xs uppercase text-gray-500 mb-1">Причина отмены</label>
                    <textarea wire:model="cancelReason" rows="3" class="w-full border-gray-300 rounded-md py-2 text-sm"
                              placeholder="Почему отменяете заявку?"></textarea>
                </div>
                <div class="p-4 border-t flex justify-end gap-2">
                    <button wire:click="$set('cancelOrderId', null)" class="px-4 py-2 bg-slate-200 text-slate-700 rounded-md uppercase text-sm">Отмена</button>
                    <button wire:click="confirmCancel" class="px-4 py-2 bg-red-500 text-white rounded-md uppercase text-sm hover:bg-red-600">Отменить</button>
                </div>
            </div>
        </div>
    @endif
</div>
