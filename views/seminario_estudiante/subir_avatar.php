<?php
// Incluir archivo de conexión
require_once '../../config/conexion.php';

// Iniciar sesión
session_start();

// Verificar si el usuario está logueado
if (!isset($_SESSION['usuario']) || !isset($_SESSION['usuario']['id'])) {
    // Si no hay sesión, redirigir al login
    header("Location: /views/seminario_estudiante/login.php");
    exit();
}

// Obtener ID del usuario de la sesión
$usuario_id = $_SESSION['usuario']['id'];

// Crear instancia de conexión
$conexion = new Conexion();
$db = $conexion->getConexion();

// Mensaje para mostrar al usuario
$mensaje = '';
$tipo_mensaje = '';



// Procesar el formulario si se ha enviado
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verificar si se seleccionó un avatar predefinido
    if (isset($_POST['avatar_predefinido']) && !empty($_POST['avatar_predefinido'])) {
        try {
            $avatar_url = $_POST['avatar_predefinido'];
            
            // Actualizar el avatar en la base de datos
            $stmt = $db->prepare("UPDATE usuarios SET avatar = :avatar WHERE id = :usuario_id");
            $stmt->bindParam(':avatar', $avatar_url);
            $stmt->bindParam(':usuario_id', $usuario_id);
            
            if ($stmt->execute()) {
                // Actualizar la sesión
                $_SESSION['usuario']['avatar'] = $avatar_url;
                
                // Forzar la actualización de la sesión
                session_write_close();
                session_start();
                
                // Agregar un pequeño retraso para asegurar que la base de datos se actualice
                sleep(1);
                
                $mensaje = "¡Avatar actualizado correctamente! Serás redirigido en unos segundos...";
                $tipo_mensaje = "success";
            } else {
                $mensaje = "Error al actualizar el avatar.";
                $tipo_mensaje = "danger";
            }
        } catch (PDOException $e) {
            $mensaje = "Error en la base de datos: " . $e->getMessage();
            $tipo_mensaje = "danger";
        }
    }
    // Verificar si se subió un archivo
    elseif (isset($_FILES['avatar_file']) && $_FILES['avatar_file']['error'] === UPLOAD_ERR_OK) {
        $file_tmp = $_FILES['avatar_file']['tmp_name'];
        $file_name = $_FILES['avatar_file']['name'];
        $file_size = $_FILES['avatar_file']['size'];
        $file_type = $_FILES['avatar_file']['type'];
        
        // Verificar el tipo de archivo
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
        if (!in_array($file_type, $allowed_types)) {
            $mensaje = "Tipo de archivo no permitido. Solo se permiten imágenes JPG, PNG y GIF.";
            $tipo_mensaje = "danger";
        }
        // Verificar el tamaño del archivo (máximo 2MB)
        elseif ($file_size > 2 * 1024 * 1024) {
            $mensaje = "El archivo es demasiado grande. El tamaño máximo permitido es 2MB.";
            $tipo_mensaje = "danger";
        }
        else {
            // Crear directorio para los avatares si no existe
            $upload_dir = "../../uploads/avatars/";
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            // Generar un nombre único para el archivo
            $file_extension = pathinfo($file_name, PATHINFO_EXTENSION);
            $new_file_name = uniqid() . '_' . $usuario_id . '.' . $file_extension;
            $upload_path = $upload_dir . $new_file_name;
            
            // Mover el archivo subido al directorio de destino
            if (move_uploaded_file($file_tmp, $upload_path)) {
                try {
                    // Actualizar el avatar en la base de datos
                    $avatar_url = '/uploads/avatars/' . $new_file_name;
                    $stmt = $db->prepare("UPDATE usuarios SET avatar = :avatar WHERE id = :usuario_id");
                    $stmt->bindParam(':avatar', $avatar_url);
                    $stmt->bindParam(':usuario_id', $usuario_id);
                    
                    if ($stmt->execute()) {
                        // Actualizar la sesión
                        $_SESSION['usuario']['avatar'] = $avatar_url;
                        
                        // Forzar la actualización de la sesión
                        session_write_close();
                        session_start();
                        
                        $mensaje = "¡Avatar subido y actualizado correctamente!";
                        $tipo_mensaje = "success";
                    } else {
                        $mensaje = "Error al actualizar el avatar en la base de datos.";
                        $tipo_mensaje = "danger";
                    }
                } catch (PDOException $e) {
                    $mensaje = "Error en la base de datos: " . $e->getMessage();
                    $tipo_mensaje = "danger";
                }
            } else {
                $mensaje = "Error al subir el archivo.";
                $tipo_mensaje = "danger";
            }
        }
    } else {
        $mensaje = "Por favor, selecciona un avatar predefinido o sube una imagen.";
        $tipo_mensaje = "warning";
    }
}

// Obtener el avatar actual del usuario
try {
    $stmt = $db->prepare("SELECT avatar FROM usuarios WHERE id = :usuario_id");
    $stmt->bindParam(':usuario_id', $usuario_id);
    $stmt->execute();
    $avatar_actual = $stmt->fetchColumn();
} catch (PDOException $e) {
    $avatar_actual = null;
}

// Avatares predefinidos relacionados con software y tecnología
$avatares_predefinidos = [
    [
        'url' => 'https://cdn-icons-png.flaticon.com/512/2721/2721620.png',
        'name' => 'Programador'
    ],
    [
        'url' => 'https://cdn-icons-png.flaticon.com/512/2721/2721623.png',
        'name' => 'Desarrolladora'
    ],
    [
        'url' => 'https://cdn-icons-png.flaticon.com/512/2721/2721629.png',
        'name' => 'Ingeniero de Software'
    ],
    [
        'url' => 'https://cdn-icons-png.flaticon.com/512/4319/4319132.png',
        'name' => 'Desarrollador Web'
    ],
    [
        'url' => 'https://cdn-icons-png.flaticon.com/512/2721/2721631.png',
        'name' => 'Analista de Datos'
    ],
    [
        'url' => 'https://cdn-icons-png.flaticon.com/512/1995/1995539.png',
        'name' => 'Diseñador UX/UI'
    ],
    [
        'url' => 'https://cdn-icons-png.flaticon.com/512/2721/2721626.png',
        'name' => 'Ingeniera de Sistemas'
    ],
    [
        'url' => 'https://cdn-icons-png.flaticon.com/512/4319/4319128.png',
        'name' => 'Desarrollador Móvil'
    ],
    [
        'url' => 'https://cdn-icons-png.flaticon.com/512/2721/2721622.png',
        'name' => 'Experto en Ciberseguridad'
    ],
    [
        'url' => 'https://cdn-icons-png.flaticon.com/512/4319/4319130.png',
        'name' => 'Científica de Datos'
    ],
    [
        'url' => 'https://cdn-icons-png.flaticon.com/512/2721/2721624.png',
        'name' => 'Administrador de Sistemas'
    ],
    [
        'url' => 'https://cdn-icons-png.flaticon.com/512/4319/4319131.png',
        'name' => 'Desarrolladora Frontend'
    ]
];
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cambiar Avatar - FET</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #00a63d;
            --primary-light: #00c44b;
            --primary-dark: #008f34;
            --secondary: #f8f9fa;
            --success: #28a745;
            --danger: #dc3545;
            --warning: #ffc107;
            --info: #17a2b8;
            --dark: #343a40;
            --light: #f8f9fa;
        }
        
        body {
            font-family: 'Roboto', sans-serif;
            margin: 0;
            padding: 0;
            background-color: #f5f5f5;
        }
        
        .header {
            background: linear-gradient(135deg, var(--primary) 0%, #8dc63f 100%);
            color: white;
            padding: 10px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .logo {
            height: 40px;
        }
        
        .nav-links {
            display: flex;
            gap: 20px;
        }
        
        .nav-links a {
            color: white;
            text-decoration: none;
            font-weight: 500;
            padding: 5px 10px;
            border-radius: 5px;
            transition: all 0.3s;
        }
        
        .nav-links a:hover, .nav-links a.active {
            background-color: rgba(255,255,255,0.2);
        }
        
        .user-profile {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .notification-icon {
            font-size: 1.2rem;
            cursor: pointer;
        }
        
        .avatar {
            width: 35px;
            height: 35px;
            border-radius: 50%;
            object-fit: cover;
            cursor: pointer;
        }
        
        .main-content {
            padding: 20px;
            max-width: 1200px;
            margin: 0 auto;
        }
        
        .page-title {
            font-size: 1.8rem;
            font-weight: 700;
            margin-bottom: 20px;
            color: var(--dark);
            display: flex;
            align-items: center;
        }
        
        .page-title i {
            margin-right: 10px;
            color: var(--primary);
        }
        
        .breadcrumb {
            background-color: transparent;
            padding: 0;
            margin-bottom: 20px;
        }
        
        .breadcrumb-item a {
            color: var(--primary);
            text-decoration: none;
        }
        
        .breadcrumb-item.active {
            color: var(--dark);
        }
        
        .card {
            border: none;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        
        .card-header {
            background-color: white;
            border-bottom: 1px solid #eee;
            padding: 15px 20px;
            font-weight: 500;
            display: flex;
            align-items: center;
        }
        
        .card-header i {
            margin-right: 10px;
            color: var(--primary);
        }
        
        .avatar-current {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            object-fit: cover;
            margin: 0 auto 20px;
            display: block;
            border: 3px solid var(--primary);
        }
        
        .avatar-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
            gap: 15px;
            margin-top: 20px;
        }
        
        .avatar-option {
            text-align: center;
            cursor: pointer;
            transition: all 0.3s;
            border-radius: 10px;
            padding: 10px;
        }
        
        .avatar-option:hover {
            background-color: #f8f9fa;
            transform: translateY(-5px);
        }
        
        .avatar-option.selected {
            background-color: rgba(0, 166, 61, 0.1);
            border: 2px solid var(--primary);
        }
        
        .avatar-option img {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            object-fit: cover;
            margin-bottom: 10px;
        }
        
        .avatar-option p {
            margin: 0;
            font-size: 0.9rem;
            color: var(--dark);
        }
        
        .upload-section {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #eee;
        }
        
        .btn-primary {
            background-color: var(--primary);
            border-color: var(--primary);
        }
        
        .btn-primary:hover {
            background-color: var(--primary-dark);
            border-color: var(--primary-dark);
        }
        
        .footer {
            background: linear-gradient(135deg, var(--primary) 0%, #8dc63f 100%);
            color: white;
            padding: 30px 20px;
            margin-top: 50px;
        }
        
        .footer-content {
            max-width: 1200px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
        }
        
        .footer-info h3 {
            margin-top: 0;
            margin-bottom: 10px;
        }
        
        .footer-info p {
            margin: 5px 0;
            font-size: 0.9rem;
        }
        
        .footer-image {
            max-width: 150px;
        }
        
        .social-links {
            display: flex;
            gap: 15px;
            margin-top: 15px;
        }
        
        .social-links a {
            color: white;
            font-size: 1.2rem;
            transition: opacity 0.3s;
        }
        
        .social-links a:hover {
            opacity: 0.8;
        }
        
        @media (max-width: 768px) {
            .nav-links {
                display: none;
            }
            
            .avatar-grid {
                grid-template-columns: repeat(auto-fill, minmax(100px, 1fr));
            }
            
            .footer-content {
                flex-direction: column;
                text-align: center;
                gap: 20px;
            }
            
            .social-links {
                justify-content: center;
            }
        }

        .avatar-options {
            display: flex;
            flex-wrap: wrap;
            justify-content: space-around;
            margin-bottom: 20px;
            gap: 10px;
        }
        .avatar-option {
            width: 100px;
            height: 100px;
            border: 2px solid transparent;
            border-radius: 50%;
            margin: 10px 5px;
            cursor: pointer;
            background-size: cover;
            background-position: center;
            transition: border-color 0.2s, box-shadow 0.2s;
        }
        .avatar-option.selected {
            border-color: #007bff;
            box-shadow: 0 0 0 3px #007bff33;
        }
    </style>
</head>
<body>
    <header class="header">
        <img src="../../assets/images/logofet.png" alt="FET Logo" class="logo">
        
        <nav class="nav-links">
            <a href="inicio_estudiantes.php">Inicio</a>
            <a href="actividades.php">Actividades</a>
            <a href="aula_virtual.php">Aula Virtual</a>
            <a href="material_apoyo.php">Material de Apoyo</a>
        </nav>
        
        <div class="user-profile">
            <?php if (!empty($_SESSION['usuario']['avatar'])): ?>
                <img src="<?php echo htmlspecialchars($_SESSION['usuario']['avatar']); ?>" alt="Avatar" class="avatar">
            <?php else: ?>
                <span class="avatar" style="
                    display: flex; align-items: center; justify-content: center;
                    background: #e9ecef; color: #adb5bd; font-size: 1.3rem;
                    width: 35px; height: 35px; border-radius: 50%;
                ">
                    <i class="fas fa-user"></i>
                </span>
            <?php endif; ?>
        </div>
    </header>
    
    <main class="main-content">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="inicio_estudiantes.php">Inicio</a></li>
                <li class="breadcrumb-item active" aria-current="page">Cambiar Avatar</li>
            </ol>
        </nav>
        
        <?php if (!empty($mensaje)): ?>
            <div class="alert alert-<?php echo $tipo_mensaje; ?> alert-dismissible fade show" role="alert">
                <?php echo $mensaje; ?>
                <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
        <?php endif; ?>
        
        <h1 class="page-title">
            <i class="fas fa-user-circle"></i>
            Cambiar Avatar
        </h1>
                    <div class="text-center">

        <div class="row">
            <div class="col-lg-8 mx-auto">
                <div class="card">
                    <div class="card-header">
                        <i class="fas fa-image"></i> Selecciona tu nuevo avatar
                    </div>
                    <div class="card-body">
                        <h5 class="text-center mb-4">Avatar actual</h5>
                            
                        <?php if (!empty($avatar_actual)): ?>
                            <img src="<?php echo htmlspecialchars($avatar_actual); ?>" alt="Avatar actual" class="avatar-current">
                        <?php else: ?>
                            <div class="avatar-current d-flex align-items-center justify-content-center bg-light">
                                <i class="fas fa-user fa-3x text-muted"></i>
                            </div>
                        <?php endif; ?>
                        
                        <form action="" method="POST" enctype="multipart/form-data">
                            <h5 class="mt-4">O elige uno de nuestros avatares predefinidos</h5>
                            <div class="avatar-options">
                                <?php foreach ($avatares_predefinidos as $avatar): ?>
                                    <div class="avatar-option"
                                        data-value="<?php echo htmlspecialchars($avatar['url']); ?>"
                                        style="background-image: url('<?php echo htmlspecialchars($avatar['url']); ?>');"
                                        title="<?php echo htmlspecialchars($avatar['name']); ?>">
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <input type="hidden" id="avatar_predefinido" name="avatar_predefinido" value="">
                            
                            <div class="upload-section">
                                <h5>O sube tu propia imagen</h5>
                                
                                <div class="form-group">
                                    <div class="custom-file">
                                        <input type="file" class="custom-file-input" id="avatar_file" name="avatar_file" accept="image/jpeg,image/png,image/gif">
                                        <label class="custom-file-label" for="avatar_file">Seleccionar archivo</label>
                                    </div>
                                    <small class="form-text text-muted">Formatos permitidos: JPG, PNG, GIF. Tamaño máximo: 2MB.</small>
                                </div>
                            </div>
                            
                            <div class="text-center mt-4">
                                <a href="inicio_estudiantes.php" class="btn btn-secondary mr-2">Cancelar</a>
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save mr-1"></i> Guardar Avatar
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </main>
    
    <footer class="footer">
        <div class="footer-content">
            <div class="footer-info">
              <p>Email: direccion_software@fet.edu.co</p>
                <p>Dirección: Kilómetro 12, via Neiva – Rivera</p>
                <p>Teléfono: 6088674935 – (+57) 3223041567</p>
                
                <div class="social-links">
                    <a href="https://www.facebook.com/YoSoyFet" target="_blank"><i class="fab fa-facebook"></i></a>
                    <a href="https://twitter.com/yosoyfet" target="_blank"><i class="fab fa-twitter"></i></a>
                    <a href="https://www.instagram.com/fetneiva" target="_blank"><i class="fab fa-instagram"></i></a>
                    <a href="https://www.youtube.com/channel/UCv647ftA-d--0F02AqF7eng" target="_blank"><i class="fab fa-youtube"></i></a>
                </div>
            </div>
            
            <img src="../../assets/images/logofet.png" alt="FET Logo" class="footer-image">
        </div>
    </footer>
    
    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <script>
        document.addEventListener("DOMContentLoaded", function() {
            const avatarOptions = document.querySelectorAll(".avatar-option");
            const avatarPredefinidoInput = document.getElementById("avatar_predefinido");
            const avatarFileInput = document.getElementById("avatar_file");
            const fileLabel = document.querySelector(".custom-file-label");
            const avatarPreview = document.querySelector(".avatar-current");

            function updatePreview(imageUrl) {
                if (avatarPreview.tagName === 'IMG') {
                    avatarPreview.src = imageUrl;
                }
            }

            avatarFileInput.addEventListener('change', function(e) {
                if (this.files && this.files[0]) {
                    var fileName = this.files[0].name;
                    fileLabel.textContent = fileName;
                    avatarOptions.forEach(option => option.classList.remove('selected'));
                    avatarPredefinidoInput.value = '';
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        updatePreview(e.target.result);
                    };
                    reader.readAsDataURL(this.files[0]);
                }
            });

            avatarOptions.forEach(function(option) {
                option.addEventListener('click', function() {
                    avatarOptions.forEach(opt => opt.classList.remove('selected'));
                    this.classList.add('selected');
                    const avatarUrl = this.dataset.value;
                    avatarPredefinidoInput.value = avatarUrl;
                    updatePreview(avatarUrl);
                    avatarFileInput.value = '';
                    fileLabel.textContent = 'Seleccionar archivo';
                });
            });

            document.querySelector('form').addEventListener('submit', function(e) {
                if (avatarPredefinidoInput.value === '' && avatarFileInput.value === '') {
                    e.preventDefault();
                    alert('Por favor, selecciona un avatar predefinido o sube una imagen.');
                }
            });
        });
    </script>
    <script>
    // Forzar recarga de imágenes de avatar para evitar caché
    document.addEventListener('DOMContentLoaded', function() {
        const avatarImages = document.querySelectorAll('.avatar, .avatar-current');
        avatarImages.forEach(img => {
            if (img.tagName === 'IMG') {
                const src = img.src;
                img.src = src.includes('?') ? 
                    src.split('?')[0] + '?t=' + new Date().getTime() : 
                    src + '?t=' + new Date().getTime();
            }
        });
        
        // Si hay un mensaje de éxito, preparar redirección
        <?php if ($tipo_mensaje === 'success'): ?>
        setTimeout(function() {
            // Redirigir a la página de inicio después de 2 segundos
            window.location.href = 'inicio_estudiantes.php?avatar_updated=' + new Date().getTime();
        }, 2000);
        <?php endif; ?>
    });
</script>
</body>
</html>
