import { defineConfig } from 'vite';
import process from 'node:process';
import laravel from 'laravel-vite-plugin';
import { bunny } from 'laravel-vite-plugin/fonts';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    ...(process.env.VITE_DOCKER === 'true' && { envDir: process.env.VITE_ENV_DIR || '/vite-env' }),
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js', 'resources/css/panel.css', 'resources/js/panel.js',
                'resources/js/panel-map.js', 'resources/js/panel-picker.js',
            ],
            refresh: true,
            fonts: [
                bunny('Instrument Sans', {
                    weights: [400, 500, 600],
                }),
            ],
        }),
        tailwindcss(),
    ],
    server: {
        ...(process.env.VITE_DOCKER === 'true' && {
            host: '0.0.0.0',
            origin: `http://${process.env.VITE_HMR_HOST || 'localhost'}:${process.env.VITE_HMR_CLIENT_PORT || '5173'}`,
            cors: {
                origin: [/^http:\/\/(localhost|127\.0\.0\.1):8080$/],
            },
            hmr: {
                host: process.env.VITE_HMR_HOST || 'localhost',
                clientPort: Number(process.env.VITE_HMR_CLIENT_PORT || 5173),
            },
        }),
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
