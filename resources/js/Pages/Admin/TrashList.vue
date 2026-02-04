<script setup>
import { router } from "@inertiajs/vue3";
import AdminLayout from "@/Layouts/AdminLayout.vue";
import { Head } from "@inertiajs/vue3";

const props = defineProps({
    items: { type: Array, default: () => [] },
});

const restore = (id) => {
    if (confirm("Voulez-vous réinsérer ce matériel dans la table active ?")) {
        router.post(route("admin.trash.restore", id));
    }
};

const forceDelete = (id) => {
    if (
        confirm(
            "ATTENTION : Cette action est irréversible. Supprimer définitivement de la base de données ?",
        )
    ) {
        router.delete(route("admin.trash.force", id));
    }
};
</script>

<template>
    <AdminLayout>
        <Head title="Corbeille Matériels" />

        <template #header>
            <div class="d-flex align-center">
                <v-icon
                    icon="mdi-delete-clock-outline"
                    color="teal-darken-3"
                    class="me-2"
                />
                Corbeille & Archives
                <span class="text-caption text-grey ms-2 mt-1"
                    >| Gestion des éléments supprimés</span
                >
            </div>
        </template>

        <v-card
            elevation="4"
            class="rounded-xl overflow-hidden glass-card border-light"
        >
            <div
                class="bg-teal-darken-4 pa-4 d-flex align-center justify-space-between text-white"
            >
                <div class="text-subtitle-1 font-weight-bold">
                    Éléments en attente de traitement
                </div>
                <v-chip
                    size="small"
                    color="teal-lighten-4"
                    variant="flat"
                    class="text-teal-darken-4 font-weight-black"
                >
                    {{ items.length }} Matériel(s)
                </v-chip>
            </div>

            <v-table hover class="custom-trash-table">
                <thead>
                    <tr class="bg-teal-lighten-5">
                        <th class="font-weight-black text-teal-darken-4 px-6">
                            NOM DU MATÉRIEL
                        </th>
                        <th
                            class="font-weight-black text-teal-darken-4 text-center"
                        >
                            N° SÉRIE
                        </th>
                        <th
                            class="font-weight-black text-teal-darken-4 text-center"
                        >
                            SUPPRIMÉ LE
                        </th>
                        <th
                            class="font-weight-black text-teal-darken-4 text-center"
                        >
                            PAR
                        </th>
                        <th
                            class="font-weight-black text-teal-darken-4 text-right px-6"
                        >
                            ACTIONS
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="item in items" :key="item.id" class="table-row">
                        <td class="py-4 px-6">
                            <div class="font-weight-black text-teal-darken-4">
                                {{ item.nom }}
                            </div>
                            <div
                                class="text-caption font-weight-medium text-grey-darken-1"
                            >
                                <v-icon
                                    icon="mdi-tag-outline"
                                    size="12"
                                    class="me-1"
                                />
                                {{ item.categorie || "Sans catégorie" }}
                            </div>
                        </td>
                        <td class="text-center">
                            <v-chip
                                size="x-small"
                                variant="tonal"
                                color="teal-darken-2"
                                class="font-mono font-weight-bold px-3"
                            >
                                {{ item.numero_serie }}
                            </v-chip>
                        </td>
                        <td
                            class="text-center text-body-2 font-weight-medium text-grey-darken-2"
                        >
                            {{
                                new Date(item.supprime_le).toLocaleDateString()
                            }}
                            <span class="text-caption opacity-60 ms-1">{{
                                new Date(item.supprime_le).toLocaleTimeString(
                                    [],
                                    { hour: "2-digit", minute: "2-digit" },
                                )
                            }}</span>
                        </td>
                        <td class="text-center">
                            <v-chip
                                color="teal-lighten-5"
                                class="text-teal-darken-3 font-weight-bold"
                                size="small"
                                border="teal-lighten-3"
                            >
                                <v-icon
                                    start
                                    icon="mdi-account-cancel-outline"
                                    size="14"
                                />
                                {{ item.par_utilisateur }}
                            </v-chip>
                        </td>
                        <td class="text-right px-6">
                            <v-tooltip
                                text="Restaurer dans le stock"
                                location="top"
                            >
                                <template v-slot:activator="{ props }">
                                    <v-btn
                                        v-bind="props"
                                        color="teal-darken-2"
                                        variant="tonal"
                                        icon="mdi-restore"
                                        size="small"
                                        class="me-2 rounded-lg action-btn"
                                        @click="restore(item.id)"
                                    ></v-btn>
                                </template>
                            </v-tooltip>

                            <v-tooltip
                                text="Supprimer définitivement"
                                location="top"
                            >
                                <template v-slot:activator="{ props }">
                                    <v-btn
                                        v-bind="props"
                                        color="red-darken-3"
                                        variant="tonal"
                                        icon="mdi-delete-forever-outline"
                                        size="small"
                                        class="rounded-lg action-btn"
                                        @click="forceDelete(item.id)"
                                    ></v-btn>
                                </template>
                            </v-tooltip>
                        </td>
                    </tr>

                    <tr v-if="items.length === 0">
                        <td colspan="5" class="text-center py-16">
                            <v-icon
                                icon="mdi-delete-empty-outline"
                                size="64"
                                color="teal-lighten-4"
                                class="mb-4"
                            />
                            <div
                                class="text-h6 font-weight-bold text-teal-darken-4"
                            >
                                La corbeille est vide
                            </div>
                            <div class="text-body-2 text-grey">
                                Aucun élément archivé pour le moment.
                            </div>
                        </td>
                    </tr>
                </tbody>
            </v-table>
        </v-card>
    </AdminLayout>
</template>

<style scoped>
.glass-card {
    background: rgba(255, 255, 255, 0.95) !important;
    backdrop-filter: blur(10px);
}

.border-light {
    border: 1px solid rgba(0, 77, 64, 0.1) !important;
}

.custom-trash-table :deep(th) {
    text-transform: uppercase;
    letter-spacing: 0.05rem;
    font-size: 0.75rem !important;
}

.table-row {
    transition: background-color 0.2s ease;
}

.table-row:hover {
    background-color: #f0fdfa !important; /* Teal ultra light */
}

.font-mono {
    font-family: "Courier New", Courier, monospace !important;
}

.action-btn {
    transition: transform 0.2s ease;
}

.action-btn:hover {
    transform: translateY(-2px);
}

.opacity-60 {
    opacity: 0.6;
}
</style>
