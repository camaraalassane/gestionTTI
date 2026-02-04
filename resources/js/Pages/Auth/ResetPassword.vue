<script setup>
import { ref } from "vue";
import { Head, useForm, Link } from "@inertiajs/vue3";

const props = defineProps({
    email: { type: String, required: true },
    token: { type: String, required: true },
});

const form = useForm({
    token: props.token,
    email: props.email,
    password: "",
    password_confirmation: "",
});

// État pour afficher/masquer les mots de passe
const showPw = ref(false);
const showConfirmPw = ref(false);

const submit = () => {
    form.post(route("password.store"), {
        onFinish: () => form.reset("password", "password_confirmation"),
    });
};
</script>

<template>
    <v-container fluid class="fill-height login-bg">
        <Head title="Réinitialiser le mot de passe" />

        <v-row align="center" justify="center" no-gutters>
            <v-col cols="12" sm="8" md="5" lg="4" class="pa-4">
                <div class="text-center mb-8">
                    <v-avatar color="white" size="80" class="mb-4 elevation-10">
                        <v-icon
                            icon="mdi-shield-lock-outline"
                            size="48"
                            color="teal-darken-3"
                        />
                    </v-avatar>
                    <h1
                        class="text-h3 font-weight-black text-white text-uppercase tracking-wider"
                    >
                        TTI <span class="text-teal-lighten-3">Stock</span>
                    </h1>
                    <div class="d-flex align-center justify-center mt-2">
                        <v-divider
                            class="flex-grow-0 w-25 border-opacity-50"
                            color="white"
                        ></v-divider>
                        <span
                            class="text-overline text-white px-3 tracking-widest"
                            >SÉCURITÉ</span
                        >
                        <v-divider
                            class="flex-grow-0 w-25 border-opacity-50"
                            color="white"
                        ></v-divider>
                    </div>
                </div>

                <v-card
                    elevation="24"
                    class="pa-8 rounded-xl bg-white glass-card"
                >
                    <div class="mb-6 text-center">
                        <h2 class="text-h5 font-weight-bold text-teal-darken-4">
                            Nouveau mot de passe
                        </h2>
                        <p class="text-caption text-teal-darken-2">
                            Finalisez la sécurisation de votre accès
                        </p>
                    </div>

                    <v-form @submit.prevent="submit">
                        <div
                            class="text-subtitle-2 text-teal-darken-4 mb-1 ms-1"
                        >
                            Compte concerné
                        </div>
                        <v-text-field
                            v-model="form.email"
                            prepend-inner-icon="mdi-email-outline"
                            type="email"
                            variant="outlined"
                            density="comfortable"
                            color="teal-darken-2"
                            base-color="teal-lighten-3"
                            :error-messages="form.errors.email"
                            readonly
                            rounded="lg"
                            class="mb-4"
                        />

                        <div
                            class="text-subtitle-2 text-teal-darken-4 mb-1 ms-1"
                        >
                            Nouveau mot de passe
                        </div>
                        <v-text-field
                            v-model="form.password"
                            placeholder="••••••••"
                            prepend-inner-icon="mdi-lock-outline"
                            :type="showPw ? 'text' : 'password'"
                            :append-inner-icon="
                                showPw ? 'mdi-eye-off' : 'mdi-eye'
                            "
                            variant="outlined"
                            density="comfortable"
                            color="teal-darken-2"
                            base-color="teal-lighten-3"
                            :error-messages="form.errors.password"
                            @click:append-inner="showPw = !showPw"
                            autofocus
                            rounded="lg"
                            class="mb-3"
                        />

                        <div
                            class="text-subtitle-2 text-teal-darken-4 mb-1 ms-1"
                        >
                            Confirmer le mot de passe
                        </div>
                        <v-text-field
                            v-model="form.password_confirmation"
                            placeholder="••••••••"
                            prepend-inner-icon="mdi-lock-check-outline"
                            :type="showConfirmPw ? 'text' : 'password'"
                            :append-inner-icon="
                                showConfirmPw ? 'mdi-eye-off' : 'mdi-eye'
                            "
                            variant="outlined"
                            density="comfortable"
                            color="teal-darken-2"
                            base-color="teal-lighten-3"
                            :error-messages="form.errors.password_confirmation"
                            @click:append-inner="showConfirmPw = !showConfirmPw"
                            rounded="lg"
                            class="mb-6"
                        />

                        <v-btn
                            type="submit"
                            color="teal-darken-3"
                            block
                            size="x-large"
                            :loading="form.processing"
                            :disabled="form.processing"
                            class="rounded-lg text-none font-weight-bold elevation-4 login-btn"
                        >
                            Enregistrer le nouveau mot de passe
                            <v-icon end icon="mdi-update" />
                        </v-btn>
                    </v-form>

                    <v-divider class="my-8"></v-divider>

                    <div class="text-center">
                        <Link
                            :href="route('login')"
                            class="text-decoration-none"
                        >
                            <v-btn
                                variant="text"
                                color="teal-darken-2"
                                class="text-none font-weight-bold"
                            >
                                <v-icon start icon="mdi-arrow-left" />
                                Annuler et retourner à la connexion
                            </v-btn>
                        </Link>
                    </div>
                </v-card>

                <div class="text-center mt-10">
                    <p class="text-caption text-white opacity-70">
                        &copy; {{ new Date().getFullYear() }} — TTI Engineering
                        <br />
                        Système de gestion d'inventaire v2.1
                    </p>
                </div>
            </v-col>
        </v-row>
    </v-container>
</template>

<style scoped>
.login-bg {
    background: linear-gradient(135deg, #004d40 0%, #00796b 50%, #4db6ac 100%);
    background-size: cover;
}

.glass-card {
    border: 1px solid rgba(255, 255, 255, 0.2) !important;
}

.tracking-wider {
    letter-spacing: 0.15rem !important;
}
.tracking-widest {
    letter-spacing: 0.3rem !important;
}

.login-btn {
    transition: all 0.2s ease;
}
.login-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 15px rgba(0, 77, 64, 0.4) !important;
}

.v-container {
    animation: fadeIn 0.6s ease-out;
}
@keyframes fadeIn {
    from {
        opacity: 0;
        transform: translateY(10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}
</style>
