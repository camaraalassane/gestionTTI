<script setup>
import AuthentDemandeLayout from "@/Layouts/AuthentDemandeLayout.vue";
import { Head, Link, router } from "@inertiajs/vue3";
import { ref, watch } from "vue";

const props = defineProps({
    demandes: Object,
    filters: Object,
});

const deleteDialog = ref(false);
const selectedDemande = ref(null);
const search = ref(props.filters?.search || "");

// RECHERCHE AVEC DÉLAI
let searchTimer = null;
watch(search, (value) => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => {
        router.get(
            route("demandes.index"),
            { search: value },
            {
                preserveState: true,
                replace: true,
                preserveScroll: true,
            },
        );
    }, 400);
});

const headers = [
    { title: "N° COMMANDE", key: "numcomande", sortable: true, width: "130px" },
    { title: "DATE", key: "date_demande", sortable: true },
    { title: "DÉSIGNATION & PIÈCES", key: "nom_materiel" },
    { title: "SERVICE", key: "service_beneficiaire" },
    { title: "STATUT", key: "statut", align: "center" },
    { title: "", key: "actions", align: "end", sortable: false },
];

const updatePage = (page) => {
    router.get(
        route("demandes.index"),
        { search: search.value, page: page },
        {
            preserveState: true,
            preserveScroll: true,
        },
    );
};

const openDeleteModal = (demande) => {
    selectedDemande.value = demande;
    deleteDialog.value = true;
};

const confirmDelete = () => {
    if (!selectedDemande.value) return;
    router.delete(route("demandes.destroy", selectedDemande.value.id), {
        onSuccess: () => {
            deleteDialog.value = false;
            selectedDemande.value = null;
        },
    });
};

const formatDate = (dateString) => {
    if (!dateString) return "N/A";
    return new Date(dateString).toLocaleDateString("fr-FR", {
        day: "2-digit",
        month: "short",
        year: "numeric",
    });
};

const getStatusConfig = (status) => {
    const s = status?.toLowerCase();
    if (s === "validé" || s === "livré")
        return { color: "teal-darken-1", icon: "mdi-check-decagram" };
    if (s === "en attente")
        return { color: "orange-darken-2", icon: "mdi-clock-fast" };
    if (s === "rejeté" || s === "annulé")
        return { color: "red-darken-1", icon: "mdi-close-circle" };
    return { color: "grey-darken-1", icon: "mdi-help-circle" };
};

// Fonction pour extraire les pièces de la description
const getPieces = (description) => {
    if (!description || !description.includes("SORTIE PIÈCES :")) return [];
    // On récupère ce qu'il y a entre "SORTIE PIÈCES :" et le pipe "|" ou la fin
    const list = description.split("SORTIE PIÈCES :")[1].split("|")[0];
    return list
        .split(",")
        .map((p) => p.trim())
        .filter((p) => p !== "");
};
</script>

<template>
    <Head title="Registre des Demandes" />

    <AuthentDemandeLayout>
        <template #header>
            <div class="d-flex align-center">
                <v-icon
                    icon="mdi-clipboard-text-clock-outline"
                    class="mr-3"
                    color="teal-lighten-4"
                ></v-icon>
                Registre des Sorties & Demandes
            </div>
        </template>

        <v-container fluid class="pa-4 bg-grey-lighten-4 min-h-screen">
            <v-row dense class="mb-4">
                <v-col cols="12" sm="4">
                    <v-card
                        variant="flat"
                        border
                        class="rounded-xl pa-4 bg-white d-flex align-center"
                    >
                        <v-avatar color="teal-lighten-5" size="48" class="mr-4">
                            <v-icon color="teal-darken-1">mdi-tray-full</v-icon>
                        </v-avatar>
                        <div>
                            <div class="text-caption text-grey">
                                Total Demandes
                            </div>
                            <div class="text-h6 font-weight-black">
                                {{ demandes.total || 0 }}
                            </div>
                        </div>
                    </v-card>
                </v-col>
                <v-col cols="12" sm="4">
                    <v-card
                        variant="flat"
                        border
                        class="rounded-xl pa-4 bg-white d-flex align-center"
                    >
                        <v-avatar
                            color="orange-lighten-5"
                            size="48"
                            class="mr-4"
                        >
                            <v-icon color="orange-darken-2"
                                >mdi-timer-sand</v-icon
                            >
                        </v-avatar>
                        <div>
                            <div class="text-caption text-grey">
                                En cours / Attente
                            </div>
                            <div
                                class="text-h6 font-weight-black text-orange-darken-3"
                            >
                                {{
                                    demandes.data.filter(
                                        (d) =>
                                            d.statut?.toLowerCase() ===
                                            "en attente",
                                    ).length
                                }}
                            </div>
                        </div>
                    </v-card>
                </v-col>
            </v-row>

            <v-row class="mb-4" align="center" dense>
                <v-col cols="12" md="5">
                    <v-text-field
                        v-model="search"
                        prepend-inner-icon="mdi-magnify"
                        placeholder="Chercher une commande, un service, un matériel..."
                        variant="solo"
                        flat
                        hide-details
                        rounded="lg"
                        class="elevation-1"
                        bg-color="white"
                        clearable
                    ></v-text-field>
                </v-col>
                <v-spacer></v-spacer>
                <v-col cols="12" md="auto">
                    <v-btn
                        :href="route('demandes.create')"
                        color="teal-darken-2"
                        prepend-icon="mdi-plus-thick"
                        size="large"
                        rounded="lg"
                        class="text-none font-weight-bold elevation-2"
                    >
                        Nouvelle Demande
                    </v-btn>
                </v-col>
            </v-row>

            <v-card class="rounded-xl shadow-lg" border flat>
                <v-data-table
                    :headers="headers"
                    :items="demandes?.data || []"
                    hover
                    class="demandes-table"
                    hide-default-footer
                >
                    <template v-slot:item.numcomande="{ value }">
                        <v-chip
                            variant="tonal"
                            color="teal-darken-4"
                            size="small"
                            class="font-weight-black px-3"
                        >
                            #{{ value }}
                        </v-chip>
                    </template>

                    <template v-slot:item.nom_materiel="{ item }">
                        <div class="py-2">
                            <template
                                v-if="
                                    item.description
                                        ?.toUpperCase()
                                        .includes('SORTIE PIÈCES')
                                "
                            >
                                <div
                                    v-for="piece in item.pieces"
                                    :key="piece.id"
                                    class="text-body-2 font-weight-bold text-uppercase text-grey-darken-3"
                                >
                                    {{ piece.nom_piece }}
                                </div>
                                <div
                                    class="text-caption italic text-grey-lighten-1 ml-2"
                                >
                                    issu de : {{ item.nom_materiel }}
                                </div>
                            </template>

                            <template v-else>
                                <div
                                    class="text-body-2 font-weight-bold text-uppercase text-grey-darken-3"
                                >
                                    {{ item.nom_materiel }}
                                </div>

                                <div
                                    v-if="item.pieces && item.pieces.length > 0"
                                >
                                    <div
                                        v-for="piece in item.pieces"
                                        :key="piece.id"
                                        class="text-caption font-weight-medium text-teal-darken-1 ml-3"
                                    >
                                        + {{ piece.nom_piece }}
                                        <span
                                            class="text-grey-darken-2 font-weight-bold"
                                            >(S/N:
                                            {{
                                                piece.numero_serie || "N/A"
                                            }})</span
                                        >
                                    </div>
                                </div>

                                <div
                                    v-else-if="item.a_des_pieces_au_total"
                                    class="text-caption italic text-grey ml-3"
                                >
                                    (Matériel seul - pièces livrées ailleurs)
                                </div>

                                <div
                                    v-else-if="
                                        item.description
                                            ?.toUpperCase()
                                            .includes('SANS PIÈCE')
                                    "
                                    class="text-caption italic text-red-lighten-2 ml-3"
                                >
                                    (Sorti sans ses pièces)
                                </div>

                                <div
                                    v-else
                                    class="text-caption italic text-grey ml-3"
                                >
                                    (Complet)
                                </div>
                            </template>

                            <div
                                class="text-overline mt-1 ml-2"
                                style="font-size: 0.65rem !important"
                            >
                                <span
                                    class="text-grey-darken-2 font-weight-bold"
                                >
                                    S/N: {{ item.numero_serie || "N/A" }}
                                </span>
                                <span class="text-grey-lighten-1">
                                    | Qté: {{ item.nbredemande }}
                                </span>
                            </div>
                        </div>
                    </template>

                    <template v-slot:item.service_beneficiaire="{ item }">
                        <div class="py-1">
                            <div
                                class="text-caption font-weight-bold text-grey-darken-2"
                            >
                                {{ item.demandeur_nom || "Inconnu" }}
                            </div>
                            <div
                                class="text-caption text-grey"
                                style="font-size: 0.7rem !important"
                            >
                                {{ item.service_beneficiaire }}
                            </div>
                        </div>
                    </template>

                    <template v-slot:item.actions="{ item }">
                        <div class="d-flex justify-end pr-2">
                            <v-btn
                                icon
                                variant="text"
                                color="red-lighten-1"
                                size="small"
                                @click="openDeleteModal(item)"
                            >
                                <v-icon>mdi-delete-outline</v-icon>
                            </v-btn>
                        </div>
                    </template>

                    <template #bottom>
                        <v-divider></v-divider>
                        <div
                            class="pa-4 d-flex align-center justify-space-between flex-wrap ga-4"
                        >
                            <div
                                class="text-caption font-weight-medium text-grey-darken-1 bg-grey-lighten-4 px-4 py-1 rounded-pill border"
                            >
                                {{ demandes.from }}-{{ demandes.to }} sur
                                {{ demandes.total }}
                            </div>
                            <v-pagination
                                v-model="demandes.current_page"
                                :length="demandes.last_page"
                                :total-visible="3"
                                density="comfortable"
                                variant="outlined"
                                active-color="teal-darken-2"
                                @update:model-value="updatePage"
                            ></v-pagination>
                        </div>
                    </template>
                </v-data-table>
            </v-card>

            <v-dialog v-model="deleteDialog" max-width="400">
                <v-card class="rounded-xl pa-2">
                    <v-card-title class="d-flex align-center">
                        <v-avatar color="red-lighten-5" class="mr-3"
                            ><v-icon
                                icon="mdi-delete-alert"
                                color="red"
                            ></v-icon
                        ></v-avatar>
                        Confirmation
                    </v-card-title>
                    <v-card-text
                        >Voulez-vous annuler la commande
                        <b>#{{ selectedDemande?.numcomande }}</b> ?</v-card-text
                    >
                    <v-card-actions class="pa-4">
                        <v-btn variant="text" @click="deleteDialog = false"
                            >Fermer</v-btn
                        >
                        <v-spacer></v-spacer>
                        <v-btn
                            color="red-darken-2"
                            variant="flat"
                            @click="confirmDelete"
                            >Confirmer</v-btn
                        >
                    </v-card-actions>
                </v-card>
            </v-dialog>
        </v-container>
    </AuthentDemandeLayout>
</template>

<style scoped>
.demandes-table :deep(th) {
    background-color: #f8fafc !important;
    font-weight: 800 !important;
    text-transform: uppercase;
    font-size: 0.7rem;
    letter-spacing: 1px;
    color: #334155 !important;
}
.shadow-lg {
    box-shadow: 0 4px 20px -5px rgba(0, 0, 0, 0.05) !important;
}
.action-btn {
    transition: all 0.2s ease;
}
.action-btn:hover {
    background-color: #f44336 !important; /* Rouge discret uniquement au survol */
    color: white !important;
}
</style>
