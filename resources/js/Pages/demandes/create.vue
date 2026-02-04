<script setup lang="ts">
import { ref, watch, computed, onMounted } from "vue";
import { Head, useForm } from "@inertiajs/vue3";
import AuthentDemandeLayout from "@/Layouts/AuthentDemandeLayout.vue";

declare function route(name?: string, params?: any): string;

interface Piece {
    id: number;
    nom_piece: string;
    statut: string;
    demande_id: number | null;
}

interface Materiel {
    id: number;
    nom: string;
    numero_serie?: string;
    etat: string;
    statut: string;
    demande_id: number | null;
    categorie?: { nom: string };
    pieces: Piece[];
}

interface Service {
    id: number;
    nom: string;
}

const props = defineProps<{
    materiels: Materiel[];
    services: Service[];
}>();

// --- NOTIFICATIONS ---
const snackbar = ref({ show: false, text: "", color: "" });
const showNotify = (text: string, color: string = "success") => {
    snackbar.value = { show: true, text, color };
};

const tentativeValidation = ref(false);

const form = useForm({
    numcomande: "" as string,
    demandeur_nom: "",
    service_beneficiaire: "",
    date_demande: new Date().toISOString().substring(0, 10),
    items: [] as any[],
});

const saisieActuelle = ref({
    materiel_id: null as number | null,
    // On ajoute "unite" dans les types autorisés
    type_sortie: "complet" as "complet" | "pieces" | "unite",
    pieces_selectionnees: [] as number[],
    nom_materiel: "",
    nbredemande: 1,
    description: "",
});
const verifierChangementType = (valeur: string) => {
    if (valeur === "complet") {
        // Sélectionne toutes les pièces d'un coup
        saisieActuelle.value.pieces_selectionnees = piecesDisponibles.value.map(
            (p) => p.id,
        );
    } else if (valeur === "unite") {
        // Vide la liste pour l'unité seule
        saisieActuelle.value.pieces_selectionnees = [];
    }
};
const erreursSaisie = ref({ materiel: false, pieces: false });

// --- LOGIQUE DE FILTRAGE CORRIGÉE ---
const materielsFiltres = computed(() => {
    const idsDansPanier = form.items.map((item) => item.materiel_id);
    return (props.materiels || []).filter((mat) => {
        // Condition d'origine : est en stock au magasin
        const estDisponible = mat.etat !== "En attente" && mat.etat !== "Livré";

        // NOUVELLE CONDITION : a encore des pièces détachées libres (demande_id est null)
        const aEncoreDesPieces =
            mat.pieces && mat.pieces.some((p) => p.demande_id === null);

        const nEstPasDansPanier = !idsDansPanier.includes(mat.id);

        // On le garde si (Disponible OU s'il reste des pièces) ET n'est pas déjà dans le panier
        return (estDisponible || aEncoreDesPieces) && nEstPasDansPanier;
    });
});

const materielSelectionne = computed(() => {
    return (
        props.materiels.find(
            (m) => m.id === saisieActuelle.value.materiel_id,
        ) || null
    );
});
// Vérifie si l'unité elle-même est disponible (pas encore livrée)
const uniteEstDisponible = computed(() => {
    const mat = materielSelectionne.value;
    if (!mat) return false;
    return mat.etat !== "En attente" && mat.etat !== "Livré";
});

const aDesPieces = computed(
    () => (materielSelectionne.value?.pieces?.length || 0) > 0,
);

const verifierSiIncomplet = (mat: Materiel) => {
    if (!mat.pieces || mat.pieces.length === 0) return false;
    return mat.pieces.some((p) => p.demande_id !== null);
};

const piecesDisponibles = computed(() => {
    return materielSelectionne.value
        ? materielSelectionne.value.pieces.filter((p) => p.demande_id === null)
        : [];
});

// --- TES ACTIONS ---
watch(
    () => saisieActuelle.value.materiel_id,
    () => {
        erreursSaisie.value.materiel = false;
        saisieActuelle.value.pieces_selectionnees = [];

        if (materielSelectionne.value) {
            // CAS SPÉCIFIQUE : Le matériel a des pièces, mais elles sont toutes livrées/attente
            if (aDesPieces.value && piecesDisponibles.value.length === 0) {
                saisieActuelle.value.type_sortie = "unite";
            }
            // Si l'unité seule est déjà sortie
            else if (!uniteEstDisponible.value) {
                saisieActuelle.value.type_sortie = "pieces";
            }
            // Sinon, mode normal
            else {
                saisieActuelle.value.type_sortie = "complet";
            }
        }
    },
);
const ajouterAuPanier = () => {
    const mat = materielSelectionne.value;
    if (!mat) {
        erreursSaisie.value.materiel = true;
        return;
    }

    let nomFinal = "";
    let descriptionSpecifique = saisieActuelle.value.description;

    if (saisieActuelle.value.type_sortie === "pieces") {
        if (saisieActuelle.value.pieces_selectionnees.length === 0) {
            erreursSaisie.value.pieces = true;
            return;
        }
        nomFinal = `PIÈCES DÉTACHÉES SN: ${mat.numero_serie || "N/A"}`;
    } else {
        nomFinal = `💻 ${mat.nom}${aDesPieces.value ? (verifierSiIncomplet(mat) ? " (INCOMPLET)" : " (COMPLET)") : ""}`;
    }

    form.items.push({
        ...JSON.parse(JSON.stringify(saisieActuelle.value)),
        nom_affiche: nomFinal,
        description: descriptionSpecifique,
        numero_serie: mat.numero_serie || "N/A",
    });

    showNotify("Ajouté");
    resetSaisie();
};

const resetSaisie = () => {
    saisieActuelle.value = {
        materiel_id: null,
        type_sortie: "complet",
        pieces_selectionnees: [],
        nom_materiel: "",
        nbredemande: 1,
        description: "",
    };
};

const validerToutLePanier = () => {
    tentativeValidation.value = true;
    if (form.items.length === 0) return showNotify("Liste vide", "error");
    if (!form.demandeur_nom || !form.service_beneficiaire) return;

    form.post(route("demandes.store_group"), {
        onSuccess: () => {
            form.reset();
            tentativeValidation.value = false;
            showNotify("Bon de sortie validé !");
        },
    });
};

onMounted(() => {
    form.numcomande = `CMD-${new Date().getFullYear()}-${Math.floor(1000 + Math.random() * 9000)}`;
});
</script>

<template>
    <Head title="Nouveau Bon de Sortie" />
    <AuthentDemandeLayout>
        <v-container
            fluid
            class="pa-4 bg-grey-lighten-4 fill-height align-start"
        >
            <v-snackbar
                v-model="snackbar.show"
                :color="snackbar.color"
                timeout="2000"
                location="top right"
            >
                {{ snackbar.text }}
            </v-snackbar>

            <v-card
                class="mb-4 rounded-lg border-s-xl pa-4 w-100"
                style="border-left: 6px solid #424242 !important"
                flat
                border
            >
                <v-row dense>
                    <v-col cols="12" md="2">
                        <v-text-field
                            v-model="form.numcomande"
                            label="N°"
                            variant="outlined"
                            density="compact"
                            readonly
                            bg-color="grey-lighten-5"
                        ></v-text-field>
                    </v-col>
                    <v-col cols="12" md="3">
                        <v-text-field
                            v-model="form.demandeur_nom"
                            label="RECEVEUR *"
                            variant="outlined"
                            density="compact"
                            :error-messages="
                                tentativeValidation && !form.demandeur_nom
                                    ? 'Requis'
                                    : ''
                            "
                        ></v-text-field>
                    </v-col>
                    <v-col cols="12" md="4">
                        <v-autocomplete
                            v-model="form.service_beneficiaire"
                            :items="services"
                            item-title="nom"
                            item-value="nom"
                            label="SERVICE *"
                            variant="outlined"
                            density="compact"
                            :error-messages="
                                tentativeValidation &&
                                !form.service_beneficiaire
                                    ? 'Requis'
                                    : ''
                            "
                        ></v-autocomplete>
                    </v-col>
                    <v-col cols="12" md="3">
                        <v-text-field
                            v-model="form.date_demande"
                            type="date"
                            label="DATE"
                            variant="outlined"
                            density="compact"
                        ></v-text-field>
                    </v-col>
                </v-row>
            </v-card>

            <v-row dense>
                <v-col cols="12" md="4">
                    <v-card
                        flat
                        border
                        class="rounded-xl h-100 shadow-sm overflow-hidden"
                    >
                        <v-toolbar color="teal-darken-1" density="compact" flat>
                            <v-icon
                                icon="mdi-tag-outline"
                                size="small"
                                color="white"
                                class="ml-4"
                            ></v-icon>
                            <v-toolbar-title
                                class="text-subtitle-2 font-weight-bold text-white"
                            >
                                Détails de l'article
                            </v-toolbar-title>
                        </v-toolbar>

                        <v-card-text class="pa-5 bg-white">
                            <v-autocomplete
                                v-model="saisieActuelle.materiel_id"
                                :items="materielsFiltres"
                                item-title="nom"
                                item-value="id"
                                label="Rechercher matériel..."
                                variant="outlined"
                                density="comfortable"
                                color="teal-darken-1"
                                class="mb-4"
                            ></v-autocomplete>
                            <v-expand-transition>
                                <div v-if="aDesPieces">
                                    <v-btn-toggle
                                        v-model="saisieActuelle.type_sortie"
                                        mandatory
                                        color="teal-darken-1"
                                        variant="tonal"
                                        class="mb-4 d-flex border w-100"
                                        density="comfortable"
                                        @update:model-value="
                                            verifierChangementType
                                        "
                                    >
                                        <v-btn
                                            v-if="uniteEstDisponible"
                                            value="unite"
                                            class="flex-grow-1"
                                            >UNITÉ</v-btn
                                        >

                                        <v-btn
                                            v-if="piecesDisponibles.length > 0"
                                            value="pieces"
                                            class="flex-grow-1"
                                            >PIÈCES</v-btn
                                        >

                                        <v-btn
                                            v-if="
                                                uniteEstDisponible &&
                                                piecesDisponibles.length > 0 &&
                                                materielSelectionne?.etat !==
                                                    'En attente' &&
                                                materielSelectionne?.etat !==
                                                    'Livré'
                                            "
                                            value="complet"
                                            class="flex-grow-1"
                                            >COMPLET</v-btn
                                        >
                                    </v-btn-toggle>

                                    <v-select
                                        v-if="
                                            saisieActuelle.type_sortie !==
                                            'unite'
                                        "
                                        v-model="
                                            saisieActuelle.pieces_selectionnees
                                        "
                                        :items="piecesDisponibles"
                                        item-title="nom_piece"
                                        item-value="id"
                                        label="Sélectionner les pièces"
                                        multiple
                                        chips
                                        variant="outlined"
                                        density="comfortable"
                                        color="teal-darken-1"
                                        class="mb-4"
                                    ></v-select>
                                </div>
                            </v-expand-transition>

                            <v-text-field
                                v-model.number="saisieActuelle.nbredemande"
                                type="number"
                                label="Quantité"
                                variant="outlined"
                                density="comfortable"
                                color="teal-darken-1"
                                class="mb-4"
                            ></v-text-field>

                            <v-textarea
                                v-model="saisieActuelle.description"
                                label="Note / Observation"
                                variant="outlined"
                                rows="2"
                                density="comfortable"
                                color="teal-darken-1"
                                class="mb-4"
                            ></v-textarea>

                            <v-btn
                                color="teal-darken-1"
                                block
                                size="large"
                                variant="flat"
                                @click="ajouterAuPanier"
                                prepend-icon="mdi-plus"
                                class="text-none font-weight-bold rounded-pill elevation-2"
                            >
                                AJOUTER À LA LISTE
                            </v-btn>
                        </v-card-text>
                    </v-card>
                </v-col>

                <v-col cols="12" md="8">
                    <v-card
                        flat
                        border
                        class="rounded-xl d-flex flex-column h-100 shadow-sm overflow-hidden"
                    >
                        <v-toolbar color="teal-darken-1" density="compact" flat>
                            <v-icon
                                icon="mdi-format-list-bulleted"
                                size="small"
                                color="white"
                                class="ml-4"
                            ></v-icon>
                            <v-toolbar-title
                                class="text-subtitle-2 font-weight-bold text-white"
                            >
                                Récapitulatif de la sélection
                            </v-toolbar-title>
                        </v-toolbar>

                        <v-table density="comfortable" class="flex-grow-1">
                            <thead class="bg-teal-lighten-5">
                                <tr>
                                    <th
                                        class="text-left font-weight-bold text-teal-darken-4"
                                    >
                                        DÉSIGNATION
                                    </th>
                                    <th
                                        class="text-center font-weight-bold text-teal-darken-4"
                                    >
                                        S/N
                                    </th>
                                    <th
                                        class="text-right font-weight-bold text-teal-darken-4 pr-6"
                                    >
                                        ACTIONS
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="(item, index) in form.items"
                                    :key="index"
                                >
                                    <td
                                        class="text-caption font-weight-bold py-2"
                                    >
                                        {{ item.nom_affiche }}
                                    </td>
                                    <td class="text-center">
                                        <v-chip
                                            size="x-small"
                                            color="teal-darken-1"
                                            variant="tonal"
                                            class="font-weight-bold"
                                        >
                                            {{ item.numero_serie || "N/A" }}
                                        </v-chip>
                                    </td>
                                    <td class="text-right">
                                        <v-btn
                                            icon="mdi-delete-outline"
                                            color="red-darken-1"
                                            variant="text"
                                            size="small"
                                            @click="form.items.splice(index, 1)"
                                        ></v-btn>
                                    </td>
                                </tr>
                                <tr v-if="form.items.length === 0">
                                    <td
                                        colspan="3"
                                        class="text-center py-10 text-grey-darken-1 italic bg-grey-lighten-5"
                                    >
                                        Aucun article sélectionné.
                                    </td>
                                </tr>
                            </tbody>
                        </v-table>

                        <v-divider></v-divider>

                        <v-card-actions class="pa-4 bg-white">
                            <v-spacer></v-spacer>
                            <v-btn
                                color="teal-darken-1"
                                variant="flat"
                                size="large"
                                class="px-10 text-none font-weight-black rounded-pill elevation-3"
                                @click="validerToutLePanier"
                                :loading="form.processing"
                                prepend-icon="mdi-check-all"
                            >
                                VALIDER LE BON DE SORTIE
                            </v-btn>
                        </v-card-actions>
                    </v-card>
                </v-col>
            </v-row>
        </v-container>
    </AuthentDemandeLayout>
</template>

<style scoped>
.italic {
    font-style: italic;
}
:deep(.v-table__wrapper) {
    overflow-y: auto !important;
    max-height: 400px;
}
</style>
