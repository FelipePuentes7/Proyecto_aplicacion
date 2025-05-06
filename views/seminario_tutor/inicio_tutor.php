<?php
// Start session management
session_start();

// Mock data for upcoming classes
$upcomingClasses = [
    [
        'id' => 1,
        'title' => 'Introducción a la Programación',
        'date' => '2025-05-06',
        'time' => '10:00',
        'duration' => 60, // minutes
        'platform' => 'meet',
        'link' => 'https://meet.google.com/abc-defg-hij',
        'description' => 'Clase introductoria sobre conceptos básicos de programación.'
    ],
    [
        'id' => 2,
        'title' => 'Estructuras de Datos',
        'date' => '2025-05-08',
        'time' => '14:30',
        'duration' => 90, // minutes
        'platform' => 'classroom',
        'link' => 'https://classroom.google.com/c/123456789',
        'description' => 'Revisión de estructuras de datos fundamentales.'
    ],
    [
        'id' => 3,
        'title' => 'Algoritmos de Búsqueda',
        'date' => '2025-05-10',
        'time' => '09:00',
        'duration' => 120, // minutes
        'platform' => 'meet',
        'link' => 'https://meet.google.com/xyz-abcd-efg',
        'description' => 'Análisis y aplicación de algoritmos de búsqueda.'
    ]
];

// Mock data for assigned tasks
$assignedTasks = [
    [
        'id' => 1,
        'title' => 'Ejercicios de Programación',
        'description' => 'Completar los ejercicios 1-10 del capítulo 3.',
        'deadline_date' => '2025-05-15',
        'deadline_time' => '23:59',
        'status' => 'active',
        'submissions' => 5,
        'total_students' => 20
    ],
    [
        'id' => 2,
        'title' => 'Proyecto: Aplicación Web',
        'description' => 'Desarrollar una aplicación web simple utilizando HTML, CSS y JavaScript.',
        'deadline_date' => '2025-05-20',
        'deadline_time' => '23:59',
        'status' => 'active',
        'submissions' => 2,
        'total_students' => 20
    ],
    [
        'id' => 3,
        'title' => 'Cuestionario: Fundamentos de Bases de Datos',
        'description' => 'Responder el cuestionario sobre normalización y diseño de bases de datos.',
        'deadline_date' => '2025-05-07',
        'deadline_time' => '23:59',
        'status' => 'active',
        'submissions' => 12,
        'total_students' => 20
    ]
];

// Process form submission for new task
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'add_task') {
        // In a real application, this would save to a database
        // For demo purposes, we'll just show a success message
        $_SESSION['task_added'] = true;
    }
}

// Function to get platform icon
function getPlatformIcon($platform) {
    $icons = [
        'meet' => 'fas fa-video',
        'classroom' => 'fas fa-chalkboard',
        'zoom' => 'fas fa-video',
        'teams' => 'fas fa-users'
    ];
    
    return $icons[$platform] ?? 'fas fa-globe';
}

// Function to calculate time remaining
function getTimeRemaining($deadline_date, $deadline_time) {
    $deadline = $deadline_date . ' ' . $deadline_time;
    $deadline_timestamp = strtotime($deadline);
    $current_timestamp = time();
    $remaining_seconds = $deadline_timestamp - $current_timestamp;
    
    if ($remaining_seconds <= 0) {
        return [
            'expired' => true,
            'days' => 0,
            'hours' => 0,
            'minutes' => 0,
            'seconds' => 0
        ];
    }
    
    $days = floor($remaining_seconds / (60 * 60 * 24));
    $hours = floor(($remaining_seconds % (60 * 60 * 24)) / (60 * 60));
    $minutes = floor(($remaining_seconds % (60 * 60)) / 60);
    $seconds = $remaining_seconds % 60;
    
    return [
        'expired' => false,
        'days' => $days,
        'hours' => $hours,
        'minutes' => $minutes,
        'seconds' => $seconds,
        'timestamp' => $deadline_timestamp
    ];
}

// Function to format date in Spanish
function formatDateSpanish($date) {
    $timestamp = strtotime($date);
    $months = [
        'enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio',
        'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'
    ];
    
    $day = date('j', $timestamp);
    $month = $months[date('n', $timestamp) - 1];
    $year = date('Y', $timestamp);
    
    return "$day de $month de $year";
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FET - Panel de Tutor</title>
    <link rel="stylesheet" href="/assets/css/tutor_css/inicio_tutor.css">
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
                    <li class="active"><a href="inicio_tutor.php"><i class="fas fa-home"></i> Inicio</a></li>
                    <li ><a href="actividades_tutor.php"><i class="fas fa-book"></i> Actividades</a></li>
                    <li ><a href="clase_tutor.php"><i class="fas fa-video"></i> Aula Virtual</a></li>
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
                <h2>Panel de Tutor</h2>
                <p class="subtitle">Bienvenido al panel de gestión para tutores</p>
                <div class="header-actions">
                    <button class="notification-btn">
                        <i class="fas fa-bell"></i>
                    </button>
                </div>
            </header>
            
            <section class="dashboard-grid">
                <!-- Quick Stats -->
                <div class="dashboard-card stats-card">
                    <div class="card-header">
                        <h3>Resumen</h3>
                    </div>
                    <div class="stats-container">
                        <div class="stat-item">
                            <div class="stat-icon">
                                <i class="fas fa-users"></i>
                            </div>
                            <div class="stat-info">
                                <span class="stat-value">20</span>
                                <span class="stat-label">Estudiantes</span>
                            </div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-icon">
                                <i class="fas fa-tasks"></i>
                            </div>
                            <div class="stat-info">
                                <span class="stat-value">3</span>
                                <span class="stat-label">Tareas Activas</span>
                            </div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-icon">
                                <i class="fas fa-video"></i>
                            </div>
                            <div class="stat-info">
                                <span class="stat-value">3</span>
                                <span class="stat-label">Clases Programadas</span>
                            </div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-icon">
                                <i class="fas fa-file-alt"></i>
                            </div>
                            <div class="stat-info">
                                <span class="stat-value">12</span>
                                <span class="stat-label">Materiales</span>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Upcoming Classes -->
                <div class="dashboard-card classes-card">
                    <div class="card-header">
                        <h3>Próximas Clases</h3>
                        <button class="add-btn" id="add-class-btn">
                            <i class="fas fa-plus"></i>
                        </button>
                    </div>
                    <div class="classes-list">
                        <?php foreach ($upcomingClasses as $class): ?>
                            <div class="class-item">
                                <div class="class-info">
                                    <div class="class-title"><?php echo htmlspecialchars($class['title']); ?></div>
                                    <div class="class-details">
                                        <span class="class-date">
                                            <i class="fas fa-calendar-alt"></i> 
                                            <?php echo formatDateSpanish($class['date']); ?>
                                        </span>
                                        <span class="class-time">
                                            <i class="fas fa-clock"></i> 
                                            <?php echo htmlspecialchars($class['time']); ?> 
                                            (<?php echo htmlspecialchars($class['duration']); ?> min)
                                        </span>
                                    </div>
                                    <div class="class-description">
                                        <?php echo htmlspecialchars($class['description']); ?>
                                    </div>
                                </div>
                                <div class="class-actions">
                                    <a href="<?php echo htmlspecialchars($class['link']); ?>" target="_blank" class="join-class-btn">
                                        <i class="<?php echo getPlatformIcon($class['platform']); ?>"></i>
                                        Unirse
                                    </a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <!-- Tasks -->
                <div class="dashboard-card tasks-card">
                    <div class="card-header">
                        <h3>Tareas Asignadas</h3>
                        <button class="add-btn" id="add-task-btn">
                            <i class="fas fa-plus"></i>
                        </button>
                    </div>
                    <div class="tasks-list">
                        <?php foreach ($assignedTasks as $task): ?>
                            <?php $timeRemaining = getTimeRemaining($task['deadline_date'], $task['deadline_time']); ?>
                            <div class="task-item" data-deadline="<?php echo $timeRemaining['timestamp']; ?>">
                                <div class="task-info">
                                    <div class="task-title"><?php echo htmlspecialchars($task['title']); ?></div>
                                    <div class="task-description">
                                        <?php echo htmlspecialchars($task['description']); ?>
                                    </div>
                                    <div class="task-meta">
                                        <span class="task-submissions">
                                            <i class="fas fa-user-check"></i> 
                                            <?php echo $task['submissions']; ?>/<?php echo $task['total_students']; ?> entregas
                                        </span>
                                    </div>
                                </div>
                                <div class="task-deadline">
                                    <div class="deadline-label">Tiempo restante:</div>
                                    <div class="countdown-timer <?php echo $timeRemaining['expired'] ? 'expired' : ''; ?>">
                                        <div class="countdown-item">
                                            <span class="countdown-value days"><?php echo $timeRemaining['days']; ?></span>
                                            <span class="countdown-label">días</span>
                                        </div>
                                        <div class="countdown-item">
                                            <span class="countdown-value hours"><?php echo $timeRemaining['hours']; ?></span>
                                            <span class="countdown-label">horas</span>
                                        </div>
                                        <div class="countdown-item">
                                            <span class="countdown-value minutes"><?php echo $timeRemaining['minutes']; ?></span>
                                            <span class="countdown-label">min</span>
                                        </div>
                                        <div class="countdown-item">
                                            <span class="countdown-value seconds"><?php echo $timeRemaining['seconds']; ?></span>
                                            <span class="countdown-label">seg</span>
                                        </div>
                                    </div>
                                    <div class="deadline-date">
                                        <i class="fas fa-calendar-alt"></i> 
                                        <?php echo formatDateSpanish($task['deadline_date']); ?> a las <?php echo $task['deadline_time']; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <!-- Quick Actions -->
                <div class="dashboard-card actions-card">
                    <div class="card-header">
                        <h3>Acciones Rápidas</h3>
                    </div>
                    <div class="quick-actions">
                        <a href="#" class="action-btn" id="review-activities-btn">
                            <div class="action-icon">
                                <i class="fas fa-clipboard-check"></i>
                            </div>
                            <div class="action-label">Revisar Actividades</div>
                        </a>
                        <a href="clase_tutor.php" class="action-btn">
                            <div class="action-icon">
                                <i class="fas fa-video"></i>
                            </div>
                            <div class="action-label">Subir Clases</div>
                        </a>
                        <a href="material_tutor.php" class="action-btn">
                            <div class="action-icon">
                                <i class="fas fa-file-upload"></i>
                            </div>
                            <div class="action-label">Subir Material</div>
                        </a>
                        <a href="#" class="action-btn" id="send-notification-btn">
                            <div class="action-icon">
                                <i class="fas fa-bell"></i>
                            </div>
                            <div class="action-label">Enviar Notificación</div>
                        </a>
                    </div>
                </div>
            </section>
            
        </main>
    </div>
    
    <!-- Modal for adding new task -->
    <div class="modal" id="add-task-modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Añadir Nueva Tarea</h3>
                <button class="close-modal">&times;</button>
            </div>
            <div class="modal-body">
                <form action="inicio_tutor.php" method="post" id="add-task-form">
                    <input type="hidden" name="action" value="add_task">
                    
                    <div class="form-group">
                        <label for="task-title">Título de la Tarea <span class="required">*</span></label>
                        <input type="text" id="task-title" name="task_title" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="task-description">Descripción <span class="required">*</span></label>
                        <textarea id="task-description" name="task_description" rows="3" required></textarea>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="deadline-date">Fecha Límite <span class="required">*</span></label>
                            <input type="date" id="deadline-date" name="deadline_date" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="deadline-time">Hora Límite <span class="required">*</span></label>
                            <input type="time" id="deadline-time" name="deadline_time" required>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="task-type">Tipo de Tarea <span class="required">*</span></label>
                        <select id="task-type" name="task_type" required>
                            <option value="">Seleccionar tipo</option>
                            <option value="assignment">Tarea</option>
                            <option value="quiz">Cuestionario</option>
                            <option value="project">Proyecto</option>
                            <option value="exam">Examen</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Archivos Adjuntos</label>
                        <div class="file-upload-container" id="task-file-dropzone">
                            <div class="upload-message">
                                <i class="fas fa-cloud-upload-alt"></i>
                                <p>Arrastra y suelta archivos aquí o</p>
                                <label for="task-file" class="file-select-btn">Seleccionar archivos</label>
                                <input type="file" id="task-file" name="task_file" style="display: none;" multiple>
                            </div>
                        </div>
                        <p class="form-help">Puedes adjuntar instrucciones, ejemplos o recursos adicionales.</p>
                    </div>
                    
                    <div class="form-actions">
                        <button type="button" class="btn btn-secondary cancel-modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">Crear Tarea</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Modal for adding new class -->
    <div class="modal" id="add-class-modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Programar Nueva Clase</h3>
                <button class="close-modal">&times;</button>
            </div>
            <div class="modal-body">
                <form action="inicio_tutor.php" method="post" id="add-class-form">
                    <input type="hidden" name="action" value="add_class">
                    
                    <div class="form-group">
                        <label for="class-title">Título de la Clase <span class="required">*</span></label>
                        <input type="text" id="class-title" name="class_title" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="class-description">Descripción</label>
                        <textarea id="class-description" name="class_description" rows="3"></textarea>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="class-date">Fecha <span class="required">*</span></label>
                            <input type="date" id="class-date" name="class_date" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="class-time">Hora <span class="required">*</span></label>
                            <input type="time" id="class-time" name="class_time" required>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="class-duration">Duración (minutos) <span class="required">*</span></label>
                            <input type="number" id="class-duration" name="class_duration" min="15" step="5" value="60" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="class-platform">Plataforma <span class="required">*</span></label>
                            <select id="class-platform" name="class_platform" required>
                                <option value="">Seleccionar plataforma</option>
                                <option value="meet">Google Meet</option>
                                <option value="classroom">Google Classroom</option>
                                <option value="zoom">Zoom</option>
                                <option value="teams">Microsoft Teams</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="class-link">Enlace de la Clase <span class="required">*</span></label>
                        <input type="url" id="class-link" name="class_link" placeholder="https://" required>
                    </div>
                    
                    <div class="form-actions">
                        <button type="button" class="btn btn-secondary cancel-modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">Programar Clase</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Success notification -->
    <?php if (isset($_SESSION['task_added']) && $_SESSION['task_added']): ?>
        <div class="notification success-notification">
            <div class="notification-icon">
                <i class="fas fa-check-circle"></i>
            </div>
            <div class="notification-content">
                <div class="notification-title">¡Tarea creada exitosamente!</div>
                <div class="notification-message">La tarea ha sido asignada a los estudiantes.</div>
            </div>
            <button class="close-notification">&times;</button>
        </div>
        <?php unset($_SESSION['task_added']); ?>
    <?php endif; ?>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Modal functionality
            const addTaskBtn = document.getElementById('add-task-btn');
            const addClassBtn = document.getElementById('add-class-btn');
            const addTaskModal = document.getElementById('add-task-modal');
            const addClassModal = document.getElementById('add-class-modal');
            const closeButtons = document.querySelectorAll('.close-modal, .cancel-modal');
            
            // Open task modal
            addTaskBtn.addEventListener('click', function() {
                addTaskModal.classList.add('active');
            });
            
            // Open class modal
            addClassBtn.addEventListener('click', function() {
                addClassModal.classList.add('active');
            });
            
            // Close modals
            closeButtons.forEach(button => {
                button.addEventListener('click', function() {
                    addTaskModal.classList.remove('active');
                    addClassModal.classList.remove('active');
                });
            });
            
            // Close modal when clicking outside
            window.addEventListener('click', function(event) {
                if (event.target === addTaskModal) {
                    addTaskModal.classList.remove('active');
                }
                if (event.target === addClassModal) {
                    addClassModal.classList.remove('active');
                }
            });
            
            // File upload handling
            const fileDropzone = document.getElementById('task-file-dropzone');
            const fileInput = document.getElementById('task-file');
            
            if (fileInput && fileDropzone) {
                // Prevent default drag behaviors
                ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
                    fileDropzone.addEventListener(eventName, preventDefaults, false);
                });
                
                // Highlight drop area when item is dragged over it
                ['dragenter', 'dragover'].forEach(eventName => {
                    fileDropzone.addEventListener(eventName, highlight, false);
                });
                
                ['dragleave', 'drop'].forEach(eventName => {
                    fileDropzone.addEventListener(eventName, unhighlight, false);
                });
                
                // Handle dropped files
                fileDropzone.addEventListener('drop', handleDrop, false);
                
                // Handle file selection
                fileInput.addEventListener('change', function(e) {
                    handleFiles(e.target.files);
                });
            }
            
            function preventDefaults(e) {
                e.preventDefault();
                e.stopPropagation();
            }
            
            function highlight() {
                fileDropzone.classList.add('highlight');
            }
            
            function unhighlight() {
                fileDropzone.classList.remove('highlight');
            }
            
            function handleDrop(e) {
                const dt = e.dataTransfer;
                const files = dt.files;
                handleFiles(files);
            }
            
            function handleFiles(files) {
                if (files.length) {
                    // Display file names
                    const fileList = document.createElement('div');
                    fileList.className = 'file-list';
                    
                    Array.from(files).forEach(file => {
                        const fileItem = document.createElement('div');
                        fileItem.className = 'file-item';
                        
                        const fileIcon = document.createElement('i');
                        fileIcon.className = getFileIcon(file.name);
                        
                        const fileName = document.createElement('span');
                        fileName.className = 'file-name';
                        fileName.textContent = file.name;
                        
                        const fileSize = document.createElement('span');
                        fileSize.className = 'file-size';
                        fileSize.textContent = formatFileSize(file.size);
                        
                        fileItem.appendChild(fileIcon);
                        fileItem.appendChild(fileName);
                        fileItem.appendChild(fileSize);
                        
                        fileList.appendChild(fileItem);
                    });
                    
                    // Replace dropzone content
                    fileDropzone.innerHTML = '';
                    fileDropzone.appendChild(fileList);
                    
                    // Add reset button
                    const resetBtn = document.createElement('button');
                    resetBtn.type = 'button';
                    resetBtn.className = 'reset-files-btn';
                    resetBtn.innerHTML = 'Eliminar archivos';
                    resetBtn.addEventListener('click', resetFileUpload);
                    
                    fileDropzone.appendChild(resetBtn);
                }
            }
            
            function resetFileUpload() {
                fileInput.value = '';
                fileDropzone.innerHTML = `
                    <div class="upload-message">
                        <i class="fas fa-cloud-upload-alt"></i>
                        <p>Arrastra y suelta archivos aquí o</p>
                        <label for="task-file" class="file-select-btn">Seleccionar archivos</label>
                        <input type="file" id="task-file" name="task_file" style="display: none;" multiple>
                    </div>
                `;
                
                // Re-attach event listener to the new file input
                document.getElementById('task-file').addEventListener('change', function(e) {
                    handleFiles(e.target.files);
                });
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
            
            function formatFileSize(bytes) {
                if (bytes === 0) return '0 Bytes';
                
                const k = 1024;
                const sizes = ['Bytes', 'KB', 'MB', 'GB', 'TB'];
                const i = Math.floor(Math.log(bytes) / Math.log(k));
                
                return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
            }
            
            // Countdown timers
            const taskItems = document.querySelectorAll('.task-item');
            
            taskItems.forEach(taskItem => {
                const deadlineTimestamp = parseInt(taskItem.dataset.deadline);
                const daysElement = taskItem.querySelector('.countdown-value.days');
                const hoursElement = taskItem.querySelector('.countdown-value.hours');
                const minutesElement = taskItem.querySelector('.countdown-value.minutes');
                const secondsElement = taskItem.querySelector('.countdown-value.seconds');
                const countdownTimer = taskItem.querySelector('.countdown-timer');
                
                // Update the countdown every second
                const timerInterval = setInterval(function() {
                    const now = Math.floor(Date.now() / 1000);
                    const remainingSeconds = deadlineTimestamp - now;
                    
                    if (remainingSeconds <= 0) {
                        clearInterval(timerInterval);
                        countdownTimer.classList.add('expired');
                        daysElement.textContent = '0';
                        hoursElement.textContent = '0';
                        minutesElement.textContent = '0';
                        secondsElement.textContent = '0';
                        return;
                    }
                    
                    const days = Math.floor(remainingSeconds / (60 * 60 * 24));
                    const hours = Math.floor((remainingSeconds % (60 * 60 * 24)) / (60 * 60));
                    const minutes = Math.floor((remainingSeconds % (60 * 60)) / 60);
                    const seconds = remainingSeconds % 60;
                    
                    daysElement.textContent = days;
                    hoursElement.textContent = hours;
                    minutesElement.textContent = minutes;
                    secondsElement.textContent = seconds;
                    
                    // Add warning class when less than 24 hours remaining
                    if (remainingSeconds < 60 * 60 * 24) {
                        countdownTimer.classList.add('warning');
                    }
                    
                    // Add critical class when less than 1 hour remaining
                    if (remainingSeconds < 60 * 60) {
                        countdownTimer.classList.add('critical');
                    }
                }, 1000);
            });
            
            // Close notification
            const closeNotificationBtn = document.querySelector('.close-notification');
            if (closeNotificationBtn) {
                closeNotificationBtn.addEventListener('click', function() {
                    this.parentElement.style.display = 'none';
                });
                
                // Auto-hide notification after 5 seconds
                setTimeout(function() {
                    document.querySelector('.notification').style.display = 'none';
                }, 5000);
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
            
            // Set min date for date inputs to today
            const today = new Date().toISOString().split('T')[0];
            document.getElementById('deadline-date').min = today;
            document.getElementById('class-date').min = today;
            
            // Review activities button action
            document.getElementById('review-activities-btn').addEventListener('click', function(e) {
                e.preventDefault();
                alert('Esta funcionalidad te permitirá revisar las actividades entregadas por los estudiantes.');
            });
            
            // Send notification button action
            document.getElementById('send-notification-btn').addEventListener('click', function(e) {
                e.preventDefault();
                alert('Esta funcionalidad te permitirá enviar notificaciones a los estudiantes.');
            });
        });
    </script>
</body>
</html>