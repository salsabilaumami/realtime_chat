import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
        }),
    ],
    server: {
        host: '0.0.0.0', // dengarkan semua interface, bukan cuma localhost, biar device lain di LAN bisa load asset CSS/JS saat mode dev
        port: 5173,
    },
});
