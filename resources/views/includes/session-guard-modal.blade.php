{{--
    Модалка входа в систему при истечении сессии (419 Page Expired).

    ВАЖНО: Все Alpine-события пишем через x-on: вместо @,
    чтобы Blade не пытался парсить @click / @session-expired как свои директивы.
--}}
<div
    x-data="sessionGuardModal()"
    x-show="open"
    x-cloak
    x-transition.opacity
    style="display: none;"
    class="fixed inset-0 z-[99999] flex items-center justify-center"
    x-on:keydown.escape.window="open = false"
    x-on:session-expired.window="onSessionExpired()"
>
    {{-- Затемнение --}}
    <div
        x-show="open"
        x-transition.opacity
        class="absolute inset-0 bg-black/60 backdrop-blur-sm"
        x-on:click="open = false"
    ></div>

    {{-- Модалка --}}
    <div
        x-show="open"
        x-transition
        class="relative w-full max-w-md mx-4 bg-white rounded-2xl shadow-2xl overflow-hidden"
    >
        {{-- Шапка --}}
        <div class="px-6 py-4 bg-gradient-to-r from-amber-500 to-orange-500 text-white">
            <div class="flex items-center gap-3">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                <div>
                    <h3 class="text-lg font-semibold">Сессия истекла</h3>
                    <p class="text-sm text-white/90">Введите данные для продолжения работы</p>
                </div>
            </div>
        </div>

        {{-- Тело --}}
        <form x-on:submit.prevent="submit" class="px-6 py-5 space-y-4">
            {{-- Текущий URL (подсказка, что после входа вернёмся сюда) --}}
            <div class="text-xs text-gray-500 bg-gray-50 px-3 py-2 rounded-md border border-gray-100">
                Вы останетесь на текущей странице: <span class="font-mono break-all" x-text="currentPath"></span>
            </div>

            {{-- Email --}}
            <div>
                <label for="sg-email" class="block text-sm font-medium text-gray-700 mb-1">
                    Email
                </label>
                <input
                    id="sg-email"
                    type="email"
                    x-model="form.email"
                    autocomplete="email"
                    required
                    autofocus
                    class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-amber-500 focus:border-amber-500"
                    :class="{ 'border-red-400': errors.email }"
                />
                <p x-show="errors.email" x-text="errors.email" class="mt-1 text-xs text-red-600"></p>
            </div>

            {{-- Пароль --}}
            <div>
                <label for="sg-password" class="block text-sm font-medium text-gray-700 mb-1">
                    Пароль
                </label>
                <input
                    id="sg-password"
                    type="password"
                    x-model="form.password"
                    autocomplete="current-password"
                    required
                    class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-amber-500 focus:border-amber-500"
                    :class="{ 'border-red-400': errors.password }"
                />
                <p x-show="errors.password" x-text="errors.password" class="mt-1 text-xs text-red-600"></p>
            </div>

            {{-- Запомнить --}}
            <label class="flex items-center gap-2 text-sm text-gray-600">
                <input
                    type="checkbox"
                    x-model="form.remember"
                    class="w-4 h-4 text-amber-600 border-gray-300 rounded focus:ring-amber-500"
                />
                Запомнить меня
            </label>

            {{-- Кнопки --}}
            <div class="flex gap-2 pt-2">
                <button
                    type="submit"
                    :disabled="loading"
                    class="flex-1 inline-flex justify-center items-center gap-2 px-4 py-2 bg-amber-500 hover:bg-amber-600 disabled:bg-amber-300 disabled:cursor-not-allowed text-white rounded-md font-medium transition"
                >
                    <svg x-show="loading" class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor"
                              d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                    </svg>
                    <span x-text="loading ? 'Вход...' : 'Войти'"></span>
                </button>
                <button
                    type="button"
                    x-on:click="open = false"
                    class="px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-md font-medium transition"
                >
                    Отмена
                </button>
            </div>

            {{-- Общая ошибка --}}
            <p
                x-show="errorMessage"
                x-text="errorMessage"
                class="text-sm text-red-600 bg-red-50 border border-red-200 px-3 py-2 rounded-md"
            ></p>
        </form>
    </div>
</div>

<script>
    /**
     * Alpine-компонент для модалки логина при истечении сессии.
     *
     * Ключевая логика:
     *   1. При показе модалки — получаем СВЕЖИЙ CSRF-токен через GET /refresh-csrf.
     *      Это нужно, потому что токен в meta уже протух вместе с сессией.
     *   2. Используем свежий токен для POST /ajax-login.
     *      /ajax-login исключён из VerifyCsrfToken — но это дополнительная страховка.
     *   3. После успешного входа — стопим heartbeat в SessionGuard, чтобы он
     *      не запустил 419 снова во время reload.
     *   4. Перезагружаем страницу — URL остаётся тот же.
     */
    function sessionGuardModal() {
        return {
            open: false,
            loading: false,
            currentPath: window.location.pathname,
            form: {
                email: '',
                password: '',
                remember: true,
            },
            errors: {},
            errorMessage: '',

            /**
             * Обработчик события session-expired — вызывается когда session-guard.js
             * поймал 419. Сначала обновим CSRF-токен, потом откроем модалку.
             */
            async onSessionExpired() {
                await this.refreshCsrfToken();
                this.open = true;

                // Фокус на email после показа
                this.$nextTick(() => {
                    document.getElementById('sg-email')?.focus();
                });
            },

            /**
             * Получить свежий CSRF-токен через GET /refresh-csrf.
             * GET не требует CSRF, поэтому это сработает даже с истёкшей сессией.
             */
            async refreshCsrfToken() {
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
                        if (data.csrf_token) {
                            const meta = document.querySelector('meta[name="csrf-token"]');
                            if (meta) meta.setAttribute('content', data.csrf_token);

                            // Также обновим в axios, если он есть
                            if (window.axios) {
                                window.axios.defaults.headers.common['X-CSRF-TOKEN'] = data.csrf_token;
                            }
                        }
                    }
                } catch (e) {
                    console.error('[session-guard] refresh-csrf failed:', e);
                }
            },

            async submit() {
                this.loading = true;
                this.errors = {};
                this.errorMessage = '';

                try {
                    // Берём текущий токен из meta (он был обновлён в onSessionExpired)
                    const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';

                    const response = await fetch('/ajax-login', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': csrf,
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                        body: JSON.stringify(this.form),
                    });

                    const data = await response.json();

                    if (response.ok && data.success) {
                        // Обновляем CSRF-токен в meta (Laravel его перегенерирует после login)
                        if (data.csrf_token) {
                            const meta = document.querySelector('meta[name="csrf-token"]');
                            if (meta) meta.setAttribute('content', data.csrf_token);
                        }

                        // КРИТИЧНО: стопим heartbeat в SessionGuard, иначе он
                        // может снова триггерить 419 во время reload
                        if (window.SessionGuard && window.SessionGuard.heartbeatTimer) {
                            clearInterval(window.SessionGuard.heartbeatTimer);
                        }

                        // Закрываем модалку
                        this.open = false;

                        // Перезагружаем страницу — URL остаётся тот же.
                        // После reload браузер получит свежий HTML с новым CSRF-токеном
                        // и Livewire-данными, так что 419 не повторится.
                        window.location.reload();
                    } else if (response.status === 422 && data.errors) {
                        this.errors = data.errors;
                    } else if (response.status === 419) {
                        // Даже после refresh CSRF мог снова протухнуть — обновим ещё раз
                        await this.refreshCsrfToken();
                        this.errorMessage = 'Сессия истекла. Попробуйте ещё раз.';
                    } else {
                        this.errorMessage = data.message || 'Ошибка входа. Проверьте данные.';
                    }
                } catch (e) {
                    this.errorMessage = 'Сетевая ошибка. Проверьте соединение.';
                    console.error('[session-guard] login error:', e);
                } finally {
                    this.loading = false;
                }
            },
        };
    }
</script>