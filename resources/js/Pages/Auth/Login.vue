<script setup>
import { ref } from "vue";
import { Head, Link, useForm } from "@inertiajs/vue3";

defineProps({
    canResetPassword: { type: Boolean, default: false },
    status: { type: String, default: "" },
});

const form = useForm({
    email: "",
    password: "",
    remember: false,
});

const showPassword = ref(false);

const submit = () => {
    form.post(route("login"), {
        onFinish: () => form.reset("password"),
    });
};
</script>

<template>
    <v-container fluid class="fill-height bg-teal-lighten-5">
        <Head title="Connexion" />

        <v-row align="center" justify="center" no-gutters>
            <v-col cols="12" sm="8" md="4" lg="3" class="pa-4">
                <div class="text-center mb-6">
                    <v-avatar
                        color="teal-darken-2"
                        size="72"
                        class="mb-4 elevation-3"
                    >
                        <v-icon
                            icon="mdi-shield-lock"
                            size="40"
                            color="white"
                        />
                    </v-avatar>
                    <h1 class="text-h4 font-weight-black text-teal-darken-4">
                        Gestion TTI
                    </h1>
                    <p class="text-body-1 text-teal-darken-2">
                        Authentification sécurisée
                    </p>
                </div>

                <v-card
                    elevation="8"
                    class="pa-6 rounded-xl border-t-lg border-teal-darken-2"
                >
                    <v-alert
                        v-if="status"
                        type="success"
                        variant="tonal"
                        class="mb-6 rounded-lg"
                        density="compact"
                        closable
                    >
                        {{ status }}
                    </v-alert>

                    <v-form @submit.prevent="submit">
                        <v-text-field
                            v-model="form.email"
                            label="Adresse Email"
                            prepend-inner-icon="mdi-email-outline"
                            type="email"
                            variant="outlined"
                            density="comfortable"
                            color="teal-darken-2"
                            :error-messages="form.errors.email"
                            :disabled="form.processing"
                            class="mb-2"
                        />

                        <v-text-field
                            v-model="form.password"
                            label="Mot de passe"
                            prepend-inner-icon="mdi-lock-outline"
                            :type="showPassword ? 'text' : 'password'"
                            :append-inner-icon="
                                showPassword ? 'mdi-eye-off' : 'mdi-eye'
                            "
                            variant="outlined"
                            density="comfortable"
                            color="teal-darken-2"
                            :error-messages="form.errors.password"
                            :disabled="form.processing"
                            @click:append-inner="showPassword = !showPassword"
                            class="mb-2"
                        />

                        <div
                            class="d-flex align-center justify-space-between mb-4"
                        >
                            <v-checkbox
                                v-model="form.remember"
                                label="Rester connecté"
                                hide-details
                                color="teal-darken-2"
                                density="compact"
                                class="ms-n2"
                            />

                            <Link
                                v-if="canResetPassword"
                                :href="route('password.request')"
                                class="text-caption text-teal-darken-3 text-decoration-none font-weight-bold"
                            >
                                Oublié ?
                            </Link>
                        </div>

                        <v-btn
                            type="submit"
                            color="teal-darken-2"
                            block
                            size="large"
                            :loading="form.processing"
                            :disabled="form.processing"
                            class="rounded-lg text-none font-weight-bold elevation-2"
                        >
                            Se connecter
                        </v-btn>
                    </v-form>

                    <v-divider class="my-6">
                        <span class="text-caption text-disabled px-2">OU</span>
                    </v-divider>

                    <div class="text-center">
                        <p class="text-body-2 text-grey-darken-1">
                            Nouveau sur la plateforme ?
                            <Link
                                :href="route('register')"
                                class="text-teal-darken-2 text-decoration-none font-weight-black ms-1"
                            >
                                Créer un compte
                            </Link>
                        </p>
                    </div>
                </v-card>

                <p class="text-center text-caption text-teal-darken-1 mt-8">
                    &copy; {{ new Date().getFullYear() }} — Portail Technique
                    TTI
                </p>
            </v-col>
        </v-row>
    </v-container>
</template>

<style scoped>
/* Suppression de l'effet Hover Transform pour économiser du GPU sur Acer */
.v-card {
    border-top-width: 6px !important;
}

/* Animation d'entrée très légère */
.v-container {
    animation: fadeIn 0.4s ease-in;
}

@keyframes fadeIn {
    from {
        opacity: 0;
    }
    to {
        opacity: 1;
    }
}
</style>
