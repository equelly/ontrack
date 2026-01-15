<div class="p-6 space-y-6">
    <h3 class="text-3xl font-bold">🚧 ДИСПЕТЧЕР: Miners</h3>


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
                Распределить маршруты
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
                📊 {{ $editMode ? 'Корректировка в ручном режиме' : 'В автоматическом режиме' }} 
            </h3>
            <span class="p-3 py-3 {{ $editMode ? 'bg-yellow-100 text-yellow-800' : 'bg-emerald-100 text-emerald-800' }} rounded-2xl font-black">
                {{ count($distributionResult['distribution']) }} назначений
            </span>
        </div>
        <div class="mt-8 p-6 bg-gradient-to-r from-slate-50 to-blue-50 border-2 border-slate-200 rounded-3xl shadow-2xl">
            <h3 class="text-2xl font-black text-slate-800 mb-6 text-center">📊 СТАТИСТИКА РАСПРЕДЕЛЕНИЯ</h3>
          
            {{-- ТАБЛИЦА 3 колонки — распределения --}}
            <div class="overflow-x-auto">
                <table class="w-full bg-white rounded-2xl shadow-lg border border-gray-200" style="border-spacing: 0;">
                    <thead style="background: linear-gradient(90deg, #cad3dbff 0%, #b3b8bfff 100%);">
                        <tr>
                            <th style="padding: 16px; text-align: left; font-weight: bold; color: #1e293b; border-right: 1px solid #e2e8f0;">режим</th>
                            <th style="padding: 16px; text-align: right; font-weight: bold; color: #1e293b; border-right: 1px solid #e2e8f0;">среднее расстояние (км)</th>
                            <th style="padding: 16px; text-align: right; font-weight: bold; color: #1e293b;">приоритет</th>
                        </tr>
                    </thead>
                    
                    {{-- СТРОКА 1 - СВЕТЛАЯ --}}
                    <tr style="background-color: #dcdfe2ff;">
                        <td style="padding: 16px; font-weight: 600; color: #1d4ed8; display: flex; align-items: center; gap: 8px;">
                            <div style="width: 12px; height: 12px; background: #3b82f6; border-radius: 50%;"></div>
                            🤖 Автоматический
                        </td>
                        <td style="padding: 16px; text-align: right; font-family: monospace; font-size: 20px; color: #2563eb; font-weight: bold;">
                            {{ number_format($stats['auto_avg_distance'] ?? 0, 1) }}
                        </td>
                        <td style="padding: 16px; text-align: right; font-family: monospace; font-size: 24px; font-weight: 900; color: #1d4ed8;">
                            {{ number_format($stats['auto_avg_score'] ?? 0, 1) }}
                        </td>
                    </tr>
                    
                    {{-- СТРОКА 2 - БЕЛАЯ --}}
                    <tr style="background-color: #ffffff;">
                        <td style="padding: 16px; font-weight: 600; color: #b45309; display: flex; align-items: center; gap: 8px;">
                            <div style="width: 12px; height: 12px; background: #f59e0b; border-radius: 50%;"></div>
                            💾 Текущий
                        </td>
                        <td style="padding: 16px; text-align: right; font-family: monospace; font-size: 20px; color: #d97706; font-weight: bold;">
                            {{ number_format($stats['saved_avg_distance'] ?? 0, 1) }}
                        </td>
                        <td style="padding: 16px; text-align: right; font-family: monospace; font-size: 24px; font-weight: 900; color: #b45309;">
                            {{ number_format($stats['saved_avg_score'] ?? 0, 1) }}
                        </td>
                    </tr>
                    
                    @if($editMode && count($tempAssignments ?? []) > 0)
                    {{-- СТРОКА 3 - СВЕТЛАЯ --}}
                    <tr style="background-color: #dcdfe2ff;">
                        <td style="padding: 16px; font-weight: 600; color: #166534; display: flex; align-items: center; gap: 8px;">
                            <div style="width: 12px; height: 12px; background: #10b981; border-radius: 50%;"></div>
                            ✏️ корректировка ({{ count($tempAssignments) }})
                        </td>
                        <td style="padding: 16px; text-align: right; font-family: monospace; font-size: 20px; color: #059669; font-weight: bold;">
                            {{ number_format($stats['manual_avg_distance'] ?? 0, 1) }}
                        </td>
                        <td style="padding: 16px; text-align: right; font-family: monospace; font-size: 24px; font-weight: 900; color: #166534;">
                            {{ number_format($stats['manual_avg_score'] ?? 0, 1) }}
                        </td>
                    </tr>
                    @else
                    {{-- СТРОКА 4 - БЕЛАЯ + ПРОЗРАЧНОСТЬ --}}
                    <tr style="background-color: #ffffff; opacity: 0.5;">
                        <td style="padding: 16px; color: #6b7280; display: flex; align-items: center; gap: 8px;">
                            <div style="width: 12px; height: 12px; background: #9ca3af; border-radius: 50%;"></div>
                            ✏️ Ручная Корректировка
                        </td>
                        <td style="padding: 16px; text-align: right; color: #9ca3af; font-family: monospace; font-size: 20px;">—</td>
                        <td style="padding: 16px; text-align: right; font-family: monospace; font-size: 24px; color: #9ca3af; font-weight: 900;">—</td>
                    </tr>
                    @endif
                </table>
            </div>
        </div>
        {{-- 🔥 3 КНОПКИ: --}}
        <div class="">
            <div class="mb-8 flex justify-around">
                @if(!$editMode)
                    <button wire:click="toggleEditMode" 
                            class="p-3 py-4 bg-gradient-to-r from-orange-500 to-amber-600 font-black rounded-3xl shadow-2xl">
                        ✏️ ПЕРЕНАПРАВИТЬ
                    </button>
                @else
                    <button wire:click="toggleEditMode" 
                            class="p-3 py-4 bg-gradient-to-r from-gray-500 to-gray-600 font-black rounded-3xl shadow-2xl">
                        ❌ ОТМЕНА
                    </button>
                @endif
                
                <button wire:click="saveMiningOrders" class="border-4 border-indigo-500/100 "
                        class="px-12 py-4 bg-gradient-to-r from-emerald-500 to-teal-600 font-black rounded-3xl shadow-2xl
                            {{ !isset($distributionResult['distribution']) ? 'opacity-50 cursor-not-allowed' : '' }}"
                        {{ !isset($distributionResult['distribution']) ? 'disabled' : '' }}>
                    💾 ПРИМЕНИТЬ МАРШРУТЫ 
                </button>
        </div>




        {{-- таблица для данных --}}

@if(isset($distributionResult['distribution']) && count($distributionResult['distribution']) > 0)
<div class="mt-8">
    <div class="overflow-x-auto rounded-3xl">
        <table class="w-full bg-white/90 backdrop-blur-xl">
            <thead class="bg-gradient-to-r from-emerald-500 via-teal-500 to-blue-600 ">
                <tr class="border-1 border-indigo-500/100">
                    <th class="text-left font-black rounded-tl-3xl" style="padding: 16px; text-align: left; font-weight: bold; color: #1e293b; border-right: 1px solid #e2e8f0;"">забой</th>
                    <th class="text-left font-black" style="padding: 16px; text-align: left; font-weight: bold; color: #1e293b; border-right: 1px solid #e2e8f0;"">перегрузка</th>
                    <th class="text-right font-black" style="padding: 16px; text-align: left; font-weight: bold; color: #1e293b; border-right: 1px solid #e2e8f0;"">📏 КМ</th>
                    <th class="text-right font-black rounded-tr-3xl" style="padding: 16px; text-align: left; font-weight: bold; color: #1e293b; border-right: 1px solid #e2e8f0;"">приоритет</th>
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
                    <tr wire:key="miner-{{ $minerId }}"
                        class="border
                            {{ (!empty($savedDumpId) && !$isSaved) 
                                ? 'bg-slate-100' 
                                : ($isSaved ? 'bg-emerald-50' : ($editMode ? 'bg-yellow-50/50' : ''))
                            }}">
                       
                        <td wire:key="miner-{{ $minerId }}-row" 
                            class="font-bold text-xl relative {{ (!empty($savedDumpId) && !$isSaved) ? 'text-slate-500' : 'text-gray-900' }}">
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

                        
                        <td>
                            @if($editMode)
                                <select wire:model.live="tempAssignments.{{ $minerId }}" 
                                    wire:ignore.self  
                                    wire:key="miner-{{ $minerId }}-select"
                                    @if(($tempAssignments[$minerId] ?? $assignment['dump_id']) != $assignment['dump_id'])
                                    class="w-full  border-3 rounded-2xl focus:ring-4 font-semibold"
                                    @else
                                    class="w-full p-3 border-3 rounded-2xl focus:ring-4 font-semibold"
                                    @endif >
                                    
                                       
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
                                    <div class="bg-emerald-100 border-2 border-emerald-400 rounded-xl font-bold text-emerald-800 text-lg">
                                        с п.п. №{{ $savedDumpName }} на №{{ $availableDumps[$currentDumpId] ?? $currentDumpId }}
                                    </div>
                                @endif
                            @else
                                <span class="{{ (!empty($savedDumpId) && !$isSaved) ? 'opacity-50 text-slate-600 bg-slate-200' : '' }}">
                                    № {{ $assignment['dump_name'] }}
                                </span>
                            @endif
                        </td>
                        
                        <td class="text-right {{ (!empty($savedDumpId) && !$isSaved) ? 'opacity-50 text-slate-600 bg-slate-200' : '' }}">
                            <div class="font-black {{ (!empty($savedDumpId) && !$isSaved) ? 'text-slate-500' : 'text-emerald-700' }}">
                                {{ number_format($assignment['distance'], 1) }}
                            </div>
                            <div class="font-black {{ (!empty($savedDumpId) && !$isSaved) ? 'opacity-50 text-slate-600 bg-slate-200' : '' }}">
                                {{ number_format($assignment['travel_time'], 2) }}ч
                            </div>
                        </td>
                        
                        <td class="text-right {{ (!empty($savedDumpId) && !$isSaved) ? 'opacity-50 text-slate-600 bg-slate-200' : '' }}">
                            <div class="{{ (!empty($savedDumpId) && !$isSaved) 
                                ? 'text-slate-500 bg-slate-200' 
                                : 'text-amber-600 bg-amber-100' }}">
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
                                        
                                        <span class="font-semibold text-amber-800">📝 перенаправлен диспетчером на </span>
                                </td>
                                <td>       
                                        {{--  NAME ДАМПА! --}}
                                        <span class="font-black text-lg text-amber-700">
                                            п.п.№{{ $savedDumpName }}
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
                                                <span class="text-sm">({{ $deltaScore > 0 ? '-' : '' }}{{ number_format($deltaScore, 1) }})</span>
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
@if (session()->has('error'))
    <div class="mb-4 rounded border border-red-300 bg-red-50 px-4 py-3 text-sm text-red-800">
        {{ session('error') }}
    </div>
@endif
@if (session()->has('info'))
    <div class="mb-4 rounded border border-red-300 bg-sky-50 px-4 py-3 text-sm text-red-800">
        {{ session('info') }}
    </div>
@endif

{{-- 🚛 ПАНЕЛЬ ДИСПЕТЧЕРА --}}
<div class="dispatch-panel bg-gradient-to-r from-blue-500 to-indigo-600 text-white p-6 rounded-xl mb-8">
    <div class="flex justify-between mb-4">
        <button wire:click="autoDistributeTrucks" class="bg-green-500 text-white px-6 py-2 rounded">
            🚛 назначить маршруты авто
        </button>

        <button wire:click="refreshData" 
                class="btn-refresh bg-yellow-500 hover:bg-yellow-600 px-6 py-3 rounded-lg font-bold transition-all">
            🔄 ОБНОВИТЬ
        </button>
    </div>
    
    <div class="grid md:grid-cols-2 gap-6 text-sm">
        <div class="stats-card bg-white/20 backdrop-blur p-4 rounded-lg">
            <h4>📊 СТАТИСТИКА</h4>
            <p>Свободно: <strong>{{ $freeTrucks->count() }}</strong></p>
            <p>В работе: <strong>{{ $trucks->whereIn('status', ['loading','transporting','unloading'])->count() }}</strong></p>
            <p>Заданий: <strong>{{ $assignments->count() }}</strong></p>
        </div>
        
        <div class="stats-card bg-white/20 backdrop-blur p-4 rounded-lg">
            <h4>⚡ РЕЖИМ</h4>
            <p>{{ ucfirst($mode) }} | Зоны: {{ $activeZonesOnly ? 'активные' : 'все' }}</p>
        </div>
    </div>
</div>

{{-- ГРУЗОВИКИ И ЗАДАНИЯ --}}
<div class="grid lg:grid-cols-2 gap-6">
    {{-- Свободные грузовики --}}
    <div class="trucks-panel bg-white shadow-xl rounded-xl p-6">
        <h3 class="text-xl font-bold mb-4 flex items-center">
            🚛 Свободные грузовики ({{ $freeTrucks->count() }})
        </h3>
        @forelse($freeTrucks as $truck)
            <div class="truck-item p-4 mb-3 bg-gray-50 rounded-lg hover:shadow-md transition-all">
                <div class="flex justify-between items-center">
                    <div>
                        <strong>{{ $truck->number }}</strong> 
                        <span class="ml-2 bg-green-100 text-green-800 px-2 py-1 rounded-full text-xs">
                            {{ $truck->brand ?? '–' }} | {{ $truck->load_capacity }}т
                        </span>
                    </div>
                    <span class="text-green-600 font-bold">СВОБОДЕН</span>
                </div>
            </div>
        @empty
            <div class="text-center py-8 text-gray-500">Нет свободных грузовиков</div>
        @endforelse
    </div>
    
    {{-- Текущие задания --}}
    <div class="assignments-panel bg-white shadow-xl rounded-xl p-6">
        <h3 class="text-xl font-bold mb-4 flex items-center">
            📋 Текущие задания ({{ $assignments->count() }})
        </h3>
        @forelse($assignments as $assignment)
            <div class="assignment-item p-4 mb-3 rounded-lg border-l-4 
                @switch($assignment->truck->status)
                    @case('loading') border-l-yellow-400 bg-yellow-50 @break
                    @case('transporting') border-l-blue-400 bg-blue-50 @break
                    @case('unloading') border-l-green-400 bg-green-50 @break
                    @default border-l-gray-400 bg-gray-50
                @endswitch">
                <div class="flex justify-between items-start mb-2">
                    <div>
                        <strong>{{ $assignment->miner->name_miner ?? 'Забой' }}</strong> 
                        → 
                        <strong>{{ $assignment->dump->name_dump ?? 'Дамп' }}</strong>
                    </div>
                    <span class="status-badge px-3 py-1 rounded-full text-xs font-bold
                        @switch($assignment->truck->status)
                            @case('loading') bg-yellow-100 text-yellow-800 @break
                            @case('transporting') bg-blue-100 text-blue-800 @break
                            @case('unloading') bg-green-100 text-green-800 @break
                            @default bg-gray-100 text-gray-800
                        @endswitch">
                        {{ ucfirst($assignment->truck->status) }}
                    </span>
                </div>
                <div class="text-sm text-gray-600">
                    🚛 {{ $assignment->truck->number }} | 
                    Приоритет: {{ $assignment->score }} | 
                    {{ $assignment->distance_km }} км
                </div>
            </div>
        @empty
            <div class="text-center py-8 text-gray-500">Заданий нет</div>
        @endforelse
    </div>
</div>

</div>


