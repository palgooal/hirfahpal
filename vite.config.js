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
                'resources/css/vendor-auth.css',
                'resources/css/vendor-dashboard.css',
                'resources/js/vendor-dashboard.js',
                'resources/js/vendor-dashboard-home.js',
                'resources/js/vendor-my-store.js',
                'resources/css/storefront.css',
                'resources/js/storefront.js',
                'resources/js/storefront-browse.js',
                'resources/js/storefront-product.js',
                'resources/js/storefront-vendors.js',
                'resources/js/storefront-vendor.js',
                'resources/js/storefront-login.js',
                'resources/js/storefront-addresses.js',
                'resources/js/storefront-favorites.js',
                'resources/js/storefront-checkout.js',
            ],
            refresh: true,
        }),
        tailwindcss(),
    ],
});
