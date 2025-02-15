document.addEventListener('DOMContentLoaded', function() {
    const rolSelect = document.getElementById('rol');
    const estudianteFields = document.querySelectorAll('.estudiante-only');
    const tutorFields = document.querySelectorAll('.tutor-only');
    const passwordInput = document.getElementById('password');
    const confirmPasswordInput = document.getElementById('confirm_password');
    const form = document.getElementById('registroForm');

    rolSelect.addEventListener('change', function() {
        const isEstudiante = this.value === 'estudiante';
        const isTutor = this.value === 'tutor';

        // Mostrar u ocultar campos según el rol seleccionado
        estudianteFields.forEach(field => {
            field.style.display = isEstudiante ? 'flex' : 'none';
        });

        tutorFields.forEach(field => {
            field.style.display = isTutor ? 'flex' : 'none';
        });
    });

    form.addEventListener('submit', function(e) {
        // Validar que las contraseñas coincidan
        if (passwordInput.value !== confirmPasswordInput.value) {
            e.preventDefault();
            alert('Las contraseñas no coinciden');
        }
    });
});