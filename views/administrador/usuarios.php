<?php
session_start();
require_once __DIR__ . '/../../config/conexion.php'; // si config.php está un nivel arriba de la carpeta administrador

$nombreUsuario = $_SESSION['nombreUsuario'] ?? 'Invitado';

// Manejar acciones
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        $id = $_POST['id'];
        
        try {
            switch($_POST['action']) {
                case 'edit':
                    $stmt = $conexion->prepare("UPDATE usuarios SET 
                        nombre = ?, 
                        email = ?, 
                        rol = ?,
                        documento = ?, 
                        codigo_estudiante = ?, 
                        telefono = ?, 
                        opcion_grado = ?, 
                        ciclo = ?, 
                        estado = ?
                        WHERE id = ?");
                    
                    $stmt->execute([
                        $_POST['nombre'],
                        $_POST['email'],
                        $_POST['rol'],
                        $_POST['documento'],
                        $_POST['codigo_estudiante'],
                        $_POST['telefono'],
                        $_POST['opcion_grado'],
                        $_POST['ciclo'],
                        $_POST['estado'],
                        $id
                    ]);
                    break;

                case 'toggle_status':
                    $newStatus = ($_POST['current_status'] == 'activo') ? 'inactivo' : 'activo';
                    $conexion->prepare("UPDATE usuarios SET estado = ? WHERE id = ?")
                             ->execute([$newStatus, $id]);
                    break;

                case 'delete':
                    $conexion->prepare("DELETE FROM usuarios WHERE id = ?")->execute([$id]);
                    break;
            }
            
            header("Location: ".$_SERVER['PHP_SELF']);
            exit;
            
        } catch(PDOException $e) {
            error_log("Error en la operación: " . $e->getMessage());
        }
    }
}

// Obtener usuarios con filtros
$where = [];
$params = [];

if (!empty($_GET['search'])) {
    $search = "%{$_GET['search']}%";
    $where[] = "(nombre LIKE ? OR documento LIKE ? OR email LIKE ?)";
    array_push($params, $search, $search, $search);
}

if (!empty($_GET['estado'])) {
    $where[] = "estado = ?";
    $params[] = $_GET['estado'];
}

if (!empty($_GET['rol'])) {
    $where[] = "rol = ?";
    $params[] = $_GET['rol'];
}

$sql = "SELECT * FROM usuarios";
if (!empty($where)) {
    $sql .= " WHERE " . implode(" AND ", $where);
}

if (!empty($_GET['opcion_grado'])) {
    $where[] = "opcion_grado = ?";
    $params[] = $_GET['opcion_grado'];
}

if (!empty($_GET['ciclo'])) {
    $where[] = "ciclo = ?";
    $params[] = $_GET['ciclo'];
}

$sql = "SELECT * FROM usuarios";
if (!empty($where)) {
    $sql .= " WHERE " . implode(" AND ", $where);
}

$stmt = $conexion->prepare($sql);
$stmt->execute($params);
$usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);

$conexion = null;
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
        <h1>Gestión de Usuarios</h1>
        
        <form method="GET" class="filters-section">
    <div class="search-bar">
        <input type="text" name="search" placeholder="Buscar por nombre, documento o email..." 
               value="<?= htmlspecialchars($_GET['search'] ?? '') ?>">
        <button type="submit">Buscar</button>
    </div>

    <div class="filters">
        <div class="filter">
            <label>Estado:</label>
            <select name="estado">
                <option value="">Todos</option>
                <option value="activo" <?= ($_GET['estado'] ?? '') == 'activo' ? 'selected' : '' ?>>Activo</option>
                <option value="inactivo" <?= ($_GET['estado'] ?? '') == 'inactivo' ? 'selected' : '' ?>>Inactivo</option>
            </select>
        </div>

        <div class="filter">
            <label>Rol:</label>
            <select name="rol">
                <option value="">Todos</option>
                <option value="estudiante" <?= ($_GET['rol'] ?? '') == 'estudiante' ? 'selected' : '' ?>>Estudiante</option>
                <option value="tutor" <?= ($_GET['rol'] ?? '') == 'tutor' ? 'selected' : '' ?>>Tutor</option>
                <option value="admin" <?= ($_GET['rol'] ?? '') == 'admin' ? 'selected' : '' ?>>Admin</option>
            </select>
        </div>
        
        <div class="filter">
            <label>Opción Grado:</label>
            <select name="opcion_grado">
                <option value="">Todas</option>
                <option value="seminario" <?= ($_GET['opcion_grado'] ?? '') == 'seminario' ? 'selected' : '' ?>>Seminario</option>
                <option value="proyecto" <?= ($_GET['opcion_grado'] ?? '') == 'proyecto' ? 'selected' : '' ?>>Proyecto de Grado</option>
                <option value="pasantia" <?= ($_GET['opcion_grado'] ?? '') == 'pasantia' ? 'selected' : '' ?>>Pasantía</option>
            </select>
        </div>
        
        <div class="filter">
            <label>Ciclo:</label>
            <select name="ciclo">
                <option value="">Todos</option>
                <option value="tecnico" <?= ($_GET['ciclo'] ?? '') == 'tecnico' ? 'selected' : '' ?>>Técnico</option>
                <option value="tecnologo" <?= ($_GET['ciclo'] ?? '') == 'tecnologo' ? 'selected' : '' ?>>Tecnólogo</option>
                <option value="profesional" <?= ($_GET['ciclo'] ?? '') == 'profesional' ? 'selected' : '' ?>>Profesional</option>
            </select>
        </div>

        <button type="submit" class="apply-filters">Aplicar</button>
        <button type="button" onclick="window.location.href='<?= $_SERVER['PHP_SELF'] ?>'" class="clear-filters">Limpiar</button>
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
                        <th>Registro</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($usuarios as $usuario): ?>
                    <tr>
                        <td><?= htmlspecialchars($usuario['nombre']) ?></td>
                        <td><?= ucfirst($usuario['rol']) ?></td>
                        <td><?= htmlspecialchars($usuario['documento']) ?></td>
                        <td><?= htmlspecialchars($usuario['codigo_estudiante']) ?></td>
                        <td><?= htmlspecialchars($usuario['email']) ?></td>
                        <td><?= htmlspecialchars($usuario['telefono']) ?></td>
                        <td><?= ucfirst($usuario['opcion_grado'] ?? '') ?></td>
                        <td><?= ucfirst($usuario['ciclo'] ?? '') ?></td>
                        <td><?= date('d/m/Y', strtotime($usuario['fecha_registro'])) ?></td>
                        <td>
                            <span class="status <?= $usuario['estado'] ?>">
                                <?= ucfirst($usuario['estado']) ?>
                            </span>
                        </td>
                        <td class="actions">
                            <button class="edit" onclick="openEditModal(<?= $usuario['id'] ?>)">🖊</button>
                            <form method="POST" style="display:inline">
                                <input type="hidden" name="id" value="<?= $usuario['id'] ?>">
                                <input type="hidden" name="current_status" value="<?= $usuario['estado'] ?>">
                                <button type="submit" name="action" value="toggle_status" 
                                        class="toggle-status" title="Cambiar estado">
                                    <?= $usuario['estado'] == 'activo' ? '🔒' : '🔓' ?>
                                </button>
                            </form>
                            <button class="delete" onclick="confirmDelete(<?= $usuario['id'] ?>)">🗑</button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Modal Editar Usuario -->
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
                        <label for="editEstado">Estado:</label>
                        <select id="editEstado" name="estado" required>
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

        <!-- Modal Eliminar -->
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

    <script>
        function openEditModal(id) {
            const user = <?= json_encode($usuarios) ?>.find(u => u.id == id);
            if (user) {
                document.getElementById('editId').value = user.id;
                document.getElementById('editNombre').value = user.nombre;
                document.getElementById('editEmail').value = user.email;
                document.getElementById('editRol').value = user.rol;
                document.getElementById('editDocumento').value = user.documento;
                document.getElementById('editCodigo').value = user.codigo_estudiante || '';
                document.getElementById('editTelefono').value = user.telefono || '';
                document.getElementById('editOpcionGrado').value = user.opcion_grado || '';
                document.getElementById('editCiclo').value = user.ciclo || '';
                document.getElementById('editEstado').value = user.estado;
                document.getElementById('editModal').style.display = 'block';
            }
        }

        function confirmDelete(id) {
            document.getElementById('deleteId').value = id;
            document.getElementById('deleteModal').style.display = 'block';
        }

        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }

        window.onclick = function(event) {
            if (event.target.className === 'modal') {
                event.target.style.display = 'none';
            }
        }
        function toggleNav() {
    const navbar = document.getElementById('navbar');
    navbar.classList.toggle('active');
}
    </script>
</body>
</html>