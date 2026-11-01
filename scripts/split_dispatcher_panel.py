#!/usr/bin/env python3
"""
Разделяет MainDispatcherPanel.php на traits по доменам.

Безопасность:
  - Создаёт бэкап MainDispatcherPanel.php.bak перед изменениями
  - Каждый trait получает все use-импорты (лишние не вызывают ошибок в PHP)
  - Свойства остаются в главном классе (traits обращаются через $this->)
"""

import re
import os
import shutil
from collections import defaultdict

# === НАСТРОЙКИ ===
SRC = 'app/Livewire/MainDispatcherPanel.php'
TRAITS_DIR = 'app/Livewire/Traits/'
BACKUP = SRC + '.bak'

# === ПАРСЕР PHP ===

def find_methods(content):
    """Находит все методы в PHP файле. Возвращает список словарей:
    {name, start_pos, end_pos, full_text, docblock, attributes, signature, body}
    """
    methods = []
    lines = content.split('\n')
    
    i = 0
    while i < len(lines):
        line = lines[i]
        
        # Ищем объявление метода
        match = re.match(r'^(\s*)((?:public|protected|private)\s+(?:static\s+)?function\s+(\w+))', line)
        if not match:
            i += 1
            continue
        
        indent = match.group(1)
        method_name = match.group(3)
        
        # Ищем назад: PHPDoc и атрибуты
        doc_start = i
        while doc_start > 0:
            prev = lines[doc_start - 1].strip()
            if prev.endswith('*/') or prev.startswith('/**') or prev.startswith('#[') or prev == '':
                doc_start -= 1
            elif prev.startswith('//') or prev.startswith('*'):
                doc_start -= 1
            else:
                break
        
        # Не включаем пустые строки в начало
        while doc_start < i and lines[doc_start].strip() == '':
            doc_start += 1
        
        # Ищем тело метода (баланс фигурных скобок)
        brace_count = 0
        in_string = None  # 'single' | 'double' | None
        in_line_comment = False
        in_block_comment = False
        body_start = i
        body_end = i
        found_opening = False  # нашли ли открывающую { 
        
        j = i
        while j < len(lines):
            line_j = lines[j]
            
            # Обрабатываем содержимое строки посимвольно
            k = 0
            while k < len(line_j):
                char = line_j[k]
                
                if in_line_comment:
                    break  # до конца строки
                
                if in_block_comment:
                    if char == '*' and k + 1 < len(line_j) and line_j[k+1] == '/':
                        in_block_comment = False
                        k += 1
                    k += 1
                    continue
                
                if in_string:
                    if char == '\\':
                        k += 2  # пропускаем escape
                        continue
                    if char == in_string:
                        in_string = None
                    k += 1
                    continue
                
                # Не в строке/комментарии
                if char == '/' and k + 1 < len(line_j):
                    if line_j[k+1] == '/':
                        in_line_comment = True
                        break
                    elif line_j[k+1] == '*':
                        in_block_comment = True
                        k += 2
                        continue
                
                if char == '#':
                    in_line_comment = True
                    break
                
                if char == "'":
                    in_string = "'"
                elif char == '"':
                    in_string = '"'
                elif char == '{':
                    brace_count += 1
                    found_opening = True
                elif char == '}':
                    brace_count -= 1
                    if found_opening and brace_count == 0:
                        body_end = j
                        break
                
                k += 1
            
            in_line_comment = False
            
            if found_opening and brace_count == 0:
                break
            j += 1
        
        # Полный текст метода (включая docblock/attributes)
        full_text = '\n'.join(lines[doc_start:body_end + 1])
        
        methods.append({
            'name': method_name,
            'start_line': doc_start,
            'end_line': body_end,
            'full_text': full_text,
            'indent': indent,
        })
        
        i = body_end + 1
    
    return methods


# === ГРУППИРОВКА МЕТОДОВ ПО ДОМЕНАМ ===

DOMAINS = {
    'WithAlerts': [
        'alert', 'acknowledge', 'AlertFilter', 'resetAlertFilters',
        'getNewAlertsCount', 'getRecentAlerts',
    ],
    'WithRoutes': [
        'openEditOrder', 'closeEditOrder', 'saveOrder', 'toggleOrderActive',
        'deleteOrder', 'createNewOrder', 'openCreateOrderModal', 'closeCreateOrderModal',
        'generateAllPairs', 'getOrdersForManagement', 'getOrdersGroupedByMiner',
        'optimizeRoutes', 'activateOrder', 'deactivateOrder',
        'getRouteMode', 'toggleRouteMode', 'setRouteMode',
        'openWeightEditor', 'saveWeight', 'closeWeightEditor', 'adjustWeight',
    ],
    'WithZones': [
        'toggleZone', 'applyZoneChanges', 'getZonesLoad',
        'balanceZones', 'redirectFromZone',
        'getOverloadedZonesCount', 'getOverloadedZones',
        'onZoneNeedsBerm', 'onZoneFillWarning',
    ],
    'WithTrucks': [
        'getFreeTrucksCount', 'getWorkingTrucksCount', 'getTruckDiagnostics',
        'getFreeTrucks', 'getLoadedTruckStatuses', 'isSelectedTruckLoaded',
        'getLoadedTruckInfo', 'openForceStatusModal', 'closeForceStatusModal',
        'forceChangeStatus', 'getAvailableStatuses', 'onTruckUpdated',
    ],
    'WithMiners': [
        'getActiveMinersCount', 'getBreakdownCount', 'getMinerBreakdownCount',
        'getMinerDelayedCount', 'getActiveMinersWithRock',
        'onMinerProductivityUpdated',
        'openMinerStatusModal', 'closeMinerStatusModal', 'setMinerStatus',
        'getMinerStatuses', 'getMinerDelayStatuses',
    ],
    'WithAnalytics': [
        'getPlannedDistanceStats', 'getEmptyRunStats', 'getPauseStats',
        'getMinerDelays', 'getRouteSpeeds', 'getAnalyticsTrends',
        'getStatsPeriodStart', 'getShiftStartForAnalytics',
        'getStatsPeriodLabel', 'getStatsByPeriod', 'getStatsTotals',
        'getStatsMinersList', 'getMaintenanceStatsPeriodStart',
        'getMaintenanceStatsPeriodLabel', 'getMaintenanceStatsTrucksList',
        'getMaintenanceTaskTypeLabel', 'getMaintenanceStatsByPeriod',
        'getMaintenanceStatsTotals',
    ],
    'WithSettings': [
        'loadThresholds', 'saveThresholds', 'saveAvgDistanceThreshold',
        'updatedMinerThreshold', 'updatedZoneThreshold',
        'loadServicePostSettings', 'getServicePostsStatus',
        'getServiceQueue', 'completeServiceTask', 'cancelServiceTask',
        'saveServicePostCount', 'getMaintenancePostsCount',
    ],
}

# Методы, которые ОСТАЮТСЯ в главном классе
KEEP_IN_MAIN = {
    'boot', 'mount', 'render', 'loadData', 'setActiveTab', 'onRefreshData',
    'assignRoute', 'assignAllFree',
    # updated* хуки (cross-domain)
    'updatedSelectedMinerId', 'updatedSelectedOrderId',
    'updatedSelectedTruckId', 'updatedLoadedTruckRockId',
    'updatedZoneSharingThreshold',  # если есть
    # Event listeners (cross-domain)
    'onTruckStatusChanged',
    # onMinerStopped/onMinerStarted — удалены ранее, но если есть — в main
    'onMinerStopped', 'onMinerStarted',
}


def classify_method(name):
    """Определяет, в какой trait идёт метод. Возвращает None если остаётся в main."""
    if name in KEEP_IN_MAIN:
        return None
    
    name_lower = name.lower()
    
    # WithAlerts — всё про алерты
    if any(kw in name_lower for kw in ['alert', 'acknowledge']):
        return 'WithAlerts'
    
    # WithSettings — пороги, сервисные посты, очередь ТО, планирование смены
    if any(kw in name_lower for kw in ['threshold', 'servicepost', 'servicequeue',
                                        'completeservicetask', 'cancelservicetask',
                                        'savepost', 'syncservice', 'sendtoservice',
                                        'runshiftplanning', 'queuestats',
                                        'plannedtasks', 'to1interval',
                                        'postscount', 'fuelingposts', 'tirecount']):
        return 'WithSettings'
    
    # WithAnalytics — статистика, тренды, периоды, простои, ТО-метки
    if any(kw in name_lower for kw in ['stats', 'period', 'speed', 'analytics', 'trend',
                                        'pauses', 'emptyrun', 'shiftstart',
                                        'formatseconds', 'formatminutes',
                                        'tasktype', 'maintenancetask']):
        return 'WithAnalytics'
    
    # WithRoutes — заказы, маршруты, вес, оптимизация
    if any(kw in name_lower for kw in ['order', 'route', 'weight', 'optimize',
                                        'pair', 'routemode', 'createnew',
                                        'generateall', 'getorders']):
        return 'WithRoutes'
    
    # WithZones — зоны, балансировка, перенаправление
    if any(kw in name_lower for kw in ['zone', 'balance', 'redirect', 'berm', 'fill']):
        return 'WithZones'
    
    # WithTrucks — самосвалы, статус, погрузка
    if any(kw in name_lower for kw in ['truck', 'force', 'loaded', 'freeruck',
                                        'working', 'diagnostic', 'availablestatus',
                                        'assignzonetoloaded']):
        return 'WithTrucks'
    
    # WithMiners — забои, статусы забоев
    if any(kw in name_lower for kw in ['miner', 'breakdown', 'delayed',
                                        'activemin', 'getminer']):
        return 'WithMiners'
    
    return None


# === СВОЙСТВА ПО ДОМЕНАМ ===

PROPERTIES = {
    'WithAlerts': [
        'alertFilters', 'contentSearch', 'categoryId', 'userId', 'createdAt',
    ],
    'WithRoutes': [
        'editOrderId', 'editDumpId', 'editRockId', 'editWeight', 'editDistances',
        'editActive',
        'showCreateOrderModal', 'newOrderMinerId', 'newOrderDumpId',
        'newOrderRockId', 'newOrderDistanceKm', 'newOrderWeight',
        'newOrderSets', 'newOrderImage', 'editingOrderId', 'newOrderMashineId',
        'newOrderContent',
    ],
    'WithAnalytics': [
        'statsPeriod', 'statsShift', 'statsGrouping', 'statsMinerId',
        'maintenanceStatsPeriod', 'maintenanceStatsShift', 'maintenanceStatsGrouping',
        'maintenanceStatsTruckId', 'maintenanceStatsTaskType',
    ],
    'WithSettings': [
        'minerThreshold', 'zoneThreshold', 'avgDistanceThreshold',
        'fuelingPostsCount', 'maintenancePostsCount', 'tireServicePostsCount',
        'to1IntervalHours',
    ],
    'WithTrucks': [
        'forceStatusTruckId', 'forceStatusNew', 'loadedTruckRockId',
    ],
    'WithMiners': [
        'editMinerStatusId', 'editMinerStatusNew',
        'showMinerWarning', 'minerStatusWarning', 'minerSafetyCheck',
    ],
    'WithZones': [],
}


def find_properties(content):
    """Находит все public свойства класса (включая многострочные)."""
    props = []
    lines = content.split('\n')
    i = 0
    while i < len(lines):
        line = lines[i]
        # public ИЛИ protected type $var  ИЛИ  public $var (без типа)
        match = re.match(r'^(\s+)(public|protected)\s+(?:(\S+)\s+)?\$(\w+)', line)
        if match and 'function' not in line:
            prop_name = match.group(4)  # группа 4 = имя переменной (1=indent, 2=visibility, 3=type, 4=name)
            # Если строка не заканчивается ; — свойство многострочное, ищем конец
            full_prop = line
            j = i
            while j < len(lines) and not lines[j].rstrip().endswith(';'):
                j += 1
                if j < len(lines):
                    full_prop += '\n' + lines[j]
            props.append({
                'name': prop_name,
                'line': i,
                'text': full_prop,
            })
            i = j + 1
        else:
            i += 1
    return props


def classify_property(name):
    """ВСЕ свойства остаются в главном классе (shared state).
    Traits обращаются к ним через $this->."""
    return None


# === ГЛАВНАЯ ФУНКЦИЯ ===

def main():
    with open(SRC, encoding='utf-8') as f:
        content = f.read()
    
    # Бэкап
    shutil.copy2(SRC, BACKUP)
    print(f'Бэкап создан: {BACKUP}')
    
    # Извлекаем use-импорты из начала файла
    use_lines = []
    for line in content.split('\n'):
        if line.startswith('use ') and line.endswith(';'):
            use_lines.append(line)
    
    # Находим методы
    methods = find_methods(content)
    print(f'Найдено методов: {len(methods)}')
    
    # Группируем методы
    trait_methods = defaultdict(list)
    main_methods = []
    unclassified = []
    
    for m in methods:
        trait = classify_method(m['name'])
        if trait:
            trait_methods[trait].append(m)
        else:
            main_methods.append(m)
            if m['name'] not in KEEP_IN_MAIN:
                unclassified.append(m)
    
    print(f'\nРаспределение:')
    for trait, ms in sorted(trait_methods.items()):
        print(f'  {trait}: {len(ms)} методов')
    print(f'  Главный класс: {len(main_methods)} методов')
    if unclassified:
        print(f'\n⚠ НЕКЛАССИФИЦИРОВАННЫЕ (остаются в main):')
        for m in unclassified:
            print(f'    - {m["name"]} (строка {m["start_line"]+1})')
    
    # Находим свойства
    props = find_properties(content)
    trait_props = defaultdict(list)
    main_props = []
    
    for p in props:
        trait = classify_property(p['name'])
        if trait:
            trait_props[trait].append(p)
        else:
            main_props.append(p)
    
    print(f'\nСвойства:')
    for trait, ps in sorted(trait_props.items()):
        print(f'  {trait}: {len(ps)} свойств')
    print(f'  Главный класс: {len(main_props)} свойств')
    
    # Создаём директорию
    os.makedirs(TRAITS_DIR, exist_ok=True)
    
    # Создаём trait-файлы
    for trait_name, ms in sorted(trait_methods.items()):
        filepath = os.path.join(TRAITS_DIR, trait_name + '.php')
        
        # Собираем use-импорты
        use_block = '\n'.join(use_lines)
        
        # Свойства НЕ выносятся в traits — все остаются в главном классе
        
        # Методы trait
        methods_block = '\n\n'.join(m['full_text'] for m in ms)
        
        trait_content = f"""<?php

namespace App\\Livewire\\Traits;

{use_block}

trait {trait_name}
{{
{methods_block}
}}
"""
        with open(filepath, 'w', encoding='utf-8') as f:
            f.write(trait_content)
        
        print(f'  ✓ {filepath} ({len(ms)} методов, {len(trait_props.get(trait_name, []))} свойств)')
    
    # Перезаписываем главный класс
    # Оставляем: namespace, use, class declaration, shared properties, main methods, render
    
    lines = content.split('\n')
    
    # Находим границы: начало класса (class MainDispatcherPanel extends Component)
    class_start = None
    class_end = None
    for i, line in enumerate(lines):
        if 'class MainDispatcherPanel' in line:
            class_start = i
        if class_start is not None and i > class_start and line.strip() == '}':
            class_end = i
            break
    
    # Собираем новый файл
    # 1. PHP тег + namespace + use (до class declaration)
    header_lines = lines[:class_start]
    
    # 1.5. Добавляем use-импорты для traits ДО атрибутов #[Layout] / #[Title]
    #       В PHP use-импорты должны быть до атрибутов и class declaration
    trait_imports = '\n'.join(
        f'use App\\Livewire\\Traits\\{t};' for t in sorted(trait_methods.keys())
    )
    
    # Находим позицию: после последнего use-statement, но ДО атрибутов #[
    insert_pos = len(header_lines)
    for idx in range(len(header_lines) - 1, -1, -1):
        line_stripped = header_lines[idx].strip()
        if line_stripped.startswith('#[') or line_stripped == '' or line_stripped.startswith('/*') or line_stripped.startswith('*') or line_stripped.startswith('//'):
            continue
        # Нашли последнюю строку с use-импортом или namespace
        insert_pos = idx + 1
        break
    
    # Вставляем trait-импорты после последнего use-statement
    header_text = '\n'.join(header_lines[:insert_pos])
    header_text += '\n' + trait_imports + '\n\n'
    header_text += '\n'.join(header_lines[insert_pos:])
    
    # 2. Use statements для traits ВНУТРИ класса
    trait_uses = '    use ' + ', '.join(sorted(trait_methods.keys())) + ';\n'
    
    # 3. Shared свойства (только те, что не ушли в traits)
    shared_props = []
    prop_names_in_traits = set()
    for ps in trait_props.values():
        for p in ps:
            prop_names_in_traits.add(p['name'])
    
    for p in main_props:
        shared_props.append(f'    {p["text"].strip()}')
    
    # 4. Главные методы
    main_methods_text = '\n\n'.join(m['full_text'] for m in main_methods if m['name'] not in ['boot'])
    
    # boot — особый, может быть первым
    boot_method = next((m for m in main_methods if m['name'] == 'boot'), None)
    
    new_content = header_text
    new_content += 'class MainDispatcherPanel extends Component\n'
    new_content += '{\n'
    new_content += trait_uses
    new_content += '\n'
    if shared_props:
        new_content += '\n'.join(shared_props) + '\n'
    new_content += '\n'
    if boot_method:
        new_content += boot_method['full_text'] + '\n\n'
    new_content += main_methods_text + '\n'
    new_content += '}\n'
    
    with open(SRC, 'w', encoding='utf-8') as f:
        f.write(new_content)
    
    print(f'\n✓ {SRC} перезаписан')
    print(f'  Traits: {", ".join(sorted(trait_methods.keys()))}')
    print(f'  Методов в main: {len(main_methods)}')
    print(f'  Свойств в main: {len(main_props)}')
    print(f'\nБэкап: {BACKUP}')
    print(f'\nДалее: php artisan optimize:clear && php artisan serve')


if __name__ == '__main__':
    main()
