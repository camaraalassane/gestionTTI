<script setup>
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import { Head, useForm, router } from "@inertiajs/vue3";

const props = defineProps({
    historique: {
        type: Array,
        default: () => [],
    },
});

const form = useForm({
    annee: new Date().getFullYear().toString(),
});

const submitCloture = () => {
    if (!form.annee) return;

    const message =
        `⚠️ ATTENTION : La clôture de l'exercice ${form.annee} est définitive.\n\n` +
        `Cela archivera tout le matériel (y compris les pièces) actuellement en STOCK au magasin.\n` +
        `Voulez-vous continuer ?`;

    if (!confirm(message)) return;

    form.post(route("inventaire.store"), {
        onSuccess: () => {
            // Logique conservée
        },
        preserveScroll: true,
    });
};

const voirDetails = (id) => router.visit(route("inventaire.show", id));

const telechargerPDF = (id) => {
    window.open(route("inventaire.pdf", id), "_blank");
};
</script>

<template>
    <Head title="Clôture Annuelle" />

    <AuthenticatedLayout>
        <v-toolbar color="white" flat border density="comfortable">
            <v-toolbar-title
                class="font-weight-black text-teal-darken-4 text-subtitle-1"
            >
                <v-icon
                    icon="mdi-lock-check"
                    class="mr-2"
                    color="teal-darken-1"
                ></v-icon>
                CLÔTURE ANNUELLE & ARCHIVAGE DU STOCK
            </v-toolbar-title>
        </v-toolbar>

        <v-container fluid class="pa-4 pa-md-8 bg-teal-lighten-5 min-vh-100">
            <v-row dense>
                <v-col cols="12" md="4">
                    <v-card
                        border
                        flat
                        class="rounded-xl pa-6 shadow-sm border-teal-left h-100 bg-white"
                    >
                        <div class="d-flex align-center mb-4">
                            <v-avatar
                                color="teal-lighten-5"
                                size="40"
                                class="mr-3"
                            >
                                <v-icon
                                    icon="mdi-archive-plus"
                                    color="teal-darken-2"
                                    size="22"
                                ></v-icon>
                            </v-avatar>
                            <div>
                                <div
                                    class="text-h6 font-weight-bold text-teal-darken-4 leading-tight"
                                >
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
                            <v-text-field
                                v-model="form.annee"
                                label="Année d'exercice"
                                variant="filled"
                                bg-color="teal-lighten-5"
                                color="teal-darken-2"
                                density="comfortable"
                                rounded="lg"
                                prepend-inner-icon="mdi-calendar-badge"
                                :error-messages="form.errors.annee"
                                class="mb-4"
                                persistent-placeholder
                            />

                            <v-btn
                                type="submit"
                                block
                                color="teal-darken-3"
                                size="x-large"
                                variant="elevated"
                                class="rounded-lg font-weight-black text-uppercase"
                                :loading="form.processing"
                                :disabled="form.processing"
                                prepend-icon="mdi-check-all"
                                elevation="2"
                            >
                                GÉNÉRER LA CLÔTURE
                            </v-btn>
                        </v-form>

                        <v-alert
                            v-if="form.wasSuccessful"
                            type="success"
                            variant="tonal"
                            density="compact"
                            class="mt-4 rounded-lg text-caption border-teal"
                            icon="mdi-check-circle"
                        >
                            Archive {{ form.annee }} générée avec succès.
                        </v-alert>
                    </v-card>
                </v-col>

                <v-col cols="12" md="8">
                    <v-card
                        border
                        flat
                        class="rounded-xl shadow-sm overflow-hidden bg-white"
                    >
                        <v-toolbar
                            color="white"
                            density="compact"
                            flat
                            class="border-b"
                        >
                            <v-icon
                                icon="mdi-history"
                                class="ml-4"
                                color="teal-darken-1"
                                size="small"
                            />
                            <v-toolbar-title
                                class="text-overline font-weight-black text-teal-darken-3"
                            >
                                Historique des inventaires clos
                            </v-toolbar-title>
                            <v-spacer></v-spacer>
                            <v-chip
                                size="small"
                                color="teal-lighten-4"
                                text-color="teal-darken-4"
                                variant="flat"
                                class="mr-4 font-weight-bold"
                            >
                                {{ historique.length }} ARCHIVES
                            </v-chip>
                        </v-toolbar>

                        <div class="scroll-limit">
                            <v-table
                                hover
                                fixed-header
                                density="comfortable"
                                class="custom-table"
                            >
                                <thead>
                                    <tr>
                                        <th
                                            class="text-left text-teal-darken-4"
                                        >
                                            ANNÉE
                                        </th>
                                        <th class="text-left">
                                            DATE D'ARCHIVAGE
                                        </th>
                                        <th class="text-center">
                                            QUANTITÉ CLÔTURÉE
                                        </th>
                                        <th class="text-right">ACTIONS</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr
                                        v-for="inv in historique"
                                        :key="inv.id"
                                        class="table-row"
                                    >
                                        <td>
                                            <v-chip
                                                size="small"
                                                variant="flat"
                                                color="teal-darken-1"
                                                class="font-weight-black"
                                            >
                                                {{ inv.annee }}
                                            </v-chip>
                                        </td>
                                        <td
                                            class="text-body-2 text-grey-darken-2"
                                        >
                                            {{
                                                new Date(
                                                    inv.date_cloture ||
                                                        inv.created_at,
                                                ).toLocaleDateString("fr-FR", {
                                                    day: "2-digit",
                                                    month: "short",
                                                    year: "numeric",
                                                })
                                            }}
                                        </td>
                                        <td class="text-center">
                                            <v-chip
                                                size="x-small"
                                                variant="outlined"
                                                color="teal"
                                                class="font-weight-bold"
                                            >
                                                {{ inv.total_items }} items
                                            </v-chip>
                                        </td>
                                        <td class="text-right">
                                            <v-btn
                                                icon="mdi-eye"
                                                variant="text"
                                                color="teal-darken-1"
                                                size="small"
                                                @click="voirDetails(inv.id)"
                                            />
                                            <v-btn
                                                icon="mdi-file-pdf-box"
                                                variant="text"
                                                color="red-darken-1"
                                                size="small"
                                                @click="telechargerPDF(inv.id)"
                                            />
                                        </td>
                                    </tr>

                                    <tr v-if="historique.length === 0">
                                        <td
                                            colspan="4"
                                            class="text-center py-12"
                                        >
                                            <v-icon
                                                icon="mdi-database-off-outline"
                                                size="48"
                                                color="teal-lighten-4"
                                                class="mb-2"
                                            ></v-icon>
                                            <div
                                                class="text-caption text-teal-lighten-2 italic"
                                            >
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
    background-color: #f0fdfa !important; /* teal-lighten-5 */
    font-size: 0.75rem !important;
    letter-spacing: 0.5px !important;
    text-transform: uppercase !important;
    font-weight: 800 !important;
    color: #0f766e !important; /* teal-darken-3 */
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
    box-shadow: 0 2px 10px rgba(0, 121, 107, 0.08) !important;
}

.leading-tight {
    line-height: 1.2;
}

/* Animation douce à l'affichage */
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
