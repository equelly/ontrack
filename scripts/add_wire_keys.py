#!/usr/bin/env python3
"""
Добавить wire:key на элементы внутри @foreach/@forelse.
Без wire:key morphdom пере-создаёт все элементы цикла при каждом обновлении,
что занимает секунды на больших списках.

Стратегия:
- Для каждого @foreach($coll as $var) / @forelse($coll as $var)
- Найти первый открывающий тег после (например <tr>, <div>, <li>)
- Добавить wire:key="{{ $var }}-{{ $loop->iteration }}"
  (используем iteration — уникально в рамках цикла, не зависит от поля модели)

Также добавляем wire:key на корневой div каждой вкладки:
    <div wire:key="tab-dashboard" ...>
Это помогает morphdom понять, что при переключении вкладки это НОВЫЙ блок,
а не модификация старого (что вызывает долгий diff).
"""
import re
from pathlib import Path

# Определяем путь к файлу относительно папки, где лежит сам скрипт
# __file__ указывает на add_wire_keys.py, .parent — это папка scripts/, .parent.parent — корень проекта
SRC = Path(__file__).resolve().parent.parent / "resources" / "views" / "livewire" / "master-panel.blade.php"

text = SRC.read_text(encoding="utf-8")
lines = text.splitlines(keepends=True)


# 1. wire:key на корневых div'ах вкладок
# Паттерн: <div class="mt-4 space-y-..." внутри @if($activeTab === 'xxx')
tab_div_pattern = re.compile(
    r"(\s+@if\(\$activeTab === '(\w+)'\)\n\s+)<div class=\"(mt-4 space-y-\d)\""
)
def add_tab_key(m):
    indent_if = m.group(1)
    tab = m.group(2)
    cls = m.group(3)
    return f'{indent_if}<div wire:key="tab-{tab}" class="{cls}"'

text, tab_count = tab_div_pattern.subn(add_tab_key, text)
print(f"OK: wire:key добавлен на {tab_count} корневых div'ов вкладок")

# 2. wire:key на первый открывающий тег после @foreach/@forelse
# Ищем @foreach($coll as $var) или @forelse($coll as $var)
# Затем первый тег вида <TAG ...> на новой строке
# Добавляем wire:key="var-{{ $loop->iteration }}"
# Если это @forelse — $loop недоступен, используем $var->id или key

foreach_pattern = re.compile(
    r"(@(?:foreach|forelse)\((?:[^()]|\([^()]*\))*\bas\s+(\$\w+)(?:\s*\))?[^()]*\)\s*\n)"
    r"(\s*)(<\w+)((?:[^>]*?)>)",
    re.MULTILINE
)

def add_foreach_key(m):
    full_first = m.group(1)  # @foreach(...) line + newline
    var = m.group(2)         # $var
    indent = m.group(3)
    tag_open = m.group(4)    # <tr / <div / <li etc.
    rest = m.group(5)        # rest of tag until >

    # Skip if already has wire:key
    if 'wire:key' in rest:
        return m.group(0)

    # Determine loop variable: $loop->iteration for @foreach, fallback for @forelse
    if '@foreach' in full_first:
        key_expr = f"{{{{ ${var.lstrip('$')}-{{{{ $loop->iteration }}}}"
        # Better: use var name + iteration
        key_str = f'wire:key="{var.lstrip("$")}-{{{{ $loop->iteration }}}}"'
    else:  # @forelse — $loop also available in Blade @forelse
        key_str = f'wire:key="{var.lstrip("$")}-{{{{ $loop->iteration }}}}"'

    # Insert wire:key as first attribute of the tag
    return f"{full_first}{indent}{tag_open} {key_str}{rest}"

text, foreach_count = foreach_pattern.subn(add_foreach_key, text)
print(f"OK: wire:key добавлен на {foreach_count} элементов внутри @foreach/@forelse")

SRC.write_text(text, encoding="utf-8")
print(f"\nГотово. Строк: {len(text.splitlines())}")
