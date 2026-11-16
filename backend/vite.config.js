import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';
import vue from '@vitejs/plugin-vue';

export default defineConfig({
    plugins: [
        laravel({
            // The Blade portal and the Inertia pages (sign-in, onboarding) are separate bundles,
            // so neither loads the other's framework.
            input: [
                'resources/css/app.css',
                'resources/js/app.js',
                'resources/css/inertia.css',
                'resources/js/inertia.js',
            ],
            refresh: true,
        }),
        vue({
            template: {
                transformAssetUrls: {
                    base: null,
                    includeAbsolute: false,
                },
            },
        }),
        tailwindcss(),
    ],
});
