<script setup>
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import { Head, useForm } from "@inertiajs/vue3";
import { ref, computed, watch } from "vue";

const props = defineProps({ categories: Array });

// --- RÉFÉRENCES ---
const fileInputKey = ref(0);
const tentativeAjout = ref(false);
const tentativeFinale = ref(false);

const infosCommunes = ref({
    fournisseur: "",
    numero_contrat: "",
    date_livraison: new Date().toISOString().substr(0, 10),
    scan_contrat: null,
});

const modeVrac = ref(false);
const articleActuel = ref({
    categorie_id: null,
    nbrcarton: 0,
    unite: 0,
    details_unites: [],
});

const panier = ref([]);

// --- LOGIQUE CALCULS ---
const totalUnitesPhysiques = computed(() => {
    const nbrCtn = parseInt(articleActuel.value.nbrcarton) || 0;
    const ute = parseInt(articleActuel.value.unite) || 0;
    return modeVrac.value ? ute : nbrCtn * ute;
});

watch(totalUnitesPhysiques, (total) => {
    const currentLength = articleActuel.value.details_unites.length;
    if (total > currentLength) {
        for (let i = currentLength; i < total; i++) {
            articleActuel.value.details_unites.push({
                nom: "",
                numero_serie: "",
                pieces: [],
            });
        }
    } else if (total < currentLength) {
        articleActuel.value.details_unites =
            articleActuel.value.details_unites.slice(0, total);
    }
});

// --- LOGIQUE PIÈCES ---
const ajouterPiece = (idx) =>
    articleActuel.value.details_unites[idx].pieces.push({ nom: "", sn: "" });
const retirerPiece = (uIdx, pIdx) =>
    articleActuel.value.details_unites[uIdx].pieces.splice(pIdx, 1);

// --- VALIDATION DOUBLONS ---
const isDuplicate = (sn, index) => {
    if (!sn || sn.trim() === "") return false;
    const inCurrent = articleActuel.value.details_unites.some(
        (u, i) => u.numero_serie === sn && i !== index,
    );
    const inPanier = panier.value.some((p) =>
        p.details_unites.some((u) => u.numero_serie === sn),
    );
    return inCurrent || inPanier;
};

// --- ACTION : AJOUTER AU PANIER ---
const ajouterAuPanier = () => {
    tentativeAjout.value = true;
    const allSnFilled = articleActuel.value.details_unites.every((u) =>
        u.numero_serie?.trim(),
    );
    const hasDuplicates = articleActuel.value.details_unites.some((u, i) =>
        isDuplicate(u.numero_serie, i),
    );

    if (
        !articleActuel.value.categorie_id ||
        totalUnitesPhysiques.value <= 0 ||
        !allSnFilled ||
        hasDuplicates
    ) {
        return;
    }

    const cat = props.categories.find(
        (c) => c.id === articleActuel.value.categorie_id,
    );
    panier.value.push({
        ...JSON.parse(JSON.stringify(articleActuel.value)),
        nbrcarton: modeVrac.value ? 0 : articleActuel.value.nbrcarton,
        total_unites: totalUnitesPhysiques.value,
        nom_categorie: cat?.nom || "Inconnue",
    });

    // Reset
    articleActuel.value = {
        categorie_id: null,
        nbrcarton: 0,
        unite: 0,
        details_unites: [],
    };
    modeVrac.value = false;
    tentativeAjout.value = false;
};

const form = useForm({
    items: [],
    fournisseur: "",
    numero_contrat: "",
    date_livraison: "",
    scan_contrat: null,
});

const submitFinal = () => {
    tentativeFinale.value = true;
    if (
        !infosCommunes.value.fournisseur ||
        !infosCommunes.value.numero_contrat ||
        panier.value.length === 0
    ) {
        return;
    }
    Object.assign(form, infosCommunes.value);
    form.items = panier.value;
    form.post(route("materiel.store_group"), {
        forceFormData: true,
        onSuccess: () => {
            panier.value = [];
            form.reset();
            fileInputKey.value++;
            tentativeFinale.value = false;
        },
    });
};

const handleFileUpload = (e) => {
    infosCommunes.value.scan_contrat = e.target.files[0];
};
</script>

<template>
    <Head title="Réception de Stock" />

    <AuthenticatedLayout>
        <v-container
            fluid
            class="pa-4 bg-teal-lighten-5 custom-font min-vh-100 d-flex flex-column"
        >
            <v-card
                class="mb-4 rounded-xl shadow-card border-teal-top flex-shrink-0"
                elevation="0"
            >
                <v-card-text class="pa-4">
                    <v-row dense>
                        <v-col cols="12" md="3">
                            <v-text-field
                                v-model="infosCommunes.fournisseur"
                                label="Fournisseur"
                                variant="outlined"
                                color="teal-darken-1"
                                density="compact"
                                :error="
                                    tentativeFinale &&
                                    !infosCommunes.fournisseur
                                "
                                hide-details="auto"
                                class="custom-field"
                            ></v-text-field>
                        </v-col>
                        <v-col cols="12" md="3">
                            <v-text-field
                                v-model="infosCommunes.numero_contrat"
                                label="N° Contrat / BC"
                                variant="outlined"
                                color="teal-darken-1"
                                density="compact"
                                :error="
                                    tentativeFinale &&
                                    !infosCommunes.numero_contrat
                                "
                                hide-details="auto"
                                class="custom-field"
                            ></v-text-field>
                        </v-col>
                        <v-col cols="12" md="3">
                            <v-file-input
                                :key="fileInputKey"
                                label="Scan document"
                                variant="outlined"
                                color="teal-darken-1"
                                density="compact"
                                hide-details="auto"
                                class="custom-field"
                                prepend-icon=""
                                prepend-inner-icon="mdi-paperclip"
                                @change="handleFileUpload"
                            ></v-file-input>
                        </v-col>
                        <v-col cols="12" md="3">
                            <v-text-field
                                v-model="infosCommunes.date_livraison"
                                type="date"
                                label="Date Réception"
                                variant="outlined"
                                color="teal-darken-1"
                                density="compact"
                                hide-details="auto"
                                class="custom-field"
                            ></v-text-field>
                        </v-col>
                    </v-row>
                </v-card-text>
            </v-card>

            <v-row class="flex-grow-1 mb-2" no-gutters style="min-height: 0">
                <v-col
                    cols="12"
                    md="7"
                    class="pr-md-2 d-flex flex-column"
                    style="height: 80vh"
                >
                    <v-card
                        elevation="0"
                        class="rounded-xl shadow-card border overflow-hidden d-flex flex-column h-100"
                    >
                        <v-toolbar
                            color="teal-darken-1"
                            density="compact"
                            flat
                            class="flex-shrink-0"
                        >
                            <v-icon size="small" class="ml-4 mr-2"
                                >mdi-package-variant-closed</v-icon
                            >
                            <v-toolbar-title
                                class="text-caption font-weight-bold uppercase"
                                >CONFIGURATION DU LOT</v-toolbar-title
                            >
                            <v-spacer></v-spacer>
                            <v-btn-toggle
                                v-model="modeVrac"
                                mandatory
                                density="compact"
                                class="mr-4 custom-toggle-fixed"
                                selected-class="bg-white text-teal-darken-1"
                            >
                                <v-btn
                                    :value="false"
                                    size="x-small"
                                    class="toggle-btn"
                                    >CARTONS</v-btn
                                >
                                <v-btn
                                    :value="true"
                                    size="x-small"
                                    class="toggle-btn"
                                    >VRAC</v-btn
                                >
                            </v-btn-toggle>
                        </v-toolbar>

                        <div class="pa-4 bg-white border-b flex-shrink-0">
                            <v-row dense align="center">
                                <v-col cols="12" md="4">
                                    <v-autocomplete
                                        v-model="articleActuel.categorie_id"
                                        :items="categories"
                                        item-title="nom"
                                        item-value="id"
                                        label="Catégorie"
                                        variant="outlined"
                                        color="teal-darken-1"
                                        density="compact"
                                        hide-details="auto"
                                        class="custom-field"
                                    ></v-autocomplete>
                                </v-col>

                                <v-col cols="4" md="2">
                                    <v-text-field
                                        v-if="!modeVrac"
                                        v-model.number="articleActuel.nbrcarton"
                                        type="number"
                                        label="Nb Cartons"
                                        variant="outlined"
                                        color="teal-darken-1"
                                        density="compact"
                                        hide-details="auto"
                                        class="custom-field center-input"
                                    ></v-text-field>
                                    <div v-else style="height: 40px"></div>
                                </v-col>

                                <v-col cols="4" md="3">
                                    <v-text-field
                                        v-model.number="articleActuel.unite"
                                        type="number"
                                        :label="
                                            modeVrac
                                                ? 'Total Unités'
                                                : 'Uté/Ctn'
                                        "
                                        variant="outlined"
                                        color="teal-darken-1"
                                        density="compact"
                                        hide-details="auto"
                                        class="custom-field center-input"
                                    ></v-text-field>
                                </v-col>

                                <v-col cols="4" md="3">
                                    <div
                                        :class="
                                            modeVrac
                                                ? 'total-badge-vrac'
                                                : 'total-badge-teal'
                                        "
                                        class="d-flex align-center justify-center"
                                        style="
                                            height: 40px;
                                            white-space: nowrap;
                                        "
                                    >
                                        <v-icon size="x-small" class="mr-1"
                                            >mdi-sigma</v-icon
                                        >
                                        TOTAL: {{ totalUnitesPhysiques }}
                                    </div>
                                </v-col>
                            </v-row>
                        </div>

                        <div
                            class="flex-grow-1 overflow-y-auto bg-teal-lighten-5 pa-3"
                            style="max-height: 100%"
                        >
                            <v-expansion-panels
                                v-if="totalUnitesPhysiques > 0"
                                variant="accordion"
                                class="custom-panels"
                            >
                                <v-expansion-panel
                                    v-for="(
                                        u, i
                                    ) in articleActuel.details_unites"
                                    :key="i"
                                    class="mb-2 rounded-lg border overflow-hidden"
                                    elevation="0"
                                >
                                    <v-expansion-panel-title
                                        class="py-2 px-4 bg-white"
                                    >
                                        <div class="d-flex align-center w-100">
                                            <v-chip
                                                size="x-small"
                                                color="teal-darken-1"
                                                variant="flat"
                                                class="mr-3 font-weight-bold"
                                                >UNITÉ {{ i + 1 }}</v-chip
                                            >
                                            <span
                                                :class="
                                                    u.numero_serie
                                                        ? 'text-teal-darken-3'
                                                        : 'text-grey-darken-1'
                                                "
                                                class="text-caption font-weight-bold"
                                            >
                                                {{
                                                    u.numero_serie ||
                                                    "Saisir le N° de Série..."
                                                }}
                                            </span>
                                            <v-spacer></v-spacer>
                                            <v-icon
                                                v-if="
                                                    isDuplicate(
                                                        u.numero_serie,
                                                        i,
                                                    ) ||
                                                    (tentativeAjout &&
                                                        !u.numero_serie)
                                                "
                                                color="red"
                                                size="small"
                                                class="mr-2"
                                                >mdi-alert-circle</v-icon
                                            >
                                            <v-chip
                                                v-if="u.pieces.length"
                                                size="x-small"
                                                color="orange-darken-2"
                                                variant="tonal"
                                                class="font-weight-bold"
                                                >{{
                                                    u.pieces.length
                                                }}
                                                PCS</v-chip
                                            >
                                        </div>
                                    </v-expansion-panel-title>

                                    <v-expansion-panel-text
                                        class="bg-white border-t"
                                    >
                                        <v-row dense class="pt-2">
                                            <v-col cols="6"
                                                ><v-text-field
                                                    v-model="u.nom"
                                                    label="Désignation"
                                                    color="teal-darken-1"
                                                    variant="underlined"
                                                    density="compact"
                                                    class="small-text"
                                                ></v-text-field
                                            ></v-col>
                                            <v-col cols="6"
                                                ><v-text-field
                                                    v-model="u.numero_serie"
                                                    label="N° de Série"
                                                    color="teal-darken-1"
                                                    variant="underlined"
                                                    density="compact"
                                                    prepend-inner-icon="mdi-barcode-scan"
                                                    class="small-text"
                                                ></v-text-field
                                            ></v-col>
                                        </v-row>

                                        <div
                                            class="mt-2 pa-2 rounded-lg border-dashed-teal bg-teal-lighten-5"
                                        >
                                            <div
                                                class="d-flex justify-space-between align-center mb-1"
                                            >
                                                <span
                                                    class="text-overline text-teal-darken-3 font-weight-bold"
                                                    style="
                                                        font-size: 0.65rem !important;
                                                    "
                                                    >PIÈCES</span
                                                >
                                                <v-btn
                                                    size="x-small"
                                                    color="teal-darken-1"
                                                    variant="flat"
                                                    @click="ajouterPiece(i)"
                                                    class="rounded-pill"
                                                    height="20"
                                                >
                                                    + Ajouter
                                                </v-btn>
                                            </div>

                                            <v-row
                                                v-for="(p, pi) in u.pieces"
                                                :key="pi"
                                                dense
                                                align="center"
                                                class="mb-1"
                                            >
                                                <v-col cols="5">
                                                    <v-text-field
                                                        v-model="p.nom"
                                                        placeholder="Nom"
                                                        variant="solo"
                                                        density="compact"
                                                        flat
                                                        hide-details
                                                        class="compact-input"
                                                    ></v-text-field>
                                                </v-col>
                                                <v-col cols="5">
                                                    <v-text-field
                                                        v-model="p.sn"
                                                        placeholder="S/N"
                                                        variant="solo"
                                                        density="compact"
                                                        flat
                                                        hide-details
                                                        class="compact-input"
                                                    ></v-text-field>
                                                </v-col>
                                                <v-col
                                                    cols="2"
                                                    class="text-right"
                                                >
                                                    <v-btn
                                                        icon="mdi-close-circle"
                                                        size="x-small"
                                                        variant="text"
                                                        color="red-darken-2"
                                                        @click="
                                                            retirerPiece(i, pi)
                                                        "
                                                        density="comfortable"
                                                    ></v-btn>
                                                </v-col>
                                            </v-row>
                                        </div>
                                    </v-expansion-panel-text>
                                </v-expansion-panel>
                            </v-expansion-panels>
                            <div
                                v-else
                                class="d-flex flex-column align-center justify-center fill-height text-teal-lighten-3"
                            >
                                <v-icon size="64">mdi-package-variant</v-icon>
                                <p class="text-caption mt-2">
                                    Définissez une quantité pour commencer
                                </p>
                            </div>
                        </div>

                        <v-card-actions
                            class="pa-4 border-t bg-white flex-shrink-0"
                        >
                            <v-btn
                                block
                                color="teal-darken-1"
                                size="large"
                                variant="elevated"
                                @click="ajouterAuPanier"
                                class="rounded-xl font-weight-bold shadow-teal"
                            >
                                <v-icon start>mdi-plus-box</v-icon> AJOUTER CE
                                LOT AU RÉCAPITULATIF
                            </v-btn>
                        </v-card-actions>
                    </v-card>
                </v-col>

                <v-col
                    cols="12"
                    md="5"
                    class="pl-md-2 d-flex flex-column"
                    style="height: 80vh"
                >
                    <v-card
                        elevation="0"
                        class="rounded-xl shadow-card border overflow-hidden d-flex flex-column h-100"
                    >
                        <v-toolbar
                            color="teal-darken-2"
                            density="compact"
                            flat
                            class="flex-shrink-0"
                        >
                            <v-icon size="small" class="ml-4 mr-2"
                                >mdi-clipboard-list-outline</v-icon
                            >
                            <v-toolbar-title
                                class="text-caption font-weight-bold"
                                >RÉCAPITULATIF PRÊT À L'ENVOI</v-toolbar-title
                            >
                            <v-chip
                                size="x-small"
                                class="mr-4 font-weight-black"
                                color="white"
                                variant="flat"
                                text-color="teal-darken-2"
                                >{{ panier.length }} LOTS</v-chip
                            >
                        </v-toolbar>

                        <div
                            class="flex-grow-1 overflow-y-auto bg-white"
                            style="max-height: 100%"
                        >
                            <v-alert
                                v-if="tentativeFinale && panier.length === 0"
                                type="error"
                                variant="tonal"
                                class="ma-3 rounded-lg text-caption"
                            >
                                Votre panier est vide.
                            </v-alert>

                            <v-list v-if="panier.length" class="pa-0">
                                <v-list-item
                                    v-for="(p, idx) in panier"
                                    :key="idx"
                                    class="border-b pa-4 hover-item-teal"
                                >
                                    <template v-slot:prepend>
                                        <v-avatar
                                            color="teal-darken-1"
                                            size="40"
                                            class="shadow-sm"
                                        >
                                            <v-icon color="white" size="small"
                                                >mdi-layers-triple</v-icon
                                            >
                                        </v-avatar>
                                    </template>
                                    <v-list-item-title
                                        class="text-caption font-weight-black text-teal-darken-3"
                                        >{{
                                            p.nom_categorie
                                        }}</v-list-item-title
                                    >
                                    <v-list-item-subtitle
                                        class="text-caption mt-1"
                                    >
                                        <v-chip
                                            size="x-small"
                                            variant="outlined"
                                            color="teal"
                                            class="mr-1"
                                            >{{ p.total_unites }} Unités</v-chip
                                        >
                                        <v-chip
                                            v-if="p.nbrcarton > 0"
                                            size="x-small"
                                            variant="outlined"
                                            color="teal"
                                            >{{ p.nbrcarton }} Cartons</v-chip
                                        >
                                    </v-list-item-subtitle>
                                    <template v-slot:append>
                                        <v-btn
                                            icon="mdi-delete-outline"
                                            size="small"
                                            color="red-darken-1"
                                            variant="text"
                                            @click="panier.splice(idx, 1)"
                                        ></v-btn>
                                    </template>
                                </v-list-item>
                            </v-list>
                        </div>

                        <v-card-actions
                            class="pa-4 border-t bg-teal-lighten-5 flex-shrink-0"
                        >
                            <v-btn
                                block
                                color="teal-darken-2"
                                size="x-large"
                                @click="submitFinal"
                                :loading="form.processing"
                                class="rounded-xl font-weight-black shadow-teal"
                            >
                                <v-icon start size="small"
                                    >mdi-cloud-check</v-icon
                                >
                                FINALISER L'ENREGISTREMENT
                            </v-btn>
                        </v-card-actions>
                    </v-card>
                </v-col>
            </v-row>
        </v-container>
    </AuthenticatedLayout>
</template>

<style scoped>
.compact-input :deep(.v-field__input) {
    min-height: 32px !important;
    padding-top: 5px !important;
    padding-bottom: 5px !important;
    font-size: 0.8rem !important;
}
.custom-font {
    font-family: "Inter", sans-serif !important;
}

/* Fixation de la hauteur pour que les deux colonnes soient identiques */

/* Gestion du scroll interne */
.overflow-y-auto {
    overflow-y: auto;
}

.shadow-card {
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.04) !important;
}
.shadow-teal {
    box-shadow: 0 4px 12px rgba(0, 137, 123, 0.25) !important;
}
.border-teal-top {
    border-top: 4px solid #00897b !important;
}

/* Empêche les colonnes de s'écraser */
.v-container {
    height: 100vh;
}

.total-badge-teal,
.total-badge-vrac {
    padding: 8px;
    font-weight: 900;
    border-radius: 12px;
    font-size: 0.7rem;
    text-align: center;
}
.total-badge-teal {
    background: #e0f2f1;
    color: #00695c;
    border: 2px solid #00897b;
}
.total-badge-vrac {
    background: #fff3e0;
    color: #e65100;
    border: 2px solid #fb8c00;
}

.custom-toggle {
    border: 1px solid rgba(255, 255, 255, 0.2);
    background: rgba(0, 0, 0, 0.1);
}

.overflow-y-auto::-webkit-scrollbar {
    width: 5px;
}
.overflow-y-auto::-webkit-scrollbar-thumb {
    background: #b2dfdb;
    border-radius: 10px;
}
/* Fixer la largeur des boutons de switch */
.toggle-btn {
    min-width: 80px !important; /* Force une largeur égale pour les deux boutons */
}

/* Forcer le badge total à ne pas changer de taille selon le nombre */
.total-badge-teal,
.total-badge-vrac {
    min-width: 100px;
    height: 40px !important; /* Aligné sur la hauteur des text-fields compact */
    display: flex;
    align-items: center;
    justify-content: center;
}

/* Optionnel : aligner parfaitement les inputs */
.custom-field :deep(.v-input__control) {
    height: 40px !important;
}
</style>
