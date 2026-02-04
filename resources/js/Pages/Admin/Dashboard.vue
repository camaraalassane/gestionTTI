<script setup>
import { computed } from "vue";
import { usePage, Head } from "@inertiajs/vue3";

const page = usePage();

// Récupère le nom de l'admin (Alassane) ou met "Administrateur" par défaut - INCHANGÉ
const adminName = computed(
    () => page.props.auth?.user?.name || "Administrateur",
);
</script>

<template>
    <Head title="Administration - TTI SERVICE" />

    <v-app>
        <v-navigation-drawer
            permanent
            color="teal-darken-4"
            elevation="10"
            class="border-e-sm border-teal-lighten-4"
        >
            <div class="pa-6 text-center">
                <v-avatar color="white" size="64" class="mb-3 elevation-4">
                    <v-icon
                        icon="mdi-shield-crown-outline"
                        size="36"
                        color="teal-darken-4"
                    />
                </v-avatar>
                <div
                    class="text-subtitle-1 font-weight-black text-white text-uppercase tracking-wider"
                >
                    {{ adminName }}
                </div>
                <v-chip
                    size="x-small"
                    color="teal-lighten-3"
                    variant="flat"
                    class="mt-1 font-weight-bold"
                    >ADMINISTRATEUR</v-chip
                >
            </div>

            <v-divider class="border-opacity-25 mb-2" color="white"></v-divider>

            <v-list nav density="comfortable">
                <v-list-item
                    prepend-icon="mdi-view-dashboard-outline"
                    title="Tableau de bord"
                    :href="route('admin.dashboard')"
                    class="mb-1 nav-item"
                    rounded="lg"
                ></v-list-item>

                <v-list-item
                    prepend-icon="mdi-account-group-outline"
                    title="Utilisateurs"
                    :href="route('admin.users.index')"
                    class="mb-1 nav-item"
                    active-color="teal-lighten-4"
                    rounded="lg"
                ></v-list-item>

                <v-list-item
                    prepend-icon="mdi-package-variant-closed"
                    title="Correction Stock"
                    :href="route('admin.materiel.index')"
                    class="mb-1 nav-item"
                    rounded="lg"
                ></v-list-item>

                <v-list-item
                    prepend-icon="mdi-file-clock-outline"
                    title="Historique Demandes"
                    :href="route('admin.demandes.index')"
                    class="mb-1 nav-item"
                    rounded="lg"
                ></v-list-item>
            </v-list>

            <template v-slot:append>
                <div class="pa-4 bg-teal-darken-4">
                    <v-btn
                        block
                        color="teal-lighten-4"
                        variant="tonal"
                        prepend-icon="mdi-logout"
                        :href="route('logout')"
                        method="post"
                        as="button"
                        class="text-none font-weight-bold rounded-lg"
                    >
                        Déconnexion
                    </v-btn>
                </div>
            </template>
        </v-navigation-drawer>

        <v-app-bar flat color="white" border="b" class="px-4">
            <v-app-bar-title
                class="font-weight-black text-teal-darken-4 tracking-wider"
            >
                TTI <span class="text-teal-darken-1">STOCK</span>
                <span
                    class="text-caption text-grey-darken-1 ms-2 font-weight-medium"
                    >| ADMIN PANEL</span
                >
            </v-app-bar-title>

            <v-spacer></v-spacer>

            <v-btn icon class="me-2" color="teal-darken-4">
                <v-icon icon="mdi-bell-outline"></v-icon>
            </v-btn>

            <v-chip
                class="px-4 font-weight-bold"
                color="teal-darken-3"
                variant="flat"
                label
            >
                <v-icon start icon="mdi-account-circle-outline"></v-icon>
                {{ adminName }}
            </v-chip>
        </v-app-bar>

        <v-main class="bg-teal-lighten-5">
            <v-container fluid class="pa-8">
                <v-row class="mb-6">
                    <v-col cols="12">
                        <div class="d-flex align-center">
                            <v-icon
                                icon="mdi-hand-wave-outline"
                                color="teal-darken-4"
                                size="32"
                                class="me-3"
                            />
                            <div>
                                <h2
                                    class="text-h4 font-weight-black text-teal-darken-4"
                                >
                                    Salut, {{ adminName }} !
                                </h2>
                                <p class="text-subtitle-2 text-teal-darken-2">
                                    Voici ce qui se passe sur votre plateforme
                                    aujourd'hui.
                                </p>
                            </div>
                        </div>
                    </v-col>
                </v-row>

                <v-fade-transition mode="out-in">
                    <slot />
                </v-fade-transition>
            </v-container>
        </v-main>
    </v-app>
</template>

<style scoped>
.tracking-wider {
    letter-spacing: 0.1rem !important;
}

.nav-item {
    color: rgba(255, 255, 255, 0.7) !important;
    transition: all 0.2s ease;
}

.nav-item:hover {
    background-color: rgba(255, 255, 255, 0.1) !important;
    color: white !important;
    transform: translateX(5px);
}

/* Style pour l'item actif */
:deep(.v-list-item--active) {
    background-color: rgba(255, 255, 255, 0.15) !important;
    color: white !important;
    font-weight: bold !important;
}

/* Background subtil pour le main */
.bg-teal-lighten-5 {
    background: linear-gradient(135deg, #f0f4f4 0%, #e0f2f1 100%) !important;
}
</style>
