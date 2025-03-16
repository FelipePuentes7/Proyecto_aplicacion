<?php
session_start();
require_once __DIR__ . '/../../config/conexion.php';

$nombreUsuario = $_SESSION['nombreUsuario'] ?? 'Invitado';

// Manejo de aprobación/rechazo
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['aprobar']) || isset($_POST['rechazar'])) {
        $idSolicitud = $_POST['user_id'] ?? null;
        
        if ($idSolicitud) {
            try {
                // Obtener datos de la solicitud
                $stmt = $conexion->prepare("SELECT * FROM solicitudes_registro WHERE id = ?");
                $stmt->execute([$idSolicitud]);
                $solicitud = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($solicitud) {
                    if (isset($_POST['aprobar'])) {
                        // Insertar en usuarios - Ahora incluye nombre_proyecto y nombre_empresa
                        $sqlUsuario = "INSERT INTO usuarios (
                            nombre, email, password, rol, documento, 
                            codigo_estudiante, telefono, opcion_grado, nombre_proyecto, nombre_empresa, ciclo, estado
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'activo')";
                        
                        $stmtUsuario = $conexion->prepare($sqlUsuario);
                        $stmtUsuario->execute([
                            $solicitud['nombre'],
                            $solicitud['email'],
                            $solicitud['password'],
                            $solicitud['rol'],
                            $solicitud['documento'],
                            $solicitud['codigo_estudiante'],
                            $solicitud['telefono'],
                            $solicitud['opcion_grado'],
                            $solicitud['nombre_proyecto'],
                            $solicitud['nombre_empresa'],
                            $solicitud['ciclo']
                        ]);
                        
                        $nuevoUsuarioId = $conexion->lastInsertId();

                        // Registrar en historial - Ahora incluye nombre_proyecto y nombre_empresa
                        $sqlHistorial = "INSERT INTO historial_solicitudes (
                            usuario_id, nombre, email, rol, documento, 
                            codigo_estudiante, telefono, opcion_grado, nombre_proyecto, nombre_empresa, ciclo, estado
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'aprobado')";
                        
                        $stmtHistorial = $conexion->prepare($sqlHistorial);
                        $stmtHistorial->execute([
                            $nuevoUsuarioId,
                            $solicitud['nombre'],
                            $solicitud['email'],
                            $solicitud['rol'],
                            $solicitud['documento'],
                            $solicitud['codigo_estudiante'],
                            $solicitud['telefono'],
                            $solicitud['opcion_grado'],
                            $solicitud['nombre_proyecto'],
                            $solicitud['nombre_empresa'],
                            $solicitud['ciclo']
                        ]);

                    } elseif (isset($_POST['rechazar'])) {
                        // Registrar en historial sin usuario_id - Ahora incluye nombre_proyecto y nombre_empresa
                        $sqlHistorial = "INSERT INTO historial_solicitudes (
                            nombre, email, rol, documento, 
                            codigo_estudiante, telefono, opcion_grado, nombre_proyecto, nombre_empresa, ciclo, estado
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'rechazado')";
                        
                        $stmtHistorial = $conexion->prepare($sqlHistorial);
                        $stmtHistorial->execute([
                            $solicitud['nombre'],
                            $solicitud['email'],
                            $solicitud['rol'],
                            $solicitud['documento'],
                            $solicitud['codigo_estudiante'],
                            $solicitud['telefono'],
                            $solicitud['opcion_grado'],
                            $solicitud['nombre_proyecto'],
                            $solicitud['nombre_empresa'],
                            $solicitud['ciclo']
                        ]);
                    }

                    // Eliminar solicitud
                    $conexion->prepare("DELETE FROM solicitudes_registro WHERE id = ?")
                             ->execute([$idSolicitud]);
                }
            } catch (PDOException $e) {
                error_log("Error en la operación: " . $e->getMessage());
            }
        }
    }
}

// Obtener datos
$solicitudes = $conexion->query("SELECT * FROM solicitudes_registro")->fetchAll();
$historial = $conexion->query("SELECT * FROM historial_solicitudes")->fetchAll();
$conexion = null;
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
                <li><a href="#">Usuario: <?php echo htmlspecialchars($nombreUsuario); ?></a></li>
                <li><a href="#">Cerrar Sesión</a></li>
            </ul>
        </nav>
    </header>

    <main>
        <h1>Aprobación de Usuarios</h1>
        
        <div class="filter">
    <select id="filter">
        <option value="">Filtrar por...</option>
        <option value="recent">Más reciente</option>
        <option value="oldest">Más antiguo</option>
        <option value="az">A-Z</option>
        <option value="za">Z-A</option>
        <option value="tutores">Tutores</option>
        <option value="estudiantes">Estudiantes</option>
    </select>
        </div>
            <div class="search">
                <input type="text" id="searchInput" placeholder="Buscar por nombre...">
                <button id="searchButton">Buscar</button>
            </div>
        

        <div class="tabs">
            <button id="solicitudesTab" class="active">Solicitudes</button>
            <button id="historialTab">Historial</button>
        </div>

        <table id="solicitudesTable" class="user-table">
            <thead>
                <tr>
                    <th>Nombre completo</th>
                    <th>Tipo de usuario</th>
                    <th>Documento</th>
                    <th>Código</th>
                    <th>Correo</th>
                    <th>Fecha solicitud</th>
                    <th>Opción grado</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($solicitudes as $solicitud): ?>
                <tr>
                    <td><?= htmlspecialchars($solicitud['nombre']) ?></td>
                    <td><?= htmlspecialchars($solicitud['rol']) ?></td>
                    <td><?= htmlspecialchars($solicitud['documento']) ?></td>
                    <td><?= htmlspecialchars($solicitud['codigo_estudiante']) ?></td>
                    <td><?= htmlspecialchars($solicitud['email']) ?></td>
                    <td><?= htmlspecialchars($solicitud['fecha_solicitud']) ?></td>
                    <td><?= htmlspecialchars($solicitud['opcion_grado']) ?></td>
                    <td>
                        <form method="POST">
                            <input type="hidden" name="user_id" value="<?= $solicitud['id'] ?>">
                            <button type="submit" name="aprobar" class="aprobar">✔ Aprobar</button>
                            <button type="submit" name="rechazar" class="rechazar">❌ Rechazar</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <!-- Tabla de historial -->
        <table id="historialTable" class="user-table" style="display:none;">
            <thead>
                <tr>
                    <th>Nombre</th>
                    <th>Rol</th>
                    <th>Documento</th>
                    <th>Código</th>
                    <th>Correo</th>
                    <th>Fecha</th>
                    <th>Opción</th>
                    <th>Estado</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($historial as $registro): ?>
                <tr>
                    <td><?= htmlspecialchars($registro['nombre']) ?></td>
                    <td><?= htmlspecialchars($registro['rol']) ?></td>
                    <td><?= htmlspecialchars($registro['documento']) ?></td>
                    <td><?= htmlspecialchars($registro['codigo_estudiante']) ?></td>
                    <td><?= htmlspecialchars($registro['email']) ?></td>
                    <td><?= htmlspecialchars($registro['fecha_registro']) ?></td>
                    <td><?= htmlspecialchars($registro['opcion_grado']) ?></td>
                    <td><?= $registro['estado'] === 'aprobado' ? '✅ Aprobado' : '❌ Rechazado' ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </main>

    <footer>
        <p>&copy; 2023 Sistema de Gestión Académica. Todos los derechos reservados.</p>
    </footer>

    <script>
        
    function toggleNav() {
        var navbar = document.getElementById("navbar");
        navbar.classList.toggle("active");
    }

    // Elementos clave
    const solicitudesTab = document.getElementById('solicitudesTab');
    const historialTab = document.getElementById('historialTab');
    const tables = {
        solicitudes: document.getElementById('solicitudesTable'),
        historial: document.getElementById('historialTable')
    };
    const searchInput = document.getElementById('searchInput');
    const filterSelect = document.getElementById('filter');

    // Inicializar al cargar
    document.addEventListener('DOMContentLoaded', function() {
        aplicarFiltrosYBusqueda(); // Ejecutar inmediatamente al cargar
    });

    // Manejo de pestañas
    solicitudesTab.addEventListener('click', () => {
        tables.solicitudes.style.display = 'table';
        tables.historial.style.display = 'none';
        solicitudesTab.classList.add('active');
        historialTab.classList.remove('active');
        aplicarFiltrosYBusqueda();
    });

    historialTab.addEventListener('click', () => {
        tables.historial.style.display = 'table';
        tables.solicitudes.style.display = 'none';
        historialTab.classList.add('active');
        solicitudesTab.classList.remove('active');
        aplicarFiltrosYBusqueda();
    });

    // Eventos comunes
    searchInput.addEventListener('input', aplicarFiltrosYBusqueda);
    filterSelect.addEventListener('change', aplicarFiltrosYBusqueda);

    // Función principal mejorada
    function aplicarFiltrosYBusqueda() {
        const activeTable = solicitudesTab.classList.contains('active') ? tables.solicitudes : tables.historial;
        const esHistorial = activeTable === tables.historial;
        
        procesarTabla(activeTable, esHistorial);
    }

    function procesarTabla(tabla, esHistorial) {
        const searchTerm = searchInput.value.toLowerCase();
        const filterValue = filterSelect.value;
        const rows = tabla.tBodies[0].rows;

        Array.from(rows).forEach(row => {
            const cells = row.cells;
            const nombre = cells[0].textContent.toLowerCase();
            const rol = cells[1].textContent.toLowerCase();
            const fecha = esHistorial ? cells[5].textContent : cells[5].textContent; // Ajustar índices
            const email = cells[4].textContent.toLowerCase();

            // Búsqueda
            const matchSearch = nombre.includes(searchTerm) || email.includes(searchTerm);
            
            // Filtrado
            let matchFilter = true;
            switch(filterValue) {
    case 'recent':
    case 'oldest':
        // Se manejará en la ordenación
        break;
    case 'az':
    case 'za':
        // Se manejará en la ordenación
        break;
    case 'tutores':
        matchFilter = rol === 'tutor'; // Asegúrate que coincida con el valor en tu BD
        break;
    case 'estudiantes':
        matchFilter = rol === 'estudiante'; // Asegúrate que coincida con el valor en tu BD
        break;
}

            row.style.display = (matchSearch && matchFilter) ? '' : 'none';
        });

        // Ordenación
        ordenarTabla(tabla, esHistorial);
    }

    function ordenarTabla(tabla, esHistorial) {
        const tbody = tabla.tBodies[0];
        const rows = Array.from(tbody.rows);
        const filterValue = filterSelect.value;

        rows.sort((a, b) => {
            const index = obtenerIndiceOrdenacion(esHistorial, filterValue);
            const cellA = a.cells[index];
            const cellB = b.cells[index];
            
            if (['recent', 'oldest'].includes(filterValue)) {
                const dateA = new Date(cellA.textContent);
                const dateB = new Date(cellB.textContent);
                return filterValue === 'recent' ? dateB - dateA : dateA - dateB;
            } else {
                const textA = cellA.textContent.toLowerCase();
                const textB = cellB.textContent.toLowerCase();
                return filterValue === 'az' ? 
                    textA.localeCompare(textB) : 
                    textB.localeCompare(textA);
            }
        });

        // Limpiar y reinsertar filas ordenadas
        while (tbody.firstChild) tbody.removeChild(tbody.firstChild);
        rows.forEach(row => tbody.appendChild(row));
    }

    function obtenerIndiceOrdenacion(esHistorial, filterValue) {
        if (['recent', 'oldest'].includes(filterValue)) {
            return esHistorial ? 5 : 5; // Índice de fecha en cada tabla
        }
        return 0; // Índice de nombre para ordenación A-Z/Z-A
    }

    // Confirmación para rechazos
    document.querySelectorAll('form').forEach(form => {
        form.addEventListener('submit', (e) => {
            const boton = e.submitter;
            if (boton.name === 'rechazar' && !confirm('¿Estás seguro de rechazar esta solicitud?')) {
                e.preventDefault();
            }
        });
    });
</script>
</body>
</html>