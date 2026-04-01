<script setup lang="ts">
    import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
    import { Head, useForm } from "@inertiajs/vue3";

    // --- INTERFACES POUR LE TYPAGE ---
    interface Piece {
        id: number | null;
        nom_piece: string;
        numero_serie: string;
        demande_id?: number | null;
    }

    interface Materiel {
        id: number;
        nom: string;
        numero_serie: string;
        categorie_id: number;
        etat: string;
        statut: string;
        description: string | null;
        pieces: Piece[];
    }

    const props = defineProps<{
        materiel: Materiel;
        categories: any[];
    }>();

    const route = (window as any).route;

    // --- INITIALISATION DU FORMULAIRE ---
    const form = useForm({
        nom: props.materiel.nom,
        numero_serie: props.materiel.numero_serie,
        categorie_id: props.materiel.categorie_id,
        etat: props.materiel.etat,
        statut: props.materiel.statut,
        description: props.materiel.description || "",
        pieces: props.materiel.pieces.map((p: Piece) => ({
            id: p.id,
            nom_piece: p.nom_piece,
            numero_serie: p.numero_serie,
            demande_id: p.demande_id,
        })),
    });

    // --- LOGIQUE DES PIÈCES ---
    const addPiece = () => {
        form.pieces.push({
            id: null,
            nom_piece: "",
            numero_serie: "",
            demande_id: null,
        });
    };

    const removePiece = (index: number) => {
        if (form.pieces[index].demande_id) {
            alert("Impossible de supprimer une pièce déjà livrée.");
            return;
        }
        form.pieces.splice(index, 1);
    };

    const submit = () => {
        const targetId = Number(props.materiel.id);
        form.put(route("materiel.update", targetId), {
            preserveScroll: true,
        });
    };

    const goBack = () => {
        window.history.back();
    };
</script>

<template>

    <Head :title="`Édition : ${materiel.nom}`" />
    <AuthenticatedLayout>
        <template #header>
            <div class="d-flex align-center">
                <v-btn icon="mdi-arrow-left" variant="text" color="teal-darken-2" class="mr-2" @click="goBack"></v-btn>
                <div>
                    <div class="text-caption text-teal-darken-1 font-weight-bold">
                        GESTION DES STOCKS
                    </div>
                    <span class="font-weight-black text-h6 text-teal-darken-4 text-uppercase">
                        Modifier : {{ materiel.nom }}
                    </span>
                </div>
            </div>
        </template>

        <v-container fluid class="pa-6 bg-teal-lighten-5 fill-height align-start">
            <v-row justify="center">
                <v-col cols="12" md="10" lg="8">
                    <form @submit.prevent="submit">
                        <v-card border flat class="rounded-xl mb-6 pa-6 shadow-sm bg-white">
                            <v-card-title class="text-teal-darken-4 font-weight-bold px-0 mb-6 d-flex align-center">
                                <v-icon start color="teal-darken-1" class="mr-3">mdi-information-outline</v-icon>
                                Informations Générales
                            </v-card-title>

                            <v-row>
                                <v-col cols="12" md="6">
                                    <v-text-field v-model="form.nom" label="Désignation" variant="outlined" color="teal" density="comfortable" :error-messages="form.errors.nom"></v-text-field>
                                </v-col>
                                <v-col cols="12" md="6">
                                    <v-text-field v-model="form.numero_serie" label="N° de Série" variant="outlined" color="teal" density="comfortable" :error-messages="form.errors.numero_serie
                                        "></v-text-field>
                                </v-col>
                                <v-col cols="12" md="4">
                                    <v-select v-model="form.categorie_id" :items="categories" item-title="nom" item-value="id" label="Catégorie" variant="outlined" color="teal" density="comfortable"></v-select>
                                </v-col>
                                <v-col cols="12" md="4">
                                    <v-select v-model="form.etat" :items="[
                                        'Disponible',
                                        'Livré',
                                        'En stock',
                                        'En maintenance',
                                    ]" label="État Logistique" variant="outlined" color="teal" density="comfortable"></v-select>
                                </v-col>
                                <v-col cols="12" md="4">
                                    <v-select v-model="form.statut" :items="[
                                        'Neuf',
                                        'Occasion',
                                        'En panne',
                                        'Rebut',
                                    ]" label="État Physique" variant="outlined" color="teal" density="comfortable"></v-select>
                                </v-col>
                                <v-col cols="12">
                                    <v-textarea v-model="form.description" label="Observations / Détails techniques" variant="outlined" color="teal" density="comfortable" rows="2"></v-textarea>
                                </v-col>
                            </v-row>
                        </v-card>

                        <v-card border flat class="rounded-xl mb-6 pa-6 shadow-sm bg-white">
                            <v-card-title class="text-teal-darken-4 font-weight-bold px-0 d-flex justify-space-between align-center mb-4">
                                <div class="d-flex align-center">
                                    <v-icon start color="teal-darken-1" class="mr-3">mdi-puzzle-outline</v-icon>
                                    Pièces et Composants
                                </div>
                                <v-btn prepend-icon="mdi-plus" size="small" color="teal-darken-1" variant="flat" @click="addPiece" class="rounded-lg font-weight-bold">
                                    Ajouter une pièce
                                </v-btn>
                            </v-card-title>

                            <v-divider class="mb-6"></v-divider>

                            <v-row v-for="(piece, index) in form.pieces" :key="index" class="align-center mb-4 rounded-lg ma-0 pa-3 border shadow-xs transition-swing" :class="piece.demande_id
                                ? 'bg-orange-lighten-5 border-orange-lighten-2'
                                : 'bg-teal-lighten-5 border-teal-lighten-4'
                                ">
                                <v-col cols="12" md="5" class="py-1">
                                    <v-text-field v-model="form.pieces[index].nom_piece" label="Désignation de la pièce" :readonly="!!piece.demande_id" :variant="piece.demande_id
                                        ? 'plain'
                                        : 'solo-filled'
                                        " flat density="compact" hide-details rounded="lg" :class="piece.demande_id
                                                ? 'text-orange-darken-4 font-weight-bold'
                                                : 'text-teal-darken-4'
                                                "></v-text-field>
                                </v-col>

                                <v-col cols="12" md="5" class="py-1">
                                    <v-text-field v-model="form.pieces[index].numero_serie
                                        " label="N° de Série" :readonly="!!piece.demande_id" :variant="piece.demande_id
                                            ? 'plain'
                                            : 'solo-filled'
                                            " flat density="compact" hide-details rounded="lg"></v-text-field>
                                </v-col>

                                <v-col cols="12" md="2" class="text-right py-1">
                                    <v-tooltip v-if="piece.demande_id" location="top">
                                        <template v-slot:activator="{ props }">
                                            <v-icon v-bind="props" color="orange-darken-3">mdi-lock-check</v-icon>
                                        </template>
                                        <span>Sortie via demande #{{
                                            piece.demande_id
                                        }}
                                            (Modif. impossible)</span>
                                    </v-tooltip>
                                    <v-btn v-else icon="mdi-close-circle-outline" color="red-darken-1" variant="text" @click="removePiece(index)"></v-btn>
                                </v-col>

                                <v-col v-if="piece.demande_id" cols="12" class="pt-0">
                                    <v-chip size="x-small" color="orange-darken-4" variant="text" class="px-0 font-italic font-weight-bold">
                                        <v-icon start size="14">mdi-truck-delivery</v-icon>
                                        Élément déjà livré : les données sont
                                        verrouillées pour l'intégrité de
                                        l'archive.
                                    </v-chip>
                                </v-col>
                            </v-row>

                            <div v-if="form.pieces.length === 0" class="text-center py-10 border-dashed rounded-lg bg-grey-lighten-5">
                                <v-icon color="teal-lighten-3" size="48">mdi-package-variant</v-icon>
                                <div class="text-teal-darken-1 text-caption mt-2 font-weight-bold">
                                    Aucun composant associé à ce matériel.
                                </div>
                            </div>
                        </v-card>

                        <div class="d-flex justify-end gap-4 mt-10">
                            <v-btn variant="outlined" color="teal-darken-1" class="rounded-lg px-8 font-weight-bold" @click="goBack">Annuler</v-btn>
                            <v-btn type="submit" color="teal-darken-3" size="large" class="rounded-lg px-12 elevation-4 font-weight-bold" :loading="form.processing">
                                Enregistrer les modifications
                            </v-btn>
                        </div>
                    </form>
                </v-col>
            </v-row>
        </v-container>
    </AuthenticatedLayout>
</template>

<style scoped>
    .shadow-sm {
        box-shadow: 0 4px 20px rgba(0, 77, 64, 0.08) !important;
    }

    .shadow-xs {
        box-shadow: 0 2px 6px rgba(0, 0, 0, 0.02) !important;
    }

    .gap-4 {
        gap: 16px;
    }

    .border-dashed {
        border: 2px dashed #b2dfdb !important;
    }

    .transition-swing {
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }
</style>
