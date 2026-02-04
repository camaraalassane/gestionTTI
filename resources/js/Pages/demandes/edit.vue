<script setup>
import { computed, watch } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import AuthentDemandeLayout from '@/Layouts/AuthentDemandeLayout.vue';

const props = defineProps({ 
    demande: Object, 
    materiels: Array, 
    services: Array 
});

const form = useForm({
    numcomande: props.demande?.numcomande || '',
    demandeur_nom: props.demande?.demandeur_nom || '',
    service_beneficiaire: props.demande?.service_beneficiaire || '',
    date_demande: props.demande?.date_demande || '',
    nom_materiel: props.demande?.nom_materiel || '',
    categorie: props.demande?.categorie || '',
    nbredemande: props.demande?.nbredemande || 1,
    description: props.demande?.description || '',
});

// Watcher pour synchroniser la catégorie
watch(() => form.nom_materiel, (nouveauNom) => {
    const matFound = props.materiels.find(m => m.nom === nouveauNom);
    form.categorie = matFound?.categorie?.nom || 'Non classé';
});

const submit = () => {
    form.put(route('demandes.update', props.demande.id), {
        onSuccess: () => {
            // Optionnel : redirection ou toast
        },
    });
};

const goBack = () => window.history.back();
</script>

<template>
    <Head title="Modifier Demande" />
    <AuthentDemandeLayout>
        <v-container fluid class="pa-6 bg-grey-lighten-4 min-vh-100">
            
            <v-row class="mb-6 align-center">
                <v-col cols="auto">
                    <v-btn icon="mdi-arrow-left" variant="elevated" color="white" @click="goBack" class="rounded-lg shadow-sm"></v-btn>
                </v-col>
                <v-col>
                    <h1 class="text-h5 font-weight-black text-teal-darken-4">Édition de la pièce</h1>
                    <p class="text-caption text-grey-darken-1">Réf interne : <span class="font-weight-bold text-teal">#{{ demande.id }}</span></p>
                </v-col>
            </v-row>

            <form @submit.prevent="submit">
                <v-row>
                    <v-col cols="12" md="8">
                        <v-card class="mb-4 rounded-xl border-0 shadow-sm" flat>
                            <v-card-item class="bg-teal-darken-4 text-white">
                                <template v-slot:prepend>
                                    <v-icon icon="mdi-truck-outline"></v-icon>
                                </template>
                                <v-card-title class="text-subtitle-1 font-weight-bold">Logistique & Destination</v-card-title>
                            </v-card-item>

                            <v-card-text class="pa-6">
                                <v-row>
                                    <v-col cols="12" md="4">
                                        <v-text-field v-model="form.numcomande" label="N° Commande" variant="outlined" density="comfortable" color="teal" :error-messages="form.errors.numcomande"></v-text-field>
                                    </v-col>
                                    <v-col cols="12" md="8">
                                        <v-text-field v-model="form.demandeur_nom" label="Nom du demandeur" variant="outlined" density="comfortable" color="teal" :error-messages="form.errors.demandeur_nom"></v-text-field>
                                    </v-col>
                                    <v-col cols="12" md="6">
                                        <v-autocomplete v-model="form.service_beneficiaire" :items="services" item-title="nom" item-value="nom" label="Service Bénéficiaire" variant="outlined" density="comfortable" color="teal" :error-messages="form.errors.service_beneficiaire"></v-autocomplete>
                                    </v-col>
                                    <v-col cols="12" md="6">
                                        <v-text-field v-model="form.date_demande" type="date" label="Date de demande" variant="outlined" density="comfortable" color="teal" :error-messages="form.errors.date_demande"></v-text-field>
                                    </v-col>
                                </v-row>
                            </v-card-text>
                        </v-card>

                        <v-card class="rounded-xl border-0 shadow-sm" flat>
                            <v-card-item class="bg-grey-darken-3 text-white">
                                <template v-slot:prepend>
                                    <v-icon icon="mdi-cog-outline"></v-icon>
                                </template>
                                <v-card-title class="text-subtitle-1 font-weight-bold">Spécifications Matériel</v-card-title>
                            </v-card-item>

                            <v-card-text class="pa-6">
                                <v-row>
                                    <v-col cols="12" md="7">
                                        <v-select 
                                            v-model="form.nom_materiel" 
                                            :items="materiels" 
                                            item-title="nom" 
                                            item-value="nom" 
                                            label="Désignation" 
                                            variant="outlined" 
                                            density="comfortable"
                                            color="teal"
                                            :error-messages="form.errors.nom_materiel"
                                        >
                                            <template v-slot:item="{ props, item }">
                                                <v-list-item v-bind="props" :subtitle="'Catégorie: ' + (item.raw.categorie?.nom || 'N/A')"></v-list-item>
                                            </template>
                                        </v-select>
                                    </v-col>
                                    <v-col cols="12" md="5">
                                        <v-text-field v-model="form.categorie" label="Catégorie" variant="filled" density="comfortable" readonly bg-color="grey-lighten-4" prepend-inner-icon="mdi-tag-outline"></v-text-field>
                                    </v-col>
                                    <v-col cols="12" md="4">
                                        <v-text-field v-model="form.nbredemande" type="number" label="Quantité" variant="outlined" density="comfortable" color="teal" :error-messages="form.errors.nbredemande"></v-text-field>
                                    </v-col>
                                    <v-col cols="12">
                                        <v-textarea v-model="form.description" label="Instructions particulières" variant="outlined" density="comfortable" rows="3" color="teal"></v-textarea>
                                    </v-col>
                                </v-row>
                            </v-card-text>
                        </v-card>
                    </v-col>

                    <v-col cols="12" md="4">
                        <div class="sticky-top">
                            <v-card class="rounded-xl overflow-hidden shadow-lg border-0" flat>
                                <v-sheet color="teal-lighten-5" class="pa-6 text-center">
                                    <v-avatar color="teal-darken-3" size="64" class="mb-3 elevation-2">
                                        <v-icon icon="mdi-check-all" size="32"></v-icon>
                                    </v-avatar>
                                    <div class="text-h6 font-weight-black text-teal-darken-4">Prêt à valider ?</div>
                                    <p class="text-caption text-teal-darken-1">Vérifiez les quantités avant de sauvegarder.</p>
                                </v-sheet>
                                
                                <v-card-text class="pa-6">
                                    <div class="d-flex justify-space-between mb-2">
                                        <span class="text-grey">Matériel :</span>
                                        <span class="font-weight-bold">{{ form.nom_materiel || '---' }}</span>
                                    </div>
                                    <div class="d-flex justify-space-between mb-4">
                                        <span class="text-grey">Quantité :</span>
                                        <v-chip size="small" color="teal" variant="flat" class="font-weight-black">{{ form.nbredemande }}</v-chip>
                                    </div>
                                    
                                    <v-divider class="mb-6"></v-divider>

                                    <v-btn 
                                        block 
                                        color="teal-darken-3" 
                                        size="x-large" 
                                        type="submit" 
                                        class="rounded-lg font-weight-bold elevation-4 mb-3"
                                        :loading="form.processing"
                                        prepend-icon="mdi-content-save"
                                    >
                                        Sauvegarder
                                    </v-btn>
                                    
                                    <v-btn block variant="tonal" color="grey-darken-1" size="large" @click="goBack" class="rounded-lg">
                                        Abandonner
                                    </v-btn>
                                </v-card-text>
                                
                                <v-alert v-if="form.isDirty" type="warning" variant="tonal" icon="mdi-alert-circle" density="compact" class="ma-4 rounded-lg text-caption">
                                    Vous avez des modifications non enregistrées.
                                </v-alert>
                            </v-card>
                        </div>
                    </v-col>
                </v-row>
            </form>
        </v-container>
    </AuthentDemandeLayout>
</template>

<style scoped>
.sticky-top {
    position: sticky;
    top: 24px;
}
.shadow-sm {
    box-shadow: 0 2px 8px rgba(0,0,0,0.05) !important;
}
.shadow-lg {
    box-shadow: 0 10px 30px rgba(0,0,0,0.1) !important;
}
:deep(.v-field--variant-filled) {
    border-radius: 8px 8px 0 0;
}
</style>