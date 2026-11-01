        <div x-show="tab === 'analyticsTab'" x-cloak class="mt-4 space-y-6" id="analyticsTab"
             x-data="analyticsCharts"
             x-init="(() => {
                if (tab === 'analyticsTab') {
                    $nextTick(() => initCharts());
                }
                $watch('tab', (v) => {
                    if (v === 'analyticsTab') {
                        $nextTick(() => initCharts());
                    }
                });
            })()"
        >
            {{-- Chart.js подключается в layout (components/layouts/app.blade.php) --}}

            {{-- Данные для графиков передаются через скрытый JSON-блок, читаются из JS --}}
            <script type="application/json" id="analytics-data">
                {{ json_encode($this->analytics_trends) }}
            </script>

            {{-- KPI-карточки --}}
            @php $trends = $this->analytics_trends; $kpi = $trends['kpi']; @endphp
            <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-3">
                <div class="bg-white p-3 rounded-xl border shadow-sm text-center">
                    <p class="text-[10px] text-gray-500 uppercase font-semibold">Ср. расстояние (план)</p>
                    <p class="text-xl font-bold text-emerald-600">{{ $kpi['avg_distance_planned'] }} <span class="text-xs">км</span></p>
                </div>
                <div class="bg-white p-3 rounded-xl border shadow-sm text-center">
                    <p class="text-[10px] text-gray-500 uppercase font-semibold">Ср. расстояние (факт)</p>
                    <p class="text-xl font-bold {{ ($kpi['avg_distance_actual'] > $kpi['avg_distance_planned']) ? 'text-red-600' : 'text-blue-600' }}">{{ $kpi['avg_distance_actual'] }} <span class="text-xs">км</span></p>
                </div>
                <div class="bg-white p-3 rounded-xl border shadow-sm text-center">
                    <p class="text-[10px] text-gray-500 uppercase font-semibold">Ср. скорость</p>
                    <p class="text-xl font-bold text-purple-600">{{ $kpi['avg_speed'] }} <span class="text-xs">км/ч</span></p>
                </div>
                <div class="bg-white p-3 rounded-xl border shadow-sm text-center">
                    <p class="text-[10px] text-gray-500 uppercase font-semibold">Эффективность</p>
                    <p class="text-xl font-bold {{ $kpi['efficiency_pct'] >= 70 ? 'text-emerald-600' : ($kpi['efficiency_pct'] >= 50 ? 'text-amber-600' : 'text-red-600') }}">{{ $kpi['efficiency_pct'] }}<span class="text-xs">%</span></p>
                </div>
                <div class="bg-white p-3 rounded-xl border shadow-sm text-center">
                    <p class="text-[10px] text-gray-500 uppercase font-semibold">Гружёный</p>
                    <p class="text-xl font-bold text-emerald-600">{{ $kpi['total_loaded_km'] }} <span class="text-xs">км</span></p>
                </div>
                <div class="bg-white p-3 rounded-xl border shadow-sm text-center">
                    <p class="text-[10px] text-gray-500 uppercase font-semibold">Холостой</p>
                    <p class="text-xl font-bold {{ $kpi['total_empty_km'] > 0 ? 'text-amber-600' : 'text-emerald-600' }}">{{ $kpi['total_empty_km'] }} <span class="text-xs">км</span></p>
                </div>
            </div>

            {{-- Сводка по смене --}}
            <div class="bg-slate-50 rounded-lg border p-3 text-sm text-gray-600 flex items-center gap-4 flex-wrap">
                <span><i class="fas fa-clock text-blue-500 mr-1"></i> <strong>Смена:</strong> {{ $trends['shift']['label'] }}</span>
                <span><i class="fas fa-pause-circle text-amber-500 mr-1"></i> <strong>Простои:</strong> {{ $kpi['pauses_count'] }} ({{ $kpi['total_pause_formatted'] }})</span>
                <span class="ml-auto"><i class="fas fa-route text-emerald-500 mr-1"></i> <strong>Всего пробег:</strong> {{ $kpi['total_loaded_km'] + $kpi['total_empty_km'] }} км</span>
            </div>

            {{-- Таблица скоростей --}}
            <div class="bg-white rounded-xl border shadow-sm overflow-hidden">
                <div class="p-4 border-b font-bold text-gray-800 uppercase text-sm">Средняя скорость по маршрутам <span class="text-gray-400 normal-case font-normal">(за смену)</span></div>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-slate-50 border-b">
                            <tr><th class="p-3 text-left">Маршрут</th><th class="p-3 text-left">Ср. скорость</th><th class="p-3 text-left">Рейсов</th><th class="p-3 text-left">Расстояние</th></tr>
                        </thead>
                        <tbody>
                            @php $routeSpeeds = $this->route_speeds; @endphp
                            @if(empty($routeSpeeds))
                                <tr><td colspan="4" class="p-8 text-center text-gray-500">Нет данных за текущую смену. Данные появятся после завершения рейсов.</td></tr>
                            @else
                                @foreach($routeSpeeds as $route)
                                    <tr class="border-b {{ $route['avg_speed'] > 0 && $route['avg_speed'] < 20 ? 'bg-red-50' : '' }}">
                                        <td class="p-3 font-medium text-gray-800">
                                            {{ $route['route'] }}
                                            @if($route['avg_speed'] > 0 && $route['avg_speed'] < 20)
                                                <small class="text-red-500 block"><i class="fas fa-exclamation-triangle"></i> Низкая скорость</small>
                                            @elseif($route['avg_speed'] > 0 && $route['avg_speed'] < 25)
                                                <small class="text-amber-500 block"><i class="fas fa-clock"></i> Средняя скорость</small>
                                            @endif
                                        </td>
                                        <td class="p-3">
                                            <span class="px-2 py-0.5 text-xs rounded {{ $route['avg_speed'] < 20 ? 'bg-red-100 text-red-700' : ($route['avg_speed'] < 25 ? 'bg-amber-100 text-amber-700' : 'bg-emerald-100 text-emerald-700') }}">{{ $route['avg_speed'] }} км/ч</span>
                                        </td>
                                        <td class="p-3 text-gray-600">{{ $route['trips_count'] }}</td>
                                        <td class="p-3 text-gray-500">{{ $route['total_distance'] }} км</td>
                                    </tr>
                                @endforeach
                            @endif
                        </tbody>
                    </table>
                </div>
                <div class="p-3 bg-slate-50 text-xs text-gray-500 border-t">
                    <i class="fas fa-info-circle"></i> Маршруты отсортированы по скорости (проблемные вверху). Низкая: &lt; 20 км/ч, Средняя: 20-25 км/ч, Высокая: &ge; 25 км/ч.
                </div>
            </div>

        {{-- Alpine-компонент для графиков Аналитики.
             Вынесен из x-data="..." в отдельный script, потому что внутри JS
             есть строки с одинарными кавычками ('rgba(...)') и HTML-строка
             с двойными кавычками — Blade-парсер ломается на них. --}}
        <script>
            document.addEventListener('alpine:init', () => {
                Alpine.data('analyticsCharts', () => ({
                    volumeChart: null,
                    pausesChart: null,
                    efficiencyChart: null,

                    // Читает данные из скрытого JSON-блока <script id="analytics-data">
                    // Обновляется при Livewire-перерисовке (блок тоже перерисовывается)
                    getData() {
                        const el = document.getElementById('analytics-data');
                        if (!el) return null;
                        try {
                            return JSON.parse(el.textContent);
                        } catch (e) {
                            console.error('Не удалось распарсить analytics-data', e);
                            return null;
                        }
                    },

                    formatSeconds(s) {
                        const h = Math.floor(s / 3600);
                        const m = Math.floor((s % 3600) / 60);
                        if (h > 0) return h + 'ч ' + m + 'м';
                        return m + 'м';
                    },

                    initVolumeChart() {
                        const ctx = document.getElementById('volumeChart');
                        if (!ctx) return;
                        const data = this.getData();
                        if (!data) return;

                        if (this.volumeChart) this.volumeChart.destroy();
                        this.volumeChart = new Chart(ctx, {
                            type: 'bar',
                            data: {
                                labels: data.hourly.labels,
                                datasets: [{
                                    label: 'Объём (т)',
                                    data: data.hourly.volume,
                                    backgroundColor: 'rgba(16, 185, 129, 0.6)',
                                    borderColor: 'rgba(16, 185, 129, 1)',
                                    borderWidth: 1,
                                    yAxisID: 'y'
                                }, {
                                    label: 'Рейсов',
                                    data: data.hourly.trips_count,
                                    backgroundColor: 'rgba(59, 130, 246, 0.6)',
                                    borderColor: 'rgba(59, 130, 246, 1)',
                                    borderWidth: 1,
                                    yAxisID: 'y1',
                                    type: 'line',
                                    tension: 0.3
                                }]
                            },
                            options: {
                                responsive: true,
                                maintainAspectRatio: false,
                                scales: {
                                    y: { beginAtZero: true, position: 'left', title: { display: true, text: 'Объём (т)' } },
                                    y1: { beginAtZero: true, position: 'right', grid: { drawOnChartArea: false }, title: { display: true, text: 'Рейсов' } }
                                },
                                plugins: { legend: { position: 'top' } }
                            }
                        });
                    },

                    initPausesChart() {
                        const ctx = document.getElementById('pausesChart');
                        if (!ctx) return;
                        const data = this.getData();
                        if (!data) return;

                        if (this.pausesChart) this.pausesChart.destroy();

                        const pauses = data.pauses.data;
                        if (!pauses || !pauses.length) {
                            ctx.parentElement.innerHTML = '<div class="text-center text-gray-400 text-sm py-8"><i class="fas fa-check-circle text-2xl text-emerald-400 mb-2"></i><p>Нет простоев за смену</p></div>';
                            return;
                        }

                        const colors = [
                            'rgba(239, 68, 68, 0.7)',   // red
                            'rgba(245, 158, 11, 0.7)',  // amber
                            'rgba(59, 130, 246, 0.7)',  // blue
                            'rgba(16, 185, 129, 0.7)',  // emerald
                            'rgba(139, 92, 246, 0.7)',  // purple
                            'rgba(236, 72, 153, 0.7)',  // pink
                        ];

                        const self = this;
                        this.pausesChart = new Chart(ctx, {
                            type: 'doughnut',
                            data: {
                                labels: pauses.map(d => d.label + ' (' + self.formatSeconds(d.seconds) + ')'),
                                datasets: [{
                                    data: pauses.map(d => d.seconds),
                                    backgroundColor: colors,
                                    borderWidth: 2,
                                    borderColor: '#fff'
                                }]
                            },
                            options: {
                                responsive: true,
                                maintainAspectRatio: false,
                                plugins: {
                                    legend: { position: 'right' },
                                    tooltip: {
                                        callbacks: {
                                            label: (ctx) => ctx.label.split(' (')[0] + ': ' + self.formatSeconds(ctx.parsed)
                                        }
                                    }
                                }
                            }
                        });
                    },

                    initEfficiencyChart() {
                        const ctx = document.getElementById('efficiencyChart');
                        if (!ctx) return;
                        const data = this.getData();
                        if (!data) return;

                        if (this.efficiencyChart) this.efficiencyChart.destroy();
                        this.efficiencyChart = new Chart(ctx, {
                            type: 'bar',
                            data: {
                                labels: data.hourly.labels,
                                datasets: [{
                                    label: 'Гружёный (км)',
                                    data: data.hourly.loaded_km,
                                    backgroundColor: 'rgba(16, 185, 129, 0.7)',
                                    stack: 'km'
                                }, {
                                    label: 'Холостой (км)',
                                    data: data.hourly.empty_km,
                                    backgroundColor: 'rgba(239, 68, 68, 0.6)',
                                    stack: 'km'
                                }]
                            },
                            options: {
                                responsive: true,
                                maintainAspectRatio: false,
                                scales: {
                                    x: { stacked: true },
                                    y: { stacked: true, beginAtZero: true, title: { display: true, text: 'Км' } }
                                },
                                plugins: { legend: { position: 'top' } }
                            }
                        });
                    },

                    initCharts() {
                        if (typeof Chart === 'undefined') {
                            console.warn('Chart.js не загружен');
                            return;
                        }
                        this.initVolumeChart();
                        this.initPausesChart();
                        this.initEfficiencyChart();
                    }
                }));
            });
        </script>

        {{-- ========================================== --}}
        {{-- ТАБЛИЦА РЕЙСОВ ПАРКА С ФИЛЬТРАМИ --}}
        {{-- ========================================== --}}
        @php
            $statsRows = $this->stats_by_period;
            $statsTotals = $this->stats_totals;
            $statsMinersList = $this->stats_miners_list;
            $periodLabel = $this->stats_period_label;
        @endphp
        <div class="bg-white rounded-xl border shadow-sm overflow-hidden">
            <div class="p-4 border-b">
                <div class="flex items-center justify-between flex-wrap gap-3">
                    <h3 class="font-bold text-gray-800 uppercase text-sm flex items-center gap-2">
                        <i class="fas fa-chart-bar text-blue-500"></i> Рейсы парка {{ $periodLabel }}
                    </h3>
                    <div class="flex items-center gap-2 flex-wrap">
                        {{-- Фильтр периода --}}
                        <select wire:model.live="statsPeriod" class="text-xs border-gray-300 rounded-md py-1.5 px-2">
                            <option value="shift">За смену</option>
                            <option value="today">За сегодня</option>
                            <option value="week">За неделю</option>
                            <option value="month">За месяц</option>
                            <option value="year">За год</option>
                        </select>
                        {{-- Фильтр смены --}}
                        <select wire:model.live="statsShift" class="text-xs border-gray-300 rounded-md py-1.5 px-2">
                            <option value="all">Все смены</option>
                            <option value="day">Дневная</option>
                            <option value="night">Ночная</option>
                        </select>
                        {{-- Фильтр экскаватора --}}
                        <select wire:model.live="statsMinerId" class="text-xs border-gray-300 rounded-md py-1.5 px-2">
                            <option value="">Все экскаваторы</option>
                            @foreach($statsMinersList as $id => $name)
                                <option value="{{ $id }}">{{ $name }}</option>
                            @endforeach
                        </select>
                        {{-- Группировка --}}
                        <select wire:model.live="statsGrouping" class="text-xs border-gray-300 rounded-md py-1.5 px-2">
                            <option value="by_miner">По экскаваторам</option>
                            <option value="by_truck">По грузовикам</option>
                            <option value="by_rock">По породам</option>
                        </select>
                    </div>
                </div>
            </div>

            <div class="overflow-x-auto">
                @if(!empty($statsRows))
                <table class="w-full text-sm">
                    <thead class="bg-slate-50 border-b">
                        <tr>
                            @php $first = $statsRows[0]; @endphp
                            <th class="text-left p-3 font-semibold text-gray-600">{{ $first['col1_label'] }}</th>
                            <th class="text-left p-3 font-semibold text-gray-600">{{ $first['col2_label'] }}</th>
                            @if($first['col3_label'])
                            <th class="text-left p-3 font-semibold text-gray-600">{{ $first['col3_label'] }}</th>
                            @endif
                            <th class="text-center p-3 font-semibold text-gray-600">Рейсов</th>
                            <th class="text-center p-3 font-semibold text-gray-600">Объём (т)</th>
                            <th class="text-center p-3 font-semibold text-gray-600">Гружёный (км)</th>
                            <th class="text-center p-3 font-semibold text-gray-600">Холостой (км)</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($statsRows as $row)
                        <tr class="border-b hover:bg-slate-50">
                            <td class="p-3 font-medium text-gray-800">{{ $row['col1'] }}</td>
                            <td class="p-3 text-gray-600">{{ $row['col2'] }}</td>
                            @if($first['col3_label'])
                            <td class="p-3">
                                <span class="px-2 py-0.5 text-xs rounded bg-cyan-100 text-cyan-700">{{ $row['col3'] }}</span>
                            </td>
                            @endif
                            <td class="p-3 text-center font-bold text-gray-800">{{ $row['trips'] }}</td>
                            <td class="p-3 text-center text-blue-700 font-medium">{{ number_format($row['volume'], 1) }}</td>
                            <td class="p-3 text-center text-emerald-600">{{ $row['loaded_km'] }}</td>
                            <td class="p-3 text-center text-amber-600">{{ $row['empty_km'] }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                    <tfoot class="bg-slate-100 border-t-2 border-slate-300">
                        <tr>
                            <td colspan="{{ $first['col3_label'] ? 3 : 2 }}" class="p-3 font-bold text-gray-700 text-right">Итого:</td>
                            <td class="p-3 text-center font-bold text-emerald-700">{{ $statsTotals['trips'] }}</td>
                            <td class="p-3 text-center font-bold text-blue-700">{{ number_format($statsTotals['volume'], 1) }}</td>
                            <td class="p-3 text-center font-bold text-emerald-600">{{ $statsTotals['loaded_km'] }}</td>
                            <td class="p-3 text-center font-bold text-amber-600">{{ $statsTotals['empty_km'] }}</td>
                        </tr>
                    </tfoot>
                </table>
                @else
                <div class="p-8 text-center text-gray-400 text-sm">
                    <i class="fas fa-inbox text-3xl text-gray-300 mb-2"></i>
                    <p>Нет данных за выбранный период</p>
                </div>
                @endif
            </div>
        </div>

        {{-- ========================================== --}}
        {{-- ТАБЛИЦА ПРОВЕДЁННЫХ ТО С ФИЛЬТРАМИ --}}
        {{-- (по образцу «Рейсов парка») --}}
        {{-- ========================================== --}}
        @php
            $mRows   = $this->maintenance_stats_by_period;
            $mTotals = $this->maintenance_stats_totals;
            $mTrucks = $this->maintenance_stats_trucks_list;
            $mLabel  = $this->maintenance_stats_period_label;
        @endphp
        <div class="bg-white rounded-xl border shadow-sm overflow-hidden">
            <div class="p-4 border-b">
                <div class="flex items-center justify-between flex-wrap gap-3">
                    <h3 class="font-bold text-gray-800 uppercase text-sm flex items-center gap-2">
                        <i class="fas fa-tools text-amber-500"></i> Проведённые ТО {{ $mLabel }}
                    </h3>
                    <div class="flex items-center gap-2 flex-wrap">
                        {{-- Фильтр периода --}}
                        <select wire:model.live="maintenanceStatsPeriod" class="text-xs border-gray-300 rounded-md py-1.5 px-2">
                            <option value="shift">За смену</option>
                            <option value="today">За сегодня</option>
                            <option value="week">За неделю</option>
                            <option value="month">За месяц</option>
                            <option value="year">За год</option>
                        </select>
                        {{-- Фильтр смены --}}
                        <select wire:model.live="maintenanceStatsShift" class="text-xs border-gray-300 rounded-md py-1.5 px-2">
                            <option value="all">Все смены</option>
                            <option value="day">Дневная</option>
                            <option value="night">Ночная</option>
                        </select>
                        {{-- Фильтр грузовика --}}
                        <select wire:model.live="maintenanceStatsTruckId" class="text-xs border-gray-300 rounded-md py-1.5 px-2">
                            <option value="">Все грузовики</option>
                            @foreach($mTrucks as $id => $number)
                                <option value="{{ $id }}">{{ $number }}</option>
                            @endforeach
                        </select>
                        {{-- Фильтр типа задачи --}}
                        <select wire:model.live="maintenanceStatsTaskType" class="text-xs border-gray-300 rounded-md py-1.5 px-2">
                            <option value="all">Все типы</option>
                            <option value="maintenance">ТО</option>
                            <option value="fueling">Заправка</option>
                            <option value="tire_inflation">Подкачка шин</option>
                            <option value="wheel_tightening">Обтяжка колёс</option>
                            <option value="inspection">Инспекция</option>
                        </select>
                        {{-- Группировка --}}
                        <select wire:model.live="maintenanceStatsGrouping" class="text-xs border-gray-300 rounded-md py-1.5 px-2">
                            <option value="by_truck">По грузовикам</option>
                            <option value="by_type">По типам работ</option>
                            <option value="by_post">По постам</option>
                            <option value="by_to_type">По типам ТО (ТО-1/ТО-2)</option>
                        </select>
                    </div>
                </div>
            </div>

            <div class="overflow-x-auto">
                @if(!empty($mRows))
                <table class="w-full text-sm">
                    <thead class="bg-slate-50 border-b">
                        <tr>
                            @php $mFirst = $mRows[0]; @endphp
                            <th class="text-left p-3 font-semibold text-gray-600">{{ $mFirst['col1_label'] }}</th>
                            <th class="text-left p-3 font-semibold text-gray-600">{{ $mFirst['col2_label'] }}</th>
                            @if($mFirst['col3_label'])
                            <th class="text-left p-3 font-semibold text-gray-600">{{ $mFirst['col3_label'] }}</th>
                            @endif
                            <th class="text-center p-3 font-semibold text-gray-600">Проведено</th>
                            <th class="text-center p-3 font-semibold text-gray-600">Типы ТО</th>
                            <th class="text-center p-3 font-semibold text-gray-600">План (мин)</th>
                            <th class="text-center p-3 font-semibold text-gray-600">Факт (мин)</th>
                            <th class="text-center p-3 font-semibold text-gray-600">Среднее (мин)</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($mRows as $row)
                        <tr class="border-b hover:bg-slate-50">
                            <td class="p-3 font-medium text-gray-800">{{ $row['col1'] }}</td>
                            <td class="p-3 text-gray-600">{{ $row['col2'] }}</td>
                            @if($mFirst['col3_label'])
                            <td class="p-3">
                                <span class="px-2 py-0.5 text-xs rounded bg-amber-100 text-amber-700">{{ $row['col3'] }}</span>
                            </td>
                            @endif
                            <td class="p-3 text-center font-bold text-gray-800">{{ $row['count'] }}</td>
                            <td class="p-3 text-center text-gray-600">{{ $row['to_types_label'] }}</td>
                            <td class="p-3 text-center text-blue-700">{{ $row['planned_minutes'] }}</td>
                            <td class="p-3 text-center @if($row['actual_minutes'] > $row['planned_minutes']) text-red-600 @else text-emerald-600 @endif">{{ $row['actual_minutes'] }}</td>
                            <td class="p-3 text-center font-medium text-gray-700">{{ $row['avg_minutes'] }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                    <tfoot class="bg-slate-100 border-t-2 border-slate-300">
                        <tr>
                            <td colspan="{{ $mFirst['col3_label'] ? 3 : 2 }}" class="p-3 font-bold text-gray-700 text-right">Итого:</td>
                            <td class="p-3 text-center font-bold text-emerald-700">{{ $mTotals['count'] }}</td>
                            <td class="p-3 text-center text-gray-500">—</td>
                            <td class="p-3 text-center font-bold text-blue-700">{{ $mTotals['planned_minutes'] }}</td>
                            <td class="p-3 text-center font-bold @if($mTotals['actual_minutes'] > $mTotals['planned_minutes']) text-red-600 @else text-emerald-600 @endif">{{ $mTotals['actual_minutes'] }}</td>
                            <td class="p-3 text-center text-gray-400">—</td>
                        </tr>
                    </tfoot>
                </table>
                @else
                <div class="p-8 text-center text-gray-400 text-sm">
                    <i class="fas fa-tools text-3xl text-gray-300 mb-2"></i>
                    <p>Нет проведённых ТО за выбранный период</p>
                </div>
                @endif
            </div>
        </div>
        </div>

        <!-- Настройки порогов и сервисных постов -->
