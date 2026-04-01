# Infinity V4

Proyecto Laravel con Vue 3 y Vite

## Tecnologías

- **Laravel 12** - Framework PHP moderno
- **Vue 3** - Framework JavaScript progresivo con Composition API
- **Vite** - Build tool ultra rápido
- **Tailwind CSS** - Framework CSS utility-first

## Instalación

Las dependencias ya están instaladas. Si necesitas reinstalarlas:

```bash
# Dependencias de PHP
composer install

# Dependencias de Node.js
npm install
```

## Configuración

1. Copia el archivo `.env.example` a `.env` (ya está hecho)
2. Genera la clave de aplicación (ya está hecho):
```bash
php artisan key:generate
```

3. Configura tu base de datos en el archivo `.env`

## Desarrollo

Para iniciar el servidor de desarrollo:

```bash
# Terminal 1 - Servidor Laravel
php artisan serve

# Terminal 2 - Compilación de assets con Vite
npm run dev
```

Luego abre tu navegador en: `http://localhost:8000`

## Estructura del Proyecto

```
resources/
├── js/
│   ├── app.js              # Punto de entrada de Vue
│   └── components/
│       └── App.vue         # Componente principal de Vue
├── css/
│   └── app.css            # Estilos con Tailwind
└── views/
    └── app.blade.php      # Vista principal que monta Vue
```

## Scripts Disponibles

```bash
# Desarrollo
npm run dev

# Producción
npm run build

# Linting con Pint
./vendor/bin/pint

# Tests
php artisan test
```

## Características

- ✅ Hot Module Replacement (HMR) con Vite
- ✅ Composition API de Vue 3
- ✅ Tailwind CSS configurado
- ✅ Estructura lista para desarrollo
- ✅ Componente de ejemplo funcional

## Próximos Pasos

1. Configura tu base de datos en `.env`
2. Ejecuta las migraciones: `php artisan migrate`
3. Comienza a desarrollar tus componentes Vue en `resources/js/components/`
4. Crea tus rutas API en `routes/api.php`
5. Agrega nuevas rutas web en `routes/web.php`

## Licencia

MIT
