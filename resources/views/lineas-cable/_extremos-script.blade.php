@push('scripts')
<script>
(function () {
    function sync(extremo) {
        var tipoSel = document.querySelector('[data-extremo-tipo="' + extremo + '"]');
        if (!tipoSel) return;
        var tipo = tipoSel.value;
        document.querySelectorAll('[data-extremo-panel="' + extremo + '"]').forEach(function (panel) {
            var activo = panel.getAttribute('data-tipo') === tipo;
            panel.hidden = !activo;
            panel.querySelectorAll('select').forEach(function (sel) {
                sel.disabled = !activo;
            });
        });
    }

    function tubos() {
        var capSel = document.getElementById('cantidad_hilos');
        var el = document.getElementById('capacidad-tubos');
        if (!capSel || !el) return;
        var cap = parseInt(capSel.value, 10) || 0;
        var n = Math.ceil(cap / 12);
        el.textContent = cap + ' pelos distribuidos en ' + n + (n === 1 ? ' tubo buffer' : ' tubos buffer');
    }

    ['origen', 'destino'].forEach(function (extremo) {
        var sel = document.querySelector('[data-extremo-tipo="' + extremo + '"]');
        if (!sel) return;
        sel.addEventListener('change', function () { sync(extremo); });
        sync(extremo);
    });

    var capSel = document.getElementById('cantidad_hilos');
    if (capSel) {
        capSel.addEventListener('change', tubos);
        tubos();
    }
})();
</script>
@endpush
