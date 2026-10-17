/// <reference types="vitest/config" />
import { defineConfig, type Plugin } from 'vite';
import vue from '@vitejs/plugin-vue';

// Dev-only: `npm run dev:mock` serves an in-memory fake of the engine's student API
// (dev/mockApi.ts) so the whole flow can be clicked through without Go or Redis.
async function mockPlugin(): Promise<Plugin[]> {
    if (!process.env.EXAM_MOCK) return [];
    const { mockApiPlugin } = await import('./dev/mockApi');
    return [mockApiPlugin()];
}

export default defineConfig(async () => ({
    base: '/',
    plugins: [vue(), ...(await mockPlugin())],
    server: {
        port: 5180,
        proxy: process.env.EXAM_MOCK
            ? undefined
            : { '/api': { target: 'http://127.0.0.1:8090', changeOrigin: false } },
    },
    build: {
        outDir: 'dist',
        emptyOutDir: true,
        assetsDir: 'assets',
        // Students use old phones: keep syntax at ES2019 (Safari 13 / Chrome 80). No regex
        // lookbehind anywhere in src (it can't be transpiled and breaks iOS < 16.4).
        target: ['es2019', 'chrome80', 'safari13', 'firefox78', 'edge88'],
        cssTarget: ['chrome80', 'safari13', 'firefox78'],
        sourcemap: false,
        reportCompressedSize: true,
        chunkSizeWarningLimit: 400,
    },
    test: {
        environment: 'node',
        include: ['test/**/*.test.ts'],
    },
}));
