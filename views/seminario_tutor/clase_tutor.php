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

// Get current step (default to 1)
$currentStep = isset($_GET['step']) ? intval($_GET['step']) : 1;

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Store form data in session
    if ($currentStep === 1 && isset($_POST['title'])) {
        $_SESSION['video_upload'] = [
            'title' => $_POST['title'],
            'description' => $_POST['description'],
            'filename' => $_POST['filename'] ?? '',
            'filesize' => $_POST['filesize'] ?? ''
        ];
        // Redirect to step 2
        header('Location: clase_tutor.php?step=2');
        exit;
    } elseif ($currentStep === 2 && isset($_POST['selected_students'])) {
        // Store selected students
        $_SESSION['video_upload']['selected_students'] = $_POST['selected_students'];
        // Redirect to step 3
        header('Location: clase_tutor.php?step=3');
        exit;
    } elseif ($currentStep === 3 && isset($_POST['share'])) {
        // Process video sharing (in a real app, this would save to database)
        // For demo purposes, we'll just show success message
        $_SESSION['video_shared'] = true;
        // Redirect to success page or back to dashboard
        header('Location: clase_tutor.php?step=4');
        exit;
    }
}

// Get video upload data from session
$videoData = $_SESSION['video_upload'] ?? [];

// Get selected students data
$selectedStudents = [];
if (isset($videoData['selected_students'])) {
    foreach ($videoData['selected_students'] as $studentId) {
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
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FET - Subir Videos para Estudiantes</title>
    <link rel="stylesheet" href="../../assets/css/tutor_css/clase_tutor.css">
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
                    <li ><a href="actividades_tutor.php"><i class="fas fa-book"></i> Actividades</a></li>
                    <li class="active"><a href="clase_tutor.php"><i class="fas fa-video"></i> Aula Virtual</a></li>
                    <li><a href="material_tutor.php"><i class="fas fa-file-alt"></i> Material de Apoyo</a></li>
                </ul>
            </nav>

            <div class="sidebar-footer">
                <a href="#" class="logout-btn"><i class="fas fa-sign-out-alt"></i> Cerrar Sesión</a>
            </div>
        </aside>
        
        <!-- Main Content -->
        <main class="main-content">
            <header class="main-header">
                <h2>Subir Videos para Estudiantes</h2>
                <p class="subtitle">Comparte contenido educativo con tus estudiantes</p>
                <div class="header-actions">
                    <button class="notification-btn">
                        <i class="fas fa-bell"></i>
                    </button>
                </div>
            </header>
            
            <section class="content-section">
                <div class="upload-container">
                    <div class="upload-header">
                        <h3><i class="fas fa-cloud-upload-alt"></i> Subir Nuevo Video</h3>
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
                            <!-- Step 1: Video Details -->
                            <form action="clase_tutor.php?step=1" method="post" class="upload-form">
                                <div class="form-group">
                                    <label for="title">Título del Video <span class="required">*</span></label>
                                    <input type="text" id="title" name="title" required 
                                        value="<?php echo htmlspecialchars($videoData['title'] ?? ''); ?>">
                                </div>
                                
                                <div class="form-group">
                                    <label for="description">Descripción</label>
                                    <textarea id="description" name="description" rows="5"><?php echo htmlspecialchars($videoData['description'] ?? ''); ?></textarea>
                                </div>
                                
                                <div class="form-group">
                                    <label for="video-file">Archivo de Video <span class="required">*</span></label>
                                    <div class="file-upload-container" id="dropzone">
                                        <?php if (!empty($videoData['filename'])): ?>
                                            <div class="file-preview">
                                                <i class="fas fa-file-video"></i>
                                                <div class="file-info">
                                                    <span class="file-name"><?php echo htmlspecialchars($videoData['filename']); ?></span>
                                                    <span class="file-size"><?php echo htmlspecialchars($videoData['filesize']); ?></span>
                                                </div>
                                                <button type="button" class="remove-file" id="remove-file">
                                                    <i class="fas fa-times"></i>
                                                </button>
                                            </div>
                                            <input type="hidden" name="filename" value="<?php echo htmlspecialchars($videoData['filename']); ?>">
                                            <input type="hidden" name="filesize" value="<?php echo htmlspecialchars($videoData['filesize']); ?>">
                                        <?php else: ?>
                                            <div class="upload-message">
                                                <i class="fas fa-cloud-upload-alt"></i>
                                                <p>Arrastra y suelta tu archivo de video aquí o</p>
                                                <label for="video-file" class="file-select-btn">Seleccionar archivo</label>
                                                <input type="file" id="video-file" name="video-file" accept="video/*" style="display: none;">
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <div class="form-actions">
                                    <button type="submit" class="btn btn-primary">Continuar</button>
                                </div>
                            </form>
                        
                        <?php elseif ($currentStep === 2): ?>
                            <!-- Step 2: Select Students -->
                            <form action="clase_tutor.php?step=2" method="post" class="upload-form">
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
                                                        <?php if (isset($videoData['selected_students']) && in_array($student['id'], $videoData['selected_students'])) echo 'checked'; ?>>
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
                                    <a href="clase_tutor.php?step=1" class="btn btn-secondary">Atrás</a>
                                    <button type="submit" class="btn btn-primary">Continuar</button>
                                </div>
                            </form>
                        
                        <?php elseif ($currentStep === 3): ?>
                            <!-- Step 3: Review and Share -->
                            <form action="clase_tutor.php?step=3" method="post" class="upload-form">
                                <div class="review-section">
                                    <h4>Revisar y Compartir</h4>
                                    
                                    <div class="review-group">
                                        <h5>DETALLES DEL VIDEO</h5>
                                        <div class="review-item">
                                            <div class="review-label">Título:</div>
                                            <div class="review-value"><?php echo htmlspecialchars($videoData['title'] ?? ''); ?></div>
                                        </div>
                                        <div class="review-item">
                                            <div class="review-label">Descripción:</div>
                                            <div class="review-value"><?php echo htmlspecialchars($videoData['description'] ?? ''); ?></div>
                                        </div>
                                        <div class="review-item">
                                            <div class="review-label">Archivo:</div>
                                            <div class="review-value"><?php echo htmlspecialchars($videoData['filename'] ?? ''); ?></div>
                                        </div>
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
                                    <a href="clase_tutor.php?step=2" class="btn btn-secondary">Atrás</a>
                                    <button type="submit" name="share" class="btn btn-success">
                                        <i class="fas fa-paper-plane"></i> Compartir Video
                                    </button>
                                </div>
                            </form>
                            
                        <?php elseif ($currentStep === 4): ?>
                            <!-- Step 4: Upload Progress/Success -->
                            <div class="upload-success">
                                <div class="progress-container">
                                    <h4>Progreso de la Subida</h4>
                                    <div class="progress-bar-container">
                                        <div class="progress-bar" style="width: 100%;"></div>
                                    </div>
                                    <div class="progress-percentage">100%</div>
                                </div>
                                
                                <div class="success-message">
                                    <i class="fas fa-check-circle"></i>
                                    <h4>¡Video compartido exitosamente!</h4>
                                    <p>Tu video ha sido compartido con los estudiantes seleccionados.</p>
                                </div>
                                
                                <div class="form-actions">
                                    <a href="clase_tutor.php?step=1" class="btn btn-secondary">Subir otro video</a>
                                    <a href="#" class="btn btn-primary">Volver al inicio</a>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </section>
             
        </main>
    </div>

    <script>
        // File upload handling
        document.addEventListener('DOMContentLoaded', function() {
            const dropzone = document.getElementById('dropzone');
            const fileInput = document.getElementById('video-file');
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
                            <p>Arrastra y suelta tu archivo de video aquí o</p>
                            <label for="video-file" class="file-select-btn">Seleccionar archivo</label>
                            <input type="file" id="video-file" name="video-file" accept="video/*" style="display: none;">
                        </div>
                    `;
                    
                    // Re-attach event listener to the new file input
                    document.getElementById('video-file').addEventListener('change', function(e) {
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
            
            function handleFileSelect(file) {
                if (file && file.type.startsWith('video/')) {
                    // Format file size
                    const size = formatFileSize(file.size);
                    
                    // Update the dropzone with file preview
                    dropzone.innerHTML = `
                        <div class="file-preview">
                            <i class="fas fa-file-video"></i>
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
                                <p>Arrastra y suelta tu archivo de video aquí o</p>
                                <label for="video-file" class="file-select-btn">Seleccionar archivo</label>
                                <input type="file" id="video-file" name="video-file" accept="video/*" style="display: none;">
                            </div>
                        `;
                        
                        // Re-attach event listener to the new file input
                        document.getElementById('video-file').addEventListener('change', function(e) {
                            handleFileSelect(e.target.files[0]);
                        });
                    });
                } else {
                    alert('Por favor, selecciona un archivo de video válido.');
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