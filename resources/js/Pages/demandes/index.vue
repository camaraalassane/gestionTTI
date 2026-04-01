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

    // Configuration du tableau groupé
    const headers = [
        { title: "DÉSIGNATION & DÉTAILS", key: "nom_materiel", sortable: false },
        // On enlève les autres colonnes car elles sont maintenant dans la carte
    ];

    const groupBy = [{ key: 'numcomande' }];

    // ACTIONS
    const openDeleteModal = (demande) => {
        selectedDemande.value = demande;
        deleteDialog.value = true;
    };

    const confirmDelete = () => {
        if (!selectedDemande.value) return;

        router.delete(route("demandes.destroy_by_commande", selectedDemande.value.numcomande), {
            onSuccess: () => {
                deleteDialog.value = false;
                selectedDemande.value = null;
            }
        });
    };

    const updatePage = (page) => {
        router.get(
            route("demandes.index"),
            { search: search.value, page: page },
            { preserveState: true, preserveScroll: true }
        );
    };

    // FORMATTAGE & STYLE
    // FORMATTAGE & STYLE
    const formatDate = (dateString) => {
        if (!dateString) return "N/A";

        // Si c'est déjà une chaîne formatée (comme "15/03/2024"), la retourner
        if (typeof dateString === 'string' && dateString.match(/^\d{2}\/\d{2}\/\d{4}$/)) {
            return dateString;
        }

        try {
            const date = new Date(dateString);
            if (isNaN(date.getTime())) {
                // Essayer de parser le format MySQL (YYYY-MM-DD)
                if (typeof dateString === 'string' && dateString.match(/^\d{4}-\d{2}-\d{2}/)) {
                    const [year, month, day] = dateString.split(' ')[0].split('-');
                    return `${day}/${month}/${year}`;
                }
                return "Date invalide";
            }

            return date.toLocaleDateString("fr-FR", {
                day: "2-digit",
                month: "short",
                year: "numeric",
            });
        } catch (e) {
            return "Date invalide";
        }
    };

    const getStatusConfig = (status) => {
        const s = status?.toLowerCase();
        if (s === "validé" || s === "livré" || s === "clôturé")
            return { color: "teal-darken-1", icon: "mdi-check-decagram" };
        if (s === "en attente")
            return { color: "orange-darken-2", icon: "mdi-clock-fast" };
        if (s === "rejeté" || s === "annulé")
            return { color: "red-darken-1", icon: "mdi-close-circle" };
        return { color: "grey-darken-1", icon: "mdi-help-circle" };
    };

</script>

<template>

    <Head title="Registre des Demandes" />

    <AuthentDemandeLayout>
        <template #header>
            <div class="d-flex align-center">
                <v-icon icon="mdi-clipboard-text-clock-outline" class="mr-3" color="teal-lighten-4"></v-icon>
                Registre des Sorties & Demandes
            </div>
        </template>

        <v-container fluid class="pa-4 bg-grey-lighten-4 min-h-screen">
            <v-row dense class="mb-4">
                <v-col cols="12" sm="4">
                    <v-card variant="flat" border class="rounded-xl pa-4 bg-white d-flex align-center shadow-sm">
                        <v-avatar color="teal-lighten-5" size="48" class="mr-4">
                            <v-icon color="teal-darken-1">mdi-tray-full</v-icon>
                        </v-avatar>
                        <div>
                            <div class="text-caption text-grey">Total Demandes</div>
                            <div class="text-h6 font-weight-black">{{ demandes.total || 0 }}</div>
                        </div>
                    </v-card>
                </v-col>
                <v-col cols="12" sm="4">
                    <v-card variant="flat" border class="rounded-xl pa-4 bg-white d-flex align-center shadow-sm">
                        <v-avatar color="orange-lighten-5" size="48" class="mr-4">
                            <v-icon color="orange-darken-2">mdi-timer-sand</v-icon>
                        </v-avatar>
                        <div>
                            <div class="text-caption text-grey">En cours / Attente</div>
                            <div class="text-h6 font-weight-black text-orange-darken-3">
                                {{demandes.data.filter(d => d.statut?.toLowerCase() === "en attente").length}}
                            </div>
                        </div>
                    </v-card>
                </v-col>
            </v-row>

            <v-row class="mb-4" align="center" dense>
                <v-col cols="12" md="5">
                    <v-text-field v-model="search" id="search-input" name="search" prepend-inner-icon="mdi-magnify" placeholder="Rechercher..." variant="solo" flat hide-details rounded="lg" bg-color="white" clearable></v-text-field>
                </v-col>
                <v-spacer></v-spacer>
                <v-col cols="12" md="auto">
                    <v-btn :href="route('demandes.create')" color="teal-darken-2" prepend-icon="mdi-plus" size="large" rounded="lg">
                        Nouvelle Demande
                    </v-btn>
                </v-col>
            </v-row>

            <v-card class="rounded-xl shadow-lg" border flat>
                <v-data-table :headers="headers" :items="demandes.data" :group-by="groupBy" class="demandes-table-compact" hide-default-footer>
                    <template v-slot:group-header="{ item, columns, toggleGroup, isGroupOpen }">
                        <tr class="bg-grey-lighten-4" style="height: 32px !important;">
                            <td :colspan="columns.length" class="py-0 px-2">
                                <div class="d-flex align-center">
                                    <v-btn :icon="isGroupOpen(item) ? 'mdi-chevron-down' : 'mdi-chevron-right'" variant="text" size="x-small" @click="toggleGroup(item)"></v-btn>

                                    <v-chip color="teal-darken-4" size="x-small" class="mr-2 font-weight-black" style="height: 18px; font-size: 0.65rem !important;">
                                        #{{ item.value }}
                                    </v-chip>

                                    <span class="text-caption font-weight-bold mr-3" style="font-size: 0.7rem !important;">
                                        {{ item.items[0]?.raw?.date_affichee || item.items[0]?.date_affichee }}
                                    </span>

                                    <span class="text-caption" style="font-size: 0.7rem !important;">
                                        {{ item.items[0]?.raw?.demandeur_nom || item.items[0]?.demandeur_nom }}
                                        <span class="mx-1 text-grey">|</span>
                                        <b class="text-grey-darken-3">{{ item.items[0]?.raw?.service_beneficiaire || item.items[0]?.service_beneficiaire }}</b>
                                    </span>

                                    <v-spacer></v-spacer>

                                    <v-chip :color="getStatusConfig(item.items[0]?.raw?.statut || item.items[0]?.statut).color" size="x-small" variant="flat" class="mr-2 font-weight-bold" style="height: 18px; font-size: 0.6rem !important;" :prepend-icon="getStatusConfig(item.items[0]?.raw?.statut || item.items[0]?.statut).icon">
                                        {{ item.items[0]?.raw?.statut || item.items[0]?.statut }}
                                    </v-chip>

                                    <v-btn icon="mdi-delete-sweep" color="red" variant="text" size="x-small" @click="openDeleteModal(item.items[0]?.raw || item.items[0])"></v-btn>
                                </div>
                            </td>
                        </tr>
                    </template>

                    <template v-slot:item="{ item }">
                        <tr v-if="item && (item.raw || item)" style="height: auto !important;">
                            <td :colspan="headers.length" class="pa-0 border-0">
                                <v-card variant="outlined" border class="rounded-lg my-1 bg-white ml-8 mr-2 px-2 py-1" style="border-color: #e0e0e0 !important;">
                                    <div class="d-flex align-center">
                                        <div class="flex-grow-1">
                                            <template v-if="!(item.raw || item).est_sortie_materiel && (item.raw || item).pieces?.length > 0">
                                                <div v-for="piece in (item.raw || item).pieces" :key="'only-piece-' + piece.id">
                                                    <div class="d-flex align-center">
                                                        <v-avatar size="20" color="orange-lighten-4" class="mr-2">
                                                            <v-icon color="orange-darken-3" size="14">mdi-puzzle</v-icon>
                                                        </v-avatar>
                                                        <span class="font-weight-black text-uppercase text-orange-darken-4" style="font-size: 0.85rem !important;">
                                                            {{ piece.nom_piece }}
                                                        </span>
                                                        <v-chip size="x-small" variant="outlined" color="orange-darken-2" class="ml-2 px-1" style="height: 16px; font-size: 0.6rem !important;">
                                                            PIÈCE DÉTACHÉE
                                                        </v-chip>
                                                    </div>

                                                    <div class="text-grey ml-7 mt-1" style="font-size: 0.65rem !important;">
                                                        Origine : {{ (item.raw || item).nom_materiel }} (S/N Mat : {{ (item.raw || item).numero_serie || 'N/A' }})
                                                    </div>
                                                </div>
                                            </template>

                                            <template v-else>
                                                <div class="d-flex align-center">
                                                    <v-icon color="teal-darken-2" size="small" class="mr-1">mdi-monitor</v-icon>
                                                    <span class="font-weight-black text-uppercase" style="font-size: 0.8rem !important;">
                                                        {{ (item.raw || item).nom_materiel }}
                                                    </span>
                                                    <span class="ml-2 text-grey-darken-1" style="font-size: 0.7rem !important;">
                                                        (S/N: {{ (item.raw || item).numero_serie || 'N/A' }})
                                                    </span>

                                                    <v-chip v-if="(item.raw || item).a_des_pieces_au_total" size="x-small" color="success" variant="flat" class="ml-2 px-1 font-weight-bold" style="height: 14px; font-size: 0.55rem !important;">
                                                        COMPLET
                                                    </v-chip>
                                                </div>

                                                <div v-if="(item.raw || item).pieces?.length > 0" class="ml-6 mt-1 border-left-dashed pl-2">
                                                    <div v-for="piece in (item.raw || item).pieces" :key="'inc-piece-' + piece.id" class="d-flex align-center mb-1">
                                                        <v-icon color="orange-darken-2" size="10" class="mr-1">mdi-plus-circle-outline</v-icon>
                                                        <span style="font-size: 0.65rem !important;" class="text-grey-darken-2">
                                                            {{ piece.nom_piece }} (S/N: {{ piece.numero_serie || 'N/A' }})
                                                        </span>
                                                    </div>
                                                </div>
                                            </template>
                                        </div>

                                        <v-divider vertical class="mx-2" style="height: 20px;"></v-divider>
                                        <div class="text-right" style="min-width: 40px">
                                            <span class="text-grey mr-1" style="font-size: 0.6rem !important;">QTÉ:</span>
                                            <span class="font-weight-black" style="font-size: 0.8rem !important;">
                                                {{ (item.raw || item).est_sortie_materiel ? (item.raw || item).nbredemande : (item.raw || item).pieces.length }}
                                            </span>
                                        </div>
                                    </div>
                                </v-card>
                            </td>
                        </tr>
                    </template>
                </v-data-table>

                <v-divider></v-divider>
                <div class="pa-2 d-flex align-center justify-space-between bg-white rounded-b-xl">
                    <span style="font-size: 0.65rem !important;" class="text-grey ml-2">
                        {{ demandes.from }}-{{ demandes.to }} / {{ demandes.total }}
                    </span>
                    <v-pagination v-model="demandes.current_page" :length="demandes.last_page" :total-visible="3" @update:model-value="updatePage" density="compact" active-color="teal-darken-2" variant="text"></v-pagination>
                </div>
            </v-card>


            <v-dialog v-model="deleteDialog" max-width="400">
                <v-card class="rounded-xl pa-2">
                    <v-card-title class="d-flex align-center">
                        <v-avatar color="red-lighten-5" class="mr-3"><v-icon icon="mdi-delete-alert" color="red"></v-icon></v-avatar>
                        Confirmation
                    </v-card-title>
                    <v-card-text>Voulez-vous annuler la commande <b>#{{ selectedDemande?.numcomande }}</b> ?</v-card-text>
                    <v-card-actions class="pa-4">
                        <v-btn variant="text" @click="deleteDialog = false">Fermer</v-btn>
                        <v-spacer></v-spacer>
                        <v-btn color="red-darken-2" variant="flat" @click="confirmDelete">Confirmer</v-btn>
                    </v-card-actions>
                </v-card>
            </v-dialog>
        </v-container>
    </AuthentDemandeLayout>
</template>

<style scoped>


    /* Ajout d'une bordure fine pour le séparateur de carte */
    .border-t-sm {
        border-top: 1px solid #eeeeee !important;
    }

    .border-dashed {
        border-style: dashed !important;
    }


    .demandes-table :deep(th) {
        background-color: #f8fafc !important;
        font-weight: 800 !important;
        text-transform: uppercase;
        font-size: 0.7rem;
        color: #334155 !important;
    }

    .shadow-lg {
        box-shadow: 0 4px 20px -5px rgba(0, 0, 0, 0.05) !important;
    }

    .demandes-table :deep(.v-row-group__header) {
        cursor: default !important;
    }
</style>
