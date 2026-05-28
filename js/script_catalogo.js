let todosLosUsuarios = [];

function getAvatar(nombre, rol) {
    const colores = { admin:'4a235a', gerente:'8e44ad', empleado:'9b59b6' };
    return `https://ui-avatars.com/api/?name=${encodeURIComponent(nombre||'U')}&background=${colores[rol]||'9b59b6'}&color=fff&size=150&bold=true`;
}

// Obtener correo del usuario en sesión
function getCorreoSesion() {
    try {
        const s = localStorage.getItem('user_session');
        if (s) { const u = JSON.parse(s); return (u.correo || u.email || '').toLowerCase(); }
    } catch {}
    return '';
}

async function cargarUsuarios() {
    const grid = document.getElementById('catalogoGrid');
    grid.innerHTML = '<p class="text-muted px-4">Cargando usuarios...</p>';
    try {
        const res  = await fetch('api.php?action=usuarios.listar');
        const data = await res.json();
        if (data.exito && data.usuarios.length > 0) {
            todosLosUsuarios = data.usuarios;
        } else { throw new Error(); }
    } catch {
        const local = localStorage.getItem('usuarios_registrados');
        todosLosUsuarios = local ? JSON.parse(local) : [];
    }
    renderizarCatalogo(todosLosUsuarios);
}

function renderizarCatalogo(lista) {
    const grid      = document.getElementById('catalogoGrid');
    const sinUsers  = document.getElementById('sinUsuarios');
    const subtitulo = document.getElementById('subtitulo');
    const correoSesion = getCorreoSesion();
    grid.innerHTML  = '';

    if (lista.length === 0) { sinUsers.style.display='block'; subtitulo.textContent='0 usuarios registrados'; return; }
    sinUsers.style.display = 'none';
    subtitulo.textContent  = `${lista.length} usuario${lista.length!==1?'s':''} registrado${lista.length!==1?'s':''}`;

    lista.forEach(u => {
        const id     = u.id_usuario || u.id || '';
        const nombre = u.nombre || u.nombre_completo || 'Sin nombre';
        const rfc    = u.rfc    || '—';
        const rol    = u.rol    || 'empleado';
        const esSesion = correoSesion && (u.correo||'').toLowerCase() === correoSesion;

        const card = document.createElement('div');
        card.className = 'user-card-cat';
        if (esSesion) card.classList.add('es-sesion');

        card.innerHTML = `
            ${esSesion ? `<div class="yo-badge"><i class="fas fa-circle me-1"></i>Tú</div>` : ''}
            <img src="${getAvatar(nombre, rol)}" alt="${nombre}" class="avatar-cat ${esSesion?'avatar-sesion':''}">
            <h5 class="fw-bold mb-1 ${esSesion?'text-sesion':''}" style="font-size:.95rem;">${nombre}</h5>
            <span class="badge-rol ${esSesion?'badge-sesion':''}">${rol.charAt(0).toUpperCase()+rol.slice(1)}</span><br>
            <small class="text-muted d-block mb-3" style="font-size:.78rem;">${rfc}</small>
            <a href="tarjeta_usuario.html?id=${id}" class="btn-ver ${esSesion?'btn-ver-sesion':''}">
                <i class="fas fa-eye me-1"></i>Ver Detalles
            </a>
        `;
        grid.appendChild(card);
    });
}

document.getElementById('buscador').addEventListener('input', function () {
    const q = this.value.toLowerCase();
    renderizarCatalogo(todosLosUsuarios.filter(u =>
        (u.nombre||'').toLowerCase().includes(q) ||
        (u.rfc||'').toLowerCase().includes(q) ||
        (u.rol||'').toLowerCase().includes(q) ||
        (u.correo||'').toLowerCase().includes(q)
    ));
});

document.addEventListener('DOMContentLoaded', cargarUsuarios);
