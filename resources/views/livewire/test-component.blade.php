<div class="p-6 space-y-6">
    <h3 class="text-3xl font-bold">🚧 ДИСПЕТЧЕР: Miners</h3>

    {{-- 🔥 3 КНОПКИ: --}}
    <div class="mb-8 flex gap-4">
        @if(!$editMode)
            <button wire:click="toggleEditMode" 
                    class="p-3 py-4 bg-gradient-to-r from-orange-500 to-amber-600 font-black rounded-3xl shadow-2xl">
                ✏️ РУЧНОЕ РЕДАКТИРОВАНИЕ
            </button>
        @else
            <button wire:click="toggleEditMode" 
                    class="p-3 py-4 bg-gradient-to-r from-gray-500 to-gray-600 font-black rounded-3xl shadow-2xl">
                ❌ ОТМЕНА
            </button>
        @endif
        
        <button wire:click="saveMiningOrders" 
                class="px-12 py-4 bg-gradient-to-r from-emerald-500 to-teal-600 font-black rounded-3xl shadow-2xl
                       {{ !isset($distributionResult['distribution']) ? 'opacity-50 cursor-not-allowed' : '' }}"
                {{ !isset($distributionResult['distribution']) ? 'disabled' : '' }}>
            💾 СОХРАНИТЬ МАРШРУТЫ 
            ({{ isset($distributionResult['distribution']) ? count($distributionResult['distribution']) : 0 }})
        </button>
    
</div>


@if (session('success'))
<div style="position: fixed; top: 20px; right: 20px; z-index: 999999 !important; background: linear-gradient(135deg, #10b981, #059669); color: white; padding: 24px 32px; border-radius: 24px; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.5); border: 4px solid #047857; min-width: 400px; backdrop-filter: blur(20px); animation: slideInRight 0.5s cubic-bezier(0.68, -0.55, 0.265, 1.55), fadeOut 4s 2.5s forwards;">
    <div class="flex items-center justify-between">
        <div class="flex items-center gap-4">
            <div class="text-3xl">✅</div>
            <div class="font-black tracking-wide">{{ session('success') }}</div>
        </div>
        <button type="button" onclick="this.closest('div').style.display='none'" 
                class="text-2xl hover:scale-110 transition-transform hover:bg-white/20 px-3 py-1 rounded-xl font-bold">×</button>
    </div>
</div>

<style>
@keyframes slideInRight {
    from { transform: translateX(100%); opacity: 0; }
    to { transform: translateX(0); opacity: 1; }
}
@keyframes fadeOut {
    to { opacity: 0; transform: translateX(100%); }
}
</style>
@endif


    {{-- Форма распределения --}}
<div class="mb-6 p-4 bg-gray-50 rounded-lg">
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div>
            <label>Режим:</label>
            <select wire:model="mode" class="w-full border rounded p-2">
                <option value="balance">⚖️ Баланс</option>
                <option value="volume">📦 Объём</option>
                <option value="distance">🏃 Расстояние</option>
            </select>
        </div>
        
        <div class="flex items-end gap-2">
            <label class="flex items-center gap-2">
                <input type="checkbox" wire:model="activeZonesOnly" checked>
                Только активные зоны
            </label>
            <button 
                wire:click="distribute"
                class="bg-blue-600 p-3 py-2 rounded hover:bg-blue-700"
            >
                Распределить
            </button>
        </div>
    </div>
</div>

{{-- Результат --}}
@if($distributionResult)
    <div class="mt-6 p-4 bg-green-50 border rounded-lg">
        <h3>РЕЗУЛЬТАТЫ РАСПРЕДЕЛЕНИЯ:</h3>
       
        @if($editMode)
        <div class="mb-4 p-4 bg-gradient-to-r from-indigo-100 to-purple-100 border-2 border-indigo-300 rounded-2xl">
            <div class="font-bold text-lg">🔍 СТАТУС РЕДАКТИРОВАНИЯ:</div>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mt-2 text-sm">
                <div>📊 Всего маршрутов: {{ count($distributionResult['distribution'] ?? []) }}</div>
                <div>✏️ Изменено: {{ count($tempAssignments ?? []) }}</div>
                <div>🟢 Активные зоны: {{ $activeZonesOnly ? 'ВКЛ' : 'ВЫКЛ' }}</div>
                <div>⚙️ Режим: {{ $mode ?? 'balance' }}</div>
            </div>
        </div>
        @endif

        {{-- таблица для данных --}}

@if(isset($distributionResult['distribution']) && count($distributionResult['distribution']) > 0)
<div class="mt-8 p-8 bg-gradient-to-br from-emerald-50 to-blue-50 border-2 border-emerald-200 rounded-3xl shadow-2xl">
    <div class="flex justify-around items-center mb-8 m-2">
        <h3 class="text-4xl font-black bg-gradient-to-r from-emerald-600 to-teal-600 bg-clip-text text-transparent">
            📊 {{ $editMode ? 'Редактирование в ручном режиме' : 'В автоматическом режиме' }} 
        </h3>
        <span class="p-3 py-3 {{ $editMode ? 'bg-yellow-100 text-yellow-800' : 'bg-emerald-100 text-emerald-800' }} rounded-2xl font-black shadow-lg">
            {{ count($distributionResult['distribution']) }} назначений
        </span>
    </div>

    <div class="overflow-x-auto rounded-3xl shadow-2xl">
        <table class="w-full bg-white/90 backdrop-blur-xl">
            <thead class="bg-gradient-to-r from-emerald-500 via-teal-500 to-blue-600 ">
                <tr>
                    <th class="p-6 text-left font-black rounded-tl-3xl">забой</th>
                    <th class="p-6 text-left font-black">перегрузка</th>
                    <th class="p-6 text-right font-black">📏 КМ</th>
                    <th class="p-6 text-right font-black rounded-tr-3xl">приоритет</th>
                </tr>
            </thead>
            <tbody>
                @foreach($distributionResult['distribution'] as $minerId => $minerAssignments)
                    {{-- 🔥 БЕРЁМ ПЕРВЫЙ (и единственный) assignment для этого miner --}}
                    @php $assignment = $minerAssignments[0] ?? null; @endphp
                    
                    @if($assignment)
                    <tr class="hover:bg-gradient-to-r {{ $editMode ? 'hover:from-yellow-50 hover:to-orange-50 bg-yellow-50/50 border-2 border-yellow-200' : 'hover:from-emerald-50 hover:to-blue-50' }} border-b border-emerald-100 transition-all">
                        <td class="p-6 font-bold text-gray-900">
                            {{ $assignment['miner_name'] }}
                           
                        </td>
                        
                        <td class="p-6">
                            @if($editMode)
                                <select wire:model.live="tempAssignments.{{ $minerId }}" 
                                    wire:key="miner-{{ $minerId }}-select"
                                    class="w-full p-4 border-3 rounded-2xl focus:ring-4 font-semibold shadow-lg">

                                    @foreach($availableDumps as $dumpId => $dumpName)
                                        @php
                                            $distanceData = \App\Models\MinerDumpDistance::where('miner_id', $minerId)
                                                ->where('dump_id', $dumpId)
                                                ->first();
                                            $distance = $distanceData?->distance_km ?? 999;
                                            $score = max(0, 100 - ($distance * 8));
                                            $isBest = abs($score - $assignment['score']) < 1;
                                        @endphp
                                        
                                        <option value="{{ $dumpId }}" 
                                                class="{{ $isBest ? 'bg-emerald-100 font-bold border-l-4 border-emerald-500' : '' }}"
                                                style="padding: 16px; font-size: 16px;">
                                            п.п.№ {{ $dumpName }}
                                            <span class="text-xs bg-gray-200 px-2 py-1 ml-2"> → {{ number_format($distance, 1) }}км</span>
                                            <span class="font-black text-amber-600 ml-3">({{ number_format($score, 1) }})</span>
                                        </option>
                                    @endforeach
                                </select>
                                
                                @if(($tempAssignments[$minerId] ?? $assignment['dump_id']) != $assignment['dump_id'])
                                    <div class="mt-2 px-4 py-2 bg-emerald-100 border-2 border-emerald-400 rounded-xl font-bold text-emerald-800 text-lg">
                                        ✨ изменен с п.п.№{{ $dumpName }} на п.п.№{{ $availableDumps[$tempAssignments[$minerId]] ?? 'Дамп' }}
                                    </div>
                                @endif
                            @else
                                <span class="inline-flex items-center gap-2 p-3 bg-blue-100 text-blue-800 rounded-2xl font-bold shadow-lg">
                                    № {{ $assignment['dump_name'] }}
                                </span>
                            @endif
                        </td>
                        
                        <td class="p-6 text-right">
                            <div class="text-3xl font-black text-emerald-700">
                                {{ number_format($assignment['distance'], 1) }}
                            </div>
                            <div class="text-sm text-emerald-600 font-medium">
                                {{ number_format($assignment['travel_time'], 2) }}ч
                            </div>
                        </td>
                        
                        <td class="p-6 text-right">
                            <div class="text-3xl font-black text-amber-600 bg-amber-100 p-3 py-3 rounded-2xl inline-block shadow-lg">
                                {{ number_format($assignment['score'], 1) }}
                            </div>
                        </td>
                    </tr>
                    @endif
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endif



    </div>
@endif
</div>


