<script setup>
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import { Head, Link } from "@inertiajs/vue3";
import { ref, computed, watch, onMounted, onUnmounted } from "vue";
import axios from "axios";
import * as echarts from "echarts";

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
const chartRef = ref(null);
let chart = null;
let resizeObserver = null;

// Graphique par service des modèles affectés
const chartData = ref({
    services: [],
    modeles: [],
    data: []
});

// Palette de couleurs pour les différents modèles
const colorPalette = [
    '#26a69a', '#00897b', '#4db6ac', '#80cbc4',
    '#ffb74d', '#ff9800', '#f57c00', '#e65100',
    '#42a5f5', '#1e88e5', '#1565c0', '#0d47a1',
    '#ab47bc', '#8e24aa', '#6a1b9a', '#4a148c',
    '#ef5350', '#e53935', '#c62828', '#b71c1c'
];

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

// Charger les statistiques du service sélectionné
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

// Charger les données du graphique (modèles affectés par service)
const loadChartData = async () => {
    try {
        const response = await axios.get('/api/modeles-par-service', {
            params: { _: Date.now() }
        });
        chartData.value = response.data;
        initChart();
    } catch (error) {
        console.error("Erreur chargement graphique:", error);
    }
};

// Calculer la hauteur dynamique du graphique
const getChartHeight = () => {
    if (window.innerWidth < 600) return 300;
    if (window.innerWidth < 960) return 350;
    return 450;
};

// Calculer l'angle des labels selon le nombre de services
const getLabelRotate = () => {
    const serviceCount = chartData.value.services?.length || 0;
    if (serviceCount <= 5) return 0;
    if (serviceCount <= 10) return 25;
    return 45;
};

// Initialiser le graphique ECharts en mode STACK
const initChart = () => {
    if (!chartRef.value) return;

    if (chart) {
        chart.dispose();
    }

    chart = echarts.init(chartRef.value);

    const servicesLabels = chartData.value.services || [];
    const modelesLabels = chartData.value.modeles || [];
    const rawData = chartData.value.data || [];

    if (servicesLabels.length === 0 || modelesLabels.length === 0) {
        return;
    }

    // Calculer la valeur max pour ajuster l'axe Y
    let maxTotal = 0;
    for (let i = 0; i < servicesLabels.length; i++) {
        let total = 0;
        for (let j = 0; j < modelesLabels.length; j++) {
            total += rawData[i]?.[j] || 0;
        }
        maxTotal = Math.max(maxTotal, total);
    }
    // Ajouter 10% de marge
    const yAxisMax = Math.ceil(maxTotal * 1.1);

    const series = modelesLabels.map((modeleNom, modelIndex) => {
        const dataForThisModel = servicesLabels.map((_, serviceIndex) => {
            try {
                return rawData[serviceIndex]?.[modelIndex] || 0;
            } catch (e) {
                return 0;
            }
        });

        return {
            name: modeleNom,
            type: 'bar',
            stack: 'total',
            barMaxWidth: 80,
            data: dataForThisModel,
            itemStyle: {
                borderRadius: [4, 4, 0, 0],
                color: colorPalette[modelIndex % colorPalette.length]
            },
            label: {
                show: dataForThisModel.some(v => v > 0),
                position: 'inside',
                fontSize: 10,
                fontWeight: 'bold',
                color: '#fff',
                formatter: (params) => params.value > 0 ? params.value : ''
            },
            emphasis: {
                focus: 'series'
            }
        };
    });

    const option = {
        backgroundColor: 'transparent',
        tooltip: {
            trigger: 'axis',
            axisPointer: { type: 'shadow' },
            backgroundColor: 'rgba(255, 255, 255, 0.96)',
            textStyle: { color: '#333', fontSize: 12 },
            borderWidth: 1,
            borderColor: '#e0e0e0',
            formatter: function (params) {
                let result = `<div style="padding: 4px 8px;">`;
                result += `<strong style="color: #00897b;">${params[0].axisValue}</strong><br/>`;
                let total = 0;
                params.forEach(param => {
                    if (param.value > 0) {
                        result += `<div style="display: flex; justify-content: space-between; gap: 16px; margin-top: 4px;">
                                    <span>${param.marker} ${param.seriesName}</span>
                                    <b>${param.value}</b>
                                   </div>`;
                        total += param.value;
                    }
                });
                result += `<div style="margin-top: 6px; padding-top: 4px; border-top: 1px solid #eee; font-weight: bold; display: flex; justify-content: space-between;">
                            <span>📦 TOTAL</span>
                            <span>${total}</span>
                           </div>`;
                result += `</div>`;
                return result;
            }
        },
        legend: {
            top: 0,
            left: 'center',
            type: 'scroll',
            textStyle: { fontSize: 11 },
            pageIconColor: '#00897b',
            pageTextStyle: { color: '#00897b' }
        },
        grid: {
            left: '3%',
            right: '4%',
            bottom: '8%',
            top: '12%',
            containLabel: true
        },
        xAxis: {
            type: 'category',
            data: servicesLabels,
            axisLabel: {
                rotate: getLabelRotate(),
                interval: 0,
                fontSize: 10,
                fontWeight: '600',
                color: '#546e7a'
            },
            axisLine: { lineStyle: { color: '#cfd8dc' } },
            axisTick: { show: false }
        },
        yAxis: {
            type: 'value',
            name: 'Unités affectées',
            nameTextStyle: { fontWeight: 'bold', fontSize: 11 },
            splitLine: { lineStyle: { type: 'dashed', color: '#eceff1' } },
            axisLabel: { fontSize: 10, color: '#78909c' },
            max: yAxisMax
        },
        series: series
    };

    chart.setOption(option);
};

// Gestion du responsive
const handleResize = () => {
    if (chart) {
        chart.resize();
    }
};

watch(selectedService, () => {
    loadServiceStats();
});

onMounted(() => {
    loadChartData();
    window.addEventListener('resize', handleResize);
});

onUnmounted(() => {
    window.removeEventListener('resize', handleResize);
    if (chart) {
        chart.dispose();
        chart = null;
    }
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
            <!-- Cartes de statistiques générales - RESPONSIVE -->
            <v-row dense class="mb-4 mb-md-6">
                <v-col cols="6" sm="4" md="2" class="px-1 px-md-2">
                    <v-card flat
                        class="rounded-xl pa-2 pa-md-4 bg-teal-darken-1 text-white elevation-2 text-center text-md-left">
                        <div class="text-caption font-weight-bold opacity-70">PARC TOTAL</div>
                        <div class="text-h6 text-md-h4 font-weight-black">{{ stats.total }}</div>
                    </v-card>
                </v-col>

                <v-col cols="6" sm="4" md="2" class="px-1 px-md-2">
                    <v-card flat class="rounded-xl pa-2 pa-md-4 bg-white border-success-left h-100 elevation-1">
                        <div class="text-caption font-weight-bold text-success">DISPONIBLE</div>
                        <div class="text-h6 text-md-h5 font-weight-black">{{ stats.en_stock }}</div>
                    </v-card>
                </v-col>

                <v-col cols="6" sm="4" md="2" class="px-1 px-md-2">
                    <v-card flat class="rounded-xl pa-2 pa-md-4 bg-white border-orange-left h-100 elevation-1">
                        <div class="text-caption font-weight-bold text-orange-darken-4">EN ATTENTE</div>
                        <div class="text-h6 text-md-h5 font-weight-black">{{ stats.en_attente || 0 }}</div>
                    </v-card>
                </v-col>

                <v-col cols="6" sm="6" md="3" class="px-1 px-md-2">
                    <v-card flat class="rounded-xl pa-2 pa-md-4 bg-white border-teal-left h-100 elevation-1">
                        <div class="text-caption font-weight-bold text-teal-darken-1">LIVRÉS (AFFECTÉS)</div>
                        <div class="text-h6 text-md-h5 font-weight-black">{{ stats.octroyes }}</div>
                    </v-card>
                </v-col>

                <v-col cols="6" sm="6" md="3" class="px-1 px-md-2">
                    <v-card flat class="rounded-xl pa-2 pa-md-4 bg-white border-red-left h-100 elevation-1">
                        <div class="text-caption font-weight-bold text-error">EN PANNE</div>
                        <div class="text-h6 text-md-h5 font-weight-black">{{ stats.en_panne }}</div>
                    </v-card>
                </v-col>
            </v-row>

            <v-row>
                <!-- GRAPHIQUE PAR SERVICE - RESPONSIVE -->
                <v-col cols="12" lg="12">
                    <v-card flat class="rounded-xl overflow-hidden elevation-1 mb-4 mb-md-6">
                        <v-card-title class="pa-3 pa-md-4 d-flex align-center bg-white flex-wrap">
                            <span class="text-subtitle-2 font-weight-black text-grey-darken-3">
                                <v-icon icon="mdi-chart-bar" class="mr-2" color="teal-darken-1"></v-icon>
                                MODÈLES AFFECTÉS PAR SERVICE
                            </span>
                            <v-spacer></v-spacer>
                            <v-chip size="x-small" size-md="small" color="teal-lighten-4"
                                class="font-weight-bold mt-2 mt-md-0">
                                📊 Vue empilée
                            </v-chip>
                        </v-card-title>

                        <div class="pa-2 pa-md-4">
                            <div ref="chartRef" :style="{ width: '100%', height: getChartHeight() + 'px' }"></div>
                        </div>
                    </v-card>
                </v-col>

                <v-col cols="12" lg="9">
                    <v-card flat class="rounded-xl overflow-hidden elevation-1">
                        <v-card-title class="pa-3 pa-md-4 d-flex align-center bg-white flex-wrap">
                            <span class="text-subtitle-2 font-weight-black text-grey-darken-3">
                                <v-icon icon="mdi-clipboard-list" class="mr-2" color="teal-darken-1"></v-icon>
                                DÉTAIL PAR SERVICE
                            </span>
                            <v-spacer></v-spacer>
                            <v-select v-model="selectedService" :items="services" item-title="nom" item-value="id"
                                label="Filtrer par service" variant="outlined" density="compact" hide-details
                                class="service-select mt-2 mt-md-0" clearable placeholder="Tous les services"
                                style="min-width: 150px;" />
                        </v-card-title>

                        <!-- Affichage des statistiques du service sélectionné -->
                        <div v-if="isLoading" class="text-center pa-8">
                            <v-progress-circular indeterminate color="teal-darken-1"></v-progress-circular>
                            <div class="mt-2 text-caption">Chargement des données...</div>
                        </div>

                        <div v-else-if="selectedService && serviceStats" class="stats-container">
                            <div class="pa-3 pa-md-4 bg-grey-lighten-5 border-bottom">
                                <div class="d-flex flex-column flex-sm-row align-center justify-space-between">
                                    <div>
                                        <span class="text-h6 font-weight-bold text-teal-darken-4">{{
                                            serviceStats.service_nom
                                        }}</span>
                                        <v-chip size="x-small" color="teal-lighten-4" class="ml-2">
                                            Total: {{ serviceStats.total_materiels }} matériels
                                        </v-chip>
                                    </div>
                                    <div class="text-caption text-grey mt-2 mt-sm-0">
                                        Dernière mise à jour: {{ serviceStats.last_update }}
                                    </div>
                                </div>
                            </div>

                            <div v-if="serviceStats.categories && serviceStats.categories.length > 0"
                                class="scroll-container" style="max-height: 65vh; overflow-y: auto;">
                                <div v-for="categorie in serviceStats.categories" :key="categorie.id">
                                    <div class="bg-teal-lighten-5 pa-2 pa-md-3 mt-2 mt-md-3 first:mt-0">
                                        <div class="d-flex align-center flex-wrap">
                                            <v-btn
                                                :icon="isCategoryExpanded(categorie.id) ? 'mdi-chevron-down' : 'mdi-chevron-right'"
                                                variant="text" size="small" @click="toggleCategory(categorie.id)"
                                                class="mr-1" />
                                            <v-icon icon="mdi-folder" size="small" class="mr-1" color="teal-darken-2" />
                                            <span class="font-weight-black text-teal-darken-4 text-uppercase"
                                                style="font-size: 0.85rem;">
                                                {{ categorie.nom }}
                                            </span>
                                            <v-chip size="x-small" color="teal-darken-3"
                                                class="ml-2 font-weight-bold text-white">
                                                📦 {{ categorie.total_quantite }} unités
                                            </v-chip>
                                        </div>
                                    </div>

                                    <v-table v-if="isCategoryExpanded(categorie.id)" density="compact"
                                        class="border rounded-b-lg mb-3 mb-md-4">
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
                                                    <v-chip color="green-lighten-4" variant="flat" size="x-small"
                                                        class="font-weight-bold">
                                                        {{ modele.quantite }}
                                                    </v-chip>
                                                </td>
                                                <td class="text-center">
                                                    <v-chip
                                                        :color="modele.quantite > 0 ? 'teal-lighten-4' : 'grey-lighten-3'"
                                                        size="x-small" variant="flat">
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

                        <!-- Tableau des dernières réceptions -->
                        <div v-else>
                            <v-data-table :headers="headers" :items="stats.recents" hide-default-footer
                                class="custom-table">
                                <template #item.nom="{ item }">
                                    <div class="py-2">
                                        <div class="font-weight-bold text-grey-darken-4">{{ item.nom }}</div>
                                        <div class="text-caption text-grey-darken-1">{{ item.categorie?.nom }}</div>
                                    </div>
                                </template>

                                <template #item.numero_serie="{ item }">
                                    <span class="text-caption font-weight-bold text-grey-darken-2">{{ item.numero_serie
                                        || "N/A" }}</span>
                                </template>

                                <template #item.pieces="{ item }">
                                    <v-chip size="x-small"
                                        :color="item.pieces?.some((p) => p.demande_id) ? 'orange-darken-1' : 'teal-lighten-4'"
                                        variant="flat" class="font-weight-bold">
                                        {{ item.pieces?.length || 0 }} Composants
                                    </v-chip>
                                </template>

                                <template #item.statut="{ item }">
                                    <v-chip size="x-small"
                                        :color="item.statut === 'En panne' ? 'red-lighten-5' : 'green-lighten-5'"
                                        variant="flat" class="font-weight-bold">
                                        {{ item.statut?.toUpperCase() || "NEUF" }}
                                    </v-chip>
                                </template>

                                <template #item.etat="{ item }">
                                    <v-chip size="x-small"
                                        :prepend-icon="item.etat === 'Livré' ? 'mdi-account' : 'mdi-warehouse'"
                                        :color="item.etat === 'Livré' ? 'teal-darken-1' : 'grey-darken-1'"
                                        variant="outlined" class="font-weight-bold">
                                        {{ item.etat }}
                                    </v-chip>
                                </template>
                            </v-data-table>
                        </div>
                    </v-card>
                </v-col>

                <v-col cols="12" lg="3">
                    <v-card flat class="rounded-xl pa-3 pa-md-5 mb-4 text-center elevation-1 bg-white">
                        <div class="text-overline mb-2 text-grey-darken-1">Taux d'affectation</div>
                        <v-progress-circular :model-value="tauxOccupation" :size="90" :width="8" color="teal-darken-1">
                            <span class="text-h6 font-weight-black text-teal-darken-4">{{ tauxOccupation }}%</span>
                        </v-progress-circular>
                    </v-card>

                    <v-card flat class="rounded-xl bg-teal-darken-4 text-white pa-3 pa-md-4 elevation-2">
                        <div class="text-subtitle-2 font-weight-bold mb-3 d-flex align-center">
                            <v-icon size="small" class="mr-2">mdi-lightning-bolt</v-icon>
                            ACTIONS RAPIDES
                        </div>
                        <v-btn :component="Link" :href="route('materiel.indexmat')" block color="white"
                            class="text-teal-darken-4 mb-2 text-none font-weight-black rounded-lg"
                            prepend-icon="mdi-plus-box" size="small" size-md="default">
                            Nouvelle Réception
                        </v-btn>
                        <v-btn :component="Link" :href="route('demandes.create')" block variant="outlined" color="white"
                            class="text-none font-weight-bold rounded-lg" prepend-icon="mdi-tray-arrow-up" size="small"
                            size-md="default">
                            Affecter Matériel
                        </v-btn>
                    </v-card>
                </v-col>
            </v-row>
        </v-container>
    </AuthenticatedLayout>
</template>

<style scoped>
/* Responsive */
@media (min-width: 960px) {
    .v-col-custom-5 {
        flex: 0 0 20%;
        max-width: 20%;
    }
}

@media (max-width: 600px) {
    .stats-icon {
        display: none;
    }

    .service-select {
        width: 100%;
        max-width: none !important;
    }
}

/* Desktop small */
@media (min-width: 601px) and (max-width: 959px) {
    .stats-icon {
        font-size: 32px !important;
    }
}

.stats-icon {
    position: absolute;
    right: 8px;
    bottom: 8px;
    font-size: 40px;
    opacity: 0.15;
}

/* Border colors */
.border-success-left {
    border-left: 4px solid #2e7d32 !important;
}

.border-teal-left {
    border-left: 4px solid #00897b !important;
}

.border-red-left {
    border-left: 4px solid #d32f2f !important;
}

.border-orange-left {
    border-left: 4px solid #ef6c00 !important;
}

.service-select {
    max-width: 220px;
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
    font-size: 10px !important;
    font-weight: 800 !important;
    color: #475569 !important;
    height: 40px !important;
    padding: 0 8px !important;
}

.custom-table :deep(tbody td) {
    height: 48px !important;
    padding: 0 8px !important;
}

@media (min-width: 960px) {
    .custom-table :deep(thead th) {
        font-size: 11px !important;
        height: 48px !important;
        padding: 0 16px !important;
    }

    .custom-table :deep(tbody td) {
        height: 56px !important;
        padding: 0 16px !important;
    }
}

:deep(.v-table) {
    font-size: 11px;
}

:deep(.v-table th) {
    font-size: 10px;
    font-weight: 700;
    text-transform: uppercase;
    background-color: #f5f5f5;
}

@media (min-width: 960px) {
    :deep(.v-table) {
        font-size: 12px;
    }

    :deep(.v-table th) {
        font-size: 11px;
    }
}
</style>
