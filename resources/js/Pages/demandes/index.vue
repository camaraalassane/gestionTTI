<script setup>
import AuthentDemandeLayout from "@/Layouts/AuthentDemandeLayout.vue";
import { Head, Link, router } from "@inertiajs/vue3";
import { ref, watch, computed } from "vue";

const props = defineProps({
    commandes: Object,
    stats: Object,
    filters: Object,
});

const deleteDialog = ref(false);
const selectedCommande = ref(null);
const search = ref(props.filters?.search || "");

// État d'ouverture/fermeture des commandes
const openCommandes = ref({});

// Calculer le nombre total de commandes
const nombreCommandes = computed(() => props.commandes?.total || 0);

// Calculer le nombre de commandes en attente sur la page actuelle
const commandesEnAttente = computed(() => {
    if (!props.commandes?.data) return 0;
    return props.commandes.data.length;
});

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

// ACTIONS
const openDeleteModal = (commande) => {
    selectedCommande.value = commande;
    deleteDialog.value = true;
};

const confirmDelete = () => {
    if (!selectedCommande.value) return;

    router.delete(route("demandes.destroy_by_commande", selectedCommande.value.numcomande), {
        onSuccess: () => {
            deleteDialog.value = false;
            selectedCommande.value = null;
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

// GESTION DU DÉROULEMENT
const toggleCommande = (numcomande) => {
    openCommandes.value[numcomande] = !openCommandes.value[numcomande];
};

const isCommandeOpen = (numcomande) => {
    return openCommandes.value[numcomande] || false;
};

// FORMATTAGE & STYLE
const formatDate = (dateString) => {
    if (!dateString) return "N/A";

    if (typeof dateString === 'string' && dateString.match(/^\d{2}\/\d{2}\/\d{4}$/)) {
        return dateString;
    }

    try {
        const date = new Date(dateString);
        if (isNaN(date.getTime())) {
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

// Calcul du total d'articles par commande
const getTotalItems = (commande) => {
    if (!commande.demandes) return 0;
    return commande.demandes.reduce((total, demande) => {
        if (demande.est_sortie_materiel) {
            return total + (demande.nbredemande || 0);
        } else {
            return total + (demande.pieces?.length || 0);
        }
    }, 0);
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
            <!-- STATISTIQUES -->
            <v-row dense class="mb-4">
                <v-col cols="12" sm="6" md="4">
                    <v-card variant="flat" border class="rounded-xl pa-4 bg-white d-flex align-center shadow-sm">
                        <v-avatar color="teal-lighten-5" size="48" class="mr-4">
                            <v-icon color="teal-darken-1">mdi-tray-full</v-icon>
                        </v-avatar>
                        <div>
                            <div class="text-caption text-grey">Total Commandes</div>
                            <div class="text-h6 font-weight-black">{{ nombreCommandes }}</div>
                        </div>
                    </v-card>
                </v-col>
                <v-col cols="12" sm="6" md="4">
                    <v-card variant="flat" border class="rounded-xl pa-4 bg-white d-flex align-center shadow-sm">
                        <v-avatar color="orange-lighten-5" size="48" class="mr-4">
                            <v-icon color="orange-darken-2">mdi-timer-sand</v-icon>
                        </v-avatar>
                        <div>
                            <div class="text-caption text-grey">Commandes en attente</div>
                            <div class="text-h6 font-weight-black text-orange-darken-3">{{ commandesEnAttente }}</div>
                        </div>
                    </v-card>
                </v-col>
            </v-row>

            <!-- BARRE DE RECHERCHE + BOUTON -->
            <v-row class="mb-4" align="center" dense>
                <v-col cols="12" md="5">
                    <v-text-field v-model="search" prepend-inner-icon="mdi-magnify"
                        placeholder="Rechercher par commande, service ou demandeur..." variant="solo" flat hide-details
                        rounded="lg" bg-color="white" clearable></v-text-field>
                </v-col>
                <v-spacer></v-spacer>
                <v-col cols="12" md="auto">
                    <v-btn :href="route('demandes.create')" color="teal-darken-2" prepend-icon="mdi-plus" size="large"
                        rounded="lg">
                        Nouvelle Demande
                    </v-btn>
                </v-col>
            </v-row>

            <!-- LISTE DES COMMANDES -->
            <v-card class="rounded-xl shadow-lg" border flat>
                <v-list class="pa-2">
                    <v-list-item v-for="commande in commandes.data" :key="commande.numcomande"
                        class="mb-3 border rounded-lg" style="background-color: white;">
                        <!-- EN-TÊTE DE LA COMMANDE (toujours visible) -->
                        <template #prepend>
                            <v-avatar color="teal-lighten-4" size="45" class="mr-2">
                                <v-icon color="teal-darken-3" size="24">mdi-receipt</v-icon>
                            </v-avatar>
                        </template>

                        <template #title>
                            <div class="d-flex align-center flex-wrap w-100">
                                <v-btn
                                    :icon="isCommandeOpen(commande.numcomande) ? 'mdi-chevron-down' : 'mdi-chevron-right'"
                                    variant="text" size="small" @click="toggleCommande(commande.numcomande)"
                                    class="mr-2"></v-btn>

                                <v-chip color="teal-darken-4" size="small" class="mr-2 font-weight-black">
                                    #{{ commande.numcomande }}
                                </v-chip>

                                <span class="text-caption font-weight-bold mr-3">
                                    📅 {{ commande.date_affichee }}
                                </span>

                                <span class="text-caption">
                                    👤 {{ commande.demandeur_nom }}
                                    <span class="mx-1 text-grey">|</span>
                                    🏢 <b class="text-grey-darken-3">{{ commande.service_beneficiaire }}</b>
                                </span>

                                <v-spacer></v-spacer>

                                <v-chip :color="getStatusConfig(commande.statut).color" size="small" variant="flat"
                                    class="mr-2 font-weight-bold" :prepend-icon="getStatusConfig(commande.statut).icon">
                                    {{ commande.statut }}
                                </v-chip>

                                <v-chip size="small" variant="outlined" color="grey" class="mr-2">
                                    📦 {{ getTotalItems(commande) }} article(s)
                                </v-chip>

                                <v-btn icon="mdi-delete-sweep" color="red" variant="text" size="small"
                                    @click="openDeleteModal(commande)"></v-btn>
                            </div>
                        </template>

                        <!-- DÉTAILS DES DEMANDES (caché/déroulant) -->
                        <template #subtitle>
                            <v-slide-y-transition>
                                <div v-if="isCommandeOpen(commande.numcomande)" class="mt-3">
                                    <v-divider class="mb-3"></v-divider>

                                    <div v-for="(demande, index) in commande.demandes" :key="demande.id"
                                        class="mb-3 pb-2"
                                        :class="{ 'border-bottom': index < commande.demandes.length - 1 }">
                                        <div class="d-flex align-start">
                                            <!-- Icône selon le type -->
                                            <v-avatar size="28" class="mr-2"
                                                :color="demande.est_sortie_materiel ? 'teal-lighten-5' : 'orange-lighten-5'">
                                                <v-icon
                                                    :color="demande.est_sortie_materiel ? 'teal-darken-2' : 'orange-darken-2'"
                                                    size="16">
                                                    {{ demande.est_sortie_materiel ? 'mdi-monitor' : 'mdi-puzzle' }}
                                                </v-icon>
                                            </v-avatar>

                                            <div class="flex-grow-1">
                                                <div class="d-flex align-center flex-wrap">
                                                    <span class="font-weight-black text-uppercase"
                                                        style="font-size: 0.9rem;">
                                                        {{ demande.nom_materiel || 'Pièce détachée' }}
                                                    </span>

                                                    <span class="ml-2 text-grey-darken-1" style="font-size: 0.7rem;">
                                                        S/N: {{ demande.numero_serie || 'N/A' }}
                                                    </span>

                                                    <v-chip v-if="demande.a_des_pieces_au_total" size="x-small"
                                                        color="success" variant="flat" class="ml-2"
                                                        style="height: 18px;">
                                                        COMPLET
                                                    </v-chip>

                                                    <v-spacer></v-spacer>

                                                    <div class="text-right">
                                                        <span class="text-grey mr-1"
                                                            style="font-size: 0.7rem;">QTÉ:</span>
                                                        <span class="font-weight-black teal--text"
                                                            style="font-size: 1rem;">
                                                            {{ demande.est_sortie_materiel ? demande.nbredemande :
                                                                (demande.pieces?.length || 0) }}
                                                        </span>
                                                    </div>
                                                </div>

                                                <!-- Pièces détachées associées -->
                                                <div v-if="demande.pieces?.length > 0" class="ml-4 mt-2 pl-2">
                                                    <div class="text-caption text-grey mb-1">📎 Pièces incluses :</div>
                                                    <div v-for="piece in demande.pieces" :key="piece.id"
                                                        class="d-flex align-center mb-1">
                                                        <v-icon color="orange-darken-2" size="12"
                                                            class="mr-1">mdi-plus-circle-outline</v-icon>
                                                        <span style="font-size: 0.7rem;" class="text-grey-darken-2">
                                                            {{ piece.nom_piece }}
                                                        </span>
                                                        <span class="ml-2 text-grey" style="font-size: 0.65rem;">
                                                            (S/N: {{ piece.numero_serie || 'N/A' }})
                                                        </span>
                                                    </div>
                                                </div>

                                                <!-- Description si présente -->
                                                <div v-if="demande.description" class="ml-4 mt-1">
                                                    <span class="text-caption text-grey">📝 {{ demande.description
                                                    }}</span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </v-slide-y-transition>
                        </template>
                    </v-list-item>

                    <!-- Message si aucune commande -->
                    <v-list-item v-if="!commandes.data?.length" class="text-center py-8">
                        <v-icon icon="mdi-inbox-outline" size="48" color="grey-lighten-1" class="mb-2"></v-icon>
                        <div class="text-grey">Aucune commande en attente</div>
                        <v-btn :href="route('demandes.create')" color="teal-darken-2" variant="text" class="mt-2">
                            Créer une demande
                        </v-btn>
                    </v-list-item>
                </v-list>

                <!-- PAGINATION -->
                <v-divider v-if="commandes.data?.length"></v-divider>
                <div class="pa-3 d-flex align-center justify-space-between bg-white rounded-b-xl">
                    <span style="font-size: 0.7rem;" class="text-grey ml-2">
                        {{ commandes.from }}-{{ commandes.to }} / {{ commandes.total }} commandes
                    </span>
                    <v-pagination v-model="commandes.current_page" :length="commandes.last_page" :total-visible="5"
                        @update:model-value="updatePage" density="compact" active-color="teal-darken-2"
                        variant="text"></v-pagination>
                </div>
            </v-card>

            <!-- DIALOGUE DE CONFIRMATION SUPPRESSION -->
            <v-dialog v-model="deleteDialog" max-width="450">
                <v-card class="rounded-xl pa-2">
                    <v-card-title class="d-flex align-center">
                        <v-avatar color="red-lighten-5" class="mr-3">
                            <v-icon icon="mdi-delete-alert" color="red" size="28"></v-icon>
                        </v-avatar>
                        <span class="text-h6">Confirmation d'annulation</span>
                    </v-card-title>
                    <v-card-text class="pt-4">
                        Voulez-vous vraiment annuler la commande
                        <span class="font-weight-black teal--text">#{{ selectedCommande?.numcomande }}</span> ?
                        <br>
                        <span class="text-caption text-grey">Cette action est irréversible.</span>
                    </v-card-text>
                    <v-card-actions class="pa-4">
                        <v-btn variant="text" @click="deleteDialog = false" color="grey">Fermer</v-btn>
                        <v-spacer></v-spacer>
                        <v-btn color="red-darken-2" variant="flat" @click="confirmDelete" prepend-icon="mdi-delete">
                            Confirmer l'annulation
                        </v-btn>
                    </v-card-actions>
                </v-card>
            </v-dialog>
        </v-container>
    </AuthentDemandeLayout>
</template>

<style scoped>
.border {
    border: 1px solid #e0e0e0 !important;
}

.border-bottom {
    border-bottom: 2px dashed #f0f0f0;
}

.v-list-item:hover {
    background-color: #fafafa !important;
    transition: background-color 0.2s ease;
}

::-webkit-scrollbar {
    width: 6px;
    height: 6px;
}

::-webkit-scrollbar-track {
    background: #f1f1f1;
    border-radius: 10px;
}

::-webkit-scrollbar-thumb {
    background: #c1c1c1;
    border-radius: 10px;
}

::-webkit-scrollbar-thumb:hover {
    background: #a8a8a8;
}

/* Animation smooth pour le déroulement */
.v-slide-y-transition-enter-active,
.v-slide-y-transition-leave-active {
    transition: all 0.3s ease;
}

.shadow-sm {
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04) !important;
}

/* Amélioration de la lisibilité */
.text-caption {
    letter-spacing: 0.3px;
}

.font-weight-black {
    letter-spacing: 0.2px;
}
</style>
