        <div wire:key="tab-requests" class="mt-4 space-y-6">
            <!-- Панель фильтрации -->
            <div class="bg-white rounded-xl border shadow-sm p-4 mb-6">
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4">
                    <div>
                        <label class="block text-xs uppercase font-semibold text-gray-500 mb-1">Категория</label>
                        <select wire:model.live="categoryId" class="w-full border-gray-300 rounded-md shadow-sm py-2 text-sm">
                            <option value="">Все</option>
                            @foreach($categories as $category)
                                <option wire:key="category-{{ $loop->iteration }}" value="{{ $category->id }}">{{ $category->title }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs uppercase font-semibold text-gray-500 mb-1">Автор</label>
                        <select wire:model.live="userId" class="w-full border-gray-300 rounded-md shadow-sm py-2 text-sm">
                            <option value="">Все</option>
                            @foreach($users as $user)
                                <option wire:key="user-{{ $loop->iteration }}" value="{{ $user->id }}">{{ $user->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs uppercase font-semibold text-gray-500 mb-1">Оборудование</label>
                        <select wire:model.live="mashineId" class="w-full border-gray-300 rounded-md shadow-sm py-2 text-sm">
                            <option value="">Все</option>
                            @foreach($allMashines as $mashine)
                                <option wire:key="mashine-{{ $loop->iteration }}" value="{{ $mashine->id }}">ЭКГ №{{ $mashine->number }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs uppercase font-semibold text-gray-500 mb-1">Дата</label>
                        <input type="date" wire:model.live="createdAt" class="w-full border-gray-300 rounded-md shadow-sm py-2 text-sm">
                    </div>
                    <div>
                        <label class="block text-xs uppercase font-semibold text-gray-500 mb-1">Фрагмент заявки</label>
                        <input type="text" wire:model.live.debounce.300ms="contentSearch" placeholder="Поиск по тексту..." class="w-full border-gray-300 rounded-md shadow-sm py-2 text-sm">
                    </div>
                </div>
                <div class="flex justify-between items-center mt-4 pt-4 border-t">
                    <h3 class="font-bold text-gray-800 uppercase text-sm">Всего заявок: <span class="text-emerald-600">{{ $ordersCount }}</span></h3>
                    <button wire:click="openCreateOrderModal" class="px-4 py-2 bg-emerald-600 text-white rounded-md font-semibold uppercase text-xs hover:bg-emerald-700">
                        <i class="fas fa-plus mr-1"></i> Создать заявку
                    </button>
                </div>
            </div>

            <!-- Карточки техники с заявками -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                @foreach($mashines as $mashine)
                    @if(count($mashine->sets) != 0 || count($mashine->orders) != 0)
                        <div class="bg-white rounded-xl border shadow-sm overflow-hidden flex flex-col sm:flex-row">

                            <!-- Левая часть: Заявки -->
                            <div class="flex-1 p-4 border-b sm:border-b-0 sm:border-r">
                                <h5 class="font-bold text-gray-800 mb-3">ЭКГ №{{ $mashine->number }}</h5>
                                <div class="space-y-3">
                                    @foreach($mashine->orders as $order)
                                        <div wire:key="order-{{ $loop->iteration }}" class="bg-slate-50 p-3 rounded-md border border-slate-200">
                                            <small class="text-gray-500 block mb-1">
                                                {{ $order->created_at->translatedFormat('d F Y') }} ({{ $order->created_at->diffForHumans() }})
                                            </small>
                                            <p class="text-sm text-gray-700">{{ $order->content }}</p>
                                            <button wire:click="viewOrder({{ $order->id }})" class="text-xs text-emerald-600 hover:text-emerald-800 mt-2 inline-block font-semibold">
                                                Смотреть подробнее <i class="fas fa-arrow-right ml-1"></i>
                                            </button>
                                        </div>
                                    @endforeach
                                    @if(count($mashine->orders) == 0)
                                        <p class="text-gray-400 text-sm">Нет активных заявок</p>
                                    @endif
                                </div>
                            </div>

                            <!-- Правая часть: Комплектация -->
                            @if(count($mashine->sets) != 0)
                                <div class="sm:w-1/3 p-4 bg-slate-50">
                                    <h6 class="font-bold text-gray-600 uppercase text-xs mb-2">Необходимо укомплектовать:</h6>
                                    <ul class="space-y-1">
                                        @foreach($mashine->sets as $set)
                                            <li wire:key="set-{{ $loop->iteration }}" class="text-sm text-gray-700 flex items-center gap-2">
                                                <i class="fas fa-circle text-[6px] text-emerald-500"></i> {{ $set->name }}
                                            </li>
                                        @endforeach
                                    </ul>
                                </div>
                            @endif

                        </div>
                    @endif
                @endforeach
            </div>
        </div>
