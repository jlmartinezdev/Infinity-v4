<script>
    (function () {
        var STORAGE_KEY = 'theme';
        var stored = localStorage.getItem(STORAGE_KEY);
        var isDark = stored !== 'light';
        document.documentElement.classList.toggle('dark', isDark);
        if (stored === null) {
            localStorage.setItem(STORAGE_KEY, 'dark');
        }
    })();
</script>
