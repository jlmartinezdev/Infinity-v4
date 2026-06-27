import axios from 'axios';
import Swal from 'sweetalert2';

const ACCION_SELECCIONAR_NODO = 'SELECCIONAR_NODO';
const ACCION_SELECCIONAR_TIPO_TECNOLOGIA = 'SELECCIONAR_TIPO_TECNOLOGIA';
const ACCION_SELECCIONAR_PLAN = 'SELECCIONAR_PLAN';
const ACCION_CREAR_USUARIO = 'CREAR_USUARIO';
const ACCION_FINALIZAR = 'FINALIZAR';

export function getSwalThemeOptions() {
    const isDark = document.documentElement.classList.contains('dark');
    if (!isDark) return {};

    return {
        background: '#1f2937',
        color: '#f3f4f6',
        customClass: {
            popup: 'border border-gray-700',
            title: 'text-gray-100',
            htmlContainer: 'text-gray-200',
            confirmButton: 'focus:!ring-2 focus:!ring-offset-2 focus:!ring-offset-gray-800',
            cancelButton: 'focus:!ring-2 focus:!ring-offset-2 focus:!ring-offset-gray-800',
        },
    };
}

function getAccionesFromParametro(parametro) {
    if (!parametro || typeof parametro !== 'string') return [];
    return parametro.split(',').map((s) => {
        const a = s.trim().toUpperCase();
        if (a === 'SELECCIONAR_NODO') return ACCION_SELECCIONAR_NODO;
        if (a === 'SELECIONAR_PLAN') return ACCION_SELECCIONAR_PLAN;
        return a;
    }).filter(Boolean);
}

function extraerTipoTecnologiaDeNotas(notas) {
    if (!notas || typeof notas !== 'string') return null;
    const prefix = 'Tipo tecnología:';
    const lines = notas.split(/\r?\n/);
    for (const line of lines) {
        const trimmed = line.trim();
        if (trimmed.startsWith(prefix)) {
            const jsonStr = trimmed.slice(prefix.length).trim();
            try {
                const data = JSON.parse(jsonStr);
                if (data && (data.id != null || data.value != null)) {
                    return { id: data.id, value: data.value };
                }
            } catch (_e) {
                // JSON inválido
            }
        }
    }
    return null;
}

function getPlanesFiltradosPorTecnologia(planes, tecnologiaId) {
    if (!planes || !planes.length) return [];
    if (tecnologiaId == null || tecnologiaId === '') return planes;
    const id = String(tecnologiaId);
    return planes.filter((p) => String(p.tecnologia_id || '') === id);
}

function actualizarSelectPlan(planes, tecnologiaId) {
    const selectPlan = document.getElementById('swal-select-plan');
    if (!selectPlan || !planes) return;
    const id = tecnologiaId != null && tecnologiaId !== '' ? String(tecnologiaId) : null;
    const filtrados = getPlanesFiltradosPorTecnologia(planes, id);
    const selected = selectPlan.value;
    if (id) {
        selectPlan.disabled = false;
        selectPlan.removeAttribute('disabled');
        selectPlan.innerHTML = '<option value="">-- Seleccionar plan --</option>' +
            filtrados.map((p) => `<option value="${p.plan_id}">${(p.nombre || '').replace(/"/g, '&quot;')}</option>`).join('');
        const sigueValido = filtrados.some((p) => String(p.plan_id) === selected);
        if (!sigueValido) selectPlan.value = '';
    } else {
        selectPlan.disabled = true;
        selectPlan.setAttribute('disabled', 'disabled');
        selectPlan.innerHTML = '<option value="">Seleccione primero el tipo de tecnología</option>';
        selectPlan.value = '';
    }
}

function aplicarFiltroPlanDesdeNotas(planes, acciones) {
    if (!acciones?.includes(ACCION_SELECCIONAR_PLAN) || !planes?.length) return;
    const notasInput = document.getElementById('swal-notas');
    if (!notasInput) return;
    const extraido = extraerTipoTecnologiaDeNotas(notasInput.value);
    actualizarSelectPlan(planes, extraido?.id ?? null);
}

function descripcionTecnologiaEsGpon(desc) {
    return /gpon|epon|ftth|fibra|fiber|pon|xg-pon/i.test(desc || '');
}

function descripcionTecnologiaEsWireless(desc) {
    return /wireless|inalambr|anten|radio|wifi/i.test(desc || '');
}

function nodosFiltradosPorTecnologia(nodos, tecnologiaId, tiposTecnologia) {
    if (!nodos?.length) return [];
    if (tecnologiaId == null || tecnologiaId === '') return nodos;
    const t = (tiposTecnologia || []).find((x) => String(x.tecnologia_id) === String(tecnologiaId));
    const desc = t?.descripcion || '';
    const esGpon = descripcionTecnologiaEsGpon(desc);
    const esWireless = descripcionTecnologiaEsWireless(desc);
    if (!esGpon && !esWireless) return nodos;
    return nodos.filter((n) => {
        if (esGpon && esWireless) return n.tecnologia_gpon || n.tecnologia_wireless;
        if (esGpon) return !!n.tecnologia_gpon;
        if (esWireless) return !!n.tecnologia_wireless;
        return true;
    });
}

function etiquetaNodoSelect(n) {
    const base = (n.descripcion || `Nodo #${n.nodo_id}`).replace(/"/g, '&quot;');
    const tech = n.tecnologias_etiqueta || '';
    return tech ? `${base} (${tech})` : base;
}

function obtenerTecnologiaIdDesdeSwal(acciones) {
    const auto = document.getElementById('swal-tecnologia-auto')?.value;
    if (auto) return auto;
    const desdeNodo = document.getElementById('swal-select-tecnologia-nodo')?.value;
    if (desdeNodo) return desdeNodo;
    if (acciones.includes(ACCION_SELECCIONAR_TIPO_TECNOLOGIA)) {
        return document.getElementById('swal-select-tecnologia')?.value || '';
    }
    return '';
}

function obtenerPoolIdDesdeSwal() {
    const auto = document.getElementById('swal-pool-auto')?.value;
    if (auto) return auto;
    return document.getElementById('swal-select-pool')?.value || '';
}

async function aplicarOpcionesNodoEnSwal(nodoId, planes, acciones, urlOpcionesNodoAprobacion) {
    const wrapTech = document.getElementById('swal-tecnologia-nodo-wrap');
    const wrapPool = document.getElementById('swal-pool-wrap');
    const selectTechNodo = document.getElementById('swal-select-tecnologia-nodo');
    const selectPool = document.getElementById('swal-select-pool');
    const autoTech = document.getElementById('swal-tecnologia-auto');
    const autoPool = document.getElementById('swal-pool-auto');
    const hintTech = document.getElementById('swal-tecnologia-auto-hint');

    const reset = () => {
        if (autoTech) autoTech.value = '';
        if (autoPool) autoPool.value = '';
        if (wrapTech) wrapTech.style.display = 'none';
        if (wrapPool) wrapPool.style.display = 'none';
        if (hintTech) {
            hintTech.style.display = 'none';
            hintTech.textContent = '';
        }
        if (selectTechNodo) selectTechNodo.innerHTML = '<option value="">-- Seleccionar tipo --</option>';
        if (selectPool) selectPool.innerHTML = '<option value="">-- Seleccionar pool --</option>';
    };

    if (!nodoId) {
        reset();
        if (acciones.includes(ACCION_SELECCIONAR_PLAN)) {
            actualizarSelectPlan(planes, null);
        }
        return;
    }

    if (!urlOpcionesNodoAprobacion) return;

    try {
        const url = urlOpcionesNodoAprobacion.replace('__id__', String(nodoId));
        const { data } = await axios.get(url);

        if (data.sin_pools_activos) {
            reset();
            Swal.showValidationMessage('El nodo no tiene pools de IP activos.');
            return;
        }
        if (data.sin_tecnologia_configurada) {
            reset();
            Swal.showValidationMessage('El nodo no tiene tipos de tecnología compatibles en el catálogo.');
            return;
        }

        Swal.resetValidationMessage();

        if (data.tecnologia_id_auto) {
            if (autoTech) autoTech.value = String(data.tecnologia_id_auto);
            if (wrapTech) wrapTech.style.display = 'none';
            if (hintTech) {
                const t = (data.tecnologias || []).find((x) => String(x.tecnologia_id) === String(data.tecnologia_id_auto));
                hintTech.textContent = `Tecnología asignada desde el nodo: ${t?.descripcion || 'automática'}`;
                hintTech.style.display = 'block';
            }
            if (acciones.includes(ACCION_SELECCIONAR_PLAN)) {
                actualizarSelectPlan(planes, data.tecnologia_id_auto);
            }
        } else if (data.requiere_seleccion_tecnologia && selectTechNodo && wrapTech) {
            if (autoTech) autoTech.value = '';
            selectTechNodo.innerHTML = '<option value="">-- Seleccionar tipo --</option>' +
                (data.tecnologias || []).map((t) =>
                    `<option value="${t.tecnologia_id}">${(t.descripcion || '').replace(/"/g, '&quot;')}</option>`,
                ).join('');
            wrapTech.style.display = 'block';
            if (hintTech) hintTech.style.display = 'none';
        }

        if (data.pool_id_auto) {
            if (autoPool) autoPool.value = String(data.pool_id_auto);
            if (wrapPool) wrapPool.style.display = 'none';
        } else if (data.requiere_seleccion_pool && selectPool && wrapPool) {
            if (autoPool) autoPool.value = '';
            selectPool.innerHTML = '<option value="">-- Seleccionar pool --</option>' +
                (data.pools || []).map((p) =>
                    `<option value="${p.pool_id}">${(p.label || '').replace(/"/g, '&quot;')}</option>`,
                ).join('');
            wrapPool.style.display = 'block';
        }
    } catch (_e) {
        Swal.showValidationMessage('No se pudieron cargar las opciones del nodo.');
    }
}

function buildHtmlAcciones(acciones, nodos, planes, tiposTecnologia, tecnologiaIdSeleccionado = null) {
    let html = '';
    const tieneSeleccionNodo = acciones.includes(ACCION_SELECCIONAR_NODO);
    const nodosSelect = nodosFiltradosPorTecnologia(nodos, tecnologiaIdSeleccionado, tiposTecnologia);
    if (tieneSeleccionNodo && nodosSelect.length) {
        html += `
            <div class="mb-3">
                <label class="block text-sm font-medium text-gray-700 mb-1">Nodo</label>
                <select id="swal-select-nodo" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-purple-500 focus:border-purple-500">
                    <option value="">-- Seleccionar nodo --</option>
                    ${nodosSelect.map((n) => `<option value="${n.nodo_id}">${etiquetaNodoSelect(n)}</option>`).join('')}
                </select>
            </div>
            <input type="hidden" id="swal-tecnologia-auto" value="">
            <input type="hidden" id="swal-pool-auto" value="">
            <p id="swal-tecnologia-auto-hint" class="mb-2 text-xs text-green-700" style="display:none"></p>
            <div id="swal-tecnologia-nodo-wrap" class="mb-3" style="display:none">
                <label class="block text-sm font-medium text-gray-700 mb-1">Tipo de tecnología</label>
                <select id="swal-select-tecnologia-nodo" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-purple-500 focus:border-purple-500">
                    <option value="">-- Seleccionar tipo --</option>
                </select>
                <p class="mt-0.5 text-xs text-gray-500">El nodo maneja GPON y Wireless; elegí cuál aplica a este pedido.</p>
            </div>
            <div id="swal-pool-wrap" class="mb-3" style="display:none">
                <label class="block text-sm font-medium text-gray-700 mb-1">Pool de IP</label>
                <select id="swal-select-pool" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-purple-500 focus:border-purple-500">
                    <option value="">-- Seleccionar pool --</option>
                </select>
                <p class="mt-0.5 text-xs text-gray-500">Este nodo tiene más de un pool activo.</p>
            </div>`;
    } else if (tieneSeleccionNodo && nodos?.length && nodosSelect.length === 0) {
        html += '<p class="mb-3 text-sm text-amber-700">No hay nodos configurados para la tecnología de este pedido. Revisá las tecnologías del nodo en Sistema → Nodos.</p>';
    }
    if (acciones.includes(ACCION_SELECCIONAR_TIPO_TECNOLOGIA) && !tieneSeleccionNodo && tiposTecnologia && tiposTecnologia.length) {
        html += `
            <div class="mb-3">
                <label class="block text-sm font-medium text-gray-700 mb-1">Tipo de tecnología</label>
                <select id="swal-select-tecnologia" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-purple-500 focus:border-purple-500">
                    <option value="">-- Seleccionar tipo --</option>
                    ${tiposTecnologia.map((t) => `<option value="${t.tecnologia_id}">${(t.descripcion || '').replace(/"/g, '&quot;')}</option>`).join('')}
                </select>
            </div>`;
    }
    if (acciones.includes(ACCION_SELECCIONAR_PLAN) && planes && planes.length) {
        const requiereTecnologiaPrimero = acciones.includes(ACCION_SELECCIONAR_TIPO_TECNOLOGIA) || tieneSeleccionNodo;
        const idParaFiltrar = (tecnologiaIdSeleccionado != null && tecnologiaIdSeleccionado !== '') ? tecnologiaIdSeleccionado : null;
        const tieneTecnologiaPreseleccionada = idParaFiltrar !== null;
        const planesToShow = requiereTecnologiaPrimero
            ? (tieneTecnologiaPreseleccionada ? getPlanesFiltradosPorTecnologia(planes, idParaFiltrar) : [])
            : getPlanesFiltradosPorTecnologia(planes, idParaFiltrar);
        const planDisabled = requiereTecnologiaPrimero && !tieneTecnologiaPreseleccionada;
        html += `
            <div class="mb-3">
                <label class="block text-sm font-medium text-gray-700 mb-1">Plan</label>
                <select id="swal-select-plan" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-purple-500 focus:border-purple-500" ${planDisabled ? 'disabled' : ''}>
                    <option value="">${planDisabled ? 'Seleccione primero el tipo de tecnología' : '-- Seleccionar plan --'}</option>
                    ${planesToShow.map((p) => `<option value="${p.plan_id}">${(p.nombre || '').replace(/"/g, '&quot;')}</option>`).join('')}
                </select>
                ${requiereTecnologiaPrimero ? '<p class="mt-0.5 text-xs text-gray-500">El plan se filtrará según el tipo de tecnología elegido.</p>' : ''}
            </div>`;
    }
    return html;
}

/**
 * @param {object} config
 * @param {object|number} pedido
 * @param {number} estadoId
 * @param {string|null} parametro
 * @param {{ reloadOnSuccess?: boolean }} [options]
 */
export async function aprobarEstadoPedido(config, pedido, estadoId, parametro, options = {}) {
    const { reloadOnSuccess = false } = options;
    const pedidoId = pedido?.pedido_id ?? pedido;
    const tecnologiaIdSeleccionado = pedido?.tecnologia_id_seleccionado ?? null;
    let notasValue = '';
    let nodoIdValue = null;
    let tecnologiaIdValue = null;
    let planIdValue = null;
    let poolIdValue = null;
    const acciones = getAccionesFromParametro(parametro);
    const htmlAcciones = buildHtmlAcciones(
        acciones,
        config.nodos,
        config.planes,
        config.tiposTecnologia,
        tecnologiaIdSeleccionado,
    );

    const result = await Swal.fire({
        ...getSwalThemeOptions(),
        title: '¿Aprobar este estado?',
        html: `
            <div class="text-left">
                <p class="mb-4">Una vez aprobado, este estado no se podrá modificar.</p>
                ${htmlAcciones}
                <label class="block text-sm font-medium text-gray-700 mb-1">Notas (opcional)</label>
                <textarea id="swal-notas"
                          rows="3"
                          maxlength="1000"
                          placeholder="Agregar notas sobre la aprobación..."
                          class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-purple-500 focus:border-purple-500 resize-none"></textarea>
                <p id="swal-char-count" class="mt-1 text-xs text-gray-500">0/1000 caracteres</p>
            </div>
        `,
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#16a34a',
        cancelButtonColor: '#6b7280',
        confirmButtonText: 'Sí, aprobar',
        cancelButtonText: 'Cancelar',
        didOpen: () => {
            const notasInput = document.getElementById('swal-notas');
            const charCount = document.getElementById('swal-char-count');
            if (notasInput && charCount) {
                notasInput.addEventListener('input', () => {
                    charCount.textContent = `${notasInput.value.length}/1000 caracteres`;
                });
            }
            const selectTecnologia = document.getElementById('swal-select-tecnologia');
            if (selectTecnologia) {
                const techIdInicial = tecnologiaIdSeleccionado != null && tecnologiaIdSeleccionado !== '' ? String(tecnologiaIdSeleccionado) : null;
                if (techIdInicial) {
                    selectTecnologia.value = techIdInicial;
                }
                const onTecnologiaChange = () => {
                    const valor = selectTecnologia.value;
                    if (!valor && techIdInicial) return;
                    actualizarSelectPlan(config.planes, valor || null);
                };
                queueMicrotask(() => selectTecnologia.addEventListener('change', onTecnologiaChange));
            } else if (!acciones.includes(ACCION_SELECCIONAR_NODO)) {
                if (!tecnologiaIdSeleccionado) {
                    aplicarFiltroPlanDesdeNotas(config.planes, acciones);
                }
            }
            const selectNodo = document.getElementById('swal-select-nodo');
            if (selectNodo && acciones.includes(ACCION_SELECCIONAR_NODO)) {
                const onNodoChange = () => {
                    aplicarOpcionesNodoEnSwal(selectNodo.value || '', config.planes, acciones, config.urlOpcionesNodoAprobacion);
                };
                const onTechNodoChange = () => {
                    const tid = document.getElementById('swal-select-tecnologia-nodo')?.value;
                    if (tid && acciones.includes(ACCION_SELECCIONAR_PLAN)) {
                        actualizarSelectPlan(config.planes, tid);
                    }
                };
                queueMicrotask(() => {
                    selectNodo.addEventListener('change', onNodoChange);
                    document.getElementById('swal-select-tecnologia-nodo')?.addEventListener('change', onTechNodoChange);
                });
            }
        },
        preConfirm: async () => {
            const notasInput = document.getElementById('swal-notas');
            notasValue = notasInput ? notasInput.value.trim() : '';
            if (acciones.includes(ACCION_SELECCIONAR_NODO)) {
                const selectNodo = document.getElementById('swal-select-nodo');
                if (!selectNodo?.value) {
                    Swal.showValidationMessage('Seleccioná un nodo.');
                    return false;
                }
                nodoIdValue = selectNodo.value;
                const tech = obtenerTecnologiaIdDesdeSwal(acciones);
                const pool = obtenerPoolIdDesdeSwal();
                if (config.urlOpcionesNodoAprobacion) {
                    try {
                        const url = config.urlOpcionesNodoAprobacion.replace('__id__', String(nodoIdValue));
                        const { data } = await axios.get(url);
                        if (data.sin_pools_activos) {
                            Swal.showValidationMessage('El nodo no tiene pools de IP activos.');
                            return false;
                        }
                        if (data.sin_tecnologia_configurada) {
                            Swal.showValidationMessage('El nodo no tiene tecnologías compatibles configuradas.');
                            return false;
                        }
                        if (data.requiere_seleccion_tecnologia && !tech) {
                            Swal.showValidationMessage('Seleccioná el tipo de tecnología (el nodo maneja GPON y Wireless).');
                            return false;
                        }
                        if (data.requiere_seleccion_pool && !pool) {
                            Swal.showValidationMessage('Seleccioná el pool de IP.');
                            return false;
                        }
                        if (tech) tecnologiaIdValue = tech;
                        else if (data.tecnologia_id_auto) tecnologiaIdValue = String(data.tecnologia_id_auto);
                        if (pool) poolIdValue = pool;
                        else if (data.pool_id_auto) poolIdValue = String(data.pool_id_auto);
                    } catch (_e) {
                        Swal.showValidationMessage('No se pudieron validar las opciones del nodo.');
                        return false;
                    }
                }
            } else if (acciones.includes(ACCION_SELECCIONAR_TIPO_TECNOLOGIA)) {
                const selectTecnologia = document.getElementById('swal-select-tecnologia');
                if (selectTecnologia?.value) tecnologiaIdValue = selectTecnologia.value;
            }
            if (acciones.includes(ACCION_SELECCIONAR_PLAN)) {
                const selectPlan = document.getElementById('swal-select-plan');
                if (selectPlan?.value) planIdValue = selectPlan.value;
            }
            return true;
        },
    });

    if (!result.isConfirmed) return;

    const payload = {
        estado_id: estadoId,
        notas: notasValue || null,
    };
    if (acciones.includes(ACCION_SELECCIONAR_NODO) && nodoIdValue != null) payload.nodo_id = parseInt(nodoIdValue, 10);
    if (tecnologiaIdValue != null && tecnologiaIdValue !== '') payload.tecnologia_id = parseInt(tecnologiaIdValue, 10);
    else if (acciones.includes(ACCION_SELECCIONAR_TIPO_TECNOLOGIA) && tecnologiaIdValue != null) payload.tecnologia_id = parseInt(tecnologiaIdValue, 10);
    if (poolIdValue != null && poolIdValue !== '') payload.pool_id = parseInt(poolIdValue, 10);
    if (acciones.includes(ACCION_SELECCIONAR_PLAN) && planIdValue != null) payload.plan_id = parseInt(planIdValue, 10);

    if (!config.aprobarEstadoUrl) {
        throw new Error('URL de aprobar estado no configurada');
    }

    const url = config.aprobarEstadoUrl.replace(':pedido', pedidoId);

    try {
        const response = await axios.post(url, payload, {
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
            },
        });

        await Swal.fire({
            ...getSwalThemeOptions(),
            icon: 'success',
            title: '¡Éxito!',
            text: response.data?.message || 'Estado aprobado correctamente.',
            confirmButtonColor: '#16a34a',
            timer: 1500,
            timerProgressBar: true,
        });

        if (reloadOnSuccess) {
            window.location.reload();
        } else if (response.data?.redirect) {
            window.location.href = response.data.redirect;
        } else {
            window.location.reload();
        }
    } catch (error) {
        if (error.response) {
            const status = error.response.status;
            const message = error.response.data?.message || 'Error al aprobar el estado';

            if (status === 400) {
                await Swal.fire({
                    ...getSwalThemeOptions(),
                    icon: 'warning',
                    title: 'Advertencia',
                    text: message,
                    confirmButtonColor: '#7c3aed',
                });
            } else {
                await Swal.fire({
                    ...getSwalThemeOptions(),
                    icon: 'error',
                    title: 'Error',
                    text: message,
                    confirmButtonColor: '#7c3aed',
                });
            }
        } else {
            await Swal.fire({
                ...getSwalThemeOptions(),
                icon: 'error',
                title: 'Error',
                text: 'Error al aprobar el estado. Por favor, intenta nuevamente.',
                confirmButtonColor: '#7c3aed',
            });
        }
        throw error;
    }
}
