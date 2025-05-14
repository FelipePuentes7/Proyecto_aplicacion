<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();

require_once realpath(__DIR__ . '/../../config/conexion.php');

// Verificar si el usuario está logueado y es tutor
if (!isset($_SESSION['usuario']) || !isset($_SESSION['usuario']['id']) || $_SESSION['usuario']['rol'] !== 'tutor') {
    header('Location: /views/general/login.php');
    exit();
}

$tutor_id = $_SESSION['usuario']['id'];
$nombre_tutor = $_SESSION['usuario']['nombre'];

// Verificar si el tutor tiene pasantías asignadas
$query_pasantias = "SELECT COUNT(*) as total_pasantias 
                    FROM pasantias 
                    WHERE tutor_id = ?";
$stmt_pasantias = $conexion->prepare($query_pasantias);
$stmt_pasantias->execute([$tutor_id]);
$resultado_pasantias = $stmt_pasantias->fetch(PDO::FETCH_ASSOC);
$tiene_pasantias = $resultado_pasantias['total_pasantias'] > 0;
$total_pasantias = $resultado_pasantias['total_pasantias'];
$stmt_pasantias->closeCursor();

// Verificar si el tutor tiene proyectos asignados
$query_proyectos = "SELECT COUNT(*) as total_proyectos 
                    FROM proyectos 
                    WHERE tutor_id = ?";
$stmt_proyectos = $conexion->prepare($query_proyectos);
$stmt_proyectos->execute([$tutor_id]);
$resultado_proyectos = $stmt_proyectos->fetch(PDO::FETCH_ASSOC);
$tiene_proyectos = $resultado_proyectos['total_proyectos'] > 0;
$total_proyectos = $resultado_proyectos['total_proyectos'];
$stmt_proyectos->closeCursor();

// Obtener pasantías pendientes de revisión - Usando entregas_pasantia en lugar de avances_pasantia
$query_pasantias_pendientes = "SELECT COUNT(*) as pendientes 
                              FROM entregas_pasantia a
                              INNER JOIN pasantias p ON a.pasantia_id = p.id
                              WHERE p.tutor_id = ? AND a.estado = 'pendiente'";
$stmt_pasantias_pendientes = $conexion->prepare($query_pasantias_pendientes);
$stmt_pasantias_pendientes->execute([$tutor_id]);
$resultado_pasantias_pendientes = $stmt_pasantias_pendientes->fetch(PDO::FETCH_ASSOC);
$pasantias_pendientes = $resultado_pasantias_pendientes['pendientes'];
$stmt_pasantias_pendientes->closeCursor();

// Obtener proyectos pendientes de revisión
$query_proyectos_pendientes = "SELECT COUNT(*) as pendientes 
                              FROM avances_proyecto a
                              INNER JOIN proyectos p ON a.proyecto_id = p.id
                              WHERE p.tutor_id = ? AND a.estado = 'pendiente'";
$stmt_proyectos_pendientes = $conexion->prepare($query_proyectos_pendientes);
$stmt_proyectos_pendientes->execute([$tutor_id]);
$resultado_proyectos_pendientes = $stmt_proyectos_pendientes->fetch(PDO::FETCH_ASSOC);
$proyectos_pendientes = $resultado_proyectos_pendientes['pendientes'];
$stmt_proyectos_pendientes->closeCursor();

// Obtener mensajes no leídos de pasantías - Usando mensajes_chat
// Nota: No hay campo 'leido' en la tabla mensajes_chat según el dump, así que contamos todos los mensajes
$query_mensajes_pasantias = "SELECT COUNT(*) as no_leidos 
                            FROM mensajes_chat m
                            INNER JOIN pasantias p ON m.pasantia_id = p.id
                            WHERE p.tutor_id = ? AND m.receptor_id = ?";
$stmt_mensajes_pasantias = $conexion->prepare($query_mensajes_pasantias);
$stmt_mensajes_pasantias->execute([$tutor_id, $tutor_id]);
$resultado_mensajes_pasantias = $stmt_mensajes_pasantias->fetch(PDO::FETCH_ASSOC);
$mensajes_pasantias = $resultado_mensajes_pasantias['no_leidos'];
$stmt_mensajes_pasantias->closeCursor();

// No hay tabla mensajes_proyecto en la base de datos, así que establecemos mensajes_proyectos a 0
$mensajes_proyectos = 0;

// Total de notificaciones
$total_notificaciones_pasantias = $pasantias_pendientes + $mensajes_pasantias;
$total_notificaciones_proyectos = $proyectos_pendientes; // Solo contamos los avances pendientes, no hay mensajes
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Portal del Tutor - Bienvenida</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="/assets/css/tutor.css">
</head>
<body>
    <div class="wrapper">
        <header class="top-header">
            <div class="header-logo">
                <i class="fas fa-university"></i>
                <h1>Portal Académico</h1>
            </div>
            <div class="user-info">
                <div class="user-avatar">
                    <i class="fas fa-user-tie"></i>
                </div>
                <div class="user-details">
                    <p class="user-name"><?php echo htmlspecialchars($nombre_tutor); ?></p>
                    <p class="user-role">Tutor Académico</p>
                </div>
            </div>
        </header>

        <main class="main-content">
            <div class="welcome-container">
                <div class="welcome-header">
                    <h2>Bienvenido al Portal del Tutor</h2>
                    <p>Seleccione una opción para continuar</p>
                </div>

                <div class="options-container">
                    <div class="option-card <?php echo !$tiene_pasantias ? 'disabled' : ''; ?>">
                        <div class="option-icon">
                            <i class="fas fa-briefcase"></i>
                            <?php if ($total_notificaciones_pasantias > 0): ?>
                            <span class="notification-badge"><?php echo $total_notificaciones_pasantias; ?></span>
                            <?php endif; ?>
                        </div>
                        <h3>Gestionar Pasantías</h3>
                        <p>Supervise y evalúe las pasantías asignadas</p>
                        <div class="option-stats">
                            <div class="stat-item">
                                <span class="stat-value"><?php echo $total_pasantias; ?></span>
                                <span class="stat-label">Pasantías</span>
                            </div>
                            <div class="stat-item">
                                <span class="stat-value"><?php echo $pasantias_pendientes; ?></span>
                                <span class="stat-label">Pendientes</span>
                            </div>
                        </div>
                        <a href="/views/profesores/Pasantias.php" class="option-button" <?php echo !$tiene_pasantias ? 'disabled' : ''; ?>>
                            <i class="fas fa-arrow-right"></i> Acceder
                        </a>
                        <?php if (!$tiene_pasantias): ?>
                        <div class="option-disabled-message">
                            <i class="fas fa-lock"></i> No tiene pasantías asignadas
                        </div>
                        <?php endif; ?>
                    </div>

                    <div class="option-card <?php echo !$tiene_proyectos ? 'disabled' : ''; ?>">
                        <div class="option-icon">
                            <i class="fas fa-project-diagram"></i>
                            <?php if ($total_notificaciones_proyectos > 0): ?>
                            <span class="notification-badge"><?php echo $total_notificaciones_proyectos; ?></span>
                            <?php endif; ?>
                        </div>
                        <h3>Gestionar Proyectos</h3>
                        <p>Supervise y evalúe los proyectos asignados</p>
                        <div class="option-stats">
                            <div class="stat-item">
                                <span class="stat-value"><?php echo $total_proyectos; ?></span>
                                <span class="stat-label">Proyectos</span>
                            </div>
                            <div class="stat-item">
                                <span class="stat-value"><?php echo $proyectos_pendientes; ?></span>
                                <span class="stat-label">Pendientes</span>
                            </div>
                        </div>
                        <a href="/views/profesores/Proyectos.php" class="option-button" <?php echo !$tiene_proyectos ? 'disabled' : ''; ?>>
                            <i class="fas fa-arrow-right"></i> Acceder
                        </a>
                        <?php if (!$tiene_proyectos): ?>
                        <div class="option-disabled-message">
                            <i class="fas fa-lock"></i> No tiene proyectos asignados
                        </div>
                        <?php endif; ?>
                    </div>

                    <div class="option-card logout-card">
                        <div class="option-icon">
                            <i class="fas fa-sign-out-alt"></i>
                        </div>
                        <h3>Cerrar Sesión</h3>
                        <p>Salir del sistema de forma segura</p>
                        <a href="/login.php" class="option-button logout-button">
                            <i class="fas fa-power-off"></i> Salir
                        </a>
                    </div>
                </div>
            </div>
        </main>

        <footer class="footer">
            <p>&copy; <?php echo date('Y'); ?> Sistema de Gestión Académica. Todos los derechos reservados.</p>
        </footer>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Deshabilitar enlaces si la tarjeta está deshabilitada
        const disabledCards = document.querySelectorAll('.option-card.disabled');
        
        disabledCards.forEach(card => {
            const button = card.querySelector('.option-button');
            if (button) {
                button.addEventListener('click', function(e) {
                    e.preventDefault();
                    // Mostrar mensaje de que no tiene asignaciones
                    const message = card.querySelector('.option-disabled-message');
                    if (message) {
                        message.classList.add('show-message');
                        setTimeout(() => {
                            message.classList.remove('show-message');
                        }, 3000);
                    }
                });
            }
        });

        // Efecto hover en las tarjetas
        const optionCards = document.querySelectorAll('.option-card:not(.disabled)');
        
        optionCards.forEach(card => {
            card.addEventListener('mouseenter', function() {
                this.classList.add('hover');
            });
            
            card.addEventListener('mouseleave', function() {
                this.classList.remove('hover');
            });
        });

        // Animación de entrada
        const welcomeContainer = document.querySelector('.welcome-container');
        if (welcomeContainer) {
            welcomeContainer.classList.add('animate-in');
        }
    });
    </script>
</body>
</html>