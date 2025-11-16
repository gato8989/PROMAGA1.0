import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import react from '@vitejs/plugin-react';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
        }),
        react(),
    ],
    // AGREGAR ESTO PARA PRODUCCIÓN:
    build: {
        outDir: 'public/dist',
        assetsDir: 'assets',
    },
    // Configuración base para producción
    base: process.env.NODE_ENV === 'production' ? '/' : '/',
});