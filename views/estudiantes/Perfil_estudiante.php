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
    
    // Obtener los proyectos del estudiante desde la tabla de relaciones proyecto_estudiante
    $stmtProyectos = $conexion->prepare("
        SELECT p.*, u.nombre as tutor_nombre, u.email as tutor_email 
        FROM proyectos p
        INNER JOIN proyecto_estudiante pe ON p.id = pe.proyecto_id
        LEFT JOIN usuarios u ON p.tutor_id = u.id
        WHERE pe.estudiante_id = ?
    ");
    $stmtProyectos->execute([$userId]);
    $proyectos = $stmtProyectos->fetchAll(PDO::FETCH_ASSOC);
    
    // Si el estudiante tiene un nombre_proyecto pero no tiene proyectos asignados en la tabla de relaciones,
    // buscar proyectos por nombre que coincidan con el nombre_proyecto del usuario
    if (empty($proyectos) && !empty($user['nombre_proyecto'])) {
        $stmtProyectosPorNombre = $conexion->prepare("
            SELECT p.*, u.nombre as tutor_nombre, u.email as tutor_email 
            FROM proyectos p
            LEFT JOIN usuarios u ON p.tutor_id = u.id
            WHERE p.titulo LIKE ?
        ");
        $stmtProyectosPorNombre->execute(['%' . $user['nombre_proyecto'] . '%']);
        $proyectosPorNombre = $stmtProyectosPorNombre->fetchAll(PDO::FETCH_ASSOC);
        
        // Si encontramos proyectos por nombre, los agregamos a la lista
        if (!empty($proyectosPorNombre)) {
            $proyectos = array_merge($proyectos, $proyectosPorNombre);
            
            // Opcional: Crear automáticamente la relación en la tabla proyecto_estudiante
            foreach ($proyectosPorNombre as $proyecto) {
                // Verificar si ya existe la relación
                $stmtVerificarRelacion = $conexion->prepare("
                    SELECT id FROM proyecto_estudiante 
                    WHERE proyecto_id = ? AND estudiante_id = ?
                ");
                $stmtVerificarRelacion->execute([$proyecto['id'], $userId]);
                
                if (!$stmtVerificarRelacion->fetch()) {
                    // Crear la relación si no existe
                    $stmtCrearRelacion = $conexion->prepare("
                        INSERT INTO proyecto_estudiante (proyecto_id, estudiante_id, rol_en_proyecto)
                        VALUES (?, ?, 'miembro')
                    ");
                    $stmtCrearRelacion->execute([$proyecto['id'], $userId]);
                }
            }
        }
    }
    
    // Para cada proyecto, obtener los integrantes y el último avance
    foreach ($proyectos as &$proyecto) {
        // Obtener integrantes
        $stmtIntegrantes = $conexion->prepare("
            SELECT u.id, u.nombre, u.email, pe.rol_en_proyecto
            FROM usuarios u
            INNER JOIN proyecto_estudiante pe ON u.id = pe.estudiante_id
            WHERE pe.proyecto_id = ?
        ");
        $stmtIntegrantes->execute([$proyecto['id']]);
        $integrantes = $stmtIntegrantes->fetchAll(PDO::FETCH_ASSOC);
        
        // Si no hay integrantes registrados en la tabla de relaciones,
        // buscar usuarios con el mismo nombre_proyecto
        if (empty($integrantes)) {
            $stmtIntegrantesPorNombre = $conexion->prepare("
                SELECT u.id, u.nombre, u.email, 'miembro' as rol_en_proyecto
                FROM usuarios u
                WHERE u.nombre_proyecto LIKE ? AND u.rol = 'estudiante'
            ");
            $stmtIntegrantesPorNombre->execute(['%' . $proyecto['titulo'] . '%']);
            $integrantesPorNombre = $stmtIntegrantesPorNombre->fetchAll(PDO::FETCH_ASSOC);
            
            if (!empty($integrantesPorNombre)) {
                // Marcar al usuario actual como líder si está en la lista
                foreach ($integrantesPorNombre as &$integrante) {
                    if ($integrante['id'] == $userId) {
                        $integrante['rol_en_proyecto'] = 'lider';
                        break;
                    }
                }
                
                $integrantes = $integrantesPorNombre;
            }
        }
        
        $proyecto['integrantes'] = $integrantes;
        
        // Obtener el último avance del proyecto
        $stmtAvance = $conexion->prepare("
            SELECT ap.*, u.nombre as registrado_por_nombre
            FROM avances_proyecto ap
            LEFT JOIN usuarios u ON ap.registrado_por = u.id
            WHERE ap.proyecto_id = ? 
            ORDER BY ap.fecha_registro DESC 
            LIMIT 1
        ");
        $stmtAvance->execute([$proyecto['id']]);
        $ultimoAvance = $stmtAvance->fetch(PDO::FETCH_ASSOC);
        
        if ($ultimoAvance) {
            $proyecto['ultimo_avance'] = $ultimoAvance;
        } else {
            $proyecto['ultimo_avance'] = null;
        }
    }
    
    // Eliminar duplicados de proyectos basados en el ID
    $proyectosUnicos = [];
    $idsProyectos = [];
    
    foreach ($proyectos as $proyecto) {
        if (!in_array($proyecto['id'], $idsProyectos)) {
            $idsProyectos[] = $proyecto['id'];
            $proyectosUnicos[] = $proyecto;
        }
    }
    
    $proyectos = $proyectosUnicos;
    
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
    <style>
        /* Estilos adicionales para la sección de proyectos */
        .projects {
            margin: 30px auto;
            max-width: 1200px;
            padding: 20px;
            background-color: #f9f9f9;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }
        
        .projects h3 {
            color: #2c3e50;
            margin-bottom: 20px;
            font-size: 24px;
            border-bottom: 2px solid #3498db;
            padding-bottom: 10px;
        }
        
        .project-item {
            background-color: white;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s ease;
        }
        
        .project-item:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }
        
        .project-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            border-bottom: 1px solid #eee;
            padding-bottom: 10px;
        }
        
        .project-title {
            font-size: 20px;
            font-weight: bold;
            color: #2c3e50;
        }
        
        .project-status {
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: bold;
        }
        
        .status-propuesto { background-color: #f1c40f; color: #000; }
        .status-en_revision { background-color: #3498db; color: #fff; }
        .status-aprobado { background-color: #2ecc71; color: #fff; }
        .status-rechazado { background-color: #e74c3c; color: #fff; }
        .status-en_proceso { background-color: #9b59b6; color: #fff; }
        .status-finalizado { background-color: #34495e; color: #fff; }
        
        .project-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .detail-item {
            margin-bottom: 10px;
        }
        
        .detail-item strong {
            color: #2c3e50;
        }
        
        .team-section {
            margin-top: 20px;
            padding-top: 15px;
            border-top: 1px solid #eee;
        }
        
        .team-title {
            font-size: 18px;
            color: #2c3e50;
            margin-bottom: 10px;
        }
        
        .team-members {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }
        
        .team-member {
            background-color: #f8f9fa;
            padding: 8px 15px;
            border-radius: 20px;
            font-size: 14px;
            display: flex;
            align-items: center;
        }
        
        .team-member i {
            margin-right: 5px;
            color: #3498db;
        }
        
        .team-member.leader {
            background-color: #e3f2fd;
            border: 1px solid #bbdefb;
        }
        
        .project-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 20px;
            padding-top: 15px;
            border-top: 1px solid #eee;
        }
        
        .btn-action {
            display: inline-flex;
            align-items: center;
            padding: 8px 15px;
            background-color: #3498db;
            color: white;
            border-radius: 5px;
            text-decoration: none;
            font-weight: 500;
            transition: background-color 0.3s;
        }
        
        .btn-action i {
            margin-right: 5px;
        }
        
        .btn-action:hover {
            background-color: #2980b9;
        }
        
        .no-projects {
            text-align: center;
            padding: 30px;
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }
        
        .no-projects p {
            margin-bottom: 20px;
            color: #7f8c8d;
            font-size: 16px;
        }
        
        .progress-bar {
            height: 10px;
            background-color: #ecf0f1;
            border-radius: 5px;
            margin-top: 5px;
            overflow: hidden;
        }
        
        .progress-fill {
            height: 100%;
            background-color: #3498db;
            border-radius: 5px;
        }
        
        .avance-info {
            background-color: #f8f9fa;
            border-left: 4px solid #3498db;
            padding: 10px 15px;
            margin-top: 10px;
            border-radius: 0 5px 5px 0;
        }
        
        .avance-info p {
            margin: 5px 0;
        }
        
        .avance-info .avance-meta {
            display: flex;
            justify-content: space-between;
            color: #7f8c8d;
            font-size: 0.9em;
            margin-top: 8px;
        }
    </style>
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
        
        <?php if (empty($proyectos)): ?>
            <div class="no-projects">
                <p>No tienes proyectos registrados actualmente.</p>
                <a href="#" class="btn-action"><i class="fa-solid fa-plus"></i> Solicitar nuevo proyecto</a>
            </div>
        <?php else: ?>
            <?php foreach ($proyectos as $proyecto): ?>
                <div class="project-item">
                    <div class="project-header">
                        <div class="project-title">
                            <i class="fa-solid fa-file-code"></i> <?php echo htmlspecialchars($proyecto['titulo']); ?>
                        </div>
                        <div class="project-status status-<?php echo $proyecto['estado']; ?>">
                            <?php echo ucfirst(str_replace('_', ' ', $proyecto['estado'])); ?>
                        </div>
                    </div>
                    
                    <div class="project-details">
                        <div>
                            <div class="detail-item">
                                <strong><i class="fa-solid fa-tag"></i> Tipo:</strong> 
                                <?php echo ucfirst($proyecto['tipo']); ?>
                            </div>
                            
                            <div class="detail-item">
                                <strong><i class="fa-solid fa-calendar-alt"></i> Fecha de creación:</strong> 
                                <?php echo date('d/m/Y', strtotime($proyecto['fecha_creacion'])); ?>
                            </div>
                            
                            <?php if (!empty($proyecto['nombre_empresa'])): ?>
                            <div class="detail-item">
                                <strong><i class="fa-solid fa-building"></i> Empresa:</strong> 
                                <?php echo htmlspecialchars($proyecto['nombre_empresa']); ?>
                            </div>
                            <?php endif; ?>
                        </div>
                        
                        <div>
                            <div class="detail-item">
                                <strong><i class="fa-solid fa-user-tie"></i> Tutor:</strong> 
                                <?php if (!empty($proyecto['tutor_nombre'])): ?>
                                    <?php echo htmlspecialchars($proyecto['tutor_nombre']); ?> 
                                    <small>(<?php echo htmlspecialchars($proyecto['tutor_email']); ?>)</small>
                                <?php else: ?>
                                    <span>No asignado</span>
                                <?php endif; ?>
                            </div>
                            
                            <?php if (!empty($proyecto['ultimo_avance'])): ?>
                            <div class="detail-item">
                                <strong><i class="fa-solid fa-chart-line"></i> Progreso:</strong> 
                                <span><?php echo $proyecto['ultimo_avance']['porcentaje_avance']; ?>%</span>
                                <div class="progress-bar">
                                    <div class="progress-fill" style="width: <?php echo $proyecto['ultimo_avance']['porcentaje_avance']; ?>%"></div>
                                </div>
                            </div>
                            <?php else: ?>
                            <div class="detail-item">
                                <strong><i class="fa-solid fa-chart-line"></i> Progreso:</strong> 0%
                                <div class="progress-bar">
                                    <div class="progress-fill" style="width: 0%"></div>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="detail-item">
                        <strong><i class="fa-solid fa-align-left"></i> Descripción:</strong><br>
                        <p><?php echo nl2br(htmlspecialchars($proyecto['descripcion'])); ?></p>
                    </div>
                    
                    <?php if (!empty($proyecto['ultimo_avance'])): ?>
                    <div class="detail-item">
                        <strong><i class="fa-solid fa-clock-rotate-left"></i> Último avance:</strong>
                        <div class="avance-info">
                            <p><strong><?php echo htmlspecialchars($proyecto['ultimo_avance']['titulo']); ?></strong></p>
                            <p><?php echo nl2br(htmlspecialchars($proyecto['ultimo_avance']['descripcion'])); ?></p>
                            <div class="avance-meta">
                                <span>Registrado por: <?php echo htmlspecialchars($proyecto['ultimo_avance']['registrado_por_nombre'] ?? 'Usuario'); ?></span>
                                <span>Fecha: <?php echo date('d/m/Y H:i', strtotime($proyecto['ultimo_avance']['fecha_registro'])); ?></span>
                            </div>
                        </div>
                    </div>
                    <?php else: ?>
                    <div class="detail-item">
                        <strong><i class="fa-solid fa-clock-rotate-left"></i> Último avance:</strong> 
                        <span>No hay avances registrados</span>
                    </div>
                    <?php endif; ?>
                    
                    <div class="team-section">
                        <div class="team-title">
                            <i class="fa-solid fa-users"></i> Integrantes del Proyecto:
                        </div>
                        <div class="team-members">
                            <?php if (empty($proyecto['integrantes'])): ?>
                                <div class="team-member leader">
                                    <i class="fa-solid fa-crown"></i>
                                    <?php echo htmlspecialchars($user['nombre']); ?> <small>(Tú)</small>
                                </div>
                            <?php else: ?>
                                <?php foreach ($proyecto['integrantes'] as $integrante): ?>
                                    <div class="team-member <?php echo ($integrante['rol_en_proyecto'] === 'lider') ? 'leader' : ''; ?>">
                                        <?php if ($integrante['rol_en_proyecto'] === 'lider'): ?>
                                            <i class="fa-solid fa-crown"></i>
                                        <?php else: ?>
                                            <i class="fa-solid fa-user"></i>
                                        <?php endif; ?>
                                        <?php echo htmlspecialchars($integrante['nombre']); ?>
                                        <?php if ($integrante['id'] == $userId): ?>
                                            <small>(Tú)</small>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="project-actions">
                        <a href="/views/estudiantes/subir_avance.php?proyecto_id=<?php echo $proyecto['id']; ?>" class="btn-action">
                            <i class="fa-solid fa-upload"></i> Subir Avance
                        </a>
                        <a href="/views/estudiantes/historial_avances.php?proyecto_id=<?php echo $proyecto['id']; ?>" class="btn-action">
                            <i class="fa-solid fa-history"></i> Ver Historial
                        </a>
                        <a href="#" class="btn-action">
                            <i class="fa-solid fa-file-pdf"></i> Ver Documentación
                        </a>
                        <a href="#" class="btn-action">
                            <i class="fa-solid fa-comments"></i> Comentarios
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
