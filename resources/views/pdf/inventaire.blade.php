<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        @page { 
            margin: 1cm; 
        }
        body { 
            font-family: sans-serif; 
            font-size: 9px; 
            color: #000;
            line-height: 1.2;
        }
        
        /* En-tête du document */
        .header-table { 
            width: 100%; 
            border-bottom: 2px solid #000; 
            margin-bottom: 15px; 
            padding-bottom: 5px; 
        }
        
        /* Table des données */
        .data-table { 
            width: 100%; 
            border-collapse: collapse; 
            table-layout: fixed; 
            margin-bottom: 10px;
        }
        .data-table th { 
            background: #ffffff; 
            border: 1px solid #000; 
            padding: 5px; 
            font-size: 8px; 
            text-transform: uppercase;
        }
        .data-table td { 
            border: 1px solid #000; 
            padding: 4px; 
            vertical-align: top; 
            word-wrap: break-word; 
        }

        /* Groupement Niveau 1 : FOURNISSEUR + CONTRAT (Gris très clair pour l'économie d'encre) */
        .supplier-header { 
            background-color: #f2f2f2 !important; 
            font-weight: bold; 
            font-size: 10px; 
            text-align: left;
            padding: 6px 8px !important;
            border: 2px solid #000 !important;
        }

        /* Groupement Niveau 2 : CATEGORIE (Italique) */
        .category-header { 
            background-color: #ffffff !important;
            font-weight: bold; 
            font-style: italic;
            font-size: 9px; 
            padding: 4px 4px 4px 20px !important;
            border-right: 1px solid #000 !important;
            border-left: 1px solid #000 !important;
        }

        /* Liste des pièces / accessoires */
        .piece-list { margin-top: 2px; padding-left: 10px; }
        .piece-item { display: block; font-size: 7.5px; color: #333; }

        /* BLOC SIGNATURES COMPACT (Seulement à la fin) */
        .footer-signatures {
            margin-top: 15px;
            width: 100%;
            page-break-inside: avoid; /* Empêche de couper le bloc sur deux pages */
        }
        .sig-table { width: 100%; border-collapse: collapse; }
        .sig-box {
            width: 45%;
            border: 1px solid #000;
            height: 65px; /* Hauteur réduite pour économiser de l'espace */
            padding: 6px;
            vertical-align: top;
            font-size: 8px;
        }
        .spacer { width: 10%; }
        
        .text-center { text-align: center; }
        .bold { font-weight: bold; }
    </style>
</head>
<body>

    <table class="header-table">
        <tr>
            <td>
                <h2 style="margin:0; text-transform: uppercase;">{{ $title }}</h2>
                <div style="margin-top: 3px;">Année d'inventaire : <strong>{{ $inventaire->annee }}</strong></div>
            </td>
            <td style="text-align:right;">
                Édité le : {{ \Carbon\Carbon::parse($date)->format('d/m/Y') }}<br>
                Responsable : {{ $responsable }}
            </td>
        </tr>
    </table>

    <table class="data-table">
        <thead>
            <tr>
                <th width="40%">Désignation & Pièces Détachées</th>
                <th width="20%">N° de Série</th>
                <th width="10%">État</th>
                <th width="30%">Localisation / Bénéficiaire</th>
            </tr>
        </thead>
        <tbody>
            @php 
                // Groupement par Fournisseur
                $groupedBySupplier = collect($details)->groupBy('fournisseur'); 
            @endphp

            @foreach($groupedBySupplier as $fournisseur => $categoryGroups)
                @php 
                    // On récupère le contrat une seule fois pour le groupe
                    $firstItem = $categoryGroups->first();
                    $contrat = $firstItem['numero_contrat'] ?? 'N/A';
                @endphp
                
                <tr>
                    <td colspan="4" class="supplier-header">
                        FOURNISSEUR : {{ $fournisseur ?: 'NON RENSEIGNÉ' }} 
                        <span style="margin-left: 40px;">CONTRAT : {{ $contrat }}</span>
                    </td>
                </tr>

                @foreach($categoryGroups->groupBy('categorie') as $categorie => $items)
                    <tr>
                        <td colspan="4" class="category-header">
                            Famille : {{ $categorie ?: 'AUTRES' }} ({{ count($items) }} matériels)
                        </td>
                    </tr>

                    @foreach($items as $d)
                    <tr>
                        <td>
                            <div class="bold">{{ $d['designation'] }}</div>
                            @if(!empty($d['pieces']))
                                <div class="piece-list">
                                    @foreach($d['pieces'] as $p)
                                        <span class="piece-item">
                                            - {{ $p['nom'] }} 
                                            @if(isset($p['demande']['service']))
                                                (Sortie : {{ $p['demande']['service'] }})
                                            @endif
                                        </span>
                                    @endforeach
                                </div>
                            @endif
                        </td>
                        <td class="text-center" style="font-family: monospace;">{{ $d['numero_serie'] }}</td>
                        <td class="text-center">{{ $d['etat_materiel'] }}</td>
                        <td>
                            @if($d['demande'])
                                <strong>{{ $d['demande']['demandeur'] }}</strong><br>
                                Service : {{ $d['demande']['service'] }}
                            @else
                                <span style="color: #444;">DISPONIBLE (MAGASIN)</span>
                            @endif
                        </td>
                    </tr>
                    @endforeach
                @endforeach
            @endforeach
        </tbody>
    </table>

    <div class="footer-signatures">
        <table class="sig-table">
            <tr>
                <td class="sig-box">
                    <strong>Le Responsable Magasin :</strong>
                    <br><br><br>
                    <span style="font-size: 7px;">Signature : ..........................................</span>
                </td>
                <td class="spacer"></td>
                <td class="sig-box">
                    <strong>Audit / Direction :</strong>
                    <br>
                    <span style="font-size: 7px;">Obs : .......................................................</span>
                    <br><br>
                    <span style="font-size: 7px;">Cachet & Signature :</span>
                </td>
            </tr>
        </table>
        <div style="text-align: center; font-size: 7px; margin-top: 5px; color: #555;">
            Inventaire TTI - Page de clôture générée le {{ date('d/m/Y H:i') }}
        </div>
    </div>

</body>
</html>