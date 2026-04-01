<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>{{ $titre }}</title>
    <style>
        @page { margin: 1cm; }
        body {
            font-family: 'Helvetica', Arial, sans-serif;
            font-size: 11px;
            color: #37474f;
            line-height: 1.4;
            margin: 0;
        }

        /* En-tête principal */
        .header-main {
            text-align: center;
            margin-bottom: 20px;
            border-bottom: 2px solid #455a64;
            padding-bottom: 10px;
        }
        .header-main h2 { margin: 0; color: #263238; text-transform: uppercase; font-size: 18px; }
        .subtitle { margin-top: 5px; font-size: 12px; font-weight: bold; color: #00796b; }
        .meta-info { font-style: italic; font-size: 9px; color: #78909c; margin-top: 5px; }

        /* Bandeau Date */
        .date-header {
            background-color: #455a64;
            color: white;
            padding: 6px 12px;
            margin-top: 20px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 1px;
            border-radius: 3px;
        }

        /* Boîte Service + Demandeur */
        .service-demandeur-box {
            background-color: #f8fafc;
            padding: 8px 12px;
            border: 1px solid #cfd8dc;
            border-left: 4px solid #009688;
            margin-top: 10px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .service-name {
            font-weight: bold;
            font-size: 12px;
            color: #263238;
        }
        .demandeur-name {
            font-size: 11px;
            color: #00796b;
            background-color: #e0f2f1;
            padding: 3px 10px;
            border-radius: 20px;
            font-weight: bold;
        }

        /* Boîte Commande */
        .commande-box {
            background-color: #e0f2f1;
            padding: 6px 10px;
            margin-top: 8px;
            border-left: 4px solid #00796b;
            font-weight: bold;
            font-size: 11px;
            color: #00695c;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .commande-number {
            font-size: 12px;
            color: #004d40;
        }
        .commande-chip {
            background-color: #00796b;
            color: white;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 9px;
        }

        /* Tableau */
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
            table-layout: fixed;
        }
        th {
            background-color: #eceff1;
            color: #455a64;
            font-size: 9px;
            text-transform: uppercase;
            padding: 8px;
            border-bottom: 2px solid #cfd8dc;
            text-align: left;
        }
        td {
            padding: 8px;
            border-bottom: 1px solid #eceff1;
            vertical-align: top;
            word-wrap: break-word;
        }

        .item-name {
            font-weight: bold;
            color: #1a237e;
            display: block;
        }
        .sn-container {
            font-size: 9px;
            color: #263238;
        }
        .piece-detail {
            font-size: 8px;
            color: #00796b;
            margin-top: 3px;
            font-style: italic;
            border-top: 1px dashed #cfd8dc;
            padding-top: 4px;
        }
        .qty {
            text-align: center;
            font-weight: bold;
        }

        footer {
            position: fixed;
            bottom: -10px;
            left: 0;
            right: 0;
            text-align: center;
            font-size: 9px;
            color: #90a4ae;
        }
    </style>
</head>
<body>

    <div class="header-main">
        <h2>{{ $titre }}</h2>
        <div class="subtitle">{{ $sousTitre }}</div>
        <div class="meta-info">
            Généré le {{ date('d/m/Y') }} à {{ date('H:i') }}
        </div>
    </div>

    @forelse($donnees as $date => $services)
        <div class="date-header">
            {{ \Carbon\Carbon::parse($date)->translatedFormat('l d F Y') }}
        </div>

        @foreach($services as $serviceName => $demandeurs)
            @foreach($demandeurs as $demandeurNom => $commandes)
                <!-- Boîte Service + Demandeur unique -->
                <div class="service-demandeur-box">
                    <span class="service-name">UNITÉ : {{ strtoupper($serviceName) }}</span>
                    <span class="demandeur-name">
    <span style="margin-right: 5px; font-weight: normal; color: #455a64;">Receveur :</span>
    <span style="font-weight: bold;">{{ $demandeurNom }}</span>
</span>
                </div>

                @foreach($commandes as $numComande => $items)
                    <!-- Boîte Commande -->
                    <div class="commande-box">
                        <span class="commande-number">#{{ $numComande }}</span>
                        <span class="commande-chip">{{ count($items) }} article(s)</span>
                    </div>

                    <table>
                        <thead>
                            <tr>
                                <th width="45%">Désignation</th>
                                <th width="45%">Numéros de Série (S/N)</th>
                                <th width="10%" style="text-align:center">Qté</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($items as $item)
                                <tr>
                                    <td>
                                        <span class="item-name">{{ $item->nom_materiel }}</span>
                                    </td>
                                    <td>
                                        <div class="sn-container">
                                            @if($item->numero_serie)
                                                <strong>S/N : {{ $item->numero_serie }}</strong>
                                            @endif

                                            @if($item->pieces && $item->pieces->isNotEmpty())
                                                <div class="piece-detail">
                                                    @foreach($item->pieces as $piece)
                                                        • {{ $piece->nom_piece }} : {{ $piece->numero_serie ?: 'N/A' }}<br>
                                                    @endforeach
                                                </div>
                                            @endif
                                        </div>
                                    </td>
                                    <td class="qty">
                                        {{ (int)$item->nbredemande === 0 ? 'PCS' : $item->nbredemande }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endforeach
            @endforeach
        @endforeach
    @empty
        <div style="text-align: center; padding: 50px; color: #90a4ae;">
            <h3>Aucune donnée disponible pour cette période.</h3>
        </div>
    @endforelse

    <footer>
        Logiciel de Gestion TTI - Page
        <script type="text/php">
            if (isset($pdf)) {
                $x = 280; $y = 820; $text = "{PAGE_NUM} / {PAGE_COUNT}";
                $font = $fontMetrics->get_font("helvetica", "bold");
                $size = 8; $color = array(0.5, 0.5, 0.5);
                $pdf->page_text($x, $y, $text, $font, $size, $color);
            }
        </script>
    </footer>

</body>
</html>
