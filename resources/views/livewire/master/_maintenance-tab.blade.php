        <div wire:key="tab-maintenance" class="mt-4 space-y-6">

            <!-- Что проводится сейчас -->
            <div class="bg-white rounded-xl border shadow-sm overflow-hidden">
                <div class="p-4 border-b font-bold text-gray-800 uppercase text-sm flex items-center gap-2">
                    <span class="w-3 h-3 bg-emerald-500 rounded-full animate-pulse"></span>
                    Что проводится сейчас (Занятые посты)
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-slate-50 border-b">
                            <tr>
                                <th class="text-left p-3 font-semibold text-gray-600">Грузовик</th>
                                <th class="text-left p-3 font-semibold text-gray-600">Тип обслуживания</th>
                                <th class="text-left p-3 font-semibold text-gray-600">Пост</th>
                                <th class="text-left p-3 font-semibold text-gray-600">Начало</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($activeServiceTasks as $task)
                                <tr wire:key="task-{{ $loop->iteration }}" class="border-b hover:bg-slate-50">
                                    <td class="p-3 font-bold text-gray-800">{{ $task->truck?->number ?? '—' }}</td>
                                    <td class="p-3 text-gray-600">{{ $task->getTypeLabel() }}</td>
                                    <td class="p-3">
                                        <span class="px-2 py-0.5 text-xs rounded bg-amber-100 text-amber-700">{{ $task->servicePost?->name ?? '—' }}</span>
                                    </td>
                                    <td class="p-3 text-gray-500">{{ $task->started_at?->format('H:i') }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="p-4 text-center text-gray-500">Нет активных обслуживаний</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Запланировано (Очередь) -->
            <div class="bg-white rounded-xl border shadow-sm overflow-hidden">
                <div class="p-4 border-b font-bold text-gray-800 uppercase text-sm">Запланировано (Очередь)</div>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-slate-50 border-b">
                            <tr>
                                <th class="text-left p-3 font-semibold text-gray-600">Грузовик</th>
                                <th class="text-left p-3 font-semibold text-gray-600">Тип обслуживания</th>
                                <th class="text-left p-3 font-semibold text-gray-600">Позиция в очереди</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($pendingServiceTasks as $task)
                                <tr wire:key="task-{{ $loop->iteration }}" class="border-b hover:bg-slate-50">
                                    <td class="p-3 font-bold text-gray-800">{{ $task->truck?->number ?? '—' }}</td>
                                    <td class="p-3 text-gray-600">{{ $task->getTypeLabel() }}</td>
                                    <td class="p-3">
                                        <span class="px-2 py-0.5 text-xs rounded bg-slate-200 text-slate-700">{{ $task->queue_position }}</span>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="3" class="p-4 text-center text-gray-500">Очередь пуста</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
