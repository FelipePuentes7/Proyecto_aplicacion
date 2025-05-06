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
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
<header class="bg-green-600 text-white p-4 flex justify-between items-center">
    <div class="flex items-center">
        <img alt="FET Logo" src="../../assets/img/logofet.png" class="h-12" height="100" width="100"/>
    </div>
    <nav class="flex space-x-4">
        <a class="hover:underline" href="#">Inicio</a>
        <a class="hover:underline" href="actividades.php">Actividades</a>
        <a class="hover:underline" href="#">Aula Virtual</a>
        <a class="hover:underline" href="#">Material de Apoyo</a>
    </nav>
    <div class="flex items-center space-x-4">
        <i class="fas fa-bell"></i>
        <img alt="User Avatar" class="h-10 w-10 rounded-full" height="40"
             src="https://storage.googleapis.com/a1aa/image/rGwRP1XDqncVs5Qe91jniYR4E9ipWdbNPtR2FDUEMUE.jpg"
             width="40"/>
    </div>
</header>

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

    <footer class="bg-green-600 text-white p-4 mt-8 ">
    <div class="flex flex-col md:flex-row justify-between items-center">
        <div class="text-center md:text-left">
            <h4 class="font-bold">Fundacion Escuela Tecnologica de Neiva</h4>
            <p>Email: soporte@universidad.edu</p>
            <p>Dirección: Calle 123, Bogotá</p>
            <p>Teléfono: +57 123 456 7890</p>
        </div>
        <img alt="Promotional Image" class="h-24 mt-4 md:mt-0" height="100" src="../../assets/img/image.png"
             width="150"/>
    </div>
    <div class="flex justify-center space-x-4 mt-4">
        <a href="#"><i class="fab fa-facebook-f"></i></a>
        <a href="#"><i class="fab fa-twitter"></i></a>
        <a href="#"><i class="fab fa-instagram"></i></a>
        <a href="#"><i class="fab fa-tiktok"></i></a>
    </div>
    <div class="text-center mt-4">
        <p class="font-bold">FET</p>
    </div>
</footer>
</body>
</html>