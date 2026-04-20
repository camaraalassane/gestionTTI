<script setup>
    import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
    import { Head, Link, router } from "@inertiajs/vue3";
    import { ref, computed, watch, onMounted } from "vue";
    import axios from "axios";

    const props = defineProps({
        stats: {
            type: Object,
            required: true,
        },
        services: {
            type: Array,
            default: () => []
        }
    });

    const selectedService = ref("");
    const serviceStats = ref(null);
    const isLoading = ref(false);
    const expandedCategories = ref({});

    const tauxOccupation = computed(() => {
        if (!props.stats.total || props.stats.total === 0) return 0;
        return Math.min(
            Math.round((props.stats.octroyes / props.stats.total) * 100),
            100,
        );
    });

    const headers = [
        { title: "MATÉRIEL", key: "nom", align: "start" },
        { title: "S/N", key: "numero_serie", align: "start" },
        { title: "COMPOSANTS", key: "pieces", align: "center" },
        { title: "STATUT (PHY)", key: "statut", align: "center" },
        { title: "LOGISTIQUE", key: "etat", align: "end" },
    ];

    const toggleCategory = (categorieId) => {
        expandedCategories.value[categorieId] = !expandedCategories.value[categorieId];
    };

    const isCategoryExpanded = (categorieId) => {
        return expandedCategories.value[categorieId] !== false;
    };

    const loadServiceStats = async () => {
        if (!selectedService.value) {
            serviceStats.value = null;
            return;
        }

        isLoading.value = true;
        try {
            const response = await axios.get(`/api/service-stats/${selectedService.value}`);
            serviceStats.value = response.data;

            if (serviceStats.value?.categories) {
                serviceStats.value.categories.forEach(cat => {
                    if (expandedCategories.value[cat.id] === undefined) {
                        expandedCategories.value[cat.id] = true;
                    }
                });
            }
        } catch (error) {
            console.error("Erreur chargement statistiques:", error);
            serviceStats.value = null;
        } finally {
            isLoading.value = false;
        }
    };

    watch(selectedService, () => {
        loadServiceStats();
    });
</script>

<template>

    <Head title="Tableau de Bord" />

    <AuthenticatedLayout>
        <template #header>
            <v-icon icon="mdi-view-dashboard-outline" class="mr-2" color="teal-darken-1"></v-icon>
            Tableau de Bord
        </template>

        <v-container fluid class="pa-0">
            <!-- Cartes de statistiques générales -->
            <v-row dense class="mb-6">
                <v-col cols="12" sm="4" class="v-col-custom-5">
                    <v-card flat class="rounded-xl pa-4 bg-teal-darken-1 text-white h-100 position-relative elevation-2">
                        <div class="text-caption font-weight-bold opacity-70">
                            PARC TOTAL
                        </div>
                        <div class="text-h4 font-weight-black">
                            {{ stats.total }}
                        </div>
                        <v-icon icon="mdi-devices" class="stats-icon"></v-icon>
                    </v-card>
                </v-col>

                <v-col cols="6" sm="4" class="v-col-custom-5">
                    <v-card flat class="rounded-xl pa-4 bg-white border-success-left h-100 elevation-1">
                        <div class="text-caption font-weight-bold text-success">
                            DISPONIBLE
                        </div>
                        <div class="text-h5 font-weight-black">
                            {{ stats.en_stock }}
                        </div>
                    </v-card>
                </v-col>

                <v-col cols="6" sm="4" class="v-col-custom-5">
                    <v-card flat class="rounded-xl pa-4 bg-white border-orange-left h-100 elevation-1">
                        <div class="text-caption font-weight-bold text-orange-darken-4">
                            EN ATTENTE
                        </div>
                        <div class="text-h5 font-weight-black">
                            {{ stats.en_attente || 0 }}
                        </div>
                    </v-card>
                </v-col>

                <v-col cols="6" sm="6" class="v-col-custom-5">
                    <v-card flat class="rounded-xl pa-4 bg-white border-teal-left h-100 elevation-1">
                        <div class="text-caption font-weight-bold text-teal-darken-1">
                            LIVRÉS (AFFECTÉS)
                        </div>
                        <div class="text-h5 font-weight-black">
                            {{ stats.octroyes }}
                        </div>
                    </v-card>
                </v-col>

                <v-col cols="6" sm="6" class="v-col-custom-5">
                    <v-card flat class="rounded-xl pa-4 bg-white border-red-left h-100 elevation-1">
                        <div class="text-caption font-weight-bold text-error">
                            EN PANNE
                        </div>
                        <div class="text-h5 font-weight-black">
                            {{ stats.en_panne }}
                        </div>
                    </v-card>
                </v-col>
            </v-row>

            <v-row>
                <v-col cols="12" lg="9">
                    <v-card flat class="rounded-xl overflow-hidden elevation-1">
                        <v-card-title class="pa-4 d-flex align-center bg-white">
                            <span class="text-subtitle-2 font-weight-black text-grey-darken-3">DERNIÈRES RÉCEPTIONS</span>
                            <v-spacer></v-spacer>
                            <v-select v-model="selectedService" :items="services" item-title="nom" item-value="id" label="Filtrer par service" variant="outlined" density="compact" hide-details class="service-select" clearable placeholder="Tous les services" />
                        </v-card-title>

                        <!-- Affichage des statistiques du service sélectionné -->
                        <div v-if="isLoading" class="text-center pa-8">
                            <v-progress-circular indeterminate color="teal-darken-1"></v-progress-circular>
                            <div class="mt-2 text-caption">Chargement des données...</div>
                        </div>

                        <div v-else-if="selectedService && serviceStats" class="stats-container">
                            <div class="pa-4 bg-grey-lighten-5 border-bottom">
                                <div class="d-flex align-center justify-space-between">
                                    <div>
                                        <span class="text-h6 font-weight-bold text-teal-darken-4">{{ serviceStats.service_nom }}</span>
                                        <v-chip size="small" color="teal-lighten-4" class="ml-2">
                                            Total: {{ serviceStats.total_materiels }} matériels
                                        </v-chip>
                                    </div>
                                    <div class="text-caption text-grey">
                                        Dernière mise à jour: {{ serviceStats.last_update }}
                                    </div>
                                </div>
                            </div>

                            <div v-if="serviceStats.categories && serviceStats.categories.length > 0" class="scroll-container" style="max-height: 65vh; overflow-y: auto;">
                                <div v-for="categorie in serviceStats.categories" :key="categorie.id">
                                    <div class="bg-teal-lighten-5 pa-3 mt-3 first:mt-0">
                                        <div class="d-flex align-center">
                                            <v-btn :icon="isCategoryExpanded(categorie.id) ? 'mdi-chevron-down' : 'mdi-chevron-right'" variant="text" size="small" @click="toggleCategory(categorie.id)" class="mr-1" />
                                            <v-icon icon="mdi-folder" size="small" class="mr-1" color="teal-darken-2" />
                                            <span class="font-weight-black text-teal-darken-4 text-uppercase" style="font-size: 0.9rem;">
                                                {{ categorie.nom }}
                                            </span>
                                            <v-chip size="small" color="teal-darken-3" class="ml-2 font-weight-bold text-white">
                                                📦 Total: {{ categorie.total_quantite }} unités
                                            </v-chip>
                                        </div>
                                    </div>

                                    <v-table v-if="isCategoryExpanded(categorie.id)" density="compact" class="border rounded-b-lg mb-4">
                                        <thead>
                                            <tr class="bg-grey-lighten-4">
                                                <th class="text-left" width="55%">MODÈLE</th>
                                                <th class="text-center" width="25%">QUANTITÉ REÇUE</th>
                                                <th class="text-center" width="20%">STATUT</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr v-for="modele in categorie.modeles" :key="modele.id">
                                                <td class="font-weight-bold text-teal-darken-4">{{ modele.nom }}</td>
                                                <td class="text-center">
                                                    <v-chip color="green-lighten-4" variant="flat" size="small" class="font-weight-bold">
                                                        {{ modele.quantite }}
                                                    </v-chip>
                                                </td>
                                                <td class="text-center">
                                                    <v-chip :color="modele.quantite > 0 ? 'teal-lighten-4' : 'grey-lighten-3'" size="small" variant="flat">
                                                        {{ modele.quantite > 0 ? 'En stock' : 'Rupture' }}
                                                    </v-chip>
                                                </td>
                                            </tr>
                                        </tbody>
                                    </v-table>
                                </div>
                            </div>
                            <div v-else class="text-center pa-8">
                                <v-icon icon="mdi-folder-open" size="48" color="grey-lighten-2" class="mb-2"></v-icon>
                                <div class="text-body-2 text-grey-darken-1">Aucune donnée pour ce service</div>
                            </div>
                        </div>

                        <!-- Tableau des dernières réceptions (quand aucun service sélectionné) -->
                        <div v-else>
                            <v-data-table :headers="headers" :items="stats.recents" hide-default-footer class="custom-table">
                                <template #item.nom="{ item }">
                                    <div class="py-2">
                                        <div class="font-weight-bold text-grey-darken-4">
                                            {{ item.nom }}
                                        </div>
                                        <div class="text-caption text-grey-darken-1">
                                            {{ item.categorie?.nom }}
                                        </div>
                                    </div>
                                </template>

                                <template #item.numero_serie="{ item }">
                                    <span class="text-caption font-weight-bold text-grey-darken-2">{{ item.numero_serie || "N/A" }}</span>
                                </template>

                                <template #item.pieces="{ item }">
                                    <v-chip size="x-small" :color="item.pieces?.some((p) => p.demande_id) ? 'orange-darken-1' : 'teal-lighten-4'" :class="item.pieces?.some((p) => p.demande_id) ? 'text-white' : 'text-teal-darken-4'" variant="flat" class="font-weight-bold">
                                        {{ item.pieces?.length || 0 }} Composants
                                    </v-chip>
                                </template>

                                <template #item.statut="{ item }">
                                    <v-chip size="x-small" :color="item.statut === 'En panne' ? 'red-lighten-5' : 'green-lighten-5'" :class="item.statut === 'En panne' ? 'text-red-darken-4' : 'text-green-darken-4'" variant="flat" class="font-weight-bold">
                                        {{ item.statut?.toUpperCase() || "NEUF" }}
                                    </v-chip>
                                </template>

                                <template #item.etat="{ item }">
                                    <v-chip size="x-small" :prepend-icon="item.etat === 'Livré' ? 'mdi-account' : 'mdi-warehouse'" :color="item.etat === 'Livré' ? 'teal-darken-1' : 'grey-darken-1'" variant="outlined" class="font-weight-bold">
                                        {{ item.etat }}
                                    </v-chip>
                                </template>
                            </v-data-table>
                        </div>
                    </v-card>
                </v-col>

                <v-col cols="12" lg="3">
                    <v-card flat class="rounded-xl pa-5 mb-4 text-center elevation-1 bg-white">
                        <div class="text-overline mb-2 text-grey-darken-1">
                            Taux d'affectation
                        </div>
                        <v-progress-circular :model-value="tauxOccupation" :size="100" :width="10" color="teal-darken-1">
                            <span class="text-h6 font-weight-black text-teal-darken-4">{{ tauxOccupation }}%</span>
                        </v-progress-circular>
                    </v-card>

                    <v-card flat class="rounded-xl bg-teal-darken-4 text-white pa-4 elevation-2">
                        <div class="text-subtitle-2 font-weight-bold mb-4 d-flex align-center">
                            <v-icon size="small" class="mr-2">mdi-lightning-bolt</v-icon>
                            ACTIONS RAPIDES
                        </div>
                        <v-btn :component="Link" :href="route('materiel.indexmat')" block color="white" class="text-teal-darken-4 mb-3 text-none font-weight-black rounded-lg" prepend-icon="mdi-plus-box">
                            Nouvelle Réception
                        </v-btn>
                        <v-btn :component="Link" :href="route('demandes.create')" block variant="outlined" color="white" class="text-none font-weight-bold rounded-lg" prepend-icon="mdi-tray-arrow-up">
                            Affecter Matériel
                        </v-btn>
                    </v-card>
                </v-col>
            </v-row>
        </v-container>
    </AuthenticatedLayout>
</template>

<style scoped>
    @media (min-width: 960px) {
        .v-col-custom-5 {
            flex: 0 0 20%;
            max-width: 20%;
        }
    }

    .stats-icon {
        position: absolute;
        right: 12px;
        bottom: 12px;
        font-size: 48px;
        opacity: 0.15;
    }

    .border-success-left {
        border-left: 6px solid #2e7d32 !important;
    }

    .border-teal-left {
        border-left: 6px solid #00897b !important;
    }

    .border-red-left {
        border-left: 6px solid #d32f2f !important;
    }

    .border-orange-left {
        border-left: 6px solid #ef6c00 !important;
    }

    .service-select {
        max-width: 250px;
    }

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

    .custom-table :deep(thead th) {
        background-color: #f8fafc !important;
        text-transform: uppercase;
        font-size: 11px !important;
        font-weight: 800 !important;
        color: #475569 !important;
        height: 48px !important;
    }

    .custom-table :deep(tbody td) {
        height: 56px !important;
    }

    :deep(.v-table) {
        font-size: 12px;
    }

    :deep(.v-table th) {
        font-size: 11px;
        font-weight: 700;
        text-transform: uppercase;
        background-color: #f5f5f5;
    }
</style>
