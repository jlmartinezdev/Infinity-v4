/** @type {import('tailwindcss').Config} */
module.exports = {
    darkMode: 'class',
    content: [
        './resources/**/*.blade.php',
        './resources/**/*.js',
        './resources/**/*.vue',
    ],
    safelist: [
        // Colores: gray, purple, red, green, blue, amber (bg, text, border, ring) para que la compilación incluya las clases usadas en Blade/Vue
        { pattern: /^bg-(gray|purple|red|green|blue|amber|white)-(50|100|200|300|400|500|600|700|800|900)$/ },
        { pattern: /^text-(gray|purple|red|green|blue|amber|white|black)-(50|100|200|300|400|500|600|700|800|900)$/ },
        { pattern: /^border-(gray|purple|red|green|blue)-(50|100|200|300|400|500|600|700|800)$/ },
        { pattern: /^ring-(gray|purple|red|green|blue)-(50|100|200|300|400|500|600)$/ },
        { pattern: /^hover:bg-(gray|purple|red|green|blue|amber)-(50|100|200|300|400|500|600|700)$/ },
        { pattern: /^focus:ring-(purple|gray)-(\d+\/\d+|\d+)$/ },
        { pattern: /^focus:border-purple-\d+$/ },
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
        { pattern: /^md:(flex-row|flex-col|border-r|border-l|border-b-0|w-80)$/ },
        { pattern: /^dark:(bg|text|border|ring|hover|focus)-.+/ },
    ],
    theme: {
        extend: {},
    },
    plugins: [],
};
