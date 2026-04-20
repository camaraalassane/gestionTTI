<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <style>
        @page { margin: 1cm 1.5cm 1.5cm 1.5cm; }
        body { font-family: 'Helvetica', sans-serif; font-size: 11px; color: #333; margin: 0; padding: 0; }

        .header { margin-bottom: 10px; border-bottom: 2px solid #1a237e; padding-bottom: 5px; }
        .title { text-align: center; color: #1a237e; text-transform: uppercase; margin: 0; font-size: 16px; }

        .info-table { width: 100%; margin-bottom: 10px; border-spacing: 0; }
        .info-table td { padding: 2px 0; border: none; }

        .table-data { width: 100%; border-collapse: collapse; margin-top: 5px; }
        .table-data th { background-color: #1a237e; color: white; padding: 6px; font-size: 10px; text-align: left; }
        .table-data td { padding: 5px; border: 1px solid #ddd; }

        .stats-banner { background-color: #f5f5f5; padding: 8px; margin-bottom: 10px; border: 1px solid #ddd; }

        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .bold { font-weight: bold; }

        .signature-wrapper { margin-top: 30px; page-break-inside: avoid; }
        .signature-table { width: 100%; border-collapse: collapse; }
        .signature-table td { border: 1px solid #333; width: 48%; height: 80px; vertical-align: top; padding: 8px; text-align: center; }
        .spacer { width: 4%; border: none !important; }
    </style>
</head>
<body>

    @php
        $isGlobal = isset($is_global) && $is_global === true;
        $isLot = isset($is_lot) && $is_lot === true;

        // Formatage de la date directement sans fonction
        $dateFormatee = 'Date inconnue';
        if (isset($reception->date_livraison)) {
            if ($reception->date_livraison instanceof \Carbon\Carbon) {
                $dateFormatee = $reception->date_livraison->format('d/m/Y');
            } elseif (is_string($reception->date_livraison)) {
                if (preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $reception->date_livraison)) {
                    $dateFormatee = $reception->date_livraison;
                } else {
                    try {
                        $dateFormatee = \Carbon\Carbon::parse($reception->date_livraison)->format('d/m/Y');
                    } catch (\Exception $e) {
                        $dateFormatee = 'Date inconnue';
                    }
                }
            }
        }
    @endphp

    <div class="header">
        <h1 class="title">Fiche d'Inventaire Matériel</h1>
        <div style="text-align: center; font-size: 9px;">Généré le {{ $date }}</div>
    </div>

    {{-- Bloc Infos Contrat --}}
    <table class="info-table">
        <tr>
            <td>CONTRAT : <strong>{{ $reception->numero_contrat }}</strong></td>
            <td class="text-right">FOURNISSEUR : <strong>{{ $reception->fournisseur }}</strong></td>
        </tr>
        <tr>
            <td>
                @if($isGlobal)
                    DATE : <strong>Toutes les réceptions</strong>
                @elseif($isLot)
                    DATE RÉCEPTION : <strong>{{ $dateFormatee }}</strong>
                @else
                    DATE : <strong>{{ $dateFormatee }}</strong>
                @endif
            </td>
            <td class="text-right">
                @if($isGlobal)
                    TYPE : <strong>INVENTAIRE GLOBAL</strong>
                @else
                    TYPE : <strong>LOT DU {{ $dateFormatee }}</strong>
                @endif
            </td>
        </tr>
    </table>

    <div class="stats-banner">
        <strong>RÉSUMÉ :</strong>
        {{ $total_materiels }} Matériels |
        {{ $total_modeles }} Modèles |
        <span style="color:green">{{ $total_stock }} En Stock</span> |
        <span style="color:orange">{{ $total_sorti }} Sortis</span>
    </div>

    {{-- Tableau unique --}}
    <table class="table-data">
        <thead>
            <tr>
                <th width="55%">DÉSIGNATION DU MODÈLE</th>
                <th width="15%" class="text-center">EN STOCK</th>
                <th width="15%" class="text-center">SORTIS</th>
                <th width="15%" class="text-center">TOTAL</th>
            </tr>
        </thead>
        <tbody>
            @foreach($groupes as $groupe)
            <tr>
                <td class="bold">{{ $groupe['designation'] }}</td>
                <td class="text-center">{{ $groupe['qte_stock'] }}</td>
                <td class="text-center">{{ $groupe['qte_sorti'] }}</td>
                <td class="text-center">{{ $groupe['total'] }}</td>
            </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr style="background-color: #eee; font-weight: bold;">
                <td class="text-right">TOTAL GÉNÉRAL</td>
                <td class="text-center">{{ $total_stock }}</td>
                <td class="text-center">{{ $total_sorti }}</td>
                <td class="text-center">{{ $total_materiels }}</td>
            </tr>
        </tfoot>
    </table>

    {{-- Signature à la fin --}}
    <div class="signature-wrapper">
        <table class="signature-table">
            <tr>
                <td>
                    <div class="bold" style="text-decoration: underline;">L'Officier Matériel</div>
                    <div style="margin-top: 35px; font-size: 8px; color: #777;">Date, Nom et Signature</div>
                </td>
                <td class="spacer"></td>
                <td>
                    <div class="bold" style="text-decoration: underline;">Le Fournisseur / Livreur</div>
                    <div style="margin-top: 35px; font-size: 8px; color: #777;">Date, Nom et Signature</div>
                </td>
            </tr>
        </table>
    </div>

</body>
</html>
