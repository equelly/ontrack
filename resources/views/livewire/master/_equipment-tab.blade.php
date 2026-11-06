        <div wire:key="tab-equipment" class="mt-4 space-y-6">

            <!-- Самосвалы -->
            <div class="bg-white rounded-xl border shadow-sm overflow-hidden" x-data="{ showAddTruck: false }">
                <div class="p-4 border-b flex justify-between items-center">
                    <span class="font-bold text-gray-800 uppercase text-sm">Самосвалы</span>
                    <button @click="showAddTruck = !showAddTruck" class="text-emerald-600 hover:text-emerald-800 text-xs font-semibold uppercase">
                        + Добавить
                    </button>
                </div>

                <!-- Форма добавления самосвала -->
                <div x-show="showAddTruck" x-cloak class="p-4 bg-slate-50 border-b">
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-3 items-end">

                        <!-- Номер -->
                        <div>
                            <label class="block text-xs text-gray-500 uppercase mb-1">Номер</label>
                            <input type="text" wire:model="newTruckNumber" placeholder="Напр. 120" class="w-full border-gray-300 rounded-md shadow-sm py-2 text-sm @error('newTruckNumber') border-red-500 bg-red-50 @enderror">
                            @error('newTruckNumber')
                                <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- Модель -->
                        <div>
                            <label class="block text-xs text-gray-500 uppercase mb-1">Модель</label>
                            <select wire:model="newTruckModelId" class="w-full border-gray-300 rounded-md shadow-sm py-2 text-sm @error('newTruckModelId') border-red-500 bg-red-50 @enderror">
                                <option value="">Выберите модель</option>
                                @foreach(\App\Models\TruckModel::all() as $model)
                                    <option wire:key="model-{{ $loop->iteration }}" value="{{ $model->id }}">{{ $model->name }}</option>
                                @endforeach
                            </select>
                            @error('newTruckModelId')
                                <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- Топливо -->
                        <div>
                            <label class="block text-xs text-gray-500 uppercase mb-1">Топливо (л)</label>
                            <input type="number" wire:model="newTruckFuel" placeholder="Напр. 150" class="w-full border-gray-300 rounded-md shadow-sm py-2 text-sm @error('newTruckFuel') border-red-500 bg-red-50 @enderror" min="0">
                            @error('newTruckFuel')
                                <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- Кнопка -->
                        <button wire:click="addTruck" class="px-4 py-2 bg-emerald-600 text-white rounded-md text-xs font-semibold uppercase hover:bg-emerald-700">
                            Создать
                        </button>
                    </div>
                </div>

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
                                <tr wire:key="truck-{{ $loop->iteration }}" class="border-b hover:bg-slate-50">
                                    <td class="p-3 font-bold text-gray-800">
                                        <div class="flex items-center gap-2">
                                            <span>{{ $truck->number }}</span>
                                            <button wire:click="deleteTruck({{ $truck->id }})" wire:confirm="Удалить самосвал?" class="text-red-400 hover:text-red-600 text-xs">
                                                <i class="fas fa-trash-alt"></i>
                                            </button>
                                        </div>
                                    </td>
                                    <td class="p-3">
                                        <span class="px-2 py-0.5 text-xs font-medium rounded-md
                                            {{ $truck->status === 'breakdown' ? 'bg-red-100 text-red-700' :
                                            ($truck->status === 'free' ? 'bg-slate-200 text-slate-700' : 'bg-emerald-100 text-emerald-700') }}">
                                            {{ \App\Domain\TruckStatus::label($truck->status) }}
                                        </span>
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
            <div class="bg-white rounded-xl border shadow-sm overflow-hidden" x-data="{ showAddMiner: false }">
                <div class="p-4 border-b flex justify-between items-center">
                    <span class="font-bold text-gray-800 uppercase text-sm">Экскаваторы (Забои)</span>
                    <button @click="showAddMiner = !showAddMiner" class="text-emerald-600 hover:text-emerald-800 text-xs font-semibold uppercase">
                        + Добавить
                    </button>
                </div>

                {{-- Форма добавления экскаватора --}}
                <div x-show="showAddMiner" x-cloak class="p-4 bg-slate-50 border-b">
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-3 items-end">
                        <!-- Название -->
                        <div>
                            <label class="block text-xs text-gray-500 uppercase mb-1">Название *</label>
                            <input type="text" wire:model="newMinerName" placeholder="Напр. ЭКГ-10" class="w-full border-gray-300 rounded-md shadow-sm py-2 text-sm @error('newMinerName') border-red-500 bg-red-50 @enderror">
                            @error('newMinerName')
                                <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                            @enderror
                        </div>
                        <!-- Ёмкость ковша -->
                        <div>
                            <label class="block text-xs text-gray-500 uppercase mb-1">Ёмкость ковша (т)</label>
                            <input type="number" step="0.1" wire:model="newMinerCapacityPerTrip" placeholder="Напр. 25" class="w-full border-gray-300 rounded-md shadow-sm py-2 text-sm @error('newMinerCapacityPerTrip') border-red-500 bg-red-50 @enderror" min="0" max="500">
                            @error('newMinerCapacityPerTrip')
                                <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                            @enderror
                        </div>
                        <!-- Норма погрузки -->
                        <div>
                            <label class="block text-xs text-gray-500 uppercase mb-1">Норма погрузки (сек)</label>
                            <input type="number" wire:model="newMinerTargetLoadTime" placeholder="Напр. 180" class="w-full border-gray-300 rounded-md shadow-sm py-2 text-sm @error('newMinerTargetLoadTime') border-red-500 bg-red-50 @enderror" min="25" max="3600">
                            @error('newMinerTargetLoadTime')
                                <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                            @enderror
                        </div>
                        <!-- Текущая порода -->
                        <div>
                            <label class="block text-xs text-gray-500 uppercase mb-1">Текущая порода</label>
                            <select wire:model="newMinerRockId" class="w-full border-gray-300 rounded-md shadow-sm py-2 text-sm @error('newMinerRockId') border-red-500 bg-red-50 @enderror">
                                <option value="">— Не указана —</option>
                                @foreach(\App\Models\Rock::all() as $rock)
                                    <option wire:key="rock-{{ $loop->iteration }}" value="{{ $rock->id }}">{{ $rock->name_rock }}</option>
                                @endforeach
                            </select>
                            @error('newMinerRockId')
                                <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>
                    <div class="mt-3 flex justify-end">
                        <button wire:click="addMiner" wire:loading.attr="disabled" class="px-4 py-2 bg-emerald-600 text-white rounded-md text-xs font-semibold uppercase hover:bg-emerald-700 disabled:bg-emerald-300">
                            <span wire:loading.remove><i class="fas fa-plus mr-1"></i> Создать</span>
                            <span wire:loading><i class="fas fa-spinner fa-spin mr-1"></i> Создание...</span>
                        </button>
                    </div>
                </div>

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
                                <tr wire:key="miner-{{ $loop->iteration }}" class="border-b hover:bg-slate-50">
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
