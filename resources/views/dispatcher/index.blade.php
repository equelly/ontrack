<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Dispatcher Console</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @vite('resources/js/app.js')
</head>
<body>
    <h2>🧭 Dispatcher Console</h2>

    <table border="1" cellpadding="5" cellspacing="0">
        <thead>
            <tr>
                <th>Truck ID</th>
                <th>Number</th>
                <th>Status</th>
                <th>Driver ID</th>
                <th>Current Order</th>
            </tr>
        </thead>
        <tbody>
            @foreach($trucks as $truck)
                <tr id="truck-{{ $truck->id }}">
                    <td>{{ $truck->id }}</td>
                    <td>{{ $truck->number }}</td>
                    <td class="status">{{$truck->status }}</td>
                    <td>{{ $truck->driver_id }}</td>
                    <td class="current-order">
                        {{ optional($truck->currentOrder)->id ?? '-' }}
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
