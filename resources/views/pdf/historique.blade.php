<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>{{ $titre }}</title>
    <style>
        @page { margin: 1cm; }
        body { font-family: 'Helvetica', Arial, sans-serif; font-size: 11px; color: #37474f; line-height: 1.4; margin: 0; }
        .header-main { text-align: center; margin-bottom: 20px; border-bottom: 2px solid #455a64; padding-bottom: 10px; }
        .header-main h2 { margin: 0; color: #263238; text-transform: uppercase; font-size: 18px; }
        .subtitle { margin-top: 5px; font-size: 12px; font-weight: bold; color: #00796b; }
        .meta-info { font-style: italic; font-size: 9px; color: #78909c; margin-top: 5px; }
        .date-header { background-color: #455a64; color: white; padding: 6px 12px; margin-top: 20px; font-weight: bold; text-transform: uppercase; border-radius: 3px; }
        .service-demandeur-box { background-color: #f8fafc; padding: 8px 12px; border: 1px solid #cfd8dc; border-left: 4px solid #009688; margin-top: 10px; display: flex; align-items: center; justify-content: space-between; }
        .service-name { font-weight: bold; font-size: 12px; color: #263238; }
        .demandeur-name { font-size: 11px; color: #00796b; background-color: #e0f2f1; padding: 3px 10px; border-radius: 20px; font-weight: bold; }
        .commande-box { background-color: #e0f2f1; padding: 6px 10px; margin-top: 8px; border-left: 4px solid #00796b; font-weight: bold; font-size: 11px; color: #00695c; display: flex; align-items: center; justify-content: space-between; }
        .commande-number { font-size: 12px; color: #004d40; }
        .commande-date { font-size: 10px; color: #00695c; }
        .commande-chip { background-color: #00796b; color: white; padding: 2px 8px; border-radius: 12px; font-size: 9px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 20px; table-layout: fixed; }
        thead { display: table-header-group; }
        tr { page-break-inside: avoid; }
        th { background-color: #eceff1; color: #455a64; font-size: 9px; text-transform: uppercase; padding: 8px; border-bottom: 2px solid #cfd8dc; text-align: left; }
        td { padding: 8px; border-bottom: 1px solid #eceff1; vertical-align: top; word-wrap: break-word; }
        .item-name { font-weight: bold; color: #1a237e; display: block; }
        .sn-container { font-size: 9px; color: #263238; }
        .qty { text-align: center; font-weight: bold; }
    </style>
</head>
<body>
    <div class="header-main">
        <h2>{{ $titre }}</h2>
        <div class="subtitle">{{ $sousTitre }}</div>
        <div class="meta-info">Généré le {{ date('d/m/Y') }} à {{ date('H:i') }}</div>
    </div>

    @forelse($donnees as $date => $services)
        <div class="date-header">{{ \Carbon\Carbon::parse($date)->translatedFormat('l d F Y') }}</div>
        @foreach($services as $serviceName => $demandeurs)
            @foreach($demandeurs as $demandeurNom => $commandes)
                <div class="service-demandeur-box">
                    <span class="service-name">UNITE : {{ strtoupper($serviceName) }}</span>
                    <span class="demandeur-name">Receveur : {{ $demandeurNom }}</span>
                </div>
                @foreach($commandes as $numComande => $data)
                    <div class="commande-box">
                        <span class="commande-number">#{{ $numComande }}</span>
                        <span class="commande-date">Date: {{ \Carbon\Carbon::parse($data['date_commande'])->format('d/m/Y') }}</span>
                        <span class="commande-chip">{{ $data['demandes']->count() }} article(s)</span>
                    </div>
                    <table>
                        <thead>
                            <tr>
                                <th width="45%">Désignation</th>
                                <th width="45%">S/N</th>
                                <th width="10%">Qté</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($data['demandes'] as $item)
                            <tr>
                                <td><span class="item-name">{{ $item->nom_materiel }}</span></td>
                                <td>
                                    <div class="sn-container">
                                        @if($item->numero_serie)
                                            <strong>S/N : {{ $item->numero_serie }}</strong>
                                        @endif
                                    </div>
                                </td>
                                <td class="qty">{{ (int)$item->nbredemande === 0 ? 'PCS' : $item->nbredemande }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endforeach
            @endforeach
        @endforeach
    @empty
        <div style="text-align:center;padding:50px;color:#90a4ae;">
            <h3>Aucune donnée disponible pour cette période.</h3>
        </div>
    @endforelse
</body>
</html>
