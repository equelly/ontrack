/**
 * session-guard.js
 *
 * Глобальный перехватчик 419 Page Expired для Laravel + Livewire 3.
 *
 * Что делает:
 *   1. Ловит 419 от любых fetch() — оборачивает window.fetch.
 *   2. Ловит 419 от Livewire 3 через hook Livewire.hook('request').
 *   3. Ловит 419 от XMLHttpRequest (для axios / старого кода).
 *   4. Показывает модалку входа (через событие 'session-expired').
 *   5. Heartbeat: раз в N минут пингует /refresh-csrf, продлевая сессию
 *      (если пользователь активен).
 *
 * URL НЕ меняется — после успешного входа модалка сама перезагружает страницу
 * через location.reload(), и пользователь остаётся на том же адресе.
 */

const SessionGuard = {
    // Последняя активность пользователя (для heartbeat)
    lastActivity: Date.now(),
    // Минимальный интервал между запросами heartbeat (мс)
    heartbeatIntervalMs: 5 * 60 * 1000, // 5 минут
    // Таймер heartbeat
    heartbeatTimer: null,
    // Показана ли уже модалка (чтобы не показывать повторно до закрытия)
    modalShown: false,

    init() {
        if (window.__sessionGuardInit) return;
        window.__sessionGuardInit = true;

        this.wrapFetch();
        this.wrapXhr();
        this.hookLivewire();
        this.startHeartbeat();
        this.bindActivityTracking();

        console.debug('[session-guard] initialized');
    },

    // ----- 1. Перехват fetch -----------------------------------------------
    wrapFetch() {
        if (!window.fetch || window.fetch.__wrappedBySessionGuard) return;
        const originalFetch = window.fetch;
        const self = this;

        const wrapped = function (input, init) {
            return originalFetch.call(this, input, init).then(async (response) => {
                if (response.status === 419) {
                    self.handleSessionExpired();
                }
                return response;
            });
        };
        wrapped.__wrappedBySessionGuard = true;
        window.fetch = wrapped;
    },

    // ----- 2. Перехват XMLHttpRequest (для axios) -------------------------
    wrapXhr() {
        const self = this;
        const origOpen = XMLHttpRequest.prototype.open;
        const origSend = XMLHttpRequest.prototype.send;

        XMLHttpRequest.prototype.open = function (method, url, ...rest) {
            this.__sgUrl = url;
            return origOpen.call(this, method, url, ...rest);
        };

        XMLHttpRequest.prototype.send = function (body) {
            this.addEventListener('load', function () {
                if (this.status === 419) {
                    self.handleSessionExpired();
                }
            });
            return origSend.call(this, body);
        };
    },

    // ----- 3. Перехват Livewire 3 -----------------------------------------
    hookLivewire() {
        // Livewire 3 грузится асинхронно, ждём его
        const tryHook = () => {
            if (window.Livewire && typeof window.Livewire.hook === 'function') {
                window.Livewire.hook('request', ({ options, succeed, fail }) => {
                    fail(({ status, preventDefault }) => {
                        if (status === 419) {
                            preventDefault();
                            this.handleSessionExpired();
                        }
                    });
                });
                console.debug('[session-guard] Livewire hook installed');
            } else {
                // Повтор через 50ms, но не дольше 5 секунд
                if (!this.__lwTries) this.__lwTries = 0;
                if (this.__lwTries < 100) {
                    this.__lwTries++;
                    setTimeout(tryHook, 50);
                }
            }
        };
        tryHook();
    },

    // ----- 4. Показ модалки ------------------------------------------------
    handleSessionExpired() {
        if (this.modalShown) return;
        this.modalShown = true;

        // Если на странице есть Alpine-компонент модалки — он слушает это событие
        window.dispatchEvent(new CustomEvent('session-expired', {
            detail: { message: 'Сессия истекла. Пожалуйста, войдите снова.' }
        }));

        // Сбрасываем флаг, чтобы можно было показать снова после закрытия
        setTimeout(() => { this.modalShown = false; }, 2000);
    },

    // ----- 5. Heartbeat ---------------------------------------------------
    startHeartbeat() {
        const tick = async () => {
            // Только если пользователь вообще проявлял активность за последние 5 минут
            const idleMs = Date.now() - this.lastActivity;
            if (idleMs > this.heartbeatIntervalMs) {
                // Не пингуем — иначе сессия никогда не истечёт при простое.
                // Это правильно: простой = истечение сессии = модалка логина.
                return;
            }

            try {
                const resp = await fetch('/refresh-csrf', {
                    method: 'GET',
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                });
                if (resp.ok) {
                    const data = await resp.json();
                    // Если сессия истекла пока пользователь был на вкладке,
                    // но не проявлял активность — обновим CSRF-токен в meta
                    // (но только если не залогинен — иначе покажем модалку).
                    if (data.csrf_token) {
                        const meta = document.querySelector('meta[name="csrf-token"]');
                        if (meta) meta.setAttribute('content', data.csrf_token);
                    }
                    if (data.is_authenticated === false) {
                        // Пользователь разлогинен на сервере — покажем модалку
                        this.handleSessionExpired();
                    }
                }
            } catch (e) {
                // Сетевая ошибка — тихо игнорируем
            }
        };

        this.heartbeatTimer = setInterval(tick, this.heartbeatIntervalMs);
    },

    bindActivityTracking() {
        const events = ['mousedown', 'keydown', 'touchstart', 'scroll'];
        events.forEach(evt => {
            document.addEventListener(evt, () => {
                this.lastActivity = Date.now();
            }, { passive: true });
        });
    },
};

// Стартуем после загрузки DOM
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => SessionGuard.init());
} else {
    SessionGuard.init();
}

// Экспортируем в window, чтобы модалка могла остановить heartbeat перед reload.
// (default export оставляем для Vite-модулей)
window.SessionGuard = SessionGuard;

export default SessionGuard;