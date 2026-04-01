<script setup>
    import { ref, computed, watch } from "vue";
    import { Link, usePage, router } from "@inertiajs/vue3";

    /**
     * ÉTAT & AUTHENTIFICATION
     */
    const drawer = ref(false);
    const isNavigating = ref(false); // État pour la barre de chargement
    const page = usePage();

    const user = computed(() => page.props.auth?.user || {});
    const initials = computed(() => {
        return user.value?.name
            ? user.value.name.substring(0, 2).toUpperCase()
            : "??";
    });

    /**
     * GESTION DU CHARGEMENT (Lazy Feedback)
     * Active la barre de progression dès qu'on clique sur un lien
     */
    router.on('start', () => { isNavigating.value = true });
    router.on('finish', () => { isNavigating.value = false });

    /**
     * GESTION DES NOTIFICATIONS
     */
    const snackbar = ref({
        show: false,
        message: "",
        color: "teal-darken-2",
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
</script>

<template>
    <v-app>
        <v-app-bar elevation="0" color="white" border="b">
            <v-progress-linear
                :active="isNavigating"
                indeterminate
                absolute
                bottom
                color="teal-darken-1"
                height="3"
            ></v-progress-linear>

            <v-app-bar-nav-icon class="hidden-sm-and-up" @click="drawer = !drawer"></v-app-bar-nav-icon>

            <v-container class="fill-height d-flex align-center px-4" fluid>
                <div class="mr-6">
                    <Link :href="route('dashboard')" class="d-flex text-decoration-none">
                        <v-avatar color="teal-darken-1" size="40" class="elevation-2 cursor-pointer">
                            <v-icon color="white" icon="mdi-garage-variant"></v-icon>
                        </v-avatar>
                    </Link>
                </div>

                <div class="hidden-xs d-sm-flex align-center" style="gap: 12px">
                    <Link :href="route('selection')">
                        <v-btn :active="route().current('selection')" variant="text" prepend-icon="mdi-view-home-outline" class="text-none custom-nav-btn">
                            Demandes
                        </v-btn>
                    </Link>

                    <Link :href="route('dashboard')">
                        <v-btn :active="route().current('dashboard')" variant="text" prepend-icon="mdi-view-dashboard-outline" class="text-none custom-nav-btn">
                            Dashboard
                        </v-btn>
                    </Link>

                    <Link :href="route('materiel.index')">
                        <v-btn :active="route().current('materiel.index')" variant="text" prepend-icon="mdi-format-list-bulleted" class="text-none custom-nav-btn">
                            Inventaire
                        </v-btn>
                    </Link>

                    <Link :href="route('reception.index')">
                        <v-btn :active="route().current('reception.index')" variant="text" prepend-icon="mdi-file-certificate-outline" class="text-none custom-nav-btn">
                            Contrats
                        </v-btn>
                    </Link>

                    <Link :href="route('materiel.indexmat')">
                        <v-btn variant="flat" color="teal-darken-1" prepend-icon="mdi-plus" class="ml-4 text-none font-weight-black rounded-lg">
                            Nouveau
                        </v-btn>
                    </Link>
                </div>

                <v-spacer></v-spacer>

                <v-menu min-width="240px" rounded="xl" transition="slide-y-transition">
                    <template v-slot:activator="{ props }">
                        <v-btn icon v-bind="props" class="ml-1 border elevation-1">
                            <v-avatar color="teal-lighten-5" size="36">
                                <span class="text-teal-darken-3 font-weight-black text-caption">
                                    {{ initials }}
                                </span>
                            </v-avatar>
                        </v-btn>
                    </template>

                    <v-list class="pa-2" nav elevation="10">
                        <v-list-subheader class="text-uppercase text-caption font-weight-black text-teal">Connecté en tant que</v-list-subheader>
                        <v-list-item class="mb-2">
                            <template v-slot:prepend>
                                <v-avatar color="teal-darken-1" size="32" class="mr-2">
                                    <span class="text-white text-caption">{{ initials }}</span>
                                </v-avatar>
                            </template>
                            <v-list-item-title class="font-weight-bold">{{ user?.name }}</v-list-item-title>
                        </v-list-item>

                        <v-divider class="mb-2"></v-divider>

                        <Link :href="route('profile.edit')" class="text-decoration-none">
                            <v-list-item prepend-icon="mdi-account-circle-outline" title="Mon Profil" class="rounded-lg mb-1"></v-list-item>
                        </Link>

                        <Link :href="route('parametres.index')" class="text-decoration-none">
                            <v-list-item prepend-icon="mdi-cog-outline" title="Configuration" class="rounded-lg mb-1"></v-list-item>
                        </Link>

                        <v-divider class="my-2"></v-divider>
                        <v-list-subheader class="text-uppercase text-caption font-weight-bold text-teal-darken-2">Administration</v-list-subheader>

                        <Link :href="route('inventaire.index')" class="text-decoration-none">
                            <v-list-item prepend-icon="mdi-archive-check-outline" title="Clôture Annuelle" class="rounded-lg mb-1"></v-list-item>
                        </Link>

                        <v-divider class="my-2"></v-divider>

                        <Link :href="route('logout')" method="post" as="div" class="cursor-pointer">
                            <v-list-item prepend-icon="mdi-logout" title="Déconnexion" class="rounded-lg text-red-darken-3 font-weight-black"></v-list-item>
                        </Link>
                    </v-list>
                </v-menu>
            </v-container>
        </v-app-bar>

        <v-navigation-drawer v-model="drawer" temporary width="280">
            <div class="pa-4 text-center bg-teal-darken-1 text-white">
                <v-avatar size="64" color="white" class="mb-2">
                    <span class="text-teal-darken-1 text-h5 font-weight-black">{{ initials }}</span>
                </v-avatar>
                <div class="font-weight-bold">{{ user?.name }}</div>
            </div>
            <v-list nav>
                <Link :href="route('selection')">
                    <v-list-item prepend-icon="mdi-view-home" title="Demandes" :active="route().current('selection')"></v-list-item>
                </Link>
                <Link :href="route('dashboard')">
                    <v-list-item prepend-icon="mdi-view-dashboard" title="Dashboard" :active="route().current('dashboard')"></v-list-item>
                </Link>
                <Link :href="route('materiel.index')">
                    <v-list-item prepend-icon="mdi-format-list-bulleted" title="Inventaire" :active="route().current('materiel.index')"></v-list-item>
                </Link>
                <Link :href="route('reception.index')">
                    <v-list-item prepend-icon="mdi-file-certificate" title="Contrats" :active="route().current('reception.index')"></v-list-item>
                </Link>
            </v-list>
        </v-navigation-drawer>

        <v-main class="bg-teal-lighten-5">
            <header v-if="$slots.header" class="bg-white border-b mb-6">
                <v-container class="py-6" fluid>
                    <h2 class="text-h4 font-weight-black text-teal-darken-4">
                        <slot name="header" />
                    </h2>
                </v-container>
            </header>

            <v-container fluid class="page-container px-4 px-md-8">
                <slot />
            </v-container>
        </v-main>

        <v-snackbar v-model="snackbar.show" :color="snackbar.color" location="top right" elevation="12" rounded="lg" class="mt-4">
            <div class="d-flex align-center">
                <v-icon :icon="snackbar.icon" class="mr-3" size="large"></v-icon>
                <span class="font-weight-bold">{{ snackbar.message }}</span>
            </div>
            <template v-slot:actions>
                <v-btn icon="mdi-close" variant="text" @click="snackbar.show = false"></v-btn>
            </template>
        </v-snackbar>
    </v-app>
</template>

<style scoped>
    .cursor-pointer { cursor: pointer !important; }

    .custom-nav-btn {
        font-size: 0.875rem !important;
        font-weight: 600 !important;
        color: #455a64 !important;
    }

    .page-container {
        animation: fadeInSlide 0.4s ease-out;
    }

    @keyframes fadeInSlide {
        from { opacity: 0; transform: translateY(10px); }
        to { opacity: 1; transform: translateY(0); }
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

    :deep(a) { text-decoration: none !important; color: inherit; }
</style>