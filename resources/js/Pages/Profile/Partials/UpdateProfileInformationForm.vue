<script setup>
import { useForm, usePage, Link } from "@inertiajs/vue3";

defineProps({
    mustVerifyEmail: {
        type: Boolean,
    },
    status: {
        type: String,
    },
});

const user = usePage().props.auth.user;

const form = useForm({
    name: user.name,
    email: user.email,
});
</script>

<template>
    <section>
        <header class="mb-6">
            <h2 class="text-h6 font-weight-bold text-teal-darken-4">
                Informations du profil
            </h2>
            <p class="text-body-2 text-teal-darken-2">
                Mettez à jour les informations de votre compte et votre adresse
                e-mail.
            </p>
        </header>

        <v-form @submit.prevent="form.patch(route('profile.update'))">
            <div class="text-subtitle-2 text-teal-darken-4 mb-1 ms-1">
                Nom complet
            </div>
            <v-text-field
                v-model="form.name"
                placeholder="Votre nom"
                variant="outlined"
                density="comfortable"
                color="teal-darken-2"
                base-color="teal-lighten-3"
                prepend-inner-icon="mdi-account-outline"
                :error-messages="form.errors.name"
                required
                rounded="lg"
                class="mb-3"
            />

            <div class="text-subtitle-2 text-teal-darken-4 mb-1 ms-1">
                Adresse Email
            </div>
            <v-text-field
                v-model="form.email"
                placeholder="nom@entreprise.com"
                type="email"
                variant="outlined"
                density="comfortable"
                color="teal-darken-2"
                base-color="teal-lighten-3"
                prepend-inner-icon="mdi-email-outline"
                :error-messages="form.errors.email"
                required
                rounded="lg"
                class="mb-2"
            />

            <div
                v-if="mustVerifyEmail && user.email_verified_at === null"
                class="mb-4"
            >
                <v-alert
                    type="warning"
                    variant="tonal"
                    density="compact"
                    class="text-caption rounded-lg border-teal-lighten-4"
                    color="teal-darken-4"
                >
                    Votre adresse e-mail n'est pas vérifiée.
                    <Link
                        :href="route('verification.send')"
                        method="post"
                        as="button"
                        class="text-decoration-underline font-weight-bold ml-1 text-teal-darken-4"
                    >
                        Cliquez ici pour renvoyer l'e-mail de vérification.
                    </Link>
                </v-alert>

                <v-alert
                    v-if="status === 'verification-link-sent'"
                    type="success"
                    variant="tonal"
                    density="compact"
                    class="mt-2 text-caption rounded-lg"
                    color="teal-darken-2"
                >
                    Un nouveau lien de vérification a été envoyé.
                </v-alert>
            </div>

            <div class="d-flex align-center mt-6">
                <v-btn
                    type="submit"
                    color="teal-darken-3"
                    elevation="4"
                    :loading="form.processing"
                    :disabled="form.processing"
                    class="text-none px-8 rounded-lg font-weight-bold save-btn"
                    size="large"
                >
                    Enregistrer les modifications
                    <v-icon end icon="mdi-content-save-check-outline" />
                </v-btn>

                <v-fade-transition>
                    <span
                        v-if="form.recentlySuccessful"
                        class="ml-4 text-teal-darken-2 text-body-2 font-weight-bold d-flex align-center"
                    >
                        <v-icon
                            icon="mdi-check-circle-outline"
                            start
                            color="teal-darken-2"
                        ></v-icon>
                        Enregistré avec succès
                    </span>
                </v-fade-transition>
            </div>
        </v-form>
    </section>
</template>

<style scoped>
.save-btn {
    transition: all 0.3s ease;
}

.save-btn:hover:not(:disabled) {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0, 77, 64, 0.3) !important;
}

/* Style spécifique pour les messages d'erreur Vuetify pour rester dans le thème */
:deep(.v-messages__message) {
    font-weight: 500;
}
</style>
