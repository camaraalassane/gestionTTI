<script setup lang="ts">
    import { ref, watch, computed, onMounted } from "vue";
    import { Head, useForm } from "@inertiajs/vue3";
    import AuthentDemandeLayout from "@/Layouts/AuthentDemandeLayout.vue";
    import axios from 'axios';

    // Déclaration de la fonction route
    declare function route(name?: string, params?: any): string;

    // --- INTERFACES ---
    interface Piece {
        id: number;
        nom_piece: string;
        statut: string;
        demande_id: number | null;
        numero_serie?: string;
    }

    interface ModeleMateriel {
        id: number;
        nom: string;
        total_materiels?: number;
    }

    interface Materiel {
        id: number;
        nom: string;
        numero_serie?: string;
        etat: string;
        statut: string;
        demande_id: number | null;
        service_id?: number | null;
        categorie?: { nom: string };
        pieces: Piece[];
        modele?: ModeleMateriel;
        modele_materiel_id: number;
    }

    interface Service {
        id: number;
        nom: string;
    }

    interface PanierItem {
        materiel_id: number;
        materiel_nom: string;
        mode_sortie: "unite" | "pieces" | "complet";
        pieces_ids: number[];
        pieces_details: Piece[];
        nom_affiche: string;
        numero_serie: string;
        quantite: number;
        description: string;
        icone_affiche: string;
    }

    interface MaterielTemp {
        materiel: Materiel;
        numero_serie: string;
        mode_sortie: "unite" | "pieces" | "complet";
        pieces_selectionnees: number[];
        pieces_serie: Record<number, string>;
        statut: {
            text: string;
            color: string;
            badge: string | null;
            detail: string;
        };
    }

    interface OptionItem {
        value: "unite" | "pieces" | "complet";
        label: string;
        icon: string;
    }

    // Props
    const props = defineProps<{
        services: Service[];
    }>();

    // --- ETATS ---
    const snackbar = ref({ show: false, text: "", color: "" });
    const tentativeValidation = ref(false);
    const loadingModeles = ref(false);
    const loadingMateriels = ref(false);

    const form = useForm({
        numcomande: "",
        demandeur_nom: "",
        service_beneficiaire: "",
        date_demande: new Date().toISOString().substring(0, 10),
        items: [] as PanierItem[],
    });

    const saisieActuelle = ref({
        modele_id: null as number | null,
        quantite: 1,
        description: "",
    });

    // Liste des modèles (chargée dynamiquement)
    const modelesListe = ref<ModeleMateriel[]>([]);
    const searchModele = ref('');

    // Liste des matériels disponibles pour le modèle sélectionné (SANS LIMITE)
    const materielsDisponibles = ref<Materiel[]>([]);

    // Liste des matériels temporaires à configurer
    const materielsTemp = ref<MaterielTemp[]>([]);

    // --- NOTIFICATIONS ---
    const showNotify = (text: string, color: string = "success") => {
        snackbar.value = { show: true, text, color };
    };

    // --- LOGIQUE DE DISPONIBILITÉ ---
    const idsMaterielsDansPanier = computed(() => form.items.map(item => item.materiel_id));

    const idsPiecesDansPanier = computed(() => {
        return form.items.reduce((acc, item) => [...acc, ...(item.pieces_ids || [])], [] as number[]);
    });

    // --- VÉRIFIER SI UN MATÉRIEL EST SORTI ---
    const estMaterielSorti = (mat: Materiel) => {
        return (mat.demande_id !== null && mat.demande_id !== 0) ||
            (mat.service_id !== null && mat.service_id !== 0);
    };

    // --- FONCTION STATUT MATÉRIEL ---
    const getStatutMateriel = (mat: Materiel) => {
        if (!mat) return { text: '?', color: 'grey', badge: '❓', detail: 'Matériel inconnu' };

        const totalPieces = mat.pieces?.length || 0;
        const piecesSortiesBDD = mat.pieces?.filter(p => p.demande_id !== null && p.demande_id !== 0).length || 0;
        const piecesDansPanier = mat.pieces?.filter(p => idsPiecesDansPanier.value.includes(p.id)).length || 0;
        const piecesIndisponibles = piecesSortiesBDD + piecesDansPanier;
        const piecesRestantes = totalPieces - piecesIndisponibles;
        const uniteDansPanier = idsMaterielsDansPanier.value.includes(mat.id);
        const estTotalementSorti = estMaterielSorti(mat) || (totalPieces > 0 && piecesIndisponibles === totalPieces);

        if (estTotalementSorti) {
            return { text: 'SORTI', color: 'red', badge: '🗄️', detail: 'Matériel déjà sorti' };
        }

        if (uniteDansPanier && !estMaterielSorti(mat)) {
            if (totalPieces === 0) return { text: 'AU PANIER', color: 'purple', badge: '🛒', detail: 'Unité dans le panier' };
            if (piecesRestantes === 0) return { text: 'COMPLET', color: 'red', badge: '🗄️', detail: 'Unité au panier, toutes pièces sorties' };
            return { text: `${piecesRestantes}/${totalPieces}`, color: 'purple', badge: '🛒', detail: `Unité au panier, ${piecesRestantes} pièce(s) restante(s)` };
        }

        if (piecesDansPanier > 0) {
            return { text: `${piecesRestantes}/${totalPieces}`, color: 'purple', badge: '🛒', detail: `${piecesDansPanier} dans panier, ${piecesSortiesBDD} sorties` };
        }

        if (piecesSortiesBDD > 0) {
            return { text: `${piecesRestantes}/${totalPieces}`, color: 'orange', badge: '⚠️', detail: `${piecesSortiesBDD} pièce(s) sortie(s)` };
        }

        if (totalPieces > 0) {
            return { text: `${totalPieces}`, color: 'green', badge: '✓', detail: `${totalPieces} pièce(s) disponible(s)` };
        }

        return { text: 'DISPO', color: 'green', badge: '', detail: 'Disponible (sans pièces)' };
    };

    // --- CHARGEMENT DES MODÈLES (AJAX) ---
    const loadModeles = async () => {
        loadingModeles.value = true;
        try {
            const response = await axios.get(route('api.modeles.search'), {
                params: { search: searchModele.value }
            });
            modelesListe.value = response.data;
        } catch (error) {
            console.error('Erreur chargement modèles:', error);
            showNotify("Erreur de chargement des modèles", "error");
        } finally {
            loadingModeles.value = false;
        }
    };

    // Debounce pour la recherche de modèles
    let searchTimeout: ReturnType<typeof setTimeout>;
    watch(searchModele, () => {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(() => {
            loadModeles();
        }, 300);
    });

    // --- CHARGEMENT DES MATÉRIELS D'UN MODÈLE (AJAX) - SANS LIMITE ---
    const loadMaterielsForModele = async () => {
        if (!saisieActuelle.value.modele_id) {
            materielsDisponibles.value = [];
            return;
        }

        loadingMateriels.value = true;
        try {
            // Appel API sans limite
            const response = await axios.get(route('api.materiels.by-modele', {
                modele_id: saisieActuelle.value.modele_id
            }));
            materielsDisponibles.value = response.data;
            console.log(`📦 ${materielsDisponibles.value.length} matériels chargés pour ce modèle`);
        } catch (error) {
            console.error('Erreur chargement matériels:', error);
            showNotify("Erreur de chargement des matériels", "error");
        } finally {
            loadingMateriels.value = false;
        }
    };

    // --- NOMBRE MAX DE MATÉRIELS DISPONIBLES ---
    const maxDisponibles = computed(() => {
        return materielsDisponibles.value.length;
    });

    // Vérifier si la quantité est valide
    const quantiteValide = computed(() => {
        return saisieActuelle.value.quantite > 0 &&
            saisieActuelle.value.quantite <= maxDisponibles.value;
    });

    // --- FONCTIONS POUR LES OPTIONS DE SORTIE ---
    const getPiecesDisponiblesPourMateriel = (materiel: Materiel) => {
        if (!materiel.pieces) return [];
        return materiel.pieces.filter(p =>
            (p.demande_id === null || p.demande_id === 0) &&
            !idsPiecesDansPanier.value.includes(p.id)
        );
    };

    const getOptionsDisponiblesPourMateriel = (materiel: Materiel): OptionItem[] => {
        const options: OptionItem[] = [];

        const uniteNonSortie = !estMaterielSorti(materiel);
        const piecesDispo = getPiecesDisponiblesPourMateriel(materiel);
        const totalPieces = materiel.pieces?.length || 0;

        if (uniteNonSortie) {
            options.push({ value: 'unite', label: 'UNITÉ', icon: 'mdi-package-variant' });
        }

        if (piecesDispo.length > 0) {
            options.push({ value: 'pieces', label: 'PIÈCES', icon: 'mdi-puzzle' });
        }

        if (uniteNonSortie && piecesDispo.length === totalPieces && totalPieces > 0) {
            options.push({ value: 'complet', label: 'COMPLET', icon: 'mdi-check-all' });
        }

        return options;
    };

    // --- GÉNÉRER LA LISTE DES MATÉRIELS À CONFIGURER ---
    const genererListeMateriels = () => {
        if (!quantiteValide.value) {
            showNotify(`Quantité invalide. Maximum: ${maxDisponibles.value}`, "error");
            return;
        }

        const materielsChoisis = materielsDisponibles.value.slice(0, saisieActuelle.value.quantite);

        materielsTemp.value = materielsChoisis.map(mat => {
            const options = getOptionsDisponiblesPourMateriel(mat);
            return {
                materiel: mat,
                numero_serie: mat.numero_serie || "",
                mode_sortie: options.length > 0 ? options[0].value : "unite",
                pieces_selectionnees: [],
                pieces_serie: {},
                statut: getStatutMateriel(mat)
            };
        });

        showNotify(`${materielsChoisis.length} matériel(s) à configurer`, "info");
    };

    // --- QUAND LE MODÈLE CHANGE ---
    watch(() => saisieActuelle.value.modele_id, () => {
        saisieActuelle.value.quantite = 1;
        materielsTemp.value = [];
        loadMaterielsForModele();
    });

    // --- QUAND LE MODE CHANGE POUR UN MATÉRIEL ---
    const onModeChange = (tempItem: MaterielTemp, mode: string) => {
        tempItem.mode_sortie = mode as "unite" | "pieces" | "complet";

        if (mode === 'complet') {
            const piecesDispo = getPiecesDisponiblesPourMateriel(tempItem.materiel);
            tempItem.pieces_selectionnees = piecesDispo.map(p => p.id);
            piecesDispo.forEach(piece => {
                if (!tempItem.pieces_serie[piece.id]) {
                    tempItem.pieces_serie[piece.id] = piece.numero_serie || "";
                }
            });
        } else if (mode === 'unite') {
            tempItem.pieces_selectionnees = [];
        }
    };

    // --- AJOUTER TOUS LES MATÉRIELS CONFIGURÉS AU PANIER ---
    const ajouterTousAuPanier = () => {
        if (materielsTemp.value.length === 0) {
            showNotify("Aucun matériel à ajouter", "error");
            return;
        }

        let ajoutes = 0;

        for (const temp of materielsTemp.value) {
            const mat = temp.materiel;
            const options = getOptionsDisponiblesPourMateriel(mat);
            const modeDisponible = options.some(opt => opt.value === temp.mode_sortie);

            if (!modeDisponible) {
                showNotify(`Mode ${temp.mode_sortie} non disponible pour ${mat.modele?.nom || mat.nom}`, "warning");
                continue;
            }

            let piecesSelectionnees = [...temp.pieces_selectionnees];
            let piecesSerie = { ...temp.pieces_serie };

            if (temp.mode_sortie === 'complet') {
                const toutesPieces = getPiecesDisponiblesPourMateriel(mat);
                piecesSelectionnees = toutesPieces.map(p => p.id);
                toutesPieces.forEach(piece => {
                    if (!piecesSerie[piece.id]) {
                        piecesSerie[piece.id] = piece.numero_serie || "";
                    }
                });
            }

            const piecesDetails = mat.pieces?.filter(p =>
                piecesSelectionnees.includes(p.id)
            ).map(p => ({
                ...p,
                numero_serie: piecesSerie[p.id] || p.numero_serie
            })) || [];

            let label = "";
            let ico = "";

            if (temp.mode_sortie === 'complet') {
                label = `🔹 [COMPLET] ${mat.modele?.nom || mat.nom}`;
                ico = "mdi-check-all";
            } else if (temp.mode_sortie === 'unite') {
                label = `📦 [UNITÉ] ${mat.modele?.nom || mat.nom}`;
                ico = "mdi-package-variant";
            } else {
                label = `🔸 [PIÈCES] ${mat.modele?.nom || mat.nom}`;
                ico = "mdi-puzzle";
            }

            form.items.push({
                materiel_id: mat.id,
                materiel_nom: mat.modele?.nom || mat.nom,
                mode_sortie: temp.mode_sortie,
                pieces_ids: piecesSelectionnees,
                pieces_details: piecesDetails,
                nom_affiche: label,
                numero_serie: temp.numero_serie || mat.numero_serie || "N/A",
                quantite: 1,
                description: saisieActuelle.value.description,
                icone_affiche: ico
            });

            ajoutes++;
        }

        if (ajoutes > 0) {
            showNotify(`${ajoutes} matériel(s) ajouté(s) au panier`);
        } else {
            showNotify("Aucun matériel n'a pu être ajouté", "error");
        }

        const modeleId = saisieActuelle.value.modele_id;
        saisieActuelle.value = {
            modele_id: modeleId,
            quantite: 1,
            description: "",
        };
        materielsTemp.value = [];
    };

    const retirerDuPanier = (index: number) => {
        form.items.splice(index, 1);
        showNotify("Article retiré", "info");
    };

    const validerToutLePanier = () => {
        tentativeValidation.value = true;
        if (form.items.length === 0 || !form.demandeur_nom || !form.service_beneficiaire) {
            showNotify("Veuillez remplir les champs obligatoires", "error");
            return;
        }

        form.post(route("demandes.store_group"), {
            onSuccess: () => {
                form.reset();
                genererNumCommande();
                tentativeValidation.value = false;
                showNotify("Bon de sortie enregistré !");
            },
            onError: (errors) => {
                console.error("Erreurs:", errors);
                showNotify("Erreur de validation", "error");
            }
        });
    };

    const genererNumCommande = () => {
        form.numcomande = `CMD-${new Date().getFullYear()}-${Math.floor(1000 + Math.random() * 9000)}`;
    };

    onMounted(() => {
        genererNumCommande();
        loadModeles();
    });
</script>

<!-- TEMPLATE IDENTIQUE (garde ton template actuel) -->

<template>

    <Head title="Nouveau Bon de Sortie" />
    <AuthentDemandeLayout>
        <v-container fluid class="pa-2 pa-md-4 bg-grey-lighten-4 fill-height align-start">
            <v-snackbar v-model="snackbar.show" :color="snackbar.color" timeout="3000" location="top right" rounded="pill" class="mt-16">
                <v-icon :icon="snackbar.color === 'success' ? 'mdi-check-circle' : 'mdi-alert-circle'" class="mr-2"></v-icon>
                {{ snackbar.text }}
            </v-snackbar>

            <v-row dense class="w-100 container-compact">
                <v-col cols="12" md="5" class="pr-md-2 h-100">
                    <v-card flat border class="rounded-xl shadow-sm overflow-hidden d-flex flex-column h-100">
                        <v-toolbar color="teal-darken-1" density="compact" flat>
                            <v-icon icon="mdi-plus-box-outline" size="small" color="white" class="ml-4"></v-icon>
                            <v-toolbar-title class="text-caption font-weight-bold text-white">Saisie Matériel</v-toolbar-title>
                        </v-toolbar>

                        <v-card-text class="pa-4 bg-white flex-grow-1 overflow-y-auto">
                            <!-- SELECT MODÈLE AVEC RECHERCHE AJAX -->
                            <v-autocomplete v-model="saisieActuelle.modele_id" :items="modelesListe" :loading="loadingModeles" :search-input.sync="searchModele" item-title="nom" item-value="id" label="Sélectionner un modèle..." variant="outlined" density="compact" color="teal-darken-1" class="mb-3 custom-small-text" hide-details clearable :filter="() => true">
                                <template v-slot:item="{ props, item }">
                                    <v-list-item v-bind="props">
                                        <template v-slot:prepend>
                                            <v-icon color="teal-darken-2">mdi-chip</v-icon>
                                        </template>
                                        <template v-slot:title>
                                            <span class="text-caption">{{ item.raw.nom }}</span>
                                        </template>
                                        <template v-slot:subtitle>
                                            <span class="text-7px text-grey">Stock: {{ item.raw.total_materiels || 0 }}</span>
                                        </template>
                                    </v-list-item>
                                </template>
                                <template v-slot:no-data>
                                    <v-list-item>
                                        <v-list-item-title class="text-center pa-4">
                                            <v-icon icon="mdi-package-variant" size="32" color="grey-lighten-2" class="mb-2"></v-icon>
                                            <div class="text-caption">Aucun modèle trouvé</div>
                                        </v-list-item-title>
                                    </v-list-item>
                                </template>
                            </v-autocomplete>

                            <!-- STATS DU MODÈLE -->
                            <div v-if="saisieActuelle.modele_id && !loadingMateriels" class="mb-3">
                                <v-card variant="tonal" color="teal-lighten-5" class="pa-2">
                                    <div class="d-flex justify-space-between align-center">
                                        <div>
                                            <div class="text-caption font-weight-bold">📊 ÉTAT DU STOCK</div>
                                            <div class="text-7px">
                                                Disponibles: <span class="text-green-darken-2 font-weight-bold">{{ maxDisponibles }}</span>
                                            </div>
                                        </div>
                                    </div>
                                </v-card>
                            </div>

                            <!-- CHARGEMENT DES MATÉRIELS -->
                            <div v-if="loadingMateriels" class="text-center pa-4">
                                <v-progress-circular indeterminate color="teal" size="32"></v-progress-circular>
                                <div class="text-caption mt-2">Chargement des matériels...</div>
                            </div>

                            <!-- MESSAGE SI AUCUN MATÉRIEL DISPONIBLE -->
                            <v-alert v-if="saisieActuelle.modele_id && !loadingMateriels && maxDisponibles === 0" type="warning" variant="tonal" density="compact" class="mb-3" icon="mdi-alert-circle">
                                ⚠️ Aucun matériel disponible pour ce modèle
                            </v-alert>

                            <!-- CHAMP QUANTITÉ -->
                            <div v-if="saisieActuelle.modele_id && !loadingMateriels && maxDisponibles > 0" class="mb-3">
                                <v-text-field v-model.number="saisieActuelle.quantite" type="number" label="Quantité à sortir" variant="outlined" density="compact" color="teal-darken-1" :min="1" hide-details class="custom-small-text" prepend-inner-icon="mdi-counter">
                                    <template v-slot:append>
                                        <v-chip size="x-small" color="teal-lighten-4" class="font-weight-bold">
                                            Max: {{ maxDisponibles }}
                                        </v-chip>
                                    </template>
                                </v-text-field>
                                <div v-if="saisieActuelle.quantite > maxDisponibles" class="text-caption text-red mt-1">
                                    ⚠️ La quantité dépasse le nombre disponible ({{ maxDisponibles }})
                                </div>
                            </div>

                            <!-- BOUTON GÉNÉRER -->
                            <v-btn v-if="saisieActuelle.modele_id && !loadingMateriels && maxDisponibles > 0 && materielsTemp.length === 0" color="teal-darken-1" variant="tonal" block size="small" class="mb-3" @click="genererListeMateriels" :disabled="saisieActuelle.quantite > maxDisponibles || saisieActuelle.quantite < 1">
                                <v-icon icon="mdi-refresh" size="small" class="mr-2"></v-icon>
                                GÉNÉRER LA LISTE ({{ saisieActuelle.quantite }} matériel(s))
                            </v-btn>

                            <!-- LISTE DES MATÉRIELS À CONFIGURER -->
                            <div v-if="materielsTemp.length > 0" class="mt-2" style="max-height: 450px; overflow-y: auto;">
                                <div v-for="(item, idx) in materielsTemp" :key="item.materiel.id" class="mb-4 pa-2 border rounded-lg bg-grey-lighten-5">
                                    <div class="d-flex align-center justify-space-between mb-2">
                                        <div class="font-weight-bold text-teal-darken-3" style="font-size: 11px;">
                                            <v-icon icon="mdi-package" size="x-small" class="mr-1"></v-icon>
                                            #{{ idx + 1 }} - {{ item.materiel.modele?.nom || item.materiel.nom }}
                                        </div>
                                        <v-chip :color="item.statut.color" size="x-small" variant="flat" class="font-weight-bold" style="font-size: 9px;">
                                            <span v-if="item.statut.badge" class="mr-1">{{ item.statut.badge }}</span>
                                            {{ item.statut.text }}
                                        </v-chip>
                                    </div>

                                    <v-text-field v-model="item.numero_serie" label="S/N Matériel" variant="outlined" density="compact" class="mb-2 custom-small-text" prepend-inner-icon="mdi-barcode" :placeholder="item.materiel.numero_serie || 'Saisir S/N...'" hide-details />

                                    <div class="mb-2">
                                        <div class="text-7px font-weight-bold mb-1 text-grey-darken-1">Mode de sortie :</div>
                                        <v-btn-toggle v-model="item.mode_sortie" mandatory divided class="w-100" color="teal-darken-1" variant="flat" density="compact" size="x-small">
                                            <v-btn v-for="opt in getOptionsDisponiblesPourMateriel(item.materiel)" :key="opt.value" :value="opt.value" class="flex-grow-1" @click="onModeChange(item, opt.value)" style="min-height: 28px;">
                                                <v-icon :icon="opt.icon" size="x-small" class="mr-1"></v-icon>
                                                <span style="font-size: 8px;">{{ opt.label }}</span>
                                            </v-btn>
                                        </v-btn-toggle>
                                    </div>

                                    <!-- Sélection des pièces -->
                                    <div v-if="item.mode_sortie === 'pieces' || item.mode_sortie === 'complet'">
                                        <div class="text-7px font-weight-bold mb-1 text-teal-darken-3">
                                            <v-icon icon="mdi-puzzle" size="x-small" class="mr-1"></v-icon>
                                            Pièces disponibles :
                                        </div>
                                        <div class="ml-1" style="max-height: 150px; overflow-y: auto;">
                                            <div v-for="piece in getPiecesDisponiblesPourMateriel(item.materiel)" :key="piece.id" class="d-flex align-center mb-2" style="gap: 8px;">
                                                <v-checkbox v-model="item.pieces_selectionnees" :value="piece.id" :label="piece.nom_piece" hide-details density="compact" color="teal" class="flex-grow-1 custom-small-text" :disabled="item.mode_sortie === 'complet'" style="font-size: 9px;" />
                                                <v-text-field v-model="item.pieces_serie[piece.id]" label="S/N Pièce" variant="outlined" density="compact" hide-details class="custom-small-text" style="max-width: 130px; font-size: 9px;" placeholder="S/N..." :disabled="item.mode_sortie === 'complet' && !item.pieces_selectionnees.includes(piece.id)" />
                                            </div>
                                            <div v-if="getPiecesDisponiblesPourMateriel(item.materiel).length === 0" class="text-caption text-grey text-center pa-2">
                                                Aucune pièce disponible
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <v-textarea v-model="saisieActuelle.description" label="Note" variant="outlined" rows="2" density="compact" color="teal-darken-1" hide-details class="custom-small-text mt-2" />
                        </v-card-text>

                        <v-divider />
                        <v-card-actions class="pa-2 bg-white">
                            <v-btn v-if="materielsTemp.length === 0" color="teal-darken-1" block size="small" variant="flat" @click="genererListeMateriels" :disabled="saisieActuelle.quantite > maxDisponibles || saisieActuelle.quantite < 1" prepend-icon="mdi-plus" class="text-caption font-weight-bold rounded-pill">
                                GÉNÉRER
                            </v-btn>
                            <v-btn v-else color="teal-darken-1" block size="small" variant="flat" @click="ajouterTousAuPanier" prepend-icon="mdi-cart-plus" class="text-caption font-weight-bold rounded-pill">
                                AJOUTER ({{ materielsTemp.length }})
                            </v-btn>
                        </v-card-actions>
                    </v-card>
                </v-col>

                <!-- Panier (inchangé) -->
                <v-col cols="12" md="7" class="pl-md-2 mt-2 mt-md-0 h-100">
                    <!-- ... le reste du panier reste identique ... -->
                    <v-card flat border class="rounded-xl d-flex flex-column shadow-sm overflow-hidden h-100 bg-white">
                        <div class="pa-2 bg-teal-lighten-5 border-bottom">
                            <div class="d-flex align-center mb-2">
                                <v-icon icon="mdi-file-document-outline" size="small" color="teal-darken-2" class="mr-2"></v-icon>
                                <span class="text-caption font-weight-bold text-teal-darken-3">BON : {{ form.numcomande }}</span>
                                <v-spacer />
                                <v-chip color="teal-darken-1" size="x-small" variant="flat" class="font-weight-bold">{{ form.items.length }} ARTICLES</v-chip>
                            </div>

                            <v-row dense>
                                <v-col cols="12" md="5">
                                    <v-text-field v-model="form.demandeur_nom" label="RECEVEUR *" variant="outlined" density="compact" bg-color="white" hide-details class="custom-small-text" />
                                </v-col>
                                <v-col cols="12" md="5">
                                    <v-autocomplete v-model="form.service_beneficiaire" :items="services" item-title="nom" item-value="nom" label="SERVICE *" variant="outlined" density="compact" bg-color="white" hide-details class="custom-small-text" />
                                </v-col>
                                <v-col cols="12" md="2">
                                    <v-text-field v-model="form.date_demande" type="date" variant="outlined" density="compact" bg-color="white" hide-details class="custom-small-text" />
                                </v-col>
                            </v-row>
                        </div>

                        <div class="flex-grow-1 overflow-auto pa-2">
                            <v-table density="compact" class="border rounded-lg">
                                <thead class="sticky-header">
                                    <tr class="bg-grey-lighten-4">
                                        <th class="text-7px font-weight-bold px-2">DÉSIGNATION</th>
                                        <th class="text-7px font-weight-bold text-center">MODE</th>
                                        <th class="text-7px font-weight-bold text-center">S/N</th>
                                        <th class="text-right text-7px font-weight-bold px-2">ACTION</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="(item, index) in form.items" :key="index">
                                        <td class="text-7px py-1 px-2">
                                            <div class="font-weight-bold text-teal-darken-4">
                                                <v-icon :icon="item.icone_affiche" size="x-small" class="mr-1" />
                                                {{ item.materiel_nom }}
                                            </div>
                                            <div class="d-flex flex-wrap gap-1 mt-1">
                                                <v-chip v-for="p in item.pieces_details" :key="p.id" size="x-small" color="grey-lighten-3" variant="flat" style="font-size: 7px;">
                                                    <v-icon icon="mdi-puzzle" size="x-small" class="mr-1" />
                                                    {{ p.nom_piece }}
                                                    <span v-if="p.numero_serie" class="ml-1">({{ p.numero_serie }})</span>
                                                </v-chip>
                                            </div>
                                            <div v-if="item.description" class="text-grey text-7px mt-1 italic">
                                                {{ item.description }}
                                            </div>
                                        </td>
                                        <td class="text-7px text-center">
                                            <v-chip size="x-small" :color="item.mode_sortie === 'complet' ? 'success' : (item.mode_sortie === 'unite' ? 'teal' : 'orange')" variant="tonal" style="font-size: 8px;">
                                                {{ item.mode_sortie === 'unite' ? 'UNITÉ' : item.mode_sortie === 'pieces' ? 'PIÈCES' : 'COMPLET' }}
                                            </v-chip>
                                        </td>
                                        <td class="text-7px text-center">
                                            <v-chip size="x-small" color="orange-lighten-4" variant="flat" class="font-mono" style="font-size: 8px;">
                                                {{ item.numero_serie }}
                                            </v-chip>
                                        </td>
                                        <td class="text-right px-2">
                                            <v-btn icon="mdi-delete" size="x-small" variant="text" color="red" @click="retirerDuPanier(index)" />
                                        </td>
                                    </tr>
                                    <tr v-if="form.items.length === 0">
                                        <td colspan="4" class="text-center py-4 text-grey text-caption italic">
                                            <v-icon icon="mdi-cart-outline" size="small" class="mb-1" />
                                            <br>Panier vide
                                        </td>
                                    </tr>
                                </tbody>
                            </v-table>
                        </div>

                        <v-divider />
                        <v-card-actions class="pa-2 bg-white">
                            <v-btn color="teal-darken-2" block size="small" variant="flat" @click="validerToutLePanier" :loading="form.processing" :disabled="form.items.length === 0" prepend-icon="mdi-check" class="text-caption font-weight-black rounded-pill">
                                VALIDER
                            </v-btn>
                        </v-card-actions>
                    </v-card>
                </v-col>
            </v-row>
        </v-container>
    </AuthentDemandeLayout>
</template>

<style scoped>
    .container-compact {
        height: 70vh !important;
        min-height: 500px;
    }

    .h-100 {
        height: 100% !important;
    }

    .border-bottom {
        border-bottom: 1px solid #d1dbda;
    }

    .text-7px {
        font-size: 9px !important;
    }

    .custom-small-text :deep(input),
    .custom-small-text :deep(.v-label) {
        font-size: 0.7rem !important;
    }

    .sticky-header {
        position: sticky;
        top: 0;
        z-index: 1;
    }

    .sticky-header th {
        background-color: #f5f5f5 !important;
    }

    .gap-1 {
        display: flex;
        gap: 4px;
        flex-wrap: wrap;
    }

    .font-mono {
        font-family: monospace;
    }

    ::-webkit-scrollbar {
        width: 4px;
        height: 4px;
    }

    ::-webkit-scrollbar-track {
        background: #f1f5f9;
        border-radius: 10px;
    }

    ::-webkit-scrollbar-thumb {
        background: #94a3b8;
        border-radius: 10px;
    }
</style>