<?php
session_start();
require_once __DIR__ . '/../../config/conexion.php';

$mensaje = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Validación básica
        if ($_POST['password'] !== $_POST['confirm_password']) {
            throw new Exception('Las contraseñas no coinciden');
        }

        // Sanitizar entradas
        $rol = htmlspecialchars($_POST['rol']);
        $nombre = htmlspecialchars($_POST['nombre']);
        $email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);
        $documento = htmlspecialchars($_POST['documento']);
        
        // Campos específicos por rol
        if ($rol === 'estudiante') {
            $codigo = htmlspecialchars($_POST['codigo_estudiante']);
            $carrera = htmlspecialchars($_POST['carrera']);
            $semestre = htmlspecialchars($_POST['semestre']);
            $telefono = htmlspecialchars($_POST['telefono']);
        } else {
            $codigo = htmlspecialchars($_POST['codigo_institucional']);
            $telefono = htmlspecialchars($_POST['telefono_tutor']);
            $carrera = null;
            $semestre = null;
        }

        // Verificar email único
        $stmt = $conexion->prepare("SELECT email FROM solicitudes_registro WHERE email = ? UNION SELECT email FROM usuarios WHERE email = ?");
        $stmt->execute([$email, $email]);
        if ($stmt->fetch()) {
            throw new Exception('El correo ya está registrado');
        }

        // Hash de contraseña
        $hashedPassword = password_hash($_POST['password'], PASSWORD_DEFAULT);

        // Insertar en solicitudes
        $stmt = $conexion->prepare("INSERT INTO solicitudes_registro (
            nombre, email, password, rol, documento,
            codigo_estudiante, codigo_institucional,
            telefono, telefono_tutor, carrera, semestre
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

        $stmt->execute([
            $nombre, $email, $hashedPassword, $rol, $documento,
            ($rol === 'estudiante') ? $codigo : null,
            ($rol === 'tutor') ? $codigo : null,
            ($rol === 'estudiante') ? $telefono : null,
            ($rol === 'tutor') ? $telefono : null,
            $carrera, $semestre
        ]);

        $mensaje = "Registro exitoso. Espera la aprobación del administrador.";

    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registro FET</title>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/registro.css">
</head>
<body>
    <div class="container">
        <form id="registroForm" method="POST">
            <h2>Registro FET</h2>

            <?php if ($mensaje): ?>
                <div class="mensaje"><?= $mensaje ?></div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="mensaje" style="background-color: rgba(255,0,0,0.2)"><?= $error ?></div>
            <?php endif; ?>

            <div class="form-row">
                <div class="form-group">
                    <label for="rol">Rol:</label>
                    <select id="rol" name="rol" required>
                        <option value="">Selecciona tu rol</option>
                        <option value="estudiante" <?= ($_POST['rol'] ?? '') === 'estudiante' ? 'selected' : '' ?>>Estudiante</option>
                        <option value="tutor" <?= ($_POST['rol'] ?? '') === 'tutor' ? 'selected' : '' ?>>Tutor</option>
                        <option value="admin" hidden <?= ($_POST['rol'] ?? '') === 'admin' ? 'selected' : '' ?>>Administrador</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="nombre">Nombre completo:</label>
                    <input type="text" id="nombre" name="nombre" value="<?= $_POST['nombre'] ?? '' ?>" required>
                </div>
            </div>

            <div class="form-row">
    <div class="form-group">
        <label for="email">Correo institucional:</label>
        <input type="email" id="email" name="email" 
               value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
    </div>
    <div class="form-group">
        <label for="documento">Número de documento:</label>
        <input type="text" id="documento" name="documento" 
               value="<?= htmlspecialchars($_POST['documento'] ?? '') ?>" required>
    </div>
</div>

<div class="form-row estudiante-only" style="display: none;">
    <div class="form-group">
        <label for="codigo_estudiante">Código de estudiante:</label>
        <input type="text" id="codigo_estudiante" name="codigo_estudiante" 
               value="<?= htmlspecialchars($_POST['codigo_estudiante'] ?? '') ?>">
    </div>
    <div class="form-group">
        <label for="carrera">Carrera:</label>
        <select id="carrera" name="carrera" required>
            <option value="">Seleccione una carrera</option>
            <option value="ingenieria_software" <?= ($_POST['carrera'] ?? '') === 'ingenieria_software' ? 'selected' : '' ?>>Ingeniería de Software</option>
            <option value="ingenieria_sistemas" <?= ($_POST['carrera'] ?? '') === 'ingenieria_sistemas' ? 'selected' : '' ?>>Ingeniería de Sistemas</option>
            <option value="ingenieria_industrial" <?= ($_POST['carrera'] ?? '') === 'ingenieria_industrial' ? 'selected' : '' ?>>Ingeniería Industrial</option>
        </select>
    </div>
</div>

<div class="form-row estudiante-only" style="display: none;">
    <div class="form-group">
        <label for="semestre">Semestre:</label>
        <select id="semestre" name="semestre">
            <option value="">Seleccione un semestre</option>
            <?php for ($i = 1; $i <= 10; $i++): ?>
                <option value="<?= $i ?>" <?= ($_POST['semestre'] ?? '') == $i ? 'selected' : '' ?>>
                    <?= $i ?>
                </option>
            <?php endfor; ?>
        </select>
    </div>
    <div class="form-group">
        <label for="telefono">Teléfono:</label>
        <input type="tel" id="telefono" name="telefono" 
               value="<?= htmlspecialchars($_POST['telefono'] ?? '') ?>" required>
    </div>
</div>

<div class="form-row tutor-only" style="display: none;">
    <div class="form-group">
        <label for="codigo_institucional">Código institucional:</label>
        <input type="text" id="codigo_institucional" name="codigo_institucional" 
               value="<?= htmlspecialchars($_POST['codigo_institucional'] ?? '') ?>">
    </div>
    <div class="form-group">
        <label for="telefono_tutor">Teléfono:</label>
        <input type="tel" id="telefono_tutor" name="telefono_tutor" 
               value="<?= htmlspecialchars($_POST['telefono_tutor'] ?? '') ?>" required>
    </div>
</div>

<div class="form-row">
    <div class="form-group">
        <label for="password">Contraseña:</label>
        <input type="password" id="password" name="password" required>
    </div>
    <div class="form-group">
        <label for="confirm_password">Confirmar contraseña:</label>
        <input type="password" id="confirm_password" name="confirm_password" required>
    </div>
</div>
            
            <div class="form-terms">
                <input type="checkbox" id="terms" name="terms" required>
                <label for="terms">Acepto los términos y condiciones</label>
            </div>

            <button type="submit" class="btn-registro">Registrarse</button>
            <p class="login-link">¿Ya tienes cuenta? <a href="/views/general/login.php">Iniciar sesión</a></p>
        </form>
    </div>
    <script src="/assets/js/registro.js"></script>
</body>
</html>