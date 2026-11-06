<?php

namespace App\Livewire\Traits\Master;

use Livewire\Component;
use Livewire\Attributes\On;
use App\Services\ShiftService;
use App\Models\TruckTrip;
use App\Models\Truck;
use App\Models\Miner;
use App\Models\Rock;
use App\Models\Zone;
use App\Models\Mashine;
use App\Models\Dump;
use App\Models\Order;
use App\Models\Category;
use App\Models\User;
use App\Models\MiningOrder;
use App\Models\TruckPlannedTask;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

trait WithAlerts
{
    public array $alertFilters = [
        'type'     => [],
        'severity' => [],
        'entity'   => [],
        'status'   => [],
    ];

    public function getNewAlertsCountProperty(): int
    {
        return \App\Models\AiAlert::new()->count();
    }

    public function getRecentAlertsProperty()
    {
        $query = \App\Models\AiAlert::active();

        if (!empty($this->alertFilters['type'])) {
            $query->whereIn('type', $this->alertFilters['type']);
        }
        if (!empty($this->alertFilters['severity'])) {
            $query->whereIn('severity', $this->alertFilters['severity']);
        }
        if (!empty($this->alertFilters['entity'])) {
            $query->whereIn('entity_type', $this->alertFilters['entity']);
        }
        if (!empty($this->alertFilters['status'])) {
            $query->whereIn('status', $this->alertFilters['status']);
        }

        return $query->orderBy('created_at', 'desc')->limit(50)->get();
    }

    public function getAlertFilterOptionsProperty(): array
    {
        return [
            'type' => [
                ['value' => 'truck_anomaly',        'label' => 'Аномалия рейса',  'icon' => 'fa-truck'],
                ['value' => 'productivity_drop',    'label' => 'Падение произв.', 'icon' => 'fa-chart-line'],
                ['value' => 'empty_run_high',       'label' => 'Холостой пробег','icon' => 'fa-route'],
                ['value' => 'zone_overflow',        'label' => 'Переполнение зоны','icon' => 'fa-layer-group'],
                ['value' => 'maintenance_predict', 'label' => 'Риск поломки',    'icon' => 'fa-wrench'],
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

    public function getAlertFiltersActiveProperty(): bool
    {
        return !empty($this->alertFilters['type'])
            || !empty($this->alertFilters['severity'])
            || !empty($this->alertFilters['entity'])
            || !empty($this->alertFilters['status']);
    }

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

    public function isAlertFilterActive(string $category, string $value): bool
    {
        return isset($this->alertFilters[$category])
            && in_array($value, $this->alertFilters[$category], true);
    }

    public function resetAlertFilters(): void
    {
        $this->alertFilters = [
            'type'     => [],
            'severity' => [],
            'entity'   => [],
            'status'   => [],
        ];
    }

    public function acknowledgeAlert(int $alertId): void
    {
        $alert = \App\Models\AiAlert::find($alertId);
        if ($alert) {
            $alert->acknowledge(auth()->id());
        }
    }

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
