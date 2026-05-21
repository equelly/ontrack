<?php

namespace App\Http\Controllers;

use App\Models\SystemSetting;
use Illuminate\Http\Request;

class SettingsController extends Controller
{
    /**
     * Получить все настройки
     */
    public function index()
    {
        return response()->json([
            'miner_threshold' => SystemSetting::getMinerOverloadThreshold(),
            'zone_threshold' => SystemSetting::getZoneOverloadThreshold(),
            'route_mode' => SystemSetting::getRouteActivationMode(),
        ]);
    }

    /**
     * Получить пороги перегруженности
     */
    public function getThresholds()
    {
        return response()->json(SystemSetting::getOverloadThresholds());
    }

    /**
     * Установить пороги перегруженности
     */
    public function setThresholds(Request $request)
    {
        $request->validate([
            'miner_threshold' => 'nullable|integer|min:1|max:20',
            'zone_threshold' => 'nullable|integer|min:1|max:20',
        ]);

        if ($request->has('miner_threshold')) {
            SystemSetting::setMinerOverloadThreshold($request->miner_threshold);
        }

        if ($request->has('zone_threshold')) {
            SystemSetting::setZoneOverloadThreshold($request->zone_threshold);
        }

        return response()->json([
            'success' => true,
            'thresholds' => SystemSetting::getOverloadThresholds(),
        ]);
    }
}

