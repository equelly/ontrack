@php
    // Карточка одного AiAlert — выносится в отдельный компонент для читаемости.
    // Принимает $alert (App\Models\AiAlert).

    // Иконка и цвет акцента по типу алерта
    $typeMeta = [
        'truck_anomaly'        => ['icon' => 'fa-truck',         'color' => 'text-red-600',   'bg' => 'bg-red-50'],
        'productivity_drop'   => ['icon' => 'fa-chart-line',    'color' => 'text-amber-600', 'bg' => 'bg-amber-50'],
        'empty_run_high'       => ['icon' => 'fa-route',         'color' => 'text-purple-600','bg' => 'bg-purple-50'],
        'zone_overflow'        => ['icon' => 'fa-layer-group',   'color' => 'text-blue-600',  'bg' => 'bg-blue-50'],
        'maintenance_predict'  => ['icon' => 'fa-wrench',        'color' => 'text-orange-600','bg' => 'bg-orange-50'],
    ];
    $meta = $typeMeta[$alert->type] ?? ['icon' => 'fa-bell', 'color' => 'text-slate-600', 'bg' => 'bg-slate-50'];

    // Цвет severity для бейджа
    $severityMeta = [
        'critical' => ['label' => 'КРИТИЧНО',  'class' => 'bg-red-600 text-white'],
        'warning'   => ['label' => 'ВНИМАНИЕ', 'class' => 'bg-amber-500 text-white'],
        'info'      => ['label' => 'ИНФО',     'class' => 'bg-blue-500 text-white'],
    ];
    $sev = $severityMeta[$alert->severity] ?? $severityMeta['info'];

    // Метрики из data — выводим как мини-карточки
    // Для каждого типа — свои человекочитаемые подписи
    $metrics = [];
    $data = $alert->data ?? [];
    switch ($alert->type) {
        case 'truck_anomaly':
            if (isset($data['current_minutes']))  $metrics[] = ['label' => 'Текущее',  'value' => $data['current_minutes'] . ' мин', 'highlight' => true];
            if (isset($data['average_minutes'])) $metrics[] = ['label' => 'Норма',     'value' => $data['average_minutes'] . ' мин'];
            if (isset($data['deviation_pct']))   $metrics[] = ['label' => 'Отклонение','value' => '+' . $data['deviation_pct'] . '%', 'danger' => true];
            if (isset($data['z_score']))         $metrics[] = ['label' => 'z-score',  'value' => $data['z_score']];
            break;
        case 'productivity_drop':
            if (isset($data['current_minutes']))  $metrics[] = ['label' => 'Текущее',  'value' => $data['current_minutes'] . ' мин', 'highlight' => true];
            if (isset($data['average_minutes'])) $metrics[] = ['label' => 'Норма',     'value' => $data['average_minutes'] . ' мин'];
            if (isset($data['deviation_pct']))   $metrics[] = ['label' => 'Отклонение','value' => '+' . $data['deviation_pct'] . '%', 'danger' => true];
            break;
        case 'empty_run_high':
            if (isset($data['total_empty_km']))  $metrics[] = ['label' => 'Холостой',  'value' => $data['total_empty_km'] . ' км', 'danger' => true];
            if (isset($data['total_loaded_km'])) $metrics[] = ['label' => 'Гружёный',  'value' => $data['total_loaded_km'] . ' км'];
            if (isset($data['empty_pct']))        $metrics[] = ['label' => '% холостого','value' => $data['empty_pct'] . '%', 'highlight' => true];
            if (isset($data['trips_count']))      $metrics[] = ['label' => 'Рейсов',    'value' => $data['trips_count']];
            break;
        case 'zone_overflow':
            if (isset($data['fill_pct']))           $metrics[] = ['label' => 'Заполнено',   'value' => $data['fill_pct'] . '%', 'danger' => $data['fill_pct'] > 90];
            if (isset($data['volume']))             $metrics[] = ['label' => 'Объём',       'value' => round($data['volume'], 1) . ' м³'];
            if (isset($data['capacity']))           $metrics[] = ['label' => 'Вместимость', 'value' => round($data['capacity'], 1) . ' м³'];
            if (isset($data['hours_to_overflow']))  $metrics[] = ['label' => 'До переполнения', 'value' => $data['hours_to_overflow'] . ' ч', 'highlight' => $data['hours_to_overflow'] < 3];
            break;
        case 'maintenance_predict':
            if (isset($data['trend_pct']))             $metrics[] = ['label' => 'Рост времени', 'value' => '+' . $data['trend_pct'] . '%', 'danger' => true];
            if (isset($data['last5_avg_minutes']))     $metrics[] = ['label' => 'Текущее',     'value' => $data['last5_avg_minutes'] . ' мин', 'highlight' => true];
            if (isset($data['prev5_avg_minutes']))    $metrics[] = ['label' => 'Прошлое',      'value' => $data['prev5_avg_minutes'] . ' мин'];
            if (isset($data['moto_hours_since_to']))   $metrics[] = ['label' => 'Мото-часы',    'value' => $data['moto_hours_since_to'] . ' ч'];
            break;
    }

    // Ссылка на связанную сущность
    $entityLink = null;
    if ($alert->entity_type === 'truck' && $alert->entity_id) {
        // Можно сделать route('trucks.show', $alert->entity_id) если есть
        $entityLink = ['label' => 'К самосвалу #' . $alert->entity_id, 'icon' => 'fa-truck'];
    } elseif ($alert->entity_type === 'miner' && $alert->entity_id) {
        $entityLink = ['label' => 'К забою #' . $alert->entity_id, 'icon' => 'fa-hard-hat'];
    } elseif ($alert->entity_type === 'zone' && $alert->entity_id) {
        $entityLink = ['label' => 'К зоне', 'icon' => 'fa-map-marker-alt'];
    }
@endphp

<div class="p-4 ml-3 {{ $alert->status === 'new' ? $meta['bg'] . ' border-l-4 ' . 'border-' . ($alert->severity === 'critical' ? 'red-500' : 'amber-500') : 'bg-white' }} hover:bg-slate-50 transition">
    {{-- Header: иконка + бейдж severity + время --}}
    <div class="flex items-center gap-2 mb-2">
        <i class="fas {{ $meta['icon'] }} {{ $meta['color'] }} text-base"></i>
        <span class="px-2 py-0.5 text-[10px] font-bold rounded {{ $sev['class'] }}">
            {{ $sev['label'] }}
        </span>
        @if($alert->status === 'new')
            <span class="w-2 h-2 bg-red-500 rounded-full animate-pulse" title="Новый"></span>
        @endif
        <small class="text-gray-400 ml-auto">{{ $alert->created_at->diffForHumans() }}</small>
    </div>

    {{-- Заголовок --}}
    <p class="text-sm font-bold text-gray-800 leading-snug mb-2">{{ $alert->title }}</p>

    {{-- Метрики из data — выводим как мини-карточки, только если есть --}}
    @if(!empty($metrics))
    <div class="grid grid-cols-2 gap-1.5 mb-2.5">
        @foreach($metrics as $m)
            <div class="px-2 py-1 bg-white border {{ $m['danger'] ?? false ? 'border-red-300 bg-red-50' : ($m['highlight'] ?? false ? 'border-emerald-300 bg-emerald-50' : 'border-slate-200 bg-slate-50') }} rounded text-center">
                <p class="text-[10px] text-gray-500 uppercase font-semibold leading-none mb-0.5">{{ $m['label'] }}</p>
                <p class="text-sm font-bold {{ $m['danger'] ?? false ? 'text-red-700' : ($m['highlight'] ?? false ? 'text-emerald-700' : 'text-gray-800') }}">{{ $m['value'] }}</p>
            </div>
        @endforeach
    </div>
    @endif

    {{-- Сообщение с переносами --}}
    <p class="text-xs text-gray-600 leading-relaxed mb-2">{{ $alert->message }}</p>

    {{-- Footer: кнопки действий --}}
    <div class="flex items-center justify-between gap-2 pt-1">
        @if($alert->status === 'new')
            <button wire:click="acknowledgeAlert({{ $alert->id }})"
                    wire:loading.attr="disabled"
                    class="px-2.5 py-1 bg-emerald-100 text-emerald-700 hover:bg-emerald-200 rounded-md text-xs font-semibold transition whitespace-nowrap flex items-center gap-1">
                <i class="fas fa-check"></i>
                <span wire:loading.remove>Подтвердить</span>
                <span wire:loading>...</span>
            </button>
        @else
            <span class="text-xs text-emerald-600 flex items-center gap-1">
                <i class="fas fa-check-circle"></i> Подтверждено
            </span>
        @endif

        @if($entityLink)
            <span class="text-xs text-slate-400 flex items-center gap-1">
                <i class="fas {{ $entityLink['icon'] }}"></i>{{ $entityLink['label'] }}
            </span>
        @endif
    </div>
</div>
