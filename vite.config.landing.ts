import { fileURLToPath } from 'node:url';
import tailwindcss from '@tailwindcss/vite';
import vue from '@vitejs/plugin-vue';
import { defineConfig } from 'vite';

// Standalone build of the public landing page (resources/js/pages/Welcome.vue),
// reusing the exact Vue components. Inertia's <Link>/<Head> and the wayfinder
// route helpers are swapped for plain-anchor shims so the output runs with no
// Laravel/Inertia runtime. Run with: npm run build:landing
const r = (p: string) => fileURLToPath(new URL(p, import.meta.url));

export default defineConfig({
    root: r('./resources/landing'),
    publicDir: r('./public'),
    base: './', // relative asset URLs → publishable under any path or host
    plugins: [tailwindcss(), vue()],
    resolve: {
        alias: [
            { find: '@/routes/tenants', replacement: r('./resources/landing/routes-shim.ts') },
            { find: '@/routes', replacement: r('./resources/landing/routes-shim.ts') },
            { find: '@inertiajs/vue3', replacement: r('./resources/landing/inertia-shim.ts') },
            { find: '@', replacement: r('./resources/js') },
        ],
    },
    build: {
        outDir: r('./public/landing'),
        emptyOutDir: true,
    },
});
