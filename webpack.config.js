const path = require('path');
const { VueLoaderPlugin } = require('vue-loader');
const MiniCssExtractPlugin = require('mini-css-extract-plugin');

module.exports = {
    entry: {
        app: './resources/js/app.js',
        login: './resources/js/login.js',
        register: './resources/js/register.js',
        'pedido-form': './resources/js/pedido-form.js',
        'usuario-management': './resources/js/usuario-management.js',
        'pedidos-list': './resources/js/pedidos-list.js'
    },
    output: {
        path: path.resolve(__dirname, 'public'),
        filename: 'js/[name].js',
        clean: {
            keep: /index\.php$/,
        },
    },
    module: {
        rules: [
            {
                test: /\.vue$/,
                loader: 'vue-loader'
            },
            {
                test: /\.js$/,
                loader: 'babel-loader',
                exclude: /node_modules/
            },
            {
                test: /\.css$/,
                use: [
                    MiniCssExtractPlugin.loader,
                    'css-loader',
                    'postcss-loader'
                ]
            }
        ]
    },
    plugins: [
        new VueLoaderPlugin(),
        new MiniCssExtractPlugin({
            filename: 'css/[name].css'
        })
    ],
    resolve: {
        alias: {
            'vue': 'vue/dist/vue.esm-bundler.js',
            '@': path.resolve(__dirname, 'resources/js')
        },
        extensions: ['.js', '.vue', '.json']
    }
};
