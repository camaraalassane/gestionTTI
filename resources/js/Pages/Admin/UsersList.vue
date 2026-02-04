<script setup>
import { ref } from "vue";
import { useForm, Head, router, usePage } from "@inertiajs/vue3";
import AdminLayout from "@/Layouts/AdminLayout.vue";

const props = defineProps({
    users: { type: Array, default: () => [] },
    stats: { type: Object, default: () => ({ total: 0, admins: 0 }) },
});

const page = usePage();
const dialog = ref(false);
const showPw = ref(false);

const form = useForm({
    name: "",
    email: "",
    password: "",
    password_confirmation: "",
    role: "user",
});

const submit = () => {
    form.post(route("admin.users.store"), {
        onSuccess: () => {
            dialog.value = false;
            form.reset();
        },
        onFinish: () => form.reset("password", "password_confirmation"),
    });
};

const toggleRole = (user) => {
    const newRole = user.role === "admin" ? "user" : "admin";
    if (confirm(`Changer le rôle de ${user.name} en ${newRole} ?`)) {
        router.patch(
            route("admin.users.updateRole", user.id),
            { role: newRole },
            { preserveScroll: true },
        );
    }
};

const generateCode = (user) => {
    if (
        confirm(`Générer un nouveau code d'accès matériel pour ${user.name} ?`)
    ) {
        router.post(
            route("admin.users.generateCode", user.id),
            {},
            { preserveScroll: true },
        );
    }
};

const revokeCode = (user) => {
    if (
        confirm(
            `Retirer l'accès matériel et supprimer le code de ${user.name} ?`,
        )
    ) {
        router.post(
            route("admin.users.revokeCode", user.id),
            {},
            { preserveScroll: true },
        );
    }
};

const deleteUser = (id) => {
    if (confirm("Supprimer cet utilisateur définitivement ?")) {
        router.delete(route("admin.users.destroy", id), {
            preserveScroll: true,
        });
    }
};
</script>

<template>
    <AdminLayout>
        <Head title="Gestion des Comptes" />

        <template #header>
            <div class="d-flex align-center">
                <v-icon
                    icon="mdi-account-cog-outline"
                    color="teal-darken-3"
                    class="me-2"
                />
                Gestion des Comptes & Accès
            </div>
        </template>

        <v-row class="mb-6 align-center">
            <v-col>
                <p
                    class="text-subtitle-2 text-teal-darken-2 font-weight-medium"
                >
                    Contrôlez les accès utilisateurs et gérez les privilèges de
                    génération de codes matériels.
                </p>
            </v-col>
            <v-col class="text-right">
                <v-btn
                    color="teal-darken-3"
                    prepend-icon="mdi-account-plus"
                    elevation="2"
                    class="text-none rounded-lg font-weight-bold px-6"
                    @click="dialog = true"
                >
                    Nouvel Utilisateur
                </v-btn>
            </v-col>
        </v-row>

        <v-row class="mb-8">
            <v-col cols="12" sm="4">
                <v-card
                    elevation="2"
                    class="rounded-xl pa-4 border-teal shadow-sm"
                >
                    <div class="d-flex align-center">
                        <v-avatar color="teal-lighten-5" size="48" class="me-4">
                            <v-icon
                                icon="mdi-account-group"
                                color="teal-darken-3"
                            ></v-icon>
                        </v-avatar>
                        <div>
                            <div
                                class="text-caption text-grey-darken-1 font-weight-black uppercase tracking-wider"
                            >
                                Total Comptes
                            </div>
                            <div
                                class="text-h4 font-weight-black text-teal-darken-4"
                            >
                                {{ stats?.total || 0 }}
                            </div>
                        </div>
                    </div>
                </v-card>
            </v-col>

            <v-col cols="12" sm="4">
                <v-card
                    elevation="2"
                    class="rounded-xl pa-4 border-red shadow-sm"
                >
                    <div class="d-flex align-center">
                        <v-avatar color="red-lighten-5" size="48" class="me-4">
                            <v-icon
                                icon="mdi-shield-crown"
                                color="red-darken-3"
                            ></v-icon>
                        </v-avatar>
                        <div>
                            <div
                                class="text-caption text-grey-darken-1 font-weight-black uppercase tracking-wider"
                            >
                                Administrateurs
                            </div>
                            <div
                                class="text-h4 font-weight-black text-red-darken-4"
                            >
                                {{ stats?.admins || 0 }}
                            </div>
                        </div>
                    </div>
                </v-card>
            </v-col>
        </v-row>

        <v-card
            elevation="4"
            class="rounded-xl overflow-hidden glass-card border-light"
        >
            <v-table hover class="custom-user-table">
                <thead>
                    <tr class="bg-teal-darken-4 text-white">
                        <th class="font-weight-black text-white px-6">
                            UTILISATEUR
                        </th>
                        <th class="font-weight-black text-white text-center">
                            CODE & ACCÈS MATÉRIEL
                        </th>
                        <th class="font-weight-black text-white text-center">
                            RÔLE
                        </th>
                        <th class="font-weight-black text-white text-center">
                            CRÉATION
                        </th>
                        <th
                            class="font-weight-black text-white text-right px-6"
                        >
                            ACTIONS
                        </th>
                    </tr>
                </thead>
                <tbody class="bg-white">
                    <tr v-for="user in users" :key="user.id" class="table-row">
                        <td class="py-4 px-6">
                            <div class="d-flex align-center">
                                <v-avatar
                                    :color="
                                        user.role === 'admin'
                                            ? 'red-lighten-4'
                                            : 'teal-lighten-4'
                                    "
                                    size="40"
                                    class="me-3 border-sm"
                                >
                                    <span
                                        :class="
                                            user.role === 'admin'
                                                ? 'text-red-darken-4'
                                                : 'text-teal-darken-4'
                                        "
                                        class="font-weight-black"
                                        >{{ user.name?.charAt(0) }}</span
                                    >
                                </v-avatar>
                                <div>
                                    <div
                                        class="font-weight-black text-teal-darken-4"
                                    >
                                        {{ user.name }}
                                    </div>
                                    <div
                                        class="text-caption font-weight-medium text-grey"
                                    >
                                        {{ user.email }}
                                    </div>
                                </div>
                            </div>
                        </td>

                        <td class="text-center">
                            <div
                                v-if="user.code_materiel"
                                class="d-flex align-center justify-center"
                            >
                                <v-chip
                                    color="teal-darken-3"
                                    variant="flat"
                                    size="small"
                                    class="font-weight-black px-4 shadow-sm font-mono"
                                >
                                    {{ user.code_materiel }}
                                </v-chip>
                                <v-tooltip
                                    text="Révoquer l'accès"
                                    location="top"
                                >
                                    <template v-slot:activator="{ props }">
                                        <v-btn
                                            v-bind="props"
                                            icon="mdi-lock-open-remove-outline"
                                            variant="text"
                                            size="x-small"
                                            color="red-darken-2"
                                            class="ms-2"
                                            @click="revokeCode(user)"
                                        ></v-btn>
                                    </template>
                                </v-tooltip>
                            </div>
                            <v-btn
                                v-else
                                color="teal-darken-1"
                                variant="tonal"
                                size="x-small"
                                prepend-icon="mdi-key-plus"
                                class="text-none font-weight-bold px-3 rounded-pill"
                                @click="generateCode(user)"
                            >
                                Autoriser l'accès
                            </v-btn>
                        </td>

                        <td class="text-center">
                            <v-chip
                                :color="
                                    user.role === 'admin'
                                        ? 'red-darken-4'
                                        : 'slate-darken-3'
                                "
                                size="x-small"
                                variant="flat"
                                class="font-weight-black px-3 rounded-lg"
                            >
                                {{ user.role.toUpperCase() }}
                            </v-chip>
                        </td>

                        <td
                            class="text-center text-body-2 font-weight-medium text-grey-darken-2"
                        >
                            {{
                                user.created_at
                                    ? new Date(
                                          user.created_at,
                                      ).toLocaleDateString()
                                    : "N/A"
                            }}
                        </td>

                        <td class="text-right px-6">
                            <v-tooltip text="Changer le rôle" location="top">
                                <template v-slot:activator="{ props }">
                                    <v-btn
                                        v-bind="props"
                                        icon="mdi-account-switch-outline"
                                        variant="text"
                                        color="teal-darken-2"
                                        size="small"
                                        class="me-1"
                                        @click="toggleRole(user)"
                                    ></v-btn>
                                </template>
                            </v-tooltip>

                            <v-tooltip
                                text="Supprimer le compte"
                                location="top"
                            >
                                <template v-slot:activator="{ props }">
                                    <v-btn
                                        v-bind="props"
                                        icon="mdi-delete-outline"
                                        variant="text"
                                        color="red-darken-3"
                                        size="small"
                                        @click="deleteUser(user.id)"
                                    ></v-btn>
                                </template>
                            </v-tooltip>
                        </td>
                    </tr>
                </tbody>
            </v-table>
        </v-card>

        <v-dialog
            v-model="dialog"
            max-width="500"
            persistent
            transition="dialog-bottom-transition"
        >
            <v-card class="rounded-xl pa-2 elevation-24">
                <v-card-title
                    class="text-h6 font-weight-black px-6 pt-6 text-teal-darken-4"
                >
                    <v-avatar color="teal-lighten-5" size="32" class="me-2">
                        <v-icon
                            icon="mdi-account-plus"
                            color="teal-darken-3"
                            size="20"
                        ></v-icon>
                    </v-avatar>
                    Nouveau Compte Utilisateur
                </v-card-title>

                <v-card-text class="px-6 pb-6">
                    <v-form @submit.prevent="submit" class="mt-4">
                        <v-text-field
                            v-model="form.name"
                            label="Nom complet"
                            variant="outlined"
                            density="comfortable"
                            color="teal-darken-3"
                            :error-messages="form.errors.name"
                            prepend-inner-icon="mdi-account-outline"
                            class="mb-2"
                            rounded="lg"
                        />

                        <v-text-field
                            v-model="form.email"
                            label="Adresse Email"
                            variant="outlined"
                            density="comfortable"
                            color="teal-darken-3"
                            :error-messages="form.errors.email"
                            prepend-inner-icon="mdi-email-outline"
                            class="mb-2"
                            rounded="lg"
                        />

                        <v-text-field
                            v-model="form.password"
                            :type="showPw ? 'text' : 'password'"
                            label="Mot de passe"
                            variant="outlined"
                            density="comfortable"
                            color="teal-darken-3"
                            :append-inner-icon="
                                showPw ? 'mdi-eye-off' : 'mdi-eye'
                            "
                            @click:append-inner="showPw = !showPw"
                            :error-messages="form.errors.password"
                            prepend-inner-icon="mdi-lock-outline"
                            class="mb-2"
                            rounded="lg"
                        />

                        <v-text-field
                            v-model="form.password_confirmation"
                            type="password"
                            label="Confirmation"
                            variant="outlined"
                            density="comfortable"
                            color="teal-darken-3"
                            prepend-inner-icon="mdi-lock-check-outline"
                            class="mb-4"
                            rounded="lg"
                        />

                        <v-select
                            v-model="form.role"
                            :items="[
                                { title: 'Administrateur', value: 'admin' },
                                {
                                    title: 'Utilisateur Standard',
                                    value: 'user',
                                },
                            ]"
                            label="Attribution du rôle"
                            variant="outlined"
                            density="comfortable"
                            color="teal-darken-3"
                            rounded="lg"
                            prepend-inner-icon="mdi-shield-account-outline"
                        />

                        <v-divider class="my-6"></v-divider>

                        <div class="d-flex justify-end">
                            <v-btn
                                variant="text"
                                color="grey-darken-1"
                                class="text-none font-weight-bold me-2 px-4"
                                @click="
                                    dialog = false;
                                    form.reset();
                                "
                                >Annuler</v-btn
                            >
                            <v-btn
                                color="teal-darken-3"
                                type="submit"
                                class="text-none px-8 font-weight-bold rounded-lg"
                                :loading="form.processing"
                                elevation="2"
                            >
                                Créer l'accès
                            </v-btn>
                        </div>
                    </v-form>
                </v-card-text>
            </v-card>
        </v-dialog>
    </AdminLayout>
</template>

<style scoped>
.font-mono {
    font-family: "Courier New", Courier, monospace !important;
    letter-spacing: 1px;
}
.border-teal {
    border-left: 6px solid #00695c !important;
}
.border-red {
    border-left: 6px solid #b71c1c !important;
}
.glass-card {
    background: rgba(255, 255, 255, 0.95) !important;
    backdrop-filter: blur(10px);
}

.table-row {
    transition: all 0.2s ease;
}
.table-row:hover {
    background-color: #f0fdfa !important;
}

.uppercase {
    text-transform: uppercase;
}
.tracking-wider {
    letter-spacing: 0.05rem;
}
</style>
