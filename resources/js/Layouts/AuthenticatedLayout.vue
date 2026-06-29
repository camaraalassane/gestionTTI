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
        <v-app-bar elevation="0" color="white" border="b">
            <v-progress-linear :active="isNavigating" indeterminate absolute bottom color="teal-darken-1"
                height="3"></v-progress-linear>
            <v-app-bar-nav-icon class="hidden-sm-and-up" @click="drawer = !drawer"></v-app-bar-nav-icon>

            <v-container class="fill-height d-flex align-center px-4" fluid>
                <div class="mr-6">
                    <v-btn @click="navigateTo('dashboard')" variant="text" min-width="auto">
                        <v-avatar color="teal-darken-1" size="40" class="elevation-2 cursor-pointer">
                            <v-icon color="white" icon="mdi-garage-variant"></v-icon>
                        </v-avatar>
                    </v-btn>
                </div>

                <div class="hidden-xs d-sm-flex align-center" style="gap: 12px">
                    <v-btn @click="navigateTo('selection')" :active="route().current('selection')" variant="text"
                        prepend-icon="mdi-view-home-outline" class="text-none custom-nav-btn">
                        Demandes
                    </v-btn>
                    <v-btn @click="navigateTo('dashboard')" :active="route().current('dashboard')" variant="text"
                        prepend-icon="mdi-view-dashboard-outline" class="text-none custom-nav-btn">
                        Dashboard
                    </v-btn>
                    <v-btn @click="navigateTo('materiel.index')" :active="route().current('materiel.index')"
                        variant="text" prepend-icon="mdi-format-list-bulleted" class="text-none custom-nav-btn">
                        Inventaire
                    </v-btn>
                    <v-btn @click="navigateTo('reception.index')" :active="route().current('reception.index')"
                        variant="text" prepend-icon="mdi-file-certificate-outline" class="text-none custom-nav-btn">
                        Contrats
                    </v-btn>
                    <v-btn @click="navigateTo('materiel.indexmat')" variant="flat" color="teal-darken-1"
                        prepend-icon="mdi-plus" class="ml-4 text-none font-weight-black rounded-lg">
                        Nouveau
                    </v-btn>
                </div>

                <v-spacer></v-spacer>

                <v-menu min-width="240px" rounded="xl" transition="slide-y-transition">
                    <template v-slot:activator="{ props }">
                        <v-btn icon v-bind="props" class="ml-1 border elevation-1">
                            <v-avatar color="teal-lighten-5" size="36">
                                <span class="text-teal-darken-3 font-weight-black text-caption">{{ initials }}</span>
                            </v-avatar>
                        </v-btn>
                    </template>

                    <v-list class="pa-2" nav elevation="10">
                        <v-list-subheader class="text-uppercase text-caption font-weight-black text-teal">Connecté en
                            tant
                            que</v-list-subheader>
                        <v-list-item class="mb-2">
                            <template v-slot:prepend><v-avatar color="teal-darken-1" size="32" class="mr-2"><span
                                        class="text-white text-caption">{{ initials }}</span></v-avatar></template>
                            <v-list-item-title class="font-weight-bold">{{ user?.name }}</v-list-item-title>
                        </v-list-item>
                        <v-divider class="mb-2"></v-divider>
                        <v-list-item @click="navigateTo('profile.edit')" prepend-icon="mdi-account-circle-outline"
                            title="Mon Profil" class="rounded-lg mb-1"></v-list-item>
                        <v-list-item @click="navigateTo('parametres.index')" prepend-icon="mdi-cog-outline"
                            title="Configuration" class="rounded-lg mb-1"></v-list-item>
                        <v-divider class="my-2"></v-divider>
                        <v-list-subheader
                            class="text-uppercase text-caption font-weight-bold text-teal-darken-2">Administration</v-list-subheader>
                        <v-list-item @click="navigateTo('inventaire.index')" prepend-icon="mdi-archive-check-outline"
                            title="Clôture Annuelle" class="rounded-lg mb-1"></v-list-item>
                        <v-divider class="my-2"></v-divider>
                        <!-- ✅ Déconnexion avec POST -->
                        <v-list-item @click="logout" prepend-icon="mdi-logout" title="Déconnexion"
                            class="rounded-lg text-red-darken-3 font-weight-black"></v-list-item>
                    </v-list>
                </v-menu>
            </v-container>
        </v-app-bar>

        <v-navigation-drawer v-model="drawer" temporary width="280">
            <div class="pa-4 text-center bg-teal-darken-1 text-white">
                <v-avatar size="64" color="white" class="mb-2"><span
                        class="text-teal-darken-1 text-h5 font-weight-black">{{
                            initials }}</span></v-avatar>
                <div class="font-weight-bold">{{ user?.name }}</div>
            </div>
            <v-list nav>
                <v-list-item @click="navigateTo('selection'); drawer = false" prepend-icon="mdi-view-home"
                    title="Demandes" :active="route().current('selection')"></v-list-item>
                <v-list-item @click="navigateTo('dashboard'); drawer = false" prepend-icon="mdi-view-dashboard"
                    title="Dashboard" :active="route().current('dashboard')"></v-list-item>
                <v-list-item @click="navigateTo('materiel.index'); drawer = false"
                    prepend-icon="mdi-format-list-bulleted" title="Inventaire"
                    :active="route().current('materiel.index')"></v-list-item>
                <v-list-item @click="navigateTo('reception.index'); drawer = false" prepend-icon="mdi-file-certificate"
                    title="Contrats" :active="route().current('reception.index')"></v-list-item>
                <v-divider class="my-2"></v-divider>
                <!-- ✅ Déconnexion dans drawer -->
                <v-list-item @click="logout; drawer = false" prepend-icon="mdi-logout" title="Déconnexion"
                    class="text-red-darken-3 font-weight-bold"></v-list-item>
            </v-list>
        </v-navigation-drawer>

        <v-main class="bg-teal-lighten-5">
            <header v-if="$slots.header" class="bg-white border-b mb-6"><v-container class="py-6" fluid>
                    <h2 class="text-h4 font-weight-black text-teal-darken-4">
                        <slot name="header" />
                    </h2>
                </v-container></header>
            <v-container fluid class="page-container px-4 px-md-8">
                <slot />
            </v-container>
        </v-main>

        <v-snackbar v-model="snackbar.show" :color="snackbar.color" location="top right" elevation="12" rounded="lg"
            class="mt-4">
            <div class="d-flex align-center"><v-icon :icon="snackbar.icon" class="mr-3" size="large"></v-icon><span
                    class="font-weight-bold">{{ snackbar.message }}</span></div>
            <template v-slot:actions><v-btn icon="mdi-close" variant="text"
                    @click="snackbar.show = false"></v-btn></template>
        </v-snackbar>
    </v-app>
</template>

<style scoped>
.cursor-pointer {
    cursor: pointer !important;
}

.custom-nav-btn {
    font-size: 0.875rem !important;
    font-weight: 600 !important;
    color: #455a64 !important;
}

.page-container {
    animation: fadeInSlide 0.4s ease-out;
}

@keyframes fadeInSlide {
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
    color: #00796b !important;
    font-weight: 800 !important;
}

:deep(.v-btn--active::after) {
    content: "";
    position: absolute;
    bottom: -10px;
    left: 20%;
    right: 20%;
    height: 3px;
    background: #00796b;
    border-radius: 4px;
}
</style>
