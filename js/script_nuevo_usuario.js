/**
 * script_nuevo_usuario.js
 * Registra usuario via PHP y también guarda en localStorage.
 * Así aparece en el catálogo inmediatamente.
 */

document.addEventListener('DOMContentLoaded', () => {
    const form         = document.getElementById('formNuevoUsuario');
    const correoInput  = document.getElementById('correo');
    const mensajeExito = document.getElementById('mensajeExito');

    form.addEventListener('submit', async function (event) {
        event.preventDefault();
        event.stopPropagation();

        let isValid = true;

        // Validar correo
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if (!emailRegex.test(correoInput.value)) {
            correoInput.classList.add('is-invalid');
            isValid = false;
        } else {
            correoInput.classList.remove('is-invalid');
            correoInput.classList.add('is-valid');
        }

        // Validar campos obligatorios
        form.querySelectorAll('[required]').forEach(input => {
            if (input.id === 'correo') return;
            if (!input.value.trim()) {
                input.classList.add('is-invalid');
                isValid = false;
            } else {
                input.classList.remove('is-invalid');
                input.classList.add('is-valid');
            }
        });

        if (!isValid) return;

        const btnGuardar = form.querySelector('button[type="submit"]');
        btnGuardar.disabled  = true;
        btnGuardar.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Guardando...';

        const payload = {
            nombre:   document.getElementById('nombre').value.trim(),
            rfc:      document.getElementById('rfc').value.trim(),
            correo:   correoInput.value.trim(),
            password: document.getElementById('password')?.value || 'Temporal123',
            rol:      document.getElementById('rol').value,
            telefono: document.getElementById('telefono')?.value.trim() || '',
        };

        let idGenerado = null;

        // Intentar guardar en el backend
        try {
            const res  = await fetch('api.php?action=registro', {
                method:  'POST',
                headers: { 'Content-Type': 'application/json' },
                body:    JSON.stringify(payload),
            });
            const data = await res.json();

            if (data.exito) {
                idGenerado = data.id;
            }
            // Si falla (correo duplicado etc.) mostramos el error
            if (!data.exito && data.mensaje) {
                let errorEl = document.getElementById('errorServidor');
                if (!errorEl) {
                    errorEl = document.createElement('div');
                    errorEl.id = 'errorServidor';
                    errorEl.className = 'alert alert-danger mt-3';
                    form.appendChild(errorEl);
                }
                errorEl.innerText = data.mensaje;
                btnGuardar.disabled  = false;
                btnGuardar.innerHTML = '<i class="fas fa-save me-2"></i>Guardar Usuario';
                return;
            }
        } catch (err) {
            console.warn('Backend no disponible, guardando en localStorage', err);
        }

        // Guardar en localStorage para que aparezca en el catálogo
        const usuarioLocal = {
            id:          idGenerado || Date.now(),
            id_usuario:  idGenerado || Date.now(),
            nombre:      payload.nombre,
            rfc:         payload.rfc,
            correo:      payload.correo,
            rol:         payload.rol,
            telefono:    payload.telefono,
            fecha_registro: new Date().toISOString(),
        };

        const local = localStorage.getItem('usuarios_registrados');
        const lista = local ? JSON.parse(local) : [];
        lista.push(usuarioLocal);
        localStorage.setItem('usuarios_registrados', JSON.stringify(lista));

        // Mostrar éxito y redirigir
        mensajeExito.classList.remove('d-none');
        mensajeExito.innerHTML =
            '<i class="fas fa-check-circle me-2"></i>Usuario registrado exitosamente. Redirigiendo...';
        form.querySelectorAll('button').forEach(btn => btn.disabled = true);

        setTimeout(() => {
            window.location.href = 'gestion_usuarios.html';
        }, 2000);
    });

    // Validar email en tiempo real
    correoInput.addEventListener('input', function () {
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if (this.value && !emailRegex.test(this.value)) {
            this.classList.add('is-invalid');
            this.classList.remove('is-valid');
        } else if (this.value) {
            this.classList.remove('is-invalid');
            this.classList.add('is-valid');
        } else {
            this.classList.remove('is-invalid', 'is-valid');
        }
    });
});
