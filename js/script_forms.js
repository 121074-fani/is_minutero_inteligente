/**
 * script_forms.js — Flujo de minuta con token OTP
 * Estructura del HTML:
 *   - #p1-lugar, #p1-correo  → siempre visibles (Paso 1)
 *   - #seccionToken          → aparece al solicitar token (Paso 2)
 *   - #formularioPrincipal   → bloqueado hasta verificar token (Pasos 3-4)
 */

let idMinutaActual = null;
let minutaValidada = false;
let pdfBase64Global = '';

// ── Helpers ──────────────────────────────────────────────────
const $ = id => document.getElementById(id);

function setMsg(id, txt, tipo) {
    const el = $(id);
    if (!el) return;
    el.className = `alert alert-${tipo} py-2 small`;
    el.innerHTML = txt;
    el.style.display = 'block';
}

// ── Bloqueo / desbloqueo del formulario principal ────────────
function bloquear() {
    const fp = $('formularioPrincipal');
    fp.classList.add('form-bloqueado');
    $('lockMsg').style.display = 'block';
    $('btnGuardar').disabled = true;
    // Deshabilitar inputs dentro del form
    fp.querySelectorAll('input,select,textarea').forEach(el => {
        el.disabled = true;
        el.style.opacity = '.5';
    });
    fp.querySelectorAll('button:not(#btnGuardar)').forEach(el => {
        el.disabled = true;
        el.style.opacity = '.5';
    });
}

function desbloquear() {
    const fp = $('formularioPrincipal');
    fp.classList.remove('form-bloqueado');
    $('lockMsg').style.display = 'none';
    $('btnGuardar').disabled = false;
    fp.querySelectorAll('input,select,textarea').forEach(el => {
        el.disabled = false;
        el.style.opacity = '1';
    });
    fp.querySelectorAll('button').forEach(el => {
        el.disabled = false;
        el.style.opacity = '1';
    });
}

// ── Tablas dinámicas ─────────────────────────────────────────
function agregarFila() {
    const tbody = $('tablaTemas').querySelector('tbody');
    const n = tbody.rows.length + 1;
    const r = tbody.insertRow();
    r.innerHTML = `
        <td>${n}</td>
        <td><input type="text" name="tema[]" placeholder="Tema de la reunión"></td>
        <td><input type="text" name="descripcion[]" placeholder="Descripción"></td>
        <td><button type="button" class="btn btn-danger btn-sm py-0" onclick="eliminarFila(this)">✕</button></td>`;
}

function agregarAcuerdo() {
    const tbody = $('tablaAcuerdos').querySelector('tbody');
    const r = tbody.insertRow();
    r.innerHTML = `
        <td><input type="text" name="responsable[]" placeholder="Nombre"></td>
        <td><input type="text" name="actividad[]" placeholder="Acuerdo"></td>
        <td><input type="date" name="fecha_acuerdo[]"></td>
        <td><button type="button" class="btn btn-danger btn-sm py-0" onclick="eliminarFila(this)">✕</button></td>`;
}

function eliminarFila(btn) {
    btn.closest('tr').remove();
    // Renumerar temas
    document.querySelectorAll('#tablaTemas tbody tr').forEach((r, i) => {
        r.cells[0].innerText = i + 1;
    });
}

function agregarParticipante() {
    const tbody = $('tablaParticipantes').querySelector('tbody');
    const r = tbody.insertRow();
    r.innerHTML = `
        <td><input type="text" class="form-control form-control-sm" placeholder="Nombre completo"></td>
        <td><input type="email" class="form-control form-control-sm" placeholder="correo@ejemplo.com"></td>
        <td><button type="button" class="btn btn-danger btn-sm py-0"
            onclick="this.closest('tr').remove()">✕</button></td>`;
}

// ── Limpiar todo ─────────────────────────────────────────────
function limpiarFormulario() {
    $('p1-lugar').value = '';
    $('p1-correo').value = '';
    $('formMinuta').reset();
    $('tablaTemas').querySelector('tbody').innerHTML = '';
    $('tablaAcuerdos').querySelector('tbody').innerHTML = '';
    agregarFila();
    agregarAcuerdo();
    $('seccionToken').style.display = 'none';
    $('seccionParticipantes').style.display = 'none';
    $('mensaje').style.display = 'none';
    $('msgPaso1').style.display = 'none';
    idMinutaActual = null;
    minutaValidada = false;
    pdfBase64Global = '';
    bloquear();
}

// ════════════════════════════════════════════════════════════
// PASO 1 — Solicitar Token OTP
// ════════════════════════════════════════════════════════════
async function solicitarToken() {
    const correo = $('p1-correo').value.trim();
    const lugar  = $('p1-lugar').value.trim();

    if (!lugar) {
        setMsg('msgPaso1', '⚠️ Ingresa el lugar de la reunión.', 'warning'); return;
    }
    if (!correo || !correo.includes('@')) {
        setMsg('msgPaso1', '⚠️ Ingresa un correo válido del responsable.', 'warning'); return;
    }

    const btn = $('btnSolicitarToken');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Enviando...';

    try {
        const res = await fetch('api.php?action=minutas.token', {
            method:  'POST',
            headers: { 'Content-Type': 'application/json' },
            body:    JSON.stringify({ correo, lugar })
        });

        const data = await res.json();

        if (data.exito) {
            idMinutaActual = data.id_minuta;

            // Mostrar sección de token
            $('seccionToken').style.display = 'block';
            $('seccionToken').scrollIntoView({ behavior: 'smooth', block: 'nearest' });

            // Mostrar token en pantalla (simulación XAMPP)
            if (data.token) {
                $('tokenSimulado').textContent = data.token;
                $('alertaSimulado').style.display = 'block';
            }

            setMsg('msgPaso1',
                `✅ Token enviado a <b>${correo}</b>. Ingresa el código en el paso 2.`,
                'success');
        } else {
            setMsg('msgPaso1', '❌ ' + (data.mensaje || 'Error al generar token.'), 'danger');
        }

    } catch (err) {
        setMsg('msgPaso1',
            '❌ Error de conexión. Verifica que XAMPP esté corriendo y que ' +
            '<a href="api.php" target="_blank">api.php</a> responda.',
            'danger');
        console.error('solicitarToken error:', err);
    }

    btn.disabled = false;
    btn.innerHTML = '<i class="fas fa-paper-plane"></i>Solicitar Token';
}

// ════════════════════════════════════════════════════════════
// PASO 2 — Verificar Token → DESBLOQUEAR formulario
// ════════════════════════════════════════════════════════════
async function verificarToken() {
    const token = $('inputToken').value.trim();
    if (token.length !== 6) {
        setMsg('msgPaso1', '⚠️ El token tiene 6 dígitos.', 'warning'); return;
    }

    const btn = $('btnVerificarToken');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Verificando...';

    try {
        const res = await fetch('api.php?action=minutas.verificar', {
            method:  'POST',
            headers: { 'Content-Type': 'application/json' },
            body:    JSON.stringify({ id_minuta: idMinutaActual, token })
        });

        const data = await res.json();

        if (data.exito) {
            minutaValidada = true;

            // UI feedback
            $('tokenOkIcon').style.display = 'inline';
            $('inputToken').disabled = true;
            btn.style.display = 'none';
            $('seccionToken').classList.add('validado');

            // Copiar lugar y correo al form principal
            const lugarInput = document.querySelector('[name="lugar"]');
            const correoInput = document.querySelector('[name="correo"]');
            if (lugarInput)  lugarInput.value  = $('p1-lugar').value;
            if (correoInput) correoInput.value  = $('p1-correo').value;

            // ✅ DESBLOQUEAR el formulario
            desbloquear();

            setMsg('msgPaso1',
                '✅ <b>Reunión validada.</b> Ahora completa los datos, temas y acuerdos.',
                'success');

            // Scroll al formulario
            $('formularioPrincipal').scrollIntoView({ behavior: 'smooth', block: 'start' });

        } else {
            setMsg('msgPaso1', '❌ ' + (data.mensaje || 'Token incorrecto.'), 'danger');
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-check me-1"></i>Verificar';
        }

    } catch (err) {
        setMsg('msgPaso1', '❌ Error de conexión al verificar.', 'danger');
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-check me-1"></i>Verificar';
        console.error('verificarToken error:', err);
    }
}

// ════════════════════════════════════════════════════════════
// PASO 3 — Guardar Minuta + autoagregar tareas + generar PDF
// ════════════════════════════════════════════════════════════
async function guardarMinuta() {
    if (!minutaValidada) {
        setMsg('mensaje', '⚠️ Debes verificar el token primero (pasos 1 y 2).', 'warning');
        return;
    }

    const form = $('formMinuta');
    const fd   = new FormData(form);

    // Recopilar temas
    const temas = [];
    document.querySelectorAll('#tablaTemas tbody tr').forEach(r => {
        const inputs = r.querySelectorAll('input');
        if (inputs[0]?.value.trim()) {
            temas.push({
                titulo:      inputs[0].value.trim(),
                descripcion: inputs[1]?.value.trim() || ''
            });
        }
    });

    // Recopilar acuerdos
    const acuerdos = [];
    document.querySelectorAll('#tablaAcuerdos tbody tr').forEach(r => {
        const inputs = r.querySelectorAll('input');
        if (inputs[0]?.value.trim()) {
            acuerdos.push({
                responsable:   inputs[0].value.trim(),
                actividad:     inputs[1]?.value.trim() || '',
                fecha_acuerdo: inputs[2]?.value || ''
            });
        }
    });

    const payload = {
        id_minuta:     idMinutaActual,
        lugar:         $('p1-lugar').value.trim(),
        fecha:         fd.get('fecha')         || '',
        hora:          fd.get('hora')          || '',
        correo:        $('p1-correo').value.trim(),
        tipo:          fd.get('tipo')          || 'presencial',
        area:          fd.get('area')          || '',
        fecha_proxima: fd.get('fecha_proxima') || null,
        hora_proxima:  fd.get('hora_proxima')  || null,
        temas,
        acuerdos
    };

    const btn = $('btnGuardar');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Guardando...';

    try {
        const res  = await fetch('api.php?action=minutas.guardar', {
            method:  'POST',
            headers: { 'Content-Type': 'application/json' },
            body:    JSON.stringify(payload)
        });
        const data = await res.json();

        if (!data.exito) throw new Error(data.mensaje || 'Error al guardar');

        setMsg('mensaje',
            `✅ Minuta #${idMinutaActual} guardada. ` +
            `<b>${data.tareas_creadas || 0} tareas</b> agregadas al panel de seguimiento.`,
            'success');

    } catch (e) {
        setMsg('mensaje', '❌ Error: ' + e.message, 'danger');
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-save"></i>Guardar Minuta y PDF';
        return;
    }

    // ── Generar PDF ──────────────────────────────────────────
    try {
        const { jsPDF } = window.jspdf;
        const doc = new jsPDF();
        doc.setFontSize(18);
        doc.text('Minuta de Reunión', 14, 20);
        doc.setFontSize(11);
        doc.text(`Lugar: ${payload.lugar}`,          14, 30);
        doc.text(`Fecha: ${payload.fecha}  Hora: ${payload.hora || '—'}`, 14, 37);
        doc.text(`Área: ${payload.area || '—'}`,      14, 44);
        doc.text(`Responsable: ${payload.correo}`,   14, 51);

        if (temas.length) {
            doc.autoTable({
                startY: 60,
                head:   [['#', 'Tema', 'Descripción']],
                body:   temas.map((t, i) => [i + 1, t.titulo, t.descripcion]),
                theme:  'grid',
                headStyles: { fillColor: [74, 35, 90] }
            });
        }
        if (acuerdos.length) {
            doc.autoTable({
                startY: (doc.lastAutoTable?.finalY || 60) + 8,
                head:   [['Responsable', 'Acuerdo', 'Fecha límite']],
                body:   acuerdos.map(a => [a.responsable, a.actividad, a.fecha_acuerdo]),
                theme:  'striped',
                headStyles: { fillColor: [39, 174, 96] }
            });
        }

        const fy = (doc.lastAutoTable?.finalY || 80) + 14;
        doc.setFont('helvetica', 'bold');
        doc.text('Próxima Reunión:', 14, fy);
        doc.setFont('helvetica', 'normal');
        doc.text(`${payload.fecha_proxima || '—'}  ${payload.hora_proxima || ''}`, 14, fy + 7);

        doc.save(`Minuta_${payload.fecha || 'sin-fecha'}_${idMinutaActual}.pdf`);
        pdfBase64Global = doc.output('datauristring').split(',')[1];

    } catch (pdfErr) {
        console.warn('Error al generar PDF:', pdfErr);
    }

    // Mostrar sección participantes
    $('seccionParticipantes').style.display = 'block';
    $('seccionParticipantes').scrollIntoView({ behavior: 'smooth' });

    btn.disabled = false;
    btn.innerHTML = '<i class="fas fa-save"></i>Guardar Minuta y PDF';

    // Actualizar caché local de tareas
    try {
        const r2 = await fetch('api.php?action=tareas.listar');
        const d2 = await r2.json();
        if (d2.exito) {
            try { localStorage.setItem('calendar_tasks_isc', JSON.stringify(d2.tareas)); } catch(e) {}
        }
    } catch {}
}

// ════════════════════════════════════════════════════════════
// PASO 4 — Enviar PDF + tokens de firma a participantes
// ════════════════════════════════════════════════════════════
async function enviarPDFParticipantes() {
    const participantes = [];
    document.querySelectorAll('#tablaParticipantes tbody tr').forEach(r => {
        const inputs = r.querySelectorAll('input');
        const nombre = inputs[0]?.value.trim();
        const correo = inputs[1]?.value.trim();
        if (nombre && correo) participantes.push({ nombre, correo });
    });

    if (!participantes.length) {
        setMsg('mensaje', '⚠️ Agrega al menos un participante.', 'warning');
        return;
    }

    const btn = $('btnEnviarParticipantes');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Enviando...';

    try {
        const res  = await fetch('api.php?action=minutas.firmas', {
            method:  'POST',
            headers: { 'Content-Type': 'application/json' },
            body:    JSON.stringify({
                id_minuta:      idMinutaActual,
                participantes,
                pdf_base64:     pdfBase64Global
            })
        });
        const data = await res.json();

        let html = data.exito
            ? `✅ ${data.mensaje}`
            : `⚠️ ${data.mensaje}`;

        if (data.tokens_generados?.length) {
            html += '<hr class="my-2"><b>🔐 Tokens generados</b> (válidos 72h):<br>';
            html += '<small class="text-muted">En producción llegan por correo. En XAMPP se muestran aquí:</small>';
            data.tokens_generados.forEach(t => {
                html += `<div class="mt-1 p-2 rounded" style="background:#f9f5fc;font-size:.78rem;">
                    <b>${t.nombre}</b> — ${t.correo}<br>
                    <a href="${t.url}" target="_blank" style="color:#9b59b6;word-break:break-all;">${t.url}</a>
                </div>`;
            });
        }

        setMsg('mensaje', html, data.exito ? 'success' : 'warning');
        $('seccionEstadoFirmas').style.display = 'block';
        verEstadoFirmas();

    } catch {
        setMsg('mensaje', '❌ Error de conexión al enviar.', 'danger');
    }

    btn.disabled = false;
    btn.innerHTML = '<i class="fas fa-paper-plane"></i>Enviar PDF y Tokens de Firma';
}

async function verEstadoFirmas() {
    if (!idMinutaActual) return;
    try {
        const res  = await fetch(`api.php?action=minutas.estadoFirmas&id_minuta=${idMinutaActual}`);
        const data = await res.json();
        const cont = $('tablaEstadoFirmas');
        if (!data.firmas?.length) {
            cont.innerHTML = '<p class="text-muted small">Sin firmas aún.</p>';
            return;
        }
        cont.innerHTML = data.firmas.map(f => `
            <div class="d-flex align-items-center gap-3 p-2 mb-1 rounded" style="background:#f9f5fc;">
                <i class="fas fa-${f.firmado ? 'check-circle text-success' : 'clock text-warning'}"></i>
                <div class="flex-grow-1">
                    <div class="fw-semibold" style="font-size:.86rem;color:#4a235a;">${f.nombre}</div>
                    <div style="font-size:.74rem;color:#888;">${f.correo}</div>
                </div>
                <span class="badge" style="font-size:.7rem;background:${
                    f.firmado ? '#d1e7dd;color:#0f5132' : '#fef3cd;color:#856404'
                }">${f.firmado ? '✅ Firmado' : '⏳ Pendiente'}</span>
            </div>`).join('');
    } catch {}
}

// ── Init ──────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
    bloquear(); // Formulario bloqueado al cargar
    $('formMinuta').addEventListener('submit', e => e.preventDefault());

    // Autocompletar correo del responsable con el usuario en sesión
    try {
        const raw = (
            (()=>{ try{return localStorage.getItem('user_session')}catch(e){return null} })() ||
            (()=>{ try{return sessionStorage.getItem('user_session')}catch(e){return null} })() ||
            (window._mem?.user_session ?? null)
        );
        if (raw) {
            const u = JSON.parse(raw);
            const correoField = $('p1-correo');
            if (correoField && u.correo) {
                correoField.value = u.correo;
                correoField.style.background = '#f9f5fc';
            }
        }
    } catch(e) {}
});
