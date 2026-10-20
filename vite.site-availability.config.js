// SITE-AVAILABILITY (docs/deployment/HIRFAH-SITE-AVAILABILITY-DESIGN.md §8.3): regenerates the tracked, minified CSS
// inlined into the Coming Soon and Maintenance pages. Not part of `npm run build`; run only when the classes used by
// resources/site-availability/{coming-soon,maintenance}.html or resources/css/site-availability.css change:
//
//     npx vite build --config vite.site-availability.config.js
//
// Output: resources/site-availability/site-availability.min.css (no manifest, no hash, nothing in public/).
import { defineConfig } from 'vite';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [tailwindcss()],
    publicDir: false,
    logLevel: 'warn',
    build: {
        outDir: 'resources/site-availability',
        emptyOutDir: false,
        copyPublicDir: false,
        manifest: false,
        cssMinify: true,
        rollupOptions: {
            input: 'resources/css/site-availability.css',
            output: {
                assetFileNames: 'site-availability.min.css',
            },
        },
    },
});
