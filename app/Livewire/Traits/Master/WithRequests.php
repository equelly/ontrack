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

trait WithRequests
{
    public $categoryId = '';
    public $userId = '';
    public $mashineId = '';
    public $createdAt = '';
    public $contentSearch = '';
    public bool $showCreateOrderModal = false;
    public bool $showOrderDetailsModal = false;
    public ?int $viewingOrderId = null;
    public ?int $newOrderMashineId = null;
    public array $newOrderSets = [];
    public string $newOrderContent = '';
    public $newOrderImage = null;
    public $viewingOrder = null;
    public ?int $editOrderCategoryId = null;
    public ?int $editingOrderId = null;

    public function getMashinesProperty()
    {
        return Mashine::with(['orders' => function ($q) {
            $q->where('content', '!=', '')
              ->when($this->contentSearch, function ($q) {
                  $q->where('content', 'like', '%' . $this->contentSearch . '%');
              })
              ->when($this->categoryId, function ($q) {
                  $q->where('category_id', $this->categoryId);
              })
              ->when($this->userId, function ($q) {
                  $q->where('user_id', $this->userId);
              })
              ->when($this->createdAt, function ($q) {
                  $q->whereDate('created_at', $this->createdAt);
              });
        }, 'sets'])
        ->when($this->mashineId, function ($query) {
            $query->where('id', $this->mashineId);
        })
        ->get()
        ->filter(function ($mashine) {
            return $mashine->sets->isNotEmpty() || $mashine->orders->isNotEmpty();
        })
        ->values();
    }

    public function getCategoriesProperty()
    {
        return Cache::remember('master_panel.categories', 3600, function () {
            return Category::all();
        });
    }

    public function getUsersProperty()
    {
        return Cache::remember('master_panel.users', 3600, function () {
            return User::orderBy('name')->get();
        });
    }

    public function getAllMashinesProperty()
    {
        return Cache::remember('master_panel.all_mashines', 300, function () {
            return Mashine::orderBy('number')->get();
        });
    }

    public function getOrdersCountProperty(): int
    {
        return Order::where('content', '!=', '')->count();
    }

    public function openCreateOrderModal(): void
    {
        $this->editingOrderId = null;
        $this->reset(['newOrderMashineId', 'newOrderSets', 'newOrderContent', 'newOrderImage']);
        $this->showCreateOrderModal = true;
    }

    public function closeCreateOrderModal(): void
    {
        if ($this->newOrderImage) {
            $this->newOrderImage = null;
        }
        $this->showCreateOrderModal = false;
        $this->editingOrderId = null;
        $this->reset(['newOrderMashineId', 'newOrderSets', 'newOrderContent', 'newOrderImage']);
    }

    public function editOrder(int $orderId): void
    {
        $order = \App\Models\Order::with(['mashine.sets'])->find($orderId);
        if (!$order) {
            $this->dispatch('notify', ['type' => 'error', 'message' => 'Заявка не найдена']);
            return;
        }

        if ($order->user_id_req !== auth()->id() && auth()->user()?->role !== 'admin') {
            $this->dispatch('notify', ['type' => 'error', 'message' => 'Редактировать может только автор']);
            return;
        }

        $this->editingOrderId = $orderId;
        $this->newOrderMashineId = $order->mashine_id;
        $this->newOrderContent = $order->content ?? '';
        $this->newOrderImage = null;
        $this->newOrderSets = $order->mashine?->sets?->pluck('id')?->toArray() ?? [];

        $this->showOrderDetailsModal = false;
        $this->showCreateOrderModal = true;
    }

    public function saveOrder(): void
    {
        if ($this->editingOrderId) {
            $this->updateOrder();
        } else {
            $this->createOrder();
        }
    }

    public function updateOrder(): void
    {
        $order = \App\Models\Order::find($this->editingOrderId);
        if (!$order) {
            $this->dispatch('notify', ['type' => 'error', 'message' => 'Заявка не найдена']);
            return;
        }

        if ($order->user_id_req !== auth()->id() && auth()->user()?->role !== 'admin') {
            $this->dispatch('notify', ['type' => 'error', 'message' => 'Редактировать может только автор']);
            return;
        }

        $this->validate([
            'newOrderMashineId'  => 'required|exists:mashines,id',
            'newOrderContent'    => 'required|string|max:2000',
            'newOrderImage'     => 'nullable|image|mimes:jpg,jpeg,png,webp|max:5120',
        ]);

        $order->update([
            'content'     => $this->newOrderContent,
            'mashine_id'  => $this->newOrderMashineId,
        ]);

        if ($this->newOrderImage) {
            $imagePath = $this->newOrderImage->store('orders', 'public');
            $order->update(['image' => $imagePath]);
        }

        // Обновляем комплектацию
        \App\Models\MashineSet::where('mashine_id', $this->newOrderMashineId)->delete();
        foreach ($this->newOrderSets as $setId) {
            \App\Models\MashineSet::firstOrCreate([
                'mashine_id' => $this->newOrderMashineId,
                'set_id'     => $setId,
            ]);
        }

        $this->dispatch('notify', [
            'type'    => 'success',
            'message' => 'Заявка обновлена',
        ]);

        $this->closeCreateOrderModal();
    }

    public function createOrder(): void
    {
        $this->validate([
            'newOrderMashineId'  => 'required|exists:mashines,id',
            'newOrderContent'    => 'required|string|max:2000',
            'newOrderSets'       => 'array',
            'newOrderSets.*'    => 'exists:sets,id',
            'newOrderImage'     => 'nullable|image|mimes:jpg,jpeg,png,webp|max:5120',
        ]);

        // Находим или создаём категорию "текущие"
        $currentCategory = \App\Models\Category::firstOrCreate(['title' => 'текущие']);

        // Сохраняем фото, если загружено
        $imagePath = null;
        if ($this->newOrderImage) {
            $imagePath = $this->newOrderImage->store('orders', 'public');
        }

        // Создаём заявку
        $order = \App\Models\Order::create([
            'content'     => $this->newOrderContent,
            'mashine_id'  => $this->newOrderMashineId,
            'category_id' => $currentCategory->id,
            'user_id_req' => auth()->id(),
            'image'       => $imagePath,
        ]);

        // Сохраняем выбранные расходники в mashine_sets
        foreach ($this->newOrderSets as $setId) {
            \App\Models\MashineSet::firstOrCreate([
                'mashine_id' => $this->newOrderMashineId,
                'set_id'     => $setId,
            ]);
        }

        $this->dispatch('notify', [
            'type'    => 'success',
            'message' => 'Заявка создана и направлена мастеру',
        ]);

        $this->closeCreateOrderModal();
    }

    public function viewOrder(int $orderId): void
    {
        $this->viewingOrderId = $orderId;
        $this->viewingOrder = \App\Models\Order::with(['category', 'mashine.sets', 'user', 'userExec'])
            ->find($orderId);
        $this->editOrderCategoryId = $this->viewingOrder?->category_id;
        $this->showOrderDetailsModal = true;
    }

    public function closeOrderDetailsModal(): void
    {
        $this->showOrderDetailsModal = false;
        $this->viewingOrderId = null;
        $this->viewingOrder = null;
        $this->editOrderCategoryId = null;
    }

    public function deleteOrder(int $orderId): void
    {
        $order = \App\Models\Order::find($orderId);
        if (!$order) {
            $this->dispatch('notify', ['type' => 'error', 'message' => 'Заявка не найдена']);
            return;
        }

        // Проверка: только автор может удалить
        if ($order->user_id_req !== auth()->id() && auth()->user()?->role !== 'admin') {
            $this->dispatch('notify', ['type' => 'error', 'message' => 'Удалить заявку может только её автор']);
            return;
        }

        $order->delete();

        $this->dispatch('notify', [
            'type'    => 'info',
            'message' => 'Заявка удалена',
        ]);

        if ($this->viewingOrderId === $orderId) {
            $this->closeOrderDetailsModal();
        }
    }

    public function completeOrder(int $orderId): void
    {
        $order = \App\Models\Order::find($orderId);
        if (!$order) {
            $this->dispatch('notify', ['type' => 'error', 'message' => 'Заявка не найдена']);
            return;
        }

        $order->update([
            'user_exec' => auth()->id(),
        ]);

        // Сменить категорию на "выполненные"
        $doneCategory = \App\Models\Category::firstOrCreate(['title' => 'выполненные']);
        $order->update(['category_id' => $doneCategory->id]);

        $this->dispatch('notify', [
            'type'    => 'success',
            'message' => 'Заявка отмечена как выполненная',
        ]);

        if ($this->viewingOrderId === $orderId) {
            $this->viewingOrder = \App\Models\Order::with(['category', 'mashine.sets', 'user', 'userExec'])
                ->find($orderId);
        }
    }

    public function changeOrderCategory(int $orderId): void
    {
        $order = \App\Models\Order::find($orderId);
        if (!$order) {
            $this->dispatch('notify', ['type' => 'error', 'message' => 'Заявка не найдена']);
            return;
        }

        $this->validate([
            'editOrderCategoryId' => 'nullable|exists:categories,id',
        ]);

        $order->update(['category_id' => $this->editOrderCategoryId]);

        $this->dispatch('notify', [
            'type'    => 'info',
            'message' => 'Категория заявки изменена',
        ]);

        // Обновляем viewingOrder
        $this->viewingOrder = \App\Models\Order::with(['category', 'mashine.sets', 'user', 'userExec'])
            ->find($orderId);
    }

    public function toggleSet(int $mashineId, int $setId): void
    {
        $existing = \App\Models\MashineSet::where('mashine_id', $mashineId)
            ->where('set_id', $setId)
            ->first();

        if ($existing) {
            // Снимаем галочку — позиция не требуется
            $existing->delete();
        } else {
            // Устанавливаем галочку — позиция требуется
            \App\Models\MashineSet::create([
                'mashine_id' => $mashineId,
                'set_id'     => $setId,
            ]);
        }

        // Обновляем viewingOrder, чтобы чекбоксы перерисовались
        // Визуальное переключение (зелёный ↔ серый) — это и есть обратная связь
        if ($this->viewingOrderId) {
            $this->viewingOrder = \App\Models\Order::with(['category', 'mashine.sets', 'user', 'userExec'])
                ->find($this->viewingOrderId);
        }
    }

    public function updated($name, $value): void
    {
        if (in_array($name, ['categoryId', 'userId', 'mashineId', 'createdAt', 'contentSearch'], true)) {
            // isset() защита — чтобы не получить PHP Notice "Undefined property"
            // если getter mashines ни разу не вычислялся в текущем запросе.
            if (isset($this->mashines)) {
                unset($this->mashines);
            }
        }
    }

}
