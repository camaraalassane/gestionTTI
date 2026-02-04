<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <style>
        /* Configuration de la page */
        @page { 
            margin: 1cm 1.5cm 4cm 1.5cm; /* Marge du bas large (4cm) pour laisser la place aux signatures */
        }
        
        body { font-family: 'Helvetica', sans-serif; font-size: 11px; color: #333; }
        
        /* En-tête */
        .header { margin-bottom: 20px; border-bottom: 2px solid #1a237e; padding-bottom: 10px; }
        .title { text-align: center; color: #1a237e; text-transform: uppercase; margin: 0; font-size: 18px; }
        
        /* Tableaux de données */
        .table-data { width: 100%; border-collapse: collapse; margin-top: 10px; table-layout: fixed; }
        .table-data th { background-color: #1a237e; color: white; padding: 8px; font-size: 9px; text-align: left; }
        .table-data td { padding: 6px; border: 1px solid #ddd; word-wrap: break-word; }
        
        /* Lignes */
        .main-row { background-color: #f5f5f5; font-weight: bold; }
        .piece-row { color: #555; font-size: 10px; }
        .symbol { color: #999; margin-right: 5px; }

        /* POSITIONNEMENT FIXE EN BAS DE PAGE */
        #footer {
            position: fixed; 
            bottom: -2.5cm; /* Aligne tout en bas de la marge définie dans @page */
            left: 0px; 
            right: 0px;
            height: 3cm;
        }

        .signature-table { 
            width: 100%; 
            border-collapse: collapse;
        }
        .signature-table td { 
            border: 1px solid #333; 
            width: 45%;
            height: 100px; /* Hauteur fixe pour les boites */
            vertical-align: top; 
            padding: 10px; 
            text-align: center;
        }
        .signature-space { border: none !important; width: 10%; }
        .sig-label { font-weight: bold; text-decoration: underline; display: block; margin-bottom: 10px; }
    </style>
</head>
<body>

    <div id="footer">
        <table class="signature-table">
            <tr>
                <td>
                    <span class="sig-label">Le Responsable Logistique</span>
                    <div style="margin-top: 60px; font-size: 9px; color: #777;">Nom, Date et Signature</div>
                </td>
                <td class="signature-space"></td>
                <td>
                    <span class="sig-label">Le Fournisseur / Livreur</span>
                    <div style="margin-top: 60px; font-size: 9px; color: #777;">Nom, Date et Signature</div>
                </td>
            </tr>
        </table>
    </div>

    <div class="header">
        <h1 class="title">Fiche d'Inventaire Matériel</h1>
        <div style="text-align: center; margin-top: 5px;">Généré le {{ $date }}</div>
    </div>

    <table style="width: 100%; margin-bottom: 10px;">
        <tr>
            <td style="border:none; padding: 0;">CONTRAT : <strong>{{ $reception->numero_contrat }}</strong></td>
            <td style="border:none; padding: 0; text-align: right;">FOURNISSEUR : <strong>{{ $reception->fournisseur }}</strong></td>
        </tr>
    </table>

    <table class="table-data">
        <thead>
            <tr>
                <th width="45%">Désignation / Composants</th>
                <th width="25%">N° de Série</th>
                <th width="15%">État</th>
                <th width="15%">Statut</th>
            </tr>
        </thead>
        <tbody>
            @foreach($materiels as $mat)
                <tr class="main-row">
                    <td>{{ $mat->nom }}</td>
                    <td>{{ $mat->numero_serie }}</td>
                    <td>{{ $mat->etat }}</td>
                    <td>{{ $mat->statut }}</td>
                </tr>
                @foreach($mat->pieces as $piece)
                    <tr class="piece-row">
                        <td style="padding-left: 20px;">
                            <span class="symbol">></span> {{ $piece->nom_piece }}
                        </td>
                        <td>{{ $piece->numero_serie ?? 'N/A' }}</td>
                        <td>Pièce</td>
                        <td>{{ $piece->statut }}</td>
                    </tr>
                @endforeach
            @endforeach
        </tbody>
    </table>

</body>
</html>