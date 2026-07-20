<script setup>
import { ref, computed, onMounted } from "vue";
import { Head, router } from "@inertiajs/vue3";
import AuthentDemandeLayout from "@/Layouts/AuthentDemandeLayout.vue";

const props = defineProps({
    demandes: Object,
    services: Array,
    services_actifs: Array,
    services_totaux: Object,
});

const serviceSelectionne = ref(null);
const perPage = 15;
const currentPage = ref(1);
const allDemandes = ref([]);
const isLoading = ref(false);
const hasMore = ref(true);

// --- LOGIQUE DE DONNÉES ---
const listeDemandesRaw = computed(() => {
    return allDemandes.value;
});

// ✅ Utiliser les services actifs passés par le contrôleur
const servicesAvecDemandes = computed(() => {
    return props.services_actifs || [];
});

// ✅ Compter les demandes par service
const getNbDemandesParService = (service) => {
    if (props.services_totaux && props.services_totaux[service] !== undefined) {
        return props.services_totaux[service];
    }
    return listeDemandesRaw.value.filter(
        (d) => d.service_beneficiaire === service && d.statut !== "Clôturé"
    ).length;
};

const demandesDuService = computed(() => {
    if (!serviceSelectionne.value) return [];
    return listeDemandesRaw.value.filter(
        (d) =>
            d.service_beneficiaire === serviceSelectionne.value &&
            d.statut !== "Clôturé"
    );
});

const demandesGroupeesParDemandeur = computed(() => {
    const groupes = {};
    demandesDuService.value.forEach((d) => {
        const nom = d.demandeur_nom || "Sans nom";
        if (!groupes[nom]) groupes[nom] = [];
        groupes[nom].push(d);
    });
    return groupes;
});

// --- CHARGEMENT PAGINÉ ---
const loadMore = async () => {
    if (isLoading.value || !hasMore.value) return;

    isLoading.value = true;

    try {
        const response = await axios.get(route("demandes.gestionService"), {
            params: {
                page: currentPage.value,
                per_page: perPage,
                service: serviceSelectionne.value,
                statut: "En attente",
            },
        });

        const newData = response.data.data || [];
        allDemandes.value = [...allDemandes.value, ...newData];

        hasMore.value = response.data.next_page_url !== null;
        currentPage.value++;
    } catch (error) {
        console.error("Erreur de chargement:", error);
    } finally {
        isLoading.value = false;
    }
};

// --- INFINITE SCROLL ---
const observer = ref(null);
const loadMoreRef = ref(null);

const setupObserver = () => {
    const options = {
        root: null,
        rootMargin: "0px 0px 100px 0px",
        threshold: 0.1,
    };

    observer.value = new IntersectionObserver((entries) => {
        if (entries[0].isIntersecting && hasMore.value && !isLoading.value) {
            loadMore();
        }
    }, options);

    if (loadMoreRef.value) {
        observer.value.observe(loadMoreRef.value);
    }
};

onMounted(() => {
    if (servicesAvecDemandes.value.length > 0) {
        serviceSelectionne.value = servicesAvecDemandes.value[0];
        loadMore();
    }
    setupObserver();
});

// --- ACTIONS PAR DEMANDEUR ---
const validerParDemandeur = (articles) => {
    router.post(
        route("demandes.valider_groupe"),
        { ids: articles.map((d) => d.id) },
        { preserveScroll: true }
    );
};

const imprimerEtCloturerParDemandeur = (nom, articles) => {
    window.open(
        route("demandes.imprimer_bon", {
            service: serviceSelectionne.value,
            demandeur: nom,
        }),
        "_blank"
    );
    router.post(
        route("demandes.cloturer_groupe"),
        { ids: articles.map((d) => d.id) },
        { preserveScroll: true }
    );
};

const estGroupeValide = (articles) => {
    return articles.length > 0 && articles.every((a) => a.statut === "Validé");
};

// --- STATISTIQUES ---
const totalArticles = computed(() => demandesDuService.value.length);
const nbValides = computed(
    () => demandesDuService.value.filter((d) => d.statut === "Validé").length
);
const progression = computed(() =>
    totalArticles.value > 0 ? (nbValides.value / totalArticles.value) * 100 : 0
);

// --- HELPERS ---
const estSortiePiece = (item) => {
    return item.pieces && item.pieces.length > 0;
};

const nomAffichage = (item) => {
    if (estSortiePiece(item)) {
        const nomsDesPieces = item.pieces.map((p) => p.nom_piece).join(", ");
        return `${item.nom_materiel} (${nomsDesPieces})`;
    }
    return item.nom_materiel;
};

const extrairePiece = (item) => {
    if (estSortiePiece(item)) {
        return item.pieces[0].nom_piece + (item.pieces.length > 1 ? "..." : "");
    }
    return "Unité complète";
};

const extraireParent = (item) =>
    item.description?.split(/SORTIE PIÈCES/i)[0].trim() || item.nom_materiel;

const allerAjouterArticle = () => {
    router.get(route("demandes.create"), { service: serviceSelectionne.value });
};

const supprimerLigne = (item) => {
    router.delete(route("demandes.destroy", item.id), { preserveScroll: true });
};
</script>

<template>

    <Head title="Distribution" />
    <AuthentDemandeLayout>
        <v-container fluid class="pa-6 bg-teal-lighten-5 min-vh-100">
            <!-- ROW FIXE : SELECTEUR ET CHIPS -->
            <v-row justify="center" class="mb-8">
                <v-col cols="12" md="10" lg="8">
                    <v-card class="rounded-xl pa-2" elevation="2" border>
                        <v-autocomplete v-model="serviceSelectionne" :items="services" item-title="nom" item-value="nom"
                            label="Service en cours de traitement" variant="solo" flat hide-details
                            prepend-inner-icon="mdi-office-building" color="teal-darken-1"></v-autocomplete>

                        <div v-if="servicesAvecDemandes && servicesAvecDemandes.length > 0"
                            class="px-4 py-3 d-flex align-center flex-wrap ga-2 border-t mt-2">
                            <span class="text-caption font-weight-bold text-teal-darken-2 text-uppercase"
                                style="letter-spacing: 1px">
                                Unités en attente ({{ servicesAvecDemandes.length }}) :
                            </span>
                            <v-chip v-for="s in servicesAvecDemandes" :key="s" size="small"
                                :variant="s === serviceSelectionne ? 'flat' : 'outlined'"
                                :color="s === serviceSelectionne ? 'teal-darken-2' : 'teal-darken-1'"
                                :class="s === serviceSelectionne ? 'text-white' : 'bg-white'"
                                @click="serviceSelectionne = s" link>
                                {{ s }}
                                <v-badge v-if="getNbDemandesParService(s) > 0" color="teal-lighten-1" inline>
                                    {{ getNbDemandesParService(s) }}
                                </v-badge>
                            </v-chip>
                        </div>
                        <div v-else class="px-4 py-3 text-caption text-grey">
                            Aucune demande en attente.
                        </div>
                    </v-card>
                </v-col>
            </v-row>

            <!-- ROW FIXE : CONTENU PRINCIPAL AVEC SCROLL -->
            <div v-if="serviceSelectionne" class="d-flex">
                <!-- COLONNE GAUCHE : LISTE DES DEMANDES (FIXE + SCROLL) -->
                <v-col cols="12" lg="9" class="pa-0 pr-3">
                    <!-- CARTE FIXE POUR LE CONTENU -->
                    <v-card class="rounded-xl border shadow-sm overflow-hidden" elevation="0"
                        style="height: calc(100vh - 280px);">
                        <!-- HEADER FIXE -->
                        <div class="bg-teal-darken-1 pa-3 d-flex align-center" style="min-height: 52px;">
                            <v-icon color="white" class="mr-2">mdi-format-list-bulleted</v-icon>
                            <span class="text-white font-weight-bold text-uppercase" style="letter-spacing: 0.5px">
                                Demandes du service {{ serviceSelectionne }}
                            </span>
                            <v-spacer></v-spacer>
                            <v-chip size="x-small" color="white" variant="flat"
                                class="text-teal-darken-1 font-weight-black">
                                {{ totalArticles }} Article(s)
                            </v-chip>
                        </div>

                        <!-- ZONE DE SCROLL -->
                        <div style="height: calc(100% - 52px); overflow-y: auto; overflow-x: hidden;"
                            class="scrollable-area">
                            <!-- Message si aucune demande -->
                            <div v-if="demandesDuService.length === 0" class="text-center pa-10">
                                <v-icon size="64" color="grey-lighten-2">mdi-inbox-outline</v-icon>
                                <div class="text-h6 text-grey mt-4">
                                    Aucune demande pour ce service
                                </div>
                                <v-btn color="teal-darken-1" class="mt-4" prepend-icon="mdi-plus"
                                    @click="allerAjouterArticle">
                                    Ajouter une demande
                                </v-btn>
                            </div>

                            <!-- LISTE DES DEMANDES -->
                            <div v-for="(articles, nom) in demandesGroupeesParDemandeur" :key="nom" class="pa-3">
                                <div class="d-flex align-center mb-3">
                                    <v-icon color="teal-darken-1" class="mr-2">mdi-account-circle</v-icon>
                                    <span class="font-weight-bold text-teal-darken-3 text-uppercase"
                                        style="letter-spacing: 0.5px">
                                        Receveur : {{ nom }}
                                    </span>
                                    <v-chip size="x-small" color="teal-lighten-3" class="ml-2 font-weight-black">
                                        {{ articles.length }} Article(s)
                                    </v-chip>
                                </div>

                                <v-table hover density="comfortable" style="background: transparent !important">
                                    <thead>
                                        <tr class="bg-teal-lighten-5" style="position: sticky; top: 0; z-index: 10;">
                                            <th class="text-teal-darken-3 font-weight-bold text-left"
                                                style="border-top-left-radius: 0 !important; border-bottom: none !important;">
                                                Désignation
                                            </th>
                                            <th class="text-teal-darken-3 font-weight-bold text-center"
                                                style="border-bottom: none !important;">
                                                S/N
                                            </th>
                                            <th class="text-teal-darken-3 font-weight-bold text-center"
                                                style="border-bottom: none !important;">
                                                Qté
                                            </th>
                                            <th class="text-teal-darken-3 font-weight-bold text-center"
                                                style="border-bottom: none !important;">
                                                État
                                            </th>
                                            <th class="text-teal-darken-3 font-weight-bold text-right"
                                                style="border-top-right-radius: 0 !important; border-bottom: none !important;">
                                                Actions
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr v-for="item in articles" :key="item.id">
                                            <td class="pa-2">
                                                <!-- Cas 1 : Pièces seules -->
                                                <template
                                                    v-if="item.nbredemande === 0 && item.pieces && item.pieces.length > 0">
                                                    <div v-for="(piece, pIdx) in item.pieces" :key="piece.id"
                                                        class="d-flex align-center" :class="{ 'mt-2': pIdx > 0 }">
                                                        <v-icon color="orange-darken-2" size="small"
                                                            class="mr-2">mdi-puzzle</v-icon>
                                                        <div class="font-weight-black text-uppercase text-orange-darken-4"
                                                            style="font-size: 0.9rem">
                                                            {{ piece.nom_piece }}
                                                        </div>
                                                    </div>
                                                    <div class="text-caption italic ml-7 mt-1" style="color: #888">
                                                        Origine : {{ item.nom_materiel }}
                                                        <span v-if="item.numero_serie">(S/N : {{ item.numero_serie
                                                            }})</span>
                                                    </div>
                                                </template>

                                                <!-- Cas 2 : Matériel avec ou sans pièces -->
                                                <template v-else>
                                                    <div class="d-flex align-center">
                                                        <v-icon color="teal-darken-2" size="small"
                                                            class="mr-2">mdi-monitor</v-icon>
                                                        <div class="font-weight-black text-uppercase"
                                                            style="font-size: 0.9rem">
                                                            {{ item.nom_materiel }}
                                                        </div>
                                                    </div>
                                                    <div v-if="item.pieces && item.pieces.length > 0" class="mt-2">
                                                        <div v-for="(p, idx) in item.pieces" :key="p.id"
                                                            class="d-flex align-center ml-7">
                                                            <v-icon size="12" color="teal-darken-3"
                                                                class="mr-1">mdi-plus-circle</v-icon>
                                                            <span class="text-caption text-teal-darken-3">{{ p.nom_piece
                                                                }}</span>
                                                        </div>
                                                    </div>
                                                    <div v-else class="text-caption italic ml-7" style="color: #666">
                                                        (Matériel complet sans pièces détachées)
                                                    </div>
                                                </template>
                                            </td>

                                            <td class="text-center font-mono font-weight-bold">
                                                <template
                                                    v-if="item.nbredemande === 0 && item.pieces && item.pieces.length > 0">
                                                    <div v-for="piece in item.pieces" :key="'sn-' + piece.id"
                                                        class="py-1 text-orange-darken-3">
                                                        {{ piece.numero_serie || "—" }}
                                                    </div>
                                                    <div class="py-1 invisible">—</div>
                                                </template>
                                                <template v-else-if="item.pieces && item.pieces.length > 0">
                                                    <div class="py-1 font-weight-bold">
                                                        {{ item.numero_serie || "—" }}
                                                    </div>
                                                    <div v-for="piece in item.pieces" :key="'sn-' + piece.id"
                                                        class="py-1 text-teal-darken-3">
                                                        {{ piece.numero_serie || "—" }}
                                                    </div>
                                                </template>
                                                <template v-else>
                                                    {{ item.numero_serie || "—" }}
                                                </template>
                                            </td>

                                            <td class="text-center font-weight-black">
                                                <template
                                                    v-if="item.nbredemande === 0 && item.pieces && item.pieces.length > 0">
                                                    <div v-for="piece in item.pieces" :key="'qty-' + piece.id"
                                                        class="py-1">
                                                        1
                                                    </div>
                                                    <div class="py-1 invisible">—</div>
                                                </template>
                                                <template v-else-if="item.pieces && item.pieces.length > 0">
                                                    <div class="py-1">{{ item.nbredemande }}</div>
                                                    <div v-for="piece in item.pieces" :key="'qty-' + piece.id"
                                                        class="py-1">
                                                        1
                                                    </div>
                                                </template>
                                                <template v-else>
                                                    {{ item.nbredemande }}
                                                </template>
                                            </td>

                                            <td class="text-center">
                                                <v-chip :color="item.statut === 'Validé' ? 'success' : 'amber-darken-2'"
                                                    size="x-small" variant="tonal" class="font-weight-bold">
                                                    <v-icon start size="12">
                                                        {{ item.statut === "Validé" ? "mdi-check-circle" :
                                                        "mdi-clock-outline" }}
                                                    </v-icon>
                                                    {{ item.statut }}
                                                </v-chip>
                                            </td>

                                            <td class="text-right">
                                                <v-btn icon="mdi-trash-can-outline" size="x-small" variant="text"
                                                    color="red-lighten-3" @click="supprimerLigne(item)"></v-btn>
                                            </td>
                                        </tr>
                                    </tbody>
                                </v-table>

                                <!-- BOUTONS PAR GROUPE -->
                                <div class="d-flex justify-end ga-2 mt-3">
                                    <v-btn v-if="!estGroupeValide(articles)" color="teal-darken-1"
                                        class="rounded-lg text-none font-weight-bold shadow-sm"
                                        prepend-icon="mdi-check-all" @click="validerParDemandeur(articles)">
                                        Valider la demande
                                    </v-btn>
                                    <v-btn v-else color="teal-darken-2" class="rounded-lg text-none font-weight-bold"
                                        prepend-icon="mdi-printer"
                                        @click="imprimerEtCloturerParDemandeur(nom, articles)">
                                        Imprimer & Clôturer
                                    </v-btn>
                                </div>

                                <v-divider class="my-3"></v-divider>
                            </div>

                            <!-- LOADER -->
                            <div ref="loadMoreRef" class="text-center py-3">
                                <v-progress-circular v-if="isLoading" indeterminate color="teal-darken-1"
                                    size="32"></v-progress-circular>
                                <span v-else-if="hasMore" class="text-caption text-grey">⬇ Scroll pour charger
                                    plus...</span>
                                <span v-else class="text-caption text-grey">✓ Toutes les demandes sont chargées</span>
                            </div>
                        </div>
                    </v-card>
                </v-col>

                <!-- COLONNE DROITE : STATISTIQUES (FIXE) -->
                <v-col cols="12" lg="3" class="pa-0 pl-3">
                    <v-card class="rounded-xl pa-5 sticky-card border bg-white shadow-sm" elevation="0"
                        style="position: sticky; top: 24px;">
                        <div class="text-overline text-teal-lighten-1">Service</div>
                        <div class="text-h6 font-weight-black text-teal-darken-4 mb-4 leading-tight">
                            {{ serviceSelectionne }}
                        </div>
                        <v-progress-linear :model-value="progression" color="teal-lighten-2" height="8" rounded
                            class="mb-6"></v-progress-linear>
                        <div class="bg-teal-lighten-5 pa-3 rounded-lg mb-4">
                            <div class="d-flex justify-space-between mb-1">
                                <span class="text-caption text-teal-darken-3">Articles :</span>
                                <span class="font-weight-bold">{{ totalArticles }}</span>
                            </div>
                            <div class="d-flex justify-space-between">
                                <span class="text-caption text-teal-darken-3">Validés :</span>
                                <span class="font-weight-bold text-success">{{ nbValides }}</span>
                            </div>
                        </div>
                        <v-btn block color="teal-lighten-4" variant="flat"
                            class="text-teal-darken-3 rounded-lg text-none font-weight-bold" prepend-icon="mdi-plus"
                            @click="allerAjouterArticle">
                            Ajouter un article
                        </v-btn>
                    </v-card>
                </v-col>
            </div>
        </v-container>
    </AuthentDemandeLayout>
</template>

<style scoped>
.sticky-card {
    position: sticky;
    top: 24px;
}

/* ===== SCROLL PERSONNALISÉ ===== */
.scrollable-area {
    scroll-behavior: smooth;
}

.scrollable-area::-webkit-scrollbar {
    width: 6px;
}

.scrollable-area::-webkit-scrollbar-track {
    background: #e0f2f1;
    border-radius: 3px;
}

.scrollable-area::-webkit-scrollbar-thumb {
    background: #26a69a;
    border-radius: 3px;
}

.scrollable-area::-webkit-scrollbar-thumb:hover {
    background: #1a6d5e;
}

/* ===== STICKY HEADER DU TABLEAU ===== */
.v-table thead tr th {
    position: sticky !important;
    top: 0 !important;
    z-index: 10 !important;
    background: #e0f2f1 !important;
}

/* ===== AUTRES STYLES ===== */
tr {
    page-break-inside: avoid;
}

.sn-badge {
    background: #f8fafc;
    padding: 2px 6px;
    border-radius: 4px;
    font-family: monospace;
    font-size: 0.7rem;
    color: #64748b;
    border: 1px solid #e2e8f0;
}

:deep(.v-table > .v-table__wrapper > table) {
    width: 100%;
    border-spacing: 0;
}

:deep(.v-table__wrapper) {
    border-radius: 0 !important;
}
</style>
