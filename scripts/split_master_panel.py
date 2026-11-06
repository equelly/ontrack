#!/usr/bin/env python3
"""
Разделение MasterPanel.php на traits по вкладкам.

Структура:
  app/Livewire/Traits/Master/
    WithAlerts.php       — AI-алерты
    WithDashboard.php    — Обзор (getters)
    WithEquipment.php    — Оборудование (trucks + miners)
    WithMaintenance.php  — Обслуживание
    WithRequests.php     — Заявки + Order методы
    WithRocks.php        — Породы
    WithDumps.php        — Перегрузки + berm
    WithEvents.php       — Вебсокеты
    WithSettings.php     — Базовые (mount/loadMasterData/setTab/cache/cars)

MasterPanel.php остаётся:
  - use ... traits
  - $shift, $activeTab, $validationAttributes
  - render()
"""
import re
from pathlib import Path

SRC = Path("app/Livewire/MasterPanel.php")
TRAITS_DIR = Path("app/Livewire/Traits/Master")
TRAITS_DIR.mkdir(parents=True, exist_ok=True)

text = SRC.read_text(encoding="utf-8")

# 1. Извлечь use statements (для копирования в traits)
use_block = re.search(r'^(use [^;]+;\s*\n)+', text, re.MULTILINE)
use_statements = ""
if use_block:
    use_statements = use_block.group(0).strip() + "\n"
print(f"Найдено use statements:\n{use_statements}")

# 2. Mapping методов → trait
trait_methods = {
    'WithAlerts': [
        'getNewAlertsCountProperty',
        'getRecentAlertsProperty',
        'getAlertFilterOptionsProperty',
        'getAlertFiltersActiveProperty',
        'toggleAlertFilter',
        'isAlertFilterActive',
        'resetAlertFilters',
        'acknowledgeAlert',
        'acknowledgeAllAlerts',
    ],
    'WithDashboard': [
        'getTripMetricsProperty',
        'getZoneVolumesProperty',
        'getHaulsSummaryProperty',
        'getActiveHaulsProperty',
        'getActiveRoutesProperty',
    ],
    'WithEquipment': [
        'getTrucksProperty',
        'getMinersProperty',
        'getTrucksSummaryProperty',
        'getIssueSummaryProperty',
        'addMiner',
        'deleteMiner',
        'addTruck',
        'deleteTruck',
        'editMinerDistances',
        'saveDistance',
    ],
    'WithMaintenance': [
        'getActiveServiceTasksProperty',
        'getPendingServiceTasksProperty',
    ],
    'WithRequests': [
        'getMashinesProperty',
        'getCategoriesProperty',
        'getUsersProperty',
        'getAllMashinesProperty',
        'getOrdersCountProperty',
        'openCreateOrderModal',
        'closeCreateOrderModal',
        'editOrder',
        'saveOrder',
        'updateOrder',
        'createOrder',
        'viewOrder',
        'closeOrderDetailsModal',
        'deleteOrder',
        'completeOrder',
        'changeOrderCategory',
        'toggleSet',
        'updated',  # hook для фильтров
    ],
    'WithRocks': [
        'addRock',
        'deleteRock',
    ],
    'WithDumps': [
        'getDumpsProperty',
        'getActiveBermRequestsProperty',
        'addDump',
        'deleteDump',
        'toggleServicePoint',
        'toggleAddZone',
        'addZone',
        'updateZoneField',
        'deleteZone',
        'openBermModal',
        'closeBermModal',
        'createBermRequest',
        'cancelBermRequest',
    ],
    'WithEvents': [
        'onZoneNeedsBerm',
        'onBermProgress',
        'onZoneFillWarning',
    ],
    'WithSettings': [
        'getRocksProperty',
        'mount',
        'refreshMasterData',
        'loadMasterData',
        'invalidateCache',
        'invalidateLivewireCache',
        'autoOptimizeRoutes',
        'setTab',
    ],
}

# Properties → trait
trait_properties = {
    'WithAlerts': {
        'alertFilters': "public array $alertFilters = [\n        'type'     => [],\n        'severity' => [],\n        'entity'   => [],\n        'status'   => [],\n    ];",
    },
    'WithEquipment': {
        'newMinerName': 'public $newMinerName;',
        'newTruckNumber': 'public $newTruckNumber;',
        'newTruckModelId': 'public $newTruckModelId;',
        'newTruckFuel': 'public $newTruckFuel;',
        'editingMinerId': 'public $editingMinerId = null;',
    },
    'WithRocks': {
        'newRockName': 'public $newRockName;',
    },
    'WithDumps': {
        'newDumpName': 'public $newDumpName;',
        'addZoneDumpId': 'public $addZoneDumpId = null;',
        'newZoneName': "public $newZoneName = 'Зона 1';",
        'newZoneRockId': 'public $newZoneRockId;',
        'newZoneCapacity': 'public $newZoneCapacity = 10000;',
        'newZoneVolume': 'public $newZoneVolume = 0;',
        'bermZoneId': 'public ?int $bermZoneId = null;',
        'bermZoneName': 'public ?string $bermZoneName = null;',
        'bermTrucksNeeded': 'public int $bermTrucksNeeded = 1;',
        'bermRockId': 'public ?int $bermRockId = null;',
        'showBermModal': 'public bool $showBermModal = false;',
    },
    'WithRequests': {
        'categoryId': "public $categoryId = '';",
        'userId': "public $userId = '';",
        'mashineId': "public $mashineId = '';",
        'createdAt': "public $createdAt = '';",
        'contentSearch': "public $contentSearch = '';",
        'showCreateOrderModal': 'public bool $showCreateOrderModal = false;',
        'showOrderDetailsModal': 'public bool $showOrderDetailsModal = false;',
        'viewingOrderId': 'public ?int $viewingOrderId = null;',
        'newOrderMashineId': 'public ?int $newOrderMashineId = null;',
        'newOrderSets': 'public array $newOrderSets = [];',
        'newOrderContent': "public string $newOrderContent = '';",
        'newOrderImage': 'public $newOrderImage = null;',
        'viewingOrder': 'public $viewingOrder = null;',
        'editOrderCategoryId': 'public ?int $editOrderCategoryId = null;',
        'editingOrderId': 'public ?int $editingOrderId = null;',
    },
}

# 3. Найти все методы в файле (function signature → matching closing })
# Регекс: <modifiers> function <name>(<params>) <return>: { ... }
# Глубина {} отслеживается

def extract_methods(text):
    """Извлечь все методы с их телом."""
    methods = {}
    # Найти все начала методов
    pattern = re.compile(
        r'(?P<indent>[ \t]*)'
        r'(?P<modifiers>(?:public|protected|private)(?:\s+static)?)\s+'
        r'function\s+(?P<name>\w+)\s*'
        r'\([^)]*\)'
        r'(?::\s*\w+|\?\\?\w+|\|[\w\\?]+)*'
        r'\s*\{',
        re.MULTILINE
    )
    for m in pattern.finditer(text):
        name = m.group('name')
        start = m.start()
        # Найти закрывающую } с балансом
        depth = 0
        i = m.end() - 1  # позиция {
        while i < len(text):
            if text[i] == '{':
                depth += 1
            elif text[i] == '}':
                depth -= 1
                if depth == 0:
                    methods[name] = text[start:i+1]
                    break
            i += 1
    return methods

methods = extract_methods(text)
print(f"\nНайдено методов: {len(methods)}")
for name, body in methods.items():
    print(f"  {name}(): {len(body.splitlines())} строк")

# 4. Найти render() отдельно (остаётся в MasterPanel)
render_method = methods.get('render')
if not render_method:
    raise Exception("render() не найден!")

# 5. Создать trait файлы
all_assigned = set()
for trait_name, method_list in trait_methods.items():
    trait_path = TRAITS_DIR / f"{trait_name}.php"
    trait_content = f"<?php\n\nnamespace App\\Livewire\\Traits\\Master;\n\n"
    # Копируем ВСЕ use statements (не критично, что есть неиспользуемые)
    trait_content += use_statements + "\n"
    trait_content += f"trait {trait_name}\n{{\n"

    # Properties
    if trait_name in trait_properties:
        for prop_name, prop_def in trait_properties[trait_name].items():
            trait_content += f"    {prop_def}\n"
        trait_content += "\n"

    # Methods
    for method_name in method_list:
        if method_name in methods:
            body = methods[method_name]
            # Уменьшить отступ на 1 уровень (4 пробела)? Нет — оставляем как есть
            trait_content += body + "\n\n"
            all_assigned.add(method_name)
        else:
            print(f"  WARN: метод {method_name} не найден в файле!")

    trait_content += "}\n"
    trait_path.write_text(trait_content, encoding="utf-8")
    print(f"OK: создан {trait_path.name} ({len(method_list)} методов)")

# 6. Найти методы которые НЕ назначены (могут быть пропущены)
unassigned = set(methods.keys()) - all_assigned - {'render'}
if unassigned:
    print(f"\n⚠️  НЕ НАЗНАЧЕНЫ в traits:")
    for name in unassigned:
        print(f"  - {name}")

print(f"\n✅ Создано {len(trait_methods)} trait файлов в {TRAITS_DIR}")
