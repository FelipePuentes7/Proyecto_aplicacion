<?php
// Aquí iría la lógica PHP para manejar sesiones, obtener datos, etc.
$nombreUsuario = "Admin"; // Ejemplo
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Aprobación de Usuarios</title>
    <link rel="stylesheet" href="/assets/css/aprobacion.css">
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
        <h1>Aprobación de Usuarios</h1>
        
        <div class="controls">
            <div class="filter">
                <select id="filter">
                    <option value="">Filtrar por...</option>
                    <option value="recent">Más reciente</option>
                    <option value="oldest">Más antiguo</option>
                    <option value="az">A-Z</option>
                    <option value="za">Z-A</option>
                    <option value="role">Rol</option>
                </select>
            </div>
            <div class="search">
                <input type="text" placeholder="Buscar por nombre...">
                <button>Buscar</button>
            </div>
        </div>

        <div class="tabs">
            <button id="solicitudesTab" class="active">Solicitudes</button>
            <button id="historialTab">Historial</button>
        </div>

        <form id="solicitudesForm">
            <table id="solicitudesTable" class="user-table">
                <thead>
                    <tr>
                        <th><input type="checkbox" id="selectAll"></th>
                        <th>Nombre completo</th>
                        <th>Tipo de usuario</th>
                        <th>Documento de identidad</th>
                        <th>Código</th>
                        <th>Correo institucional</th>
                        <th>Fecha de solicitud</th>
                        <th>Opción de grado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <!-- Aquí se insertarían las filas de solicitudes dinámicamente -->
                    <tr>
                        <td><input type="checkbox" name="user[]" value="1"></td>
                        <td>Juan Pérez</td>
                        <td>Estudiante</td>
                        <td>1234567890</td>
                        <td>EST001</td>
                        <td>juan.perez@universidad.edu</td>
                        <td>2023-05-15</td>
                        <td>Proyecto</td>
                        <td>
                            <button type="button" class="aprobar">✔ Aprobar</button>
                            <button type="button" class="rechazar">❌ Rechazar</button>
                        </td>
                    </tr>
                    <!-- Más filas... -->
                </tbody>
            </table>
            <div class="bulk-actions">
                <button type="button" id="bulkAprobar" style="display:none;">Aprobar seleccionados</button>
                <button type="button" id="bulkRechazar" style="display:none;">Rechazar seleccionados</button>
            </div>
        </form>

        <table id="historialTable" class="user-table" style="display:none;">
            <thead>
                <tr>
                    <th>Nombre completo</th>
                    <th>Tipo de usuario</th>
                    <th>Documento de identidad</th>
                    <th>Código</th>
                    <th>Correo institucional</th>
                    <th>Fecha de solicitud</th>
                    <th>Opción de grado</th>
                    <th>Estado</th>
                </tr>
            </thead>
            <tbody>
                <!-- Aquí se insertarían las filas del historial dinámicamente -->
                <tr>
                    <td>María González</td>
                    <td>Tutor</td>
                    <td>0987654321</td>
                    <td>TUT001</td>
                    <td>maria.gonzalez@universidad.edu</td>
                    <td>2023-05-10</td>
                    <td>Seminario</td>
                    <td>Aprobado</td>
                </tr>
                <!-- Más filas... -->
            </tbody>
        </table>
    </main>

    <footer>
        <!-- Contenido del footer -->
        <p>&copy; 2023 Sistema de Gestión Académica. Todos los derechos reservados.</p>
    </footer>

    <script>
        function toggleNav() {
            var navbar = document.getElementById("navbar");
            navbar.classList.toggle("active");
        }

        document.getElementById('solicitudesTab').addEventListener('click', function() {
            document.getElementById('solicitudesTable').style.display = 'table';
            document.getElementById('historialTable').style.display = 'none';
            this.classList.add('active');
            document.getElementById('historialTab').classList.remove('active');
        });

        document.getElementById('historialTab').addEventListener('click', function() {
            document.getElementById('historialTable').style.display = 'table';
            document.getElementById('solicitudesTable').style.display = 'none';
            this.classList.add('active');
            document.getElementById('solicitudesTab').classList.remove('active');
        });

        document.getElementById('selectAll').addEventListener('change', function() {
            var checkboxes = document.querySelectorAll('input[name="user[]"]');
            for (var checkbox of checkboxes) {
                checkbox.checked = this.checked;
            }
            toggleBulkActions();
        });

        document.querySelectorAll('input[name="user[]"]').forEach(function(checkbox) {
            checkbox.addEventListener('change', toggleBulkActions);
        });

        function toggleBulkActions() {
            var checkedBoxes = document.querySelectorAll('input[name="user[]"]:checked');
            var bulkAprobar = document.getElementById('bulkAprobar');
            var bulkRechazar = document.getElementById('bulkRechazar');
            if (checkedBoxes.length >= 2) {
                bulkAprobar.style.display = 'inline-block';
                bulkRechazar.style.display = 'inline-block';
            } else {
                bulkAprobar.style.display = 'none';
                bulkRechazar.style.display = 'none';
            }
        }
    </script>
</body>
</html>