        <div x-show="tab === 'trucksTab'" x-cloak class="mt-4" id="trucksTab">
            <div class="bg-white rounded-xl border shadow-sm overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-slate-50 border-b">
                            <tr>
                                <th class="text-left p-3 font-semibold text-gray-600">Номер</th>
                                <th class="text-left p-3 font-semibold text-gray-600">Груз.</th>
                                <th class="text-left p-3 font-semibold text-gray-600">Статус</th>
                                <th class="text-left p-3 font-semibold text-gray-600">Порода</th>
                                <th class="text-left p-3 font-semibold text-gray-600">Маршрут</th>
                                <th class="text-left p-3 font-semibold text-gray-600">Время</th>
                                <th class="text-left p-3 font-semibold text-gray-600">Задержка</th>
                                <th class="text-left p-3 font-semibold text-gray-600"></th>
                            </tr>
                        </thead>
                        <tbody>
                            @php
                                $statusLabels = [
                                    'free' => ['label' => 'В отстое', 'color' => 'slate', 'icon' => 'fa-parking'],
                                    'completed' => ['label' => 'Ожидает', 'color' => 'emerald', 'icon' => 'fa-check-circle'],
                                    'to_miner' => ['label' => 'К забою', 'color' => 'cyan', 'icon' => 'fa-arrow-right'],
                                    'loading' => ['label' => 'Погрузка', 'color' => 'amber', 'icon' => 'fa-truck-loading'],
                                    'transporting' => ['label' => 'Перевозка', 'color' => 'blue', 'icon' => 'fa-truck'],
                                    'unloading' => ['label' => 'Разгрузка', 'color' => 'slate', 'icon' => 'fa-truck-unload'],
                                    'breakdown' => ['label' => 'Поломка', 'color' => 'red', 'icon' => 'fa-wrench'],
                                    'delayed' => ['label' => 'Задержка', 'color' => 'amber', 'icon' => 'fa-clock'],
                                    'waiting_loading' => ['label' => 'Ожид. погрузки', 'color' => 'amber', 'icon' => 'fa-hourglass-half'],
                                    'waiting_unloading' => ['label' => 'Ожид. разгрузки', 'color' => 'red', 'icon' => 'fa-exclamation-triangle'],
                                ];
                            @endphp
                            @foreach($trucks as $truck)
                                @php
                                    $status = $statusLabels[$truck->status] ?? ['label' => $truck->status, 'color' => 'slate', 'icon' => 'fa-question'];
                                    $trip = $truck->trips->first();
                                    
                                    $truckRock = null;
                                    $truckRockLabel = '';
                                    if ($trip) {
                                        if (in_array($truck->status, ['transporting', 'unloading', 'waiting_unloading']) && $trip->rock) {
                                            $truckRock = $trip->rock;
                                            $truckRockLabel = 'Загружена';
                                        } elseif ($trip->rock_id) {
                                            $truckRock = $trip->rock;
                                            $truckRockLabel = 'В рейсе';
                                        } elseif ($trip->miningOrder && $trip->miningOrder->rock) {
                                            $truckRock = $trip->miningOrder->rock;
                                            $truckRockLabel = 'По маршруту';
                                        } elseif ($trip->miner && $trip->miner->currentRock) {
                                            $truckRock = $trip->miner->currentRock;
                                            $truckRockLabel = 'В забое';
                                        }
                                    }
                                    
                                    $activePause = null;
                                    if (in_array($truck->status, ['delayed', 'breakdown']) && $trip) {
                                        $activePause = $trip->pauses->first();
                                    }
                                @endphp
                                <tr class="border-b hover:bg-slate-50 {{ $truck->status === 'breakdown' ? 'bg-red-50' : ($truck->status === 'delayed' ? 'bg-amber-50' : ($truck->status === 'waiting_unloading' ? 'bg-red-50' : '')) }}">
                                    <td class="p-3 font-bold text-gray-800">{{ $truck->number }}</td>
                                    <td class="p-3 text-gray-500">{{ $truck->load_capacity }}т</td>
                                    <td class="p-3">
                                        <span class="px-2 py-0.5 text-xs font-medium rounded-md bg-{{ $status['color'] }}-100 text-{{ $status['color'] }}-700">
                                            <i class="fas {{ $status['icon'] }} mr-1"></i>{{ $status['label'] }}
                                        </span>
                                        {{-- Бейдж «Обваловка» если рейс назначен на зону под обваловкой --}}
                                        @if($trip && $trip->zone_id && in_array($trip->zone_id, $bermZoneIds))
                                            <span class="ml-1 px-2 py-0.5 text-[10px] font-bold rounded-md bg-amber-500 text-white" title="Рейс на обваловку зоны {{ $trip->zone?->name_zone ?? '' }}">
                                                <i class="fas fa-mountain mr-0.5"></i>Обваловка
                                            </span>
                                        @endif
                                        {{-- Для свободных самосвалов без маршрута показываем точную причину --}}
                                        @if(in_array($truck->status, ['free', 'completed']))
                                            @php
                                                $truckDiag = $this->truckDiagnostics[$truck->id] ?? null;
                                            @endphp
                                            @if($truckDiag && $truckDiag['primary_reason'])
                                                @php
                                                    $reasonCode = $truckDiag['primary_reason'];
                                                    $reasonLabel = \App\Domain\RouteBlockReason::shortLabel($reasonCode);
                                                    $reasonColor = \App\Domain\RouteBlockReason::color($reasonCode);
                                                    $reasonIcon = \App\Domain\RouteBlockReason::icon($reasonCode);
                                                    $reasonAction = \App\Domain\RouteBlockReason::action($reasonCode);
                                                    $reasonCount = $truckDiag['summary'][$reasonCode] ?? 0;
                                                @endphp
                                                <div class="mt-1 group relative inline-block">
                                                    <span class="px-2 py-0.5 text-[10px] font-medium rounded-md bg-{{ $reasonColor }}-100 text-{{ $reasonColor }}-700 cursor-help"
                                                          title="{{ \App\Domain\RouteBlockReason::label($reasonCode) }}. {{ $reasonAction }}">
                                                        <i class="fas {{ $reasonIcon }} mr-0.5"></i>{{ $reasonLabel }}@if($reasonCount > 1) ×{{ $reasonCount }}@endif
                                                    </span>
                                                    {{-- Тултип с детальным описанием --}}
                                                    <div class="hidden group-hover:block absolute z-50 left-0 top-full mt-1 w-64 bg-slate-800 text-white text-xs rounded-lg shadow-xl p-3">
                                                        <div class="font-semibold mb-1">{{ \App\Domain\RouteBlockReason::label($reasonCode) }}</div>
                                                        <div class="text-slate-300 mb-2">{{ $reasonAction }}</div>
                                                        @if(!empty($truckDiag['orders']))
                                                            <div class="border-t border-slate-600 pt-2 mt-2">
                                                                <div class="text-slate-400 mb-1">По маршрутам:</div>
                                                                @foreach(collect($truckDiag['orders'])->take(5) as $orderDiag)
                                                                    <div class="text-slate-200 text-[10px]">
                                                                        <span class="font-mono">#{{ $orderDiag['order_id'] }}</span>
                                                                        {{ $orderDiag['miner_name'] }}
                                                                        @if($orderDiag['rock_name'])
                                                                            <span class="text-slate-400">({{ $orderDiag['rock_name'] }})</span>
                                                                        @endif
                                                                        — {{ $orderDiag['reason_label'] }}
                                                                    </div>
                                                                @endforeach
                                                                @if(count($truckDiag['orders']) > 5)
                                                                    <div class="text-slate-400 text-[10px] mt-1">…и ещё {{ count($truckDiag['orders']) - 5 }}</div>
                                                                @endif
                                                            </div>
                                                        @endif
                                                    </div>
                                                </div>
                                            @else
                                                <span class="ml-1 px-2 py-0.5 text-[10px] font-medium rounded-md bg-emerald-50 text-emerald-600">
                                                    <i class="fas fa-check mr-0.5"></i>Готов к назначению
                                                </span>
                                            @endif
                                        @endif
                                    </td>
                                    <td class="p-3">
                                        @if($truckRock)
                                            <span class="px-2 py-0.5 text-xs font-medium rounded-md bg-cyan-100 text-cyan-700">{{ $truckRock->name_rock }}</span>
                                        @else
                                            <span class="text-gray-400">—</span>
                                        @endif
                                    </td>
                                    <td class="p-3 text-gray-600">
                                        @if($trip)
                                            <small>
                                                <i class="fas fa-route text-gray-400 mr-1"></i>
                                                {{ $trip->miner?->name_miner ?? '-' }} → {{ $trip->dump?->name_dump ?? '-' }}
                                                @if($trip->zone)
                                                    <span class="ml-1 px-2 py-0.5 text-xs bg-slate-200 rounded">{{ $trip->zone->name_zone }}</span>
                                                @endif
                                            </small>
                                        @else
                                            <span class="text-gray-400">—</span>
                                        @endif
                                    </td>
                                    <td class="p-3">
                                        @if($trip && $trip->started_at)
                                            @php
                                                $completedPauseSeconds = 0;
                                                $activePauseStart = null;
                                                foreach ($trip->pauses as $pause) {
                                                    if ($pause->ended_at) {
                                                        $completedPauseSeconds += $pause->duration_seconds ?? 0;
                                                    } else {
                                                        $activePauseStart = $pause->started_at;
                                                    }
                                                }
                                            @endphp
                                            <small class="font-monospace trip-timer {{ $activePause ? 'text-amber-600' : 'text-gray-800' }}"
                                                   data-started-at="{{ $trip->started_at->toISOString() }}"
                                                   data-pause-seconds="{{ $completedPauseSeconds }}"
                                                   @if($activePauseStart)
                                                   data-active-pause-start="{{ $activePauseStart->toISOString() }}"
                                                   @endif
                                                   data-truck-id="{{ $truck->id }}">
                                                {{ $trip->getFormattedTripDuration() }}
                                            </small>
                                        @else
                                            <span class="text-gray-400">—</span>
                                        @endif
                                    </td>
                                    <td class="p-3">
                                        @if($activePause)
                                            <span class="px-2 py-0.5 text-xs font-medium rounded-md {{ $truck->status === 'breakdown' ? 'bg-red-100 text-red-700' : 'bg-amber-100 text-amber-700' }}">
                                                {{ \App\Models\TripPause::typeLabel($activePause->type) }}
                                            </span>
                                        @else
                                            <span class="text-gray-400">—</span>
                                        @endif
                                    </td>
                                    <td class="p-3">
                                        <button class="text-gray-400 hover:text-emerald-600" wire:click="openForceStatusModal({{ $truck->id }})" title="Изменить статус">
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

        <!-- Забои -->
