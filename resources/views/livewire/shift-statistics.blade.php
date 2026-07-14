<div class="card industrial-card mt-4">
    <div class="card-header industrial-header">
        <i class="fas fa-chart-bar mr-1"></i> Статистика смены
    </div>
    <div class="card-body d-flex justify-content-around text-center p-3">
        <div>
            <div class="industrial-label">Рейсов за смену</div>
            <h3 class="mb-0 font-weight-bold">{{ $tripsCount }}</h3>
        </div>
        <div>
            <div class="industrial-label">Общий объем (м³)</div>
            <h3 class="mb-0 font-weight-bold">{{ $totalVolume }}</h3>
        </div>
        <div>
            <div class="industrial-label">Средняя скорость (км/ч)</div>
            <h3 class="mb-0 font-weight-bold">{{ $avgSpeed }}</h3>
        </div>
    </div>
</div>