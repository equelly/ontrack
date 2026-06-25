<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Driver Console</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @vite('resources/js/app.js')
    

</head>
<body>
    <h3>Driver Console</h3>
    <p>Открой консоль</p>

    <script>
        // ВАЖНО: только данные, без Echo
        window.driverId = @json($truck->driver_id);
        console.log('🧩 driverId from Blade:', window.driverId);
    </script>
</body>
</html>
