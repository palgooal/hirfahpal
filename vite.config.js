import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/js/app.js',
                'resources/css/admin-auth.css',
                'resources/js/admin-auth.js',
                'resources/css/storefront.css',
                'resources/js/storefront.js',
                'resources/js/storefront-browse.js',
                'resources/js/storefront-product.js',
            ],
            refresh: true,
        }),
        tailwindcss(),
    ],
});
