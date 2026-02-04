<script setup>
import { ref, computed, watch } from "vue";
import { Link, usePage } from "@inertiajs/vue3";

const drawer = ref(false);
const page = usePage();

/**
 * GESTION DES NOTIFICATIONS (Flash messages)
 */
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

/**
 * RÉCUPÉRATION UTILISATEUR & INITIALES (Données conservées)
 */
const user = computed(() => page.props.auth?.user || {});

const initials = computed(() => {
    const name = user.value?.name;
    if (!name) return "??";
    const parts = name.split(" ");
    if (parts.length > 1) return (parts[0][0] + parts[1][0]).toUpperCase();
    return name.substring(0, 2).toUpperCase();
});
</script>

<template>
    <v-app>
        <v-app-bar elevation="1" color="white" border="b">
            <v-app-bar-nav-icon
                class="hidden-md-and-up"
                @click="drawer = !drawer"
            ></v-app-bar-nav-icon>

            <v-container class="fill-height d-flex align-center" fluid>
                <div class="mr-4">
                    <Link
                        :href="route('selection')"
                        class="d-flex text-decoration-none"
                    >
                        <v-avatar
                            color="teal-darken-2"
                            size="40"
                            class="elevation-2 cursor-pointer"
                        >
                            <v-icon color="white" icon="mdi-file-send"></v-icon>
                        </v-avatar>
                    </Link>
                </div>

                <div
                    class="hidden-sm-and-down d-md-flex align-center"
                    style="gap: 8px"
                >
                    <Link :href="route('selection')">
                        <v-btn
                            :active="route().current('selection')"
                            variant="text"
                            prepend-icon="mdi-home"
                            class="text-none rounded-lg"
                        >
                            Materiels
                        </v-btn>
                    </Link>

                    <Link :href="route('demandes.index')">
                        <v-btn
                            :active="route().current('demandes.index')"
                            variant="text"
                            prepend-icon="mdi-clipboard-text-clock-outline"
                            class="text-none rounded-lg"
                        >
                            Liste
                        </v-btn>
                    </Link>

                    <Link :href="route('demandes.gestion_service')">
                        <v-btn
                            :active="
                                route().current('demandes.gestion_service')
                            "
                            variant="text"
                            prepend-icon="mdi-office-building-cog"
                            class="text-none rounded-lg"
                        >
                            Par Service
                        </v-btn>
                    </Link>

                    <Link :href="route('demandes.historique')">
                        <v-btn
                            :active="route().current('demandes.historique')"
                            variant="text"
                            prepend-icon="mdi-history"
                            class="text-none rounded-lg"
                        >
                            Historique
                        </v-btn>
                    </Link>

                    <Link :href="route('demandes.create')">
                        <v-btn
                            variant="flat"
                            color="teal-darken-1"
                            prepend-icon="mdi-send-plus"
                            class="ml-4 text-none rounded-pill elevation-2 px-6"
                        >
                            Nouvelle Demande
                        </v-btn>
                    </Link>
                </div>

                <v-spacer></v-spacer>

                <v-menu
                    min-width="240px"
                    rounded="xl"
                    transition="slide-y-transition"
                >
                    <template v-slot:activator="{ props }">
                        <v-btn icon v-bind="props" class="ml-2">
                            <v-avatar
                                color="teal-lighten-4"
                                size="38"
                                class="border"
                            >
                                <span
                                    class="text-teal-darken-3 font-weight-bold text-caption"
                                    >{{ initials }}</span
                                >
                            </v-avatar>
                        </v-btn>
                    </template>

                    <v-list class="pa-2" nav elevation="10">
                        <v-list-item class="mb-2">
                            <template v-slot:prepend>
                                <v-avatar color="teal-lighten-4">{{
                                    initials
                                }}</v-avatar>
                            </template>
                            <v-list-item-title class="font-weight-bold">{{
                                user?.name
                            }}</v-list-item-title>
                            <v-list-item-subtitle class="text-caption"
                                >Espace Demandes</v-list-item-subtitle
                            >
                        </v-list-item>

                        <v-divider class="mb-2"></v-divider>

                        <Link
                            :href="route('profile.edit')"
                            class="text-decoration-none"
                        >
                            <v-list-item
                                prepend-icon="mdi-account-circle-outline"
                                title="Mon Profil"
                                class="rounded-lg"
                            ></v-list-item>
                        </Link>

                        <Link
                            :href="route('logout')"
                            method="post"
                            as="div"
                            class="cursor-pointer mt-1"
                        >
                            <v-list-item class="rounded-lg pa-0">
                                <div
                                    class="d-flex align-center text-red-darken-2 font-weight-bold w-100 px-4 py-2"
                                >
                                    <v-icon
                                        icon="mdi-logout"
                                        class="mr-3"
                                    ></v-icon>
                                    <span>Déconnexion</span>
                                </div>
                            </v-list-item>
                        </Link>
                    </v-list>
                </v-menu>
            </v-container>
        </v-app-bar>

        <v-navigation-drawer v-model="drawer" temporary width="280">
            <v-list nav>
                <Link :href="route('selection')">
                    <v-list-item
                        prepend-icon="mdi-arrow-left"
                        title="Retour Materiels"
                        class="text-teal-darken-3 font-weight-bold"
                    ></v-list-item>
                </Link>
                <v-divider class="my-2"></v-divider>

                <Link :href="route('demandes.index')">
                    <v-list-item
                        prepend-icon="mdi-clipboard-list"
                        title="Liste Demandes"
                        :active="route().current('demandes.index')"
                    ></v-list-item>
                </Link>

                <Link :href="route('demandes.gestion_service')">
                    <v-list-item
                        prepend-icon="mdi-office-building-cog"
                        title="Par Service"
                        :active="route().current('demandes.gestion_service')"
                    ></v-list-item>
                </Link>

                <Link :href="route('demandes.historique')">
                    <v-list-item
                        prepend-icon="mdi-history"
                        title="Historique"
                        :active="route().current('demandes.historique')"
                    ></v-list-item>
                </Link>

                <v-divider class="my-2"></v-divider>

                <Link :href="route('demandes.create')">
                    <v-list-item
                        prepend-icon="mdi-send-plus"
                        title="Créer Demande"
                        color="teal-darken-1"
                        variant="tonal"
                    ></v-list-item>
                </Link>
            </v-list>
        </v-navigation-drawer>

        <v-main class="bg-teal-lighten-5">
            <header
                v-if="$slots.header"
                class="bg-white border-b-lg border-teal-darken-1 elevation-2 mb-6"
            >
                <v-container class="py-6" fluid>
                    <h1
                        class="text-h4 font-weight-black text-teal-darken-4 px-4"
                    >
                        <slot name="header" />
                    </h1>
                </v-container>
            </header>

            <v-container fluid class="animate-fade-in px-4 px-md-10 pb-10">
                <slot />
            </v-container>
        </v-main>

        <v-snackbar
            v-model="snackbar.show"
            :color="snackbar.color"
            location="top right"
            elevation="24"
            rounded="pill"
            :timeout="4500"
        >
            <div class="d-flex align-center">
                <v-icon :icon="snackbar.icon" class="mr-3"></v-icon>
                <span class="font-weight-bold">{{ snackbar.message }}</span>
            </div>
            <template v-slot:actions>
                <v-btn
                    icon="mdi-close"
                    variant="text"
                    size="small"
                    @click="snackbar.show = false"
                ></v-btn>
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
</style>
