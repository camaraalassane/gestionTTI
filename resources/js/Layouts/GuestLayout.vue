<script setup>
import { Link } from "@inertiajs/vue3";
</script>

<template>
    <v-app>
        <v-main class="login-bg">
            <v-container fluid class="fill-height">
                <v-row align="center" justify="center" no-gutters>
                    <v-col
                        cols="12"
                        sm="8"
                        md="5"
                        lg="4"
                        class="pa-4 text-center"
                    >
                        <div class="mb-8">
                            <Link href="/">
                                <v-avatar
                                    color="white"
                                    size="80"
                                    class="mb-4 elevation-10"
                                >
                                    <v-icon
                                        icon="mdi-shield-check"
                                        size="48"
                                        color="teal-darken-3"
                                    />
                                </v-avatar>
                            </Link>
                            <h1
                                class="text-h3 font-weight-black text-white text-uppercase tracking-wider"
                            >
                                TTI
                                <span class="text-teal-lighten-3">Stock</span>
                            </h1>
                            <div
                                class="d-flex align-center justify-center mt-2"
                            >
                                <v-divider
                                    class="flex-grow-0 w-25 border-opacity-50"
                                    color="white"
                                ></v-divider>
                                <span
                                    class="text-overline text-white px-3 tracking-widest"
                                    >SÉCURISÉ</span
                                >
                                <v-divider
                                    class="flex-grow-0 w-25 border-opacity-50"
                                    color="white"
                                ></v-divider>
                            </div>
                        </div>

                        <v-card
                            elevation="24"
                            class="pa-8 rounded-xl bg-white glass-card text-left"
                        >
                            <slot />
                        </v-card>

                        <div class="mt-10">
                            <p class="text-caption text-white opacity-70">
                                &copy; {{ new Date().getFullYear() }} — TTI
                                Engineering
                                <br />
                                Système de gestion d'inventaire v2.1
                            </p>
                        </div>
                    </v-col>
                </v-row>
            </v-container>
        </v-main>
    </v-app>
</template>

<style scoped>
.login-bg {
    background: linear-gradient(135deg, #004d40 0%, #00796b 50%, #4db6ac 100%);
    background-size: cover;
    min-height: 100vh;
}

.glass-card {
    border: 1px solid rgba(255, 255, 255, 0.2) !important;
}

.tracking-wider {
    letter-spacing: 0.15rem !important;
}

.tracking-widest {
    letter-spacing: 0.3rem !important;
}

/* Animation fluide à l'affichage */
.v-container {
    animation: fadeIn 0.6s ease-out;
}

@keyframes fadeIn {
    from {
        opacity: 0;
        transform: translateY(10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}
</style>
