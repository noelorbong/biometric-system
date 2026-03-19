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