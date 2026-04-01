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
            background-color: #d3d3d3 !important;
            font-weight: bold;
            font-size: 10px;
            padding: 8px 5px !important;
            border: 2px solid #000 !important;
        }

        .group-header-category {
            background-color: #f0f0f0 !important;
            font-weight: bold;
            font-style: italic;
            font-size: 9px;
            padding: 6px 5px 6px 20px !important;
            border-left: 1px solid #000 !important;
            border-right: 1px solid #000 !important;
        }

        /* ===== GROUPEMENT PAR MATÉRIEL ===== */
        .materiel-group {
            background-color: #fafafa !important;
            font-weight: bold;
            font-size: 9px;
            padding: 5px 5px 5px 30px !important;
            border-left: 1px solid #000 !important;
            border-right: 1px solid #000 !important;
        }

        /* ===== AFFICHAGE DES NUMÉROS DE SÉRIE ===== */
        .serie-list {
            margin-top: 2px;
            padding-left: 15px;
        }

        .serie-item {
            display: block;
            font-family: monospace;
            font-size: 8px;
            color: #333;
            margin-bottom: 1px;
        }

        /* ===== AFFICHAGE DES PIÈCES ===== */
        .pieces-container {
            margin-top: 3px;
            padding-left: 5px;
        }

        .piece-item {
            display: block;
            font-size: 7.5px;
            color: #555;
            margin-bottom: 1px;
        }

        .piece-badge {
            display: inline-block;
            background-color: #f0f0f0;
            border: 1px solid #aaa;
            padding: 1px 4px;
            margin-left: 3px;
            font-size: 6px;
        }

        /* ===== BADGES D'ÉTAT ===== */
        .badge {
            display: inline-block;
            padding: 2px 6px;
            border-radius: 10px;
            font-size: 7px;
            font-weight: bold;
            text-align: center;
        }

        .badge-success {
            background-color: #d4edda;
            border: 1px solid #28a745;
            color: #155724;
        }

        .badge-warning {
            background-color: #fff3cd;
            border: 1px solid #ffc107;
            color: #856404;
        }

        .badge-stock {
            background-color: #e0e0e0;
            border: 1px solid #666;
            color: #333;
            font-weight: normal;
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
            height: 70px;
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
            <span class="info-label">Total articles :</span>
            {{ $total_lignes ?? count($details) }}
        </div>
    </div>

    <!-- ===== TABLEAU DES MATÉRIELS ===== -->
    <table class="data-table">
        <thead>
            <tr>
                <th width="35%">DÉSIGNATION & SÉRIES</th>
                <th width="15%">CATÉGORIE</th>
                <th width="10%">ÉTAT</th>
                <th width="20%">FOURNISSEUR</th>
                <th width="20%">LOCALISATION</th>
            </tr>
        </thead>
        <tbody>
            @php
                $groupedBySupplier = collect($details)->groupBy('fournisseur');
            @endphp

            @forelse($groupedBySupplier as $fournisseur => $items)
                <!-- LIGNE DE GROUPEMENT : FOURNISSEUR -->
                <tr>
                    <td colspan="5" class="group-header-supplier">
                        <span style="margin-right: 30px;">FOURNISSEUR : {{ $fournisseur ?: 'NON RENSEIGNÉ' }}</span>
                        @php
                            $firstItem = $items->first();
                        @endphp
                        @if(!empty($firstItem['numero_contrat']) && $firstItem['numero_contrat'] !== 'N/A')
                            <span>CONTRAT : {{ $firstItem['numero_contrat'] }}</span>
                        @endif
                    </td>
                </tr>

                @foreach($items->groupBy('categorie') as $categorie => $categoryItems)
                    <!-- LIGNE DE GROUPEMENT : CATÉGORIE -->
                    <tr>
                        <td colspan="5" class="group-header-category">
                            CATÉGORIE : {{ $categorie ?: 'NON DÉFINIE' }} ({{ $categoryItems->count() }} article(s))
                        </td>
                    </tr>

                    @php
                        // Regrouper les matériels par nom/désignation
                        $groupedByDesignation = $categoryItems->groupBy('designation');
                    @endphp

                    @foreach($groupedByDesignation as $designation => $designationItems)
                        <!-- LIGNE DE GROUPEMENT : NOM DU MATÉRIEL -->
                        <tr>
                            <td colspan="5" class="materiel-group">
                                {{ strtoupper($designation) }} ({{ $designationItems->count() }} exemplaire(s))
                            </td>
                        </tr>

                        @foreach($designationItems as $item)
                        <tr>
                            <!-- COLONNE 1 : Désignation et Liste des séries -->
                            <td>
                                <!-- Liste des numéros de série -->
                                <div class="serie-list">
                                    <span class="serie-item">• {{ $item['numero_serie'] }}</span>
                                </div>

                                <!-- Affichage des pièces si existantes -->
                                @if(!empty($item['pieces']) && count($item['pieces']) > 0)
                                    <div class="pieces-container">
                                        <span style="font-size: 7px; font-weight: bold;">Pièces incluses:</span>
                                        @foreach($item['pieces'] as $piece)
                                            <span class="piece-item">
                                                - {{ $piece['nom'] }}
                                                @if(!empty($piece['demande']['service']))
                                                    <span class="piece-badge">(Sortie: {{ $piece['demande']['service'] }})</span>
                                                @endif
                                            </span>
                                        @endforeach
                                    </div>
                                @endif
                            </td>

                            <!-- COLONNE 2 : Catégorie -->
                            <td class="text-center">
                                {{ $item['categorie'] }}
                            </td>

                            <!-- COLONNE 3 : État -->
                            <td class="text-center">
                                @php
                                    $etat = $item['etat_materiel'] ?? 'N/A';
                                    $badgeClass = in_array($etat, ['Bon', 'Disponible', 'Neuf']) ? 'badge-success' : 'badge-warning';
                                @endphp
                                <span class="badge {{ $badgeClass }}">
                                    {{ $etat }}
                                </span>
                            </td>

                            <!-- COLONNE 4 : Fournisseur -->
                            <td>
                                {{ $item['fournisseur'] }}
                            </td>

                            <!-- COLONNE 5 : Localisation / Bénéficiaire -->
                            <td>
                                @if(!empty($item['demande']))
                                    <span class="font-bold">{{ $item['demande']['demandeur'] }}</span><br>
                                    <span style="font-size: 7px;">{{ $item['demande']['service'] }}</span>
                                @else
                                    <span class="badge badge-stock">EN STOCK</span>
                                @endif
                            </td>
                        </tr>
                        @endforeach
                    @endforeach
                @endforeach
            @empty
                <tr>
                    <td colspan="5" class="text-center" style="padding: 30px;">
                        <span style="font-size: 11px;">AUCUN ARTICLE DANS CET INVENTAIRE</span>
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <!-- ===== RÉCAPITULATIF PAR FOURNISSEUR ===== -->
    <div style="margin-top: 15px; margin-bottom: 20px;">
        <table style="width: 100%; border-collapse: collapse; font-size: 8px;">
            <tr style="background-color: #e0e0e0;">
                <th style="border: 1px solid #000; padding: 5px;">FOURNISSEUR</th>
                <th style="border: 1px solid #000; padding: 5px;">NB ARTICLES</th>
                <th style="border: 1px solid #000; padding: 5px;">CATÉGORIES</th>
            </tr>
            @php
                $recapBySupplier = collect($details)->groupBy('fournisseur')->map(function($items, $fournisseur) {
                    return [
                        'fournisseur' => $fournisseur,
                        'count' => $items->count(),
                        'categories' => $items->pluck('categorie')->unique()->implode(', '),
                    ];
                });
            @endphp
            @foreach($recapBySupplier as $recap)
                <tr>
                    <td style="border: 1px solid #000; padding: 4px;">{{ $recap['fournisseur'] }}</td>
                    <td style="border: 1px solid #000; padding: 4px; text-align: center;">{{ $recap['count'] }}</td>
                    <td style="border: 1px solid #000; padding: 4px;">{{ $recap['categories'] }}</td>
                </tr>
            @endforeach
        </table>
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
        <span>Inventaire TTI {{ $inventaire->annee }} - Document officiel - {{ count($details) }} articles archivés</span>
        <span style="margin-left: 20px;">Généré le {{ date('d/m/Y H:i:s') }}</span>
    </div>

</body>
</html>
