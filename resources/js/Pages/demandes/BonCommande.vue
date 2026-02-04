<script setup lang="ts">
import { Head } from "@inertiajs/vue3";
import { computed } from "vue";

interface PieceItem {
    id: number;
    nom_piece: string;
    numero_serie: string;
}

interface DemandeItem {
    id: number;
    numcomande: string;
    nom_materiel: string;
    numero_serie?: string;
    nbredemande: number;
    demandeur_nom: string;
    description?: string;
    pieces?: PieceItem[];
    a_des_pieces_au_total?: boolean;
}

const props = defineProps<{
    service: string;
    demandes: DemandeItem[];
    date: string;
    demandeur: string;
}>();

const demandesDuBeneficiaire = computed(() => {
    return props.demandes.filter(
        (d) =>
            String(d.demandeur_nom).toLowerCase() ===
            String(props.demandeur).toLowerCase(),
    );
});

const numeroCommande = computed(() => {
    return demandesDuBeneficiaire.value.length > 0
        ? demandesDuBeneficiaire.value[0].numcomande
        : "N/A";
});

const totalArticles = computed(() => {
    return demandesDuBeneficiaire.value.reduce(
        (sum, item) => sum + (item.nbredemande || 0),
        0,
    );
});

// Fonctions avec types de retour explicites pour satisfaire TypeScript
const lancerImpression = (): void => window.print();
const retourner = (): void => window.history.back();
</script>

<template>
    <Head :title="'BON DE COMMANDE - ' + demandeur" />

    <div class="print-page">
        <div class="no-print d-flex justify-center pa-6 bg-grey-lighten-3">
            <v-btn
                variant="tonal"
                color="grey-darken-3"
                prepend-icon="mdi-arrow-left"
                @click="retourner"
                class="mr-4"
                >Retour</v-btn
            >
            <v-btn
                color="black"
                prepend-icon="mdi-printer"
                @click="lancerImpression"
                elevation="4"
                >Imprimer le Bon de livraison</v-btn
            >
        </div>

        <div class="document">
            <div class="header-grid mb-6">
                <div class="company-info">
                    <h1 class="text-h4 font-weight-black mb-1">
                        Magasin DTTIA
                    </h1>
                    <p class="text-uppercase font-weight-bold mb-0">
                        Département Logistique & Matériel
                    </p>
                    <p class="text-caption mb-0">République du Mali</p>
                    <p class="text-caption mb-0">Un Peuple-Un But-Une fois</p>
                </div>
                <div class="doc-title-box">
                    <h2 class="title-text">BON DE LIVRAISON</h2>
                    <div class="order-number">N°..................</div>
                </div>
            </div>

            <div class="info-table mb-6">
                <div class="info-row">
                    <div class="info-cell flex-2">
                        <label>Receveur</label>
                        <div class="value text-uppercase">{{ demandeur }}</div>
                    </div>
                    <div class="info-cell flex-1">
                        <label>DATE D'ÉMISSION</label>
                        <div class="value">{{ date }}</div>
                    </div>
                </div>
                <div class="info-row">
                    <div class="info-cell flex-2">
                        <label>SERVICE RÉFÉRENT</label>
                        <div class="value">{{ service }}</div>
                    </div>
                    <div class="info-cell flex-1">
                        <label>RÉFÉRENCE INTERNE</label>
                        <div class="value">N°{{ numeroCommande }}</div>
                    </div>
                </div>
            </div>

            <table class="main-table">
                <thead>
                    <tr>
                        <th style="width: 10%">RÉF.</th>
                        <th style="width: 50%">DESCRIPTION DES ARTICLES</th>
                        <th style="width: 25%">NUMÉRO DE SÉRIE</th>
                        <th style="width: 15%">QTÉ</th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="(item, index) in demandesDuBeneficiaire"
                        :key="item.id"
                    >
                        <td class="text-center font-weight-bold">
                            {{ index + 1 }}
                        </td>

                        <td class="pa-2">
                            <template
                                v-if="
                                    item.description
                                        ?.toUpperCase()
                                        .includes('SORTIE PIÈCES')
                                "
                            >
                                <div
                                    v-for="piece in item.pieces"
                                    :key="piece.id"
                                    class="font-weight-bold text-uppercase"
                                >
                                    {{ piece.nom_piece }}
                                </div>
                                <div
                                    class="text-caption italic ml-2"
                                    style="color: #666"
                                >
                                    issu de {{ item.nom_materiel }}
                                    <span
                                        >(S/N Mat:
                                        {{ item.numero_serie || "N/A" }})</span
                                    >
                                </div>
                            </template>

                            <template v-else>
                                <div class="font-weight-bold text-uppercase">
                                    {{ item.nom_materiel }}
                                </div>

                                <div
                                    v-if="item.pieces && item.pieces.length > 0"
                                >
                                    <div
                                        v-for="piece in item.pieces"
                                        :key="piece.id"
                                        class="text-caption italic ml-2"
                                    >
                                        + {{ piece.nom_piece }} (S/N:
                                        {{ piece.numero_serie }})
                                    </div>
                                </div>

                                <div
                                    v-else-if="
                                        item.description
                                            ?.toUpperCase()
                                            .includes('SANS PIÈCE')
                                    "
                                    class="text-caption italic ml-2"
                                    style="color: #d32f2f"
                                >
                                    (Sorti sans ses pièces d'origine)
                                </div>

                                <div
                                    v-else-if="item.a_des_pieces_au_total"
                                    class="text-caption italic ml-2"
                                    style="color: #d32f2f"
                                >
                                    (Matériel seul - Composants livrés
                                    séparément)
                                </div>

                                <div
                                    v-else
                                    class="text-caption italic ml-2"
                                    style="color: #444"
                                >
                                    (Matériel complet / Composants inclus)
                                </div>
                            </template>
                        </td>

                        <td class="text-center font-mono font-weight-bold">
                            <template
                                v-if="
                                    item.description
                                        ?.toUpperCase()
                                        .includes('SORTIE PIÈCES') &&
                                    item.pieces?.length
                                "
                            >
                                <div
                                    v-for="piece in item.pieces"
                                    :key="piece.id"
                                >
                                    {{ piece.numero_serie || "—" }}
                                </div>
                            </template>
                            <template v-else>
                                {{ item.numero_serie || "—" }}
                            </template>
                        </td>

                        <td class="text-center text-h6 font-weight-black">
                            {{ item.nbredemande }}
                        </td>
                    </tr>

                    <tr
                        v-for="n in Math.max(
                            0,
                            8 - demandesDuBeneficiaire.length,
                        )"
                        :key="'empty-' + n"
                        class="empty-row"
                    >
                        <td>&nbsp;</td>
                        <td>&nbsp;</td>
                        <td>&nbsp;</td>
                        <td>&nbsp;</td>
                    </tr>
                </tbody>
                <tfoot>
                    <tr>
                        <td
                            colspan="3"
                            class="text-right pa-2 font-weight-black"
                        >
                            TOTAL ARTICLES
                        </td>
                        <td
                            class="text-center font-weight-black bg-light text-h6 border-double"
                        >
                            {{ totalArticles }}
                        </td>
                    </tr>
                </tfoot>
            </table>

            <div class="signature-grid mt-8">
                <div class="sig-card">
                    <div class="sig-header">LE CHEF MAGASINIER</div>
                    <div class="sig-body">
                        <div class="sig-date">Date: ____/____/20__</div>
                        <div class="sig-space">Signature</div>
                    </div>
                </div>
                <div class="sig-card">
                    <div class="sig-header">SOUS DIRECTION</div>
                    <div class="sig-body">
                        <div class="sig-date">Date: ____/____/20__</div>
                        <div class="sig-space">Signature</div>
                    </div>
                </div>
                <div class="sig-card">
                    <div class="sig-header">RÉCEPTIONNÉ PAR</div>
                    <div class="sig-body">
                        <div class="sig-name-field">
                            Nom: <strong>{{ demandeur }}</strong>
                        </div>
                        <div class="sig-space">Signature & Cachet</div>
                    </div>
                </div>
            </div>

            <footer class="doc-footer mt-auto">
                <div class="footer-line"></div>
                <p>
                    Magasin DTTIA - Document Officiel de bon de livraison - Page
                    1 / 1
                </p>
            </footer>
        </div>
    </div>
</template>

<style scoped>
/* BASE PRINT LAYOUT */
.print-page {
    background-color: #525659;
    min-height: 100vh;
    padding: 40px 0;
}
.document {
    width: 210mm;
    min-height: 297mm;
    margin: 0 auto;
    background: white;
    padding: 15mm;
    box-shadow: 0 0 20px rgba(0, 0, 0, 0.5);
    display: flex;
    flex-direction: column;
    font-family: "Helvetica", "Arial", sans-serif;
    color: #000;
}

/* HEADER STYLE BUSINESS-IN-A-BOX */
.header-grid {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    border-bottom: 4px solid #000;
    padding-bottom: 10px;
}
.doc-title-box {
    text-align: right;
    border-left: 2px solid #000;
    padding-left: 20px;
}
.title-text {
    font-size: 24pt;
    font-weight: 900;
    line-height: 1;
    margin-bottom: 5px;
}
.order-number {
    font-size: 18pt;
    font-weight: bold;
    color: #444;
}

/* INFO GRID (CELLULES) */
.info-table {
    display: flex;
    flex-direction: column;
    border: 2px solid #000;
}
.info-row {
    display: flex;
    border-bottom: 1px solid #000;
}
.info-row:last-child {
    border-bottom: none;
}
.info-cell {
    padding: 8px;
    border-right: 1px solid #000;
}
.info-cell:last-child {
    border-right: none;
}
.info-cell label {
    display: block;
    font-size: 7pt;
    font-weight: 900;
    color: #666;
    margin-bottom: 2px;
}
.info-cell .value {
    font-size: 11pt;
    font-weight: bold;
}
.flex-1 {
    flex: 1;
}
.flex-2 {
    flex: 2;
}

/* MAIN TABLE */
.main-table {
    width: 100%;
    border-collapse: collapse;
    border: 2px solid #000;
}
.main-table th {
    background: #000;
    color: #fff;
    padding: 10px;
    font-size: 10pt;
    text-transform: uppercase;
}
.main-table td {
    border: 1px solid #000;
    padding: 8px;
    font-size: 10pt;
}
.empty-row td {
    height: 35px;
}
.bg-light {
    background-color: #f0f0f0;
}
.border-double {
    border-top: 4px double #000 !important;
}

/* SIGNATURES */
.signature-grid {
    display: grid;
    grid-template-columns: 1fr 1fr 1fr;
    gap: 15px;
}
.sig-card {
    border: 1px solid #000;
    min-height: 120px;
}
.sig-header {
    background: #eee;
    border-bottom: 1px solid #000;
    padding: 5px;
    font-size: 8pt;
    font-weight: 900;
    text-align: center;
}
.sig-body {
    padding: 10px;
    display: flex;
    flex-direction: column;
    height: 100%;
}
.sig-space {
    margin-top: 30px;
    border-top: 1px dashed #ccc;
    font-size: 7pt;
    color: #999;
    text-align: center;
}

.doc-footer {
    text-align: center;
    font-size: 8pt;
    color: #777;
}
.footer-line {
    border-top: 1px solid #eee;
    margin-bottom: 5px;
}

@media print {
    .no-print {
        display: none !important;
    }
    .print-page {
        padding: 0;
        background: white;
    }
    .document {
        box-shadow: none;
        margin: 0;
        width: 100%;
        padding: 10mm;
    }
    @page {
        size: A4;
        margin: 0;
    }
}
</style>
