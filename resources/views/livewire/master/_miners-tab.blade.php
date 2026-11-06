        <div wire:key="tab-miners" class="mt-4 space-y-4">
            {{-- Форма добавления экскаватора (забоя) --}}
            <div class="bg-white p-4 rounded-xl border shadow-sm">
                <h3 class="text-sm font-bold text-gray-700 uppercase mb-3 flex items-center gap-2">
                    <i class="fas fa-plus-circle text-emerald-600"></i>
                    Добавить экскаватор (забой)
                </h3>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
                    <div>
                        <label class="block text-xs text-gray-500 mb-1">Название *</label>
                        <input type="text" wire:model="newMinerName" placeholder="Напр. ЭКГ-10" class="w-full border-gray-300 rounded-md shadow-sm py-2 text-sm @error('newMinerName') border-red-500 bg-red-50 @enderror">
                        @error('newMinerName')
                            <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                        @enderror
                    </div>
                    <div>
                        <label class="block text-xs text-gray-500 mb-1">Ёмкость ковша (т)</label>
                        <input type="number" step="0.1" wire:model="newMinerCapacityPerTrip" placeholder="Напр. 25" class="w-full border-gray-300 rounded-md shadow-sm py-2 text-sm @error('newMinerCapacityPerTrip') border-red-500 bg-red-50 @enderror" min="0" max="500">
                        @error('newMinerCapacityPerTrip')
                            <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                        @enderror
                    </div>
                    <div>
                        <label class="block text-xs text-gray-500 mb-1">Норма погрузки (сек)</label>
                        <input type="number" wire:model="newMinerTargetLoadTime" placeholder="Напр. 180" class="w-full border-gray-300 rounded-md shadow-sm py-2 text-sm @error('newMinerTargetLoadTime') border-red-500 bg-red-50 @enderror" min="25" max="3600">
                        @error('newMinerTargetLoadTime')
                            <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                        @enderror
                    </div>
                    <div>
                        <label class="block text-xs text-gray-500 mb-1">Текущая порода</label>
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
                    <button wire:click="addMiner" wire:loading.attr="disabled" class="px-4 py-2 bg-emerald-600 text-white rounded-md font-semibold uppercase text-sm hover:bg-emerald-700 disabled:bg-emerald-300">
                        <span wire:loading.remove><i class="fas fa-plus mr-1"></i> Добавить экскаватор</span>
                        <span wire:loading><i class="fas fa-spinner fa-spin mr-1"></i> Добавление...</span>
                    </button>
                </div>
            </div>
            <div class="bg-white rounded-xl border shadow-sm overflow-hidden">
                <div class="p-4 border-b font-bold text-gray-800 uppercase text-sm">Список забоев</div>
                <ul class="divide-y divide-gray-100">
                    @foreach($miners as $miner)
                        <li wire:key="miner-{{ $loop->iteration }}" class="p-3 hover:bg-slate-50">
                            <div class="flex justify-between items-center">
                                <span class="font-medium text-gray-800">{{ $miner->name_miner }}</span>
                                <div class="flex gap-3 items-center">
                                    <button wire:click="editMinerDistances({{ $miner->id }})" class="text-blue-500 hover:text-blue-700 text-xs font-semibold uppercase">
                                        Расстояния <i class="fas fa-route ml-1"></i>
                                    </button>
                                    <button wire:click="deleteMiner({{ $miner->id }})" wire:confirm="Удалить забой?" class="text-red-400 hover:text-red-600 text-sm"><i class="fas fa-trash"></i></button>
                                </div>
                            </div>

                            <!-- Раскрывающаяся панель расстояний -->
                            @if($editingMinerId === $miner->id)
                                <div class="mt-4 pt-4 border-t bg-slate-50 p-3 rounded-lg">
                                    <h6 class="text-xs font-bold text-gray-500 uppercase mb-3">Расстояние до мест разгрузки (км)</h6>
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                                        @foreach($dumps as $dump)
                                            @php
                                                $dist = \App\Models\MinerDumpDistance::where('miner_id', $miner->id)->where('dump_id', $dump->id)->first();
                                                $distVal = $dist ? $dist->distance_km : '';
                                            @endphp
                                            <div class="flex items-center gap-2">
                                                <span class="flex-1 text-sm text-gray-700">{{ $dump->name_dump }}</span>
                                                <input type="number" step="0.1" wire:change="saveDistance({{ $miner->id }}, {{ $dump->id }}, $event.target.value)" value="{{ $distVal }}" class="w-24 border-gray-300 rounded-md py-1 text-sm" placeholder="км">
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            @endif
                        </li>
                    @endforeach
                </ul>
            </div>
        </div>
