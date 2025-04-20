<?php
session_start();
// Activar la visualización de errores para depuración (útil durante el desarrollo)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../../config/conexion.php';

// Verificar si la sesión de usuario está establecida y tiene el ID
if (!isset($_SESSION['usuario']['id'])) {
    // Redirigir a la página de login si no está logueado
    header("Location: /views/general/login.php");
    exit();
}

$nombreUsuario = $_SESSION['usuario']['nombre'] ?? 'Invitado'; // Usar nombre de la sesión si está disponible

// Variable para mensajes de estado
$mensaje = '';
$mensaje_tipo = ''; // 'exito' o 'error'

// Función para manejar valores nulos en htmlspecialchars
function htmlSafe($str) {
    return $str !== null ? htmlspecialchars((string)$str, ENT_QUOTES, 'UTF-8') : '';
}


// Manejar acciones POST (editar/eliminar)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        $id = $_POST['id'] ?? null;

        if (!$id) {
             $mensaje = "Error: ID de usuario no proporcionado.";
             $mensaje_tipo = 'error';
        } else {
            try {
                switch($_POST['action']) {
                    case 'edit':
                        // Asegúrate de que el campo 'estado' esté en el POST
                        if (!isset($_POST['estado'])) {
                             $mensaje = "Error: Estado del usuario no proporcionado.";
                             $mensaje_tipo = 'error';
                             break; // Salir del switch
                        }

                        $stmt = $conexion->prepare("UPDATE usuarios SET
                            nombre = ?,
                            email = ?,
                            rol = ?,
                            documento = ?,
                            codigo_estudiante = ?,
                            telefono = ?,
                            opcion_grado = ?,
                            ciclo = ?,
                            estado = ?  -- <-- Añadimos el campo estado
                            WHERE id = ?");

                        $exito = $stmt->execute([
                            $_POST['nombre'] ?? null,
                            $_POST['email'] ?? null,
                            $_POST['rol'] ?? null,
                            $_POST['documento'] ?? null,
                            $_POST['codigo_estudiante'] ?? null,
                            $_POST['telefono'] ?? null,
                            $_POST['opcion_grado'] ?? null,
                            $_POST['ciclo'] ?? null,
                            $_POST['estado'] ?? null, // <-- Añadimos el valor del estado
                            $id
                        ]);

                        if ($exito) {
                            $mensaje = "Usuario actualizado correctamente.";
                            $mensaje_tipo = 'exito';
                        } else {
                             error_log("Error al actualizar usuario: " . print_r($stmt->errorInfo(), true));
                             $mensaje = "Error al actualizar el usuario.";
                             $mensaje_tipo = 'error';
                        }
                        break;

                    case 'delete':
                        // Opcional: Añadir confirmación o manejo de errores si el usuario tiene relaciones
                         $stmtDelete = $conexion->prepare("DELETE FROM usuarios WHERE id = ?");
                         $exito = $stmtDelete->execute([$id]);

                         if ($exito) {
                             $mensaje = "Usuario eliminado correctamente.";
                             $mensaje_tipo = 'exito';
                         } else {
                             error_log("Error al eliminar usuario: " . print_r($stmtDelete->errorInfo(), true));
                             // Puede haber errores de clave foránea si el usuario está relacionado con proyectos, seminarios, etc.
                             $mensaje = "Error al eliminar el usuario. Asegúrese de que no tenga datos relacionados (proyectos, seminarios, etc.).";
                             $mensaje_tipo = 'error';
                         }
                        break;

                    default:
                        $mensaje = "Acción no válida.";
                        $mensaje_tipo = 'error';
                        break;
                }

            } catch(PDOException $e) {
                error_log("Error en la operación (PDOException): " . $e->getMessage());
                $mensaje = "Error en la base de datos: " . $e->getMessage();
                $mensaje_tipo = 'error';
            } catch(Exception $e) {
                 error_log("Error inesperado: " . $e->getMessage());
                 $mensaje = "Ocurrió un error inesperado: " . $e->getMessage();
                 $mensaje_tipo = 'error';
            }
        }

        // Redirigir con mensaje después de POST para evitar reenvío del formulario
        // Usamos http_build_query para manejar correctamente los parámetros en la URL
        $params = ['mensaje' => $mensaje, 'tipo' => $mensaje_tipo];
        header("Location: ".$_SERVER['PHP_SELF'] . '?' . http_build_query($params));
        exit;
    }
}

// Recuperar mensaje de la URL si existe (después de una redirección POST)
if (isset($_GET['mensaje'], $_GET['tipo'])) {
    $mensaje = $_GET['mensaje'];
    $mensaje_tipo = $_GET['tipo'];
}


// Obtener usuarios con filtros
$where = [];
$params = [];

// Filtro de búsqueda general
if (!empty($_GET['search'])) {
    $search = "%{$_GET['search']}%";
    $where[] = "(nombre LIKE ? OR documento LIKE ? OR email LIKE ?)";
    array_push($params, $search, $search, $search);
}

// Filtro por Estado <-- Nuevo filtro
if (!empty($_GET['estado'])) {
    $where[] = "estado = ?";
    $params[] = $_GET['estado'];
}

// Filtro por Rol
if (!empty($_GET['rol'])) {
    $where[] = "rol = ?";
    $params[] = $_GET['rol'];
}

// Filtro por Opción de Grado
if (!empty($_GET['opcion_grado'])) {
    $where[] = "opcion_grado = ?";
    $params[] = $_GET['opcion_grado'];
}

// Filtro por Ciclo
if (!empty($_GET['ciclo'])) {
    $where[] = "ciclo = ?";
    $params[] = $_GET['ciclo'];
}

$sql = "SELECT * FROM usuarios";
if (!empty($where)) {
    $sql .= " WHERE " . implode(" AND ", $where);
}

// Opcional: Añadir ordenación por defecto si no hay filtro de orden
// $sql .= " ORDER BY nombre ASC";

try {
    $stmt = $conexion->prepare($sql);
    $stmt->execute($params);
    $usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error al obtener usuarios: " . $e->getMessage());
    $mensaje = "Error al cargar los usuarios: " . $e->getMessage();
    $mensaje_tipo = 'error';
    $usuarios = []; // Asegurarse de que $usuarios sea un array vacío en caso de error
}


$conexion = null; // Cerrar conexión
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Usuarios</title>
    <link rel="stylesheet" href="/assets/css/usuarios.css">
</head>
<body>
<header>
        <div id="logo" onclick="toggleNav()">Logo</div>
        <nav id="navbar">
        <ul>
            <li><a href="/views/administrador/inicio.php">Inicio</a></li>
            <li><a href="/views/administrador/aprobacion.php">Aprobación de Usuarios</a></li>
            <li><a href="/views/administrador/usuarios.php" class="active">Gestión de Usuarios</a></li>
            <li class="dropdown">
                <a href="#">Gestión de Modalidades de Grado</a>
                <ul class="dropdown-content">
                    <li><a href="/views/administrador/gestion_seminario.php">Seminario</a></li>
                    <li><a href="/views/administrador/gestion_proyectos.php">Proyectos</a></li>
                    <li><a href="/views/administrador/gestion_pasantias.php">Pasantías</a></li>
                </ul>
            </li>
            <li><a href="/views/administrador/reportes.php">Reportes y Estadísticas</a></li>
            <li><a href="#">Usuario: <?php echo htmlSafe($nombreUsuario); ?></a></li>
            <li><a href="/views/general/login.php">Cerrar Sesión</a></li>
        </ul>
        </nav>
    </header>

    <main>
        <h1>Gestión de Usuarios</h1>

        <?php if (!empty($mensaje)): ?>
            <div class="mensaje <?php echo htmlSafe($mensaje_tipo); ?>">
                <?php echo htmlSafe($mensaje); ?>
            </div>
        <?php endif; ?>

        <form method="GET" class="filters-section">
            <div class="search-bar">
                <input type="text" name="search" placeholder="Buscar por nombre, documento o email..."
                       value="<?= htmlSafe($_GET['search'] ?? '') ?>">
                <button type="submit">Buscar</button>
            </div>

            <div class="filters">
                <div class="filter">
                    <label for="filter-estado">Estado:</label>
                    <select name="estado" id="filter-estado">
                        <option value="">Todos</option>
                        <option value="activo" <?= (($_GET['estado'] ?? '') == 'activo') ? 'selected' : '' ?>>Activo</option>
                        <option value="inactivo" <?= (($_GET['estado'] ?? '') == 'inactivo') ? 'selected' : '' ?>>Inactivo</option>
                    </select>
                </div>

                <div class="filter">
                    <label for="filter-rol">Rol:</label>
                    <select name="rol" id="filter-rol">
                        <option value="">Todos</option>
                        <option value="estudiante" <?= (($_GET['rol'] ?? '') == 'estudiante') ? 'selected' : '' ?>>Estudiante</option>
                        <option value="tutor" <?= (($_GET['rol'] ?? '') == 'tutor') ? 'selected' : '' ?>>Tutor</option>
                        <option value="admin" <?= (($_GET['rol'] ?? '') == 'admin') ? 'selected' : '' ?>>Admin</option>
                    </select>
                </div>

                <div class="filter">
                    <label for="filter-opcion_grado">Opción Grado:</label>
                    <select name="opcion_grado" id="filter-opcion_grado">
                        <option value="">Todas</option>
                        <option value="seminario" <?= (($_GET['opcion_grado'] ?? '') == 'seminario') ? 'selected' : '' ?>>Seminario</option>
                        <option value="proyecto" <?= (($_GET['opcion_grado'] ?? '') == 'proyecto') ? 'selected' : '' ?>>Proyecto de Grado</option>
                        <option value="pasantia" <?= (($_GET['opcion_grado'] ?? '') == 'pasantia') ? 'selected' : '' ?>>Pasantía</option>
                    </select>
                </div>

                <div class="filter">
                    <label for="filter-ciclo">Ciclo:</label>
                    <select name="ciclo" id="filter-ciclo">
                        <option value="">Todos</option>
                        <option value="tecnico" <?= (($_GET['ciclo'] ?? '') == 'tecnico') ? 'selected' : '' ?>>Técnico</option>
                        <option value="tecnologo" <?= (($_GET['ciclo'] ?? '') == 'tecnologo') ? 'selected' : '' ?>>Tecnólogo</option>
                        <option value="profesional" <?= (($_GET['ciclo'] ?? '') == 'profesional') ? 'selected' : '' ?>>Profesional</option>
                    </select>
                </div>

                <button type="submit" class="apply-filters">Aplicar</button>
                <button type="button" onclick="window.location.href='<?= htmlSafe($_SERVER['PHP_SELF']) ?>'" class="clear-filters">Limpiar</button>
            </div>
        </form>


        <div class="table-container">
            <table class="users-table">
            <thead>
                    <tr>
                        <th>Nombre</th>
                        <th>Tipo</th>
                        <th>Documento</th>
                        <th>Código</th>
                        <th>Email</th>
                        <th>Teléfono</th>
                        <th>Opción Grado</th>
                        <th>Ciclo</th>
                        <th>Estado</th>
                        <th>Acciones</th> </tr>
                </thead>
                <tbody>
                    <?php if (empty($usuarios)): ?>
                        <tr>
                             <td colspan="10" style="text-align: center;">No se encontraron usuarios con los filtros aplicados.</td>
                        </tr>
                    <?php else: ?>
                         <?php foreach ($usuarios as $usuario): ?>
                            <tr>
                            <td><?= htmlSafe($usuario['nombre']) ?></td>
                            <td><?= htmlSafe(ucfirst($usuario['rol'])) ?></td>
                            <td><?= htmlSafe($usuario['documento']) ?></td>
                            <td><?= htmlSafe($usuario['codigo_estudiante']) ?></td>
                            <td><?= htmlSafe($usuario['email']) ?></td>
                            <td><?= htmlSafe($usuario['telefono']) ?></td>
                            <td><?= htmlSafe(ucfirst($usuario['opcion_grado'] ?? '')) ?></td>
                            <td><?= htmlSafe(ucfirst($usuario['ciclo'] ?? '')) ?></td>
                            <td><span class="status <?= htmlSafe($usuario['estado']) ?>"><?= htmlSafe(ucfirst($usuario['estado'])) ?></span></td>
                            <td class="actions"> 
                                <button class="edit" onclick="openEditModal(<?= htmlSafe($usuario['id']) ?>)">🖊</button>
                                <button class="delete" onclick="confirmDelete(<?= htmlSafe($usuario['id']) ?>)">🗑</button>
                            </td> 
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div id="editModal" class="modal">
            <div class="modal-content">
                <span class="close" onclick="closeModal('editModal')">&times;</span>
                <h2>Editar Usuario</h2>
                <form method="POST" id="editForm">
                    <input type="hidden" name="id" id="editId">
                    <input type="hidden" name="action" value="edit">

                    <div class="form-group">
                        <label for="editNombre">Nombre:</label>
                        <input type="text" id="editNombre" name="nombre" required>
                    </div>

                    <div class="form-group">
                        <label for="editEmail">Email:</label>
                        <input type="email" id="editEmail" name="email" required>
                    </div>

                    <div class="form-group">
                        <label for="editRol">Rol:</label>
                        <select id="editRol" name="rol" required>
                            <option value="estudiante">Estudiante</option>
                            <option value="tutor">Tutor</option>
                            <option value="admin">Admin</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="editDocumento">Documento:</label>
                        <input type="text" id="editDocumento" name="documento" required>
                    </div>

                    <div class="form-group">
                        <label for="editCodigo">Código:</label>
                        <input type="text" id="editCodigo" name="codigo_estudiante">
                    </div>

                    <div class="form-group">
                        <label for="editTelefono">Teléfono:</label>
                        <input type="text" id="editTelefono" name="telefono">
                    </div>

                    <div class="form-group">
                        <label for="editOpcionGrado">Opción Grado:</label>
                        <select id="editOpcionGrado" name="opcion_grado">
                            <option value="">Ninguna</option>
                            <option value="seminario">Seminario</option>
                            <option value="proyecto">Proyecto</option>
                            <option value="pasantia">Pasantia</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="editCiclo">Ciclo:</label>
                        <select id="editCiclo" name="ciclo">
                            <option value="">Ninguno</option>
                            <option value="tecnico">Técnico</option>
                            <option value="tecnologo">Tecnólogo</option>
                            <option value="profesional">Profesional</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="editEstado">Estado:</label> <select id="editEstado" name="estado" required>
                            <option value="activo">Activo</option>
                            <option value="inactivo">Inactivo</option>
                        </select>
                    </div>

                    <div class="form-actions">
                        <button type="submit">Guardar</button>
                        <button type="button" class="cancel" onclick="closeModal('editModal')">Cancelar</button>
                    </div>
                </form>
            </div>
        </div>

        <div id="deleteModal" class="modal">
            <div class="modal-content">
                <span class="close" onclick="closeModal('deleteModal')">&times;</span>
                <h2>Confirmar Eliminación</h2>
                <p>¿Está seguro de eliminar este usuario?</p>
                <form method="POST" id="deleteForm">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" id="deleteId">
                    <div class="form-actions">
                        <button type="submit">Eliminar</button>
                        <button type="button" class="cancel" onclick="closeModal('deleteModal')">Cancelar</button>
                    </div>
                </form>
            </div>
        </div>
    </main>

    <footer>
        <p>&copy; 2023 Sistema de Gestión Académica. Todos los derechos reservados.</p>
    </footer>


    <script>
        // Pasa los usuarios a JavaScript para el modal de edición
        // Asegúrate de que la variable usuarios sea un array en PHP incluso si está vacío
        const usuariosData = <?= json_encode($usuarios); ?>;

        function openEditModal(id) {
            const user = usuariosData.find(u => u.id == id); // Comparación con == para coincidencia de tipo
            if (user) {
                document.getElementById('editId').value = user.id;
                document.getElementById('editNombre').value = user.nombre || '';
                document.getElementById('editEmail').value = user.email || '';
                document.getElementById('editRol').value = user.rol || '';
                document.getElementById('editDocumento').value = user.documento || '';
                document.getElementById('editCodigo').value = user.codigo_estudiante || '';
                document.getElementById('editTelefono').value = user.telefono || '';
                document.getElementById('editOpcionGrado').value = user.opcion_grado || '';
                document.getElementById('editCiclo').value = user.ciclo || '';
                document.getElementById('editEstado').value = user.estado || 'activo'; // <-- Cargar el estado actual
                document.getElementById('editModal').style.display = 'block';
            } else {
                console.error('Usuario no encontrado con ID:', id);
                // Opcional: Mostrar un mensaje al usuario
            }
        }

        function confirmDelete(id) {
            document.getElementById('deleteId').value = id;
            document.getElementById('deleteModal').style.display = 'block';
        }

        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }

        // Cerrar el modal haciendo clic fuera de él
        window.onclick = function(event) {
            if (event.target.className === 'modal') {
                event.target.style.display = 'none';
            }
        }

        // Función para el menú de navegación (asumiendo que existe)
        function toggleNav() {
            const navbar = document.getElementById('navbar');
            navbar.classList.toggle('active');
        }

        // Ocultar mensaje de estado después de unos segundos
         document.addEventListener('DOMContentLoaded', function() {
             const mensajeElement = document.querySelector('.mensaje');
             if (mensajeElement) {
                 setTimeout(() => {
                     mensajeElement.style.display = 'none';
                 }, 5000); // Ocultar después de 5 segundos
             }
         });

    </script>
</body>
</html>