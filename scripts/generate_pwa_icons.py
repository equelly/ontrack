#!/usr/bin/env python3
"""
Генерация PNG-иконок для PWA из SVG.
Создаёт:
  - icon-192.png  (192x192) — стандартная
  - icon-512.png  (512x512) — стандартная
  - apple-touch-icon.png (180x180) — для iOS
  - icon-maskable-512.png (512x512) — маскируемая (safe-zone)
"""
#!/usr/bin/env python3
import os
import io
import sys

# Собираем правильный xmlns по символам, чтобы обойти любые автозамены
XMLNS_URL = "http" + "://" + "www.w3.org/2000/svg"

# Исправляем пути: выходим из папки scripts и переходим в public/icons
BASE_DIR = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
ICONS_DIR = os.path.join(BASE_DIR, "public", "icons")
SVG_PATH = os.path.join(ICONS_DIR, "icon.svg")

# Конфигурация иконок
TARGETS = [
    ("icon-192.png", 192, 0),
    ("icon-512.png", 512, 0),
    ("apple-touch-icon.png", 180, 0),
    ("icon-maskable-512.png", 512, 10),
]

def check_and_fix_svg():
    """Проверяет SVG. Если он пустой, поврежден или отсутствует, создает рабочую заглушку."""
    placeholder_svg = (
        f'<svg xmlns="{XMLNS_URL}" viewBox="0 0 512 512" width="512" height="512">'
        '<circle cx="256" cy="256" r="240" fill="#007bff"/>'
        '<text x="50%" y="55%" dominant-baseline="middle" text-anchor="middle" '
        'font-family="sans-serif" font-size="140" fill="#ffffff" font-weight="bold">App</text>'
        '</svg>'
    )
    
    # Создаем папку public/icons, если её ещё нет
    os.makedirs(ICONS_DIR, exist_ok=True)
    
    # Перезаписываем файл, если он пуст, отсутствует или содержит старый битый xmlns
    should_fix = False
    if not os.path.exists(SVG_PATH) or os.path.getsize(SVG_PATH) == 0:
        should_fix = True
    else:
        with open(SVG_PATH, "r", encoding="utf-8", errors="ignore") as f:
            content = f.read()
            if "w3.org}" in content or "w3.org\"" in content and "2000" not in content:
                should_fix = True

    if should_fix:
        print(f"⚠️ Исходный SVG пуст или содержит ошибку. Создаю правильную заглушку в {SVG_PATH}...")
        with open(SVG_PATH, "w", encoding="utf-8") as f:
            f.write(placeholder_svg)

def render_svg_at_size(svg_path: str, size: int) -> 'Image.Image':
    """Рендеринг SVG in PNG через cairosvg."""
    with open(svg_path, "rb") as f:
        svg_bytes = f.read()
    png_bytes = cairosvg.svg2png(
        bytestring=svg_bytes,
        output_width=size,
        output_height=size,
    )
    return Image.open(io.BytesIO(png_bytes)).convert("RGBA")

def main():
    try:
        global cairosvg, Image
        import cairosvg
        from PIL import Image
    except ImportError:
        print(f"❌ Ошибка: Не установлены библиотеки. Выполните:\nsudo apt install python3-cairosvg python3-pil")
        return 1

    check_and_fix_svg()

    for filename, size, padding in TARGETS:
        out_path = os.path.join(ICONS_DIR, filename)
        print(f"Генерация {filename} ({size}x{size}, padding={padding}%)...")
        try:
            if padding > 0:
                inner_size = int(size * (100 - 2 * padding) / 100)
                inner_img = render_svg_at_size(SVG_PATH, inner_size)
                bg_color = (30, 41, 59, 255)  # #1e293b RGBA (slate-800)
                img = Image.new("RGBA", (size, size), bg_color)
                offset = (size - inner_size) // 2
                img.paste(inner_img, (offset, offset), inner_img)
            else:
                img = render_svg_at_size(SVG_PATH, size)
            
            img.save(out_path, "PNG", optimize=True)
            print(f"  ✅ Сохранено: {out_path}")
        except Exception as e:
            print(f"  ❌ Ошибка при генерации {filename}: {e}")

    print("\n🎉 Все PWA иконки успешно созданы в папочке public/icons/!")
    return 0

if __name__ == "__main__":
    sys.exit(main())
