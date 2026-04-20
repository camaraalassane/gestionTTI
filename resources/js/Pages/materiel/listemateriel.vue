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

    interface ModeleMateriel {
        id: number;
        nom: string;
        qte_materiel: number;
        qte_livree: number;
        qte_pieces: number;
    }

    interface CategorieGroup {
        id: number;
        nom: string;
        modeleMateriels: ModeleMateriel[];
    }

    // CORRECTION: Ajout de per_page dans l'interface
    interface PaginatedCategories {
        data: CategorieGroup[];
        current_page: number;
        last_page: number;
        total: number;
        from: number;
        to: number;
        per_page: number; // Ajout de cette propriété
    }

    // --- PROPS ---
    const props = defineProps<{
        categories: PaginatedCategories;
        stats: {
            total: number;
            disponible: number;
            livres: number;
            pieces_sorties: number;
            en_attente: number;
        };
        filters: any;
        categoriesList: any[];
    }>();

    // --- ÉMITS POUR SNACKBAR ---
    const emit = defineEmits<{
        (e: 'show-snackbar', message: { text: string; color: string }): void;
    }>();

    // --- ÉTATS POUR LE COLLAPSE ---
    const expandedCategories = ref<Record<number, boolean>>({});

    const toggleCategory = (categorieId: number) => {
        expandedCategories.value[categorieId] = !expandedCategories.value[categorieId];
    };

    const isCategoryExpanded = (categorieId: number) => {
        return expandedCategories.value[categorieId] !== false;
    };

    // Initialiser toutes les catégories comme ouvertes
    const initExpandedCategories = () => {
        if (props.categories?.data) {
            props.categories.data.forEach(categorie => {
                if (expandedCategories.value[categorie.id] === undefined) {
                    expandedCategories.value[categorie.id] = true;
                }
            });
        }
    };

    onMounted(() => {
        initExpandedCategories();
    });

    watch(() => props.categories?.data, () => {
        initExpandedCategories();
    }, { immediate: true, deep: true });

    // --- LOGIQUE FILTRES ---
    const search = ref(props.filters?.search || "");
    const isLoading = ref(false);
    const isClearing = ref(false);

    const filteredCategories = computed(() => {
        if (!props.categories?.data) return [];

        const searchTerm = search.value.toLowerCase().trim();
        if (!searchTerm) return props.categories.data;

        return props.categories.data
            .map(categorie => {
                const filteredModeles = (categorie.modeleMateriels || []).filter(modele =>
                    modele.nom?.toLowerCase().includes(searchTerm)
                );

                if (filteredModeles.length > 0) {
                    expandedCategories.value[categorie.id] = true;
                    return {
                        ...categorie,
                        modeleMateriels: filteredModeles
                    };
                }
                return null;
            })
            .filter(c => c !== null);
    });

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
        isLoading.value = true;
        router.get(route("materiel.index"),
            {
                page,
                search: search.value || null
            },
            {
                preserveScroll: true,
                preserveState: true,
                onFinish: () => {
                    isLoading.value = false;
                }
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

    const openEditModal = (modele: any) => {
        form.id = modele.id;
        form.nom = modele.nom;
        form.categorie_id = null;
        form.description = '';
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
        itemToDelete.value = item;
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
    const historyData = ref<any[]>([]);
    const historyLoading = ref(false);
    const selectedModelName = ref("");
    const currentModeleId = ref<number | null>(null);

    const viewHistory = async (modele: any) => {
        const idDuMateriel = modele.id;

        if (!idDuMateriel) {
            emit('show-snackbar', { text: "Erreur : ID matériel introuvable", color: "red" });
            return;
        }

        currentModeleId.value = idDuMateriel;
        selectedModelName.value = modele.nom;
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

            <!-- TABLEAU GROUPÉ PAR CATÉGORIE AVEC COLLAPSE -->
            <v-card elevation="2" class="rounded-xl border-0 overflow-hidden">
                <div class="scroll-container" style="max-height: 65vh; overflow-y: auto;">
                    <div v-if="!props.categories?.data || props.categories.data.length === 0" class="text-center pa-8">
                        <v-icon icon="mdi-database-search" size="64" color="grey-lighten-2" class="mb-4"></v-icon>
                        <div class="text-h6 text-grey-darken-1">Aucune catégorie trouvée</div>
                    </div>

                    <template v-else>
                        <div v-for="categorie in filteredCategories" :key="categorie.id">
                            <!-- En-tête avec ancienne couleur mais informations visibles -->
                            <div class="bg-teal-lighten-5 pa-3 mt-3 first:mt-0">
                                <div class="d-flex align-center">
                                    <v-btn :icon="isCategoryExpanded(categorie.id) ? 'mdi-chevron-down' : 'mdi-chevron-right'" variant="text" size="small" @click="toggleCategory(categorie.id)" class="mr-1" />
                                    <v-icon icon="mdi-folder" size="small" class="mr-1" color="teal-darken-2" />
                                    <span class="font-weight-black text-teal-darken-4 text-uppercase" style="font-size: 0.9rem;">
                                        {{ categorie.nom }}
                                    </span>
                                    <!-- Nombre de modèles - PLUS VISIBLE -->
                                    <v-chip size="small" color="teal-darken-2" class="ml-2 font-weight-bold text-white">
                                        📋 {{ categorie.modeleMateriels?.length || 0 }} modèle(s)
                                    </v-chip>
                                    <!-- Nombre total d'unités - PLUS VISIBLE -->
                                    <v-chip size="small" color="teal-darken-4" class="ml-2 font-weight-bold text-white">
                                        📦 Total: {{categorie.modeleMateriels?.reduce((sum, m) => sum + (m.qte_materiel || 0), 0) || 0}} unités
                                    </v-chip>
                                </div>
                            </div>

                            <v-table v-if="isCategoryExpanded(categorie.id)" density="compact" class="border rounded-b-lg mb-4">
                                <thead>
                                    <tr class="bg-grey-lighten-4">
                                        <th class="text-left">DÉSIGNATION</th>
                                        <th class="text-center" width="150">UNITÉS (MAGASIN)</th>
                                        <th class="text-center" width="150">UNITÉS (LIVRÉES)</th>
                                        <th class="text-center" width="120">PIÈCES (MAGASIN)</th>
                                        <th class="text-center" width="100">ACTIONS</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="modele in (categorie.modeleMateriels || [])" :key="modele.id">
                                        <td class="font-weight-bold text-teal-darken-4">{{ modele.nom }}</td>
                                        <td class="text-center">
                                            <v-chip color="green-lighten-4" variant="flat" size="small" class="font-weight-bold">
                                                {{ modele.qte_materiel || 0 }}
                                            </v-chip>
                                        </td>
                                        <td class="text-center">
                                            <v-chip color="orange-lighten-4" variant="flat" size="small" class="font-weight-bold">
                                                {{ modele.qte_livree || 0 }}
                                            </v-chip>
                                        </td>
                                        <td class="text-center">
                                            <v-chip :color="modele.qte_pieces > 0 ? 'orange' : 'grey'" size="small" variant="tonal">
                                                {{ modele.qte_pieces || 0 }} pièces
                                            </v-chip>
                                        </td>
                                        <td class="text-center">
                                            <div class="d-flex justify-center gap-2">
                                                <v-btn icon="mdi-history" variant="text" color="blue-darken-2" size="small" @click="viewHistory(modele)"></v-btn>
                                                <v-btn icon="mdi-pencil" variant="text" color="teal" size="small" @click="openEditModal(modele)"></v-btn>
                                            </div>
                                        </td>
                                    </tr>
                                </tbody>
                            </v-table>
                        </div>
                    </template>
                </div>

                <!-- PAGINATION -->
                <!-- CORRECTION: Vérifier si per_page existe et si total > per_page -->
                <div v-if="props.categories && props.categories.total > (props.categories.per_page || 10)" class="d-flex justify-center align-center pa-4">
                    <v-pagination v-model="props.categories.current_page" :length="props.categories.last_page" :total-visible="5" @update:model-value="changePage" color="teal-darken-3" />
                    <span class="text-caption text-grey ml-4">
                        {{ props.categories.from }} - {{ props.categories.to }} sur {{ props.categories.total }} catégories
                    </span>
                </div>
            </v-card>
        </v-container>

        <!-- MODAL HISTORIQUE -->
        <v-dialog v-model="isHistoryModalOpen" max-width="800px" scrollable eager>
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
                            <div class="d-flex align-center pa-2 bg-grey-lighten-4 border-bottom">
                                <v-icon icon="mdi-file-document-outline" size="small" color="teal-darken-3" class="mr-2"></v-icon>
                                <span class="text-caption font-weight-bold text-teal-darken-3">{{ commande.numcomande }}</span>
                                <v-spacer></v-spacer>
                                <span class="text-caption text-grey-darken-1 mr-2">
                                    {{ new Date(commande.date).toLocaleDateString('fr-FR') }}
                                </span>
                            </div>

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
        <v-dialog v-model="isEditModalOpen" max-width="600px" scrollable eager>
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
                            <v-select v-model="form.categorie_id" :items="props.categoriesList" item-title="nom" item-value="id" label="Catégorie" variant="outlined" rounded="lg" required></v-select>
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
        <v-dialog v-model="isDeleteDialogOpen" max-width="500px" eager>
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
    .scroll-container {
        max-height: 65vh;
        overflow-y: auto;
    }

    .scroll-container::-webkit-scrollbar {
        width: 6px;
    }

    .scroll-container::-webkit-scrollbar-track {
        background: #f1f1f1;
        border-radius: 10px;
    }

    .scroll-container::-webkit-scrollbar-thumb {
        background: #c1c1c1;
        border-radius: 10px;
    }

    .scroll-container::-webkit-scrollbar-thumb:hover {
        background: #a8a8a8;
    }

    .gap-2 {
        gap: 8px;
    }

    .border-thin {
        border: 1px solid #E0E0E0 !important;
    }

    :deep(.v-chip) {
        font-size: 10px;
        height: 24px;
    }

    :deep(.v-chip .v-icon) {
        font-size: 12px;
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
