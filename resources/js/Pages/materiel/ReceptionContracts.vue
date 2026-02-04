<script setup>
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import { Head } from "@inertiajs/vue3";
import { ref, computed } from "vue";
import axios from "axios";

const props = defineProps({
    receptions: Array,
});

const search = ref("");
const dateDebut = ref("");
const dateFin = ref("");
const showMaterielsDialog = ref(false);
const loading = ref(false);
const selectedContract = ref({ id: null, numero: "", fournisseur: "" });
const materielsToShow = ref([]);

/** * LOGIQUE DE DONNÉES (STRICTEMENT INTACTE)
 */
const filteredAndGroupedReceptions = computed(() => {
    const groups = {};
    const rawFiltered = (props.receptions || []).filter((item) => {
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
                ...item,
                all_categories: item.categorie?.nom ? [item.categorie.nom] : [],
            };
        } else if (
            item.categorie?.nom &&
            !groups[num].all_categories.includes(item.categorie.nom)
        ) {
            groups[num].all_categories.push(item.categorie.nom);
        }
    });
    return Object.values(groups);
});

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

const downloadFile = (id) =>
    window.open(route("reception.download", id), "_blank");
const resetFilters = () => {
    search.value = "";
    dateDebut.value = "";
    dateFin.value = "";
};
const downloadPdf = (id) => {
    if (!id) return;
    window.open(`/docs/pdf/${id}`, "_blank");
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
    printWindow.document.write(
        '<div style="text-align:center;"><h2 style="color:#00796b;">Archives Contrats & Stocks</h2>',
    );
    printWindow.document.write(
        `<p>Date : ${new Date().toLocaleDateString()}</p></div>`,
    );
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
                            <v-text-field
                                v-model="search"
                                prepend-inner-icon="mdi-magnify"
                                label="Rechercher un fournisseur ou contrat..."
                                variant="solo-filled"
                                flat
                                density="comfortable"
                                hide-details
                                rounded="lg"
                                color="teal-darken-1"
                            ></v-text-field>
                        </v-col>
                        <v-col cols="6" md="2">
                            <v-text-field
                                v-model="dateDebut"
                                type="date"
                                label="Depuis le"
                                variant="outlined"
                                density="comfortable"
                                hide-details
                                rounded="lg"
                                color="teal-darken-1"
                            ></v-text-field>
                        </v-col>
                        <v-col cols="6" md="2">
                            <v-text-field
                                v-model="dateFin"
                                type="date"
                                label="Jusqu'au"
                                variant="outlined"
                                density="comfortable"
                                hide-details
                                rounded="lg"
                                color="teal-darken-1"
                            ></v-text-field>
                        </v-col>
                        <v-spacer></v-spacer>
                        <v-btn
                            icon="mdi-refresh"
                            variant="text"
                            color="teal-darken-1"
                            @click="resetFilters"
                        ></v-btn>
                        <v-btn
                            prepend-icon="mdi-printer"
                            color="teal-darken-1"
                            class="text-none font-weight-black rounded-lg ml-2"
                            elevation="2"
                            @click="printPage"
                        >
                            Imprimer
                        </v-btn>
                    </v-row>
                </v-card>
            </v-col>
        </v-row>

        <v-card class="rounded-xl border-0 elevation-2">
            <v-data-table
                :headers="[
                    {
                        title: 'N° CONTRAT',
                        key: 'numero_contrat',
                        sortable: true,
                    },
                    {
                        title: 'FOURNISSEUR',
                        key: 'fournisseur',
                        sortable: true,
                    },
                    {
                        title: 'CATÉGORIES',
                        key: 'all_categories',
                        sortable: false,
                    },
                    {
                        title: 'DATE RÉCEPTION',
                        key: 'date_livraison',
                        align: 'center',
                    },
                    {
                        title: 'ACTIONS',
                        key: 'actions',
                        align: 'end',
                        sortable: false,
                    },
                ]"
                :items="filteredAndGroupedReceptions"
                class="modern-table"
                hover
            >
                <template #[`item.numero_contrat`]="{ item }">
                    <span class="font-weight-black text-teal-darken-3">{{
                        item.numero_contrat
                    }}</span>
                </template>

                <template #[`item.all_categories`]="{ item }">
                    <v-chip
                        v-for="cat in item.all_categories"
                        :key="cat"
                        size="x-small"
                        color="teal-lighten-5"
                        class="mr-1 text-teal-darken-3 font-weight-bold"
                        variant="flat"
                    >
                        {{ cat }}
                    </v-chip>
                </template>

                <template #[`item.actions`]="{ item }">
                    <v-btn
                        icon="mdi-eye-outline"
                        variant="text"
                        color="teal-darken-1"
                        size="small"
                        @click="openDetails(item)"
                    ></v-btn>
                    <v-btn
                        v-if="item.scan_contrat"
                        icon="mdi-cloud-download-outline"
                        variant="text"
                        color="blue-grey-darken-1"
                        size="small"
                        @click="downloadFile(item.id)"
                    ></v-btn>
                </template>
            </v-data-table>
        </v-card>

        <v-dialog v-model="showMaterielsDialog" max-width="1000px" scrollable>
            <v-card class="rounded-xl">
                <v-card-title
                    class="bg-teal-darken-1 text-white pa-4 d-flex align-center"
                >
                    <v-icon
                        icon="mdi-file-document-check"
                        class="mr-3"
                    ></v-icon>
                    <span class="font-weight-black"
                        >DÉTAILS DU CONTRAT :
                        {{ selectedContract.numero }}</span
                    >
                    <v-spacer></v-spacer>
                    <v-btn
                        icon="mdi-close"
                        variant="text"
                        @click="showMaterielsDialog = false"
                    ></v-btn>
                </v-card-title>

                <v-card-text class="pa-0">
                    <v-progress-linear
                        v-if="loading"
                        indeterminate
                        color="teal"
                    ></v-progress-linear>
                    <v-table hover density="comfortable" class="details-table">
                        <thead>
                            <tr class="bg-teal-lighten-5">
                                <th
                                    class="text-teal-darken-4 font-weight-black"
                                >
                                    DÉSIGNATION
                                </th>
                                <th
                                    class="text-center text-teal-darken-4 font-weight-black"
                                >
                                    N° SÉRIE
                                </th>
                                <th
                                    class="text-center text-teal-darken-4 font-weight-black"
                                >
                                    ÉTAT
                                </th>
                                <th
                                    class="text-center text-teal-darken-4 font-weight-black"
                                >
                                    STATUT
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <template
                                v-for="mat in materielsToShow"
                                :key="mat.id"
                            >
                                <tr class="bg-grey-lighten-4">
                                    <td
                                        class="font-weight-bold text-teal-darken-2"
                                    >
                                        <v-icon
                                            :icon="
                                                mat.est_complet
                                                    ? 'mdi-check-circle'
                                                    : 'mdi-alert'
                                            "
                                            :color="
                                                mat.est_complet
                                                    ? 'teal'
                                                    : 'orange'
                                            "
                                            size="small"
                                            class="mr-2"
                                        ></v-icon>
                                        {{ mat.nom }}
                                    </td>
                                    <td class="text-center font-weight-medium">
                                        {{ mat.numero_serie }}
                                    </td>
                                    <td class="text-center">
                                        <v-chip
                                            size="x-small"
                                            variant="outlined"
                                            color="teal"
                                            >{{ mat.etat }}</v-chip
                                        >
                                    </td>
                                    <td class="text-center">
                                        <v-chip
                                            size="x-small"
                                            :color="
                                                mat.est_complet
                                                    ? 'teal-darken-1'
                                                    : 'orange-darken-2'
                                            "
                                            class="text-white font-weight-black"
                                        >
                                            {{ mat.statut }}
                                            {{
                                                !mat.est_complet
                                                    ? "(INCOMPLET)"
                                                    : ""
                                            }}
                                        </v-chip>
                                    </td>
                                </tr>
                                <tr
                                    v-for="piece in mat.pieces"
                                    :key="piece.id"
                                    class="text-grey-darken-1"
                                >
                                    <td class="pl-10 text-caption">
                                        <v-icon
                                            icon="mdi-subdirectory-arrow-right"
                                            size="14"
                                            class="mr-2"
                                        ></v-icon>
                                        {{ piece.nom_piece }}
                                    </td>
                                    <td class="text-center text-caption italic">
                                        {{ piece.numero_serie || "---" }}
                                    </td>
                                    <td class="text-center text-caption">
                                        Composant
                                    </td>
                                    <td class="text-center">
                                        <v-chip
                                            size="x-small"
                                            :color="
                                                piece.statut === 'En Stock'
                                                    ? 'success'
                                                    : 'grey'
                                            "
                                            variant="text"
                                            class="font-weight-bold"
                                        >
                                            ● {{ piece.statut }}
                                        </v-chip>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </v-table>
                </v-card-text>

                <v-card-actions class="pa-4 bg-grey-lighten-5">
                    <v-btn
                        prepend-icon="mdi-file-pdf-box"
                        color="red-darken-1"
                        variant="flat"
                        class="rounded-lg text-none font-weight-bold"
                        @click="downloadPdf(selectedContract.id)"
                        >Exporter PDF</v-btn
                    >
                    <v-spacer></v-spacer>
                    <v-btn
                        color="teal-darken-1"
                        variant="text"
                        class="font-weight-bold"
                        @click="showMaterielsDialog = false"
                        >Fermer</v-btn
                    >
                </v-card-actions>
            </v-card>
        </v-dialog>
    </AuthenticatedLayout>
</template>

<style scoped>
.modern-table :deep(thead th) {
    background-color: #f0fdfa !important; /* teal-lighten-5 */
    font-size: 0.75rem !important;
    font-weight: 900 !important;
    color: #00695c !important; /* teal-darken-3 */
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.details-table :deep(thead th) {
    font-size: 0.7rem !important;
}

.italic {
    font-style: italic;
}

:deep(.v-data-table-footer) {
    border-top: 1px solid #f1f5f9;
}

:deep(.v-field--variant-solo-filled) {
    background: #f8fafc !important;
}
</style>
