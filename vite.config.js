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
        vuetify({
            autoImport: true,
        }),
    ],
    server: {
        host: "127.0.0.1",
        port: 5175,
        strictPort: true,
        https: false,
        hmr: {
            host: "127.0.0.1",
        },
    },
    // AJOUT : Optimisation pour le mode Build (accélère le chargement sur PC Acer)
    build: {
        chunkSizeWarningLimit: 1000,
        rollupOptions: {
            output: {
                manualChunks(id) {
                    if (id.includes('node_modules')) {
                        return 'vendor'; // Sépare Vuetify et Vue du reste de ton code
                    }
                }
            }
        }
    },
    resolve: {
        alias: {
            "@": "/resources/js",
        },
    },
});
