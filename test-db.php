<?php
require __DIR__.'/vendor/autoload.php';

echo "<h2>DB Config:</h2>";
echo "Username: " . env('DB_USERNAME') . "<br>";
echo "Host: " . env('DB_HOST') . "<br>"; 
echo "Port: " . env('DB_PORT') . "<br>";
echo "Database: " . env('DB_DATABASE') . "<br>";

try {
    $pdo = new PDO("mysql:host=".env('DB_HOST').";port=".env('DB_PORT').";dbname=".env('DB_DATABASE'), 
                   env('DB_USERNAME'), env('DB_PASSWORD'));
    echo "<h2 style='color:green'>✅ ПОДКЛЮЧЕНИЕ УСПЕШНО!</h2>";
    
    $stmt = $pdo->query("SHOW TABLES");
    echo "<h3>Таблицы:</h3><pre>";
    while($row = $stmt->fetch()) {
        echo $row[0] . "\n";
    }
    echo "</pre>";
    
} catch(PDOException $e) {
    echo "<h2 style='color:red'>❌ ОШИБКА: " . $e->getMessage() . "</h2>";
}
?>
