<script setup>
import { computed } from "vue";
import { Head, Link, useForm } from "@inertiajs/vue3";

const props = defineProps({
    status: {
        type: String,
    },
});

const form = useForm({});

const submit = () => {
    form.post(route("verification.send"));
};

const verificationLinkSent = computed(
    () => props.status === "verification-link-sent",
);
</script>

<template>
    <v-container fluid class="fill-height login-bg">
        <Head title="Vérification de l'e-mail" />

        <v-row align="center" justify="center" no-gutters>
            <v-col cols="12" sm="8" md="5" lg="4" class="pa-4">
                <div class="text-center mb-8">
                    <v-avatar color="white" size="80" class="mb-4 elevation-10">
                        <v-icon
                            icon="mdi-email-check-outline"
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
                            >VÉRIFICATION</span
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
                    <div
                        class="mb-6 text-body-1 text-teal-darken-4 text-center"
                    >
                        Merci pour votre inscription ! Avant de commencer,
                        pourriez-vous vérifier votre adresse e-mail en cliquant
                        sur le lien que nous venons de vous envoyer ?
                    </div>

                    <v-fade-transition>
                        <v-alert
                            v-if="verificationLinkSent"
                            type="success"
                            variant="tonal"
                            class="mb-6 rounded-lg"
                            density="comfortable"
                        >
                            Un nouveau lien de vérification a été envoyé à
                            l'adresse e-mail fournie lors de l'inscription.
                        </v-alert>
                    </v-fade-transition>

                    <v-form @submit.prevent="submit">
                        <v-btn
                            type="submit"
                            color="teal-darken-3"
                            block
                            size="x-large"
                            :loading="form.processing"
                            :disabled="form.processing"
                            class="rounded-lg text-none font-weight-bold elevation-4 login-btn mb-4"
                        >
                            Renvoyer l'e-mail de vérification
                            <v-icon end icon="mdi-send-outline" />
                        </v-btn>

                        <div class="d-flex justify-center mt-6">
                            <Link
                                :href="route('logout')"
                                method="post"
                                as="button"
                                class="text-caption text-teal-darken-4 text-decoration-none font-weight-bold hover-underline"
                            >
                                Se déconnecter
                            </Link>
                        </div>
                    </v-form>
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

.hover-underline:hover {
    text-decoration: underline !important;
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
