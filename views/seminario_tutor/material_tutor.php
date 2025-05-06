<?php
// Start session management
session_start();

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

// Mock data for material categories
$categories = [
    'video_links' => 'Enlaces de Video',
    'documents' => 'Documentación',
];

// Get current step (default to 1) and category
$currentStep = isset($_GET['step']) ? intval($_GET['step']) : 1;
$category = isset($_GET['category']) ? $_GET['category'] : '';

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
    }
}

// Get material data from session
$materialData = $_SESSION['material'] ?? [];

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
    
    return $icons[$platform] ?? $icons['other'];
}

// Function to get file icon
function getDocumentIcon($filename) {
    $extension = pathinfo($filename, PATHINFO_EXTENSION);
    
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
    
    return $icons[strtolower($extension)] ?? 'fas fa-file';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FET - Material de Apoyo</title>
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
    </div>

    <script>
        // File upload handling
        document.addEventListener('DOMContentLoaded', function() {
            const dropzone = document.getElementById('dropzone');
            const fileInput = document.getElementById('document-file');
            const removeFileBtn = document.getElementById('remove-file');
            
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
                    });
                });
            }
            
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
        });
    </script>
</body>
</html>