import { defineConfig } from "vite";
import laravel from "laravel-vite-plugin";
import vue from "@vitejs/plugin-vue";
import vuetify from "vite-plugin-vuetify";

export default defineConfig({
    plugins: [
        laravel({
            input: "resources/js/app.js",
            refresh: true,
        }),
        vue(),
        vuetify({ autoImport: true }),
    ],
    server: {
        host: "127.0.0.1",
        port: 5175,
        strictPort: true,
        https: false,
        hmr: { host: "127.0.0.1" },
    },
    resolve: {
        alias: { "@": "/resources/js" },
    },
    // PAS de build.rollupOptions pour éviter les erreurs
});
