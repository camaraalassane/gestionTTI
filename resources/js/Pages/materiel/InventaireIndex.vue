<script setup>
    import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
    import { Head, useForm, router } from "@inertiajs/vue3";
    import { ref } from "vue";
    import axios from 'axios';

    const props = defineProps({
        historique: {
            type: Array,
            default: () => [],
        },
        flash: {
            type: Object,
            default: () => ({}),
        },
    });

    // État pour les notifications
    const snackbar = ref({
        show: false,
        text: '',
        color: 'success'
    });

    const form = useForm({
        annee: new Date().getFullYear().toString(),
    });

    const submitCloture = () => {
        if (!form.annee) {
            snackbar.value = {
                show: true,
                text: 'Veuillez sélectionner une année',
                color: 'warning'
            };
            return;
        }

        // Vérification si l'année existe déjà
        const anneeExiste = props.historique.some(
            inv => inv.annee.toString() === form.annee.toString()
        );

        if (anneeExiste) {
            const message = `⚠️ L'année ${form.annee} a déjà été clôturée.\n\n` +
                `Voulez-vous vraiment générer un nouvel inventaire pour cette année ?\n` +
                `Cela créera une archive supplémentaire.`;

            if (!confirm(message)) return;
        } else {
            const message =
                `⚠️ ATTENTION : La clôture de l'exercice ${form.annee} est définitive.\n\n` +
                `Cela archivera tout le matériel (y compris les pièces) actuellement en STOCK au magasin.\n` +
                `Voulez-vous continuer ?`;

            if (!confirm(message)) return;
        }

        form.post(route("inventaire.store"), {
            preserveScroll: true,
            onSuccess: (page) => {
                snackbar.value = {
                    show: true,
                    text: `✅ Inventaire ${form.annee} généré avec succès !`,
                    color: 'success'
                };
                form.reset();
                form.annee = new Date().getFullYear().toString();
                // Recharger la page pour voir le nouvel inventaire
                setTimeout(() => router.reload(), 1500);
            },
            onError: (errors) => {
                console.error('Erreur lors de la clôture:', errors);
                const errorMessage = errors.annee || errors.global || 'Erreur lors de la génération';
                snackbar.value = {
                    show: true,
                    text: `❌ ${errorMessage}`,
                    color: 'error'
                };
            }
        });
    };

    const voirDetails = (id) => {
        router.visit(route("inventaire.show", id));
    };

    const telechargerPDF = (id) => {
        snackbar.value = {
            show: true,
            text: 'Génération du PDF en cours...',
            color: 'info'
        };
        window.open(route("inventaire.pdf", id), "_blank");
        setTimeout(() => {
            snackbar.value.show = false;
        }, 2000);
    };

    // Vider le cache

    const clearCache = async () => {
        try {
            const response = await axios.post(route('inventaire.clear-cache'));
            if (response.data.success) {
                snackbar.value = {
                    show: true,
                    text: '✅ Cache vidé avec succès !',
                    color: 'success'
                };
                setTimeout(() => router.reload(), 1000);
            }
        } catch (error) {
            snackbar.value = {
                show: true,
                text: '❌ Erreur lors du vidage du cache',
                color: 'error'
            };
        }
    };

    // Formater la date
    const formatDate = (dateString) => {
        if (!dateString) return 'Date inconnue';
        const date = new Date(dateString);
        if (isNaN(date.getTime())) return 'Date invalide';
        return date.toLocaleDateString("fr-FR", {
            day: "2-digit",
            month: "short",
            year: "numeric",
        });
    };

    // Vérifier si une année est déjà archivée
    const isAnneeArchivee = (annee) => {
        return props.historique.some(inv => inv.annee.toString() === annee.toString());
    };
</script>

<template>

    <Head title="Clôture Annuelle" />

    <AuthenticatedLayout>
        <!-- Snackbar pour les notifications -->
        <v-snackbar v-model="snackbar.show" :color="snackbar.color" timeout="4000" location="top" rounded="pill" class="mt-16">
            <div class="d-flex align-center">
                <v-icon :icon="snackbar.color === 'success' ? 'mdi-check-circle' :
                    snackbar.color === 'error' ? 'mdi-alert-circle' :
                        'mdi-information'" class="mr-2" />
                <span class="font-weight-bold">{{ snackbar.text }}</span>
            </div>
        </v-snackbar>

        <v-toolbar color="white" flat border density="comfortable">
            <v-toolbar-title class="font-weight-black text-teal-darken-4 text-subtitle-1">
                <v-icon icon="mdi-lock-check" class="mr-2" color="teal-darken-1"></v-icon>
                CLÔTURE ANNUELLE & ARCHIVAGE DU STOCK
            </v-toolbar-title>
        </v-toolbar>

        <v-container fluid class="pa-4 pa-md-8 bg-teal-lighten-5 min-vh-100">
            <v-row dense>
                <v-col cols="12" md="4">
                    <v-card border flat class="rounded-xl pa-6 shadow-sm border-teal-left h-100 bg-white">
                        <div class="d-flex align-center mb-4">
                            <v-avatar color="teal-lighten-5" size="40" class="mr-3">
                                <v-icon icon="mdi-archive-plus" color="teal-darken-2" size="22"></v-icon>
                            </v-avatar>
                            <div>
                                <div class="text-h6 font-weight-bold text-teal-darken-4 leading-tight">
                                    Nouvelle Archive
                                </div>
                                <div class="text-caption text-teal-darken-1">
                                    Génération de l'inventaire
                                </div>
                            </div>
                        </div>

                        <p class="text-body-2 text-grey-darken-2 mb-6">
                            L'archivage capture l'état instantané du matériel au
                            magasin (pièces, pannes et stocks).
                        </p>

                        <v-form @submit.prevent="submitCloture">
                            <v-select v-model="form.annee" :items="[2023, 2024, 2025, 2026, 2027, 2028]" label="Année d'exercice" variant="filled" bg-color="teal-lighten-5" color="teal-darken-2" density="comfortable" rounded="lg" prepend-inner-icon="mdi-calendar-badge" :error-messages="form.errors.annee" class="mb-4" persistent-placeholder>
                                <template #item="{ item, props: itemProps }">
                                    <v-list-item v-bind="itemProps">
                                        <template #append>
                                            <v-chip v-if="isAnneeArchivee(item.value)" size="x-small" color="teal-lighten-3" class="font-weight-bold">
                                                DÉJÀ ARCHIVÉE
                                            </v-chip>
                                        </template>
                                    </v-list-item>
                                </template>
                            </v-select>

                            <v-btn type="submit" block color="teal-darken-3" size="large" variant="elevated" class="rounded-lg font-weight-black text-uppercase" :loading="form.processing" :disabled="form.processing" prepend-icon="mdi-check-all" elevation="2">
                                GÉNÉRER LA CLÔTURE
                            </v-btn>
                        </v-form>

                        <!-- Message de succès -->
                        <v-alert v-if="form.recentlySuccessful" type="success" variant="tonal" density="compact" class="mt-4 rounded-lg text-caption border-teal" icon="mdi-check-circle">
                            Archive {{ form.annee }} générée avec succès.
                        </v-alert>
                    </v-card>
                </v-col>

                <v-col cols="12" md="8">
                    <v-card border flat class="rounded-xl shadow-sm overflow-hidden bg-white">
                        <v-toolbar color="white" density="compact" flat class="border-b">
                            <v-icon icon="mdi-history" class="ml-4" color="teal-darken-1" size="small" />
                            <v-toolbar-title class="text-overline font-weight-black text-teal-darken-3">
                                Historique des inventaires clos
                            </v-toolbar-title>
                            <v-spacer></v-spacer>

                            <!-- BOUTON VIDER LE CACHE -->
                            <v-btn color="grey-darken-1" variant="text" size="small" @click="clearCache" prepend-icon="mdi-cache-refresh" class="mr-2" title="Vider le cache">
                                Vider le cache
                            </v-btn>

                            <v-chip size="small" color="teal-lighten-4" text-color="teal-darken-4" variant="flat" class="mr-4 font-weight-bold">
                                {{ historique.length }} ARCHIVE{{ historique.length > 1 ? 'S' : '' }}
                            </v-chip>
                        </v-toolbar>

                        <div class="scroll-limit">
                            <v-table hover fixed-header density="comfortable" class="custom-table">
                                <thead>
                                    <tr>
                                        <th class="text-left text-teal-darken-4">ANNÉE</th>
                                        <th class="text-left">DATE D'ARCHIVAGE</th>
                                        <th class="text-center">QUANTITÉ CLÔTURÉE</th>
                                        <th class="text-right">ACTIONS</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="inv in historique" :key="inv.id" class="table-row">
                                        <td>
                                            <v-chip size="small" variant="flat" color="teal-darken-1" class="font-weight-black">
                                                {{ inv.annee }}
                                            </v-chip>
                                        </td>
                                        <td class="text-body-2 text-grey-darken-2">
                                            {{ formatDate(inv.date_cloture || inv.created_at) }}
                                        </td>
                                        <td class="text-center">
                                            <v-chip size="x-small" variant="outlined" color="teal" class="font-weight-bold">
                                                {{ inv.total_items }} article{{ inv.total_items > 1 ? 's' : '' }}
                                            </v-chip>
                                        </td>
                                        <td class="text-right">
                                            <v-btn icon="mdi-eye" variant="text" color="teal-darken-1" size="small" @click="voirDetails(inv.id)" :title="'Voir les détails de ' + inv.annee" />
                                            <v-btn icon="mdi-file-pdf-box" variant="text" color="red-darken-1" size="small" @click="telechargerPDF(inv.id)" :title="'Télécharger PDF ' + inv.annee" />
                                        </td>
                                    </tr>

                                    <tr v-if="historique.length === 0">
                                        <td colspan="4" class="text-center py-12">
                                            <v-icon icon="mdi-database-off-outline" size="48" color="teal-lighten-4" class="mb-2"></v-icon>
                                            <div class="text-caption text-teal-lighten-2 italic">
                                                Aucun inventaire enregistré.
                                            </div>
                                        </td>
                                    </tr>
                                </tbody>
                            </v-table>
                        </div>
                    </v-card>
                </v-col>
            </v-row>
        </v-container>
    </AuthenticatedLayout>
</template>

<style scoped>
    .scroll-limit {
        max-height: 550px;
        overflow-y: auto;
    }

    .custom-table :deep(thead th) {
        background-color: #f0fdfa !important;
        font-size: 0.75rem !important;
        letter-spacing: 0.5px !important;
        text-transform: uppercase !important;
        font-weight: 800 !important;
        color: #0f766e !important;
        height: 52px !important;
    }

    .table-row:hover {
        background-color: #f0fdfa !important;
    }

    .border-teal-left {
        border-left: 8px solid #00796b !important;
    }

    .border-teal {
        border: 1px solid #00796b !important;
    }

    .shadow-sm {
        box-shadow: 0 2px 10px rgba(1, 84, 75, 0.08) !important;
    }

    .leading-tight {
        line-height: 1.2;
    }

    .v-container {
        animation: fadeIn 0.4s ease-out;
    }

    @keyframes fadeIn {
        from {
            opacity: 0;
        }

        to {
            opacity: 1;
        }
    }
</style>
