<?php
// Definir la URL base del sitio
define('BASE_URL', '');  // Dejamos vacío para usar rutas relativas al dominio raíz

// Función para generar URLs absolutas
function url($path = '') {
    return '/' . ltrim($path, '/');
}

// Función para obtener la ruta base del proyecto
function base_path() {
    return __DIR__ . '/..';
}

// Función para incluir archivos de manera segura
function require_file($path) {
    $full_path = base_path() . '/' . ltrim($path, '/');
    if (file_exists($full_path)) {
        require_once $full_path;
    } else {
        error_log("Archivo no encontrado: " . $full_path);
    }
}
?> 