# Deploy Guide — Подготовка к "боевой" среде

Подробная инструкция по настройке проекта для работы в карьере: Redis, очереди, WebSocket, офлайн-режим.

## 📋 Что входит

- `scripts/install-redis.sh` — установка Redis на Ubuntu
- `.env.example` — обновлённый пример конфигурации (Redis + Reverb)
- `deploy/supervisor/laravel-worker.conf` — queue workers (Redis queue)
- `deploy/supervisor/laravel-reverb.conf` — Reverb WebSocket server
- `deploy/supervisor/supervisord.conf` — для Docker-контейнера
- `docker-compose.yml` — полный стек для прода
- `Dockerfile` — образ PHP-FPM + supervisor
- `app/Console/Commands/HealthCheck.php` — проверка системы

---

## 🚀 Быстрый старт (локальный компьютер, Ubuntu)

### 1. Установить Redis

```bash
chmod +x scripts/install-redis.sh
sudo ./scripts/install-redis.sh
```

Скрипт:
- Устанавливает redis-server
- Настраивает bind 127.0.0.1 (безопасность)
- Запускает и включает автозапуск
- Проверяет `redis-cli ping`

### 2. Обновить `.env`

Скопируй обновлённый `.env.example` в `.env`:
```bash
cp .env.example .env
php artisan key:generate
```

Заполни:
- `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` — твои данные БД
- `REVERB_APP_ID`, `REVERB_APP_KEY`, `REVERB_APP_SECRET` — сгенерируй через `php artisan reverb:install`

Основные изменения:
- `BROADCAST_DRIVER=reverb` (не log!)
- `CACHE_STORE=redis` (не file!)
- `QUEUE_CONNECTION=redis` (не sync!)
- `SESSION_DRIVER=redis` (быстрее, шарится между процессами)

### 3. Очистить кэш

```bash
php artisan config:clear
php artisan cache:clear
php artisan view:clear
php artisan optimize:clear
```

### 4. Запустить supervisor (workers + Reverb)

```bash
sudo cp deploy/supervisor/laravel-worker.conf /etc/supervisor/conf.d/
sudo cp deploy/supervisor/laravel-reverb.conf /etc/supervisor/conf.d/
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start laravel-worker
sudo supervisorctl start laravel-reverb
```

Проверка статуса:
```bash
sudo supervisorctl status
```

Должно показать:
```
laravel-reverb                  RUNNING   pid 12345, uptime 0:01:23
laravel-worker:laravel-worker_00   RUNNING   pid 12346, uptime 0:01:23
laravel-worker:laravel-worker_01   RUNNING   pid 12347, uptime 0:01:23
```

### 5. Cron для scheduler'а

```bash
crontab -e
```

Добавить строку:
```
* * * * * cd /opt/lampp/htdocs/lavue && php artisan schedule:run >> /dev/null 2>&1
```

### 6. Проверка системы

```bash
php artisan health:check
```

Должен вернуть `✅ Ошибок нет` для всех компонентов.

### 7. Тест офлайн-режима

- Открой Панель Водителя в браузере
- Выключи Wi-Fi на компьютере (или через DevTools → Network → "Offline")
- Вверху должен появиться красный баннер "Нет связи с сервером"
- Включи Wi-Fi обратно
- Баннер исчезнет, появится toast "Связь восстановлена — данные обновлены"
- Данные в панели обновятся автоматически

---

## 🐳 Docker (для прода/продажи)

### 1. Создать `.env` для Docker

```bash
cp .env.example .env
# Отредактируй DB_PASSWORD, REVERB_APP_KEY, APP_KEY
```

### 2. Запустить стек

```bash
docker compose up -d
```

Сервисы:
- `app` — PHP-FPM (порт 9000, для nginx)
- `nginx` — reverse proxy (порт 8080 → http://localhost:8080)
- `mysql` — база данных (порт 3306)
- `redis` — кэш/очередь/сессии (порт 6379)
- `supervisor` — queue workers + Reverb (порт 8081)

### 3. Инициализация

```bash
docker compose exec app php artisan key:generate
docker compose exec app php artisan reverb:install
docker compose exec app php artisan migrate
docker compose exec app php artisan config:cache
```

### 4. Проверка

```bash
docker compose exec app php artisan health:check
```

### 5. Остановить

```bash
docker compose down
# С данными: docker compose down -v
```

---

## 📊 Что делает каждый сервис

### Queue Worker (`laravel-worker`)
- Читает jobs из Redis очередей `default`, `realtime`, `ai`
- Обрабатывает broadcast events (`DriverRouteUpdated`, `LoadingCompleted`, `ZoneFillWarning`)
- Запускает AI-анализ, автобалансировку маршрутов
- 2 параллельных процесса (`numprocs=2`)
- Auto-restart при падении
- Лог в `/var/log/supervisor/laravel-worker.log`

### Reverb (`laravel-reverb`)
- WebSocket-сервер для real-time коммуникации
- Принимает события от PHP и рассылает браузерам
- Слушает `127.0.0.1:8081` (для dev — 8080, для prod — за nginx с TLS)
- Авто-restart

### Redis
- Cache (заметно ускоряет часто используемые методы)
- Queue (персистентные jobs — не теряются при падении PHP-FPM)
- Session (шарится между серверами)

### Cron (scheduler)
- Запускается каждую минуту
- Выполняет команды по расписанию (`ai:analyze-fleet`, `trips:close-stale`, etc.)
- См. `app/Console/Kernel.php`

---

## ⚠️ Важные нюансы для карьера

### 1. Офлайн-режим браузера

Когда самосвал в карьере теряет Wi-Fi:
- Красный баннер вверху: "Нет связи с сервером"
- Все действия пользователя (например "Получить маршрут") ставятся в очередь
- Livewire и Echo автоматически пытаются переподключиться
- При восстановлении связи:
  - Livewire dispatch `refresh-*` → данные обновляются
  - Echo reconnect → снова слушает события
  - Toast: "Связь восстановлена — данные обновлены"

### 2. События потерянные во время offline

Real-time события (`LoadingCompleted`, `DriverRouteUpdated`) которые сервер отправил
пока самосвал был offline — **теряются** (Reverb их разослал, но никто не получил).

Для восстановления:
- При reconnect сервер отдаёт актуальное состояние из БД (через Livewire mount)
- AiAlert — персистентны в БД, не теряются
- Но: realtime toast'ы (например "Погрузка завершена") не повторяются

Решение для критичных событий — нужно хранить event history и при reconnect запрашивать
"что было пока я был offline" — это Stage 4 (Service Worker + IndexedDB).

### 3. Memory limit

В `laravel-worker.conf` указано `--memory=128` — воркер перезапустится если превысит 128 MB.
При росте нагрузки можно увеличить до 256 или 512 MB.

### 4. Supervisor reload при деплое

При обновлении кода **обязательно перезапустить воркеры** — иначе они работают со старым кодом:

```bash
sudo supervisorctl restart laravel-worker
# Reverb перезапускать НЕ нужно — он не зависит от кода
```

### 5. Логи

- App: `storage/logs/laravel.log`
- Queue worker: `/var/log/supervisor/laravel-worker.log`
- Reverb: `/var/log/supervisor/laravel-reverb.log`
- Supervisor: `/var/log/supervisor/supervisord.log`

---

## 🔍 Мониторинг

### HealthCheck команда
```bash
php artisan health:check
php artisan health:check --verbose
```

Возвращает код выхода:
- 0 — всё ок
- 1 — есть warnings
- 2 — есть errors (критично)

### Статус supervisor
```bash
sudo supervisorctl status
```

### Размер очередей в Redis
```bash
redis-cli llen queues:default
redis-cli llen queues:realtime
redis-cli llen queues:ai
```

### Failed jobs
```bash
php artisan queue:failed
php artisan queue:retry all  # повторить все
php artisan queue:flush       # удалить все failed
```

### Scheduler список
```bash
php artisan schedule:list
```
