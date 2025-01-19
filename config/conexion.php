<?php
// Configuración de la base de datos
$host = 'localhost';
$dbname = 'fet_db';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    // Si la conexión es exitosa, no mostraremos nada
} catch(PDOException $e) {
    // En caso de error, mostrar mensaje
    die("Error de conexión: " . $e->getMessage());
}
?>
