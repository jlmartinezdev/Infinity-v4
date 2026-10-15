@once
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11" crossorigin="anonymous"></script>
@endonce
<script>
(function () {
    if (window.__tvRenovarSwalBound) return;
    window.__tvRenovarSwalBound = true;

    function themeSwal() {
        if (!document.documentElement.classList.contains('dark')) return {};
        return { background: '#1f2937', color: '#f3f4f6' };
    }

    function enviarRenovar(form, dia) {
        var inputDia = form.querySelector('input[name="dia_aviso_vencimiento"]');
        if (inputDia) inputDia.value = String(dia);
        form.dataset.tvRenovarOk = '1';
        if (typeof form.requestSubmit === 'function') form.requestSubmit();
        else form.submit();
    }

    document.addEventListener('submit', function (e) {
        var form = e.target.closest('form.js-tv-renovar');
        if (!form || form.dataset.tvRenovarOk === '1') return;
        e.preventDefault();

        var dia = form.getAttribute('data-dia') || '1';
        var venc = form.getAttribute('data-vencimiento') || '—';
        var cuenta = form.getAttribute('data-cuenta') || 'esta cuenta';

        if (typeof Swal === 'undefined') {
            var nuevo = window.prompt('Día de vencimiento (1-31) para ' + cuenta + ' (vence ' + venc + '):', dia);
            if (nuevo === null) return;
            var nPrompt = parseInt(nuevo, 10);
            if (!nPrompt || nPrompt < 1 || nPrompt > 31) {
                alert('Ingresá un día entre 1 y 31.');
                return;
            }
            enviarRenovar(form, nPrompt);
            return;
        }

        Swal.fire(Object.assign({
            title: 'Renovar +1 mes',
            html:
                '<div class="text-left text-sm space-y-3">' +
                    '<p>Se adelanta el vencimiento de <strong id="swal-tv-cuenta"></strong>.</p>' +
                    '<p class="text-gray-500 dark:text-gray-400">Vence ahora: <strong class="text-gray-800 dark:text-gray-200">' +
                        venc.replace(/</g, '') +
                    '</strong></p>' +
                    '<label for="swal-tv-dia" class="block text-xs font-semibold uppercase tracking-wide text-gray-500">Día de vencimiento</label>' +
                    '<input id="swal-tv-dia" type="number" min="1" max="31" step="1" value="' + dia + '" class="swal2-input" style="margin:0;width:100%;" inputmode="numeric">' +
                    '<p class="text-xs text-gray-500 dark:text-gray-400">Del 1 al 31. El próximo ciclo usa este día, un mes adelante.</p>' +
                '</div>',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Renovar',
            cancelButtonText: 'Cancelar',
            confirmButtonColor: '#9333ea',
            cancelButtonColor: '#6b7280',
            focusConfirm: false,
            didOpen: function () {
                var el = document.getElementById('swal-tv-dia');
                var nombre = document.getElementById('swal-tv-cuenta');
                if (nombre) nombre.textContent = cuenta;
                if (el) {
                    el.focus();
                    el.select();
                }
            },
            preConfirm: function () {
                var el = document.getElementById('swal-tv-dia');
                var n = parseInt(el && el.value, 10);
                if (!n || n < 1 || n > 31) {
                    Swal.showValidationMessage('Ingresá un día entre 1 y 31.');
                    return false;
                }
                return n;
            }
        }, themeSwal())).then(function (result) {
            if (!result.isConfirmed) return;
            enviarRenovar(form, result.value);
        });
    });
})();
</script>
