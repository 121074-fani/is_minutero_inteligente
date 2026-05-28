// Storage seguro para Edge
const safeStorage = {
    set(k,v){try{localStorage.setItem(k,v)}catch(e){try{sessionStorage.setItem(k,v)}catch(e2){window._mem=window._mem||{};window._mem[k]=v}}},
    get(k){try{return localStorage.getItem(k)}catch(e){try{return sessionStorage.getItem(k)}catch(e2){return window._mem?.[k]??null}}},
    remove(k){try{localStorage.removeItem(k)}catch(e){}try{sessionStorage.removeItem(k)}catch(e){}if(window._mem)delete window._mem[k]}
};

document.addEventListener('DOMContentLoaded', () => {
    document.getElementById('authForm').addEventListener('submit', async function(e) {
        e.preventDefault();
        const correo   = this.correo.value.trim();
        const password = this.password.value;
        const msgEl    = document.getElementById('mensajeForm');
        const btn      = document.getElementById('submitBtn');

        if (!correo.includes('@') || correo.length < 5) {
            mostrar(msgEl, 'Correo inválido.', 'danger'); return;
        }
        if (password.length < 6) {
            mostrar(msgEl, 'La contraseña debe tener al menos 6 caracteres.', 'danger'); return;
        }

        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Verificando...';

        try {
            const res = await fetch('api.php?action=login', {
                method:  'POST',
                headers: { 'Content-Type': 'application/json' },
                body:    JSON.stringify({ correo, password })
            });

            if (!res.ok) {
                const txt = await res.text();
                mostrar(msgEl, `Error del servidor (${res.status}). Abre <a href="diagnostico_mvc.php" target="_blank">diagnóstico</a>.`, 'danger');
                console.error(txt);
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-sign-in-alt me-2"></i>Iniciar sesión';
                return;
            }

            const data = await res.json();

            if (data.exito) {
                safeStorage.set('user_session', JSON.stringify(data.usuario));
                safeStorage.remove('_permisos');
                mostrar(msgEl, data.mensaje, 'success');
                setTimeout(() => {
                    const rol = data.usuario?.rol || 'empleado';
                    window.location.href = rol === 'empleado' ? 'seguimiento.html' : 'index.html';
                }, 600);
            } else {
                mostrar(msgEl, data.mensaje || 'Credenciales incorrectas.', 'danger');
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-sign-in-alt me-2"></i>Iniciar sesión';
            }
        } catch(err) {
            mostrar(msgEl, 'No se puede conectar con el servidor. Verifica XAMPP.', 'danger');
            console.error(err);
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-sign-in-alt me-2"></i>Iniciar sesión';
        }
    });
});

function mostrar(el, txt, tipo) {
    if (!el) return;
    el.className = `alert alert-${tipo} mb-3`;
    el.innerHTML  = txt;
    el.classList.remove('d-none');
}
