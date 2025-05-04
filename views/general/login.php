<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once __DIR__ . '/../../config/conexion.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
    $password = $_POST['password'];

    try {
        // Buscar usuario en la base de datos - la estructura de la tabla usuarios se mantiene similar
        $stmt = $conexion->prepare("SELECT * FROM usuarios WHERE email = ?");
        $stmt->execute([$email]);
        $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($usuario && password_verify($password, $usuario['password'])) {
            // Almacenar información básica del usuario en la sesión
            $_SESSION['usuario'] = [
                'id' => $usuario['id'],
                'nombre' => $usuario['nombre'],
                'email' => $usuario['email'],
                'rol' => $usuario['rol']
            ];

            // Redirección según rol
            switch ($usuario['rol']) {
                case 'admin':
                    header('Location: /views/administrador/inicio.php');
                    exit();
                case 'tutor':
                    header('Location: /views/profesores/Pasantias.php');
                    exit();
                case 'estudiante':
                        // Redirección específica para estudiantes según su opción de grado
                    switch ($usuario['opcion_grado']) {
                        case 'pasantia':
                        header('Location: /views/estudiantes/Pasantias.php'); // Replace with the actual path for pasantia students
                        exit();
                        case 'proyecto':
                         header('Location: /views/estudiantes/Inicio_Proyecto.php'); // Replace with the actual path for proyecto students
                        exit();
                        case 'seminario':
                        header('Location: /views/estudiantes/Inicio_Seminario.php'); // Replace with the actual path for seminario students
                        exit();
                        default:
                        // Default redirection for students if opcion_grado is not recognized
                        header('Location: /views/estudiantes/Inicio_Estudiante.php'); // Keep the original default
                        exit();
                        }
                    break; // Important to break the outer switch after handling the student case
                        default:
                        header('Location: /');
                        exit();
            }
        } else {
            $error = "Credenciales incorrectas";
        }
    } catch (PDOException $e) {
        $error = "Error al conectar con la base de datos: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inicio de Sesión FET</title>
    <link rel="stylesheet" href="/assets/css/login.css">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
</head>
<body>
    <div class="container">
        <div class="form-container">
            <div class="logo">
                <img src="/assets/images/logofet.png" alt="FET Logo">
            </div>
            
            <form method="POST" action="">
                <?php if ($error): ?>
                    <div class="mensaje-error"><?= $error ?></div>
                <?php endif; ?>
                
                <div class="form-group">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email" required>
                </div>
                
                <div class="form-group">
                    <label for="password">Contraseña</label>
                    <input type="password" id="password" name="password" required>
                </div>
                
                <div class="forgot-password">
                    <a href="#" class="forgot-password-link">¿Olvidaste tu contraseña?</a>
                </div>

                <div class="forgot-password">
                    <a href="/views/general/registro.php" class="forgot-password-link">Registrarse</a>
                </div>
                
                <button type="submit" class="register-btn">Iniciar Sesión</button>
            </form>
        </div>
        
        <div class="promo-image">
            <img src="/assets/images/image.png" alt="Somos Generación FET">
        </div>
    </div>
</body>
</html>