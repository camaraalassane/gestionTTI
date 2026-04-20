<script setup>
    import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
    import { Head, router } from "@inertiajs/vue3";
    import { ref, computed } from "vue";

    const props = defineProps({
        inventaire: {
            type: Object,
            default: null,
        },
        groupes: {
            type: Object,
            default: () => ({ data: [] }),
        },
    });

    const search = ref("");
    const isLoading = ref(false);

    // Filtrer les groupes par recherche
    const filteredGroupes = computed(() => {
        if (!search.value) return props.groupes?.data || [];
        const searchLower = search.value.toLowerCase();
        return (props.groupes?.data || []).filter(group =>
            group.fournisseur?.toLowerCase().includes(searchLower) ||
            group.numero_contrat?.toLowerCase().includes(searchLower) ||
            group.modeles?.some(m => m.designation?.toLowerCase().includes(searchLower))
        );
    });

    const retour = () => router.visit(route("inventaire.index"));
    const telechargerPDF = () => window.open(route("inventaire.pdf", props.inventaire?.id), "_blank");
    const imprimerPage = () => window.print();

    const changePage = (newPage) => {
        isLoading.value = true;
        router.get(
            route("inventaire.show", props.inventaire?.id),
            { page: newPage },
            {
                preserveState: true,
                preserveScroll: true,
                onFinish: () => { isLoading.value = false; }
            }
        );
    };
</script>

<template>

    <Head :title="'Détails Archive ' + (inventaire?.annee || '')" />
    <AuthenticatedLayout>
        <v-toolbar color="white" flat border density="comfortable" class="no-print">
            <v-btn prepend-icon="mdi-arrow-left" variant="text" color="teal-darken-3" class="rounded-lg font-weight-bold ml-2" @click="retour">
                Retour
            </v-btn>
            <v-divider vertical inset class="mx-4"></v-divider>
            <v-toolbar-title class="font-weight-black text-teal-darken-4 text-subtitle-1">
                INVENTAIRE {{ inventaire?.annee }}
            </v-toolbar-title>
            <v-spacer></v-spacer>
            <v-btn prepend-icon="mdi-printer" variant="outlined" color="teal-darken-1" class="rounded-lg font-weight-bold mr-2" @click="imprimerPage">
                Imprimer
            </v-btn>
            <v-btn prepend-icon="mdi-file-pdf-box" color="teal-darken-3" variant="flat" class="rounded-lg font-weight-bold mr-2" @click="telechargerPDF">
                Exporter PDF
            </v-btn>
        </v-toolbar>

        <v-container fluid class="pa-4 bg-teal-lighten-5 min-vh-100">
            <v-card class="rounded-xl">
                <v-card-title class="pa-4 border-b">
                    <div class="d-flex align-center">
                        <span class="text-caption font-weight-bold text-teal-darken-3">
                            RÉCAPITULATIF PAR FOURNISSEUR
                        </span>
                        <v-spacer></v-spacer>
                        <v-text-field 
                            v-model="search" 
                            prepend-inner-icon="mdi-magnify" 
                            label="Rechercher..." 
                            density="compact" 
                            hide-details 
                            class="search-bar"
                        />
                    </div>
                </v-card-title>

                <v-divider />

                <div class="pa-4">
                    <div v-if="isLoading" class="text-center pa-8">
                        <v-progress-circular indeterminate color="teal-darken-3" size="48"></v-progress-circular>
                        <p class="text-caption text-grey mt-4">Chargement...</p>
                    </div>

                    <div v-else-if="!filteredGroupes || filteredGroupes.length === 0" class="text-center pa-8">
                        <v-icon icon="mdi-database-search" size="64" color="grey-lighten-2" class="mb-4"></v-icon>
                        <p class="text-h6 text-grey-darken-1">Aucun fournisseur trouvé</p>
                    </div>

                    <div v-else class="scroll-container">
                        <template v-for="(group, idx) in filteredGroupes" :key="idx">
                            <!-- En-tête du fournisseur -->
                            <div class="bg-teal-darken-2 pa-3 rounded-t-lg mt-4 first:mt-0">
                                <div class="d-flex align-center">
                                    <v-icon icon="mdi-store" color="white" size="small" class="mr-2"></v-icon>
                                    <span class="font-weight-black text-uppercase text-white">
                                        {{ group.fournisseur }}
                                    </span>
                                    <v-chip size="x-small" color="white" text-color="teal-darken-2" class="ml-3 font-weight-bold">
                                        CONTRAT : {{ group.numero_contrat }}
                                    </v-chip>
                                    <v-chip size="x-small" color="teal-lighten-4" class="ml-2">
                                        {{ group.modeles?.length || 0 }} modèle(s)
                                    </v-chip>
                                </div>
                            </div>

                            <!-- Tableau des modèles -->
                            <v-table density="compact" class="border rounded-b-lg mb-4">
                                <thead>
                                    <tr class="bg-grey-lighten-4">
                                        <th class="text-left">MODÈLE</th>
                                        <th class="text-center" width="140">EN STOCK (MAGASIN)</th>
                                        <th class="text-center" width="120">SORTIS</th>
                                        <th class="text-center" width="100">TOTAL</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="modele in group.modeles" :key="modele.designation">
                                        <td class="font-weight-bold text-teal-darken-4">{{ modele.designation }}</td>
                                        
                                        <!-- EN STOCK - Vert foncé -->
                                        <td class="text-center">
                                            <span class="stock-badge">
                                                <v-icon icon="mdi-store" size="small" class="mr-1" color="#2e7d32"></v-icon>
                                                <strong style="color: #2e7d32; font-size: 14px;">{{ modele.qte_stock || 0 }}</strong>
                                            </span>
                                        </td>
                                        
                                        <!-- SORTIS - Orange foncé -->
                                        <td class="text-center">
                                            <span class="sorti-badge">
                                                <v-icon icon="mdi-truck" size="small" class="mr-1" color="#ed6c02"></v-icon>
                                                <strong style="color: #ed6c02; font-size: 14px;">{{ modele.qte_sorti || 0 }}</strong>
                                            </span>
                                        </td>
                                        
                                        <!-- TOTAL - Bleu foncé -->
                                        <td class="text-center">
                                            <span class="total-badge">
                                                <strong style="color: #0d47a1; font-size: 14px;">{{ modele.total || 0 }}</strong>
                                            </span>
                                        </td>
                                    </tr>
                                </tbody>
                                <tfoot>
                                    <tr class="bg-grey-lighten-5">
                                        <td class="text-right font-weight-bold">TOTAL {{ group.fournisseur }} :</td>
                                        <td class="text-center">
                                            <strong style="color: #2e7d32; font-size: 15px;">
                                                {{ group.modeles.reduce((sum, m) => sum + (m.qte_stock || 0), 0) }}
                                            </strong>
                                        </td>
                                        <td class="text-center">
                                            <strong style="color: #ed6c02; font-size: 15px;">
                                                {{ group.modeles.reduce((sum, m) => sum + (m.qte_sorti || 0), 0) }}
                                            </strong>
                                        </td>
                                        <td class="text-center">
                                            <strong style="color: #0d47a1; font-size: 15px;">
                                                {{ group.modeles.reduce((sum, m) => sum + (m.total || 0), 0) }}
                                            </strong>
                                        </td>
                                    </tr>
                                </tfoot>
                            </v-table>
                        </template>
                    </div>

                    <!-- Pagination -->
                    <div v-if="groupes?.last_page > 1" class="d-flex justify-center align-center pa-4">
                        <v-pagination 
                            v-model="groupes.current_page" 
                            :length="groupes.last_page" 
                            :total-visible="5" 
                            @update:model-value="changePage" 
                            color="teal-darken-3"
                        />
                        <span class="text-caption text-grey ml-4">
                            {{ groupes.from }} - {{ groupes.to }} / {{ groupes.total }}
                        </span>
                    </div>
                </div>
            </v-card>

            <!-- Section impression -->
            <div class="print-section">
                <div class="print-header">
                    <h1>INVENTAIRE DU MATÉRIEL - {{ inventaire?.annee }}</h1>
                    <div class="print-header-info">
                        <p>Responsable : {{ inventaire?.responsable || 'Système' }}</p>
                        <p>Date : {{ new Date().toLocaleDateString('fr-FR') }}</p>
                    </div>
                </div>

                <template v-for="(group, idx) in groupes?.data || []" :key="idx">
                    <h3 class="print-group-title">{{ group.fournisseur }} ({{ group.numero_contrat }})</h3>
                    <table class="print-table">
                        <thead>
                            <tr>
                                <th>MODÈLE</th>
                                <th class="text-center">EN STOCK</th>
                                <th class="text-center">SORTIS</th>
                                <th class="text-center">TOTAL</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="modele in group.modeles" :key="modele.designation">
                                <td>{{ modele.designation }}</td>
                                <td class="text-center">{{ modele.qte_stock || 0 }}</td>
                                <td class="text-center">{{ modele.qte_sorti || 0 }}</td>
                                <td class="text-center">{{ modele.total || 0 }}</td>
                            </tr>
                        </tbody>
                    </table>
                </template>

                <div class="print-signatures">
                    <div class="signature-box">
                        <h4>LE RESPONSABLE MAGASIN</h4>
                        <div>Nom : _________________________</div>
                        <div>Signature : ____________________</div>
                    </div>
                    <div class="signature-box">
                        <h4>AUDIT / DIRECTION</h4>
                        <div>Observations : ________________</div>
                        <div>Cachet & Signature : ___________</div>
                    </div>
                </div>
            </div>
        </v-container>
    </AuthenticatedLayout>
</template>

<style scoped>
    .search-bar {
        max-width: 300px;
    }
    
    .scroll-container {
        max-height: 60vh;
        overflow-y: auto;
    }
    
    /* Style pour les badges */
    .stock-badge {
        display: inline-flex;
        align-items: center;
        background-color: #e8f5e9;
        padding: 4px 10px;
        border-radius: 20px;
        font-weight: bold;
    }
    
    .sorti-badge {
        display: inline-flex;
        align-items: center;
        background-color: #fff3e0;
        padding: 4px 10px;
        border-radius: 20px;
        font-weight: bold;
    }
    
    .total-badge {
        display: inline-flex;
        align-items: center;
        background-color: #e3f2fd;
        padding: 4px 10px;
        border-radius: 20px;
        font-weight: bold;
    }
    
    /* Personnalisation de la scrollbar */
    .scroll-container::-webkit-scrollbar {
        width: 6px;
    }
    
    .scroll-container::-webkit-scrollbar-track {
        background: #f1f1f1;
        border-radius: 10px;
    }
    
    .scroll-container::-webkit-scrollbar-thumb {
        background: #c1c1c1;
        border-radius: 10px;
    }
    
    .print-section {
        display: none;
    }
    
    @media print {
        .no-print, .v-toolbar, .v-card, .v-pagination, .search-bar, .scroll-container {
            display: none !important;
        }
        
        .print-section {
            display: block !important;
        }
        
        .print-table {
            width: 100%;
            border-collapse: collapse;
            margin: 10px 0;
        }
        
        .print-table th, .print-table td {
            border: 1px solid #000;
            padding: 5px;
        }
        
        .print-group-title {
            background-color: #e0e0e0;
            padding: 5px;
            margin-top: 15px;
        }
        
        .text-center {
            text-align: center;
        }
        
        .signature-box {
            display: inline-block;
            width: 45%;
            border: 1px solid #000;
            margin: 10px;
            padding: 10px;
        }
        
        .signature-box h4 {
            text-align: center;
            margin-bottom: 10px;
        }
    }
</style>