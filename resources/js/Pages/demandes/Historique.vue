<script setup lang="ts">
import { ref, computed, watch, nextTick } from "vue";
import axios from "axios";
import { Head, router } from "@inertiajs/vue3";
import AuthentDemandeLayout from "@/Layouts/AuthentDemandeLayout.vue";
import debounce from "lodash/debounce";

// Interfaces
interface PieceItem {
    id: number;
    nom_piece: string;
    numero_serie: string;
}

interface DemandeItem {
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
    est_sortie_uniquement_piece?: boolean;
}

interface CommandeGroup {
    numcomande: string;
    date_demande: string;
    service_beneficiaire: string;
    demandeur_nom: string;
    statut: string;
    demandes: DemandeItem[];
    total_items: number;
}

const props = defineProps<{
    historique: {
        data: CommandeGroup[];
        current_page: number;
        last_page: number;
        total: number;
        from: number;
        to: number;
        per_page: number;
    };
    services: { id: number; nom: string }[];
    filters: {
        search?: string;
        year?: string;
        month?: string;
        service?: string;
    };
}>();

// --- Variables d'état ---
const serviceFiltre = ref<string | null>(props.filters.service || null);
const recherche = ref(props.filters.search || "");
const anneeFiltre = ref(props.filters.year || null);
const moisFiltre = ref(props.filters.month || null);

// --- État pour l'export PDF ---
const exportLoading = ref(false);
const exportMessage = ref("");

// Déterminer si on a des filtres actifs
const hasActiveFilters = computed(() => {
    return !!(recherche.value || anneeFiltre.value || moisFiltre.value || serviceFiltre.value);
});

// Listes pour les sélecteurs
const annees = computed(() => {
    const currentYear = new Date().getFullYear();
    return Array.from({ length: 5 }, (_, i) => (currentYear - i).toString());
});

const mois = [
    { title: "Janvier", value: "01" }, { title: "Février", value: "02" },
    { title: "Mars", value: "03" }, { title: "Avril", value: "04" },
    { title: "Mai", value: "05" }, { title: "Juin", value: "06" },
    { title: "Juillet", value: "07" }, { title: "Août", value: "08" },
    { title: "Septembre", value: "09" }, { title: "Octobre", value: "10" },
    { title: "Novembre", value: "11" }, { title: "Décembre", value: "12" }
];

// Fonction de mise à jour globale
const appliquerFiltres = () => {
    const params: any = {};

    if (recherche.value && recherche.value.trim() !== '') {
        params.search = recherche.value;
    }
    if (anneeFiltre.value) {
        params.year = anneeFiltre.value;
    }
    if (moisFiltre.value) {
        params.month = moisFiltre.value;
    }
    if (serviceFiltre.value) {
        params.service = serviceFiltre.value;
    }

    router.get(
        route("demandes.historique"),
        params,
        {
            preserveState: true,
            replace: true,
            onSuccess: () => window.scrollTo(0, 0)
        }
    );
};

// --- Synchronisation des props avec les valeurs locales ---
watch(() => props.filters.search, (newVal) => {
    if (recherche.value !== newVal) recherche.value = newVal || "";
});

watch(() => props.filters.year, (newVal) => {
    if (anneeFiltre.value !== newVal) anneeFiltre.value = newVal || null;
});

watch(() => props.filters.month, (newVal) => {
    if (moisFiltre.value !== newVal) moisFiltre.value = newVal || null;
});

watch(() => props.filters.service, (newVal) => {
    if (serviceFiltre.value !== newVal) serviceFiltre.value = newVal || null;
});

// --- Surveillances (Watchers) pour les filtres ---
watch(serviceFiltre, () => appliquerFiltres());
watch(recherche, debounce(() => appliquerFiltres(), 400));
watch(anneeFiltre, () => appliquerFiltres());
watch(moisFiltre, (nouveauMois) => {
    if (nouveauMois && !anneeFiltre.value) {
        anneeFiltre.value = new Date().getFullYear().toString();
    }
    appliquerFiltres();
});

const afficherTout = () => {
    recherche.value = "";
    anneeFiltre.value = null;
    moisFiltre.value = null;
    serviceFiltre.value = null;
    router.get(route("demandes.historique"), {}, {
        preserveState: false,
        replace: true
    });
};

const formatDate = (dateStr: string) => {
    if (!dateStr || dateStr === "Date Inconnue") return "DATE INCONNUE";
    const normalizedDate = dateStr.includes(' ') ? dateStr.replace(' ', 'T') : dateStr;
    const date = new Date(normalizedDate);
    if (isNaN(date.getTime())) return dateStr;
    return new Intl.DateTimeFormat("fr-FR", {
        day: "numeric",
        month: "long",
        year: "numeric",
    }).format(date);
};

const allerAuBon = (service: string, date: string, demandeur: string) => {
    router.get(route("demandes.imprimer_bon", { service }), {
        date,
        demandeur,
    });
};

const changerPage = (page: number) => {
    const params: any = { page: page };

    if (recherche.value && recherche.value.trim() !== '') {
        params.search = recherche.value;
    }
    if (anneeFiltre.value) {
        params.year = anneeFiltre.value;
    }
    if (moisFiltre.value) {
        params.month = moisFiltre.value;
    }
    if (serviceFiltre.value) {
        params.service = serviceFiltre.value;
    }

    router.get(
        route("demandes.historique"),
        params,
        {
            preserveState: true,
            preserveScroll: false,
            replace: true
        }
    );
};

// Générer l'URL d'export avec les filtres actuels
const getExportUrl = () => {
    const params: any = {};

    if (recherche.value && recherche.value.trim() !== '') {
        params.search = recherche.value;
    }
    if (anneeFiltre.value) {
        params.year = anneeFiltre.value;
    }
    if (moisFiltre.value) {
        params.month = moisFiltre.value;
    }
    if (serviceFiltre.value) {
        params.service = serviceFiltre.value;
    }

    return route('demandes.pdf', params);
};

// Fonction de secours pour réessayer l'export
const retryExport = () => {
    window.location.href = getExportUrl();
    setTimeout(() => {
        exportLoading.value = false;
    }, 3000);
};

const exporterPDF = async () => {
    const totalCommandes = props.historique.total;
    const isFullExport = !serviceFiltre.value && !anneeFiltre.value && !moisFiltre.value && !recherche.value;

    if (isFullExport && totalCommandes > 200) {
        const confirm = window.confirm(
            `⚠️ ATTENTION : L'export complet contient ${totalCommandes} commandes.\n\n` +
            `Cette opération peut prendre plusieurs minutes.\n\n` +
            `Voulez-vous continuer quand même ?`
        );
        if (!confirm) return;
    }

    // 1. Afficher l'overlay immédiatement
    exportLoading.value = true;
    exportMessage.value = isFullExport
        ? 'Export volumineux en cours... (Préparation du fichier sur le serveur)'
        : 'Génération du PDF en cours...';

    try {
        // 2. Lancer la requête en arrière-plan et attendre la réponse réelle du serveur
        const response = await axios.get(getExportUrl(), {
            responseType: 'blob' // <-- Très important : indique à Axios qu'on attend un fichier fichier
        });

        // 3. Modifier le message juste avant de lancer le téléchargement local
        exportMessage.value = "Téléchargement en cours...";

        // 4. Créer un lien temporaire dans le DOM pour forcer le téléchargement du Blob
        const blob = new Blob([response.data], { type: 'application/pdf' });
        const link = document.createElement('a');
        link.href = window.URL.createObjectURL(blob);

        // Optionnel : Donner un nom dynamique au fichier
        link.download = `historique_sorties_${new Date().toISOString().slice(0, 10)}.pdf`;

        document.body.appendChild(link);
        link.click(); // Déclenche le téléchargement
        document.body.removeChild(link); // Nettoie le DOM

    } catch (error) {
        console.error("Erreur lors de l'export PDF:", error);
        alert("Une erreur est survenue lors de la génération du PDF.");
    } finally {
        // 5. S'EXÉCUTE PILE À LA FIN : Que ce soit un succès ou un échec, on ferme l'overlay
        exportLoading.value = false;
    }
};
</script>

<template>

    <Head title="Historique des Sorties" />
    <AuthentDemandeLayout>
        <v-container fluid class="pa-6 bg-grey-lighten-4 min-vh-100">
            <!-- En-tête -->
            <v-row class="mb-6 align-center" dense>
                <v-col cols="12" md="4">
                    <div class="d-flex align-center">
                        <v-icon size="40" color="blue-grey-darken-3" class="mr-3">mdi-folder-clock-outline</v-icon>
                        <div>
                            <h1 class="text-h5 font-weight-bold text-blue-grey-darken-4 mb-0">Historique</h1>
                            <span class="text-caption text-grey-darken-1 font-italic">Consultation des archives</span>
                        </div>
                    </div>
                </v-col>

                <v-col cols="12" md="8">
                    <div class="d-flex gap-2 align-center">
                        <v-text-field v-model="recherche" label="Rechercher par commande, service, demandeur ou date..."
                            variant="solo" flat density="comfortable" prepend-inner-icon="mdi-magnify" hide-details
                            clearable class="flex-grow-1 border-thin rounded-lg"
                            hint="Ex: CMD-2026, PRESIDENCE, Nom du demandeur, 2025..." persistent-hint></v-text-field>

                        <v-fade-transition>
                            <v-btn v-if="hasActiveFilters" icon="mdi-refresh" variant="outlined"
                                color="blue-grey-darken-2" height="48" width="48"
                                class="rounded-lg bg-white border-thin refresh-btn" @click="afficherTout"></v-btn>
                        </v-fade-transition>

                        <v-btn variant="outlined" color="blue-grey-darken-2" prepend-icon="mdi-file-pdf-box" height="48"
                            class="px-6 font-weight-bold ml-2 bg-white border-thin text-none rounded-lg"
                            @click="exporterPDF" :loading="exportLoading" :disabled="exportLoading">
                            Exporter PDF
                        </v-btn>
                    </div>
                </v-col>
            </v-row>

            <!-- Filtres -->
            <v-row class="mb-8" dense>
                <v-col cols="6" sm="2">
                    <v-select v-model="anneeFiltre" :items="annees" label="Année" variant="solo" flat
                        density="comfortable" hide-details clearable></v-select>
                </v-col>
                <v-col cols="6" sm="3">
                    <v-select v-model="moisFiltre" :items="mois" item-title="title" item-value="value" label="Mois"
                        variant="solo" flat density="comfortable" hide-details clearable></v-select>
                </v-col>
                <v-col cols="12" sm="7">
                    <v-autocomplete v-model="serviceFiltre" :items="services" item-title="nom" item-value="nom"
                        label="Filtrer par Unité" variant="solo" flat density="comfortable"
                        prepend-inner-icon="mdi-filter-variant" hide-details clearable></v-autocomplete>
                </v-col>
            </v-row>

            <!-- Avertissement export volumineux -->
            <v-alert v-if="props.historique.total > 200 && !serviceFiltre && !anneeFiltre && !moisFiltre && !recherche"
                type="warning" variant="tonal" density="compact" class="mb-4 rounded-lg">
                <div class="d-flex align-center">
                    <v-icon size="20" class="mr-2">mdi-alert-circle</v-icon>
                    <span class="text-caption">
                        L'export complet contient {{ props.historique.total }} commandes.
                        Utilisez les filtres pour accélérer l'export.
                    </span>
                </div>
            </v-alert>

            <!-- Liste des commandes AVEC SCROLL INTERNE -->
            <div class="mb-10">
                <v-card v-for="commande in props.historique.data" :key="commande.numcomande"
                    class="mb-6 rounded-lg border-thin" elevation="0">
                    <!-- En-tête avec numéro de commande -->
                    <div class="bg-teal-lighten-4 pa-2 d-flex align-center">
                        <v-icon size="16" color="teal-darken-3" class="mr-2">mdi-file-document-outline</v-icon>
                        <span class="font-weight-bold text-teal-darken-4 text-caption">Commande #{{ commande.numcomande
                        }}</span>
                        <v-chip size="x-small" color="teal-darken-2" variant="flat" class="ml-3">
                            {{ commande.demandes.length }} article(s)
                        </v-chip>
                        <v-spacer></v-spacer>
                        <span class="text-caption text-grey-darken-1">
                            {{ formatDate(commande.date_demande) }}
                        </span>
                    </div>

                    <!-- Informations du service et demandeur -->
                    <div class="pa-2 bg-grey-lighten-5 border-b">
                        <div class="d-flex align-center">
                            <v-icon size="14" class="mr-2 text-grey-darken-2">mdi-office-building-marker</v-icon>
                            <span class="text-caption font-weight-bold text-blue-grey-darken-4">
                                {{ commande.service_beneficiaire }}
                                <span class="text-grey mx-2">|</span>
                                <v-icon size="12" class="mr-1">mdi-account</v-icon>{{ commande.demandeur_nom }}
                            </span>
                            <v-spacer></v-spacer>
                            <v-btn size="x-small" variant="outlined" color="blue-grey-darken-2"
                                prepend-icon="mdi-file-eye-outline"
                                class="text-none font-weight-bold bg-white text-caption"
                                @click="allerAuBon(commande.service_beneficiaire, commande.date_demande, commande.demandeur_nom)">
                                Voir
                            </v-btn>
                        </div>
                    </div>

                    <!-- Tableau des demandes de la commande AVEC SCROLL -->
                    <div class="table-container" :style="{ maxHeight: '400px', overflowY: 'auto' }">
                        <v-table density="compact" class="custom-table">
                            <thead class="table-header-sticky">
                                <tr class="bg-teal-lighten-5">
                                    <th class="text-teal-darken-3 font-weight-bold text-left text-caption">Désignation
                                    </th>
                                    <th class="text-teal-darken-3 font-weight-bold text-center text-caption">N° de Série
                                    </th>
                                    <th class="text-teal-darken-3 font-weight-bold text-center text-caption">Qté</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="item in commande.demandes" :key="item.id" class="table-row">
                                    <!-- Colonne Désignation -->
                                    <td class="py-1 text-caption">
                                        <template v-if="item.est_sortie_uniquement_piece">
                                            <div v-for="(piece, pIdx) in (item.pieces || [])" :key="piece.id"
                                                class="d-flex align-center" :class="{ 'mt-1': pIdx > 0 }">
                                                <v-icon color="orange-darken-2" size="x-small"
                                                    class="mr-2">mdi-puzzle</v-icon>
                                                <span class="font-weight-bold text-uppercase text-orange-darken-4">{{
                                                    piece.nom_piece }}</span>
                                            </div>
                                            <div class="italic ml-7 mt-1" style="color: #888; font-size: 0.65rem;">
                                                Origine : {{ item.nom_materiel }}
                                                <span v-if="item.numero_serie">(S/N : {{ item.numero_serie }})</span>
                                            </div>
                                        </template>
                                        <template v-else>
                                            <div class="d-flex align-center">
                                                <v-icon color="teal-darken-2" size="x-small"
                                                    class="mr-2">mdi-monitor</v-icon>
                                                <span class="font-weight-bold text-uppercase">{{ item.nom_materiel
                                                }}</span>
                                            </div>
                                            <div v-if="item.pieces && item.pieces.length > 0" class="mt-1">
                                                <div v-for="p in item.pieces" :key="p.id"
                                                    class="d-flex align-center ml-7">
                                                    <v-icon size="10" color="teal-darken-3"
                                                        class="mr-1">mdi-plus-circle</v-icon>
                                                    <span class="text-teal-darken-3">{{ p.nom_piece }}</span>
                                                </div>
                                            </div>
                                            <div v-else class="italic ml-7" style="color: #666; font-size: 0.65rem;">
                                                (Matériel complet sans pièces détachées)
                                            </div>
                                        </template>
                                    </td>

                                    <!-- Colonne N° de Série -->
                                    <td class="text-center font-mono text-caption">
                                        <template v-if="item.est_sortie_uniquement_piece">
                                            <div v-for="piece in item.pieces" :key="'sn-' + piece.id"
                                                class="py-0 text-orange-darken-3">
                                                {{ piece.numero_serie || "—" }}
                                            </div>
                                            <div class="py-0 invisible">—</div>
                                        </template>
                                        <template v-else-if="item.pieces && item.pieces.length > 0">
                                            <div class="py-0 font-weight-bold">{{ item.numero_serie || "—" }}</div>
                                            <div v-for="piece in item.pieces" :key="'sn-' + piece.id"
                                                class="py-0 text-teal-darken-3">
                                                {{ piece.numero_serie || "—" }}
                                            </div>
                                        </template>
                                        <template v-else>
                                            <div class="py-0">{{ item.numero_serie || "—" }}</div>
                                        </template>
                                    </td>

                                    <!-- Colonne Quantité -->
                                    <td class="text-center font-weight-black text-caption">
                                        <template v-if="item.est_sortie_uniquement_piece">
                                            <div v-for="piece in item.pieces" :key="'qty-' + piece.id" class="py-0">1
                                            </div>
                                            <div class="py-0 invisible">—</div>
                                        </template>
                                        <template v-else-if="item.pieces && item.pieces.length > 0">
                                            <div class="py-0">{{ item.nbredemande }}</div>
                                            <div v-for="piece in item.pieces" :key="'qty-' + piece.id" class="py-0">1
                                            </div>
                                        </template>
                                        <template v-else>
                                            <div class="py-0">{{ item.nbredemande }}</div>
                                        </template>
                                    </td>
                                </tr>
                            </tbody>
                        </v-table>
                    </div>
                </v-card>
            </div>

            <!-- Pagination -->
            <v-row v-if="props.historique.last_page > 1" justify="center" class="mt-6 mb-10">
                <v-pagination :model-value="props.historique.current_page" :length="props.historique.last_page"
                    @update:model-value="changerPage" color="blue-grey-darken-3" density="compact" size="small"
                    :total-visible="5" />
                <div class="text-caption text-grey ml-3 mt-1">
                    Page {{ props.historique.current_page }} / {{ props.historique.last_page }} -
                    {{ props.historique.total }} commandes
                </div>
            </v-row>

            <!-- Message vide -->
            <v-card v-if="!props.historique.data || props.historique.data.length === 0"
                class="pa-8 text-center rounded-lg" variant="outlined" border>
                <v-icon size="48" color="grey-lighten-1">mdi-tray-search</v-icon>
                <div class="text-body-1 text-grey mt-2">Aucune archive ne correspond à votre recherche</div>
            </v-card>

            <!-- Overlay de progression pour l'export PDF -->
            <v-overlay v-model="exportLoading" class="align-center justify-center" persistent :scrim="true"
                style="z-index: 9999">
                <v-card class="pa-6 text-center" elevation="12" width="400" rounded="lg">
                    <v-progress-circular indeterminate :size="70" :width="8" color="primary"></v-progress-circular>

                    <div class="mt-4">
                        <v-icon color="primary" size="24" class="mr-2">mdi-file-pdf-box</v-icon>
                        <span class="text-subtitle-1 font-weight-medium text-grey-darken-4">
                            Export en cours...
                        </span>
                    </div>

                    <p class="text-body-2 text-grey-darken-3 mt-3 mb-0 font-weight-medium">
                        {{ exportMessage }}
                    </p>

                    <p class="text-caption text-grey mt-4">
                        <v-icon size="12">mdi-clock-outline</v-icon>
                        Si le téléchargement ne démarre pas,
                        <a href="#" @click.prevent="retryExport" class="text-primary font-weight-bold">
                            cliquez ici
                        </a>
                    </p>

                    <v-btn variant="text" size="small" color="grey-darken-1" class="mt-4"
                        @click="exportLoading = false">
                        Fermer
                    </v-btn>
                </v-card>
            </v-overlay>
        </v-container>
    </AuthentDemandeLayout>
</template>

<style scoped>
.custom-table :deep(th) {
    background-color: #ffffff !important;
    text-transform: uppercase !important;
    font-size: 0.7rem !important;
    font-weight: 800 !important;
    color: #455a64 !important;
    border-bottom: 1px solid #eceff1 !important;
    padding: 6px 8px !important;
}

.custom-table :deep(td) {
    padding: 4px 8px !important;
    font-size: 0.7rem !important;
}

.table-row:hover {
    background-color: #fafafa;
}

.border-thin {
    border: 1px solid #e0e0e0 !important;
}

.border-b {
    border-bottom: 1px solid #e0e0e0;
}

.refresh-btn:hover :deep(.v-icon) {
    transform: rotate(180deg);
    transition: transform 0.4s ease-in-out;
}

.text-caption {
    font-size: 0.7rem !important;
}

.v-chip {
    font-size: 0.65rem !important;
    height: 20px !important;
}

.invisible {
    visibility: hidden;
}

/* Style pour le conteneur avec scroll */
.table-container {
    position: relative;
}

/* Header sticky pour le scroll */
.table-header-sticky :deep(th) {
    position: sticky;
    top: 0;
    background-color: #e0f2f1 !important;
    z-index: 10;
}
</style>
