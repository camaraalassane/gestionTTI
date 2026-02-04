<script setup>
import AdminLayout from "@/Layouts/AdminLayout.vue";
import { Head } from "@inertiajs/vue3";

// Récupération des données envoyées par le contrôleur PHP
defineProps({
    stats: {
        type: Object,
        default: () => ({
            total_materiels: 0,
            total_demandes: 0,
            services_actifs: 0,
        }),
    },
    demandes_par_service: {
        type: Array,
        default: () => [],
    },
    top_services: {
        type: Array,
        default: () => [],
    },
});
</script>

<template>
    <AdminLayout>
        <Head title="Suivi Global" />

        <template #header>
            <div class="d-flex align-center">
                <v-icon
                    icon="mdi-monitor-dashboard"
                    color="teal-darken-3"
                    class="me-2"
                />
                Tableau de Bord Global
                <span class="text-teal-lighten-1 ms-2">/ 2026</span>
            </div>
        </template>

        <v-row>
            <v-col cols="12" md="4">
                <v-card
                    class="rounded-xl pa-5 border-teal position-relative overflow-hidden stat-card bg-teal-darken-4 text-white"
                    elevation="8"
                >
                    <div
                        class="text-overline text-teal-lighten-3 font-weight-bold"
                    >
                        Stock Total
                    </div>
                    <div class="text-h3 font-weight-black mt-1">
                        {{ stats.total_materiels }}
                    </div>
                    <div class="text-caption text-teal-lighten-4 italic mt-2">
                        Équipements répertoriés
                    </div>
                    <v-icon
                        icon="mdi-package-variant-closed"
                        size="100"
                        class="position-absolute ghost-icon"
                    />
                </v-card>
            </v-col>

            <v-col cols="12" md="4">
                <v-card
                    class="rounded-xl pa-5 border-emerald position-relative overflow-hidden stat-card bg-emerald-darken-3 text-white"
                    elevation="8"
                >
                    <div
                        class="text-overline text-emerald-lighten-3 font-weight-bold"
                    >
                        Demandes Traitées
                    </div>
                    <div class="text-h3 font-weight-black mt-1">
                        {{ stats.total_demandes }}
                    </div>
                    <div
                        class="text-caption text-emerald-lighten-4 italic mt-2"
                    >
                        Mises à jour réussies
                    </div>
                    <v-icon
                        icon="mdi-check-decagram-outline"
                        size="100"
                        class="position-absolute ghost-icon"
                    />
                </v-card>
            </v-col>

            <v-col cols="12" md="4">
                <v-card
                    class="rounded-xl pa-5 border-slate position-relative overflow-hidden stat-card bg-slate-darken-4 text-white"
                    elevation="8"
                >
                    <div
                        class="text-overline text-slate-lighten-3 font-weight-bold"
                    >
                        Services Actifs
                    </div>
                    <div class="text-h3 font-weight-black mt-1">
                        {{ stats.services_actifs }}
                    </div>
                    <div class="text-caption text-slate-lighten-4 italic mt-2">
                        Départements opérationnels
                    </div>
                    <v-icon
                        icon="mdi-office-building-cog"
                        size="100"
                        class="position-absolute ghost-icon"
                    />
                </v-card>
            </v-col>
        </v-row>

        <v-row class="mt-8">
            <v-col cols="12" md="6">
                <v-card
                    class="rounded-xl border-light glass-card h-100"
                    elevation="2"
                >
                    <v-card-title
                        class="d-flex align-center pa-5 text-teal-darken-4"
                    >
                        <v-avatar color="teal-lighten-5" size="40" class="me-3">
                            <v-icon
                                icon="mdi-trophy-variant"
                                color="teal-darken-3"
                            />
                        </v-avatar>
                        <span class="font-weight-black"
                            >Top 5 des Services</span
                        >
                    </v-card-title>
                    <v-divider></v-divider>

                    <v-list lines="two" class="bg-transparent pa-2">
                        <v-list-item
                            v-for="(s, i) in top_services"
                            :key="i"
                            class="rounded-lg mb-2 service-item shadow-sm border mx-2"
                        >
                            <template v-slot:prepend>
                                <v-avatar
                                    :color="
                                        i === 0
                                            ? 'teal-darken-3'
                                            : 'teal-lighten-4'
                                    "
                                    size="36"
                                    class="font-weight-bold"
                                    :class="
                                        i === 0
                                            ? 'text-white'
                                            : 'text-teal-darken-4'
                                    "
                                >
                                    {{ i + 1 }}
                                </v-avatar>
                            </template>

                            <v-list-item-title
                                class="font-weight-black text-teal-darken-4"
                                >{{ s.nom }}</v-list-item-title
                            >
                            <v-list-item-subtitle class="text-teal-darken-1"
                                >TTI SERVICE</v-list-item-subtitle
                            >

                            <template v-slot:append>
                                <v-chip
                                    color="teal-darken-3"
                                    variant="flat"
                                    size="small"
                                    class="font-weight-bold rounded-lg px-4 shadow-sm"
                                >
                                    {{ s.total }}
                                    <v-icon
                                        icon="mdi-cube-outline"
                                        end
                                        size="14"
                                    />
                                </v-chip>
                            </template>
                        </v-list-item>

                        <v-list-item
                            v-if="top_services.length === 0"
                            class="text-center py-10"
                        >
                            <v-icon
                                icon="mdi-database-off"
                                size="48"
                                color="grey-lighten-1"
                            />
                            <p class="text-grey mt-2">
                                Aucune donnée disponible
                            </p>
                        </v-list-item>
                    </v-list>
                </v-card>
            </v-col>

            <v-col cols="12" md="6">
                <v-card
                    class="rounded-xl border-light glass-card h-100"
                    elevation="2"
                >
                    <v-card-title
                        class="d-flex align-center pa-5 text-teal-darken-4"
                    >
                        <v-avatar color="teal-lighten-5" size="40" class="me-3">
                            <v-icon
                                icon="mdi-chart-arc"
                                color="teal-darken-3"
                            />
                        </v-avatar>
                        <span class="font-weight-black"
                            >Répartition des Volumes</span
                        >
                    </v-card-title>
                    <v-divider></v-divider>

                    <v-table
                        density="comfortable"
                        class="bg-transparent mx-4 my-2"
                    >
                        <thead>
                            <tr class="text-teal-darken-4">
                                <th class="text-left font-weight-black">
                                    SERVICE
                                </th>
                                <th class="text-center font-weight-black">
                                    UNITÉS
                                </th>
                                <th class="text-left font-weight-black">
                                    CHARGE %
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="item in demandes_par_service"
                                :key="item.nom"
                                class="table-row"
                            >
                                <td class="font-weight-bold text-teal-darken-3">
                                    {{ item.nom }}
                                </td>
                                <td class="text-center">
                                    <v-chip
                                        size="x-small"
                                        variant="tonal"
                                        color="teal-darken-4"
                                        class="font-weight-black"
                                    >
                                        {{ item.total }}
                                    </v-chip>
                                </td>
                                <td class="py-4">
                                    <div class="d-flex align-center">
                                        <v-progress-linear
                                            :model-value="
                                                stats.total_materiels > 0
                                                    ? (item.total /
                                                          stats.total_materiels) *
                                                      100
                                                    : 0
                                            "
                                            color="teal-darken-3"
                                            height="12"
                                            rounded="pill"
                                            class="flex-grow-1 elevation-1"
                                        >
                                        </v-progress-linear>
                                        <span
                                            class="text-caption font-weight-black ms-3 text-teal-darken-4"
                                            style="min-width: 40px"
                                        >
                                            {{
                                                Math.ceil(
                                                    stats.total_materiels > 0
                                                        ? (item.total /
                                                              stats.total_materiels) *
                                                              100
                                                        : 0,
                                                )
                                            }}%
                                        </span>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="demandes_par_service.length === 0">
                                <td
                                    colspan="3"
                                    class="text-center py-10 text-grey italic"
                                >
                                    Aucun matériel assigné.
                                </td>
                            </tr>
                        </tbody>
                    </v-table>
                </v-card>
            </v-col>
        </v-row>
    </AdminLayout>
</template>

<style scoped>
/* Couleurs personnalisées pour les cartes */
.bg-emerald-darken-3 {
    background-color: #059669 !important;
}
.bg-slate-darken-4 {
    background-color: #1e293b !important;
}

.stat-card {
    transition:
        transform 0.3s ease,
        box-shadow 0.3s ease;
}
.stat-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 12px 20px rgba(0, 77, 64, 0.2) !important;
}

.ghost-icon {
    right: -15px;
    bottom: -15px;
    opacity: 0.15;
    transform: rotate(-10deg);
}

.glass-card {
    background: rgba(255, 255, 255, 0.9) !important;
    backdrop-filter: blur(10px);
}

.service-item {
    background: white !important;
    border: 1px solid rgba(0, 121, 107, 0.05) !important;
    transition: all 0.2s ease;
}
.service-item:hover {
    background: #f0fdfa !important;
    border-color: #26a69a !important;
}

.table-row:hover {
    background-color: rgba(204, 242, 239, 0.3) !important;
}

.border-teal {
    border-top: 4px solid #4db6ac !important;
}
.border-emerald {
    border-top: 4px solid #6ee7b7 !important;
}
.border-slate {
    border-top: 4px solid #94a3b8 !important;
}

.italic {
    font-style: italic;
}
</style>
