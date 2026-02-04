<script setup>
import { useForm } from "@inertiajs/vue3";
import { nextTick, ref } from "vue";

const confirmingUserDeletion = ref(false);
const passwordInput = ref(null);
const showPw = ref(false);

const form = useForm({
    password: "",
});

const confirmUserDeletion = () => {
    confirmingUserDeletion.value = true;
    // On attend l'ouverture du dialogue pour mettre le focus
    nextTick(() => {
        setTimeout(() => passwordInput.value?.focus(), 200);
    });
};

const deleteUser = () => {
    form.delete(route("profile.destroy"), {
        preserveScroll: true,
        onSuccess: () => closeModal(),
        onError: () => passwordInput.value?.focus(),
        onFinish: () => form.reset(),
    });
};

const closeModal = () => {
    confirmingUserDeletion.value = false;
    form.clearErrors();
    form.reset();
};
</script>

<template>
    <section>
        <header class="mb-6">
            <h2
                class="text-h6 font-weight-bold text-red-darken-3 d-flex align-center"
            >
                <v-icon
                    icon="mdi-alert-octagon-outline"
                    start
                    color="red-darken-3"
                />
                Zone de danger : Supprimer le compte
            </h2>
            <p class="text-body-2 text-grey-darken-1">
                Une fois votre compte supprimé, toutes ses ressources et données
                seront définitivement effacées. Veuillez procéder avec prudence.
            </p>
        </header>

        <v-btn
            color="red-darken-3"
            variant="tonal"
            class="text-none font-weight-bold rounded-lg border-sm"
            prepend-icon="mdi-delete-forever-outline"
            @click="confirmUserDeletion"
        >
            Supprimer mon compte
        </v-btn>

        <v-dialog v-model="confirmingUserDeletion" max-width="550" persistent>
            <v-card class="rounded-xl pa-2 elevation-24 border-red-lighten-4">
                <v-card-title
                    class="text-h6 font-weight-bold text-red-darken-4 pt-4 px-6 d-flex align-center"
                >
                    <v-icon
                        icon="mdi-shield-alert-outline"
                        color="red-darken-3"
                        class="me-3"
                    />
                    Confirmation de suppression
                </v-card-title>

                <v-card-text class="text-body-1 text-teal-darken-4 pt-2 px-6">
                    Êtes-vous certain de vouloir quitter
                    <strong>TTI Stock</strong> ?
                    <p class="text-body-2 text-grey-darken-1 mt-2">
                        Pour valider cette action irréversible, merci de
                        confirmer votre identité en saisissant votre mot de
                        passe actuel.
                    </p>

                    <v-text-field
                        v-model="form.password"
                        ref="passwordInput"
                        label="Mot de passe de confirmation"
                        :type="showPw ? 'text' : 'password'"
                        variant="outlined"
                        density="comfortable"
                        color="red-darken-3"
                        base-color="grey-lighten-2"
                        class="mt-6"
                        placeholder="••••••••"
                        :error-messages="form.errors.password"
                        prepend-inner-icon="mdi-lock-outline"
                        :append-inner-icon="showPw ? 'mdi-eye-off' : 'mdi-eye'"
                        @click:append-inner="showPw = !showPw"
                        @keyup.enter="deleteUser"
                        rounded="lg"
                    />
                </v-card-text>

                <v-card-actions class="pb-6 px-6">
                    <v-spacer></v-spacer>

                    <v-btn
                        variant="text"
                        color="grey-darken-1"
                        class="text-none font-weight-bold px-4 rounded-lg"
                        @click="closeModal"
                    >
                        Annuler
                    </v-btn>

                    <v-btn
                        color="red-darken-3"
                        variant="flat"
                        class="text-none font-weight-bold px-6 rounded-lg elevation-2 delete-btn"
                        :loading="form.processing"
                        @click="deleteUser"
                    >
                        Confirmer la suppression
                    </v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>
    </section>
</template>

<style scoped>
.delete-btn {
    transition: all 0.2s ease;
}
.delete-btn:hover {
    background-color: #b71c1c !important; /* rouge plus sombre au survol */
    transform: scale(1.02);
}

/* Bordure subtile pour le dialogue de danger */
.v-card {
    border: 1px solid rgba(211, 47, 47, 0.1) !important;
}
</style>
