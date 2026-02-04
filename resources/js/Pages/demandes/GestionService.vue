<script setup>
import { ref, computed, onMounted } from "vue";
import { Head, router } from "@inertiajs/vue3";
import AuthentDemandeLayout from "@/Layouts/AuthentDemandeLayout.vue";

const props = defineProps({
    demandes: Object,
    services: Array,
});

const serviceSelectionne = ref(null);

// --- LOGIQUE DE DONNÉES ---
const listeDemandesRaw = computed(() => {
    return (
        props.demandes?.data ||
        (Array.isArray(props.demandes) ? props.demandes : [])
    );
});

const servicesAvecDemandes = computed(() => {
    const actifs = listeDemandesRaw.value
        .filter((d) => d.statut !== "Clôturé")
        .map((d) => d.service_beneficiaire);
    return [...new Set(actifs)].filter(Boolean);
});

const demandesDuService = computed(() => {
    if (!serviceSelectionne.value) return [];
    return listeDemandesRaw.value.filter(
        (d) =>
            d.service_beneficiaire === serviceSelectionne.value &&
            d.statut !== "Clôturé",
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

// --- ACTIONS PAR DEMANDEUR ---
const validerParDemandeur = (articles) => {
    router.post(
        route("demandes.valider_groupe"),
        { ids: articles.map((d) => d.id) },
        { preserveScroll: true },
    );
};

const imprimerEtCloturerParDemandeur = (nom, articles) => {
    window.open(
        route("demandes.imprimer_bon", {
            service: serviceSelectionne.value,
            demandeur: nom,
        }),
        "_blank",
    );
    router.post(
        route("demandes.cloturer_groupe"),
        { ids: articles.map((d) => d.id) },
        { preserveScroll: true },
    );
};

const estGroupeValide = (articles) => {
    return articles.length > 0 && articles.every((a) => a.statut === "Validé");
};

// --- STATISTIQUES ---
const totalArticles = computed(() => demandesDuService.value.length);
const nbValides = computed(
    () => demandesDuService.value.filter((d) => d.statut === "Validé").length,
);
const progression = computed(() =>
    totalArticles.value > 0 ? (nbValides.value / totalArticles.value) * 100 : 0,
);

onMounted(() => {
    if (servicesAvecDemandes.value.length > 0) {
        serviceSelectionne.value = servicesAvecDemandes.value[0];
    }
});

// --- HELPERS ---
const estSortiePiece = (desc) => desc?.toUpperCase().includes("SORTIE PIÈCES");
const extrairePiece = (desc) =>
    desc
        ?.split(/SORTIE PIÈCES\s*:/i)[1]
        ?.split("|")[0]
        ?.trim() || "Pièce";
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
            <v-row justify="center" class="mb-8">
                <v-col cols="12" md="10" lg="8">
                    <v-card class="rounded-xl pa-2" elevation="2" border>
                        <v-autocomplete
                            v-model="serviceSelectionne"
                            :items="props.services"
                            item-title="nom"
                            item-value="nom"
                            label="Service en cours de traitement"
                            variant="solo"
                            flat
                            hide-details
                            prepend-inner-icon="mdi-office-building"
                            color="teal-darken-1"
                        ></v-autocomplete>

                        <div
                            v-if="servicesAvecDemandes.length > 0"
                            class="px-4 py-3 d-flex align-center flex-wrap ga-2 border-t mt-2"
                        >
                            <span
                                class="text-caption font-weight-bold text-teal-darken-2 text-uppercase"
                                style="letter-spacing: 1px"
                            >
                                Unités en attente :
                            </span>
                            <v-chip
                                v-for="s in servicesAvecDemandes"
                                :key="s"
                                size="small"
                                :variant="
                                    s === serviceSelectionne
                                        ? 'flat'
                                        : 'outlined'
                                "
                                :color="
                                    s === serviceSelectionne
                                        ? 'teal-darken-2'
                                        : 'teal-darken-1'
                                "
                                :class="
                                    s === serviceSelectionne
                                        ? 'text-white'
                                        : 'bg-white'
                                "
                                @click="serviceSelectionne = s"
                                link
                            >
                                {{ s }}
                            </v-chip>
                        </div>
                    </v-card>
                </v-col>
            </v-row>

            <div v-if="serviceSelectionne">
                <v-row>
                    <v-col cols="12" lg="9">
                        <div
                            v-for="(
                                articles, nom
                            ) in demandesGroupeesParDemandeur"
                            :key="nom"
                            class="mb-6"
                        >
                            <v-card
                                class="rounded-xl border shadow-sm overflow-hidden mb-6"
                                elevation="0"
                            >
                                <div
                                    class="bg-teal-darken-1 pa-4 d-flex align-center"
                                >
                                    <v-icon color="white" class="mr-2"
                                        >mdi-account-circle</v-icon
                                    >

                                    <span
                                        class="text-white font-weight-bold text-uppercase"
                                        style="letter-spacing: 0.5px"
                                    >
                                        Receveur : {{ nom }}
                                    </span>

                                    <v-spacer></v-spacer>

                                    <v-chip
                                        size="x-small"
                                        color="white"
                                        variant="flat"
                                        class="text-teal-darken-1 font-weight-black"
                                    >
                                        {{ articles.length }} Article(s)
                                    </v-chip>
                                </div>

                                <v-table
                                    hover
                                    density="comfortable"
                                    style="background: transparent !important"
                                >
                                    <thead>
                                        <tr class="bg-teal-lighten-5">
                                            <th
                                                class="text-teal-darken-3 font-weight-bold"
                                                style="
                                                    border-top-left-radius: 0 !important;
                                                    border-bottom: none !important;
                                                "
                                            >
                                                Désignation
                                            </th>
                                            <th
                                                class="text-center text-teal-darken-3 font-weight-bold"
                                                style="
                                                    border-bottom: none !important;
                                                "
                                            >
                                                S/N
                                            </th>
                                            <th
                                                class="text-center text-teal-darken-3 font-weight-bold"
                                                style="
                                                    border-bottom: none !important;
                                                "
                                            >
                                                Qté
                                            </th>
                                            <th
                                                class="text-center text-teal-darken-3 font-weight-bold"
                                                style="
                                                    border-bottom: none !important;
                                                "
                                            >
                                                État
                                            </th>
                                            <th
                                                class="text-right"
                                                style="
                                                    border-top-right-radius: 0 !important;
                                                    border-bottom: none !important;
                                                "
                                            ></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr
                                            v-for="item in articles"
                                            :key="item.id"
                                        >
                                            <td
                                                class="py-3 font-weight-bold text-teal-darken-4"
                                            >
                                                {{
                                                    estSortiePiece(
                                                        item.description,
                                                    )
                                                        ? extrairePiece(
                                                              item.description,
                                                          )
                                                        : item.nom_materiel
                                                }}
                                            </td>
                                            <td class="text-center">
                                                <span class="sn-badge">{{
                                                    item.numero_serie || "N/A"
                                                }}</span>
                                            </td>
                                            <td
                                                class="text-center font-weight-black"
                                            >
                                                {{ item.nbredemande }}
                                            </td>
                                            <td class="text-center">
                                                <v-icon
                                                    :color="
                                                        item.statut === 'Validé'
                                                            ? 'success'
                                                            : 'amber-darken-2'
                                                    "
                                                    size="small"
                                                >
                                                    {{
                                                        item.statut === "Validé"
                                                            ? "mdi-check-circle"
                                                            : "mdi-clock-outline"
                                                    }}
                                                </v-icon>
                                            </td>
                                            <td class="text-right">
                                                <v-btn
                                                    icon="mdi-trash-can-outline"
                                                    size="x-small"
                                                    variant="text"
                                                    color="grey-lighten-1"
                                                    @click="
                                                        supprimerLigne(item)
                                                    "
                                                ></v-btn>
                                            </td>
                                        </tr>
                                    </tbody>
                                </v-table>

                                <v-divider></v-divider>

                                <div
                                    class="pa-3 bg-white d-flex justify-end ga-2"
                                >
                                    <v-btn
                                        v-if="!estGroupeValide(articles)"
                                        color="teal-darken-1"
                                        class="rounded-lg text-none font-weight-bold shadow-sm"
                                        prepend-icon="mdi-check-all"
                                        @click="validerParDemandeur(articles)"
                                    >
                                        Valider la demande
                                    </v-btn>

                                    <v-btn
                                        v-else
                                        color="teal-darken-2"
                                        class="rounded-lg text-none font-weight-bold"
                                        prepend-icon="mdi-printer"
                                        @click="
                                            imprimerEtCloturerParDemandeur(
                                                nom,
                                                articles,
                                            )
                                        "
                                    >
                                        Imprimer & Clôturer
                                    </v-btn>
                                </div>
                            </v-card>
                        </div>
                    </v-col>

                    <v-col cols="12" lg="3">
                        <v-card
                            class="rounded-xl pa-5 sticky-card border bg-white shadow-sm"
                            elevation="0"
                        >
                            <div class="text-overline text-teal-lighten-1">
                                Service
                            </div>
                            <div
                                class="text-h6 font-weight-black text-teal-darken-4 mb-4 leading-tight"
                            >
                                {{ serviceSelectionne }}
                            </div>
                            <v-progress-linear
                                :model-value="progression"
                                color="teal-lighten-2"
                                height="8"
                                rounded
                                class="mb-6"
                            ></v-progress-linear>
                            <div class="bg-teal-lighten-5 pa-3 rounded-lg mb-4">
                                <div class="d-flex justify-space-between mb-1">
                                    <span
                                        class="text-caption text-teal-darken-3"
                                        >Articles :</span
                                    >
                                    <span class="font-weight-bold">{{
                                        totalArticles
                                    }}</span>
                                </div>
                                <div class="d-flex justify-space-between">
                                    <span
                                        class="text-caption text-teal-darken-3"
                                        >Validés :</span
                                    >
                                    <span
                                        class="font-weight-bold text-success"
                                        >{{ nbValides }}</span
                                    >
                                </div>
                            </div>
                            <v-btn
                                block
                                color="teal-lighten-4"
                                variant="flat"
                                class="text-teal-darken-3 rounded-lg text-none font-weight-bold"
                                prepend-icon="mdi-plus"
                                @click="allerAjouterArticle"
                            >
                                Ajouter un article
                            </v-btn>
                        </v-card>
                    </v-col>
                </v-row>
            </div>
        </v-container>
    </AuthentDemandeLayout>
</template>

<style scoped>
.sticky-card {
    position: sticky;
    top: 24px;
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
/* Supprime l'espace blanc sur les côtés du tableau */
:deep(.v-table > .v-table__wrapper > table) {
    width: 100%;
    border-spacing: 0;
}

:deep(.v-table__wrapper) {
    border-radius: 0 !important;
}
</style>
