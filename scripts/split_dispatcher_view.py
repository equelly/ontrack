#!/usr/bin/env python3
"""Разрезает main-dispatcher-panel.blade.php на отдельные файлы по вкладкам."""

import os, re

# Пути относительно корня проекта (запускай из корня: python3 scripts/split_dispatcher_view.py)
SRC = 'resources/views/livewire/main-dispatcher-panel.blade.php'
DEST_DIR = 'resources/views/livewire/dispatcher/'

# (filename, start_line, end_line) — 1-indexed, inclusive
SECTIONS = [
    ('_header.blade.php',         2, 263),
    ('_trucks-tab.blade.php',   264, 454),
    ('_miners-tab.blade.php',   455, 636),
    ('_routes-tab.blade.php',   637, 1065),
    ('_assign-tab.blade.php',  1066, 1166),
    ('_zones-tab.blade.php',   1167, 1309),
    ('_pauses-tab.blade.php',  1310, 1428),
    ('_analytics-tab.blade.php',1429,1909),
    ('_settings-tab.blade.php',1910, 2091),
    ('_modals.blade.php',      2092, 2169),
    ('_scripts.blade.php',     2170, 2210),
]

# Читаем исходный файл
with open(SRC, encoding='utf-8') as f:
    lines = f.readlines()

# Создаём директорию
os.makedirs(DEST_DIR, exist_ok=True)

# Режем на файлы
for filename, start, end in SECTIONS:
    section_lines = lines[start-1:end]
    filepath = os.path.join(DEST_DIR, filename)
    with open(filepath, 'w', encoding='utf-8') as f:
        f.writelines(section_lines)
    count = end - start + 1
    print(f'  ✓ {filename} ({count} строк)')

# ФИКС 1: убираем <div class="tab-content"> из _header
header_path = os.path.join(DEST_DIR, '_header.blade.php')
with open(header_path, encoding='utf-8') as f:
    header = f.read()
header = header.replace('    <div class="tab-content">\n', '')
with open(header_path, 'w', encoding='utf-8') as f:
    f.write(header)

# ФИКС 2: убираем лишний </div> из конца _settings-tab
settings_path = os.path.join(DEST_DIR, '_settings-tab.blade.php')
with open(settings_path, encoding='utf-8') as f:
    s_lines = f.readlines()
while s_lines and s_lines[-1].strip() == '':
    s_lines.pop()
if s_lines and s_lines[-1].strip() == '</div>':
    s_lines.pop()
with open(settings_path, 'w', encoding='utf-8') as f:
    f.writelines(s_lines)

# Записываем новый главный файл (с tab-content обёрткой)
main_content = """<div class="dispatcher-panel-wrapper" x-data="{ tab: @entangle('activeTab') }">
    @include('livewire.dispatcher._header')
    <div class="tab-content">
        @include('livewire.dispatcher._trucks-tab')
        @include('livewire.dispatcher._miners-tab')
        @include('livewire.dispatcher._routes-tab')
        @include('livewire.dispatcher._assign-tab')
        @include('livewire.dispatcher._zones-tab')
        @include('livewire.dispatcher._pauses-tab')
        @include('livewire.dispatcher._analytics-tab')
        @include('livewire.dispatcher._settings-tab')
    </div>
    @include('livewire.dispatcher._modals')
    @include('livewire.dispatcher._scripts')
</div>
"""
with open(SRC, 'w', encoding='utf-8') as f:
    f.write(main_content)

# Проверка div-баланса
OPEN = re.compile(r'<div\\b')
CLOSE = re.compile(r'</div>')
total_o = 0
total_c = 0
for fn in sorted(os.listdir(DEST_DIR)):
    if not fn.endswith('.blade.php'): continue
    content = open(os.path.join(DEST_DIR, fn), encoding='utf-8').read()
    o = len(OPEN.findall(content))
    c = len(CLOSE.findall(content))
    total_o += o; total_c += c
    print(f'  {fn:30s} {o:3d}/{c:3d} {"OK" if o==c else "ИМБАЛАНС"}')
main = open(SRC, encoding='utf-8').read()
mo = len(OPEN.findall(main)); mc = len(CLOSE.findall(main))
total_o += mo; total_c += mc
print(f'  {"main":30s} {mo:3d}/{mc:3d} OK')
print(f'  {"ИТОГО":30s} {total_o:3d}/{total_c:3d} {"OK" if total_o==total_c else "ИМБАЛАНС!"}')
print(f'\\nГотово! 11 файлов в dispatcher/ + 1 главный (15 строк)')