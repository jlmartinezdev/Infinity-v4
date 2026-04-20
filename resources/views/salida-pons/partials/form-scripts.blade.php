<script>
(function () {
    function maxPuertosFromOption(opt) {
        if (!opt || !opt.value) {
            return {{ \App\Models\SalidaPon::PUERTOS_MAX_SIN_DECLARAR_EN_OLT }};
        }
        var n = parseInt(opt.getAttribute('data-ports') || '0', 10);
        return n > 0 ? n : {{ \App\Models\SalidaPon::PUERTOS_MAX_SIN_DECLARAR_EN_OLT }};
    }
    function filtrarOltsPorNodo() {
        var nodoSelect = document.getElementById('nodo_id');
        var oltSelect = document.getElementById('olt_id');
        if (!nodoSelect || !oltSelect) return;
        var nodoId = String(nodoSelect.value || '');
        var selected = oltSelect.value;
        var first = '';
        Array.prototype.forEach.call(oltSelect.options, function (opt) {
            if (opt.value === '') {
                opt.hidden = false;
                return;
            }
            var match = !nodoId || String(opt.getAttribute('data-nodo') || '') === nodoId;
            opt.hidden = !match;
            if (match && !first) first = opt.value;
        });
        var sel = oltSelect.selectedOptions[0];
        if (sel && sel.hidden) {
            oltSelect.value = first || '';
        }
        if (!oltSelect.value && selected && nodoId) {
            var prev = Array.prototype.find.call(oltSelect.options, function (o) { return o.value === selected && !o.hidden; });
            if (prev) oltSelect.value = selected;
        }
        actualizarPuertosOlt();
    }
    function actualizarPuertosOlt() {
        var oltSelect = document.getElementById('olt_id');
        var puertoSelect = document.getElementById('puerto_olt');
        if (!puertoSelect) return;
        var max = maxPuertosFromOption(oltSelect ? oltSelect.selectedOptions[0] : null);
        var current = parseInt(puertoSelect.value, 10);
        if (isNaN(current) || current < 1) current = 1;
        if (current > max) current = max;
        var html = '';
        for (var i = 1; i <= max; i++) {
            html += '<option value="' + i + '"' + (i === current ? ' selected' : '') + '>Puerto ' + i + '</option>';
        }
        puertoSelect.innerHTML = html;
    }
    document.addEventListener('DOMContentLoaded', function () {
        var nodo = document.getElementById('nodo_id');
        var olt = document.getElementById('olt_id');
        if (nodo) nodo.addEventListener('change', filtrarOltsPorNodo);
        if (olt) olt.addEventListener('change', actualizarPuertosOlt);
        filtrarOltsPorNodo();
    });
})();
</script>
