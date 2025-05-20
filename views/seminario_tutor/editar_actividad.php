<?php
// Incluir archivo de conexión a la base de datos
require_once '../../config/conexion.php';

// Verificar si se proporcionó un ID
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: actividades_tutor.php');
    exit();
}

$actividad_id = $_GET['id'];
$tutor_id = 1; // En un sistema real, esto vendría de la sesión

// Procesar el formulario cuando se envía
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $conexion = new Conexion();
        $db = $conexion->getConexion();
        
        // Preparar la consulta de actualización
        $stmt = $db->prepare("
            UPDATE actividades SET
                titulo = :titulo,
                descripcion = :descripcion,
                tipo = :tipo,
                fecha_limite = :fecha_limite,
                hora_limite = :hora_limite,
                puntaje = :puntaje,
                permitir_entregas_tarde = :permitir_entregas_tarde
            WHERE id = :id AND tutor_id = :tutor_id
        ");
        
        // Vincular parámetros
        $stmt->bindParam(':titulo', $_POST['titulo']);
        $stmt->bindParam(':descripcion', $_POST['descripcion']);
        $stmt->bindParam(':tipo', $_POST['tipo']);
        $stmt->bindParam(':fecha_limite', $_POST['fecha_limite']);
        $stmt->bindParam(':hora_limite', $_POST['hora_limite']);
        $stmt->bindParam(':puntaje', $_POST['puntaje']);
        $permitirEntregas = isset($_POST['permitir_entregas_tarde']) ? 1 : 0;
        $stmt->bindParam(':permitir_entregas_tarde', $permitirEntregas);
        $stmt->bindParam(':id', $actividad_id);
        $stmt->bindParam(':tutor_id', $tutor_id);
        
        // Ejecutar la consulta
        if ($stmt->execute()) {
            // Procesar archivos adjuntos si existen
            if (!empty($_FILES['adjuntos']['name'][0])) {
                $uploadDir = '../../uploads/actividades/' . $actividad_id . '/';
                if (!file_exists($uploadDir)) {
                    mkdir($uploadDir, 0777, true);
                }
                
                foreach ($_FILES['adjuntos']['tmp_name'] as $key => $tmp_name) {
                    if ($_FILES['adjuntos']['error'][$key] === 0) {
                        $fileName = $_FILES['adjuntos']['name'][$key];
                        $filePath = $uploadDir . $fileName;
                        
                        if (move_uploaded_file($tmp_name, $filePath)) {
                            // Guardar referencia del archivo en la base de datos
                            $stmtFile = $db->prepare("
                                INSERT INTO archivos_actividad (
                                    id_actividad, nombre_archivo, ruta_archivo,
                                    tipo_archivo, tamano_archivo
                                ) VALUES (
                                    :id_actividad, :nombre_archivo, :ruta_archivo,
                                    :tipo_archivo, :tamano_archivo
                                )
                            ");
                            
                            $fileType = pathinfo($fileName, PATHINFO_EXTENSION);
                            $fileSize = filesize($filePath);
                            
                            $stmtFile->execute([
                                ':id_actividad' => $actividad_id,
                                ':nombre_archivo' => $fileName,
                                ':ruta_archivo' => $filePath,
                                ':tipo_archivo' => $fileType,
                                ':tamano_archivo' => $fileSize
                            ]);
                        }
                    }
                }
            }
            
            // Eliminar archivos si se solicitó
            if (isset($_POST['eliminar_archivos']) && is_array($_POST['eliminar_archivos'])) {
                foreach ($_POST['eliminar_archivos'] as $archivo_id) {
                    // Obtener información del archivo
                    $stmtGetFile = $db->prepare("
                        SELECT ruta_archivo FROM archivos_actividad 
                        WHERE id = :id AND id_actividad = :id_actividad
                    ");
                    $stmtGetFile->bindParam(':id', $archivo_id);
                    $stmtGetFile->bindParam(':id_actividad', $actividad_id);
                    $stmtGetFile->execute();
                    $archivo = $stmtGetFile->fetch(PDO::FETCH_ASSOC);
                    
                    if ($archivo) {
                        // Eliminar el archivo físico
                        if (file_exists($archivo['ruta_archivo'])) {
                            unlink($archivo['ruta_archivo']);
                        }
                        
                        // Eliminar el registro de la base de datos
                        $stmtDeleteFile = $db->prepare("
                            DELETE FROM archivos_actividad 
                            WHERE id = :id AND id_actividad = :id_actividad
                        ");
                        $stmtDeleteFile->bindParam(':id', $archivo_id);
                        $stmtDeleteFile->bindParam(':id_actividad', $actividad_id);
                        $stmtDeleteFile->execute();
                    }
                }
            }
            
            $mensaje = "Actividad actualizada correctamente.";
            $tipo_mensaje = "success";
        }
    } catch (PDOException $e) {
        $error = "Error al actualizar la actividad: " . $e->getMessage();
    }
}

// PROCESO DE ELIMINACIÓN DE ACTIVIDAD
if (isset($_POST['eliminar_actividad']) && $_POST['eliminar_actividad'] == '1') {
    try {
        $conexion = new Conexion();
        $db = $conexion->getConexion();

        // Eliminar archivos físicos y registros
        $stmtArchivos = $db->prepare("SELECT ruta_archivo FROM archivos_actividad WHERE id_actividad = :id_actividad");
        $stmtArchivos->bindParam(':id_actividad', $actividad_id);
        $stmtArchivos->execute();
        $archivos = $stmtArchivos->fetchAll(PDO::FETCH_ASSOC);
        foreach ($archivos as $archivo) {
            if (file_exists($archivo['ruta_archivo'])) {
                unlink($archivo['ruta_archivo']);
            }
        }
        $stmtDelArchivos = $db->prepare("DELETE FROM archivos_actividad WHERE id_actividad = :id_actividad");
        $stmtDelArchivos->bindParam(':id_actividad', $actividad_id);
        $stmtDelArchivos->execute();

        // Eliminar la actividad
        $stmtDelAct = $db->prepare("DELETE FROM actividades WHERE id = :id AND tutor_id = :tutor_id");
        $stmtDelAct->bindParam(':id', $actividad_id);
        $stmtDelAct->bindParam(':tutor_id', $tutor_id);
        $stmtDelAct->execute();

        // Redirigir después de eliminar
        header('Location: actividades_tutor.php?eliminado=1');
        exit();
    } catch (PDOException $e) {
        $error = "Error al eliminar la actividad: " . $e->getMessage();
    }
}

// Obtener detalles de la actividad
try {
    $conexion = new Conexion();
    $db = $conexion->getConexion();
    
    // Consultar la actividad
    $stmt = $db->prepare("
        SELECT * FROM actividades 
        WHERE id = :id AND tutor_id = :tutor_id
    ");
    $stmt->bindParam(':id', $actividad_id);
    $stmt->bindParam(':tutor_id', $tutor_id);
    $stmt->execute();
    
    $actividad = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$actividad) {
        // Si no se encuentra la actividad, redirigir
        header('Location: actividades_tutor.php');
        exit();
    }
    
    // Obtener archivos adjuntos
    $stmt = $db->prepare("
        SELECT * FROM archivos_actividad 
        WHERE id_actividad = :id_actividad
    ");
    $stmt->bindParam(':id_actividad', $actividad_id);
    $stmt->execute();
    
    $archivos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    $error = "Error al cargar los detalles de la actividad: " . $e->getMessage();
}

// Función para formatear tamaño de archivo
function formatearTamano($tamano) {
    $unidades = ['B', 'KB', 'MB', 'GB'];
    $i = 0;
    while ($tamano >= 1024 && $i < count($unidades) - 1) {
        $tamano /= 1024;
        $i++;
    }
    return round($tamano, 2) . ' ' . $unidades[$i];
}
?>

<!DOCTYPE html>
<html lang="es">
<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Actividad - Panel de Tutor</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <style>
        :root {
            --primary: #00a63d;
            --primary-light: #00c44b;
            --secondary: #f8f9fa;
        }
        
        body {
            background-color: #f5f5f5;
            font-family: 'Roboto', sans-serif;
        }
        
        .sidebar {
            background-color: var(--primary);
            color: white;
            height: 100vh;
            position: fixed;
            width: 250px;
            z-index: 1000;
        }
        
        .main-content {
            margin-left: 250px;
            padding: 20px;
        }
        
        .card {
            border: none;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .card-header {
            background-color: white;
            border-bottom: 1px solid #eee;
            padding: 1.5rem;
        }
        
        .form-control:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 0.2rem rgba(0, 166, 61, 0.25);
        }
        
        .btn-primary {
            background-color: var(--primary) !important;
            border-color: var(--primary) !important;
            color: #fff !important;
        }
        
        .btn-primary:hover {
            background-color: var(--primary-light) !important;
            border-color: var(--primary-light) !important;
            color: #fff !important;
        }
        
        .file-upload {
            border: 2px dashed #ddd;
            border-radius: 10px;
            padding: 20px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .file-upload:hover {
            border-color: var(--primary);
            background-color: rgba(0, 166, 61, 0.05);
        }
        
        .file-preview {
            margin-top: 20px;
        }
        
        .file-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 10px;
            background-color: #f8f9fa;
            border-radius: 5px;
            margin-bottom: 10px;
        }
        
        .file-item .remove-file {
            color: #dc3545;
            cursor: pointer;
        }
        
        .back-button {
            color: var(--primary);
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            margin-bottom: 1rem;
        }
        
        .back-button:hover {
            color: var(--primary-light);
            text-decoration: none;
        }
        
        .form-label {
            font-weight: 500;
            color: #495057;
        }
        
        .custom-checkbox .custom-control-label::before {
            border-color: var(--primary);
        }
        
        .custom-checkbox .custom-control-input:checked ~ .custom-control-label::before {
            background-color: var(--primary);
            border-color: var(--primary);
        }
        
        .existing-files {
            margin-bottom: 20px;
        }
        
        .existing-file {
            display: flex;
            align-items: center;
            padding: 10px;
            background-color: #f8f9fa;
            border-radius: 5px;
            margin-bottom: 10px;
        }
        
        .file-icon {
            margin-right: 10px;
            color: var(--primary);
        }
        
        .file-details {
            flex-grow: 1;
        }
        
        .file-name {
            font-weight: 500;
        }
        
        .file-meta {
            font-size: 0.8rem;
            color: #6c757d;
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <aside class="sidebar">
        <div class="p-4 sidebar-header">
            <h3 style="background: none; box-shadow: none; padding: 0; margin: 0;">
                <i class="fas fa-graduation-cap mr-2"></i>FET
            </h3>
            <div class="tutor-profile" style="margin-top: 15px; display: flex; align-items: center; background: var(--primary); border-radius: 8px; padding: 10px 12px;">
                <div style="background: #fff; border-radius: 50%; width: 38px; height: 38px; display: flex; align-items: center; justify-content: center; margin-right: 10px;">
                    <i class="fas fa-user-tie" style="color: var(--primary); font-size: 1.5rem;"></i>
                </div>
                <div style="color: #fff;">
                    <div style="font-weight: 500; font-size: 1rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 120px;">
                        <?php
                            $nombre_tutor = isset($tutor['nombre']) && isset($tutor['apellido'])
                                ? htmlspecialchars($tutor['nombre'] . ' ' . $tutor['apellido'])
                                : 'Derek Agmeth Quevedo';
                            echo $nombre_tutor;
                        ?>
                    </div>
                    <div style="font-size: 0.95em; color: #e0e0e0;">Tutor Académico</div>
                </div>
            </div>
        </div>
        <ul class="nav flex-column">
            <li class="nav-item">
                <a href="inicio_tutor.php" class="nav-link text-white">
                    <i class="fas fa-home mr-2"></i>Inicio
                </a>
            </li>
            <li class="nav-item">
                <a href="actividades_tutor.php" class="nav-link text-white active">
                    <i class="fas fa-tasks mr-2"></i>Actividades
                </a>
            </li>
            <li class="nav-item">
                <a href="clase_tutor.php" class="nav-link text-white">
                    <i class="fas fa-video mr-2"></i>Aula Virtual
                </a>
            </li>
            <li class="nav-item">
                <a href="material_tutor.php" class="nav-link text-white">
                    <i class="fas fa-book mr-2"></i>Material de Apoyo
                </a>
            </li>
        </ul>
    </aside>

    <!-- Main Content -->
    <div class="main-content">
        <a href="actividades_tutor.php" class="back-button">
            <i class="fas fa-arrow-left mr-2"></i>
            Volver a Actividades
        </a>
        
        <div class="card">
            <div class="card-header">
                <h2 class="mb-0">Editar Actividad</h2>
            </div>
            <div class="card-body">
                <?php if (isset($error)): ?>
                    <div class="alert alert-danger" role="alert">
                        <?php echo $error; ?>
                    </div>
                <?php endif; ?>
                
                <?php if (isset($mensaje)): ?>
                    <div class="alert alert-<?php echo $tipo_mensaje; ?>" role="alert">
                        <?php echo $mensaje; ?>
                    </div>
                <?php endif; ?>
                
                <form action="editar_actividad.php?id=<?php echo $actividad_id; ?>" method="POST" enctype="multipart/form-data" id="form-editar">
                    <div class="form-group">
                        <label class="form-label">Título de la actividad</label>
                        <input type="text" name="titulo" class="form-control" required
                               value="<?php echo htmlspecialchars($actividad['titulo']); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Descripción</label>
                        <textarea name="descripcion" class="form-control" rows="4" required><?php echo htmlspecialchars($actividad['descripcion']); ?></textarea>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-label">Tipo de actividad</label>
                                <select name="tipo" class="form-control" required>
                                    <option value="tarea" <?php echo $actividad['tipo'] == 'tarea' ? 'selected' : ''; ?>>Tarea</option>
                                    <option value="proyecto" <?php echo $actividad['tipo'] == 'proyecto' ? 'selected' : ''; ?>>Proyecto</option>
                                    <option value="examen" <?php echo $actividad['tipo'] == 'examen' ? 'selected' : ''; ?>>Examen</option>
                                    <option value="cuestionario" <?php echo $actividad['tipo'] == 'cuestionario' ? 'selected' : ''; ?>>Cuestionario</option>
                                    <option value="investigacion" <?php echo $actividad['tipo'] == 'investigacion' ? 'selected' : ''; ?>>Investigación</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-label">Puntaje máximo</label>
                                <input type="number" name="puntaje" class="form-control" 
                                       value="<?php echo $actividad['puntaje']; ?>" min="0.0" max="5.0" step="0.1" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-label">Fecha límite</label>
                                <input type="date" name="fecha_limite" class="form-control" 
                                       value="<?php echo $actividad['fecha_limite']; ?>" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-label">Hora límite</label>
                                <input type="time" name="hora_limite" class="form-control" 
                                       value="<?php echo $actividad['hora_limite']; ?>" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <div class="custom-control custom-checkbox">
                            <input type="checkbox" class="custom-control-input" 
                                   id="permitirEntregas" name="permitir_entregas_tarde"
                                   <?php echo $actividad['permitir_entregas_tarde'] ? 'checked' : ''; ?>>
                            <label class="custom-control-label" for="permitirEntregas">
                                Permitir entregas después de la fecha límite (con penalización)
                            </label>
                        </div>
                    </div>
                    
                    <?php if (count($archivos) > 0): ?>
                        <div class="form-group">
                            <label class="form-label">Archivos adjuntos existentes</label>
                            <div class="existing-files">
                                <?php foreach ($archivos as $archivo): ?>
                                    <div class="existing-file">
                                        <div class="file-icon">
                                            <i class="fas fa-file"></i>
                                        </div>
                                        <div class="file-details">
                                            <div class="file-name"><?php echo htmlspecialchars($archivo['nombre_archivo']); ?></div>
                                            <div class="file-meta">
                                                <?php echo strtoupper($archivo['tipo_archivo']); ?> · 
                                                <?php echo formatearTamano($archivo['tamano_archivo']); ?>
                                            </div>
                                        </div>
                                        <div class="custom-control custom-checkbox">
                                            <input type="checkbox" class="custom-control-input" 
                                                   id="eliminar_<?php echo $archivo['id']; ?>" 
                                                   name="eliminar_archivos[]" 
                                                   value="<?php echo $archivo['id']; ?>">
                                            <label class="custom-control-label text-danger" for="eliminar_<?php echo $archivo['id']; ?>">
                                                Eliminar
                                            </label>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <div class="form-group">
                        <label class="form-label">Añadir nuevos archivos (opcional)</label>
                        <div class="file-upload" onclick="document.getElementById('adjuntos').click()">
                            <i class="fas fa-cloud-upload-alt fa-2x mb-2"></i>
                            <p class="mb-0">Arrastra archivos aquí o haz clic para seleccionar</p>
                            <small class="text-muted">PDF, DOCX, PPTX, XLSX, ZIP hasta 10MB</small>
                            <input type="file" id="adjuntos" name="adjuntos[]" multiple 
                                   style="display: none" onchange="handleFileSelect(this)">
                        </div>
                        <div id="filePreview" class="file-preview"></div>
                    </div>
                    
                    
                </form>
                <!-- Cierra el form de edición aquí -->
            </div>
        </div>
        <!-- Formulario de eliminar fuera del form principal -->
        <div class="text-right mt-4 d-flex justify-content-between align-items-center">
            <form method="POST" id="form-eliminar-actividad" style="margin-bottom:0;">
                <input type="hidden" name="eliminar_actividad" value="1">
                <button type="button" class="btn btn-danger" onclick="confirmarEliminacion()">
                    <i class="fas fa-trash-alt mr-2"></i>Eliminar Actividad
                </button>
            </form>
            <div>
                <a href="actividades_tutor.php" class="btn btn-secondary mr-2">Cancelar</a>
                <button type="submit" class="btn btn-primary" form="form-editar">
                    <i class="fas fa-save mr-2"></i>Guardar Cambios
                </button>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <script>
        function handleFileSelect(input) {
            const filePreview = document.getElementById('filePreview');
            filePreview.innerHTML = '';
            
            Array.from(input.files).forEach((file, index) => {
                const fileItem = document.createElement('div');
                fileItem.className = 'file-item';
                fileItem.innerHTML = `
                    <span>${file.name}</span>
                    <i class="fas fa-times remove-file" onclick="removeFile(${index}, this)"></i>
                `;
                filePreview.appendChild(fileItem);
            });
        }
        

        function removeFile(index, element) {
            const input = document.getElementById('adjuntos');
            const dt = new DataTransfer();
            
            Array.from(input.files)
                .filter((file, i) => i !== index)
                .forEach(file => dt.items.add(file));
                
            input.files = dt.files;
            element.parentElement.remove();
        }

        function confirmarEliminacion() {
            if (confirm('⚠️ ¡Atención!\n\nEsta acción eliminará la actividad y todos sus archivos adjuntos de forma permanente.\n¿Estás seguro de que deseas continuar?')) {
                document.getElementById('form-eliminar-actividad').submit();
            }
        }

        function eliminarActividad(id) {
            if (confirm('¿Estás seguro de que deseas eliminar esta actividad? Esta acción no se puede deshacer.')) {
                window.location.href = 'eliminar_actividad.php?id=' + id;
            }
        }
    </script>
</body>
</html>
