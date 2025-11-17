const mix = require('laravel-mix');

// Compilar React
mix.js('resources/js/app.jsx', 'public/js')
   .react();

// Compilar CSS como archivo normal (no SASS)
mix.postCss('resources/css/app.css', 'public/css', [
    require('autoprefixer')
]);

// Opcional: agregar versionado
if (mix.inProduction()) {
    mix.version();
}