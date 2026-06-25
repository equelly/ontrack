import { defineConfig } from 'vite'
import laravel from 'laravel-vite-plugin'

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/js/app.js',
            ],
            refresh: true,
        }),
    ],
        server: {
        host: 'localhost', // ← ВАЖНО
        port: 5173,
    },
    // скрыть предупреждения
    css: {
        preprocessorOptions: {
            scss: {
                api: 'modern', 
                quietDeps: true,
                silenceDeprecations: ['import', 'global-builtin', 'color-functions', 'mixed-decls'],
            },
        },
    },
})
