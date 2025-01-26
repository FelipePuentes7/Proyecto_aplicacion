<?php
// views/administrador/filtro.php
session_start();

// Verificar autenticación y rol de administrador
if (!isset($_SESSION['rol']) || $_SESSION['rol'] !== 'admin') {
    header("Location: /views/general/login.php");
    exit();
}

// Incluir conexión a la base de datos
require_once __DIR__ . '/../../config/conexion.php';

// Procesar acciones (aprobar/rechazar)
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['accion'])) {
    if (!isset($_GET['id'])) {
        die("ID no especificado");
    }
    
    $id = $_GET['id'];
    
    try {
        if ($_GET['accion'] === 'aprobar') {
            // Obtener solicitud
            $stmt = $conexion->prepare("SELECT * FROM solicitudes_registro WHERE id = ?");
            $stmt->execute([$id]);
            $solicitud = $stmt->fetch(PDO::FETCH_ASSOC);

            // Insertar en usuarios
            $insert = $conexion->prepare("INSERT INTO usuarios (nombre, email, password, rol, documento, codigo, telefono, carrera, semestre) 
                                  VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $insert->execute([
                $solicitud['nombre'],
                $solicitud['email'],
                $solicitud['password'],
                $solicitud['rol'],
                $solicitud['documento'],
                $solicitud['codigo_estudiante'] ?? $solicitud['codigo_institucional'],
                $solicitud['telefono'] ?? $solicitud['telefono_tutor'],
                $solicitud['carrera'] ?? null,
                $solicitud['semestre'] ?? null
            ]);

            // Actualizar estado
            $conexion->prepare("UPDATE solicitudes_registro SET estado = 'aprobado' WHERE id = ?")->execute([$id]);
            
        } elseif ($_GET['accion'] === 'rechazar') {
            $conexion->prepare("UPDATE solicitudes_registro SET estado = 'rechazado' WHERE id = ?")->execute([$id]);
        }
        
        $mensaje = "Acción realizada con éxito!";
        
    } catch(PDOException $e) {
        $error = "Error: " . $e->getMessage();
    }
}

// Obtener solicitudes pendientes
try {
    $stmt = $conexion->query("SELECT * FROM solicitudes_registro WHERE estado = 'pendiente'");
    $solicitudes = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    die("Error al obtener solicitudes: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel de Administración FET</title>
    <style>
        .admin-container {
    padding: 20px;
    max-width: 1200px;
    margin: 0 auto;
}

.admin-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 30px;
    padding: 15px;
    background-color: #f8f9fa;
    border-radius: 5px;
}

.table-responsive {
    margin-top: 20px;
    border: 1px solid #dee2e6;
    border-radius: 5px;
    padding: 15px;
}

.btn-sm {
    margin: 2px;
}
    </style>
</head>
<body>
    <div class="admin-container">
        <div class="header">
            <h1>Panel de Administración FET</h1>
            <a href="logout.php" class="btn btn-danger">Cerrar Sesión</a>
        </div>

        <?php if (isset($mensaje)) : ?>
            <div class="alert alert-success"><?php echo $mensaje; ?></div>
        <?php endif; ?>

        <?php if (isset($error)) : ?>
            <div class="alert alert-error"><?php echo $error; ?></div>
        <?php endif; ?>

        <h3>Solicitudes Pendientes</h3>
        
        <table>
            <thead>
                <tr>
                    <th>Nombre</th>
                    <th>Email</th>
                    <th>Rol</th>
                    <th>Documento</th>
                    <th>Código</th>
                    <th>Teléfono</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($solicitudes)) : ?>
                    <tr>
                        <td colspan="7" style="text-align: center;">No hay solicitudes pendientes</td>
                    </tr>
                <?php else : ?>
                    <?php foreach ($solicitudes as $solicitud) : ?>
                        <tr>
                            <td><?php echo htmlspecialchars($solicitud['nombre']); ?></td>
                            <td><?php echo htmlspecialchars($solicitud['email']); ?></td>
                            <td><?php echo ucfirst($solicitud['rol']); ?></td>
                            <td><?php echo htmlspecialchars($solicitud['documento']); ?></td>
                            <td>
                                <?php 
                                if ($solicitud['rol'] === 'estudiante') {
                                    echo htmlspecialchars($solicitud['codigo_estudiante']);
                                } else {
                                    echo htmlspecialchars($solicitud['codigo_institucional']);
                                }
                                ?>
                            </td>
                            <td>
                                <?php 
                                if ($solicitud['rol'] === 'estudiante') {
                                    echo htmlspecialchars($solicitud['telefono']);
                                } else {
                                    echo htmlspecialchars($solicitud['telefono_tutor']);
                                }
                                ?>
                            </td>
                            <td>
                                <a href="?accion=aprobar&id=<?php echo $solicitud['id']; ?>" class="btn btn-success">Aprobar</a>
                                <a href="?accion=rechazar&id=<?php echo $solicitud['id']; ?>" class="btn btn-danger">Rechazar</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</body>
</html>