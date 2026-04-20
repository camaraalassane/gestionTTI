<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Inventaire du Matériel</title>
    <style>
        @page {
            margin: 1cm 1.5cm 1.5cm 1.5cm;
        }

        body {
            font-family: 'Helvetica', sans-serif;
            font-size: 10px;
            color: #333;
            margin: 0;
            padding: 0;
        }

        .header {
            margin-bottom: 15px;
            border-bottom: 2px solid #1a237e;
            padding-bottom: 8px;
        }

        .title {
            text-align: center;
            color: #1a237e;
            text-transform: uppercase;
            margin: 0;
            font-size: 16px;
        }

        .stats-banner {
            background-color: #f5f5f5;
            padding: 8px;
            margin-bottom: 15px;
            border: 1px solid #ddd;
            display: flex;
            justify-content: space-between;
            flex-wrap: wrap;
        }

        .stats-item {
            margin-right: 15px;
            font-size: 9px;
        }

        .stats-value {
            font-weight: bold;
            font-size: 11px;
        }

        .categorie-header {
            background-color: #e8eaf6;
            padding: 8px;
            margin-top: 15px;
            margin-bottom: 5px;
            font-weight: bold;
            color: #1a237e;
            border-left: 3px solid #1a237e;
        }

        .categorie-header span {
            float: right;
            font-size: 8px;
            font-weight: normal;
        }

        .table-data {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 10px;
        }

        .table-data th {
            background-color: #1a237e;
            color: white;
            padding: 6px;
            font-size: 8px;
            text-align: left;
        }

        .table-data td {
            padding: 5px;
            border: 1px solid #ddd;
            font-size: 9px;
        }

        .text-center {
            text-align: center;
        }

        .text-right {
            text-align: right;
        }

        .bold {
            font-weight: bold;
        }

        .badge-stock {
            color: #2e7d32;
            font-weight: bold;
        }

        .badge-sorti {
            color: #ed6c02;
            font-weight: bold;
        }

        .signature-table {
            width: 100%;
            margin-top: 30px;
            border-collapse: collapse;
            page-break-inside: avoid;
        }

        .signature-table td {
            border: 1px solid #333;
            width: 45%;
            height: 80px;
            vertical-align: top;
            padding: 10px;
        }

        .signature-space {
            width: 10%;
            border: none;
        }

        .sig-label {
            font-weight: bold;
            text-decoration: underline;
            display: block;
            margin-bottom: 10px;
        }

        /* Pied de page uniquement sur la dernière page */
        .footer {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            text-align: center;
            font-size: 8px;
            color: #777;
            padding: 10px;
            border-top: 1px solid #ddd;
            background: white;
        }
    </style>
</head>
<body>

    <div class="header">
        <h1 class="title">INVENTAIRE DU MATÉRIEL</h1>
        <div style="text-align: center; font-size: 9px; margin-top: 5px;">
            Généré le {{ $date }}
        </div>
    </div>

    <div class="stats-banner">
        <div class="stats-item">
             TOTAL : <span class="stats-value">{{ $stats['total'] ?? 0 }}</span>
        </div>
        <div class="stats-item">
             DISPONIBLE : <span class="stats-value badge-stock">{{ $stats['disponible'] ?? 0 }}</span>
        </div>
        <div class="stats-item">
             LIVRÉS : <span class="stats-value">{{ $stats['livres'] ?? 0 }}</span>
        </div>
        <div class="stats-item">
             PIÈCES : <span class="stats-value">{{ $stats['pieces_sorties'] ?? 0 }}</span>
        </div>
    </div>

    @forelse($categories as $categorie)
        <div class="categorie-header">
             CATÉGORIE : {{ $categorie['nom'] }}
            <span>
                {{ count($categorie['modeleMateriels']) }} modèle(s) |
                 {{ collect($categorie['modeleMateriels'])->sum('qte_materiel') }} unités
            </span>
        </div>

        <table class="table-data">
            <thead>
                <tr>
                    <th width="55%">DÉSIGNATION</th>
                    <th width="15%" class="text-center">UNITÉS (MAGASIN)</th>
                    <th width="15%" class="text-center">UNITÉS (LIVRÉES)</th>
                    <th width="15%" class="text-center">PIÈCES (MAGASIN)</th>
                </tr>
            </thead>
            <tbody>
                @foreach($categorie['modeleMateriels'] as $modele)
                <tr>
                    <td class="bold">{{ $modele['nom'] }}</td>
                    <td class="text-center">
                        <span class="badge-stock">{{ $modele['qte_materiel'] }}</span>
                    </td>
                    <td class="text-center">
                        <span class="badge-sorti">{{ $modele['qte_livree'] }}</span>
                    </td>
                    <td class="text-center">{{ $modele['qte_pieces'] }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    @empty
        <div class="text-center pa-8">
            <p>Aucune catégorie trouvée</p>
        </div>
    @endforelse

    <div class="signature-table">
        <table style="width: 100%;">
            <tr>
                <td>
                    <div class="sig-label">Officier matériel</div>
                    <div style="margin-top: 30px; font-size: 9px; color: #777;">Nom, Date et Signature</div>
                </td>
                <td class="signature-space"></td>
                <td>
                    <div class="sig-label">Le Fournisseur / Livreur</div>
                    <div style="margin-top: 30px; font-size: 9px; color: #777;">Nom, Date et Signature</div>
                </td>
            </tr>
        </table>
    </div>

    <!-- Pied de page - apparaît uniquement en bas de la dernière page -->
    <div class="footer">
        Document généré automatiquement par le système de gestion de matériel
    </div>

</body>
</html>
