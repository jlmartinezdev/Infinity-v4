<script>
(function () {
    function filtrarSalidasPonPorNodo() {
        var nodoSelect = document.getElementById('nodo_id');
        var salidaSelect = document.getElementById('salida_pon_id');
        if (!nodoSelect || !salidaSelect) return;

        var nodoId = String(nodoSelect.value || '');
        var selected = String(salidaSelect.value || '');

        Array.prototype.forEach.call(salidaSelect.options, function (opt) {
            if (opt.value === '') {
                opt.hidden = false;
                return;
            }
            var optNodo = String(opt.getAttribute('data-nodo') || '');
            var match = nodoId !== '' && optNodo === nodoId;
            opt.hidden = !match;
        });

        var sel = salidaSelect.selectedOptions[0];
        if (sel && sel.hidden) {
            salidaSelect.value = '';
        }
        if (!salidaSelect.value && nodoId && selected) {
            var prev = Array.prototype.find.call(salidaSelect.options, function (o) {
                return o.value === selected && !o.hidden;
            });
            if (prev) salidaSelect.value = selected;
        }
    }

    document.addEventListener('DOMContentLoaded', function () {
        var nodo = document.getElementById('nodo_id');
        if (nodo) nodo.addEventListener('change', filtrarSalidasPonPorNodo);
        filtrarSalidasPonPorNodo();
    });
})();
</script>
