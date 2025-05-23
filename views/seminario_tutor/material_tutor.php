<?php
// Start session management
session_start();

<<<<<<< HEAD
// Mock data for students
$students = [
    [
        'id' => 1,
        'name' => 'Noah Wilson',
        'email' => 'noah.w@example.com',
        'avatar' => 'https://randomuser.me/api/portraits/men/32.jpg'
    ],
    [
        'id' => 2,
        'name' => 'Olivia Brown',
        'email' => 'olivia.b@example.com',
        'avatar' => 'https://randomuser.me/api/portraits/women/65.jpg'
    ],
    [
        'id' => 3,
        'name' => 'Liam Smith',
        'email' => 'liam.s@example.com',
        'avatar' => 'https://randomuser.me/api/portraits/men/22.jpg'
    ],
    [
        'id' => 4,
        'name' => 'Ava Davis',
        'email' => 'ava.d@example.com',
        'avatar' => 'https://randomuser.me/api/portraits/women/17.jpg'
    ],
    [
        'id' => 5,
        'name' => 'Ethan Miller',
        'email' => 'ethan.m@example.com',
        'avatar' => 'https://randomuser.me/api/portraits/men/42.jpg'
    ]
];
=======
// Include database connection
require_once '../../config/conexion.php';

// Create connection instance
$conexion = new Conexion();
$db = $conexion->getConexion();

// Función para validar URL de documento
function validateDocumentUrl($url) {
    return filter_var($url, FILTER_VALIDATE_URL) !== false;
}

// Obtener estudiantes activos
try {
    $stmt = $db->prepare("
        SELECT u.id, u.nombre, u.email, u.avatar 
        FROM usuarios u 
        WHERE u.opcion_grado = 'seminario' 
        AND u.rol = 'estudiante' 
        AND u.estado = 'activo'
    ");
    $stmt->execute();
    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $students = [];
}
>>>>>>> origin/Master

// Mock data for material categories
$categories = [
    'video_links' => 'Enlaces de Video',
    'documents' => 'Documentación',
];

// Get current step (default to 1) and category
$currentStep = isset($_GET['step']) ? intval($_GET['step']) : 1;
$category = isset($_GET['category']) ? $_GET['category'] : '';

<<<<<<< HEAD
// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Store form data in session
    if ($currentStep === 1 && isset($_POST['title'])) {
        $_SESSION['material'] = [
            'category' => $_POST['category'],
            'title' => $_POST['title'],
            'description' => $_POST['description'],
        ];
        
        // Store additional information based on category
        if ($_POST['category'] === 'video_links') {
            $_SESSION['material']['video_url'] = $_POST['video_url'];
            $_SESSION['material']['platform'] = $_POST['platform'];
        } else if ($_POST['category'] === 'documents') {
            $_SESSION['material']['filename'] = $_POST['filename'] ?? '';
            $_SESSION['material']['filesize'] = $_POST['filesize'] ?? '';
        }
        
        // Redirect to step 2
        header('Location: material_tutor.php?step=2&category=' . $_POST['category']);
        exit;
    } elseif ($currentStep === 2 && isset($_POST['selected_students'])) {
        // Store selected students
        $_SESSION['material']['selected_students'] = $_POST['selected_students'];
        // Redirect to step 3
        header('Location: material_tutor.php?step=3&category=' . $_POST['category']);
        exit;
    } elseif ($currentStep === 3 && isset($_POST['share'])) {
        // Process material sharing (in a real app, this would save to database)
        // For demo purposes, we'll just show success message
        $_SESSION['material_shared'] = true;
        // Redirect to success page
        header('Location: material_tutor.php?step=4&category=' . $_SESSION['material']['category']);
        exit;
=======
// Variables para manejo de errores y éxito
$errors = [];
$success = false;

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Check if the materiales_apoyo table exists
        $stmt = $db->prepare("SHOW TABLES LIKE 'materiales_apoyo'");
        $stmt->execute();
        $table_exists = $stmt->rowCount() > 0;
        
        if (!$table_exists) {
            // Create the materiales_apoyo table if it doesn't exist
            $db->exec("
                CREATE TABLE IF NOT EXISTS `materiales_apoyo` (
                  `id` int(11) NOT NULL AUTO_INCREMENT,
                  `titulo` varchar(255) NOT NULL,
                  `descripcion` text DEFAULT NULL,
                  `tipo` varchar(50) NOT NULL,
                  `plataforma` varchar(50) DEFAULT NULL,
                  `enlace` varchar(255) DEFAULT NULL,
                  `thumbnail_url` varchar(255) DEFAULT NULL,
                  `fecha_creacion` timestamp NOT NULL DEFAULT current_timestamp(),
                  PRIMARY KEY (`id`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
            ");
        }
        
        // Verificar si existe la columna plataforma
        $stmt = $db->prepare("
            SHOW COLUMNS FROM `materiales_apoyo` LIKE 'plataforma'
        ");
        $stmt->execute();
        $columna_existe = $stmt->rowCount() > 0;

        if (!$columna_existe) {
            // Agregar la columna plataforma si no existe
            $db->exec("
                ALTER TABLE `materiales_apoyo` 
                ADD COLUMN `plataforma` varchar(50) DEFAULT NULL
            ");
        }

        // Verificar si existe la columna thumbnail_url
        $stmt = $db->prepare("
            SHOW COLUMNS FROM `materiales_apoyo` LIKE 'thumbnail_url'
        ");
        $stmt->execute();
        $columna_existe = $stmt->rowCount() > 0;

        if (!$columna_existe) {
            // Agregar la columna thumbnail_url si no existe
            $db->exec("
                ALTER TABLE `materiales_apoyo` 
                ADD COLUMN `thumbnail_url` varchar(255) DEFAULT NULL
            ");
        }
        
        // Verificar si existe la columna enlace
        $stmt = $db->prepare("
            SHOW COLUMNS FROM `materiales_apoyo` LIKE 'enlace'
        ");
        $stmt->execute();
        $columna_existe = $stmt->rowCount() > 0;

        if (!$columna_existe) {
            // Agregar la columna enlace si no existe
            $db->exec("
                ALTER TABLE `materiales_apoyo` 
                ADD COLUMN `enlace` varchar(255) DEFAULT NULL
            ");
        }
        
        // Verificar si existe la tabla asignaciones_material
        $stmt = $db->prepare("SHOW TABLES LIKE 'asignaciones_material'");
        $stmt->execute();
        $tabla_existe = $stmt->rowCount() > 0;

        if (!$tabla_existe) {
            // Crear la tabla asignaciones_material si no existe
            $db->exec("
                CREATE TABLE `asignaciones_material` (
                  `id` int(11) NOT NULL AUTO_INCREMENT,
                  `material_id` int(11) NOT NULL,
                  `estudiante_id` int(11) NOT NULL,
                  `fecha_asignacion` timestamp NOT NULL DEFAULT current_timestamp(),
                  PRIMARY KEY (`id`),
                  KEY `material_id` (`material_id`),
                  KEY `estudiante_id` (`estudiante_id`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
            ");
        }
        
        // Verificar si existe la tabla usuarios
        $stmt = $db->prepare("SHOW TABLES LIKE 'usuarios'");
        $stmt->execute();
        $tabla_usuarios_existe = $stmt->rowCount() > 0;

        if (!$tabla_usuarios_existe) {
            // Crear la tabla usuarios si no existe
            $db->exec("
                CREATE TABLE `usuarios` (
                  `id` int(11) NOT NULL AUTO_INCREMENT,
                  `nombre` varchar(100) NOT NULL,
                  `apellido` varchar(100) NOT NULL,
                  `email` varchar(255) NOT NULL,
                  `avatar` varchar(255) DEFAULT NULL,
                  PRIMARY KEY (`id`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
            ");
        }
        
        // Verificar si existe la tabla estudiantes
        $stmt = $db->prepare("SHOW TABLES LIKE 'estudiantes'");
        $stmt->execute();
        $tabla_estudiantes_existe = $stmt->rowCount() > 0;

        if (!$tabla_estudiantes_existe) {
            // Crear la tabla estudiantes si no existe
            $db->exec("
                CREATE TABLE `estudiantes` (
                  `id` int(11) NOT NULL AUTO_INCREMENT,
                  `usuario_id` int(11) NOT NULL,
                  `nombre` varchar(255) NOT NULL,
                  `avatar` varchar(255) DEFAULT NULL,
                  PRIMARY KEY (`id`),
                  KEY `usuario_id` (`usuario_id`),
                  CONSTRAINT `estudiantes_ibfk_1` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
            ");
        }
        
        // Antes de asignar materiales, verificar si los estudiantes existen en la tabla estudiantes
        foreach ($students as $student) {
            // Primero, verificar si el usuario ya existe en la tabla usuarios
            $stmt = $db->prepare("
                SELECT id FROM usuarios WHERE id = :id
            ");
            $stmt->bindParam(':id', $student['id']);
            $stmt->execute();
            
            if ($stmt->rowCount() == 0) {
                // El usuario no existe, crearlo primero
                $stmt = $db->prepare("
                    INSERT INTO usuarios (
                        id, 
                        nombre,
                        email,
                        avatar
                    ) VALUES (
                        :id,
                        :nombre,
                        :email,
                        :avatar
                    )
                ");
                
                $stmt->bindParam(':id', $student['id']);
                $stmt->bindParam(':nombre', $student['name']);
                $stmt->bindParam(':email', $student['email']);
                $stmt->bindParam(':avatar', $student['avatar']);
                $stmt->execute();
            }
        }

        // Modificar el código para procesar videos y documentos directamente sin selección de estudiantes
        if ($currentStep === 1 && isset($_POST['title']) && ($_POST['category'] === 'video_links' || $_POST['category'] === 'documents')) {
            // Insert into materiales_apoyo table
            if ($_POST['category'] === 'video_links') {
                $stmt = $db->prepare("
                    INSERT INTO materiales_apoyo (
                        titulo, 
                        descripcion, 
                        tipo, 
                        plataforma, 
                        enlace,
                        thumbnail_url
                    ) VALUES (
                        :titulo, 
                        :descripcion, 
                        'videos', 
                        :plataforma, 
                        :enlace,
                        :thumbnail_url
                    )
                ");
                
                $stmt->bindParam(':titulo', $_POST['title']);
                $stmt->bindParam(':descripcion', $_POST['description']);
                $stmt->bindParam(':plataforma', $_POST['platform']);
                $stmt->bindParam(':enlace', $_POST['video_url']);
                $stmt->bindParam(':thumbnail_url', $_POST['thumbnail_url']);
                $stmt->execute();
                
                $material_id = $db->lastInsertId();
                
                // Desactivar verificación de claves foráneas temporalmente
                $db->exec("SET FOREIGN_KEY_CHECKS = 0");

                // Asignar solo al usuario específico seleccionado
                $estudiante_id = isset($_POST['estudiante_id']) && !empty($_POST['estudiante_id']) ? $_POST['estudiante_id'] : null;
                
                if ($estudiante_id) {
                    $stmt = $db->prepare("
                        INSERT INTO asignaciones_material (
                            material_id, 
                            estudiante_id
                        ) VALUES (
                            :material_id, 
                            :usuario_id
                        )
                    ");
                    
                    $stmt->bindParam(':material_id', $material_id);
                    $stmt->bindParam(':usuario_id', $estudiante_id);
                    $stmt->execute();
                } else {
                    // Si no se seleccionó un usuario específico, asignar a todos (comportamiento anterior)
                    // Get all students with seminario option_grado
                    $stmt = $db->prepare("
                        SELECT id FROM usuarios 
                        WHERE opcion_grado = 'seminario' AND rol = 'estudiante' AND estado = 'activo'
                    ");
                    $stmt->execute();
                    $all_students = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    foreach ($all_students as $student) {
                        $stmt = $db->prepare("
                            INSERT INTO asignaciones_material (
                                material_id, 
                                estudiante_id
                            ) VALUES (
                                :material_id, 
                                :usuario_id
                            )
                        ");
                        
                        $stmt->bindParam(':material_id', $material_id);
                        $stmt->bindParam(':usuario_id', $student['id']);
                        $stmt->execute();
                    }
                }
                
                // Reactivar verificación de claves foráneas
                $db->exec("SET FOREIGN_KEY_CHECKS = 1");

                // Guardar datos en sesión para mostrar confirmación
                $_SESSION['material'] = [
                    'category' => $_POST['category'],
                    'title' => $_POST['title'],
                    'description' => $_POST['description'],
                    'video_url' => $_POST['video_url'],
                    'platform' => $_POST['platform'],
                    'thumbnail_url' => $_POST['thumbnail_url'] ?? '',
                    'estudiante_id' => $estudiante_id
                ];
                
                // Set success flag
                $_SESSION['material_shared'] = true;

                // Check if it's an AJAX request
                if (isset($_POST['ajax_submit'])) {
                    // If AJAX, just output success response
                    echo "success";
                    exit;
                } else {
                    // If not AJAX, redirect as before
                    header('Location: material_apoyo.php?categoria=videos');
                    exit;
                }
            } else if ($_POST['category'] === 'documents') {
                // Process document upload
                if (empty($_POST['title'])) {
                    throw new Exception('El título es requerido');
                }

                // Handle file upload
                $uploadDir = '../../uploads/documents/';
                if (!file_exists($uploadDir)) {
                    mkdir($uploadDir, 0777, true);
                }

                $fileName = '';
                if (isset($_FILES['document-file']) && $_FILES['document-file']['error'] === UPLOAD_ERR_OK) {
                    $tmpName = $_FILES['document-file']['tmp_name'];
                    $originalName = $_FILES['document-file']['name'];
                    $fileExtension = pathinfo($originalName, PATHINFO_EXTENSION);
                    $fileName = uniqid() . '.' . $fileExtension;
                    $uploadFile = $uploadDir . $fileName;

                    if (!move_uploaded_file($tmpName, $uploadFile)) {
                        throw new Exception('Error al subir el archivo');
                    }
                }

                // Insert into database
                $stmt = $db->prepare("
                    INSERT INTO materiales_apoyo (
                        titulo, 
                        descripcion, 
                        tipo, 
                        enlace,
                        fecha_creacion
                    ) VALUES (
                        :titulo, 
                        :descripcion, 
                        'documentacion',
                        :enlace,
                        CURRENT_TIMESTAMP
                    )
                ");

                $title = $_POST['title'];
                $description = $_POST['description'] ?? '';
                $documentUrl = $_POST['document_url'] ?? '';

                $stmt->bindParam(':titulo', $title);
                $stmt->bindParam(':descripcion', $description);
                $stmt->bindParam(':enlace', $documentUrl);

                if ($stmt->execute()) {
                    $material_id = $db->lastInsertId();

                    // Asignar a todos los estudiantes activos
                    $stmt = $db->prepare("
                        INSERT INTO asignaciones_material (
                            material_id, 
                            estudiante_id,
                            fecha_asignacion
                        ) VALUES (
                            :material_id, 
                            :estudiante_id,
                            CURRENT_TIMESTAMP
                        )
                    ");

                    // Si se seleccionó un estudiante específico
                    if (!empty($_POST['estudiante_id'])) {
                        $estudiante_id = $_POST['estudiante_id'];
                        $stmt->bindParam(':material_id', $material_id);
                        $stmt->bindParam(':estudiante_id', $estudiante_id);
                        $stmt->execute();
                    } else {
                        // Asignar a todos los estudiantes activos
                        $stmt_students = $db->prepare("
                            SELECT id FROM usuarios 
                            WHERE opcion_grado = 'seminario' 
                            AND rol = 'estudiante' 
                            AND estado = 'activo'
                        ");
                        $stmt_students->execute();
                        $students = $stmt_students->fetchAll(PDO::FETCH_ASSOC);

                        foreach ($students as $student) {
                            $stmt->bindParam(':material_id', $material_id);
                            $stmt->bindParam(':estudiante_id', $student['id']);
                            $stmt->execute();
                        }
                    }

                    // Guardar datos en sesión para mostrar confirmación
                    $_SESSION['material'] = [
                        'category' => 'documents',
                        'title' => $title,
                        'description' => $description,
                        'document_url' => $documentUrl,
                        'filename' => $fileName,
                        'filesize' => isset($_FILES['document-file']) ? $_FILES['document-file']['size'] : 0
                    ];

                    // Set success flag
                    $_SESSION['material_shared'] = true;

                    // Si es una petición AJAX, devolver respuesta JSON
                    if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
                        header('Content-Type: application/json');
                        echo json_encode(['success' => true, 'message' => 'Documento subido exitosamente']);
                        exit;
                    }

                    // Redirigir a la página de éxito
                    header('Location: material_tutor.php?step=4&category=documents');
                    exit;
                } else {
                    throw new Exception('Error al guardar el material en la base de datos');
                }
            }
        }
        // Store form data in session for other categories
        else if ($currentStep === 1 && isset($_POST['title'])) {
            $_SESSION['material'] = [
                'category' => $_POST['category'],
                'title' => $_POST['title'],
                'description' => $_POST['description'],
            ];
            
            // Store additional information based on category
            if ($_POST['category'] === 'documents') {
                $_SESSION['material']['filename'] = $_POST['filename'] ?? '';
                $_SESSION['material']['filesize'] = $_POST['filesize'] ?? '';
                $_SESSION['material']['document_url'] = $_POST['document_url'] ?? '';
            }
            
            // Redirect to step 2
            header('Location: material_tutor.php?step=2&category=' . $_POST['category']);
            exit;
        } elseif ($currentStep === 2 && isset($_POST['selected_students'])) {
            // Store selected students
            $_SESSION['material']['selected_students'] = $_POST['selected_students'];
            // Redirect to step 3
            header('Location: material_tutor.php?step=3&category=' . $_POST['category']);
            exit;
        } elseif ($currentStep === 3 && isset($_POST['share'])) {
            // Process material sharing (in a real app, this would save to database)
            // For demo purposes, we'll just show success message
            $_SESSION['material_shared'] = true;
            // Redirect to success page
            header('Location: material_tutor.php?step=4&category=' . $_SESSION['material']['category']);
            exit;
        }
    } catch (Exception $e) {
        // Si es una petición AJAX, devolver error en formato JSON
        if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
            header('Content-Type: application/json');
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            exit;
        }

        // Si no es AJAX, mostrar error en la página
        $errors['database'] = "Error al guardar el material: " . $e->getMessage();
>>>>>>> origin/Master
    }
}

// Get material data from session
$materialData = $_SESSION['material'] ?? [];

<<<<<<< HEAD
=======
// Set default values if not present
if (!isset($materialData['category']) && isset($_GET['category'])) {
    $materialData['category'] = $_GET['category'];
}

>>>>>>> origin/Master
// Get selected students data
$selectedStudents = [];
if (isset($materialData['selected_students'])) {
    foreach ($materialData['selected_students'] as $studentId) {
        foreach ($students as $student) {
            if ($student['id'] == $studentId) {
                $selectedStudents[] = $student;
                break;
            }
        }
    }
}

// Function to get step status
function getStepStatus($step, $currentStep) {
    if ($step < $currentStep) {
        return 'completed';
    } elseif ($step === $currentStep) {
        return 'active';
    } else {
        return 'pending';
    }
}

// Function to get icon class for video platform
function getPlatformIcon($platform) {
    $icons = [
        'tiktok' => 'fab fa-tiktok',
        'youtube' => 'fab fa-youtube',
        'instagram' => 'fab fa-instagram',
        'facebook' => 'fab fa-facebook',
        'vimeo' => 'fab fa-vimeo',
        'other' => 'fas fa-link'
    ];
<<<<<<< HEAD
    
=======

>>>>>>> origin/Master
    return $icons[$platform] ?? $icons['other'];
}

// Function to get file icon
function getDocumentIcon($filename) {
    $extension = pathinfo($filename, PATHINFO_EXTENSION);
<<<<<<< HEAD
    
=======

>>>>>>> origin/Master
    $icons = [
        'pdf' => 'fas fa-file-pdf',
        'doc' => 'fas fa-file-word',
        'docx' => 'fas fa-file-word',
        'xls' => 'fas fa-file-excel',
        'xlsx' => 'fas fa-file-excel',
        'ppt' => 'fas fa-file-powerpoint',
        'pptx' => 'fas fa-file-powerpoint',
        'txt' => 'fas fa-file-alt',
        'zip' => 'fas fa-file-archive',
        'rar' => 'fas fa-file-archive',
        'jpg' => 'fas fa-file-image',
        'jpeg' => 'fas fa-file-image',
        'png' => 'fas fa-file-image',
        'gif' => 'fas fa-file-image'
    ];
<<<<<<< HEAD
    
    return $icons[strtolower($extension)] ?? 'fas fa-file';
}
=======

    return $icons[strtolower($extension)] ?? 'fas fa-file';
}

// Function to extract video ID from URL
function getVideoId($url, $platform) {
    if ($platform === 'youtube') {
        // YouTube URL patterns
        $patterns = [
            '/youtube\.com\/watch\?v=([a-zA-Z0-9_-]+)/',
            '/youtu\.be\/([a-zA-Z0-9_-]+)/',
            '/youtube\.com\/embed\/([a-zA-Z0-9_-]+)/'
        ];
        
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $url, $matches)) {
                return $matches[1];
            }
        }
    } elseif ($platform === 'vimeo') {
        // Vimeo URL patterns
        $patterns = [
            '/vimeo\.com\/([0-9]+)/',
            '/player\.vimeo\.com\/video\/([0-9]+)/'
        ];
        
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $url, $matches)) {
                return $matches[1];
            }
        }
    }

    return null;
}

// Function to get video thumbnail URL
function getVideoThumbnail($url, $platform) {
    $videoId = getVideoId($url, $platform);

    if ($videoId) {
        if ($platform === 'youtube') {
            // YouTube thumbnails
            return "https://img.youtube.com/vi/{$videoId}/maxresdefault.jpg";
        } elseif ($platform === 'vimeo') {
            // Vimeo thumbnails require API call, using placeholder for demo
            return "https://via.placeholder.com/640x360.png?text=Vimeo+Video";
        }
    }

    // Default placeholder
    return "https://via.placeholder.com/640x360.png?text=Video+Thumbnail";
}
>>>>>>> origin/Master
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FET - Material de Apoyo</title>
<<<<<<< HEAD
    <link rel="stylesheet" href="/assets/css/tutor_css/material_tutor.css">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <div class="container">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <div class="logo">
                    <i class="fas fa-graduation-cap"></i>
                    <h1>FET</h1>
                </div>
            </div>
            
            <nav class="sidebar-nav">
                <ul>
                    <li><a href="inicio_tutor.php"><i class="fas fa-home"></i> Inicio</a></li>
                    <li><a href="actividades_tutor.php"><i class="fas fa-book"></i> Actividades</a></li>
                    <li><a href="clase_tutor.php"><i class="fas fa-video"></i> Aula Virtual</a></li>
                    <li class="active"><a href="material_tutor.php"><i class="fas fa-file-alt"></i> Material de Apoyo</a></li>
                </ul>
            </nav>

            <div class="sidebar-footer">
                <a href="#" class="logout-btn"><i class="fas fa-sign-out-alt"></i> Cerrar Sesión</a>
            </div>
        </aside>
        
        <!-- Main Content -->
        <main class="main-content">
            <header class="main-header">
                <h2>Material de Apoyo</h2>
                <p class="subtitle">Comparte recursos educativos con tus estudiantes</p>
                <div class="header-actions">
                    <button class="notification-btn">
                        <i class="fas fa-bell"></i>
                    </button>
                </div>
            </header>
            
            <section class="content-section">
                <?php if ($currentStep === 1 && $category === ''): ?>
                    <!-- Category Selection -->
                    <div class="material-categories">
                        <h3>Selecciona el tipo de material que deseas compartir</h3>
                        <div class="category-cards">
                            <a href="material_tutor.php?step=1&category=video_links" class="category-card">
                                <div class="category-icon">
                                    <i class="fas fa-video"></i>
                                </div>
                                <h4>Enlaces de Video</h4>
                                <p>Comparte videos de TikTok, Instagram, YouTube y más</p>
                            </a>
                            <a href="material_tutor.php?step=1&category=documents" class="category-card">
                                <div class="category-icon">
                                    <i class="fas fa-file-alt"></i>
                                </div>
                                <h4>Documentación</h4>
                                <p>Comparte archivos PDF, documentos, presentaciones, etc.</p>
                            </a>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="upload-container">
                        <div class="upload-header">
                            <h3>
                                <?php if ($category === 'video_links' || $materialData['category'] === 'video_links'): ?>
                                    <i class="fas fa-video"></i> Compartir Enlaces de Video
                                <?php else: ?>
                                    <i class="fas fa-file-alt"></i> Compartir Documentación
                                <?php endif; ?>
                            </h3>
                        </div>
                        
                        <!-- Progress Steps -->
                        <div class="progress-steps">
                            <div class="step <?php echo getStepStatus(1, $currentStep); ?>">
                                <div class="step-number">
                                    <?php if (getStepStatus(1, $currentStep) === 'completed'): ?>
                                        <i class="fas fa-check"></i>
                                    <?php else: ?>
                                        1
                                    <?php endif; ?>
                                </div>
                                <div class="step-label">Detalles</div>
                            </div>
                            <div class="step-line"></div>
                            <div class="step <?php echo getStepStatus(2, $currentStep); ?>">
                                <div class="step-number">
                                    <?php if (getStepStatus(2, $currentStep) === 'completed'): ?>
                                        <i class="fas fa-check"></i>
                                    <?php else: ?>
                                        2
                                    <?php endif; ?>
                                </div>
                                <div class="step-label">Estudiantes</div>
                            </div>
                            <div class="step-line"></div>
                            <div class="step <?php echo getStepStatus(3, $currentStep); ?>">
                                <div class="step-number">3</div>
                                <div class="step-label">Revisión</div>
                            </div>
                        </div>
                        
                        <!-- Step Content -->
                        <div class="step-content">
                            <?php if ($currentStep === 1): ?>
                                <!-- Step 1: Material Details -->
                                <form action="material_tutor.php?step=1&category=<?php echo htmlspecialchars($category); ?>" method="post" class="upload-form">
                                    <input type="hidden" name="category" value="<?php echo htmlspecialchars($category); ?>">
                                    
                                    <div class="form-group">
                                        <label for="title">Título del Material <span class="required">*</span></label>
                                        <input type="text" id="title" name="title" required 
                                            value="<?php echo htmlspecialchars($materialData['title'] ?? ''); ?>">
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="description">Descripción</label>
                                        <textarea id="description" name="description" rows="3"><?php echo htmlspecialchars($materialData['description'] ?? ''); ?></textarea>
                                    </div>
                                    
                                    <?php if ($category === 'video_links'): ?>
                                        <!-- Video Links Form -->
                                        <div class="form-group">
                                            <label for="platform">Plataforma <span class="required">*</span></label>
                                            <select id="platform" name="platform" required>
                                                <option value="">Selecciona una plataforma</option>
                                                <option value="tiktok" <?php if (isset($materialData['platform']) && $materialData['platform'] === 'tiktok') echo 'selected'; ?>>TikTok</option>
                                                <option value="youtube" <?php if (isset($materialData['platform']) && $materialData['platform'] === 'youtube') echo 'selected'; ?>>YouTube</option>
                                                <option value="instagram" <?php if (isset($materialData['platform']) && $materialData['platform'] === 'instagram') echo 'selected'; ?>>Instagram</option>
                                                <option value="facebook" <?php if (isset($materialData['platform']) && $materialData['platform'] === 'facebook') echo 'selected'; ?>>Facebook</option>
                                                <option value="vimeo" <?php if (isset($materialData['platform']) && $materialData['platform'] === 'vimeo') echo 'selected'; ?>>Vimeo</option>
                                                <option value="other" <?php if (isset($materialData['platform']) && $materialData['platform'] === 'other') echo 'selected'; ?>>Otro</option>
                                            </select>
                                        </div>
                                        
                                        <div class="form-group">
                                            <label for="video_url">URL del Video <span class="required">*</span></label>
                                            <input type="url" id="video_url" name="video_url" placeholder="https://" required 
                                                value="<?php echo htmlspecialchars($materialData['video_url'] ?? ''); ?>">
                                            <p class="form-help">Pega la URL completa del video que deseas compartir</p>
                                        </div>
                                        
                                    <?php elseif ($category === 'documents'): ?>
                                        <!-- Document Upload Form -->
                                        <div class="form-group">
                                            <label for="document-file">Archivo de Documento <span class="required">*</span></label>
                                            <div class="file-upload-container" id="dropzone">
                                                <?php if (!empty($materialData['filename'])): ?>
                                                    <div class="file-preview">
                                                        <i class="<?php echo getDocumentIcon($materialData['filename']); ?>"></i>
                                                        <div class="file-info">
                                                            <span class="file-name"><?php echo htmlspecialchars($materialData['filename']); ?></span>
                                                            <span class="file-size"><?php echo htmlspecialchars($materialData['filesize']); ?></span>
=======
    <link rel="stylesheet" href="../../assets/css/tutor_css/material_tutor.css">
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #039708;
            --primary-light: #039708;
            --secondary: #f8f9fa;
            --success: #28a745;
            --danger: #dc3545;
            --warning: #ffc107;
            --info: #00c44b;
            --dark: #343a40;
            --light: #f8f9fa;
        }
        
        body {
            font-family: 'Roboto', sans-serif;
            background-color: #f5f5f5;
            margin: 0;
            padding: 0;
        }
        
        .sidebar {
            background-color: var(--primary) !important;
            color: #fff;
            height: 100vh;
            position: fixed;
            width: 250px;
            z-index: 1000;
            box-shadow: 2px 0 10px rgba(0,0,0,0.1);
            transition: all 0.3s;
        }
        
        .sidebar-header {
            padding: 20px;
            background-color: rgba(0,166,61,0.08) !important;
        }
        
        .sidebar-header h3 {
            margin: 0;
            font-size: 1.5rem;
            display: flex;
            align-items: center;
        }
        
        .sidebar ul {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        
        .sidebar ul li {
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }
        
        .sidebar ul li a {
            color: white;
            padding: 15px 20px;
            display: block;
            text-decoration: none;
            transition: all 0.3s;
        }
        
        .sidebar ul li a:hover,
        .sidebar ul li a.active {
            background-color: var(--primary-light) !important;
            border-left: 4px solid #fff;
        }
        
        .sidebar ul li a i {
            margin-right: 10px;
            width: 20px;
            text-align: center;
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
            transition: all 0.3s;
        }
        
        .main-header {
            background-color: white;
            padding: 20px;
            border-radius: 5px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
        }
        
        .main-header h2 {
            margin: 0;
            color: var(--dark);
        }
        
        .subtitle {
            color: #6c757d;
            margin: 5px 0 0 0;
        }
        
        .header-actions {
            display: flex;
            align-items: center;
        }
        
        .notification-btn {
            background: none;
            border: none;
            color: #6c757d;
            font-size: 1.2rem;
            cursor: pointer;
            transition: color 0.3s;
        }
        
        .notification-btn:hover {
            color: var(--primary);
        }
        
        .content-section {
            background-color: white;
            border-radius: 5px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            padding: 20px;
        }
        
        .material-categories {
            text-align: center;
            padding: 20px 0;
        }
        
        .material-categories h3 {
            margin-bottom: 30px;
            color: var(--dark);
        }
        
        .category-cards {
            display: flex;
            justify-content: center;
            gap: 30px;
            flex-wrap: wrap;
        }
        
        .category-card {
            width: 250px;
            padding: 30px 20px;
            border-radius: 10px;
            background-color: white;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            text-align: center;
            text-decoration: none;
            color: var(--dark);
            transition: all 0.3s;
            border: 2px solid transparent;
        }
        
        .category-card:hover,
        .category-card.active {
            transform: translateY(-5px);
            border-color: var(--primary) !important;
            box-shadow: 0 0 0 2px var(--primary-light, #00c44b);
            text-decoration: none;
            color: var(--dark);
        }
        
        .category-icon {
            width: 70px;
            height: 70px;
            background-color: var(--light);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            font-size: 1.8rem;
            color: var(--primary);
        }
        
        .category-card h4 {
            margin-bottom: 10px;
        }
        
        .category-card p {
            color: #6c757d;
            font-size: 0.9rem;
            margin: 0;
        }
        
        .upload-container {
            max-width: 800px;
            margin: 0 auto;
        }
        
        .upload-header {
            margin-bottom: 30px;
            text-align: center;
        }
        
        .upload-header h3 {
            color: var(--dark);
            margin: 0;
        }
        
        .progress-steps {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 40px;
            position: relative;
        }
        
        .step {
            display: flex;
            flex-direction: column;
            align-items: center;
            z-index: 1;
        }
        
        .step-number {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background-color: #e9ecef;
            color: #6c757d;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 500;
            margin-bottom: 10px;
            transition: all 0.3s;
        }
        
        .step.active .step-number {
            background-color: var(--primary);
            color: white;
        }
        
        .step.completed .step-number {
            background-color: var(--success);
            color: white;
        }
        
        .step-label {
            font-size: 0.9rem;
            color: #6c757d;
            transition: all 0.3s;
        }
        
        .step.active .step-label,
        .step.completed .step-label {
            color: var(--dark);
            font-weight: 500;
        }
        
        .step-line {
            flex-grow: 1;
            height: 2px;
            background-color: #e9ecef;
            position: relative;
            z-index: 0;
        }
        
        .step-content {
            background-color: #f8f9fa;
            border-radius: 10px;
            padding: 30px;
        }
        
        .upload-form {
            max-width: 600px;
            margin: 0 auto;
        }
        
        .form-group {
            margin-bottom: 25px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: var(--dark);
        }
        
        .required {
            color: var(--danger);
        }
        
        .form-group input[type="text"],
        .form-group input[type="url"],
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 10px 15px;
            border: 1px solid #ced4da;
            border-radius: 5px;
            font-size: 1rem;
            transition: border-color 0.3s;
        }
        
        .form-group input[type="text"]:focus,
        .form-group input[type="url"]:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            border-color: var(--primary);
            outline: none;
            box-shadow: 0 0 0 3px rgba(0, 166, 61, 0.1);
        }
        
        .form-help {
            font-size: 0.85rem;
            color: #6c757d;
            margin-top: 5px;
        }
        
        .file-upload-container {
            border: 2px dashed #ced4da;
            border-radius: 5px;
            padding: 30px;
            text-align: center;
            transition: all 0.3s;
            cursor: pointer;
        }
        
        .file-upload-container.highlight {
            border-color: var(--primary);
            background-color: rgba(0, 166, 61, 0.05);
        }
        
        .upload-message {
            color: #6c757d;
        }
        
        .upload-message i {
            font-size: 2.5rem;
            color: #adb5bd;
            margin-bottom: 15px;
        }
        
        .file-select-btn {
            display: inline-block;
            background-color: var(--primary);
            color: white;
            padding: 8px 15px;
            border-radius: 5px;
            cursor: pointer;
            transition: background-color 0.3s;
            margin-top: 10px;
        }
        
        .file-select-btn:hover {
            background-color: var(--primary-dark);
        }
        
        .file-preview {
            display: flex;
            align-items: center;
        }
        
        .file-preview i {
            font-size: 2rem;
            color: var(--primary);
            margin-right: 15px;
        }
        
        .file-info {
            flex-grow: 1;
            text-align: left;
        }
        
        .file-name {
            display: block;
            font-weight: 500;
            color: var(--dark);
            margin-bottom: 5px;
        }
        
        .file-size {
            display: block;
            font-size: 0.85rem;
            color: #6c757d;
        }
        
        .remove-file {
            background: none;
            border: none;
            color: var(--danger);
            cursor: pointer;
            font-size: 1.2rem;
            transition: color 0.3s;
        }
        
        .remove-file:hover {
            color: #bd2130;
        }
        
        .form-actions {
            display: flex;
            justify-content: space-between;
            margin-top: 30px;
        }
        
        .btn {
            padding: 10px 20px;
            border-radius: 5px;
            font-weight: 500;
            transition: all 0.3s;
        }
        
        .btn-primary,
        .btn-primary:focus,
        .btn-primary:active,
        .btn-primary:visited {
            background-color: var(--primary) !important;
            border-color: var(--primary) !important;
            color: #fff !important;
        }
        
        .btn-primary:hover {
            background-color: var(--primary-light) !important;
            border-color: var(--primary-light) !important;
        }
        
        .btn-secondary {
            background-color: #6c757d;
            border-color: #6c757d;
            color: white;
        }
        
        .btn-secondary:hover {
            background-color: #5a6268;
            border-color: #545b62;
        }
        
        .btn-success,
        .btn-success:focus,
        .btn-success:active,
        .btn-success:visited {
            background-color: var(--primary-light) !important;
            border-color: var(--primary-light) !important;
            color: #fff !important;
        }
        
        .btn-success:hover {
            background-color: var(--primary) !important;
            border-color: var(--primary) !important;
            color: #fff !important;
        }
        
        .search-container {
            position: relative;
            margin-bottom: 15px;
        }
        
        .search-icon {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #6c757d;
        }
        
        #student-search {
            width: 100%;
            padding: 10px 15px 10px 40px;
            border: 1px solid #ced4da;
            border-radius: 5px;
            font-size: 1rem;
        }
        
        .students-list {
            max-height: 300px;
            overflow-y: auto;
            border: 1px solid #ced4da;
            border-radius: 5px;
            margin-bottom: 15px;
        }
        
        .student-item {
            display: flex;
            align-items: center;
            padding: 15px;
            border-bottom: 1px solid #ced4da;
        }
        
        .student-item:last-child {
            border-bottom: none;
        }
        
        .student-checkbox {
            position: relative;
            display: block;
            min-width: 24px;
            height: 24px;
            margin-right: 15px;
        }
        
        .student-checkbox input {
            position: absolute;
            opacity: 0;
            cursor: pointer;
            height: 0;
            width: 0;
        }
        
        .checkmark {
            position: absolute;
            top: 0;
            left: 0;
            height: 24px;
            width: 24px;
            background-color: #eee;
            border-radius: 4px;
            transition: all 0.3s;
        }
        
        .student-checkbox:hover input ~ .checkmark {
            background-color: #ccc;
        }
        
        .student-checkbox input:checked ~ .checkmark {
            background-color: var(--primary);
        }
        
        .checkmark:after {
            content: "";
            position: absolute;
            display: none;
        }
        
        .student-checkbox input:checked ~ .checkmark:after {
            display: block;
        }
        
        .student-checkbox .checkmark:after {
            left: 9px;
            top: 5px;
            width: 6px;
            height: 12px;
            border: solid white;
            border-width: 0 2px 2px 0;
            transform: rotate(45deg);
        }
        
        .student-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
            margin-right: 15px;
        }
        
        .student-info {
            flex-grow: 1;
        }
        
        .student-name {
            font-weight: 500;
            color: var(--dark);
            margin-bottom: 3px;
        }
        
        .student-email {
            font-size: 0.85rem;
            color: #6c757d;
        }
        
        .selected-count {
            font-size: 0.9rem;
            color: #6c757d;
            text-align: right;
        }
        
        .review-section {
            margin-bottom: 30px;
        }
        
        .review-section h4 {
            color: var(--dark);
            margin-bottom: 20px;
            text-align: center;
        }
        
        .review-group {
            background-color: white;
            border-radius: 5px;
            padding: 20px;
            margin-bottom: 20px;
        }
        
        .review-group h5 {
            color: var(--primary);
            margin-bottom: 15px;
            font-size: 0.9rem;
            font-weight: 700;
        }
        
        .review-item {
            display: flex;
            margin-bottom: 15px;
        }
        
        .review-item:last-child {
            margin-bottom: 0;
        }
        
        .review-label {
            width: 120px;
            font-weight: 500;
            color: var(--dark);
        }
        
        .review-value {
            flex-grow: 1;
            color: #6c757d;
        }
        
        .selected-students {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
        }
        
        .selected-student {
            display: flex;
            align-items: center;
            background-color: #f8f9fa;
            border-radius: 5px;
            padding: 10px;
            width: calc(50% - 7.5px);
        }
        
        .upload-success {
            text-align: center;
            padding: 20px;
        }
        
        .success-message {
            margin-bottom: 30px;
        }
        
        .success-message i {
            font-size: 4rem;
            color: var(--success);
            margin-bottom: 15px;
        }
        
        .success-message h4 {
            color: var(--dark);
            margin-bottom: 10px;
        }
        
        .success-message p {
            color: #6c757d;
        }
        
        .shared-content {
            background-color: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 30px;
            display: flex;
            align-items: center;
            text-align: left;
        }
        
        .shared-video, .shared-document {
            display: flex;
            align-items: center;
            width: 100%;
        }
        
        .video-platform-icon, .document-icon {
            width: 60px;
            height: 60px;
            background-color: #f8f9fa;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.8rem;
            color: var(--primary);
            margin-right: 20px;
        }
        
        .video-info, .document-info {
            flex-grow: 1;
        }
        
        .video-info h5, .document-info h5 {
            color: var(--dark);
            margin-bottom: 5px;
        }
        
        .video-info p, .document-info p {
            color: #6c757d;
            margin-bottom: 10px;
        }
        
        .video-link {
            color: var(--primary);
            text-decoration: none;
            font-weight: 500;
            display: inline-block;
        }
        
        .video-link:hover {
            text-decoration: underline;
        }
        
        .document-file {
            color: #6c757d;
            font-size: 0.9rem;
        }
        
        /* Arreglo para las miniaturas */
        .thumbnail-preview {
            width: 100%;
            height: 200px;
            border: 1px solid #ced4da;
            border-radius: 5px;
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
            background-color: #f8f9fa;
            margin-bottom: 10px;
        }
        
        .thumbnail-preview img {
            max-width: 100%;
            max-height: 100%;
            object-fit: contain;
        }
        
        .no-thumbnail {
            text-align: center;
            color: #6c757d;
        }
        
        .no-thumbnail i {
            font-size: 3rem;
            margin-bottom: 10px;
            color: #adb5bd;
        }
        
        .loading-thumbnail {
            text-align: center;
            color: #6c757d;
        }
        
        .loading-thumbnail i {
            font-size: 2rem;
            margin-bottom: 10px;
            color: var(--primary);
        }
        
        .thumbnail-error {
            text-align: center;
            color: #721c24;
        }
        
        .thumbnail-error i {
            font-size: 2rem;
            margin-bottom: 10px;
            color: var(--danger);
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <aside class="sidebar">
                <div class="sidebar-header">
                    <h3>
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
                <ul>
                    <li><a href="inicio_tutor.php"><i class="fas fa-home"></i> Inicio</a></li>
                    <li><a href="actividades_tutor.php"><i class="fas fa-tasks"></i> Actividades</a></li>
                    <li><a href="clase_tutor.php"><i class="fas fa-video"></i> Aula Virtual</a></li>
                    <li><a href="material_tutor.php" class="active"><i class="fas fa-book"></i> Material de Apoyo</a></li>
                </ul>


            <!-- Botón de cerrar sesión fijo abajo -->
                <a href="/views/general/login.php" class="logout-btn" style="margin-top: auto; padding: 15px 20px; color: rgba(255, 255, 255, 0.8); text-decoration: none; display: flex; align-items: center;">
                    <i class="fas fa-sign-out-alt" style="margin-right: 10px;"></i> Cerrar sesión
                </a>


            </aside>
        </div>

        <div>    
            <!-- Main Content -->
            <main class="main-content">
                <header class="main-header">
                    <h2>Material de Apoyo</h2>
                    <p class="subtitle">Comparte recursos educativos con tus estudiantes</p>
                </header>
                
                <section class="content-section">
                    <?php if ($currentStep === 1 && $category === ''): ?>
                        <!-- Category Selection -->
                        <div class="material-categories">
                            <h3>Selecciona el tipo de material que deseas compartir</h3>
                            <div class="category-cards">
                                <a href="material_tutor.php?step=1&category=video_links" class="category-card">
                                    <div class="category-icon">
                                        <i class="fas fa-video"></i>
                                    </div>
                                    <h4>Enlaces de Video</h4>
                                    <p>Comparte videos de TikTok, Instagram, YouTube y más</p>
                                </a>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="upload-container">
                            <div class="upload-header">
                                <h3>
                                    <?php if ($category === 'video_links'): ?>
                                        <i class="fas fa-video"></i> Compartir Enlaces de Video
                                    <?php else: ?>
                                        <i class="fas fa-file-alt"></i> Compartir Documentación
                                    <?php endif; ?>
                                </h3>
                            </div>
                            
                            <!-- Progress Steps -->
                            <div class="progress-steps">
                                <div class="step active">
                                    <div class="step-number">1</div>
                                    <div class="step-label">
                                        <?php if ($category === 'video_links'): ?>
                                            Detalles del Video
                                        <?php else: ?>
                                            Detalles del Documento
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Step Content -->
                            <div class="step-content">
                                <?php if ($currentStep === 1): ?>
                                    <!-- Step 1: Material Details -->
                                    <form action="material_tutor.php?step=1&category=<?php echo htmlspecialchars($category); ?>" method="post" class="upload-form" enctype="multipart/form-data">
                                        <input type="hidden" name="category" value="<?php echo htmlspecialchars($category); ?>">
                                        
                                        <div class="form-group">
                                            <label for="title">Título del Material <span class="required">*</span></label>
                                            <input type="text" id="title" name="title" required value="">
                                        </div>
                                        
                                        <div class="form-group">
                                            <label for="description">Descripción</label>
                                            <textarea id="description" name="description" rows="3"></textarea>
                                        </div>
                                        
                                        <?php if ($category === 'video_links'): ?>
                                            <!-- Video Links Form -->
                                            <div class="form-group">
                                                <label for="platform">Plataforma <span class="required">*</span></label>
                                                <select id="platform" name="platform" required>
                                                    <option value="">Selecciona una plataforma</option>
                                                    <option value="youtube" <?php if (isset($materialData['platform']) && $materialData['platform'] === 'youtube') echo 'selected'; ?>>YouTube</option>
                                                    <option value="vimeo" <?php if (isset($materialData['platform']) && $materialData['platform'] === 'vimeo') echo 'selected'; ?>>Vimeo</option>
                                                    <option value="tiktok" <?php if (isset($materialData['platform']) && $materialData['platform'] === 'tiktok') echo 'selected'; ?>>TikTok</option>
                                                    <option value="instagram" <?php if (isset($materialData['platform']) && $materialData['platform'] === 'instagram') echo 'selected'; ?>>Instagram</option>
                                                    <option value="facebook" <?php if (isset($materialData['platform']) && $materialData['platform'] === 'facebook') echo 'selected'; ?>>Facebook</option>
                                                    <option value="other" <?php if (isset($materialData['platform']) && $materialData['platform'] === 'other') echo 'selected'; ?>>Otro</option>
                                                </select>
                                            </div>
                                            
                                            <div class="form-group">
                                                <label for="video_url">URL del Video <span class="required">*</span></label>
                                                <input type="text" id="video_url" name="video_url" required value="">
                                                <p class="form-help">Pega la URL del video que deseas compartir</p>
                                            </div>
                                            
                                            <div class="form-group">
                                                <label for="thumbnail_preview">Vista previa de la miniatura</label>
                                                <div id="thumbnail_preview" class="thumbnail-preview">
                                                    <?php if (isset($materialData['thumbnail_url']) && !empty($materialData['thumbnail_url'])): ?>
                                                        <img src="<?php echo htmlspecialchars($materialData['thumbnail_url']); ?>" alt="Vista previa del video">
                                                    <?php else: ?>
                                                        <div class="no-thumbnail">
                                                            <i class="fas fa-image"></i>
                                                            <p>La miniatura se generará automáticamente</p>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                                <input type="hidden" id="thumbnail_url" name="thumbnail_url" value="<?php echo isset($materialData['thumbnail_url']) ? htmlspecialchars($materialData['thumbnail_url']) : ''; ?>">
                                                <p class="form-help">La miniatura se generará automáticamente a partir de la URL del video</p>
                                            </div>
                                        <?php else: ?>
                                            <!-- Document Upload Form -->
                                            <div class="form-group">
                                                <label for="document-file">Archivo de Documento <span class="required">*</span></label>
                                                <div class="file-upload-container" id="dropzone">
                                                    <?php if (isset($materialData['filename']) && !empty($materialData['filename'])): ?>
                                                        <div class="file-preview">
                                                            <i class="<?php echo getDocumentIcon($materialData['filename']); ?>"></i>
                                                            <div class="file-info">
                                                                <span class="file-name"><?php echo htmlspecialchars($materialData['filename']); ?></span>
                                                                <span class="file-size"><?php echo htmlspecialchars($materialData['filesize']); ?></span>
                                                            </div>
                                                            <button type="button" class="remove-file" id="remove-file">
                                                                <i class="fas fa-times"></i>
                                                            </button>
                                                        </div>
                                                        <input type="hidden" name="filename" value="<?php echo htmlspecialchars($materialData['filename']); ?>">
                                                        <input type="hidden" name="filesize" value="<?php echo htmlspecialchars($materialData['filesize']); ?>">
                                                    <?php else: ?>
                                                        <div class="upload-message">
                                                            <i class="fas fa-cloud-upload-alt"></i>
                                                            <p>Arrastra y suelta tu documento aquí o</p>
                                                            <label for="document-file" class="file-select-btn">Seleccionar archivo</label>
                                                            <input type="file" id="document-file" name="document-file" style="display: none;">
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                                <p class="form-help">Formatos soportados: PDF, DOC, DOCX, XLS, XLSX, PPT, PPTX, TXT, ZIP, etc.</p>
                                            </div>

                                            <div class="form-group">
                                                <label for="document_url">URL del Documento (opcional)</label>
                                                <input type="text" id="document_url" name="document_url" placeholder="https://" 
                                                    value="<?php echo isset($materialData['document_url']) ? htmlspecialchars($materialData['document_url']) : ''; ?>">
                                                <p class="form-help">Si el documento está en línea, puedes proporcionar la URL</p>
                                            </div>
                                        <?php endif; ?>

                                        <div class="form-group">
                                            <label for="estudiante_id">Asignar a estudiante específico (opcional)</label>
                                            <select id="estudiante_id" name="estudiante_id" class="form-control">
                                                <option value="">Todos los estudiantes</option>
                                                <?php 
                                                try {
                                                    $stmt = $db->prepare("
                                                        SELECT id, nombre, email 
                                                        FROM usuarios 
                                                        WHERE opcion_grado = 'seminario' AND rol = 'estudiante' AND estado = 'activo'
                                                    ");
                                                    $stmt->execute();
                                                    $seminario_students = $stmt->fetchAll(PDO::FETCH_ASSOC);
                                                    
                                                    foreach ($seminario_students as $student): 
                                                ?>
                                                    <option value="<?php echo $student['id']; ?>">
                                                        <?php echo htmlspecialchars($student['nombre']); ?> (<?php echo htmlspecialchars($student['email']); ?>)
                                                    </option>
                                                <?php 
                                                    endforeach; 
                                                } catch (PDOException $e) {
                                                    foreach ($students as $student): 
                                                ?>
                                                    <option value="<?php echo $student['id']; ?>">
                                                        <?php echo htmlspecialchars($student['name']); ?> (<?php echo htmlspecialchars($student['email']); ?>)
                                                    </option>
                                                <?php 
                                                    endforeach;
                                                }
                                                ?>
                                            </select>
                                            <p class="form-help">Si no selecciona ningún estudiante, el material se compartirá con todos.</p>
                                        </div>
                                        
                                        <div class="form-actions">
                                            <a href="material_tutor.php" class="btn btn-secondary">Cancelar</a>
                                            <?php if ($category === 'video_links'): ?>
                                                <button type="button" id="publish_video_btn" class="btn btn-success">
                                                    <i class="fas fa-paper-plane"></i> Publicar Video
                                                </button>
                                            <?php else: ?>
                                                <button type="submit" id="publish_document_btn" class="btn btn-primary">
                                                    <i class="fas fa-paper-plane"></i> Publicar Documento
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </form>

                                    <?php if ($category === 'documents'): ?>
                                    <script>
                                        document.addEventListener('DOMContentLoaded', function() {
                                            const publishDocumentBtn = document.getElementById('publish_document_btn');
                                            const dropzone = document.getElementById('dropzone');
                                            const fileInput = document.getElementById('document-file');
                                            const removeFileBtn = document.getElementById('remove-file');

                                            // Handle publish button click
                                            if (publishDocumentBtn) {
                                                publishDocumentBtn.addEventListener('click', function(e) {
                                                    e.preventDefault();
                                                    const form = this.closest('form');
                                                    
                                                    if (form) {
                                                        // Validar que se haya seleccionado un archivo
                                                        const fileInput = document.getElementById('document-file');
                                                        if (!fileInput || !fileInput.files || fileInput.files.length === 0) {
                                                            alert('Por favor, selecciona un archivo para subir.');
                                                            return;
                                                        }

                                                        // Validar el título
                                                        const titleInput = document.getElementById('title');
                                                        if (!titleInput || !titleInput.value.trim()) {
                                                            alert('Por favor, ingresa un título para el documento.');
                                                            return;
                                                        }
                                                        
                                                        // Mostrar mensaje de carga
                                                        publishDocumentBtn.disabled = true;
                                                        publishDocumentBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Subiendo documento...';
                                                        
                                                        // Crear FormData y agregar el archivo
                                                        const formData = new FormData(form);
                                                        
                                                        // Enviar el formulario vía AJAX
                                                        fetch(form.action, {
                                                            method: 'POST',
                                                            body: formData
                                                        })
                                                        .then(response => {
                                                            if (!response.ok) {
                                                                throw new Error('Error en la respuesta del servidor');
                                                            }
                                                            return response.text();
                                                        })
                                                        .then(data => {
                                                            try {
                                                                const result = JSON.parse(data);
                                                                if (result.success) {
                                                                    // Re-enable button
                                                                    publishDocumentBtn.disabled = false;
                                                                    publishDocumentBtn.innerHTML = '<i class="fas fa-check"></i> ¡Documento publicado!';
                                                                    
                                                                    // Show success message
                                                                    const successMessage = document.createElement('div');
                                                                    successMessage.className = 'alert alert-success mt-3';
                                                                    successMessage.innerHTML = '<i class="fas fa-check-circle"></i> El documento ha sido enviado exitosamente a Material de Apoyo.';
                                                                    
                                                                    // Insert after the form
                                                                    form.after(successMessage);
                                                                    
                                                                    // Reset form after 2 seconds
                                                                    setTimeout(() => {
                                                                        form.reset();
                                                                        const dropzone = document.getElementById('dropzone');
                                                                        if (dropzone) {
                                                                            dropzone.innerHTML = `
                                                                                <div class="upload-message">
                                                                                    <i class="fas fa-cloud-upload-alt"></i>
                                                                                    <p>Arrastra y suelta tu documento aquí o</p>
                                                                                    <label for="document-file" class="file-select-btn">Seleccionar archivo</label>
                                                                                    <input type="file" id="document-file" name="document-file" style="display: none;">
                                                                                </div>
                                                                            `;
                                                                        }
                                                                        
                                                                        // Reset button
                                                                        publishDocumentBtn.innerHTML = '<i class="fas fa-paper-plane"></i> Publicar Documento';
                                                                        
                                                                        // Remove success message after 3 seconds
                                                                        setTimeout(() => {
                                                                            successMessage.remove();
                                                                        }, 3000);
                                                                    }, 2000);
                                                                } else {
                                                                    throw new Error(result.message || 'Error al subir el documento');
                                                                }
                                                            } catch (error) {
                                                                throw new Error('Error al procesar la respuesta del servidor');
                                                            }
                                                        })
                                                        .catch(error => {
                                                            console.error('Error:', error);
                                                            
                                                            // Re-enable button
                                                            publishDocumentBtn.disabled = false;
                                                            publishDocumentBtn.innerHTML = '<i class="fas fa-paper-plane"></i> Publicar Documento';
                                                            
                                                            // Show error message
                                                            const errorMessage = document.createElement('div');
                                                            errorMessage.className = 'alert alert-danger mt-3';
                                                            errorMessage.innerHTML = '<i class="fas fa-exclamation-triangle"></i> ' + error.message;
                                                            
                                                            // Insert after the form
                                                            form.after(errorMessage);
                                                            
                                                            // Remove error message after 5 seconds
                                                            setTimeout(() => {
                                                                errorMessage.remove();
                                                            }, 5000);
                                                        });
                                                    }
                                                });
                                            }

                                            // File upload handling
                                            if (dropzone && fileInput) {
                                                dropzone.addEventListener('dragover', function(e) {
                                                    e.preventDefault();
                                                    dropzone.classList.add('dragover');
                                                });

                                                dropzone.addEventListener('dragleave', function(e) {
                                                    e.preventDefault();
                                                    dropzone.classList.remove('dragover');
                                                });

                                                dropzone.addEventListener('drop', function(e) {
                                                    e.preventDefault();
                                                    dropzone.classList.remove('dragover');
                                                    const files = e.dataTransfer.files;
                                                    if (files.length > 0) {
                                                        fileInput.files = files;
                                                        handleFileSelect(files[0]);
                                                    }
                                                });

                                                fileInput.addEventListener('change', function(e) {
                                                    if (this.files && this.files.length > 0) {
                                                        handleFileSelect(this.files[0]);
                                                    }
                                                });

                                                if (removeFileBtn) {
                                                    removeFileBtn.addEventListener('click', function() {
                                                        const filePreview = this.closest('.file-preview');
                                                        if (filePreview) {
                                                            filePreview.remove();
                                                        }
                                                        fileInput.value = '';
                                                        dropzone.innerHTML = `
                                                            <div class="upload-message">
                                                                <i class="fas fa-cloud-upload-alt"></i>
                                                                <p>Arrastra y suelta tu documento aquí o</p>
                                                                <label for="document-file" class="file-select-btn">Seleccionar archivo</label>
                                                                <input type="file" id="document-file" name="document-file" style="display: none;">
                                                            </div>
                                                        `;
                                                    });
                                                }
                                            }

                                            function handleFileSelect(file) {
                                                if (!file) return;

                                                const fileIcon = getFileIcon(file.name);
                                                const fileSize = formatFileSize(file.size);
                                                
                                                dropzone.innerHTML = `
                                                    <div class="file-preview">
                                                        <i class="${fileIcon}"></i>
                                                        <div class="file-info">
                                                            <span class="file-name">${file.name}</span>
                                                            <span class="file-size">${fileSize}</span>
>>>>>>> origin/Master
                                                        </div>
                                                        <button type="button" class="remove-file" id="remove-file">
                                                            <i class="fas fa-times"></i>
                                                        </button>
                                                    </div>
<<<<<<< HEAD
                                                    <input type="hidden" name="filename" value="<?php echo htmlspecialchars($materialData['filename']); ?>">
                                                    <input type="hidden" name="filesize" value="<?php echo htmlspecialchars($materialData['filesize']); ?>">
                                                <?php else: ?>
                                                    <div class="upload-message">
                                                        <i class="fas fa-cloud-upload-alt"></i>
                                                        <p>Arrastra y suelta tu documento aquí o</p>
                                                        <label for="document-file" class="file-select-btn">Seleccionar archivo</label>
                                                        <input type="file" id="document-file" name="document-file" style="display: none;">
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                            <p class="form-help">Formatos soportados: PDF, DOC, DOCX, XLS, XLSX, PPT, PPTX, TXT, ZIP, etc.</p>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <div class="form-actions">
                                        <a href="material_tutor.php" class="btn btn-secondary">Cancelar</a>
                                        <button type="submit" class="btn btn-primary">Continuar</button>
                                    </div>
                                </form>
                            
                            <?php elseif ($currentStep === 2): ?>
                                <!-- Step 2: Select Students -->
                                <form action="material_tutor.php?step=2&category=<?php echo htmlspecialchars($materialData['category']); ?>" method="post" class="upload-form">
                                    <div class="form-group">
                                        <label>Seleccionar Estudiantes <span class="required">*</span></label>
                                        <div class="search-container">
                                            <i class="fas fa-search search-icon"></i>
                                            <input type="text" id="student-search" placeholder="Buscar estudiantes por nombre...">
                                        </div>
                                        
                                        <div class="students-list">
                                            <?php foreach ($students as $student): ?>
                                                <div class="student-item">
                                                    <label class="student-checkbox">
                                                        <input type="checkbox" name="selected_students[]" value="<?php echo $student['id']; ?>"
                                                            <?php if (isset($materialData['selected_students']) && in_array($student['id'], $materialData['selected_students'])) echo 'checked'; ?>>
                                                        <span class="checkmark"></span>
                                                    </label>
                                                    <img src="<?php echo $student['avatar']; ?>" alt="<?php echo htmlspecialchars($student['name']); ?>" class="student-avatar">
                                                    <div class="student-info">
                                                        <div class="student-name"><?php echo htmlspecialchars($student['name']); ?></div>
                                                        <div class="student-email"><?php echo htmlspecialchars($student['email']); ?></div>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                        
                                        <div class="selected-count">
                                            <span id="selected-count">0</span> estudiantes seleccionados
                                        </div>
                                    </div>
                                    
                                    <div class="form-actions">
                                        <a href="material_tutor.php?step=1&category=<?php echo htmlspecialchars($materialData['category']); ?>" class="btn btn-secondary">Atrás</a>
                                        <button type="submit" class="btn btn-primary">Continuar</button>
                                    </div>
                                </form>
                            
                            <?php elseif ($currentStep === 3): ?>
                                <!-- Step 3: Review and Share -->
                                <form action="material_tutor.php?step=3&category=<?php echo htmlspecialchars($materialData['category']); ?>" method="post" class="upload-form">
                                    <div class="review-section">
                                        <h4>Revisar y Compartir</h4>
                                        
                                        <div class="review-group">
                                            <h5>DETALLES DEL MATERIAL</h5>
                                            <div class="review-item">
                                                <div class="review-label">Tipo:</div>
                                                <div class="review-value">
                                                    <?php echo htmlspecialchars($categories[$materialData['category']] ?? $materialData['category']); ?>
                                                </div>
                                            </div>
                                            <div class="review-item">
                                                <div class="review-label">Título:</div>
                                                <div class="review-value"><?php echo htmlspecialchars($materialData['title'] ?? ''); ?></div>
                                            </div>
                                            <div class="review-item">
                                                <div class="review-label">Descripción:</div>
                                                <div class="review-value"><?php echo htmlspecialchars($materialData['description'] ?? ''); ?></div>
                                            </div>
                                            
                                            <?php if ($materialData['category'] === 'video_links'): ?>
                                                <div class="review-item">
                                                    <div class="review-label">Plataforma:</div>
                                                    <div class="review-value">
                                                        <i class="<?php echo getPlatformIcon($materialData['platform']); ?>"></i>
                                                        <?php 
                                                            $platforms = [
                                                                'tiktok' => 'TikTok',
                                                                'youtube' => 'YouTube',
                                                                'instagram' => 'Instagram',
                                                                'facebook' => 'Facebook',
                                                                'vimeo' => 'Vimeo',
                                                                'other' => 'Otro'
                                                            ];
                                                            echo htmlspecialchars($platforms[$materialData['platform']] ?? $materialData['platform']); 
                                                        ?>
                                                    </div>
                                                </div>
                                                <div class="review-item">
                                                    <div class="review-label">URL:</div>
                                                    <div class="review-value">
                                                        <a href="<?php echo htmlspecialchars($materialData['video_url']); ?>" target="_blank">
                                                            <?php echo htmlspecialchars($materialData['video_url']); ?>
                                                        </a>
                                                    </div>
                                                </div>
                                            <?php elseif ($materialData['category'] === 'documents'): ?>
                                                <div class="review-item">
                                                    <div class="review-label">Archivo:</div>
                                                    <div class="review-value">
                                                        <i class="<?php echo getDocumentIcon($materialData['filename']); ?>"></i>
                                                        <?php echo htmlspecialchars($materialData['filename']); ?>
                                                        (<?php echo htmlspecialchars($materialData['filesize']); ?>)
                                                    </div>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <div class="review-group">
                                            <h5>ESTUDIANTES SELECCIONADOS</h5>
                                            <div class="selected-students">
                                                <?php foreach ($selectedStudents as $student): ?>
                                                    <div class="selected-student">
                                                        <img src="<?php echo $student['avatar']; ?>" alt="<?php echo htmlspecialchars($student['name']); ?>" class="student-avatar">
                                                        <div class="student-info">
                                                            <div class="student-name"><?php echo htmlspecialchars($student['name']); ?></div>
                                                            <div class="student-email"><?php echo htmlspecialchars($student['email']); ?></div>
                                                        </div>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="form-actions">
                                        <a href="material_tutor.php?step=2&category=<?php echo htmlspecialchars($materialData['category']); ?>" class="btn btn-secondary">Atrás</a>
                                        <button type="submit" name="share" class="btn btn-success">
                                            <i class="fas fa-paper-plane"></i> Compartir Material
                                        </button>
                                    </div>
                                </form>
                                
                            <?php elseif ($currentStep === 4): ?>
                                <!-- Step 4: Success -->
                                <div class="upload-success">
                                    <div class="success-message">
                                        <i class="fas fa-check-circle"></i>
                                        <h4>¡Material compartido exitosamente!</h4>
                                        <p>Tu material ha sido compartido con los estudiantes seleccionados.</p>
                                    </div>
                                    
                                    <div class="shared-content">
                                        <?php if ($materialData['category'] === 'video_links'): ?>
                                            <div class="shared-video">
                                                <div class="video-platform-icon">
                                                    <i class="<?php echo getPlatformIcon($materialData['platform']); ?>"></i>
                                                </div>
                                                <div class="video-info">
                                                    <h5><?php echo htmlspecialchars($materialData['title']); ?></h5>
                                                    <p><?php echo htmlspecialchars($materialData['description']); ?></p>
                                                    <a href="<?php echo htmlspecialchars($materialData['video_url']); ?>" target="_blank" class="video-link">
                                                        <i class="fas fa-external-link-alt"></i> Ver video
                                                    </a>
                                                </div>
                                            </div>
                                        <?php elseif ($materialData['category'] === 'documents'): ?>
                                            <div class="shared-document">
                                                <div class="document-icon">
                                                    <i class="<?php echo getDocumentIcon($materialData['filename']); ?>"></i>
                                                </div>
                                                <div class="document-info">
                                                    <h5><?php echo htmlspecialchars($materialData['title']); ?></h5>
                                                    <p><?php echo htmlspecialchars($materialData['description']); ?></p>
                                                    <p class="document-file">
                                                        <i class="fas fa-paperclip"></i> 
                                                        <?php echo htmlspecialchars($materialData['filename']); ?>
                                                        (<?php echo htmlspecialchars($materialData['filesize']); ?>)
                                                    </p>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div class="form-actions">
                                        <a href="material_tutor.php" class="btn btn-secondary">Compartir otro material</a>
                                        <a href="#" class="btn btn-primary">Volver al inicio</a>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </section>
            
            <footer class="main-footer">
                <div class="institution-info">
                    <h4>Fundación Escuela Tecnológica de Neiva</h4>
                    <p>Dirección: Calle 11S Bogotá</p>
                    <div class="social-links">
                        <a href="#"><i class="fab fa-facebook"></i></a>
                        <a href="#"><i class="fab fa-instagram"></i></a>
                        <a href="#"><i class="fab fa-twitter"></i></a>
                    </div>
                </div>
            </footer>
        </main>
=======
                                                    <input type="hidden" name="filename" value="${file.name}">
                                                    <input type="hidden" name="filesize" value="${fileSize}">
                                                `;

                                                // Reattach event listener to the new remove button
                                                const newRemoveBtn = dropzone.querySelector('#remove-file');
                                                if (newRemoveBtn) {
                                                    newRemoveBtn.addEventListener('click', function() {
                                                        const filePreview = this.closest('.file-preview');
                                                        if (filePreview) {
                                                            filePreview.remove();
                                                        }
                                                        fileInput.value = '';
                                                        dropzone.innerHTML = `
                                                            <div class="upload-message">
                                                                <i class="fas fa-cloud-upload-alt"></i>
                                                                <p>Arrastra y suelta tu documento aquí o</p>
                                                                <label for="document-file" class="file-select-btn">Seleccionar archivo</label>
                                                                <input type="file" id="document-file" name="document-file" style="display: none;">
                                                            </div>
                                                        `;
                                                    });
                                                }
                                            }

                                            function getFileIcon(filename) {
                                                const ext = filename.split('.').pop().toLowerCase();
                                                const icons = {
                                                    'pdf': 'fas fa-file-pdf',
                                                    'doc': 'fas fa-file-word',
                                                    'docx': 'fas fa-file-word',
                                                    'xls': 'fas fa-file-excel',
                                                    'xlsx': 'fas fa-file-excel',
                                                    'ppt': 'fas fa-file-powerpoint',
                                                    'pptx': 'fas fa-file-powerpoint',
                                                    'txt': 'fas fa-file-alt',
                                                    'zip': 'fas fa-file-archive',
                                                    'rar': 'fas fa-file-archive',
                                                    '7z': 'fas fa-file-archive'
                                                };
                                                return icons[ext] || 'fas fa-file';
                                            }

                                            function formatFileSize(bytes) {
                                                if (bytes === 0) return '0 Bytes';
                                                const k = 1024;
                                                const sizes = ['Bytes', 'KB', 'MB', 'GB'];
                                                const i = Math.floor(Math.log(bytes) / Math.log(k));
                                                return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
                                            }
                                        });
                                    </script>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </section>
            </main>
        </div>
>>>>>>> origin/Master
    </div>

    <script>
        // File upload handling
        document.addEventListener('DOMContentLoaded', function() {
            const dropzone = document.getElementById('dropzone');
            const fileInput = document.getElementById('document-file');
            const removeFileBtn = document.getElementById('remove-file');
<<<<<<< HEAD
            
            if (fileInput) {
                fileInput.addEventListener('change', function(e) {
                    handleFileSelect(e.target.files[0]);
                });
            }
            
            if (dropzone) {
                // Prevent default drag behaviors
                ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
                    dropzone.addEventListener(eventName, preventDefaults, false);
                });
                
                // Highlight drop area when item is dragged over it
                ['dragenter', 'dragover'].forEach(eventName => {
                    dropzone.addEventListener(eventName, highlight, false);
                });
                
                ['dragleave', 'drop'].forEach(eventName => {
                    dropzone.addEventListener(eventName, unhighlight, false);
                });
                
                // Handle dropped files
                dropzone.addEventListener('drop', handleDrop, false);
            }
            
            if (removeFileBtn) {
                removeFileBtn.addEventListener('click', function() {
                    // Reset the file upload container
                    dropzone.innerHTML = `
                        <div class="upload-message">
                            <i class="fas fa-cloud-upload-alt"></i>
                            <p>Arrastra y suelta tu documento aquí o</p>
                            <label for="document-file" class="file-select-btn">Seleccionar archivo</label>
                            <input type="file" id="document-file" name="document-file" style="display: none;">
                        </div>
                    `;
                    
                    // Re-attach event listener to the new file input
                    document.getElementById('document-file').addEventListener('change', function(e) {
                        handleFileSelect(e.target.files[0]);
                    });
                });
            }
            
            function preventDefaults(e) {
                e.preventDefault();
                e.stopPropagation();
            }
            
            function highlight() {
                dropzone.classList.add('highlight');
            }
            
            function unhighlight() {
                dropzone.classList.remove('highlight');
            }
            
            function handleDrop(e) {
                const dt = e.dataTransfer;
                const files = dt.files;
                
                if (files.length) {
                    handleFileSelect(files[0]);
                }
            }
            
            function getFileIcon(filename) {
                const extension = filename.split('.').pop().toLowerCase();
                const icons = {
                    'pdf': 'fas fa-file-pdf',
                    'doc': 'fas fa-file-word',
                    'docx': 'fas fa-file-word',
                    'xls': 'fas fa-file-excel',
                    'xlsx': 'fas fa-file-excel',
                    'ppt': 'fas fa-file-powerpoint',
                    'pptx': 'fas fa-file-powerpoint',
                    'txt': 'fas fa-file-alt',
                    'zip': 'fas fa-file-archive',
                    'rar': 'fas fa-file-archive',
                    'jpg': 'fas fa-file-image',
                    'jpeg': 'fas fa-file-image',
                    'png': 'fas fa-file-image',
                    'gif': 'fas fa-file-image'
                };
                
                return icons[extension] || 'fas fa-file';
            }
            
            function handleFileSelect(file) {
                if (file) {
                    // Format file size
                    const size = formatFileSize(file.size);
                    
                    // Get icon based on file extension
                    const iconClass = getFileIcon(file.name);
                    
                    // Update the dropzone with file preview
                    dropzone.innerHTML = `
                        <div class="file-preview">
                            <i class="${iconClass}"></i>
                            <div class="file-info">
                                <span class="file-name">${file.name}</span>
                                <span class="file-size">${size}</span>
                            </div>
                            <button type="button" class="remove-file" id="remove-file">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                        <input type="hidden" name="filename" value="${file.name}">
                        <input type="hidden" name="filesize" value="${size}">
                    `;
                    
                    // Re-attach event listener to the new remove button
                    document.getElementById('remove-file').addEventListener('click', function() {
                        dropzone.innerHTML = `
                            <div class="upload-message">
                                <i class="fas fa-cloud-upload-alt"></i>
                                <p>Arrastra y suelta tu documento aquí o</p>
                                <label for="document-file" class="file-select-btn">Seleccionar archivo</label>
                                <input type="file" id="document-file" name="document-file" style="display: none;">
                            </div>
                        `;
                        
                        // Re-attach event listener to the new file input
                        document.getElementById('document-file').addEventListener('change', function(e) {
                            handleFileSelect(e.target.files[0]);
                        });
                    });
                } else {
                    alert('Por favor, selecciona un archivo válido.');
                }
            }
            
            function formatFileSize(bytes) {
                if (bytes === 0) return '0 Bytes';
                
                const k = 1024;
                const sizes = ['Bytes', 'KB', 'MB', 'GB', 'TB'];
                const i = Math.floor(Math.log(bytes) / Math.log(k));
                
                return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
            }
            
            // Student search functionality
            const studentSearch = document.getElementById('student-search');
            if (studentSearch) {
                studentSearch.addEventListener('input', function() {
                    const searchTerm = this.value.toLowerCase();
                    const studentItems = document.querySelectorAll('.student-item');
                    
                    studentItems.forEach(item => {
                        const name = item.querySelector('.student-name').textContent.toLowerCase();
                        const email = item.querySelector('.student-email').textContent.toLowerCase();
                        
                        if (name.includes(searchTerm) || email.includes(searchTerm)) {
                            item.style.display = 'flex';
                        } else {
                            item.style.display = 'none';
                        }
=======
            const videoUrlInput = document.getElementById('video_url');
            const platformSelect = document.getElementById('platform');
            const thumbnailPreview = document.getElementById('thumbnail_preview');
            const thumbnailUrlInput = document.getElementById('thumbnail_url');
            const publishVideoBtn = document.getElementById('publish_video_btn');
            
            // Video form submission handling
            if (publishVideoBtn) {
                publishVideoBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    
                    // Find the form
                    const form = document.querySelector('.upload-form');
                    
                    // Validar campos obligatorios
                    const title = document.getElementById('title').value.trim();
                    const platform = document.getElementById('platform').value;
                    const videoUrl = document.getElementById('video_url').value.trim();
                    
                    let isValid = true;
                    let errorMessage = '';
                    
                    // Validar título
                    if (!title) {
                        isValid = false;
                        errorMessage = 'Por favor, ingresa un título para el video.';
                    }
                    // Validar plataforma
                    else if (!platform) {
                        isValid = false;
                        errorMessage = 'Por favor, selecciona una plataforma.';
                    }
                    // Validar URL del video
                    else if (!videoUrl) {
                        isValid = false;
                        errorMessage = 'Por favor, ingresa la URL del video.';
                    }
                    // Validar formato de URL según la plataforma
                    else if (platform === 'youtube' && !videoUrl.match(/(?:youtube\.com\/(?:[^\/]+\/.+\/|(?:v|e(?:mbed)?)\/|.*[?&]v=)|youtu\.be\/)([^"&?\/\s]{11})/i)) {
                        isValid = false;
                        errorMessage = 'Por favor, ingresa una URL válida de YouTube.';
                    }
                    else if (platform === 'vimeo' && !videoUrl.match(/vimeo\.com\/([0-9]+)/)) {
                        isValid = false;
                        errorMessage = 'Por favor, ingresa una URL válida de Vimeo.';
                    }
                    
                    if (!isValid) {
                        // Mostrar mensaje de error
                        const errorDiv = document.createElement('div');
                        errorDiv.className = 'alert alert-danger mt-3';
                        errorDiv.innerHTML = `<i class="fas fa-exclamation-triangle"></i> ${errorMessage}`;
                        
                        // Remover mensajes de error anteriores
                        const existingError = form.nextElementSibling;
                        if (existingError && existingError.classList.contains('alert-danger')) {
                            existingError.remove();
                        }
                        
                        // Insertar nuevo mensaje de error
                        form.after(errorDiv);
                        
                        // Remover mensaje después de 5 segundos
                        setTimeout(() => {
                            errorDiv.remove();
                        }, 5000);
                        
                        return;
                    }
                    
                    // Si todo es válido, continuar con el envío
                    const formData = new FormData(form);
                    formData.append('ajax_submit', '1');
                    
                    // Mostrar estado de carga
                    publishVideoBtn.disabled = true;
                    publishVideoBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Enviando...';
                    
                    // Enviar solicitud AJAX
                    fetch(form.action, {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.text())
                    .then(data => {
                        // Re-habilitar botón
                        publishVideoBtn.disabled = false;
                        publishVideoBtn.innerHTML = '<i class="fas fa-check"></i> ¡Publicado!';
                        
                        // Mostrar mensaje de éxito
                        const successMessage = document.createElement('div');
                        successMessage.className = 'alert alert-success mt-3';
                        successMessage.innerHTML = '<i class="fas fa-check-circle"></i> El video ha sido enviado exitosamente a Material de Apoyo.';
                        
                        // Insertar después del formulario
                        form.after(successMessage);
                        
                        // Resetear formulario después de 2 segundos
                        setTimeout(() => {
                            form.reset();
                            const thumbnailPreview = document.getElementById('thumbnail_preview');
                            if (thumbnailPreview) {
                                thumbnailPreview.innerHTML = `
                                    <div class="no-thumbnail">
                                        <i class="fas fa-image"></i>
                                        <p>La miniatura se generará automáticamente</p>
                                    </div>
                                `;
                            }
                            
                            // Resetear botón
                            publishVideoBtn.innerHTML = '<i class="fas fa-paper-plane"></i> Publicar Video';
                            
                            // Remover mensaje de éxito después de 3 segundos
                            setTimeout(() => {
                                successMessage.remove();
                            }, 3000);
                        }, 2000);
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        
                        // Re-habilitar botón
                        publishVideoBtn.disabled = false;
                        publishVideoBtn.innerHTML = '<i class="fas fa-paper-plane"></i> Publicar Video';
                        
                        // Mostrar mensaje de error
                        const errorMessage = document.createElement('div');
                        errorMessage.className = 'alert alert-danger mt-3';
                        errorMessage.innerHTML = '<i class="fas fa-exclamation-triangle"></i> Ocurrió un error al enviar el video. Por favor intente de nuevo.';
                        
                        // Insertar después del formulario
                        form.after(errorMessage);
                        
                        // Remover mensaje de error después de 5 segundos
                        setTimeout(() => {
                            errorMessage.remove();
                        }, 5000);
>>>>>>> origin/Master
                    });
                });
            }
            
<<<<<<< HEAD
            // Update selected students count
            const checkboxes = document.querySelectorAll('input[name="selected_students[]"]');
            const selectedCountElement = document.getElementById('selected-count');
            
            if (checkboxes.length && selectedCountElement) {
                // Initial count
                updateSelectedCount();
                
                // Update count on checkbox change
                checkboxes.forEach(checkbox => {
                    checkbox.addEventListener('change', updateSelectedCount);
                });
            }
            
            function updateSelectedCount() {
                const selectedCount = document.querySelectorAll('input[name="selected_students[]"]:checked').length;
                selectedCountElement.textContent = selectedCount;
            }
            
            // Mobile sidebar toggle
            const menuToggle = document.createElement('button');
            menuToggle.classList.add('menu-toggle');
            menuToggle.innerHTML = '<i class="fas fa-bars"></i>';
            document.querySelector('.main-header').prepend(menuToggle);
            
            menuToggle.addEventListener('click', function() {
                document.querySelector('.sidebar').classList.toggle('active');
            });
            
            // Close sidebar when clicking outside on mobile
            document.addEventListener('click', function(event) {
                const sidebar = document.querySelector('.sidebar');
                const menuToggle = document.querySelector('.menu-toggle');
                
                if (window.innerWidth <= 768 && 
                    !sidebar.contains(event.target) && 
                    !menuToggle.contains(event.target)) {
                    sidebar.classList.remove('active');
                }
            });
=======
            // Resto del código existente...
            // ... existing code ...
>>>>>>> origin/Master
        });
    </script>
</body>
</html>