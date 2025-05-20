<?php
// Iniciar buffer de salida para capturar cualquier salida no deseada
ob_start();

session_start();
error_reporting(E_ALL);
// Cambiar esto para que los errores se registren en lugar de mostrarse
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../../logs/php-errors.log');

require_once __DIR__ . '/../../config/conexion.php';

$error = '';
$mensaje = '';

// Función para enviar respuestas JSON limpias
function enviar_json($data) {
    // Limpiar cualquier salida anterior
    ob_clean();
    
    // Establecer encabezados para JSON
    header('Content-Type: application/json');
    
    // Enviar respuesta JSON
    echo json_encode($data);
    exit();
}

// Proceso de login
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'login') {
    $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
    $password = $_POST['password'];

    try {
        // Buscar usuario en la base de datos
        $stmt = $conexion->prepare("SELECT * FROM usuarios WHERE email = ?");
        $stmt->execute([$email]);
        $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($usuario && password_verify($password, $usuario['password'])) {
            // Almacenar información básica del usuario en la sesión
            $_SESSION['usuario'] = [
                'id' => $usuario['id'],
                'nombre' => $usuario['nombre'],
                'email' => $usuario['email'],
                'rol' => $usuario['rol']
            ];

            // Redirección según rol
            switch ($usuario['rol']) {
                case 'admin':
                    header('Location: /views/administrador/inicio.php');
                    exit();
                case 'tutor':
                    header('Location: /views/profesores/tutor.php');
                    exit();
                case 'estudiante':
                    // Redirección específica para estudiantes según su opción de grado
                    switch ($usuario['opcion_grado']) {
                        case 'pasantia':
                            header('Location: /views/estudiantes/Pasantias.php');
                            exit();
                        case 'proyecto':
                            header('Location: /views/estudiantes/Proyectos.php');
                            exit();
                        case 'seminario':
                            header('Location: /views/seminario_estudiante/inicio_estudiantes.php');
                            exit();
                        default:
                            header('Location: /views/estudiantes/Inicio_Estudiante.php');
                            exit();
                    }
                    break;
                default:
                    header('Location: /');
                    exit();
            }
        } else {
            $error = "Credenciales incorrectas";
        }
    } catch (PDOException $e) {
        $error = "Error al conectar con la base de datos: " . $e->getMessage();
    }
}

// Proceso de verificación para recuperar contraseña
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'verificar') {
    $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
    $documento = filter_input(INPUT_POST, 'documento', FILTER_SANITIZE_STRING);
    $codigo = filter_input(INPUT_POST, 'codigo_estudiante', FILTER_SANITIZE_STRING);
    
    try {
        // Verificar si el usuario existe y los datos coinciden
        $stmt = $conexion->prepare("SELECT * FROM usuarios WHERE email = ? AND documento = ? AND codigo_estudiante = ?");
        $stmt->execute([$email, $documento, $codigo]);
        $usuario = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($usuario) {
            // Si los datos son correctos, mostrar el formulario para cambiar la contraseña
            enviar_json(['success' => true, 'usuario_id' => $usuario['id']]);
        } else {
            enviar_json(['success' => false, 'mensaje' => 'Los datos ingresados no coinciden con nuestros registros.']);
        }
    } catch (PDOException $e) {
        error_log("Error en verificación: " . $e->getMessage());
        enviar_json(['success' => false, 'mensaje' => 'Error al conectar con la base de datos: ' . $e->getMessage()]);
    }
}

// Proceso de cambio de contraseña
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'cambiar_password') {
    // Depuración: Guardar los datos recibidos en un log
    error_log("Datos recibidos para cambio de contraseña: " . print_r($_POST, true));
    
    $usuario_id = filter_input(INPUT_POST, 'usuario_id', FILTER_SANITIZE_NUMBER_INT);
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    // Validar que se recibieron todos los datos necesarios
    if (empty($usuario_id) || empty($password) || empty($confirm_password)) {
        enviar_json(['success' => false, 'mensaje' => 'Faltan datos requeridos para cambiar la contraseña.']);
    }
    
    if ($password !== $confirm_password) {
        enviar_json(['success' => false, 'mensaje' => 'Las contraseñas no coinciden.']);
    }
    
    try {
        // Verificar que el usuario existe
        $check_stmt = $conexion->prepare("SELECT id FROM usuarios WHERE id = ?");
        $check_stmt->execute([$usuario_id]);
        $usuario_existe = $check_stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$usuario_existe) {
            enviar_json(['success' => false, 'mensaje' => 'Usuario no encontrado.']);
        }
        
        // Actualizar la contraseña en la base de datos
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $conexion->prepare("UPDATE usuarios SET password = ? WHERE id = ?");
        $resultado = $stmt->execute([$hashed_password, $usuario_id]);
        
        if ($resultado) {
            enviar_json(['success' => true, 'mensaje' => 'Contraseña actualizada correctamente.']);
        } else {
            // Obtener información sobre el error de la consulta
            $error_info = $stmt->errorInfo();
            enviar_json(['success' => false, 'mensaje' => 'Error al actualizar la contraseña: ' . $error_info[2]]);
        }
    } catch (PDOException $e) {
        error_log("Error PDO al cambiar contraseña: " . $e->getMessage());
        enviar_json(['success' => false, 'mensaje' => 'Error al conectar con la base de datos: ' . $e->getMessage()]);
    } catch (Exception $e) {
        error_log("Error general al cambiar contraseña: " . $e->getMessage());
        enviar_json(['success' => false, 'mensaje' => 'Error inesperado: ' . $e->getMessage()]);
    }
}

// Limpiar el buffer antes de mostrar la página HTML
ob_clean();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inicio de Sesión FET</title>
    <link rel="stylesheet" href="/assets/css/login.css">
    <link rel="stylesheet" href="/assets/css/recuperar-password.css">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
</head>
<body>
    <div class="container">
        <div class="form-container">
            <div class="logo">
                <img src="/assets/images/logofet.png" alt="FET Logo">
            </div>
            
            <form method="POST" action="">
                <input type="hidden" name="action" value="login">
                <?php if ($error): ?>
                    <div class="mensaje-error"><?= $error ?></div>
                <?php endif; ?>
                
                <div class="form-group">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email" required>
                </div>
                
                <div class="form-group">
                    <label for="password">Contraseña</label>
                    <input type="password" id="password" name="password" required>
                </div>
                
                <div class="forgot-password">
                    <a href="#" id="forgot-password-link" class="forgot-password-link">¿Olvidaste tu contraseña?</a>
                </div>

                <div class="forgot-password">
                    <a href="/views/general/registro.php" class="forgot-password-link">Registrarse</a>
                </div>
                
                <button type="submit" class="register-btn">Iniciar Sesión</button>
            </form>
        </div>
        
        <div class="promo-image">
            <img src="/assets/images/image.png" alt="Somos Generación FET">
        </div>
    </div>

    <!-- Modal de recuperación de contraseña -->
    <div id="modal-recuperar" class="modal">
        <div class="modal-content">
            <span class="close">&times;</span>
            <div class="logo">
                <img src="/assets/images/logofet.png" alt="FET Logo">
            </div>
            
            <!-- Paso 1: Verificación de identidad -->
            <div id="paso-verificacion">
                <h2>Recuperar Contraseña</h2>
                <p>Por favor, completa los siguientes campos para verificar tu identidad:</p>
                
                <form id="form-verificacion">
                    <div class="form-group">
                        <label for="email-recuperar">Email</label>
                        <input type="email" id="email-recuperar" name="email" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="documento">Número de Documento</label>
                        <input type="text" id="documento" name="documento" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="codigo-estudiante">Código de Estudiante</label>
                        <input type="text" id="codigo-estudiante" name="codigo_estudiante" required>
                    </div>
                    
                    <div id="mensaje-verificacion" class="mensaje"></div>
                    
                    <button type="submit" class="register-btn">Verificar</button>
                </form>
            </div>
            
            <!-- Paso 2: Cambio de contraseña -->
            <div id="paso-cambio-password" style="display: none;">
                <h2>Establecer Nueva Contraseña</h2>
                <p>Ingresa tu nueva contraseña:</p>
                
                <form id="form-cambio-password">
                    <input type="hidden" id="usuario-id" name="usuario_id">
                    
                    <div class="form-group">
                        <label for="nueva-password">Nueva Contraseña</label>
                        <input type="password" id="nueva-password" name="password" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="confirmar-password">Confirmar Contraseña</label>
                        <input type="password" id="confirmar-password" name="confirm_password" required>
                    </div>
                    
                    <div id="mensaje-cambio-password" class="mensaje"></div>
                    
                    <button type="submit" class="register-btn">Cambiar Contraseña</button>
                </form>
            </div>
            
            <!-- Paso 3: Confirmación -->
            <div id="paso-confirmacion" style="display: none;">
                <h2>¡Contraseña Actualizada!</h2>
                <p>Tu contraseña ha sido actualizada correctamente.</p>
                <button id="btn-volver-login" class="register-btn">Volver al Login</button>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Referencias a elementos del DOM
            const modal = document.getElementById('modal-recuperar');
            const btnForgotPassword = document.getElementById('forgot-password-link');
            const btnClose = document.querySelector('.close');
            const formVerificacion = document.getElementById('form-verificacion');
            const formCambioPassword = document.getElementById('form-cambio-password');
            const btnVolverLogin = document.getElementById('btn-volver-login');
            
            const pasoVerificacion = document.getElementById('paso-verificacion');
            const pasoCambioPassword = document.getElementById('paso-cambio-password');
            const pasoConfirmacion = document.getElementById('paso-confirmacion');
            
            const mensajeVerificacion = document.getElementById('mensaje-verificacion');
            const mensajeCambioPassword = document.getElementById('mensaje-cambio-password');
            
            // Abrir modal
            btnForgotPassword.addEventListener('click', function(e) {
                e.preventDefault();
                modal.style.display = 'block';
                
                // Resetear formularios y mensajes
                formVerificacion.reset();
                formCambioPassword.reset();
                mensajeVerificacion.innerHTML = '';
                mensajeCambioPassword.innerHTML = '';
                
                // Mostrar solo el primer paso
                pasoVerificacion.style.display = 'block';
                pasoCambioPassword.style.display = 'none';
                pasoConfirmacion.style.display = 'none';
            });
            
            // Cerrar modal
            btnClose.addEventListener('click', function() {
                modal.style.display = 'none';
            });
            
            // Cerrar modal al hacer clic fuera del contenido
            window.addEventListener('click', function(event) {
                if (event.target === modal) {
                    modal.style.display = 'none';
                }
            });
            
            // Función para manejar errores de fetch
            async function handleFetchResponse(response) {
                if (!response.ok) {
                    throw new Error(`Error HTTP: ${response.status}`);
                }
                
                const contentType = response.headers.get('content-type');
                if (!contentType || !contentType.includes('application/json')) {
                    // Si no es JSON, obtener el texto y mostrar un error
                    const text = await response.text();
                    console.error('Respuesta no JSON:', text);
                    throw new Error('La respuesta del servidor no es JSON válido');
                }
                
                return response.json();
            }
            
            // Enviar formulario de verificación
            formVerificacion.addEventListener('submit', function(e) {
                e.preventDefault();
                
                const formData = new FormData(formVerificacion);
                formData.append('action', 'verificar');
                
                // Mostrar mensaje de carga
                mensajeVerificacion.innerHTML = '<div class="mensaje-info">Verificando datos...</div>';
                
                fetch(window.location.href, {
                    method: 'POST',
                    body: formData
                })
                .then(handleFetchResponse)
                .then(data => {
                    if (data.success) {
                        // Si la verificación es exitosa, mostrar el formulario de cambio de contraseña
                        document.getElementById('usuario-id').value = data.usuario_id;
                        pasoVerificacion.style.display = 'none';
                        pasoCambioPassword.style.display = 'block';
                    } else {
                        // Mostrar mensaje de error
                        mensajeVerificacion.innerHTML = `<div class="mensaje-error">${data.mensaje}</div>`;
                    }
                })
                .catch(error => {
                    mensajeVerificacion.innerHTML = '<div class="mensaje-error">Error al procesar la solicitud: ' + error.message + '</div>';
                    console.error('Error detallado:', error);
                });
            });
            
            // Enviar formulario de cambio de contraseña
            formCambioPassword.addEventListener('submit', function(e) {
                e.preventDefault();
                
                const password = document.getElementById('nueva-password').value;
                const confirmPassword = document.getElementById('confirmar-password').value;
                const usuarioId = document.getElementById('usuario-id').value;
                
                // Validar que las contraseñas coincidan
                if (password !== confirmPassword) {
                    mensajeCambioPassword.innerHTML = '<div class="mensaje-error">Las contraseñas no coinciden.</div>';
                    return;
                }
                
                // Validar que el ID de usuario exista
                if (!usuarioId) {
                    mensajeCambioPassword.innerHTML = '<div class="mensaje-error">Error: ID de usuario no encontrado.</div>';
                    return;
                }
                
                // Mostrar mensaje de carga
                mensajeCambioPassword.innerHTML = '<div class="mensaje-info">Procesando solicitud...</div>';
                
                // Usar XMLHttpRequest en lugar de fetch para mejor manejo de errores
                const xhr = new XMLHttpRequest();
                xhr.open('POST', window.location.href, true);
                xhr.onreadystatechange = function() {
                    if (xhr.readyState === 4) {
                        if (xhr.status === 200) {
                            try {
                                const data = JSON.parse(xhr.responseText);
                                if (data.success) {
                                    // Si el cambio es exitoso, mostrar confirmación
                                    pasoCambioPassword.style.display = 'none';
                                    pasoConfirmacion.style.display = 'block';
                                } else {
                                    // Mostrar mensaje de error
                                    mensajeCambioPassword.innerHTML = `<div class="mensaje-error">${data.mensaje}</div>`;
                                }
                            } catch (e) {
                                console.error('Error al parsear JSON:', e);
                                console.error('Respuesta del servidor:', xhr.responseText);
                                mensajeCambioPassword.innerHTML = '<div class="mensaje-error">Error al procesar la respuesta del servidor.</div>';
                            }
                        } else {
                            mensajeCambioPassword.innerHTML = '<div class="mensaje-error">Error en la solicitud: ' + xhr.status + '</div>';
                        }
                    }
                };
                
                const formData = new FormData();
                formData.append('action', 'cambiar_password');
                formData.append('usuario_id', usuarioId);
                formData.append('password', password);
                formData.append('confirm_password', confirmPassword);
                xhr.send(formData);
            });
            
            // Volver al login después de cambiar la contraseña
            btnVolverLogin.addEventListener('click', function() {
                modal.style.display = 'none';
            });
        });
    </script>
</body>
</html>