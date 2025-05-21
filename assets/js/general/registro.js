document.addEventListener('DOMContentLoaded', function() {
    const rolSelect = document.getElementById('rol');
    const estudianteFields = document.querySelectorAll('.estudiante-fields');
    const tutorFields = document.querySelectorAll('.tutor-fields');
    const opcionGradoSelect = document.getElementById('opcion_grado');
    const proyectoFields = document.querySelectorAll('.proyecto-fields');
    const pasantiaFields = document.querySelectorAll('.pasantia-fields');
    const form = document.getElementById('registroForm');
    const password = document.getElementById('password');
    const confirmPassword = document.getElementById('confirm_password');
    const passwordStrengthBar = document.querySelector('.strength-bar');
    const passwordStrengthText = document.querySelector('.strength-text');
    const passwordMatch = document.querySelector('.password-match');
    const emailInput = document.getElementById('email');
    const emailHint = document.querySelector('.email-hint');
    const termsCheckbox = document.getElementById('terms');
    const submitButton = document.querySelector('.btn-registro');
    const codigoEstudiante = document.getElementById('codigo_estudiante');

    // Crear contenedor para mensajes emergentes
    const createTooltip = () => {
        const tooltip = document.createElement('div');
        tooltip.className = 'tooltip';
        tooltip.style.display = 'none';
        tooltip.style.position = 'absolute';
        tooltip.style.backgroundColor = '#333';
        tooltip.style.color = 'white';
        tooltip.style.padding = '5px 10px';
        tooltip.style.borderRadius = '4px';
        tooltip.style.fontSize = '14px';
        tooltip.style.zIndex = '1000';
        tooltip.style.boxShadow = '0 2px 5px rgba(0,0,0,0.2)';
        tooltip.style.transition = 'opacity 0.3s ease';
        document.body.appendChild(tooltip);
        return tooltip;
    };

    // Crear tooltips para cada tipo de validación
    const emailTooltip = createTooltip();
    const passwordStrengthTooltip = createTooltip();
    const passwordMatchTooltip = createTooltip();
    const codigoEstudianteTooltip = createTooltip();

    // Función para mostrar tooltip
    const showTooltip = (element, tooltip, message, isError = true) => {
        const rect = element.getBoundingClientRect();
        tooltip.textContent = message;
        tooltip.style.backgroundColor = isError ? '#ff4d4d' : '#4dff4d';
        tooltip.style.top = `${rect.bottom + window.scrollY + 5}px`;
        tooltip.style.left = `${rect.left + window.scrollX}px`;
        tooltip.style.display = 'block';
        tooltip.style.opacity = '1';
        
        // Ocultar después de 3 segundos
        setTimeout(() => {
            tooltip.style.opacity = '0';
            setTimeout(() => {
                tooltip.style.display = 'none';
            }, 300);
        }, 3000);
    };

    // Ocultar mensajes de ayuda inicialmente
    emailHint.style.display = 'none';
    passwordStrengthText.style.display = 'none';
    passwordMatch.style.display = 'none';
    
    // Deshabilitar botón de registro inicialmente
    submitButton.disabled = true;
    submitButton.style.opacity = '0.5';
    submitButton.style.cursor = 'not-allowed';

    // Animación de entrada para el formulario
    form.style.opacity = 0;
    form.style.transform = 'translateY(20px)';
    setTimeout(() => {
        form.style.transition = 'opacity 0.5s ease, transform 0.5s ease';
        form.style.opacity = 1;
        form.style.transform = 'translateY(0)';
    }, 100);

    // Verificar si todos los campos requeridos están completos
    const checkFormValidity = () => {
        const requiredFields = form.querySelectorAll('input[required], select[required]');
        let isValid = true;
        
        requiredFields.forEach(field => {
            if (!field.value) {
                isValid = false;
            }
        });
        
        // Verificar campos específicos por rol
        if (rolSelect.value === 'estudiante') {
            const estudianteRequiredFields = document.querySelectorAll('.estudiante-fields input, .estudiante-fields select');
            estudianteRequiredFields.forEach(field => {
                if (!field.value) {
                    isValid = false;
                }
            });
            
            // Verificar que el código de estudiante comience con SOF (solo para estudiantes)
            if (codigoEstudiante && codigoEstudiante.value) {
                if (!codigoEstudiante.value.toUpperCase().startsWith('SOF')) {
                    isValid = false;
                }
            }
            
            // Verificar campos específicos por opción de grado
            if (opcionGradoSelect.value === 'proyecto') {
                const nombreProyecto = document.getElementById('nombre_proyecto');
                if (!nombreProyecto.value) {
                    isValid = false;
                }
            } else if (opcionGradoSelect.value === 'pasantia') {
                const nombreEmpresa = document.getElementById('nombre_empresa');
                if (!nombreEmpresa.value) {
                    isValid = false;
                }
            }
        } else {
            const tutorRequiredFields = document.querySelectorAll('.tutor-fields input:not(#codigo_institucional)');
            tutorRequiredFields.forEach(field => {
                if (!field.value) {
                    isValid = false;
                }
            });
        }
        
        // Verificar términos y condiciones
        if (!termsCheckbox.checked) {
            isValid = false;
        }
        
        // Verificar que las contraseñas coincidan
        if (password.value !== confirmPassword.value) {
            isValid = false;
        }
        
        // Verificar correo institucional
        if (!emailInput.value.endsWith('@fet.edu.co') && emailInput.value !== '') {
            isValid = false;
        }
        
        // Habilitar/deshabilitar botón
        submitButton.disabled = !isValid;
        submitButton.style.opacity = isValid ? '1' : '0.5';
        submitButton.style.cursor = isValid ? 'pointer' : 'not-allowed';
    };

    // Toggle campos según opción de grado
    const toggleGradoFields = () => {
        const opcionGrado = opcionGradoSelect.value;
        
        // Ocultar todos los campos específicos primero
        proyectoFields.forEach(field => {
            field.style.display = 'none';
            field.querySelectorAll('input').forEach(input => {
                input.required = false;
            });
        });
        
        pasantiaFields.forEach(field => {
            field.style.display = 'none';
            field.querySelectorAll('input').forEach(input => {
                input.required = false;
            });
        });
        
        // Mostrar campos según la opción seleccionada
        if (opcionGrado === 'proyecto') {
            proyectoFields.forEach(field => {
                field.style.display = 'flex';
                field.style.opacity = 1; // Establecer opacidad directamente a 1
                
                field.querySelectorAll('input').forEach(input => {
                    input.required = true;
                });
            });
        } else if (opcionGrado === 'pasantia') {
            pasantiaFields.forEach(field => {
                field.style.display = 'flex';
                field.style.opacity = 1; // Establecer opacidad directamente a 1
                
                field.querySelectorAll('input').forEach(input => {
                    input.required = true;
                });
            });
        }
        
        // Actualizar validez del formulario
        checkFormValidity();
    };

    // Toggle campos según rol con animación
    const toggleFields = () => {
        const isEstudiante = rolSelect.value === 'estudiante';
        
        estudianteFields.forEach(field => {
            if (isEstudiante) {
                field.style.display = 'flex';
                field.style.opacity = 0;
                setTimeout(() => {
                    field.style.transition = 'opacity 0.3s ease';
                    field.style.opacity = 1;
                }, 10);
            } else {
                field.style.opacity = 0;
                setTimeout(() => {
                    field.style.display = 'none';
                }, 300);
            }
            
            field.querySelectorAll('input, select').forEach(input => {
                input.required = isEstudiante;
            });
        });

        tutorFields.forEach(field => {
            if (!isEstudiante) {
                field.style.display = 'flex';
                field.style.opacity = 0;
                setTimeout(() => {
                    field.style.transition = 'opacity 0.3s ease';
                    field.style.opacity = 1;
                }, 10);
            } else {
                field.style.opacity = 0;
                setTimeout(() => {
                    field.style.display = 'none';
                }, 300);
            }
            
            field.querySelectorAll('input:not(#codigo_institucional), select').forEach(input => {
                input.required = !isEstudiante;
            });
        });
        
        // Si no es estudiante, ocultar campos de proyecto y pasantía
        if (!isEstudiante) {
            proyectoFields.forEach(field => {
                field.style.display = 'none';
            });
            
            pasantiaFields.forEach(field => {
                field.style.display = 'none';
            });
        } else {
            // Si es estudiante, verificar la opción de grado actual
            setTimeout(toggleGradoFields, 350); // Esperar a que termine la animación de los campos de estudiante
        }
        
        // Actualizar validez del formulario después de cambiar los campos
        setTimeout(checkFormValidity, 400);
        
        // Actualizar la validación del código de estudiante según el rol
        if (codigoEstudiante) {
            if (isEstudiante) {
                // Activar validación para estudiantes
                validateCodigoEstudiante();
            } else {
                // Desactivar validación para tutores
                codigoEstudiante.setCustomValidity('');
                codigoEstudiante.classList.remove('invalid');
            }
        }
    };

    // Validación de fortaleza de contraseña
    const checkPasswordStrength = (password) => {
        let strength = 0;
        
        // Si la contraseña tiene 8 o más caracteres, suma puntos
        if (password.length >= 8) strength += 1;
        
        // Si la contraseña tiene letras minúsculas y mayúsculas, suma puntos
        if (password.match(/([a-z].*[A-Z])|([A-Z].*[a-z])/)) strength += 1;
        
        // Si la contraseña tiene números, suma puntos
        if (password.match(/([0-9])/)) strength += 1;
        
        // Si la contraseña tiene caracteres especiales, suma puntos
        if (password.match(/([!,%,&,@,#,$,^,*,?,_,~])/)) strength += 1;
        
        // Actualizar la barra de fortaleza
        passwordStrengthBar.style.width = (strength * 25) + '%';
        
        // Mostrar tooltip con la fortaleza de la contraseña
        if (password.length > 0) {
            let message = '';
            let isError = true;
            
            switch (strength) {
                case 0:
                    passwordStrengthBar.style.backgroundColor = '#ff4d4d';
                    message = 'Seguridad: Muy débil';
                    break;
                case 1:
                    passwordStrengthBar.style.backgroundColor = '#ffa64d';
                    message = 'Seguridad: Débil';
                    break;
                case 2:
                    passwordStrengthBar.style.backgroundColor = '#ffff4d';
                    message = 'Seguridad: Media';
                    isError = false;
                    break;
                case 3:
                    passwordStrengthBar.style.backgroundColor = '#4dff4d';
                    message = 'Seguridad: Fuerte';
                    isError = false;
                    break;
                case 4:
                    passwordStrengthBar.style.backgroundColor = '#4d4dff';
                    message = 'Seguridad: Muy fuerte';
                    isError = false;
                    break;
            }
            
            showTooltip(password, passwordStrengthTooltip, message, isError);
        }
        
        checkFormValidity();
    };

    // Función para validar el código de estudiante
    const validateCodigoEstudiante = () => {
        if (!codigoEstudiante) return;
        
        const valor = codigoEstudiante.value.trim();
        const isEstudiante = rolSelect.value === 'estudiante';
        
        // Solo validar si es estudiante y hay un valor
        if (isEstudiante && valor) {
            if (!valor.toUpperCase().startsWith('SOF')) {
                codigoEstudiante.setCustomValidity('El código debe comenzar con "SOF"');
                codigoEstudiante.classList.add('invalid');
                showTooltip(codigoEstudiante, codigoEstudianteTooltip, 'El código debe comenzar con "SOF"', true);
            } else {
                codigoEstudiante.setCustomValidity('');
                codigoEstudiante.classList.remove('invalid');
                showTooltip(codigoEstudiante, codigoEstudianteTooltip, 'Código válido', false);
            }
        } else {
            // Si no es estudiante o no hay valor, no validar
            codigoEstudiante.setCustomValidity('');
            codigoEstudiante.classList.remove('invalid');
        }
    };

    // Validación en tiempo real de contraseña
    password.addEventListener('input', () => {
        checkPasswordStrength(password.value);
        
        if (confirmPassword.value !== '') {
            if (password.value !== confirmPassword.value) {
                confirmPassword.setCustomValidity('Las contraseñas no coinciden');
                showTooltip(confirmPassword, passwordMatchTooltip, '❌ Las contraseñas no coinciden', true);
            } else {
                confirmPassword.setCustomValidity('');
                showTooltip(confirmPassword, passwordMatchTooltip, '✅ Las contraseñas coinciden', false);
            }
        }
        
        checkFormValidity();
    });

    confirmPassword.addEventListener('input', () => {
        if (confirmPassword.value !== '') {
            if (password.value !== confirmPassword.value) {
                confirmPassword.setCustomValidity('Las contraseñas no coinciden');
                showTooltip(confirmPassword, passwordMatchTooltip, '❌ Las contraseñas no coinciden', true);
            } else {
                confirmPassword.setCustomValidity('');
                showTooltip(confirmPassword, passwordMatchTooltip, '✅ Las contraseñas coinciden', false);
            }
        }
        
        checkFormValidity();
    });

    // Validación de correo institucional
    emailInput.addEventListener('input', () => {
        if (emailInput.value !== '') {
            if (!emailInput.value.endsWith('@fet.edu.co')) {
                emailInput.setCustomValidity('El correo debe terminar en @fet.edu.co');
                showTooltip(emailInput, emailTooltip, 'El correo debe terminar en @fet.edu.co', true);
            } else {
                emailInput.setCustomValidity('');
                showTooltip(emailInput, emailTooltip, 'Correo válido', false);
            }
        }
        
        checkFormValidity();
    });

    // Validación del código de estudiante
    if (codigoEstudiante) {
        codigoEstudiante.addEventListener('input', () => {
            validateCodigoEstudiante();
            checkFormValidity();
        });
    }

    // Verificar términos y condiciones
    termsCheckbox.addEventListener('change', checkFormValidity);

    // Efecto de animación en los campos al hacer focus
    const inputs = document.querySelectorAll('input, select');
    inputs.forEach(input => {
        input.addEventListener('focus', () => {
            input.parentElement.classList.add('focused');
        });
        
        input.addEventListener('blur', () => {
            if (input.value === '') {
                input.parentElement.classList.remove('focused');
            }
        });
        
        // Inicializar estado para campos con valor
        if (input.value !== '') {
            input.parentElement.classList.add('focused');
        }
        
        // Agregar evento input para verificar validez del formulario
        input.addEventListener('input', checkFormValidity);
    });

    // Event listeners
    rolSelect.addEventListener('change', toggleFields);
    
    // Importante: usar un evento que no se dispare automáticamente
    opcionGradoSelect.addEventListener('change', function() {
        // Usar setTimeout para asegurar que el cambio persista
        setTimeout(toggleGradoFields, 50);
    });
    
    form.addEventListener('submit', function(e) {
        if (password.value !== confirmPassword.value) {
            e.preventDefault();
            alert('Las contraseñas no coinciden');
        }
        
        if (!emailInput.value.endsWith('@fet.edu.co')) {
            e.preventDefault();
            alert('El correo debe terminar en @fet.edu.co');
        }
        
        // Validar campos específicos según opción de grado
        if (rolSelect.value === 'estudiante') {
            // Validar que el código de estudiante comience con SOF (solo para estudiantes)
            if (codigoEstudiante && codigoEstudiante.value) {
                if (!codigoEstudiante.value.toUpperCase().startsWith('SOF')) {
                    e.preventDefault();
                    alert('El código de estudiante debe comenzar con "SOF"');
                }
            }
            
            if (opcionGradoSelect.value === 'proyecto') {
                const nombreProyecto = document.getElementById('nombre_proyecto');
                if (!nombreProyecto.value) {
                    e.preventDefault();
                    alert('Debe ingresar el nombre del proyecto');
                }
            } else if (opcionGradoSelect.value === 'pasantia') {
                const nombreEmpresa = document.getElementById('nombre_empresa');
                if (!nombreEmpresa.value) {
                    e.preventDefault();
                    alert('Debe ingresar el nombre de la empresa');
                }
            }
        }
    });

    // Inicializar campos al cargar
    toggleFields();
    
    // Ocultar el campo de código institucional para tutores
    const codigoInstitucionalField = document.getElementById('codigo_institucional');
    if (codigoInstitucionalField) {
        codigoInstitucionalField.parentElement.style.display = 'none';
    }
    
    // Inicializar campos de opción de grado si ya hay un valor seleccionado
    if (opcionGradoSelect.value) {
        // Usar setTimeout para asegurar que se ejecute después de que el DOM esté completamente cargado
        setTimeout(toggleGradoFields, 500);
    }
});