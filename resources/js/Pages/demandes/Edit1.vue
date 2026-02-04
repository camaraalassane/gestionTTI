<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import AuthentDemandeLayout from '@/Layouts/AuthentDemandeLayout.vue';

// 1. Correction Erreur TypeScript : Déclaration de la fonction route de Ziggy
declare function route(name?: string, params?: any): string;

interface Props {
    demande: any;
    materiels: any[];
    services: any[];
}

const props = defineProps<Props>();

// 2. Initialisation du formulaire avec les données existantes
const form = useForm({
    materiel_id: props.demande.materiel_id,
    service_beneficiaire: props.demande.service_beneficiaire,
    demandeur_nom: props.demande.demandeur_nom,
    numcomande: props.demande.numcomande,
    nbredemande: props.demande.nbredemande,
    description: props.demande.description || '',
});

// 3. Fonction de mise à jour
const submit = () => {
    form.put(route('demandes.update', props.demande.id), {
        onSuccess: () => {
            // Logique après succès
        },
    });
};

// 4. Correction Erreur TypeScript : Accès sécurisé à window
const retourner = () => {
    if (typeof window !== 'undefined') {
        window.history.back();
    }
};
</script>

<template>
    <Head title="Modifier la Demande" />

    <AuthentDemandeLayout>
        <template #header>Modifier la demande N° {{ form.numcomande }}</template>

        <v-container fluid class="pa-4 bg-grey-lighten-4">
            <v-card class="mx-auto rounded-lg" max-width="800" border flat>
                <v-toolbar color="teal-darken-3" density="compact">
                    <v-toolbar-title class="text-caption font-weight-bold">ÉDITION DES INFORMATIONS</v-toolbar-title>
                </v-toolbar>

                <v-card-text class="pa-6">
                    <v-form @submit.prevent="submit">
                        <v-row>
                            <v-col cols="12" md="6">
                                <v-text-field
                                    v-model="form.numcomande"
                                    label="N° Commande"
                                    variant="outlined"
                                    readonly
                                    disabled
                                    density="comfortable"
                                ></v-text-field>
                            </v-col>

                            <v-col cols="12" md="6">
                                <v-text-field
                                    v-model="form.demandeur_nom"
                                    label="Demandeur"
                                    variant="outlined"
                                    density="comfortable"
                                ></v-text-field>
                            </v-col>

                            <v-col cols="12">
                                <v-autocomplete
                                    v-model="form.materiel_id"
                                    :items="props.materiels"
                                    item-title="nom"
                                    item-value="id"
                                    label="Matériel"
                                    variant="outlined"
                                    density="comfortable"
                                ></v-autocomplete>
                            </v-col>

                            <v-col cols="12" md="6">
                                <v-autocomplete
                                    v-model="form.service_beneficiaire"
                                    :items="props.services"
                                    item-title="nom"
                                    item-value="nom"
                                    label="Service Bénéficiaire"
                                    variant="outlined"
                                    density="comfortable"
                                ></v-autocomplete>
                            </v-col>

                            <v-col cols="12" md="6">
                                <v-text-field
                                    v-model.number="form.nbredemande"
                                    type="number"
                                    label="Quantité"
                                    variant="outlined"
                                    density="comfortable"
                                ></v-text-field>
                            </v-col>

                            <v-col cols="12">
                                <v-textarea
                                    v-model="form.description"
                                    label="Observations"
                                    variant="outlined"
                                    rows="3"
                                ></v-textarea>
                            </v-col>
                        </v-row>

                        <v-divider class="my-4"></v-divider>

                        <div class="d-flex justify-end gap-2">
                            <v-btn
                                variant="text"
                                color="grey-darken-1"
                                @click="retourner"
                                class="mr-2"
                            >
                                Annuler
                            </v-btn>
                            <v-btn
                                type="submit"
                                color="teal-darken-2"
                                :loading="form.processing"
                                prepend-icon="mdi-check"
                            >
                                Enregistrer les modifications
                            </v-btn>
                        </div>
                    </v-form>
                </v-card-text>
            </v-card>
        </v-container>
    </AuthentDemandeLayout>
</template>
<style scoped>
.shadow-lg {
    box-shadow: 0 15px 35px rgba(0,0,0,0.1) !important;
}
</style>