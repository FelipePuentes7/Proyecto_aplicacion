<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Actividades - FET</title>
    <link rel="stylesheet" href="../../assets/css/actividades.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css" rel="stylesheet"/>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body>
<header class="bg-green-600 text-white p-4 flex justify-between items-center">
    <div class="flex items-center">
        <img alt="FET Logo" src="../../assets/img/logofet.png" class="h-12" height="100" width="100"/>
    </div>
    <nav class="flex space-x-4">
        <a class="hover:underline" href="#">Inicio</a>
        <a class="hover:underline" href="actividades.php">Actividades</a>
        <a class="hover:underline" href="#">Aula Virtual</a>
        <a class="hover:underline" href="#">Material de Apoyo</a>
    </nav>
    <div class="flex items-center space-x-4">
        <i class="fas fa-bell"></i>
        <img alt="User Avatar" class="h-10 w-10 rounded-full" height="40"
             src="https://storage.googleapis.com/a1aa/image/rGwRP1XDqncVs5Qe91jniYR4E9ipWdbNPtR2FDUEMUE.jpg"
             width="40"/>
    </div>
</header>
    <main class="main-content">
        <h1 class="page-title">Gestión de Actividades</h1>
        
        <div class="tabs">
            <button class="tab-button active">Pendientes</button>
            <button class="tab-button">Entregadas</button>
            <button class="tab-button">Calificadas</button>
            <button class="tab-button">Todas</button>
        </div>

        <div class="activities-list">
            <div class="activity-card">
                <div class="activity-icon">
                    <i class="fas fa-file-alt"></i>
                </div>
                <div class="activity-content">
                    <h3>Título de la actividad:</h3>
                    <p>Fecha límite: <span>15/04/2025</span></p>
                    <p>Estado: <span>Pendiente</span></p>
                    <div class="activity-description">
                        <p>Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua.</p>
                    </div>
                    <button class="confirm-button">Confirmar Entrega</button>
                </div>
            </div>

            <div class="activity-card">
                <div class="activity-icon">
                    <i class="fas fa-file-alt"></i>
                </div>
                <div class="activity-content">
                    <h3>Título de la actividad:</h3>
                    <p>Fecha límite: <span>20/04/2025</span></p>
                    <p>Estado: <span>Pendiente</span></p>
                </div>
            </div>

            <div class="activity-card">
                <div class="activity-icon">
                    <i class="fas fa-file-alt"></i>
                </div>
                <div class="activity-content">
                    <h3>Título de la actividad:</h3>
                    <p>Fecha límite: <span>25/04/2025</span></p>
                    <p>Estado: <span>Pendiente</span></p>
                </div>
            </div>

            <div class="activity-card">
                <div class="activity-icon">
                    <i class="fas fa-file-alt"></i>
                </div>
                <div class="activity-content">
                    <h3>Título de la actividad:</h3>
                    <p>Fecha límite: <span>30/04/2025</span></p>
                    <p>Estado: <span>Pendiente</span></p>
                </div>
            </div>
        </div>
    </main>

    <footer class="bg-green-600 text-white p-4 mt-8">
    <div class="flex flex-col md:flex-row justify-between items-center">
        <div class="text-center md:text-left">
            <h4 class="font-bold">Fundacion Escuela Tecnologica de Neiva</h4>
            <p>Email: soporte@universidad.edu</p>
            <p>Dirección: Calle 123, Bogotá</p>
            <p>Teléfono: +57 123 456 7890</p>
        </div>
        <img alt="Promotional Image" class="h-24 mt-4 md:mt-0" height="100" src="../../assets/img/image.png"
             width="150"/>
    </div>
    <div class="flex justify-center space-x-4 mt-4">
        <a href="#"><i class="fab fa-facebook-f"></i></a>
        <a href="#"><i class="fab fa-twitter"></i></a>
        <a href="#"><i class="fab fa-instagram"></i></a>
        <a href="#"><i class="fab fa-tiktok"></i></a>
    </div>
    <div class="text-center mt-4">
        <p class="font-bold">FET</p>
    </div>
</footer>
</body>
</html>