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

trait WithAlerts
{
    // ==========================================
    // AI АЛЕРТЫ
    // ==========================================

    /**
     * Количество новых (непросмотренных) алертов.
     */
    public function getNewAlertsCountProperty(): int
    {
        return \App\Models\AiAlert::new()->count();
    }

    /**
     * Активные алерты с применёнными фильтрами (для виджета).
     */
    public function getRecentAlertsProperty()
    {
        $query = \App\Models\AiAlert::active();

        // Фильтр по типу
        if (!empty($this->alertFilters['type'])) {
            $query->whereIn('type', $this->alertFilters['type']);
        }

        // Фильтр по severity
        if (!empty($this->alertFilters['severity'])) {
            $query->whereIn('severity', $this->alertFilters['severity']);
        }

        // Фильтр по сущности
        if (!empty($this->alertFilters['entity'])) {
            $query->whereIn('entity_type', $this->alertFilters['entity']);
        }

        // Фильтр по статусу (по умолчанию active = new + acknowledged)
        // Если выбраны конкретные — фильтруем
        if (!empty($this->alertFilters['status'])) {
            $query->whereIn('status', $this->alertFilters['status']);
        }

        return $query->orderBy('created_at', 'desc')->limit(50)->get();
    }

    /**
     * Метаданные для фильтров — какие бывают типы/severity/сущности.
     * Используется в blade для отрисовки чипов фильтров.
     */
    public function getAlertFilterOptionsProperty(): array
    {
        return [
            'type' => [
                ['value' => 'truck_anomaly',         'label' => 'Аномалия рейса',     'icon' => 'fa-truck'],
                ['value' => 'productivity_drop',     'label' => 'Падение произв.',    'icon' => 'fa-chart-line'],
                ['value' => 'empty_run_high',        'label' => 'Холостой пробег',    'icon' => 'fa-route'],
                ['value' => 'zone_overflow',         'label' => 'Переполнение зоны',  'icon' => 'fa-layer-group'],
                ['value' => 'zone_volume_action',    'label' => 'Мало материала (<760)', 'icon' => 'fa-triangle-exclamation'],
                ['value' => 'avg_distance_high',     'label' => 'Ср. расстояние > порога', 'icon' => 'fa-arrows-left-right-to-line'],
                ['value' => 'balance_suggestion',    'label' => 'Балансировка парка',  'icon' => 'fa-scale-balanced'],
                ['value' => 'maintenance_predict',  'label' => 'Риск поломки',       'icon' => 'fa-wrench'],
            ],
            'severity' => [
                ['value' => 'critical', 'label' => 'Критично',  'icon' => 'fa-circle text-red-500'],
                ['value' => 'warning',  'label' => 'Внимание', 'icon' => 'fa-circle text-amber-500'],
                ['value' => 'info',      'label' => 'Инфо',     'icon' => 'fa-circle text-blue-500'],
            ],
            'entity' => [
                ['value' => 'truck', 'label' => 'Самосвалы', 'icon' => 'fa-truck'],
                ['value' => 'miner', 'label' => 'Забои',     'icon' => 'fa-hard-hat'],
                ['value' => 'zone',  'label' => 'Зоны',       'icon' => 'fa-map-marker-alt'],
                ['value' => 'fleet', 'label' => 'Парк',        'icon' => 'fa-truck-loading'],
            ],
            'status' => [
                ['value' => 'new',          'label' => 'Новые',          'icon' => 'fa-circle text-red-500'],
                ['value' => 'acknowledged', 'label' => 'Подтверждённые', 'icon' => 'fa-circle text-emerald-500'],
            ],
        ];
    }

    /**
     * Активен ли фильтр (хотя бы один) — для кнопки "Сбросить".
     */
    public function getAlertFiltersActiveProperty(): bool
    {
        return !empty($this->alertFilters['type'])
            || !empty($this->alertFilters['severity'])
            || !empty($this->alertFilters['entity'])
            || !empty($this->alertFilters['status']);
    }

    /**
     * Переключить фильтр (вкл/выкл) по категории.
     * Вызывается из blade: wire:click="toggleAlertFilter('severity', 'critical')"
     */
    public function toggleAlertFilter(string $category, string $value): void
    {
        if (!isset($this->alertFilters[$category])) {
            $this->alertFilters[$category] = [];
        }

        $idx = array_search($value, $this->alertFilters[$category], true);
        if ($idx === false) {
            $this->alertFilters[$category][] = $value;
        } else {
            unset($this->alertFilters[$category][$idx]);
            $this->alertFilters[$category] = array_values($this->alertFilters[$category]);
        }
    }

    /**
     * Проверить, активен ли конкретный фильтр (для подсветки чипа).
     */
    public function isAlertFilterActive(string $category, string $value): bool
    {
        return isset($this->alertFilters[$category])
            && in_array($value, $this->alertFilters[$category], true);
    }

    /**
     * Сбросить все фильтры.
     */
    public function resetAlertFilters(): void
    {
        $this->alertFilters = [
            'type'     => [],
            'severity' => [],
            'entity'   => [],
            'status'   => [],
        ];
    }

    /**
     * Подтвердить алерт.
     */
    public function acknowledgeAlert(int $alertId): void
    {
        $alert = \App\Models\AiAlert::find($alertId);
        if ($alert) {
            $alert->acknowledge(auth()->id());
        }
    }

    /**
     * Подтвердить все.
     */
    public function acknowledgeAllAlerts(): void
    {
        \App\Models\AiAlert::new()->update([
            'status' => \App\Models\AiAlert::STATUS_ACKNOWLEDGED,
            'acknowledged_at' => now(),
            'acknowledged_by' => auth()->id(),
        ]);
        $this->dispatch('notify', ['type' => 'info', 'message' => 'Все алерты подтверждены']);
    }
}
