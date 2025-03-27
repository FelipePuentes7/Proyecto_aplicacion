<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8"/>
    <meta content="width=device-width, initial-scale=1.0" name="viewport"/>
    <title>Seminario - Base de Datos Relacionales</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;700&display=swap" rel="stylesheet"/>
</head>
<body class="bg-[url('/assets/img/fondo.jpeg')] bg-cover bg-center bg-no-repeat">
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
<main class="p-4">
    <section class="text-center mb-8">
        <h2 class="text-2xl font-bold">Seminario - Base de Datos Relacionales</h2>
        <p class="mt-2">Llevas el 50% del curso, ¡sigue así! 😊</p>
        <div class="w-full bg-gray-300 rounded-full h-2.5 mt-2">
            <div class="bg-green-600 h-2.5 rounded-full" style="width: 50%"></div>
        </div>
    </section>
    <section class="mb-8">
        <h3 class="text-xl font-bold mb-4">Tu Próxima Clase Programada:</h3>
        <div class="bg-white p-4 rounded-lg shadow-md flex items-center justify-between">
            <img alt="Class Illustration" class="h-24" height="100"
                 src="https://storage.googleapis.com/a1aa/image/-dLyfR8b9FK_dnZRhbCATqBDNU9bIBYsBw0oNGdL-eA.jpg"
                 width="100"/>
            <div class="text-center">
                <p class="text-lg font-bold">Sabado 10 de enero a las 10 AM</p>
                <div class="flex justify-center space-x-2 mt-2">
                    <div class="bg-gray-200 p-2 rounded">
                        <p id="days" class="text-xl font-bold">00</p>
                        <p class="text-sm">Días</p>
                    </div>
                    <div class="bg-gray-200 p-2 rounded">
                        <p id="hours" class="text-xl font-bold">12</p>
                        <p class="text-sm">Horas</p>
                    </div>
                    <div class="bg-gray-200 p-2 rounded">
                        <p id="minutes" class="text-xl font-bold">16</p>
                        <p class="text-sm">Minutos</p>
                    </div>
                    <div class="bg-gray-200 p-2 rounded">
                        <p id="seconds" class="text-xl font-bold">45</p>
                        <p class="text-sm">Segundos</p>
                    </div>
                </div>
                <button class="bg-green-600 text-white px-4 py-2 rounded mt-4">Unirse a la Clase</button>
            </div>
        </div>
    </section>
    <section>
        <h3 class="text-xl font-bold mb-4">Actividades Pendientes:</h3>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div class="bg-white p-4 rounded-lg shadow-md">
                <h4 class="font-bold">(Título de la Actividad)</h4>
                <p class="mt-2">Fecha de Entrega:</p>
                <p class="mt-2">Descripción Breve:</p>
                <button class="bg-green-600 text-white px-4 py-2 rounded mt-4">Realizar Entrega</button>
            </div>
            <div class="bg-white p-4 rounded-lg shadow-md">
                <h4 class="font-bold">(Título de la Actividad)</h4>
                <p class="mt-2">Fecha de Entrega:</p>
                <p class="mt-2">Descripción Breve:</p>
                <button class="bg-green-600 text-white px-4 py-2 rounded mt-4">Realizar Entrega</button>
            </div>
            <div class="bg-white p-4 rounded-lg shadow-md">
                <h4 class="font-bold">(Título de la Actividad)</h4>
                <p class="mt-2">Fecha de Entrega:</p>
                <p class="mt-2">Descripción Breve:</p>
                <button class="bg-green-600 text-white px-4 py-2 rounded mt-4">Realizar Entrega</button>
            </div>
        </div>
    </section>
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

<script>
    
    const targetDate = new Date("2023-12-31T23:59:59").getTime();

    function updateCountdown() {
        const now = new Date().getTime();
        const distance = targetDate - now;

        if (distance > 0) {
            const days = Math.floor(distance / (1000 * 60 * 60 * 24));
            const hours = Math.floor((distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
            const minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
            const seconds = Math.floor((distance % (1000 * 60)) / 1000);

            document.getElementById("days").innerText = days.toString().padStart(2, '0');
            document.getElementById("hours").innerText = hours.toString().padStart(2, '0');
            document.getElementById("minutes").innerText = minutes.toString().padStart(2, '0');
            document.getElementById("seconds").innerText = seconds.toString().padStart(2, '0');
        } else {
            clearInterval(countdownInterval);
            document.getElementById("days").innerText = "00";
            document.getElementById("hours").innerText = "00";
            document.getElementById("minutes").innerText = "00";
            document.getElementById("seconds").innerText = "00";
        }
    }

    const countdownInterval = setInterval(updateCountdown, 1000);
    updateCountdown(); // Llamada inicial para mostrar el contador inmediatamente
</script>
</body>
</html>
