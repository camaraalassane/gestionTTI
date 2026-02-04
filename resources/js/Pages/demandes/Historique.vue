<script setup lang="ts">
import { ref, computed, watch } from "vue";
import { Head, router } from "@inertiajs/vue3";
import AuthentDemandeLayout from "@/Layouts/AuthentDemandeLayout.vue";
import debounce from "lodash/debounce";

// Interfaces
interface PieceItem {
    id: number;
    nom_piece: string;
    numero_serie: string;
}

interface HistoriqueItem {
    id: number;
    numcomande: string;
    nom_materiel: string;
    numero_serie?: string;
    nbredemande: number;
    date_demande: string;
    service_beneficiaire: string;
    demandeur_nom: string;
    description?: string;
    pieces?: PieceItem[];
    a_des_pieces_au_total?: boolean;
}

const props = defineProps<{
    historique: {
        data: HistoriqueItem[];
        current_page: number;
        last_page: number;
        total: number;
    };
    services: { id: number; nom: string }[];
    filters: { search?: string };
}>();

const serviceFiltre = ref<string | null>(null);
const recherche = ref(props.filters.search || "");

watch(
    recherche,
    debounce((value) => {
        router.get(
            route("demandes.historique"),
            { search: value, page: 1 }, // Ajout de page: 1 pour reset la recherche
            { preserveState: true, replace: true },
        );
    }, 400),
);

const historiqueGroupe = computed(() => {
    const rawData = props.historique?.data || [];
    const filteredData = rawData.filter(
        (h) =>
            !serviceFiltre.value ||
            h.service_beneficiaire === serviceFiltre.value,
    );

    const groups: Record<
        string,
        Record<string, Record<string, HistoriqueItem[]>>
    > = {};
    filteredData.forEach((item) => {
        const d = item.date_demande || "Date Inconnue";
        const s = item.service_beneficiaire || "Service non défini";
        const u = item.demandeur_nom || "Sans nom";
        if (!groups[d]) groups[d] = {};
        if (!groups[d][s]) groups[d][s] = {};
        if (!groups[d][s][u]) groups[d][s][u] = [];
        groups[d][s][u].push(item);
    });
    return groups;
});

const formatDate = (dateStr: string) => {
    if (!dateStr || dateStr === "Date Inconnue") return "Date Inconnue";
    return new Date(dateStr).toLocaleDateString("fr-FR", {
        weekday: "long",
        year: "numeric",
        month: "long",
        day: "numeric",
    });
};

const allerAuBon = (service: string, date: string, demandeur: string) => {
    router.get(route("demandes.imprimer_bon", { service }), {
        date,
        demandeur,
    });
};

const changerPage = (page: number) => {
    router.get(
        route("demandes.historique"),
        { page: page, search: recherche.value }, // Envoi de la page correcte au serveur
        { preserveState: true, preserveScroll: true },
    );
};
</script>

<template>
    <Head title="Historique des Sorties" />
    <AuthentDemandeLayout>
        <v-container fluid class="pa-6 bg-grey-lighten-4 min-vh-100">
            <v-row class="mb-8 align-center">
                <v-col cols="12" md="4">
                    <div class="d-flex align-center">
                        <v-icon
                            size="40"
                            color="blue-grey-darken-3"
                            class="mr-3"
                            >mdi-folder-clock-outline</v-icon
                        >
                        <div>
                            <h1
                                class="text-h5 font-weight-bold text-blue-grey-darken-4 mb-0"
                            >
                                Historique
                            </h1>
                            <span
                                class="text-caption text-grey-darken-1 font-italic"
                                >Consultation des archives</span
                            >
                        </div>
                    </div>
                </v-col>
                <v-col cols="12" md="4">
                    <v-autocomplete
                        v-model="serviceFiltre"
                        :items="services"
                        item-title="nom"
                        item-value="nom"
                        label="Filtrer par Unité"
                        variant="solo"
                        flat
                        density="comfortable"
                        prepend-inner-icon="mdi-filter-variant"
                        hide-details
                        clearable
                    ></v-autocomplete>
                </v-col>
                <v-col cols="12" md="4">
                    <v-text-field
                        v-model="recherche"
                        label="Rechercher un bon..."
                        variant="solo"
                        flat
                        density="comfortable"
                        prepend-inner-icon="mdi-magnify"
                        hide-details
                        clearable
                    ></v-text-field>
                </v-col>
            </v-row>

            <div
                v-for="(servicesObj, date) in historiqueGroupe"
                :key="date"
                class="mb-10"
            >
                <div class="d-flex align-center mb-6">
                    <div
                        class="text-subtitle-1 font-weight-black text-blue-grey-darken-3 d-flex align-center"
                    >
                        <v-icon start size="20" class="mr-2"
                            >mdi-calendar-text</v-icon
                        >
                        {{ formatDate(date as string).toUpperCase() }}
                    </div>
                    <v-divider class="ml-4 opacity-20"></v-divider>
                </div>

                <div
                    v-for="(demandeurs, serviceName) in servicesObj"
                    :key="serviceName"
                    class="mb-4"
                >
                    <v-card
                        v-for="(items, demandeurNom) in demandeurs"
                        :key="demandeurNom"
                        class="mb-4 rounded-lg border-thin"
                        elevation="0"
                    >
                        <div
                            class="d-flex align-center pa-3 bg-grey-lighten-5 border-b"
                        >
                            <v-icon size="18" class="mr-2 text-grey-darken-2"
                                >mdi-office-building-marker</v-icon
                            >
                            <span
                                class="text-body-2 font-weight-bold text-blue-grey-darken-4"
                            >
                                {{ serviceName }}
                                <span class="text-grey mx-2">|</span>
                                <v-icon size="16" class="mr-1"
                                    >mdi-account</v-icon
                                >{{ demandeurNom }}
                            </span>
                            <v-spacer></v-spacer>
                            <v-btn
                                size="small"
                                variant="outlined"
                                color="blue-grey-darken-2"
                                prepend-icon="mdi-file-eye-outline"
                                class="text-none font-weight-bold bg-white"
                                @click="
                                    allerAuBon(
                                        serviceName as string,
                                        date as string,
                                        demandeurNom as string,
                                    )
                                "
                            >
                                Voir le bon
                            </v-btn>
                        </div>

                        <v-table density="compact" class="custom-table">
                            <thead>
                                <tr>
                                    <th width="120">N° Commande</th>
                                    <th>Désignation des matériels</th>
                                    <th>N° de Série</th>
                                    <th class="text-center" width="80">Qté</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="item in items"
                                    :key="item.id"
                                    class="table-row"
                                >
                                    <td class="text-caption font-weight-bold">
                                        #{{ item.numcomande }}
                                    </td>
                                    <td class="py-2">
                                        <template
                                            v-if="
                                                !item.nom_materiel ||
                                                item.description
                                                    ?.toUpperCase()
                                                    .includes('SORTIE PIÈCES')
                                            "
                                        >
                                            <div
                                                class="text-body-2 text-blue-grey-darken-2"
                                            >
                                                <v-icon size="14" class="mr-1"
                                                    >mdi-package-variant</v-icon
                                                >
                                                {{
                                                    item.pieces
                                                        ?.map(
                                                            (p) => p.nom_piece,
                                                        )
                                                        .join(", ") || "N/A"
                                                }}
                                                <span
                                                    class="text-caption text-grey-darken-1 ml-1"
                                                    >(Composants)</span
                                                >
                                            </div>
                                        </template>
                                        <template v-else>
                                            <div
                                                class="text-body-2 font-weight-bold text-grey-darken-4"
                                            >
                                                {{ item.nom_materiel }}
                                            </div>
                                            <div
                                                v-if="
                                                    item.pieces &&
                                                    item.pieces.length > 0
                                                "
                                                class="text-caption text-grey-darken-1"
                                            >
                                                Accompagné de :
                                                {{
                                                    item.pieces
                                                        .map((p) => p.nom_piece)
                                                        .join(", ")
                                                }}
                                            </div>

                                            <div
                                                v-else-if="
                                                    item.a_des_pieces_au_total
                                                "
                                                class="text-caption text-orange-darken-3 font-weight-bold"
                                            >
                                                ⚠ Matériel seul (Sans pièces)
                                            </div>
                                            <div
                                                v-else
                                                class="text-caption text-blue-grey"
                                            >
                                                {{
                                                    item.description
                                                        ?.toUpperCase()
                                                        .includes("INCOMPLET")
                                                        ? "⚠ État Incomplet"
                                                        : "✓ État Complet"
                                                }}
                                            </div>
                                        </template>
                                    </td>
                                    <td>
                                        <div
                                            v-if="item.numero_serie"
                                            class="text-caption font-weight-medium"
                                        >
                                            <v-icon size="12" class="mr-1"
                                                >mdi-barcode</v-icon
                                            >{{ item.numero_serie }}
                                        </div>
                                        <div
                                            v-for="p in item.pieces"
                                            :key="'sn-' + p.id"
                                            class="text-caption text-grey"
                                        >
                                            <v-icon size="10"
                                                >mdi-subdirectory-arrow-right</v-icon
                                            >
                                            {{ p.numero_serie }}
                                        </div>
                                    </td>
                                    <td
                                        class="text-center font-weight-bold text-body-2"
                                    >
                                        {{ item.nbredemande }}
                                    </td>
                                </tr>
                            </tbody>
                        </v-table>
                    </v-card>
                </div>
            </div>

            <v-pagination
                v-if="props.historique.last_page > 1"
                v-model="props.historique.current_page"
                :length="props.historique.last_page"
                @update:model-value="changerPage"
                color="blue-grey-darken-3"
                density="comfortable"
                class="mt-6"
            ></v-pagination>

            <v-card
                v-if="Object.keys(historiqueGroupe).length === 0"
                class="pa-12 text-center rounded-lg"
                variant="outlined"
                border
            >
                <v-icon size="64" color="grey-lighten-1"
                    >mdi-tray-search</v-icon
                >
                <div class="text-h6 text-grey mt-4">
                    Aucune archive ne correspond à votre recherche
                </div>
            </v-card>
        </v-container>
    </AuthentDemandeLayout>
</template>

<style scoped>
.custom-table {
    border-radius: 0 !important;
}

.custom-table :deep(th) {
    background-color: #ffffff !important;
    text-transform: uppercase !important;
    font-size: 0.65rem !important;
    font-weight: 800 !important;
    color: #455a64 !important;
    border-bottom: 1px solid #eceff1 !important;
}

.table-row:hover {
    background-color: #fafafa;
}

.border-thin {
    border: 1px solid #e0e0e0 !important;
}

/* Scrollbar discrète */
::-webkit-scrollbar {
    width: 5px;
}
::-webkit-scrollbar-thumb {
    background: #cfd8dc;
    border-radius: 10px;
}
</style>
