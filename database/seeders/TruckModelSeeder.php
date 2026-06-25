<?php

namespace Database\Seeders;

use App\Models\TruckModel;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class TruckModelSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
        {
            //
            $models = [
                ['BelAZ', '75710', 'BelAZ-75710', 1850, 105, 450],
                ['KamAZ', '6580', 'KamAZ-6580', 620, 38, 40],
                ['Volvo', 'FH16', 'Volvo FH16 700', 700, 32, 38],
                ['MAN', 'TGS', 'MAN TGS 41.480', 560, 34, 41],
            ];
            foreach ($models as $model) {
            TruckModel::create([
                'brand' => $model[0],
                'model' => $model[1],
                'full_name' => $model[2],
                'fuel_capacity' => $model[3],
                'fuel_consumption' => $model[4],
                'load_capacity' => $model[5],
            ]);
        }
    }
}
