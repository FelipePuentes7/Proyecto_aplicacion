<?php
$host = '127.0.0.1'; // Asegurar que es 127.0.0.1
$dbname = 'grados_fet';
$username = 'root';
$password = '';

try {
    $conexion = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $conexion->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "✅ Conexión a la base de datos establecida correctamente.";
} catch (PDOException $e) {
    die("❌ Error de conexión en conexion.php: " . $e->getMessage());
}

?>
