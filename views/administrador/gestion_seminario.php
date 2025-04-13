<?php
session_start();
require_once __DIR__ . '/../../config/conexion.php';

// Manejar solicitudes API
if (isset($_GET['api'])) {
    header('Content-Type: application/json');
    
    // API para obtener detalles completos del seminario
    if ($_GET['api'] === 'details' && isset($_GET['id'])) {
        try {
            // Obtener detalles del seminario
            $stmt = $conexion->prepare("
                SELECT s.*, u.nombre as tutor_nombre,
                (SELECT COUNT(*) FROM inscripciones_seminario WHERE seminario_id = s.id) as num_inscritos
                FROM seminarios s
                LEFT JOIN usuarios u ON s.tutor_id = u.id
                WHERE s.id = ?
            ");
            $stmt->execute([$_GET['id']]);
            $seminario = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$seminario) {
                http_response_code(404);
                echo json_encode(['error' => 'Seminario no encontrado']);
                exit;
            }

            // Obtener estudiantes inscritos
            $stmt = $conexion->prepare("
                SELECT i.*, u.nombre, u.email, u.codigo_estudiante
                FROM inscripciones_seminario i
                JOIN usuarios u ON i.estudiante_id = u.id
                WHERE i.seminario_id = ?
                ORDER BY u.nombre
            ");
            $stmt->execute([$_GET['id']]);
            $estudiantes = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Formatear fechas
            $seminario['fecha'] = date('d/m/Y', strtotime($seminario['fecha']));
            $seminario['hora'] = date('H:i', strtotime($seminario['hora']));
            $seminario['fecha_creacion'] = date('d/m/Y H:i', strtotime($seminario['fecha_creacion']));

            // Preparar respuesta
            $response = [
                'id' => $seminario['id'],
                'titulo' => $seminario['titulo'],
                'descripcion' => nl2br(htmlspecialchars($seminario['descripcion'])),
                'fecha' => $seminario['fecha'],
                'hora' => $seminario['hora'],
                'modalidad' => ucfirst($seminario['modalidad']),
                'lugar' => $seminario['lugar'],
                'cupos' => $seminario['cupos'],
                'inscritos' => $seminario['num_inscritos'],
                'tutor_nombre' => $seminario['tutor_nombre'],
                'archivo_guia' => $seminario['archivo_guia'],
                'estado' => ucfirst($seminario['estado']),
                'fecha_creacion' => $seminario['fecha_creacion'],
                'estudiantes' => array_map(function($est) {
                    return [
                        'nombre' => $est['nombre'],
                        'email' => $est['email'],
                        'codigo' => $est['codigo_estudiante'],
                        'estado' => ucfirst($est['estado']),
                        'asistencia' => $est['asistencia'],
                        'nota' => $est['nota']
                    ];
                }, $estudiantes)
            ];

            echo json_encode($response);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Error al obtener los detalles del seminario']);
        }
        exit;
    }
    
    // API para obtener datos básicos del seminario para edición
    if ($_GET['api'] === 'edit' && isset($_GET['id'])) {
        try {
            $stmt = $conexion->prepare("
                SELECT s.*, u.nombre as tutor_nombre
                FROM seminarios s
                LEFT JOIN usuarios u ON s.tutor_id = u.id
                WHERE s.id = ?
            ");
            $stmt->execute([$_GET['id']]);
            $seminario = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$seminario) {
                http_response_code(404);
                echo json_encode(['error' => 'Seminario no encontrado']);
                exit;
            }

            // Formatear fecha para el input type="date"
            $seminario['fecha'] = date('Y-m-d', strtotime($seminario['fecha']));
            // Formatear hora para el input type="time"
            $seminario['hora'] = date('H:i', strtotime($seminario['hora']));

            echo json_encode($seminario);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Error al obtener los datos del seminario']);
        }
        exit;
    }
    
    // Si llegamos aquí, la API solicitada no existe
    http_response_code(404);
    echo json_encode(['error' => 'Endpoint no encontrado']);
    exit;
}

// Inicializar variables
$mensaje = '';
$error = '';
$nombreUsuario = $_SESSION['nombreUsuario'] ?? 'Administrador';

// Obtener tutores para el selector
$tutores = $conexion->query("
    SELECT id, nombre, email 
    FROM usuarios 
    WHERE rol = 'tutor' 
    ORDER BY nombre
")->fetchAll(PDO::FETCH_ASSOC);

// Obtener seminarios existentes
$seminarios = $conexion->query("
    SELECT s.*, u.nombre as tutor_nombre,
    (SELECT COUNT(*) FROM inscripciones_seminario WHERE seminario_id = s.id) as num_inscritos
    FROM seminarios s
    LEFT JOIN usuarios u ON s.tutor_id = u.id
    ORDER BY s.fecha DESC, s.hora DESC
")->fetchAll(PDO::FETCH_ASSOC);

// Procesar formularios
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion'])) {
    try {
        if ($_POST['accion'] === 'crear_seminario') {
            // Validar datos
            if (empty($_POST['titulo']) || empty($_POST['descripcion']) || empty($_POST['fecha']) || 
                empty($_POST['hora']) || empty($_POST['modalidad']) || empty($_POST['lugar'])) {
                throw new Exception("Todos los campos marcados con * son obligatorios");
            }

            // Procesar archivo si se ha subido
            $archivo_guia = null;
            if (isset($_FILES['archivo_guia']) && $_FILES['archivo_guia']['error'] === UPLOAD_ERR_OK) {
                $archivo_nombre = $_FILES['archivo_guia']['name'];
                $archivo_tmp = $_FILES['archivo_guia']['tmp_name'];
                $archivo_extension = strtolower(pathinfo($archivo_nombre, PATHINFO_EXTENSION));
                
                if (!in_array($archivo_extension, ['pdf', 'doc', 'docx'])) {
                    throw new Exception("El archivo debe ser PDF o Word (doc/docx)");
                }
                
                $archivo_nuevo_nombre = uniqid() . '_' . $archivo_nombre;
                $ruta_destino = __DIR__ . '/../../uploads/seminarios/' . $archivo_nuevo_nombre;
                
                if (!is_dir(dirname($ruta_destino))) {
                    mkdir(dirname($ruta_destino), 0755, true);
                }
                
                if (move_uploaded_file($archivo_tmp, $ruta_destino)) {
                    $archivo_guia = $archivo_nuevo_nombre;
                } else {
                    throw new Exception("Error al subir el archivo. Intente nuevamente.");
                }
            }

            // Insertar seminario
            $stmt = $conexion->prepare("
                INSERT INTO seminarios (titulo, descripcion, fecha, hora, modalidad, lugar, cupos, 
                                      tutor_id, archivo_guia, estado) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'activo')
            ");
            
            $stmt->execute([
                $_POST['titulo'],
                $_POST['descripcion'],
                $_POST['fecha'],
                $_POST['hora'],
                $_POST['modalidad'],
                $_POST['lugar'],
                $_POST['cupos'] ?? 30,
                !empty($_POST['tutor_id']) ? $_POST['tutor_id'] : null,
                $archivo_guia
            ]);
            
            $mensaje = "Seminario creado exitosamente";
            
        } elseif ($_POST['accion'] === 'actualizar_seminario') {
            if (empty($_POST['seminario_id'])) {
                throw new Exception("ID de seminario no válido");
            }

            // Procesar archivo si se ha subido uno nuevo
            $archivo_guia = null;
            $archivo_actualizado = false;
            
            if (isset($_FILES['edit_archivo_guia']) && $_FILES['edit_archivo_guia']['error'] === UPLOAD_ERR_OK) {
                $archivo_nombre = $_FILES['edit_archivo_guia']['name'];
                $archivo_tmp = $_FILES['edit_archivo_guia']['tmp_name'];
                $archivo_extension = strtolower(pathinfo($archivo_nombre, PATHINFO_EXTENSION));
                
                if (!in_array($archivo_extension, ['pdf', 'doc', 'docx'])) {
                    throw new Exception("El archivo debe ser PDF o Word (doc/docx)");
                }
                
                $archivo_nuevo_nombre = uniqid() . '_' . $archivo_nombre;
                $ruta_destino = __DIR__ . '/../../uploads/seminarios/' . $archivo_nuevo_nombre;
                
                if (!is_dir(dirname($ruta_destino))) {
                    mkdir(dirname($ruta_destino), 0755, true);
                }
                
                if (move_uploaded_file($archivo_tmp, $ruta_destino)) {
                    $archivo_guia = $archivo_nuevo_nombre;
                    $archivo_actualizado = true;
                } else {
                    throw new Exception("Error al subir el archivo. Intente nuevamente.");
                }
            }

            // Actualizar seminario
            if ($archivo_actualizado) {
                $stmt = $conexion->prepare("
                    UPDATE seminarios 
                    SET titulo = ?, descripcion = ?, fecha = ?, hora = ?, modalidad = ?, 
                        lugar = ?, cupos = ?, tutor_id = ?, archivo_guia = ?, estado = ? 
                    WHERE id = ?
                ");
                
                $stmt->execute([
                    $_POST['titulo'],
                    $_POST['descripcion'],
                    $_POST['fecha'],
                    $_POST['hora'],
                    $_POST['modalidad'],
                    $_POST['lugar'],
                    $_POST['cupos'],
                    !empty($_POST['tutor_id']) ? $_POST['tutor_id'] : null,
                    $archivo_guia,
                    $_POST['estado'],
                    $_POST['seminario_id']
                ]);
            } else {
                $stmt = $conexion->prepare("
                    UPDATE seminarios 
                    SET titulo = ?, descripcion = ?, fecha = ?, hora = ?, modalidad = ?, 
                        lugar = ?, cupos = ?, tutor_id = ?, estado = ? 
                    WHERE id = ?
                ");
                
                $stmt->execute([
                    $_POST['titulo'],
                    $_POST['descripcion'],
                    $_POST['fecha'],
                    $_POST['hora'],
                    $_POST['modalidad'],
                    $_POST['lugar'],
                    $_POST['cupos'],
                    !empty($_POST['tutor_id']) ? $_POST['tutor_id'] : null,
                    $_POST['estado'],
                    $_POST['seminario_id']
                ]);
            }
            
            $mensaje = "Seminario actualizado exitosamente";
            
        } elseif ($_POST['accion'] === 'eliminar_seminario') {
            if (empty($_POST['seminario_id'])) {
                throw new Exception("ID de seminario no válido");
            }
            
            // Eliminar inscripciones
            $stmt = $conexion->prepare("DELETE FROM inscripciones_seminario WHERE seminario_id = ?");
            $stmt->execute([$_POST['seminario_id']]);
            
            // Eliminar seminario
            $stmt = $conexion->prepare("DELETE FROM seminarios WHERE id = ?");
            $stmt->execute([$_POST['seminario_id']]);
            
            $mensaje = "Seminario eliminado exitosamente";
        }
        
        // Recargar la página para mostrar los cambios
        header("Location: " . $_SERVER['PHP_SELF'] . "?mensaje=" . urlencode($mensaje));
        exit;
        
    } catch (Exception $e) {
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
    <title>Gestión de Seminarios - FET</title>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/gestion_seminario.css">
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
                    <li><a href="#" class="active">Seminario</a></li>
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
        <h1>Gestión de Seminarios</h1>
        
        <?php if ($mensaje): ?>
            <div class="mensaje exito"><?= htmlspecialchars($mensaje) ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="mensaje error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        
        <div class="tabs">
            <button id="crearSeminarioTab" class="active">Crear Seminario</button>
            <button id="listarSeminariosTab">Listar Seminarios</button>
        </div>
        
        <!-- Sección para crear seminarios -->
        <section id="crearSeminarioSection" class="tab-content">
            <form id="formCrearSeminario" method="POST" action="" enctype="multipart/form-data">
                <input type="hidden" name="accion" value="crear_seminario">
                
                <div class="form-group">
                    <h2>Información del Seminario</h2>
                    
                    <div class="form-row">
                        <div class="form-field full-width">
                            <label for="titulo">Nombre o Tema del Seminario *</label>
                            <input type="text" id="titulo" name="titulo" required>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-field full-width">
                            <label for="descripcion">Descripción *</label>
                            <textarea id="descripcion" name="descripcion" rows="4" required></textarea>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-field">
                            <label for="fecha">Fecha *</label>
                            <input type="date" id="fecha" name="fecha" required>
                        </div>
                        <div class="form-field">
                            <label for="hora">Hora *</label>
                            <input type="time" id="hora" name="hora" required>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-field">
                            <label for="modalidad">Modalidad *</label>
                            <select id="modalidad" name="modalidad" required>
                                <option value="">Seleccione una modalidad</option>
                                <option value="presencial">Presencial</option>
                                <option value="virtual">Virtual</option>
                            </select>
                        </div>
                        <div class="form-field">
                            <label for="lugar">Lugar o Enlace *</label>
                            <input type="text" id="lugar" name="lugar" required>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-field">
                            <label for="cupos">Cupos Disponibles</label>
                            <input type="number" id="cupos" name="cupos" value="30" min="1">
                        </div>
                        <div class="form-field">
                            <label for="tutor_id">Tutor Encargado</label>
                            <select id="tutor_id" name="tutor_id">
                                <option value="">Seleccione un tutor</option>
                                <?php foreach ($tutores as $tutor): ?>
                                    <option value="<?= $tutor['id'] ?>"><?= htmlspecialchars($tutor['nombre']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-field full-width">
                            <label for="archivo_guia">Archivo Guía o Material (PDF o Word)</label>
                            <input type="file" id="archivo_guia" name="archivo_guia" accept=".pdf,.doc,.docx">
                            <p class="info-text">Formatos permitidos: PDF, DOC, DOCX. Tamaño máximo: 10MB</p>
                        </div>
                    </div>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn-primary">Crear Seminario</button>
                    <button type="reset" class="btn-secondary">Limpiar Formulario</button>
                </div>
            </form>
        </section>
        
        <!-- Sección para listar seminarios -->
        <section id="listarSeminariosSection" class="tab-content" style="display: none;">
            <div class="search-filter">
                <input type="text" id="searchSeminarios" placeholder="Buscar seminarios...">
                <select id="filterEstadoSeminario">
                    <option value="">Todos los estados</option>
                    <option value="activo">Activo</option>
                    <option value="finalizado">Finalizado</option>
                    <option value="cancelado">Cancelado</option>
                </select>
                <select id="filterModalidad">
                    <option value="">Todas las modalidades</option>
                    <option value="presencial">Presencial</option>
                    <option value="virtual">Virtual</option>
                </select>
            </div>
            
            <div class="seminarios-grid">
                <?php foreach ($seminarios as $seminario): ?>
                    <div class="seminario-card" 
                         data-id="<?= $seminario['id'] ?>"
                         data-titulo="<?= strtolower(htmlspecialchars($seminario['titulo'])) ?>"
                         data-estado="<?= htmlspecialchars($seminario['estado']) ?>"
                         data-modalidad="<?= htmlspecialchars($seminario['modalidad']) ?>">
                        <div class="seminario-header estado-<?= htmlspecialchars($seminario['estado']) ?>">
                            <h3><?= htmlspecialchars($seminario['titulo']) ?></h3>
                            <span class="seminario-estado"><?= ucfirst($seminario['estado']) ?></span>
                        </div>
                        <div class="seminario-body">
                            <p><strong>Fecha:</strong> <?= date('d/m/Y', strtotime($seminario['fecha'])) ?></p>
                            <p><strong>Hora:</strong> <?= date('H:i', strtotime($seminario['hora'])) ?></p>
                            <p><strong>Modalidad:</strong> <?= ucfirst($seminario['modalidad']) ?></p>
                            <p><strong>Lugar:</strong> <?= htmlspecialchars($seminario['lugar']) ?></p>
                            <p><strong>Cupos:</strong> <?= $seminario['num_inscritos'] ?>/<?= $seminario['cupos'] ?></p>
                            <p><strong>Tutor:</strong> <?= htmlspecialchars($seminario['tutor_nombre'] ?? 'No asignado') ?></p>
                            <?php if (!empty($seminario['archivo_guia'])): ?>
                                <p><strong>Material:</strong> <a href="/uploads/seminarios/<?= htmlspecialchars($seminario['archivo_guia']) ?>" target="_blank" class="archivo-link">Ver material</a></p>
                            <?php endif; ?>
                        </div>
                        <div class="seminario-footer">
                            <button class="btn-ver" onclick="verSeminario(<?= $seminario['id'] ?>)">Ver Detalles</button>
                            <button class="btn-editar" onclick="editarSeminario(<?= $seminario['id'] ?>)">Editar</button>
                        </div>
                    </div>
                <?php endforeach; ?>
                
                <?php if (empty($seminarios)): ?>
                    <div class="no-seminarios">
                        <p>No hay seminarios registrados. Cree un nuevo seminario en la pestaña "Crear Seminario".</p>
                    </div>
                <?php endif; ?>
            </div>
        </section>
    </main>
    
    <!-- Modal para ver detalles del seminario -->
    <div id="modalVerSeminario" class="modal">
        <div class="modal-content">
            <span class="close" onclick="cerrarModal('modalVerSeminario')">&times;</span>
            <h2>Detalles del Seminario</h2>
            <div id="detallesSeminario"></div>
        </div>
    </div>
    
    <!-- Modal para editar seminario -->
    <div id="modalEditarSeminario" class="modal">
        <div class="modal-content">
            <span class="close" onclick="cerrarModal('modalEditarSeminario')">&times;</span>
            <h2>Editar Seminario</h2>
            <form id="formEditarSeminario" method="POST" action="" enctype="multipart/form-data">
                <input type="hidden" name="accion" value="actualizar_seminario">
                <input type="hidden" name="seminario_id" id="edit_seminario_id">
                
                <div class="form-row">
                    <div class="form-field full-width">
                        <label for="edit_titulo">Nombre o Tema del Seminario *</label>
                        <input type="text" id="edit_titulo" name="titulo" required>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-field full-width">
                        <label for="edit_descripcion">Descripción *</label>
                        <textarea id="edit_descripcion" name="descripcion" rows="4" required></textarea>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-field">
                        <label for="edit_fecha">Fecha *</label>
                        <input type="date" id="edit_fecha" name="fecha" required>
                    </div>
                    <div class="form-field">
                        <label for="edit_hora">Hora *</label>
                        <input type="time" id="edit_hora" name="hora" required>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-field">
                        <label for="edit_modalidad">Modalidad *</label>
                        <select id="edit_modalidad" name="modalidad" required>
                            <option value="presencial">Presencial</option>
                            <option value="virtual">Virtual</option>
                        </select>
                    </div>
                    <div class="form-field">
                        <label for="edit_lugar">Lugar o Enlace *</label>
                        <input type="text" id="edit_lugar" name="lugar" required>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-field">
                        <label for="edit_cupos">Cupos Disponibles</label>
                        <input type="number" id="edit_cupos" name="cupos" min="1">
                    </div>
                    <div class="form-field">
                        <label for="edit_tutor_id">Tutor Encargado</label>
                        <select id="edit_tutor_id" name="tutor_id">
                            <option value="">Sin tutor asignado</option>
                            <?php foreach ($tutores as $tutor): ?>
                                <option value="<?= $tutor['id'] ?>"><?= htmlspecialchars($tutor['nombre']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-field">
                        <label for="edit_estado">Estado del Seminario *</label>
                        <select id="edit_estado" name="estado" required>
                            <option value="activo">Activo</option>
                            <option value="finalizado">Finalizado</option>
                            <option value="cancelado">Cancelado</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-field full-width">
                        <label for="edit_archivo_guia">Archivo Guía o Material (PDF o Word)</label>
                        <input type="file" id="edit_archivo_guia" name="edit_archivo_guia" accept=".pdf,.doc,.docx">
                        <p class="info-text">Formatos permitidos: PDF, DOC, DOCX. Tamaño máximo: 10MB</p>
                        <div id="archivo_actual" class="archivo-actual"></div>
                    </div>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn-primary">Guardar Cambios</button>
                    <button type="button" class="btn-secondary" onclick="cerrarModal('modalEditarSeminario')">Cancelar</button>
                    <button type="button" class="btn-danger" onclick="confirmarEliminarSeminario()">Eliminar Seminario</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Modal para confirmar eliminación -->
    <div id="modalConfirmarEliminar" class="modal">
        <div class="modal-content modal-small">
            <span class="close" onclick="cerrarModal('modalConfirmarEliminar')">&times;</span>
            <h2>Confirmar Eliminación</h2>
            <p>¿Está seguro que desea eliminar este seminario? Esta acción no se puede deshacer.</p>
            <form id="formEliminarSeminario" method="POST" action="">
                <input type="hidden" name="accion" value="eliminar_seminario">
                <input type="hidden" name="seminario_id" id="eliminar_seminario_id">
                <div class="form-actions">
                    <button type="submit" class="btn-danger">Eliminar</button>
                    <button type="button" class="btn-secondary" onclick="cerrarModal('modalConfirmarEliminar')">Cancelar</button>
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
        const crearSeminarioTab = document.getElementById('crearSeminarioTab');
        const listarSeminariosTab = document.getElementById('listarSeminariosTab');
        const crearSeminarioSection = document.getElementById('crearSeminarioSection');
        const listarSeminariosSection = document.getElementById('listarSeminariosSection');
        
        crearSeminarioTab.addEventListener('click', () => {
            crearSeminarioTab.classList.add('active');
            listarSeminariosTab.classList.remove('active');
            crearSeminarioSection.style.display = 'block';
            listarSeminariosSection.style.display = 'none';
        });
        
        listarSeminariosTab.addEventListener('click', () => {
        listarSeminariosTab.classList.add('active');
        crearSeminarioTab.classList.remove('active');
        listarSeminariosSection.style.display = 'block';
        crearSeminarioSection.style.display = 'none';
        });
        
        // Búsqueda y filtrado de seminarios
        const searchSeminarios = document.getElementById('searchSeminarios');
        const filterEstadoSeminario = document.getElementById('filterEstadoSeminario');
        const filterModalidad = document.getElementById('filterModalidad');
        const seminarioCards = document.querySelectorAll('.seminario-card');
        
        function filtrarSeminarios() {
            const searchTerm = searchSeminarios.value.toLowerCase();
            const estado = filterEstadoSeminario.value;
            const modalidad = filterModalidad.value;
            
            seminarioCards.forEach(card => {
                const titulo = card.dataset.titulo;
                const estadoCard = card.dataset.estado;
                const modalidadCard = card.dataset.modalidad;
                
                const matchSearch = titulo.includes(searchTerm);
                const matchEstado = estado === '' || estadoCard === estado;
                const matchModalidad = modalidad === '' || modalidadCard === modalidad;
                
                card.style.display = (matchSearch && matchEstado && matchModalidad) ? 'block' : 'none';
            });
        }
        
        searchSeminarios.addEventListener('input', filtrarSeminarios);
        filterEstadoSeminario.addEventListener('change', filtrarSeminarios);
        filterModalidad.addEventListener('change', filtrarSeminarios);
        
        // Funciones para modales
        function verSeminario(seminarioId) {
            // Hacer una petición AJAX para obtener los detalles del seminario
            fetch(`?api=details&id=${seminarioId}`)
                .then(response => response.json())
                .then(data => {
                    // Construir el HTML con los detalles
                    let html = `
                        <div class="seminario-detalle">
                            <div class="seminario-header estado-${data.estado.toLowerCase()}">
                                <h3>${data.titulo}</h3>
                                <span class="seminario-estado">${data.estado}</span>
                            </div>
                            <div class="seminario-info">
                                <p><strong>Fecha:</strong> ${data.fecha}</p>
                                <p><strong>Hora:</strong> ${data.hora}</p>
                                <p><strong>Modalidad:</strong> ${data.modalidad}</p>
                                <p><strong>Lugar:</strong> ${data.lugar}</p>
                                <p><strong>Cupos:</strong> ${data.inscritos}/${data.cupos}</p>
                                <p><strong>Tutor:</strong> ${data.tutor_nombre || 'No asignado'}</p>
                                
                                <h4>Descripción:</h4>
                                <div class="descripcion-completa">${data.descripcion}</div>
                                
                                <h4>Estudiantes Inscritos:</h4>
                                ${data.estudiantes.length > 0 ? 
                                    `<ul class="estudiantes-lista">
                                        ${data.estudiantes.map(est => `
                                            <li>
                                                <strong>${est.nombre}</strong>
                                                <br>Estado: ${est.estado}
                                                ${est.asistencia ? '<br>✓ Asistió' : ''}
                                                ${est.nota ? `<br>Nota: ${est.nota}` : ''}
                                            </li>
                                        `).join('')}
                                    </ul>` : 
                                    '<p>No hay estudiantes inscritos</p>'
                                }
                            </div>
                        </div>
                    `;
                    
                    detallesSeminario.innerHTML = html;
                    abrirModal('modalVerSeminario');
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error al cargar los detalles del seminario');
                });
        }
        
        function editarSeminario(seminarioId) {
            // Hacer una petición AJAX para obtener los datos del seminario
            fetch(`?api=edit&id=${seminarioId}`)
                .then(response => response.json())
                .then(seminario => {
                    document.getElementById('edit_seminario_id').value = seminario.id;
                    document.getElementById('eliminar_seminario_id').value = seminario.id;
                    document.getElementById('edit_titulo').value = seminario.titulo;
                    document.getElementById('edit_descripcion').value = seminario.descripcion;
                    document.getElementById('edit_fecha').value = seminario.fecha;
                    document.getElementById('edit_hora').value = seminario.hora;
                    document.getElementById('edit_modalidad').value = seminario.modalidad;
                    document.getElementById('edit_lugar').value = seminario.lugar;
                    document.getElementById('edit_cupos').value = seminario.cupos;
                    document.getElementById('edit_estado').value = seminario.estado;
                    
                    if (seminario.tutor_id) {
                        document.getElementById('edit_tutor_id').value = seminario.tutor_id;
                    }
                    
                    const archivoActualDiv = document.getElementById('archivo_actual');
                    if (seminario.archivo_guia) {
                        archivoActualDiv.innerHTML = `
                            <p>Archivo actual: <a href="/uploads/seminarios/${seminario.archivo_guia}" target="_blank">${seminario.archivo_guia}</a></p>
                            <p>Si sube un nuevo archivo, reemplazará al actual.</p>
                        `;
                    } else {
                        archivoActualDiv.innerHTML = '<p>No hay archivo adjunto actualmente.</p>';
                    }
                    
                    abrirModal('modalEditarSeminario');
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error al cargar los datos del seminario');
                });
        }
        
        function confirmarEliminarSeminario() {
            cerrarModal('modalEditarSeminario');
            abrirModal('modalConfirmarEliminar');
        }
        
        function abrirModal(modalId) {
            document.getElementById(modalId).style.display = 'block';
            document.body.style.overflow = 'hidden';
        }
        
        function cerrarModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
            document.body.style.overflow = 'auto';
        }
        
        // Cerrar modal al hacer clic fuera del contenido
        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                event.target.style.display = 'none';
                document.body.style.overflow = 'auto';
            }
        }
        
        // Validación de formularios
        document.getElementById('formCrearSeminario').addEventListener('submit', function(e) {
            const fecha = new Date(document.getElementById('fecha').value);
            const hoy = new Date();
            hoy.setHours(0, 0, 0, 0);
            
            if (fecha < hoy) {
                e.preventDefault();
                alert('La fecha del seminario no puede ser anterior a hoy');
            }
        });
        
        document.getElementById('formEditarSeminario').addEventListener('submit', function(e) {
            const fecha = new Date(document.getElementById('edit_fecha').value);
            const hoy = new Date();
            hoy.setHours(0, 0, 0, 0);
            
            if (fecha < hoy && document.getElementById('edit_estado').value === 'activo') {
                e.preventDefault();
                alert('No se puede programar un seminario activo en una fecha pasada');
            }
        });
        
        // Actualizar campo de lugar según modalidad
        document.getElementById('modalidad').addEventListener('change', function() {
            const lugarInput = document.getElementById('lugar');
            if (this.value === 'virtual') {
                lugarInput.placeholder = 'Ingrese el enlace de la reunión';
            } else {
                lugarInput.placeholder = 'Ingrese la ubicación física';
            }
        });
        
        document.getElementById('edit_modalidad').addEventListener('change', function() {
            const lugarInput = document.getElementById('edit_lugar');
            if (this.value === 'virtual') {
                lugarInput.placeholder = 'Ingrese el enlace de la reunión';
            } else {
                lugarInput.placeholder = 'Ingrese la ubicación física';
            }
        });
        
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