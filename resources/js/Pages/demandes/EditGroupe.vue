<script setup>
    import { Head, useForm, router } from '@inertiajs/vue3';
    import { computed } from 'vue';
    import AuthentDemandeLayout from '@/Layouts/AuthentDemandeLayout.vue';

    const props = defineProps({
        service: String,
        demandes: Array
    });

    // Initialisation du formulaire
    const form = useForm({
        items: props.demandes.map(d => ({
            id: d.id,
            nom_materiel: d.nom_materiel,
            nbredemande: d.nbredemande,
            description: d.description || ''
        }))
    });

    // Calcul du total d'articles pour vérification visuelle
    const totalArticles = computed(() => {
        return form.items.reduce((acc, item) => acc + (Number(item.nbredemande) || 0), 0);
    });

    const submit = () => {
        form.put(route('demandes.update_groupe'), {
            onSuccess: () => {
                // Optionnel : Notification Toast ici
            },
            onError: () => {
                alert("Veuillez vérifier les quantités saisies.");
            }
        });
    };

    const annuler = () => {
        if (form.isDirty && !confirm("Quitter sans enregistrer les modifications ?")) return;
        window.history.back();
    };
</script>

<template>

    <Head :title="'Modif. ' + service" />
    <AuthentDemandeLayout>
        <v-container fluid class="pa-6 bg-grey-lighten-4 min-vh-100">

            <v-row class="mb-6 align-center">
                <v-col cols="12" md="6" class="d-flex align-center">
                    <v-btn icon="mdi-close" variant="text" color="grey-darken-2" @click="annuler" class="mr-4"></v-btn>
                    <div>
                        <h1 class="text-h5 font-weight-black text-teal-darken-4">Correction du lot</h1>
                        <v-chip size="small" color="teal-darken-3" variant="flat" label class="mt-1">
                            {{ service }}
                        </v-chip>
                    </div>
                </v-col>
                <v-col cols="12" md="6" class="text-md-right">
                    <div class="text-overline text-grey-darken-1">Total articles après modif.</div>
                    <div class="text-h4 font-weight-black text-teal">{{ totalArticles }}</div>
                </v-col>
            </v-row>

            <form @submit.prevent="submit">
                <v-card class="rounded-xl overflow-hidden shadow-lg border-0">
                    <v-table density="comfortable" class="edit-table">
                        <thead>
                            <tr class="bg-teal-darken-4">
                                <th class="text-white font-weight-bold">DÉSIGNATION</th>
                                <th class="text-white font-weight-bold text-center" style="width: 150px;">QUANTITÉ</th>
                                <th class="text-white font-weight-bold">OBSERVATIONS / NOTES</th>
                                <th class="text-white font-weight-bold text-center" style="width: 100px;">RÉF.</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="(item, index) in form.items" :key="item.id" class="row-hover">
                                <td class="py-4">
                                    <div class="text-subtitle-2 font-weight-bold text-teal-darken-4">{{ item.nom_materiel }}</div>
                                    <div v-if="form.errors[`items.${index}.nbredemande`]" class="text-caption text-red-darken-2 mt-1">
                                        {{ form.errors[`items.${index}.nbredemande`] }}
                                    </div>
                                </td>

                                <td>
                                    <v-text-field v-model="item.nbredemande" type="number" variant="outlined" density="compact" hide-details bg-color="white" class="text-center font-weight-black custom-input" min="1"></v-text-field>
                                </td>

                                <td>
                                    <v-text-field v-model="item.description" variant="outlined" density="compact" hide-details placeholder="Taille, couleur, ou motif de modif..." bg-color="grey-lighten-5" class="custom-input"></v-text-field>
                                </td>

                                <td class="text-center">
                                    <v-chip size="x-small" variant="tonal" color="grey">#{{ item.id }}</v-chip>
                                </td>
                            </tr>
                        </tbody>
                    </v-table>

                    <v-divider></v-divider>

                    <v-card-actions class="pa-8 bg-white d-flex align-center">
                        <div v-if="form.isDirty" class="text-caption text-orange-darken-3 d-flex align-center">
                            <v-icon icon="mdi-alert-circle-outline" class="mr-2"></v-icon>
                            Modifications non enregistrées
                        </div>

                        <v-spacer></v-spacer>

                        <v-btn variant="text" color="grey-darken-1" class="mr-4 px-6" @click="annuler">
                            Abandonner
                        </v-btn>

                        <v-btn type="submit" color="teal-darken-3" variant="elevated" size="x-large" class="px-10 rounded-lg font-weight-bold shadow-teal" :loading="form.processing" :disabled="!form.isDirty" prepend-icon="mdi-check-circle">
                            Appliquer les changements
                        </v-btn>
                    </v-card-actions>
                </v-card>
            </form>
        </v-container>
    </AuthentDemandeLayout>
</template>

<style scoped>
    .edit-table :deep(th) {
        height: 50px !important;
        text-transform: uppercase;
        font-size: 0.7rem !important;
        letter-spacing: 1px;
    }

    .row-hover:hover {
        background-color: #f0fdfa !important;
    }

    .custom-input :deep(.v-field__outline) {
        --v-field-border-opacity: 0.15;
    }

    .shadow-teal {
        box-shadow: 0 4px 15px rgba(0, 77, 64, 0.3) !important;
    }

    .edit-table :deep(.v-field__input) {
        font-weight: 700 !important;
    }

    /* Chrome, Safari, Edge, Opera - supprimer les flèches du input number */
    :deep(input::-webkit-outer-spin-button),
    :deep(input::-webkit-inner-spin-button) {
        -webkit-appearance: none;
        margin: 0;
    }
</style>
