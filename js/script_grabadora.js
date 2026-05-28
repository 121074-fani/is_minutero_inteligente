/**
 * script_grabadora.js
 * Grabación + transcripción en tiempo real (Web Speech API)
 * Al detener → Claude extrae los datos y rellena el formulario.
 *
 * Compatibilidad: Chrome / Edge (Web Speech API)
 * Firefox: solo grabación, sin transcripción en tiempo real.
 */

// ── Estado ────────────────────────────────────────────────────
let mediaRecorder   = null;
let audioChunks     = [];
let audioBlob       = null;
let timerInterval   = null;
let segundos        = 0;
let grabando        = false;
let recognition     = null;       // SpeechRecognition
let transcripcion   = '';         // texto acumulado

// ── Elementos del DOM ────────────────────────────────────────
const btnMic       = document.getElementById('btnMic');
const btnDescargar = document.getElementById('btnDescargarAudio');
const btnIA        = document.getElementById('btnAnalizarIA');
const recStatus    = document.getElementById('rec-status');
const recTimer     = document.getElementById('rec-timer');
const audioPlayer  = document.getElementById('audioPlayer');
const iaResultado  = document.getElementById('ia-resultado');
const iaTexto      = document.getElementById('ia-texto');

// ── Soporte de Web Speech API ────────────────────────────────
const SpeechRecognition =
    window.SpeechRecognition || window.webkitSpeechRecognition || null;

// ── Formato tiempo ────────────────────────────────────────────
function fmt(s) {
    return String(Math.floor(s / 60)).padStart(2,'0') + ':' + String(s % 60).padStart(2,'0');
}

// ════════════════════════════════════════════════════════════
// INICIAR GRABACIÓN
// ════════════════════════════════════════════════════════════
btnMic.addEventListener('click', async () => {
    if (grabando) {
        detenerGrabacion();
    } else {
        await iniciarGrabacion();
    }
});

async function iniciarGrabacion() {
    try {
        const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
        mediaRecorder = new MediaRecorder(stream);
        audioChunks   = [];
        transcripcion = '';

        mediaRecorder.ondataavailable = e => {
            if (e.data.size > 0) audioChunks.push(e.data);
        };

        mediaRecorder.onstop = () => {
            audioBlob = new Blob(audioChunks, { type: 'audio/webm' });
            audioPlayer.src = URL.createObjectURL(audioBlob);
            audioPlayer.style.display = 'block';
            btnDescargar.disabled = false;
            btnIA.disabled        = false;
            recStatus.textContent = `Grabación lista · ${fmt(segundos)} · Procesando transcripción...`;

            // Si hay transcripción rellenar formulario automáticamente
            if (transcripcion.trim().length > 10) {
                rellenarConIA(transcripcion);
            } else {
                recStatus.textContent = `Grabación lista · ${fmt(segundos)}`;
                mostrarTranscripcion('No se detectó voz clara. Prueba hablar más cerca del micrófono.', false);
            }
        };

        mediaRecorder.start(200);
        grabando = true;

        // ── Web Speech API ──────────────────────────────────
        if (SpeechRecognition) {
            recognition = new SpeechRecognition();
            recognition.lang          = 'es-MX';
            recognition.continuous    = true;
            recognition.interimResults = true;
            recognition.maxAlternatives = 1;

            // Acumular texto final
            recognition.onresult = e => {
                let interim = '';
                for (let i = e.resultIndex; i < e.results.length; i++) {
                    if (e.results[i].isFinal) {
                        transcripcion += e.results[i][0].transcript + ' ';
                    } else {
                        interim += e.results[i][0].transcript;
                    }
                }
                // Mostrar transcripción en vivo
                const liveDiv  = document.getElementById('transcripcion-live');
                const liveText = document.getElementById('texto-live');
                if (liveDiv)  liveDiv.style.display = 'block';
                if (liveText) {
                    const mostrar = transcripcion + (interim ? '<em style="color:#9b59b6;">' + interim + '</em>' : '');
                    liveText.innerHTML = mostrar || 'Esperando voz...';
                }
                recStatus.textContent = '🎙 Escuchando...';
            };

            recognition.onerror = e => {
                if (e.error !== 'no-speech') {
                    console.warn('SpeechRecognition error:', e.error);
                }
            };

            // Reiniciar automáticamente si se interrumpe (Chrome lo corta cada ~60s)
            recognition.onend = () => {
                if (grabando) recognition.start();
            };

            recognition.start();
            recStatus.textContent = '🎙 Escuchando... habla claramente';
        } else {
            recStatus.textContent = '⚠️ Grabando (transcripción no disponible en este navegador)';
        }

        // ── UI ──────────────────────────────────────────────
        btnMic.classList.add('recording');
        btnMic.innerHTML = '<i class="fas fa-stop"></i>';
        recTimer.style.display = 'block';
        recTimer.textContent   = '00:00';
        segundos = 0;
        timerInterval = setInterval(() => {
            segundos++;
            recTimer.textContent = fmt(segundos);
        }, 1000);

    } catch (err) {
        alert('No se pudo acceder al micrófono. Verifica los permisos del navegador.');
        console.error(err);
    }
}

// ════════════════════════════════════════════════════════════
// DETENER GRABACIÓN
// ════════════════════════════════════════════════════════════
function detenerGrabacion() {
    // Ocultar transcripción en vivo
    const liveDiv = document.getElementById('transcripcion-live');
    if (liveDiv) liveDiv.style.display = 'none';

    if (recognition) {
        recognition.onend = null; // Evitar que se reinicie
        recognition.stop();
    }
    if (mediaRecorder && mediaRecorder.state !== 'inactive') {
        mediaRecorder.stop();
        mediaRecorder.stream.getTracks().forEach(t => t.stop());
    }
    clearInterval(timerInterval);
    grabando = false;
    btnMic.classList.remove('recording');
    btnMic.innerHTML      = '<i class="fas fa-microphone"></i>';
    recTimer.style.display = 'none';
}

// ════════════════════════════════════════════════════════════
// RELLENAR FORMULARIO CON IA (Claude)
// Recibe la transcripción y extrae: lugar, fecha, hora, área,
// tipo, temas y acuerdos para poblar el formulario.
// ════════════════════════════════════════════════════════════
async function rellenarConIA(texto) {
    mostrarTranscripcion('Analizando la grabación con IA...', true);
    iaResultado.style.display = 'block';
    iaTexto.textContent = '⏳ Procesando transcripción y extrayendo datos del formulario...';

    const hoy = new Date().toISOString().split('T')[0]; // YYYY-MM-DD

    const prompt = `Eres un asistente que extrae información de minutas de reunión a partir de una transcripción de audio.

TRANSCRIPCIÓN:
"""
${texto}
"""

Extrae la información y responde ÚNICAMENTE con un objeto JSON válido, sin texto adicional, sin bloques de código, sin explicaciones. Solo el JSON puro.

El JSON debe tener exactamente esta estructura:
{
  "lugar": "nombre del lugar o sala mencionado, si no se menciona usa cadena vacía",
  "fecha": "fecha en formato YYYY-MM-DD, si no se menciona usa ${hoy}",
  "hora": "hora en formato HH:MM, si no se menciona usa cadena vacía",
  "tipo": "presencial o virtual según se mencione, default presencial",
  "area": "área o departamento mencionado: Dirección General, TI, Administración, Ventas, Recursos Humanos, o cadena vacía",
  "temas": [
    {"titulo": "tema 1", "descripcion": "descripción breve"},
    {"titulo": "tema 2", "descripcion": "descripción breve"}
  ],
  "acuerdos": [
    {"responsable": "nombre del responsable", "actividad": "descripción del acuerdo o tarea", "fecha_acuerdo": "YYYY-MM-DD o cadena vacía"},
    {"responsable": "nombre", "actividad": "tarea", "fecha_acuerdo": ""}
  ]
}

Reglas importantes:
- Si no se mencionan temas, devuelve temas como array vacío []
- Si no se mencionan acuerdos ni tareas, devuelve acuerdos como array vacío []
- Fechas siempre en formato YYYY-MM-DD
- Horas siempre en formato HH:MM (24h)
- Extrae TODOS los acuerdos, compromisos o tareas mencionadas con su responsable
- Solo JSON puro, sin markdown, sin texto adicional`;

    try {
        const response = await fetch('https://api.anthropic.com/v1/messages', {
            method:  'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                model:      'claude-sonnet-4-20250514',
                max_tokens: 1000,
                messages:   [{ role: 'user', content: prompt }]
            })
        });

        const data  = await response.json();
        const texto_respuesta = data.content?.[0]?.text || '';

        // Parsear JSON de la respuesta
        let datos;
        try {
            // Limpiar posibles backticks o texto extra
            const limpio = texto_respuesta
                .replace(/```json/gi, '')
                .replace(/```/g, '')
                .trim();
            datos = JSON.parse(limpio);
        } catch {
            throw new Error('La IA no devolvió JSON válido: ' + texto_respuesta.substring(0, 100));
        }

        // ── Rellenar formulario ──────────────────────────────
        aplicarDatosAlFormulario(datos);

        // ── Mostrar transcripción y resumen ──────────────────
        let resumen = '✅ Formulario rellenado automáticamente.\n\n';
        resumen += `📍 Lugar: ${datos.lugar || '(no detectado)'}\n`;
        resumen += `📅 Fecha: ${datos.fecha || '(no detectada)'}\n`;
        resumen += `🏢 Área: ${datos.area || '(no detectada)'}\n`;
        resumen += `📋 Temas extraídos: ${datos.temas?.length || 0}\n`;
        resumen += `✅ Acuerdos extraídos: ${datos.acuerdos?.length || 0}\n\n`;
        resumen += `📝 Transcripción:\n"${transcripcion.trim()}"`;

        iaTexto.textContent = resumen;
        recStatus.textContent = `✅ Formulario rellenado · ${datos.temas?.length || 0} temas · ${datos.acuerdos?.length || 0} acuerdos`;

    } catch (err) {
        iaTexto.textContent = '❌ Error al procesar con IA: ' + err.message +
            '\n\nTranscripción capturada:\n"' + transcripcion.trim() + '"';
        recStatus.textContent = 'Error al procesar — revisa la consola';
        console.error(err);
    }
}

// ════════════════════════════════════════════════════════════
// APLICAR DATOS AL FORMULARIO
// ════════════════════════════════════════════════════════════
function aplicarDatosAlFormulario(datos) {
    // Paso 1: lugar y fecha (campos siempre visibles)
    if (datos.lugar) {
        const lugarEl = document.getElementById('p1-lugar');
        if (lugarEl) lugarEl.value = datos.lugar;
    }

    // Dentro del form (pueden estar deshabilitados si no se verificó el token)
    const form = document.getElementById('formMinuta');

    if (datos.fecha) {
        const fechaEl = form?.querySelector('[name="fecha"]') || document.querySelector('[name="fecha"]');
        if (fechaEl) fechaEl.value = datos.fecha;
    }
    if (datos.hora) {
        const horaEl = form?.querySelector('[name="hora"]') || document.querySelector('[name="hora"]');
        if (horaEl) horaEl.value = datos.hora;
    }
    if (datos.tipo) {
        const tipoEl = form?.querySelector('[name="tipo"]') || document.querySelector('[name="tipo"]');
        if (tipoEl) tipoEl.value = datos.tipo;
    }
    if (datos.area) {
        const areaEl = form?.querySelector('[name="area"]') || document.querySelector('[name="area"]');
        if (areaEl) {
            // Intentar coincidir con las opciones del select
            const opciones = [...(areaEl.options || [])];
            const match    = opciones.find(o =>
                o.value.toLowerCase().includes(datos.area.toLowerCase()) ||
                datos.area.toLowerCase().includes(o.value.toLowerCase())
            );
            if (match) areaEl.value = match.value;
        }
    }

    // Temas — rellenar tabla
    if (datos.temas?.length > 0) {
        const tbody = document.querySelector('#tablaTemas tbody');
        if (tbody) {
            tbody.innerHTML = '';
            datos.temas.forEach((t, i) => {
                const tr = tbody.insertRow();
                tr.innerHTML = `
                    <td>${i + 1}</td>
                    <td><input type="text" name="tema[]" value="${escapar(t.titulo)}" placeholder="Tema"></td>
                    <td><input type="text" name="descripcion[]" value="${escapar(t.descripcion)}" placeholder="Descripción"></td>
                    <td><button type="button" class="btn btn-danger btn-sm py-0" onclick="eliminarFila(this)">✕</button></td>`;
            });
        }
    }

    // Acuerdos — rellenar tabla
    if (datos.acuerdos?.length > 0) {
        const tbody = document.querySelector('#tablaAcuerdos tbody');
        if (tbody) {
            tbody.innerHTML = '';
            datos.acuerdos.forEach(a => {
                const tr = tbody.insertRow();
                tr.innerHTML = `
                    <td><input type="text" name="responsable[]" value="${escapar(a.responsable)}" placeholder="Nombre"></td>
                    <td><input type="text" name="actividad[]"   value="${escapar(a.actividad)}" placeholder="Acuerdo"></td>
                    <td><input type="date" name="fecha_acuerdo[]" value="${a.fecha_acuerdo || ''}"></td>
                    <td><button type="button" class="btn btn-danger btn-sm py-0" onclick="eliminarFila(this)">✕</button></td>`;
            });
        }
    }

    // Flash visual para indicar que se rellenó
    ['p1-lugar','p1-correo'].forEach(id => {
        const el = document.getElementById(id);
        if (el && el.value) {
            el.style.transition = 'background .4s';
            el.style.background = '#d1e7dd';
            setTimeout(() => { el.style.background = ''; }, 2000);
        }
    });
}

function escapar(str) {
    return (str || '').replace(/"/g, '&quot;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
}

// ════════════════════════════════════════════════════════════
// DESCARGAR AUDIO
// ════════════════════════════════════════════════════════════
btnDescargar.addEventListener('click', () => {
    if (!audioBlob) return;
    const a    = document.createElement('a');
    a.href     = URL.createObjectURL(audioBlob);
    a.download = `reunion_${new Date().toISOString().slice(0,10)}.webm`;
    a.click();
});

// ════════════════════════════════════════════════════════════
// ANALIZAR CON IA (botón manual — análisis narrativo)
// ════════════════════════════════════════════════════════════
btnIA.addEventListener('click', async () => {
    if (!transcripcion.trim() && !audioBlob) return;

    iaResultado.style.display = 'block';
    iaTexto.textContent       = '⏳ Generando diagnóstico...';
    btnIA.disabled            = true;
    btnIA.innerHTML           = '<i class="fas fa-spinner fa-spin me-1"></i>Analizando...';

    // Contexto del formulario actual
    const lugar  = document.getElementById('p1-lugar')?.value  || '—';
    const correo = document.getElementById('p1-correo')?.value || '—';
    const area   = document.querySelector('[name="area"]')?.value || '—';

    const textoAnalizar = transcripcion.trim() ||
        `Reunión en ${lugar}, área ${area}, responsable ${correo}`;

    const prompt = `Eres un experto en análisis de reuniones de trabajo.

Transcripción / contexto de la reunión:
"""
${textoAnalizar}
"""

Genera un diagnóstico profesional con:
1. **Resumen ejecutivo** (2-3 oraciones)
2. **Puntos clave** identificados
3. **Riesgos o pendientes** que requieren atención
4. **Recomendaciones** para el seguimiento
5. **Evaluación de productividad** (Alta/Media/Baja) con justificación

Sé concreto y enfocado en acciones.`;

    try {
        const res  = await fetch('https://api.anthropic.com/v1/messages', {
            method:  'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                model:      'claude-sonnet-4-20250514',
                max_tokens: 1000,
                messages:   [{ role: 'user', content: prompt }]
            })
        });
        const data = await res.json();
        iaTexto.textContent = data.content?.[0]?.text || 'Sin respuesta de la IA.';
    } catch (err) {
        iaTexto.textContent = '❌ Error al conectar con la IA: ' + err.message;
    }

    btnIA.disabled  = false;
    btnIA.innerHTML = '<i class="fas fa-robot me-1"></i>Analizar IA';
});

// ── Helpers UI ───────────────────────────────────────────────
function mostrarTranscripcion(msg, cargando) {
    iaResultado.style.display = 'block';
    if (cargando) {
        iaTexto.innerHTML = `<span style="color:#9b59b6;"><i class="fas fa-spinner fa-spin me-2"></i>${msg}</span>`;
    } else {
        iaTexto.textContent = msg;
    }
}
