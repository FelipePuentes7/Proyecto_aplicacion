<?php
$categories = ['videos', 'documentation', 'tools', 'motivation'];
$activeCategory = isset($_GET['category']) ? $_GET['category'] : 'videos';

$courses = [
    [
        'title' => 'Introduccion a HTML y CSS',
        'image' => 'https://images.unsplash.com/photo-1621839673705-6617adf9e890?w=500',
        'category' => 'videos'
    ],
    [
        'title' => 'Introduccion a PHP',
        'image' => 'https://images.unsplash.com/photo-1599507593499-a3f7d7d97667?w=500',
        'category' => 'videos'
    ],
    [
        'title' => 'JavaScript Esencial',
        'image' => 'https://images.unsplash.com/photo-1579468118864-1b9ea3c0db4a?w=500',
        'category' => 'videos'
    ],
    [
        'title' => 'MySQL Basico',
        'image' => 'https://images.unsplash.com/photo-1544383835-bda2bc66a55d?w=500',
        'category' => 'videos'
    ]
];

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

$motivationalVideos = [
    [
        'title' => 'El camino del programador',
        'thumbnail' => 'https://images.unsplash.com/photo-1516321497487-e288fb19713f?w=500'
    ],
    [
        'title' => 'Nunca es tarde para programar',
        'thumbnail' => 'https://images.unsplash.com/photo-1498050108023-c5249f4df085?w=500'
    ]
];
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FET - Material de Apoyo</title>
    <link rel="stylesheet" href="../../assets/css/material_apoyo.css">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://kit.fontawesome.com/4fac1b523f.js" crossorigin="anonymous"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>

<body>

<nav class="navbar">
        <!-- Espacio para el logo -->
        <div class="logo">
            <img src="/assets/images/logofet.png" alt="Logo FET" style="max-height: 50px;">
        </div>

        <!-- Links de navegación -->
        <div class="nav-links">
            <a href="/views/estudiantes/Inicio_Estudiante.php">Inicio</a>
            <a href="#">Material de Apoyo</a>
            <a href="#">Tutorías</a>
        </div>

        <!-- Acciones del usuario -->
        <div class="user-actions">
            <div class="notifications">
                <i class="fa-solid fa-bell"></i> <!-- Ícono de notificaciones -->
            </div>
            <div class="dropdown">
                <i class="fa-solid fa-user"></i> <!-- Ícono de perfil -->
                <div class="dropdown-content">
                    <a href="/views/general/login.php" class="logout-btn">
                        <i class="fa-solid fa-sign-out-alt"></i> Cerrar Sesión
                    </a>
                </div>
            </div>
        </div>
</nav>

    <main class="container">
        <div class="categories">
            <h2>Categoría:</h2>
            <div class="category-buttons">
                <a href="?category=videos" class="category-btn <?php echo $activeCategory === 'videos' ? 'active' : ''; ?>">
                    <i class="fas fa-video"></i> Videos
                </a>
                <a href="?category=documentation" class="category-btn <?php echo $activeCategory === 'documentation' ? 'active' : ''; ?>">
                    <i class="fas fa-file-alt"></i> Documentación
                </a>
                <a href="?category=tools" class="category-btn <?php echo $activeCategory === 'tools' ? 'active' : ''; ?>">
                    <i class="fas fa-tools"></i> Herramientas
                </a>
                <a href="?category=motivation" class="category-btn <?php echo $activeCategory === 'motivation' ? 'active' : ''; ?>">
                    <i class="fas fa-fire"></i> Motivación
                </a>
            </div>
        </div>

        <div class="content">
            <?php if ($activeCategory === 'videos'): ?>
                <div class="courses-grid">
                    <?php foreach ($courses as $course): ?>
                        <div class="course-card">
                            <img src="<?php echo $course['image']; ?>" alt="<?php echo $course['title']; ?>">
                            <div class="course-info">
                                <h3><?php echo $course['title']; ?></h3>
                                <button class="btn">Ver curso</button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php elseif ($activeCategory === 'documentation'): ?>
                <div class="documentation">
                    <h2>Documentación</h2>
                    <div class="docs-grid">
                        <div class="doc-card">
                            <h3>Manual de HTML</h3>
                            <p>Documento PDF - 2.5MB</p>
                        </div>
                        <div class="doc-card">
                            <h3>Guía de PHP</h3>
                            <p>Documento PDF - 3.1MB</p>
                        </div>
                    </div>
                </div>
            <?php elseif ($activeCategory === 'tools'): ?>
                <div class="tools-grid">
                    <?php foreach ($tools as $tool): ?>
                        <div class="tool-card">
                            <h3><?php echo $tool['name']; ?></h3>
                            <p><?php echo $tool['description']; ?></p>
                            <a href="<?php echo $tool['link']; ?>" class="btn" target="_blank">Descargar</a>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php elseif ($activeCategory === 'motivation'): ?>
                <div class="motivation-grid">
                    <?php foreach ($motivationalVideos as $video): ?>
                        <div class="motivation-card">
                            <img src="<?php echo $video['thumbnail']; ?>" alt="<?php echo $video['title']; ?>">
                            <div class="video-info">
                                <h3><?php echo $video['title']; ?></h3>
                                <button class="btn btn-red">Ver video</button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <footer class="footer">
        <!-- Sección de información -->
        <div class="info-section">
            <!-- Links -->
            <div class="links">
                <p class="title_footer"><strong>Fundación Escuela Tecnológica de Neiva</strong></p>
                <a href="#">Inicio</a>
                <a href="#">Subir Avances</a>
                <a href="#">Historial de Avances</a>
                <a href="#">Material de Apoyo</a>
            </div>

            <!-- Información de contacto -->
            <div class="contact">
                <p>Email: soporte@universidad.edu</p>
                <p>Dirección: Calle 123, Bogotá</p>
                <p>Teléfono: +57 123 456 7890</p>
            </div>

            <!-- Espacio para la imagen -->
            <div class="image">
                <img src="/assets/images/image.png" alt="Imagen del footer" style="max-width: 50%;">
            </div>
        </div>

        <!-- Sección de redes sociales -->
        <div class="social-section">
            <!-- Redes sociales -->
            <div class="social-links">
                <a href="https://es-es.facebook.com/" target="_blank"><i class="fa-brands fa-facebook"></i></a>
                <a href="https://twitter.com/?lang=es" target="_blank"><i class="fa-brands fa-x-twitter"></i></a>
                <a href="https://www.instagram.com/" target="_blank"><i class="fa-brands fa-square-instagram"></i></a>
            </div>

            <!-- Logo del medio -->
            <div class="logo">
                <img src="/assets/images/logo_footer.png" alt="Footer FET" style="max-height: 50px;">
            </div>
        </div>
    </footer>


</body>

</html>
