<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registro FET</title>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/registro.css">
    <script src="https://www.google.com/recaptcha/api.js" async defer></script>
</head>
<body>
    <div class="container">
        <form id="registroForm" method="POST" action="">
            <h2>Registro FET</h2>

            <?php if (isset($mensaje)) : ?>
                <div class="mensaje"><?php echo $mensaje; ?></div>
            <?php endif; ?>

            <div class="form-row">
                <div class="form-group">
                    <label for="rol">Seleccionar rol:</label>
                    <select id="rol" name="rol" required>
                        <option value="">Selecciona tu rol</option>
                        <option value="estudiante">Estudiante</option>
                        <option value="tutor">Tutor</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="nombre">Nombre completo:</label>
                    <input type="text" id="nombre" name="nombre" required>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="email">Correo institucional:</label>
                    <input type="email" id="email" name="email" required>
                </div>
                <div class="form-group">
                    <label for="documento">Número de documento:</label>
                    <input type="text" id="documento" name="documento" required>
                </div>
            </div>

            <div class="form-row estudiante-only" style="display: none;">
                <div class="form-group">
                    <label for="codigo_estudiante">Código de estudiante:</label>
                    <input type="text" id="codigo_estudiante" name="codigo_estudiante">
                </div>
                <div class="form-group">
                    <label for="carrera">Carrera:</label>
                    <select id="carrera" name="carrera" required>
                        <option value="">Seleccione una carrera</option>
                        <option value="ingenieria_software">Ingeniería de Software</option>
                        <option value="ingenieria_sistemas">Ingeniería de Sistemas</option>
                        <option value="ingenieria_industrial">Ingeniería Industrial</option>
                    </select>
                </div>
            </div>

            <div class="form-row estudiante-only" style="display: none;">
                <div class="form-group">
                    <label for="semestre">Semestre:</label>
                    <select id="semestre" name="semestre">
                        <option value="">Seleccione un semestre</option>
                        <?php for ($i = 1; $i <= 10; $i++) : ?>
                            <option value="<?php echo $i; ?>"><?php echo $i; ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="telefono">Teléfono:</label>
                    <input type="tel" id="telefono" name="telefono" required>
                </div>
            </div>

            <div class="form-row tutor-only" style="display: none;">
                <div class="form-group">
                    <label for="codigo_institucional">Código institucional:</label>
                    <input type="text" id="codigo_institucional" name="codigo_institucional">
                </div>
                <div class="form-group">
                    <label for="telefono">Teléfono:</label>
                    <input type="tel" id="telefono_tutor" name="telefono_tutor" required>
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
                <label for="terms">Acepto los términos y condiciones para el uso y tratamiento de datos personales.</label>
            </div>

            <button type="submit" class="btn-registro">Registrarse</button>

            <p class="login-link">¿Ya tienes cuenta? <a href="/views/general/login.php">Iniciar sesión</a></p>
        </form>
    </div>

    <script src="/assets/js/registro.js"></script>
</body>
</html>