<script setup>
import { computed } from "vue";
import { usePage, Head } from "@inertiajs/vue3";

// Importation des 3 Layouts - Logique conservée
import AdminLayout from "@/Layouts/AdminLayout.vue";
import AuthentDemandeLayout from "@/Layouts/AuthentDemandeLayout.vue";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";

// Importation des formulaires - Logique conservée
import UpdatePasswordForm from "./Partials/UpdatePasswordForm.vue";
import UpdateProfileInformationForm from "./Partials/UpdateProfileInformationForm.vue";

const props = defineProps({
    mustVerifyEmail: Boolean,
    status: String,
});

const page = usePage();

// Logique de détection basée sur tes props partagées - INCHANGÉ
const layout = computed(() => {
    const user = page.props.auth.user;
    if (!user) return AuthentDemandeLayout;
    if (user.role === "admin") return AdminLayout;
    if (user.code_materiel) return AuthenticatedLayout;
    return AuthentDemandeLayout;
});
</script>

<template>
    <Head title="Profil" />

    <component :is="layout">
        <template #header>
            <h2
                class="text-xl font-black text-teal-darken-4 text-uppercase tracking-wider"
            >
                <v-icon
                    icon="mdi-account-circle-outline"
                    start
                    color="teal-darken-3"
                />
                Gestion du Profil
            </h2>
        </template>

        <v-container fluid class="py-8 profile-gradient-bg fill-height">
            <v-row justify="center">
                <v-col cols="12" md="10" lg="8">
                    <v-card
                        class="pa-8 mb-8 rounded-xl elevation-4 glass-card-light"
                    >
                        <div class="d-flex align-center mb-6">
                            <v-icon
                                icon="mdi-card-account-details-outline"
                                size="32"
                                color="teal-darken-3"
                                class="me-4"
                            />
                            <div>
                                <h3
                                    class="text-h5 font-weight-bold text-teal-darken-4"
                                >
                                    Informations personnelles
                                </h3>
                                <p class="text-caption text-grey-darken-1">
                                    Mettez à jour vos informations de compte et
                                    votre adresse e-mail
                                </p>
                            </div>
                        </div>

                        <v-divider
                            class="mb-8 border-opacity-25"
                            color="teal"
                        ></v-divider>

                        <UpdateProfileInformationForm
                            :must-verify-email="mustVerifyEmail"
                            :status="status"
                        />
                    </v-card>

                    <v-card
                        class="pa-8 rounded-xl elevation-4 glass-card-light"
                    >
                        <div class="d-flex align-center mb-6">
                            <v-icon
                                icon="mdi-shield-key-outline"
                                size="32"
                                color="teal-darken-3"
                                class="me-4"
                            />
                            <div>
                                <h3
                                    class="text-h5 font-weight-bold text-teal-darken-4"
                                >
                                    Sécurité du compte
                                </h3>
                                <p class="text-caption text-grey-darken-1">
                                    Assurez-vous que votre compte utilise un mot
                                    de passe long et complexe
                                </p>
                            </div>
                        </div>

                        <v-divider
                            class="mb-8 border-opacity-25"
                            color="teal"
                        ></v-divider>

                        <UpdatePasswordForm />
                    </v-card>
                </v-col>
            </v-row>
        </v-container>
    </component>
</template>

<style scoped>
/* Rappel du thème teal en fond de page */
.profile-gradient-bg {
    background: linear-gradient(180deg, #f0f4f4 0%, #e0e7e7 100%);
    min-height: calc(100vh - 64px);
}

/* Version "light" du glassmorphism pour les pages internes */
.glass-card-light {
    background: rgba(255, 255, 255, 0.9) !important;
    border: 1px solid rgba(0, 121, 107, 0.1) !important;
    backdrop-filter: blur(10px);
}

/* Style spécifique pour les titres de section */
.tracking-wider {
    letter-spacing: 0.1rem !important;
}

/* Animation douce à l'entrée */
.v-container {
    animation: fadeIn 0.5s ease-out;
}

@keyframes fadeIn {
    from {
        opacity: 0;
        transform: translateY(5px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}
</style>
