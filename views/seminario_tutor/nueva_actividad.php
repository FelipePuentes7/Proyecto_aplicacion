<?php
// Procesar el formulario cuando se envía
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once '../../config/conexion.php';
    
    try {
        $conexion = new Conexion();
        $db = $conexion->getConexion();
        
        // Primero, añadimos las columnas faltantes si no existen
        try {
            $db->exec("ALTER TABLE actividades ADD COLUMN IF NOT EXISTS puntaje decimal(5,2) DEFAULT NULL");
            $db->exec("ALTER TABLE actividades ADD COLUMN IF NOT EXISTS permitir_entregas_tarde tinyint(1) DEFAULT 0");
        } catch (PDOException $e) {
            // Si falla, continuamos de todos modos
            // Puede fallar si la base de datos no soporta IF NOT EXISTS
        }
        
        // Preparar la consulta
                $stmt = $db->prepare("
            INSERT INTO actividades (
                tutor_id, titulo, descripcion, tipo, 
                fecha_limite, hora_limite, puntaje, 
                permitir_entregas_tarde, fecha_creacion
            ) VALUES (
                1, :titulo, :descripcion, :tipo,
                COALESCE(:fecha_limite, CURDATE()), :hora_limite, :puntaje,
                :permitir_entregas_tarde, NOW()
            )
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
        
        // Ejecutar la consulta
        if ($stmt->execute()) {
            $actividadId = $db->lastInsertId();
            
            // Procesar archivos adjuntos si existen
            if (!empty($_FILES['adjuntos']['name'][0])) {
                $uploadDir = '../../uploads/actividades/' . $actividadId . '/';
                if (!file_exists($uploadDir)) {
                    mkdir($uploadDir, 0777, true);
                }
                
                foreach ($_FILES['adjuntos']['tmp_name'] as $key => $tmp_name) {
                    $fileName = $_FILES['adjuntos']['name'][$key];
                    $filePath = $uploadDir . $fileName;
                    
                    if (move_uploaded_file($tmp_name, $filePath)) {
                        // Guardar referencia del archivo en la base de datos
                        // Corregido: usar id_actividad en lugar de actividad_id
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
                            ':id_actividad' => $actividadId,
                            ':nombre_archivo' => $fileName,
                            ':ruta_archivo' => $filePath,
                            ':tipo_archivo' => $fileType,
                            ':tamano_archivo' => $fileSize
                        ]);
                    }
                }
            }
            
            header('Location: actividades_tutor.php?success=1');
            exit();
        }
    } catch (PDOException $e) {
        $error = "Error al crear la actividad: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nueva Actividad - Panel de Tutor</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <style>
        :root {
            --primary: #039708;
            --primary-light: #039708;
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

          .sidebar {
        display: flex;
        flex-direction: column;
        height: 100vh;
        background-color: #039708; /* o tu color verde institucional */
        }

        .logout-btn:hover {
        color: white;
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
        .btn-secondary {
            background-color: #e9ecef !important;
            color: var(--primary) !important;
            border-color: var(--primary) !important;
        }
        .btn-secondary:hover {
            background-color: var(--primary-light) !important;
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
    </style>
</head>
<body>
    <!-- Sidebar -->
    <aside class="sidebar">
        <div class="p-4 sidebar-header">
            <h3 style="background: none; box-shadow: none; padding: 0; margin: 0;">
                <img src="/assets/images/logofet.png" alt="FET Logo" style="width: 100px;">
            </h3>
            <div class="tutor-profile" style="margin-top: 15px; display: flex; align-items: center; background: var(--primary); border-radius: 8px; padding: 10px 12px;">
                <div style="background: #fff; border-radius: 50%; width: 38px; height: 38px; display: flex; align-items: center; justify-content: center; margin-right: 10px;">
                    <i class="fas fa-user-tie" style="color: var(--primary); font-size: 1.5rem;"></i>
                </div>
                <div style="color: #fff;">

                <div style="font-size: 0.95em; color: #e0e0e0;">Tutor Seminario</div>
                </div>
            </div>
        </div>
        <ul class="nav flex-column">
            <li class="nav-item">
                <a href="../seminario_tutor/inicio_tutor.php" class="nav-link text-white">
                    <i class="fas fa-home mr-2"></i>Inicio
                </a>
            </li>
            <li class="nav-item">
                <a href="../seminario_tutor/actividades_tutor.php" class="nav-link text-white active">
                    <i class="fas fa-tasks mr-2"></i>Actividades
                </a>
            </li>
            <li class="nav-item">
                <a href="../seminario_tutor/clase_tutor.php" class="nav-link text-white">
                    <i class="fas fa-video mr-2"></i>Aula Virtual
                </a>
            </li>
            <li class="nav-item">
                <a href="../seminario_tutor/material_tutor.php" class="nav-link text-white">
                    <i class="fas fa-book mr-2"></i>Material de Apoyo
                </a>
            </li>
        </ul>


            <!-- Botón de cerrar sesión fijo abajo -->
                <a href="/views/general/login.php" class="logout-btn" style="margin-top: auto; padding: 15px 20px; color: rgba(255, 255, 255, 0.8); text-decoration: none; display: flex; align-items: center;">
                    <i class="fas fa-sign-out-alt" style="margin-right: 10px;"></i> Cerrar sesión
                </a>


    </aside>

    <!-- Main Content -->
    <div class="main-content">
        <a href="actividades_tutor.php" class="back-button">
            <i class="fas fa-arrow-left mr-2"></i>
            Volver a Actividades
        </a>
        
        <div class="card">
            <div class="card-header">
                <h2 class="mb-0">Nueva Actividad</h2>
            </div>
            <div class="card-body">
                <?php if (isset($error)): ?>
                    <div class="alert alert-danger" role="alert">
                        <?php echo $error; ?>
                    </div>
                <?php endif; ?>
                
                <form action="nueva_actividad.php" method="POST" enctype="multipart/form-data">
                    <div class="form-group">
                        <label class="form-label">Título de la actividad</label>
                        <input type="text" name="titulo" class="form-control" required
                               placeholder="Ej: Investigación sobre algoritmos de ordenamiento">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Descripción</label>
                        <textarea name="descripcion" class="form-control" rows="4" required
                                  placeholder="Describe detalladamente los objetivos y las instrucciones de la actividad..."></textarea>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-label">Tipo de actividad</label>
                                <select name="tipo" class="form-control" required>
                                    <option value="tarea">Tarea</option>
                                    <option value="proyecto">Proyecto</option>
                                    <option value="examen">Examen</option>
                                    <option value="cuestionario">Cuestionario</option>
                                    <option value="investigacion">Investigación</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-label">Puntaje máximo</label>
                                <input type="number" name="puntaje" class="form-control" 
                                       value="0.0" min="0.0" max="5.0" step="0.1" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-label">Fecha límite</label>
                                <input type="date" name="fecha_limite" class="form-control" 
                                       min="<?php echo date('Y-m-d'); ?>" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-label">Hora límite</label>
                                <input type="time" name="hora_limite" class="form-control" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <div class="custom-control custom-checkbox">
                            <input type="checkbox" class="custom-control-input" 
                                   id="permitirEntregas" name="permitir_entregas_tarde">
                            <label class="custom-control-label" for="permitirEntregas">
                                Permitir entregas después de la fecha límite (con penalización)
                            </label>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Archivos adjuntos (opcional)</label>
                        <div class="file-upload" onclick="document.getElementById('adjuntos').click()">
                            <i class="fas fa-cloud-upload-alt fa-2x mb-2"></i>
                            <p class="mb-0">Arrastra archivos aquí o haz clic para seleccionar</p>
                            <small class="text-muted">PDF, DOCX, PPTX, XLSX, ZIP hasta 10MB</small>
                            <input type="file" id="adjuntos" name="adjuntos[]" multiple 
                                   style="display: none" onchange="handleFileSelect(this)">
                        </div>
                        <div id="filePreview" class="file-preview"></div>
                    </div>
                    
                    <div class="text-right mt-4">
                        <a href="actividades_tutor.php" class="btn btn-secondary mr-2">Cancelar</a>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save mr-2"></i>Guardar Actividad
                        </button>
                    </div>
                </form>
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
    </script>
</body>
</html>