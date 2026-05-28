let currentDate = new Date();
let allTasks    = [];
let feriados    = {};

document.addEventListener('DOMContentLoaded', async () => {
    await Promise.all([cargarTareasCalendario(), cargarFeriados(currentDate.getFullYear())]);
    renderCalendar();
    document.getElementById('prevMonth').addEventListener('click', () => {
        currentDate.setMonth(currentDate.getMonth() - 1); renderCalendar();
    });
    document.getElementById('nextMonth').addEventListener('click', () => {
        currentDate.setMonth(currentDate.getMonth() + 1); renderCalendar();
    });
});

async function cargarTareasCalendario() {
    try {
        const res  = await fetch('api.php?action=tareas.listar');
        const data = await res.json();
        if (data.exito) { allTasks = data.tareas || []; localStorage.setItem('calendar_tasks_isc', JSON.stringify(allTasks)); }
        else throw new Error();
    } catch {
        const local = localStorage.getItem('calendar_tasks_isc');
        allTasks = local ? JSON.parse(local) : [];
    }
}

async function cargarFeriados(anio) {
    try {
        const res  = await fetch(`php/servicio_feriados.php?año=${anio}`);
        const data = await res.json();
        if (data.exito && data.feriados) {
            feriados = {};
            data.feriados.forEach(f => { feriados[f.date] = f.localName || f.name; });
        }
    } catch {}
}

function renderCalendar() {
    const meses = ['Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];
    const year  = currentDate.getFullYear();
    const month = currentDate.getMonth();
    document.getElementById('currentMonth').innerText = `${meses[month]} ${year}`;

    const grid = document.getElementById('calendarGrid');
    grid.innerHTML = '';

    // Primer día: ajuste para empezar en lunes (0=lun … 6=dom)
    let firstDay = new Date(year, month, 1).getDay();
    firstDay = firstDay === 0 ? 6 : firstDay - 1;
    const daysInMonth = new Date(year, month + 1, 0).getDate();
    const today = new Date();

    for (let i = 0; i < firstDay; i++) {
        const e = document.createElement('div'); e.className = 'cal-day empty'; grid.appendChild(e);
    }

    for (let day = 1; day <= daysInMonth; day++) {
        const cell    = document.createElement('div');
        cell.className = 'cal-day';
        const dateStr = `${year}-${String(month+1).padStart(2,'0')}-${String(day).padStart(2,'0')}`;
        const isToday = day === today.getDate() && month === today.getMonth() && year === today.getFullYear();
        if (isToday)         cell.classList.add('today');
        if (feriados[dateStr]) { cell.classList.add('feriado'); }

        const numEl = document.createElement('span');
        numEl.className = 'day-num';
        if (isToday) numEl.innerHTML = `<span class="today-badge">${day}</span>`;
        else numEl.textContent = day;
        cell.appendChild(numEl);

        if (feriados[dateStr]) {
            const fl = document.createElement('div');
            fl.className = 'feriado-label';
            fl.textContent = feriados[dateStr].substring(0,16);
            cell.appendChild(fl);
        }

        allTasks.filter(t => (t.fecha_compromiso || t.fecha) === dateStr).forEach(t => {
            const pill = document.createElement('div');
            pill.className = `task-pill ${t.estado || 'pendiente'}`;
            pill.textContent = t.titulo.length > 20 ? t.titulo.substring(0,20)+'…' : t.titulo;
            pill.onclick = () => window.location.href = `detalle_tarea.html?id=${t.id_tarea || t.id}`;
            cell.appendChild(pill);
        });

        grid.appendChild(cell);
    }
}

async function guardarTarea() {
    const titulo       = document.getElementById('tituloTarea').value.trim();
    const responsable  = document.getElementById('responsableTarea').value.trim();
    const departamento = document.getElementById('departamentoTarea').value;
    const fecha        = document.getElementById('fechaTarea').value;

    if (!titulo || !fecha) { alert('El título y la fecha son obligatorios.'); return; }

    try {
        const res  = await fetch('api.php?action=tareas.crear', {
            method: 'POST', headers: {'Content-Type':'application/json'},
            body: JSON.stringify({titulo, responsable, departamento, fecha_compromiso: fecha}),
        });
        const data = await res.json();
        if (!data.exito) throw new Error();
    } catch {
        const nueva = {id: Date.now().toString(), titulo, responsable, departamento, fecha, fecha_compromiso: fecha, estado:'pendiente'};
        allTasks.push(nueva);
        localStorage.setItem('calendar_tasks_isc', JSON.stringify(allTasks));
    }

    bootstrap.Modal.getInstance(document.getElementById('modalTarea')).hide();
    document.getElementById('tituloTarea').value = '';
    document.getElementById('fechaTarea').value  = '';
    await cargarTareasCalendario();
    renderCalendar();
}
