<script>
(function () {
    var maxSinOlt = {{ \App\Models\SalidaPon::PUERTOS_MAX_SIN_DECLARAR_EN_OLT }};

    function maxPuertosFromOption(opt) {
        if (!opt || !opt.value) {
            return maxSinOlt;
        }
        var n = parseInt(opt.getAttribute('data-ports') || '0', 10);
        return n > 0 ? n : maxSinOlt;
    }

    function parsePuertosJson(opt) {
        if (!opt) return [];
        var raw = opt.getAttribute('data-puertos');
        if (!raw) return [];
        try {
            var arr = JSON.parse(raw);
            return Array.isArray(arr) ? arr : [];
        } catch (e) {
            return [];
        }
    }

    function setSelectEnabled(sel, enabled) {
        if (!sel) return;
        sel.disabled = !enabled;
        if (!enabled) {
            sel.removeAttribute('required');
        }
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
        actualizarSelectorPuertoPon();
    }

    function actualizarSelectorPuertoPon() {
        var oltSelect = document.getElementById('olt_id');
        var wrapReg = document.getElementById('wrap-olt-puerto-reg');
        var wrapNum = document.getElementById('wrap-puerto-numerico');
        var selReg = document.getElementById('olt_puerto_id');
        var selNum = document.getElementById('puerto_olt');

        if (!wrapReg || !wrapNum || !selReg || !selNum) return;

        var opt = oltSelect && oltSelect.value ? oltSelect.selectedOptions[0] : null;
        var puertos = parsePuertosJson(opt);

        if (puertos.length > 0) {
            wrapReg.classList.remove('hidden');
            wrapNum.classList.add('hidden');
            setSelectEnabled(selReg, true);
            setSelectEnabled(selNum, false);
            selReg.setAttribute('required', 'required');

            var preferred = String(selReg.value || selReg.getAttribute('data-current') || '');
            var html = '<option value="">— Elegí un puerto PON —</option>';
            puertos.forEach(function (p) {
                var id = String(p.id);
                var label = 'Puerto ' + p.n + ' (' + (p.t || '—') + ')';
                html += '<option value="' + id + '"' + (preferred === id ? ' selected' : '') + '>' + label + '</option>';
            });
            selReg.innerHTML = html;
            if (preferred) {
                var matchOpt = Array.prototype.find.call(selReg.options, function (o) { return o.value === preferred; });
                if (matchOpt) selReg.value = preferred;
            }
            selReg.removeAttribute('data-current');
        } else if (opt && opt.value) {
            wrapReg.classList.add('hidden');
            wrapNum.classList.remove('hidden');
            setSelectEnabled(selReg, false);
            setSelectEnabled(selNum, true);
            selReg.removeAttribute('required');
            selReg.innerHTML = '<option value=""></option>';

            var max = maxPuertosFromOption(opt);
            var cur = parseInt(selNum.value, 10);
            if (isNaN(cur) || cur < 1) cur = 1;
            if (cur > max) cur = max;
            var h = '';
            for (var i = 1; i <= max; i++) {
                h += '<option value="' + i + '"' + (i === cur ? ' selected' : '') + '>Puerto ' + i + '</option>';
            }
            selNum.innerHTML = h;
        } else {
            wrapReg.classList.add('hidden');
            wrapNum.classList.remove('hidden');
            setSelectEnabled(selReg, false);
            setSelectEnabled(selNum, true);
            selReg.removeAttribute('required');
            selReg.innerHTML = '<option value=""></option>';

            var max0 = maxSinOlt;
            var cur0 = parseInt(selNum.value, 10);
            if (isNaN(cur0) || cur0 < 1) cur0 = 1;
            if (cur0 > max0) cur0 = max0;
            var h0 = '';
            for (var j = 1; j <= max0; j++) {
                h0 += '<option value="' + j + '"' + (j === cur0 ? ' selected' : '') + '>Puerto ' + j + '</option>';
            }
            selNum.innerHTML = h0;
        }
    }

    document.addEventListener('DOMContentLoaded', function () {
        var nodo = document.getElementById('nodo_id');
        var olt = document.getElementById('olt_id');
        if (nodo) nodo.addEventListener('change', filtrarOltsPorNodo);
        if (olt) olt.addEventListener('change', actualizarSelectorPuertoPon);
        filtrarOltsPorNodo();
    });
})();
</script>
