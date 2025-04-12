<?php
session_start();
// Activar la visualización de errores para depuración
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Cargar SDK de Twilio
require_once __DIR__ . '/../../vendor/autoload.php'; // Ajusta según tu estructura de carpetas
use Twilio\Rest\Client;

require_once __DIR__ . '/../../config/conexion.php';

$twilioConfig = require_once __DIR__ . '/../../config/twilio_config.php';

$nombreUsuario = $_SESSION['nombreUsuario'] ?? 'Invitado';
$mensaje = ''; // Variable para mensajes de estado

// Función para manejar valores nulos en htmlspecialchars
function htmlSafe($str) {
    return $str !== null ? htmlspecialchars($str, ENT_QUOTES, 'UTF-8') : '';
}

// Manejo de aprobación/rechazo
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Depuración: Registrar los datos POST recibidos
    error_log("Datos POST recibidos: " . print_r($_POST, true));
    
    if (isset($_POST['aprobar']) || isset($_POST['rechazar'])) {
        $idSolicitud = $_POST['user_id'] ?? null;
        
        if ($idSolicitud) {
            try {
                // Obtener datos de la solicitud
                $stmt = $conexion->prepare("SELECT * FROM solicitudes_registro WHERE id = ?");
                $stmt->execute([$idSolicitud]);
                $solicitud = $stmt->fetch(PDO::FETCH_ASSOC);
                
                // Depuración: Verificar si se encontró la solicitud
                if (!$solicitud) {
                    error_log("No se encontró la solicitud con ID: $idSolicitud");
                    $mensaje = "Error: No se encontró la solicitud.";
                } else {
                    error_log("Solicitud encontrada: " . print_r($solicitud, true));
                    
                    if (isset($_POST['aprobar'])) {
                        // Insertar en usuarios
                        $sqlUsuario = "INSERT INTO usuarios (
                            nombre, email, password, rol, documento, 
                            codigo_estudiante, telefono, opcion_grado, nombre_proyecto, nombre_empresa, ciclo, estado
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'activo')";
                        
                        $stmtUsuario = $conexion->prepare($sqlUsuario);
                        $resultado = $stmtUsuario->execute([
                            $solicitud['nombre'],
                            $solicitud['email'],
                            $solicitud['password'],
                            $solicitud['rol'],
                            $solicitud['documento'],
                            $solicitud['codigo_estudiante'] ?? null,
                            $solicitud['telefono'] ?? null,
                            $solicitud['opcion_grado'] ?? null,
                            $solicitud['nombre_proyecto'] ?? null,
                            $solicitud['nombre_empresa'] ?? null,
                            $solicitud['ciclo'] ?? null
                        ]);
                        
                        // Depuración: Verificar si se insertó el usuario
                        if (!$resultado) {
                            error_log("Error al insertar usuario: " . print_r($stmtUsuario->errorInfo(), true));
                            $mensaje = "Error al insertar usuario en la base de datos.";
                        } else {
                            $nuevoUsuarioId = $conexion->lastInsertId();
                            error_log("Usuario insertado con ID: $nuevoUsuarioId");
                            
                            // ============ ENVÍO DE WHATSAPP ============
                            try {
                                // Usar configuración desde archivo externo
                                $twilioSid = $twilioConfig['account_sid'];
                                $twilioToken = $twilioConfig['auth_token'];
                                $twilioFrom = $twilioConfig['whatsapp_from'];
                                
                                // Formatear teléfono
                                $telefono = $solicitud['telefono'] ?? '';
                                if ($telefono && !str_starts_with($telefono, '+')) {
                                    $telefono = preg_replace('/^\+/', '', $telefono); // Eliminar + existente
                                    $telefono = '+57' . ltrim($telefono, '0'); 
                                }
                            
                                if ($telefono) {
                                    $client = new Client($twilioSid, $twilioToken);
                                    
                                    $message = $client->messages->create(
                                        "whatsapp:$telefono", 
                                        [
                                            'from' => $twilioFrom,
                                            'body' => "¡Hola {$solicitud['nombre']}! 🎉\nTu cuenta en la plataforma Mision FET fue aprobada.\nUsuario: {$solicitud['email']}"
                                        ]
                                    );
                                    
                                    error_log("[WhatsApp] Mensaje enviado a $telefono");
                                }
                            } catch (Exception $e) {
                                error_log("[WhatsApp] Error: " . $e->getMessage());
                                // No interrumpimos el proceso si falla el envío de WhatsApp
                            }
                            // ==========================================

                            // Insertar en historial
                            $sqlHistorial = "INSERT INTO historial_solicitudes (
                                usuario_id, nombre, email, rol, documento, 
                                codigo_estudiante, telefono, opcion_grado, nombre_proyecto, nombre_empresa, ciclo, estado
                            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'aprobado')";
                            
                            $stmtHistorial = $conexion->prepare($sqlHistorial);
                            $resultadoHistorial = $stmtHistorial->execute([
                                $nuevoUsuarioId,
                                $solicitud['nombre'],
                                $solicitud['email'],
                                $solicitud['rol'],
                                $solicitud['documento'],
                                $solicitud['codigo_estudiante'] ?? null,
                                $solicitud['telefono'] ?? null,
                                $solicitud['opcion_grado'] ?? null,
                                $solicitud['nombre_proyecto'] ?? null,
                                $solicitud['nombre_empresa'] ?? null,
                                $solicitud['ciclo'] ?? null
                            ]);
                            
                            // Depuración: Verificar si se insertó en el historial
                            if (!$resultadoHistorial) {
                                error_log("Error al insertar en historial: " . print_r($stmtHistorial->errorInfo(), true));
                                $mensaje = "Usuario aprobado pero hubo un error al registrar en el historial.";
                            } else {
                                error_log("Registro insertado en historial");
                                $mensaje = "Usuario aprobado correctamente.";
                            }
                        }
                    } elseif (isset($_POST['rechazar'])) {
                        $sqlHistorial = "INSERT INTO historial_solicitudes (
                            nombre, email, rol, documento, 
                            codigo_estudiante, telefono, opcion_grado, nombre_proyecto, nombre_empresa, ciclo, estado
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'rechazado')";
                        
                        $stmtHistorial = $conexion->prepare($sqlHistorial);
                        $resultadoHistorial = $stmtHistorial->execute([
                            $solicitud['nombre'],
                            $solicitud['email'],
                            $solicitud['rol'],
                            $solicitud['documento'],
                            $solicitud['codigo_estudiante'] ?? null,
                            $solicitud['telefono'] ?? null,
                            $solicitud['opcion_grado'] ?? null,
                            $solicitud['nombre_proyecto'] ?? null,
                            $solicitud['nombre_empresa'] ?? null,
                            $solicitud['ciclo'] ?? null
                        ]);
                        
                        // Depuración: Verificar si se insertó en el historial
                        if (!$resultadoHistorial) {
                            error_log("Error al insertar rechazo en historial: " . print_r($stmtHistorial->errorInfo(), true));
                            $mensaje = "Error al registrar el rechazo en el historial.";
                        } else {
                            error_log("Rechazo registrado en historial");
                            $mensaje = "Usuario rechazado correctamente.";
                        }
                    }
                    
                    // Eliminar solicitud después de procesarla (aprobada o rechazada)
                    $stmtDelete = $conexion->prepare("DELETE FROM solicitudes_registro WHERE id = ?");
                    $resultadoDelete = $stmtDelete->execute([$idSolicitud]);
                    
                    // Depuración: Verificar si se eliminó la solicitud
                    if (!$resultadoDelete) {
                        error_log("Error al eliminar solicitud: " . print_r($stmtDelete->errorInfo(), true));
                        $mensaje .= " Error al eliminar la solicitud original.";
                    } else {
                        error_log("Solicitud eliminada correctamente");
                    }
                }
            } catch (PDOException $e) {
                error_log("Error en la operación: " . $e->getMessage());
                $mensaje = "Error en la base de datos: " . $e->getMessage();
            }
        } else {
            error_log("ID de solicitud no proporcionado");
            $mensaje = "Error: ID de solicitud no proporcionado.";
        }
    }
    
    // Redirigir para evitar reenvío del formulario
    header("Location: " . $_SERVER['PHP_SELF'] . ($mensaje ? "?mensaje=" . urlencode($mensaje) : ""));
    exit;
}

// Recuperar mensaje de la URL si existe
if (isset($_GET['mensaje'])) {
    $mensaje = $_GET['mensaje'];
}

// Obtener datos
try {
    $solicitudes = $conexion->query("SELECT * FROM solicitudes_registro")->fetchAll();
    $historial = $conexion->query("SELECT * FROM historial_solicitudes")->fetchAll();
} catch (PDOException $e) {
    error_log("Error al obtener datos: " . $e->getMessage());
    $mensaje = "Error al cargar los datos: " . $e->getMessage();
    $solicitudes = [];
    $historial = [];
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Aprobación de Usuarios</title>
    <link rel="stylesheet" href="/assets/css/aprobacion.css">
    <style>
        .mensaje {
            padding: 10px;
            margin: 10px 0;
            border-radius: 5px;
            text-align: center;
        }
        .mensaje.exito {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .mensaje.error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
    </style>
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
                <li><a href="#">Usuario: <?php echo htmlSafe($nombreUsuario); ?></a></li>
                <li><a href="#">Cerrar Sesión</a></li>
            </ul>
        </nav>
    </header>

    <main>
        <h1>Aprobación de Usuarios</h1>
        
        <?php if (!empty($mensaje)): ?>
            <div class="mensaje <?php echo strpos($mensaje, 'Error') !== false ? 'error' : 'exito'; ?>">
                <?php echo htmlSafe($mensaje); ?>
            </div>
        <?php endif; ?>
        
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
                <?php if (empty($solicitudes)): ?>
                    <tr>
                        <td colspan="8" style="text-align: center;">No hay solicitudes pendientes</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($solicitudes as $solicitud): ?>
                    <tr>
                        <td><?= htmlSafe($solicitud['nombre']) ?></td>
                        <td><?= htmlSafe($solicitud['rol']) ?></td>
                        <td><?= htmlSafe($solicitud['documento']) ?></td>
                        <td><?= htmlSafe($solicitud['codigo_estudiante']) ?></td>
                        <td><?= htmlSafe($solicitud['email']) ?></td>
                        <td><?= htmlSafe($solicitud['fecha_solicitud']) ?></td>
                        <td><?= htmlSafe($solicitud['opcion_grado']) ?></td>
                        <td>
                            <form method="POST" action="<?php echo htmlSafe($_SERVER['PHP_SELF']); ?>">
                                <input type="hidden" name="user_id" value="<?= $solicitud['id'] ?>">
                                <button type="submit" name="aprobar" value="1" class="aprobar">✔ Aprobar</button>
                                <button type="submit" name="rechazar" value="1" class="rechazar">❌ Rechazar</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
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
                <?php if (empty($historial)): ?>
                    <tr>
                        <td colspan="8" style="text-align: center;">No hay registros en el historial</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($historial as $registro): ?>
                    <tr>
                        <td><?= htmlSafe($registro['nombre']) ?></td>
                        <td><?= htmlSafe($registro['rol']) ?></td>
                        <td><?= htmlSafe($registro['documento']) ?></td>
                        <td><?= htmlSafe($registro['codigo_estudiante']) ?></td>
                        <td><?= htmlSafe($registro['email']) ?></td>
                        <td><?= htmlSafe($registro['fecha_registro']) ?></td>
                        <td><?= htmlSafe($registro['opcion_grado']) ?></td>
                        <td><?= $registro['estado'] === 'aprobado' ? '✅ Aprobado' : '❌ Rechazado' ?></td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </main>

    <footer>
        <p>&copy; 2025 Sistema de Gestión Académica. Todos los derechos reservados.</p>
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
            
            // Mostrar la pestaña correcta si hay un parámetro en la URL
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.has('tab') && urlParams.get('tab') === 'historial') {
                historialTab.click();
            }
            
            // Ocultar mensaje después de 5 segundos
            const mensajeElement = document.querySelector('.mensaje');
            if (mensajeElement) {
                setTimeout(() => {
                    mensajeElement.style.display = 'none';
                }, 5000);
            }
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
            
            // Si no hay filas o solo hay una fila con mensaje "No hay solicitudes/registros", salir
            if (rows.length === 0 || (rows.length === 1 && rows[0].cells.length === 1)) {
                return;
            }

            Array.from(rows).forEach(row => {
                // Verificar si es una fila de mensaje
                if (row.cells.length === 1) return;
                
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
                        matchFilter = rol.includes('tutor'); // Más flexible para coincidir
                        break;
                    case 'estudiantes':
                        matchFilter = rol.includes('estudiante'); // Más flexible para coincidir
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
            
            // Si no hay filas o solo hay una fila con mensaje "No hay solicitudes/registros", salir
            if (rows.length === 0 || (rows.length === 1 && rows[0].cells.length === 1)) {
                return;
            }
            
            const filterValue = filterSelect.value;
            
            // Filtrar filas que no son mensajes
            const filasNormales = rows.filter(row => row.cells.length > 1);
            const filasMensaje = rows.filter(row => row.cells.length === 1);

            if (filasNormales.length > 0) {
                filasNormales.sort((a, b) => {
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
                
                // Primero las filas de mensaje, luego las normales ordenadas
                filasMensaje.forEach(row => tbody.appendChild(row));
                filasNormales.forEach(row => tbody.appendChild(row));
            }
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
                if (boton && boton.name === 'rechazar' && !confirm('¿Estás seguro de rechazar esta solicitud?')) {
                    e.preventDefault();
                }
            });
        });
    </script>
</body>
</html>

