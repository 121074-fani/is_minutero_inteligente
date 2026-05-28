// Storage seguro compatible con Edge
const safeStorage = {
    set(k,v){try{localStorage.setItem(k,v)}catch(e){try{sessionStorage.setItem(k,v)}catch(e2){window._mem=window._mem||{};window._mem[k]=v}}},
    get(k){try{return localStorage.getItem(k)}catch(e){try{return sessionStorage.getItem(k)}catch(e2){return window._mem?.[k]??null}}},
    remove(k){try{localStorage.removeItem(k)}catch(e){}try{sessionStorage.removeItem(k)}catch(e){}if(window._mem)delete window._mem[k]}
};
/**
 * sidebar.js — Web Component con menú dinámico por ROL + hamburguesa para móvil
 */
class AppSidebar extends HTMLElement {
    async connectedCallback() {
        if (!document.querySelector('link[href="css/side-bar.css"]')) {
            const l = document.createElement('link');
            l.rel = 'stylesheet'; l.href = 'css/side-bar.css';
            document.head.appendChild(l);
        }
        if (!document.querySelector('link[href="css/responsive.css"]')) {
            const l = document.createElement('link');
            l.rel = 'stylesheet'; l.href = 'css/responsive.css';
            document.head.appendChild(l);
        }

        let usuario = { nombre:'', rol:'empleado', puede:{} };
        try {
            const cache = safeStorage.get('_permisos');
            if (cache) {
                usuario = JSON.parse(cache);
            } else {
                const res = await fetch('api.php?action=sesion');
                const d   = await res.json();
                if (d.exito) {
                    usuario = d.usuario;
                    const rp = await fetch('api.php?action=usuarios.permisos');
                    const dp = await rp.json();
                    if (dp.exito) usuario.puede = dp.puede;
                    safeStorage.set('_permisos', JSON.stringify(usuario));
                    safeStorage.set('user_session', JSON.stringify(usuario));
                }
            }
        } catch {}

        const rol  = usuario.rol || 'empleado';
        const rolColor = { admin:'#e74c3c', minutero:'#9b59b6', empleado:'#27ae60' };
        const rolLabel = { admin:'Administrador', minutero:'Minutero', empleado:'Empleado' };

        const menuAdmin = `
            <li class="nav-item"><a href="index.html"><i class="fas fa-home"></i> Dashboard</a></li>
            <li class="nav-item"><a href="formulario.html"><i class="fas fa-file-signature"></i> Nueva Minuta</a></li>
            <li class="nav-item"><a href="reportes.html"><i class="fas fa-chart-line"></i> Reportes</a></li>
            <li class="nav-item"><a href="calendario.html"><i class="fas fa-calendar-alt"></i> Calendario</a></li>
            <li class="nav-item"><a href="seguimiento.html"><i class="fas fa-tasks"></i> Seguimiento</a></li>
            <li class="nav-item"><a href="gestion_usuarios.html"><i class="fas fa-users"></i> Usuarios</a></li>
            <li class="nav-item"><a href="nuevo_usuario.html"><i class="fas fa-user-plus"></i> Nuevo Usuario</a></li>`;

        const menuMinutero = `
            <li class="nav-item"><a href="index.html"><i class="fas fa-home"></i> Dashboard</a></li>
            <li class="nav-item"><a href="formulario.html"><i class="fas fa-file-signature"></i> Nueva Minuta</a></li>
            <li class="nav-item"><a href="reportes.html"><i class="fas fa-chart-line"></i> Reportes</a></li>
            <li class="nav-item"><a href="calendario.html"><i class="fas fa-calendar-alt"></i> Calendario</a></li>
            <li class="nav-item"><a href="seguimiento.html"><i class="fas fa-tasks"></i> Seguimiento</a></li>
            <li class="nav-item"><a href="gestion_usuarios.html"><i class="fas fa-users"></i> Usuarios</a></li>`;

        const menuEmpleado = `
            <li class="nav-item"><a href="calendario.html"><i class="fas fa-calendar-alt"></i> Calendario</a></li>
            <li class="nav-item"><a href="seguimiento.html"><i class="fas fa-tasks"></i> Mis Tareas</a></li>
            <li class="nav-item"><a href="reportes.html"><i class="fas fa-file-alt"></i> Mis Minutas</a></li>`;

        const menu = rol==='admin' ? menuAdmin : rol==='minutero' ? menuMinutero : menuEmpleado;

        this.innerHTML = `
        <nav class="sidebar" id="appSidebar">
            <div class="sidebar-header">
                <div style="display:flex;align-items:center;gap:8px;">
                    <i class="fas fa-tasks logo-icon"></i>
                    <span class="logo-text" style="line-height:1.2;">Minutero<br>Inteligente</span>
                </div>
                <!-- Hamburguesa visible solo en móvil via CSS -->
                <button class="btn-hamburguesa" id="btnHamburguesa" aria-label="Menú" aria-expanded="false">
                    <i class="fas fa-bars" id="iconHamburguesa"></i>
                    <span style="font-size:.78rem;font-weight:600;">Menú</span>
                </button>
            </div>

            ${usuario.nombre ? `
            <div style="margin:8px 0 10px;padding:8px 10px;background:rgba(155,89,182,.13);border-radius:10px;">
                <div style="font-size:.72rem;color:#888;text-transform:uppercase;letter-spacing:.5px;">Sesión</div>
                <div style="font-size:.86rem;font-weight:700;color:#4a235a;">${usuario.nombre}</div>
                <span style="font-size:.7rem;background:${rolColor[rol]||'#888'};color:white;padding:2px 8px;border-radius:20px;">${rolLabel[rol]||rol}</span>
            </div>` : ''}

            <ul class="nav-menu">${menu}</ul>

            <div style="margin-top:auto;padding-top:14px;">
                <button onclick="cerrarSesion()" class="btn w-100 shadow-sm"
                    style="background:#ff6b6b;color:white;border:none;border-radius:10px;font-weight:bold;padding:10px;display:flex;justify-content:center;align-items:center;gap:8px;min-height:44px;">
                    <i class="fas fa-sign-out-alt"></i> Cerrar sesión
                </button>
            </div>
        </nav>`;

        // Marcar página activa
        const current = window.location.pathname.split('/').pop() || 'index.html';
        this.querySelectorAll('.nav-item a').forEach(link => {
            if (link.getAttribute('href') === current) link.parentElement.classList.add('active');
        });

        // ── Hamburguesa ──────────────────────────────────
        const btnH = this.querySelector('#btnHamburguesa');
        const nav  = this.querySelector('#appSidebar');
        const icon = this.querySelector('#iconHamburguesa');
        btnH?.addEventListener('click', () => {
            const open = nav.classList.toggle('nav-open');
            icon.className = open ? 'fas fa-times' : 'fas fa-bars';
            btnH.setAttribute('aria-expanded', String(open));
        });
        // Cerrar menú al navegar
        this.querySelectorAll('.nav-item a').forEach(a => {
            a.addEventListener('click', () => {
                nav.classList.remove('nav-open');
                if (icon) icon.className = 'fas fa-bars';
            });
        });

        // Proteger páginas para empleados
        const restringidas = ['index.html','formulario.html','gestion_usuarios.html','nuevo_usuario.html','tarjeta_usuario.html'];
        if (rol === 'empleado' && restringidas.includes(current)) {
            window.location.href = 'seguimiento.html';
        }
    }
}
customElements.define('app-sidebar', AppSidebar);

function cerrarSesion() {
    fetch('api.php?action=logout', {method:'POST'}).catch(()=>{});
    safeStorage.remove('user_session');
    safeStorage.remove('_permisos');
    window.location.href = 'login.html';
}

window.puedeEliminar = () => {
    try {
        const p = safeStorage.get('_permisos');
        if (p) { const u = JSON.parse(p); return u.puede?.eliminarUsuarios === true; }
    } catch {}
    return false;
};
