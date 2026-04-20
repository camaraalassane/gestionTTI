<script setup>
    import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
    import { Head, router } from "@inertiajs/vue3";
    import { ref, computed } from "vue";
    import axios from "axios";

    const props = defineProps({
        receptions: [Array, Object],
    });

    const search = ref("");
    const dateDebut = ref("");
    const dateFin = ref("");
    const showMaterielsDialog = ref(false);
    const showLotsDialog = ref(false);
    const loading = ref(false);
    const selectedContract = ref({ id: null, numero: "", fournisseur: "" });
    const materielsToShow = ref([]);
    const lotsToShow = ref([]);
    const currentPage = ref(1);
    const itemsPerPage = ref(5);

    // Données groupées par contrat
    const groupedByContrat = computed(() => {
        const groups = {};
        const dataToFilter = props.receptions?.data || props.receptions || [];

        const rawFiltered = dataToFilter.filter((item) => {
            const term = search.value.toLowerCase();
            const matchSearch =
                !search.value ||
                item.fournisseur?.toLowerCase().includes(term) ||
                item.numero_contrat?.toLowerCase().includes(term);
            const matchDate =
                (!dateDebut.value || item.date_livraison >= dateDebut.value) &&
                (!dateFin.value || item.date_livraison <= dateFin.value);
            return matchSearch && matchDate;
        });

        rawFiltered.forEach((item) => {
            const num = item.numero_contrat;
            if (!groups[num]) {
                groups[num] = {
                    id: item.id,
                    numero_contrat: item.numero_contrat,
                    fournisseur: item.fournisseur,
                    date_livraison: item.date_livraison,
                    scan_contrat: item.scan_contrat,
                    created_at: item.created_at,
                    all_categories: [],
                };
            }

            // CORRECTION: Ajouter les catégories depuis les réceptions si disponibles
            if (item.all_categories && Array.isArray(item.all_categories)) {
                groups[num].all_categories = [...new Set([...groups[num].all_categories, ...item.all_categories])];
            }
        });
        return Object.values(groups);
    });

    // Pagination des contrats
    const paginatedContrats = computed(() => {
        const start = (currentPage.value - 1) * itemsPerPage.value;
        const end = start + itemsPerPage.value;
        return groupedByContrat.value.slice(start, end);
    });

    const totalPages = computed(() => {
        return Math.ceil(groupedByContrat.value.length / itemsPerPage.value);
    });

    // --- FONCTION DE PAGINATION ---
    const changerPage = (page) => {
        currentPage.value = page;
    };

    // --- ACTIONS MATÉRIELS ---
    const openDetails = async (contract) => {
        selectedContract.value = {
            id: contract.id,
            numero: contract.numero_contrat,
            fournisseur: contract.fournisseur,
        };
        showMaterielsDialog.value = true;
        loading.value = true;
        materielsToShow.value = [];

        try {
            const response = await axios.get(`/docs/api/${contract.id}`);
            materielsToShow.value = response.data;
        } catch (error) {
            console.error("Erreur:", error);
        } finally {
            loading.value = false;
        }
    };

    // --- ACTION TRAÇABILITÉ ---
    const openLotsTracabilite = async (contract) => {
        selectedContract.value = {
            id: contract.id,
            numero: contract.numero_contrat,
            fournisseur: contract.fournisseur,
        };
        showLotsDialog.value = true;
        loading.value = true;
        lotsToShow.value = [];

        try {
            const response = await axios.get(`/docs/api/lots/${contract.id}`);
            lotsToShow.value = response.data;
        } catch (error) {
            console.error("Erreur lots:", error);
        } finally {
            loading.value = false;
        }
    };

    const downloadFile = (id) => window.open(route("reception.download", id), "_blank");
    const downloadPdf = (id) => id && window.open(`/docs/pdf/${id}`, "_blank");
    const imprimerUnLot = (lotId) => window.open(`/docs/pdf-lot/${lotId}`, "_blank");

    const resetFilters = () => {
        search.value = "";
        dateDebut.value = "";
        dateFin.value = "";
        currentPage.value = 1;
    };

    const printPage = () => {
        const tableElement = document.querySelector(".v-data-table");
        if (!tableElement) return;
        const printContents = tableElement.innerHTML;
        const printWindow = window.open("", "", "height=700,width=900");
        printWindow.document.write("<html><head><title>Rapport Archives</title>");
        printWindow.document.write(
            "<style>@media print { .v-data-table-footer, button, .no-print { display: none !important; } table { width: 100%; border-collapse: collapse; } th, td { border: 1px solid #e0e0e0; padding: 10px; text-align: left; } th { background-color: #00796b !important; color: white !important; -webkit-print-color-adjust: exact; } }</style></head><body>",
        );
        printWindow.document.write('<div style="text-align:center;"><h2 style="color:#00796b;">Archives Contrats & Stocks</h2>');
        printWindow.document.write(`<p>Date : ${new Date().toLocaleDateString()}</p></div>`);
        printWindow.document.write(printContents);
        printWindow.document.write("</body></html>");
        printWindow.document.close();
        setTimeout(() => {
            printWindow.print();
            printWindow.close();
        }, 500);
    };
</script>

<template>

    <Head title="Archives Contrats" />
    <AuthenticatedLayout>
        <template #header> Gestion des Archives </template>

        <v-row class="mb-6">
            <v-col cols="12">
                <v-card class="rounded-xl border-0 elevation-2 pa-2">
                    <v-row dense align="center" class="px-4 py-2">
                        <v-col cols="12" md="5">
                            <v-text-field v-model="search" prepend-inner-icon="mdi-magnify" label="Rechercher..." variant="solo-filled" flat density="comfortable" hide-details rounded="lg" color="teal-darken-1" clearable />
                        </v-col>
                        <v-col cols="6" md="2">
                            <v-text-field v-model="dateDebut" type="date" label="Depuis le" variant="outlined" density="comfortable" hide-details rounded="lg" color="teal-darken-1" />
                        </v-col>
                        <v-col cols="6" md="2">
                            <v-text-field v-model="dateFin" type="date" label="Jusqu'au" variant="outlined" density="comfortable" hide-details rounded="lg" color="teal-darken-1" />
                        </v-col>
                        <v-spacer></v-spacer>
                        <v-btn icon="mdi-refresh" variant="text" color="teal-darken-1" @click="resetFilters" />
                        <v-btn prepend-icon="mdi-printer" color="teal-darken-1" class="text-none font-weight-black rounded-lg ml-2" elevation="2" @click="printPage">
                            Imprimer
                        </v-btn>
                    </v-row>
                </v-card>
            </v-col>
        </v-row>

        <v-card class="rounded-xl border-0 elevation-2">
            <div class="table-container" style="max-height: 60vh; overflow-y: auto;">
                <v-data-table :headers="[
                    { title: 'N° CONTRAT', key: 'numero_contrat', sortable: true },
                    { title: 'FOURNISSEUR', key: 'fournisseur', sortable: true },
                    { title: 'CATÉGORIES', key: 'all_categories', sortable: false },
                    { title: 'DATE RÉCEPTION', key: 'date_livraison', align: 'center' },
                    { title: 'TRAÇABILITÉ', key: 'tracabilite', align: 'center', sortable: false },
                    { title: 'ACTIONS', key: 'actions', align: 'end', sortable: false },
                ]" :items="paginatedContrats" class="modern-table" hover hide-default-footer density="compact">
                    <template #item.numero_contrat="{ item }">
                        <span class="font-weight-black text-teal-darken-3">{{ item.numero_contrat }}</span>
                    </template>

                    <template #item.all_categories="{ item }">
                        <div class="d-flex flex-wrap gap-1">
                            <template v-if="item.all_categories && item.all_categories.length > 0">
                                <v-chip v-for="cat in item.all_categories" :key="cat" size="x-small" color="teal-lighten-5" class="text-teal-darken-3 font-weight-bold" variant="flat">
                                    {{ cat }}
                                </v-chip>
                            </template>
                            <v-chip v-else size="x-small" color="grey-lighten-3" class="text-grey-darken-2 font-weight-bold" variant="flat">
                                Non catégorisé
                            </v-chip>
                        </div>
                    </template>

                    <template #item.date_livraison="{ item }">
                        <span class="text-caption">{{ item.date_livraison ? new Date(item.date_livraison).toLocaleDateString('fr-FR') : 'Date inconnue' }}</span>
                    </template>

                    <template #item.tracabilite="{ item }">
                        <v-btn prepend-icon="mdi-layers-triple" color="teal-darken-1" variant="tonal" size="small" class="font-weight-bold rounded-lg" @click="openLotsTracabilite(item)">
                            VOIR LES LOTS
                        </v-btn>
                    </template>

                    <template #item.actions="{ item }">
                        <v-btn icon="mdi-eye-outline" variant="text" color="teal-darken-1" size="small" @click="openDetails(item)" />
                        <v-btn v-if="item.scan_contrat" icon="mdi-cloud-download-outline" variant="text" color="blue-grey-darken-1" size="small" @click="downloadFile(item.id)" />
                    </template>
                </v-data-table>
            </div>

            <!-- PAGINATION PAR CONTRAT -->
            <div v-if="totalPages > 1" class="pa-4 border-t d-flex justify-center bg-white">
                <v-pagination v-model="currentPage" :length="totalPages" @update:model-value="changerPage" :total-visible="7" color="teal-darken-3" density="comfortable" size="small" />
                <span class="text-caption text-grey ml-4">
                    {{ (currentPage - 1) * itemsPerPage + 1 }} - {{ Math.min(currentPage * itemsPerPage, groupedByContrat.length) }} sur {{ groupedByContrat.length }} contrats
                </span>
            </div>
        </v-card>

        <!-- MODAL DÉTAILS CONTRAT -->
        <v-dialog v-model="showMaterielsDialog" max-width="900px" scrollable>
            <v-card class="rounded-xl">
                <v-card-title class="bg-teal-darken-1 text-white pa-4 d-flex align-center">
                    <v-icon icon="mdi-file-document-check" class="mr-3"></v-icon>
                    <span class="font-weight-black">DÉTAILS DU CONTRAT : {{ selectedContract.numero }}</span>
                    <v-spacer></v-spacer>
                    <v-btn icon="mdi-close" variant="text" @click="showMaterielsDialog = false"></v-btn>
                </v-card-title>

                <v-card-text class="pa-0">
                    <v-progress-linear v-if="loading" indeterminate color="teal" />

                    <div v-else class="details-scroll-container" style="max-height: 60vh; overflow-y: auto;">
                        <div class="d-flex justify-space-between pa-3 bg-grey-lighten-4 border-b">
                            <div>📦 Total matériels : <strong>{{ materielsToShow.total_materiels || 0 }}</strong></div>
                            <div>📋 Total modèles : <strong>{{ materielsToShow.total_modeles || 0 }}</strong></div>
                            <div>✅ En stock : <strong class="text-green-darken-2">{{ materielsToShow.total_stock || 0 }}</strong></div>
                            <div>🚚 Sortis : <strong class="text-orange-darken-2">{{ materielsToShow.total_sorti || 0 }}</strong></div>
                        </div>

                        <v-table hover density="compact" class="details-table">
                            <thead>
                                <tr class="bg-teal-lighten-5 sticky-header">
                                    <th class="text-left">MODÈLE</th>
                                    <th class="text-center" width="150">EN STOCK (MAGASIN)</th>
                                    <th class="text-center" width="150">SORTIS</th>
                                    <th class="text-center" width="100">TOTAL</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="modele in materielsToShow.modeles" :key="modele.designation">
                                    <td class="font-weight-bold text-teal-darken-4">{{ modele.designation }}</td>
                                    <td class="text-center">
                                        <v-chip color="green-lighten-4" variant="flat" size="small" class="font-weight-bold">
                                            {{ modele.qte_stock }}
                                        </v-chip>
                                    </td>
                                    <td class="text-center">
                                        <v-chip color="orange-lighten-4" variant="flat" size="small" class="font-weight-bold">
                                            {{ modele.qte_sorti }}
                                        </v-chip>
                                    </td>
                                    <td class="text-center">
                                        <v-chip color="teal-lighten-4" variant="flat" size="small" class="font-weight-bold">
                                            {{ modele.total }}
                                        </v-chip>
                                    </td>
                                </tr>
                            </tbody>
                            <tfoot>
                                <tr class="bg-grey-lighten-5 font-weight-bold">
                                    <td class="text-right">TOTAL :</td>
                                    <td class="text-center">{{ materielsToShow.total_stock || 0 }}</td>
                                    <td class="text-center">{{ materielsToShow.total_sorti || 0 }}</td>
                                    <td class="text-center">{{ materielsToShow.total_materiels || 0 }}</td>
                                </tr>
                            </tfoot>
                        </v-table>
                    </div>
                </v-card-text>

                <v-card-actions class="pa-4 bg-grey-lighten-5">
                    <v-btn prepend-icon="mdi-file-pdf-box" color="red-darken-1" variant="flat" class="rounded-lg text-none font-weight-bold" @click="downloadPdf(selectedContract.id)">
                        Exporter PDF
                    </v-btn>
                    <v-spacer></v-spacer>
                    <v-btn color="teal-darken-1" variant="text" class="font-weight-bold" @click="showMaterielsDialog = false">
                        Fermer
                    </v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>

        <!-- MODAL TRAÇABILITÉ DES LOTS -->
        <v-dialog v-model="showLotsDialog" max-width="800px">
            <v-card class="rounded-xl">
                <v-card-title class="bg-teal-darken-4 text-white pa-4 d-flex align-center">
                    <v-icon icon="mdi-history" class="mr-3"></v-icon>
                    <span class="font-weight-black">TRAÇABILITÉ DES LOTS : {{ selectedContract.numero }}</span>
                    <v-spacer></v-spacer>
                    <v-btn icon="mdi-close" variant="text" @click="showLotsDialog = false"></v-btn>
                </v-card-title>
                <v-card-text class="pa-0">
                    <v-progress-linear v-if="loading" indeterminate color="teal" />
                    <v-table hover>
                        <thead>
                            <tr class="bg-grey-lighten-4">
                                <th class="text-teal-darken-4 font-weight-black px-6">LOT</th>
                                <th class="text-teal-darken-4 font-weight-black">DATE RÉCEPTION</th>
                                <th class="text-center text-teal-darken-4 font-weight-black">QTÉ REÇUE</th>
                                <th class="text-right text-teal-darken-4 font-weight-black px-6">ACTIONS</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="(lot, index) in lotsToShow" :key="lot.id">
                                <td class="px-6 font-weight-bold">LOT N°{{ index + 1 }}</td>
                                <td>{{ new Date(lot.date_livraison).toLocaleDateString("fr-FR") }}</td>
                                <td class="text-center">
                                    <v-chip size="small" variant="tonal" color="teal">{{ lot.quantite_recue }} articles</v-chip>
                                </td>
                                <td class="text-right px-6">
                                    <v-btn prepend-icon="mdi-printer" size="small" color="teal-darken-1" variant="flat" class="rounded-lg text-none" @click="imprimerUnLot(lot.id)">
                                        Imprimer
                                    </v-btn>
                                </td>
                            </tr>
                        </tbody>
                    </v-table>
                </v-card-text>
                <v-card-actions class="pa-4 bg-grey-lighten-5">
                    <v-spacer></v-spacer>
                    <v-btn color="teal-darken-1" variant="text" class="font-weight-bold" @click="showLotsDialog = false">Fermer</v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>
    </AuthenticatedLayout>
</template>

<style scoped>
    .table-container {
        max-height: 60vh;
        overflow-y: auto;
    }

    .table-container::-webkit-scrollbar {
        width: 6px;
    }

    .table-container::-webkit-scrollbar-track {
        background: #f1f1f1;
        border-radius: 10px;
    }

    .table-container::-webkit-scrollbar-thumb {
        background: #c1c1c1;
        border-radius: 10px;
    }

    .modern-table :deep(thead th) {
        background-color: #f0fdfa !important;
        font-size: 0.75rem !important;
        font-weight: 900 !important;
        color: #00695c !important;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .details-table :deep(thead th) {
        font-size: 0.7rem !important;
    }

    .italic {
        font-style: italic;
    }

    .gap-1 {
        gap: 4px;
    }

    :deep(.v-data-table-footer) {
        border-top: 1px solid #f1f5f9;
    }

    :deep(.v-field--variant-solo-filled) {
        background: #f8fafc !important;
    }
</style>
