<?php
session_start();
require_once '../../config/conexion.php';

// Create connection instance first, before using it
$conexion = new Conexion();
$db = $conexion->getConexion();

// Añadir al inicio del archivo, después de las conexiones
$default_images = [
    'https://images.unsplash.com/photo-1555066931-4365d14bab8c?w=500', // Código en pantalla
    'https://images.unsplash.com/photo-1498050108023-c5249f4df085?w=500', // Laptop con código
    'https://images.unsplash.com/photo-1504639725590-34d0984388bd?w=500', // Desarrollo web
    'https://images.unsplash.com/photo-1461749280684-dccba630e2f6?w=500', // Programación
    'https://images.unsplash.com/photo-1517694712202-14dd9538aa97?w=500', // Código en laptop
    'https://images.unsplash.com/photo-1499951360447-b19be8fe80f5?w=500', // Desarrollo
    'https://images.unsplash.com/photo-1504384764586-bb4cdc1707b0?w=500', // Equipo de trabajo
    'https://images.unsplash.com/photo-1516116216624-53e697fedbea?w=500', // Arquitectura de software
    'https://images.unsplash.com/photo-1555949963-ff9fe0c870eb?w=500', // Diseño de software
    'https://images.unsplash.com/photo-1555066931-bf19f8fd8865?w=500'  // Programación moderna
];

function getRandomImage() {
    global $default_images;
    return $default_images[array_rand($default_images)];
}

// Obtener todos los materiales sin restricciones
try {
    // Obtener todos los materiales sin restricciones
    $sql = "SELECT * FROM materiales_apoyo ORDER BY fecha_subida DESC";
    $stmt = $db->prepare($sql);
    $stmt->execute();
    $materiales = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    error_log("Error en la consulta de materiales: " . $e->getMessage());
    $materiales = [];
}

// Definir categorías
$categories = ['todo', 'documentation', 'tools'];
$activeCategory = isset($_GET['category']) ? $_GET['category'] : 'todo';

// Datos de documentación
$documentation = [
    [
        'title' => 'Manual de HTML',
        'size' => '2.5MB',
        'type' => 'PDF',
        'link' => '#'
    ],
    [
        'title' => 'Guía de PHP',
        'size' => '3.1MB',
        'type' => 'PDF',
        'link' => '#'
    ],
    [
        'title' => 'Tutorial de JavaScript',
        'size' => '1.8MB',
        'type' => 'PDF',
        'link' => '#'
    ]
];

// Datos de herramientas
$tools = [
    [
        'name' => 'Visual Studio Code',
        'description' => 'Editor de código potente y ligero',
        'link' => 'https://code.visualstudio.com/download'
    ],
    [
        'name' => 'XAMPP',
        'description' => 'Servidor local para PHP y MySQL',
        'link' => 'https://www.apachefriends.org/download.html'
    ],
    [
        'name' => 'Node.js',
        'description' => 'Entorno de ejecución para JavaScript',
        'link' => 'https://nodejs.org/download'
    ]
];
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FET - Material de Apoyo</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #00a63d;
            --primary-light: #00c44b;
            --primary-dark: #008f34;
            --secondary: #f8f9fa;
            --success: #28a745;
            --danger: #dc3545;
            --warning: #ffc107;
            --info: #17a2b8;
            --dark: #343a40;
            --light: #f8f9fa;
        }
        
        html, body {
            height: 100%;
            margin: 0;
            padding: 0;
        }
        
        body {
            font-family: 'Roboto', sans-serif;
            background-color: #f5f5f5;
            display: flex;
            flex-direction: column;
            min-height: 100vh;
        }
        
        .header {
            background: linear-gradient(135deg, var(--primary) 0%, #8dc63f 100%);
            color: white;
            padding: 10px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .logo {
            height: 40px;
        }
        
        .nav-links {
            display: flex;
            gap: 20px;
        }
        
        .nav-links a {
            color: white;
            text-decoration: none;
            font-weight: 500;
            padding: 5px 10px;
            border-radius: 5px;
            transition: all 0.3s;
        }
        
        .nav-links a:hover, .nav-links a.active {
            background-color: rgba(255,255,255,0.2);
        }
        
        .user-profile {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .notification-icon {
            font-size: 1.2rem;
            cursor: pointer;
        }
        
        .avatar {
            width: 35px;
            height: 35px;
            border-radius: 50%;
            object-fit: cover;
            cursor: pointer;
        }
        
        .main-content {
            flex: 1 0 auto;
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
            width: 100%;
        }
        
        .card-header {
            background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%);
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 30px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            border: 1px solid rgba(0,0,0,0.05);
        }
        
        .card-title {
            color: var(--dark);
            font-size: 1.8rem;
            font-weight: 700;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 12px;
            padding-bottom: 15px;
            border-bottom: 2px solid rgba(0, 166, 61, 0.1);
        }
        
        .card-title i {
            color: var(--primary);
            font-size: 1.6rem;
            background: rgba(0, 166, 61, 0.1);
            padding: 12px;
            border-radius: 12px;
        }
        
        .categories {
            background-color: white;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        
        .categories h3 {
            color: var(--dark);
            font-size: 1.2rem;
            font-weight: 600;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .categories h3::before {
            content: '';
            display: block;
            width: 4px;
            height: 20px;
            background-color: var(--primary);
            border-radius: 2px;
        }
        
        .category-buttons {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
        }
        
        .category-btn {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            padding: 12px 24px;
            background-color: #f8f9fa;
            border-radius: 8px;
            color: var(--dark);
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s ease;
            border: 1px solid rgba(0,0,0,0.05);
        }
        
        .category-btn i {
            font-size: 1.1rem;
            color: var(--primary);
            transition: all 0.3s ease;
        }
        
        .category-btn:hover {
            background-color: #e9ecef;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        
        .category-btn:hover i {
            transform: scale(1.1);
        }
        
        .category-btn.active {
            background-color: var(--primary);
            color: white;
            box-shadow: 0 4px 12px rgba(0, 166, 61, 0.2);
        }
        
        .category-btn.active i {
            color: white;
        }
        
        .materials-grid {
            display: flex;
            gap: 20px;
            overflow-x: auto;
            padding: 10px 5px;
            scrollbar-width: thin;
            scrollbar-color: var(--primary) #f0f0f0;
        }
        
        .materials-grid::-webkit-scrollbar {
            height: 8px;
        }
        
        .materials-grid::-webkit-scrollbar-track {
            background: #f0f0f0;
            border-radius: 4px;
        }
        
        .materials-grid::-webkit-scrollbar-thumb {
            background-color: var(--primary);
            border-radius: 4px;
        }
        
        .material-card {
            flex: 0 0 300px;
            background-color: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
            display: flex;
            flex-direction: column;
        }
        
        .material-image {
            height: 180px;
            position: relative;
            overflow: hidden;
        }
        
        .material-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.5s ease;
        }
        
        .material-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
        }
        
        .material-type-badge {
            position: absolute;
            top: 15px;
            right: 15px;
            background-color: rgba(0, 166, 61, 0.9);
            color: white;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
            backdrop-filter: blur(4px);
        }
        
        .material-content {
            padding: 20px;
            flex: 1;
            display: flex;
            flex-direction: column;
        }
        
        .material-title {
            font-size: 1.2rem;
            font-weight: 600;
            color: var(--dark);
            margin: 0 0 10px 0;
            line-height: 1.4;
        }
        
        .material-meta {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 15px;
            color: #6c757d;
            font-size: 0.9rem;
        }
        
        .material-meta i {
            color: var(--primary);
        }
        
        .material-description {
            color: #6c757d;
            font-size: 0.95rem;
            line-height: 1.6;
            margin-bottom: 20px;
            flex: 1;
        }
        
        .material-actions {
            display: flex;
            gap: 10px;
            margin-top: auto;
        }
        
        .material-button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 8px 16px;
            background-color: var(--primary);
            color: white;
            border-radius: 6px;
            text-decoration: none;
            font-weight: 500;
            font-size: 0.9rem;
            transition: all 0.3s ease;
            border: none;
            cursor: pointer;
            box-shadow: 0 2px 4px rgba(0, 166, 61, 0.2);
        }
        
        .material-button:hover {
            background-color: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0, 166, 61, 0.3);
        }
        
        .material-button i {
            font-size: 0.9rem;
            transition: transform 0.3s ease;
        }
        
        .material-button:hover i {
            transform: translateX(2px);
        }
        
        .empty-state {
            text-align: center;
            padding: 50px 20px;
            background-color: white;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            margin-top: 30px;
        }
        
        .empty-state i {
            font-size: 4rem;
            color: #ced4da;
            margin-bottom: 20px;
        }
        
        .empty-state h3 {
            color: var(--dark);
            margin-bottom: 10px;
        }
        
        .empty-state p {
            color: #6c757d;
            margin-bottom: 20px;
        }
        
        .footer {
            flex-shrink: 0;
            background: linear-gradient(135deg, var(--primary) 0%, #8dc63f 100%);
            color: white;
            padding: 30px 20px;
            width: 100%;
        }
        
        .footer-content {
            max-width: 1200px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
        }
        
        .footer-info h3 {
            margin-top: 0;
            margin-bottom: 10px;
        }
        
        .footer-info p {
            margin: 5px 0;
            font-size: 0.9rem;
        }
        
        .footer-image {
            max-width: 150px;
        }
        
        .social-links {
            display: flex;
            gap: 15px;
            margin-top: 15px;
        }
        
        .social-links a {
            color: white;
            font-size: 1.2rem;
            transition: opacity 0.3s;
        }
        
        .social-links a:hover {
            opacity: 0.8;
        }
        
        /* Video modal */
        .video-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.8);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }
        
        .video-container {
            width: 80%;
            max-width: 800px;
            background-color: black;
            border-radius: 10px;
            overflow: hidden;
            position: relative;
        }
        
        .close-button {
            position: absolute;
            top: 10px;
            right: 10px;
            background-color: rgba(0,0,0,0.5);
            color: white;
            width: 30px;
            height: 30px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            z-index: 1001;
            transition: all 0.3s;
        }
        
        .close-button:hover {
            background-color: rgba(255,255,255,0.2);
        }
        
        @media (max-width: 768px) {
            .nav-links {
                display: none;
            }
            
            .materials-grid,
            .documentation-grid,
            .tools-grid {
                padding: 5px;
            }
            
            .material-card,
            .doc-card,
            .tool-card {
                flex: 0 0 260px;
            }
            
            .material-image,
            .doc-image,
            .tool-image {
                height: 140px;
            }
            
            .footer-content {
                flex-direction: column;
                text-align: center;
                gap: 20px;
            }
            
            .social-links {
                justify-content: center;
            }
            
            .card-header {
                padding: 20px;
                margin-bottom: 20px;
            }
            
            .card-title {
                font-size: 1.5rem;
                margin-bottom: 20px;
            }
            
            .card-title i {
                font-size: 1.3rem;
                padding: 10px;
            }
            
            .categories {
                padding: 15px;
            }
            
            .category-buttons {
                flex-direction: column;
            }
            
            .category-btn {
                width: 100%;
                justify-content: center;
            }
        }
        
        /* Eliminar los estilos del debug panel */
        .debug-panel {
            display: none;
        }
        
        .documentation-grid,
        .tools-grid {
            display: flex;
            gap: 20px;
            overflow-x: auto;
            padding: 10px 5px;
            scrollbar-width: thin;
            scrollbar-color: var(--primary) #f0f0f0;
        }
        
        .documentation-grid::-webkit-scrollbar,
        .tools-grid::-webkit-scrollbar {
            height: 8px;
        }
        
        .documentation-grid::-webkit-scrollbar-track,
        .tools-grid::-webkit-scrollbar-track {
            background: #f0f0f0;
            border-radius: 4px;
        }
        
        .documentation-grid::-webkit-scrollbar-thumb,
        .tools-grid::-webkit-scrollbar-thumb {
            background-color: var(--primary);
            border-radius: 4px;
        }
        
        .doc-card,
        .tool-card {
            flex: 0 0 300px;
            background-color: white;
            border-radius: 12px;
            padding: 20px;
            display: flex;
            flex-direction: column;
            gap: 15px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
        }
        
        .doc-card:hover,
        .tool-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
        }
        
        .doc-image,
        .tool-image {
            height: 160px;
            border-radius: 8px;
            overflow: hidden;
        }
        
        .doc-image img,
        .tool-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.5s ease;
        }
        
        .doc-card:hover .doc-image img,
        .tool-card:hover .tool-image img {
            transform: scale(1.05);
        }
    </style>
</head>
<body>
    <header class="header">
        <img src="../../assets/images/logofet.png" alt="FET Logo" class="logo">
        
        <nav class="nav-links">
            <a href="inicio_estudiantes.php">Inicio</a>
            <a href="actividades.php">Actividades</a>
            <a href="aula_virtual.php">Aula Virtual</a>
            <a href="material_apoyo.php" class="active">Material de Apoyo</a>
        </nav>
        
        <div class="user-profile" style="position: relative;">
    <!-- Notificación -->
    <div id="notification-bell" style="position: relative; cursor: pointer;">
        <i class="fas fa-bell notification-icon"></i>
        <?php
        // Notificaciones: actividades pendientes
        try {
            $stmt = $db->prepare("
                SELECT a.titulo, a.fecha_limite
                FROM actividades a
                LEFT JOIN entregas_actividad e ON a.id = e.id_actividad AND e.id_estudiante = :usuario_id
                WHERE e.id IS NULL
                ORDER BY a.fecha_limite ASC
                LIMIT 10
            ");
            $stmt->bindParam(':usuario_id', $usuario_id);
            $stmt->execute();
            $actividades_pendientes = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Crear array de notificaciones
        $notificaciones = [];
        foreach ($actividades_pendientes as $act) {
            $notificaciones[] = [
                'mensaje' => 'Nueva actividad: ' . htmlspecialchars($act['titulo']),
                'fecha' => date('d/m/Y', strtotime($act['fecha_limite']))
            ];
        }
        $num_notificaciones = count($notificaciones);
    } catch (PDOException $e) {
        $notificaciones = [];
        $num_notificaciones = 0;
    }
    ?>
    <?php if ($num_notificaciones > 0): ?>
        <span id="notification-badge" style="
            position: absolute;
            top: -6px; right: -6px;
            background: #dc3545;
            color: #fff;
            border-radius: 50%;
            font-size: 0.75rem;
            width: 20px; height: 20px;
            display: flex; align-items: center; justify-content: center;
            font-weight: bold;
            border: 2px solid #fff;
            z-index: 2;
        "><?php echo $num_notificaciones; ?></span>
    <?php endif; ?>
    <!-- Panel de notificaciones -->
    <div id="notification-panel" style="
        display: none;
        position: absolute;
        right: 0; top: 35px;
        background: #fff;
        color: #343a40;
        min-width: 280px;
        box-shadow: 0 4px 16px rgba(0,0,0,0.15);
        border-radius: 8px;
        z-index: 10;
        overflow: hidden;
    ">
        <div style="padding: 12px 16px; border-bottom: 1px solid #eee; font-weight: bold;">
            Notificaciones
        </div>
        <?php if ($num_notificaciones > 0): ?>
            <ul style="list-style: none; margin: 0; padding: 0; max-height: 250px; overflow-y: auto;">
                <?php foreach ($notificaciones as $n): ?>
                    <li style="padding: 12px 16px; border-bottom: 1px solid #f2f2f2;">
                        <div style="font-size: 0.97em;"><?php echo $n['mensaje']; ?></div>
                        <div style="font-size: 0.8em; color: #888;"><?php echo $n['fecha']; ?></div>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php else: ?>
            <div style="padding: 16px; color: #888;">No tienes notificaciones nuevas.</div>
        <?php endif; ?>
    </div>
</div>
    <!-- Avatar y menú usuario -->
    <div id="avatar-container" style="position: relative; margin-left: 10px; cursor: pointer;">
    <?php if (!empty($_SESSION['usuario']['avatar'])): ?>
        <img src="<?php echo htmlspecialchars($_SESSION['usuario']['avatar']); ?>" alt="Avatar" class="avatar">
    <?php else: ?>
        <span class="avatar" style="
            display: flex; align-items: center; justify-content: center;
            background: #e9ecef; color: #adb5bd; font-size: 1.3rem;
            width: 35px; height: 35px; border-radius: 50%;
        ">
            <i class="fas fa-user"></i>
        </span>
    <?php endif; ?>
    <!-- Menú usuario -->
    <div id="user-menu" style="
        display: none;
        position: absolute;
        right: 0; top: 40px;
        background: #fff;
        color: #343a40;
        min-width: 180px;
        box-shadow: 0 4px 16px rgba(0,0,0,0.15);
        border-radius: 8px;
        z-index: 10;
        overflow: hidden;
    ">
        <div style="padding: 12px 16px; border-bottom: 1px solid #eee;">
            <strong><?php echo htmlspecialchars($_SESSION['usuario']['nombre'] ?? 'Usuario'); ?></strong>
        </div>
        <a href="subir_avatar.php" style="display: block; padding: 12px 16px; color: #343a40; text-decoration: none; border-bottom: 1px solid #eee;">
            <i class="fas fa-upload"></i> Cambiar avatar
        </a>
        <form action="../../views/general/login.php" method="post" style="margin: 0;">
            <button type="submit" style="
                width: 100%; background: #dc3545; color: #fff;
                border: none; padding: 12px 16px; text-align: left;
                font-weight: bold; cursor: pointer;
            ">
                <i class="fas fa-sign-out-alt"></i> Cerrar sesión
            </button>
        </form>
    </div>
</div>
</div>
    </header>
    
    <script>
document.addEventListener('DOMContentLoaded', function() {
    // Notificaciones
    const bell = document.getElementById('notification-bell');
    const panel = document.getElementById('notification-panel');
    bell.addEventListener('click', function(e) {
        e.stopPropagation();
        panel.style.display = (panel.style.display === 'block') ? 'none' : 'block';
        // Oculta el menú usuario si está abierto
        document.getElementById('user-menu').style.display = 'none';
    });
    // Avatar menú
    const avatar = document.getElementById('avatar-container');
    const userMenu = document.getElementById('user-menu');
    avatar.addEventListener('click', function(e) {
        e.stopPropagation();
        userMenu.style.display = (userMenu.style.display === 'block') ? 'none' : 'block';
        // Oculta el panel de notificaciones si está abierto
        panel.style.display = 'none';
    });
    // Cerrar ambos al hacer click fuera
    document.addEventListener('click', function() {
        panel.style.display = 'none';
        userMenu.style.display = 'none';
    });
});
</script>
    
    <main class="main-content">
        <div class="card-header">
            <h2 class="card-title">
                <i class="fas fa-book"></i>
                Material de Apoyo
            </h2>
            
            <div class="categories">
                <h3>Categoría:</h3>
                <div class="category-buttons">
                    <a href="?category=todo" class="category-btn <?php echo $activeCategory === 'todo' ? 'active' : ''; ?>">
                        <i class="fas fa-th-large"></i> Todo
                    </a>
                    <a href="?category=documentation" class="category-btn <?php echo $activeCategory === 'documentation' ? 'active' : ''; ?>">
                        <i class="fas fa-file-alt"></i> Documentación
                    </a>
                    <a href="?category=tools" class="category-btn <?php echo $activeCategory === 'tools' ? 'active' : ''; ?>">
                    <i class="fas fa-tools"></i> Herramientas
                </a>
            </div>
            </div>

            <?php if ($activeCategory === 'todo'): ?>
                <?php if (empty($materiales)): ?>
                    <div class="empty-state">
                        <i class="fas fa-book"></i>
                        <h3>No hay materiales disponibles</h3>
                        <p>Aún no se han publicado materiales.</p>
                    </div>
                <?php else: ?>
            <div class="materials-grid">
                        <?php foreach ($materiales as $material): ?>
                <div class="material-card">
                    <div class="material-image">
                                    <?php if (!empty($material['imagen'])): ?>
                                        <img src="<?php echo htmlspecialchars($material['imagen']); ?>" alt="<?php echo htmlspecialchars($material['titulo']); ?>">
                        <?php else: ?>
                                        <img src="<?php echo getRandomImage(); ?>" alt="Imagen de ingeniería de software">
                                    <?php endif; ?>
                                    <?php if (!empty($material['tipo'])): ?>
                                        <span class="material-type-badge">
                                            <i class="fas fa-tag"></i> <?php echo htmlspecialchars($material['tipo']); ?>
                                        </span>
                        <?php endif; ?>
                    </div>
                                
                                <div class="material-content">
                        <h3 class="material-title"><?php echo htmlspecialchars($material['titulo']); ?></h3>
                                    
                                    <div class="material-meta">
                                        <span><i class="far fa-calendar"></i> <?php echo date('d/m/Y', strtotime($material['fecha_subida'])); ?></span>
                                        <?php if (!empty($material['plataforma'])): ?>
                                            <span><i class="fas fa-globe"></i> <?php echo htmlspecialchars($material['plataforma']); ?></span>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <?php if (!empty($material['descripcion'])): ?>
                        <p class="material-description"><?php echo htmlspecialchars($material['descripcion']); ?></p>
                                    <?php endif; ?>
                                    
                                    <div class="material-actions">
                        <?php if (!empty($material['enlace'])): ?>
                            <a href="<?php echo htmlspecialchars($material['enlace']); ?>" class="material-button" target="_blank">
                                                <i class="fas fa-external-link-alt"></i> Ver
                                            </a>
                                        <?php elseif (!empty($material['archivo'])): ?>
                                            <a href="<?php echo htmlspecialchars($material['archivo']); ?>" class="material-button" target="_blank">
                                                <i class="fas fa-download"></i> Descargar
                                            </a>
                        <?php endif; ?>
                                    </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
            <?php elseif ($activeCategory === 'documentation'): ?>
                <div class="documentation-grid">
                    <?php foreach ($documentation as $doc): ?>
                        <div class="doc-card">
                            <div class="doc-image">
                                <img src="../../assets/images/documentation/<?php echo strtolower(str_replace(' ', '_', $doc['title'])); ?>.jpg" 
                                     alt="<?php echo htmlspecialchars($doc['title']); ?>"
                                     onerror="this.src='<?php echo getRandomImage(); ?>'">
        </div>
                            <div class="doc-content">
                                <h3><?php echo htmlspecialchars($doc['title']); ?></h3>
                                <p class="doc-meta">
                                    <span class="doc-type"><i class="fas fa-file-pdf"></i> <?php echo htmlspecialchars($doc['type']); ?></span>
                                    <span class="doc-size"><i class="fas fa-weight-hanging"></i> <?php echo htmlspecialchars($doc['size']); ?></span>
                                </p>
                                <a href="<?php echo htmlspecialchars($doc['link']); ?>" class="material-button" target="_blank">
                                    <i class="fas fa-download"></i> Descargar
                                </a>
        </div>
    </div>
                    <?php endforeach; ?>
                </div>
            <?php elseif ($activeCategory === 'tools'): ?>
                <div class="tools-grid">
                    <?php foreach ($tools as $tool): ?>
                        <div class="tool-card">
                            <div class="tool-image">
                                <img src="../../assets/images/tools/<?php echo strtolower(str_replace(' ', '_', $tool['name'])); ?>.jpg" 
                                     alt="<?php echo htmlspecialchars($tool['name']); ?>"
                                     onerror="this.src='<?php echo getRandomImage(); ?>'">
                            </div>
                            <div class="tool-content">
                                <h3><?php echo htmlspecialchars($tool['name']); ?></h3>
                                <p><?php echo htmlspecialchars($tool['description']); ?></p>
                                <a href="<?php echo htmlspecialchars($tool['link']); ?>" class="material-button" target="_blank">
                                    <i class="fas fa-download"></i> Descargar
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
</div>
    </main>
    
    <footer class="footer">
        <div class="footer-content">
            <div class="footer-info">
                <h3>Fundación Escuela Tecnológica de Neiva</h3>
                <p>Email: soporte@universidad.edu</p>
                <p>Dirección: Calle 123, Bogotá</p>
                <p>Teléfono: +57 123 456 7890</p>
                
                <div class="social-links">
                    <a href="#"><i class="fab fa-facebook"></i></a>
                    <a href="#"><i class="fab fa-twitter"></i></a>
                    <a href="#"><i class="fab fa-instagram"></i></a>
                    <a href="#"><i class="fab fa-youtube"></i></a>
                </div>
            </div>
            
            <img src="../../assets/images/logofet.png" alt="FET Logo" class="footer-image">
        </div>
    </footer>
</body>
</html>
