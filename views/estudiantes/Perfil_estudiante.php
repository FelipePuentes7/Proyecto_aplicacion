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
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="/assets/css/Perfil_Estu.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <title>MissionFet - Perfil Estudiante</title>
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

                    <a href="/views/general/login.php" class="logout-btn" onclick="cerrarSesion(event)">
                <i class="fa-solid fa-sign-out-alt"></i> Cerrar Sesión
                    </a>
                </div>
            </div>
        </div>
</nav>

    <div class="profile-container">

            <div class="Titulo_perfil">
                <h2>¡Bienvenido, <?php echo htmlspecialchars($user['nombre']); ?>!</h2>
                <img src="/assets/images/IMAGEN_USUARIO.png" alt="Imagen_usuario" width="300" height="300">
            </div>

            <div class="profile_info">
                <p><strong><i class="fa-solid fa-envelope"></i> Email:</strong> <?php echo htmlspecialchars($user['email']); ?></p>
                <p><strong><i class="fa-solid fa-phone"></i> Teléfono:</strong> <?php echo htmlspecialchars($user['telefono'] ?? 'No registrado'); ?></p>
                <p><strong><i class="fa-solid fa-location-dot"></i> Rol:</strong> <?php echo ucfirst($user['rol']); ?></p>
                <p><strong><i class="fa-solid fa-fingerprint"></i> Documento:</strong> <?php echo htmlspecialchars($user['documento']); ?></p>
                <p><strong><i class="fa-solid fa-id-card"></i> Código Institucional:</strong> <?php echo htmlspecialchars($user['codigo_estudiante'] ?? 'No registrado'); ?></p>
                <p><strong><i class="fa-solid fa-graduation-cap"></i> Opción De Grado:</strong> <?php echo ucfirst($user['opcion_grado'] ?? 'No registrada'); ?></p>
                
                <?php if (!empty($user['nombre_proyecto'])): ?>
                <p><strong><i class="fa-solid fa-file-code"></i> Proyecto:</strong> <?php echo htmlspecialchars($user['nombre_proyecto']); ?></p>
                <?php endif; ?>
                
                <?php if (!empty($user['nombre_empresa'])): ?>
                <p><strong><i class="fa-solid fa-building"></i> Empresa:</strong> <?php echo htmlspecialchars($user['nombre_empresa']); ?></p>
                <?php endif; ?>
                
                <?php if (!empty($user['ciclo'])): ?>
                <p><strong><i class="fa-solid fa-graduation-cap"></i> Ciclo:</strong> <?php echo ucfirst($user['ciclo']); ?></p>
                <?php endif; ?>
            </div>

    </div>

    
    <div class="projects">
        <h3>Tus Proyectos:</h3>
        
        <?php if (empty($proyectosConAvances)): ?>
            <div class="no-projects">
                <p>No tienes proyectos registrados actualmente.</p>
                <a href="#" class="btn-action"><i class="fa-solid fa-plus"></i> Solicitar nuevo proyecto</a>
            </div>
        <?php else: ?>
            <?php foreach ($proyectosConAvances as $proyecto): ?>
                <div class="project-item">
                    <p><strong>Proyecto:</strong> <?php echo htmlspecialchars($proyecto['titulo']); ?></p>
                    <p><strong>Tipo:</strong> <?php echo ucfirst($proyecto['tipo']); ?></p>
                    <p><strong>Estado:</strong> <?php echo ucfirst($proyecto['estado']); ?></p>
                    
                    <?php if (!empty($proyecto['ultimo_avance'])): ?>
                        <p><strong>Avance:</strong> <?php echo $proyecto['ultimo_avance']['porcentaje_avance']; ?>%</p>
                        <p><strong>Último avance:</strong> <?php echo htmlspecialchars($proyecto['ultimo_avance']['titulo']); ?></p>
                        <p><strong>Fecha:</strong> <?php echo date('d/m/Y', strtotime($proyecto['ultimo_avance']['fecha_registro'])); ?></p>
                    <?php else: ?>
                        <p><strong>Avance:</strong> 0%</p>
                        <p><strong>Último avance:</strong> No hay avances registrados</p>
                    <?php endif; ?>
                    
                    <p><strong>Tutor Asignado:</strong> <?php echo htmlspecialchars($proyecto['tutor_nombre'] ?? 'No asignado'); ?></p>
                    
                    <div class="project-actions">
                        <a href="/views/estudiantes/subir_avance.php?proyecto_id=<?php echo $proyecto['id']; ?>" class="btn-action">
                            <i class="fa-solid fa-upload"></i> Subir Avance
                        </a>
                        <a href="/views/estudiantes/historial_avances.php?proyecto_id=<?php echo $proyecto['id']; ?>" class="btn-action">
                            <i class="fa-solid fa-history"></i> Ver Historial
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
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