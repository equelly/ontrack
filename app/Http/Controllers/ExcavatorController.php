<?php

namespace App\Http\Controllers;

use App\Models\Miner;
use App\Models\Rock;
use App\Models\Truck;
use App\Models\TruckTrip;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ExcavatorController extends Controller
{
    /**
     * Главная панель машиниста
     */
    public function index(Request $request)
    {
        // Получаем последний выбранный экскаватор из cookies
        $minerId = $request->cookie('selected_miner');
        $miner = $minerId ? Miner::with('rocks')->find($minerId) : null;

        // Все активные экскаваторы для выбора
        $miners = Miner::where('active', true)->orderBy('name_miner')->get();

        // Все породы
        $rocks = Rock::orderBy('name_rock')->get();

        // Данные для отображения
        $trucks = collect();
        $stats = null;

        if ($miner) {
            // Самосвалы, едущие к этому забою
             $trucks = Truck::with(['trips' => function ($q) use ($miner) {
                $q->where('miner_id', $miner->id)
                ->whereNull('completed_at')
                ->with(['miningOrder.dump', 'miningOrder.zone']);
            }])
            ->whereIn('status', ['to_miner', 'loading', 'waiting_loading'])
            ->whereHas('trips', function ($q) use ($miner) {
                $q->where('miner_id', $miner->id)->whereNull('completed_at');
            })
            ->get();

            // Статистика за смену
            $stats = $this->getShiftStats($miner);
        }

        return view('excavator.show', compact('miner', 'miners', 'rocks', 'trucks', 'stats'));
    }

    /**
     * Установить выбранный экскаватор
     */
    public function setMiner(Request $request)
    {
        $request->validate([
            'miner_id' => 'required|exists:miners,id'
        ]);

        $miner = Miner::find($request->miner_id);

        if (!$miner || !$miner->active) {
            return response()->json(['success' => false, 'message' => 'Экскаватор не найден или неактивен']);
        }

        // Сохраняем в cookies на 30 дней
        return response()->json([
            'success' => true,
            'miner' => $miner->load('rocks')
        ])->cookie('selected_miner', $miner->id, 60 * 24 * 30);
    }

    /**
     * Установить породу в забое
     */
    public function setRock(Request $request)
    {
        $request->validate([
            'miner_id' => 'required|exists:miners,id',
            'rock_id' => 'required|exists:rocks,id'
        ]);

        $miner = Miner::find($request->miner_id);

        // Устанавливаем породу (sync - заменяет текущую)
        $miner->rocks()->sync([$request->rock_id]);
        $miner->update([
            'last_updated_at' => now(),
            'last_updated_by' => Auth::id()
        ]);

        return response()->json([
            'success' => true,
            'miner' => $miner->fresh()->load('rocks')
        ]);
    }

    /**
     * Получить статистику за смену
     */
    protected function getShiftStats(Miner $miner): array
    {
        $shiftStart = $this->getShiftStart();

        // Рейсы за смену
        $trips = TruckTrip::where('miner_id', $miner->id)
            ->whereNotNull('completed_at')
            ->where('completed_at', '>=', $shiftStart)
            ->with('truck')
            ->get();

        // Количество рейсов
        $tripsCount = $trips->count();

        // Объём добытой породы
        $totalVolume = $trips->sum('load_volume');

        // Среднее время погрузки (от started_at до completed_at, минус время в пути)
        // Для точности можно хранить время начала погрузки отдельно
        $avgLoadingTime = $trips->whereNotNull('started_at')->whereNotNull('completed_at')->avg(function ($trip) {
            return $trip->started_at->diffInSeconds($trip->completed_at) / 60; // в минутах
        });

        return [
            'trips_count' => $tripsCount,
            'total_volume' => round($totalVolume, 1),
            'avg_loading_time' => round($avgLoadingTime ?? 0, 1),
            'shift_start' => $shiftStart->format('H:i'),
            'shift_name' => $this->getShiftName()
        ];
    }

    /**
     * Определить начало текущей смены
     */
    protected function getShiftStart(): \Carbon\Carbon
    {
        $now = now();
        $hour = $now->hour;
        $minute = $now->minute;

        // 1 смена: 7:30 - 19:30
        // 2 смена: 19:30 - 7:30

        if ($hour >= 7 && $hour < 19) {
            // Утро/день - проверяем, началась ли смена
            if ($hour === 7 && $minute < 30) {
                // Ещё 2-я смена предыдущего дня
                return $now->copy()->subDay()->setTime(19, 30);
            }
            // 1-я смена сегодня
            return $now->copy()->setTime(7, 30);
        } elseif ($hour >= 19) {
            // Вечер - началась 2-я смена
            if ($hour === 19 && $minute < 30) {
                // Ещё 1-я смена
                return $now->copy()->setTime(7, 30);
            }
            // 2-я смена сегодня
            return $now->copy()->setTime(19, 30);
        } else {
            // Ночь (0:00 - 6:59) - это 2-я смена предыдущего дня
            return $now->copy()->subDay()->setTime(19, 30);
        }
    }

    /**
     * Название текущей смены
     */
    protected function getShiftName(): string
    {
        $hour = now()->hour;
        $minute = now()->minute;

        if (($hour >= 7 && $hour < 19) || ($hour === 19 && $minute < 30)) {
            return '1-я смена';
        } else {
            return '2-я смена';
        }
    }
}