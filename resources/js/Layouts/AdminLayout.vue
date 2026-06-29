<script setup>
import { ref, computed, watch } from "vue";
import { usePage, router } from "@inertiajs/vue3";

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
    return user.value?.name ? user.value.name.substring(0, 2).toUpperCase() : "AD";
});

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
    <v-app id="inspire">
        <v-app-bar elevation="1" color="white">
            <v-progress-linear :active="isNavigating" indeterminate absolute bottom color="primary"
                height="3"></v-progress-linear>
            <v-app-bar-nav-icon class="hidden-md-and-up" @click="drawer = !drawer"></v-app-bar-nav-icon>

            <v-container class="fill-height d-flex align-center" fluid>
                <v-btn @click="navigateTo('admin.users.index')" class="me-4" variant="text" min-width="auto">
                    <v-avatar color="primary" size="38" class="elevation-2 cursor-pointer">
                        <v-icon color="white" icon="mdi-shield-crown"></v-icon>
                    </v-avatar>
                </v-btn>

                <div class="text-subtitle-1 font-weight-black text-grey-darken-3 me-6 hidden-sm-and-down">ADMINISTRATION
                </div>

                <div class="hidden-sm-and-down d-flex" style="gap: 8px">
                    <v-btn @click="navigateTo('admin.stats.global')" :active="route().current('admin.stats.global')"
                        prepend-icon="mdi-chart-box-outline" variant="text" class="text-none rounded-lg">
                        Suivi Global
                    </v-btn>
                    <v-btn @click="navigateTo('admin.users.index')" :active="route().current('admin.users.index')"
                        prepend-icon="mdi-account-group" variant="text" class="text-none rounded-lg">
                        Utilisateurs
                    </v-btn>
                    <v-btn @click="navigateTo('admin.trash.index')" :active="route().current('admin.trash.index')"
                        prepend-icon="mdi-delete-clock" variant="text" class="text-none rounded-lg">
                        Corbeille
                    </v-btn>
                </div>

                <v-spacer></v-spacer>

                <v-btn @click="navigateTo('selection')" variant="tonal" color="primary"
                    prepend-icon="mdi-logout-variant" class="text-none rounded-lg me-3 hidden-xs">
                    Quitter Admin
                </v-btn>

                <v-menu min-width="220px" rounded="xl" transition="slide-y-transition">
                    <template v-slot:activator="{ props }">
                        <v-btn icon v-bind="props">
                            <v-avatar color="primary-lighten-4" size="36" class="border">
                                <span class="text-primary font-weight-bold text-caption">{{ initials }}</span>
                            </v-avatar>
                        </v-btn>
                    </template>

                    <v-list class="pa-2" nav>
                        <v-list-item :title="user?.name" :subtitle="user?.email"
                            prepend-icon="mdi-account-circle"></v-list-item>
                        <v-divider class="my-2"></v-divider>
                        <v-list-item @click="navigateTo('profile.edit')" prepend-icon="mdi-cog-outline"
                            title="Mon Profil"></v-list-item>
                        <v-divider class="my-2"></v-divider>
                        <!-- ✅ Déconnexion avec POST -->
                        <v-list-item @click="logout" prepend-icon="mdi-power" title="Déconnexion"
                            class="text-error"></v-list-item>
                    </v-list>
                </v-menu>
            </v-container>
        </v-app-bar>

        <v-navigation-drawer v-model="drawer" temporary width="280">
            <v-list nav>
                <v-list-subheader class="font-weight-black text-primary">MENU PRINCIPAL</v-list-subheader>
                <v-list-item @click="navigateTo('admin.stats.global')" prepend-icon="mdi-chart-box-outline"
                    title="Suivi Global" :active="route().current('admin.stats.global')"></v-list-item>
                <v-list-item @click="navigateTo('admin.users.index')" prepend-icon="mdi-account-group"
                    title="Utilisateurs" :active="route().current('admin.users.index')"></v-list-item>
                <v-list-item @click="navigateTo('admin.trash.index')" prepend-icon="mdi-delete-clock" title="Corbeille"
                    :active="route().current('admin.trash.index')"></v-list-item>
                <v-divider class="my-2"></v-divider>
                <!-- ✅ Déconnexion dans drawer -->
                <v-list-item @click="logout" prepend-icon="mdi-power" title="Déconnexion"
                    class="text-error"></v-list-item>
            </v-list>
        </v-navigation-drawer>

        <v-main class="bg-grey-lighten-4">
            <div v-if="$slots.header" class="bg-white border-b shadow-sm py-4 mb-6">
                <v-container>
                    <h2 class="text-h5 font-weight-black text-grey-darken-4">
                        <slot name="header" />
                    </h2>
                </v-container>
            </div>
            <v-container fluid class="page-transition px-md-8 pb-10">
                <slot />
            </v-container>
        </v-main>

        <v-snackbar v-model="snackbar.show" :color="snackbar.color" location="top right" elevation="10" rounded="pill">
            <v-icon :icon="snackbar.icon" class="me-2"></v-icon>{{ snackbar.message }}
            <template v-slot:actions><v-btn icon="mdi-close" size="small" variant="text"
                    @click="snackbar.show = false"></v-btn></template>
        </v-snackbar>
    </v-app>
</template>

<style scoped>
.page-transition {
    animation: slideUp 0.3s ease-out;
}

@keyframes slideUp {
    from {
        opacity: 0;
        transform: translateY(15px);
    }

    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.cursor-pointer {
    cursor: pointer;
}

:deep(.v-btn--active) {
    background-color: rgba(0, 121, 107, 0.1) !important;
    font-weight: bold !important;
    color: #00796b !important;
}
</style>
