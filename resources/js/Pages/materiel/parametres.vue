<script setup>
    import { ref } from "vue";
    import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
    import { Head, useForm, router } from "@inertiajs/vue3";

    // On reçoit les listes depuis le contrôleur (Logique intacte)
    const props = defineProps({
        categories: Array,
        services: Array,
    });

    // Formulaires (Logique intacte)
    const formCat = useForm({ nom: "" });
    const formServ = useForm({ nom: "" });

    const submitCategory = () => {
        if (!formCat.nom.trim() || formCat.processing) return;
        formCat.post(route("categories.store"), {
            onSuccess: () => formCat.reset(),
            preserveScroll: true,
        });
    };

    const submitService = () => {
        if (!formServ.nom.trim() || formServ.processing) return;
        formServ.post(route("services.store"), {
            onSuccess: () => formServ.reset(),
            preserveScroll: true,
        });
    };

    const deleteCategory = (id) => {
        if (
            confirm(
                "Attention : Supprimer cette catégorie pourrait affecter le matériel. Confirmer ?",
            )
        ) {
            router.delete(route("categories.destroy", id), {
                preserveScroll: true,
            });
        }
    };

    const deleteService = (id) => {
        if (confirm("Voulez-vous vraiment supprimer ce service ?")) {
            router.delete(route("services.destroy", id), { preserveScroll: true });
        }
    };
</script>

<template>

    <Head title="Configuration Système" />
    <AuthenticatedLayout>
        <v-container fluid class="pa-4 pa-md-8 bg-teal-lighten-5 min-vh-100">
            <v-row align="center" class="mb-8">
                <v-col>
                    <div class="d-flex align-center mb-1">
                        <v-icon class="mr-3" size="32" color="teal-darken-2">mdi-cog-sync</v-icon>
                        <h1 class="text-h4 font-weight-black text-teal-darken-4">
                            Configuration
                        </h1>
                    </div>
                    <div class="text-subtitle-1 text-teal-darken-1 ml-11">
                        Gestion des structures et référentiels
                    </div>
                </v-col>
            </v-row>

            <v-row>
                <v-col cols="12" md="6">
                    <v-card border flat class="rounded-xl shadow-sm bg-white elevation-0">
                        <div class="pa-1 bg-teal-darken-1"></div>
                        <v-card-item class="pt-4">
                            <template v-slot:prepend>
                                <v-icon icon="mdi-tag-multiple" color="teal-darken-1"></v-icon>
                            </template>
                            <v-card-title class="text-h6 font-weight-bold text-teal-darken-3">
                                Catégories de Matériel
                            </v-card-title>
                        </v-card-item>

                        <v-card-text class="pa-6">
                            <v-text-field v-model="formCat.nom" label="Nouvelle catégorie" placeholder="Ex: Informatique, Mobilier..." variant="filled" bg-color="teal-lighten-5" color="teal-darken-2" rounded="lg" :error-messages="formCat.errors.nom" @keyup.enter="submitCategory" class="mb-2" hide-details="auto">
                                <template v-slot:append-inner>
                                    <v-btn icon="mdi-plus" variant="flat" color="teal-darken-1" size="small" class="rounded-lg shadow-sm" @click="submitCategory" :loading="formCat.processing"></v-btn>
                                </template>
                            </v-text-field>

                            <v-divider class="my-6 border-opacity-25"></v-divider>

                            <div class="d-flex align-center mb-4">
                                <span class="text-caption font-weight-black text-uppercase text-teal-darken-1 letter-spacing-1">
                                    Référentiel Actif ({{ categories.length }})
                                </span>
                            </div>

                            <div class="d-flex flex-wrap gap-2">
                                <v-chip v-for="cat in categories" :key="cat.id" color="teal-darken-2" variant="flat" size="large" class="chip-item white--text font-weight-medium" closable @click:close="deleteCategory(cat.id)">
                                    {{ cat.nom }}
                                </v-chip>
                            </div>
                        </v-card-text>
                    </v-card>
                </v-col>

                <v-col cols="12" md="6">
                    <v-card border flat class="rounded-xl shadow-sm bg-white elevation-0">
                        <div class="pa-1 bg-teal-darken-1"></div>
                        <v-card-item class="pt-4">
                            <template v-slot:prepend>
                                <v-icon icon="mdi-office-building-cog" color="teal-darken-1"></v-icon>
                            </template>
                            <v-card-title class="text-h6 font-weight-bold text-teal-darken-3">
                                Directions & Services
                            </v-card-title>
                        </v-card-item>

                        <v-card-text class="pa-6">
                            <v-text-field v-model="formServ.nom" label="Nouveau service" placeholder="Ex: Ressources Humaines..." variant="filled" bg-color="teal-lighten-5" color="teal-darken-2" rounded="lg" :error-messages="formServ.errors.nom" @keyup.enter="submitService" class="mb-2" hide-details="auto">
                                <template v-slot:append-inner>
                                    <v-btn icon="mdi-plus" variant="flat" color="teal-darken-1" size="small" class="rounded-lg shadow-sm" @click="submitService" :loading="formServ.processing"></v-btn>
                                </template>
                            </v-text-field>

                            <v-divider class="my-6 border-opacity-25"></v-divider>

                            <div class="d-flex align-center mb-4">
                                <span class="text-caption font-weight-black text-uppercase text-teal-darken-1 letter-spacing-1">
                                    Services Enregistrés ({{ services.length }})
                                </span>
                            </div>

                            <div class="d-flex flex-wrap gap-2">
                                <v-chip v-for="serv in services" :key="serv.id" color="white" variant="elevated" size="large" class="chip-item border-teal font-weight-medium text-teal-darken-3" closable @click:close="deleteService(serv.id)">
                                    {{ serv.nom }}
                                </v-chip>
                            </div>
                        </v-card-text>
                    </v-card>
                </v-col>
            </v-row>
        </v-container>
    </AuthenticatedLayout>
</template>

<style scoped>
    .gap-2 {
        gap: 10px;
    }

    .letter-spacing-1 {
        letter-spacing: 1px;
    }

    .chip-item {
        transition: all 0.2s ease;
        border: 1px solid rgba(0, 121, 107, 0.1) !important;
    }

    .chip-item:hover {
        transform: scale(1.05);
        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.05) !important;
    }

    .border-teal {
        border: 1px solid #00796b !important;
    }

    /* On réutilise l'animation d'entrée du layout */
    .v-row {
        animation: fadeInSlide 0.6s cubic-bezier(0.4, 0, 0.2, 1);
    }

    @keyframes fadeInSlide {
        from {
            opacity: 0;
            transform: translateY(10px);
        }

        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
</style>
