@extends('pdf.layouts.base')

@section('title', 'Compte de Résultat SYSCOHADA')
@section('orientation', 'portrait')
@section('page_margin', '12mm 15mm 20mm 15mm')

@section('styles')
    <style>
        body {
            font-family: 'Helvetica', Arial, sans-serif;
            font-size: 8.5pt;
            color: #111;
        }

        .header-box {
            border-bottom: 1.5px solid #cbd5e1;
            padding-bottom: 10px;
            margin-bottom: 14px;
        }

        .company-name {
            font-size: 15pt;
            font-weight: 900;
            color: #0f172a;
            margin-bottom: 2px;
        }

        .doc-badge {
            background-color: #1e293b;
            color: #fff;
            padding: 5px 12px;
            text-align: center;
            font-size: 11pt;
            font-weight: bold;
            border-radius: 4px;
        }

        .section-header {
            background-color: #f8fafc;
            border-left: 3px solid #334155;
            padding: 6px 10px;
            font-weight: bold;
            font-size: 9pt;
            color: #0f172a;
            margin-top: 14px;
            margin-bottom: 5px;
        }

        .section-charges {
            border-left-color: #b91c1c;
        }

        .section-produits {
            border-left-color: #047857;
        }

        .excel-table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
            margin-bottom: 5px;
        }

        .excel-table th,
        .excel-table td {
            border: 1px solid #cbd5e1;
            padding: 5px 8px;
            word-wrap: break-word;
        }

        .excel-table th {
            background-color: #f8fafc;
            color: #334155;
            font-weight: bold;
            text-transform: uppercase;
            font-size: 7.5pt;
        }

        .zebra tr:nth-child(even) {
            background-color: #f8fafc;
        }

        .text-right {
            text-align: right;
        }

        .text-center {
            text-align: center;
        }

        .font-bold {
            font-weight: bold;
        }

        .font-mono {
            font-family: 'Courier', monospace;
        }

        .result-box {
            margin-top: 20px;
            border: 2px solid {{ $resultat['resultat_net'] >= 0 ? '#047857' : '#b91c1c' }};
            background-color: {{ $resultat['resultat_net'] >= 0 ? '#ecfdf5' : '#fef2f2' }};
            padding: 12px 16px;
            display: table;
            width: 100%;
        }

        .signature-section {
            margin-top: 30px;
            display: table;
            width: 100%;
        }

        .signature-box {
            display: table-cell;
            width: 33.33%;
            border-right: 1px dashed #aaa;
            padding: 8px;
            text-align: center;
            height: 60px;
            vertical-align: top;
        }

        .signature-box:last-child {
            border-right: none;
        }

        .signature-label {
            font-size: 8pt;
            font-weight: bold;
            text-decoration: underline;
            margin-bottom: 30px;
        }

        .pdf-footer {
            margin-top: 20px;
            text-align: center;
            font-size: 7pt;
            color: #666;
            border-top: 1px dotted #ccc;
            padding-top: 6px;
        }
    </style>
@endsection

@section('content')
    <div class="header-box">
        <table style="width: 100%; border: none;">
            <tr>
                <td style="width: 50%; border: none; vertical-align: top;">
                    <div class="company-name">{{ $boutique->nom ?? 'MA BOUTIQUE' }}</div>
                    <div style="font-size: 8pt; color: #4b5563;">
                        {{ $boutique->adresse ?? 'Adresse non spécifiée' }} &bull;
                        Tél : {{ $boutique->telephone ?? '-' }}<br>
                        @if(!empty($boutique->nif)) NIF: {{ $boutique->nif }} &bull; @endif
                        @if(!empty($boutique->rccm)) RCCM: {{ $boutique->rccm }} @endif
                    </div>
                </td>
                <td style="width: 50%; border: none; vertical-align: top; text-align: right;">
                    <div class="doc-badge">COMPTE DE RÉSULTAT SYSCOHADA</div>
                    <div style="font-size: 7.5pt; margin-top: 4px; color: #374151;">
                        <strong>Système Comptable :</strong> SYSCOHADA Révisé &bull; État Financier (FCFA)<br>
                        <strong>Exercice :</strong> {{ $filters['start_date'] ?? 'Origine' }} au {{ $filters['end_date'] ?? now()->format('d/m/Y') }}<br>
                        <strong>Édité le :</strong> {{ now()->format('d/m/Y H:i') }}
                    </div>
                </td>
            </tr>
        </table>
    </div>

    <!-- PRODUITS (CLASSE 7) -->
    <div class="section-header section-produits">
        PRODUITS D'EXPLOITATION &amp; REVENUS (CLASSE 7)
    </div>
    <table class="excel-table zebra">
        <thead>
            <tr>
                <th style="width: 80px;" class="text-center">N° Compte</th>
                <th>Intitulé du Produit</th>
                <th class="text-right" style="width: 150px;">Montant (FCFA)</th>
            </tr>
        </thead>
        <tbody>
            @forelse($resultat['produits'] as $p)
                <tr>
                    <td class="text-center font-mono font-bold">{{ $p['numero'] }}</td>
                    <td>{{ $p['libelle'] }}</td>
                    <td class="text-right font-mono font-bold" style="color: #047857;">
                        {{ number_format($p['montant'], 0, ',', ' ') }}
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="3" class="text-center" style="padding: 10px; color: #64748b;">
                        Aucun produit enregistré pour la période.
                    </td>
                </tr>
            @endforelse
        </tbody>
        <tfoot>
            <tr style="background-color: #d1fae5; font-weight: bold;">
                <td colspan="2" class="text-right uppercase">TOTAL DES PRODUITS (I) :</td>
                <td class="text-right font-mono" style="font-size: 9pt; color: #047857;">
                    {{ number_format($resultat['total_produits'], 0, ',', ' ') }} FCFA
                </td>
            </tr>
        </tfoot>
    </table>

    <!-- CHARGES (CLASSE 6) -->
    <div class="section-header section-charges">
        CHARGES D'EXPLOITATION &amp; FRAIS (CLASSE 6)
    </div>
    <table class="excel-table zebra">
        <thead>
            <tr>
                <th style="width: 80px;" class="text-center">N° Compte</th>
                <th>Intitulé de la Charge</th>
                <th class="text-right" style="width: 150px;">Montant (FCFA)</th>
            </tr>
        </thead>
        <tbody>
            @forelse($resultat['charges'] as $c)
                <tr>
                    <td class="text-center font-mono font-bold">{{ $c['numero'] }}</td>
                    <td>{{ $c['libelle'] }}</td>
                    <td class="text-right font-mono font-bold" style="color: #b91c1c;">
                        {{ number_format($c['montant'], 0, ',', ' ') }}
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="3" class="text-center" style="padding: 10px; color: #64748b;">
                        Aucune charge enregistrée pour la période.
                    </td>
                </tr>
            @endforelse
        </tbody>
        <tfoot>
            <tr style="background-color: #fee2e2; font-weight: bold;">
                <td colspan="2" class="text-right uppercase">TOTAL DES CHARGES (II) :</td>
                <td class="text-right font-mono" style="font-size: 9pt; color: #b91c1c;">
                    {{ number_format($resultat['total_charges'], 0, ',', ' ') }} FCFA
                </td>
            </tr>
        </tfoot>
    </table>

    <!-- SYNTHÈSE RÉSULTAT NET -->
    <div class="result-box">
        <table style="width: 100%; border: none;">
            <tr>
                <td style="border: none; vertical-align: middle;">
                    <span style="font-size: 11pt; font-weight: bold; text-transform: uppercase; color: {{ $resultat['resultat_net'] >= 0 ? '#065f46' : '#991b1b' }};">
                        RÉSULTAT NET DE L'EXERCICE (I - II) : {{ $resultat['statut_resultat'] }}
                    </span>
                    <div style="font-size: 8pt; color: #4b5563; margin-top: 2px;">
                        Marge nette calculée conformément aux normes SYSCOHADA Révisé.
                    </div>
                </td>
                <td style="border: none; vertical-align: middle; text-align: right;">
                    <span style="font-size: 16pt; font-weight: 900; font-family: 'Courier', monospace; color: {{ $resultat['resultat_net'] >= 0 ? '#047857' : '#b91c1c' }};">
                        {{ number_format($resultat['resultat_net'], 0, ',', ' ') }} FCFA
                    </span>
                </td>
            </tr>
        </table>
    </div>

    <div class="signature-section">
        <div class="signature-box">
            <div class="signature-label">Le Responsable Comptable</div>
        </div>
        <div class="signature-box">
            <div class="signature-label">Le Commissaire / Contrôle</div>
        </div>
        <div class="signature-box">
            <div class="signature-label">La Direction Générale</div>
        </div>
    </div>

    <div class="pdf-footer">
        {{ $boutique->nom ?? 'MalCom' }} &bull; Document officiel extrait du module Comptabilité SYSCOHADA Révisé
    </div>
@endsection
