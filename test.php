<?php
require 'db.php'; // Usamos tu conexión como base para los parámetros

// Extraemos los valores de tu db.php
$dsn = "mysql:host=localhost;dbname=cursosav_CRM;charset=utf8mb4";
$user = 'cursosav_crmadmin';
$pass = 'cLENc!TRK#6q';

$max_conexiones = 300; // Puedes subirlo o bajarlo
$conexiones = [];
$errores = 0;

echo "<h2>Prueba de estrés: intentando $max_conexiones conexiones a MySQL</h2>";

for ($i = 1; $i <= $max_conexiones; $i++) {
    try {
        $conn = new PDO($dsn, $user, $pass);
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $conexiones[] = $conn;
        echo "✔️ Conexión $i exitosa<br>";
    } catch (PDOException $e) {
        echo "❌ Error en conexión $i: " . $e->getMessage() . "<br>";
        $errores++;
        break;
    }
}

echo "<hr>";
echo "<strong>Total conexiones exitosas:</strong> " . count($conexiones) . "<br>";
echo "<strong>Errores:</strong> $errores<br>";

// Liberar conexiones
foreach ($conexiones as $c) {
    $c = null;
}
?>
