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
        vue({
            template: {
                transformAssetUrls: {
                    base: null,
                    includeAbsolute: false,
                },
            },
        }),
        // Le plugin vuetify doit être placé après le plugin vue
        vuetify({
            autoImport: true,
        }),
    ],
    // Force Vite à rester sur 127.0.0.1 pour éviter les erreurs de connexion
    server: {
        host: "127.0.0.1",
        port: 5173,
        strictPort: true,
        hmr: {
            host: "127.0.0.1",
        },
    },
    resolve: {
        alias: {
            "@": "/resources/js",
        },
    },
});
