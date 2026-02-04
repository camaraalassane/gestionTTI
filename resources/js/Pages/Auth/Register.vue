<script setup>
import { ref } from "vue";
import { Head, Link, useForm } from "@inertiajs/vue3";

const form = useForm({
    name: "",
    email: "",
    password: "",
    password_confirmation: "",
});

// Gestion de la visibilité des mots de passe
const showPw = ref(false);
const showConfirmPw = ref(false);

const submit = () => {
    form.post(route("register"), {
        onFinish: () => form.reset("password", "password_confirmation"),
    });
};
</script>

<template>
    <v-container fluid class="fill-height bg-grey-lighten-4">
        <Head title="Inscription" />

        <v-row align="center" justify="center">
            <v-col cols="12" sm="8" md="5" lg="4">
                <!-- Header Inscription -->
                <div class="text-center mb-6">
                    <v-icon
                        icon="mdi-account-plus"
                        size="64"
                        color="primary"
                        class="mb-2"
                    />
                    <h1 class="text-h4 font-weight-bold">Créer un compte</h1>
                    <p class="text-subtitle-1 text-medium-emphasis">
                        Rejoignez-nous en quelques clics
                    </p>
                </div>

                <v-card elevation="10" class="pa-6 rounded-xl">
                    <v-form @submit.prevent="submit">
                        <!-- Nom complet -->
                        <v-text-field
                            v-model="form.name"
                            label="Nom complet"
                            prepend-inner-icon="mdi-account-outline"
                            variant="outlined"
                            :error-messages="form.errors.name"
                            required
                            autofocus
                            class="mb-2"
                        />

                        <!-- Email -->
                        <v-text-field
                            v-model="form.email"
                            label="Adresse Email"
                            prepend-inner-icon="mdi-email-outline"
                            type="email"
                            variant="outlined"
                            :error-messages="form.errors.email"
                            required
                            class="mb-2"
                        />

                        <!-- Mot de passe -->
                        <v-text-field
                            v-model="form.password"
                            label="Mot de passe"
                            prepend-inner-icon="mdi-lock-outline"
                            :type="showPw ? 'text' : 'password'"
                            :append-inner-icon="
                                showPw ? 'mdi-eye-off' : 'mdi-eye'
                            "
                            variant="outlined"
                            :error-messages="form.errors.password"
                            @click:append-inner="showPw = !showPw"
                            required
                            class="mb-2"
                        />

                        <!-- Confirmation Mot de passe -->
                        <v-text-field
                            v-model="form.password_confirmation"
                            label="Confirmer le mot de passe"
                            prepend-inner-icon="mdi-lock-check-outline"
                            :type="showConfirmPw ? 'text' : 'password'"
                            :append-inner-icon="
                                showConfirmPw ? 'mdi-eye-off' : 'mdi-eye'
                            "
                            variant="outlined"
                            :error-messages="form.errors.password_confirmation"
                            @click:append-inner="showConfirmPw = !showConfirmPw"
                            required
                            class="mb-4"
                        />

                        <!-- Bouton d'action -->
                        <v-btn
                            type="submit"
                            color="primary"
                            block
                            size="large"
                            :loading="form.processing"
                            class="font-weight-bold text-uppercase"
                            elevation="4"
                        >
                            S'inscrire
                        </v-btn>

                        <v-divider class="my-6"></v-divider>

                        <!-- Lien vers Connexion -->
                        <div class="text-center">
                            <span class="text-body-2 text-grey-darken-1"
                                >Déjà inscrit ?</span
                            >
                            <v-btn
                                variant="text"
                                color="primary"
                                :href="route('login')"
                                class="text-none font-weight-bold"
                                tag="a"
                            >
                                <Link
                                    :href="route('login')"
                                    class="text-decoration-none color-inherit"
                                >
                                    Se connecter
                                </Link>
                            </v-btn>
                        </div>
                    </v-form>
                </v-card>

                <!-- Footer optionnel -->
                <div class="text-center mt-6 text-caption text-grey">
                    &copy; 2026 Votre Application. Tous droits réservés.
                </div>
            </v-col>
        </v-row>
    </v-container>
</template>

<style scoped>
/* Supprime le style par défaut des liens Inertia pour garder le style Vuetify */
a {
    color: inherit;
    text-decoration: none;
}

.v-card {
    border-top: 5px solid rgb(var(--v-theme-primary));
}
</style>
