        <div x-show="tab === 'pausesTab'" x-cloak class="mt-4" id="pausesTab">
            @php
                $pauseStats = $this->pause_stats;
                $minerDelays = $this->miner_delays;
            @endphp

            <!-- Фильтры -->
            <div class="bg-white rounded-xl border shadow-sm p-4">
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <div>
                        <label class="block text-xs uppercase font-semibold text-gray-500 mb-1">Период:</label>
                        <select wire:model.live="pausePeriod" class="w-full border-gray-300 rounded-md py-2 text-sm">
                            <option value="shift">За смену</option>
                            <option value="today">За сегодня</option>
                            <option value="week">За неделю</option>
                            <option value="month">За месяц</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs uppercase font-semibold text-gray-500 mb-1"><i class="fas fa-truck mr-1"></i>Тип простоев ТС:</label>
                        <div class="border rounded p-2 bg-slate-50 max-h-[120px] overflow-y-auto text-sm">
                            @foreach(\App\Models\TripPause::types() as $typeKey => $typeLabel)
                                <label class="flex items-center gap-2 mb-1">
                                    <input type="checkbox" value="{{ $typeKey }}" wire:model.live="pauseTypes" class="rounded text-emerald-600">
                                    {{ $typeLabel }}
                                </label>
                            @endforeach
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs uppercase font-semibold text-gray-500 mb-1"><i class="fas fa-mountain mr-1"></i>Тип простоев забоев:</label>
                        <div class="border rounded p-2 bg-slate-50 max-h-[120px] overflow-y-auto text-sm">
                            @foreach(\App\Models\MinerPause::types() as $typeKey => $typeLabel)
                                <label class="flex items-center gap-2 mb-1">
                                    <input type="checkbox" value="{{ $typeKey }}" wire:model.live="minerPauseTypes" class="rounded text-emerald-600">
                                    {{ $typeLabel }}
                                </label>
                            @endforeach
                        </div>
                    </div>
                    <div class="text-xs text-gray-500 self-end">
                        {{ $pauseStats['period_label'] }}: <strong class="text-gray-800">{{ $pauseStats['total_count'] }}</strong> инц. ТС (<strong class="text-{{ $pauseStats['total_seconds'] > 0 ? 'red' : 'gray' }}-600">{{ $pauseStats['total_formatted'] }}</strong>)<br>
                        <strong class="text-gray-800">{{ $minerDelays['total_count'] }}</strong> задержек забоев (<strong class="text-{{ $minerDelays['total_minutes'] > 0 ? 'red' : 'gray' }}-600">{{ $minerDelays['total_formatted'] }}</strong>)
                        @php $waitingUnloadingCount = $trucks->where('status', 'waiting_unloading')->count(); @endphp
                        @if($waitingUnloadingCount > 0)<br><strong class="text-red-600">{{ $waitingUnloadingCount }}</strong> ожидают назначения!@endif
                    </div>
                </div>
            </div>

            <!-- Ожидают назначения зоны -->
            @php $trucksWaitingUnloading = $trucks->where('status', 'waiting_unloading'); @endphp
            @if($trucksWaitingUnloading->count() > 0)
            <div class="bg-white rounded-xl border shadow-sm p-4">
                <h6 class="text-red-600 font-bold uppercase text-sm mb-3"><i class="fas fa-exclamation-triangle mr-1"></i> Ожидают назначения зоны разгрузки</h6>
                <div class="flex flex-wrap gap-2">
                    @foreach($trucksWaitingUnloading as $truck)
                        @php $trip = $truck->trips->first(); @endphp
                        <div class="px-3 py-1 border border-red-300 bg-red-50 rounded-md text-sm">
                            <strong>{{ $truck->number }}</strong> <span class="text-gray-500">({{ $trip?->rock?->name_rock ?? '—' }})</span>
                        </div>
                    @endforeach
                </div>
            </div>
            @endif

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                <!-- Таблица простоев ТС -->
                <div class="bg-white rounded-xl border shadow-sm overflow-hidden">
                    <div class="p-4 border-b font-bold text-gray-800 uppercase text-sm flex justify-between items-center">
                        <span><i class="fas fa-truck mr-1"></i> Самосвалы</span>
                        @if($pauseStats['active_count'] > 0)<span class="px-2 py-0.5 bg-red-100 text-red-700 rounded text-xs">{{ $pauseStats['active_count'] }} активн.</span>@endif
                    </div>
                    <div class="overflow-x-auto max-h-[400px] overflow-y-auto">
                        <table class="w-full text-sm">
                            <thead class="bg-slate-50 border-b sticky top-0">
                                <tr><th class="p-2 text-left">Время</th><th class="p-2 text-left">ТС</th><th class="p-2 text-left">Тип</th><th class="p-2 text-left">Длит.</th></tr>
                            </thead>
                            <tbody>
                                @foreach($pauseStats['pauses']->take(20) as $pause)
                                    <tr class="border-b {{ $pause->ended_at ? '' : 'bg-amber-50' }}">
                                        <td class="p-2 text-gray-500">{{ $pause->started_at->format('H:i') }}</td>
                                        <td class="p-2 font-medium">{{ $pause->truck?->number ?? '—' }}</td>
                                        <td class="p-2"><span class="px-2 py-0.5 text-xs rounded {{ $pause->type === 'breakdown' ? 'bg-red-100 text-red-700' : 'bg-amber-100 text-amber-700' }}">{{ \App\Models\TripPause::typeLabel($pause->type) }}</span></td>
                                        <td class="p-2">{{ $pause->getFormattedDuration() }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Таблица простоев забоев -->
                <div class="bg-white rounded-xl border shadow-sm overflow-hidden">
                    <div class="p-4 border-b font-bold text-gray-800 uppercase text-sm flex justify-between items-center">
                        <span><i class="fas fa-mountain mr-1"></i> Забои</span>
                        @if($minerDelays['total_count'] > 0)<span class="px-2 py-0.5 bg-red-100 text-red-700 rounded text-xs">{{ $minerDelays['total_count'] }}</span>@endif
                    </div>
                    <div class="overflow-x-auto max-h-[400px] overflow-y-auto">
                        <table class="w-full text-sm">
                            <thead class="bg-slate-50 border-b sticky top-0">
                                <tr><th class="p-2 text-left">Забой</th><th class="p-2 text-left">Тип</th><th class="p-2 text-left">Начало</th><th class="p-2 text-left">Длит.</th></tr>
                            </thead>
                            <tbody>
                                @foreach($minerDelays['pauses']->take(20) as $pause)
                                    <tr class="border-b {{ $pause->type === 'breakdown' ? 'bg-red-50' : ($pause->ended_at ? '' : 'bg-amber-50') }}">
                                        <td class="p-2 font-medium">{{ $pause->miner?->name_miner ?? '—' }}</td>
                                        <td class="p-2"><span class="px-2 py-0.5 text-xs rounded {{ $pause->type === 'breakdown' ? 'bg-red-100 text-red-700' : 'bg-amber-100 text-amber-700' }}">{{ $pause->getTypeLabel() }}</span></td>
                                        <td class="p-2 text-gray-500">{{ $pause->started_at->format('d.m H:i') }}</td>
                                        <td class="p-2">{{ $pause->getFormattedDuration() }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Аналитика скоростей по маршрутам + тренды -->
