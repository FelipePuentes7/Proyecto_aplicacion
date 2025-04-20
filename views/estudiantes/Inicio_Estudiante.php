<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();

require_once realpath(__DIR__ . '/../../config/conexion.php');

// Verificar si el usuario está logueado
if (!isset($_SESSION['usuario']) || !isset($_SESSION['usuario']['id'])) {
    header('Location: /views/general/login.php');
    exit();
}

// Obtener los datos del usuario desde la base de datos
try {
    $userId = $_SESSION['usuario']['id'];
    $stmt = $conexion->prepare("SELECT * FROM usuarios WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        die("Error: No se encontró información del usuario.");
    }
    
} catch (PDOException $e) {
    die("Error de base de datos: " . $e->getMessage());
}
?>




<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="/assets/css/Inicio_Estu.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <title>MissionFet - Inicio Estudiante</title>
</head>

<body>

<nav class="navbar">
        <!-- Espacio para el logo -->
        <div class="logo">
            <img src="/assets/images/logofet.png" alt="Logo FET" style="max-height: 50px;">
        </div>

        <!-- Links de navegación -->
        <div class="nav-links">
            <a href="/views/estudiantes/Inicio_Estudiante.php">Inicio</a>
            <a href="#">Material de Apoyo</a>
            <a href="#">Tutorías</a>
        </div>

        <!-- Acciones del usuario -->
        <div class="user-actions">
            <div class="notifications">
            <i class="fa-solid fa-bell"></i> <!-- Ícono de notificaciones -->
            </div>
            <div class="dropdown">
                <i class="fa-solid fa-user"></i> <!-- Ícono de perfil -->
                <div class="dropdown-content">
                    <a href="/views/estudiantes/Perfil_estudiante.php">Editar Perfil</a>
                    <a href="/views/general/login.php" class="logout-btn" onclick="cerrarSesion(event)">
                <i class="fa-solid fa-sign-out-alt"></i> Cerrar Sesión
                    </a>
                </div>
            </div>
        </div>
</nav>

<section class="Bienvenido">

    <div class="mensaje">
        <h3><strong>¡BIENVENIDO, <?php echo htmlspecialchars($user['nombre']); ?>!</strong></h3>

            <p>Organiza y Gestiona tu proyecto de manera eficiente. <br>Aqui Encontraras todo lo que necesitas para avanzar.</p>
    </div>

    <img src="/assets/images/Imagen_principal.png" alt="Imagen_inicio">

</section>

<div class="calendario">
    


</div>







<footer class="footer">

        <!-- Sección de información -->
        <div class="info-section">

            <!-- Links -->
            <div class="links">

                <p class="title_footer"><strong>Fundación Escuela Tecnológica de Neiva</strong></p>

                <a href="#">Inicio</a>
                <a href="#">Subir Avances</a>
                <a href="#">Historial de Avances</a>
                <a href="#">Material de Apoyo</a>
            </div>

            <!-- Información de contacto -->
            <div class="contact">
                <p>Email: soporte@universidad.edu</p>
                <p>Dirección: Calle 123, Bogotá</p>
                <p>Teléfono: +57 123 456 7890</p>
            </div>

            <!-- Espacio para la imagen -->
            <div class="image">
                <img src="/assets/images/image.png" alt="Imagen del footer" style="max-width: 50%;">
            </div>
        </div>

        <!-- Sección de redes sociales -->
        <div class="social-section">
            <!-- Redes sociales -->
            <div class="social-links">
                <a href="https://es-es.facebook.com/" target="_blank"><i class="fa-brands fa-facebook"></i></a>
                <a href="https://twitter.com/?lang=es" target="_blank"><i class="fa-brands fa-x-twitter"></i></a>
                <a href="https://www.instagram.com/" target="_blank"><i class="fa-brands fa-square-instagram"></i></a>
            </div>

            <!-- Logo del medio -->
            <div class="logo">
            <img src="/assets/images/logo_footer.png" alt="Footer FET" style="max-height: 50px;">
            </div>
        </div>
    </footer>




    <script src="https://kit.fontawesome.com/4fac1b523f.js" crossorigin="anonymous"></script>

</body>
</html>