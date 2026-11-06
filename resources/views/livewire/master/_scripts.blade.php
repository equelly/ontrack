<!-- Подключаем стили и скрипты Leaflet (если еще не подключены) -->
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

<script>
    // =========================================
    // Echo-подписки на публичные каналы мастера
    // =========================================
    // Дублируем нативные Livewire Echo listeners (#[On('echo:master,...')])
    // для надёжности — нативные listeners иногда пропускают события,
    // особенно при первом рендере. JS-подписка гарантированно работает,
    // потому что Echo инициализируется в bootstrap.js до Livewire.

    document.addEventListener('livewire:init', () => {
        if (!window.Echo) {
            console.warn('[Echo] window.Echo не инициализирован — подписки на master-каналы не работают');
            return;
        }

        // Канал мастера — для уведомлений о зонах и обваловке
        window.Echo.channel('master')
            .listen('.zone.fill.warning', (data) => {
                console.log('[Echo master] .zone.fill.warning', data);

                // Toast напрямую — нативный listener может не сработать
                const severity = data?.severity ?? 'warning';
                const hours    = data?.hours_to_overflow ?? null;
                const isCritical = severity === 'critical' || (hours !== null && hours < 3);
                Livewire.dispatch('notify', [{
                    type: isCritical ? 'error' : 'warning',
                    message: data?.message ?? `Зона «${data?.zone_name ?? '—'}» заполнена на ${data?.fill_percent ?? 0}%.`,
                }]);

                // Триггерим обновление данных через стандартный механизм Livewire
                // (можно диспатчить событие, которое слушает MasterPanel)
                Livewire.dispatch('refresh-master-data');
            })
            .listen('.zone.needs.berm', (data) => {
                console.log('[Echo master] .zone.needs.berm', data);
                // Toast для критического случая — нативный listener может не сработать
                Livewire.dispatch('notify', [{
                    type: 'error',
                    message: data?.message ?? `Зона «${data?.zone_name ?? '—'}» заполнена. Требуется обваловка!`,
                }]);
                Livewire.dispatch('refresh-master-data');
            })
            .listen('.berm.progress', (data) => {
                console.log('[Echo master] .berm.progress', data);
                // Toast для прогресса обваловки
                const status = data?.status ?? 'in_progress';
                const type = status === 'completed' ? 'success'
                           : status === 'cancelled' ? 'info'
                           : 'info';
                if (data?.message) {
                    Livewire.dispatch('notify', [{ type, message: data.message }]);
                }
                Livewire.dispatch('refresh-master-data');
            });

        console.log('[Echo] Подписка на канал master активна');
    });
</script>

</div>