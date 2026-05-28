document.addEventListener('DOMContentLoaded', async () => {
    const params    = new URLSearchParams(window.location.search);
    const idBuscado = params.get('id');
    if (!idBuscado) { document.getElementById('dt-titulo').textContent = 'Error: Falta ID'; return; }

    let tarea = null;
    try {
        const res  = await fetch('api.php?action=tareas.listar');
        const data = await res.json();
        if (data.exito) tarea = data.tareas.find(t => String(t.id_tarea) === String(idBuscado) || String(t.id) === String(idBuscado));
    } catch {}

    if (!tarea) {
        const local = localStorage.getItem('calendar_tasks_isc');
        const lista = local ? JSON.parse(local) : [];
        tarea = lista.find(t => String(t.id) === String(idBuscado) || String(t.id_tarea) === String(idBuscado));
    }

    if (!tarea) {
        document.getElementById('dt-titulo').textContent = 'Tarea no encontrada';
        ['btn-pendiente','btn-en_progreso','btn-completada','btnEliminar'].forEach(id => {
            const el = document.getElementById(id); if (el) el.style.display = 'none';
        });
        return;
    }

    const idReal = tarea.id_tarea || tarea.id;
    document.getElementById('dt-id').textContent           = idReal;
    document.getElementById('dt-titulo').textContent       = tarea.titulo;
    document.getElementById('dt-responsable').textContent  = tarea.responsable  || '—';
    document.getElementById('dt-departamento').textContent = tarea.departamento || 'No asignado';
    document.getElementById('dt-fecha').textContent        = tarea.fecha_compromiso || tarea.fecha || '—';

    marcarEstadoActivo(tarea.estado || 'pendiente');

    // Botón eliminar
    document.getElementById('btnEliminar').addEventListener('click', async () => {
        if (!confirm('¿Eliminar esta tarea permanentemente?')) return;
        try {
            const res  = await fetch('api.php?action=tareas.eliminar', {
                method:'POST', headers:{'Content-Type':'application/json'},
                body: JSON.stringify({id_tarea: idReal})
            });
            const data = await res.json();
            if (data.exito) window.location.href = 'seguimiento.html';
            else alert('Error: ' + data.mensaje);
        } catch {
            const local = localStorage.getItem('calendar_tasks_isc');
            let lista = local ? JSON.parse(local) : [];
            lista = lista.filter(t => String(t.id) !== String(idBuscado));
            localStorage.setItem('calendar_tasks_isc', JSON.stringify(lista));
            window.location.href = 'seguimiento.html';
        }
    });

    // Exponer idReal globalmente para cambiarEstado
    window._idTareaActual = idReal;
    window._idBuscado     = idBuscado;
});

function marcarEstadoActivo(estado) {
    const badges = { pendiente:'#ffc107 text-dark', en_progreso:'#0dcaf0 text-dark', completada:'#198754 text-white' };
    const labels = { pendiente:'Pendiente', en_progreso:'En Proceso', completada:'Completada' };
    const badge  = document.getElementById('dt-estado');
    badge.textContent = labels[estado] || estado;
    badge.className   = `badge rounded-pill`;
    badge.style.background = estado === 'completada' ? '#198754' : estado === 'en_progreso' ? '#0dcaf0' : '#ffc107';
    badge.style.color      = estado === 'pendiente' || estado === 'en_progreso' ? '#000' : '#fff';

    ['pendiente','en_progreso','completada'].forEach(e => {
        const btn = document.getElementById(`btn-${e}`);
        if (btn) btn.classList.toggle('active', e === estado);
    });
}

async function cambiarEstado(nuevoEstado) {
    const idReal    = window._idTareaActual;
    const idBuscado = window._idBuscado;

    try {
        const res  = await fetch('api.php?action=tareas.estado', {
            method:'POST', headers:{'Content-Type':'application/json'},
            body: JSON.stringify({id_tarea: idReal, estado: nuevoEstado})
        });
        const data = await res.json();
        if (!data.exito) throw new Error(data.mensaje);
    } catch {
        // Fallback localStorage
        const local = localStorage.getItem('calendar_tasks_isc');
        let lista = local ? JSON.parse(local) : [];
        lista = lista.map(t => String(t.id||t.id_tarea) === String(idBuscado) ? {...t, estado: nuevoEstado} : t);
        localStorage.setItem('calendar_tasks_isc', JSON.stringify(lista));
    }

    marcarEstadoActivo(nuevoEstado);

    // Toast de confirmación
    const toast = document.createElement('div');
    toast.style.cssText = 'position:fixed;bottom:24px;right:24px;background:#4a235a;color:white;padding:12px 22px;border-radius:12px;font-weight:600;z-index:9999;box-shadow:0 4px 16px rgba(0,0,0,.2);transition:opacity .4s;';
    const icons = { pendiente:'⏳', en_progreso:'🔄', completada:'✅' };
    const labels = { pendiente:'Pendiente', en_progreso:'En Proceso', completada:'Completada' };
    toast.textContent = `${icons[nuevoEstado]} Estado actualizado: ${labels[nuevoEstado]}`;
    document.body.appendChild(toast);
    setTimeout(() => { toast.style.opacity = '0'; setTimeout(() => toast.remove(), 400); }, 2000);
}
