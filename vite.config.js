import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue'
import path from 'path'

export default defineConfig({
    plugins: [
        vue(),
        laravel([
            'resources/js/app.js',
        ]),
        
    ],
    server: {
        host: '127.0.0.1',
        port: 5173,
        strictPort: true,
        hmr: {
            host: '127.0.0.1',
            protocol: 'ws',
            port: 5173,
        },
    },
    // build: {
    //     rollupOptions: {
    //       output: {
    //         format: 'iife', // or 'umd'
    //         entryFileNames: '[name].js',
    //         dir: 'public/build',
    //       },
    //     },
    //     outDir: 'build',
    //   },
    resolve: {
        alias: {
            '~bootstrap': path.resolve(__dirname, 'node_modules/bootstrap'),
            '@': '/resources/js',
        }
    },
});