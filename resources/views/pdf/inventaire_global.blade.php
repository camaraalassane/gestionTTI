<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        @page {
            size: A4;
            margin: 1.2cm;
        }
        body {
            font-family: 'Helvetica', 'Arial', sans-serif;
            font-size: 10px;
            color: #000;
            line-height: 1.4;
            margin: 0;
            padding: 0;
        }

        /* Header */
        .header-table {
            width: 100%;
            border-bottom: 2px solid #000;
            margin-bottom: 20px;
            padding-bottom: 10px;
        }

        /* Table principale */
        .data-table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }
        .data-table th {
            background: #f0f0f0;
            border: 1px solid #000;
            padding: 8px;
            font-size: 9px;
            text-transform: uppercase;
        }
        .data-table td {
            border: 1px solid #000;
            padding: 8px;
            vertical-align: top;
        }

        /* CATÉGORIE */
        .category-header {
            background-color: #333 !important;
            color: #fff !important;
            font-weight: bold;
            font-size: 11px;
            padding: 10px !important;
            border: 1px solid #000 !important;
            text-transform: uppercase;
        }

        /* MODÈLE */
        .model-group-row {
            background-color: #e9e9e9;
            font-weight: bold;
            font-size: 10px;
        }

        /* Pièces sur une seule ligne */
        .piece-item {
            display: block;
            font-size: 8.5px;
            padding: 3px 0;
            border-bottom: 1px solid #eee;
        }

        /* Signatures */
        .footer-signatures {
            margin-top: 30px;
            width: 100%;
            page-break-inside: avoid;
        }
        .sig-table { width: 100%; border-collapse: collapse; }
        .sig-box {
            width: 48%;
            border: 1px solid #000;
            height: 90px;
            padding: 10px;
            vertical-align: top;
        }
    </style>
</head>
<body>

    <table class="header-table">
        <tr>
            <td>
                <h1 style="margin:0; font-size: 18px;">INVENTAIRE TECHNIQUE PAR MATÉRIEL</h1>
                <div style="margin-top: 5px;">Période : <strong>{{ $periode }}</strong></div>
                <div style="margin-top: 5px; color: #d32f2f; font-weight: bold;">[ ÉTAT DES STOCKS PRÉSENTS AU MAGASIN ]</div>
            </td>
            <td style="text-align:right;">
                Date d'édition : {{ $date }}<br>
                <strong>Rapport Certifié</strong>
            </td>
        </tr>
    </table>

    <table class="data-table">
        <thead>
            <tr>
                <th width="45%">Désignation & Pièces (Stock)</th>
                <th width="20%">N° Série Principal</th>
                <th width="10%">État</th>
                <th width="25%">Localisation / Service</th>
            </tr>
        </thead>
        <tbody>
            @forelse($materielsGroupes as $nomCat => $groupesParModele)
                <tr>
                    <td colspan="4" class="category-header">CATÉGORIE : {{ $nomCat }}</td>
                </tr>

                @foreach($groupesParModele as $nomModele => $materiels)
                    <tr class="model-group-row">
                        <td colspan="4">
                            MODÈLE : {{ strtoupper($nomModele) }}
                            <span style="float: right;">Unités disponibles : {{ $materiels->count() }}</span>
                        </td>
                    </tr>

                    @foreach($materiels as $mat)
                        <tr>
                            <td style="padding-left: 15px;">
                                @forelse($mat->pieces as $p)
                                    <span class="piece-item">
                                        • {{ $p->nom_piece }} ({{ $p->numero_serie ?? 'N/A' }})
                                        <span style="color: #2e7d32; font-size: 7px;"> [EN STOCK]</span>
                                    </span>
                                @empty
                                    <span style="color: #999; font-style: italic;">Aucune pièce</span>
                                @endforelse
                            </td>
                            <td align="center"><strong>{{ $mat->numero_serie ?? 'N/A' }}</strong></td>
                            <td align="center">{{ $mat->etat ?? 'N/A' }}</td>
                            <td>
                                <span style="color: #2e7d32; font-weight: bold;">MAGASIN CENTRAL</span><br>
                                <small>Disponible pour affectation</small>
                            </td>
                        </tr>
                    @endforeach
                @endforeach
            @empty
                <tr>
                    <td colspan="4" style="text-align: center; padding: 20px;">
                        <strong>AUCUN MATÉRIEL EN STOCK</strong>
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div class="footer-signatures">
        <table class="sig-table">
            <tr>
                <td class="sig-box"><strong>OFFICIER MATERIEL :</strong></td>
                <td width="4%"></td>
                <td class="sig-box"><strong>SOUS DIRECTEUR ST :</strong></td>
            </tr>
        </table>
    </div>

</body>
</html>