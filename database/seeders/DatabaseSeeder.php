<?php

namespace Database\Seeders;

use App\Models\Rock;
use App\Models\Miner;
use App\Models\Dump;
use App\Models\Zone;
use App\Models\Truck;
use App\Models\TruckModel;
use App\Models\User;
use App\Models\MiningOrder;
use App\Models\ServicePost;
use App\Models\SystemSetting;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Породы
        $rocks = collect([
            Rock::create(['name_rock' => 'руда']),
            Rock::create(['name_rock' => 'вскрыша']),
            Rock::create(['name_rock' => 'песчаник']),
            Rock::create(['name_rock' => 'руда_S']),
            Rock::create(['name_rock' => 'руда_ЦПТ']),
        ])->keyBy('name_rock');

        // Забои
        $minerNames = ['ЭКГ-13', 'ЭКГ-15', 'ЭКГ-17', 'ЭКГ-18', 'ЭКГ-19', 'ЭКГ-20', 'ЭКГ-25', 'ЭКГ-31', 'П-449', 'П-450'];
        $miners = collect();
        foreach ($minerNames as $name) {
            $miners->push(Miner::create([
                'name_miner' => $name,
                'capacity_per_trip' => 50.00,
                'active' => true
            ]));
        }

        // Назначаем породы забоям (разделяем экскаваторы по типам работ)
        foreach ($miners as $index => $miner) {
            if ($index < 4) {
                // Первые 4 экскаватора копают только руду
                $miner->rocks()->attach([
                    $rocks['руда']->id,
                    $rocks['руда_S']->id,
                    $rocks['руда_ЦПТ']->id,
                ]);
            } elseif ($index < 8) {
                // Следующие 4 экскаватора — на вскрыше
                $miner->rocks()->attach([$rocks['вскрыша']->id]);
            } else {
                // Остальные копают песчаник
                $miner->rocks()->attach([$rocks['песчаник']->id]);
            }
        }

        // Перегрузки
        $dumps = collect([
            Dump::create(['name_dump' => 'Перегрузка №6', 'delivered_volume' => 0, 'trips_count' => 0]),
            Dump::create(['name_dump' => 'Перегрузка №10', 'delivered_volume' => 0, 'trips_count' => 0]),
        ]);

        // Зоны с четким технологическим разделением пород
        $zones = collect();
        foreach ($dumps as $dump) {
            // Зона 1: Принимает руду, руду_S и руду_ЦПТ
            $zone1 = Zone::create([
                'dump_id' => $dump->id,
                'name_zone' => "Зона 1",
                'volume' => 0, 'capacity' => 10000, 'delivery' => true, 'ship' => false
            ]);
            $zone1->rocks()->attach([
                $rocks['руда']->id,
                $rocks['руда_S']->id,
                $rocks['руда_ЦПТ']->id,
            ]);
            $zones->push($zone1);

            // Зона 2: Принимает только вскрышу
            $zone2 = Zone::create([
                'dump_id' => $dump->id,
                'name_zone' => "Зона 2",
                'volume' => 0, 'capacity' => 10000, 'delivery' => true, 'ship' => false
            ]);
            $zone2->rocks()->attach([$rocks['вскрыша']->id]);
            $zones->push($zone2);

            // Зона 3: Принимает только песчаник
            $zone3 = Zone::create([
                'dump_id' => $dump->id,
                'name_zone' => "Зона 3",
                'volume' => 0, 'capacity' => 10000, 'delivery' => true, 'ship' => false
            ]);
            $zone3->rocks()->attach([$rocks['песчаник']->id]);
            $zones->push($zone3);
        }


        // Модели самосвалов
        $truckModels = collect([
            TruckModel::create([
                'brand' => 'БелАЗ',
                'model' => '75131',
                'full_name' => 'БелАЗ-75131',
                'fuel_capacity' => 600,
                'fuel_consumption' => 40,
                'load_capacity' => 90
            ]),
            TruckModel::create([
                'brand' => 'БелАЗ',
                'model' => '75303',
                'full_name' => 'БелАЗ-75303',
                'fuel_capacity' => 800,
                'fuel_consumption' => 55,
                'load_capacity' => 136
            ]),
        ]);

        // Самосвалы
        $truckNumbers = ['А001АА', 'А002АВ', 'А003АС', 'А004АЕ', 'А005АН', 'В001ВВ', 'В002ВС', 'В003ВЕ'];
        $trucks = collect();
        foreach ($truckNumbers as $i => $number) {
            $trucks->push(Truck::create([
                'number' => $number,
                'load_capacity' => $i % 2 === 0 ? 90 : 136,
                'truck_model_id' => $truckModels[$i % 2]->id,
                'status' => 'free',
                'fuel_level' => 100,
                // Пробег и мото-часы (начальные значения для тестирования)
                'mileage' => rand(1000, 5000),
                'mileage_since_fuel' => rand(100, 800),
                'moto_minutes' => rand(6000, 30000), // 100-500 мото-часов
                'moto_minutes_since_to' => rand(6000, 15000), // 100-250 мото-часов с последнего ТО
                'last_to_type' => $i % 2 === 0 ? 'TO-1' : 'TO-2',
            ]));
        }

        // Маршруты (mining_orders) — Логичное распределение
        foreach ($miners as $miner) {
            // 1. Выбираем ОДНУ случайную породу, которую СЕЙЧАС копает этот экскаватор
            $currentRock = $miner->rocks->random();

            // 2. Выбираем ОДНУ случайную перегрузку для этого экскаватора
            $dump = $dumps->random();

            // 3. Находим случайную зону СТРОГО на выбранной перегрузке
            $zone = Zone::where('dump_id', $dump->id)->inRandomOrder()->first();

            if ($zone) {
                MiningOrder::create([
                    'miner_id'    => $miner->id,
                    'dump_id'     => $dump->id,
                    'zone_id'     => $zone->id,        // Четкая привязка к выбранной перегрузке
                    'rock_id'     => $currentRock->id, // Экскаватор везет именно ту породу, которую добывает
                    'distance_km' => rand(10, 60) / 10,
                    'active'      => true
                ]);
            }
        }


        // Пользователи
        User::create([
            'name' => 'Администратор',
            'email' => 'admin@mine.ru',
            'password' => Hash::make('12345asdf'),
            'role' => 'admin',
            'position' => 'admin'
        ]);

        User::create([
            'name' => 'Диспетчер',
            'email' => 'dispatcher@mine.ru',
            'password' => Hash::make('12345asdf'),
            'role' => 'эксплуатационный',
            'position' => 'dispatcher'
        ]);

        // Водители
        for ($i = 0; $i < 4; $i++) {
            $user = User::create([
                'name' => "Водитель {$trucks[$i]->number}",
                'email' => "driver" . ($i + 1) . "@mine.ru",
                'password' => Hash::make('12345asdf'),
                'role' => 'эксплуатационный',
                'position' => 'driver'
            ]);
            $trucks[$i]->update(['driver_id' => $user->id]);
        }

        // Машинисты
        for ($i = 0; $i < 3; $i++) {
            User::create([
                'name' => "Машинист {$miners[$i]->name_miner}",
                'email' => "operator" . ($i + 1) . "@mine.ru",
                'password' => Hash::make('12345asdf'),
                'role' => 'эксплуатационный',
                'position' => 'excavator_operator'
            ]);
        }

        // Сервисные посты
        ServicePost::createDefaultPosts();

        // Настройки системы (пороги, количество постов)
        SystemSetting::setServicePostsSettings([
            'fueling_posts_count' => 2,
            'maintenance_posts_count' => 2,
            'tire_service_posts_count' => 3,
            'to1_interval_hours' => 250,
            'to2_interval_hours' => 500,
            'empty_run_coefficient' => 0.5,
        ]);
    }
}
