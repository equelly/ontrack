<?php

namespace App\Livewire\Traits;

use App\Models\Truck;
use App\Models\Miner;
use App\Models\Dump;
use App\Models\Zone;
use App\Models\Rock;
use App\Models\TruckTrip;
use App\Models\MiningOrder;
use App\Models\TripPause;
use App\Models\SystemSetting;
use App\Models\ServicePost;
use App\Models\TruckPlannedTask;
use App\Events\DriverRouteUpdated;
use App\Events\ExcavatorNotification;
use App\Services\RouteAssignmentService;
use App\Services\RouteOptimizerService;
use App\Services\MinerStatusService;
use App\Services\ZoneStatusService;
use App\Services\ServiceSchedulingService;
use App\Services\ShiftPlanningService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;

trait WithMiners
{
    public function getActiveMinersCountProperty(): int
    {
        return $this->miners->where('status', 'active')->count();
    }

    public function getBreakdownCountProperty(): int
    {
        return $this->trucks->where('status', 'breakdown')->count();
    }

    /**
     * Количество забоев в поломке
     */
    public function getMinerBreakdownCountProperty(): int
    {
        return $this->miners->where('status', 'breakdown')->count();
    }

    /**
     * Количество забоев в задержке (вспомогательные работы, НЕ breakdown).
     */
    public function getMinerDelayedCountProperty(): int
    {
        return $this->miners
            ->whereIn('status', ['maintenance', 'face_dismantling', 'access_setup', 'relocation'])
            ->count();
    }

    /**
     * Активные экскаваторы с породой в забое
     */
    public function getActiveMinersWithRockProperty()
    {
        // Фильтруем по status=active (поле active убрано как наследие)
        return $this->miners
            ->filter(fn($m) => $m->status === Miner::STATUS_ACTIVE && ($m->currentRock || $m->rocks->isNotEmpty()))
            ->values();
    }

    #[On('miner-productivity-updated')]
    public function onMinerProductivityUpdated(array $data): void
    {
        // Обновляем данные о забоях
        $this->miners = Miner::with(['rocks', 'currentRock'])->get();

        // Отправляем уведомление
        $minerName = $data['miner_name'] ?? "Забой #{$data['miner_id']}";
        $this->dispatch('notify', [
            'type' => 'info',
            'message' => "Обновлено время погрузки забоя «{$minerName}»: {$data['target_load_time']} сек",
        ]);
    }

    // =========================================
    // УПРАВЛЕНИЕ ЗАБОЯМИ
    // =========================================
    // Раньше тут был метод toggleMinerStatus() — переключал булево поле `active`.
    // Поле убрано как наследие. Управление статусом забоя — через setStatus()
    // (status: active / maintenance / breakdown / ...).

    /**
     * Открыть модальное окно смены статуса забоя
     */
    public function openMinerStatusModal(int $minerId): void
    {
        $miner = Miner::find($minerId);
        if ($miner) {
            $this->editMinerStatusId = $minerId;
            $this->editMinerStatusNew = $miner->status;
        }
    }

    /**
     * Закрыть модальное окно смены статуса забоя
     */
    public function closeMinerStatusModal(): void
    {
        $this->editMinerStatusId = null;
        $this->editMinerStatusNew = null;
    }

    /**
     * Сохранить новый статус забоя
     */
    public function setMinerStatus(): void
    {
        if (!$this->editMinerStatusId || !$this->editMinerStatusNew) {
            return;
        }

        $miner = Miner::find($this->editMinerStatusId);
        if (!$miner) {
            $this->dispatch('notify', ['type' => 'error', 'message' => 'Забой не найден']);
            return;
        }

        $oldStatus = $miner->status;

        try {
            $statusService = app(MinerStatusService::class);
            $result = $statusService->changeStatus($miner, $this->editMinerStatusNew, Auth::id());

            if ($result['success']) {
                $this->closeMinerStatusModal();
                $this->loadData();

                $this->dispatch('notify', [
                    'type' => in_array($this->editMinerStatusNew, Miner::STATUSES_DELAYED) ? 'warning' : 'success',
                    'message' => "Статус забоя {$miner->name_miner}: {$miner->getStatusLabel()}",
                ]);

                // === EVENT-DRIVEN: пересмотр маршрутов при остановке/запуске забоя ===
                $wasActive = in_array($oldStatus, [Miner::STATUS_ACTIVE]) || $oldStatus === 'active';
                $isActive = in_array($this->editMinerStatusNew, [Miner::STATUS_ACTIVE]) || $this->editMinerStatusNew === 'active';

                if ($wasActive && !$isActive) {
                    // Забой ОСТАНОВЛЕН → деактивировать маршруты + переназначить самосвалы
                    $this->onMinerStopped($miner->id);
                } elseif (!$wasActive && $isActive) {
                    // Забой ЗАПУЩЕН → создать пары + оптимизация (если auto)
                    $this->onMinerStarted($miner->id);
                }
            } else {
                $this->dispatch('notify', ['type' => 'error', 'message' => $result['message']]);
            }
        } catch (\Exception $e) {
            Log::error('setMinerStatus failed', ['error' => $e->getMessage()]);
            $this->dispatch('notify', ['type' => 'error', 'message' => 'Ошибка: ' . $e->getMessage()]);
        }
    }

    /**
     * Список всех статусов забоя
     */
    public function getMinerStatusesProperty(): array
    {
        return Miner::getAllStatuses();
    }

    /**
     * Список типов пауз забоя для фильтра
     */
    public function getMinerDelayStatusesProperty(): array
    {
        return \App\Models\MinerPause::types();
    }

    /**
     * Статистика по простоям забоев
     */
    public function getMinerDelaysProperty(): array
    {
        $query = \App\Models\MinerPause::with(['miner'])
            ->orderBy('started_at', 'desc');

        // Период
        $periodLabel = 'За смену';
        switch ($this->pausePeriod) {
            case 'today':
                $query->whereDate('started_at', today());
                $periodLabel = 'За сегодня';
                break;
            case 'week':
                $query->where('started_at', '>=', now()->subWeek());
                $periodLabel = 'За неделю';
                break;
            case 'month':
                $query->where('started_at', '>=', now()->subMonth());
                $periodLabel = 'За месяц';
                break;
            default: // shift
                // Для смены берём последние 12 часов (покрывает ночную смену)
                // или от начала дня если сейчас день
                $shiftStart = now()->subHours(12);
                if ($shiftStart->isYesterday()) {
                    // Если смена началась вчера, берём с этого времени
                    $query->where('started_at', '>=', $shiftStart);
                } else {
                    // Иначе берём с начала дня
                    $query->where('started_at', '>=', now()->startOfDay());
                }
                $periodLabel = 'За смену';
        }

        // Фильтр по типу статуса
        if (!empty($this->minerPauseTypes)) {
            $query->whereIn('type', $this->minerPauseTypes);
        }

        $pauses = $query->get();

        // Группируем по типу
        $byStatus = $pauses->groupBy('type')->map(function ($group, $type) {
            $totalSeconds = $group->sum(function ($p) {
                return $p->getCurrentDuration();
            });
            return [
                'status' => $type,
                'label' => \App\Models\MinerPause::typeLabel($type),
                'count' => $group->count(),
                'total_minutes' => round($totalSeconds / 60),
                'total_formatted' => $this->formatMinutes(round($totalSeconds / 60)),
            ];
        })->values();

        $totalSeconds = $pauses->sum(fn($p) => $p->getCurrentDuration());
        $totalMinutes = round($totalSeconds / 60);

        return [
            'pauses' => $pauses,
            'total_count' => $pauses->count(),
            'total_minutes' => $totalMinutes,
            'total_formatted' => $this->formatMinutes($totalMinutes),
            'active_count' => $pauses->whereNull('ended_at')->count(),
            'by_status' => $byStatus,
            'period_label' => $periodLabel,
        ];
    }
}
