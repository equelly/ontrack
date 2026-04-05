<?php

namespace App\Http\Controllers\User\Dump;

use App\Http\Controllers\Controller;
use App\Models\Zone;
use App\Models\Dump;
use App\Services\RouteSyncService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class UpdateController extends Controller
{
    protected RouteSyncService $routeSync;

    public function __construct(RouteSyncService $routeSync)
    {
        $this->routeSync = $routeSync;
    }

    /**
     * Обновить отвал (основные данные)
     */
    public function __invoke(Request $request, Dump $dump)
    {
        $data = $request->validate([
            'name_dump' => 'sometimes|string|max:255',
            'delivered_volume' => 'sometimes|numeric|min:0',
        ]);

        $dump->update($data);

        return redirect()->route('dump.index')
            ->with('success', 'Отвал обновлён');
    }

    /**
     * Обновить зону (породы, приём, вместимость)
     */
    public function zone(Request $request, Zone $zone)
    {
        Log::info('=== UpdateController@zone START ===');
        Log::info('Request method: ' . $request->method());
        Log::info('Request body: ' . $request->getContent());
        Log::info('Zone data: ', $zone->toArray());

        try {
            // Прямое получение данных из JSON
            $rawContent = $request->getContent();
            $jsonData = json_decode($rawContent, true);
            Log::info('Parsed JSON data: ', $jsonData ?? ['error' => 'Failed to parse JSON']);

            $data = $request->validate([
                'delivery' => 'sometimes|boolean',
                'rock_ids' => 'sometimes|array',
                'rock_ids.*' => 'exists:rocks,id',
                'capacity' => 'sometimes|numeric|min:0',
            ]);

            Log::info('Validated data: ', $data);
        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::error('VALIDATION ERROR: ', $e->errors());
            return response()->json([
                'success' => false,
                'message' => 'Ошибка валидации',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('EXCEPTION: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Ошибка: ' . $e->getMessage()
            ], 500);
        }

        try {
            $result = DB::transaction(function () use ($zone, $data, $request) {
                // Запоминаем старые значения для синхронизации
                $oldDelivery = $zone->delivery;
                $oldRockIds = $zone->rocks()->pluck('rocks.id')->toArray();

                // Обновляем delivery если передан
                if ($request->has('delivery')) {
                    $deliveryValue = filter_var($request->input('delivery'), FILTER_VALIDATE_BOOLEAN);
                    Log::info("Setting delivery from {$oldDelivery} to {$deliveryValue}");
                    $zone->delivery = $deliveryValue;
                }

                // Обновляем capacity если передан
                if (isset($data['capacity'])) {
                    $zone->capacity = $data['capacity'];
                }

                $zone->save();
                Log::info('Zone saved');

                // Обновляем породы если переданы
                if (isset($data['rock_ids'])) {
                    $zone->rocks()->sync($data['rock_ids']);
                    Log::info('Rocks synced: ' . count($data['rock_ids']));
                }

                return $zone;
            });

            // СИНХРОНИЗАЦИЯ МАРШРУТОВ (после транзакции)
            // Обновляем модель зоны, чтобы видеть изменения пород
            $zone->refresh();
            $syncResult = $this->syncRoutes($zone, $data, $request);

            Log::info('=== UpdateController@zone SUCCESS ===');

            return response()->json([
                'success' => true,
                'message' => 'Зона обновлена',
                'zone' => $result->fresh(['rocks']),
                'sync' => $syncResult,
            ]);
        } catch (\Exception $e) {
            Log::error('DB TRANSACTION ERROR: ' . $e->getMessage());
            Log::error('Stack trace: ' . $e->getTraceAsString());
            return response()->json([
                'success' => false,
                'message' => 'Ошибка базы данных: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Синхронизация маршрутов при изменении зоны
     */
    protected function syncRoutes(Zone $zone, array $data, Request $request): array
    {
        $syncResult = [
            'synced' => false,
            'created' => 0,
            'deleted' => 0,
        ];

        // Если изменился delivery
        if ($request->has('delivery')) {
            $newDelivery = filter_var($request->input('delivery'), FILTER_VALIDATE_BOOLEAN);

            if (!$newDelivery) {
                // Зона закрылась - удаляем маршруты
                $result = $this->routeSync->syncOnZoneClose($zone);
                $syncResult['deleted'] = $result['deleted'];
                $syncResult['synced'] = true;
                Log::info("Zone closed, deleted {$result['deleted']} routes");
            }
            // Если зона открылась - создаём маршруты (породы уже обновлены)
            elseif ($newDelivery) {
                $result = $this->routeSync->syncOnZoneOpen($zone);
                $syncResult['created'] = $result['created'];
                $syncResult['synced'] = true;
                Log::info("Zone opened, created {$result['created']} routes");
            }
        }
        // Если изменились породы (и зона открыта)
        elseif (isset($data['rock_ids']) && $zone->delivery) {
            $result = $this->routeSync->fullSyncForZone($zone, $data['rock_ids']);
            $syncResult['created'] = $result['created'];
            $syncResult['deleted'] = $result['deleted'];
            $syncResult['synced'] = true;
            Log::info("Rocks updated, created {$result['created']}, deleted {$result['deleted']} routes");
        }

        return $syncResult;
    }
}
