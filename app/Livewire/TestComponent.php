<?php

namespace App\Livewire;

use Livewire\Component;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;
use App\Models\MiningOrder;
use App\Models\Dump;
use App\Models\MinerDumpDistance;


class TestComponent extends Component
{
    // свойства для редактирования в автоматическом режиме
    public $selectedDumpId;
    public string $mode = 'balance';
    public bool $activeZonesOnly = true;
    public Collection $miners;
    public Collection $dumps;
    public $distributionResult;

    // свойства для ручного редактирования
    public $editMode = false;
    public $tempAssignments = [];
    public $availableDumps = [];
    public $allMinerDumpScores = [];
    // для подсветки уже сохраненных маршрутов при ручном редактировании 
    public array $savedRoutes = [];
    // массив названий перегрузок
    public array $dumpNames = [];

    // массив для статистических данных распределения
    public array $stats = [
    'auto_avg_distance' => 0,
    'auto_avg_score' => 0,
    'manual_avg_distance' => 0, 
    'manual_avg_score' => 0,
    'saved_avg_distance' => 0,
    'saved_avg_score' => 0,
    'total_improvement' => 0
];
    
    
    public function mount(): void
    {
        // в случае перехода со страницы на страницу явно устанавливаем состояние зон
        $this->activeZonesOnly = true;
        // передаем названия дамп в массив для вывода по id
        $this->dumpNames = Dump::pluck('name_dump', 'id')->toArray();
        $this->loadSavedRoutes();

        

        $this->dumps = DB::table('dumps')
            ->select('id', 'name_dump as name')
            ->get();
            
        $firstDump = $this->dumps->first();
        $this->selectedDumpId = $firstDump?->id ?? null;
        
        $this->miners = new Collection();
        $this->loadMiners();
        //при загрузке страницы отработает метод распределения 
        //$this->distribute();
    }

    protected function loadSavedRoutes(): void
    {
            // Все АКТИВНЫЕ маршруты из БД
    $this->savedRoutes = MiningOrder::where('active', true)
        ->pluck('dump_id', 'miner_id')
        ->toArray();
    }

    public function loadMiners(): void
    {
        if (!$this->selectedDumpId) {
            $this->miners = new Collection();
            return;
        }

        $this->miners = DB::table('miners as m')
            ->join('miner_dump_distances as mdd', 'm.id', '=', 'mdd.miner_id')
            ->where('mdd.dump_id', $this->selectedDumpId)
            ->select('m.id', 'm.name_miner as name', 'm.active', 'mdd.distance_km')
            ->orderBy('mdd.distance_km')
            ->limit(20)
            ->get();
    }

    public function updatedSelectedDumpId(): void
    {
        $this->loadMiners();
    }

        // ✅ ЭТО ОСНОВНАЯ ФУНКЦИЯ ДИСПЕТЧЕРА!
    public function distribute(): void
    {
        
        // 
        $controller = app(\App\Http\Controllers\User\Dump\DistributionController::class);
        $request = request()->merge([
            'mode' => $this->mode,
            'active_zones_only' => $this->activeZonesOnly,
        ]);
        
        $view = $controller->index($request);
        
        // 🔥 ИЗВЛЕКАЕМ ЛУЧШИЕ + ВСЕ score!
        $assignmentsPoints = $view->getData()['assignmentsPoints'] ?? [];
        $stats = $view->getData()['stats'] ?? [];
        
        $this->distributionResult = [
            'distribution' => $assignmentsPoints,
            'stats' => $stats
        ];
        
        // 🔥 НОВОЕ: СЧИТАЕМ score ДЛЯ ВСЕХ miner-dump пар
        $this->calculateAllMinerDumpScores($assignmentsPoints);
        // запуск метода расчета статистики
        $this->calculateStats();
    }

    private function calculateAllMinerDumpScores($assignmentsPoints)
    {
        $this->allMinerDumpScores = [];
        
        $allMinerIds = collect($assignmentsPoints)->pluck('miner_id', 'miner_id')->unique()->values();
        
        foreach($allMinerIds as $minerId) {
            $this->allMinerDumpScores[$minerId] = [];
            
            foreach($this->dumps as $dump) {
                $dumpName = $dump->name;
                // 🔥 БЕРЁМ расстояние из БД
                $distanceData = MinerDumpDistance::where('miner_id', $minerId)
                    ->where('dump_id', $dump->id)
                    ->first();
                
                $distance = $distanceData?->distance_km ?? 999;
                
                // 🔥 ПРОСТАЯ ФОРМУЛА score (замени на свою позже):
                // score = 100 - (distance × 10) - штраф за дальность
                $baseScore = 100 - ($distance * 8);
                
                // Корректировка по режиму
                switch($this->mode) {
                    case 'distance':
                        $score = 100 - ($distance * 12); // Приоритет близости
                        break;
                    case 'volume':
                        $score = 85 - ($distance * 5); // Меньше штраф за расстояние
                        break;
                    default: // balance
                        $score = $baseScore;
                        break;
                }
                
                $this->allMinerDumpScores[$minerId][$dump->id] = [
                    'dump_name' => $dumpName,
                    'distance' => $distance,
                    'score' => max(0, round($score, 1)),
                    'travel_time' => $distance / 20 // 20 км/ч
                ];
            }
        }
    }

    public function fixCurrentShift()
    {
        $savedCount = 0;
        
        foreach($this->distributionResult['distribution'] as $minerId => $minerAssignments) {
            $assignment = $minerAssignments[0] ?? null;
            if (!$assignment) continue;
            
            // Берём ручной выбор ИЛИ авто
            $dumpId = $this->tempAssignments[$minerId] ?? $assignment['dump_id'];
            
            MiningOrder::create([
                'miner_id' => $minerId,
                'dump_id' => $dumpId,
                'operator_id' => null, // Позже диспетчер
                'distance_km' => $assignment['distance'],
                'score' => $assignment['score'],
                'active' => true,
                'assigned_round' => $assignment['assigned_round'] ?? 1,
            ]);
            
            $savedCount++;
        }
        
        session()->flash('success', "📊 Зафиксировано смена: $savedCount маршрутов");
    }

    public function toggleEditMode()
    {
        $this->editMode = !$this->editMode;
        
        if ($this->editMode) {
            // Загружаем текущие назначения для редактирования
            $this->tempAssignments = [];
            foreach($this->distributionResult['distribution'] ?? [] as $minerId => $assignments) {
                $this->tempAssignments[$minerId] = $assignments[0]['dump_id'] ?? null;
            }
            // Все доступные дампы
            $this->availableDumps = Dump::pluck('name_dump', 'id')->toArray();
        }
    }
    public function saveMiningOrders(): void
    {
        if (!$this->distributionResult || !isset($this->distributionResult['distribution'])) {
            session()->flash('success', 'Нет данных для сохранения');
            return;
        }

        $savedCount = 0;
        $manualChanges = 0;
        
        foreach ($this->distributionResult['distribution'] as $minerId => $minerAssignments) {
            $assignment = $minerAssignments[0] ?? null;
            if (!$assignment) continue;

            $originalDumpId = $assignment['dump_id'];
            $newDumpId = $this->tempAssignments[$minerId] ?? $originalDumpId;
            
            // 🔥 ТОЛЬКО если dump_id РЕАЛЬНО ИЗМЕНИЛСЯ!
            if ($newDumpId != $originalDumpId) {
                $manualChanges++;
            }

            $distanceData = MinerDumpDistance::where('miner_id', $minerId)
                ->where('dump_id', $newDumpId)
                ->first();

            $distance = $distanceData?->distance_km ?? $assignment['distance'] ?? 0;
            $score    = $distance > 0 ? max(0, 100 - ($distance * 8)) : $assignment['score'] ?? 0;

            MiningOrder::updateOrCreate(
                ['miner_id' => $minerId],
                [
                    'dump_id'      => $newDumpId,
                    'operator_id'  => null,
                    'distance_km'  => $distance,
                    'score'        => $score,
                    'active'       => true,
                    'assigned_round' => $assignment['assigned_round'] ?? 1,
                ]
            );

            $savedCount++;
            
        }
        // Обновим сохранённые маршруты
        $this->loadSavedRoutes();  
        // и пересчитаем статистику по сохраненным маршрутам
        $this->calculateStats(); 

        $this->tempAssignments = [];
        $this->editMode = false;
        
        
        $message = "Сохранено маршрутов: $savedCount";
        if ($manualChanges > 0) {
            $message .= " (из них $manualChanges изменено)";
        }
        session()->flash('success', $message);
    }

    private function getDistanceForMinerDump($minerId, $dumpId)
    {
        // ВРЕМЕННО — потом возьмёшь из miner_dump_distances
        return 3.0; 
    }

    private function getScoreForMinerDump($minerId, $dumpId)
    {
        // ВРЕМЕННО — потом из твоего алгоритма score
        return 75.5;
    }
    
    public function getMinerDumpPriorities($minerId)
    {
        // 🔥 ВЕРНИ ВСЕ варианты для miner по твоему алгоритму score
        return MinerDumpDistance::where('miner_id', $minerId)
            ->join('dumps', 'miner_dump_distances.dump_id', '=', 'dumps.id')
            ->select('dumps.id', 'dumps.name_dump', 'miner_dump_distances.distance_km', 'score')
            ->orderByDesc('score') // Лучшие сверху!
            ->get()
            ->keyBy('id')
            ->toArray();
    }

    //  функции для рассчета и вывода статистики распределения
    public function updatedTempAssignments($value, $keyAtRoot)
    {
        $this->calculateStats();
    }

    public function calculateStats(): void
    {
        $autoDistances = [];
        $autoScores = [];
        $manualDistances = [];
        $manualScores = [];
        $savedDistances = [];
        $savedScores = [];

        foreach($this->distributionResult['distribution'] ?? [] as $minerId => $minerAssignments) {
            $assignment = $minerAssignments[0] ?? null;
            if (!$assignment) continue;

            // Автоматическое
            $autoDistances[] = $assignment['distance'];
            $autoScores[] = $assignment['score'];

            // Ручное
            if (isset($this->tempAssignments[$minerId])) {
                $manualDumpId = $this->tempAssignments[$minerId];
                $manualDistanceData = MinerDumpDistance::where('miner_id', $minerId)
                    ->where('dump_id', $manualDumpId)
                    ->first();
                $manualDistances[] = $manualDistanceData?->distance_km ?? 999;
                $manualScores[] = max(0, 100 - ($manualDistances[count($manualDistances)-1] * 8));
            }

            // Сохранённое
            if (isset($this->savedRoutes[$minerId])) {
                $savedDumpId = $this->savedRoutes[$minerId];
                $savedDistanceData = MinerDumpDistance::where('miner_id', $minerId)
                    ->where('dump_id', $savedDumpId)
                    ->first();
                $savedDistances[] = $savedDistanceData?->distance_km ?? 999;
                $savedScores[] = max(0, 100 - ($savedDistances[count($savedDistances)-1] * 8));
            }
        }

        $this->stats = [
            'auto_avg_distance' => count($autoDistances) ? array_sum($autoDistances)/count($autoDistances) : 0,
            'auto_avg_score' => count($autoScores) ? array_sum($autoScores)/count($autoScores) : 0,
            'manual_avg_distance' => count($manualDistances) ? array_sum($manualDistances)/count($manualDistances) : 0,
            'manual_avg_score' => count($manualScores) ? array_sum($manualScores)/count($manualScores) : 0,
            'saved_avg_distance' => count($savedDistances) ? array_sum($savedDistances)/count($savedDistances) : 0,
            'saved_avg_score' => count($savedScores) ? array_sum($savedScores)/count($savedScores) : 0,
            'total_improvement' => $this->stats['auto_avg_score'] - $this->stats['saved_avg_score']
        ];
    }



    public function render()
    {
        return view('livewire.test-component')->layout('components.layouts.app');
    }
}
