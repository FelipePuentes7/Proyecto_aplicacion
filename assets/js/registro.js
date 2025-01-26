document.addEventListener('DOMContentLoaded', function() {
    const rolSelect = document.getElementById('rol');
    const estudianteFields = document.querySelectorAll('.estudiante-only');
    const tutorFields = document.querySelectorAll('.tutor-only');
    const form = document.getElementById('registroForm');
    const password = document.getElementById('password');
    const confirmPassword = document.getElementById('confirm_password');

    // Toggle campos según rol
    const toggleFields = () => {
        const isEstudiante = rolSelect.value === 'estudiante';
        
        estudianteFields.forEach(field => {
            field.style.display = isEstudiante ? 'flex' : 'none';
            field.querySelectorAll('input, select').forEach(input => {
                input.required = isEstudiante;
            });
        });

        tutorFields.forEach(field => {
            field.style.display = !isEstudiante ? 'flex' : 'none';
            field.querySelectorAll('input, select').forEach(input => {
                input.required = !isEstudiante;
            });
        });
    };

    // Validación en tiempo real de contraseña
    confirmPassword.addEventListener('input', () => {
        if (password.value !== confirmPassword.value) {
            confirmPassword.setCustomValidity('Las contraseñas no coinciden');
        } else {
            confirmPassword.setCustomValidity('');
        }
    });

    // Event listeners
    rolSelect.addEventListener('change', toggleFields);
    form.addEventListener('submit', function(e) {
        if (password.value !== confirmPassword.value) {
            e.preventDefault();
            alert('Las contraseñas no coinciden');
        }
    });

    // Inicializar campos al cargar
    toggleFields();
});