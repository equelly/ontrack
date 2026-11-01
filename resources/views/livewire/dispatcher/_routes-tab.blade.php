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
                    <button class="px-4 py-2 bg-blue-600 text-white rounded-md text-xs font-semibold uppercase hover:bg-blue-700 w-full sm:w-auto" wire:click="openCreateOrderModal">
                        <i class="fas fa-plus mr-1"></i> Добавить маршрут
                    </button>
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
                                                <button class="text-gray-600 hover:text-gray-800 px-2 py-1 text-sm font-bold border rounded disabled:opacity-40 disabled:cursor-not-allowed" wire:click="adjustWeight({{ $order->id }}, -10)" wire:loading.attr="disabled" title="Уменьшить вес"><span wire:loading.remove>−</span><span wire:loading>⟳</span></button>
                                                <span class="px-2 py-0.5 text-xs font-bold rounded-md {{ $order->active ? 'bg-blue-100 text-blue-700' : 'bg-slate-200 text-slate-700' }}" style="min-width: 35px; text-align: center;">{{ $order->weight }}</span>
                                                <button class="text-gray-600 hover:text-gray-800 px-2 py-1 text-sm font-bold border rounded disabled:opacity-40 disabled:cursor-not-allowed" wire:click="adjustWeight({{ $order->id }}, 10)" wire:loading.attr="disabled" title="Увеличить вес"><span wire:loading.remove>+</span><span wire:loading>⟳</span></button>
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
                                                    <button class="text-gray-600 hover:text-amber-600 px-2 py-1 text-sm border rounded disabled:opacity-40 disabled:cursor-not-allowed" wire:click="deactivateOrder({{ $order->id }})" wire:loading.attr="disabled" title="Деактивировать"><span wire:loading.remove>⏸</span><span wire:loading>⟳</span></button>
                                                @else
                                                    <button class="text-gray-600 hover:text-emerald-600 px-2 py-1 text-sm border rounded disabled:opacity-40 disabled:cursor-not-allowed" wire:click="activateOrder({{ $order->id }})" wire:loading.attr="disabled" title="Активировать"><span wire:loading.remove>▶</span><span wire:loading>⟳</span></button>
                                                @endif
                                                <button class="text-gray-600 hover:text-blue-600 px-2 py-1 text-sm border rounded disabled:opacity-40 disabled:cursor-not-allowed" wire:click="openEditOrder({{ $order->id }})" wire:loading.attr="disabled" title="Редактировать"><span wire:loading.remove>✎</span><span wire:loading>⟳</span></button>
                                                <button class="text-gray-600 hover:text-red-600 px-2 py-1 text-sm border rounded disabled:opacity-40 disabled:cursor-not-allowed" wire:click="deleteOrder({{ $order->id }})" wire:confirm="Удалить маршрут?" wire:loading.attr="disabled" title="Удалить"><span wire:loading.remove>🗑</span><span wire:loading>⟳</span></button>
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
