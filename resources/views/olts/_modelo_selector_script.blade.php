<script>
(function() {
    var input = document.getElementById('modelo');
    var marcaInput = document.getElementById('marca');
    if (!input) return;

    document.querySelectorAll('.olt-modelo-btn').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var slug = this.getAttribute('data-slug');
            var marca = this.getAttribute('data-marca') || '';
            input.value = slug;
            document.querySelectorAll('.olt-modelo-btn').forEach(function(b) {
                b.classList.remove('border-purple-600', 'bg-purple-50', 'dark:bg-purple-900/20', 'ring-2', 'ring-purple-500/20');
                b.classList.add('border-gray-200', 'dark:border-gray-600', 'bg-white', 'dark:bg-gray-800');
            });
            this.classList.remove('border-gray-200', 'dark:border-gray-600', 'bg-white', 'dark:bg-gray-800');
            this.classList.add('border-purple-600', 'bg-purple-50', 'dark:bg-purple-900/20', 'ring-2', 'ring-purple-500/20');
            if (marcaInput && marca && marca !== 'Otro') {
                marcaInput.value = marca;
            }
        });
    });
})();
</script>
