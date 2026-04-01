<script setup>
    import { ref, computed, onMounted } from "vue";
    import { Head, Link, usePage, router } from "@inertiajs/vue3";

    const page = usePage();

    // --- RÉCUPÉRATION DES DONNÉES UTILISATEUR ---
    const user = computed(() => page.props.auth?.user || {});
    const userName = computed(() => user.value.name || "Utilisateur");
    const userRole = computed(() => user.value.role || "Utilisateur");

    // VÉRIFICATION DE LA PRÉSENCE DU CODE EN BASE
    const hasMaterialCode = computed(() => !!user.value.code_materiel);

    // --- ÉTATS POUR LES MODALES ---
    const showCodeModal = ref(false);
    const showDeniedModal = ref(false);
    const inputCode = ref("");
    const errorMessage = ref("");
    const isProcessing = ref(false);

    // --- GESTION DU CLIC SUR LE MODULE MATÉRIEL ---
    const handleMaterialAccess = () => {
        if (!hasMaterialCode.value) {
            showDeniedModal.value = true;
        } else {
            showCodeModal.value = true;
            errorMessage.value = "";
            inputCode.value = "";
        }
    };

    // --- VÉRIFICATION DU CODE VIA LE SERVEUR ---
    const verifyAndEnter = () => {
        if (!inputCode.value) return;

        isProcessing.value = true;
        errorMessage.value = "";

        router.post(
            route("materiel.verify"),
            {
                code: inputCode.value,
            },
            {
                onSuccess: () => {
                    showCodeModal.value = false;
                    isProcessing.value = false;
                },
                onError: (errors) => {
                    isProcessing.value = false;
                    errorMessage.value = errors.code || "Code d'accès incorrect.";
                    inputCode.value = "";
                },
                preserveState: true,
            },
        );
    };

    onMounted(() => {
        console.log(
            "Portail TTI chargé. Accès matériel :",
            hasMaterialCode.value ? "Activé" : "Verrouillé",
        );
    });
</script>

<template>
    <Head title="Portail de Gestion" />

    <v-app class="portal-app bg-teal-lighten-5">
        <v-main class="d-flex align-center justify-center">
            
            <v-card class="profile-fixed-top ma-4 ma-md-8 pa-2 pr-4 rounded-pill elevation-2" color="white" style="
                    position: fixed;
                    top: 0;
                    right: 0;
                    z-index: 1000;
                    border-top: 3px solid #00796b !important;
                ">
                <div class="d-flex align-center">
                    <v-avatar color="teal-darken-2" size="32" class="mr-3 ml-1">
                        <span class="text-caption font-weight-bold text-white">
                            {{ userName.charAt(0).toUpperCase() }}
                        </span>
                    </v-avatar>
                    <div class="text-left mr-4 d-none d-sm-block">
                        <div class="text-subtitle-2 font-weight-black text-teal-darken-4 leading-none">
                            {{ userName }}
                        </div>
                        <div class="text-caption text-teal-darken-1 font-weight-bold text-uppercase" style="font-size: 0.65rem !important">
                            {{ userRole }}
                        </div>
                    </div>
                    <Link :href="route('logout')" method="post" as="button">
                        <v-btn icon="mdi-power" variant="text" color="red-darken-1" size="small"></v-btn>
                    </Link>
                </div>
            </v-card>

            <v-container class="py-12">
                <div class="text-center mb-12 mt-10 mt-md-0">
                    <v-avatar color="teal-darken-2" size="72" class="mb-4 elevation-3">
                        <v-icon icon="mdi-shield-lock" size="40" color="white" />
                    </v-avatar>
                    <h1 class="text-h3 text-md-h2 font-weight-black text-teal-darken-4 mb-2">
                        Espace <span class="text-teal-darken-2">TTI Service</span>
                    </h1>
                    <p class="text-teal-darken-1 text-h6 font-weight-light">
                        Portail de gestion interne • 2026
                    </p>
                </div>

                <v-row justify="center" class="px-4">
                    <v-col cols="12" md="5" lg="4">
                        <v-card @click="handleMaterialAccess" elevation="8" class="module-card pa-8 rounded-xl text-center h-100 d-flex flex-column border-t-lg border-teal-darken-2" color="white">
                            <div class="flex-grow-1">
                                <v-avatar :color="hasMaterialCode ? 'teal-lighten-5' : 'red-lighten-5'" size="80" class="mb-6">
                                    <v-icon :icon="hasMaterialCode ? 'mdi-package-variant' : 'mdi-lock-alert'" 
                                            size="40" 
                                            :color="hasMaterialCode ? 'teal-darken-2' : 'red-darken-2'"></v-icon>
                                </v-avatar>
                                <h2 class="text-h5 font-weight-black mb-4 text-uppercase text-teal-darken-4">
                                    Réception Matériel
                                </h2>
                                <p class="text-grey-darken-1 mb-8 text-body-2">
                                    {{ hasMaterialCode 
                                        ? "Authentification requise pour gérer l'inventaire." 
                                        : "Accès restreint : Code d'accès requis." 
                                    }}
                                </p>
                            </div>
                            <v-btn :color="hasMaterialCode ? 'teal-darken-2' : 'red-darken-3'" rounded="pill" class="action-btn px-8 font-weight-bold align-self-center text-none">
                                Entrer
                            </v-btn>
                        </v-card>
                    </v-col>

                    <v-col cols="12" md="5" lg="4">
                        <v-card @click="router.visit(route('demandes.index'))" elevation="8" class="module-card pa-8 rounded-xl text-center h-100 d-flex flex-column border-t-lg border-teal-darken-2" color="white">
                            <div class="flex-grow-1">
                                <v-avatar color="teal-lighten-5" size="80" class="mb-6">
                                    <v-icon icon="mdi-file-document-edit-outline" size="40" color="teal-darken-2"></v-icon>
                                </v-avatar>
                                <h2 class="text-h5 font-weight-black mb-4 text-uppercase text-teal-darken-4">
                                    Gestion Demandes
                                </h2>
                                <p class="text-grey-darken-1 mb-8 text-body-2">
                                    Suivre les requêtes des services et imprimer les bons.
                                </p>
                            </div>
                            <v-btn color="teal-darken-2" rounded="pill" class="action-btn px-8 font-weight-bold align-self-center text-none">
                                Entrer
                            </v-btn>
                        </v-card>
                    </v-col>
                </v-row>

                <p class="text-center text-caption text-teal-darken-1 mt-12">
                    &copy; {{ new Date().getFullYear() }} — Portail Technique TTI
                </p>
            </v-container>

            <v-dialog v-model="showCodeModal" max-width="400" persistent>
                <v-card class="rounded-xl pa-6 text-center border-t-lg border-teal-darken-2" color="white">
                    <v-icon icon="mdi-key-variant" color="teal-darken-2" size="48" class="mb-4" />
                    <v-card-title class="text-h5 font-weight-black text-teal-darken-4">Clé d'accès requise</v-card-title>
                    <v-card-text>
                        <p class="text-grey-darken-1 mb-6">Saisissez votre code matériel pour déverrouiller ce module.</p>
                        <v-text-field v-model="inputCode" label="Code Matériel" variant="outlined" color="teal-darken-2" :error-messages="errorMessage" :loading="isProcessing" type="password" @keyup.enter="verifyAndEnter" />
                    </v-card-text>
                    <v-card-actions class="justify-center">
                        <v-btn variant="text" color="grey" @click="showCodeModal = false">Annuler</v-btn>
                        <v-btn color="teal-darken-2" class="px-8 font-weight-bold" @click="verifyAndEnter">Valider</v-btn>
                    </v-card-actions>
                </v-card>
            </v-dialog>

            <v-dialog v-model="showDeniedModal" max-width="450">
                <v-card class="rounded-xl pa-6 text-center border-t-lg border-red-darken-2" color="white">
                    <v-avatar color="red-lighten-5" size="70" class="mb-4 mx-auto">
                        <v-icon icon="mdi-shield-lock" color="red-darken-2" size="40" />
                    </v-avatar>
                    <v-card-title class="text-h5 font-weight-black text-red-darken-4 justify-center">Accès Interdit</v-card-title>
                    <v-card-text class="text-grey-darken-1 text-body-1">
                        Désolé <strong>{{ userName }}</strong>, vous ne disposez pas d'un code d'accès matériel.<br /><br />
                        Veuillez contacter l'administrateur système.
                    </v-card-text>
                    <v-card-actions class="justify-center mt-4">
                        <v-btn color="red-darken-2" variant="flat" rounded="pill" class="px-8 font-weight-bold" @click="showDeniedModal = false">Compris</v-btn>
                    </v-card-actions>
                </v-card>
            </v-dialog>
        </v-main>
    </v-app>
</template>

<style scoped>
    .portal-app {
        animation: fadeIn 0.4s ease-in;
    }

    .module-card {
        border-top-width: 6px !important;
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        cursor: pointer;
    }

    .module-card:hover {
        transform: translateY(-10px);
        box-shadow: 0 20px 25px -5px rgba(0, 121, 107, 0.1) !important;
    }

    @keyframes fadeIn {
        from { opacity: 0; }
        to { opacity: 1; }
    }

    .leading-none {
        line-height: 1;
    }
</style>