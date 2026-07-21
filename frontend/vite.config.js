import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        react(),
        tailwindcss(),
    ],
    resolve: {
        alias: {
            '@': '/src',
        },
    },
    // Le site est servi à la racine du domaine : http://ns3485498.ip-193-70-32.eu/
    base: '/',
    server: {
        port: 5173,
        // ⚠️ Uniquement pour le DÉVELOPPEMENT local (npm run dev).
        // Aucun effet sur le build de production (npm run build).
        // En production, /api et /sanctum sont servis par le même domaine que le React.
        proxy: {
            '/api': {
                target: 'http://localhost',
                changeOrigin: true,
            },
            '/sanctum': {
                target: 'http://localhost',
                changeOrigin: true,
            },
        },
    },
    build: {
        outDir: 'dist',
        sourcemap: false,
    },
});