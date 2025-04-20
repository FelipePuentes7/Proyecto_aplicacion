<?php
session_start();
require_once __DIR__ . '/../../config/conexion.php';

// Habilitar reporte de errores para depuración (¡deshabilitar en producción!)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Inicializar variables para evitar errores
$mensaje = '';
$error = '';
$nombreUsuario = $_SESSION['nombreUsuario'] ?? 'Administrador';

// Función para manejar valores nulos y sanitizar para HTML
function htmlSafe($str) {
    return $str !== null ? htmlspecialchars((string)$str, ENT_QUOTES, 'UTF-8') : '';
}

// Obtener estudiantes con opción de grado "proyecto" que NO están asignados a ningún grupo
$estudiantes = $conexion->query("
    SELECT u.id, u.nombre, u.email, u.codigo_estudiante, u.opcion_grado, u.nombre_proyecto
    FROM usuarios u
    LEFT JOIN estudiantes_proyecto ep ON u.id = ep.estudiante_id
    WHERE u.rol = 'estudiante' AND u.opcion_grado = 'proyecto' AND ep.id IS NULL
    ORDER BY u.nombre
")->fetchAll(PDO::FETCH_ASSOC);

// Obtener todos los estudiantes de proyecto para el modal de edición
$todosEstudiantes = $conexion->query("
    SELECT u.id, u.nombre, u.email, u.codigo_estudiante, u.opcion_grado, u.nombre_proyecto,
    (SELECT ep.proyecto_id FROM estudiantes_proyecto ep WHERE ep.estudiante_id = u.id LIMIT 1) as proyecto_asignado_id
    FROM usuarios u
    WHERE u.rol = 'estudiante' AND u.opcion_grado = 'proyecto'
    ORDER BY u.nombre
")->fetchAll(PDO::FETCH_ASSOC);

// Obtener tutores
$tutoresQuery = $conexion->query("
    SELECT u.id, u.nombre, u.email
    FROM usuarios u
    WHERE u.rol = 'tutor'
    ORDER BY u.nombre
");
$tutores = $tutoresQuery ? $tutoresQuery->fetchAll(PDO::FETCH_ASSOC) : [];


// Obtener proyectos existentes con el número de estudiantes asignados Y el nombre del tutor
$proyectosQuery = $conexion->query("
    SELECT p.*,
    (SELECT COUNT(*) FROM estudiantes_proyecto ep WHERE ep.proyecto_id = p.id) as num_estudiantes,
    u_tutor.nombre AS tutor_nombre -- Añadimos el nombre del tutor
    FROM proyectos p
    LEFT JOIN usuarios u_tutor ON p.tutor_id = u_tutor.id -- Unimos con usuarios para obtener el nombre del tutor
    ORDER BY p.id DESC
");
$proyectos = $proyectosQuery ? $proyectosQuery->fetchAll(PDO::FETCH_ASSOC) : [];


// Obtener asignaciones de estudiantes a proyectos y estructurarlas
$asignacionesEstudiantes = [];
$asignacionesQuery = $conexion->query("
    SELECT ep.proyecto_id, ep.estudiante_id, u.nombre as estudiante_nombre, u.email as estudiante_email, ep.rol_en_proyecto
    FROM estudiantes_proyecto ep
    JOIN usuarios u ON ep.estudiante_id = u.id
    ORDER BY ep.proyecto_id, ep.rol_en_proyecto DESC, u.nombre
");

if ($asignacionesQuery) {
    while ($asignacion = $asignacionesQuery->fetch(PDO::FETCH_ASSOC)) {
        if (!isset($asignacionesEstudiantes[$asignacion['proyecto_id']])) {
            $asignacionesEstudiantes[$asignacion['proyecto_id']] = [];
        }
        $asignacionesEstudiantes[$asignacion['proyecto_id']][] = $asignacion;
    }
}


// Obtener nombres de proyectos de la tabla usuarios para filtrado (si aplica)
$nombresProyectos = $conexion->query("
    SELECT DISTINCT nombre_proyecto
    FROM usuarios
    WHERE nombre_proyecto IS NOT NULL AND nombre_proyecto != ''
    ORDER BY nombre_proyecto
")->fetchAll(PDO::FETCH_COLUMN);

// Procesar la creación, actualización o eliminación de un proyecto
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion'])) {
    try {
        // Iniciar transacción (se aplica a todas las acciones dentro del try)
        $conexion->beginTransaction();

        if ($_POST['accion'] === 'crear_proyecto') {
            // --- Lógica para CREAR un proyecto ---
            $titulo = $_POST['titulo'] ?? '';
            $descripcion = $_POST['descripcion'] ?? '';
            $tutor_id = $_POST['tutor_id'] ?? null; // Obtener el ID del tutor seleccionado
            $estudiantes_seleccionados = $_POST['estudiantes'] ?? []; // Array de IDs de estudiantes

            // 1. Validar datos recibidos
            // Ahora validamos también que se haya seleccionado un tutor (si es obligatorio)
            if (empty($titulo) || empty($descripcion) || empty($tutor_id) || count($estudiantes_seleccionados) < 1 || count($estudiantes_seleccionados) > 3) {
                 // Revertir transacción antes de lanzar excepción de validación
                 $conexion->rollBack();
                throw new Exception("Faltan datos requeridos: título, descripción, tutor, y debes seleccionar entre 1 y 3 estudiantes.");
            }

            // 2. Manejar subida de archivo (si existe)
            $archivo_nombre = null;
            if (isset($_FILES['archivo_proyecto']) && $_FILES['archivo_proyecto']['error'] === UPLOAD_ERR_OK) {
                $fileTmpPath = $_FILES['archivo_proyecto']['tmp_name'];
                $fileName = $_FILES['archivo_proyecto']['name'];
                // Considerar validar tamaño, tipo MIME más estrictamente si es necesario
                $fileNameCmps = explode(".", $fileName);
                $fileExtension = strtolower(end($fileNameCmps));

                $allowedfileExtensions = array('pdf', 'doc', 'docx');
                if (!in_array($fileExtension, $allowedfileExtensions)) {
                     $conexion->rollBack();
                    throw new Exception("Tipo de archivo no permitido. Solo se permiten PDF, DOC y DOCX.");
                }

                // Directorio donde se guardarán los archivos (asegúrate de que exista y tenga permisos de escritura)
                $uploadFileDir = __DIR__ . '/../../uploads/proyectos/'; // Ajusta la ruta si es necesario
                 // Asegurarse de que el directorio existe
                 if (!is_dir($uploadFileDir)) {
                     mkdir($uploadFileDir, 0777, true); // Crea el directorio si no existe (ajusta permisos según necesites)
                 }

                $dest_path = $uploadFileDir . uniqid('proyecto_') . '_' . basename($fileName); // Generar nombre único

                if (!move_uploaded_file($fileTmpPath, $dest_path)) {
                     $conexion->rollBack();
                    throw new Exception("Error al subir el archivo.");
                }
                $archivo_nombre = basename($dest_path); // Guardar solo el nombre del archivo en la DB
            } elseif (isset($_FILES['archivo_proyecto']) && $_FILES['archivo_proyecto']['error'] !== UPLOAD_ERR_NO_FILE) {
                 // Manejar otros errores de subida que no sean "no se subió archivo"
                 $conexion->rollBack();
                 $phpFileUploadErrors = array(
                    0 => 'No hay error, el archivo se subió con éxito',
                    1 => 'El archivo subido supera la directiva upload_max_filesize en php.ini',
                    2 => 'El archivo subido supera la directiva MAX_FILE_SIZE que se especificó en el formulario HTML',
                    3 => 'El archivo subido solo se subió parcialmente',
                    4 => 'No se subió ningún archivo',
                    6 => 'Falta una carpeta temporal',
                    7 => 'No se pudo escribir el archivo en el disco.',
                    8 => 'Una extensión de PHP detuvo la carga del archivo.',
                 );
                 $error_code = $_FILES['archivo_proyecto']['error'];
                 $error_message = $phpFileUploadErrors[$error_code] ?? 'Error de subida desconocido';
                 throw new Exception("Error en la subida del archivo: " . $error_message);
            }


            // 3. Insertar el proyecto en la tabla 'proyectos'
            // Añadimos tutor_id a la consulta de inserción
            $sql_proyecto = "INSERT INTO proyectos (titulo, descripcion, archivo_proyecto, estado, tipo, tutor_id) VALUES (:titulo, :descripcion, :archivo_proyecto, :estado, :tipo, :tutor_id)";
            $stmt_proyecto = $conexion->prepare($sql_proyecto);

            $estado_inicial = 'propuesto'; // Estado por defecto al crear
            $tipo_proyecto = 'proyecto'; // Tipo por defecto si no se especifica otro

            $stmt_proyecto->bindParam(':titulo', $titulo);
            $stmt_proyecto->bindParam(':descripcion', $descripcion);
            $stmt_proyecto->bindParam(':archivo_proyecto', $archivo_nombre);
            $stmt_proyecto->bindParam(':estado', $estado_inicial);
            $stmt_proyecto->bindParam(':tipo', $tipo_proyecto);
            $stmt_proyecto->bindParam(':tutor_id', $tutor_id, PDO::PARAM_INT); // Asegurarse que es INT


            if (!$stmt_proyecto->execute()) {
                // Si falla la ejecución, lanzar excepción para activar el catch y rollback
                 $conexion->rollBack();
                throw new Exception("Error al insertar el proyecto en la base de datos: " . $stmt_proyecto->errorInfo()[2]);
            }

            // 4. Obtener el ID del proyecto recién insertado
            $proyecto_id = $conexion->lastInsertId();

            // 5. Iterar sobre los estudiantes seleccionados e insertar en 'estudiantes_proyecto'
            $sql_estudiante_proyecto = "INSERT INTO estudiantes_proyecto (proyecto_id, estudiante_id, rol_en_proyecto) VALUES (:proyecto_id, :estudiante_id, :rol)";
            $stmt_estudiante_proyecto = $conexion->prepare($sql_estudiante_proyecto);

            foreach ($estudiantes_seleccionados as $index => $estudiante_id) {
                $rol = ($index === 0) ? 'líder' : 'miembro'; // El primer estudiante es el líder

                $stmt_estudiante_proyecto->bindParam(':proyecto_id', $proyecto_id);
                $stmt_estudiante_proyecto->bindParam(':estudiante_id', $estudiante_id, PDO::PARAM_INT); // Asegúrate que $estudiante_id es INT
                $stmt_estudiante_proyecto->bindParam(':rol', $rol);

                if (!$stmt_estudiante_proyecto->execute()) {
                    // Si falla la ejecución, lanzar excepción para activar el catch y rollback
                    $conexion->rollBack();
                    throw new Exception("Error al asignar estudiante (ID: {$estudiante_id}) al proyecto: " . $stmt_estudiante_proyecto->errorInfo()[2]);
                }
            }

            // 6. Confirmar la transacción si todo fue exitoso
            $conexion->commit();

            // Redirigir con mensaje de éxito
            $mensaje = "Proyecto '" . htmlSafe($titulo) . "' creado y estudiantes asignados exitosamente.";
            header("Location: " . $_SERVER['PHP_SELF'] . "?mensaje=" . urlencode($mensaje));
            exit;

        } elseif ($_POST['accion'] === 'actualizar_proyecto') {
             // --- Lógica para ACTUALIZAR un proyecto ---
             $proyecto_id = $_POST['proyecto_id'] ?? null;
             $titulo = $_POST['titulo'] ?? '';
             $descripcion = $_POST['descripcion'] ?? '';
             $estado = $_POST['estado'] ?? '';
             $tutor_id = $_POST['tutor_id'] ?? null; // Obtener el nuevo ID del tutor
             $estudiantes_seleccionados = $_POST['edit_estudiantes'] ?? []; // IDs de estudiantes seleccionados en el modal

             // 1. Validar datos recibidos
             if (empty($proyecto_id) || empty($titulo) || empty($descripcion) || empty($estado) || empty($tutor_id) || count($estudiantes_seleccionados) < 1 || count($estudiantes_seleccionados) > 3) {
                  $conexion->rollBack();
                 throw new Exception("Faltan datos requeridos o la selección de estudiantes/tutor no es válida para actualizar el proyecto.");
             }

             // 2. Manejar subida de archivo para actualización (opcional)
             $archivo_nombre = null; // Por defecto no hay cambio de archivo
             $mantener_archivo_actual = false; // Lógica para mantener el archivo existente

             // Si se envió un nuevo archivo
             if (isset($_FILES['edit_archivo_proyecto']) && $_FILES['edit_archivo_proyecto']['error'] === UPLOAD_ERR_OK) {
                 $fileTmpPath = $_FILES['edit_archivo_proyecto']['tmp_name'];
                 $fileName = $_FILES['edit_archivo_proyecto']['name'];
                 $fileNameCmps = explode(".", $fileName);
                 $fileExtension = strtolower(end($fileNameCmps));
                 $allowedfileExtensions = array('pdf', 'doc', 'docx');

                 if (!in_array($fileExtension, $allowedfileExtensions)) {
                      $conexion->rollBack();
                     throw new Exception("Tipo de archivo no permitido para actualización. Solo se permiten PDF, DOC y DOCX.");
                 }

                 $uploadFileDir = __DIR__ . '/../../uploads/proyectos/';
                 if (!is_dir($uploadFileDir)) {
                     mkdir($uploadFileDir, 0777, true);
                 }
                 $dest_path = $uploadFileDir . uniqid('proyecto_edit_') . '_' . basename($fileName);

                 if (!move_uploaded_file($fileTmpPath, $dest_path)) {
                      $conexion->rollBack();
                     throw new Exception("Error al subir el nuevo archivo para el proyecto.");
                 }
                 $archivo_nombre = basename($dest_path); // Nombre del nuevo archivo
                 // Lógica opcional: Eliminar el archivo antiguo si se subió uno nuevo
                 // Primero, obtén el nombre del archivo antiguo de la DB antes de actualizar
                 // Si $proyecto ya se obtuvo antes en el script para llenar el modal, úsalo, si no, consulta la DB.
                 $stmt_old_file = $conexion->prepare("SELECT archivo_proyecto FROM proyectos WHERE id = :id");
                 $stmt_old_file->bindParam(':id', $proyecto_id, PDO::PARAM_INT);
                 $stmt_old_file->execute();
                 $old_file = $stmt_old_file->fetchColumn();
                 if ($old_file && file_exists($uploadFileDir . $old_file)) {
                     unlink($uploadFileDir . $old_file); // Elimina el archivo antiguo
                 }

             } elseif (isset($_FILES['edit_archivo_proyecto']) && $_FILES['edit_archivo_proyecto']['error'] !== UPLOAD_ERR_NO_FILE) {
                  // Manejar otros errores de subida al intentar subir un nuevo archivo
                  $conexion->rollBack();
                  $phpFileUploadErrors = array( /* ... definir array ... */ ); // Reutiliza el array de arriba
                  $error_code = $_FILES['edit_archivo_proyecto']['error'];
                  $error_message = $phpFileUploadErrors[$error_code] ?? 'Error de subida desconocido';
                  throw new Exception("Error en la subida del archivo de actualización: " . $error_message);
             } else {
                 // Si no se subió un nuevo archivo y no hubo error de subida,
                 // significa que no se desea cambiar el archivo. Mantenemos el existente.
                 $mantener_archivo_actual = true; // Usaremos esto en la consulta SQL
             }


             // 3. Actualizar el proyecto en la tabla 'proyectos'
             // Añadimos tutor_id a la consulta de actualización
             $sql_update_proyecto = "UPDATE proyectos SET titulo = :titulo, descripcion = :descripcion, estado = :estado, tutor_id = :tutor_id";
             // Solo actualiza el archivo si se subió uno nuevo
             if ($archivo_nombre !== null) {
                 $sql_update_proyecto .= ", archivo_proyecto = :archivo_proyecto";
             }
             // Si no se subió un nuevo archivo Y se desea mantener el actual, no hacemos nada con archivo_proyecto en el UPDATE
             // Si se subió un nuevo archivo, ya se manejó arriba.
             // Si se quiere ELIMINAR el archivo, necesitarías un checkbox o lógica adicional en el formulario/JS
             $sql_update_proyecto .= " WHERE id = :id";

             $stmt_update_proyecto = $conexion->prepare($sql_update_proyecto);

             $stmt_update_proyecto->bindParam(':id', $proyecto_id, PDO::PARAM_INT);
             $stmt_update_proyecto->bindParam(':titulo', $titulo);
             $stmt_update_proyecto->bindParam(':descripcion', $descripcion);
             $stmt_update_proyecto->bindParam(':estado', $estado);
             $stmt_update_proyecto->bindParam(':tutor_id', $tutor_id, PDO::PARAM_INT); // Bind del nuevo tutor_id
             if ($archivo_nombre !== null) {
                 $stmt_update_proyecto->bindParam(':archivo_proyecto', $archivo_nombre);
             }

              if (!$stmt_update_proyecto->execute()) {
                   $conexion->rollBack();
                   throw new Exception("Error al actualizar el proyecto: " . $stmt_update_proyecto->errorInfo()[2]);
              }


             // 4. Actualizar asignación de estudiantes: Eliminar las asignaciones existentes y crear las nuevas
             // Eliminar asignaciones actuales para este proyecto
             $sql_delete_asignaciones = "DELETE FROM estudiantes_proyecto WHERE proyecto_id = :proyecto_id";
             $stmt_delete_asignaciones = $conexion->prepare($sql_delete_asignaciones);
             $stmt_delete_asignaciones->bindParam(':proyecto_id', $proyecto_id, PDO::PARAM_INT);
              if (!$stmt_delete_asignaciones->execute()) {
                  $conexion->rollBack();
                  throw new Exception("Error al eliminar asignaciones de estudiantes existentes: " . $stmt_delete_asignaciones->errorInfo()[2]);
             }


             // Insertar las nuevas asignaciones
             $sql_insert_asignacion = "INSERT INTO estudiantes_proyecto (proyecto_id, estudiante_id, rol_en_proyecto) VALUES (:proyecto_id, :estudiante_id, :rol)";
             $stmt_insert_asignacion = $conexion->prepare($sql_insert_asignacion);

             foreach ($estudiantes_seleccionados as $index => $estudiante_id) {
                 $rol = ($index === 0) ? 'líder' : 'miembro'; // El primer estudiante es el líder

                 $stmt_insert_asignacion->bindParam(':proyecto_id', $proyecto_id, PDO::PARAM_INT);
                 $stmt_insert_asignacion->bindParam(':estudiante_id', $estudiante_id, PDO::PARAM_INT); // Asegúrate de que $estudiante_id es INT
                 $stmt_insert_asignacion->bindParam(':rol', $rol);

                 if (!$stmt_insert_asignacion->execute()) {
                      $conexion->rollBack();
                     throw new Exception("Error al reasignar estudiante (ID: {$estudiante_id}) al proyecto: " . $stmt_insert_asignacion->errorInfo()[2]);
                 }
             }


            // Confirmar la transacción si todo fue exitoso
            $conexion->commit();

             // Redirigir con mensaje de éxito
            $mensaje = "Proyecto '" . htmlSafe($titulo) . "' actualizado exitosamente.";
            header("Location: " . $_SERVER['PHP_SELF'] . "?mensaje=" . urlencode($mensaje));
            exit;


        } elseif ($_POST['accion'] === 'eliminar_proyecto') {
             // --- Lógica para ELIMINAR un proyecto ---
             $proyecto_id = $_POST['proyecto_id'] ?? null;

             if (empty($proyecto_id)) {
                  $conexion->rollBack();
                 throw new Exception("ID de proyecto no proporcionado para eliminar.");
             }

              // 1. Eliminar asignaciones de estudiantes
             $sql_delete_asignaciones = "DELETE FROM estudiantes_proyecto WHERE proyecto_id = :proyecto_id";
             $stmt_delete_asignaciones = $conexion->prepare($sql_delete_asignaciones);
             $stmt_delete_asignaciones->bindParam(':proyecto_id', $proyecto_id, PDO::PARAM_INT);
              if (!$stmt_delete_asignaciones->execute()) {
                  $conexion->rollBack();
                  throw new Exception("Error al eliminar asignaciones de estudiantes del proyecto: " . $stmt_delete_asignaciones->errorInfo()[2]);
              }

              // 2. Opcional: Eliminar archivos asociados al proyecto
              $stmt_file = $conexion->prepare("SELECT archivo_proyecto FROM proyectos WHERE id = :id");
              $stmt_file->bindParam(':id', $proyecto_id, PDO::PARAM_INT);
              $stmt_file->execute();
              $archivo_a_eliminar = $stmt_file->fetchColumn();
              if ($archivo_a_eliminar) {
                   $uploadFileDir = __DIR__ . '/../../uploads/proyectos/';
                   $filePath = $uploadFileDir . $archivo_a_eliminar;
                   if (file_exists($filePath)) {
                       unlink($filePath); // Elimina el archivo físico
                   }
              }


              // 3. Eliminar el proyecto
             $sql_delete_proyecto = "DELETE FROM proyectos WHERE id = :proyecto_id";
             $stmt_delete_proyecto = $conexion->prepare($sql_delete_proyecto);
             $stmt_delete_proyecto->bindParam(':proyecto_id', $proyecto_id, PDO::PARAM_INT);
              if (!$stmt_delete_proyecto->execute()) {
                  $conexion->rollBack();
                  throw new Exception("Error al eliminar el proyecto de la base de datos: " . $stmt_delete_proyecto->errorInfo()[2]);
              }


            // Confirmar la transacción si todo fue exitoso
            $conexion->commit();

             // Redirigir con mensaje de éxito
            $mensaje = "Proyecto eliminado exitosamente.";
            header("Location: " . $_SERVER['PHP_SELF'] . "?mensaje=" . urlencode($mensaje));
            exit;
        }


    } catch (Exception $e) {
        // Revertir transacción en caso de error
        if (isset($conexion) && $conexion->inTransaction()) {
            $conexion->rollBack();
        }
        $error = $e->getMessage();
        // Opcional: registrar error detallado con error_log()
        error_log("Error en gestión de proyectos: " . $e->getMessage()); // Registrar el error

        // Redirigir con error
        header("Location: " . $_SERVER['PHP_SELF'] . "?error=" . urlencode($error));
        exit; // Importante salir después de la redirección
    }
     // Si hubo un error en el POST sin lanzar excepción, el error se mostraría aquí (menos común con try/catch/throw)
}

// Recuperar mensaje o error de la URL si existe (después de una redirección GET)
if (isset($_GET['mensaje'])) {
    $mensaje = htmlSafe($_GET['mensaje']);
} elseif (isset($_GET['error'])) {
     $error = htmlSafe($_GET['error']);
}


// Obtener títulos de proyectos existentes para autocompletado
$titulosProyectos = $conexion ? $conexion->query("SELECT DISTINCT titulo FROM proyectos ORDER BY titulo")->fetchAll(PDO::FETCH_COLUMN) : [];


// Convertir datos a JSON para usar en JavaScript
// Asegúrate de que $tutores esté definido antes de encodear
$proyectosDataJSON = json_encode($proyectos);
$asignacionesEstudiantesJSON = json_encode($asignacionesEstudiantes);
$todosEstudiantesJSON = json_encode($todosEstudiantes);
$titulosProyectosDataJSON = json_encode($titulosProyectos);
$nombresProyectosJSON = json_encode($nombresProyectos);
$tutoresDataJSON = json_encode($tutores); // Pasar datos de tutores a JS


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
            <li><a href="/views/administrador/inicio.php">Inicio</a></li>
            <li><a href="/views/administrador/aprobacion.php">Aprobación de Usuarios</a></li>
            <li><a href="/views/administrador/usuarios.php">Gestión de Usuarios</a></li>
            <li class="dropdown">
                <a href="#">Gestión de Modalidades de Grado</a>
                <ul class="dropdown-content">
                    <li><a href="/views/administrador/gestion_seminario.php">Seminario</a></li>
                    <li><a href="/views/administrador/gestion_proyectos.php" class="active">Proyectos</a></li>
                    <li><a href="/views/administrador/gestion_pasantias.php">Pasantías</a></li>
                </ul>
            </li>
            <li><a href="/views/administrador/reportes.php">Reportes y Estadísticas</a></li>
            <li><a href="#">Rol: <?php echo htmlspecialchars($nombreUsuario); ?></a></li>
            <li><a href="/views/general/login.php">Cerrar Sesión</a></li>
        </ul>
    </nav>

    <main>
        <h1>Gestión de Proyectos</h1>

        <?php if ($mensaje): ?>
            <div class="mensaje exito"><?= $mensaje ?></div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="mensaje error"><?= $error ?></div>
        <?php endif; ?>

        <div class="tabs">
            <button id="crearProyectoTab" class="active">Crear Proyecto</button>
            <button id="listarProyectosTab">Listar Proyectos</button>
        </div>

        <section id="crearProyectoSection" class="tab-content active">
             <form id="formCrearProyecto" method="POST" action="" enctype="multipart/form-data">
                 <input type="hidden" name="accion" value="crear_proyecto">
                 <div class="form-group">
                     <h2>Información del Proyecto</h2>
                     <div class="form-row">
                         <div class="form-field full-width">
                              <label for="titulo">Título del Proyecto *</label>
                              <div class="autocomplete-container">
                                  <input type="text" id="titulo" name="titulo" required autocomplete="off">
                                  <div id="tituloSugerencias" class="autocomplete-items"></div>
                              </div>
                         </div>
                     </div>
                     <div class="form-row">
                            <div class="form-field full-width">
                                 <label for="descripcion">Descripción del Proyecto *</label>
                                 <textarea id="descripcion" name="descripcion" rows="4" required></textarea>
                            </div>
                     </div>
                      <div class="form-row">
                           <div class="form-field">
                                <label for="tutor_id">Tutor Asignado *</label>
                                <select id="tutor_id" name="tutor_id" required>
                                     <option value="">-- Seleccione un tutor --</option>
                                     <?php foreach ($tutores as $tutor): ?>
                                         <option value="<?= htmlSafe($tutor['id']) ?>">
                                             <?= htmlSafe($tutor['nombre']) ?>
                                         </option>
                                     <?php endforeach; ?>
                                 </select>
                           </div>
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
                     </div>
                      <div class="search-filter">
                          <input type="text" id="searchProyectosEstudiantes" placeholder="Buscar por nombre de proyecto...">
                          <select id="filterNombreProyecto">
                              <option value="">Todos los proyectos</option>
                              <?php foreach ($nombresProyectos as $nombreProyecto): ?>
                                  <option value="<?= strtolower(htmlSafe($nombreProyecto)) ?>">
                                      <?= htmlSafe($nombreProyecto) ?>
                                  </option>
                              <?php endforeach; ?>
                          </select>
                     </div>
                     <div class="estudiantes-container">
                         <?php if (!empty($estudiantes)): ?>
                             <?php foreach ($estudiantes as $estudiante): ?>
                                  <div class="estudiante-card"
                                       data-id="<?= htmlSafe($estudiante['id']) ?>"
                                       data-nombre="<?= strtolower(htmlSafe($estudiante['nombre'])) ?>"
                                       data-opcion="<?= strtolower(htmlSafe($estudiante['opcion_grado'] ?? '')) ?>"
                                       data-proyecto="<?= strtolower(htmlSafe($estudiante['nombre_proyecto'] ?? '')) ?>">
                                       <div class="estudiante-info">
                                             <h3><?= htmlSafe($estudiante['nombre']) ?></h3>
                                             <p><strong>Código:</strong> <?= htmlSafe($estudiante['codigo_estudiante'] ?? 'N/A') ?></p>
                                             <p><strong>Email:</strong> <?= htmlSafe($estudiante['email']) ?></p>
                                             <p><strong>Opción de Grado:</strong> <?= htmlSafe(ucfirst($estudiante['opcion_grado'] ?? 'No asignada')) ?></p>
                                             <?php if (!empty($estudiante['nombre_proyecto'])): ?>
                                                <p><strong>Proyecto (estudiante):</strong> <?= htmlSafe($estudiante['nombre_proyecto']) ?></p>
                                             <?php endif; ?>
                                        </div>
                                        <div class="estudiante-select">
                                             <input type="checkbox" name="estudiantes[]" value="<?= htmlSafe($estudiante['id']) ?>" class="estudiante-checkbox">
                                        </div>
                                   </div>
                              <?php endforeach; ?>
                         <?php else: ?>
                             <p style="text-align: center; width: 100%;">No hay estudiantes disponibles para asignar a proyectos.</p>
                         <?php endif; ?>
                      </div>
                      <div class="estudiantes-seleccionados">
                           <h3>Estudiantes Seleccionados: <span id="contadorEstudiantes">0/3</span></h3>
                           <ul id="listaEstudiantesSeleccionados"></ul>
                      </div>
                 </div>

                 <div class="form-actions">
                     <button type="submit" class="btn-primary">Crear Proyecto</button>
                     <button type="reset" class="btn-secondary">Limpiar Formulario</button>
                 </div>
             </form>
        </section>

        <section id="listarProyectosSection" class="tab-content">
            <div class="search-filter">
                <input type="text" id="searchProyectos" placeholder="Buscar proyectos...">
                <select id="filterEstadoProyecto">
                    <option value="">Todos los estados</option>
                    <option value="propuesto">Propuesto</option>
                    <option value="en_revision">En Revisión</option>
                    <option value="aprobado">Aprobado</option>
                    <option value="finalizado">Finalizado</option>
                 </select>
            </div>

            <div class="proyectos-grid">
                <?php if (!empty($proyectos)): ?>
                    <?php foreach ($proyectos as $proyecto): ?>
                        <div class="proyecto-card"
                             data-id="<?= htmlSafe($proyecto['id']) ?>"
                             data-titulo="<?= strtolower(htmlSafe($proyecto['titulo'])) ?>"
                             data-estado="<?= htmlSafe($proyecto['estado']) ?>">
                            <div class="proyecto-header estado-<?= htmlSafe($proyecto['estado']) ?>">
                                <h3><?= htmlSafe($proyecto['titulo']) ?></h3>
                                <span class="proyecto-estado"><?= htmlSafe(ucfirst(str_replace('_', ' ', $proyecto['estado']))) ?></span>
                            </div>
                            <div class="proyecto-body">
                                <p><strong>Estudiantes:</strong> <?= htmlSafe($proyecto['num_estudiantes'] ?? 0) ?>/3</p>
                                <p><strong>Tutor:</strong> <?= htmlSafe($proyecto['tutor_nombre'] ?? 'No asignado') ?></p> <p><strong>Tipo:</strong> <?= htmlSafe($proyecto['tipo'] ?? 'Proyecto') ?></p>
                                <p><strong>ID:</strong> <?= htmlSafe($proyecto['id']) ?></p>
                                <?php if (!empty($proyecto['archivo_proyecto'])): ?>
                                    <p><strong>Archivo:</strong> <a href="/uploads/proyectos/<?= htmlSafe($proyecto['archivo_proyecto']) ?>" target="_blank" class="archivo-link">Ver archivo</a></p>
                                <?php endif; ?>
                                <div class="proyecto-descripcion">
                                    <?= nl2br(htmlSafe(substr($proyecto['descripcion'] ?? '', 0, 100))) ?>
                                    <?= (strlen($proyecto['descripcion'] ?? '') > 100) ? '...' : '' ?>
                                </div>
                            </div>
                            <div class="proyecto-footer">
                                <button class="btn-ver" onclick="verProyecto(<?= htmlSafe($proyecto['id']) ?>)">Ver Detalles</button>
                                <button class="btn-editar" onclick="editarProyecto(<?= htmlSafe($proyecto['id']) ?>)">Editar</button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="no-proyectos">
                        <p>No hay proyectos registrados. Cree un nuevo proyecto en la pestaña "Crear Proyecto".</p>
                    </div>
                <?php endif; ?>
            </div>
        </section>
    </main>

    <div id="modalVerProyecto" class="modal">
        <div class="modal-content">
            <span class="close" onclick="cerrarModal('modalVerProyecto')">&times;</span>
            <h2>Detalles del Proyecto</h2>
            <div id="detallesProyecto"></div>
        </div>
    </div>

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
                         <div class="autocomplete-container">
                             <input type="text" id="edit_titulo" name="titulo" required autocomplete="off">
                             <div id="editTituloSugerencias" class="autocomplete-items"></div>
                         </div>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-field">
                        <label for="edit_estado">Estado del Proyecto *</label>
                         <select id="edit_estado" name="estado" required>
                             <option value="propuesto">Propuesto</option>
                             <option value="en_revision">En Revisión</option>
                             <option value="aprobado">Aprobado</option>
                             <option value="finalizado">Finalizado</option>
                           </select>
                    </div>
                     <div class="form-field">
                         <label for="edit_tutor_id">Tutor Asignado *</label>
                         <select id="edit_tutor_id" name="tutor_id" required>
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

                <div class="form-group">
                    <h2>Estudiantes Asignados</h2>
                    <p class="info-text">Seleccione de 1 a 3 estudiantes para el proyecto. El primer estudiante seleccionado será el líder del proyecto.</p>
                    <div class="estudiantes-actuales">
                        <h3>Estudiantes Actuales: <span id="contadorEstudiantesEdit">0/3</span></h3>
                        <ul id="listaEstudiantesActuales"></ul>
                    </div>
                    <div class="search-filter">
                        <input type="text" id="searchEstudiantesEdit" placeholder="Buscar estudiantes...">
                    </div>
                    <div class="estudiantes-container" id="estudiantesEditContainer">
                         </div>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn-primary">Guardar Cambios</button>
                    <button type="button" class="btn-secondary" onclick="cerrarModal('modalEditarProyecto')">Cancelar</button>
                    <button type="button" class="btn-danger" onclick="confirmarEliminarProyecto()">Eliminar Proyecto</button>
                </div>
            </form>
        </div>
    </div>

     <div id="modalConfirmarEliminar" class="modal">
         <div class="modal-content modal-small"> <span class="close" onclick="cerrarModal('modalConfirmarEliminar')">&times;</span>
              <h2>Confirmar Eliminación</h2>
              <p>¿Está seguro de eliminar este proyecto?</p>
              <form id="formEliminarProyecto" method="POST" action="">
                   <input type="hidden" name="accion" value="eliminar_proyecto">
                   <input type="hidden" name="proyecto_id" id="eliminar_proyecto_id"> <div class="form-actions">
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
        // Pasa los datos PHP a variables JavaScript globales
        // Estos datos se generan dinámicamente por PHP y son necesarios al cargar la página
        const proyectosData = <?= $proyectosDataJSON ?>;
        const asignacionesEstudiantesData = <?= $asignacionesEstudiantesJSON ?>;
        const todosEstudiantesData = <?= $todosEstudiantesJSON ?>;
        const titulosProyectosData = <?= $titulosProyectosDataJSON ?>; // Títulos de proyectos de la tabla 'proyectos'
        const nombresProyectosUsuarios = <?= $nombresProyectosJSON ?>; // Nombres de proyectos de la tabla 'usuarios'
        const tutoresData = <?= $tutoresDataJSON ?>; // Datos de tutores


        // Helper para sanitizar texto en HTML (básico) - Importante si inyectas contenido dinámico
        function htmlSafe(str) {
             if (str === null || str === undefined) return '';
             let div = document.createElement('div');
             div.appendChild(document.createTextNode(str));
             return div.innerHTML;
        }


        // Funciones de navegación
        function toggleNav() {
            const navbar = document.getElementById("navbar");
            if (navbar) {
               navbar.classList.toggle("active");
            } else {
                console.error("Elemento #navbar no encontrado para toggleNav.");
            }
        }

        // Manejo de pestañas
        const crearProyectoTab = document.getElementById('crearProyectoTab');
        const listarProyectosTab = document.getElementById('listarProyectosTab');
        const crearProyectoSection = document.getElementById('crearProyectoSection');
        const listarProyectosSection = document.getElementById('listarProyectosSection');

        // Asegurarse de que los elementos existen antes de añadir listeners
        if (crearProyectoTab && listarProyectosTab && crearProyectoSection && listarProyectosSection) {
            crearProyectoTab.addEventListener('click', () => {
                crearProyectoTab.classList.add('active');
                listarProyectosTab.classList.remove('active');
                crearProyectoSection.classList.add('active');
                listarProyectosSection.classList.remove('active');
                 // Opcional: Limpiar el formulario de creación al cambiar de pestaña si es necesario
                 const formCrear = document.getElementById('formCrearProyecto');
                 if(formCrear) formCrear.reset();
                 // También necesitas limpiar la lista de estudiantes seleccionados
                 if(listaEstudiantesSeleccionados) listaEstudiantesSeleccionados.innerHTML = '';
                 if(contadorEstudiantes) contadorEstudiantes.textContent = '0/3';
                 // Y asegurar que todos los checkboxes de estudiante en crear esten desmarcados
                 document.querySelectorAll('#crearProyectoSection .estudiante-checkbox').forEach(cb => cb.checked = false);
                 // Restablecer filtros de estudiante si los tienes
                 if(searchEstudiantes) searchEstudiantes.value = '';
                 if(searchProyectosEstudiantes) searchProyectosEstudiantes.value = '';
                 if(filterNombreProyecto) filterNombreProyecto.value = '';
                 filtrarEstudiantes(); // Volver a mostrar todos los estudiantes disponibles
            });

            listarProyectosTab.addEventListener('click', () => {
                listarProyectosTab.classList.add('active');
                crearProyectoTab.classList.remove('active');
                listarProyectosSection.classList.add('active');
                crearProyectoSection.classList.remove('active');

                // Aplicar filtros al listar proyectos
                filtrarProyectos();
            });
        } else {
             console.error("Elementos de pestañas o secciones no encontrados.");
        }


        // Búsqueda y filtrado de estudiantes (Crear Proyecto)
        const searchEstudiantes = document.getElementById('searchEstudiantes');
        const searchProyectosEstudiantes = document.getElementById('searchProyectosEstudiantes');
        const filterNombreProyecto = document.getElementById('filterNombreProyecto');

        if (searchEstudiantes || searchProyectosEstudiantes || filterNombreProyecto) {
             if (searchEstudiantes) searchEstudiantes.addEventListener('input', filtrarEstudiantes);
             if (searchProyectosEstudiantes) searchProyectosEstudiantes.addEventListener('input', filtrarEstudiantes);
             if (filterNombreProyecto) filterNombreProyecto.addEventListener('change', filtrarEstudiantes);
        } else {
            console.warn("Elementos de filtro de estudiante en crear proyecto no encontrados.");
             if (searchEstudiantes) searchEstudiantes.disabled = true;
            if (searchProyectosEstudiantes) searchProyectosEstudiantes.disabled = true;
            if (filterNombreProyecto) filterNombreProyecto.disabled = true;
        }


        function filtrarEstudiantes() {
            const cardsToFilter = document.querySelectorAll('#crearProyectoSection .estudiante-card');
            if (cardsToFilter.length === 0) return;

            const searchTerm = searchEstudiantes ? searchEstudiantes.value.toLowerCase() : '';
            const searchProyectoTerm = searchProyectosEstudiantes ? searchProyectosEstudiantes.value.toLowerCase() : '';
            const nombreProyecto = filterNombreProyecto ? filterNombreProyecto.value.toLowerCase() : '';

            cardsToFilter.forEach(card => {
                const nombre = card.dataset.nombre || '';
                const proyecto = card.dataset.proyecto || '';
                const matchSearch = nombre.includes(searchTerm);
                const matchProyectoSearch = proyecto.includes(searchProyectoTerm);
                const matchProyecto = nombreProyecto === '' || proyecto === nombreProyecto;
                card.style.display = (matchSearch && matchProyecto && matchProyectoSearch) ? 'flex' : 'none';
            });
        }

        // Búsqueda de estudiantes en el modal de edición
        const searchEstudiantesEdit = document.getElementById('searchEstudiantesEdit');
        const estudiantesEditContainer = document.getElementById('estudiantesEditContainer');

        if (searchEstudiantesEdit && estudiantesEditContainer) {
            searchEstudiantesEdit.addEventListener('input', () => {
                const searchTerm = searchEstudiantesEdit.value.toLowerCase();
                const estudianteCardsEdit = estudiantesEditContainer.querySelectorAll('.estudiante-card');
                estudianteCardsEdit.forEach(card => {
                    const nombre = card.dataset.nombre || '';
                    card.style.display = nombre.includes(searchTerm) ? 'flex' : 'none';
                });
            });
        }


        // Búsqueda y filtrado de proyectos (Listar Proyectos)
        const searchProyectos = document.getElementById('searchProyectos');
        const filterEstadoProyecto = document.getElementById('filterEstadoProyecto');

        if (searchProyectos || filterEstadoProyecto) {
             if (searchProyectos) searchProyectos.addEventListener('input', filtrarProyectos);
             if (filterEstadoProyecto) filterEstadoProyecto.addEventListener('change', filtrarProyectos);
        } else {
             console.warn("Elementos de filtro de proyectos no encontrados.");
             if (searchProyectos) searchProyectos.disabled = true;
             if (filterEstadoProyecto) filterEstadoProyecto.disabled = true;
        }


        function filtrarProyectos() {
            const cardsToFilter = document.querySelectorAll('#listarProyectosSection .proyecto-card');
             if (cardsToFilter.length === 0) {
                 console.warn("No hay tarjetas de proyecto para filtrar en la sección listar.");
                 return;
             }

            const searchTerm = searchProyectos ? searchProyectos.value.toLowerCase() : '';
            const estadoProyecto = filterEstadoProyecto ? filterEstadoProyecto.value : '';

            cardsToFilter.forEach(card => {
                const titulo = card.dataset.titulo || '';
                const estado = card.dataset.estado || '';
                const matchSearch = titulo.includes(searchTerm);
                const matchEstado = estadoProyecto === '' || estado === estadoProyecto;
                card.style.display = (matchSearch && matchEstado) ? 'flex' : 'none';
            });
        }


        // Control de selección de estudiantes (Crear Proyecto)
        const contadorEstudiantes = document.getElementById('contadorEstudiantes');
        const listaEstudiantesSeleccionados = document.getElementById('listaEstudiantesSeleccionados');

        // Listener para checkboxes en la sección de creación (se añaden en DOMContentLoaded)

        function actualizarEstudiantesSeleccionados(event) {
            const seleccionados = document.querySelectorAll('#crearProyectoSection .estudiante-checkbox:checked');
            const numSeleccionados = seleccionados.length;

            if (contadorEstudiantes) {
                 contadorEstudiantes.textContent = `${numSeleccionados}/3`;
            }

            if (numSeleccionados > 3) {
                alert('No puede seleccionar más de 3 estudiantes para un proyecto');
                if (event && event.target) {
                    event.target.checked = false;
                    actualizarEstudiantesSeleccionados();
                }
                return;
            }

            if (listaEstudiantesSeleccionados) {
                listaEstudiantesSeleccionados.innerHTML = '';
                seleccionados.forEach((checkbox, index) => {
                    const estudianteCard = checkbox.closest('.estudiante-card');
                     if (estudianteCard) {
                        const nombreEstudianteElement = estudianteCard.querySelector('h3');
                        const nombreEstudiante = nombreEstudianteElement ? nombreEstudianteElement.textContent : 'Nombre desconocido';
                        const li = document.createElement('li');
                        li.textContent = `${index === 0 ? '👑 ' : ''}${htmlSafe(nombreEstudiante)}${index === 0 ? ' (Líder)' : ''}`;
                        listaEstudiantesSeleccionados.appendChild(li);
                    }
                });
            }
        }

        // Control de selección de estudiantes en el modal de edición
        // Llamada por cargarEstudiantesParaEdicion y listeners en checkboxes del modal
        function actualizarEstudiantesSeleccionadosEdit(event) {
            const seleccionados = document.querySelectorAll('#estudiantesEditContainer .estudiante-checkbox-edit:checked');
            const numSeleccionados = seleccionados.length;

            const contadorEstudiantesEdit = document.getElementById('contadorEstudiantesEdit');
            if (contadorEstudiantesEdit) {
                contadorEstudiantesEdit.textContent = `${numSeleccionados}/3`;
            }

            if (numSeleccionados > 3) {
                alert('No puede seleccionar más de 3 estudiantes para un proyecto');
                if (event && event.target) {
                    event.target.checked = false;
                     actualizarEstudiantesSeleccionadosEdit();
                } else {
                     console.error("Error: Más de 3 estudiantes seleccionados en edición sin un evento click.");
                     document.querySelectorAll('#estudiantesEditContainer .estudiante-checkbox-edit').forEach(cb => cb.checked = false);
                     actualizarEstudiantesSeleccionadosEdit();
                }
                return;
            }

            const listaEstudiantesActuales = document.getElementById('listaEstudiantesActuales');
            if (listaEstudiantesActuales) {
                listaEstudiantesActuales.innerHTML = '';
                seleccionados.forEach((checkbox, index) => {
                    const estudianteCard = checkbox.closest('.estudiante-card');
                     if (estudianteCard) {
                        const nombreEstudianteElement = estudianteCard.querySelector('h3');
                        const nombreEstudiante = nombreEstudianteElement ? nombreEstudianteElement.textContent : 'Nombre desconocido';
                        const li = document.createElement('li');
                        li.textContent = `${index === 0 ? '👑 ' : ''}${htmlSafe(nombreEstudiante)}${index === 0 ? ' (Líder)' : ''}`;
                        listaEstudiantesActuales.appendChild(li);
                    }
                });
            }
        }


        // Funciones para modales
        function verProyecto(proyectoId) {
            const proyecto = proyectosData.find(p => p.id == proyectoId);

            if (!proyecto) {
                alert('No se pudo encontrar información del proyecto');
                console.error('Proyecto no encontrado con ID:', proyectoId, proyectosData);
                return;
            }

            const estudiantes = asignacionesEstudiantesData[proyecto.id] || [];

            const proyectoCompleto = {
                ...proyecto,
                estudiantes: estudiantes,
                avances: [], // Placeholder
                comentarios: [] // Placeholder
            };

            mostrarDetallesProyecto(proyectoCompleto);
        }

        function mostrarDetallesProyecto(proyecto) {
            const detallesProyecto = document.getElementById('detallesProyecto');
            if (!detallesProyecto) {
                 console.error("Elemento #detallesProyecto no encontrado.");
                 return;
            }

            let html = `
                <div class="proyecto-detalle">
                    <div class="proyecto-header estado-${htmlSafe(proyecto.estado)}">
                        <h3>${htmlSafe(proyecto.titulo ? proyecto.titulo : 'Sin título')}</h3>
                        <span class="proyecto-estado">${htmlSafe(proyecto.estado ? proyecto.estado.replace('_', ' ') : 'Desconocido')}</span>
                    </div>
                    <div class="proyecto-info">
                        <p><strong>ID del Proyecto:</strong> ${htmlSafe(proyecto.id ? proyecto.id : 'N/A')}</p>
                        <p><strong>Tutor:</strong> ${htmlSafe(proyecto.tutor_nombre ? proyecto.tutor_nombre : 'No asignado')}</p> ${proyecto.archivo_proyecto ?
                            `<p><strong>Archivo del proyecto:</strong> <a href="/uploads/proyectos/${encodeURIComponent(proyecto.archivo_proyecto)}" target="_blank" class="archivo-link">${htmlSafe(proyecto.archivo_proyecto)}</a></p>` :
                            '<p><strong>Archivo del proyecto:</strong> No hay archivo adjunto</p>'
                        }

                        <h4>Descripción:</h4>
                        <div class="descripcion-completa">${htmlSafe(proyecto.descripcion ? proyecto.descripcion : '').replace(/\n/g, '<br>')}</div>

                        <h4>Estudiantes asignados:</h4>
                        ${proyecto.estudiantes && proyecto.estudiantes.length > 0 ?
                            `<ul class="estudiantes-lista">
                                ${proyecto.estudiantes.map((est) => {
                                     const rolDisplay = est.rol_en_proyecto === 'líder' ? ' (Líder)' : '';
                                     const icon = est.rol_en_proyecto === 'líder' ? '👑 ' : '';
                                     const nombre = htmlSafe(est.estudiante_nombre || 'Nombre desconocido');
                                     const email = htmlSafe(est.estudiante_email || 'Email desconocido');
                                     return `<li>${icon}${nombre} (${email})${rolDisplay}</li>`;
                                }).join('')}
                            </ul>` :
                            '<p>No hay estudiantes asignados</p>'
                        }

                        <h4>Avances del proyecto:</h4>
                        <p>No hay avances registrados (funcionalidad pendiente)</p>

                        <h4>Comentarios:</h4>
                         <p>No hay comentarios (funcionalidad pendiente)</p>
                    </div>
                </div>
            `;

            detallesProyecto.innerHTML = html;
            abrirModal('modalVerProyecto');
        }


        function editarProyecto(proyectoId) {
            const proyecto = proyectosData.find(p => p.id == proyectoId);

            if (!proyecto) {
                alert('No se pudo encontrar información del proyecto');
                 console.error('Proyecto no encontrado con ID:', proyectoId, proyectosData);
                return;
            }

            const form = document.getElementById('formEditarProyecto');
             if (form) {
                document.getElementById('edit_proyecto_id').value = proyecto.id;
                document.getElementById('edit_titulo').value = proyecto.titulo || '';
                document.getElementById('edit_descripcion').value = proyecto.descripcion || '';

                const editEstadoSelect = document.getElementById('edit_estado');
                if (editEstadoSelect) {
                     editEstadoSelect.value = proyecto.estado || 'propuesto';
                } else {
                     console.warn("Select de estado #edit_estado no encontrado.");
                }

                 // Llenar y seleccionar el tutor en el modal de edición
                 const editTutorSelect = document.getElementById('edit_tutor_id');
                 if (editTutorSelect) {
                     // Limpiar opciones existentes (excepto la primera si es placeholder)
                     editTutorSelect.innerHTML = '<option value="">-- Seleccione un tutor --</option>'; // Asegúrate de que este valor coincide con tu validación PHP

                     // Cargar tutores desde tutoresData (pasado desde PHP)
                     tutoresData.forEach(tutor => {
                         const option = document.createElement('option');
                         option.value = htmlSafe(tutor.id); // ID del tutor
                         option.textContent = htmlSafe(tutor.nombre); // Nombre del tutor
                         editTutorSelect.appendChild(option);
                     });

                     // Seleccionar el tutor asignado al proyecto actual
                     if (proyecto.tutor_id) {
                         editTutorSelect.value = proyecto.tutor_id;
                     } else {
                         // Si no hay tutor asignado, asegúrate de que el placeholder esté seleccionado
                         editTutorSelect.value = '';
                     }
                 } else {
                      console.warn("Select de tutor #edit_tutor_id no encontrado.");
                 }


                // Mostrar información del archivo actual si existe
                const archivoActualDiv = document.getElementById('archivo_actual');
                if (archivoActualDiv) {
                    if (proyecto.archivo_proyecto) {
                        archivoActualDiv.innerHTML = `
                            <p>Archivo actual: <a href="/uploads/proyectos/${encodeURIComponent(proyecto.archivo_proyecto)}" target="_blank">${htmlSafe(proyecto.archivo_proyecto)}</a></p>
                            <p>Si sube un nuevo archivo, reemplazará al actual.</p>
                        `;
                    } else {
                        archivoActualDiv.innerHTML = '<p>No hay archivo adjunto actualmente.</p>';
                    }
                }

                // Cargar estudiantes para edición
                cargarEstudiantesParaEdicion(proyecto.id);

                abrirModal('modalEditarProyecto');
             } else {
                 console.error("Formulario de edición #formEditarProyecto no encontrado.");
             }
        }

        function cargarEstudiantesParaEdicion(proyectoId) {
            const estudiantesAsignados = asignacionesEstudiantesData[proyectoId] || [];
            const estudiantesAsignadosIds = estudiantesAsignados.map(est => est.estudiante_id);

            const estudiantesDisponibles = todosEstudiantesData.filter(est =>
                !est.proyecto_asignado_id || parseInt(est.proyecto_asignado_id) === parseInt(proyectoId)
            );

            const contenedor = document.getElementById('estudiantesEditContainer');
            if (!contenedor) {
                 console.error("Contenedor de estudiantes en edición #estudiantesEditContainer no encontrado.");
                 return;
            }
            contenedor.innerHTML = '';

            estudiantesDisponibles.forEach(estudiante => {
                const isAsignado = estudiantesAsignadosIds.includes(estudiante.id);

                const div = document.createElement('div');
                div.className = 'estudiante-card';
                div.dataset.id = htmlSafe(estudiante.id);
                div.dataset.nombre = estudiante.nombre ? estudiante.nombre.toLowerCase() : '';

                div.innerHTML = `
                    <div class="estudiante-info">
                        <h3>${htmlSafe(estudiante.nombre || 'Sin nombre')}</h3>
                        <p><strong>Código:</strong> ${htmlSafe(estudiante.codigo_estudiante || 'N/A')}</p>
                        <p><strong>Email:</strong> ${htmlSafe(estudiante.email || 'Sin email')}</p>
                        <p><strong>Opción de Grado:</strong> ${htmlSafe(estudiante.opcion_grado || 'No asignada')}</p>
                        ${estudiante.nombre_proyecto ? `<p><strong>Proyecto (estudiante):</strong> ${htmlSafe(estudiante.nombre_proyecto)}</p>` : ''}
                    </div>
                    <div class="estudiante-select">
                        <input type="checkbox" name="edit_estudiantes[]" value="${htmlSafe(estudiante.id)}" class="estudiante-checkbox-edit" ${isAsignado ? 'checked' : ''}>
                    </div>
                `;
                contenedor.appendChild(div);
            });

            document.querySelectorAll('#estudiantesEditContainer .estudiante-checkbox-edit').forEach(checkbox => {
                checkbox.addEventListener('change', actualizarEstudiantesSeleccionadosEdit);
            });

            actualizarEstudiantesSeleccionadosEdit();
        }

        function confirmarEliminarProyecto() {
             const proyectoIdInput = document.getElementById('edit_proyecto_id');
             if (!proyectoIdInput || !proyectoIdInput.value) {
                 console.error("ID de proyecto no encontrado o vacío en #edit_proyecto_id.");
                 alert("No se pudo obtener el ID del proyecto para eliminar.");
                 return;
             }
             const proyectoId = proyectoIdInput.value;

             const inputEliminarId = document.getElementById('eliminar_proyecto_id');
             if (inputEliminarId) {
                 inputEliminarId.value = proyectoId;
             } else {
                  console.error("Input oculto #eliminar_proyecto_id en el modal de confirmación no encontrado.");
             }

            cerrarModal('modalEditarProyecto');
            abrirModal('modalConfirmarEliminar');
        }


        // Funciones genéricas para abrir/cerrar modales
        function abrirModal(modalId) {
            const modal = document.getElementById(modalId);
            if (modal) {
                modal.style.display = 'block';
                document.body.style.overflow = 'hidden';
            } else {
                 console.error("Modal con ID '" + modalId + "' no encontrado.");
            }
        }

        function cerrarModal(modalId) {
             const modal = document.getElementById(modalId);
             if (modal) {
                modal.style.display = 'none';
                document.body.style.overflow = 'auto';
            } else {
                 console.error("Modal con ID '" + modalId + "' no encontrado.");
            }
        }

        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                 const modalId = event.target.id;
                  if (modalId) {
                      cerrarModal(modalId);
                  }
            }
        }

        // Autocompletado para el título del proyecto
        const tituloInput = document.getElementById('titulo');
        const tituloSugerencias = document.getElementById('tituloSugerencias');
        const editTituloInput = document.getElementById('edit_titulo');
        const editTituloSugerencias = document.getElementById('editTituloSugerencias');

        function mostrarSugerencias(inputElement, sugerenciasDivElement) {
             if (!inputElement || !sugerenciasDivElement || !titulosProyectosData || titulosProyectosData.length === 0) {
                 if (sugerenciasDivElement) sugerenciasDivElement.style.display = 'none';
                 return;
            }

            const valor = inputElement.value.toLowerCase();
            sugerenciasDivElement.innerHTML = '';

            if (valor.length < 2) {
                sugerenciasDivElement.style.display = 'none';
                return;
            }

            const coincidencias = titulosProyectosData.filter(titulo =>
                titulo.toLowerCase().includes(valor)
            );

            if (coincidencias.length === 0) {
                sugerenciasDivElement.style.display = 'none';
                return;
            }

            coincidencias.forEach(titulo => {
                const div = document.createElement('div');
                div.classList.add('autocomplete-item');
                div.textContent = titulo;

                div.addEventListener('click', function() {
                    inputElement.value = titulo;
                    sugerenciasDivElement.style.display = 'none';
                     const event = new Event('input', { bubbles: true });
                     inputElement.dispatchEvent(event);
                });
                sugerenciasDivElement.appendChild(div);
            });

            sugerenciasDivElement.style.display = 'block';
        }

        if (tituloInput && tituloSugerencias) {
            tituloInput.addEventListener('input', function() {
                mostrarSugerencias(tituloInput, tituloSugerencias);
            });
            tituloInput.addEventListener('blur', function() {
                setTimeout(() => {
                     if (tituloSugerencias) tituloSugerencias.style.display = 'none';
                }, 200);
            });
             window.addEventListener('scroll', function() {
                  if (tituloSugerencias) tituloSugerencias.style.display = 'none';
             }, true);
        }

        if (editTituloInput && editTituloSugerencias) {
            editTituloInput.addEventListener('input', function() {
                mostrarSugerencias(editTituloInput, editTituloSugerencias);
            });
            editTituloInput.addEventListener('blur', function() {
                setTimeout(() => {
                     if (editTituloSugerencias) editTituloSugerencias.style.display = 'none';
                }, 200);
            });
             window.addEventListener('scroll', function() {
                  if (editTituloSugerencias) editTituloSugerencias.style.display = 'none';
             }, true);
        }


        // Inicializar funcionalidades al cargar la página
        document.addEventListener('DOMContentLoaded', function() {
            console.log("DOM completamente cargado. Inicializando...");

            const mensajes = document.querySelectorAll('.mensaje');
            if (mensajes.length > 0) {
                mensajes.forEach(msg => {
                     msg.style.opacity = '1';
                });
                setTimeout(() => {
                    mensajes.forEach(msg => {
                        msg.style.opacity = '0';
                        setTimeout(() => msg.style.display = 'none', 500);
                    });
                }, 5000);
                 if (mensajes[0]) {
                      mensajes[0].scrollIntoView({ behavior: 'smooth', block: 'start' });
                 }
            } else {
                 console.log("No hay mensajes de estado para mostrar.");
            }

            const activeSection = document.querySelector('.tab-content.active');
            if (!activeSection) {
                 if (crearProyectoSection) {
                      crearProyectoSection.classList.add('active');
                      if (crearProyectoTab) crearProyectoTab.classList.add('active');
                 } else if (listarProyectosSection) {
                      listarProyectosSection.classList.add('active');
                      if (listarProyectosTab) listarProyectosTab.classList.add('active');
                 } else {
                     console.error("No se encontraron secciones de tab-content.");
                 }
            } else {
                if (activeSection.id === 'crearProyectoSection' && crearProyectoTab) {
                     crearProyectoTab.classList.add('active');
                } else if (activeSection.id === 'listarProyectosSection' && listarProyectosTab) {
                     listarProyectosTab.classList.add('active');
                }
            }

            // Seleccionar las tarjetas después de que el DOM esté listo
            const estudianteCheckboxesCrear = document.querySelectorAll('#crearProyectoSection .estudiante-checkbox');
            const estudianteCardsCrear = document.querySelectorAll('#crearProyectoSection .estudiante-card');
            const proyectoCardsList = document.querySelectorAll('#listarProyectosSection .proyecto-card');

            if (crearProyectoSection && crearProyectoSection.classList.contains('active')) {
                 if (searchEstudiantes || searchProyectosEstudiantes || filterNombreProyecto) {
                     filtrarEstudiantes();
                 } else if (estudianteCardsCrear.length > 0) {
                     console.log("No hay filtros de estudiante para aplicar al cargar, mostrando todas las tarjetas de estudiante.");
                 } else {
                     console.log("No hay filtros de estudiante ni tarjetas de estudiante para mostrar.");
                 }
            }

            if (listarProyectosSection && listarProyectosSection.classList.contains('active')) {
                 if (searchProyectos || filterEstadoProyecto) {
                     filtrarProyectos();
                 } else if (proyectoCardsList.length > 0) {
                      console.log("No hay filtros de proyecto para aplicar al cargar, mostrando todas las tarjetas de proyecto.");
                 } else {
                     console.log("No hay filtros de proyecto ni tarjetas de proyecto para mostrar.");
                 }
            }

            const inicialCheckboxesCrear = document.querySelectorAll('#crearProyectoSection .estudiante-checkbox:checked');
            if (inicialCheckboxesCrear.length > 0) {
                actualizarEstudiantesSeleccionados();
            } else {
                 if (contadorEstudiantes) contadorEstudiantes.textContent = `0/3`;
                 if (listaEstudiantesSeleccionados) listaEstudiantesSeleccionados.innerHTML = '';
            }

             // Añadir listeners para checkboxes de estudiante en la sección de creación
             document.querySelectorAll('#crearProyectoSection .estudiante-checkbox').forEach(checkbox => {
                 checkbox.addEventListener('change', actualizarEstudiantesSeleccionados);
             });


        });

        // Asegurarse de que las funciones globales llamadas desde onclick en el HTML estén accesibles
        // verProyecto, editarProyecto, confirmarEliminarProyecto, cerrarModal, toggleNav
        // Estas funciones ya están definidas en el scope global porque este script no está en un módulo.

    </script>

</body>
</html>