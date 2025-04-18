<?php
session_start();
require_once __DIR__ . '/../../config/conexion.php';

// Inicializar variables
$nombreUsuario = $_SESSION['nombreUsuario'] ?? 'Administrador';
$usuariosPendientes = 0;
$proyectosRecientes = [];
$seminariosRecientes = [];
$pasantiasRecientes = [];

try {
    // Obtener cantidad de usuarios pendientes de aprobación
    $stmt = $conexion->prepare("
        SELECT COUNT(*) as total FROM usuarios 
        WHERE estado = 'inactivo'
    ");
    $stmt->execute();
    $usuariosPendientes = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Obtener proyectos recientes
    $stmt = $conexion->prepare("
        SELECT p.id, p.titulo, p.estado, p.fecha_creacion,
               u.nombre as tutor_nombre
        FROM proyectos p
        LEFT JOIN usuarios u ON p.tutor_id = u.id
        WHERE p.tipo = 'proyecto'
        ORDER BY p.fecha_creacion DESC
        LIMIT 2
    ");
    $stmt->execute();
    $proyectosRecientes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Obtener seminarios recientes
    $stmt = $conexion->prepare("
        SELECT s.id, s.titulo, s.fecha, s.estado, s.modalidad,
               (SELECT COUNT(*) FROM inscripciones_seminario WHERE seminario_id = s.id) as num_inscritos,
               s.cupos,
               u.nombre as tutor_nombre
        FROM seminarios s
        LEFT JOIN usuarios u ON s.tutor_id = u.id
        ORDER BY s.fecha_creacion DESC
        LIMIT 2
    ");
    $stmt->execute();
    $seminariosRecientes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Obtener pasantías recientes
    $stmt = $conexion->prepare("
        SELECT p.id, p.titulo, p.empresa, p.estado, p.fecha_inicio,
               e.nombre as estudiante_nombre,
               t.nombre as tutor_nombre
        FROM pasantias p
        LEFT JOIN usuarios e ON p.estudiante_id = e.id
        LEFT JOIN usuarios t ON p.tutor_id = t.id
        ORDER BY p.fecha_creacion DESC
        LIMIT 2
    ");
    $stmt->execute();
    $pasantiasRecientes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Obtener estadísticas generales
    $stmt = $conexion->prepare("
        SELECT COUNT(*) as total FROM usuarios 
        WHERE rol = 'estudiante' AND estado = 'activo'
    ");
    $stmt->execute();
    $totalEstudiantes = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    $stmt = $conexion->prepare("
        SELECT COUNT(*) as total FROM proyectos 
        WHERE tipo = 'proyecto'
    ");
    $stmt->execute();
    $totalProyectos = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    $stmt = $conexion->prepare("
        SELECT COUNT(*) as total FROM pasantias
    ");
    $stmt->execute();
    $totalPasantias = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    $stmt = $conexion->prepare("
        SELECT COUNT(*) as total FROM seminarios
    ");
    $stmt->execute();
    $totalSeminarios = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
} catch (Exception $e) {
    // Manejar errores
    $error = "Error al cargar los datos: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel de Administrador - FET</title>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/inicio.css">
</head>
<body>
    <div id="logo" onclick="toggleNav()">Logo</div>
    
    <nav id="navbar">
        <div class="nav-header">
            <div id="nav-logo" onclick="toggleNav()">Logo</div>
        </div>
        <ul>
            <li><a href="/views/administrador/inicio.php" class="active">Inicio</a></li>
            <li><a href="/views/administrador/aprobacion.php">Aprobación de Usuarios</a></li>
            <li><a href="/views/administrador/usuarios.php">Gestión de Usuarios</a></li>
            <li class="dropdown">
                <a href="#">Gestión de Modalidades de Grado</a>
                <ul class="dropdown-content">
                    <li><a href="/views/administrador/gestion_seminario.php">Seminario</a></li>
                    <li><a href="/views/administrador/gestion_proyectos.php">Proyectos</a></li>
                    <li><a href="/views/administrador/gestion_pasantias.php">Pasantías</a></li>
                </ul>
            </li>
            <li><a href="/views/administrador/reportes.php">Reportes y Estadísticas</a></li>
            <li><a href="#">Rol: <?php echo htmlspecialchars($nombreUsuario); ?></a></li>
            <li><a href="/views/general/login.php">Cerrar Sesión</a></li>
        </ul>
    </nav>

    <main>
        <h1>Panel de Administración</h1>
        
        <section class="dashboard-section">
            <h2>Resumen General</h2>
            
            <div class="dashboard-cards">
                <div class="dashboard-card">
                    <div class="card-icon">👨‍🎓</div>
                    <div class="card-title">Estudiantes Activos</div>
                    <div class="card-value"><?= $totalEstudiantes ?? 0 ?></div>
                </div>
                
                <div class="dashboard-card">
                    <div class="card-icon">📁</div>
                    <div class="card-title">Proyectos</div>
                    <div class="card-value"><?= $totalProyectos ?? 0 ?></div>
                </div>
                
                <div class="dashboard-card">
                    <div class="card-icon">🧪</div>
                    <div class="card-title">Pasantías</div>
                    <div class="card-value"><?= $totalPasantias ?? 0 ?></div>
                </div>
                
                <div class="dashboard-card">
                    <div class="card-icon">📚</div>
                    <div class="card-title">Seminarios</div>
                    <div class="card-value"><?= $totalSeminarios ?? 0 ?></div>
                </div>
            </div>
        </section>
        
        <section class="dashboard-section" id="resumen-aprobaciones">
            <h2>Usuarios Pendientes de Aprobación</h2>
            <div class="aprobaciones-card">
                <div class="aprobaciones-icon">🔔</div>
                <div class="aprobaciones-info">
                    <p><?= $usuariosPendientes ?> usuarios pendientes de aprobación</p>
                    <a href="/views/administrador/aprobacion.php" class="btn-primary">Revisar Solicitudes</a>
                </div>
            </div>
        </section>

        <section class="dashboard-section" id="accesos-rapidos">
            <h2>Accesos Rápidos</h2>
            <div class="accesos-container">
                <div class="acceso-card">
                    <div class="acceso-header">
                        <h3>Proyectos</h3>
                        <div class="acceso-icon">📁</div>
                    </div>
                    <div class="acceso-body">
                        <p>Gestione los proyectos de grado, asigne tutores y realice seguimiento.</p>
                        <a href="/views/administrador/gestion_proyectos.php" class="btn-primary">Ir a Proyectos</a>
                    </div>
                </div>
                
                <div class="acceso-card">
                    <div class="acceso-header">
                        <h3>Seminarios</h3>
                        <div class="acceso-icon">📚</div>
                    </div>
                    <div class="acceso-body">
                        <p>Administre los seminarios, inscripciones y calificaciones.</p>
                        <a href="/views/administrador/gestion_seminario.php" class="btn-primary">Ir a Seminarios</a>
                    </div>
                </div>
                
                <div class="acceso-card">
                    <div class="acceso-header">
                        <h3>Pasantías</h3>
                        <div class="acceso-icon">🧪</div>
                    </div>
                    <div class="acceso-body">
                        <p>Gestione las pasantías, empresas y seguimiento de estudiantes.</p>
                        <a href="/views/administrador/gestion_pasantias.php" class="btn-primary">Ir a Pasantías</a>
                    </div>
                </div>
            </div>
        </section>

        <section class="dashboard-section" id="elementos-recientes">
            <h2>Elementos Recientes</h2>
            
            <div class="recientes-container">
                <div class="recientes-card">
                    <h3>Proyectos Recientes</h3>
                    <div class="recientes-list">
                        <?php if (!empty($proyectosRecientes)): ?>
                            <?php foreach ($proyectosRecientes as $proyecto): ?>
                                <div class="reciente-item">
                                    <div class="reciente-info">
                                        <h4><?= htmlspecialchars($proyecto['titulo']) ?></h4>
                                        <p><strong>Tutor:</strong> <?= htmlspecialchars($proyecto['tutor_nombre'] ?? 'No asignado') ?></p>
                                        <p><strong>Estado:</strong> 
                                            <span class="badge estado-<?= str_replace('_', '-', $proyecto['estado']) ?>">
                                                <?= ucfirst(str_replace('_', ' ', $proyecto['estado'])) ?>
                                            </span>
                                        </p>
                                        <p><strong>Fecha:</strong> <?= date('d/m/Y', strtotime($proyecto['fecha_creacion'])) ?></p>
                                    </div>
                                    
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p class="no-data">No hay proyectos recientes.</p>
                        <?php endif; ?>
                    </div>
                    <a href="/views/administrador/gestion_proyectos.php" class="ver-todos">Ver todos los proyectos</a>
                </div>
                
                <div class="recientes-card">
                    <h3>Seminarios Recientes</h3>
                    <div class="recientes-list">
                        <?php if (!empty($seminariosRecientes)): ?>
                            <?php foreach ($seminariosRecientes as $seminario): ?>
                                <div class="reciente-item">
                                    <div class="reciente-info">
                                        <h4><?= htmlspecialchars($seminario['titulo']) ?></h4>
                                        <p><strong>Tutor:</strong> <?= htmlspecialchars($seminario['tutor_nombre'] ?? 'No asignado') ?></p>
                                        <p><strong>Estado:</strong> 
                                            <span class="badge estado-<?= $seminario['estado'] ?>">
                                                <?= ucfirst($seminario['estado']) ?>
                                            </span>
                                        </p>
                                        <p><strong>Fecha:</strong> <?= date('d/m/Y', strtotime($seminario['fecha'])) ?></p>
                                        <p><strong>Inscritos:</strong> <?= $seminario['num_inscritos'] ?>/<?= $seminario['cupos'] ?></p>
                                    </div>
                                    
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p class="no-data">No hay seminarios recientes.</p>
                        <?php endif; ?>
                    </div>
                    <a href="/views/administrador/gestion_seminario.php" class="ver-todos">Ver todos los seminarios</a>
                </div>
                
                <div class="recientes-card">
                    <h3>Pasantías Recientes</h3>
                    <div class="recientes-list">
                        <?php if (!empty($pasantiasRecientes)): ?>
                            <?php foreach ($pasantiasRecientes as $pasantia): ?>
                                <div class="reciente-item">
                                    <div class="reciente-info">
                                        <h4><?= htmlspecialchars($pasantia['titulo']) ?></h4>
                                        <p><strong>Estudiante:</strong> <?= htmlspecialchars($pasantia['estudiante_nombre'] ?? 'No asignado') ?></p>
                                        <p><strong>Empresa:</strong> <?= htmlspecialchars($pasantia['empresa']) ?></p>
                                        <p><strong>Estado:</strong> 
                                            <span class="badge estado-<?= str_replace('_', '-', $pasantia['estado']) ?>">
                                                <?= ucfirst(str_replace('_', ' ', $pasantia['estado'])) ?>
                                            </span>
                                        </p>
                                        <p><strong>Inicio:</strong> <?= !empty($pasantia['fecha_inicio']) ? date('d/m/Y', strtotime($pasantia['fecha_inicio'])) : 'No establecida' ?></p>
                                    </div>
                                    
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p class="no-data">No hay pasantías recientes.</p>
                        <?php endif; ?>
                    </div>
                    <a href="/views/administrador/gestion_pasantias.php" class="ver-todos">Ver todas las pasantías</a>
                </div>
            </div>
        </section>
    </main>
    
    <footer>
        <p>&copy; 2023 Sistema de Gestión Académica. Todos los derechos reservados.</p>
    </footer>

    <script>
        // Funciones de navegación
        function toggleNav() {
            document.getElementById("navbar").classList.toggle("active");
            document.querySelector("main").classList.toggle("nav-active");
            document.querySelector("footer").classList.toggle("nav-active");
        }
    </script>
</body>
</html>