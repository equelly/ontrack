/**
 * OfflineQueue — буферизация действий водителя при отсутствии связи.
 *
 * Сценарий:
 *   Водитель в забое без Wi-Fi. Нажимает "Прибыл на погрузку".
 *   JS видит что navigator.onLine === false → сохраняет action в IndexedDB.
 *   При восстановлении связи — flush() отправляет действия по одному
 *   через Livewire.call(method, ...args).
 *
 * ВАЖНО: действия применяются через СТАНДАРТНЫЕ Livewire-методы.
 * Серверная сторона не меняется — каждый метод уже проверяет статус
 * самосвала и применяет action только если он уместен.
 *
 * Хранение: IndexedDB (не localStorage, потому что:
 *   - localStorage лимит 5-10 МБ
 *   - IndexedDB — много больше, асинхронный, лучше для очередей)
 *
 * Идемпотентность: каждое действие имеет UUID. Если flush() упал на середине,
 * повторный flush() не отправит уже отправленные (по status: 'sent').
 */

class OfflineQueue {
    constructor() {
        this.dbName = 'lavue_offline_queue';
        this.storeName = 'actions';
        this.db = null;
        this.isFlushing = false;
        this.lastFlushAt = null;
    }

    /**
     * Инициализация — открывает IndexedDB.
     * Возвращает Promise.
     */
    async init() {
        if (this.db) return this.db;

        return new Promise((resolve, reject) => {
            const request = indexedDB.open(this.dbName, 1);

            request.onupgradeneeded = (event) => {
                const db = event.target.result;
                if (!db.objectStoreNames.contains(this.storeName)) {
                    const store = db.createObjectStore(this.storeName, { keyPath: 'id' });
                    store.createIndex('timestamp', 'timestamp', { unique: false });
                    store.createIndex('status', 'status', { unique: false });
                }
            };

            request.onsuccess = (event) => {
                this.db = event.target.result;
                resolve(this.db);
            };

            request.onerror = (event) => {
                console.error('[OfflineQueue] IndexedDB open failed:', event.target.error);
                reject(event.target.error);
            };
        });
    }

    /**
     * Добавить действие в очередь.
     *
     * @param {string} method  — имя Livewire-метода (например 'confirmArrivalAtMiner')
     * @param {array}  args    — аргументы метода (например [truckId])
     * @param {string} label   — человекочитаемое описание (для UI)
     * @returns {Promise<string>} UUID добавленного action
     */
    async enqueue(method, args = [], label = '') {
        await this.init();

        const action = {
            id: this.generateUUID(),
            method,
            args,
            label: label || method,
            timestamp: Date.now(),
            status: 'pending', // pending | sending | sent | failed
            attempts: 0,
            lastError: null,
        };

        return new Promise((resolve, reject) => {
            const tx = this.db.transaction(this.storeName, 'readwrite');
            const store = tx.objectStore(this.storeName);
            const request = store.add(action);

            request.onsuccess = () => {
                console.log('[OfflineQueue] Action enqueued:', action);
                this.notifyQueueChanged();
                resolve(action.id);
            };

            request.onerror = () => reject(request.error);
        });
    }

    /**
     * Получить все pending действия (отсортированные по timestamp).
     */
    async getPending() {
        await this.init();

        return new Promise((resolve, reject) => {
            const tx = this.db.transaction(this.storeName, 'readonly');
            const store = tx.objectStore(this.storeName);
            const index = store.index('timestamp');
            const request = index.openCursor();
            const result = [];

            request.onsuccess = (event) => {
                const cursor = event.target.result;
                if (cursor) {
                    if (cursor.value.status === 'pending' || cursor.value.status === 'failed') {
                        result.push(cursor.value);
                    }
                    cursor.continue();
                } else {
                    resolve(result);
                }
            };

            request.onerror = () => reject(request.error);
        });
    }

    /**
     * Отправить все pending действия на сервер.
     * Вызывается при window.online или вручную.
     *
     * ВАЖНО: отправляет ПО ОДНОМУ, последовательно. Если action зависит
     * от предыдущего (например "Начать погрузку" после "Прибыл на погрузку"),
     * то порядок важен.
     */
    async flush() {
        if (this.isFlushing) {
            console.log('[OfflineQueue] Already flushing, skip');
            return;
        }

        if (!navigator.onLine) {
            console.log('[OfflineQueue] Still offline, skip flush');
            return;
        }

        const pending = await this.getPending();
        if (pending.length === 0) {
            console.log('[OfflineQueue] No pending actions');
            return;
        }

        this.isFlushing = true;
        this.lastFlushAt = Date.now();

        console.log(`[OfflineQueue] Flushing ${pending.length} action(s)...`);

        // НЕ показываем toast "Отправляем N действий" — лишний шум.
        // Водитель видит в синем значке что flush идёт (анимация).

        let successCount = 0;
        let failCount = 0;
        const failedActions = []; // для итогового toast

        for (const action of pending) {
            try {
                await this.markStatus(action.id, 'sending');

                // Вызываем Livewire-метод
                const success = await this.callLivewireMethod(action.method, action.args);

                if (success) {
                    await this.remove(action.id);
                    successCount++;
                    console.log(`[OfflineQueue] ✓ ${action.label} (${action.method})`);

                    // НЕ показываем toast на каждое успешное действие —
                    // водитель увидит изменение в UI (статус самосвала, маршрут).
                    // Если toast'ов будет N — забьётся экран.
                } else {
                    // Livewire вернул ошибку (например метод не существует)
                    action.attempts++;
                    action.lastError = this.lastError || 'Livewire call returned false';
                    await this.markFailed(action.id, action.lastError, action.attempts);
                    failCount++;
                    failedActions.push({ label: action.label, error: action.lastError });
                    console.warn(`[OfflineQueue] ✗ ${action.label}: ${action.lastError}`);

                    // Toast на ошибку — водитель должен знать что не сработало
                    if (window.Livewire) {
                        Livewire.dispatch('notify', [{
                            type: 'error',
                            message: `✗ ${action.label}: ${action.lastError}`,
                        }]);
                    }

                    // Если 3 попытки провалились — удаляем (не зацикливаем)
                    if (action.attempts >= 3) {
                        await this.remove(action.id);
                        console.warn(`[OfflineQueue] Dropped action ${action.id} after 3 attempts`);
                    }
                }
            } catch (error) {
                action.attempts++;
                action.lastError = error.message || String(error);
                await this.markFailed(action.id, action.lastError, action.attempts);
                failCount++;
                failedActions.push({ label: action.label, error: action.lastError });
                console.error(`[OfflineQueue] ✗ ${action.label}:`, error);

                // Toast на исключение
                if (window.Livewire) {
                    Livewire.dispatch('notify', [{
                        type: 'error',
                        message: `✗ ${action.label}: ${action.lastError}`,
                    }]);
                }

                // Если 3 попытки провалились — удаляем (не зацикливаем)
                if (action.attempts >= 3) {
                    await this.remove(action.id);
                    console.warn(`[OfflineQueue] Dropped action ${action.id} after 3 attempts`);
                }
            }
        }

        this.isFlushing = false;
        this.notifyQueueChanged();

        // Итоговый toast (только если есть успешные ИЛИ если не было ошибок).
        // Если только ошибки — они уже показаны выше по одной, не дублируем.
        if (window.Livewire && successCount > 0) {
            if (failCount === 0) {
                Livewire.dispatch('notify', [{
                    type: 'success',
                    message: `Применено действий: ${successCount}`,
                }]);
            } else {
                Livewire.dispatch('notify', [{
                    type: 'warning',
                    message: `Применено: ${successCount}, не удалось: ${failCount}`,
                }]);
            }
        }

        // Триггерим обновление данных
        if (window.Livewire) {
            Livewire.dispatch('refresh-master-data');
            Livewire.dispatch('refresh-miner-data');
            Livewire.dispatch('reload-truck-data');
        }
    }

    /**
     * Вызвать Livewire-метод.
     * Возвращает Promise<boolean> — true если успешно.
     *
     * В Livewire 3:
     *   - window.Livewire.all() → array of components
     *   - window.Livewire.first() → first component
     *   - window.Livewire.find(id) → component by id
     *   - component.$wire.call(method, ...args) → Promise
     *
     * НЕ существует: window.Livewire.getByName(), window.Livewire.components
     */
    async callLivewireMethod(method, args) {
        if (!window.Livewire) {
            throw new Error('Livewire not loaded');
        }

        // Ищем Livewire-компонент DriverPanel
        let component = null;

        // Вариант 1: через all() + filter по имени
        if (typeof window.Livewire.all === 'function') {
            const components = window.Livewire.all();
            // Ищем по имени (driver-panel или DriverPanel)
            component = components.find(c =>
                c.name === 'driver-panel' ||
                c.name === 'driverPanel' ||
                c.name === 'DriverPanel'
            );
            // Если не нашли по имени — и компонент всего один, берём его
            if (!component && components.length === 1) {
                component = components[0];
            }
        }

        // Вариант 2: через first() (если на странице всего один компонент)
        if (!component && typeof window.Livewire.first === 'function') {
            component = window.Livewire.first();
        }

        // Вариант 3: fallback — найти DOM-элемент с wire:id и взять его компонент
        if (!component) {
            const wireEl = document.querySelector('[wire\\:id]');
            if (wireEl && wireEl.getAttribute('wire:id')) {
                const wireId = wireEl.getAttribute('wire:id');
                if (typeof window.Livewire.find === 'function') {
                    component = window.Livewire.find(wireId);
                }
            }
        }

        if (!component) {
            throw new Error('Livewire component not found');
        }

        // Получаем $wire (proxy с PHP-методами)
        const $wire = component.$wire || component;
        if (!$wire || typeof $wire.call !== 'function') {
            throw new Error(`$wire.call not available (component: ${component.name || component.id})`);
        }

        return new Promise((resolve) => {
            try {
                console.log(`[OfflineQueue] Calling Livewire.${method}(${args.map(a => JSON.stringify(a)).join(', ')}) on component ${component.name}`);

                const result = $wire.call(method, ...args);

                // В Livewire 3 $wire.call ВСЕГДА возвращает Promise
                if (result && typeof result.then === 'function') {
                    result
                        .then((response) => {
                            console.log(`[OfflineQueue] ✓ Livewire.${method} success`, response);
                            resolve(true);
                        })
                        .catch((error) => {
                            const errMsg = error?.message || error?.response?.statusText || String(error);
                            console.error(`[OfflineQueue] ✗ Livewire.${method} failed:`, error);
                            this.lastError = errMsg;
                            resolve(false);
                        });
                } else {
                    // Синхронный вызов (не должно быть в Livewire 3, но на всякий)
                    console.log(`[OfflineQueue] ✓ Livewire.${method} sync result:`, result);
                    resolve(true);
                }
            } catch (error) {
                const errMsg = error?.message || String(error);
                console.error(`[OfflineQueue] Error calling ${method}:`, error);
                this.lastError = errMsg;
                resolve(false);
            }
        });
    }

    /**
     * Обновить статус действия.
     */
    async markStatus(id, status) {
        await this.init();
        return new Promise((resolve, reject) => {
            const tx = this.db.transaction(this.storeName, 'readwrite');
            const store = tx.objectStore(this.storeName);
            const getReq = store.get(id);

            getReq.onsuccess = () => {
                const action = getReq.result;
                if (action) {
                    action.status = status;
                    store.put(action);
                }
                resolve();
            };

            getReq.onerror = () => reject(getReq.error);
        });
    }

    /**
     * Отметить действие как failed.
     */
    async markFailed(id, error, attempts) {
        await this.init();
        return new Promise((resolve, reject) => {
            const tx = this.db.transaction(this.storeName, 'readwrite');
            const store = tx.objectStore(this.storeName);
            const getReq = store.get(id);

            getReq.onsuccess = () => {
                const action = getReq.result;
                if (action) {
                    action.status = 'failed';
                    action.attempts = attempts;
                    action.lastError = error;
                    action.lastFailedAt = Date.now();
                    store.put(action);
                }
                resolve();
            };

            getReq.onerror = () => reject(getReq.error);
        });
    }

    /**
     * Удалить действие (после успешной отправки).
     */
    async remove(id) {
        await this.init();
        return new Promise((resolve, reject) => {
            const tx = this.db.transaction(this.storeName, 'readwrite');
            const store = tx.objectStore(this.storeName);
            const request = store.delete(id);

            request.onsuccess = () => resolve();
            request.onerror = () => reject(request.error);
        });
    }

    /**
     * Сгенерировать UUID для действия.
     */
    generateUUID() {
        if (crypto && crypto.randomUUID) {
            return crypto.randomUUID();
        }
        // Fallback для старых браузеров
        return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, (c) => {
            const r = Math.random() * 16 | 0;
            const v = c === 'x' ? r : (r & 0x3 | 0x8);
            return v.toString(16);
        });
    }

    /**
     * Уведомить UI что очередь изменилась.
     * Слушается в layout (индикатор очереди).
     */
    notifyQueueChanged() {
        window.dispatchEvent(new CustomEvent('offline-queue-changed'));
    }

    /**
     * Количество pending действий.
     */
    async count() {
        const pending = await this.getPending();
        return pending.length;
    }

    /**
     * Очистить всю очередь (для dev/тестов).
     */
    async clear() {
        await this.init();
        return new Promise((resolve, reject) => {
            const tx = this.db.transaction(this.storeName, 'readwrite');
            const store = tx.objectStore(this.storeName);
            const request = store.clear();

            request.onsuccess = () => {
                this.notifyQueueChanged();
                resolve();
            };
            request.onerror = () => reject(request.error);
        });
    }
}

// Глобальный singleton
window.OfflineQueue = new OfflineQueue();

// Авто-flush при восстановлении связи
window.addEventListener('online', () => {
    console.log('[OfflineQueue] Online — flushing queue');
    setTimeout(() => window.OfflineQueue.flush(), 1000);
});

// Инициализация при загрузке
document.addEventListener('DOMContentLoaded', async () => {
    await window.OfflineQueue.init();
    const count = await window.OfflineQueue.count();
    if (count > 0) {
        console.log(`[OfflineQueue] ${count} pending actions on load`);
        // Если уже онлайн — flush сразу
        if (navigator.onLine) {
            setTimeout(() => window.OfflineQueue.flush(), 2000);
        }
    }
});

// Делаем доступным для Livewire/Alpine
export default window.OfflineQueue;

// ==========================================
// ГЛОБАЛЬНЫЙ ПЕРЕХВАТ CLICK ПРИ OFFLINE
// ==========================================
// Если navigator.onLine === false — ВСЕ кнопки с wire:click
// автоматически идут в очередь вместо вызова Livewire.
//
// Парсим wire:click="methodName" и wire:click="methodName(arg1, arg2)"
//
// ВАЖНО: используется capture phase (третий аргумент true),
// чтобы перехватить событие ДО Livewire.
document.addEventListener('click', (e) => {
    // Если онлайн — ничего не делаем, пусть Livewire работает
    if (navigator.onLine) return;

    // Если OfflineQueue не инициализирован — пропускаем
    if (!window.OfflineQueue || !window.OfflineQueue.db) return;

    // Ищем ближайший элемент с wire:click (включая сам target)
    const button = e.target.closest('[wire\\:click]');
    if (!button) return;

    // Не перехватываем если есть wire:confirm — Livewire сам покажет confirm
    if (button.hasAttribute('wire:confirm')) {
        // Даже offline — confirm должен сработать (но потом всё равно в очередь)
        // Тут нужно думать отдельно — пока просто пропускаем
        return;
    }

    // Не перехватываем кнопки в модалках — пусть закрываются и т.д.
    // (просто не ставим в очередь действия в .modal — это обычно cancel/close)
    if (button.closest('[x-data*="open"]') && button.closest('.fixed')) {
        return;
    }

    // === Перехватываем! ===
    e.preventDefault();
    e.stopPropagation();
    e.stopImmediatePropagation();

    // Парсим wire:click
    const wireClick = button.getAttribute('wire:click') || '';
    const parsed = parseWireClick(wireClick);
    if (!parsed.method) {
        console.warn('[OfflineQueue] Cannot parse wire:click:', wireClick);
        return;
    }

    // Человекочитаемый label — берём из текста кнопки или из data-offline-label
    const label = button.getAttribute('data-offline-label') ||
                  button.textContent.trim().slice(0, 30) ||
                  parsed.method;

    // Добавляем в очередь
    window.OfflineQueue.enqueue(parsed.method, parsed.args, label).then(() => {
        // Toast для пользователя
        if (window.Livewire) {
            Livewire.dispatch('notify', [{
                type: 'info',
                message: `Действие "${label}" сохранено в очередь (offline)`,
            }]);
        }
    });
}, true); // ← capture phase

/**
 * Парсим значение wire:click.
 *   "assignRoute" → { method: 'assignRoute', args: [] }
 *   "completeTrip" → { method: 'completeTrip', args: [] }
 *   "setRock(5)" → { method: 'setRock', args: [5] }
 *   "confirmArrivalAtMiner" → { method: 'confirmArrivalAtMiner', args: [] }
 */
function parseWireClick(value) {
    const trimmed = value.trim();
    if (!trimmed) return { method: '', args: [] };

    // Метод с аргументами: methodName(arg1, arg2, ...)
    const match = trimmed.match(/^(\w+)\s*\(([^)]*)\)$/);
    if (match) {
        const method = match[1];
        const argsStr = match[2].trim();
        const args = [];

        if (argsStr) {
            // Простое разбиение по запятым — НЕ учитывает запятые внутри строк
            // (но для наших случаев этого достаточно — аргументы простые)
            argsStr.split(',').forEach(arg => {
                arg = arg.trim();
                if (!arg) return;

                // Число
                if (/^-?\d+$/.test(arg)) {
                    args.push(parseInt(arg, 10));
                } else if (/^-?\d+\.\d+$/.test(arg)) {
                    args.push(parseFloat(arg));
                }
                // Строка в одинарных или двойных кавычках
                else if (/^['"].*['"]$/.test(arg)) {
                    args.push(arg.slice(1, -1));
                }
                // Boolean
                else if (arg === 'true') {
                    args.push(true);
                } else if (arg === 'false') {
                    args.push(false);
                }
                // Что-то другое (например, PHP-переменная $something) — оставляем как строку
                else {
                    args.push(arg);
                }
            });
        }

        return { method, args };
    }

    // Метод без аргументов
    if (/^\w+$/.test(trimmed)) {
        return { method: trimmed, args: [] };
    }

    // Не удалось распарсить
    return { method: '', args: [] };
}
