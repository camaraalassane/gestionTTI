<script setup>
import { ref, computed, watch } from "vue";
import { usePage, router } from "@inertiajs/vue3";

const drawer = ref(false);
const isNavigating = ref(false);
const page = usePage();

router.on('start', () => { isNavigating.value = true });
router.on('finish', () => { isNavigating.value = false });

const snackbar = ref({ show: false, message: "", color: "teal-darken-2", icon: "mdi-check-circle" });
const showNotify = (msg, color, icon) => { snackbar.value = { show: true, message: msg, color, icon }; };

watch(() => page.props.flash, (flash) => {
    if (flash?.success) showNotify(flash.success, "teal-darken-2", "mdi-check-circle");
    else if (flash?.error) showNotify(flash.error, "red-darken-2", "mdi-alert-circle");
}, { deep: true });

const user = computed(() => page.props.auth?.user || {});
const initials = computed(() => user.value?.name ? user.value.name.substring(0, 2).toUpperCase() : "??");

// ✅ Fonction de navigation
const navigateTo = (routeName, params = {}) => {
    router.get(route(routeName, params));
};

// ✅ Fonction de déconnexion (POST)
const logout = () => {
    router.post(route('logout'));
};
</script>

<template>
    <v-app>
        <v-app-bar elevation="1" color="white" border="b" class="app-bar">
            <v-progress-linear :active="isNavigating" indeterminate absolute bottom color="teal-darken-1"
                height="3"></v-progress-linear>
            <v-app-bar-nav-icon class="d-md-none" @click="drawer = !drawer"></v-app-bar-nav-icon>

            <v-container class="fill-height d-flex align-center" fluid>
                <div class="mr-2 mr-md-4">
                    <v-btn @click="navigateTo('selection')" variant="text" min-width="auto">
                        <v-avatar color="teal-darken-2" size="36" size-md="40" class="elevation-2">
                            <v-icon color="white" icon="mdi-file-send" size="20" size-md="24"></v-icon>
                        </v-avatar>
                    </v-btn>
                </div>

                <div class="d-none d-md-flex align-center" style="gap: 4px">
                    <v-btn @click="navigateTo('selection')" :active="route().current('selection')" variant="text"
                        prepend-icon="mdi-home" class="text-none rounded-lg">Materiels</v-btn>
                    <v-btn @click="navigateTo('demandes.index')" :active="route().current('demandes.index')"
                        variant="text" prepend-icon="mdi-clipboard-text-clock-outline"
                        class="text-none rounded-lg">Liste</v-btn>
                    <v-btn @click="navigateTo('demandes.gestion_service')"
                        :active="route().current('demandes.gestion_service')" variant="text"
                        prepend-icon="mdi-office-building-cog" class="text-none rounded-lg">Par Service</v-btn>
                    <v-btn @click="navigateTo('demandes.historique')" :active="route().current('demandes.historique')"
                        variant="text" prepend-icon="mdi-history" class="text-none rounded-lg">Historique</v-btn>
                    <v-btn @click="navigateTo('demandes.create')" variant="flat" color="teal-darken-1"
                        prepend-icon="mdi-send-plus"
                        class="ml-4 text-none rounded-pill elevation-2 px-4 px-md-6">Nouvelle Demande</v-btn>
                </div>

                <v-spacer></v-spacer>

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
                            <template v-slot:prepend><v-avatar color="teal-lighten-4">{{ initials
                            }}</v-avatar></template>
                            <v-list-item-title class="font-weight-bold">{{ user?.name }}</v-list-item-title>
                            <v-list-item-subtitle class="text-caption">Espace Demandes</v-list-item-subtitle>
                        </v-list-item>
                        <v-divider class="mb-2"></v-divider>
                        <v-list-item @click="navigateTo('profile.edit')" prepend-icon="mdi-account-circle-outline"
                            title="Mon Profil" class="rounded-lg"></v-list-item>
                        <v-divider class="my-2"></v-divider>
                        <!-- ✅ Déconnexion avec POST -->
                        <v-list-item @click="logout" prepend-icon="mdi-logout" title="Déconnexion"
                            class="rounded-lg text-red-darken-3 font-weight-bold"></v-list-item>
                    </v-list>
                </v-menu>
            </v-container>
        </v-app-bar>

        <v-navigation-drawer v-model="drawer" temporary width="280" class="rounded-r-xl">
            <div class="pa-4 pb-2 bg-teal-darken-4">
                <div class="d-flex align-center">
                    <v-avatar color="teal-lighten-4" size="48" class="mr-3"><span
                            class="text-teal-darken-4 font-weight-bold text-h6">{{ initials }}</span></v-avatar>
                    <div>
                        <div class="text-white font-weight-bold">{{ user?.name }}</div>
                        <div class="text-teal-lighten-3 text-caption">Espace Demandes</div>
                    </div>
                </div>
            </div>

            <v-list nav class="pt-2">
                <v-list-item @click="navigateTo('selection'); drawer = false" prepend-icon="mdi-home" title="Materiels"
                    :active="route().current('selection')" class="rounded mx-2 mb-1"></v-list-item>
                <v-list-item @click="navigateTo('demandes.index'); drawer = false"
                    prepend-icon="mdi-clipboard-text-clock-outline" title="Liste Demandes"
                    :active="route().current('demandes.index')" class="rounded mx-2 mb-1"></v-list-item>
                <v-list-item @click="navigateTo('demandes.gestion_service'); drawer = false"
                    prepend-icon="mdi-office-building-cog" title="Par Service"
                    :active="route().current('demandes.gestion_service')" class="rounded mx-2 mb-1"></v-list-item>
                <v-list-item @click="navigateTo('demandes.historique'); drawer = false" prepend-icon="mdi-history"
                    title="Historique" :active="route().current('demandes.historique')"
                    class="rounded mx-2 mb-1"></v-list-item>
                <v-divider class="my-3 mx-2"></v-divider>
                <v-list-item @click="navigateTo('profile.edit'); drawer = false"
                    prepend-icon="mdi-account-circle-outline" title="Mon Profil"
                    class="rounded mx-2 mb-1"></v-list-item>
                <!-- ✅ Déconnexion dans drawer -->
                <v-list-item @click="logout; drawer = false" prepend-icon="mdi-logout" title="Déconnexion"
                    class="rounded mx-2 mt-2 text-red-darken-3 font-weight-bold"></v-list-item>
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
            <div class="d-flex align-center"><v-icon :icon="snackbar.icon" class="mr-3"></v-icon><span
                    class="font-weight-bold">{{ snackbar.message }}</span></div>
            <template v-slot:actions><v-btn icon="mdi-close" variant="text" size="small"
                    @click="snackbar.show = false"></v-btn></template>
        </v-snackbar>
    </v-app>
</template>

<style scoped>
.app-bar {
    border-bottom: 1px solid #e0e0e0;
}

.cursor-pointer {
    cursor: pointer;
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

@media (max-width: 600px) {
    .app-bar {
        padding: 0 8px !important;
    }

    :deep(.v-btn) {
        font-size: 12px !important;
    }

    :deep(.v-btn .v-icon) {
        font-size: 18px !important;
    }
}
</style>
