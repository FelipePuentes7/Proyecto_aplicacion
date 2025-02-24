<?php
// Aquí iría la lógica PHP para manejar sesiones, obtener datos, etc.
$nombreUsuario = "Admin"; // Ejemplo
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel de Administrador</title>
    <link rel="stylesheet" href="/assets/css/inicio.css">
</head>
<body>
<header>
        <div id="logo" onclick="toggleNav()">Logo</div>
        <nav id="navbar">
            <ul>
                <li><a href="#">Inicio</a></li>
                <li><a href="#">Aprobación de Usuarios</a></li>
                <li><a href="#">Gestión de Usuarios</a></li>
                <li class="dropdown">
                    <a href="#">Gestión de Modalidades de Grado</a>
                    <ul class="dropdown-content">
                        <li><a href="#">Seminario</a></li>
                        <li><a href="#">Proyectos</a></li>
                        <li><a href="#">Pasantías</a></li>
                    </ul>
                </li>
                <li><a href="#">Reportes y Estadísticas</a></li>
                <li><a href="#">Usuario: <?php echo $nombreUsuario; ?></a></li>
                <li><a href="#">Cerrar Sesión</a></li>
            </ul>
        </nav>
    </header>

    <main>
        <section id="resumen-aprobaciones">
            <h2>Usuarios Pendientes de Aprobación</h2>
            <p>5 usuarios pendientes</p>
            <button>Revisar</button>
        </section>

        <section id="accesos-rapidos">
            <div class="tarjeta">
                <h3>Proyectos</h3>
                <button>Acceso Rápido</button>
            </div>
            <div class="tarjeta">
                <h3>Seminario</h3>
                <button>Acceso Rápido</button>
            </div>
            <div class="tarjeta">
                <h3>Pasantías</h3>
                <button>Acceso Rápido</button>
            </div>
        </section>

        <section id="estadisticas">
            <div class="estadistica">
                <h3>Estadística 1</h3>
                <!-- Aquí iría el contenido de la estadística -->
            </div>
            <div class="estadistica">
                <h3>Estadística 2</h3>
                <!-- Aquí iría el contenido de la estadística -->
            </div>
        </section>
    </main>

    <footer>
        <!-- Contenido del footer -->
    </footer>

    <script>
        function toggleNav() {
            var navbar = document.getElementById("navbar");
            navbar.classList.toggle("active");
        }
    </script>
</body>
</html>