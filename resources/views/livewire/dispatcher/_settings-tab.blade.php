        <div x-show="tab === 'settingsTab'" x-cloak class="mt-4 space-y-4" id="settingsTab">
            
            {{-- Аварийные действия — только в ручном режиме.
                 В авто-режиме система сама оптимизирует и генерирует пары.
                 В ручном режиме — эти кнопки нужны для вмешательства. --}}
            @if($this->routeMode === 'manual')
            <div class="bg-white rounded-xl border-2 border-amber-300 shadow-sm p-6">
                <h3 class="font-bold text-gray-800 uppercase text-sm mb-4 flex items-center gap-2">
                    <i class="fas fa-tools text-amber-500"></i> Аварийные действия
                    <span class="ml-auto px-2 py-0.5 text-xs font-medium rounded-md bg-amber-100 text-amber-700">ручной режим</span>
                </h3>
                <p class="text-xs text-gray-500 mb-4">
                    В авто-режиме эти кнопки не нужны — система сама пересчитывает маршруты.
                    Здесь — для ручного вмешательства, если что-то пошло не так.
                </p>
                <div class="flex flex-wrap gap-2">
                    <button class="px-4 py-2 bg-slate-200 text-slate-700 rounded-md text-xs font-semibold uppercase hover:bg-slate-300"
                            wire:click="generateAllPairs"
                            wire:loading.attr="disabled"
                            wire:confirm="Сгенерировать маршруты для всех пар забой-отвал?">
                        <span wire:loading.remove><i class="fas fa-layer-group mr-1"></i> Сгенерировать все пары</span>
                        <span wire:loading><i class="fas fa-spinner fa-spin mr-1"></i></span>
                    </button>
                    <button class="px-4 py-2 bg-amber-500 text-white rounded-md text-xs font-semibold uppercase hover:bg-amber-600"
                            wire:click="optimizeRoutes"
                            wire:loading.attr="disabled"
                            title="Принудительная оптимизация маршрутов">
                        <span wire:loading.remove><i class="fas fa-magic mr-1"></i> Оптимизировать</span>
                        <span wire:loading><i class="fas fa-spinner fa-spin mr-1"></i></span>
                    </button>
                </div>
            </div>
            @endif

            <!-- Пороги перегруженности -->
            <div class="bg-white rounded-xl border shadow-sm p-6">
                <h3 class="font-bold text-gray-800 uppercase text-sm mb-4 flex items-center gap-2"><i class="fas fa-sliders-h text-emerald-500"></i> Пороги перегруженности</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-xs uppercase text-gray-500 mb-2">Порог ожидания на забое</label>
                        <div class="flex items-center gap-2">
                            <input type="number" min="1" max="20" step="1" wire:model="minerThreshold" class="flex-1 border-gray-300 rounded-md shadow-sm py-2 px-3 text-blue-700 font-bold">
                        </div>
                        <small class="text-gray-400 block mt-2">Количество самосвалов в статусе "Ожидание погрузки" для признания забоя перегруженным.</small>
                    </div>
                    <div>
                        <label class="block text-xs uppercase text-gray-500 mb-2">Порог ожидания в зоне разгрузки</label>
                        <div class="flex items-center gap-2">
                            <input type="number" min="1" max="20" step="1" wire:model="zoneThreshold" class="flex-1 border-gray-300 rounded-md shadow-sm py-2 px-3 text-emerald-700 font-bold">
                        </div>
                        <small class="text-gray-400 block mt-2">Количество самосвалов в статусе "Ожидание разгрузки" для признания зоны перегруженной.</small>
                    </div>
                </div>
                <div class="mt-4 flex justify-end">
                    <button wire:click="saveThresholds" wire:loading.attr="disabled" class="px-4 py-2 bg-emerald-600 text-white rounded-md uppercase text-sm hover:bg-emerald-700">
                        <span wire:loading.remove>Сохранить пороги</span>
                        <span wire:loading>Сохранение...</span>
                    </button>
                </div>
            </div>

            <!-- Порог среднего расстояния перевозки -->
            <div class="bg-white rounded-xl border shadow-sm p-6">
                <h3 class="font-bold text-gray-800 uppercase text-sm mb-4 flex items-center gap-2"><i class="fas fa-route text-cyan-500"></i> Порог среднего расстояния перевозки</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-xs uppercase text-gray-500 mb-2">Значение (км, до сотых)</label>
                        <div class="flex items-center gap-2">
                            <input type="number" min="0.01" max="50" step="0.01" wire:model="avgDistanceThreshold" class="flex-1 border-gray-300 rounded-md shadow-sm py-2 px-3 text-cyan-700 font-bold">
                            <span class="text-xs text-gray-500">км</span>
                        </div>
                        <small class="text-gray-400 block mt-2">
                            Если фактическое среднее расстояние завершённых рейсов за смену превышает этот порог — создаётся AiAlert со списком рекомендаций по перенаправлению грузопотоков на ближние отвалы с подходящей породой.
                        </small>
                    </div>
                    <div class="flex flex-col justify-center">
                        <div class="p-3 bg-cyan-50 rounded-md text-cyan-700 text-sm">
                            <i class="fas fa-info-circle mr-1"></i>
                            <strong>Когда сработает:</strong> если среднее расстояние за смену превысит порог. Например, при пороге 1.50 км и среднем 2.80 км — алерт создастся со списком альтернатив.
                        </div>
                    </div>
                </div>
                <div class="mt-4 flex justify-end">
                    <button wire:click="saveAvgDistanceThreshold" wire:loading.attr="disabled" class="px-4 py-2 bg-cyan-600 text-white rounded-md uppercase text-sm hover:bg-cyan-700">
                        <span wire:loading.remove>Сохранить порог</span>
                        <span wire:loading>Сохранение...</span>
                    </button>
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
