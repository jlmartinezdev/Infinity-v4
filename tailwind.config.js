/** @type {import('tailwindcss').Config} */
module.exports = {
    darkMode: 'class',
    content: [
        './resources/**/*.blade.php',
        './resources/**/*.js',
        './resources/**/*.vue',
    ],
    safelist: [
        // Colores usados en Blade/Vue (incl. emerald/cyan/indigo para menú permisos, tickets, servicios)
        {
            pattern:
                /^bg-(gray|slate|zinc|neutral|stone|red|orange|amber|yellow|lime|green|emerald|teal|cyan|sky|blue|indigo|violet|purple|fuchsia|pink|rose|white|black)-(50|100|200|300|400|500|600|700|800|900|950)$/,
        },
        {
            pattern:
                /^text-(gray|slate|zinc|neutral|stone|red|orange|amber|yellow|lime|green|emerald|teal|cyan|sky|blue|indigo|violet|purple|fuchsia|pink|rose|white|black)-(50|100|200|300|400|500|600|700|800|900|950)$/,
        },
        {
            pattern:
                /^border-(gray|slate|zinc|neutral|stone|red|orange|amber|yellow|lime|green|emerald|teal|cyan|sky|blue|indigo|violet|purple|fuchsia|pink|rose|white|black)-(50|100|200|300|400|500|600|700|800|900|950)$/,
        },
        {
            pattern:
                /^ring-(gray|slate|red|orange|amber|green|emerald|teal|cyan|blue|indigo|violet|purple|pink|rose)-(50|100|200|300|400|500|600|700|800|900|950)$/,
        },
        // Opacidad (ej. ring-purple-500/20)
        { pattern: /^(bg|text|border|ring)-[a-z]+-\d+\/\d+$/ },
        // Layout y espaciado usados en vistas
        { pattern: /^(flex|grid|block|inline-flex|hidden)(-.*)?$/ },
        { pattern: /^(flex-1|min-w-0|flex-shrink-0|w-full|w-\d+|max-w-\w+)$/ },
        { pattern: /^(rounded|rounded-lg|rounded-xl|rounded-full|rounded-t-xl|rounded-b-xl)(-.*)?$/ },
        { pattern: /^(border|border-l|border-r|border-t|border-b)(-.*)?$/ },
        { pattern: /^shadow(-.*)?$/ },
        { pattern: /^gap-[0-9]+$/ },
        { pattern: /^grid-cols-[0-9]+$/ },
    ],
    theme: {
        extend: {},
    },
    plugins: [],
};
