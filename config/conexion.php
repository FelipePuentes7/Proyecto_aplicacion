<?php
// Clase de conexión a la base de datos
class Conexion {
    // Parámetros de conexión
    private $host = 'localhost';
    private $dbname = 'proyecto';
    private $username = 'root';
    private $password = '';
    private $charset = 'utf8mb4';
    private $conexion;
    
    // Constructor
    public function __construct() {
        // Opciones para PDO
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];
        
        try {
            // Crear conexión PDO
            $this->conexion = new PDO(
                "mysql:host=$this->host;dbname=$this->dbname;charset=$this->charset",
                $this->username,
                $this->password,
                $options
            );
            
            // Configuración adicional para UTF-8 (del segundo archivo)
            $this->conexion->exec("SET CHARACTER SET utf8mb4");
            $this->conexion->exec("SET COLLATION_CONNECTION = utf8mb4_unicode_ci");
            
        } catch(PDOException $e) {
            // En producción, es mejor registrar el error que mostrarlo
            error_log("Error de conexión: " . $e->getMessage());
            die("No se pudo conectar a la base de datos. Por favor, contacte al administrador.");
        }
    }
    
    // Método para obtener la conexión
    public function getConexion() {
        return $this->conexion;
    }
    
    // Método para conectar (compatibilidad con código antiguo)
    public function conectar() {
        return $this->conexion;
    }
}

// Variables globales para compatibilidad con código existente
$host = 'localhost';
$dbname = 'proyecto';
$username = 'root';
$password = '';
$charset = 'utf8mb4';

// Opciones para PDO
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    // Crear conexión PDO global
    $pdo = new PDO(
        "mysql:host=$host;dbname=$dbname;charset=$charset",
        $username,
        $password,
        $options
    );
    
    // Configuración adicional para UTF-8 (del segundo archivo)
    $pdo->exec("SET CHARACTER SET utf8mb4");
    $pdo->exec("SET COLLATION_CONNECTION = utf8mb4_unicode_ci");
    
    // Crear la variable $conexion para compatibilidad con el segundo archivo
    $conexion = $pdo;
    
} catch(PDOException $e) {
<<<<<<< HEAD
    die("Error de conexión: " . $e->getMessage());
}
=======
    // En producción, es mejor registrar el error que mostrarlo
    error_log("Error de conexión: " . $e->getMessage());
    die("No se pudo conectar a la base de datos. Por favor, contacte al administrador.");
}

// Función para sanitizar entradas
function sanitize($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

// Función para formatear fecha (con verificación para evitar redeclaración)
if (!function_exists('formatearFecha')) {
    function formatearFecha($fecha) {
        $timestamp = strtotime($fecha);
        $dias_semana = ['Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'];
        $meses = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
        
        $dia_semana = $dias_semana[date('w', $timestamp)];
        $dia = date('j', $timestamp);
        $mes = $meses[date('n', $timestamp) - 1];
        
        return "$dia_semana $dia de $mes";
    }
}
?>
>>>>>>> origin/Master
