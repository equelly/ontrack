    <script>
        function updateTripTimers() {
            document.querySelectorAll('.trip-timer').forEach(el => {
                const startedAtStr = el.dataset.startedAt;
                if (!startedAtStr) return;
                const startedAt = new Date(startedAtStr);
                const now = new Date();
                const totalSeconds = Math.floor((now - startedAt) / 1000);
                if (totalSeconds < 0) return;
                const completedPauseSeconds = parseInt(el.dataset.pauseSeconds) || 0;
                let activePauseSeconds = 0;
                const activePauseStartStr = el.dataset.activePauseStart;
                if (activePauseStartStr) {
                    const activePauseStart = new Date(activePauseStartStr);
                    activePauseSeconds = Math.floor((now - activePauseStart) / 1000);
                }
                const pauseSeconds = completedPauseSeconds + activePauseSeconds;
                const netSeconds = Math.max(0, totalSeconds - pauseSeconds);
                const hours = Math.floor(netSeconds / 3600);
                const minutes = Math.floor((netSeconds % 3600) / 60);
                const seconds = netSeconds % 60;
                let timeStr;
                if (hours > 0) { timeStr = `${hours}:${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`; } 
                else { timeStr = `${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`; }
                el.textContent = timeStr;
            });
        }
        setInterval(updateTripTimers, 1000);
        document.addEventListener('livewire:update', updateTripTimers);

        document.addEventListener('livewire:init', () => {
            updateTripTimers();

            
            if (window.Echo) {
                window.Echo.channel('dispatcher')
                    .listen('.truck-updated', (data) => { Livewire.dispatch('refresh-dispatcher-data'); })
                    .listen('.miner-productivity-updated', (eventData) => { Livewire.dispatch('miner-productivity-updated', { data: eventData }); });
            }
        });
    </script>
