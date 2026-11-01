        <div x-show="tab === 'assignTab'" x-cloak class="mt-4" id="assignTab">
            <div class="bg-white rounded-xl border shadow-sm p-6">
                @php
                    $isSelectedTruckLoaded = $this->isSelectedTruckLoaded();
                    $loadedTruckInfo = $this->loaded_truck_info;
                @endphp

                @if($isSelectedTruckLoaded && $loadedTruckInfo)
                    <div class="p-4 bg-amber-50 border-l-4 border-amber-500 text-amber-700 rounded mb-4 text-sm">
                        <i class="fas fa-info-circle mr-1"></i>
                        <strong>Грузовик уже загружен</strong> — выберите только зону разгрузки.
                        <br>
                        <span class="mr-3">Забой: <strong>{{ $loadedTruckInfo['miner_name'] }}</strong></span>
                        <span>Загрузка: <strong>{{ $loadedTruckInfo['load_volume'] ?? '?' }} т</strong></span>
                    </div>
                @endif
                
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
                    <div>
                        <label class="block text-xs uppercase font-semibold text-gray-500 mb-1">Самосвал</label>
                        <select wire:model.live="selectedTruckId" class="w-full border-gray-300 rounded-md shadow-sm py-2 text-sm">
                            <option value="">Выберите</option>
                            @foreach($this->free_trucks as $truck)
                                <option value="{{ $truck->id }}">{{ $truck->number }} ({{ $truck->load_capacity }}т) — {{ $truck->getStatusLabel() }}</option>
                            @endforeach
                        </select>
                        @if($selectedTruckId && !$isSelectedTruckLoaded)
                            @php $selectedTruck = $this->free_trucks->firstWhere('id', $selectedTruckId); @endphp
                            @if($selectedTruck && !in_array($selectedTruck->status, ['free', 'completed']))
                                <small class="text-amber-600 text-xs mt-1 block"><i class="fas fa-exclamation-triangle"></i> Переназначение (текущий будет отменён)</small>
                            @endif
                        @endif
                    </div>

                    @if(!$isSelectedTruckLoaded)
                        <div>
                            <label class="block text-xs uppercase font-semibold text-gray-500 mb-1">Забой</label>
                            <select wire:model.live="selectedMinerId" class="w-full border-gray-300 rounded-md shadow-sm py-2 text-sm">
                                <option value="">Выберите</option>
                                @foreach($this->active_miners_with_rock as $miner)
                                    <option value="{{ $miner->id }}">{{ $miner->name_miner }} (@if($miner->currentRock){{ $miner->currentRock->name_rock }}@elseif($miner->rocks->first()){{ $miner->rocks->first()->name_rock }}@else—@endif)</option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label class="block text-xs uppercase font-semibold text-gray-500 mb-1">Маршрут</label>
                            <select wire:model.live="selectedOrderId" class="w-full border-gray-300 rounded-md shadow-sm py-2 text-sm" @if(!$selectedMinerId) disabled @endif>
                                <option value="">Выберите забой сначала</option>
                                @foreach($availableOrders as $order)
                                    <option value="{{ $order['id'] }}">{{ $order['dump_name'] }} ({{ $order['distance'] }} км)</option>
                                @endforeach
                            </select>
                            @if($availableOrders && count($availableOrders) === 0 && $selectedMinerId)
                                <small class="text-amber-600 text-xs mt-1 block">Нет маршрутов с доступными зонами</small>
                            @endif
                        </div>
                    @else
                        <div>
                            <label class="block text-xs uppercase font-semibold text-gray-500 mb-1">Забой (авто)</label>
                            <input type="text" class="w-full border-gray-300 rounded-md shadow-sm py-2 text-sm bg-slate-100" value="{{ $loadedTruckInfo['miner_name'] ?? '—' }}" disabled>
                        </div>
                        <div>
                            <label class="block text-xs uppercase font-semibold text-gray-500 mb-1">Порода <span class="text-gray-400 normal-case">(можно изменить)</span></label>
                            <select wire:model.live="loadedTruckRockId" class="w-full border-gray-300 rounded-md shadow-sm py-2 text-sm">
                                <option value="">Выберите породу</option>
                                @foreach($rocks as $rock)
                                    <option value="{{ $rock->id }}">{{ $rock->name_rock }}</option>
                                @endforeach
                            </select>
                        </div>
                    @endif

                    <div>
                        <label class="block text-xs uppercase font-semibold text-gray-500 mb-1">Зона разгрузки</label>
                        <select wire:model.live="selectedZoneId" class="w-full border-gray-300 rounded-md shadow-sm py-2 text-sm" @if(!$isSelectedTruckLoaded && !$selectedOrderId) disabled @endif>
                            <option value="">Автоматически (наименее заполненная)</option>
                            @foreach($availableZones as $zone)
                                <option value="{{ $zone['id'] }}">{{ $zone['dump_name'] ?? '' }} - {{ $zone['name'] }} ({{ $zone['fill'] }}%)</option>
                            @endforeach
                        </select>
                        @if($isSelectedTruckLoaded && count($availableZones) === 0)
                            <small class="text-red-600 text-xs mt-1 block"><i class="fas fa-exclamation-triangle"></i> Нет доступных зон для породы!</small>
                        @endif
                    </div>
                </div>

                <div class="flex gap-3">
                    <button wire:click="assignRoute" wire:loading.attr="disabled" class="px-6 py-2 bg-emerald-600 text-white rounded-md font-semibold uppercase text-sm hover:bg-emerald-700" @if(!$selectedTruckId) disabled @endif>
                        <span wire:loading.remove><i class="fas fa-check-circle mr-1"></i> Назначить</span>
                        <span wire:loading><i class="fas fa-spinner fa-spin mr-1"></i> Назначение...</span>
                    </button>
                    <button wire:click="assignAllFree" wire:loading.attr="disabled" class="px-6 py-2 bg-slate-200 text-slate-700 rounded-md font-semibold uppercase text-sm hover:bg-slate-300">
                        <span wire:loading.remove><i class="fas fa-sync mr-1"></i> Назначить всем</span>
                        <span wire:loading><i class="fas fa-spinner fa-spin mr-1"></i> Назначение...</span>
                    </button>
                </div>
            </div>
        </div>

        <!-- Зоны разгрузки -->
