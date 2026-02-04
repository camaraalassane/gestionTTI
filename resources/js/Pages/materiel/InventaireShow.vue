<script setup lang="ts">
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import { Head, router } from "@inertiajs/vue3";
import { ref } from "vue";

const props = defineProps<{
    inventaire: any;
    details: any[];
}>();

const route = (window as any).route;
const search = ref("");

const headers = [
    {
        title: "DÉSIGNATION DU MATÉRIEL", // On enlève "/ PIÈCES" ici car c'est déjà dans le badge
        key: "designation",
        align: "start" as const,
        sortable: true,
    },
    {
        title: "N° SÉRIE",
        key: "numero_serie",
        align: "start" as const,
        sortable: true,
    },
    {
        title: "CATÉGORIE",
        key: "categorie",
        align: "start" as const,
        sortable: true,
    },
    {
        title: "ÉTAT LOGIQUE",
        key: "etat_materiel",
        align: "center" as const,
        sortable: true,
    },
    {
        title: "INTÉGRITÉ",
        key: "est_complet",
        align: "center" as const,
        sortable: true,
    },
    {
        title: "LOCALISATION / SORTIE",
        key: "localisation",
        align: "start" as const,
        sortable: true,
    },
] as const;

const groupBy = [{ key: "fournisseur", order: "asc" as const }];

const retour = () => router.visit(route("inventaire.index"));
const telechargerPDF = () => {
    if (props.inventaire?.id)
        window.open(route("inventaire.pdf", props.inventaire.id), "_blank");
};
const imprimerPage = () => window.print();
</script>

<template>
    <Head :title="'Détails Archive ' + (inventaire?.annee || '')" />
    <AuthenticatedLayout>
        <v-toolbar
            color="white"
            flat
            border
            density="comfortable"
            class="no-print"
        >
            <v-btn
                prepend-icon="mdi-arrow-left"
                variant="text"
                color="teal-darken-3"
                class="rounded-lg font-weight-bold ml-2"
                @click="retour"
                >Retour</v-btn
            >
            <v-divider vertical inset class="mx-4"></v-divider>
            <v-toolbar-title
                class="font-weight-black text-teal-darken-4 text-subtitle-1"
            >
                ARCHIVE INVENTAIRE {{ inventaire?.annee }}
            </v-toolbar-title>
            <v-spacer></v-spacer>
            <v-btn
                prepend-icon="mdi-printer"
                variant="outlined"
                color="teal-darken-1"
                class="rounded-lg font-weight-bold mr-2"
                @click="imprimerPage"
                >Imprimer</v-btn
            >
            <v-btn
                prepend-icon="mdi-file-pdf-box"
                color="teal-darken-3"
                variant="flat"
                class="rounded-lg font-weight-bold mr-2"
                @click="telechargerPDF"
                >Exporter PDF</v-btn
            >
        </v-toolbar>

        <v-container
            fluid
            class="pa-4 main-container bg-teal-lighten-5 min-vh-100"
        >
            <div class="print-only-header">
                <table
                    width="100%"
                    style="border-bottom: 2px solid black; margin-bottom: 20px"
                >
                    <tr>
                        <td>
                            <h2 style="margin: 0">
                                INVENTAIRE MATÉRIEL : {{ inventaire?.annee }}
                            </h2>
                        </td>
                        <td align="right">
                            Édité le : {{ new Date().toLocaleDateString() }}
                        </td>
                    </tr>
                </table>
            </div>

            <v-card
                border
                flat
                class="rounded-xl shadow-sm overflow-hidden d-flex flex-column main-card-container bg-white"
            >
                <v-card-title
                    class="pa-4 border-b d-flex align-center flex-shrink-0 no-print"
                >
                    <v-icon
                        icon="mdi-database-search"
                        class="mr-2"
                        color="teal-darken-1"
                        size="small"
                    />
                    <span
                        class="text-caption font-weight-black text-uppercase text-teal-darken-3"
                        >Contenu archivé</span
                    >
                    <v-spacer></v-spacer>
                    <v-text-field
                        v-model="search"
                        prepend-inner-icon="mdi-magnify"
                        label="Rechercher..."
                        variant="solo-filled"
                        density="compact"
                        hide-details
                        rounded="lg"
                        flat
                        bg-color="teal-lighten-5"
                        class="search-bar mr-4"
                    />
                    <v-chip
                        color="teal-darken-3"
                        variant="flat"
                        size="small"
                        class="font-weight-bold"
                        >{{ details?.length || 0 }} ÉLÉMENTS</v-chip
                    >
                </v-card-title>

                <div class="flex-grow-1 overflow-y-auto">
                    <v-data-table
                        :headers="headers"
                        :items="details"
                        :search="search"
                        :group-by="groupBy"
                        :items-per-page="-1"
                        hover
                        fixed-header
                        class="custom-table"
                        hide-default-footer
                    >
                        <template #[`item.designation`]="{ item }">
                            <div class="py-2">
                                <div class="d-flex align-center">
                                    <div
                                        class="font-weight-black text-teal-darken-4 text-uppercase"
                                    >
                                        {{ item.designation }}
                                    </div>

                                    <v-chip
                                        v-if="
                                            item.pieces &&
                                            item.pieces.length > 0
                                        "
                                        size="x-small"
                                        variant="flat"
                                        color="teal-lighten-4"
                                        class="ml-2 font-weight-black text-teal-darken-4 px-2"
                                        style="height: 18px"
                                    >
                                        {{ item.pieces.length }} PIÈCE{{
                                            item.pieces.length > 1 ? "S" : ""
                                        }}
                                    </v-chip>
                                </div>

                                <div
                                    v-if="item.pieces && item.pieces.length > 0"
                                    class="mt-2 pl-2"
                                >
                                    <div
                                        v-for="piece in item.pieces"
                                        :key="piece.id"
                                        class="text-caption d-flex align-center mb-1"
                                    >
                                        <v-icon
                                            icon="mdi-subdirectory-arrow-right"
                                            size="12"
                                            class="mr-1 text-teal"
                                        />
                                        <span class="mr-2">{{
                                            piece.nom
                                        }}</span>
                                        <v-chip
                                            size="x-small"
                                            :color="
                                                piece.demande
                                                    ? 'blue-grey-darken-2'
                                                    : item.demande
                                                      ? 'blue-grey-lighten-1'
                                                      : 'teal-lighten-3'
                                            "
                                            variant="flat"
                                            class="px-1"
                                            style="height: 16px; font-size: 9px"
                                        >
                                            {{
                                                piece.demande
                                                    ? piece.demande.service
                                                    : item.demande
                                                      ? item.demande.service
                                                      : "MAGASIN"
                                            }}
                                        </v-chip>
                                    </div>
                                </div>
                            </div>
                        </template>

                        <template #[`item.numero_serie`]="{ item }">
                            <code
                                class="text-body-2 font-weight-bold bg-teal-lighten-5 text-teal-darken-3 px-2 py-1 rounded"
                                >{{ item.numero_serie }}</code
                            >
                        </template>

                        <template #[`item.etat_materiel`]="{ item }">
                            <v-chip
                                size="x-small"
                                :color="
                                    item.etat_materiel === 'Bon'
                                        ? 'teal-darken-1'
                                        : 'orange-darken-2'
                                "
                                variant="flat"
                                class="font-weight-black"
                                >{{ item.etat_materiel }}</v-chip
                            >
                        </template>
                        <template #[`item.est_complet`]="{ item }">
                            <div class="d-flex align-center justify-center">
                                <v-icon
                                    :icon="
                                        item.est_complet == 0 &&
                                        (!item.pieces ||
                                            item.pieces.length === 0)
                                            ? 'mdi-alert-circle'
                                            : 'mdi-check-circle'
                                    "
                                    :color="
                                        item.est_complet == 0 &&
                                        (!item.pieces ||
                                            item.pieces.length === 0)
                                            ? 'red-darken-2'
                                            : 'teal-darken-1'
                                    "
                                    size="small"
                                    class="mr-1"
                                />
                                <span
                                    :class="
                                        item.est_complet == 0 &&
                                        (!item.pieces ||
                                            item.pieces.length === 0)
                                            ? 'text-red-darken-2'
                                            : 'text-teal-darken-3'
                                    "
                                    class="text-caption font-weight-bold"
                                >
                                    {{
                                        item.est_complet == 0 &&
                                        (!item.pieces ||
                                            item.pieces.length === 0)
                                            ? "Incomplet"
                                            : "Complet"
                                    }}
                                </span>
                            </div>
                        </template>

                        <template #[`item.localisation`]="{ item }">
                            <div
                                v-if="item.demande"
                                class="d-flex flex-column py-1"
                            >
                                <div
                                    class="text-caption font-weight-black text-teal-darken-4 text-uppercase d-flex align-center"
                                >
                                    <v-icon
                                        icon="mdi-account"
                                        size="14"
                                        class="mr-1"
                                        color="teal-darken-1"
                                    />
                                    {{ item.demande.demandeur }}
                                </div>
                                <div
                                    class="text-caption text-orange-darken-4 font-weight-bold d-flex align-center"
                                >
                                    <v-icon
                                        icon="mdi-map-marker"
                                        size="12"
                                        class="mr-1"
                                    />
                                    LIVRÉ : {{ item.demande.service }}
                                </div>
                            </div>
                            <v-chip
                                v-else
                                size="x-small"
                                color="teal-darken-1"
                                variant="flat"
                                class="font-weight-bold"
                                >AU MAGASIN</v-chip
                            >
                        </template>

                        <template
                            v-slot:group-header="{
                                item,
                                columns,
                                toggleGroup,
                                isGroupOpen,
                            }"
                        >
                            <tr class="bg-teal-lighten-5 group-row">
                                <td :colspan="columns.length">
                                    <v-btn
                                        :icon="
                                            isGroupOpen(item)
                                                ? 'mdi-chevron-down'
                                                : 'mdi-chevron-right'
                                        "
                                        size="small"
                                        variant="text"
                                        color="teal-darken-4"
                                        @click="toggleGroup(item)"
                                        class="no-print"
                                    ></v-btn>
                                    <span
                                        class="font-weight-black text-uppercase text-teal-darken-4"
                                    >
                                        FOURNISSEUR :
                                        {{ item.value || "Non renseigné" }}
                                    </span>
                                    <span
                                        class="print-only"
                                        style="margin-left: 20px"
                                    >
                                        - CONTRAT :
                                        {{
                                            item.items[0]?.raw
                                                ?.numero_contrat || "N/A"
                                        }}
                                    </span>
                                </td>
                            </tr>
                        </template>
                    </v-data-table>

                    <div class="print-only-signatures">
                        <table
                            style="
                                width: 100%;
                                border-collapse: collapse;
                                margin-top: 20px;
                            "
                        >
                            <tr>
                                <td
                                    style="
                                        width: 48%;
                                        border: 1px solid black;
                                        height: 80px;
                                        padding: 10px;
                                        vertical-align: top;
                                    "
                                >
                                    <strong>Le Responsable Magasin :</strong>
                                    <br /><br /><br />
                                    <small
                                        >Signature :
                                        ..........................................</small
                                    >
                                </td>
                                <td style="width: 4%"></td>
                                <td
                                    style="
                                        width: 48%;
                                        border: 1px solid black;
                                        height: 80px;
                                        padding: 10px;
                                        vertical-align: top;
                                    "
                                >
                                    <strong>Audit / Direction :</strong>
                                    <br /><br />
                                    <small
                                        >Observations :
                                        .....................................</small
                                    >
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>
            </v-card>
        </v-container>
    </AuthenticatedLayout>
</template>
<style scoped>
/* --- STYLES ÉCRAN (Inchangés) --- */
.main-card-container {
    height: calc(100vh - 160px) !important;
}
.search-bar {
    max-width: 350px;
}
.custom-table :deep(thead th) {
    background-color: #f0fdfa !important;
    font-size: 0.75rem !important;
    font-weight: 900 !important;
    color: #0d9488 !important;
}

.print-only-header,
.print-only-signatures,
.print-only {
    display: none;
}

/* --- LOGIQUE IMPRESSION --- */
@media print {
    /* Suppression des marges par défaut du navigateur pour éviter le débordement */
    @page {
        margin: 1cm;
    }

    .no-print {
        display: none !important;
    }

    .print-only-header {
        display: block !important;
    }

    /* Fixer les signatures en bas de page */
    .print-only-signatures {
        display: block !important;
        position: fixed; /* Reste en bas de la zone d'impression */
        bottom: 0;
        left: 0;
        right: 0;
        width: 100%;
        background: white;
    }

    .print-only {
        display: inline-block !important;
    }

    /* Ajustement du container pour éviter que les bordures débordent */
    .main-container {
        background-color: white !important;
        padding: 0 !important;
        margin: 0 !important;
        width: 100% !important;
        max-width: 100% !important;
    }

    .main-card-container {
        height: auto !important;
        overflow: visible !important;
        border: none !important;
        background-color: white !important;
        /* On laisse de l'espace en bas pour ne pas chevaucher les signatures fixes */
        margin-bottom: 120px !important;
    }

    /* Masquer entête et données matérielles */
    .custom-table :deep(thead) {
        display: none !important;
    }
    .custom-table :deep(tbody tr:not(.group-row)) {
        display: none !important;
    }

    .custom-table :deep(table) {
        border-collapse: collapse !important;
        width: 100% !important; /* Force le tableau à rester dans les limites */
        table-layout: fixed; /* Évite le débordement horizontal */
    }

    .custom-table :deep(td) {
        border: 1px solid #000 !important;
        padding: 10px !important;
        font-size: 14px !important;
        color: black !important;
        word-wrap: break-word; /* Coupe le texte si trop long */
    }

    :deep(.v-row-group__header) {
        background-color: white !important;
        color: black !important;
        font-weight: bold !important;
    }

    * {
        color: black !important;
    }

    tr {
        page-break-inside: avoid !important;
    }
}
</style>
