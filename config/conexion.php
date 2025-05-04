<?php
// database.php - Archivo de conexión a la base de datos

// Configuración de la base de datos
$host = 'localhost';
$dbname = 'proyecto';
$username = 'root';     // Cambiar por tu usuario de MySQL
$password = '';  // Cambiar por tu contraseña de MySQL

try {
    // Crear conexión PDO
    $conexion = new PDO(
        "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
        $username,
        $password,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]
    );
    
    // Configuración adicional para UTF-8
    $conexion->exec("SET CHARACTER SET utf8mb4");
    $conexion->exec("SET COLLATION_CONNECTION = utf8mb4_unicode_ci");
    // echo "¡Conexión exitosa!"; // (Comentar en producción)

} catch(PDOException $e) {
    die("Error de conexión: " . $e->getMessage());
}
