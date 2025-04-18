<?php
session_start();
require_once __DIR__ . '/../../config/conexion.php';

// Inicializar variables
$mensaje = '';
$error = '';
$nombreUsuario = $_SESSION['nombreUsuario'] ?? 'Administrador';

// Obtener métricas generales
try {
    // Total de estudiantes activos
    $stmt = $conexion->prepare("
        SELECT COUNT(*) as total FROM usuarios 
        WHERE rol = 'estudiante' AND estado = 'activo'
    ");
    $stmt->execute();
    $total_estudiantes = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

    // Total de tutores
    $stmt = $conexion->prepare("
        SELECT COUNT(*) as total FROM usuarios 
        WHERE rol = 'tutor' AND estado = 'activo'
    ");
    $stmt->execute();
    $total_tutores = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

    // Total de proyectos
    $stmt = $conexion->prepare("
        SELECT COUNT(*) as total FROM proyectos 
        WHERE tipo = 'proyecto'
    ");
    $stmt->execute();
    $total_proyectos = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

    // Total de pasantías
    $stmt = $conexion->prepare("
        SELECT COUNT(*) as total FROM pasantias
    ");
    $stmt->execute();
    $total_pasantias = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

    // Total de seminarios
    $stmt = $conexion->prepare("
        SELECT COUNT(*) as total FROM seminarios
    ");
    $stmt->execute();
    $total_seminarios = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

    // Distribución por opción de grado
    $stmt = $conexion->prepare("
        SELECT opcion_grado, COUNT(*) as total 
        FROM usuarios 
        WHERE rol = 'estudiante' AND opcion_grado IS NOT NULL 
        GROUP BY opcion_grado
    ");
    $stmt->execute();
    $distribucion_opcion_grado = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Proyectos por estado
    $stmt = $conexion->prepare("
        SELECT estado, COUNT(*) as total 
        FROM proyectos 
        WHERE tipo = 'proyecto'
        GROUP BY estado
    ");
    $stmt->execute();
    $proyectos_por_estado = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Pasantías por estado
    $stmt = $conexion->prepare("
        SELECT estado, COUNT(*) as total 
        FROM pasantias 
        GROUP BY estado
    ");
    $stmt->execute();
    $pasantias_por_estado = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Seminarios por estado
    $stmt = $conexion->prepare("
        SELECT estado, COUNT(*) as total 
        FROM seminarios 
        GROUP BY estado
    ");
    $stmt->execute();
    $seminarios_por_estado = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Estudiantes por ciclo
    $stmt = $conexion->prepare("
        SELECT ciclo, COUNT(*) as total 
        FROM usuarios 
        WHERE rol = 'estudiante' AND ciclo IS NOT NULL 
        GROUP BY ciclo
    ");
    $stmt->execute();
    $estudiantes_por_ciclo = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Lista de estudiantes con su opción de grado y estado
    $stmt = $conexion->prepare("
        SELECT id, nombre, email, documento, codigo_estudiante, opcion_grado, ciclo, estado
        FROM usuarios 
        WHERE rol = 'estudiante'
        ORDER BY nombre
    ");
    $stmt->execute();
    $lista_estudiantes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Lista de proyectos con su grupo, tutor y estado
    $stmt = $conexion->prepare("
        SELECT p.id, p.titulo, p.estado, p.fecha_creacion,
           u.nombre as tutor_nombre
    FROM proyectos p
    LEFT JOIN usuarios u ON p.tutor_id = u.id
    WHERE p.tipo = 'proyecto'
    ORDER BY p.fecha_creacion DESC
");
    $stmt->execute();
    $lista_proyectos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Obtener los estudiantes de cada proyecto
    $lista_proyectos_con_estudiantes = [];
    foreach ($lista_proyectos as $proyecto) {
        $stmt = $conexion->prepare("
            SELECT u.nombre
            FROM proyecto_estudiante pe
            JOIN usuarios u ON pe.estudiante_id = u.id
            WHERE pe.proyecto_id = ?
            ORDER BY u.nombre
        ");
        $stmt->execute([$proyecto['id']]);
        $estudiantes = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        $proyecto['estudiantes'] = $estudiantes;
        $lista_proyectos_con_estudiantes[] = $proyecto;
    }
    $lista_proyectos = $lista_proyectos_con_estudiantes;

    // Lista de pasantías con estudiante, empresa y tutor
    $stmt = $conexion->prepare("
        SELECT p.id, p.titulo, p.empresa, p.estado, p.fecha_inicio, p.fecha_fin,
               e.nombre as estudiante_nombre, e.codigo_estudiante,
               t.nombre as tutor_nombre
        FROM pasantias p
        LEFT JOIN usuarios e ON p.estudiante_id = e.id
        LEFT JOIN usuarios t ON p.tutor_id = t.id
        ORDER BY p.fecha_creacion DESC
    ");
    $stmt->execute();
    $lista_pasantias = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Lista de seminarios con nombre, inscritos y avance
    $stmt = $conexion->prepare("
        SELECT s.id, s.titulo, s.fecha, s.estado, s.modalidad,
               (SELECT COUNT(*) FROM inscripciones_seminario WHERE seminario_id = s.id) as num_inscritos,
               s.cupos,
               u.nombre as tutor_nombre
        FROM seminarios s
        LEFT JOIN usuarios u ON s.tutor_id = u.id
        ORDER BY s.fecha DESC
    ");
    $stmt->execute();
    $lista_seminarios = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    $error = "Error al cargar los datos: " . $e->getMessage();
}

// Cerrar conexión
$conexion = null;

// Convertir datos a formato JSON para gráficas
$json_distribucion_opcion_grado = json_encode($distribucion_opcion_grado);
$json_proyectos_por_estado = json_encode($proyectos_por_estado);
$json_pasantias_por_estado = json_encode($pasantias_por_estado);
$json_seminarios_por_estado = json_encode($seminarios_por_estado);
$json_estudiantes_por_ciclo = json_encode($estudiantes_por_ciclo);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reportes y Estadísticas - FET</title>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/reportes.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
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
        <h1>Reportes y Estadísticas</h1>
        
        <?php if ($mensaje): ?>
            <div class="mensaje exito"><?= htmlspecialchars($mensaje) ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="mensaje error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        
        <div class="tabs">
            <button id="dashboardTab" class="active">Panel General</button>
            <button id="graficasTab">Gráficas</button>
            <button id="estudiantesTab">Estudiantes</button>
            <button id="proyectosTab">Proyectos</button>
            <button id="pasantiasTab">Pasantías</button>
            <button id="seminariosTab">Seminarios</button>
        </div>
        
        <!-- Sección Panel General (Dashboard) -->
        <section id="dashboardSection" class="tab-content active">
            <h2>Panel General</h2>
            
            <div class="dashboard-cards">
                <div class="dashboard-card">
                    <div class="card-icon">👨‍🎓</div>
                    <div class="card-title">Estudiantes Activos</div>
                    <div class="card-value"><?= $total_estudiantes ?></div>
                </div>
                
                <div class="dashboard-card">
                    <div class="card-icon">👨‍🏫</div>
                    <div class="card-title">Tutores</div>
                    <div class="card-value"><?= $total_tutores ?></div>
                </div>
                
                <div class="dashboard-card">
                    <div class="card-icon">📁</div>
                    <div class="card-title">Proyectos</div>
                    <div class="card-value"><?= $total_proyectos ?></div>
                </div>
                
                <div class="dashboard-card">
                    <div class="card-icon">🧪</div>
                    <div class="card-title">Pasantías</div>
                    <div class="card-value"><?= $total_pasantias ?></div>
                </div>
                
                <div class="dashboard-card">
                    <div class="card-icon">📚</div>
                    <div class="card-title">Seminarios</div>
                    <div class="card-value"><?= $total_seminarios ?></div>
                </div>
            </div>
            
            <div class="charts-container">
                <div class="chart-card">
                    <div class="chart-title">Distribución por Opción de Grado</div>
                    <div class="chart-container">
                        <canvas id="opcionGradoChart"></canvas>
                    </div>
                </div>
                
                <div class="chart-card">
                    <div class="chart-title">Estudiantes por Ciclo</div>
                    <div class="chart-container">
                        <canvas id="cicloChart"></canvas>
                    </div>
                </div>
                
                <div class="chart-card">
                    <div class="chart-title">Proyectos por Estado</div>
                    <div class="chart-container">
                        <canvas id="proyectosEstadoChart"></canvas>
                    </div>
                </div>
                
                <div class="chart-card">
                    <div class="chart-title">Pasantías por Estado</div>
                    <div class="chart-container">
                        <canvas id="pasantiasEstadoChart"></canvas>
                    </div>
                </div>
            </div>
        </section>
        
        <!-- Sección Gráficas -->
        <section id="graficasSection" class="tab-content">
            <h2>Gráficas Estadísticas</h2>
            
            <div class="charts-container">
                <div class="chart-card">
                    <div class="chart-title">Distribución por Opción de Grado</div>
                    <div class="chart-container">
                        <canvas id="opcionGradoChartFull"></canvas>
                    </div>
                </div>
                
                <div class="chart-card">
                    <div class="chart-title">Estudiantes por Ciclo</div>
                    <div class="chart-container">
                        <canvas id="cicloChartFull"></canvas>
                    </div>
                </div>
                
                <div class="chart-card">
                    <div class="chart-title">Proyectos por Estado</div>
                    <div class="chart-container">
                        <canvas id="proyectosEstadoChartFull"></canvas>
                    </div>
                </div>
                
                <div class="chart-card">
                    <div class="chart-title">Pasantías por Estado</div>
                    <div class="chart-container">
                        <canvas id="pasantiasEstadoChartFull"></canvas>
                    </div>
                </div>
                
                <div class="chart-card">
                    <div class="chart-title">Seminarios por Estado</div>
                    <div class="chart-container">
                        <canvas id="seminariosEstadoChart"></canvas>
                    </div>
                </div>
            </div>
        </section>
        
        <!-- Sección Estudiantes -->
        <section id="estudiantesSection" class="tab-content">
            <h2>Listado de Estudiantes</h2>
            
            <div class="table-container">
                <div class="table-header">
                    <div class="table-title">Estudiantes Registrados</div>
                    <div class="table-filter">
                        <input type="text" id="filtroEstudiantes" placeholder="Buscar estudiante...">
                        <select id="filtroOpcionGrado">
                            <option value="">Todas las opciones</option>
                            <option value="seminario">Seminario</option>
                            <option value="proyecto">Proyecto</option>
                            <option value="pasantia">Pasantía</option>
                        </select>
                        <select id="filtroCiclo">
                            <option value="">Todos los ciclos</option>
                            <option value="tecnico">Técnico</option>
                            <option value="tecnologo">Tecnólogo</option>
                            <option value="profesional">Profesional</option>
                        </select>
                    </div>
                </div>
                
                <div class="export-options">
                    <button class="export-btn" onclick="exportarTablaExcel('tablaEstudiantes', 'Estudiantes')">
                        <i>📊</i> Exportar a Excel
                    </button>
                </div>
                
                <div class="table-responsive">
                    <table id="tablaEstudiantes" class="tabla-pasantias">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Nombre</th>
                                <th>Código</th>
                                <th>Documento</th>
                                <th>Email</th>
                                <th>Opción de Grado</th>
                                <th>Ciclo</th>
                                <th>Estado</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($lista_estudiantes as $estudiante): ?>
                                <tr data-opcion="<?= htmlspecialchars($estudiante['opcion_grado'] ?? '') ?>" 
                                    data-ciclo="<?= htmlspecialchars($estudiante['ciclo'] ?? '') ?>"
                                    data-nombre="<?= htmlspecialchars($estudiante['nombre']) ?>">
                                    <td><?= $estudiante['id'] ?></td>
                                    <td><?= htmlspecialchars($estudiante['nombre']) ?></td>
                                    <td><?= htmlspecialchars($estudiante['codigo_estudiante'] ?? 'N/A') ?></td>
                                    <td><?= htmlspecialchars($estudiante['documento']) ?></td>
                                    <td><?= htmlspecialchars($estudiante['email']) ?></td>
                                    <td>
                                        <?php if (!empty($estudiante['opcion_grado'])): ?>
                                            <span class="badge estado-<?= $estudiante['opcion_grado'] ?>">
                                                <?= ucfirst($estudiante['opcion_grado']) ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="badge">No asignada</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= !empty($estudiante['ciclo']) ? ucfirst($estudiante['ciclo']) : 'No asignado' ?></td>
                                    <td>
                                        <span class="badge estado-<?= $estudiante['estado'] ?>">
                                            <?= ucfirst($estudiante['estado']) ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            
                            <?php if (empty($lista_estudiantes)): ?>
                                <tr>
                                    <td colspan="8" class="no-data">No hay estudiantes registrados.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>
        
        <!-- Sección Proyectos -->
        <section id="proyectosSection" class="tab-content">
            <h2>Listado de Proyectos</h2>
            
            <div class="table-container">
                <div class="table-header">
                    <div class="table-title">Proyectos Registrados</div>
                    <div class="table-filter">
                        <input type="text" id="filtroProyectos" placeholder="Buscar proyecto...">
                        <select id="filtroEstadoProyecto">
                            <option value="">Todos los estados</option>
                            <option value="propuesto">Propuesto</option>
                            <option value="en_revision">En revisión</option>
                            <option value="aprobado">Aprobado</option>
                            <option value="rechazado">Rechazado</option>
                            <option value="en_proceso">En proceso</option>
                            <option value="finalizado">Finalizado</option>
                        </select>
                    </div>
                </div>
                
                <div class="export-options">
                    <button class="export-btn" onclick="exportarTablaExcel('tablaProyectos', 'Proyectos')">
                        <i>📊</i> Exportar a Excel
                    </button>
                </div>
                
                <div class="table-responsive">
                    <table id="tablaProyectos" class="tabla-pasantias">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Título</th>
                                <th>Estudiantes</th>
                                <th>Tutor</th>
                                <th>Estado</th>
                                <th>Fecha Creación</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($lista_proyectos as $proyecto): ?>
                                <tr data-estado="<?= htmlspecialchars($proyecto['estado']) ?>"
                                    data-titulo="<?= htmlspecialchars($proyecto['titulo']) ?>">
                                    <td><?= $proyecto['id'] ?></td>
                                    <td><?= htmlspecialchars($proyecto['titulo']) ?></td>
                                    <td>
                                        <?php if (!empty($proyecto['estudiantes'])): ?>
                                            <?= implode(', ', $proyecto['estudiantes']) ?>
                                        <?php else: ?>
                                            <span class="text-muted">Sin estudiantes asignados</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= htmlspecialchars($proyecto['tutor_nombre'] ?? 'No asignado') ?></td>
                                    <td>
                                        <span class="badge estado-<?= str_replace('_', '-', $proyecto['estado']) ?>">
                                            <?= ucfirst(str_replace('_', ' ', $proyecto['estado'])) ?>
                                        </span>
                                    </td>
                                    <td><?= date('d/m/Y', strtotime($proyecto['fecha_creacion'])) ?></td>
                                </tr>
                            <?php endforeach; ?>
                            
                            <?php if (empty($lista_proyectos)): ?>
                                <tr>
                                    <td colspan="6" class="no-data">No hay proyectos registrados.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>
        
        <!-- Sección Pasantías -->
        <section id="pasantiasSection" class="tab-content">
            <h2>Listado de Pasantías</h2>
            
            <div class="table-container">
                <div class="table-header">
                    <div class="table-title">Pasantías Registradas</div>
                    <div class="table-filter">
                        <input type="text" id="filtroPasantias" placeholder="Buscar pasantía...">
                        <select id="filtroEstadoPasantia">
                            <option value="">Todos los estados</option>
                            <option value="pendiente">Pendiente</option>
                            <option value="aprobada">Aprobada</option>
                            <option value="rechazada">Rechazada</option>
                            <option value="en_proceso">En proceso</option>
                            <option value="finalizada">Finalizada</option>
                        </select>
                    </div>
                </div>
                
                <div class="export-options">
                    <button class="export-btn" onclick="exportarTablaExcel('tablaPasantias', 'Pasantias')">
                        <i>📊</i> Exportar a Excel
                    </button>
                </div>
                
                <div class="table-responsive">
                    <table id="tablaPasantias" class="tabla-pasantias">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Título</th>
                                <th>Estudiante</th>
                                <th>Empresa</th>
                                <th>Tutor</th>
                                <th>Estado</th>
                                <th>Fecha Inicio</th>
                                <th>Fecha Fin</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($lista_pasantias as $pasantia): ?>
                                <tr data-estado="<?= htmlspecialchars($pasantia['estado']) ?>"
                                    data-titulo="<?= htmlspecialchars($pasantia['titulo']) ?>"
                                    data-empresa="<?= htmlspecialchars($pasantia['empresa']) ?>"
                                    data-estudiante="<?= htmlspecialchars($pasantia['estudiante_nombre'] ?? '') ?>">
                                    <td><?= $pasantia['id'] ?></td>
                                    <td><?= htmlspecialchars($pasantia['titulo']) ?></td>
                                    <td><?= htmlspecialchars($pasantia['estudiante_nombre'] ?? 'No asignado') ?></td>
                                    <td><?= htmlspecialchars($pasantia['empresa']) ?></td>
                                    <td><?= htmlspecialchars($pasantia['tutor_nombre'] ?? 'No asignado') ?></td>
                                    <td>
                                        <span class="badge estado-<?= str_replace('_', '-', $pasantia['estado']) ?>">
                                            <?= ucfirst(str_replace('_', ' ', $pasantia['estado'])) ?>
                                        </span>
                                    </td>
                                    <td><?= !empty($pasantia['fecha_inicio']) ? date('d/m/Y', strtotime($pasantia['fecha_inicio'])) : 'No establecida' ?></td>
                                    <td><?= !empty($pasantia['fecha_fin']) ? date('d/m/Y', strtotime($pasantia['fecha_fin'])) : 'No establecida' ?></td>
                                </tr>
                            <?php endforeach; ?>
                            
                            <?php if (empty($lista_pasantias)): ?>
                                <tr>
                                    <td colspan="8" class="no-data">No hay pasantías registradas.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>
        
        <!-- Sección Seminarios -->
        <section id="seminariosSection" class="tab-content">
            <h2>Listado de Seminarios</h2>
            
            <div class="table-container">
                <div class="table-header">
                    <div class="table-title">Seminarios Registrados</div>
                    <div class="table-filter">
                        <input type="text" id="filtroSeminarios" placeholder="Buscar seminario...">
                        <select id="filtroEstadoSeminario">
                            <option value="">Todos los estados</option>
                            <option value="activo">Activo</option>
                            <option value="finalizado">Finalizado</option>
                            <option value="cancelado">Cancelado</option>
                        </select>
                    </div>
                </div>
                
                <div class="export-options">
                    <button class="export-btn" onclick="exportarTablaExcel('tablaSeminarios', 'Seminarios')">
                        <i>📊</i> Exportar a Excel
                    </button>
                </div>
                
                <div class="table-responsive">
                    <table id="tablaSeminarios" class="tabla-pasantias">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Título</th>
                                <th>Fecha</th>
                                <th>Modalidad</th>
                                <th>Tutor</th>
                                <th>Estado</th>
                                <th>Inscritos</th>
                                <th>Avance</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($lista_seminarios as $seminario): ?>
                                <tr data-estado="<?= htmlspecialchars($seminario['estado']) ?>"
                                    data-titulo="<?= htmlspecialchars($seminario['titulo']) ?>">
                                    <td><?= $seminario['id'] ?></td>
                                    <td><?= htmlspecialchars($seminario['titulo']) ?></td>
                                    <td><?= date('d/m/Y', strtotime($seminario['fecha'])) ?></td>
                                    <td><?= ucfirst($seminario['modalidad']) ?></td>
                                    <td><?= htmlspecialchars($seminario['tutor_nombre'] ?? 'No asignado') ?></td>
                                    <td>
                                        <span class="badge estado-<?= $seminario['estado'] ?>">
                                            <?= ucfirst($seminario['estado']) ?>
                                        </span>
                                    </td>
                                    <td><?= $seminario['num_inscritos'] ?> / <?= $seminario['cupos'] ?></td>
                                    <td>
                                        <div class="progress-bar">
                                            <div class="progress-fill" style="width: <?= ($seminario['num_inscritos'] / $seminario['cupos']) * 100 ?>%"></div>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            
                            <?php if (empty($lista_seminarios)): ?>
                                <tr>
                                    <td colspan="8" class="no-data">No hay seminarios registrados.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>
    </main>
    
    <footer>
        <p>&copy; 2023 Sistema de Gestión Académica. Todos los derechos reservados.</p>
    </footer>

    <script>
        // Variables globales
        let distribucionOpcionGrado = <?= $json_distribucion_opcion_grado ?>;
        let proyectosPorEstado = <?= $json_proyectos_por_estado ?>;
        let pasantiasPorEstado = <?= $json_pasantias_por_estado ?>;
        let seminariosPorEstado = <?= $json_seminarios_por_estado ?>;
        let estudiantesPorCiclo = <?= $json_estudiantes_por_ciclo ?>;
        
        // Funciones de navegación
        function toggleNav() {
            document.getElementById("navbar").classList.toggle("active");
            document.querySelector("main").classList.toggle("nav-active");
            document.querySelector("footer").classList.toggle("nav-active");
        }
        
        // Manejo de pestañas
        const dashboardTab = document.getElementById('dashboardTab');
        const graficasTab = document.getElementById('graficasTab');
        const estudiantesTab = document.getElementById('estudiantesTab');
        const proyectosTab = document.getElementById('proyectosTab');
        const pasantiasTab = document.getElementById('pasantiasTab');
        const seminariosTab = document.getElementById('seminariosTab');
        
        const dashboardSection = document.getElementById('dashboardSection');
        const graficasSection = document.getElementById('graficasSection');
        const estudiantesSection = document.getElementById('estudiantesSection');
        const proyectosSection = document.getElementById('proyectosSection');
        const pasantiasSection = document.getElementById('pasantiasSection');
        const seminariosSection = document.getElementById('seminariosSection');
        
        function activateTab(tab, section) {
            // Desactivar todas las pestañas y secciones
            [dashboardTab, graficasTab, estudiantesTab, proyectosTab, pasantiasTab, seminariosTab].forEach(t => {
                t.classList.remove('active');
            });
            
            [dashboardSection, graficasSection, estudiantesSection, proyectosSection, pasantiasSection, seminariosSection].forEach(s => {
                s.classList.remove('active');
            });
            
            // Activar la pestaña y sección seleccionada
            tab.classList.add('active');
            section.classList.add('active');
        }
        
        dashboardTab.addEventListener('click', () => activateTab(dashboardTab, dashboardSection));
        graficasTab.addEventListener('click', () => activateTab(graficasTab, graficasSection));
        estudiantesTab.addEventListener('click', () => activateTab(estudiantesTab, estudiantesSection));
        proyectosTab.addEventListener('click', () => activateTab(proyectosTab, proyectosSection));
        pasantiasTab.addEventListener('click', () => activateTab(pasantiasTab, pasantiasSection));
        seminariosTab.addEventListener('click', () => activateTab(seminariosTab, seminariosSection));
        
        // Filtrado de tablas
        const filtroEstudiantes = document.getElementById('filtroEstudiantes');
        const filtroOpcionGrado = document.getElementById('filtroOpcionGrado');
        const filtroCiclo = document.getElementById('filtroCiclo');
        const filasEstudiantes = document.querySelectorAll('#tablaEstudiantes tbody tr');
        
        function filtrarEstudiantes() {
            const searchTerm = filtroEstudiantes.value.toLowerCase();
            const opcionGrado = filtroOpcionGrado.value;
            const ciclo = filtroCiclo.value;
            
            filasEstudiantes.forEach(fila => {
                if (fila.classList.contains('no-data')) return;
                
                const nombre = fila.dataset.nombre?.toLowerCase() || '';
                const opcion = fila.dataset.opcion || '';
                const cicloDato = fila.dataset.ciclo || '';
                
                const matchSearch = nombre.includes(searchTerm);
                const matchOpcion = opcionGrado === '' || opcion === opcionGrado;
                const matchCiclo = ciclo === '' || cicloDato === ciclo;
                
                fila.style.display = (matchSearch && matchOpcion && matchCiclo) ? '' : 'none';
            });
            
            // Mostrar mensaje si no hay resultados
            mostrarMensajeNoResultados('tablaEstudiantes', filasEstudiantes);
        }
        
        if (filtroEstudiantes) filtroEstudiantes.addEventListener('input', filtrarEstudiantes);
        if (filtroOpcionGrado) filtroOpcionGrado.addEventListener('change', filtrarEstudiantes);
        if (filtroCiclo) filtroCiclo.addEventListener('change', filtrarEstudiantes);
        
        // Filtrado de proyectos
        const filtroProyectos = document.getElementById('filtroProyectos');
        const filtroEstadoProyecto = document.getElementById('filtroEstadoProyecto');
        const filasProyectos = document.querySelectorAll('#tablaProyectos tbody tr');
        
        function filtrarProyectos() {
            const searchTerm = filtroProyectos.value.toLowerCase();
            const estado = filtroEstadoProyecto.value;
            
            filasProyectos.forEach(fila => {
                if (fila.classList.contains('no-data')) return;
                
                const titulo = fila.dataset.titulo?.toLowerCase() || '';
                const estadoDato = fila.dataset.estado || '';
                
                const matchSearch = titulo.includes(searchTerm);
                const matchEstado = estado === '' || estadoDato === estado;
                
                fila.style.display = (matchSearch && matchEstado) ? '' : 'none';
            });
            
            // Mostrar mensaje si no hay resultados
            mostrarMensajeNoResultados('tablaProyectos', filasProyectos);
        }
        
        if (filtroProyectos) filtroProyectos.addEventListener('input', filtrarProyectos);
        if (filtroEstadoProyecto) filtroEstadoProyecto.addEventListener('change', filtrarProyectos);
        
        // Filtrado de pasantías
        const filtroPasantias = document.getElementById('filtroPasantias');
        const filtroEstadoPasantia = document.getElementById('filtroEstadoPasantia');
        const filasPasantias = document.querySelectorAll('#tablaPasantias tbody tr');
        
        function filtrarPasantias() {
            const searchTerm = filtroPasantias.value.toLowerCase();
            const estado = filtroEstadoPasantia.value;
            
            filasPasantias.forEach(fila => {
                if (fila.classList.contains('no-data')) return;
                
                const titulo = fila.dataset.titulo?.toLowerCase() || '';
                const empresa = fila.dataset.empresa?.toLowerCase() || '';
                const estudiante = fila.dataset.estudiante?.toLowerCase() || '';
                const estadoDato = fila.dataset.estado || '';
                
                const matchSearch = titulo.includes(searchTerm) || 
                                   empresa.includes(searchTerm) || 
                                   estudiante.includes(searchTerm);
                const matchEstado = estado === '' || estadoDato === estado;
                
                fila.style.display = (matchSearch && matchEstado) ? '' : 'none';
            });
            
            // Mostrar mensaje si no hay resultados
            mostrarMensajeNoResultados('tablaPasantias', filasPasantias);
        }
        
        if (filtroPasantias) filtroPasantias.addEventListener('input', filtrarPasantias);
        if (filtroEstadoPasantia) filtroEstadoPasantia.addEventListener('change', filtrarPasantias);
        
        // Filtrado de seminarios
        const filtroSeminarios = document.getElementById('filtroSeminarios');
        const filtroEstadoSeminario = document.getElementById('filtroEstadoSeminario');
        const filasSeminarios = document.querySelectorAll('#tablaSeminarios tbody tr');
        
        function filtrarSeminarios() {
            const searchTerm = filtroSeminarios.value.toLowerCase();
            const estado = filtroEstadoSeminario.value;
            
            filasSeminarios.forEach(fila => {
                if (fila.classList.contains('no-data')) return;
                
                const titulo = fila.dataset.titulo?.toLowerCase() || '';
                const estadoDato = fila.dataset.estado || '';
                
                const matchSearch = titulo.includes(searchTerm);
                const matchEstado = estado === '' || estadoDato === estado;
                
                fila.style.display = (matchSearch && matchEstado) ? '' : 'none';
            });
            
            // Mostrar mensaje si no hay resultados
            mostrarMensajeNoResultados('tablaSeminarios', filasSeminarios);
        }
        
        if (filtroSeminarios) filtroSeminarios.addEventListener('input', filtrarSeminarios);
        if (filtroEstadoSeminario) filtroEstadoSeminario.addEventListener('change', filtrarSeminarios);
        
        // Función para mostrar mensaje cuando no hay resultados
        function mostrarMensajeNoResultados(tablaId, filas) {
            const hayResultadosVisibles = Array.from(filas).some(fila => 
                fila.style.display !== 'none' && !fila.classList.contains('no-data')
            );
            
            let noDataRow = document.querySelector(`#${tablaId} tbody tr.no-results`);
            
            if (!hayResultadosVisibles) {
                if (!noDataRow) {
                    const tbody = document.querySelector(`#${tablaId} tbody`);
                    noDataRow = document.createElement('tr');
                    noDataRow.className = 'no-results';
                    noDataRow.innerHTML = `<td colspan="8" class="no-data">No se encontraron resultados que coincidan con la búsqueda.</td>`;
                    tbody.appendChild(noDataRow);
                }
                noDataRow.style.display = '';
            } else if (noDataRow) {
                noDataRow.style.display = 'none';
            }
        }
        
        // Exportar tablas a Excel
        function exportarTablaExcel(tablaId, nombreArchivo) {
            const tabla = document.getElementById(tablaId);
            const wb = XLSX.utils.table_to_book(tabla, { sheet: "Datos" });
            XLSX.writeFile(wb, `${nombreArchivo}_${formatearFecha(new Date())}.xlsx`);
        }
        
        // Formatear fecha para nombres de archivo
        function formatearFecha(fecha) {
            const dia = fecha.getDate().toString().padStart(2, '0');
            const mes = (fecha.getMonth() + 1).toString().padStart(2, '0');
            const anio = fecha.getFullYear();
            return `${dia}-${mes}-${anio}`;
        }
        
        // Inicializar gráficas
        document.addEventListener('DOMContentLoaded', function() {
            // Configuración de colores para gráficas
            const coloresOpcionGrado = {
                'seminario': '#3498db',
                'proyecto': '#2ecc71',
                'pasantia': '#e74c3c'
            };
            
            const coloresCiclo = {
                'tecnico': '#3498db',
                'tecnologo': '#2ecc71',
                'profesional': '#e74c3c'
            };
            
            const coloresEstadoProyecto = {
                'propuesto': '#3498db',
                'en_revision': '#9b59b6',
                'aprobado': '#2ecc71',
                'rechazado': '#e74c3c',
                'en_proceso': '#f39c12',
                'finalizado': '#34495e'
            };
            
            const coloresEstadoPasantia = {
                'pendiente': '#f39c12',
                'aprobada': '#2ecc71',
                'rechazada': '#e74c3c',
                'en_proceso': '#3498db',
                'finalizada': '#34495e'
            };
            
            const coloresEstadoSeminario = {
                'activo': '#2ecc71',
                'finalizado': '#34495e',
                'cancelado': '#e74c3c'
            };
            
            // Preparar datos para gráficas
            const datosOpcionGrado = prepararDatosGrafica(distribucionOpcionGrado, 'opcion_grado', coloresOpcionGrado);
            const datosCiclo = prepararDatosGrafica(estudiantesPorCiclo, 'ciclo', coloresCiclo);
            const datosProyectos = prepararDatosGrafica(proyectosPorEstado, 'estado', coloresEstadoProyecto);
            const datosPasantias = prepararDatosGrafica(pasantiasPorEstado, 'estado', coloresEstadoPasantia);
            const datosSeminarios = prepararDatosGrafica(seminariosPorEstado, 'estado', coloresEstadoSeminario);
            
            // Crear gráficas del dashboard
            crearGraficaPastel('opcionGradoChart', 'Distribución por Opción de Grado', datosOpcionGrado);
            crearGraficaPastel('cicloChart', 'Estudiantes por Ciclo', datosCiclo);
            crearGraficaPastel('proyectosEstadoChart', 'Proyectos por Estado', datosProyectos);
            crearGraficaPastel('pasantiasEstadoChart', 'Pasantías por Estado', datosPasantias);
            
            // Crear gráficas de la sección de gráficas
            crearGraficaBarras('opcionGradoChartFull', 'Distribución por Opción de Grado', datosOpcionGrado);
            crearGraficaBarras('cicloChartFull', 'Estudiantes por Ciclo', datosCiclo);
            crearGraficaBarras('proyectosEstadoChartFull', 'Proyectos por Estado', datosProyectos);
            crearGraficaBarras('pasantiasEstadoChartFull', 'Pasantías por Estado', datosPasantias);
            crearGraficaBarras('seminariosEstadoChart', 'Seminarios por Estado', datosSeminarios);
            
            // Mostrar mensaje de éxito/error por 5 segundos y luego ocultarlo
            const mensajes = document.querySelectorAll('.mensaje');
            if (mensajes.length > 0) {
                setTimeout(() => {
                    mensajes.forEach(msg => {
                        msg.style.opacity = '0';
                        setTimeout(() => msg.style.display = 'none', 500);
                    });
                }, 5000);
            }
        });
        
        // Función para preparar datos para gráficas
        function prepararDatosGrafica(datos, campo, colores) {
            const etiquetas = [];
            const valores = [];
            const coloresArray = [];
            
            datos.forEach(item => {
                const etiqueta = item[campo] ? ucfirst(item[campo].replace('_', ' ')) : 'No asignado';
                etiquetas.push(etiqueta);
                valores.push(item.total);
                coloresArray.push(colores[item[campo]] || '#95a5a6');
            });
            
            return {
                etiquetas,
                valores,
                colores: coloresArray
            };
        }
        
        // Función para crear gráfica de pastel
        function crearGraficaPastel(canvasId, titulo, datos) {
            const ctx = document.getElementById(canvasId);
            if (!ctx) return;
            
            new Chart(ctx, {
                type: 'pie',
                data: {
                    labels: datos.etiquetas,
                    datasets: [{
                        data: datos.valores,
                        backgroundColor: datos.colores,
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'right',
                        },
                        title: {
                            display: false,
                            text: titulo
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    const label = context.label || '';
                                    const value = context.raw || 0;
                                    const total = context.chart.data.datasets[0].data.reduce((a, b) => a + b, 0);
                                    const percentage = Math.round((value / total) * 100);
                                    return `${label}: ${value} (${percentage}%)`;
                                }
                            }
                        }
                    }
                }
            });
        }
        
        // Función para crear gráfica de barras
        function crearGraficaBarras(canvasId, titulo, datos) {
            const ctx = document.getElementById(canvasId);
            if (!ctx) return;
            
            new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: datos.etiquetas,
                    datasets: [{
                        label: titulo,
                        data: datos.valores,
                        backgroundColor: datos.colores,
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                precision: 0
                            }
                        }
                    },
                    plugins: {
                        legend: {
                            display: false
                        },
                        title: {
                            display: false,
                            text: titulo
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    const label = context.dataset.label || '';
                                    const value = context.raw || 0;
                                    const total = context.chart.data.datasets[0].data.reduce((a, b) => a + b, 0);
                                    const percentage = Math.round((value / total) * 100);
                                    return `${value} (${percentage}%)`;
                                }
                            }
                        }
                    }
                }
            });
        }
        
        // Función para capitalizar primera letra
        function ucfirst(str) {
            if (typeof str !== 'string') return '';
            return str.charAt(0).toUpperCase() + str.slice(1);
        }
    </script>
</body>
</html>