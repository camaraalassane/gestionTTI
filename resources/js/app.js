import "../css/app.css"; // <--- INDISPENSABLE : Relie ton nouveau CSS léger
import "./bootstrap";
import { createApp, h } from "vue";
import { createInertiaApp } from "@inertiajs/vue3";
import { resolvePageComponent } from "laravel-vite-plugin/inertia-helpers";
import { ZiggyVue } from "ziggy-js";

// Vuetify
import "vuetify/styles";
import { createVuetify } from "vuetify";
import * as components from "vuetify/components";
import * as directives from "vuetify/directives";
/* ASTUCE PERFORMANCE : Si ton PC rame vraiment, 
   on pourrait importer les icônes via un CDN dans le HTML, 
   mais gardons cet import JS pour l'instant.
*/
import "@mdi/font/css/materialdesignicons.css";

const vuetify = createVuetify({
    components,
    directives,
    theme: {
        defaultTheme: "light",
        themes: {
            light: {
                colors: {
                    primary: "#00796B", // Teal principal
                    secondary: "#26A69A",
                    accent: "#004D40",
                },
            },
        },
    },
});

createInertiaApp({
    title: (title) =>
        `${title} - ${import.meta.env.VITE_APP_NAME || "Laravel"}`,
    resolve: (name) =>
        resolvePageComponent(
            `./Pages/${name}.vue`,
            import.meta.glob("./Pages/**/*.vue"),
        ),
    setup({ el, App, props, plugin }) {
        createApp({ render: () => h(App, props) })
            .use(plugin)
            .use(vuetify)
            .use(ZiggyVue)
            .mount(el);
    },
    progress: {
        color: "#00796B",
        showSpinner: false, // Désactive le spinner pour gagner un peu de fluide sur Acer
    },
});
