<script setup lang="ts">
    import { ref, watch, computed, onMounted } from "vue";
    import { Head, useForm } from "@inertiajs/vue3";
    import AuthentDemandeLayout from "@/Layouts/AuthentDemandeLayout.vue";

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

    interface OptionItem {
        value: "unite" | "pieces" | "complet";
        label: string;
        icon: string;
    }

    // Props
    const props = defineProps<{
        materiels: Materiel[];
        services: Service[];
    }>();

    // --- ETATS ---
    const snackbar = ref({ show: false, text: "", color: "" });
    const tentativeValidation = ref(false);
    const modeSelection = ref<"unite" | "pieces" | "complet">("unite");

    const form = useForm({
        numcomande: "",
        demandeur_nom: "",
        service_beneficiaire: "",
        date_demande: new Date().toISOString().substring(0, 10),
        items: [] as PanierItem[],
    });

    const saisieActuelle = ref({
        modele_id: null as number | null,
        materiel_id: null as number | null,
        numero_serie: "",
        pieces_selectionnees: [] as number[],
        nbredemande: 1,
        description: "",
    });

    // --- NOTIFICATIONS ---
    const showNotify = (text: string, color: string = "success") => {
        snackbar.value = { show: true, text, color };
    };

    // --- LOGIQUE DE DISPONIBILITÉ ---
    const idsMaterielsDansPanier = computed(() => form.items.map(item => item.materiel_id));

    const idsPiecesDansPanier = computed(() => {
        return form.items.reduce((acc, item) => [...acc, ...(item.pieces_ids || [])], [] as number[]);
    });

    // --- LISTE DES MODÈLES UNIQUES ---
    const modelesListe = computed(() => {
        const modelesMap = new Map();
        props.materiels.forEach(mat => {
            if (mat.modele && !modelesMap.has(mat.modele.id)) {
                modelesMap.set(mat.modele.id, {
                    id: mat.modele.id,
                    nom: mat.modele.nom
                });
            }
        });
        return Array.from(modelesMap.values());
    });

    // --- MATÉRIELS FILTRÉS PAR MODÈLE ---
    const materielsParModele = computed(() => {
        if (!saisieActuelle.value.modele_id) return [];
        return props.materiels.filter(mat =>
            mat.modele_materiel_id === saisieActuelle.value.modele_id
        );
    });

    // --- MATÉRIELS DISPONIBLES POUR LE MODÈLE SÉLECTIONNÉ ---
    const materielsDisponibles = computed(() => {
        const modelesMateriels = materielsParModele.value;

        return modelesMateriels.filter((mat) => {
            if (!mat) return false;

            // 1. Vérifier si l'unité est déjà dans le panier
            const uniteDansPanier = idsMaterielsDansPanier.value.includes(mat.id);
            if (uniteDansPanier) {
                return false; // Cacher le matériel si l'unité est déjà dans le panier
            }

            // 2. Analyser l'état des pièces
            const piecesDansPanier = mat.pieces?.filter(p => idsPiecesDansPanier.value.includes(p.id)) || [];
            const piecesLibres = mat.pieces?.filter(p => p.demande_id === null && !idsPiecesDansPanier.value.includes(p.id)) || [];

            // 3. Si toutes les pièces sont dans le panier, cacher le matériel
            if (mat.pieces?.length > 0 && piecesLibres.length === 0 && piecesDansPanier.length > 0) {
                return false; // Toutes les pièces sont déjà dans le panier
            }

            // 4. Vérifier la disponibilité de l'unité (hors panier)
            const uniteDisponible = !mat.demande_id && mat.etat !== 'Livré' && mat.etat !== 'Validé';

            // 5. Garder le matériel si :
            //    - L'unité est disponible (et pas dans panier)
            //    - OU il reste des pièces libres à ajouter
            return uniteDisponible || piecesLibres.length > 0;
        });
    });

    // --- VÉRIFICATION SI LE MODÈLE A ENCORE DES MATÉRIELS DISPONIBLES ---
    const modeleADesDisponibles = computed(() => {
        return materielsDisponibles.value.length > 0;
    });

    // --- MESSAGE POUR MODÈLE ÉPUISÉ ---
    const messageModeleEpuise = computed(() => {
        if (!saisieActuelle.value.modele_id) return "";
        if (!modeleADesDisponibles.value) {
            const modele = modelesListe.value.find(m => m.id === saisieActuelle.value.modele_id);
            return modele ? `⚠️ Le modèle ${modele.nom} n'a plus de matériel disponible` : "⚠️ Aucun matériel disponible pour ce modèle";
        }
        return "";
    });

    const materielSelectionne = computed(() => {
        if (!saisieActuelle.value.materiel_id) return null;
        return props.materiels?.find(m => m.id === saisieActuelle.value.materiel_id) || null;
    });

    // --- FONCTION STATUT MATÉRIEL ---
    const getStatutMateriel = (mat: Materiel | undefined) => {
        if (!mat) return { text: '?', color: 'grey', badge: '❓', detail: 'Matériel inconnu' };

        const totalPieces = mat.pieces?.length || 0;
        const piecesSortiesBDD = mat.pieces?.filter(p => p.demande_id !== null).length || 0;
        const piecesDansPanier = mat.pieces?.filter(p => idsPiecesDansPanier.value.includes(p.id)).length || 0;
        const piecesIndisponibles = piecesSortiesBDD + piecesDansPanier;
        const piecesRestantes = totalPieces - piecesIndisponibles;
        const uniteDansPanier = idsMaterielsDansPanier.value.includes(mat.id);
        const estTotalementSorti = mat.demande_id !== null || (totalPieces > 0 && piecesIndisponibles === totalPieces);

        if (uniteDansPanier && !mat.demande_id) {
            if (totalPieces === 0) return { text: 'AU PANIER', color: 'purple', badge: '🛒', detail: 'Unité dans le panier' };
            if (piecesRestantes === 0) return { text: 'COMPLET', color: 'red', badge: '🗄️', detail: 'Unité au panier, toutes pièces sorties' };
            return { text: `${piecesRestantes}/${totalPieces}`, color: 'purple', badge: '🛒', detail: `Unité au panier, ${piecesRestantes} pièce(s) restante(s)` };
        }

        if (estTotalementSorti) return { text: 'COMPLET', color: 'red', badge: '🗄️', detail: 'Toutes les pièces sont sorties' };
        if (piecesDansPanier > 0) return { text: `${piecesRestantes}/${totalPieces}`, color: 'purple', badge: '🛒', detail: `${piecesDansPanier} dans panier, ${piecesSortiesBDD} sorties` };
        if (piecesSortiesBDD > 0) return { text: `${piecesRestantes}/${totalPieces}`, color: 'orange', badge: '⚠️', detail: `${piecesSortiesBDD} pièce(s) sortie(s)` };
        if (totalPieces > 0) return { text: `${totalPieces}`, color: 'green', badge: '✓', detail: `${totalPieces} pièce(s) disponible(s)` };

        return { text: 'DISPO', color: 'green', badge: null, detail: 'Disponible (sans pièces)' };
    };

    // --- SUBTITLE ---
    const getSubtitle = (mat: Materiel | undefined) => {
        if (!mat) return '';

        const piecesDansPanier = mat.pieces?.filter(p => idsPiecesDansPanier.value.includes(p.id)).length || 0;
        const piecesSorties = mat.pieces?.filter(p => p.demande_id !== null).length || 0;
        const piecesLibres = mat.pieces?.filter(p => p.demande_id === null && !idsPiecesDansPanier.value.includes(p.id)).length || 0;

        let status = [];
        if (piecesDansPanier > 0) status.push(`${piecesDansPanier} au panier`);
        if (piecesSorties > 0) status.push(`${piecesSorties} sorties`);
        if (piecesLibres > 0) status.push(`${piecesLibres} libres`);

        const uniteDansPanier = idsMaterielsDansPanier.value.includes(mat.id);
        if (uniteDansPanier) status.push('unité au panier');

        const sn = mat.numero_serie ? `S/N: ${mat.numero_serie}` : 'Pas de S/N';

        return status.length > 0 ? `${sn} | ${status.join(' • ')}` : sn;
    };

    // --- CONDITIONS DE DISPONIBILITÉ ---
    const uniteEstDisponible = computed(() => {
        const mat = materielSelectionne.value;
        if (!mat) return false;
        return !mat.demande_id && mat.etat !== 'Livré' && mat.etat !== 'Validé';
    });

    const piecesDisponibles = computed(() => {
        const mat = materielSelectionne.value;
        if (!mat || !mat.pieces) return [];
        return mat.pieces.filter(p => !p.demande_id && !idsPiecesDansPanier.value.includes(p.id));
    });

    const estComplet = computed(() => {
        const mat = materielSelectionne.value;
        if (!mat) return false;
        const totalPieces = mat.pieces?.length || 0;
        return !mat.demande_id && mat.etat !== 'Livré' && mat.etat !== 'Validé' &&
            piecesDisponibles.value.length === totalPieces && totalPieces > 0;
    });

    // --- OPTIONS DYNAMIQUE CORRIGÉE AVEC TYPE ---
    const optionsDisponibles = computed<OptionItem[]>(() => {
        const options: OptionItem[] = [];
        const mat = materielSelectionne.value;

        if (!mat) return options;

        const uniteNonSortie = !mat.demande_id && mat.etat !== 'Livré' && mat.etat !== 'Validé';

        // Toujours proposer l'unité si elle n'est pas définitivement sortie
        if (uniteNonSortie) {
            options.push({ value: 'unite', label: 'UNITÉ SEULE', icon: 'mdi-package-variant' });
        }

        // Proposer les pièces s'il y en a de disponibles
        if (piecesDisponibles.value.length > 0) {
            options.push({ value: 'pieces', label: 'PIÈCES', icon: 'mdi-puzzle' });
        }

        // Proposer complet si toutes les conditions sont remplies
        if (uniteNonSortie && piecesDisponibles.value.length === (mat.pieces?.length || 0) && (mat.pieces?.length || 0) > 0) {
            options.push({ value: 'complet', label: 'COMPLET', icon: 'mdi-check-all' });
        }

        return options;
    });

    // --- WATCHERS CORRIGÉS ---
    watch(() => saisieActuelle.value.modele_id, (newId) => {
        // Reset du matériel sélectionné quand le modèle change
        saisieActuelle.value.materiel_id = null;
        // Reset du mode
        modeSelection.value = "unite";
    });

    watch(() => saisieActuelle.value.materiel_id, (newId) => {
        if (!newId) {
            modeSelection.value = "unite";
            return;
        }

        setTimeout(() => {
            const mat = materielSelectionne.value;
            if (!mat) return;

            saisieActuelle.value.pieces_selectionnees = [];
            saisieActuelle.value.numero_serie = mat.numero_serie || "";

            setTimeout(() => {
                const options = optionsDisponibles.value;
                const uniteDansPanier = idsMaterielsDansPanier.value.includes(mat.id);

                if (uniteDansPanier) {
                    // Si l'unité est dans le panier, on force le mode PIÈCES
                    modeSelection.value = 'pieces';
                } else if (options.length > 0) {
                    if (options.some(opt => opt.value === 'complet')) {
                        modeSelection.value = 'complet';
                    } else if (options.some(opt => opt.value === 'unite')) {
                        modeSelection.value = 'unite';
                    } else {
                        modeSelection.value = 'pieces';
                    }
                }
            }, 50);
        }, 50);
    });

    watch(modeSelection, (newMode) => {
        if (newMode === 'complet') {
            saisieActuelle.value.pieces_selectionnees = piecesDisponibles.value.map(p => p.id);
        } else if (newMode === 'unite') {
            saisieActuelle.value.pieces_selectionnees = [];
        }
    });

    // --- ACTIONS CORRIGÉES ---
    const messageDoublon = computed(() => {
        if (!materielSelectionne.value) return "";
        const uniteDansPanier = idsMaterielsDansPanier.value.includes(materielSelectionne.value.id);

        if (uniteDansPanier && (modeSelection.value === 'unite' || modeSelection.value === 'complet')) {
            return "⚠️ L'unité est déjà dans le panier";
        }
        return "";
    });

    const peutAjouter = computed(() => {
        if (!materielSelectionne.value) return false;

        const mat = materielSelectionne.value;
        const uniteDansPanier = idsMaterielsDansPanier.value.includes(mat.id);

        if (uniteDansPanier) {
            // Si l'unité est dans le panier, on ne peut qu'ajouter des pièces
            if (modeSelection.value === 'pieces') {
                return saisieActuelle.value.pieces_selectionnees.length > 0;
            }
            if (modeSelection.value === 'complet') {
                return true; // Permet de transformer en complet
            }
            return false;
        }

        // Comportement normal
        if (modeSelection.value === 'pieces') {
            return saisieActuelle.value.pieces_selectionnees.length > 0;
        }
        return true;
    });

    const ajouterAuPanier = () => {
        const mat = materielSelectionne.value;
        if (!mat) return;

        const itemExistant = form.items.find(item => item.materiel_id === mat.id);

        if (itemExistant) {
            // Matériel existant dans le panier
            if (modeSelection.value === 'unite' || modeSelection.value === 'complet') {
                // Transformer l'existant en complet
                itemExistant.mode_sortie = 'complet';
                itemExistant.nom_affiche = `🔹 [COMPLET] ${mat.modele?.nom || mat.nom}`;
                itemExistant.numero_serie = saisieActuelle.value.numero_serie || mat.numero_serie || "N/A";

                // Ajouter toutes les pièces manquantes
                const toutesLesPieces = mat.pieces || [];
                const piecesManquantes = toutesLesPieces.filter(p => !itemExistant.pieces_ids.includes(p.id));

                if (piecesManquantes.length > 0) {
                    itemExistant.pieces_ids.push(...piecesManquantes.map(p => p.id));
                    itemExistant.pieces_details.push(...piecesManquantes);
                }

                showNotify("Matériel transformé en COMPLET avec toutes ses pièces");
            } else {
                // Ajout de pièces
                const nouvellesPieces = mat.pieces.filter(p =>
                    saisieActuelle.value.pieces_selectionnees.includes(p.id) &&
                    !itemExistant.pieces_ids.includes(p.id)
                );

                if (nouvellesPieces.length > 0) {
                    itemExistant.pieces_ids.push(...nouvellesPieces.map(p => p.id));
                    itemExistant.pieces_details.push(...nouvellesPieces);
                    showNotify(`${nouvellesPieces.length} pièce(s) ajoutée(s) au matériel existant`);
                } else {
                    showNotify("Ces pièces sont déjà dans le panier", "info");
                }
            }
        } else {
            // Nouvel item
            let label = "";
            let ico = "";
            const pDetails = mat.pieces.filter(p => saisieActuelle.value.pieces_selectionnees.includes(p.id));

            if (modeSelection.value === 'complet') {
                label = `🔹 [COMPLET] ${mat.modele?.nom || mat.nom}`;
                ico = "mdi-check-all";
            } else if (modeSelection.value === 'unite') {
                label = `📦 [UNITÉ] ${mat.modele?.nom || mat.nom}`;
                ico = "mdi-package-variant";
            } else {
                label = `🔸 [PIÈCES] ${mat.modele?.nom || mat.nom}`;
                ico = "mdi-puzzle";
            }

            form.items.push({
                materiel_id: mat.id,
                materiel_nom: mat.modele?.nom || mat.nom,
                mode_sortie: modeSelection.value,
                pieces_ids: [...saisieActuelle.value.pieces_selectionnees],
                pieces_details: pDetails,
                nom_affiche: label,
                numero_serie: saisieActuelle.value.numero_serie || mat.numero_serie || "N/A",
                quantite: 1,
                description: saisieActuelle.value.description,
                icone_affiche: ico
            });

            showNotify("Article ajouté au panier");
        }

        // On garde le modèle sélectionné
        const modeleId = saisieActuelle.value.modele_id;
        resetSaisie();
        saisieActuelle.value.modele_id = modeleId; // Restaurer le modèle après reset
    };

    const resetSaisie = () => {
        saisieActuelle.value = {
            modele_id: saisieActuelle.value.modele_id, // On garde le modèle
            materiel_id: null,
            pieces_selectionnees: [],
            nbredemande: 1,
            description: "",
            numero_serie: ""
        };
        modeSelection.value = "unite";
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
            onError: () => showNotify("Erreur de validation", "error")
        });
    };

    const genererNumCommande = () => {
        form.numcomande = `CMD-${new Date().getFullYear()}-${Math.floor(1000 + Math.random() * 9000)}`;
    };

    onMounted(genererNumCommande);
</script>

<template>

    <Head title="Nouveau Bon de Sortie" />
    <AuthentDemandeLayout>
        <v-container fluid class="pa-2 pa-md-4 bg-grey-lighten-4 fill-height align-start">
            <v-snackbar v-model="snackbar.show" :color="snackbar.color" timeout="3000" location="top right" rounded="pill" class="mt-16">
                <v-icon :icon="snackbar.color === 'success' ? 'mdi-check-circle' : 'mdi-alert-circle'" class="mr-2"></v-icon>
                {{ snackbar.text }}
            </v-snackbar>

            <v-row dense class="w-100 container-compact">
                <!-- Colonne gauche : Saisie -->
                <v-col cols="12" md="4" class="pr-md-2 h-100">
                    <v-card flat border class="rounded-xl shadow-sm overflow-hidden d-flex flex-column h-100">
                        <v-toolbar color="teal-darken-1" density="compact" flat>
                            <v-icon icon="mdi-plus-box-outline" size="small" color="white" class="ml-4"></v-icon>
                            <v-toolbar-title class="text-caption font-weight-bold text-white">Saisie Matériel</v-toolbar-title>
                        </v-toolbar>

                        <v-card-text class="pa-4 bg-white flex-grow-1 overflow-y-auto">
                            <!-- Premier autocomplete : MODÈLES -->
                            <v-autocomplete v-model="saisieActuelle.modele_id" :items="modelesListe" item-title="nom" item-value="id" label="Sélectionner un modèle..." variant="outlined" density="compact" color="teal-darken-1" class="mb-3 custom-small-text" hide-details clearable>
                                <template v-slot:item="{ props, item }">
                                    <v-list-item v-bind="props" :title="item.raw.nom">
                                        <template v-slot:prepend>
                                            <v-icon color="teal-darken-2">mdi-chip</v-icon>
                                        </template>
                                    </v-list-item>
                                </template>
                            </v-autocomplete>

                            <!-- Message si modèle épuisé -->
                            <v-alert v-if="messageModeleEpuise" type="warning" variant="tonal" density="compact" class="mb-3" icon="mdi-alert-circle">
                                {{ messageModeleEpuise }}
                            </v-alert>

                            <!-- Deuxième autocomplete : MATÉRIELS du modèle sélectionné -->
                            <v-autocomplete v-model="saisieActuelle.materiel_id" :items="materielsDisponibles" :item-title="(item) => item.modele?.nom || item.nom" item-value="id" label="Sélectionner un matériel..." variant="outlined" density="compact" color="teal-darken-1" class="mb-3 custom-small-text" hide-details clearable :disabled="!saisieActuelle.modele_id || !modeleADesDisponibles">
                                <template v-slot:item="{ props, item }">
                                    <v-list-item v-bind="props" :title="item.raw.modele?.nom || item.raw.nom" :subtitle="getSubtitle(item.raw)">
                                        <template v-slot:prepend>
                                            <v-icon :color="getStatutMateriel(item.raw).color">
                                                {{ item.raw.demande_id ? 'mdi-archive-lock' : 'mdi-package' }}
                                            </v-icon>
                                        </template>

                                        <template v-slot:append>
                                            <v-tooltip location="top" :text="getStatutMateriel(item.raw).detail">
                                                <template v-slot:activator="{ props }">
                                                    <div class="d-flex align-center">
                                                        <span v-if="getStatutMateriel(item.raw).badge" class="mr-1" style="font-size: 12px;">
                                                            {{ getStatutMateriel(item.raw).badge }}
                                                        </span>
                                                        <v-chip v-bind="props" size="x-small" :color="getStatutMateriel(item.raw).color" variant="flat" class="font-weight-bold" style="min-width: 60px; justify-content: center;">
                                                            {{ getStatutMateriel(item.raw).text }}
                                                        </v-chip>
                                                    </div>
                                                </template>
                                            </v-tooltip>
                                        </template>
                                    </v-list-item>
                                </template>

                                <template v-slot:no-data>
                                    <v-list-item>
                                        <v-list-item-title class="text-center pa-4">
                                            <v-icon icon="mdi-package-variant" size="32" color="grey-lighten-2" class="mb-2"></v-icon>
                                            <div class="text-caption">Aucun matériel disponible pour ce modèle</div>
                                        </v-list-item-title>
                                    </v-list-item>
                                </template>
                            </v-autocomplete>

                            <v-expand-transition>
                                <div v-if="materielSelectionne">
                                    <div class="text-7px font-weight-bold mb-1 text-grey-darken-1">MODE DE SORTIE</div>

                                    <v-btn-toggle v-if="optionsDisponibles.length > 0" v-model="modeSelection" mandatory divided class="mb-3 w-100 border" color="teal-darken-1" variant="flat" density="compact" height="32">
                                        <v-btn v-for="opt in optionsDisponibles" :key="opt.value" :value="opt.value" class="flex-grow-1">
                                            <v-icon :icon="opt.icon" size="small" class="mr-1"></v-icon>
                                            <span style="font-size: 9px">{{ opt.label }}</span>
                                        </v-btn>
                                    </v-btn-toggle>

                                    <!-- Champ S/N Matériel -->
                                    <v-expand-transition>
                                        <div v-if="modeSelection === 'unite' || modeSelection === 'complet'" class="mb-3">
                                            <v-text-field v-model="saisieActuelle.numero_serie" label="CONFIRMER S/N MATÉRIEL" variant="outlined" density="compact" color="orange-darken-2" prepend-inner-icon="mdi-barcode-scan" hide-details class="custom-small-text font-weight-bold" :placeholder="materielSelectionne?.numero_serie || 'Saisir S/N...'"></v-text-field>
                                        </div>
                                    </v-expand-transition>

                                    <!-- Sélection des pièces -->
                                    <v-expand-transition>
                                        <div v-if="modeSelection === 'pieces' || modeSelection === 'complet'" class="mb-3 pa-2 border rounded-lg bg-grey-lighten-5">
                                            <div class="text-7px font-weight-bold mb-2 text-teal-darken-3">
                                                <v-icon icon="mdi-puzzle" size="x-small" class="mr-1"></v-icon>
                                                SÉLECTION & S/N DES PIÈCES :
                                            </div>
                                            <div style="max-height: 200px; overflow-y: auto; padding-right: 4px;">
                                                <div v-for="piece in piecesDisponibles" :key="piece.id" class="d-flex align-center mb-2" style="gap: 8px;">
                                                    <v-checkbox v-model="saisieActuelle.pieces_selectionnees" :value="piece.id" :label="piece.nom_piece" hide-details density="compact" color="teal" class="flex-grow-1 custom-small-text" style="min-width: 120px;"></v-checkbox>
                                                    <v-text-field v-if="saisieActuelle.pieces_selectionnees.includes(piece.id)" v-model="piece.numero_serie" label="S/N" variant="outlined" density="compact" hide-details class="custom-small-text" style="max-width: 120px;" placeholder="S/N..."></v-text-field>
                                                </div>
                                                <div v-if="piecesDisponibles.length === 0" class="text-caption text-grey text-center pa-2">
                                                    Aucune pièce disponible
                                                </div>
                                            </div>
                                        </div>
                                    </v-expand-transition>
                                </div>
                            </v-expand-transition>

                            <v-textarea v-model="saisieActuelle.description" label="Note / Observation" variant="outlined" rows="2" density="compact" color="teal-darken-1" hide-details class="custom-small-text mt-1"></v-textarea>
                        </v-card-text>

                        <v-divider></v-divider>
                        <v-card-actions class="pa-3 bg-white">
                            <v-btn color="teal-darken-1" block height="40" variant="flat" @click="ajouterAuPanier" :disabled="!peutAjouter" prepend-icon="mdi-plus" class="text-caption font-weight-bold rounded-pill">
                                AJOUTER AU PANIER
                            </v-btn>
                        </v-card-actions>
                    </v-card>
                </v-col>

                <!-- Colonne droite : Panier -->
                <v-col cols="12" md="8" class="pl-md-2 mt-2 mt-md-0 h-100">
                    <v-card flat border class="rounded-xl d-flex flex-column shadow-sm overflow-hidden h-100 bg-white">
                        <div class="pa-3 bg-teal-lighten-5 border-bottom">
                            <div class="d-flex align-center mb-2">
                                <v-icon icon="mdi-file-document-outline" size="small" color="teal-darken-2" class="mr-2"></v-icon>
                                <span class="text-caption font-weight-bold text-teal-darken-3">BON : {{ form.numcomande }}</span>
                                <v-spacer></v-spacer>
                                <v-chip color="teal-darken-1" size="x-small" variant="flat" class="font-weight-bold">{{ form.items.length }} ARTICLES</v-chip>
                            </div>

                            <v-row dense>
                                <v-col cols="12" md="4">
                                    <v-text-field v-model="form.demandeur_nom" label="RECEVEUR" variant="outlined" density="compact" bg-color="white" hide-details class="custom-small-text"></v-text-field>
                                </v-col>
                                <v-col cols="12" md="5">
                                    <v-autocomplete v-model="form.service_beneficiaire" :items="services" item-title="nom" item-value="nom" label="SERVICE" variant="outlined" density="compact" bg-color="white" hide-details class="custom-small-text"></v-autocomplete>
                                </v-col>
                                <v-col cols="12" md="3">
                                    <v-text-field v-model="form.date_demande" type="date" variant="outlined" density="compact" bg-color="white" hide-details class="custom-small-text"></v-text-field>
                                </v-col>
                            </v-row>
                        </div>

                        <div class="flex-grow-1 d-flex flex-column" style="height: 100%; min-height: 0;">
                            <div class="flex-grow-1 overflow-auto pa-3" style="height: calc(100% - 72px);">
                                <v-table density="compact" class="border rounded-lg" style="height: 100%;">
                                    <thead class="sticky-header">
                                        <tr class="bg-grey-lighten-4">
                                            <th class="text-7px font-weight-bold px-3">DÉSIGNATION</th>
                                            <th class="text-7px font-weight-bold text-center">MODE</th>
                                            <th class="text-right text-7px font-weight-bold px-3">ACTION</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr v-for="(item, index) in form.items" :key="index">
                                            <td class="text-7px py-2 px-3">
                                                <div class="font-weight-bold text-teal-darken-4">
                                                    <v-icon :icon="item.icone_affiche" size="x-small" class="mr-1"></v-icon>
                                                    {{ item.materiel_nom }}
                                                </div>
                                                <div class="d-flex flex-wrap gap-1 mt-1">
                                                    <v-chip v-for="p in item.pieces_details" :key="p.id" size="x-small" color="grey-lighten-3" variant="flat" style="font-size: 8px; height: auto;" class="pa-1">
                                                        <v-icon icon="mdi-puzzle" size="x-small" class="mr-1"></v-icon>
                                                        {{ p.nom_piece }}
                                                        <span v-if="p.numero_serie" class="ml-1 font-weight-bold">({{ p.numero_serie }})</span>
                                                    </v-chip>
                                                </div>
                                                <div v-if="item.numero_serie && item.numero_serie !== 'N/A'" class="text-caption font-weight-bold text-orange-darken-2 mt-1">
                                                    S/N: {{ item.numero_serie }}
                                                </div>
                                            </td>
                                            <td class="text-7px text-center">
                                                <v-chip size="x-small" :color="item.mode_sortie === 'complet' ? 'success' : (item.mode_sortie === 'unite' ? 'teal' : 'orange')" variant="tonal">
                                                    {{ item.mode_sortie === 'unite' ? 'UNITÉ' : item.mode_sortie === 'pieces' ? 'PIÈCES' : 'COMPLET' }}
                                                </v-chip>
                                            </td>
                                            <td class="text-right px-3">
                                                <v-btn icon="mdi-delete" size="x-small" variant="text" color="red" @click="retirerDuPanier(index)" title="Supprimer du panier"></v-btn>
                                            </td>
                                        </tr>
                                        <tr v-if="form.items.length === 0">
                                            <td colspan="3" class="text-center py-6 text-grey text-caption italic">
                                                <v-icon icon="mdi-cart-outline" size="large" class="mb-2"></v-icon>
                                                <br>Panier vide
                                            </td>
                                        </tr>
                                    </tbody>
                                </v-table>
                            </div>

                            <v-divider></v-divider>
                            <v-card-actions class="pa-3 bg-white">
                                <v-btn color="teal-darken-2" block height="40" variant="flat" @click="validerToutLePanier" :loading="form.processing" :disabled="form.items.length === 0" prepend-icon="mdi-check" class="text-caption font-weight-black rounded-pill">
                                    VALIDER LE BON
                                </v-btn>
                            </v-card-actions>
                        </div>
                    </v-card>
                </v-col>
            </v-row>
        </v-container>
    </AuthentDemandeLayout>
</template>

<style scoped>
    .container-compact {
        height: 62vh !important;
        min-height: 450px;
    }

    .h-100 {
        height: 100% !important;
    }

    .border-bottom {
        border-bottom: 1px solid #d1dbda;
    }

    .text-7px {
        font-size: 10px !important;
    }

    .italic {
        font-style: italic;
    }

    .custom-small-text :deep(input),
    .custom-small-text :deep(.v-label) {
        font-size: 0.75rem !important;
    }

    .flex-grow-1.overflow-auto {
        overflow-y: auto !important;
    }

    .sticky-header {
        position: sticky;
        top: 0;
        z-index: 1;
    }

    .sticky-header th {
        background-color: #f5f5f5 !important;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
    }

    .gap-1 {
        display: flex;
        gap: 8px;
    }

    ::-webkit-scrollbar {
        width: 6px;
        height: 6px;
    }

    ::-webkit-scrollbar-track {
        background: #f1f5f9;
        border-radius: 10px;
    }

    ::-webkit-scrollbar-thumb {
        background: #94a3b8;
        border-radius: 10px;
        transition: background 0.2s;
    }

    ::-webkit-scrollbar-thumb:hover {
        background: #64748b;
    }
</style>
