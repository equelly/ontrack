#!/usr/bin/env bash
#
# Установка Redis на Ubuntu для Laravel 10 + Reverb проекта.
#
# Запуск:
#   chmod +x scripts/install-redis.sh
#   sudo ./scripts/install-redis.sh
#
set -e

echo "=== Установка Redis на Ubuntu (Laravel 10) ==="

# Проверка прав
if [ "$(id -u)" -ne 0 ]; then
    echo "Запусти с sudo: sudo ./scripts/install-redis.sh"
    exit 1
fi

# Обновление пакетов
echo ""
echo "[1/4] Обновление индекса пакетов..."
apt-get update -qq

# Установка Redis и PHP расширения
echo ""
echo "[2/4] Установка redis-server и php-redis..."
apt-get install -y -qq redis-server php-redis

# Перезапуск PHP-FPM, если он установлен (автоопределение версии)
PHP_FPM_SERVICE=$(systemctl list-units --type=service --state=running | grep -oE "php[0-9.]+-fpm" | head -n 1)
if [ -n "$PHP_FPM_SERVICE" ]; then
    echo "  ✓ Перезапуск $PHP_FPM_SERVICE для применения расширения..."
    systemctl restart "$PHP_FPM_SERVICE"
fi

# Настройка: redis слушает на localhost только (безопасность)
echo ""
echo "[3/4] Настройка redis.conf (слушать только localhost)..."
REDIS_CONF="/etc/redis/redis.conf"
if grep -q "^bind 127.0.0.1" "$REDIS_CONF"; then
    echo "  ✓ bind уже настроен на 127.0.0.1"
else
    sed -i 's/^bind .*/bind 127.0.0.1 -::1/' "$REDIS_CONF"
    echo "  ✓ bind настроен на 127.0.0.1"
fi

# Запуск + автозапуск
echo ""
echo "[4/4] Запуск redis и включение автозапуска..."
systemctl restart redis-server
systemctl enable redis-server

# Тест
echo ""
echo "=== Проверка ==="
if redis-cli ping | grep -q PONG; then
    echo "✓ Redis работает: PONG"
else
    echo "✗ Redis не отвечает — проверь логи: journalctl -u redis-server"
    exit 1
fi

# Вывод рекомендаций для .env
cat <<'EOF'

=== Redis установлен и работает ===

Добавь в .env (актуально для Laravel 10 и Reverb):

  REDIS_CLIENT=phpredis
  REDIS_HOST=127.0.0.1
  REDIS_PASSWORD=null
  REDIS_PORT=6379

  CACHE_DRIVER=redis
  QUEUE_CONNECTION=redis
  SESSION_DRIVER=redis
  
  # Настройки для Laravel Reverb в Laravel 10
  BROADCAST_DRIVER=reverb

После этого выполни в проекте:
  1. php artisan config:clear
  2. php artisan cache:clear
  3. Проверь: php artisan tinker >>> Cache::put('test', 'ok'); Cache::get('test');

Совет для Prod: Задай пароль в /etc/redis/redis.conf (директива requirepass ваш_пароль)
и укажи его в .env (REDIS_PASSWORD).

EOF
