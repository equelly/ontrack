    <!-- Модальное окно принудительной смены статуса -->
    @if($forceStatusTruckId)
        @php
            $truckToEdit = \App\Models\Truck::find($forceStatusTruckId);
            $statusLabels = [
                'free' => ['label' => 'Свободен', 'color' => 'emerald'],
                'to_miner' => ['label' => 'К забою', 'color' => 'cyan'],
                'loading' => ['label' => 'Погрузка', 'color' => 'amber'],
                'transporting' => ['label' => 'Перевозка', 'color' => 'blue'],
                'unloading' => ['label' => 'Разгрузка', 'color' => 'slate'],
                'breakdown' => ['label' => 'Поломка', 'color' => 'red'],
                'delayed' => ['label' => 'Задержка', 'color' => 'amber'],
            ];
            $currentStatus = $truckToEdit ? ($statusLabels[$truckToEdit->status] ?? ['label' => $truckToEdit->status, 'color' => 'slate']) : null;
            $previousStatusKey = $truckToEdit?->before_breakdown;
            $previousStatus = $previousStatusKey ? ($statusLabels[$previousStatusKey] ?? null) : null;
        @endphp
        <div wire:key="force-modal" class="fixed inset-0 z-[9999] flex items-center justify-center bg-black/70 p-4" style="display: flex;">
            <div class="bg-white rounded-xl shadow-2xl w-full max-w-md">
                <div class="p-4 border-b flex justify-between items-center">
                    <h5 class="font-bold text-gray-800 uppercase"><i class="fas fa-exclamation-triangle text-amber-500 mr-2"></i> Изменить статус: {{ $truckToEdit?->number }}</h5>
                    <button wire:click="closeForceStatusModal" class="text-gray-500 hover:text-gray-700 text-2xl leading-none">&times;</button>
                </div>
                <div class="p-4">
                    <select wire:model.live="forceStatusNew" class="w-full border-gray-300 rounded-md py-2 text-sm">
                        @if($previousStatus)<option value="{{ $previousStatusKey }}">Вернуться к "{{ $previousStatus['label'] }}"</option>@endif
                        <option value="">Оставить "{{ $currentStatus['label'] ?? '' }}"</option>
                        @foreach($this->available_statuses as $statusKey => $statusLabel)<option value="{{ $statusKey }}">{{ $statusLabel }}</option>@endforeach
                    </select>
                </div>
                <div class="p-4 border-t flex justify-end gap-2">
                    <button wire:click="closeForceStatusModal" class="px-4 py-2 bg-slate-200 text-slate-700 rounded-md uppercase text-sm">Отмена</button>
                    <button wire:click="forceChangeStatus" wire:loading.attr="disabled" class="px-4 py-2 bg-amber-500 text-white rounded-md uppercase text-sm hover:bg-amber-600" @if(!$forceStatusNew) disabled @endif>Изменить</button>
                </div>
            </div>
        </div>
    @endif

    <!-- Модальное окно редактирования маршрута -->
    @if($editOrderId)
        @php $orderToEdit = \App\Models\MiningOrder::with(['miner.rocks', 'dump'])->find($editOrderId); $currentDistance = $editDistances[$editDumpId] ?? $orderToEdit?->distance_km; @endphp
        <div wire:key="edit-order-modal" class="fixed inset-0 z-[9999] flex items-center justify-center bg-black/70 p-4" style="display: flex;">
            <div class="bg-white rounded-xl shadow-2xl w-full max-w-md">
                <div class="p-4 border-b flex justify-between items-center">
                    <h5 class="font-bold text-gray-800 uppercase">Редактирование #{{ $editOrderId }}</h5>
                    <button wire:click="closeEditOrder" class="text-gray-500 hover:text-gray-700 text-2xl leading-none">&times;</button>
                </div>
                <div class="p-4 space-y-3">
                    <div><label class="block text-xs uppercase text-gray-500 mb-1">Перегрузка</label><select wire:model.live="editDumpId" class="w-full border-gray-300 rounded-md py-2 text-sm">@foreach($dumps as $dump)<option value="{{ $dump->id }}">{{ $dump->name_dump }} ({{ $editDistances[$dump->id] ?? '—' }} км)</option>@endforeach</select></div>
                    <div><label class="block text-xs uppercase text-gray-500 mb-1">Статус</label><label class="flex items-center gap-2"><input type="checkbox" wire:model.live="editActive" class="rounded text-emerald-600"> <span class="text-sm">{{ $editActive ? 'Активен' : 'Неактивен' }}</span></label></div>
                </div>
                <div class="p-4 border-t flex justify-end gap-2"><button wire:click="closeEditOrder" class="px-4 py-2 bg-slate-200 text-slate-700 rounded-md uppercase text-sm">Отмена</button><button wire:click="saveOrder" class="px-4 py-2 bg-emerald-600 text-white rounded-md uppercase text-sm hover:bg-emerald-700">Сохранить</button></div>
            </div>
        </div>
    @endif

    <!-- Модальное окно смены статуса забоя -->
    @if($editMinerStatusId)
        @php $currentMiner = \App\Models\Miner::find($editMinerStatusId); @endphp
        <div wire:key="miner-status-modal" class="fixed inset-0 z-[9999] flex items-center justify-center bg-black/70 p-4" style="display: flex;">
            <div class="bg-white rounded-xl shadow-2xl w-full {{ $showMinerWarning ? 'max-w-3xl' : 'max-w-md' }}">
                <div class="p-4 border-b flex justify-between items-center {{ $showMinerWarning ? 'bg-red-50' : '' }}"><h5 class="font-bold text-gray-800 uppercase">Статус забоя: {{ $currentMiner?->name_miner }}</h5><button wire:click="closeMinerStatusModal" class="text-gray-500 hover:text-gray-700 text-2xl leading-none">&times;</button></div>
                <div class="p-4">
                    @if($showMinerWarning)
                        <div class="p-4 bg-red-50 border-l-4 border-red-500 text-red-700 rounded mb-4">{{ $minerStatusWarning }}</div>
                        @if($minerSafetyCheck && !empty($minerSafetyCheck['alternatives']))
                            <div class="overflow-x-auto"><table class="w-full text-sm border"><thead class="bg-slate-50"><tr><th class="p-2 text-left">Забой</th><th class="p-2 text-left">Оптим.</th><th class="p-2 text-left">Текущ.</th><th class="p-2 text-left">Вмест.</th></tr></thead><tbody>@foreach($minerSafetyCheck['alternatives'] as $alt)<tr class="border-b"><td class="p-2">{{ $alt['name'] }}</td><td class="p-2">{{ $alt['recommended'] }}</td><td class="p-2">{{ $alt['current'] }}</td><td class="p-2 {{ $alt['capacity'] > 0 ? 'text-emerald-600' : 'text-red-600' }}">{{ $alt['capacity'] }}</td></tr>@endforeach</tbody></table></div>
                        @endif
                        <div class="flex justify-end gap-2 mt-4"><button wire:click="cancelMinerStatusChange" class="px-4 py-2 bg-slate-200 text-slate-700 rounded-md uppercase text-sm">Отменить</button><button wire:click="forceMinerStatus" class="px-4 py-2 bg-red-600 text-white rounded-md uppercase text-sm hover:bg-red-700">Всё равно остановить</button></div>
                    @else
                        <div class="mb-4"><label class="block text-xs uppercase text-gray-500 mb-1">Новый статус</label><select wire:model.live="editMinerStatusNew" class="w-full border-gray-300 rounded-md py-2 text-sm">@foreach($this->miner_statuses as $status => $label)<option value="{{ $status }}">{{ $label }}</option>@endforeach</select></div>
                        <div class="flex justify-end gap-2"><button wire:click="closeMinerStatusModal" class="px-4 py-2 bg-slate-200 text-slate-700 rounded-md uppercase text-sm">Отмена</button><button wire:click="setMinerStatus" class="px-4 py-2 bg-emerald-600 text-white rounded-md uppercase text-sm hover:bg-emerald-700">Подтвердить</button></div>
                    @endif
                </div>
            </div>
        </div>
    @endif

