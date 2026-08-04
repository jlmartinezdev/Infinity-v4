const path = require('path');
const mix = require('laravel-mix');

/*
 |--------------------------------------------------------------------------
 | Mix Asset Management
 |--------------------------------------------------------------------------
 |
 | Laravel Mix compila los JS y CSS. Los scripts se cargan en las vistas
 | con asset(mix('js/nombre.js')) para usar la versión con hash en producción.
 |
 */

mix.webpackConfig({
    resolve: {
        alias: {
            '@': path.resolve('resources/js'),
        },
    },
    plugins: [
        new (require('webpack')).DefinePlugin({
            __VUE_OPTIONS_API__: true,
            __VUE_PROD_DEVTOOLS__: false,
            __VUE_PROD_HYDRATION_MISMATCH_DETAILS__: false,
        }),
    ],
});

// Vue 3 para todos los bundles que usan componentes .vue
mix.js('resources/js/theme.js', 'public/js');
mix.js('resources/js/app.js', 'public/js').vue();
mix.js('resources/js/login.js', 'public/js').vue();
mix.js('resources/js/register.js', 'public/js').vue();
mix.js('resources/js/pedido-form.js', 'public/js').vue();
mix.js('resources/js/pedidos-list.js', 'public/js').vue();
mix.js('resources/js/clientes-list.js', 'public/js').vue();
mix.js('resources/js/cliente-form-mapa.js', 'public/js').vue();
mix.js('resources/js/recibo-copy-image.js', 'public/js');
mix.js('resources/js/recibo-modo-local.js', 'public/js');
mix.js('resources/js/servicio-form-ips.js', 'public/js').vue();
mix.js('resources/js/usuario-management.js', 'public/js').vue();
mix.js('resources/js/mapas-pedidos.js', 'public/js').vue();
mix.js('resources/js/mapa-clientes-activos.js', 'public/js').vue();
mix.js('resources/js/mapa-tecnicos.js', 'public/js').vue();
mix.js('resources/js/mapa-nap.js', 'public/js').vue();
mix.js('resources/js/caja-nap-form-mapa.js', 'public/js').vue();
mix.js('resources/js/servicios-index.js', 'public/js').vue();
mix.js('resources/js/listas-cliente-servicio.js', 'public/js').vue();
mix.js('resources/js/cobros-servicios.js', 'public/js').vue();
mix.js('resources/js/facturas-internas-index.js', 'public/js').vue();
mix.js('resources/js/notas-credito-index.js', 'public/js').vue();
mix.js('resources/js/compras-create.js', 'public/js').vue();
mix.js('resources/js/pendientes-pago.js', 'public/js').vue();
mix.js('resources/js/nodo-migrar-pppoe.js', 'public/js').vue();
mix.js('resources/js/tareas-dashboard.js', 'public/js').vue();
mix.js('resources/js/whatsapp-mensajes.js', 'public/js').vue();

mix.postCss('resources/css/app.css', 'public/css', [
    require('tailwindcss'),
    require('autoprefixer'),
]);

// Versionado en todos los builds para que mix-manifest.json tenga ?id=xxx (cache busting)
mix.version();
