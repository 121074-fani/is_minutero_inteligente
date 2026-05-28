/**
 * script_seguimiento.js
 * Carga tareas desde php/obtener_tareas.php.
 * Fallback a localStorage si PHP no responde.
 */

let actividades = [];

async function cargarTareas(filtros = {}) {
    const params = new URLSearchParams();
    if (filtros.departamento) params.append('departamento', filtros.departamento);
    if (filtros.responsable)  params.append('responsable',  filtros.responsable);
    if (filtros.fecha)        params.append('fecha',        filtros.fecha);

    try {
        const res  = await fetch('api.php?action=tareas.listar?' + params.toString());
        const data = await res.json();

        if (data.exito) {
            actividades = data.tareas || [];
            // Actualizar métricas del servidor
            if (data.metricas) {
                document.getElementById('total-tareas').innerText       = data.metricas.total       || 0;
                document.getElementById('pendientes-tareas').innerText  = data.metricas.pendientes  || 0;
                document.getElementById('completadas-tareas').innerText = data.metricas.completadas || 0;
            }
            // Sincronizar localStorage
            localStorage.setItem('calendar_tasks_isc', JSON.stringify(actividades));
        } else {
            throw new Error('PHP respondió exito=false');
        }
    } catch (err) {
        console.warn('Usando localStorage:', err);
        const local = localStorage.getItem('calendar_tasks_isc');
        actividades = local ? JSON.parse(local) : [];
    }

    actualizarMetricas();
    renderizarTabla();
}

function actualizarMetricas() {
    const completadas = actividades.filter(a => a.estado === 'completada').length;
    document.getElementById('total-tareas').innerText       = actividades.length;
    document.getElementById('completadas-tareas').innerText = completadas;
    document.getElementById('pendientes-tareas').innerText  = actividades.length - completadas;
}

function renderizarTabla() {
    const contenedor = document.getElementById('tablaPendientes');
    const fDepto = document.getElementById('filtroDepto').value;
    const fEmp   = document.getElementById('filtroEmpleado').value;
    const fFecha = document.getElementById('filtroFecha').value;

    contenedor.innerHTML = '';

    const filtradas = actividades.filter(a => {
        const matchDepto = !fDepto || a.departamento === fDepto;
        const matchEmp   = !fEmp   || (a.responsable && a.responsable.includes(fEmp));
        const matchFecha = !fFecha || (a.fecha_compromiso || a.fecha) === fFecha;
        return matchDepto && matchEmp && matchFecha;
    });

    if (filtradas.length === 0) {
        contenedor.innerHTML = `
            <tr><td colspan="5" class="text-center text-muted py-4">
                No hay tareas. Agrega una con el botón "Nueva Tarea".
            </td></tr>`;
        return;
    }

    filtradas.forEach(a => {
        const idTarea = a.id_tarea || a.id;
        const fecha   = a.fecha_compromiso || a.fecha || '—';
        const estadoMap = {
            completada:  'bg-success',
            pendiente:   'bg-warning text-dark',
            en_progreso: 'bg-info text-dark',
            cancelada:   'bg-secondary',
        };
        const badgeColor = estadoMap[a.estado] || 'bg-warning text-dark';

        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td class="fw-bold">
                <a href="detalle_tarea.html?id=${idTarea}" class="text-decoration-none" style="color:#4a235a;">
                    ${a.titulo}
                </a>
            </td>
            <td>${a.responsable || '—'}</td>
            <td><span class="badge bg-secondary">${a.departamento || 'No asignado'}</span></td>
            <td>${fecha}</td>
            <td>
                <span class="badge ${badgeColor}">${a.estado || 'pendiente'}</span>
                <button class="btn btn-sm btn-outline-success ms-1 py-0"
                        onclick="cambiarEstado(${idTarea}, 'completada')" title="Completar">✔</button>
            </td>
        `;
        contenedor.appendChild(tr);
    });
}

async function cambiarEstado(idTarea, estado) {
    try {
        const res  = await fetch('api.php?action=tareas.estado', {
            method:  'POST',
            headers: { 'Content-Type': 'application/json' },
            body:    JSON.stringify({ id_tarea: idTarea, estado }),
        });
        const data = await res.json();
        if (data.exito) {
            cargarTareas();
        } else {
            // Fallback localStorage
            actividades = actividades.map(a =>
                String(a.id_tarea || a.id) === String(idTarea) ? {...a, estado} : a
            );
            localStorage.setItem('calendar_tasks_isc', JSON.stringify(actividades));
            renderizarTabla();
        }
    } catch {
        actividades = actividades.map(a =>
            String(a.id_tarea || a.id) === String(idTarea) ? {...a, estado} : a
        );
        localStorage.setItem('calendar_tasks_isc', JSON.stringify(actividades));
        renderizarTabla();
    }
}

async function guardarTarea() {
    const titulo       = document.getElementById('tituloTarea').value.trim();
    const responsable  = document.getElementById('responsableTarea').value.trim();
    const departamento = document.getElementById('departamentoTarea').value;
    const fecha        = document.getElementById('fechaTarea').value;

    if (!titulo || !fecha) { alert('El título y la fecha son obligatorios.'); return; }

    let guardadoEnBD = false;

    try {
        const res  = await fetch('api.php?action=tareas.crear', {
            method:  'POST',
            headers: { 'Content-Type': 'application/json' },
            body:    JSON.stringify({ titulo, responsable, departamento, fecha_compromiso: fecha }),
        });
        const data = await res.json();
        if (data.exito) guardadoEnBD = true;
    } catch { /* fallback */ }

    if (!guardadoEnBD) {
        // Guardar en localStorage como respaldo
        const nueva = {
            id: Date.now().toString(),
            titulo, responsable, departamento,
            fecha, fecha_compromiso: fecha,
            estado: 'pendiente'
        };
        actividades.push(nueva);
        localStorage.setItem('calendar_tasks_isc', JSON.stringify(actividades));
    }

    // Cerrar modal
    const modalEl = document.getElementById('modalTarea');
    const modal   = bootstrap.Modal.getInstance(modalEl);
    if (modal) modal.hide();
    document.getElementById('formNuevaTarea').reset();

    await cargarTareas();
}

document.addEventListener('DOMContentLoaded', () => {
    cargarTareas();

    document.getElementById('filtroDepto').addEventListener('change', renderizarTabla);
    document.getElementById('filtroEmpleado').addEventListener('change', renderizarTabla);
    document.getElementById('filtroFecha').addEventListener('change', renderizarTabla);

    document.getElementById('btnLimpiarFiltros').addEventListener('click', () => {
        document.getElementById('filtroDepto').value    = '';
        document.getElementById('filtroEmpleado').value = '';
        document.getElementById('filtroFecha').value    = '';
        cargarTareas();
    });
});
