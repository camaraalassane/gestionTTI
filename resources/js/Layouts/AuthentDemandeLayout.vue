<script setup>
import { ref, computed, watch } from "vue";
import { Link, usePage, router } from "@inertiajs/vue3";

const drawer = ref(false);
const isNavigating = ref(false);
const page = usePage();

router.on('start', () => { isNavigating.value = true });
router.on('finish', () => { isNavigating.value = false });

const snackbar = ref({
    show: false,
    message: "",
    color: "success",
    icon: "mdi-check-circle",
});

const showNotify = (msg, color, icon) => {
    snackbar.value = { show: true, message: msg, color, icon };
};

watch(
    () => page.props.flash,
    (flash) => {
        if (flash?.success) {
            showNotify(flash.success, "teal-darken-2", "mdi-check-circle");
        } else if (flash?.error) {
            showNotify(flash.error, "red-darken-2", "mdi-alert-circle");
        }
    },
    { deep: true },
);

const user = computed(() => page.props.auth?.user || {});

const initials = computed(() => {
    const name = user.value?.name;
    if (!name) return "??";
    const parts = name.split(" ");
    if (parts.length > 1) return (parts[0][0] + parts[1][0]).toUpperCase();
    return name.substring(0, 2).toUpperCase();
});

// Navigation items pour le drawer mobile
const navItems = [
    { title: "Materiels", route: "selection", icon: "mdi-home" },
    { title: "Liste Demandes", route: "demandes.index", icon: "mdi-clipboard-text-clock-outline" },
    { title: "Par Service", route: "demandes.gestion_service", icon: "mdi-office-building-cog" },
    { title: "Historique", route: "demandes.historique", icon: "mdi-history" },
];
</script>

<template>
    <v-app>
        <v-app-bar elevation="1" color="white" border="b" class="app-bar">
            <v-progress-linear :active="isNavigating" indeterminate absolute bottom color="teal-darken-1"
                height="3"></v-progress-linear>

            <!-- Bouton menu mobile -->
            <v-app-bar-nav-icon class="d-md-none" @click="drawer = !drawer"></v-app-bar-nav-icon>

            <v-container class="fill-height d-flex align-center" fluid>
                <!-- Logo -->
                <div class="mr-2 mr-md-4">
                    <Link :href="route('selection')" class="d-flex text-decoration-none">
                        <v-avatar color="teal-darken-2" size="36" size-md="40" class="elevation-2 cursor-pointer">
                            <v-icon color="white" icon="mdi-file-send" size="20" size-md="24"></v-icon>
                        </v-avatar>
                    </Link>
                </div>

                <!-- Navigation Desktop (visible sur md et plus) -->
                <div class="d-none d-md-flex align-center" style="gap: 4px">
                    <Link :href="route('selection')">
                        <v-btn :active="route().current('selection')" variant="text" prepend-icon="mdi-home"
                            class="text-none rounded-lg">
                            Materiels
                        </v-btn>
                    </Link>

                    <Link :href="route('demandes.index')">
                        <v-btn :active="route().current('demandes.index')" variant="text"
                            prepend-icon="mdi-clipboard-text-clock-outline" class="text-none rounded-lg">
                            Liste
                        </v-btn>
                    </Link>

                    <Link :href="route('demandes.gestion_service')">
                        <v-btn :active="route().current('demandes.gestion_service')" variant="text"
                            prepend-icon="mdi-office-building-cog" class="text-none rounded-lg">
                            Par Service
                        </v-btn>
                    </Link>

                    <Link :href="route('demandes.historique')">
                        <v-btn :active="route().current('demandes.historique')" variant="text"
                            prepend-icon="mdi-history" class="text-none rounded-lg">
                            Historique
                        </v-btn>
                    </Link>

                    <Link :href="route('demandes.create')">
                        <v-btn variant="flat" color="teal-darken-1" prepend-icon="mdi-send-plus"
                            class="ml-4 text-none rounded-pill elevation-2 px-4 px-md-6">
                            Nouvelle Demande
                        </v-btn>
                    </Link>
                </div>

                <v-spacer></v-spacer>

                <!-- Menu utilisateur -->
                <v-menu min-width="240px" rounded="xl" transition="slide-y-transition">
                    <template v-slot:activator="{ props }">
                        <v-btn icon v-bind="props" class="ml-2">
                            <v-avatar color="teal-lighten-4" size="36" size-md="38" class="border">
                                <span class="text-teal-darken-3 font-weight-bold text-caption">{{ initials }}</span>
                            </v-avatar>
                        </v-btn>
                    </template>

                    <v-list class="pa-2" nav elevation="10">
                        <v-list-item class="mb-2">
                            <template v-slot:prepend>
                                <v-avatar color="teal-lighten-4">{{ initials }}</v-avatar>
                            </template>
                            <v-list-item-title class="font-weight-bold">{{ user?.name }}</v-list-item-title>
                            <v-list-item-subtitle class="text-caption">Espace Demandes</v-list-item-subtitle>
                        </v-list-item>

                        <v-divider class="mb-2"></v-divider>

                        <Link :href="route('profile.edit')" class="text-decoration-none">
                            <v-list-item prepend-icon="mdi-account-circle-outline" title="Mon Profil"
                                class="rounded-lg"></v-list-item>
                        </Link>

                        <Link :href="route('logout')" method="post" as="div" class="cursor-pointer mt-1">
                            <v-list-item class="rounded pa-0">
                                <div class="d-flex align-center text-red-darken-2 font-weight-bold w-100 px-4 py-2">
                                    <v-icon icon="mdi-logout" class="mr-3"></v-icon>
                                    <span>Déconnexion</span>
                                </div>
                            </v-list-item>
                        </Link>
                    </v-list>
                </v-menu>
            </v-container>
        </v-app-bar>

        <!-- Navigation Drawer Mobile (amélioré) -->
        <v-navigation-drawer v-model="drawer" temporary width="280" class="rounded-r-xl">
            <div class="pa-4 pb-2 bg-teal-darken-4">
                <div class="d-flex align-center">
                    <v-avatar color="teal-lighten-4" size="48" class="mr-3">
                        <span class="text-teal-darken-4 font-weight-bold text-h6">{{ initials }}</span>
                    </v-avatar>
                    <div>
                        <div class="text-white font-weight-bold">{{ user?.name }}</div>
                        <div class="text-teal-lighten-3 text-caption">Espace Demandes</div>
                    </div>
                </div>
            </div>

            <v-list nav class="pt-2">
                <Link :href="route('selection')" @click="drawer = false">
                    <v-list-item prepend-icon="mdi-home" title="Materiels" :active="route().current('selection')"
                        class="rounded mx-2 mb-1"></v-list-item>
                </Link>

                <Link :href="route('demandes.index')" @click="drawer = false">
                    <v-list-item prepend-icon="mdi-clipboard-text-clock-outline" title="Liste Demandes"
                        :active="route().current('demandes.index')" class="rounded mx-2 mb-1"></v-list-item>
                </Link>

                <Link :href="route('demandes.gestion_service')" @click="drawer = false">
                    <v-list-item prepend-icon="mdi-office-building-cog" title="Par Service"
                        :active="route().current('demandes.gestion_service')" class="rounded mx-2 mb-1"></v-list-item>
                </Link>

                <Link :href="route('demandes.historique')" @click="drawer = false">
                    <v-list-item prepend-icon="mdi-history" title="Historique"
                        :active="route().current('demandes.historique')" class="rounded mx-2 mb-1"></v-list-item>
                </Link>

                <v-divider class="my-3 mx-2"></v-divider>

                <Link :href="route('profile.edit')" @click="drawer = false">
                    <v-list-item prepend-icon="mdi-account-circle-outline" title="Mon Profil"
                        class="rounded mx-2 mb-1"></v-list-item>
                </Link>

                <Link :href="route('demandes.create')" @click="drawer = false">
                    <v-list-item prepend-icon="mdi-send-plus" title="Nouvelle Demande"
                        class="rounded mx-2 mt-2 bg-teal-lighten-5">
                        <template v-slot:append>
                            <v-icon icon="mdi-arrow-right" size="small" color="teal-darken-2"></v-icon>
                        </template>
                    </v-list-item>
                </Link>
            </v-list>
        </v-navigation-drawer>

        <v-main class="bg-teal-lighten-5">
            <header v-if="$slots.header" class="bg-white border-b-lg border-teal-darken-1 elevation-2 mb-4 mb-md-6">
                <v-container class="py-4 py-md-6" fluid>
                    <h1 class="text-h5 text-md-h4 font-weight-black text-teal-darken-4 px-3 px-md-4">
                        <slot name="header" />
                    </h1>
                </v-container>
            </header>

            <v-container fluid class="animate-fade-in px-3 px-md-6 px-lg-10 pb-10">
                <slot />
            </v-container>
        </v-main>

        <v-snackbar v-model="snackbar.show" :color="snackbar.color" location="top right" elevation="24" rounded="pill"
            :timeout="4500">
            <div class="d-flex align-center">
                <v-icon :icon="snackbar.icon" class="mr-3"></v-icon>
                <span class="font-weight-bold">{{ snackbar.message }}</span>
            </div>
            <template v-slot:actions>
                <v-btn icon="mdi-close" variant="text" size="small" @click="snackbar.show = false"></v-btn>
            </template>
        </v-snackbar>
    </v-app>
</template>

<style scoped>
.cursor-pointer {
    cursor: pointer !important;
}

.animate-fade-in {
    animation: slideIn 0.4s cubic-bezier(0, 0, 0.2, 1);
}

@keyframes slideIn {
    from {
        opacity: 0;
        transform: translateY(10px);
    }

    to {
        opacity: 1;
        transform: translateY(0);
    }
}

:deep(.v-btn--active) {
    background-color: rgba(0, 121, 107, 0.1) !important;
    color: #00796b !important;
    font-weight: 800 !important;
}

:deep(a) {
    text-decoration: none !important;
    color: inherit;
}

/* Améliorations responsive */
@media (max-width: 600px) {
    .app-bar {
        padding: 0 8px !important;
    }

    :deep(.v-container) {
        padding-left: 12px !important;
        padding-right: 12px !important;
    }

    :deep(.v-btn) {
        font-size: 12px !important;
    }

    :deep(.v-btn .v-icon) {
        font-size: 18px !important;
    }
}
</style>
