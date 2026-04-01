<script setup lang="ts">
    import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
    import { Head, router, useForm } from "@inertiajs/vue3";
    import { ref, watch, onMounted, computed } from "vue";
    import debounce from "lodash/debounce";

    // --- INTERFACES ---
    interface Piece {
        id: number | null;
        nom_piece: string;
        numero_serie: string;
        demande_id?: number | null;
        demande?: { service_beneficiaire: string; demandeur_nom: string };
    }

    interface Materiel {
        id: number;
        nom: string;
        categorie_id: number;
        categorie: { nom: string };
        qte_materiel: number;
        qte_livree: number;
        qte_pieces: number;
        modele?: {
            id: number;
            nom: string;
        };
    }

    // Interface pour l'historique
    interface CommandeHistorique {
        numcomande: string;
        date: string;
        service: string;
        demandeur: string;
        materiels: any[];
        pieces_seules: any[];
    }

    // --- PROPS ---
    const props = withDefaults(defineProps<{
        materiels: { data: Materiel[]; current_page: number; last_page: number };
        categories: any[];
        filters: any;
        stats: {
            total: number;
            disponible: number;
            livres: number;
            pieces_sorties: number;
            en_attente: number;
        };
    }>(), {
        categories: () => [],
        stats: () => ({ total: 0, disponible: 0, livres: 0, pieces_sorties: 0, en_attente: 0 })
    });

    // --- ÉMITS POUR SNACKBAR ---
    const emit = defineEmits<{
        (e: 'show-snackbar', message: { text: string; color: string }): void;
    }>();

    // --- HEADERS DU TABLEAU ---
    const headers = [
        { title: "CATÉGORIE", key: "categorie.nom", align: "start" },
        { title: "DÉSIGNATION", key: "nom", align: "start" },
        { title: "UNITÉS (MAGASIN)", key: "qte_materiel", align: "center" },
        { title: "UNITÉS (LIVRÉES)", key: "qte_livree", align: "center" },
        { title: "PIÈCES (MAGASIN)", key: "qte_pieces", align: "center" },
        { title: "ACTIONS", key: "actions", align: "end", sortable: false },
    ] as const;

    // --- LOGIQUE FILTRES ---
    const search = ref(props.filters?.search || "");
    const isLoading = ref(false);
    const isClearing = ref(false);

    const appliquerFiltres = () => {
        if (isClearing.value) return;
        isLoading.value = true;

        router.get(
            route("materiel.index"),
            {
                search: search.value || null,
                page: 1
            },
            {
                only: ['materiels', 'stats', 'filters'],
                preserveState: true,
                preserveScroll: true,
                replace: true,
                onSuccess: () => {
                    isLoading.value = false;
                },
                onError: () => {
                    isLoading.value = false;
                }
            }
        );
    };

    watch(search, debounce((newValue: string) => {
        if (!isClearing.value) {
            appliquerFiltres();
        }
    }, 400));

    const effacerFiltres = () => {
        isClearing.value = true;
        search.value = "";

        router.get(route("materiel.index"), {}, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
            onSuccess: () => {
                isClearing.value = false;
                isLoading.value = false;
            },
            onError: () => {
                isClearing.value = false;
                isLoading.value = false;
            }
        });
    };

    const changePage = (page: number) => {
        router.get(route("materiel.index"),
            {
                page,
                search: search.value || null
            },
            {
                only: ['materiels'],
                preserveScroll: true,
                preserveState: true
            }
        );
    };

    // --- EXPORT PDF ---
    const exportPDF = () => {
        window.location.href = route('materiel.export', {
            format: 'pdf',
            search: search.value || null
        });
    };

    // --- LOGIQUE ÉDITION ---
    const isEditModalOpen = ref(false);
    const isDeleteDialogOpen = ref(false);
    const isSaving = ref(false);
    const isDeleting = ref(false);
    const itemToDelete = ref<any>(null);

    const form = useForm({
        id: null as number | null,
        modele_id: null as number | null,
        nom: "",
        categorie_id: null as number | null,
        description: "",
    });

    const openEditModal = (item: any) => {
        const itemData = item.raw || item;
        form.id = itemData.id;
        form.nom = itemData.nom;
        form.categorie_id = itemData.categorie_id || (itemData.categorie ? itemData.categorie.id : null);
        form.description = itemData.description || '';
        isEditModalOpen.value = true;
    };

    const saveEdit = () => {
        if (!form.id) return;
        isSaving.value = true;

        form.put(route('materiel.update.modele', { modele: form.id }), {
            preserveScroll: true,
            onSuccess: () => {
                isSaving.value = false;
                isEditModalOpen.value = false;
                emit('show-snackbar', {
                    text: 'Matériel mis à jour avec succès !',
                    color: 'teal-darken-2'
                });
            },
            onError: () => {
                isSaving.value = false;
                emit('show-snackbar', {
                    text: 'Erreur lors de la mise à jour',
                    color: 'red'
                });
            }
        });
    };

    const openDeleteDialog = (item: any) => {
        itemToDelete.value = item.raw || item;
        isDeleteDialogOpen.value = true;
    };

    const confirmDelete = () => {
        if (!itemToDelete.value) return;
        isDeleting.value = true;
        router.delete(route('materiel.destroy', itemToDelete.value.id), {
            onSuccess: () => {
                isDeleting.value = false;
                isDeleteDialogOpen.value = false;
                emit('show-snackbar', { text: 'Supprimé du stock magasin.', color: 'orange-darken-4' });
            }
        });
    };

    // --- HISTORIQUE ---
    const isHistoryModalOpen = ref(false);
    const historyData = ref<CommandeHistorique[]>([]);
    const historyLoading = ref(false);
    const selectedModelName = ref("");
    const currentModeleId = ref<number | null>(null);

    const viewHistory = async (item: any) => {
        const itemData = item.raw || item;
        const idDuMateriel = itemData.id;

        if (!idDuMateriel) {
            emit('show-snackbar', { text: "Erreur : ID matériel introuvable", color: "red" });
            return;
        }

        currentModeleId.value = idDuMateriel;
        selectedModelName.value = itemData.nom;
        isHistoryModalOpen.value = true;
        historyLoading.value = true;

        try {
            const response = await fetch(route('materiel.historique', { id: idDuMateriel }));
            if (!response.ok) throw new Error("Erreur serveur");
            const data = await response.json();
            historyData.value = data;
        } catch (error) {
            console.error("Erreur historique:", error);
            emit('show-snackbar', {
                text: "Erreur lors du chargement de l'historique",
                color: "red"
            });
        } finally {
            historyLoading.value = false;
        }
    };

    const printHistory = () => {
        if (!currentModeleId.value) return;
        const url = route('materiel.historique.pdf', {
            id: currentModeleId.value,
            nom: selectedModelName.value
        });
        window.open(url, '_blank');
    };
</script>

<template>

    <Head title="Gestion du Matériel" />
    <AuthenticatedLayout @show-snackbar="(msg: any) => emit('show-snackbar', msg)">
        <template #header> Inventaire du Matériel </template>

        <v-container fluid class="pa-6">
            <!-- STATISTIQUES -->
            <v-row class="mb-6">
                <v-col cols="12" sm="4" md="2">
                    <v-card elevation="0" border class="rounded-xl pa-5 bg-white">
                        <div class="text-overline font-weight-bold text-grey-darken-1">Total</div>
                        <div class="text-h4 font-weight-black text-teal-darken-4">{{ props.stats.total }}</div>
                    </v-card>
                </v-col>
                <v-col cols="12" sm="4" md="2">
                    <v-card elevation="0" border class="rounded-xl pa-5 bg-teal-darken-1 text-white">
                        <div class="text-overline font-weight-bold opacity-80">Dispo</div>
                        <div class="text-h4 font-weight-black">{{ props.stats.disponible }}</div>
                    </v-card>
                </v-col>
                <v-col cols="12" sm="4" md="3">
                    <v-card elevation="0" border class="rounded-xl pa-5 bg-white text-blue-darken-3">
                        <div class="text-overline font-weight-bold text-grey-darken-1">Livrés</div>
                        <div class="text-h4 font-weight-black">{{ props.stats.livres }}</div>
                    </v-card>
                </v-col>
                <v-col cols="12" sm="4" md="3">
                    <v-card elevation="0" border class="rounded-xl pa-5 bg-amber-lighten-5 text-amber-darken-4">
                        <div class="text-overline font-weight-bold">En Attente</div>
                        <div class="text-h4 font-weight-black">{{ props.stats.en_attente }}</div>
                    </v-card>
                </v-col>
                <v-col cols="12" sm="4" md="2">
                    <v-card elevation="0" border class="rounded-xl pa-5 bg-orange-lighten-5 text-orange-darken-4">
                        <div class="text-overline font-weight-bold">Pièces</div>
                        <div class="text-h4 font-weight-black">{{ props.stats.pieces_sorties }}</div>
                    </v-card>
                </v-col>
            </v-row>

            <!-- FILTRES -->
            <v-row class="mb-6" align="center">
                <v-col cols="12" md="6">
                    <v-text-field v-model="search" prepend-inner-icon="mdi-magnify" :loading="isLoading" clearable label="Rechercher par nom, catégorie, numéro de série..." variant="solo" flat hide-details rounded="lg" bg-color="white" class="border-thin" @click:clear="effacerFiltres">
                        <template v-if="search" v-slot:append-inner>
                            <v-chip size="x-small" color="teal" variant="flat">Filtre actif</v-chip>
                        </template>
                    </v-text-field>
                </v-col>
                <v-spacer></v-spacer>
                <v-col cols="auto">
                    <v-btn color="red-darken-2" prepend-icon="mdi-file-pdf-box" @click="exportPDF" variant="flat" class="rounded-lg">
                        PDF
                    </v-btn>
                </v-col>
            </v-row>

            <!-- TABLEAU -->
            <v-card elevation="2" class="rounded-xl border-0 overflow-hidden">
                <v-data-table :headers="headers" :items="props.materiels.data" :loading="isLoading" item-value="id" class="custom-table" hide-default-footer fixed-header height="60vh">
                    <template v-slot:item.categorie.nom="{ item, index }">
                        <div v-if="index === 0 || item.categorie_id !== props.materiels.data[index - 1]?.categorie_id" class="font-weight-black text-teal-darken-4">
                            {{ item.categorie?.nom?.toUpperCase() || '' }}
                        </div>
                        <div v-else class="text-grey-lighten-2">
                            <v-icon icon="mdi-menu-right" size="x-small"></v-icon>
                        </div>
                    </template>

                    <template v-slot:item.nom="{ item }">
                        <span class="ml-4 font-weight-bold">{{ item.nom }}</span>
                    </template>

                    <template v-slot:item.qte_materiel="{ item }">
                        <div class="text-center font-weight-bold">{{ item.qte_materiel ?? 0 }}</div>
                    </template>

                    <template v-slot:item.qte_livree="{ item }">
                        <div class="text-center font-weight-bold text-green-darken-2">{{ item.qte_livree ?? 0 }}</div>
                    </template>

                    <template v-slot:item.qte_pieces="{ item }">
                        <v-chip :color="item.qte_pieces > 0 ? 'orange' : 'grey'" size="small" variant="tonal">
                            {{ item.qte_pieces ?? 0 }} pièces
                        </v-chip>
                    </template>

                    <template v-slot:item.actions="{ item }">
                        <div class="d-flex justify-end gap-2">
                            <v-btn icon="mdi-history" variant="text" color="blue-darken-2" size="small" @click="viewHistory(item)"></v-btn>
                            <v-btn icon="mdi-pencil" variant="text" color="teal" size="small" @click="openEditModal(item)"></v-btn>
                        </div>
                    </template>

                    <template v-slot:no-data>
                        <div class="pa-10 text-center">
                            <v-icon icon="mdi-database-search-outline" size="64" color="grey-lighten-1" class="mb-4"></v-icon>
                            <div class="text-h6 text-grey-darken-1">Aucun matériel trouvé</div>
                            <v-btn color="teal" variant="text" class="mt-4" @click="effacerFiltres">Réinitialiser les filtres</v-btn>
                        </div>
                    </template>
                </v-data-table>

                <!-- PAGINATION -->
                <div class="d-flex align-center justify-between pa-4 bg-white border-t rounded-b-xl">
                    <div class="text-caption text-grey-darken-1">Page {{ props.materiels.current_page }} sur {{ props.materiels.last_page }}</div>
                    <v-spacer></v-spacer>
                    <v-pagination :model-value="props.materiels.current_page" :length="props.materiels.last_page" :total-visible="5" @update:model-value="changePage" rounded="lg" size="small" color="teal-darken-2"></v-pagination>
                </div>
            </v-card>
        </v-container>

        <!-- MODAL HISTORIQUE -->
        <v-dialog v-model="isHistoryModalOpen" max-width="800px" scrollable>
            <v-card class="rounded-xl">
                <v-card-title class="bg-teal-darken-3 text-white px-4 py-3">
                    <v-icon icon="mdi-history" class="mr-2"></v-icon>
                    <span class="text-subtitle-1 font-weight-bold">
                        Historique : {{ selectedModelName }}
                    </span>
                    <v-spacer></v-spacer>
                    <v-chip size="small" color="white" text-color="teal-darken-3" variant="flat">
                        {{ historyData.length }} commande(s)
                    </v-chip>
                </v-card-title>

                <v-card-text class="pa-3">
                    <v-progress-linear v-if="historyLoading" indeterminate color="teal-darken-3" height="2" class="mb-2"></v-progress-linear>

                    <div v-if="!historyLoading && historyData.length === 0" class="text-center pa-6">
                        <v-icon icon="mdi-package-variant" size="48" color="grey-lighten-2" class="mb-2"></v-icon>
                        <div class="text-body-2 text-grey-darken-1">Aucun historique</div>
                        <div class="text-caption text-grey">Ce matériel n'a pas encore été sorti</div>
                    </div>

                    <div v-else class="historique-container">
                        <v-card v-for="commande in historyData" :key="commande.numcomande" variant="outlined" class="mb-3 rounded-lg" density="compact">
                            <!-- En-tête de la commande -->
                            <div class="d-flex align-center pa-2 bg-grey-lighten-4 border-bottom">
                                <v-icon icon="mdi-file-document-outline" size="small" color="teal-darken-3" class="mr-2"></v-icon>
                                <span class="text-caption font-weight-bold text-teal-darken-3">{{ commande.numcomande }}</span>
                                <v-spacer></v-spacer>
                                <span class="text-caption text-grey-darken-1 mr-2">
                                    {{ new Date(commande.date).toLocaleDateString('fr-FR') }}
                                </span>
                            </div>

                            <!-- Service et demandeur -->
                            <div class="pa-2 bg-white">
                                <div class="d-flex align-center mb-1">
                                    <v-icon size="x-small" color="teal-darken-2" class="mr-1">mdi-office-building</v-icon>
                                    <span class="text-caption">{{ commande.service }}</span>
                                </div>
                                <div class="d-flex align-center">
                                    <v-icon size="x-small" color="teal-darken-2" class="mr-1">mdi-account</v-icon>
                                    <span class="text-caption">{{ commande.demandeur }}</span>
                                </div>
                            </div>

                            <!-- Matériels livrés -->
                            <div v-if="commande.materiels && commande.materiels.length > 0" class="pa-2 border-top">
                                <div class="text-caption font-weight-bold text-teal-darken-3 mb-1">
                                    <v-icon icon="mdi-laptop" size="x-small" class="mr-1"></v-icon>
                                    MATÉRIELS
                                </div>
                                <div class="d-flex flex-wrap gap-1">
                                    <v-chip v-for="mat in commande.materiels" :key="mat.id" size="x-small" color="teal-lighten-4" class="font-weight-black text-teal-darken-4">
                                        <v-icon start icon="mdi-laptop" size="x-small"></v-icon>
                                        {{ mat.nom_modele }}
                                        <span class="font-weight-black text-teal-darken-4">({{ mat.numero_serie }})</span>

                                        <!-- Icône pour les pièces attachées -->
                                        <template v-if="mat.pieces && mat.pieces.length > 0">
                                            <v-menu>
                                                <template v-slot:activator="{ props }">
                                                    <v-icon v-bind="props" icon="mdi-puzzle" size="x-small" color="teal-darken-3" class="ml-1"></v-icon>
                                                </template>
                                                <v-list density="compact" class="pa-1">
                                                    <v-list-item v-for="piece in mat.pieces" :key="piece.id" density="compact" class="pa-0">
                                                        <v-list-item-title class="font-weight-black text-teal-darken-4">
                                                            {{ piece.nom }}
                                                            <span class="font-weight-bold text-teal-darken-3" v-if="piece.sn">({{ piece.sn }})</span>
                                                        </v-list-item-title>
                                                    </v-list-item>
                                                </v-list>
                                            </v-menu>
                                        </template>
                                    </v-chip>
                                </div>
                            </div>

                            <!-- Pièces livrées seules -->
                            <div v-if="commande.pieces_seules && commande.pieces_seules.length > 0" class="pa-2 border-top">
                                <div class="text-caption font-weight-bold text-teal-darken-3 mb-1">
                                    <v-icon icon="mdi-puzzle" size="x-small" class="mr-1"></v-icon>
                                    PIÈCES SEULES
                                </div>
                                <div class="d-flex flex-wrap gap-1">
                                    <v-chip v-for="piece in commande.pieces_seules" :key="piece.id" size="x-small" color="teal-lighten-5" class="font-weight-black text-teal-darken-4">
                                        <v-icon start icon="mdi-puzzle" size="x-small"></v-icon>
                                        {{ piece.nom }}
                                        <span class="font-weight-black text-teal-darken-4" v-if="piece.sn">({{ piece.sn }})</span>
                                    </v-chip>
                                </div>
                            </div>
                        </v-card>
                    </div>
                </v-card-text>

                <v-card-actions class="pa-2 bg-grey-lighten-4">
                    <v-btn color="teal-darken-3" variant="text" @click="printHistory" :disabled="!historyData.length" prepend-icon="mdi-file-pdf-box" size="small" class="text-caption">
                        PDF
                    </v-btn>
                    <v-spacer></v-spacer>
                    <v-btn color="teal-darken-3" variant="flat" @click="isHistoryModalOpen = false" size="small" class="text-caption">
                        Fermer
                    </v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>

        <!-- MODAL ÉDITION -->
        <v-dialog v-model="isEditModalOpen" max-width="600px" scrollable>
            <v-card class="rounded-xl">
                <v-card-title class="bg-teal-darken-3 text-white px-6 py-4">
                    Modifier le Matériel
                </v-card-title>
                <v-card-text class="pa-6">
                    <v-row>
                        <v-col cols="12">
                            <v-text-field v-model="form.nom" label="Nom du matériel" variant="outlined" rounded="lg" required></v-text-field>
                        </v-col>
                        <v-col cols="12">
                            <v-select v-model="form.categorie_id" :items="props.categories" item-title="nom" item-value="id" label="Catégorie" variant="outlined" rounded="lg" required></v-select>
                        </v-col>
                        <v-col cols="12">
                            <v-textarea v-model="form.description" label="Description (optionnelle)" variant="outlined" rounded="lg" rows="2" auto-grow></v-textarea>
                        </v-col>
                    </v-row>
                </v-card-text>
                <v-card-actions class="pa-6 bg-grey-lighten-4">
                    <v-spacer></v-spacer>
                    <v-btn color="teal-darken-2" variant="flat" :loading="isSaving" @click="saveEdit">
                        Enregistrer
                    </v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>

        <!-- MODAL SUPPRESSION -->
        <v-dialog v-model="isDeleteDialogOpen" max-width="500px">
            <v-card class="rounded-xl">
                <v-card-title class="bg-orange-darken-4 text-white">Supprimer</v-card-title>
                <v-card-text class="pa-6">
                    Voulez-vous supprimer les exemplaires en stock ?
                </v-card-text>
                <v-card-actions class="pa-6">
                    <v-btn variant="text" @click="isDeleteDialogOpen = false">Annuler</v-btn>
                    <v-spacer></v-spacer>
                    <v-btn color="orange-darken-4" variant="flat" :loading="isDeleting" @click="confirmDelete">
                        Confirmer
                    </v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>
    </AuthenticatedLayout>
</template>

<style scoped>
    .custom-table {
        max-height: 70vh;
        overflow-y: auto;
    }

    .custom-table :deep(.v-table__wrapper) {
        overflow-y: auto;
    }

    .custom-table :deep(thead th) {
        background-color: #f8fafc !important;
        font-weight: 800 !important;
        color: #64748b !important;
        border-bottom: 2px solid #e2e8f0 !important;
    }

    .custom-table :deep(tbody tr:hover) {
        background-color: #f1f5f9 !important;
    }

    .gap-2 {
        gap: 8px;
    }

    .border-thin {
        border: 1px solid #E0E0E0 !important;
    }

    .custom-table::-webkit-scrollbar {
        width: 8px;
    }

    .custom-table::-webkit-scrollbar-thumb {
        background-color: #cbd5e1;
        border-radius: 4px;
    }

    .historique-container {
        max-height: 60vh;
        overflow-y: auto;
        padding-right: 4px;
    }

    .historique-container::-webkit-scrollbar {
        width: 4px;
    }

    .historique-container::-webkit-scrollbar-thumb {
        background-color: #b0bec5;
        border-radius: 4px;
    }

    .border-bottom {
        border-bottom: 1px solid #e0e0e0;
    }

    .border-top {
        border-top: 1px solid #e0e0e0;
    }

    .gap-1 {
        gap: 4px;
    }

    :deep(.v-chip) {
        font-size: 10px;
        height: 24px;
    }

    :deep(.v-chip .v-icon) {
        font-size: 12px;
    }

    :deep(.v-list) {
        padding: 4px !important;
    }

    :deep(.v-list-item) {
        min-height: 28px !important;
    }

    :deep(.v-list-item-title) {
        font-size: 11px !important;
    }
</style>
