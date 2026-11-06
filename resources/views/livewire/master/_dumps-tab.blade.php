        <div wire:key="tab-dumps" class="mt-4 space-y-4">
            <div class="bg-white p-4 rounded-xl border shadow-sm flex gap-2">
                <input type="text" wire:model="newDumpName" placeholder="Название новой перегрузки" class="flex-1 border-gray-300 rounded-md shadow-sm py-2">
                @error('newDumpName')
                    <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                @enderror
                <button wire:click="addDump" class="px-4 py-2 bg-emerald-600 text-white rounded-md font-semibold uppercase text-sm hover:bg-emerald-700">Добавить</button>
            </div>

            @foreach($dumps as $dump)
                <div wire:key="dump-{{ $loop->iteration }}" class="bg-white rounded-xl border shadow-sm overflow-hidden {{ $dump->is_service_point ? 'border-amber-400 border-2' : '' }}">
                    <div class="p-4 border-b bg-slate-50 flex justify-between items-center gap-2">
                        <div class="flex items-center gap-2 flex-1 min-w-0">
                            <h3 class="font-bold text-gray-800 uppercase text-sm truncate">{{ $dump->name_dump }}</h3>
                            @if($dump->is_service_point)
                                <span class="px-2 py-0.5 bg-amber-100 text-amber-800 text-xs font-bold rounded-full border border-amber-300 whitespace-nowrap" title="Сервисная точка (отстой / обслуживание / заправка). Используется как стартовая точка для новых самосвалов без истории рейсов.">
                                    <i class="fas fa-tools"></i> Сервисная
                                </span>
                            @endif
                            {{-- Бейдж обваловки — компактный, в заголовке --}}
                            @if($this->activeBermRequests->isNotEmpty() && $this->activeBermRequests->contains('dump_id', $dump->id))
                                @foreach($this->activeBermRequests->where('dump_id', $dump->id) as $berm)
                                    <span wire:key="berm-{{ $loop->iteration }}" class="px-2 py-0.5 bg-orange-100 text-orange-800 text-xs font-bold rounded-full border border-orange-300 whitespace-nowrap flex items-center gap-1">
                                        <i class="fas fa-mountain"></i>
                                        Обваловать {{ $berm->zone?->name_zone }}:
                                        <span class="text-emerald-600">{{ $berm->trucks_completed }}</span><span class="text-gray-400">/</span><span class="text-red-600">{{ $berm->trucks_needed }}</span>
                                        <button wire:click="cancelBermRequest({{ $berm->id }})" wire:confirm="Отменить обваловку?" class="text-red-500 hover:text-red-700 ml-0.5 leading-none" title="Отменить">×</button>
                                    </span>
                                @endforeach
                            @endif
                        </div>
                        <div class="flex gap-2 items-center">
                            <button wire:click="toggleServicePoint({{ $dump->id }})"
                                    wire:loading.attr="disabled"
                                    class="text-xs px-2 py-1 rounded font-semibold transition {{ $dump->is_service_point ? 'bg-amber-500 text-white hover:bg-amber-600' : 'bg-slate-200 text-slate-700 hover:bg-slate-300' }}"
                                    title="{{ $dump->is_service_point ? 'Снять статус сервисной точки' : 'Сделать сервисной точкой (отстой / обслуживание)' }}">
                                <i class="fas fa-tools mr-1"></i>
                                {{ $dump->is_service_point ? 'Сервисная' : 'Сделать сервисной' }}
                            </button>
                            <button wire:click="toggleAddZone({{ $dump->id }})" class="text-blue-500 hover:text-blue-700 text-xs font-semibold uppercase">
                                + Добавить зону
                            </button>
                            <button wire:click="deleteDump({{ $dump->id }})" wire:confirm="Удалить перегрузку?" class="text-red-400 hover:text-red-600 text-sm"><i class="fas fa-trash"></i></button>
                        </div>
                    </div>

                    <!-- Форма добавления зоны -->
                    @if($addZoneDumpId === $dump->id)
                        <div class="p-4 bg-blue-50 border-b grid grid-cols-1 md:grid-cols-5 gap-3 items-end">
                            <div class="md:col-span-2">
                                <label class="text-xs text-gray-500 uppercase">Название зоны</label>
                                <input type="text" wire:model="newZoneName" placeholder="Зона 1" class="w-full border-gray-300 rounded-md text-sm py-1 @error('newZoneName') border-red-500 bg-red-50 @enderror">
                                @error('newZoneName') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label class="text-xs text-gray-500 uppercase">Порода</label>
                                <select wire:model="newZoneRockId" class="w-full border-gray-300 rounded-md text-sm py-1 @error('newZoneRockId') border-red-500 bg-red-50 @enderror">
                                    <option value="">Выберите</option>
                                    @foreach($rocks as $rock)<option value="{{ $rock->id }}">{{ $rock->name_rock }}</option>@endforeach
                                </select>
                                @error('newZoneRockId') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label class="text-xs text-gray-500 uppercase">Вместимость (м³)</label>
                                <input type="number" wire:model="newZoneCapacity" class="w-full border-gray-300 rounded-md text-sm py-1 @error('newZoneCapacity') border-red-500 bg-red-50 @enderror">
                                @error('newZoneCapacity') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                            </div>
                            <div class="flex gap-2">
                                <div class="flex-1">
                                    <label class="text-xs text-gray-500 uppercase">Текущ. объем (м³)</label>
                                    <input type="number" wire:model="newZoneVolume" class="w-full border-gray-300 rounded-md text-sm py-1 @error('newZoneVolume') border-red-500 bg-red-50 @enderror">
                                    @error('newZoneVolume') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                                </div>
                                <button wire:click="addZone({{ $dump->id }})" class="px-3 py-2 bg-emerald-600 text-white rounded-md text-xs uppercase font-bold whitespace-nowrap">Создать</button>
                            </div>
                        </div>
                    @endif

                    <!-- Список зон этой перегрузки с инлайн-редактированием -->
                    @if($dump->zones->isNotEmpty())
                    <table class="w-full text-sm">
                        <thead class="bg-slate-50 border-b">
                            <tr>
                                <th class="text-left p-2 font-semibold text-gray-600 w-1/4">Зона</th>
                                <th class="text-left p-2 font-semibold text-gray-600">Порода</th>
                                <th class="text-left p-2 font-semibold text-gray-600">Текущ. (верт.)</th>
                                <th class="text-left p-2 font-semibold text-gray-600">Вмест. (верт.)</th>
                                <th class="text-center p-2 font-semibold text-gray-600">Принимает</th>
                                <th class="text-center p-2 font-semibold text-gray-600">Обваловать</th>
                                <th class="text-center p-2 font-semibold text-gray-600">Удалить</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($dump->zones as $zone)
                                    @php
                                        $fillPercent = $zone->capacity > 0 ? min($zone->volume / $zone->capacity * 100, 100) : 0;
                                        $currentRockId = $zone->rocks->first()?->id;
                                    @endphp
                                <tr class="border-b {{ !$zone->delivery ? 'opacity-50 bg-slate-50' : '' }}">
                                    <td class="p-2">
                                        <input type="text" wire:change="updateZoneField({{ $zone->id }}, 'name_zone', $event.target.value)" value="{{ $zone->name_zone }}" class="border-gray-300 rounded-md text-sm py-1 w-full">
                                        <div class="flex items-center gap-2">
                                                <div class="w-24 bg-gray-200 rounded-full h-2.5">
                                                    <div class="h-2.5 rounded-full {{ $fillPercent > 90 ? 'bg-red-500' : ($fillPercent > 70 ? 'bg-amber-500' : 'bg-emerald-500') }}" style="width: {{ $fillPercent }}%"></div>
                                                </div>
                                                <span class="text-xs text-gray-500">{{ round($fillPercent) }}%</span>
                                            </div>
                                    </td>
                                    <td class="p-2">
                                        <select wire:change="updateZoneField({{ $zone->id }}, 'rock_id', $event.target.value)" class="border-gray-300 rounded-md text-sm py-1 w-full">
                                            <option value="">Не указана</option>
                                            @foreach($rocks as $rock)
                                                <option wire:key="rock-{{ $loop->iteration }}" value="{{ $rock->id }}" @if($zone->rocks->first()?->id == $rock->id) selected @endif>{{ $rock->name_rock }}</option>
                                            @endforeach
                                        </select>
                                    </td>
                                    <td class="p-2">
                                        <div class="flex items-center gap-1">
                                            <input type="number" wire:change="updateZoneField({{ $zone->id }}, 'volume', $event.target.value * 380)" value="{{ round($zone->volume / 380) }}" class="border-gray-300 rounded-md text-sm py-1 w-16 text-center" step="1">
                                            {{-- Класс whitespace-nowrap не даст тексту разорваться на две строки --}}
                                            <span class="text-xs text-gray-400 whitespace-nowrap">({{ number_format($zone->volume / 1000, 0, '.', ' ') }}&nbsp;т.м³)</span>
                                        </div>
                                    </td>
                                    <td class="p-2">
                                        <div class="flex items-center gap-1">
                                            <input type="number" wire:change="updateZoneField({{ $zone->id }}, 'capacity', $event.target.value * 380)" value="{{ round($zone->capacity / 380) }}" class="border-gray-300 rounded-md text-sm py-1 w-16 text-center" step="1">
                                            <span class="text-xs text-gray-400">({{ number_format($zone->capacity, 0) }})</span>
                                        </div>
                                    </td>
                                    <td class="p-2 text-center">
                                        <input type="checkbox" wire:change="updateZoneField({{ $zone->id }}, 'delivery', $event.target.checked)" {{ $zone->delivery ? 'checked' : '' }} class="rounded text-emerald-600 h-5 w-5 cursor-pointer">
                                    </td>
                                    <td class="p-2 text-center">
                                        @if($zone->delivery)
                                            <span class="text-gray-300" title="Зона открыта — обваловка не нужна">
                                                <i class="fas fa-mountain"></i>
                                            </span>
                                        @else
                                            <button wire:click="openBermModal({{ $zone->id }})" class="text-orange-500 hover:text-orange-700" title="Зона закрыта — запросить обваловку">
                                                <i class="fas fa-mountain"></i>
                                            </button>
                                        @endif
                                    </td>
                                    <td class="p-2 text-center">
                                        <button wire:click="deleteZone({{ $zone->id }})" wire:confirm="Удалить зону? Привязанные к ней маршруты станут неактивными." class="text-red-400 hover:text-red-600">
                                            <i class="fas fa-trash-alt"></i>
                                        </button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                    @else
                        <div class="p-4 text-center text-gray-500 text-sm">Нет зон. Нажмите "+ Добавить зону".</div>
                    @endif

                </div>
            @endforeach
        </div>
