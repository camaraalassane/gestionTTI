<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>{{ $title ?? 'Inventaire du matériel' }}</title>
    <style>
        /* ===== STYLES GÉNÉRAUX ===== */
        @page {
            margin: 1.5cm 1cm 1.5cm 1cm;
        }
        @page :first {
            margin-top: 1.5cm;
        }

        body {
            font-family: 'Helvetica', 'Arial', sans-serif;
            font-size: 9px;
            line-height: 1.4;
            color: #1a1a1a;
            margin: 0;
            padding: 0;
            background: #fff;
        }

        /* ===== EN-TÊTE DU DOCUMENT ===== */
        .document-header {
            width: 100%;
            border-bottom: 3px solid #1a6d5e;
            margin-bottom: 15px;
            padding-bottom: 8px;
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
        }

        .document-title {
            font-size: 18px;
            font-weight: 700;
            text-transform: uppercase;
            color: #1a6d5e;
            margin: 0;
            letter-spacing: 0.5px;
        }

        .document-title small {
            font-size: 11px;
            font-weight: 400;
            color: #555;
            text-transform: none;
        }

        .document-meta {
            font-size: 8px;
            color: #555;
            text-align: right;
            line-height: 1.6;
        }

        .document-meta .ref {
            font-weight: 700;
            color: #1a6d5e;
        }

        /* ===== INFORMATIONS DE CLÔTURE ===== */
        .info-section {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 4px;
            padding: 8px 12px;
            margin-bottom: 15px;
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            gap: 5px;
        }

        .info-item {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            font-size: 8.5px;
            padding: 2px 6px;
            background: #fff;
            border-radius: 3px;
            border: 1px solid #e9ecef;
        }

        .info-label {
            font-weight: 700;
            color: #495057;
        }

        .info-value {
            color: #212529;
            font-weight: 600;
        }

        .info-value.highlight {
            color: #1a6d5e;
        }

        /* ===== TABLEAU PRINCIPAL ===== */
        .data-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 15px;
            font-size: 8.5px;
        }

        .data-table th {
            background: #1a6d5e;
            color: #fff;
            border: 1px solid #0d4f44;
            padding: 6px 8px;
            font-size: 7.5px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            text-align: left;
        }

        .data-table th.text-center {
            text-align: center;
        }

        .data-table td {
            border: 1px solid #dee2e6;
            padding: 5px 8px;
            vertical-align: middle;
            word-wrap: break-word;
        }

        .data-table tbody tr:nth-child(even) {
            background: #fcfcfc;
        }

        .data-table tbody tr:hover {
            background: #f0f7f5;
        }

        /* ===== LIGNES DE GROUPEMENT ===== */
        .group-header-supplier {
            background: #1a6d5e !important;
            color: #fff !important;
            font-weight: 700;
            font-size: 10px;
            padding: 8px 12px !important;
            border: 2px solid #0d4f44 !important;
        }

        .group-header-supplier .contrat-badge {
            background: rgba(255,255,255,0.15);
            border: 1px solid rgba(255,255,255,0.3);
            padding: 2px 10px;
            border-radius: 12px;
            margin-left: 12px;
            font-size: 8px;
            font-weight: 400;
            display: inline-block;
        }

        .group-header-supplier .fournisseur-nom {
            background: rgba(255,255,255,0.2);
            padding: 2px 10px;
            border-radius: 4px;
            margin-right: 8px;
            font-weight: 400;
        }

        /* ===== STATISTIQUES PAR FOURNISSEUR ===== */
        .stats-row {
            background: #f0fdfa !important;
            font-weight: 700;
            font-size: 9px;
        }

        .stats-row td {
            padding: 6px 10px !important;
            background: #f0fdfa !important;
            border-top: 2px solid #1a6d5e !important;
            border-bottom: 2px solid #1a6d5e !important;
        }

        .stats-row .label {
            color: #1a6d5e;
        }

        /* ===== BADGES ===== */
        .badge {
            display: inline-block;
            padding: 2px 10px;
            border-radius: 12px;
            font-size: 8px;
            font-weight: 700;
            min-width: 28px;
            text-align: center;
        }

        .badge-stock {
            background: #d4edda;
            border: 1px solid #28a745;
            color: #155724;
        }

        .badge-sorti {
            background: #fff3cd;
            border: 1px solid #ffc107;
            color: #856404;
        }

        .badge-total {
            background: #e9ecef;
            border: 1px solid #adb5bd;
            color: #343a40;
        }

        .badge-attente {
            background: #cce5ff;
            border: 1px solid #007bff;
            color: #004085;
        }

        /* ===== PIED DE PAGE ===== */
        .footer-section {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            font-size: 7px;
            color: #6c757d;
            text-align: center;
            border-top: 1px solid #dee2e6;
            padding: 6px 0 0 0;
            background: #fff;
        }

        .footer-section .page-number:after {
            content: "Page " counter(page);
        }

        /* ===== SIGNATURES ===== */
        .signatures-container {
            margin-top: 25px;
            page-break-inside: avoid;
            width: 100%;
        }

        .signatures-table {
            width: 100%;
            border-collapse: collapse;
        }

        .signature-box {
            width: 45%;
            border: 1px solid #1a6d5e;
            border-radius: 4px;
            height: 70px;
            padding: 10px 12px;
            vertical-align: top;
            background: #fafcfb;
        }

        .signature-spacer {
            width: 10%;
        }

        .signature-title {
            font-weight: 700;
            font-size: 8px;
            color: #1a6d5e;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 1px solid #dee2e6;
            padding-bottom: 4px;
            margin-bottom: 6px;
        }

        .signature-line {
            margin-top: 12px;
            font-size: 7px;
            color: #6c757d;
        }

        .signature-line .underline {
            display: inline-block;
            min-width: 120px;
            border-bottom: 1px solid #adb5bd;
            margin-left: 4px;
        }

        /* ===== RÉCAPITULATIF GÉNÉRAL ===== */
        .total-general {
            margin-top: 15px;
            padding: 10px 15px;
            border: 2px solid #1a6d5e;
            border-radius: 6px;
            text-align: center;
            background: #f8fffe;
            page-break-inside: avoid;
        }

        .total-general .title {
            font-size: 11px;
            font-weight: 700;
            color: #1a6d5e;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin: 0 0 6px 0;
        }

        .total-general .stats-line {
            display: flex;
            justify-content: center;
            gap: 20px;
            flex-wrap: wrap;
            font-size: 9px;
        }

        .total-general .stat-item {
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }

        .total-general .stat-item .number {
            font-weight: 700;
            font-size: 11px;
            padding: 2px 8px;
            border-radius: 4px;
        }

        .total-general .stat-item .number.stock {
            color: #155724;
            background: #d4edda;
        }

        .total-general .stat-item .number.sorti {
            color: #856404;
            background: #fff3cd;
        }

        .total-general .stat-item .number.total {
            color: #1a6d5e;
            background: #e0ece8;
        }

        .total-general .stat-item .number.fournisseurs {
            color: #004085;
            background: #cce5ff;
        }

        .total-general .stat-item .number.modeles {
            color: #721c24;
            background: #f5c6cb;
        }

        /* ===== UTILITAIRES ===== */
        .text-left { text-align: left; }
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .font-bold { font-weight: 700; }
        .font-mono { font-family: 'Courier New', monospace; }
        .text-uppercase { text-transform: uppercase; }
        .text-muted { color: #6c757d; }

        .mt-1 { margin-top: 5px; }
        .mt-2 { margin-top: 10px; }
        .mb-1 { margin-bottom: 5px; }
        .mb-2 { margin-bottom: 10px; }

        /* ===== GESTION DES SAUTS DE PAGE ===== */
        .page-break-before {
            page-break-before: always;
        }
        .page-break-inside-avoid {
            page-break-inside: avoid;
        }
        .no-break {
            page-break-inside: avoid;
        }
    </style>
</head>
<body>

    <!-- ===== EN-TÊTE DU DOCUMENT ===== -->
    <div class="document-header">
        <div>
            <h1 class="document-title">
                INVENTAIRE DU MATERIEL
                <small>{{ $inventaire->annee }}</small>
            </h1>
        </div>
        <div class="document-meta">
            <div><span class="ref">Ref :</span> INV-{{ $inventaire->annee }}-{{ str_pad($inventaire->id ?? 0, 4, '0', STR_PAD_LEFT) }}</div>
            <div>Emis le : {{ \Carbon\Carbon::parse($date ?? now())->format('d/m/Y H:i') }}</div>
        </div>
    </div>

    <!-- ===== INFORMATIONS DE CLOTURE ===== -->
    <div class="info-section">
        <div class="info-item">
            <span class="info-label">Responsable :</span>
            <span class="info-value">{{ $responsable }}</span>
        </div>
        <div class="info-item">
            <span class="info-label">Date cloture :</span>
            <span class="info-value">{{ $inventaire->date_cloture ? \Carbon\Carbon::parse($inventaire->date_cloture)->format('d/m/Y') : 'N/A' }}</span>
        </div>
        <div class="info-item">
            <span class="info-label">Total materiels :</span>
            <span class="info-value highlight">{{ $total_materiels ?? 0 }}</span>
        </div>
        <div class="info-item">
            <span class="info-label">Total modeles :</span>
            <span class="info-value highlight">{{ $total_modeles ?? 0 }}</span>
        </div>
        <div class="info-item">
            <span class="info-label">Fournisseurs :</span>
            <span class="info-value highlight">{{ count($groupes ?? []) }}</span>
        </div>
    </div>

    <!-- ===== TABLEAU DES MODELES GROUPES PAR FOURNISSEUR ===== -->
    <table class="data-table">
        <thead>
            <tr>
                <th width="50%">MODELE / DESIGNATION</th>
                <th width="20%" class="text-center">EN STOCK</th>
                <th width="20%" class="text-center">SORTIS</th>
                <th width="10%" class="text-center">TOTAL</th>
            </tr>
        </thead>
        <tbody>
            @forelse($groupes ?? [] as $groupe)
                <!-- LIGNE DE GROUPEMENT : FOURNISSEUR -->
                <tr>
                    <td colspan="4" class="group-header-supplier">
                        <span class="fournisseur-nom">FOURNISSEUR : {{ $groupe['fournisseur'] ?: 'NON RENSEIGNE' }}</span>
                        <span class="contrat-badge">CONTRAT : {{ $groupe['numero_contrat'] ?: 'N/A' }}</span>
                    </td>
                </tr>

                <!-- LIGNE DE STATISTIQUES DU FOURNISSEUR -->
                @php
                    $totalStockGroupe = collect($groupe['modeles'])->sum('qte_stock');
                    $totalSortiGroupe = collect($groupe['modeles'])->sum('qte_sorti');
                    $totalGroupe = collect($groupe['modeles'])->sum('total');
                @endphp
                <tr class="stats-row">
                    <td class="text-right">
                        <span class="label">TOTAL {{ strtoupper($groupe['fournisseur'] ?? '') }} :</span>
                    </td>
                    <td class="text-center font-bold">{{ $totalStockGroupe }}</td>
                    <td class="text-center font-bold">{{ $totalSortiGroupe }}</td>
                    <td class="text-center font-bold">{{ $totalGroupe }}</td>
                </tr>

                <!-- LISTE DES MODELES DU FOURNISSEUR -->
                @foreach($groupe['modeles'] as $modele)
                <tr>
                    <td class="font-bold">{{ $modele['designation'] }}</td>
                    <td class="text-center"><span class="badge badge-stock">{{ $modele['qte_stock'] }}</span></td>
                    <td class="text-center"><span class="badge badge-sorti">{{ $modele['qte_sorti'] }}</span></td>
                    <td class="text-center"><span class="badge badge-total">{{ $modele['total'] }}</span></td>
                </tr>
                @endforeach

                <!-- ESPACE ENTRE FOURNISSEURS -->
                <tr class="no-break">
                    <td colspan="4" style="height: 8px; background: transparent; border: none;"></td>
                </tr>
            @empty
                <tr>
                    <td colspan="4" class="text-center" style="padding: 30px; font-size: 11px; color: #6c757d;">
                        AUCUN MODELE DANS CET INVENTAIRE
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <!-- ===== RECAPITULATIF GENERAL ===== -->
    <div class="total-general">
        <p class="title">RECAPITULATIF GENERAL DE L'INVENTAIRE</p>
        <div class="stats-line">
            <span class="stat-item">
                Fournisseurs :
                <span class="number fournisseurs">{{ count($groupes ?? []) }}</span>
            </span>
            <span class="stat-item">
                Modeles :
                <span class="number modeles">{{ $total_modeles ?? 0 }}</span>
            </span>
            <span class="stat-item">
                En stock :
                <span class="number stock">{{ $total_stock ?? 0 }}</span>
            </span>
            <span class="stat-item">
                Sortis :
                <span class="number sorti">{{ $total_sorti ?? 0 }}</span>
            </span>
            <span class="stat-item">
                Total general :
                <span class="number total">{{ $total_materiels ?? 0 }}</span>
            </span>
        </div>
    </div>

    <!-- ===== SIGNATURES ===== -->
    <div class="signatures-container">
        <table class="signatures-table">
            <tr>
                <td class="signature-box">
                    <div class="signature-title">RESPONSABLE MAGASIN</div>
                    <div style="margin-top: 6px;">
                        <span style="font-size: 7px; color: #6c757d;">Nom : <span class="underline" style="min-width:150px;"></span></span>
                    </div>
                    <div class="signature-line">
                        Signature : <span class="underline" style="min-width:150px;"></span>
                    </div>
                    <div style="margin-top:4px; font-size:7px; color:#6c757d;">
                        Date : <span class="underline" style="min-width:80px;"></span>
                    </div>
                </td>
                <td class="signature-spacer"></td>
                <td class="signature-box">
                    <div class="signature-title">AUDIT / DIRECTION</div>
                    <div style="margin-top: 2px; font-size: 7px; color: #6c757d;">
                        Observations :<br>
                        <span class="underline" style="display:block; min-height:30px; width:100%; margin-top:2px;"></span>
                    </div>
                    <div class="signature-line" style="margin-top: 4px;">
                        Cachet & Signature : <span class="underline" style="min-width:100px;"></span>
                    </div>
                    <div style="margin-top:2px; font-size:7px; color:#6c757d;">
                        Date : <span class="underline" style="min-width:80px;"></span>
                    </div>
                </td>
            </tr>
        </table>
    </div>

    <!-- ===== PIED DE PAGE ===== -->
    <div class="footer-section">
        <span>Inventaire TTI {{ $inventaire->annee ?? '' }} - Document officiel</span>
        <span style="margin: 0 15px;">|</span>
        <span>Genere le {{ \Carbon\Carbon::parse($date ?? now())->format('d/m/Y H:i:s') }}</span>
        <span style="margin: 0 15px;">|</span>
        <span class="page-number"></span>
    </div>

</body>
</html>
