<script setup>
    import { useForm, Head, Link } from '@inertiajs/vue3';
    import { ref } from 'vue';

    defineProps({ canResetPassword: Boolean, status: String });

    const form = useForm({ email: '', password: '', remember: false });
    const showPassword = ref(false);

    const submit = () => form.post(route('login'), { onFinish: () => form.reset('password') });
</script>

<template>
    <v-app>
        <v-container fluid class="pa-0 fill-height">
            <v-row no-gutters class="fill-height">
                <!-- Colonne gauche avec logo DTTIA (fixe) - COULEUR TEAL comme register -->
                <v-col cols="12" md="6" class="d-none d-md-flex bg-teal-darken-4 align-center justify-center" style="height: 100vh; position: sticky; top: 0;">
                    <div class="text-center pa-10">
                        <v-img src="/images/LOGOdttia.jpeg" width="220" class="mb-8 rounded-circle bg-white pa-4 elevation-10 mx-auto"></v-img>
                        <h1 class="text-h2 font-weight-black text-white">DTTIA</h1>
                        <p class="text-h6 text-white opacity-80 mt-4">Direction des Transmissions et Informatique</p>
                        <p class="text-body-1 text-white opacity-70 mt-2">Gestion du Magasin</p>
                    </div>
                </v-col>

                <!-- Colonne droite avec formulaire (fixe, scroll dans le formulaire) -->
                <v-col cols="12" md="6" class="bg-grey-lighten-4 d-flex align-center justify-center" style="height: 100vh; position: sticky; top: 0;">
                    <v-card elevation="0" rounded="xl" class="pa-6 pa-md-10 w-100 bg-white" style="max-width: 450px; max-height: 90vh; overflow-y: auto; border: 1px solid rgba(0, 0, 0, 0.08);">
                        <div class="text-center mb-6">
                            <!-- Logo visible sur mobile uniquement -->
                            <v-avatar color="teal-darken-2" size="64" class="mb-4 d-md-none elevation-3">
                                <v-icon icon="mdi-shield-lock" size="36" color="white" />
                            </v-avatar>

                            <h2 class="text-h4 font-weight-bold mb-2 text-teal-darken-4">Connexion</h2>
                            <p class="text-grey mb-8">Connectez-vous pour accéder à vos archives</p>
                        </div>

                        <v-alert v-if="status" type="success" variant="tonal" class="mb-4" density="compact">{{ status }}</v-alert>

                        <v-form @submit.prevent="submit">
                            <v-text-field v-model="form.email" label="Email" variant="outlined" :error-messages="form.errors.email" prepend-inner-icon="mdi-email-outline" color="teal-darken-2" class="mb-2" />

                            <v-text-field v-model="form.password" label="Mot de passe" :type="showPassword ? 'text' : 'password'" variant="outlined" :error-messages="form.errors.password" prepend-inner-icon="mdi-lock-outline" :append-inner-icon="showPassword ? 'mdi-eye-off' : 'mdi-eye'" color="teal-darken-2" class="mb-6" @click:append-inner="showPassword = !showPassword" />

                            <div class="d-flex justify-space-between align-center mb-6">
                                <v-checkbox v-model="form.remember" label="Rester connecté" density="compact" color="teal-darken-2" hide-details />
                                <Link v-if="canResetPassword" :href="route('password.request')" class="text-teal-darken-2 text-decoration-none">
                                    Oublié ?
                                </Link>
                            </div>

                            <v-btn type="submit" color="teal-darken-2" block size="x-large" :loading="form.processing" class="rounded-lg font-weight-bold">
                                SE CONNECTER
                            </v-btn>

                            <div class="text-center mt-6">
                                <span class="text-grey">Pas encore de compte ?</span>
                                <Link :href="route('register')" class="text-teal-darken-2 text-decoration-none font-weight-bold ms-1">
                                    Créer un compte
                                </Link>
                            </div>
                        </v-form>
                    </v-card>
                </v-col>
            </v-row>
        </v-container>
    </v-app>
</template>

<style scoped>
    .fill-height {
        height: 100vh;
    }

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

    a {
        color: inherit;
        text-decoration: none;
    }

    .v-card::-webkit-scrollbar {
        width: 6px;
    }

    .v-card::-webkit-scrollbar-track {
        background: #f1f1f1;
        border-radius: 10px;
    }

    .v-card::-webkit-scrollbar-thumb {
        background: #c0c0c0;
        border-radius: 10px;
    }

    .v-card::-webkit-scrollbar-thumb:hover {
        background: #a0a0a0;
    }
</style>
