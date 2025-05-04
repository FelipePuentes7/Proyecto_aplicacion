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
    
    // Array para almacenar todos los proyectos y pasantías
    $opcionesGrado = [];
    
    // 1. Obtener los proyectos del estudiante desde la tabla de relaciones proyecto_estudiante
    $stmtProyectos = $conexion->prepare("
        SELECT p.*, u.nombre as tutor_nombre, u.email as tutor_email, 'proyecto' as tipo_opcion
        FROM proyectos p
        INNER JOIN proyecto_estudiante pe ON p.id = pe.proyecto_id
        LEFT JOIN usuarios u ON p.tutor_id = u.id
        WHERE pe.estudiante_id = ? AND p.tipo = 'proyecto'
    ");
    $stmtProyectos->execute([$userId]);
    $proyectos = $stmtProyectos->fetchAll(PDO::FETCH_ASSOC);
    
    // Agregar proyectos al array de opciones de grado
    foreach ($proyectos as $proyecto) {
        $opcionesGrado[] = $proyecto;
    }
    
    // 2. Obtener las pasantías del estudiante
    $stmtPasantias = $conexion->prepare("
        SELECT p.*, u.nombre as tutor_nombre, u.email as tutor_email, 'pasantia' as tipo_opcion
        FROM pasantias p
        LEFT JOIN usuarios u ON p.tutor_id = u.id
        WHERE p.estudiante_id = ?
    ");
    $stmtPasantias->execute([$userId]);
    $pasantias = $stmtPasantias->fetchAll(PDO::FETCH_ASSOC);
    
    // Agregar pasantías al array de opciones de grado
    foreach ($pasantias as $pasantia) {
        $opcionesGrado[] = $pasantia;
    }
    
    // 3. Si el estudiante tiene un nombre_proyecto pero no tiene proyectos asignados,
    // buscar proyectos por nombre que coincidan con el nombre_proyecto del usuario
    if (empty($opcionesGrado) && !empty($user['nombre_proyecto'])) {
        $stmtProyectosPorNombre = $conexion->prepare("
            SELECT p.*, u.nombre as tutor_nombre, u.email as tutor_email, 'proyecto' as tipo_opcion
            FROM proyectos p
            LEFT JOIN usuarios u ON p.tutor_id = u.id
            WHERE p.titulo LIKE ?
        ");
        $stmtProyectosPorNombre->execute(['%' . $user['nombre_proyecto'] . '%']);
        $proyectosPorNombre = $stmtProyectosPorNombre->fetchAll(PDO::FETCH_ASSOC);
        
        // Si encontramos proyectos por nombre, los agregamos a la lista
        foreach ($proyectosPorNombre as $proyecto) {
            $opcionesGrado[] = $proyecto;
            
            // Opcional: Crear automáticamente la relación en la tabla proyecto_estudiante
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
    
    // 4. Si el estudiante tiene un nombre_empresa pero no tiene pasantías asignadas,
    // buscar pasantías por nombre de empresa
    if (empty($opcionesGrado) && !empty($user['nombre_empresa'])) {
        $stmtPasantiasPorEmpresa = $conexion->prepare("
            SELECT p.*, u.nombre as tutor_nombre, u.email as tutor_email, 'pasantia' as tipo_opcion
            FROM pasantias p
            LEFT JOIN usuarios u ON p.tutor_id = u.id
            WHERE p.empresa LIKE ?
        ");
        $stmtPasantiasPorEmpresa->execute(['%' . $user['nombre_empresa'] . '%']);
        $pasantiasPorEmpresa = $stmtPasantiasPorEmpresa->fetchAll(PDO::FETCH_ASSOC);
        
        // Si encontramos pasantías por empresa, las agregamos a la lista
        foreach ($pasantiasPorEmpresa as $pasantia) {
            $opcionesGrado[] = $pasantia;
        }
    }
    
    // Para cada opción de grado, obtener información adicional
    foreach ($opcionesGrado as &$opcion) {
        // Si es un proyecto, obtener integrantes
        if ($opcion['tipo_opcion'] === 'proyecto') {
            // Obtener integrantes
            $stmtIntegrantes = $conexion->prepare("
                SELECT u.id, u.nombre, u.email, u.codigo_estudiante, pe.rol_en_proyecto
                FROM usuarios u
                INNER JOIN proyecto_estudiante pe ON u.id = pe.estudiante_id
                WHERE pe.proyecto_id = ?
            ");
            $stmtIntegrantes->execute([$opcion['id']]);
            $integrantes = $stmtIntegrantes->fetchAll(PDO::FETCH_ASSOC);
            
            // Si no hay integrantes registrados en la tabla de relaciones,
            // buscar usuarios con el mismo nombre_proyecto
            if (empty($integrantes)) {
                $stmtIntegrantesPorNombre = $conexion->prepare("
                    SELECT u.id, u.nombre, u.email, u.codigo_estudiante, 'miembro' as rol_en_proyecto
                    FROM usuarios u
                    WHERE u.nombre_proyecto LIKE ? AND u.rol = 'estudiante'
                ");
                $stmtIntegrantesPorNombre->execute(['%' . $opcion['titulo'] . '%']);
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
            
            $opcion['integrantes'] = $integrantes;
            
            // Obtener el último avance del proyecto
            $stmtAvance = $conexion->prepare("
                SELECT ap.*, u.nombre as registrado_por_nombre
                FROM avances_proyecto ap
                LEFT JOIN usuarios u ON ap.registrado_por = u.id
                WHERE ap.proyecto_id = ? 
                ORDER BY ap.fecha_registro DESC 
                LIMIT 1
            ");
            $stmtAvance->execute([$opcion['id']]);
            $ultimoAvance = $stmtAvance->fetch(PDO::FETCH_ASSOC);
            
            if ($ultimoAvance) {
                $opcion['ultimo_avance'] = $ultimoAvance;
            } else {
                $opcion['ultimo_avance'] = null;
            }
        }
    }
    
    // Eliminar duplicados basados en el ID
    $opcionesUnicas = [];
    $idsOpciones = [];
    
    foreach ($opcionesGrado as $opcion) {
        $idOpcion = $opcion['id'] . '-' . $opcion['tipo_opcion']; // Combinación única de ID y tipo
        if (!in_array($idOpcion, $idsOpciones)) {
            $idsOpciones[] = $idOpcion;
            $opcionesUnicas[] = $opcion;
        }
    }
    
    $opcionesGrado = $opcionesUnicas;
    
    // Verificar si hay opciones de grado
    $tieneOpciones = !empty($opcionesGrado);
    
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
                    <a href="/views/general/login.php" class="logout-btn">
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

    <?php if ($tieneOpciones): ?>
    <div class="projects">
        <h3>Tu 
            .0Opcion de Grado:</h3>
        
        <?php foreach ($opcionesGrado as $opcion): ?>
            <div class="project-item <?php echo $opcion['tipo_opcion']; ?>">
                <div class="project-header">
                    <div class="project-title">
                        <?php if ($opcion['tipo_opcion'] === 'proyecto'): ?>
                            <i class="fa-solid fa-file-code"></i>
                        <?php else: ?>
                            <i class="fa-solid fa-building"></i>
                        <?php endif; ?>
                        
                        <?php echo htmlspecialchars($opcion['titulo']); ?>
                        
                        <span class="tipo-badge badge-<?php echo $opcion['tipo_opcion']; ?>">
                            <?php echo ucfirst($opcion['tipo_opcion']); ?>
                        </span>
                    </div>
                    <div class="project-status status-<?php echo $opcion['estado']; ?>">
                        <?php echo ucfirst(str_replace('_', ' ', $opcion['estado'])); ?>
                    </div>
                </div>
                
                <div class="project-details">
                    <div>
                        <?php if ($opcion['tipo_opcion'] === 'pasantia'): ?>
                            <div class="detail-item">
                                <strong><i class="fa-solid fa-building"></i> Empresa:</strong> 
                                <?php echo htmlspecialchars($opcion['empresa']); ?>
                            </div>
                            
                            <?php if (!empty($opcion['direccion_empresa'])): ?>
                            <div class="detail-item">
                                <strong><i class="fa-solid fa-location-dot"></i> Dirección:</strong> 
                                <?php echo htmlspecialchars($opcion['direccion_empresa']); ?>
                            </div>
                            <?php endif; ?>
                            
                            <?php if (!empty($opcion['supervisor_empresa'])): ?>
                            <div class="detail-item">
                                <strong><i class="fa-solid fa-user-tie"></i> Supervisor:</strong> 
                                <?php echo htmlspecialchars($opcion['supervisor_empresa']); ?>
                            </div>
                            <?php endif; ?>
                            
                            <?php if (!empty($opcion['telefono_supervisor'])): ?>
                            <div class="detail-item">
                                <strong><i class="fa-solid fa-phone"></i> Teléfono Supervisor:</strong> 
                                <?php echo htmlspecialchars($opcion['telefono_supervisor']); ?>
                            </div>
                            <?php endif; ?>
                        <?php else: ?>
                            <div class="detail-item">
                                <strong><i class="fa-solid fa-tag"></i> Tipo:</strong> 
                                <?php echo ucfirst($opcion['tipo']); ?>
                            </div>
                        <?php endif; ?>
                        
                        <div class="detail-item">
                            <strong><i class="fa-solid fa-calendar-alt"></i> Fecha de creación:</strong> 
                            <?php echo date('d/m/Y', strtotime($opcion['fecha_creacion'])); ?>
                        </div>
                    </div>
                    
                    <div>
                        <div class="detail-item">
                            <strong><i class="fa-solid fa-user-tie"></i> Tutor:</strong> 
                            <?php if (!empty($opcion['tutor_nombre'])): ?>
                                <?php echo htmlspecialchars($opcion['tutor_nombre']); ?> 
                                <?php if (!empty($opcion['tutor_email'])): ?>
                                <small>(<?php echo htmlspecialchars($opcion['tutor_email']); ?>)</small>
                                <?php endif; ?>
                            <?php else: ?>
                                <span>No asignado</span>
                            <?php endif; ?>
                        </div>
                        
                        <?php if ($opcion['tipo_opcion'] === 'proyecto' && isset($opcion['ultimo_avance'])): ?>
                        <div class="detail-item">
                            <strong><i class="fa-solid fa-chart-line"></i> Progreso:</strong> 
                            <span><?php echo $opcion['ultimo_avance']['porcentaje_avance']; ?>%</span>
                            <div class="progress-bar">
                                <div class="progress-fill" style="width: <?php echo $opcion['ultimo_avance']['porcentaje_avance']; ?>%"></div>
                            </div>
                        </div>
                        <?php elseif ($opcion['tipo_opcion'] === 'proyecto'): ?>
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
                    <p><?php echo nl2br(htmlspecialchars($opcion['descripcion'])); ?></p>
                </div>
                
                <?php if ($opcion['tipo_opcion'] === 'proyecto' && !empty($opcion['ultimo_avance'])): ?>
                <div class="detail-item">
                    <strong><i class="fa-solid fa-clock-rotate-left"></i> Último avance:</strong>
                    <div class="avance-info">
                        <p><strong><?php echo htmlspecialchars($opcion['ultimo_avance']['titulo']); ?></strong></p>
                        <p><?php echo nl2br(htmlspecialchars($opcion['ultimo_avance']['descripcion'])); ?></p>
                        <div class="avance-meta">
                            <span>Registrado por: <?php echo htmlspecialchars($opcion['ultimo_avance']['registrado_por_nombre'] ?? 'Usuario'); ?></span>
                            <span>Fecha: <?php echo date('d/m/Y H:i', strtotime($opcion['ultimo_avance']['fecha_registro'])); ?></span>
                        </div>
                    </div>
                </div>
                <?php elseif ($opcion['tipo_opcion'] === 'proyecto'): ?>
                <div class="detail-item">
                    <strong><i class="fa-solid fa-clock-rotate-left"></i> Último avance:</strong> 
                    <span>No hay avances registrados</span>
                </div>
                <?php endif; ?>
                
                <?php if ($opcion['tipo_opcion'] === 'proyecto' && !empty($opcion['integrantes'])): ?>
                <div class="team-section">
                    <div class="team-title">
                        <i class="fa-solid fa-users"></i> Integrantes del Proyecto:
                    </div>
                    <div class="team-members">
                        <?php foreach ($opcion['integrantes'] as $integrante): ?>
                            <div class="team-member <?php echo ($integrante['rol_en_proyecto'] === 'lider') ? 'leader' : ''; ?>">
                                <?php if ($integrante['rol_en_proyecto'] === 'lider'): ?>
                                    <i class="fa-solid fa-crown">  === 'lider'): ?>
                                    <i class="fa-solid fa-crown"></i>
                                <?php else: ?>
                                    <i class="fa-solid fa-user"></i>
                                <?php endif; ?>
                                <?php echo htmlspecialchars($integrante['nombre']); ?>
                                <?php if (!empty($integrante['codigo_estudiante'])): ?>
                                    <small>(<?php echo htmlspecialchars($integrante['codigo_estudiante']); ?>)</small>
                                <?php endif; ?>
                                <?php if ($integrante['id'] == $userId): ?>
                                    <small>(Tú)</small>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php elseif ($opcion['tipo_opcion'] === 'proyecto'): ?>
                <div class="team-section">
                    <div class="team-title">
                        <i class="fa-solid fa-users"></i> Integrantes del Proyecto:
                    </div>
                    <div class="team-members">
                        <div class="team-member leader">
                            <i class="fa-solid fa-crown"></i>
                            <?php echo htmlspecialchars($user['nombre']); ?> <small>(Tú)</small>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
                
                <div class="project-actions">
                    <?php if ($opcion['tipo_opcion'] === 'proyecto'): ?>
                        <a href="/views/estudiantes/subir_avance.php?proyecto_id=<?php echo $opcion['id']; ?>" class="btn-action">
                            <i class="fa-solid fa-upload"></i> Subir Avance
                        </a>
                        <a href="/views/estudiantes/historial_avances.php?proyecto_id=<?php echo $opcion['id']; ?>" class="btn-action">
                            <i class="fa-solid fa-history"></i> Ver Historial
                        </a>
                        <?php if (!empty($opcion['archivo_proyecto'])): ?>
                        <a href="/uploads/proyectos/<?php echo $opcion['archivo_proyecto']; ?>" class="btn-action" target="_blank">
                            <i class="fa-solid fa-file-pdf"></i> Ver Documentación
                        </a>
                        <?php endif; ?>
                        <a href="/views/estudiantes/comentarios_proyecto.php?proyecto_id=<?php echo $opcion['id']; ?>" class="btn-action">
                            <i class="fa-solid fa-comments"></i> Comentarios
                        </a>
                    <?php else: ?>
                        <a href="/views/estudiantes/subir_informe_pasantia.php?pasantia_id=<?php echo $opcion['id']; ?>" class="btn-action">
                            <i class="fa-solid fa-upload"></i> Subir Informe
                        </a>
                        <a href="/views/estudiantes/historial_informes.php?pasantia_id=<?php echo $opcion['id']; ?>" class="btn-action">
                            <i class="fa-solid fa-history"></i> Ver Historial
                        </a>
                        <?php if (!empty($opcion['archivo_documento'])): ?>
                        <a href="/uploads/pasantias/<?php echo $opcion['archivo_documento']; ?>" class="btn-action" target="_blank">
                            <i class="fa-solid fa-file-pdf"></i> Ver Documentación
                        </a>
                        <?php endif; ?>
                        <a href="/views/estudiantes/detalles_pasantia.php?pasantia_id=<?php echo $opcion['id']; ?>" class="btn-action">
                            <i class="fa-solid fa-info-circle"></i> Ver Detalles
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
    <?php else: ?>
    <div class="projects">
        <h3>Tus Opciones de Grado:</h3>
        <div class="no-projects">
            <p>No tienes opciones de grado registradas actualmente.</p>
            <div class="options-buttons">
                <a href="/views/estudiantes/solicitar_proyecto.php" class="btn-action">
                    <i class="fa-solid fa-file-code"></i> Solicitar Proyecto
                </a>
                <a href="/views/estudiantes/solicitar_pasantia.php" class="btn-action">
                    <i class="fa-solid fa-building"></i> Solicitar Pasantía
                </a>
            </div>
        </div>
    </div>
    <?php endif; ?>

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