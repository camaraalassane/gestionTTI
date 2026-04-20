<script setup>
    import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
    import { Head, useForm } from "@inertiajs/vue3";
    import { ref, computed, watch } from "vue";
    import axios from "axios";
    import { debounce } from "lodash";

    const props = defineProps({ categories: Array });

    // --- RÉFÉRENCES & ÉTATS ---
    const fileInputKey = ref(0);
    const tentativeAjout = ref(false);
    const tentativeFinale = ref(false);
    const isLoadingContrat = ref(false);
    const contratExisteDeja = ref(false);
    const statsContrat = ref(null);
    const snEnBaseDeDonnees = ref([]);

    // Pour la recherche de modèles existants
    const modelesExistants = ref([]);
    const isLoadingModeles = ref(false);
    const showModelesList = ref(false);
    const modeleSelectionne = ref(null);

    const infosCommunes = ref({
        fournisseur: "",
        numero_contrat: "",
        quantite_totale_prevue: 0,
        date_livraison: new Date().toISOString().substr(0, 10),
        scan_contrat: null,
        ancien_scan_path: null,
    });

    const articleActuel = ref({
        designation: "",
        categorie_id: null,
        unite: 0,
        nbr_pieces_global: 0,
        pieces_modeles: [],
        nbrcarton: 0,
        details_unites: [
            {
                nom: "",
                numeros_serie: [],
            },
        ],
    });

    const panier = ref([]);

    // --- FONCTIONS POUR L'AUTOCOMPLÉTION ---
    const hideModelesList = () => {
        setTimeout(() => {
            showModelesList.value = false;
        }, 200);
    };

    // Recherche de modèles existants
    const rechercherModeles = debounce(async () => {
        const searchTerm = articleActuel.value.designation;

        if (!searchTerm || searchTerm.trim().length < 2) {
            modelesExistants.value = [];
            showModelesList.value = false;
            return;
        }

        isLoadingModeles.value = true;
        try {
            const response = await axios.get('/recherche-modeles', {
                params: {
                    q: searchTerm.trim(),
                    categorie_id: articleActuel.value.categorie_id || undefined
                }
            });

            modelesExistants.value = response.data;
            showModelesList.value = response.data.length > 0;
        } catch (error) {
            console.error("Erreur recherche modèles:", error);
            modelesExistants.value = [];
            showModelesList.value = false;
        } finally {
            isLoadingModeles.value = false;
        }
    }, 400);

    // Sélectionner un modèle existant
    const selectionnerModeleExistant = (modele) => {
        modeleSelectionne.value = modele;
        articleActuel.value.designation = modele.nom;
        articleActuel.value.categorie_id = modele.categorie_id;

        if (modele.pieces_count === 0) {
            articleActuel.value.nbr_pieces_global = 0;
        }

        modelesExistants.value = [];
        showModelesList.value = false;
    };

    // Quand la catégorie change, on relance la recherche
    watch(() => articleActuel.value.categorie_id, () => {
        if (articleActuel.value.designation && articleActuel.value.designation.length >= 2) {
            rechercherModeles();
        }
    });

    // --- LOGIQUE DE GROUPAGE POUR L'AFFICHAGE ---
    const panierGroupe = computed(() => {
        return panier.value.reduce((acc, item) => {
            const key = item.nom_categorie || "Inconnue";
            if (!acc[key]) acc[key] = [];
            acc[key].push(item);
            return acc;
        }, {});
    });

    // --- LOGIQUE DES PIÈCES COMMUNES ---
    watch(() => articleActuel.value.nbr_pieces_global, (nouveauNbr) => {
        const nbr = parseInt(nouveauNbr) || 0;
        const modeles = articleActuel.value.pieces_modeles;

        if (nbr > modeles.length) {
            for (let i = modeles.length; i < nbr; i++) {
                modeles.push({ nom: "" });
            }
        } else {
            articleActuel.value.pieces_modeles = modeles.slice(0, nbr);
        }
        synchroniserNomsPieces();
    });

    const synchroniserNomsPieces = () => {
        const series = articleActuel.value.details_unites[0]?.numeros_serie;
        const modeles = articleActuel.value.pieces_modeles;

        if (!series) return;

        series.forEach((sn) => {
            if (sn.pieces.length !== modeles.length) {
                const nouvellesPieces = [];
                modeles.forEach((m, idx) => {
                    nouvellesPieces.push({
                        nom: m.nom,
                        sn: sn.pieces[idx]?.sn || ""
                    });
                });
                sn.pieces = nouvellesPieces;
            } else {
                modeles.forEach((m, idx) => {
                    sn.pieces[idx].nom = m.nom;
                });
            }
        });
    };

    watch(() => articleActuel.value.pieces_modeles, () => {
        synchroniserNomsPieces();
    }, { deep: true });


    // --- CALCULS DE VALIDATION & BANDREAU ---
    const totalUnitesPhysiques = computed(() => Number(articleActuel.value.unite) || 0);

    const totalUnitesDansPanier = computed(() => {
        return panier.value.reduce((sum, item) => sum + (Number(item.total_unites) || 0), 0);
    });

    const totalRef = computed(() => {
        return statsContrat.value
            ? Number(statsContrat.value.total)
            : Number(infosCommunes.value.quantite_totale_prevue) || 0;
    });

    const resteARecevoir = computed(() => {
        const totalPrevu = totalRef.value;
        const dejaRecuBase = Number(statsContrat.value?.deja_recu) || 0;
        return totalPrevu - dejaRecuBase;
    });

    const resteDynamique = computed(() => {
        return resteARecevoir.value - totalUnitesPhysiques.value;
    });

    const surplusDetecte = computed(() => {
        if (totalRef.value <= 0) return false;
        const cumulTotal = (Number(statsContrat.value?.deja_recu) || 0) + totalUnitesDansPanier.value + totalUnitesPhysiques.value;
        return cumulTotal > totalRef.value;
    });


    // --- VÉRIFICATION S/N EN BASE ---
    const verifierSnEnBase = debounce(async (sn) => {
        if (!sn || sn.length < 3) return;
        try {
            const response = await axios.get(`/check-sn/${sn}`);
            if (response.data.exists) {
                if (!snEnBaseDeDonnees.value.includes(sn)) {
                    snEnBaseDeDonnees.value.push(sn);
                }
            } else {
                snEnBaseDeDonnees.value = snEnBaseDeDonnees.value.filter((s) => s !== sn);
            }
        } catch (error) {
            console.error("Erreur S/N:", error);
        }
    }, 500);

    // --- VÉRIFICATION DU CONTRAT ---
    watch(() => infosCommunes.value.numero_contrat, async (newVal) => {
        if (!newVal || newVal.length <= 2) {
            contratExisteDeja.value = false;
            statsContrat.value = null;
            return;
        }
        isLoadingContrat.value = true;
        try {
            const response = await axios.get(route("reception.check", { numero: newVal }));
            if (response.data.exists) {
                contratExisteDeja.value = true;
                infosCommunes.value.fournisseur = response.data.fournisseur;
                infosCommunes.value.quantite_totale_prevue = Number(response.data.total_prevu);
                infosCommunes.value.ancien_scan_path = response.data.scan_contrat;
                statsContrat.value = {
                    deja_recu: Number(response.data.deja_recu) || 0,
                    stock_dispo: Number(response.data.stock_dispo) || 0,
                    total: Number(response.data.total_prevu) || 0,
                    reste: Number(response.data.reste) || 0,
                    scan_existant: response.data.scan_contrat
                };
            } else {
                contratExisteDeja.value = false;
                statsContrat.value = null;
                infosCommunes.value.ancien_scan_path = null;
            }
        } catch (error) {
            console.error("Erreur contrat:", error);
        } finally {
            isLoadingContrat.value = false;
        }
    });

    // --- VALIDATION DES CHAMPS ---
    const aDesErreursDansLeLot = computed(() => {
        const art = articleActuel.value;
        if (!art.designation || !art.categorie_id || totalUnitesPhysiques.value <= 0) return true;
        if (art.nbr_pieces_global > 0) {
            return art.pieces_modeles.some(m => !m.nom || m.nom.trim() === "");
        }
        return false;
    });

    // --- GÉNÉRATION DES LIGNES S/N ---
    watch(totalUnitesPhysiques, (nouveauTotal) => {
        if (!articleActuel.value.details_unites[0]) {
            articleActuel.value.details_unites[0] = { nom: articleActuel.value.designation, numeros_serie: [] };
        }
        const listeSN = articleActuel.value.details_unites[0].numeros_serie;

        if (nouveauTotal > listeSN.length) {
            for (let i = listeSN.length; i < nouveauTotal; i++) {
                listeSN.push({ valeur: "", pieces: [] });
            }
            synchroniserNomsPieces();
        } else {
            articleActuel.value.details_unites[0].numeros_serie = listeSN.slice(0, nouveauTotal);
        }
    });

    // --- GESTION DU PANIER ---
    const ajouterAuPanier = () => {
        tentativeAjout.value = true;
        if (aDesErreursDansLeLot.value || surplusDetecte.value) return;

        const cat = props.categories.find((c) => c.id === articleActuel.value.categorie_id);
        const articleAInserer = JSON.parse(JSON.stringify(articleActuel.value));

        panier.value.push({
            ...articleAInserer,
            total_unites: totalUnitesPhysiques.value,
            nom_categorie: cat?.nom || "Inconnue",
            modele_id: modeleSelectionne.value?.id || null
        });

        articleActuel.value = {
            designation: "", categorie_id: null, unite: 0, nbr_pieces_global: 0, pieces_modeles: [], nbrcarton: 0,
            details_unites: [{ nom: "", numeros_serie: [] }],
        };
        snEnBaseDeDonnees.value = [];
        modeleSelectionne.value = null;
        modelesExistants.value = [];
        showModelesList.value = false;
        tentativeAjout.value = false;
    };

    const retirerDuPanier = (article) => {
        const index = panier.value.indexOf(article);
        if (index > -1) panier.value.splice(index, 1);
    };

    // --- SOUMISSION FINALE ---
    const form = useForm({
        items: [], fournisseur: "", numero_contrat: "", quantite_totale_prevue: 0, date_livraison: "", scan_contrat: null, ancien_scan_path: null,
    });

    const submitFinal = () => {
        tentativeFinale.value = true;
        if (!infosCommunes.value.fournisseur || !infosCommunes.value.numero_contrat || panier.value.length === 0) return;
        form.ancien_scan_path = infosCommunes.value.ancien_scan_path;
        Object.assign(form, infosCommunes.value);
        form.items = panier.value;

        form.post(route("materiel.store_group"), {
            forceFormData: true,
            onSuccess: () => {
                panier.value = [];
                infosCommunes.value = {
                    fournisseur: "", numero_contrat: "", quantite_totale_prevue: 0,
                    date_livraison: new Date().toISOString().substr(0, 10), scan_contrat: null,
                };
                form.reset();
                fileInputKey.value++;
                tentativeFinale.value = false;
                statsContrat.value = null;
                contratExisteDeja.value = false;
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
        <v-container fluid class="pa-4 bg-teal-lighten-5 custom-font min-vh-100 d-flex flex-column">
            <v-card class="mb-4 rounded-xl shadow-card border-teal-top flex-shrink-0" elevation="0">
                <v-card-text class="pa-3">
                    <v-row dense align="center">
                        <v-col cols="12" md="2">
                            <v-text-field v-model="infosCommunes.fournisseur" label="Fournisseur" variant="outlined" color="teal-darken-1" density="compact" :readonly="contratExisteDeja" hide-details="auto" class="text-caption"></v-text-field>
                        </v-col>
                        <v-col cols="12" md="2">
                            <v-text-field v-model="infosCommunes.numero_contrat" label="N° Contrat / BC" variant="outlined" color="teal-darken-1" density="compact" :loading="isLoadingContrat" hide-details="auto" class="text-caption font-weight-black"></v-text-field>
                        </v-col>
                        <v-col cols="12" md="3">
                            <div v-if="statsContrat" class="d-flex align-center bg-grey-lighten-4 pa-2 rounded-lg border" style="height: 40px;">
                                <div class="flex-grow-1 text-center border-right">
                                    <div style="font-size: 0.65rem; color: #666; font-weight: bold;">TOTAL PRÉVU</div>
                                    <div class="font-weight-black text-teal-darken-3" style="font-size: 0.9rem;">{{ statsContrat?.total || 0 }}</div>
                                </div>
                                <div class="flex-grow-1 text-center border-right">
                                    <div style="font-size: 0.65rem; color: #666; font-weight: bold;">DÉJÀ REÇU</div>
                                    <div class="font-weight-black text-blue-darken-2" style="font-size: 0.9rem;">{{ statsContrat?.deja_recu || 0 }}</div>
                                </div>
                                <div class="flex-grow-1 text-center">
                                    <div style="font-size: 0.65rem; color: #666; font-weight: bold;">RESTE</div>
                                    <div class="font-weight-black" :class="resteARecevoir <= 0 ? 'text-red-darken-2' : 'text-orange-darken-3'" style="font-size: 0.9rem;">{{ resteARecevoir }}</div>
                                </div>
                            </div>
                            <v-text-field v-else v-model.number="infosCommunes.quantite_totale_prevue" label="Qté Totale Prévue" type="number" variant="outlined" color="teal-darken-1" density="compact" hide-details="auto" class="text-caption font-weight-bold"></v-text-field>
                        </v-col>
                        <v-col cols="12" md="3">
                            <v-file-input :key="fileInputKey" :label="infosCommunes.ancien_scan_path ? 'Scan déjà présent (Cliquer pour changer)' : 'Scan document'" variant="outlined" :color="infosCommunes.ancien_scan_path ? 'blue-darken-1' : 'teal-darken-1'" density="compact" hide-details="auto" class="text-caption" prepend-inner-icon="mdi-paperclip" @change="handleFileUpload">
                                <template v-if="infosCommunes.ancien_scan_path" v-slot:append-inner>
                                    <v-icon color="success" icon="mdi-check-circle"></v-icon>
                                </template>
                            </v-file-input>
                        </v-col>
                        <v-col cols="12" md="2">
                            <v-text-field v-model="infosCommunes.date_livraison" type="date" label="Date" variant="outlined" color="teal-darken-1" density="compact" hide-details="auto" class="text-caption"></v-text-field>
                        </v-col>
                    </v-row>
                </v-card-text>
            </v-card>

            <v-row v-if="!statsContrat || resteARecevoir > 0 || panier.length > 0" class="flex-grow-1 mb-2" no-gutters style="min-height: 0">
                <v-col cols="12" md="7" class="pr-md-2 d-flex flex-column" style="height: 84vh">
                    <v-card elevation="0" class="rounded-xl shadow-card border overflow-hidden d-flex flex-column h-100">
                        <v-toolbar color="teal-darken-2" density="compact" flat>
                            <v-icon size="x-small" class="ml-4 mr-2">mdi-auto-fix</v-icon>
                            <v-toolbar-title class="text-caption font-weight-bold">SAISIE RAPIDE (GÉNÉRATION AUTO)</v-toolbar-title>
                        </v-toolbar>

                        <div class="pa-4 bg-white border-b">
                            <v-row dense>
                                <v-col cols="3">
                                    <v-text-field v-model.number="articleActuel.unite" type="number" label="Quantité" variant="outlined" color="teal" density="compact" hide-details class="font-weight-black"></v-text-field>
                                </v-col>
                                <v-col cols="3">
                                    <v-text-field v-model.number="articleActuel.nbr_pieces_global" type="number" label="Pièces / Unité" variant="outlined" color="teal" density="compact" hide-details :disabled="modeleSelectionne && modeleSelectionne.pieces_count === 0" :readonly="modeleSelectionne && modeleSelectionne.pieces_count === 0" />
                                    <div v-if="modeleSelectionne && modeleSelectionne.pieces_count === 0" class="text-caption text-grey mt-1">
                                        <v-icon size="x-small" class="mr-1">mdi-information</v-icon>
                                        Ce modèle n'a pas de pièces associées
                                    </div>
                                </v-col>
                                <v-col cols="6" style="position: relative;">
                                    <v-text-field v-model="articleActuel.designation" label="Désignation" variant="outlined" color="teal" density="compact" hide-details class="font-weight-bold" :loading="isLoadingModeles" @input="rechercherModeles" @focus="showModelesList = modelesExistants.length > 0" @blur="hideModelesList" />
                                    <!-- Liste des modèles existants -->
                                    <div v-if="showModelesList" class="autocomplete-list">
                                        <div v-for="modele in modelesExistants" :key="modele.id" class="autocomplete-item" @mousedown.prevent="selectionnerModeleExistant(modele)">
                                            <div class="d-flex align-center justify-space-between">
                                                <span class="text-caption font-weight-bold">{{ modele.nom }}</span>
                                                <div class="d-flex align-center">
                                                    <v-chip size="x-small" :color="modele.pieces_count > 0 ? 'teal-lighten-4' : 'grey-lighten-3'" variant="flat">
                                                        {{ modele.pieces_count > 0 ? modele.pieces_count + ' pièce(s)' : 'Sans pièces' }}
                                                    </v-chip>
                                                    <v-chip size="x-small" color="teal-lighten-4" class="ml-1" variant="flat">Existant</v-chip>
                                                </div>
                                            </div>
                                        </div>
                                        <div v-if="modelesExistants.length === 0 && !isLoadingModeles" class="autocomplete-item text-grey text-center">
                                            Aucun modèle trouvé
                                        </div>
                                    </div>
                                </v-col>
                                <v-col cols="12" class="mt-2">
                                    <v-autocomplete v-model="articleActuel.categorie_id" :items="categories" item-title="nom" item-value="id" label="Catégorie" variant="outlined" color="teal" density="compact" hide-details />
                                </v-col>
                            </v-row>

                            <v-expand-transition>
                                <div v-if="articleActuel.nbr_pieces_global > 0" class="mt-4 pa-3 bg-teal-lighten-5 rounded-lg border border-teal-lighten-4">
                                    <div class="text-caption font-weight-bold text-teal-darken-3 mb-2">NOMMER LES PIÈCES (CHARGEUR, SOURIS...) :</div>
                                    <v-row dense>
                                        <v-col v-for="(mod, mi) in articleActuel.pieces_modeles" :key="mi" cols="4">
                                            <v-text-field v-model="mod.nom" :label="'Nom Pièce ' + (mi + 1)" density="compact" variant="solo" hide-details flat bg-color="white" class="border rounded"></v-text-field>
                                        </v-col>
                                    </v-row>
                                </div>
                            </v-expand-transition>
                        </div>

                        <div class="flex-grow-1 overflow-y-auto bg-grey-lighten-4 pa-4">
                            <div v-if="articleActuel.unite > 0">
                                <v-alert v-if="surplusDetecte" type="error" variant="tonal" class="mb-4 text-caption">
                                    Attention : Vous dépassez la quantité prévue au contrat !
                                </v-alert>

                                <v-alert v-else color="teal-darken-1" icon="mdi-information" variant="tonal" class="text-caption">
                                    Vous allez générer <strong>{{ articleActuel.unite }}</strong> matériels.
                                    Le système créera automatiquement les numéros de série.
                                </v-alert>

                                <div class="mt-4 d-flex align-center justify-space-between bg-white pa-4 rounded-xl border">
                                    <div>
                                        <div class="text-caption text-grey">Reste après ajout :</div>
                                        <div class="text-h6 font-weight-black" :class="resteDynamique < 0 ? 'text-red' : 'text-teal'">
                                            {{ resteDynamique }} unités
                                        </div>
                                    </div>
                                    <v-icon size="large" :color="resteDynamique < 0 ? 'red' : 'teal'">mdi-calculator</v-icon>
                                </div>
                            </div>
                        </div>

                        <v-card-actions class="pa-3 border-t bg-white">
                            <v-btn block :disabled="aDesErreursDansLeLot || surplusDetecte" color="teal-darken-2" variant="elevated" @click="ajouterAuPanier">
                                VALIDER ET AJOUTER AU PANIER
                            </v-btn>
                        </v-card-actions>
                    </v-card>
                </v-col>

                <v-col cols="12" md="5" class="pl-md-2 d-flex flex-column mb-4" style="height: 82vh">
                    <v-card elevation="0" class="rounded-xl shadow-card border overflow-hidden d-flex flex-column h-100">
                        <v-toolbar color="teal-darken-2" density="compact" flat class="flex-shrink-0">
                            <v-icon size="small" class="ml-4 mr-2">mdi-clipboard-list-outline</v-icon>
                            <v-toolbar-title class="text-caption font-weight-bold">RÉCAPITULATIF</v-toolbar-title>
                            <v-chip size="x-small" class="mr-4 font-weight-black" color="white" variant="flat">{{ panier.length }} LOTS</v-chip>
                        </v-toolbar>

                        <div class="flex-grow-1 overflow-y-auto bg-grey-lighten-4">
                            <div v-for="(articles, nomCat) in panierGroupe" :key="nomCat">
                                <div class="pa-2 px-4 bg-teal-lighten-5 border-b text-caption font-weight-black text-teal-darken-4 uppercase">{{ nomCat }}</div>
                                <v-list class="pa-0 bg-transparent">
                                    <v-list-item v-for="(p, idx) in articles" :key="idx" class="bg-white border-b pa-3">
                                        <v-list-item-title class="text-caption font-weight-bold">{{ p.designation }}</v-list-item-title>
                                        <v-list-item-subtitle class="text-xxs">{{ p.total_unites }} Unités</v-list-item-subtitle>
                                        <template v-slot:append>
                                            <v-btn icon="mdi-delete-outline" size="x-small" color="red" variant="text" @click="retirerDuPanier(p)"></v-btn>
                                        </template>
                                    </v-list-item>
                                </v-list>
                            </div>
                        </div>

                        <div class="pa-3 bg-white border-t">
                            <v-expand-transition>
                                <div v-if="panier.length > 0 && !infosCommunes.fournisseur" class="mb-3">
                                    <v-alert type="warning" density="compact" variant="tonal" class="text-xxs">
                                        Veuillez renseigner le <strong>fournisseur</strong> avant de finaliser.
                                    </v-alert>
                                </div>
                            </v-expand-transition>

                            <v-expand-transition>
                                <div v-if="panier.length > 0 && !infosCommunes.numero_contrat" class="mb-3">
                                    <v-alert type="warning" density="compact" variant="tonal" class="text-xxs">
                                        Le <strong>numéro de contrat</strong> est obligatoire.
                                    </v-alert>
                                </div>
                            </v-expand-transition>

                            <v-btn block color="teal-darken-2" height="54" @click="submitFinal" :loading="form.processing" :disabled="panier.length === 0 || !infosCommunes.fournisseur || !infosCommunes.numero_contrat" class="rounded-xl font-weight-black shadow-teal">
                                FINALISER L'ENREGISTREMENT
                            </v-btn>
                        </div>
                    </v-card>
                </v-col>
            </v-row>

            <v-row v-else class="flex-grow-1 mb-2 align-center justify-center" no-gutters>
                <v-col cols="12" md="8" class="text-center">
                    <v-card variant="flat" class="pa-10 rounded-xl bg-grey-lighten-4 border-dashed border-grey-darken-1">
                        <v-icon size="80" color="grey-darken-1" class="mb-4">mdi-lock-outline</v-icon>
                        <h2 class="text-h5 font-weight-black text-grey-darken-3 mb-2">SAISIE IMPOSSIBLE</h2>
                        <p class="text-body-2 text-grey-darken-1 mb-6">Le contrat {{ infosCommunes.numero_contrat }} est totalement soldé ({{ statsContrat?.total }} reçus).</p>
                        <v-btn color="teal-darken-2" variant="text" @click="infosCommunes.numero_contrat = ''">Changer de contrat</v-btn>
                    </v-card>
                </v-col>
            </v-row>
        </v-container>
    </AuthenticatedLayout>
</template>

<style scoped>
    .autocomplete-list {
        position: absolute;
        top: 100%;
        left: 0;
        right: 0;
        background: white;
        border: 1px solid #ddd;
        border-radius: 8px;
        max-height: 250px;
        overflow-y: auto;
        z-index: 1000;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
    }

    .autocomplete-item {
        padding: 10px 12px;
        cursor: pointer;
        border-bottom: 1px solid #f0f0f0;
    }

    .autocomplete-item:hover {
        background-color: #e0f2f1;
    }

    .custom-compact-field :deep(.v-field__input) {
        min-height: 40px !important;
        padding-top: 0 !important;
        padding-bottom: 0 !important;
        font-size: 0.85rem;
    }

    .compact-input :deep(.v-field__input) {
        min-height: 32px !important;
        padding-top: 5px !important;
        padding-bottom: 5px !important;
        font-size: 0.8rem !important;
    }

    .custom-font {
        font-family: "Inter", sans-serif !important;
    }

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
        min-width: 200px !important;
        height: 45px !important;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .total-badge-teal {
        background: #f1f5f9 !important;
        border: 1px solid #e2e8f0 !important;
        box-shadow: none !important;
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

    .toggle-btn {
        min-width: 80px !important;
    }

    .custom-field :deep(.v-input__control) {
        height: 40px !important;
    }
</style>
