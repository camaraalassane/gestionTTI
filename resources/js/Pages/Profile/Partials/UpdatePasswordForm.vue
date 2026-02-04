<script setup>
import { useForm } from "@inertiajs/vue3";
import { ref } from "vue";

const passwordInput = ref(null);
const currentPasswordInput = ref(null);
const showPw1 = ref(false);
const showPw2 = ref(false);
const showPw3 = ref(false);

const form = useForm({
    current_password: "",
    password: "",
    password_confirmation: "",
});

const updatePassword = () => {
    form.put(route("password.update"), {
        preserveScroll: true,
        onSuccess: () => form.reset(),
        onError: () => {
            if (form.errors.password) {
                form.reset("password", "password_confirmation");
                passwordInput.value?.focus();
            }
            if (form.errors.current_password) {
                form.reset("current_password");
                currentPasswordInput.value?.focus();
            }
        },
    });
};
</script>

<template>
    <section>
        <header class="mb-6">
            <h2 class="text-h6 font-weight-bold text-teal-darken-4">
                Mettre à jour le mot de passe
            </h2>
            <p class="text-body-2 text-teal-darken-2">
                Assurez-vous que votre compte utilise un mot de passe long et
                complexe pour rester sécurisé.
            </p>
        </header>

        <v-form @submit.prevent="updatePassword">
            <div class="text-subtitle-2 text-teal-darken-4 mb-1 ms-1">
                Mot de passe actuel
            </div>
            <v-text-field
                v-model="form.current_password"
                ref="currentPasswordInput"
                placeholder="Entrez votre mot de passe actuel"
                :type="showPw1 ? 'text' : 'password'"
                variant="outlined"
                density="comfortable"
                color="teal-darken-2"
                base-color="teal-lighten-3"
                prepend-inner-icon="mdi-lock-outline"
                :append-inner-icon="showPw1 ? 'mdi-eye-off' : 'mdi-eye'"
                @click:append-inner="showPw1 = !showPw1"
                :error-messages="form.errors.current_password"
                rounded="lg"
                class="mb-3"
            />

            <div class="text-subtitle-2 text-teal-darken-4 mb-1 ms-1">
                Nouveau mot de passe
            </div>
            <v-text-field
                v-model="form.password"
                ref="passwordInput"
                placeholder="Nouveau mot de passe"
                :type="showPw2 ? 'text' : 'password'"
                variant="outlined"
                density="comfortable"
                color="teal-darken-2"
                base-color="teal-lighten-3"
                prepend-inner-icon="mdi-lock-reset"
                :append-inner-icon="showPw2 ? 'mdi-eye-off' : 'mdi-eye'"
                @click:append-inner="showPw2 = !showPw2"
                :error-messages="form.errors.password"
                rounded="lg"
                class="mb-3"
            />

            <div class="text-subtitle-2 text-teal-darken-4 mb-1 ms-1">
                Confirmation
            </div>
            <v-text-field
                v-model="form.password_confirmation"
                placeholder="Confirmez le nouveau mot de passe"
                :type="showPw3 ? 'text' : 'password'"
                variant="outlined"
                density="comfortable"
                color="teal-darken-2"
                base-color="teal-lighten-3"
                prepend-inner-icon="mdi-lock-check-outline"
                :append-inner-icon="showPw3 ? 'mdi-eye-off' : 'mdi-eye'"
                @click:append-inner="showPw3 = !showPw3"
                :error-messages="form.errors.password_confirmation"
                rounded="lg"
                class="mb-6"
            />

            <div class="d-flex align-center mt-4">
                <v-btn
                    type="submit"
                    color="teal-darken-3"
                    elevation="4"
                    size="large"
                    :loading="form.processing"
                    :disabled="form.processing"
                    class="text-none px-8 rounded-lg font-weight-bold update-btn"
                >
                    Mettre à jour
                    <v-icon end icon="mdi-shield-check-outline" />
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
                        Mot de passe mis à jour.
                    </span>
                </v-fade-transition>
            </div>
        </v-form>
    </section>
</template>

<style scoped>
.update-btn {
    transition: all 0.3s ease;
}

.update-btn:hover:not(:disabled) {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0, 77, 64, 0.3) !important;
}

:deep(.v-messages__message) {
    font-weight: 500;
}
</style>
