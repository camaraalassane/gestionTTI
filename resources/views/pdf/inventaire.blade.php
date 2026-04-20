<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>{{ $title ?? 'Inventaire' }}</title>
    <style>
        /* ===== STYLES GÉNÉRAUX ===== */
        @page {
            margin: 1.5cm 1cm 1.5cm 1cm;
        }

        body {
            font-family: 'Helvetica', 'Arial', sans-serif;
            font-size: 9px;
            line-height: 1.3;
            color: #000;
            margin: 0;
            padding: 0;
        }

        /* ===== EN-TÊTE DU DOCUMENT ===== */
        .document-header {
            width: 100%;
            border-bottom: 2px solid #000;
            margin-bottom: 20px;
            padding-bottom: 10px;
        }

        .document-title {
            font-size: 16px;
            font-weight: bold;
            text-transform: uppercase;
            margin: 0 0 5px 0;
        }

        .document-meta {
            display: flex;
            justify-content: space-between;
            font-size: 8px;
            color: #333;
        }

        /* ===== INFORMATIONS DE CLÔTURE ===== */
        .info-section {
            background-color: #f8f8f8;
            border: 1px solid #ccc;
            padding: 10px;
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
            font-size: 9px;
        }

        .info-item {
            display: inline-block;
        }

        .info-label {
            font-weight: bold;
            margin-right: 5px;
        }

        /* ===== TABLEAU PRINCIPAL ===== */
        .data-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
            table-layout: fixed;
        }

        .data-table th {
            background-color: #e0e0e0;
            border: 1px solid #000;
            padding: 8px 5px;
            font-size: 8px;
            font-weight: bold;
            text-transform: uppercase;
            text-align: left;
        }

        .data-table td {
            border: 1px solid #000;
            padding: 6px 5px;
            vertical-align: top;
            word-wrap: break-word;
        }

        /* ===== LIGNES DE GROUPEMENT ===== */
        .group-header-supplier {
            background-color: #1a6d5e !important;
            color: white !important;
            font-weight: bold;
            font-size: 10px;
            padding: 10px 8px !important;
            border: 2px solid #000 !important;
        }

        .group-header-supplier span {
            background-color: white;
            color: #1a6d5e;
            padding: 2px 8px;
            border-radius: 3px;
            margin-left: 15px;
            font-size: 9px;
        }

        /* ===== STATISTIQUES PAR FOURNISSEUR ===== */
        .stats-row {
            background-color: #f0fdfa !important;
            font-weight: bold;
            font-size: 9px;
        }

        .stats-row td {
            padding: 6px 8px !important;
            background-color: #f0fdfa !important;
        }

        /* ===== BADGES ===== */
        .badge-stock {
            display: inline-block;
            background-color: #d4edda;
            border: 1px solid #28a745;
            color: #155724;
            padding: 2px 8px;
            border-radius: 10px;
            font-size: 8px;
            font-weight: bold;
        }

        .badge-sorti {
            display: inline-block;
            background-color: #fff3cd;
            border: 1px solid #ffc107;
            color: #856404;
            padding: 2px 8px;
            border-radius: 10px;
            font-size: 8px;
            font-weight: bold;
        }

        .badge-total {
            display: inline-block;
            background-color: #e0e0e0;
            border: 1px solid #666;
            color: #333;
            padding: 2px 8px;
            border-radius: 10px;
            font-size: 8px;
            font-weight: bold;
        }

        /* ===== PIED DE PAGE ===== */
        .footer-section {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            font-size: 7px;
            color: #666;
            text-align: center;
            border-top: 1px solid #ccc;
            padding-top: 5px;
        }

        /* ===== SIGNATURES ===== */
        .signatures-container {
            margin-top: 30px;
            page-break-inside: avoid;
            width: 100%;
        }

        .signatures-table {
            width: 100%;
            border-collapse: collapse;
        }

        .signature-box {
            width: 45%;
            border: 1px solid #000;
            height: 80px;
            padding: 8px;
            vertical-align: top;
        }

        .signature-spacer {
            width: 10%;
        }

        .signature-title {
            font-weight: bold;
            font-size: 8px;
            margin-bottom: 5px;
        }

        .signature-line {
            margin-top: 15px;
            font-size: 7px;
            color: #555;
        }

        /* ===== RÉCAPITULATIF GÉNÉRAL ===== */
        .total-general {
            margin-top: 20px;
            padding: 10px;
            border: 1px solid #000;
            text-align: center;
            background-color: #f9f9f9;
        }

        .total-general p {
            margin: 5px 0;
            font-size: 9px;
        }

        /* ===== UTILITAIRES ===== */
        .text-left { text-align: left; }
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .font-bold { font-weight: bold; }
        .font-mono { font-family: monospace; }
        .text-uppercase { text-transform: uppercase; }
    </style>
</head>
<body>

    <!-- ===== EN-TÊTE DU DOCUMENT ===== -->
    <div class="document-header">
        <h1 class="document-title">INVENTAIRE DU MATÉRIEL - {{ $inventaire->annee }}</h1>
        <div class="document-meta">
            <span>Référence : INV-{{ $inventaire->annee }}-{{ str_pad($inventaire->id, 3, '0', STR_PAD_LEFT) }}</span>
            <span>Édité le : {{ \Carbon\Carbon::parse($date)->format('d/m/Y H:i') }}</span>
        </div>
    </div>

    <!-- ===== INFORMATIONS DE CLÔTURE ===== -->
    <div class="info-section">
        <div class="info-item">
            <span class="info-label">Responsable clôture :</span>
            {{ $responsable }}
        </div>
        <div class="info-item">
            <span class="info-label">Date clôture :</span>
            {{ $inventaire->date_cloture ? \Carbon\Carbon::parse($inventaire->date_cloture)->format('d/m/Y') : 'N/A' }}
        </div>
        <div class="info-item">
            <span class="info-label">Total matériels :</span>
            {{ $total_materiels ?? 0 }}
        </div>
        <div class="info-item">
            <span class="info-label">Total modèles :</span>
            {{ $total_modeles ?? 0 }}
        </div>
    </div>

    <!-- ===== TABLEAU DES MODÈLES GROUPÉS PAR FOURNISSEUR ===== -->
    <table class="data-table">
        <thead>
            <tr>
                <th width="50%">MODÈLE</th>
                <th width="20%" class="text-center">EN STOCK (MAGASIN)</th>
                <th width="20%" class="text-center">SORTIS</th>
                <th width="10%" class="text-center">TOTAL</th>
            </tr>
        </thead>
        <tbody>
            @forelse($groupes as $groupe)
                <!-- LIGNE DE GROUPEMENT : FOURNISSEUR -->
                <tr>
                    <td colspan="4" class="group-header-supplier">
                        FOURNISSEUR : {{ $groupe['fournisseur'] ?: 'NON RENSEIGNÉ' }}
                        <span>CONTRAT : {{ $groupe['numero_contrat'] ?: 'N/A' }}</span>
                    </td>
                </tr>

                <!-- LIGNE DE STATISTIQUES DU FOURNISSEUR -->
                <tr>
                    <td class="stats-row text-right font-bold">TOTAL {{ $groupe['fournisseur'] }} :</td>
                    <td class="stats-row text-center font-bold">
                        {{ collect($groupe['modeles'])->sum('qte_stock') }}
                    </td>
                    <td class="stats-row text-center font-bold">
                        {{ collect($groupe['modeles'])->sum('qte_sorti') }}
                    </td>
                    <td class="stats-row text-center font-bold">
                        {{ collect($groupe['modeles'])->sum('total') }}
                    </td>
                </tr>

                <!-- LISTE DES MODÈLES DU FOURNISSEUR -->
                @foreach($groupe['modeles'] as $modele)
                <tr>
                    <td class="font-bold">{{ $modele['designation'] }}</td>
                    <td class="text-center">
                        <span class="badge-stock">{{ $modele['qte_stock'] }}</span>
                    </td>
                    <td class="text-center">
                        <span class="badge-sorti">{{ $modele['qte_sorti'] }}</span>
                    </td>
                    <td class="text-center">
                        <span class="badge-total">{{ $modele['total'] }}</span>
                    </td>
                </tr>
                @endforeach

                <!-- ESPACE ENTRE FOURNISSEURS -->
                <tr><td colspan="4" style="height: 10px; background-color: transparent; border: none;"></td></tr>
            @empty
                <tr>
                    <td colspan="4" class="text-center" style="padding: 30px;">
                        <span style="font-size: 11px;">AUCUN MODÈLE DANS CET INVENTAIRE</span>
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <!-- ===== RÉCAPITULATIF GÉNÉRAL ===== -->
    <div class="total-general">
        <p><strong>RÉCAPITULATIF GÉNÉRAL DE L'INVENTAIRE</strong></p>
        <p>
            Total fournisseurs : <strong>{{ count($groupes) }}</strong> |
            Total modèles : <strong>{{ $total_modeles ?? 0 }}</strong> |
            Total matériels en stock : <strong>{{ $total_stock ?? 0 }}</strong> |
            Total matériels sortis : <strong>{{ $total_sorti ?? 0 }}</strong> |
            Total général : <strong>{{ $total_materiels ?? 0 }}</strong>
        </p>
    </div>

    <!-- ===== SIGNATURES ===== -->
    <div class="signatures-container">
        <table class="signatures-table">
            <tr>
                <td class="signature-box">
                    <div class="signature-title">LE RESPONSABLE MAGASIN</div>
                    <div style="margin-top: 25px;">
                        <span style="font-size: 7px;">Nom : ......................................</span>
                    </div>
                    <div class="signature-line">
                        Signature : ..........................................
                    </div>
                </td>
                <td class="signature-spacer"></td>
                <td class="signature-box">
                    <div class="signature-title">AUDIT / DIRECTION</div>
                    <div style="margin-top: 10px;">
                        <span style="font-size: 7px;">Observations :</span><br>
                        <span style="font-size: 7px;">................................................................</span>
                    </div>
                    <div class="signature-line" style="margin-top: 15px;">
                        Cachet & Signature :
                    </div>
                </td>
            </tr>
        </table>
    </div>

    <!-- ===== PIED DE PAGE ===== -->
    <div class="footer-section">
        <span>Inventaire TTI {{ $inventaire->annee }} - Document officiel</span>
        <span style="margin-left: 20px;">Généré le {{ \Carbon\Carbon::parse($date)->format('d/m/Y H:i:s') }}</span>
    </div>

</body>
</html>