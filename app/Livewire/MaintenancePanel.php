<?php

namespace App\Livewire;

use App\Models\Order;
use App\Models\User;
use App\Models\Mashine;
use App\Models\Category;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * MaintenancePanel — панель обслуживающего персонала (механики, электрики, диспетчер ТОиР).
 *
 * Что показывает:
 *   - Лента заявок (с фильтрами: status, work_type, приоритет, оборудование)
 *   - Сводку: сколько новых, в работе, пауз, выполнено за смену
 *   - Сервисные посты (занятость) — опционально
 *
 * Действия (в зависимости от прав):
 *   - Взять заявку в работу (если подходит по work_type)
 *   - Отметить выполнение
 *   - Поставить на паузу (с причиной)
 *   - Возобновить
 *   - Отменить (только автор / maintenance_dispatcher / admin)
 *   - Переназначить на другого исполнителя (maintenance_dispatcher / admin)
 *
 * Права:
 *   - mechanic: берёт только механические заявки (work_type=mechanic/hydraulic/to1/to2/tire_service/berm/other)
 *   - electric: берёт только электрические (work_type=electric/to1/to2/other)
 *   - maintenance_dispatcher: видит все, может брать любые, может переназначать
 *   - admin: всё
 */
#[Layout('components.layouts.app')]
#[Title('Панель обслуживания')]
class MaintenancePanel extends Component
{
    // ==========================================
    // ФИЛЬТРЫ
    // ==========================================
    public string $filterStatus = 'all';        // all | new | in_progress | paused | completed | cancelled
    public string $filterWorkType = 'all';      // all | mechanic | electric | to1 | to2 | ...
    public string $filterPriority = 'all';      // all | low | medium | high | critical
    public ?int $filterMashineId = null;        // null = все оборудование
    public ?int $filterAssignedTo = null;      // null = все, 'me' = только мои, ID = конкретный исполнитель
    public bool $showOnlyMine = false;          // shortcut: только мои заявки
    public string $filterPeriod = 'all';        // all | today | week | month — по времени создания заявки
    public bool $showWithSetsOnly = false;      // только заявки с комплектацией (есть mashine_sets)

    // ==========================================
    // МОДАЛКИ
    // ==========================================
    public bool $showCreateOrderModal = false;
    public bool $showAssignModal = false;        // модалка переназначения
    public ?int $assignModalOrderId = null;
    public ?int $assignToUserId = null;
    public ?int $viewingOrderId = null;

    // Данные формы создания заявки
    public ?int $newOrderMashineId = null;
    public string $newOrderWorkType = Order::WORK_MECHANIC;
    public string $newOrderPriority = Order::PRIORITY_MEDIUM;
    public string $newOrderContent = '';

    // Причина паузы/отмены
    public ?int $pauseOrderId = null;
    public ?int $cancelOrderId = null;
    public string $pauseReason = '';
    public string $cancelReason = '';

    public $orders;
    public $mashines;
    public $executors; // обслуживающий персонал для переназначения

    public function mount(): void
    {
        $this->loadData();
    }

    public function loadData(): void
    {
        $this->mashines = Mashine::orderBy('number')->get();
        $this->executors = User::where('role', User::ROLE_MAINTENANCE)
            ->whereIn('position', [
                User::POSITION_MECHANIC,
                User::POSITION_ELECTRIC,
                User::POSITION_MAINTENANCE_DISPATCHER,
            ])
            ->orderBy('name')
            ->get(['id', 'name', 'position']);
        $this->orders = $this->buildOrdersQuery()->get();
    }

    /**
     * Построить запрос заявок с применёнными фильтрами.
     */
    protected function buildOrdersQuery()
    {
        // eager-load mashine + mashine.sets (для фильтра комплектации и отображения)
        $q = Order::with(['mashine.sets', 'user', 'userExec', 'category'])
            ->withTrashed()
            ->whereNull('deleted_at');

        if ($this->filterStatus !== 'all') {
            $q->where('status', $this->filterStatus);
        }
        if ($this->filterWorkType !== 'all') {
            $q->where('work_type', $this->filterWorkType);
        }
        if ($this->filterPriority !== 'all') {
            $q->where('priority', $this->filterPriority);
        }
        if ($this->filterMashineId) {
            $q->where('mashine_id', $this->filterMashineId);
        }
        if ($this->showOnlyMine) {
            // «Только мои» = где я АВТОР (user_id_req) ИЛИ ИСПОЛНИТЕЛЬ (assigned_to/user_exec).
            // Покрывает:
            //   - заявки, которые я создал (но ещё не взял в работу)
            //   - заявки, которые я взял в работу (assigned_to = я)
            //   - legacy-заявки со старым полем user_exec (до миграции)
            $userId = Auth::id();
            $q->where(function ($sub) use ($userId) {
                $sub->where('user_id_req', $userId)
                    ->orWhere('assigned_to', $userId)
                    ->orWhere('user_exec', $userId);
            });
        } elseif ($this->filterAssignedTo) {
            $q->where('assigned_to', $this->filterAssignedTo);
        }

        // Фильтр по времени (по created_at)
        if ($this->filterPeriod !== 'all') {
            $now = now();
            $from = match ($this->filterPeriod) {
                'today' => $now->copy()->startOfDay(),
                'week'  => $now->copy()->startOfWeek(),
                'month' => $now->copy()->startOfMonth(),
                default => null,
            };
            if ($from) {
                $q->where('created_at', '>=', $from);
            }
        }

        // Фильтр по комплектации — только заявки, у оборудования которых есть mashine_sets
        if ($this->showWithSetsOnly) {
            $q->whereHas('mashine.sets');
        }

        // Сортировка: сначала активные, потом по приоритету (выше = раньше), затем по дате
        return $q->orderByRaw("CASE status
                WHEN 'new' THEN 1
                WHEN 'in_progress' THEN 2
                WHEN 'paused' THEN 3
                WHEN 'completed' THEN 4
                WHEN 'cancelled' THEN 5
                ELSE 6 END")
            ->orderByRaw("CASE priority
                WHEN 'critical' THEN 1
                WHEN 'high' THEN 2
                WHEN 'medium' THEN 3
                WHEN 'low' THEN 4
                ELSE 5 END")
            ->orderBy('created_at', 'desc');
    }

    /**
     * Сводка по статусам (для KPI-блоков сверху).
     */
    public function getSummaryProperty(): array
    {
        $base = Order::query();

        return [
            'new'         => (clone $base)->where('status', Order::STATUS_NEW)->count(),
            'in_progress' => (clone $base)->where('status', Order::STATUS_IN_PROGRESS)->count(),
            'paused'      => (clone $base)->where('status', Order::STATUS_PAUSED)->count(),
            'completed'   => (clone $base)->where('status', Order::STATUS_COMPLETED)
                ->whereDate('completed_at', today())->count(),
            'critical'    => (clone $base)->where('priority', Order::PRIORITY_CRITICAL)
                ->whereIn('status', [Order::STATUS_NEW, Order::STATUS_IN_PROGRESS])
                ->count(),
        ];
    }

    /**
     * Данные для вкладки «Комплектация для завозки».
     *
     * Группирует mashine_sets по mashine — выводит один раз оборудование
     * и список всех расходников, которые нужно завезти.
     */
    public function getKitsDataProperty(): array
    {
        // Получаем все mashine_sets с связанными mashine и set
        $mashineSets = \App\Models\MashineSet::with(['mashine', 'set'])
            ->whereHas('mashine')     // только если mashine существует
            ->whereHas('set')          // только если set существует
            ->get();

        if ($mashineSets->isEmpty()) {
            return [];
        }

        // Группируем по mashine_id
        $grouped = [];
        foreach ($mashineSets as $ms) {
            $mashineId = $ms->mashine_id;
            if (!isset($grouped[$mashineId])) {
                $grouped[$mashineId] = [
                    'mashine_id'     => $mashineId,
                    'mashine_number' => $ms->mashine->number,
                    'sets'           => [],
                    'sets_count'     => 0,
                ];
            }
            $grouped[$mashineId]['sets'][] = $ms->set;
            $grouped[$mashineId]['sets_count']++;
        }

        // Сортируем по номеру оборудования
        $result = array_values($grouped);
        usort($result, fn($a, $b) => strcmp($a['mashine_number'], $b['mashine_number']));

        return $result;
    }

    /**
     * Сколько всего позиций комплектации для завозки (для бейджа во вкладке).
     */
    public function getKitsCountProperty(): int
    {
        return \App\Models\MashineSet::count();
    }

    /**
     * Отметить расходник как завезённый → удалить mashine_set (он больше не нужен).
     * Снимает чекбокс → расходник исчезает из списка.
     */
    public function toggleSetDelivered(int $mashineId, int $setId): void
    {
        $mashine = Mashine::find($mashineId);
        $set = \App\Models\Set::find($setId);

        if (!$mashine || !$set) {
            $this->dispatch('notify', ['type' => 'error', 'message' => 'Оборудование или расходник не найден']);
            return;
        }

        \App\Models\MashineSet::where('mashine_id', $mashineId)
            ->where('set_id', $setId)
            ->delete();

        $this->dispatch('notify', [
            'type' => 'success',
            'message' => "✓ Завезено: {$set->name} на {$mashine->number}",
        ]);

        // Перегружаем заявки — может измениться фильтр «с комплектацией»
        $this->loadData();
    }

    public function updatedFilterStatus(): void { $this->loadData(); }
    public function updatedFilterWorkType(): void { $this->loadData(); }
    public function updatedFilterPriority(): void { $this->loadData(); }
    public function updatedFilterMashineId(): void { $this->loadData(); }
    public function updatedFilterAssignedTo(): void { $this->loadData(); }
    public function updatedFilterPeriod(): void { $this->loadData(); }
    public function updatedShowWithSetsOnly(): void { $this->loadData(); }
    public function updatedShowOnlyMine(): void { $this->loadData(); }

    public function resetFilters(): void
    {
        $this->filterStatus = 'all';
        $this->filterWorkType = 'all';
        $this->filterPriority = 'all';
        $this->filterMashineId = null;
        $this->filterAssignedTo = null;
        $this->showOnlyMine = false;
        $this->filterPeriod = 'all';
        $this->showWithSetsOnly = false;
        $this->loadData();
    }

    // ==========================================
    // ДЕЙСТВИЯ С ЗАЯВКАМИ
    // ==========================================

    /**
     * Взять заявку в работу.
     */
    public function takeOrder(int $orderId): void
    {
        $order = Order::findOrFail($orderId);
        $user = Auth::user();

        if (!$order->canBeTakenBy($user) || !$order->isAvailableForAssignment()) {
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => 'Нельзя взять эту заявку в работу',
            ]);
            return;
        }

        $order->update([
            'status'      => Order::STATUS_IN_PROGRESS,
            'assigned_to' => $user->id,
            'user_exec'   => $user->id,
            'started_at'  => now(),
        ]);

        $this->loadData();
        $this->dispatch('notify', [
            'type' => 'success',
            'message' => "Заявка #{$order->id} взята в работу",
        ]);
    }

    /**
     * Поставить заявку на паузу.
     */
    public function pauseOrder(int $orderId): void
    {
        $order = Order::findOrFail($orderId);
        $user = Auth::user();

        if ($order->assigned_to !== $user->id && !$user->canManageMaintenanceOrders()) {
            $this->dispatch('notify', ['type' => 'error', 'message' => 'Нельзя приостановить чужую заявку']);
            return;
        }

        $this->pauseOrderId = $orderId;
        $this->pauseReason = '';
        $this->dispatch('open-pause-modal');
    }

    public function confirmPause(): void
    {
        $order = Order::findOrFail($this->pauseOrderId);
        $order->update([
            'status'  => Order::STATUS_PAUSED,
            'cancelled_reason' => $this->pauseReason ?: 'Без указания причины',
        ]);

        $this->pauseOrderId = null;
        $this->pauseReason = '';
        $this->loadData();
        $this->dispatch('notify', [
            'type' => 'warning',
            'message' => "Заявка #{$order->id} приостановлена",
        ]);
    }

    /**
     * Возобновить заявку из паузы.
     */
    public function resumeOrder(int $orderId): void
    {
        $order = Order::findOrFail($orderId);
        $user = Auth::user();

        if ($order->assigned_to !== $user->id && !$user->canManageMaintenanceOrders()) {
            $this->dispatch('notify', ['type' => 'error', 'message' => 'Нельзя возобновить чужую заявку']);
            return;
        }

        $order->update(['status' => Order::STATUS_IN_PROGRESS]);

        $this->loadData();
        $this->dispatch('notify', [
            'type' => 'success',
            'message' => "Заявка #{$order->id} возобновлена",
        ]);
    }

    /**
     * Завершить заявку.
     */
    public function completeOrder(int $orderId): void
    {
        $order = Order::findOrFail($orderId);
        $user = Auth::user();

        if ($order->assigned_to !== $user->id && !$user->canManageMaintenanceOrders()) {
            $this->dispatch('notify', ['type' => 'error', 'message' => 'Нельзя завершить чужую заявку']);
            return;
        }

        // Завершаем — переносим в категорию «выполненные» (если есть)
        $completedCategory = Category::firstOrCreate(['title' => 'выполненные']);

        $order->update([
            'status'        => Order::STATUS_COMPLETED,
            'completed_at'  => now(),
            'user_exec'     => $user->id,
            'category_id'  => $completedCategory->id,
        ]);

        $this->loadData();
        $this->dispatch('notify', [
            'type' => 'success',
            'message' => "Заявка #{$order->id} завершена ✓",
        ]);
    }

    /**
     * Отменить заявку.
     */
    public function cancelOrder(int $orderId): void
    {
        $order = Order::findOrFail($orderId);
        $user = Auth::user();

        // Отменить может: автор, admin, maintenance_dispatcher
        if ($order->user_id_req !== $user->id && !$user->canManageMaintenanceOrders()) {
            $this->dispatch('notify', ['type' => 'error', 'message' => 'Нельзя отменить чужую заявку']);
            return;
        }

        $this->cancelOrderId = $orderId;
        $this->cancelReason = '';
        $this->dispatch('open-cancel-modal');
    }

    public function confirmCancel(): void
    {
        $order = Order::findOrFail($this->cancelOrderId);
        $user = Auth::user();

        $order->update([
            'status'           => Order::STATUS_CANCELLED,
            'cancelled_at'     => now(),
            'cancelled_reason' => $this->cancelReason ?: 'Без указания причины',
        ]);

        $this->cancelOrderId = null;
        $this->cancelReason = '';
        $this->loadData();
        $this->dispatch('notify', [
            'type' => 'warning',
            'message' => "Заявка #{$order->id} отменена",
        ]);
    }

    /**
     * Открыть модалку переназначения заявки.
     */
    public function openAssignModal(int $orderId): void
    {
        $user = Auth::user();
        if (!$user->canManageMaintenanceOrders()) {
            $this->dispatch('notify', ['type' => 'error', 'message' => 'Недостаточно прав']);
            return;
        }

        $this->assignModalOrderId = $orderId;
        $order = Order::find($orderId);
        $this->assignToUserId = $order?->assigned_to;
        $this->showAssignModal = true;
    }

    public function closeAssignModal(): void
    {
        $this->showAssignModal = false;
        $this->assignModalOrderId = null;
        $this->assignToUserId = null;
    }

    public function confirmAssign(): void
    {
        $user = Auth::user();
        if (!$user->canManageMaintenanceOrders() || !$this->assignModalOrderId || !$this->assignToUserId) {
            return;
        }

        $order = Order::findOrFail($this->assignModalOrderId);
        $newExecutor = User::find($this->assignToUserId);

        if (!$newExecutor || !$order->canBeTakenBy($newExecutor)) {
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => "Должность {$newExecutor?->positionLabel()} не подходит для типа работ заявки",
            ]);
            return;
        }

        $update = ['assigned_to' => $this->assignToUserId];
        // Если заявка новая — при назначении переводим в in_progress
        if ($order->status === Order::STATUS_NEW) {
            $update['status'] = Order::STATUS_IN_PROGRESS;
            $update['started_at'] = now();
        }

        $order->update($update);

        $this->closeAssignModal();
        $this->loadData();
        $this->dispatch('notify', [
            'type' => 'success',
            'message' => "Заявка #{$order->id} назначена: {$newExecutor->name}",
        ]);
    }

    // ==========================================
    // СОЗДАНИЕ ЗАЯВКИ (механик может подать от себя)
    // ==========================================
    public function openCreateOrderModal(): void
    {
        $this->showCreateOrderModal = true;
        $this->newOrderMashineId = null;
        $this->newOrderWorkType = Order::WORK_MECHANIC;
        $this->newOrderPriority = Order::PRIORITY_MEDIUM;
        $this->newOrderContent = '';
    }

    public function closeCreateOrderModal(): void
    {
        $this->showCreateOrderModal = false;
    }

    public function createOrder(): void
    {
        $this->validate([
            'newOrderMashineId' => 'required|exists:mashines,id',
            'newOrderWorkType'  => 'required|in:' . implode(',', array_keys(Order::WORK_TYPE_LABELS)),
            'newOrderPriority'  => 'required|in:' . implode(',', array_keys(Order::PRIORITY_LABELS)),
            'newOrderContent'    => 'required|string|min:3',
        ]);

        $currentCategory = Category::firstOrCreate(['title' => 'текущие']);

        Order::create([
            'mashine_id'  => $this->newOrderMashineId,
            'category_id' => $currentCategory->id,
            'user_id_req' => Auth::id(),
            'content'     => $this->newOrderContent,
            'status'      => Order::STATUS_NEW,
            'work_type'   => $this->newOrderWorkType,
            'priority'    => $this->newOrderPriority,
        ]);

        $this->closeCreateOrderModal();
        $this->loadData();
        $this->dispatch('notify', [
            'type' => 'success',
            'message' => 'Заявка создана',
        ]);
    }

    // ==========================================
    // ИНЛАЙН-РЕДАКТИРОВАНИЕ (Вариант C — прямо в карточке заявки)
    // ==========================================

    /**
     * Обновить приоритет заявки (после осмотра механиком).
     */
    public function updatePriority(int $orderId, string $priority): void
    {
        $order = Order::findOrFail($orderId);
        $user = Auth::user();

        // Любой обслуживающий персонал может осматривать и оценивать заявки
        // (механик/электрик/maintenance_dispatcher/admin)
        if (!$user->isMaintenance() && !$user->isAdmin()) {
            $this->dispatch('notify', ['type' => 'error', 'message' => 'Недостаточно прав']);
            return;
        }

        if (!array_key_exists($priority, Order::PRIORITY_LABELS)) {
            return;
        }

        $order->update([
            'priority'     => $priority,
            'inspector_id' => $user->id,
            'inspected_at' => now(),
        ]);

        $this->loadData();
        $this->dispatch('notify', [
            'type' => 'success',
            'message' => "Приоритет заявки #{$order->id}: " . Order::PRIORITY_LABELS[$priority],
        ]);
    }

    /**
     * Обновить оценку времени ремонта (часы + минуты → estimated_minutes).
     */
    public function updateEstimatedTime(int $orderId, int $hours = 0, int $minutes = 0): void
    {
        $order = Order::findOrFail($orderId);
        $user = Auth::user();

        // Любой обслуживающий персонал может осматривать и оценивать заявки
        if (!$user->isMaintenance() && !$user->isAdmin()) {
            $this->dispatch('notify', ['type' => 'error', 'message' => 'Недостаточно прав']);
            return;
        }

        $totalMinutes = ($hours * 60) + $minutes;
        if ($totalMinutes < 0 || $totalMinutes > 8 * 60) {
            $this->dispatch('notify', ['type' => 'error', 'message' => 'Время должно быть от 0 до 8 часов']);
            return;
        }

        $order->update([
            'estimated_minutes' => $totalMinutes > 0 ? $totalMinutes : null,
            'inspector_id'      => $user->id,
            'inspected_at'      => now(),
        ]);

        $this->loadData();
        $this->dispatch('notify', [
            'type' => 'success',
            'message' => "Оценка времени: " . $order->fresh()->estimatedTimeFormatted(),
        ]);
    }

    public function render()
    {
        return view('livewire.maintenance-panel');
    }
}