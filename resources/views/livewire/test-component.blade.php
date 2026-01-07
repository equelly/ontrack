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
    <div class="mt-6">
        <h3>РЕЗУЛЬТАТЫ РАСПРЕДЕЛЕНИЯ:</h3>
       <div class="flex justify-around items-center mb-8 m-2">
            <h3 class="text-4xl font-black bg-gradient-to-r from-emerald-600 to-teal-600 bg-clip-text text-transparent">
                📊 {{ $editMode ? 'Редактирование в ручном режиме' : 'В автоматическом режиме' }} 
            </h3>
            <span class="p-3 py-3 {{ $editMode ? 'bg-yellow-100 text-yellow-800' : 'bg-emerald-100 text-emerald-800' }} rounded-2xl font-black">
                {{ count($distributionResult['distribution']) }} назначений
            </span>
        </div>
        <div class="mt-8 p-6 bg-gradient-to-r from-slate-50 to-blue-50 border-2 border-slate-200 rounded-3xl shadow-2xl">
            <h3 class="text-2xl font-black text-slate-800 mb-6 text-center">📊 СТАТИСТИКА РАСПРЕДЕЛЕНИЯ</h3>
            
            {{-- ТАБЛИЦА 3 колонки — распределения --}}
            <div class="overflow-x-auto">
                <table class="w-full bg-white rounded-2xl shadow-lg">
                    <thead class="bg-gradient-to-r from-slate-100 to-slate-200">
                        <tr>
                            <th class="p-4 text-left font-bold text-slate-800 border-r border-slate-200">режим</th>
                            <th class="p-4 text-right font-bold text-slate-800 border-r border-slate-200">среднее расстояние (км)</th>
                            <th class="p-4 text-right font-bold text-slate-800">приоритет</th>
                        </tr>
                    </thead>
                    <tbody>
                        {{-- АВТОМАТИЧЕСКИЙ --}}
                        <tr class="hover:bg-blue-50 border-b border-slate-100">
                            <td class="p-4 font-semibold text-blue-700 flex items-center gap-2">
                                <div class="w-3 h-3 bg-blue-500 rounded-full"></div>
                                🤖 Автоматический
                            </td>
                            <td class="p-4 text-right font-mono text-xl text-blue-600 font-bold">
                                {{ number_format($stats['auto_avg_distance'] ?? 0, 1) }}
                            </td>
                            <td class="p-4 text-right font-mono text-2xl font-black text-blue-700">
                                {{ number_format($stats['auto_avg_score'] ?? 0, 1) }}
                            </td>
                        </tr>
                        
                        {{-- СОХРАНЁННОЕ --}}
                        <tr class="hover:bg-amber-50 border-b border-slate-100">
                            <td class="p-4 font-semibold text-amber-700 flex items-center gap-2">
                                <div class="w-3 h-3 bg-amber-500 rounded-full"></div>
                                💾 Текущий
                            </td>
                            <td class="p-4 text-right font-mono text-xl text-amber-600 font-bold">
                                {{ number_format($stats['saved_avg_distance'] ?? 0, 1) }}
                            </td>
                            <td class="p-4 text-right font-mono text-2xl font-black text-amber-700">
                                {{ number_format($stats['saved_avg_score'] ?? 0, 1) }}
                            </td>
                        </tr>
                        
                        {{-- РУЧНОЕ --}}
                        @if($editMode && count($tempAssignments ?? []) > 0)
                        <tr class="hover:bg-emerald-50">
                            <td class="p-4 font-semibold text-emerald-700 flex items-center gap-2">
                                <div class="w-3 h-3 bg-emerald-500 rounded-full"></div>
                                ✏️ редактирование ({{ count($tempAssignments) }})
                            </td>
                            <td class="p-4 text-right font-mono text-xl text-emerald-600 font-bold">
                                {{ number_format($stats['manual_avg_distance'] ?? 0, 1) }}
                            </td>
                            <td class="p-4 text-right font-mono text-2xl font-black {{ ($stats['manual_avg_score'] ?? 0) > ($stats['auto_avg_score'] ?? 0) ? 'text-emerald-700' : 'text-amber-700' }}">
                                {{ number_format($stats['manual_avg_score'] ?? 0, 1) }}
                            </td>
                        </tr>
                        @else
                        <tr class="opacity-50">
                            <td class="p-4 text-gray-500 flex items-center gap-2">
                                <div class="w-3 h-3 bg-gray-400 rounded-full"></div>
                                ✏️ Ручное редактирование
                            </td>
                            <td class="p-4 text-right text-gray-400 font-mono text-xl">—</td>
                            <td class="p-4 text-right font-mono text-2xl text-gray-400 font-black">—</td>
                        </tr>
                        @endif
                    </tbody>
                </table>
            </div>
        </div>






        {{-- таблица для данных --}}

@if(isset($distributionResult['distribution']) && count($distributionResult['distribution']) > 0)
<div class="mt-8 p-8 bg-gradient-to-br from-emerald-50 to-blue-50 border-2 border-emerald-200 rounded-3xl shadow-2xl">
    <div class="overflow-x-auto rounded-3xl">
        <table class="w-full bg-white/90 backdrop-blur-xl">
            <thead class="bg-gradient-to-r from-emerald-500 via-teal-500 to-blue-600 ">
                <tr>
                    <th class="text-left font-black rounded-tl-3xl">забой</th>
                    <th class="text-left font-black">перегрузка</th>
                    <th class="text-right font-black">📏 КМ</th>
                    <th class="text-right font-black rounded-tr-3xl">приоритет</th>
                </tr>
            </thead>
            <tbody>
                @foreach($distributionResult['distribution'] as $minerId => $minerAssignments)
                    {{-- 🔥 БЕРЁМ ПЕРВЫЙ (и единственный) assignment для этого miner --}}
                    @php 
                        $assignment = $minerAssignments[0] ?? null; 
                        $currentDumpId = $tempAssignments[$minerId] ?? $assignment['dump_id'];
                        $savedDumpId = $savedRoutes[$minerId] ?? null;
                        $isSaved = $savedDumpId === $currentDumpId && $savedDumpId !== null;
                        $savedDumpName = $savedDumpId ? ($this->dumpNames[$savedDumpId] ?? "№{$savedDumpId}") : null;
                    @endphp
                    
                    @if($assignment)
                    <tr class="border-b border-emerald-100 transition-all
                        {{ $isSaved ? 'bg-emerald-50' : ($editMode ? 'bg-yellow-50/50' : '') }}">                        
                        <td class="font-bold text-xl text-gray-900 relative">
                            <span class="font-semibold {{ 
                                $isSaved ? 'bg-emerald-200 text-emerald-800' : 
                                (isset($savedDumpId) ? 'bg-amber-200 text-amber-800 border-2 border-amber-400' : 'bg-gray-200 text-gray-700')
                            }}">
                                @if($isSaved)
                                    ✅ 
                                @elseif(isset($savedDumpId))
                                    ⚠️ 
                                @else
                                    ➕ новый маршрут
                                @endif
                            </span>
                            {{ $assignment['miner_name'] }}
                            
                            
                        </td>

                        
                        <td class="">
                            @if($editMode)
                                <select wire:model.live="tempAssignments.{{ $minerId }}" 
                                    wire:key="miner-{{ $minerId }}-select"
                                    class="w-full p-4 border-3 rounded-2xl focus:ring-4 font-semibold">

                                    @foreach($availableDumps as $dumpId => $dumpName)
                                        @php
                                            $distanceData = \App\Models\MinerDumpDistance::where('miner_id', $minerId)
                                                ->where('dump_id', $dumpId)
                                                ->first();
                                            $distance = $distanceData?->distance_km ?? 999;
                                            $score = max(0, 100 - ($distance * 8));
                                            $isBest = abs($score - $assignment['score']) < 1;
                                            // ФИЛЬТР delivery=true 
                                            $hasDelivery = \App\Models\Zone::where('dump_id', $dumpId)
                                                ->where('delivery', true)
                                                ->exists();
                                                
                                            if ($activeZonesOnly && !$hasDelivery) {
                                                continue;
                                            }
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
                                        ✨ изменен с п.п.№{{ $savedDumpName }} на п.п.№{{ $availableDumps[$currentDumpId] ?? $currentDumpId }}
                                    </div>
                                @endif
                            @else
                                <span class="inline-flex items-center gap-2 p-3 bg-blue-100 text-blue-800 rounded-2xl font-bold">
                                    № {{ $assignment['dump_name'] }}
                                </span>
                            @endif
                        </td>
                        
                        <td class="text-right">
                            <div class="text-3xl font-black text-emerald-700">
                                {{ number_format($assignment['distance'], 1) }}
                            </div>
                            <div class="text-sm text-emerald-600 font-medium">
                                {{ number_format($assignment['travel_time'], 2) }}ч
                            </div>
                        </td>
                        
                        <td class="text-right">
                            <div class="text-3xl font-black text-amber-600 bg-amber-100 p-3 py-3 rounded-2xl inline-block">
                                {{ number_format($assignment['score'], 1) }}
                            </div>
                        </td>
                    </tr>
                        {{-- строка изменений сделанных в ручную --}}
                        @if(isset($savedRoutes[$minerId]) && $savedRoutes[$minerId] != $assignment['dump_id'])
                            @php
                                $savedDumpId = $savedRoutes[$minerId];
                                // 🔥 NAME ДАМПА из БД!
                                $savedDumpName = \App\Models\Dump::find($savedDumpId)?->name_dump ?? "Дамп №{$savedDumpId}";
                                
                                $savedDistanceData = \App\Models\MinerDumpDistance::where('miner_id', $minerId)
                                    ->where('dump_id', $savedDumpId)
                                    ->first();
                                $savedDistance = $savedDistanceData?->distance_km ?? 999;
                                $savedScore = max(0, 100 - ($savedDistance * 8));
                                $deltaScore = $assignment['score'] - $savedScore;
                            @endphp
                            
                            <tr class="bg-gradient-to-r from-amber-50 to-orange-50 border-2 border-amber-300 hover:bg-amber-100">
                                <td >
                                    <div class="flex items-center gap-4 text-sm">
                                        
                                        <span class="font-semibold text-amber-800">📝 перенаправлено диспетчером на </span>
                                </td>
                                <td>       
                                        {{--  NAME ДАМПА! --}}
                                        <span class="font-black text-lg text-amber-700">
                                            п.п.{{ $savedDumpName }}
                                        </span>
                                </td>
                                <td>       
                                    <div class="flex items-center gap-4 ml-8">
                                        <div class="text-right">
                                            <div class="text-xl font-black text-amber-600">{{ number_format($savedDistance, 1) }}км</div>
                                            </div>
                                </td>
                                <td>
                                    <div class="text-right">
                                        <div class="text-2xl font-black {{ $deltaScore > 0 ? 'text-emerald-600' : 'text-amber-600' }} px-3 py-1 rounded-xl">
                                            {{ number_format($savedScore, 1) }}
                                                <span class="text-sm">({{ $deltaScore > 0 ? '+' : '' }}{{ number_format($deltaScore, 1) }})</span>
                                        </div>
                                        </div>
                                    </div>
                                
                                    </div>
                                </td>
                            </tr>
                        @endif


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


