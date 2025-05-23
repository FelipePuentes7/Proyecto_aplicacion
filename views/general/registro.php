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

        // Validar formato de correo
        $email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception('Formato de correo electrónico inválido');
        }

        // Validar que el correo termine en @fet.edu.co
        if (!preg_match('/@fet\.edu\.co$/i', $email)) {
            throw new Exception('El correo debe ser institucional (@fet.edu.co)');
        }

        // Sanitizar entradas
        $rol = filter_var($_POST['rol'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        $nombre = filter_var($_POST['nombre'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        $documento = filter_var($_POST['documento'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        
        // Validar documento (solo números)
        if (!preg_match('/^[0-9]+$/', $documento)) {
            throw new Exception('El documento debe contener solo números');
        }
        
        // Campos específicos por rol
        if ($rol === 'estudiante') {
            $codigo = filter_var($_POST['codigo_estudiante'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
            
            // Validar que el código de estudiante comience con SOF (solo para estudiantes)
            if (!preg_match('/^SOF/i', $codigo)) {
                throw new Exception('El código de estudiante debe comenzar con "SOF"');
            }
            
            $opcion_grado = filter_var($_POST['opcion_grado'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
            $ciclo = filter_var($_POST['ciclo'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
            $telefono = filter_var($_POST['telefono'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
            
            // Campos adicionales según la opción de grado
            $nombre_proyecto = null;
            $nombre_empresa = null;
            
            if ($opcion_grado === 'proyecto') {
                $nombre_proyecto = filter_var($_POST['nombre_proyecto'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
                if (empty($nombre_proyecto)) {
                    throw new Exception('Debe ingresar el nombre del proyecto');
                }
            } elseif ($opcion_grado === 'pasantia') {
                $nombre_empresa = filter_var($_POST['nombre_empresa'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
                if (empty($nombre_empresa)) {
                    throw new Exception('Debe ingresar el nombre de la empresa');
                }
            }
        } else {
            $codigo = filter_var($_POST['codigo_institucional'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
            $telefono = filter_var($_POST['telefono_tutor'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
            $opcion_grado = null;
            $ciclo = null;
            $nombre_proyecto = null;
            $nombre_empresa = null;
        }

        // Validar teléfono (solo números)
        if (!preg_match('/^[0-9]+$/', $telefono)) {
            throw new Exception('El teléfono debe contener solo números');
        }

        // Verificar email único - adaptado para PostgreSQL
        $stmt = $conexion->prepare("SELECT email FROM solicitudes_registro WHERE email = ? UNION SELECT email FROM usuarios WHERE email = ?");
        $stmt->execute([$email, $email]);
        if ($stmt->fetch()) {
            throw new Exception('El correo ya está registrado');
        }

        // Verificar documento único - adaptado para PostgreSQL
        $stmt = $conexion->prepare("SELECT documento FROM solicitudes_registro WHERE documento = ? UNION SELECT documento FROM usuarios WHERE documento = ?");
        $stmt->execute([$documento, $documento]);
        if ($stmt->fetch()) {
            throw new Exception('El documento ya está registrado');
        }

        // Hash de contraseña
        $hashedPassword = password_hash($_POST['password'], PASSWORD_DEFAULT);

        // Insertar en solicitudes - adaptado para la nueva estructura
        $stmt = $conexion->prepare("INSERT INTO solicitudes_registro (
            nombre, email, password, rol, documento,
            codigo_estudiante, codigo_institucional,
            telefono, opcion_grado, nombre_proyecto, nombre_empresa, ciclo, estado
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pendiente')");

        $stmt->execute([
            $nombre, $email, $hashedPassword, $rol, $documento,
            ($rol === 'estudiante') ? $codigo : null,
            ($rol === 'tutor') ? $codigo : null,
            $telefono,
            $opcion_grado, $nombre_proyecto, $nombre_empresa, $ciclo
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
                <div class="mensaje error"><?= $error ?></div>
            <?php endif; ?>

            <div class="form-row">
                <div class="form-group">
                    <label for="rol">Rol:</label>
                    <select id="rol" name="rol" required>
                        <option value="estudiante" <?= ($_POST['rol'] ?? 'estudiante') === 'estudiante' ? 'selected' : '' ?>>Estudiante</option>
                        <option value="tutor" <?= ($_POST['rol'] ?? '') === 'tutor' ? 'selected' : '' ?>>Tutor</option>
                        <option value="admin" hidden <?= ($_POST['rol'] ?? '') === 'admin' ? 'selected' : '' ?>>Administrador</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="nombre">Nombre completo:</label>
                    <input type="text" id="nombre" name="nombre" value="<?= htmlspecialchars($_POST['nombre'] ?? '') ?>" required>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="email">Correo institucional:</label>
                    <input type="email" id="email" name="email" 
                           value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" 
                           placeholder="ejemplo@fet.edu.co" required>
                    <small class="email-hint">El correo debe terminar en @fet.edu.co</small>
                </div>
                <div class="form-group">
                    <label for="documento">Número de documento:</label>
                    <input type="text" id="documento" name="documento" 
                           value="<?= htmlspecialchars($_POST['documento'] ?? '') ?>" required>
                </div>
            </div>

            <div class="form-row estudiante-fields">
                <div class="form-group">
                    <label for="codigo_estudiante">Código de estudiante:</label>
                    <input type="text" id="codigo_estudiante" name="codigo_estudiante" 
                           value="<?= htmlspecialchars($_POST['codigo_estudiante'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label for="opcion_grado">Opción de grado:</label>
                    <select id="opcion_grado" name="opcion_grado">
                        <option value="">Seleccione una opción</option>
                        <option value="seminario" <?= ($_POST['opcion_grado'] ?? '') === 'seminario' ? 'selected' : '' ?>>Seminario</option>
                        <option value="proyecto" <?= ($_POST['opcion_grado'] ?? '') === 'proyecto' ? 'selected' : '' ?>>Proyecto de Aplicación</option>
                        <option value="pasantia" <?= ($_POST['opcion_grado'] ?? '') === 'pasantia' ? 'selected' : '' ?>>Pasantías</option>
                    </select>
                </div>
            </div>

            <!-- Campos adicionales según la opción de grado -->
            <div class="form-row proyecto-fields" style="display: none;">
                <div class="form-group">
                    <label for="nombre_proyecto">Nombre del proyecto:</label>
                    <input type="text" id="nombre_proyecto" name="nombre_proyecto" 
                           value="<?= htmlspecialchars($_POST['nombre_proyecto'] ?? '') ?>">
                </div>
            </div>

            <div class="form-row pasantia-fields" style="display: none;">
                <div class="form-group">
                    <label for="nombre_empresa">Nombre de la empresa:</label>
                    <input type="text" id="nombre_empresa" name="nombre_empresa" 
                           value="<?= htmlspecialchars($_POST['nombre_empresa'] ?? '') ?>">
                </div>
            </div>

            <div class="form-row estudiante-fields">
                <div class="form-group">
                    <label for="ciclo">Ciclo:</label>
                    <select id="ciclo" name="ciclo">
                        <option value="">Seleccione un ciclo</option>
                        <option value="tecnico" <?= ($_POST['ciclo'] ?? '') === 'tecnico' ? 'selected' : '' ?>>Técnico</option>
                        <option value="tecnologo" <?= ($_POST['ciclo'] ?? '') === 'tecnologo' ? 'selected' : '' ?>>Tecnólogo</option>
                        <option value="profesional" <?= ($_POST['ciclo'] ?? '') === 'profesional' ? 'selected' : '' ?>>Profesional</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="telefono">Teléfono:</label>
                    <input type="tel" id="telefono" name="telefono" 
                           value="<?= htmlspecialchars($_POST['telefono'] ?? '') ?>">
                </div>
            </div>

            <div class="form-row tutor-fields" style="display: none;">
                <div class="form-group">
                    <label for="codigo_institucional">Código institucional:</label>
                    <input type="text" id="codigo_institucional" name="codigo_institucional" 
                           value="<?= htmlspecialchars($_POST['codigo_institucional'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label for="telefono_tutor">Teléfono:</label>
                    <input type="tel" id="telefono_tutor" name="telefono_tutor" 
                           value="<?= htmlspecialchars($_POST['telefono_tutor'] ?? '') ?>">
                </div>
            </div>

            <div class="form-row">
                <div class="form-group password-group">
                    <label for="password">Contraseña:</label>
                    <input type="password" id="password" name="password" required>
                    <div class="password-strength">
                        <div class="strength-bar"></div>
                        <span class="strength-text">Seguridad: No ingresada</span>
                    </div>
                </div>
                <div class="form-group">
                    <label for="confirm_password">Confirmar contraseña:</label>
                    <input type="password" id="confirm_password" name="confirm_password" required>
                    <span class="password-match"></span>
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
    <script src="/assets/js/general/registro.js"></script>
</body>
</html>