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
        est_sortie_uniquement_piece?: boolean;
    }

    const props = defineProps<{
        historique: {
            data: HistoriqueItem[];
            current_page: number;
            last_page: number;
            total: number;
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
        router.get(
            route("demandes.historique"),
            {
                search: recherche.value,
                year: anneeFiltre.value,
                month: moisFiltre.value,
                service: serviceFiltre.value,
                page: 1
            },
            {
                preserveState: true,
                replace: true,
                onSuccess: () => window.scrollTo(0, 0)
            }
        );
    };

    // --- Surveillances (Watchers) ---
    watch(serviceFiltre, () => appliquerFiltres());
    watch(recherche, debounce(() => appliquerFiltres(), 400));
    watch(anneeFiltre, () => appliquerFiltres());
    watch(moisFiltre, (nouveauMois) => {
        if (nouveauMois && !anneeFiltre.value) {
            anneeFiltre.value = new Date().getFullYear().toString();
        }
        appliquerFiltres();
    });

    // Groupement des données
    const historiqueGroupe = computed(() => {
        const rawData = [...(props.historique?.data || [])];

        const groups: Record<string, Record<string, Record<string, HistoriqueItem[]>>> = {};

        rawData.forEach((item) => {
            const d = item.date_demande || "Date Inconnue";
            const s = item.service_beneficiaire || "Service non défini";
            const u = item.demandeur_nom || "Sans nom";

            if (!groups[d]) groups[d] = {};
            if (!groups[d][s]) groups[d][s] = {};
            if (!groups[d][s][u]) groups[d][s][u] = [];

            groups[d][s][u].push(item);
        });

        const datesTriees = Object.keys(groups).sort((a, b) => {
            if (a === "Date Inconnue") return 1;
            if (b === "Date Inconnue") return -1;
            return new Date(b).getTime() - new Date(a).getTime();
        });

        const result: Record<string, any> = {};
        datesTriees.forEach(date => {
            result[date] = groups[date];
        });

        return result;
    });

    // --- NOUVEAU COMPUTED : Grouper les items par numéro de commande ---
    const itemsGroupesParCommande = computed(() => {
        const tousLesItems: HistoriqueItem[] = [];

        Object.values(historiqueGroupe.value).forEach((servicesObj: any) => {
            Object.values(servicesObj).forEach((demandeurs: any) => {
                Object.values(demandeurs).forEach((items: any) => {
                    tousLesItems.push(...items);
                });
            });
        });

        const groupes: Record<string, HistoriqueItem[]> = {};

        tousLesItems.forEach(item => {
            if (!groupes[item.numcomande]) {
                groupes[item.numcomande] = [];
            }
            groupes[item.numcomande].push(item);
        });

        return Object.values(groupes).sort((a, b) =>
            b[0].numcomande.localeCompare(a[0].numcomande, undefined, { numeric: true })
        );
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
        router.get(
            route("demandes.historique"),
            {
                search: recherche.value,
                year: anneeFiltre.value,
                month: moisFiltre.value,
                service: serviceFiltre.value,
                page: page
            },
            { preserveState: true, preserveScroll: true }
        );
    };

    const exporterPDF = () => {
        window.location.href = route('demandes.pdf', {
            search: recherche.value || '',
            year: anneeFiltre.value || '',
            month: moisFiltre.value || '',
            service: serviceFiltre.value || ''
        });
    };
</script>

<template>

    <Head title="Historique des Sorties" />
    <AuthentDemandeLayout>
        <v-container fluid class="pa-6 bg-grey-lighten-4 min-vh-100">
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
                        <v-text-field v-model="recherche" label="Rechercher..." variant="solo" flat density="comfortable" prepend-inner-icon="mdi-magnify" hide-details clearable class="flex-grow-1 border-thin rounded-lg" style="font-size: 0.75rem;"></v-text-field>

                        <v-fade-transition>
                            <v-btn v-if="recherche || anneeFiltre || moisFiltre || serviceFiltre" icon="mdi-refresh" variant="outlined" color="blue-grey-darken-2" height="48" width="48" class="rounded-lg bg-white border-thin refresh-btn" @click="afficherTout"></v-btn>
                        </v-fade-transition>

                        <v-btn variant="outlined" color="blue-grey-darken-2" prepend-icon="mdi-file-pdf-box" height="48" class="px-6 font-weight-bold ml-2 bg-white border-thin text-none rounded-lg" style="font-size: 0.75rem;" @click="exporterPDF">
                            Exporter PDF
                        </v-btn>
                    </div>
                </v-col>
            </v-row>

            <v-row class="mb-8" dense>
                <v-col cols="6" sm="2">
                    <v-select v-model="anneeFiltre" :items="annees" label="Année" variant="solo" flat density="comfortable" hide-details clearable style="font-size: 0.75rem;"></v-select>
                </v-col>
                <v-col cols="6" sm="3">
                    <v-select v-model="moisFiltre" :items="mois" item-title="title" item-value="value" label="Mois" variant="solo" flat density="comfortable" hide-details clearable style="font-size: 0.75rem;"></v-select>
                </v-col>
                <v-col cols="12" sm="7">
                    <v-autocomplete v-model="serviceFiltre" :items="services" item-title="nom" item-value="nom" label="Filtrer par Unité" variant="solo" flat density="comfortable" prepend-inner-icon="mdi-filter-variant" hide-details clearable style="font-size: 0.75rem;"></v-autocomplete>
                </v-col>
            </v-row>

            <!-- Affichage par groupes de commandes -->
            <div class="mb-10">
                <v-card v-for="commandeItems in itemsGroupesParCommande" :key="commandeItems[0].numcomande" class="mb-6 rounded-lg border-thin" elevation="0">
                    <!-- En-tête avec numéro de commande -->
                    <div class="bg-teal-lighten-4 pa-2 d-flex align-center">
                        <v-icon size="16" color="teal-darken-3" class="mr-2">mdi-file-document-outline</v-icon>
                        <span class="font-weight-bold text-teal-darken-4 text-caption">Commande #{{ commandeItems[0].numcomande }}</span>
                        <v-chip size="x-small" color="teal-darken-2" variant="flat" class="ml-3">
                            {{ commandeItems.length }} art.
                        </v-chip>
                        <v-spacer></v-spacer>
                        <span class="text-caption text-grey-darken-1">
                            {{ formatDate(commandeItems[0].date_demande) }}
                        </span>
                    </div>

                    <!-- Informations du service et demandeur -->
                    <div class="pa-2 bg-grey-lighten-5 border-b">
                        <div class="d-flex align-center">
                            <v-icon size="14" class="mr-2 text-grey-darken-2">mdi-office-building-marker</v-icon>
                            <span class="text-caption font-weight-bold text-blue-grey-darken-4">
                                {{ commandeItems[0].service_beneficiaire }}
                                <span class="text-grey mx-2">|</span>
                                <v-icon size="12" class="mr-1">mdi-account</v-icon>{{ commandeItems[0].demandeur_nom }}
                            </span>
                            <v-spacer></v-spacer>
                            <v-btn size="x-small" variant="outlined" color="blue-grey-darken-2" prepend-icon="mdi-file-eye-outline" class="text-none font-weight-bold bg-white text-caption" @click="allerAuBon(commandeItems[0].service_beneficiaire, commandeItems[0].date_demande, commandeItems[0].demandeur_nom)">
                                Voir
                            </v-btn>
                        </div>
                    </div>

                    <!-- Tableau des articles -->
                    <v-table density="compact" class="custom-table">
                        <thead>
                            <tr class="bg-teal-lighten-5">
                                <th class="text-teal-darken-3 font-weight-bold text-left text-caption">Désignation</th>
                                <th class="text-teal-darken-3 font-weight-bold text-center text-caption">N° de Série</th>
                                <th class="text-teal-darken-3 font-weight-bold text-center text-caption">Qté</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="item in commandeItems" :key="item.id" class="table-row">
                                <!-- Colonne Désignation -->
                                <td class="py-1 text-caption">
                                    <!-- Cas 1 : Pièces seules -->
                                    <template v-if="item.est_sortie_uniquement_piece">
                                        <div v-for="(piece, pIdx) in (item.pieces || [])" :key="piece.id" class="d-flex align-center" :class="{ 'mt-1': pIdx > 0 }">
                                            <v-icon color="orange-darken-2" size="x-small" class="mr-2">mdi-puzzle</v-icon>
                                            <span class="font-weight-bold text-uppercase text-orange-darken-4">{{ piece.nom_piece }}</span>
                                        </div>
                                        <div class="italic ml-7 mt-1" style="color: #888; font-size: 0.65rem;">
                                            Origine : {{ item.nom_materiel }}
                                            <span v-if="item.numero_serie">(S/N : {{ item.numero_serie }})</span>
                                        </div>
                                    </template>

                                    <!-- Cas 2 : Matériel avec ou sans pièces -->
                                    <template v-else>
                                        <div class="d-flex align-center">
                                            <v-icon color="teal-darken-2" size="x-small" class="mr-2">mdi-monitor</v-icon>
                                            <span class="font-weight-bold text-uppercase">{{ item.nom_materiel }}</span>
                                        </div>

                                        <!-- Affichage des pièces incluses -->
                                        <div v-if="item.pieces && item.pieces.length > 0" class="mt-1">
                                            <div v-for="p in item.pieces" :key="p.id" class="d-flex align-center ml-7">
                                                <v-icon size="10" color="teal-darken-3" class="mr-1">mdi-plus-circle</v-icon>
                                                <span class="text-teal-darken-3">{{ p.nom_piece }}</span>
                                            </div>
                                        </div>

                                        <!-- Message si pas de pièces -->
                                        <div v-else class="italic ml-7" style="color: #666; font-size: 0.65rem;">
                                            (Matériel complet sans pièces détachées)
                                        </div>
                                    </template>
                                </td>

                                <!-- Colonne N° de Série -->
                                <td class="text-center font-mono text-caption">
                                    <!-- Cas 1 : Pièces seules -->
                                    <template v-if="item.est_sortie_uniquement_piece">
                                        <div v-for="piece in item.pieces" :key="'sn-' + piece.id" class="py-0 text-orange-darken-3">
                                            {{ piece.numero_serie || "—" }}
                                        </div>
                                        <div class="py-0 invisible">—</div>
                                    </template>

                                    <!-- Cas 2 : Matériel avec pièces -->
                                    <template v-else-if="item.pieces && item.pieces.length > 0">
                                        <div class="py-0 font-weight-bold">{{ item.numero_serie || "—" }}</div>
                                        <div v-for="piece in item.pieces" :key="'sn-' + piece.id" class="py-0 text-teal-darken-3">
                                            {{ piece.numero_serie || "—" }}
                                        </div>
                                    </template>

                                    <!-- Cas 3 : Matériel seul -->
                                    <template v-else>
                                        <div class="py-0">{{ item.numero_serie || "—" }}</div>
                                    </template>
                                </td>

                                <!-- Colonne Quantité -->
                                <td class="text-center font-weight-black text-caption">
                                    <!-- Cas 1 : Pièces seules -->
                                    <template v-if="item.est_sortie_uniquement_piece">
                                        <div v-for="piece in item.pieces" :key="'qty-' + piece.id" class="py-0">
                                            1
                                        </div>
                                        <div class="py-0 invisible">—</div>
                                    </template>

                                    <!-- Cas 2 : Matériel avec pièces -->
                                    <template v-else-if="item.pieces && item.pieces.length > 0">
                                        <div class="py-0">{{ item.nbredemande }}</div>
                                        <div v-for="piece in item.pieces" :key="'qty-' + piece.id" class="py-0">
                                            1
                                        </div>
                                    </template>

                                    <!-- Cas 3 : Matériel seul -->
                                    <template v-else>
                                        <div class="py-0">{{ item.nbredemande }}</div>
                                    </template>
                                </td>
                            </tr>
                        </tbody>
                    </v-table>
                </v-card>
            </div>

            <v-row v-if="props.historique.last_page > 1" justify="center" class="mt-6 mb-10">
                <v-pagination v-model="props.historique.current_page" :length="props.historique.last_page" @update:model-value="changerPage" color="blue-grey-darken-3" density="compact" size="small"></v-pagination>
            </v-row>

            <v-card v-if="Object.keys(historiqueGroupe).length === 0" class="pa-8 text-center rounded-lg" variant="outlined" border>
                <v-icon size="48" color="grey-lighten-1">mdi-tray-search</v-icon>
                <div class="text-body-1 text-grey mt-2">Aucune archive ne correspond à votre recherche</div>
            </v-card>
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
</style>
