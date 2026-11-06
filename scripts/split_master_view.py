#!/usr/bin/env python3
"""
Разделение master-panel.blade.php на @include по вкладкам.

Структура после разделения:
  resources/views/livewire/
  ├── master-panel.blade.php        (главный — каркас + @include)
  └── master/
      ├── _header.blade.php          (шапка с alerts widget + навигация)
      ├── _dashboard-tab.blade.php   (Обзор)
      ├── _equipment-tab.blade.php   (Оборудование)
      ├── _maintenance-tab.blade.php (Обслуживание)
      ├── _requests-tab.blade.php    (Заявки)
      ├── _miners-tab.blade.php      (Забои)
      ├── _rocks-tab.blade.php       (Породы)
      ├── _dumps-tab.blade.php       (Перегрузки + berm)
      ├── _modals.blade.php          (Модалки: berm + order)
      └── _scripts.blade.php         (Leaflet + JS скрипты)
"""
import re
from pathlib import Path

SRC = Path("resources/views/livewire/master-panel.blade.php")
DST_DIR = Path("resources/views/livewire/master")
DST_DIR.mkdir(parents=True, exist_ok=True)

text = SRC.read_text(encoding="utf-8")
lines = text.splitlines(keepends=True)

# Найти границы @if($activeTab === 'xxx') ... @endif
tab_blocks = {}
pattern = re.compile(r"^(\s*)@if\(\$activeTab === '(\w+)'\)\s*$")
for i, line in enumerate(lines):
    m = pattern.match(line.rstrip('\n'))
    if m:
        tab = m.group(2)
        for j in range(i+1, len(lines)):
            if lines[j].rstrip() == '@endif':
                tab_blocks[tab] = (i, j)
                break

print("Найденные блоки вкладок:")
for tab, (start, end) in tab_blocks.items():
    print(f"  {tab}: строки {start+1}-{end+1} ({end-start} строк)")

# 1. Извлечь содержимое каждой вкладки в отдельный файл
for tab, (start, end) in tab_blocks.items():
    content_lines = lines[start+1:end]
    content = ''.join(content_lines)
    fname = f"_{tab}-tab.blade.php"
    (DST_DIR / fname).write_text(content, encoding="utf-8")
    print(f"OK: создан {fname} ({len(content_lines)} строк)")

# 2. Найти где заканчивается последняя вкладка → начало модалок
last_tab_end = max(end for _, end in tab_blocks.values())
modal_start = last_tab_end + 1

# Найти секцию scripts
script_start = None
for i in range(modal_start, len(lines)):
    if '<!-- Подключаем стили' in lines[i] or '<script src=' in lines[i]:
        script_start = i
        break

if script_start:
    modals_content = ''.join(lines[modal_start:script_start])
    scripts_content = ''.join(lines[script_start:])
    (DST_DIR / "_modals.blade.php").write_text(modals_content, encoding="utf-8")
    print(f"OK: создан _modals.blade.php ({script_start - modal_start} строк)")
    (DST_DIR / "_scripts.blade.php").write_text(scripts_content, encoding="utf-8")
    print(f"OK: создан _scripts.blade.php ({len(lines) - script_start} строк)")
else:
    modals_content = ''.join(lines[modal_start:])
    (DST_DIR / "_modals.blade.php").write_text(modals_content, encoding="utf-8")
    print(f"OK: создан _modals.blade.php (без отдельных scripts)")

# 3. Извлечь шапку
dashboard_start = tab_blocks['dashboard'][0]
header_lines = lines[:dashboard_start]
header_content = ''.join(header_lines)
(DST_DIR / "_header.blade.php").write_text(header_content, encoding="utf-8")
print(f"OK: создан _header.blade.php ({dashboard_start} строк)")

# 4. Переписать главный master-panel.blade.php
new_main = []
new_main.append('{{-- Главный файл панели мастера. Каркас + @include по вкладкам. --}}\n')
new_main.append('{{-- Каждая вкладка вынесена в master/_xxx-tab.blade.php для удобства поддержки. --}}\n')
new_main.append('<div class="min-h-screen flex flex-col bg-slate-50" x-data="{}">\n\n')
new_main.append('    {{-- Шапка с alerts widget и навигацией по вкладкам --}}\n')
new_main.append('    @include(\'livewire.master._header\')\n\n')

tab_order = ['dashboard', 'equipment', 'maintenance', 'requests', 'miners', 'rocks', 'dumps']
for tab in tab_order:
    new_main.append(f'    @if($activeTab === \'{tab}\')\n')
    new_main.append(f'        @include(\'livewire.master._{tab}-tab\')\n')
    new_main.append(f'    @endif\n\n')

new_main.append('    {{-- Модальные окна (berm + orders) --}}\n')
new_main.append('    @include(\'livewire.master._modals\')\n\n')
new_main.append('    {{-- Подключение стилей и скриптов (Leaflet и др.) --}}\n')
new_main.append('    @include(\'livewire.master._scripts\')\n\n')
new_main.append('</div>\n')

SRC.write_text(''.join(new_main), encoding="utf-8")
print(f"\nOK: master-panel.blade.php переписан ({len(new_main)} строк)")