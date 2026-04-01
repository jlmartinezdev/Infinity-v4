{{-- Campana de notificaciones (solo usuarios autenticados) --}}
<div class="relative" id="notifications-wrapper">
    <button type="button" id="notifications-toggle" class="relative p-2 rounded-lg text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-purple-500/30 touch-manipulation" aria-label="Notificaciones">
        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
        </svg>
        <span id="notifications-badge" class="absolute -top-0.5 -right-0.5 flex h-4 min-w-4 items-center justify-center rounded-full bg-red-500 px-1 text-xs font-medium text-white hidden">0</span>
    </button>
    <div id="notifications-dropdown" class="hidden absolute right-0 mt-2 w-120 max-h-[85vh] overflow-hidden rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 shadow-lg z-50 flex flex-col" >
        <div class="p-3 border-b border-gray-200 dark:border-gray-700 flex flex-col sm:flex-row sm:justify-between sm:items-center gap-2 shrink-0">
            <span class="font-semibold text-gray-900 dark:text-gray-100">Notificaciones</span>
            <button type="button" id="notifications-mark-all" class="text-xs text-purple-600 dark:text-purple-400 hover:text-purple-800 dark:hover:text-purple-300 font-medium text-left sm:text-right shrink-0">Marcar todas leídas</button>
        </div>
        <div id="notifications-list" class="overflow-y-auto overflow-x-hidden w-full max-h-[50vh] sm:max-h-[55vh] overscroll-contain">
            <div id="notifications-loading" class="p-4 text-center text-gray-500 dark:text-gray-400 text-sm">Cargando…</div>
            <div id="notifications-items"></div>
            <div id="notifications-empty" class="hidden p-4 text-center text-gray-500 dark:text-gray-400 text-sm">No hay notificaciones.</div>
        </div>
        <div class="p-3 border-t border-gray-100 dark:border-gray-700 text-center space-y-2 text-sm sm:text-base shrink-0">
            <a href="{{ route('sistema.auditoria.index') }}" class="text-sm text-blue-600 dark:text-blue-400 hover:text-blue-800 dark:hover:text-blue-300">Ver todas las notificaciones</a>
            <div id="notifications-push-prompt" class="hidden">
                <button type="button" id="notifications-enable-push" class="text-xs text-purple-600 dark:text-purple-400 hover:underline">Activar notificaciones del navegador</button>
            </div>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11" crossorigin="anonymous"></script>
<script>
(function() {
    var POLL_INTERVAL = 30000; // 30 segundos
    var toggle = document.getElementById('notifications-toggle');
    var dropdown = document.getElementById('notifications-dropdown');
    var badge = document.getElementById('notifications-badge');
    var list = document.getElementById('notifications-items');
    var loading = document.getElementById('notifications-loading');
    var empty = document.getElementById('notifications-empty');
    var listUrl = '{{ route("notificaciones.index") }}';
    var leerUrl = '{{ url("/notificaciones") }}';
    var leerTodasUrl = '{{ route("notificaciones.leer-todas") }}';
    var csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
    var previousSinLeer = -1;
    var PEDIDOS_URL = '{{ route("pedidos.index") }}';

    function formatFechaRelativa(dateStr) {
        if (!dateStr) return '';
        var d = new Date(dateStr);
        var now = new Date();
        var diffMs = now - d;
        var diffMin = Math.floor(diffMs / 60000);
        var diffH = Math.floor(diffMs / 3600000);
        var diffD = Math.floor(diffMs / 86400000);
        if (diffMin < 1) return 'Ahora';
        if (diffMin < 60) return 'Hace ' + diffMin + ' min';
        if (diffH < 24) return 'Hace ' + diffH + ' h';
        if (diffD <= 7) return 'Hace ' + diffD + ' d';
        var meses = ['ene.','feb.','mar.','abr.','may.','jun.','jul.','ago.','sep.','oct.','nov.','dic.'];
        return ('0' + d.getDate()).slice(-2) + '-' + meses[d.getMonth()];
    }

    function showBrowserNotification(title, body, url) {
        if (!('Notification' in window)) return;
        if (Notification.permission !== 'granted') return;
        try {
            var n = new Notification(title || 'Infinity ISP', {
                body: body || 'Nueva notificación',
                icon: '/favicon.ico',
                tag: 'infinity-notif-' + Date.now(),
                requireInteraction: false
            });
            n.onclick = function() {
                n.close();
                window.focus();
                if (url) window.location.href = url;
                else if (toggle && dropdown) { dropdown.classList.remove('hidden'); }
            };
            setTimeout(function() { n.close(); }, 8000);
        } catch (e) {}
    }

    function requestNotificationPermission(callback) {
        if (!('Notification' in window)) return;
        if (Notification.permission === 'granted') { if (callback) callback(true); return; }
        if (Notification.permission === 'denied') { if (callback) callback(false); return; }
        Notification.requestPermission().then(function(p) {
            if (callback) callback(p === 'granted');
            updatePushPromptVisibility();
        });
    }

    function updatePushPromptVisibility() {
        var prompt = document.getElementById('notifications-push-prompt');
        if (!prompt) return;
        if (!('Notification' in window)) { prompt.classList.add('hidden'); return; }
        if (Notification.permission === 'granted') { prompt.classList.add('hidden'); return; }
        if (Notification.permission === 'denied') { prompt.classList.add('hidden'); return; }
        prompt.classList.remove('hidden');
    }

    function fetchNotifications() {
        fetch(listUrl, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                var n = data.notificaciones || [];
                var sinLeer = data.sin_leer ?? 0;

                if (document.hidden && sinLeer > 0 && previousSinLeer >= 0 && sinLeer > previousSinLeer && 'Notification' in window && Notification.permission === 'granted') {
                    var primera = n.find(function(x) { return !x.read_at; }) || n[0];
                    if (primera) {
                        var d = primera.data || {};
                        var msg = d.mensaje || 'Nueva actividad';
                        var tabla = (d.tabla || '').toLowerCase();
                        var url = tabla === 'pedidos' ? PEDIDOS_URL : null;
                        showBrowserNotification('Nueva notificación', msg, url);
                    }
                }
                previousSinLeer = sinLeer;

                if (badge) {
                    if (sinLeer > 0) {
                        badge.textContent = sinLeer > 99 ? '99+' : sinLeer;
                        badge.classList.remove('hidden');
                    } else {
                        badge.classList.add('hidden');
                    }
                }
                if (loading) loading.classList.add('hidden');
                if (n.length === 0) {
                    if (empty) empty.classList.remove('hidden');
                    if (list) list.innerHTML = '';
                } else {
                    if (empty) empty.classList.add('hidden');
                    if (list) {
                        list.innerHTML = n.map(function(notif) {
                            var d = notif.data || {};
                            var msg = d.mensaje || 'Nueva actividad';
                            var fecha = formatFechaRelativa(notif.created_at);
                            var isRead = !!notif.read_at;
                            var circleBg = isRead ? 'bg-gray-200 dark:bg-gray-600' : 'bg-green-100 dark:bg-green-900/40';
                            var iconColor = isRead ? 'text-gray-500 dark:text-gray-400' : 'text-green-600 dark:text-green-400';
                            var bellSvg = '<svg class="w-4 h-4 ' + iconColor + '" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>';
                            var detailJson = JSON.stringify({ mensaje: msg, tabla: d.tabla, accion: d.accion_label || d.accion, usuario: d.usuario_nombre, registro_id: d.registro_id, registro_key: d.registro_key, fecha: notif.created_at });
                            var detailEscaped = detailJson.replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
                            var msgEscaped = msg.replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
                            return '<div class="notif-item flex gap-3 border-b border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-800 px-3 sm:px-4 py-3 text-sm hover:bg-gray-50 dark:hover:bg-gray-700/50 cursor-pointer min-w-0 w-full touch-manipulation" data-id="' + notif.id + '" data-mensaje="' + msgEscaped + '" data-detail="' + detailEscaped + '">' +
                                '<div class="flex-shrink-0 w-8 h-8 rounded-full ' + circleBg + ' flex items-center justify-center">' + bellSvg + '</div>' +
                                '<div class="min-w-0 flex-1">' +
                                '<p class="text-gray-900 dark:text-gray-100">' + (msg.replace(/</g, '&lt;').replace(/>/g, '&gt;')) + '</p>' +
                                '<p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">' + fecha + '</p>' +
                                '</div></div>';
                        }).join('');
                    }
                }
            })
            .catch(function() {
                if (loading) loading.classList.add('hidden');
                if (empty) { empty.textContent = 'Error al cargar.'; empty.classList.remove('hidden'); }
            });
    }

    function markAsRead(id) {
        fetch(leerUrl + '/' + id + '/leer', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
            body: JSON.stringify({})
        }).then(function() { fetchNotifications(); });
    }

    function markAllAsRead() {
        fetch(leerTodasUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
            body: JSON.stringify({})
        }).then(function() { fetchNotifications(); });
    }

    toggle.addEventListener('click', function(e) {
        e.stopPropagation();
        dropdown.classList.toggle('hidden');
        if (!dropdown.classList.contains('hidden')) {
            fetchNotifications();
            requestNotificationPermission();
        }
    });

    var enablePushBtn = document.getElementById('notifications-enable-push');
    if (enablePushBtn) enablePushBtn.addEventListener('click', function() {
        requestNotificationPermission();
    });
    document.addEventListener('click', function() {
        dropdown.classList.add('hidden');
    });
    if (dropdown) dropdown.addEventListener('click', function(e) { e.stopPropagation(); });

    function showDetalleNotificacion(item) {
        var id = item.getAttribute('data-id');
        var mensaje = item.getAttribute('data-mensaje') || '';
        var detailRaw = item.getAttribute('data-detail');
        var detail = {};
        try {
            if (detailRaw) {
                var raw = detailRaw.replace(/&quot;/g, '"').replace(/&amp;/g, '&').replace(/&lt;/g, '<').replace(/&gt;/g, '>');
                detail = JSON.parse(raw);
            }
        } catch (err) {}
        var html = '<p class="text-left text-gray-700 dark:text-gray-300 mb-3">' + (mensaje.replace(/&lt;/g, '<').replace(/&gt;/g, '>').replace(/&quot;/g, '"')) + '</p>';
        if (detail.tabla || detail.accion || detail.usuario || detail.registro_id != null || detail.registro_key) {
            html += '<div class="text-left text-sm text-gray-600 dark:text-gray-400 border-t border-gray-200 dark:border-gray-600 pt-3 mt-3">';
            if (detail.usuario) html += '<p><strong>Usuario:</strong> ' + escapeHtml(detail.usuario) + '</p>';
            if (detail.tabla) html += '<p><strong>Tabla:</strong> ' + escapeHtml(detail.tabla) + '</p>';
            if (detail.accion) html += '<p><strong>Acción:</strong> ' + escapeHtml(detail.accion) + '</p>';
            if (detail.registro_id != null && detail.registro_id !== '') html += '<p><strong>Registro ID:</strong> ' + escapeHtml(String(detail.registro_id)) + '</p>';
            if (detail.registro_key) html += '<p><strong>Registro:</strong> ' + escapeHtml(detail.registro_key) + '</p>';
            if (detail.fecha) html += '<p><strong>Fecha:</strong> ' + escapeHtml(new Date(detail.fecha).toLocaleString('es')) + '</p>';
            html += '</div>';
        }
        if (typeof Swal !== 'undefined') {
            Swal.fire({
                title: 'Detalle de la notificación',
                html: html,
                icon: 'info',
                confirmButtonText: 'Cerrar',
                width: window.innerWidth < 640 ? '95%' : 480
            });
        } else {
            alert(mensaje.replace(/&lt;/g, '<').replace(/&gt;/g, '>').replace(/&quot;/g, '"'));
        }
        markAsRead(id);
    }
    function escapeHtml(s) {
        if (s == null) return '';
        var div = document.createElement('div');
        div.textContent = s;
        return div.innerHTML;
    }

    list.addEventListener('click', function(e) {
        var item = e.target.closest('.notif-item');
        if (item) showDetalleNotificacion(item);
    });

    var markAllBtn = document.getElementById('notifications-mark-all');
    if (markAllBtn) markAllBtn.addEventListener('click', function(e) {
        e.preventDefault();
        e.stopPropagation();
        markAllAsRead();
    });

    fetchNotifications();
    setInterval(fetchNotifications, POLL_INTERVAL);
    updatePushPromptVisibility();

    // Permitir refrescar desde fuera (ej. después de guardar/eliminar)
    window.refreshNotifications = fetchNotifications;
    document.addEventListener('notificaciones-refresh', fetchNotifications);
})();
</script>
