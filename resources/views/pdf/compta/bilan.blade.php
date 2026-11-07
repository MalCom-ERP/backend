@extends('pdf.layouts.base')

@section('title', 'Bilan Comptable SYSCOHADA')
@section('orientation', 'landscape')
@section('page_margin', '12mm 15mm 20mm 15mm')

@section('styles')
    <style>
        body {
            font-family: 'Helvetica', Arial, sans-serif;
            font-size: 8pt;
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

        .bilan-grid {
            width: 100%;
            border-collapse: collapse;
        }

        .bilan-col {
            width: 50%;
            vertical-align: top;
            padding: 0 4px;
        }

        .excel-table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
            margin-top: 5px;
            margin-bottom: 5px;
        }

        .excel-table th,
        .excel-table td {
            border: 1px solid #cbd5e1;
            padding: 4px 6px;
            word-wrap: break-word;
        }

        .excel-table th {
            background-color: #f8fafc;
            color: #334155;
            font-weight: bold;
            text-transform: uppercase;
            font-size: 7.5pt;
        }

        .th-main-actif {
            background-color: #dbeafe;
            color: #1e3a8a;
            font-size: 8.5pt;
            text-align: center;
            font-weight: bold;
        }

        .th-main-passif {
            background-color: #fef3c7;
            color: #92400e;
            font-size: 8.5pt;
            text-align: center;
            font-weight: bold;
        }

        .sub-header {
            background-color: #f1f5f9;
            font-weight: bold;
            font-size: 7.5pt;
            color: #334155;
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

        .signature-section {
            margin-top: 25px;
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
            margin-top: 15px;
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
                <td style="width: 55%; border: none; vertical-align: top;">
                    <div class="company-name">{{ $boutique->nom ?? 'MA BOUTIQUE' }}</div>
                    <div style="font-size: 8pt; color: #4b5563;">
                        {{ $boutique->adresse ?? 'Adresse non spécifiée' }} &bull;
                        Tél : {{ $boutique->telephone ?? '-' }}<br>
                        @if(!empty($boutique->nif)) NIF: {{ $boutique->nif }} &bull; @endif
                        @if(!empty($boutique->rccm)) RCCM: {{ $boutique->rccm }} @endif
                    </div>
                </td>
                <td style="width: 45%; border: none; vertical-align: top; text-align: right;">
                    <div class="doc-badge">BILAN COMPTABLE SYSCOHADA</div>
                    <div style="font-size: 7.5pt; margin-top: 4px; color: #374151;">
                        <strong>Système Comptable :</strong> SYSCOHADA Révisé &bull; UEMOA (FCFA)<br>
                        <strong>Exercice clos au :</strong> {{ $filters['end_date'] ?? now()->format('d/m/Y') }}<br>
                        <strong>Édité le :</strong> {{ now()->format('d/m/Y H:i') }}
                    </div>
                </td>
            </tr>
        </table>
    </div>

    <table class="bilan-grid">
        <tr>
            <!-- COLONNE GAUCHE: ACTIF -->
            <td class="bilan-col">
                <table class="excel-table">
                    <thead>
                        <tr>
                            <th colspan="3" class="th-main-actif">ACTIF (Emplois)</th>
                        </tr>
                        <tr>
                            <th style="width: 55px;" class="text-center">N°</th>
                            <th>Rubriques de l'Actif</th>
                            <th class="text-right" style="width: 90px;">Net (FCFA)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <!-- ACTIF IMMOBILISE -->
                        <tr class="sub-header">
                            <td colspan="3">ACTIF IMMOBILISÉ (CLASSE 2)</td>
                        </tr>
                        @forelse($bilan['actif']['immobilise'] as $a)
                            <tr>
                                <td class="text-center font-mono">{{ $a['numero'] }}</td>
                                <td>{{ $a['libelle'] }}</td>
                                <td class="text-right font-mono font-bold">{{ number_format($a['net'], 0, ',', ' ') }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="text-center" style="color:#94a3b8;">Néant</td></tr>
                        @endforelse

                        <!-- ACTIF CIRCULANT -->
                        <tr class="sub-header">
                            <td colspan="3">ACTIF CIRCULANT (STOCKS &amp; CRÉANCES)</td>
                        </tr>
                        @forelse($bilan['actif']['circulant'] as $a)
                            <tr>
                                <td class="text-center font-mono">{{ $a['numero'] }}</td>
                                <td>{{ $a['libelle'] }}</td>
                                <td class="text-right font-mono font-bold">{{ number_format($a['net'], 0, ',', ' ') }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="text-center" style="color:#94a3b8;">Néant</td></tr>
                        @endforelse

                        <!-- TRESORERIE ACTIF -->
                        <tr class="sub-header">
                            <td colspan="3">TRÉSORERIE-ACTIF (BANQUE &amp; CAISSE)</td>
                        </tr>
                        @forelse($bilan['actif']['tresorerie'] as $a)
                            <tr>
                                <td class="text-center font-mono">{{ $a['numero'] }}</td>
                                <td>{{ $a['libelle'] }}</td>
                                <td class="text-right font-mono font-bold">{{ number_format($a['net'], 0, ',', ' ') }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="text-center" style="color:#94a3b8;">Néant</td></tr>
                        @endforelse
                    </tbody>
                    <tfoot>
                        <tr style="background-color: #bfdbfe; font-weight: bold;">
                            <td colspan="2" class="text-right uppercase" style="padding: 6px;">TOTAL GÉNÉRAL ACTIF :</td>
                            <td class="text-right font-mono" style="font-size: 9pt; color: #1e3a8a;">
                                {{ number_format($bilan['actif']['total'], 0, ',', ' ') }}
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </td>

            <!-- COLONNE DROITE: PASSIF -->
            <td class="bilan-col">
                <table class="excel-table">
                    <thead>
                        <tr>
                            <th colspan="3" class="th-main-passif">PASSIF (Ressources)</th>
                        </tr>
                        <tr>
                            <th style="width: 55px;" class="text-center">N°</th>
                            <th>Rubriques du Passif</th>
                            <th class="text-right" style="width: 90px;">Net (FCFA)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <!-- CAPITAUX PROPRES -->
                        <tr class="sub-header">
                            <td colspan="3">CAPITAUX PROPRES &amp; RÉSULTAT</td>
                        </tr>
                        @forelse($bilan['passif']['capitaux'] as $p)
                            <tr>
                                <td class="text-center font-mono">{{ $p['numero'] }}</td>
                                <td>{{ $p['libelle'] }}</td>
                                <td class="text-right font-mono font-bold">{{ number_format($p['net'], 0, ',', ' ') }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="text-center" style="color:#94a3b8;">Néant</td></tr>
                        @endforelse

                        <!-- PASSIF CIRCULANT -->
                        <tr class="sub-header">
                            <td colspan="3">PASSIF CIRCULANT (DETTES FOURNISSEURS &amp; TIERS)</td>
                        </tr>
                        @forelse($bilan['passif']['circulant'] as $p)
                            <tr>
                                <td class="text-center font-mono">{{ $p['numero'] }}</td>
                                <td>{{ $p['libelle'] }}</td>
                                <td class="text-right font-mono font-bold">{{ number_format($p['net'], 0, ',', ' ') }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="text-center" style="color:#94a3b8;">Néant</td></tr>
                        @endforelse

                        <!-- TRESORERIE PASSIF -->
                        <tr class="sub-header">
                            <td colspan="3">TRÉSORERIE-PASSIF (DÉCOUVERTS)</td>
                        </tr>
                        @forelse($bilan['passif']['tresorerie'] as $p)
                            <tr>
                                <td class="text-center font-mono">{{ $p['numero'] }}</td>
                                <td>{{ $p['libelle'] }}</td>
                                <td class="text-right font-mono font-bold">{{ number_format($p['net'], 0, ',', ' ') }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="text-center" style="color:#94a3b8;">Néant</td></tr>
                        @endforelse
                    </tbody>
                    <tfoot>
                        <tr style="background-color: #fde68a; font-weight: bold;">
                            <td colspan="2" class="text-right uppercase" style="padding: 6px;">TOTAL GÉNÉRAL PASSIF :</td>
                            <td class="text-right font-mono" style="font-size: 9pt; color: #92400e;">
                                {{ number_format($bilan['passif']['total'], 0, ',', ' ') }}
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </td>
        </tr>
    </table>

    <div style="margin-top: 10px; background-color: #f8fafc; border: 1px solid #cbd5e1; padding: 6px 12px; text-align: center; font-size: 7.5pt; font-weight: bold; color: {{ $bilan['equilibre'] ? '#047857' : '#b91c1c' }};">
        {{ $bilan['equilibre'] ? '✓ ÉQUILIBRE DU BILAN VALIDÉ : TOTAL ACTIF = TOTAL PASSIF' : '⚠ DÉSÉQUILIBRE DU BILAN CONSTATÉ' }}
    </div>

    <div class="signature-section">
        <div class="signature-box">
            <div class="signature-label">Le Responsable Comptable</div>
        </div>
        <div class="signature-box">
            <div class="signature-label">Le Commissaire aux Comptes</div>
        </div>
        <div class="signature-box">
            <div class="signature-label">La Direction Générale</div>
        </div>
    </div>

    <div class="pdf-footer">
        {{ $boutique->nom ?? 'MalCom' }} &bull; Document officiel extrait du module Comptabilité SYSCOHADA Révisé
    </div>
@endsection
