document.addEventListener('DOMContentLoaded', async () => {
    const params     = new URLSearchParams(window.location.search);
    const idBuscado  = params.get('id');
    const rfcBuscado = params.get('rfc');

    if (!idBuscado && !rfcBuscado) {
        document.getElementById('u-nombre').textContent = 'Falta el ID del usuario';
        return;
    }

    let usuario = null;
    try {
        const res  = await fetch('api.php?action=usuarios.listar');
        const data = await res.json();
        if (data.exito && data.usuarios.length > 0) {
            usuario = idBuscado
                ? data.usuarios.find(u => String(u.id_usuario) === String(idBuscado))
                : data.usuarios.find(u => (u.rfc||'').toUpperCase() === rfcBuscado.toUpperCase());
        }
    } catch {}

    if (!usuario) {
        const local = localStorage.getItem('usuarios_registrados');
        const lista = local ? JSON.parse(local) : [];
        usuario = idBuscado
            ? lista.find(u => String(u.id||u.id_usuario) === String(idBuscado))
            : lista.find(u => (u.rfc||'').toUpperCase() === rfcBuscado.toUpperCase());
    }

    if (!usuario) {
        document.getElementById('u-nombre').textContent = 'Usuario no encontrado';
        return;
    }

    const nombre = usuario.nombre || usuario.nombre_completo || 'Sin nombre';
    const rol    = usuario.rol    || 'empleado';
    const idReal = usuario.id_usuario || usuario.id;

    document.getElementById('u-nombre').textContent  = nombre;
    document.getElementById('u-rol').textContent     = rol.charAt(0).toUpperCase() + rol.slice(1);
    document.getElementById('u-rfc').textContent     = usuario.rfc    || '—';
    document.getElementById('u-correo').textContent  = usuario.correo || '—';
    document.getElementById('u-fecha').textContent   = usuario.fecha_registro
        ? new Date(usuario.fecha_registro).toLocaleDateString('es-MX',{year:'numeric',month:'long',day:'numeric'})
        : '—';

    // Campo teléfono editable
    const telInput = document.getElementById('u-tel-input');
    telInput.value = usuario.telefono || '';

    const colorMap = { admin:'4a235a', gerente:'8e44ad', empleado:'9b59b6' };
    document.getElementById('user-photo').src =
        `https://ui-avatars.com/api/?name=${encodeURIComponent(nombre)}&background=${colorMap[rol]||'9b59b6'}&color=fff&size=150&bold=true`;

    // Tareas
    try {
        const res  = await fetch(`php/obtener_tareas.php?responsable=${encodeURIComponent(nombre)}`);
        const data = await res.json();
        const tareas = data.exito ? data.tareas : [];
        document.getElementById('stat-total').textContent = tareas.length;
        document.getElementById('stat-pend').textContent  = tareas.filter(t=>t.estado==='pendiente').length;
        document.getElementById('stat-comp').textContent  = tareas.filter(t=>t.estado==='completada').length;
        document.getElementById('status-tag').textContent = `${tareas.length} tarea${tareas.length!==1?'s':''}`;

        const cont = document.getElementById('tareas-container');
        if (tareas.length === 0) {
            cont.innerHTML = '<p class="text-muted small">Sin tareas asignadas.</p>';
        } else {
            cont.innerHTML = tareas.map(t => `
                <div class="task-item">
                    <div class="dot ${t.estado||'pendiente'}"></div>
                    <div class="flex-grow-1">
                        <a href="detalle_tarea.html?id=${t.id_tarea||t.id}"
                           class="fw-semibold text-decoration-none" style="color:#4a235a;font-size:.88rem;">${t.titulo}</a>
                        <div class="text-muted" style="font-size:.75rem;">${t.fecha_compromiso||'—'}</div>
                    </div>
                    <span class="badge" style="font-size:.72rem;background:${t.estado==='completada'?'#e8f8f0;color:#27ae60':t.estado==='en_progreso'?'#cff4fc;color:#055160':'#fef3cd;color:#856404'}">
                        ${t.estado||'pendiente'}
                    </span>
                </div>`).join('');
        }
    } catch {
        document.getElementById('msg-tareas') && (document.getElementById('tareas-container').innerHTML = '<p class="text-muted small">No se pudieron cargar las tareas.</p>');
    }

    // Guardar teléfono
    document.getElementById('btn-guardar-tel').addEventListener('click', async () => {
        const nuevoTel = telInput.value.trim();
        const btn      = document.getElementById('btn-guardar-tel');
        btn.disabled   = true;
        btn.innerHTML  = '<i class="fas fa-spinner fa-spin me-1"></i>Guardando...';

        try {
            const res  = await fetch('api.php?action=usuarios.telefono', {
                method:'POST', headers:{'Content-Type':'application/json'},
                body: JSON.stringify({id_usuario: idReal, telefono: nuevoTel})
            });
            const data = await res.json();
            if (data.exito) {
                btn.innerHTML = '<i class="fas fa-check me-1"></i>Guardado';
                btn.style.background = '#e8f8f0'; btn.style.color = '#27ae60';
                setTimeout(() => { btn.innerHTML = '<i class="fas fa-save me-1"></i>Guardar'; btn.style.background=''; btn.style.color=''; btn.disabled=false; }, 2000);
            } else { throw new Error(data.mensaje); }
        } catch {
            // Fallback localStorage
            const local = localStorage.getItem('usuarios_registrados');
            let lista = local ? JSON.parse(local) : [];
            lista = lista.map(u => String(u.id||u.id_usuario) === String(idReal) ? {...u, telefono: nuevoTel} : u);
            localStorage.setItem('usuarios_registrados', JSON.stringify(lista));
            btn.innerHTML = '<i class="fas fa-check me-1"></i>Guardado'; btn.disabled = false;
        }
    });

    // Botón eliminar
    document.getElementById('btn-eliminar').addEventListener('click', async () => {
        if (!confirm(`¿Eliminar a ${nombre} del sistema?`)) return;
        try {
            const res  = await fetch('api.php?action=usuarios.eliminar', {
                method:'POST', headers:{'Content-Type':'application/json'},
                body: JSON.stringify({id_usuario: idReal})
            });
            const data = await res.json();
            if (data.exito) { alert('Usuario eliminado.'); window.location.href = 'gestion_usuarios.html'; }
            else throw new Error(data.mensaje);
        } catch {
            const local = localStorage.getItem('usuarios_registrados');
            let lista = local ? JSON.parse(local) : [];
            lista = lista.filter(u => String(u.id||u.id_usuario) !== String(idReal));
            localStorage.setItem('usuarios_registrados', JSON.stringify(lista));
            window.location.href = 'gestion_usuarios.html';
        }
    });
});
