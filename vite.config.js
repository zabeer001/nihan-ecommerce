import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import react from '@vitejs/plugin-react';

export default defineConfig({
    server: {
        watch: {
            usePolling: true,
            interval: 1000,
            ignored: [
                '**/.git/**',
                '**/node_modules/**',
                '**/vendor/**',
                '**/public/build/**',
                '**/storage/**',
                '**/bootstrap/cache/**',
                '**/documentations/**',
                '**/excel/**',
                '**/laravel-docker/**',
                '**/testing/**',
                '**/wp-content/**',
                '**/*.md',
            ],
        },
    },
    plugins: [
        laravel({
            input: 'resources/js/app.jsx',
            refresh: [
                'resources/views/**',
                'routes/**',
            ],
        }),
        react(),
    ],
});
