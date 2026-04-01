<script setup>
    import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
    import { Head, router } from "@inertiajs/vue3";
    import { ref, computed } from "vue";

    const props = defineProps({
        inventaire: {
            type: Object,
            default: null,
        },
        details: {
            type: [Object, Array],
            default: () => ({ data: [] }),
        },
    });

    // États
    const search = ref("");
    const isLoading = ref(false);
    const page = ref(1);

    // Sécurisation des données
    const safeDetails = computed(() => {
        if (!props.details) return [];
        if (props.details.data && Array.isArray(props.details.data)) {
            return props.details.data;
        }
        if (Array.isArray(props.details)) {
            return props.details;
        }
        return [];
    });

    // Informations de pagination
    const paginationInfo = computed(() => {
        if (props.details && props.details.current_page) {
            return {
                currentPage: props.details.current_page,
                lastPage: props.details.last_page,
                total: props.details.total,
                perPage: props.details.per_page,
                from: props.details.from,
                to: props.details.to,
            };
        }
        return {
            currentPage: 1,
            lastPage: 1,
            total: safeDetails.value.length,
            perPage: safeDetails.value.length,
            from: 1,
            to: safeDetails.value.length,
        };
    });

    // Regrouper les fournisseurs pour l'impression
    const fournisseursList = computed(() => {
        const fournisseurs = {};
        safeDetails.value.forEach(item => {
            const fournisseur = item.fournisseur || 'Non renseigné';
            if (!fournisseurs[fournisseur]) {
                fournisseurs[fournisseur] = {
                    nom: fournisseur,
                    contrat: item.numero_contrat || 'N/A',
                    count: 0,
                    categories: new Set()
                };
            }
            fournisseurs[fournisseur].count++;
            if (item.categorie) {
                fournisseurs[fournisseur].categories.add(item.categorie);
            }
        });
        return Object.values(fournisseurs);
    });

    const headers = [
        {
            title: "DÉSIGNATION DU MATÉRIEL",
            key: "designation",
            align: "start",
            sortable: true,
        },
        {
            title: "N° SÉRIE",
            key: "numero_serie",
            align: "start",
            sortable: true,
        },
        {
            title: "CATÉGORIE",
            key: "categorie",
            align: "start",
            sortable: true,
        },
        {
            title: "ÉTAT LOGIQUE",
            key: "etat_materiel",
            align: "center",
            sortable: true,
        },
        {
            title: "INTÉGRITÉ",
            key: "est_complet",
            align: "center",
            sortable: true,
        },
        {
            title: "LOCALISATION / SORTIE",
            key: "localisation",
            align: "start",
            sortable: true,
        },
    ];

    const groupBy = [{ key: "fournisseur", order: "asc" }];

    // Méthodes
    const retour = () => router.visit(route("inventaire.index"));

    const telechargerPDF = () => {
        if (props.inventaire?.id) {
            window.open(route("inventaire.pdf", props.inventaire.id), "_blank");
        }
    };

    const imprimerPage = () => window.print();

    // Fonction pour changer de page
    const changePage = (newPage) => {
        isLoading.value = true;
        router.get(
            route("inventaire.show", props.inventaire?.id),
            { page: newPage },
            {
                preserveState: true,
                preserveScroll: true,
                onFinish: () => {
                    isLoading.value = false;
                }
            }
        );
    };

    // Fonctions utilitaires
    const estComplet = (item) => {
        return item.est_complet ?? true;
    };

    const nombrePieces = (item) => {
        return item.pieces?.length || 0;
    };

    const formatDate = (dateString) => {
        if (!dateString) return 'Date inconnue';
        return new Date(dateString).toLocaleDateString("fr-FR", {
            day: "2-digit",
            month: "short",
            year: "numeric",
        });
    };
</script>

<template>

    <Head :title="'Détails Archive ' + (inventaire?.annee || '')" />
    <AuthenticatedLayout>
        <!-- Barre d'outils - masquée à l'impression -->
        <v-toolbar color="white" flat border density="comfortable" class="no-print">
            <v-btn prepend-icon="mdi-arrow-left" variant="text" color="teal-darken-3" class="rounded-lg font-weight-bold ml-2" @click="retour">
                Retour
            </v-btn>
            <v-divider vertical inset class="mx-4"></v-divider>
            <v-toolbar-title class="font-weight-black text-teal-darken-4 text-subtitle-1">
                ARCHIVE INVENTAIRE {{ inventaire?.annee }}
                <span v-if="inventaire?.responsable" class="text-caption text-grey ml-2">
                    ({{ inventaire.responsable }})
                </span>
            </v-toolbar-title>
            <v-spacer></v-spacer>
            <v-btn prepend-icon="mdi-printer" variant="outlined" color="teal-darken-1" class="rounded-lg font-weight-bold mr-2" @click="imprimerPage">
                Imprimer
            </v-btn>
            <v-btn prepend-icon="mdi-file-pdf-box" color="teal-darken-3" variant="flat" class="rounded-lg font-weight-bold mr-2" @click="telechargerPDF">
                Exporter PDF
            </v-btn>
        </v-toolbar>

        <v-container fluid class="pa-4 main-container bg-teal-lighten-5 min-vh-100">
            <!-- Vue normale à l'écran -->
            <v-card border flat class="rounded-xl shadow-sm overflow-hidden d-flex flex-column main-card-container bg-white screen-only">
                <v-card-title class="pa-4 border-b d-flex align-center flex-shrink-0 no-print">
                    <span class="text-caption font-weight-black text-uppercase text-teal-darken-3">
                        Contenu archivé
                    </span>
                    <v-spacer></v-spacer>
                    <v-text-field v-model="search" prepend-inner-icon="mdi-magnify" label="Rechercher dans l'inventaire..." variant="solo-filled" density="compact" hide-details rounded="lg" flat bg-color="teal-lighten-5" class="search-bar mr-4" />
                    <v-chip color="teal-darken-3" variant="flat" size="small" class="font-weight-bold">
                        {{ paginationInfo.total }} ÉLÉMENT{{ paginationInfo.total > 1 ? 'S' : '' }}
                    </v-chip>
                </v-card-title>

                <div class="flex-grow-1 overflow-y-auto">
                    <!-- Indicateur de chargement -->
                    <div v-if="isLoading" class="text-center pa-8">
                        <v-progress-circular indeterminate color="teal-darken-3" size="48"></v-progress-circular>
                        <p class="text-caption text-grey mt-4">Chargement des données...</p>
                    </div>

                    <!-- Message si aucun détail -->
                    <div v-else-if="safeDetails.length === 0" class="pa-8 text-center">
                        <p class="text-h6 text-grey-darken-1 mt-4">Aucun détail d'inventaire trouvé</p>
                    </div>

                    <!-- Tableau des données -->
                    <v-data-table v-else :headers="headers" :items="safeDetails" :search="search" :group-by="groupBy" :items-per-page="-1" hover class="custom-table" hide-default-footer>
                        <!-- Template pour la désignation -->
                        <template #[`item.designation`]="{ item }">
                            <div class="py-2">
                                <div class="d-flex align-center">
                                    <div class="font-weight-black text-teal-darken-4 text-uppercase">
                                        {{ item.designation }}
                                    </div>

                                    <v-chip v-if="nombrePieces(item) > 0" size="x-small" variant="flat" color="teal-lighten-4" class="ml-2 font-weight-black text-teal-darken-4 px-2" style="height: 18px">
                                        {{ nombrePieces(item) }} PIÈCE{{ nombrePieces(item) > 1 ? "S" : "" }}
                                    </v-chip>
                                </div>

                                <!-- Affichage des pièces -->
                                <div v-if="item.pieces && item.pieces.length > 0" class="mt-2 pl-2">
                                    <div v-for="piece in item.pieces" :key="piece.id" class="text-caption d-flex align-center mb-1">
                                        <span class="mr-2">{{ piece.nom }}</span>
                                        <v-chip size="x-small" :color="piece.demande ? 'blue-grey-darken-2' : item.demande ? 'blue-grey-lighten-1' : 'teal-lighten-3'" variant="flat" class="px-1" style="height: 16px; font-size: 9px">
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

                        <!-- Template pour le numéro de série -->
                        <template #[`item.numero_serie`]="{ item }">
                            <code class="text-body-2 font-weight-bold bg-teal-lighten-5 text-teal-darken-3 px-2 py-1 rounded">
                                {{ item.numero_serie }}
                            </code>
                        </template>

                        <!-- Template pour la catégorie -->
                        <template #[`item.categorie`]="{ item }">
                            <span class="text-caption">{{ item.categorie || 'N/A' }}</span>
                        </template>

                        <!-- Template pour l'état -->
                        <template #[`item.etat_materiel`]="{ item }">
                            <v-chip size="x-small" :color="item.etat_materiel === 'Bon' || item.etat_materiel === 'Disponible'
                                ? 'teal-darken-1'
                                : 'orange-darken-2'
                                " variant="flat" class="font-weight-black">
                                {{ item.etat_materiel }}
                            </v-chip>
                        </template>

                        <!-- Template pour l'intégrité -->
                        <template #[`item.est_complet`]="{ item }">
                            <div class="d-flex align-center justify-center">
                                <span :class="!estComplet(item) ? 'text-red-darken-2' : 'text-teal-darken-3'" class="text-caption font-weight-bold">
                                    {{ estComplet(item) ? "Complet" : "Incomplet" }}
                                </span>
                            </div>
                        </template>

                        <!-- Template pour la localisation -->
                        <template #[`item.localisation`]="{ item }">
                            <div v-if="item.demande" class="d-flex flex-column py-1">
                                <div class="text-caption font-weight-black text-teal-darken-4 text-uppercase d-flex align-center">
                                    {{ item.demande.demandeur }}
                                </div>
                                <div class="text-caption text-orange-darken-4 font-weight-bold d-flex align-center">
                                    LIVRÉ : {{ item.demande.service }}
                                </div>
                            </div>
                            <v-chip v-else size="x-small" color="teal-darken-1" variant="flat" class="font-weight-bold">
                                AU MAGASIN
                            </v-chip>
                        </template>

                        <!-- Template pour l'en-tête de groupe -->
                        <template v-slot:group-header="{
                            item,
                            columns,
                            toggleGroup,
                            isGroupOpen,
                        }">
                            <tr class="bg-teal-lighten-5 group-row">
                                <td :colspan="columns.length">
                                    <span class="font-weight-black text-uppercase text-teal-darken-4">
                                        FOURNISSEUR : {{ item.value || "Non renseigné" }}
                                    </span>
                                    <span v-if="item.items && item.items[0]" class="print-only" style="margin-left: 20px">
                                        - CONTRAT : {{ item.items[0]?.raw?.numero_contrat || "N/A" }}
                                    </span>
                                </td>
                            </tr>
                        </template>

                        <!-- Slot vide si pas de données -->
                        <template v-slot:no-data>
                            <div class="pa-4 text-center text-grey-darken-1">
                                Aucune donnée disponible
                            </div>
                        </template>
                    </v-data-table>

                    <!-- Pagination -->
                    <div v-if="paginationInfo.lastPage > 1" class="d-flex justify-center align-center pa-4 no-print">
                        <v-pagination v-model="page" :length="paginationInfo.lastPage" :total-visible="7" @update:model-value="changePage" color="teal-darken-3"></v-pagination>
                        <span class="text-caption text-grey ml-4">
                            {{ paginationInfo.from }} - {{ paginationInfo.to }} sur {{ paginationInfo.total }}
                        </span>
                    </div>
                </div>
            </v-card>

            <!-- ===== SECTION IMPRESSION OPTIMISÉE ===== -->
            <div class="print-section">
                <!-- En-tête -->
                <div class="print-header">
                    <h1>INVENTAIRE DU MATÉRIEL - {{ inventaire?.annee }}</h1>
                    <div class="print-header-info">
                        <p>Responsable : {{ inventaire?.responsable || 'Système' }}</p>
                        <p>Date : {{ new Date().toLocaleDateString('fr-FR') }}</p>
                    </div>
                </div>

                <!-- Tableau des fournisseurs -->
                <table class="print-table">
                    <thead>
                        <tr>
                            <th width="35%">FOURNISSEUR</th>
                            <th width="20%">CONTRAT</th>
                            <th width="15%">NB ARTICLES</th>
                            <th width="30%">CATÉGORIES</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="fournisseur in fournisseursList" :key="fournisseur.nom">
                            <td><strong>{{ fournisseur.nom }}</strong></td>
                            <td>{{ fournisseur.contrat }}</td>
                            <td class="text-center">{{ fournisseur.count }}</td>
                            <td>{{ Array.from(fournisseur.categories).join(', ') }}</td>
                        </tr>
                        <tr class="print-total">
                            <td colspan="2" class="text-right"><strong>TOTAL :</strong></td>
                            <td class="text-center"><strong>{{ safeDetails.length }}</strong></td>
                            <td></td>
                        </tr>
                    </tbody>
                </table>

                <!-- Signatures (toujours visibles) -->
                <div class="print-signatures">
                    <div class="signature-box">
                        <h4>LE RESPONSABLE MAGASIN</h4>
                        <div class="signature-lines">
                            <p>Nom : _________________________</p>
                            <p>Signature : ____________________</p>
                        </div>
                    </div>
                    <div class="signature-box">
                        <h4>AUDIT / DIRECTION</h4>
                        <div class="signature-lines">
                            <p>Observations : ________________</p>
                            <p>Cachet & Signature : ___________</p>
                        </div>
                    </div>
                </div>
            </div>
        </v-container>
    </AuthenticatedLayout>
</template>

<style scoped>
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
        text-transform: uppercase !important;
    }

    /* Section impression - masquée par défaut */
    .print-section {
        display: none;
    }

    /* ===== STYLES D'IMPRESSION OPTIMISÉS ===== */
    @media print {

        /* Masquer les éléments d'interface */
        .no-print,
        .screen-only,
        .v-toolbar,
        .main-card-container,
        .v-icon {
            display: none !important;
        }

        /* Configuration de la page */
        @page {
            margin: 1.5cm;
            size: A4 portrait;
        }

        /* Réinitialisation */
        body {
            background: white !important;
            font-family: 'Times New Roman', serif !important;
            line-height: 1.3 !important;
            margin: 0 !important;
            padding: 0 !important;
        }

        /* Afficher la section d'impression */
        .print-section {
            display: block !important;
            width: 100%;
        }

        /* En-tête */
        .print-header {
            text-align: center;
            margin-bottom: 15px;
            padding-bottom: 8px;
            border-bottom: 2px solid #000;
        }

        .print-header h1 {
            font-size: 16pt;
            font-weight: bold;
            margin: 0 0 5px 0;
        }

        .print-header-info {
            display: flex;
            justify-content: space-between;
            font-size: 10pt;
            margin: 0;
        }

        .print-header-info p {
            margin: 0;
        }

        /* Tableau */
        .print-table {
            width: 100%;
            border-collapse: collapse;
            margin: 10px 0 20px 0;
            font-size: 10pt;
        }

        .print-table th {
            background-color: #e0e0e0 !important;
            border: 1px solid #000;
            padding: 6px 4px;
            text-align: left;
            font-weight: bold;
            font-size: 9pt;
        }

        .print-table td {
            border: 1px solid #000;
            padding: 4px 4px;
        }

        .print-total td {
            background-color: #f0f0f0 !important;
            font-weight: bold;
        }

        .text-center {
            text-align: center;
        }

        .text-right {
            text-align: right;
        }

        /* Signatures - toujours visibles */
        .print-signatures {
            display: flex;
            justify-content: space-between;
            margin-top: 30px;
            page-break-inside: avoid;
        }

        .signature-box {
            width: 45%;
            border: 1px solid #000;
            padding: 10px;
        }

        .signature-box h4 {
            margin: 0 0 8px 0;
            font-size: 10pt;
            text-align: center;
        }

        .signature-lines p {
            margin: 4px 0;
            font-size: 9pt;
        }

        /* Forcer les couleurs */
        .print-table th,
        .print-total td {
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }
    }
</style>
