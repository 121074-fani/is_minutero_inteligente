// script_dashboard.js — Dashboard principal
document.addEventListener('DOMContentLoaded', async () => {
    // Reloj
    function actualizarReloj() {
        const ahora = new Date();
        document.getElementById('clock-time').textContent =
            ahora.toLocaleTimeString('es-MX', { hour:'2-digit', minute:'2-digit', second:'2-digit' });
        document.getElementById('clock-date').textContent =
            ahora.toLocaleDateString('es-MX', { weekday:'long', year:'numeric', month:'long', day:'numeric' });
    }
    actualizarReloj();
    setInterval(actualizarReloj, 1000);

    // Nombre de usuario
    const sesion = localStorage.getItem('user_session');
    if (sesion) {
        const u = JSON.parse(sesion);
        document.getElementById('dash-nombre').textContent = u.nombre || u.email || '';
    }

    // Cargar tareas
    let tareas = [];
    try {
        const res  = await fetch('api.php?action=tareas.listar');
        const data = await res.json();
        if (data.exito) tareas = data.tareas || [];
        else throw new Error();
    } catch {
        const local = localStorage.getItem('calendar_tasks_isc');
        tareas = local ? JSON.parse(local) : [];
    }

    // Métricas
    const hoy      = new Date().toISOString().split('T')[0];
    const pend     = tareas.filter(t => t.estado === 'pendiente').length;
    const comp     = tareas.filter(t => t.estado === 'completada').length;
    const venc     = tareas.filter(t => t.fecha_compromiso < hoy && t.estado !== 'completada').length;
    document.getElementById('m-total').textContent = tareas.length;
    document.getElementById('m-pend').textContent  = pend;
    document.getElementById('m-comp').textContent  = comp;
    document.getElementById('m-venc').textContent  = venc;

    // Lista de tareas recientes (últimas 6)
    const dashTareas = document.getElementById('dash-tareas');
    const recientes  = [...tareas].slice(0, 6);
    if (recientes.length === 0) {
        dashTareas.innerHTML = '<p class="text-muted text-center py-3">Sin tareas registradas.</p>';
    } else {
        dashTareas.innerHTML = recientes.map(t => {
            const estado = t.estado || 'pendiente';
            const fecha  = t.fecha_compromiso || t.fecha || '—';
            const id     = t.id_tarea || t.id;
            return `
            <div class="task-row">
                <div class="task-dot ${estado}"></div>
                <div class="flex-grow-1">
                    <a href="detalle_tarea.html?id=${id}" class="fw-semibold text-decoration-none" style="color:#4a235a;font-size:.9rem;">${t.titulo}</a>
                    <div class="text-muted" style="font-size:.78rem;">${t.responsable || '—'} · ${fecha}</div>
                </div>
                <span class="badge ${estado==='completada'?'bg-success':estado==='en_progreso'?'bg-info text-dark':'bg-warning text-dark'}" style="font-size:.72rem;">${estado}</span>
            </div>`;
        }).join('');
    }

    // Gráfica de barras por departamento
    const deptos = {};
    tareas.forEach(t => {
        const d = t.departamento || 'Sin área';
        deptos[d] = (deptos[d] || 0) + 1;
    });
    const colores = ['#9b59b6','#8e44ad','#c39bd3','#4a235a','#d7bde2','#6c3483'];
    const maxVal  = Math.max(...Object.values(deptos), 1);
    const barsEl  = document.getElementById('grafica-barras');
    const labsEl  = document.getElementById('grafica-labels');
    barsEl.innerHTML = '';
    labsEl.innerHTML = '';
    Object.entries(deptos).forEach(([dep, cnt], i) => {
        const pct   = Math.round((cnt / maxVal) * 100);
        const color = colores[i % colores.length];
        barsEl.innerHTML += `
            <div style="flex:1;display:flex;flex-direction:column;align-items:center;">
                <div style="font-size:.7rem;color:#666;margin-bottom:2px;">${cnt}</div>
                <div class="bar" style="height:${pct}px;background:${color};" title="${dep}: ${cnt}"></div>
                <div class="bar-label">${dep.substring(0,4)}</div>
            </div>`;
        labsEl.innerHTML += `<span style="font-size:.72rem;color:#888;display:flex;align-items:center;gap:4px;">
            <span style="width:8px;height:8px;border-radius:50%;background:${color};display:inline-block;"></span>${dep}
        </span>`;
    });
    if (Object.keys(deptos).length === 0) {
        barsEl.innerHTML = '<p class="text-muted small">Sin datos aún.</p>';
    }

    // Minutas recientes
    try {
        const resM  = await fetch('api.php?action=minutas.listar');
        const dataM = await resM.json();
        const dashM = document.getElementById('dash-minutas');
        if (dataM.exito && dataM.minutas && dataM.minutas.length > 0) {
            dashM.innerHTML = dataM.minutas.slice(0,3).map((m,i) => `
                <div style="display:flex;align-items:center;gap:8px;padding:6px 0;${i>0?'border-top:1px solid #f5eef8':''}">
                    <i class="fas fa-file-alt" style="color:#c39bd3;font-size:.85rem;"></i>
                    <div>
                        <div style="font-size:.85rem;font-weight:600;color:#4a235a;">${m.lugar} — ${m.area||'—'}</div>
                        <div style="font-size:.75rem;color:#888;">${m.fecha}</div>
                    </div>
                </div>`).join('');
        } else {
            document.getElementById('dash-minutas').innerHTML = '<p class="text-muted small">Sin minutas guardadas.</p>';
        }
    } catch { document.getElementById('dash-minutas').innerHTML = '<p class="text-muted small">Sin minutas guardadas.</p>'; }
});
