<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        @page { margin: 1.5cm; }
        body { font-family: sans-serif; font-size: 9px; color: #333; line-height: 1.4; }
        
        /* En-tête avec numérotation automatique */
        .footer { position: fixed; bottom: 0px; right: 0px; font-size: 8px; color: #999; }
        .page-number:after { content: counter(page); }

        .header { border-bottom: 2px solid #00796B; padding-bottom: 10px; margin-bottom: 15px; }
        .title { font-size: 16px; font-weight: bold; color: #00796B; }
        
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th { 
            background-color: #f8f9fa; 
            border: 1px solid #ccc; 
            padding: 8px 6px; 
            text-align: left; 
            font-size: 8px; 
            text-transform: uppercase; 
        }
        td { border: 1px solid #ccc; padding: 8px 6px; vertical-align: top; }
        
        .sn-main { font-weight: bold; color: #000; display: block; margin-bottom: 4px; }
        .piece-box { margin-top: 4px; padding-left: 5px; border-left: 2px solid #00796B; }
        .piece-text { color: #555; font-size: 8px; display: block; margin-bottom: 2px; }
        .badge { font-size: 7px; font-weight: bold; padding: 1px 3px; border-radius: 2px; }
        .badge-livre { color: #1976D2; }
        .badge-magasin { color: #2E7D32; }
    </style>
</head>
<body>
    <div class="header">
        <span class="title">TRAÇABILITÉ DES LIVRAISONS</span><br>
        MATÉRIEL : <strong>{{ strtoupper($nom) }}</strong> | Rapport du : {{ $date }}
    </div>

    <table>
        <thead>
            <tr>
                <th width="38%">Désignation & Composants</th>
                <th width="24%">Service Bénéficiaire</th>
                <th width="23%">Agent / Demandeur</th>
                <th width="15%" style="text-align: center;">Date Sortie</th>
            </tr>
        </thead>
        <tbody>
            @forelse($historique as $h)
                <tr style="page-break-inside: avoid;">
                    <td>
                        <span class="sn-main">UNITÉ : {{ $h->numero_serie }}</span>
                        @if($h->pieces && $h->pieces->count() > 0)
                            <div class="piece-box">
                                @foreach($h->pieces as $p)
                                    <div class="piece-text">
                                        &bull; {{ $p->nom_piece }} ({{ $p->numero_serie ?: 'N/A' }})
                                        <span class="badge {{ $p->demande_id ? 'badge-livre' : 'badge-magasin' }}">
                                            [{{ $p->demande_id ? 'LIVRÉ' : 'STOCK' }}]
                                        </span>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </td>
                    <td><strong>{{ $h->demande->service_beneficiaire ?? 'N/A' }}</strong></td>
                    <td>{{ $h->demande->demandeur_nom ?? 'N/A' }}</td>
                    <td style="text-align: center;">
                        {{ $h->updated_at ? $h->updated_at->format('d/m/Y') : '-' }}
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="4" style="text-align: center; padding: 20px; color: #888;">
                        Aucun historique disponible pour ce matériel.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div class="footer">Page <span class="page-number"></span></div>
</body>
</html>