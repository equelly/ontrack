<div
    id="driver-panel"
    data-truck-id="{{ $truck?->id }}"
    class="min-h-screen bg-gradient-to-br from-blue-50 to-indigo-100 p-4"
>

    {{-- Real-time уведомления --}}
<div id="realtime-driver-notification"
     class="fixed top-5 right-5 bg-blue-500 text-white p-2 rounded">
</div>


    @if($truck)
    <div class="bg-white rounded-2xl shadow-xl p-6 mb-6">
        {{-- Нет активного маршрута --}}
        @if(!$currentOrder)
        <div class="mb-6 p-4 bg-gradient-to-r from-gray-50 to-gray-100 rounded-xl text-center">
            <i class="fas fa-exclamation-triangle text-yellow-500 text-3xl mb-3"></i>
            <h3 class="font-bold text-xl mb-2">Нет активного маршрута</h3>
            <p class="text-gray-600">Ожидание задания от диспетчера</p>
        </div>
        @endif

        {{-- Текущий маршрут (если есть) --}}
        @if($currentOrder)
        <div class="mb-6 p-4 bg-gradient-to-r from-blue-50 to-indigo-50 rounded-xl">
            <h3 class="font-bold text-lg mb-3 flex items-center">
                <i class="fas fa-route text-blue-500 mr-2"></i>
                Активный маршрут
            </h3>
            <div class="grid grid-cols-3 gap-4 text-sm">
                <div>
                    <span class="text-gray-500 block">Автомобиль:</span>
                    <span class="font-semibold">{{ $truck->brand }}<br>{{ $truck->number }}</span>
                </div>
                <div>
                    <span class="text-gray-500 block">Забой:</span>
                    <span class="font-semibold">{{ $currentOrder->miner->name_miner ?? 'Забой' }}</span>
                </div>
                <div>
                    <span class="text-gray-500 block">Место разгрузки:</span>
                    <span class="font-semibold">п.п.№{{ $currentOrder->dump->name_dump ?? 'Не определено' }}</span>
                </div>
                
            </div>
            @if($truck->driver)
                <p class="text-green-600 font-semibold text-lg mt-2">{{ $truck->driver->name ?? 'Водитель' }}</p>
                    @else
                <p class="text-yellow-600 font-semibold text-lg mt-2">Водитель не назначен</p>
                @endif
            </div>
            @endif

        {{-- Статус грузовика --}}
        <div class="bg-gradient-to-r from-yellow-50 to-orange-100 rounded-2xl p-6 mb-6">
            Статус
            <div class="text-center">
                <p class="text-lg font-semibold text-gray-600">{{ $nextAction }}</p>
                <div class="mt-2 text-sm text-gray-500">
                    Перевозимый объем: {{ $truck->current_load }} / {{ $truck->load_capacity }}т
                </div>
            </div>
        </div>

        {{-- Кнопка действия --}}
        <button 
            wire:click="driverAction"
            class="w-full bg-gradient-to-r from-blue-500 to-blue-600 hover:from-blue-600 hover:to-blue-700 text-white font-bold py-4 px-6 rounded-2xl text-lg shadow-xl transform hover:scale-105 transition-all duration-200 flex items-center justify-center"
            {{-- Активна всегда, кроме completed --}}
            {{ $truck->status === 'completed' ? 'opacity-50 cursor-not-allowed' : '' }} >
               @if($truck->status === 'completed' && !$currentOrder)
                <i class="fas fa-redo mr-2"></i>НОВЫЙ РЕЙС
            @elseif(in_array($truck->status ?? '', ['maintenance','fueling','breakdown']))
                <i class="fas fa-tools mr-2"></i>В работу
            @else
                <i class="fas fa-play mr-2"></i>Далее
            @endif
        </button>


    </div>
    @else
    <div class="text-center py-12">
        <i class="fas fa-truck text-6xl text-gray-300 mb-4"></i>
        <h2 class="text-2xl font-bold text-gray-500 mb-2">Автомобиль не найден</h2>
        <p class="text-gray-400">ID: {{ $truckId }}</p>
    </div>
    @endif
{{-- экстренная кнопка --}}
@if(in_array($truck->status ?? '', ['free', 'to_miner', 'loading', 'transporting', 'unloading', 'completed']))
<div class="mt-4 p-3 bg-red-50 border border-red-200 rounded-xl">
    <button 
        wire:click="reportBreakdown"
        class="w-full bg-red-500 hover:bg-red-600 text-white font-bold py-3 px-6 rounded-xl text-sm shadow-xl flex items-center justify-center">
        <i class="fas fa-exclamation-triangle mr-2"></i>
        Неисправность
    </button>
</div>
@endif


</div>
