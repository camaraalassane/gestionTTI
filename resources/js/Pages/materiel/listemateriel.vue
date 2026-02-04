<script setup lang="ts">
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import { Head, router } from "@inertiajs/vue3";
import { ref, watch } from "vue";
import debounce from "lodash/debounce";

// INTERFACES (Intactes)
interface Piece {
    id: number;
    nom_piece: string;
    demande_id?: number | null;
    demande?: { service_beneficiaire: string; demandeur_nom: string };
}

interface Materiel {
    id: number;
    nom: string;
    numero_serie: string;
    etat: string;
    demande_id?: number | null;
    demande?: { service_beneficiaire: string; demandeur_nom: string };
    pieces?: Piece[];
}

const props = defineProps<{
    materiels: { data: Materiel[]; current_page: number; last_page: number };
    filters: any;
    stats: {
        total: number;
        disponible: number;
        livres: number;
        pieces_sorties: number;
    };
}>();

const headers = [
    { title: "MATÉRIEL", key: "nom", align: "start", width: "200px" },
    { title: "S/N", key: "numero_serie", align: "start" },
    { title: "SERVICE BÉNÉFICIAIRE", key: "service", align: "start" },
    { title: "DEMANDEUR", key: "demandeur", align: "start" },
    { title: "COMPOSANTS & AFFECTATIONS", key: "col_pieces", align: "start" },
    { title: "ÉTAT", key: "etat", align: "center" },
    { title: "ACTIONS", key: "actions", align: "end" },
] as const;

// LOGIQUE DE RECHERCHE (Intacte)
const search = ref(props.filters?.search || "");
watch(
    search,
    debounce((val) => {
        router.get(
            route("materiel.indexmat"),
            { search: val },
            { preserveState: true },
        );
    }, 500),
);
</script>

<template>
    <Head title="Gestion du Matériel" />
    <AuthenticatedLayout>
        <template #header> Inventaire du Matériel </template>

        <v-container fluid class="pa-0">
            <v-row class="mb-6">
                <v-col cols="12" sm="6" md="3">
                    <v-card
                        elevation="0"
                        border
                        class="rounded-xl pa-5 bg-white"
                    >
                        <div class="d-flex align-center mb-2">
                            <v-avatar
                                color="teal-lighten-5"
                                size="32"
                                class="mr-2"
                            >
                                <v-icon
                                    icon="mdi-package-variant-closed"
                                    color="teal-darken-1"
                                    size="small"
                                ></v-icon>
                            </v-avatar>
                            <span
                                class="text-overline font-weight-bold text-grey-darken-1"
                                >Total Stock</span
                            >
                        </div>
                        <div
                            class="text-h4 font-weight-black text-teal-darken-4"
                        >
                            {{ stats.total ?? 0 }}
                        </div>
                    </v-card>
                </v-col>

                <v-col cols="12" sm="6" md="3">
                    <v-card
                        elevation="0"
                        border
                        class="rounded-xl pa-5 bg-teal-darken-1 text-white"
                    >
                        <div class="d-flex align-center mb-2">
                            <v-avatar color="white" size="32" class="mr-2">
                                <v-icon
                                    icon="mdi-check-circle-outline"
                                    color="teal-darken-1"
                                    size="small"
                                ></v-icon>
                            </v-avatar>
                            <span
                                class="text-overline font-weight-bold opacity-80"
                                >Disponibles</span
                            >
                        </div>
                        <div class="text-h4 font-weight-black">
                            {{ stats.disponible ?? 0 }}
                        </div>
                    </v-card>
                </v-col>

                <v-col cols="12" sm="6" md="3">
                    <v-card
                        elevation="0"
                        border
                        class="rounded-xl pa-5 bg-white text-blue-darken-3"
                    >
                        <div class="d-flex align-center mb-2">
                            <v-avatar
                                color="blue-lighten-5"
                                size="32"
                                class="mr-2"
                            >
                                <v-icon
                                    icon="mdi-truck-delivery-outline"
                                    color="blue-darken-1"
                                    size="small"
                                ></v-icon>
                            </v-avatar>
                            <span
                                class="text-overline font-weight-bold text-grey-darken-1"
                                >Livrés</span
                            >
                        </div>
                        <div class="text-h4 font-weight-black">
                            {{ stats.livres ?? 0 }}
                        </div>
                    </v-card>
                </v-col>

                <v-col cols="12" sm="6" md="3">
                    <v-card
                        elevation="0"
                        border
                        class="rounded-xl pa-5 bg-orange-lighten-5 text-orange-darken-4"
                    >
                        <div class="d-flex align-center mb-2">
                            <v-avatar color="white" size="32" class="mr-2">
                                <v-icon
                                    icon="mdi-cog-transfer"
                                    color="orange-darken-3"
                                    size="small"
                                ></v-icon>
                            </v-avatar>
                            <span class="text-overline font-weight-bold"
                                >Pièces Sorties</span
                            >
                        </div>
                        <div class="text-h4 font-weight-black">
                            {{ stats.pieces_sorties ?? 0 }}
                        </div>
                        <div class="text-caption font-italic opacity-70">
                            Composants hors unité
                        </div>
                    </v-card>
                </v-col>
            </v-row>

            <v-row class="mb-6">
                <v-col cols="12" md="5">
                    <v-text-field
                        v-model="search"
                        prepend-inner-icon="mdi-magnify"
                        label="Filtrer par nom, modèle ou numéro de série..."
                        variant="solo"
                        elevation="1"
                        flat
                        hide-details
                        rounded="lg"
                        class="border-teal-lighten-4"
                        bg-color="white"
                    ></v-text-field>
                </v-col>
            </v-row>

            <v-card elevation="2" class="rounded-xl border-0 overflow-hidden">
                <v-data-table
                    :headers="headers"
                    :items="materiels.data"
                    :items-per-page="15"
                    hover
                    class="custom-table"
                >
                    <template v-slot:item.nom="{ item }">
                        <div class="d-flex align-center py-2">
                            <v-icon
                                icon="mdi-laptop"
                                color="teal-lighten-3"
                                class="mr-3"
                            ></v-icon>
                            <span
                                class="font-weight-black text-teal-darken-4"
                                >{{ item.nom }}</span
                            >
                        </div>
                    </template>

                    <template v-slot:item.numero_serie="{ item }">
                        <span class="serial-tag text-caption font-weight-bold">
                            {{ item.numero_serie }}
                        </span>
                    </template>

                    <template v-slot:item.service="{ item }">
                        <div
                            v-if="item.demande"
                            class="text-body-2 font-weight-bold text-teal-darken-2"
                        >
                            <v-icon
                                icon="mdi-office-building-marker-outline"
                                size="14"
                                class="mr-1"
                            ></v-icon>
                            {{ item.demande.service_beneficiaire }}
                        </div>
                        <v-chip
                            v-else
                            size="x-small"
                            color="grey-lighten-1"
                            variant="tonal"
                            class="text-italic font-weight-medium"
                        >
                            En stock
                        </v-chip>
                    </template>

                    <template v-slot:item.demandeur="{ item }">
                        <div v-if="item.demande" class="d-flex align-center">
                            <v-icon
                                icon="mdi-account-outline"
                                size="16"
                                class="mr-1 text-grey"
                            ></v-icon>
                            <span class="text-body-2">{{
                                item.demande.demandeur_nom
                            }}</span>
                        </div>
                        <div v-else class="text-caption text-grey-lighten-1">
                            —
                        </div>
                    </template>

                    <template v-slot:item.col_pieces="{ item }">
                        <div
                            v-if="item.pieces && item.pieces.length > 0"
                            class="py-2"
                        >
                            <div
                                v-for="p in item.pieces"
                                :key="p.id"
                                class="d-flex align-center mb-1"
                            >
                                <v-tooltip
                                    :text="
                                        p.demande
                                            ? 'Affecté à: ' +
                                              p.demande.service_beneficiaire
                                            : 'Disponible'
                                    "
                                    location="top"
                                >
                                    <template v-slot:activator="{ props }">
                                        <v-chip
                                            v-bind="props"
                                            size="x-small"
                                            :color="
                                                p.demande_id
                                                    ? 'blue-grey-lighten-4'
                                                    : 'teal-lighten-4'
                                            "
                                            variant="flat"
                                            class="mr-2 font-weight-bold"
                                        >
                                            <v-icon
                                                start
                                                :icon="
                                                    p.demande_id
                                                        ? 'mdi-link-variant'
                                                        : 'mdi-memory'
                                                "
                                                size="12"
                                                :color="
                                                    p.demande_id
                                                        ? 'blue-grey-darken-3'
                                                        : 'teal-darken-3'
                                                "
                                            ></v-icon>
                                            <span
                                                :class="
                                                    p.demande_id
                                                        ? 'text-blue-grey-darken-3'
                                                        : 'text-teal-darken-3'
                                                "
                                            >
                                                {{ p.nom_piece }}
                                            </span>
                                        </v-chip>
                                    </template>
                                </v-tooltip>

                                <span
                                    v-if="p.demande"
                                    class="text-caption text-blue-grey-lighten-1 font-weight-medium d-flex align-center"
                                >
                                    <v-icon
                                        icon="mdi-arrow-right"
                                        size="12"
                                        class="mx-1"
                                    ></v-icon>
                                    {{ p.demande.service_beneficiaire }}
                                </span>
                            </div>
                        </div>
                        <span
                            v-else
                            class="text-caption text-grey-lighten-1 font-italic"
                        >
                            Standard
                        </span>
                    </template>

                    <template v-slot:item.etat="{ item }">
                        <v-chip
                            :color="
                                item.etat === 'neuf'
                                    ? 'teal-darken-1'
                                    : 'amber-darken-2'
                            "
                            size="x-small"
                            class="text-uppercase font-weight-black"
                            label
                        >
                            {{ item.etat }}
                        </v-chip>
                    </template>

                    <template v-slot:item.actions="{ item }">
                        <div class="d-flex justify-end gap-2">
                            <template v-if="!item.demande_id">
                                <v-btn
                                    icon="mdi-pencil-outline"
                                    size="30"
                                    variant="tonal"
                                    color="teal"
                                    class="rounded-lg"
                                ></v-btn>
                                <v-btn
                                    icon="mdi-trash-can-outline"
                                    size="30"
                                    variant="tonal"
                                    color="red-lighten-1"
                                    class="rounded-lg"
                                ></v-btn>
                            </template>
                            <v-btn
                                v-else
                                icon="mdi-lock-outline"
                                size="30"
                                variant="text"
                                color="grey-lighten-1"
                                disabled
                            ></v-btn>
                        </div>
                    </template>
                </v-data-table>
            </v-card>
        </v-container>
    </AuthenticatedLayout>
</template>

<style scoped>
.serial-tag {
    background-color: #f0fdfa; /* teal 50 */
    border: 1px solid #ccfbf1; /* teal 100 */
    color: #0f766e; /* teal 700 */
    padding: 2px 8px;
    border-radius: 6px;
    font-family: "Courier New", Courier, monospace;
}

.custom-table :deep(thead th) {
    background-color: #f8fafc !important;
    text-transform: uppercase;
    font-size: 0.75rem !important;
    letter-spacing: 0.05rem;
    font-weight: 800 !important;
    color: #64748b !important;
}

.custom-table :deep(tbody tr:hover) {
    background-color: #f0fdfa !important;
}

.gap-2 {
    gap: 8px;
}

.opacity-80 {
    opacity: 0.8;
}
.opacity-70 {
    opacity: 0.7;
}
</style>
