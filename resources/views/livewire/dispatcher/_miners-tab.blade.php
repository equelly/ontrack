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
