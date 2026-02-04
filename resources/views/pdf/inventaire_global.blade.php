<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        @page { margin: 1cm 1.5cm 4cm 1.5cm; }
        body { font-family: sans-serif; font-size: 11px; color: #333; }

        #footer {
            position: fixed;
            bottom: -2.5cm;
            left: 0;
            right: 0;
            height: 3cm;
        }

        .header { border-bottom: 2px solid #1a237e; margin-bottom: 20px; padding-bottom: 10px; text-align: center; }
        .title { color: #1a237e; text-transform: uppercase; margin: 0; font-size: 16px; }

        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th { background-color: #1a237e; color: white; padding: 8px; text-align: left; font-size: 10px; }
        td { padding: 6px; border: 1px solid #ddd; vertical-align: top; }

        /* Style pour les pièces détachées */
        .pieces-list { margin-top: 5px; font-size: 9px; color: #666; }
        .piece-item { display: inline-block; margin-right: 10px; }
        .piece-out { color: #d32f2f; font-weight: bold; } /* Rouge pour les pièces sorties */

        .signature-table { width: 100%; border: none !important; }
        .signature-table td { border: 1px solid #333; width: 45%; height: 80px; vertical-align: top; text-align: center; }
    </style>
</head>
<body>

    <div id="footer">
        <table class="signature-table">
            <tr>
                <td><strong>Le Responsable Logistique</strong><br><small>(Nom et Signature)</small></td>
                <td style="border:none; width:10%;"></td>
                <td><strong>Direction Générale / Bénéficiaire</strong><br><small>(Nom et Signature)</small></td>
            </tr>
        </table>
    </div>

    <div class="header">
        <h1 class="title">Rapport d'Inventaire Matériel</h1>
        <div>
            @if(is_array($periode))
                Période du <strong>{{ $periode['debut'] ?? 'N/A' }}</strong> au <strong>{{ $periode['fin'] ?? 'N/A' }}</strong>
            @else
                <strong>{{ $periode }}</strong>
            @endif
        </div>
        <div style="margin-top: 5px;">Généré le {{ $date }}</div>
    </div>

    <table>
        <thead>
            <tr>
                <th width="40%">Désignation & Composants</th>
                <th width="20%">S/N Principal</th>
                <th width="15%">État</th>
                <th width="25%">Affectation Actuelle</th>
            </tr>
        </thead>
        <tbody>
            @forelse($materiels as $materiel)
                <tr>
                    <td>
                        <strong>{{ $materiel->nom }}</strong>

                        {{-- Liste des pièces --}}
                        @if($materiel->pieces && $materiel->pieces->count() > 0)
                            <div class="pieces-list">
                                <em>Composants :</em><br>
                                @foreach($materiel->pieces as $piece)
                                    <span class="piece-item">
                                        • {{ $piece->nom_piece }}
                                        @if($piece->demande_id)
                                            <span class="piece-out">(SORTI - CMD#{{ $piece->demande_id }})</span>
                                        @endif
                                    </span>
                                @endforeach
                            </div>
                        @endif
                    </td>
                    <td><code>{{ $materiel->numero_serie ?? 'N/A' }}</code></td>
                    <td style="text-transform: uppercase; font-weight: bold;">
                        {{ $materiel->etat }}
                    </td>
                    <td>
                        @if($materiel->demande)
                            {{ $materiel->demande->service_beneficiaire }}
                            <br><small>Reçu par: {{ $materiel->demande->demandeur_nom }}</small>
                        @else
                            <span style="color: #2e7d32;">✔ EN STOCK</span>
                        @endif
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="4" style="text-align: center;">Aucun matériel enregistré dans la base.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

</body>
</html>
