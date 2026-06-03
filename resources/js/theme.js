const STORAGE_KEY = 'theme';

export function isDarkTheme() {
    return document.documentElement.classList.contains('dark');
}

/** Aplica tema desde localStorage. Oscuro por defecto si no hay preferencia guardada. */
export function initTheme() {
    const stored = localStorage.getItem(STORAGE_KEY);
    const isDark = stored !== 'light';
    document.documentElement.classList.toggle('dark', isDark);
    if (stored === null) {
        localStorage.setItem(STORAGE_KEY, 'dark');
    }
    updateThemeColorMeta(isDark);
}

export function toggleTheme() {
    const isDark = document.documentElement.classList.toggle('dark');
    localStorage.setItem(STORAGE_KEY, isDark ? 'dark' : 'light');
    updateThemeColorMeta(isDark);
    window.dispatchEvent(new CustomEvent('theme-change', { detail: { isDark } }));
    return isDark;
}

function updateThemeColorMeta(isDark) {
    let meta = document.querySelector('meta[name="theme-color"]');
    if (!meta) {
        meta = document.createElement('meta');
        meta.name = 'theme-color';
        document.head.appendChild(meta);
    }
    meta.content = isDark ? '#111827' : '#f9fafb';
}

export function getChartThemeColors() {
    const dark = isDarkTheme();
    return {
        text: dark ? '#e5e7eb' : '#374151',
        grid: dark ? 'rgba(75, 85, 99, 0.35)' : 'rgba(209, 213, 219, 0.8)',
    };
}

/** Opciones comunes de ejes y leyenda para Chart.js según el tema activo. */
export function chartAxisTheme(yTickCallback) {
    const { text, grid } = getChartThemeColors();
    const scales = {
        x: {
            ticks: { color: text },
            grid: { color: grid },
        },
        y: {
            beginAtZero: true,
            ticks: { color: text },
            grid: { color: grid },
        },
    };
    if (typeof yTickCallback === 'function') {
        scales.y.ticks.callback = yTickCallback;
    }
    return scales;
}

export function chartLegendTheme(position = 'top') {
    return {
        position,
        labels: { color: getChartThemeColors().text },
    };
}

/** Aplica colores de tema a un gráfico Chart.js existente. */
export function applyChartTheme(chart, yTickCallback) {
    if (!chart?.options) return;
    const { text, grid } = getChartThemeColors();
    const legend = chart.options.plugins?.legend;
    if (legend?.labels) {
        legend.labels.color = text;
    }
    ['x', 'y'].forEach((axis) => {
        const scale = chart.options.scales?.[axis];
        if (!scale) return;
        scale.ticks = scale.ticks || {};
        scale.ticks.color = text;
        scale.grid = scale.grid || {};
        scale.grid.color = grid;
        if (axis === 'y' && typeof yTickCallback === 'function') {
            scale.ticks.callback = yTickCallback;
        }
    });
}

export function watchThemeCharts(charts, yTickCallbacks = []) {
    window.addEventListener('theme-change', () => {
        charts.forEach((chart, i) => {
            applyChartTheme(chart, yTickCallbacks[i]);
            chart.update();
        });
    });
}

function bindThemeToggle() {
    document.getElementById('theme-toggle')?.addEventListener('click', toggleTheme);
}

if (typeof window !== 'undefined') {
    window.InfinityTheme = {
        isDarkTheme,
        initTheme,
        toggleTheme,
        getChartThemeColors,
        chartAxisTheme,
        chartLegendTheme,
        applyChartTheme,
        watchThemeCharts,
    };
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', bindThemeToggle);
    } else {
        bindThemeToggle();
    }
}
