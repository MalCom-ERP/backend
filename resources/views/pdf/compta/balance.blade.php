@extends('pdf.layouts.base')

@section('title', 'Balance Générale des Comptes')
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

        .excel-table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
            margin-top: 10px;
        }

        .excel-table th,
        .excel-table td {
            border: 1px solid #cbd5e1;
            padding: 5px 7px;
            word-wrap: break-word;
        }

        .excel-table th {
            background-color: #f8fafc;
            color: #334155;
            font-weight: bold;
            text-transform: uppercase;
            font-size: 7.5pt;
        }

        .th-group {
            background-color: #cbd5e1;
            text-align: center;
            font-weight: bold;
            font-size: 8pt;
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
                    <div class="doc-badge">BALANCE GÉNÉRALE DES COMPTES</div>
                    <div style="font-size: 7.5pt; margin-top: 4px; color: #374151;">
                        <strong>Système Comptable :</strong> SYSCOHADA Révisé &bull; Balance à 6 Colonnes (FCFA)<br>
                        <strong>Période :</strong> 
                        {{ $filters['start_date'] ?? 'Origine' }} au {{ $filters['end_date'] ?? now()->format('d/m/Y') }}<br>
                        <strong>Éditée le :</strong> {{ now()->format('d/m/Y H:i') }}
                    </div>
                </td>
            </tr>
        </table>
    </div>

    <table class="excel-table zebra">
        <thead>
            <tr>
                <th rowspan="2" style="width: 70px;" class="text-center">N° Compte</th>
                <th rowspan="2">Intitulé du Compte (SYSCOHADA)</th>
                <th colspan="2" class="th-group">Mouvements de la Période</th>
                <th colspan="2" class="th-group">Soldes de Clôture</th>
            </tr>
            <tr>
                <th class="text-right" style="width: 110px;">Débit (FCFA)</th>
                <th class="text-right" style="width: 110px;">Crédit (FCFA)</th>
                <th class="text-right" style="width: 110px;">Solde Débiteur</th>
                <th class="text-right" style="width: 110px;">Solde Créditeur</th>
            </tr>
        </thead>
        <tbody>
            @forelse($balance['comptes'] as $c)
                @php
                    $deb = $c['total_debit'] ?? $c['cumul_debit'] ?? 0;
                    $cred = $c['total_credit'] ?? $c['cumul_credit'] ?? 0;
                    $soldeDeb = $c['solde_debiteur'] ?? 0;
                    $soldeCred = $c['solde_crediteur'] ?? 0;
                @endphp
                <tr>
                    <td class="text-center font-mono font-bold" style="color: #1e1b4b;">
                        {{ $c['numero'] }}
                    </td>
                    <td>
                        {{ $c['libelle'] }}
                    </td>
                    <td class="text-right font-mono">
                        {{ $deb > 0 ? number_format($deb, 0, ',', ' ') : '-' }}
                    </td>
                    <td class="text-right font-mono">
                        {{ $cred > 0 ? number_format($cred, 0, ',', ' ') : '-' }}
                    </td>
                    <td class="text-right font-mono font-bold" style="color: {{ $soldeDeb > 0 ? '#047857' : '#64748b' }};">
                        {{ $soldeDeb > 0 ? number_format($soldeDeb, 0, ',', ' ') : '-' }}
                    </td>
                    <td class="text-right font-mono font-bold" style="color: {{ $soldeCred > 0 ? '#b91c1c' : '#64748b' }};">
                        {{ $soldeCred > 0 ? number_format($soldeCred, 0, ',', ' ') : '-' }}
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="text-center" style="padding: 20px; color: #64748b;">
                        Aucun mouvement comptable enregistré.
                    </td>
                </tr>
            @endforelse
        </tbody>
        <tfoot>
            @php
                $totDebit = $balance['totaux']['total_debit'] ?? $balance['totaux']['cumul_debit'] ?? 0;
                $totCredit = $balance['totaux']['total_credit'] ?? $balance['totaux']['cumul_credit'] ?? 0;
                $totSoldeDeb = $balance['totaux']['total_solde_debiteur'] ?? $balance['totaux']['solde_debiteur'] ?? 0;
                $totSoldeCred = $balance['totaux']['total_solde_crediteur'] ?? $balance['totaux']['solde_crediteur'] ?? 0;
                $mvtEquilibre = abs($totDebit - $totCredit) < 0.01;
                $soldeEquilibre = abs($totSoldeDeb - $totSoldeCred) < 0.01;
            @endphp
            <tr style="background-color: #d1d5db; font-weight: bold;">
                <td colspan="2" class="text-right uppercase" style="padding: 6px;">
                    TOTAUX DE LA BALANCE :
                </td>
                <td class="text-right font-mono" style="font-size: 8.5pt;">
                    {{ number_format($totDebit, 0, ',', ' ') }}
                </td>
                <td class="text-right font-mono" style="font-size: 8.5pt;">
                    {{ number_format($totCredit, 0, ',', ' ') }}
                </td>
                <td class="text-right font-mono" style="font-size: 8.5pt; color: #047857;">
                    {{ number_format($totSoldeDeb, 0, ',', ' ') }}
                </td>
                <td class="text-right font-mono" style="font-size: 8.5pt; color: #b91c1c;">
                    {{ number_format($totSoldeCred, 0, ',', ' ') }}
                </td>
            </tr>
            <tr style="background-color: #f3f4f6; font-size: 7.5pt;">
                <td colspan="2" class="text-right font-bold">CONTRÔLE D'ÉQUILIBRE :</td>
                <td colspan="2" class="text-center font-bold" style="color: {{ $mvtEquilibre ? '#047857' : '#dc2626' }};">
                    {{ $mvtEquilibre ? '✓ Équilibre Mouvements Validé' : '⚠ Écart Débit / Crédit' }}
                </td>
                <td colspan="2" class="text-center font-bold" style="color: {{ $soldeEquilibre ? '#047857' : '#dc2626' }};">
                    {{ $soldeEquilibre ? '✓ Équilibre Soldes Validé' : '⚠ Écart Soldes' }}
                </td>
            </tr>
        </tfoot>
    </table>

    <div class="signature-section">
        <div class="signature-box">
            <div class="signature-label">Le Responsable Comptable</div>
        </div>
        <div class="signature-box">
            <div class="signature-label">Le Contrôleur Financier</div>
        </div>
        <div class="signature-box">
            <div class="signature-label">La Direction Générale</div>
        </div>
    </div>

    <div class="pdf-footer">
        {{ $boutique->nom ?? 'MalCom' }} &bull; Document officiel extrait du module Comptabilité SYSCOHADA Révisé
    </div>
@endsection
