<?php
session_start();
require_once __DIR__ . '/../../config/conexion.php';

// Inicializar variables para evitar errores
$mensaje = '';
$error = '';
$nombreUsuario = $_SESSION['nombreUsuario'] ?? 'Administrador';

// Obtener estudiantes con proyectos
$estudiantes = $conexion->query("
    SELECT u.id, u.nombre, u.email, u.codigo_estudiante, u.opcion_grado, u.nombre_proyecto 
    FROM usuarios u 
    WHERE u.rol = 'estudiante' 
    ORDER BY u.nombre
")->fetchAll(PDO::FETCH_ASSOC);

// Obtener tutores
$tutores = $conexion->query("
    SELECT u.id, u.nombre, u.email 
    FROM usuarios u 
    WHERE u.rol = 'tutor' 
    ORDER BY u.nombre
")->fetchAll(PDO::FETCH_ASSOC);

// Obtener proyectos existentes
$proyectos = $conexion->query("
    SELECT p.*, u.nombre as tutor_nombre,
    (SELECT COUNT(*) FROM proyecto_estudiante pe WHERE pe.proyecto_id = p.id) as num_estudiantes
    FROM proyectos p
    LEFT JOIN usuarios u ON p.tutor_id = u.id
    ORDER BY p.fecha_creacion DESC
")->fetchAll(PDO::FETCH_ASSOC);

// Obtener nombres de proyectos de la tabla usuarios para filtrado
$nombresProyectos = $conexion->query("
    SELECT DISTINCT nombre_proyecto 
    FROM usuarios 
    WHERE nombre_proyecto IS NOT NULL AND nombre_proyecto != ''
    ORDER BY nombre_proyecto
")->fetchAll(PDO::FETCH_COLUMN);

// Procesar la creación de un nuevo proyecto
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion'])) {
    try {
        if ($_POST['accion'] === 'crear_proyecto') {
            // Validar datos
            if (empty($_POST['titulo']) || empty($_POST['descripcion'])) {
                throw new Exception("Todos los campos marcados con * son obligatorios");
            }
            
            // Validar que se haya seleccionado al menos un estudiante
            if (!isset($_POST['estudiantes']) || count($_POST['estudiantes']) < 1) {
                throw new Exception("Debe seleccionar al menos un estudiante para el proyecto");
            }
            
            // Validar máximo 3 estudiantes
            if (count($_POST['estudiantes']) > 3) {
                throw new Exception("No puede asignar más de 3 estudiantes a un proyecto");
            }
            
            // Procesar archivo si se ha subido
            $archivo_proyecto = null;
            if (isset($_FILES['archivo_proyecto']) && $_FILES['archivo_proyecto']['error'] === UPLOAD_ERR_OK) {
                $archivo_nombre = $_FILES['archivo_proyecto']['name'];
                $archivo_tmp = $_FILES['archivo_proyecto']['tmp_name'];
                $archivo_extension = strtolower(pathinfo($archivo_nombre, PATHINFO_EXTENSION));
                
                // Validar extensión
                if (!in_array($archivo_extension, ['pdf', 'doc', 'docx'])) {
                    throw new Exception("El archivo debe ser PDF o Word (doc/docx)");
                }
                
                // Generar nombre único para el archivo
                $archivo_nuevo_nombre = uniqid() . '_' . $archivo_nombre;
                $ruta_destino = __DIR__ . '/../../uploads/proyectos/' . $archivo_nuevo_nombre;
                
                // Crear directorio si no existe
                if (!is_dir(dirname($ruta_destino))) {
                    mkdir(dirname($ruta_destino), 0755, true);
                }
                
                // Mover archivo
                if (move_uploaded_file($archivo_tmp, $ruta_destino)) {
                    $archivo_proyecto = $archivo_nuevo_nombre;
                } else {
                    throw new Exception("Error al subir el archivo. Intente nuevamente.");
                }
            }
            
            // Iniciar transacción
            $conexion->beginTransaction();
            
            // Insertar proyecto
            $stmt = $conexion->prepare("
                INSERT INTO proyectos (titulo, descripcion, archivo_proyecto, tutor_id, estado) 
                VALUES (?, ?, ?, ?, 'propuesto')
            ");
            
            $stmt->execute([
                $_POST['titulo'],
                $_POST['descripcion'],
                $archivo_proyecto,
                !empty($_POST['tutor_id']) ? $_POST['tutor_id'] : null
            ]);
            
            $proyectoId = $conexion->lastInsertId();
            
            // Asignar estudiantes al proyecto
            foreach ($_POST['estudiantes'] as $index => $estudianteId) {
                $rol = ($index === 0) ? 'lider' : 'miembro'; // El primer estudiante es el líder
                $stmt = $conexion->prepare("
                    INSERT INTO proyecto_estudiante (proyecto_id, estudiante_id, rol_en_proyecto) 
                    VALUES (?, ?, ?)
                ");
                $stmt->execute([$proyectoId, $estudianteId, $rol]);
            }
            
            // Confirmar transacción
            $conexion->commit();
            $mensaje = "Proyecto creado exitosamente";
            
            // Recargar la página para mostrar el nuevo proyecto
            header("Location: " . $_SERVER['PHP_SELF'] . "?mensaje=" . urlencode($mensaje));
            exit;
            
        } elseif ($_POST['accion'] === 'actualizar_proyecto') {
            // Validar datos
            if (empty($_POST['proyecto_id']) || empty($_POST['titulo']) || empty($_POST['descripcion'])) {
                throw new Exception("Todos los campos marcados con * son obligatorios");
            }
            
            // Procesar archivo si se ha subido
            $archivo_proyecto = null;
            $archivo_actualizado = false;
            
            if (isset($_FILES['edit_archivo_proyecto']) && $_FILES['edit_archivo_proyecto']['error'] === UPLOAD_ERR_OK) {
                $archivo_nombre = $_FILES['edit_archivo_proyecto']['name'];
                $archivo_tmp = $_FILES['edit_archivo_proyecto']['tmp_name'];
                $archivo_extension = strtolower(pathinfo($archivo_nombre, PATHINFO_EXTENSION));
                
                // Validar extensión
                if (!in_array($archivo_extension, ['pdf', 'doc', 'docx'])) {
                    throw new Exception("El archivo debe ser PDF o Word (doc/docx)");
                }
                
                // Generar nombre único para el archivo
                $archivo_nuevo_nombre = uniqid() . '_' . $archivo_nombre;
                $ruta_destino = __DIR__ . '/../../uploads/proyectos/' . $archivo_nuevo_nombre;
                
                // Crear directorio si no existe
                if (!is_dir(dirname($ruta_destino))) {
                    mkdir(dirname($ruta_destino), 0755, true);
                }
                
                // Mover archivo
                if (move_uploaded_file($archivo_tmp, $ruta_destino)) {
                    $archivo_proyecto = $archivo_nuevo_nombre;
                    $archivo_actualizado = true;
                } else {
                    throw new Exception("Error al subir el archivo. Intente nuevamente.");
                }
            }
            
            // Iniciar transacción
            $conexion->beginTransaction();
            
            // Actualizar proyecto
            if ($archivo_actualizado) {
                $stmt = $conexion->prepare("
                    UPDATE proyectos 
                    SET titulo = ?, descripcion = ?, archivo_proyecto = ?, tutor_id = ?, estado = ? 
                    WHERE id = ?
                ");
                
                $stmt->execute([
                    $_POST['titulo'],
                    $_POST['descripcion'],
                    $archivo_proyecto,
                    !empty($_POST['tutor_id']) ? $_POST['tutor_id'] : null,
                    $_POST['estado'],
                    $_POST['proyecto_id']
                ]);
            } else {
                $stmt = $conexion->prepare("
                    UPDATE proyectos 
                    SET titulo = ?, descripcion = ?, tutor_id = ?, estado = ? 
                    WHERE id = ?
                ");
                
                $stmt->execute([
                    $_POST['titulo'],
                    $_POST['descripcion'],
                    !empty($_POST['tutor_id']) ? $_POST['tutor_id'] : null,
                    $_POST['estado'],
                    $_POST['proyecto_id']
                ]);
            }
            
            // Confirmar transacción
            $conexion->commit();
            $mensaje = "Proyecto actualizado exitosamente";
            
            // Recargar la página para mostrar los cambios
            header("Location: " . $_SERVER['PHP_SELF'] . "?mensaje=" . urlencode($mensaje));
            exit;
        }
        
    } catch (Exception $e) {
        // Revertir transacción en caso de error
        if ($conexion->inTransaction()) {
            $conexion->rollBack();
        }
        $error = $e->getMessage();
    }
}

// Recuperar mensaje de la URL si existe
if (isset($_GET['mensaje'])) {
    $mensaje = $_GET['mensaje'];
}

// Cerrar conexión
$conexion = null;
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Proyectos - FET</title>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/gestion_proyectos.css">
</head>
<body>
    <div id="logo" onclick="toggleNav()">Logo</div>
    
    <nav id="navbar">
        <div class="nav-header">
            <div id="nav-logo" onclick="toggleNav()">Logo</div>
        </div>
        <ul>
            <li><a href="#">Inicio</a></li>
            <li><a href="#">Aprobación de Usuarios</a></li>
            <li><a href="#">Gestión de Usuarios</a></li>
            <li class="dropdown">
                <a href="#" class="active">Gestión de Modalidades de Grado</a>
                <ul class="dropdown-content">
                    <li><a href="#">Seminario</a></li>
                    <li><a href="#">Proyectos</a></li>
                    <li><a href="#">Pasantías</a></li>
                </ul>
            </li>
            <li><a href="#">Reportes y Estadísticas</a></li>
            <li><a href="#">Usuario: <?php echo htmlspecialchars($nombreUsuario); ?></a></li>
            <li><a href="#">Cerrar Sesión</a></li>
        </ul>
    </nav>

    <main>
        <h1>Gestión de Proyectos</h1>
        
        <?php if ($mensaje): ?>
            <div class="mensaje exito"><?= htmlspecialchars($mensaje) ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="mensaje error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        
        <div class="tabs">
            <button id="crearProyectoTab" class="active">Crear Proyecto</button>
            <button id="listarProyectosTab">Listar Proyectos</button>
        </div>
        
        <!-- Sección para crear proyectos -->
        <section id="crearProyectoSection" class="tab-content">
            <form id="formCrearProyecto" method="POST" action="" enctype="multipart/form-data">
                <input type="hidden" name="accion" value="crear_proyecto">
                
                <div class="form-group">
                    <h2>Información del Proyecto</h2>
                    <div class="form-row">
                        <div class="form-field full-width">
                            <label for="titulo">Título del Proyecto *</label>
                            <input type="text" id="titulo" name="titulo" required>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-field full-width">
                            <label for="descripcion">Descripción del Proyecto *</label>
                            <textarea id="descripcion" name="descripcion" rows="4" required></textarea>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-field full-width">
                            <label for="archivo_proyecto">Archivo del Proyecto (PDF o Word)</label>
                            <input type="file" id="archivo_proyecto" name="archivo_proyecto" accept=".pdf,.doc,.docx">
                            <p class="info-text">Formatos permitidos: PDF, DOC, DOCX. Tamaño máximo: 10MB</p>
                        </div>
                    </div>
                </div>
                
                <div class="form-group">
                    <h2>Asignación de Estudiantes</h2>
                    <p class="info-text">Seleccione de 1 a 3 estudiantes para el proyecto. El primer estudiante seleccionado será el líder del proyecto.</p>
                    
                    <div class="search-filter">
                        <input type="text" id="searchEstudiantes" placeholder="Buscar estudiantes...">
                        <select id="filterOpcionGrado">
                            <option value="">Todas las opciones de grado</option>
                            <option value="proyecto">Proyecto</option>
                            <option value="pasantia">Pasantía</option>
                            <option value="seminario">Seminario</option>
                        </select>
                    </div>
                    
                    <div class="search-filter">
                        <input type="text" id="searchProyectosEstudiantes" placeholder="Buscar por nombre de proyecto...">
                        <select id="filterNombreProyecto">
                            <option value="">Todos los proyectos</option>
                            <?php foreach ($nombresProyectos as $nombreProyecto): ?>
                                <option value="<?= strtolower(htmlspecialchars($nombreProyecto)) ?>">
                                    <?= htmlspecialchars($nombreProyecto) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="estudiantes-container">
                        <?php foreach ($estudiantes as $estudiante): ?>
                            <div class="estudiante-card" 
                                 data-nombre="<?= strtolower(htmlspecialchars($estudiante['nombre'])) ?>"
                                 data-opcion="<?= strtolower(htmlspecialchars($estudiante['opcion_grado'] ?? '')) ?>"
                                 data-proyecto="<?= strtolower(htmlspecialchars($estudiante['nombre_proyecto'] ?? '')) ?>">
                                <div class="estudiante-info">
                                    <h3><?= htmlspecialchars($estudiante['nombre']) ?></h3>
                                    <p><strong>Código:</strong> <?= htmlspecialchars($estudiante['codigo_estudiante'] ?? 'N/A') ?></p>
                                    <p><strong>Email:</strong> <?= htmlspecialchars($estudiante['email']) ?></p>
                                    <p><strong>Opción de Grado:</strong> <?= htmlspecialchars(ucfirst($estudiante['opcion_grado'] ?? 'No asignada')) ?></p>
                                    <?php if (!empty($estudiante['nombre_proyecto'])): ?>
                                        <p><strong>Proyecto:</strong> <?= htmlspecialchars($estudiante['nombre_proyecto']) ?></p>
                                    <?php endif; ?>
                                </div>
                                <div class="estudiante-select">
                                    <input type="checkbox" name="estudiantes[]" value="<?= $estudiante['id'] ?>" class="estudiante-checkbox">
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <div class="estudiantes-seleccionados">
                        <h3>Estudiantes Seleccionados: <span id="contadorEstudiantes">0/3</span></h3>
                        <ul id="listaEstudiantesSeleccionados"></ul>
                    </div>
                </div>
                
                <div class="form-group">
                    <h2>Asignación de Tutor</h2>
                    
                    <div class="search-filter">
                        <input type="text" id="searchTutores" placeholder="Buscar tutores...">
                    </div>
                    
                    <div class="tutores-container">
                        <?php foreach ($tutores as $tutor): ?>
                            <div class="tutor-card" data-nombre="<?= strtolower(htmlspecialchars($tutor['nombre'])) ?>">
                                <div class="tutor-info">
                                    <h3><?= htmlspecialchars($tutor['nombre']) ?></h3>
                                    <p><strong>Email:</strong> <?= htmlspecialchars($tutor['email']) ?></p>
                                </div>
                                <div class="tutor-select">
                                    <input type="radio" name="tutor_id" value="<?= $tutor['id'] ?>" class="tutor-radio">
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn-primary">Crear Proyecto</button>
                    <button type="reset" class="btn-secondary">Limpiar Formulario</button>
                </div>
            </form>
        </section>
        
        <!-- Sección para listar proyectos -->
        <section id="listarProyectosSection" class="tab-content" style="display: none;">
            <div class="search-filter">
                <input type="text" id="searchProyectos" placeholder="Buscar proyectos...">
                <select id="filterEstadoProyecto">
                    <option value="">Todos los estados</option>
                    <option value="propuesto">Propuesto</option>
                    <option value="en_revision">En Revisión</option>
                    <option value="aprobado">Aprobado</option>
                    <option value="rechazado">Rechazado</option>
                    <option value="en_proceso">En Proceso</option>
                    <option value="finalizado">Finalizado</option>
                </select>
            </div>
            
            <div class="proyectos-grid">
                <?php foreach ($proyectos as $proyecto): ?>
                    <div class="proyecto-card" 
                         data-titulo="<?= strtolower(htmlspecialchars($proyecto['titulo'])) ?>"
                         data-estado="<?= htmlspecialchars($proyecto['estado']) ?>">
                        <div class="proyecto-header estado-<?= htmlspecialchars($proyecto['estado']) ?>">
                            <h3><?= htmlspecialchars($proyecto['titulo']) ?></h3>
                            <span class="proyecto-estado"><?= ucfirst(str_replace('_', ' ', $proyecto['estado'])) ?></span>
                        </div>
                        <div class="proyecto-body">
                            <p><strong>Estudiantes:</strong> <?= $proyecto['num_estudiantes'] ?>/3</p>
                            <p><strong>Tutor:</strong> <?= htmlspecialchars($proyecto['tutor_nombre'] ?? 'No asignado') ?></p>
                            <p><strong>Fecha creación:</strong> <?= date('d/m/Y', strtotime($proyecto['fecha_creacion'])) ?></p>
                            <?php if (!empty($proyecto['archivo_proyecto'])): ?>
                                <p><strong>Archivo:</strong> <a href="/uploads/proyectos/<?= htmlspecialchars($proyecto['archivo_proyecto']) ?>" target="_blank" class="archivo-link">Ver archivo</a></p>
                            <?php endif; ?>
                            <div class="proyecto-descripcion">
                                <?= nl2br(htmlspecialchars(substr($proyecto['descripcion'], 0, 100))) ?>
                                <?= (strlen($proyecto['descripcion']) > 100) ? '...' : '' ?>
                            </div>
                        </div>
                        <div class="proyecto-footer">
                            <button class="btn-ver" onclick="verProyecto(<?= $proyecto['id'] ?>)">Ver Detalles</button>
                            <button class="btn-editar" onclick="editarProyecto(<?= $proyecto['id'] ?>)">Editar</button>
                        </div>
                    </div>
                <?php endforeach; ?>
                
                <?php if (empty($proyectos)): ?>
                    <div class="no-proyectos">
                        <p>No hay proyectos registrados. Cree un nuevo proyecto en la pestaña "Crear Proyecto".</p>
                    </div>
                <?php endif; ?>
            </div>
        </section>
    </main>
    
    <!-- Modal para ver detalles del proyecto -->
    <div id="modalVerProyecto" class="modal">
        <div class="modal-content">
            <span class="close" onclick="cerrarModal('modalVerProyecto')">&times;</span>
            <h2>Detalles del Proyecto</h2>
            <div id="detallesProyecto"></div>
        </div>
    </div>
    
    <!-- Modal para editar proyecto -->
    <div id="modalEditarProyecto" class="modal">
        <div class="modal-content">
            <span class="close" onclick="cerrarModal('modalEditarProyecto')">&times;</span>
            <h2>Editar Proyecto</h2>
            <form id="formEditarProyecto" method="POST" action="" enctype="multipart/form-data">
                <input type="hidden" name="accion" value="actualizar_proyecto">
                <input type="hidden" name="proyecto_id" id="edit_proyecto_id">
                
                <div class="form-row">
                    <div class="form-field full-width">
                        <label for="edit_titulo">Título del Proyecto *</label>
                        <input type="text" id="edit_titulo" name="titulo" required>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-field">
                        <label for="edit_estado">Estado del Proyecto *</label>
                        <select id="edit_estado" name="estado" required>
                            <option value="propuesto">Propuesto</option>
                            <option value="en_revision">En Revisión</option>
                            <option value="aprobado">Aprobado</option>
                            <option value="rechazado">Rechazado</option>
                            <option value="en_proceso">En Proceso</option>
                            <option value="finalizado">Finalizado</option>
                        </select>
                    </div>
                    <div class="form-field">
                        <label for="edit_tutor_id">Tutor Asignado</label>
                        <select id="edit_tutor_id" name="tutor_id">
                            <option value="">Sin tutor asignado</option>
                            <?php foreach ($tutores as $tutor): ?>
                                <option value="<?= $tutor['id'] ?>"><?= htmlspecialchars($tutor['nombre']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-field full-width">
                        <label for="edit_descripcion">Descripción del Proyecto *</label>
                        <textarea id="edit_descripcion" name="descripcion" rows="4" required></textarea>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-field full-width">
                        <label for="edit_archivo_proyecto">Archivo del Proyecto (PDF o Word)</label>
                        <input type="file" id="edit_archivo_proyecto" name="edit_archivo_proyecto" accept=".pdf,.doc,.docx">
                        <p class="info-text">Formatos permitidos: PDF, DOC, DOCX. Tamaño máximo: 10MB</p>
                        <div id="archivo_actual" class="archivo-actual"></div>
                    </div>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn-primary">Guardar Cambios</button>
                    <button type="button" class="btn-secondary" onclick="cerrarModal('modalEditarProyecto')">Cancelar</button>
                </div>
            </form>
        </div>
    </div>

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
        
        // Manejo de pestañas
        const crearProyectoTab = document.getElementById('crearProyectoTab');
        const listarProyectosTab = document.getElementById('listarProyectosTab');
        const crearProyectoSection = document.getElementById('crearProyectoSection');
        const listarProyectosSection = document.getElementById('listarProyectosSection');
        
        crearProyectoTab.addEventListener('click', () => {
            crearProyectoTab.classList.add('active');
            listarProyectosTab.classList.remove('active');
            crearProyectoSection.style.display = 'block';
            listarProyectosSection.style.display = 'none';
        });
        
        listarProyectosTab.addEventListener('click', () => {
            listarProyectosTab.classList.add('active');
            crearProyectoTab.classList.remove('active');
            listarProyectosSection.style.display = 'block';
            crearProyectoSection.style.display = 'none';
        });
        
        // Búsqueda y filtrado de estudiantes
        const searchEstudiantes = document.getElementById('searchEstudiantes');
        const searchProyectosEstudiantes = document.getElementById('searchProyectosEstudiantes');
        const filterOpcionGrado = document.getElementById('filterOpcionGrado');
        const filterNombreProyecto = document.getElementById('filterNombreProyecto');
        const estudianteCards = document.querySelectorAll('.estudiante-card');
        
        searchEstudiantes.addEventListener('input', filtrarEstudiantes);
        searchProyectosEstudiantes.addEventListener('input', filtrarEstudiantes);
        filterOpcionGrado.addEventListener('change', filtrarEstudiantes);
        filterNombreProyecto.addEventListener('change', filtrarEstudiantes);
        
        function filtrarEstudiantes() {
            const searchTerm = searchEstudiantes.value.toLowerCase();
            const searchProyectoTerm = searchProyectosEstudiantes.value.toLowerCase();
            const opcionGrado = filterOpcionGrado.value.toLowerCase();
            const nombreProyecto = filterNombreProyecto.value.toLowerCase();
            
            estudianteCards.forEach(card => {
                const nombre = card.dataset.nombre;
                const opcion = card.dataset.opcion;
                const proyecto = card.dataset.proyecto;
                
                const matchSearch = nombre.includes(searchTerm);
                const matchProyectoSearch = proyecto.includes(searchProyectoTerm);
                const matchOpcion = opcionGrado === '' || opcion === opcionGrado;
                const matchProyecto = nombreProyecto === '' || proyecto === nombreProyecto;
                
                card.style.display = (matchSearch && matchOpcion && matchProyecto && matchProyectoSearch) ? 'flex' : 'none';
            });
        }
        
        // Búsqueda de tutores
        const searchTutores = document.getElementById('searchTutores');
        const tutorCards = document.querySelectorAll('.tutor-card');
        
        searchTutores.addEventListener('input', () => {
            const searchTerm = searchTutores.value.toLowerCase();
            
            tutorCards.forEach(card => {
                const nombre = card.dataset.nombre;
                card.style.display = nombre.includes(searchTerm) ? 'flex' : 'none';
            });
        });
        
        // Búsqueda y filtrado de proyectos
        const searchProyectos = document.getElementById('searchProyectos');
        const filterEstadoProyecto = document.getElementById('filterEstadoProyecto');
        const proyectoCards = document.querySelectorAll('.proyecto-card');
        
        searchProyectos.addEventListener('input', filtrarProyectos);
        filterEstadoProyecto.addEventListener('change', filtrarProyectos);
        
        function filtrarProyectos() {
            const searchTerm = searchProyectos.value.toLowerCase();
            const estadoProyecto = filterEstadoProyecto.value;
            
            proyectoCards.forEach(card => {
                const titulo = card.dataset.titulo;
                const estado = card.dataset.estado;
                
                const matchSearch = titulo.includes(searchTerm);
                const matchEstado = estadoProyecto === '' || estado === estadoProyecto;
                
                card.style.display = (matchSearch && matchEstado) ? 'flex' : 'none';
            });
        }
        
        // Control de selección de estudiantes
        const estudianteCheckboxes = document.querySelectorAll('.estudiante-checkbox');
        const contadorEstudiantes = document.getElementById('contadorEstudiantes');
        const listaEstudiantesSeleccionados = document.getElementById('listaEstudiantesSeleccionados');
        
        estudianteCheckboxes.forEach(checkbox => {
            checkbox.addEventListener('change', actualizarEstudiantesSeleccionados);
        });
        
        function actualizarEstudiantesSeleccionados() {
            const seleccionados = document.querySelectorAll('.estudiante-checkbox:checked');
            const numSeleccionados = seleccionados.length;
            
            // Actualizar contador
            contadorEstudiantes.textContent = `${numSeleccionados}/3`;
            
            // Limitar a 3 estudiantes
            if (numSeleccionados > 3) {
                alert('No puede seleccionar más de 3 estudiantes para un proyecto');
                this.checked = false;
                actualizarEstudiantesSeleccionados();
                return;
            }
            
            // Actualizar lista de estudiantes seleccionados
            listaEstudiantesSeleccionados.innerHTML = '';
            
            seleccionados.forEach((checkbox, index) => {
                const estudianteCard = checkbox.closest('.estudiante-card');
                const nombreEstudiante = estudianteCard.querySelector('h3').textContent;
                
                const li = document.createElement('li');
                li.textContent = `${index === 0 ? '👑 ' : ''}${nombreEstudiante}${index === 0 ? ' (Líder)' : ''}`;
                listaEstudiantesSeleccionados.appendChild(li);
            });
        }
        
        // Funciones para modales
        function verProyecto(proyectoId) {
            // Aquí normalmente harías una petición AJAX para obtener los detalles del proyecto
            // Por simplicidad, simulamos que ya tenemos los datos
            fetch(`/api/proyectos/${proyectoId}`)
                .then(response => response.json())
                .then(proyecto => {
                    const detallesProyecto = document.getElementById('detallesProyecto');
                    
                    // Construir HTML con los detalles del proyecto
                    let html = `
                        <div class="proyecto-detalle">
                            <div class="proyecto-header estado-${proyecto.estado}">
                                <h3>${proyecto.titulo}</h3>
                                <span class="proyecto-estado">${proyecto.estado.replace('_', ' ')}</span>
                            </div>
                            <div class="proyecto-info">
                                <p><strong>Fecha de creación:</strong> ${new Date(proyecto.fecha_creacion).toLocaleDateString()}</p>
                                <p><strong>Última actualización:</strong> ${proyecto.fecha_actualizacion ? new Date(proyecto.fecha_actualizacion).toLocaleDateString() : 'Sin actualizar'}</p>
                                <p><strong>Tutor asignado:</strong> ${proyecto.tutor_nombre || 'No asignado'}</p>
                                
                                ${proyecto.archivo_proyecto ? 
                                    `<p><strong>Archivo del proyecto:</strong> <a href="/uploads/proyectos/${proyecto.archivo_proyecto}" target="_blank" class="archivo-link">Descargar archivo</a></p>` : 
                                    '<p><strong>Archivo del proyecto:</strong> No hay archivo adjunto</p>'
                                }
                                
                                <h4>Descripción:</h4>
                                <div class="descripcion-completa">${proyecto.descripcion.replace(/\n/g, '<br>')}</div>
                                
                                <h4>Estudiantes asignados:</h4>
                                <ul class="estudiantes-lista">
                                    ${proyecto.estudiantes.map(est => `<li>${est.rol_en_proyecto === 'lider' ? '👑 ' : ''}${est.nombre} (${est.email})</li>`).join('')}
                                </ul>
                                
                                <h4>Avances del proyecto:</h4>
                                ${proyecto.avances.length > 0 ? 
                                    `<ul class="avances-lista">
                                        ${proyecto.avances.map(av => `
                                            <li>
                                                <strong>${av.titulo}</strong> - ${new Date(av.fecha_registro).toLocaleDateString()}
                                                <div>${av.descripcion}</div>
                                                <div class="progreso-barra">
                                                    <div class="progreso" style="width: ${av.porcentaje_avance}%"></div>
                                                    <span>${av.porcentaje_avance}%</span>
                                                </div>
                                            </li>
                                        `).join('')}
                                    </ul>` : 
                                    '<p>No hay avances registrados</p>'
                                }
                                
                                <h4>Comentarios:</h4>
                                ${proyecto.comentarios.length > 0 ? 
                                    `<ul class="comentarios-lista">
                                        ${proyecto.comentarios.map(com => `
                                            <li>
                                                <strong>${com.nombre_usuario}</strong> - ${new Date(com.fecha_comentario).toLocaleDateString()}
                                                <div>${com.comentario}</div>
                                            </li>
                                        `).join('')}
                                    </ul>` : 
                                    '<p>No hay comentarios</p>'
                                }
                            </div>
                        </div>
                    `;
                    
                    detallesProyecto.innerHTML = html;
                    abrirModal('modalVerProyecto');
                })
                .catch(error => {
                    console.error('Error al obtener detalles del proyecto:', error);
                    // Para fines de demostración, mostrar datos de ejemplo si falla la petición
                    mostrarDatosEjemplo(proyectoId);
                });
        }
        
        // Función para mostrar datos de ejemplo (solo para demostración)
        function mostrarDatosEjemplo(proyectoId) {
            const proyectoCard = document.querySelector(`.proyecto-card[data-id="${proyectoId}"]`);
            if (!proyectoCard) return;
            
            const titulo = proyectoCard.querySelector('h3').textContent;
            const estado = proyectoCard.dataset.estado;
            
            const detallesProyecto = document.getElementById('detallesProyecto');
            detallesProyecto.innerHTML = `
                <div class="proyecto-detalle">
                    <div class="proyecto-header estado-${estado}">
                        <h3>${titulo}</h3>
                        <span class="proyecto-estado">${estado.replace('_', ' ')}</span>
                    </div>
                    <div class="proyecto-info">
                        <p><strong>Fecha de creación:</strong> ${new Date().toLocaleDateString()}</p>
                        <p><strong>Tutor asignado:</strong> No disponible en modo demo</p>
                        
                        <h4>Descripción:</h4>
                        <div class="descripcion-completa">Información no disponible en modo demo</div>
                        
                        <h4>Estudiantes asignados:</h4>
                        <p>Información no disponible en modo demo</p>
                        
                        <h4>Avances del proyecto:</h4>
                        <p>Información no disponible en modo demo</p>
                        
                        <h4>Comentarios:</h4>
                        <p>Información no disponible en modo demo</p>
                    </div>
                </div>
            `;
            
            abrirModal('modalVerProyecto');
        }
        
        function editarProyecto(proyectoId) {
            // Aquí normalmente harías una petición AJAX para obtener los datos del proyecto
            // Por simplicidad, simulamos que ya tenemos los datos
            fetch(`/api/proyectos/${proyectoId}`)
                .then(response => response.json())
                .then(proyecto => {
                    document.getElementById('edit_proyecto_id').value = proyecto.id;
                    document.getElementById('edit_titulo').value = proyecto.titulo;
                    document.getElementById('edit_descripcion').value = proyecto.descripcion;
                    document.getElementById('edit_estado').value = proyecto.estado;
                    document.getElementById('edit_tutor_id').value = proyecto.tutor_id || '';
                    
                    // Mostrar información del archivo actual si existe
                    const archivoActualDiv = document.getElementById('archivo_actual');
                    if (proyecto.archivo_proyecto) {
                        archivoActualDiv.innerHTML = `
                            <p>Archivo actual: <a href="/uploads/proyectos/${proyecto.archivo_proyecto}" target="_blank">${proyecto.archivo_proyecto}</a></p>
                            <p>Si sube un nuevo archivo, reemplazará al actual.</p>
                        `;
                    } else {
                        archivoActualDiv.innerHTML = '<p>No hay archivo adjunto actualmente.</p>';
                    }
                    
                    abrirModal('modalEditarProyecto');
                })
                .catch(error => {
                    console.error('Error al obtener datos del proyecto:', error);
                    alert('Error al cargar los datos del proyecto. Intente nuevamente.');
                });
        }
        
        function abrirModal(modalId) {
            document.getElementById(modalId).style.display = 'block';
            document.body.style.overflow = 'hidden'; // Evitar scroll en el fondo
        }
        
        function cerrarModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
            document.body.style.overflow = 'auto'; // Restaurar scroll
        }
        
        // Cerrar modal al hacer clic fuera del contenido
        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                event.target.style.display = 'none';
                document.body.style.overflow = 'auto';
            }
        }
        
        // Inicializar
        document.addEventListener('DOMContentLoaded', function() {
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
    </script>
</body>
</html>