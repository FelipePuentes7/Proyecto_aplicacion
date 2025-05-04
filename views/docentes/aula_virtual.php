<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Grabaciones de Clases - FET</title>
    <link rel="stylesheet" href="../../assets/css/aula_virtual.css">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://kit.fontawesome.com/4fac1b523f.js" crossorigin="anonymous"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css" rel="stylesheet"/>
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


    <main class="recordings-container">
        <h1 class="recordings-title">Grabaciones de Clases</h1>
        
        <div class="recordings-filter">
            <span>Fecha:</span>
            <button class="filter-button active">Recientes</button>
            <button class="filter-button">Antiguas</button>
        </div>

        <div class="recordings-grid">
            <div class="recording-card">
                <div class="video-thumbnail">
                    <i class="fas fa-play-circle"></i>
                </div>
                <div class="recording-info">
                    <h3>Título de la clase:</h3>
                    <p>Fecha:</p>
                </div>
            </div>

            <div class="recording-card">
                <div class="video-thumbnail">
                    <i class="fas fa-play-circle"></i>
                </div>
                <div class="recording-info">
                    <h3>Título de la clase:</h3>
                    <p>Fecha:</p>
                </div>
            </div>

            <div class="recording-card">
                <div class="video-thumbnail">
                    <i class="fas fa-play-circle"></i>
                </div>
                <div class="recording-info">
                    <h3>Título de la clase:</h3>
                    <p>Fecha:</p>
                </div>
            </div>

            <div class="recording-card">
                <div class="video-thumbnail">
                    <i class="fas fa-play-circle"></i>
                </div>
                <div class="recording-info">
                    <h3>Título de la clase:</h3>
                    <p>Fecha:</p>
                </div>
            </div>

            <div class="recording-card">
                <div class="video-thumbnail">
                    <i class="fas fa-play-circle"></i>
                </div>
                <div class="recording-info">
                    <h3>Título de la clase:</h3>
                    <p>Fecha:</p>
                </div>
            </div>

            <div class="recording-card">
                <div class="video-thumbnail">
                    <i class="fas fa-play-circle"></i>
                </div>
                <div class="recording-info">
                    <h3>Título de la clase:</h3>
                    <p>Fecha:</p>
                </div>
            </div>
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