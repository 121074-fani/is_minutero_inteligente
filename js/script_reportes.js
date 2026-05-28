/**
 * script_reportes.js
 * admin/minutero → todas las minutas
 * empleado       → solo minutas donde es responsable o participante
 */
let todasMinutas = [];
let paginaActual = 1;
const POR_PAGINA = 8;
let rolActual = 'empleado';

document.addEventListener('DOMContentLoaded', async () => {
    await cargarMinutas();
    document.getElementById('buscador').addEventListener('input',    () => { paginaActual=1; renderTabla(); });
    document.getElementById('filtroDepto').addEventListener('change', () => { paginaActual=1; renderTabla(); });
    document.getElementById('filtroFecha').addEventListener('change', () => { paginaActual=1; renderTabla(); });
    document.getElementById('btnAnterior').addEventListener('click',  () => { paginaActual--; renderTabla(); });
    document.getElementById('btnSiguiente').addEventListener('click', () => { paginaActual++; renderTabla(); });
});

async function cargarMinutas() {
    try {
        const res  = await fetch('api.php?action=minutas.listar');
        const data = await res.json();
        if (data.exito && data.minutas) {
            todasMinutas = data.minutas;
            rolActual    = data.rol || 'empleado';
        } else {
            throw new Error(data.mensaje || 'Sin datos');
        }
    } catch(e) {
        console.warn('Error cargando minutas:', e);
        todasMinutas = [];
    }

    // Adaptar UI según rol
    const subtitulo = document.getElementById('rep-subtitulo');
    const btnNueva  = document.getElementById('btnNuevaMinuta');
    if (rolActual === 'empleado') {
        if (subtitulo) subtitulo.textContent = 'Minutas en las que participas';
        if (btnNueva)  btnNueva.style.display = 'none';
    }

    document.getElementById('total-minutas').textContent = todasMinutas.length;
    renderTabla();
}

function filtradas() {
    const txt   = document.getElementById('buscador').value.toLowerCase();
    const depto = document.getElementById('filtroDepto').value;
    const fecha = document.getElementById('filtroFecha').value;
    return todasMinutas.filter(m => {
        const textoOK = !txt ||
            (m.lugar||'').toLowerCase().includes(txt) ||
            (m.area||'').toLowerCase().includes(txt)  ||
            (m.correo_responsable||'').toLowerCase().includes(txt) ||
            String(m.id_minuta).includes(txt);
        const deptoOK = !depto || (m.area||'') === depto;
        const fechaOK = !fecha || m.fecha === fecha;
        return textoOK && deptoOK && fechaOK;
    });
}

function renderTabla() {
    const lista     = filtradas();
    const total     = lista.length;
    const inicio    = (paginaActual - 1) * POR_PAGINA;
    const pagina    = lista.slice(inicio, inicio + POR_PAGINA);
    const totalPag  = Math.ceil(total / POR_PAGINA);
    const tbody     = document.getElementById('tablaMinutas');

    document.getElementById('total-minutas').textContent = total;
    document.getElementById('pag-info').textContent =
        total > 0
            ? `Mostrando ${inicio+1}–${Math.min(inicio+POR_PAGINA,total)} de ${total}`
            : '0 resultados';
    document.getElementById('btnAnterior').disabled  = paginaActual <= 1;
    document.getElementById('btnSiguiente').disabled = paginaActual >= totalPag;

    if (!pagina.length) {
        tbody.innerHTML = `<tr><td colspan="6" class="text-center text-muted py-5">
            <i class="fas fa-inbox fa-2x d-block mb-2" style="color:#e8daef;"></i>
            No se encontraron minutas.
            ${rolActual !== 'empleado' ? '<br><a href="formulario.html" style="color:#9b59b6;">Crear nueva minuta</a>' : ''}
        </td></tr>`;
        return;
    }

    tbody.innerHTML = pagina.map(m => {
        const tipoBadge = m.tipo === 'virtual'
            ? '<span class="badge" style="background:#e8f4fd;color:#2980b9;font-size:.72rem;">Virtual</span>'
            : '<span class="badge" style="background:#e8f8f0;color:#27ae60;font-size:.72rem;">Presencial</span>';
        const esResp = m.correo_responsable ? '<i class="fas fa-star me-1" style="color:#f39c12;font-size:.7rem;" title="Eres responsable"></i>' : '';
        return `<tr>
            <td><span class="id-badge">MIN-${String(m.id_minuta).padStart(3,'0')}</span></td>
            <td><span class="fw-semibold" style="color:#4a235a;">${esResp}${m.lugar}</span></td>
            <td><span class="badge" style="background:#f5eef8;color:#8e44ad;font-size:.72rem;">${m.area||'—'}</span></td>
            <td style="font-size:.86rem;">${m.fecha}</td>
            <td>${tipoBadge}</td>
            <td>
                <button class="btn-icon view" title="Ver detalle" onclick="verMinuta(${m.id_minuta})">
                    <i class="fas fa-eye" style="font-size:.8rem;"></i>
                </button>
                <button class="btn-icon pdf" title="Descargar PDF" onclick="descargarPDF(${m.id_minuta})">
                    <i class="fas fa-file-pdf" style="font-size:.8rem;"></i>
                </button>
            </td>
        </tr>`;
    }).join('');
}

function verMinuta(id) {
    alert(`Ver detalle de MIN-${String(id).padStart(3,'0')} (próximamente)`);
}

async function descargarPDF(id) {
    try {
        const res  = await fetch(`api.php?action=minutas.detalle&id=${id}`);
        const data = await res.json();
        if (!data.exito) throw new Error();
        const m = data.minuta;
        const { jsPDF } = window.jspdf;
        const doc = new jsPDF();
        doc.setFontSize(18); doc.text('Minuta de Reunión', 14, 20);
        doc.setFontSize(11);
        doc.text(`Lugar: ${m.lugar}`,                         14, 32);
        doc.text(`Fecha: ${m.fecha}  Hora: ${m.hora||'—'}`,   14, 39);
        doc.text(`Área: ${m.area||'—'}`,                      14, 46);
        doc.text(`Responsable: ${m.correo_responsable}`,      14, 53);
        doc.text(`Tipo: ${m.tipo}`,                           14, 60);
        const temas = m.temas || [];
        if (temas.length) {
            doc.autoTable({startY:68,head:[['#','Tema','Descripción']],
                body:temas.map((t,i)=>[i+1,t.titulo||t,t.descripcion||'']),
                theme:'grid',headStyles:{fillColor:[74,35,90]}});
        }
        const acuerdos = m.acuerdos || [];
        if (acuerdos.length) {
            doc.autoTable({
                startY:(doc.lastAutoTable?.finalY||68)+10,
                head:[['Responsable','Actividad','Fecha']],
                body:acuerdos.map(a=>[a.responsable,a.titulo,a.fecha_compromiso||'—']),
                theme:'striped',headStyles:{fillColor:[39,174,96]}});
        }
        doc.save(`Minuta_${m.fecha}_${m.id_minuta}.pdf`);
    } catch {
        alert('No se pudo generar el PDF.');
    }
}
