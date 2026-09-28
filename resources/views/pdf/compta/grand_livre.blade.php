@extends('pdf.layouts.base')

@section('title', 'Grand Livre des Comptes')
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

        .account-box {
            margin-top: 14px;
            margin-bottom: 6px;
            border: 1px solid #cbd5e1;
            background-color: #f8fafc;
            padding: 5px 8px;
            border-radius: 4px;
        }

        .account-num {
            background-color: #334155;
            color: #fff;
            font-family: 'Courier', monospace;
            font-weight: bold;
            padding: 2px 6px;
            border-radius: 3px;
            font-size: 8pt;
        }

        .account-title {
            font-size: 9pt;
            font-weight: bold;
            color: #0f172a;
            margin-left: 6px;
        }

        .excel-table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
            margin-bottom: 8px;
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
            font-size: 7pt;
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
                    <div class="doc-badge">GRAND LIVRE DES COMPTES</div>
                    <div style="font-size: 7.5pt; margin-top: 4px; color: #374151;">
                        <strong>Système Comptable :</strong> SYSCOHADA Révisé &bull; UEMOA (FCFA)<br>
                        <strong>Période :</strong> 
                        {{ $filters['start_date'] ?? 'Origine' }} au {{ $filters['end_date'] ?? now()->format('d/m/Y') }}<br>
                        <strong>Compte :</strong> {{ $filters['compte_libelle'] ?? 'Tous les comptes mouvementés' }} &bull; 
                        <strong>Édité le :</strong> {{ now()->format('d/m/Y H:i') }}
                    </div>
                </td>
            </tr>
        </table>
    </div>

    @forelse($grandLivre as $item)
        <div class="account-box">
            <table style="width: 100%; border: none;">
                <tr>
                    <td style="border: none;">
                        <span class="account-num">{{ $item['compte']['numero'] }}</span>
                        <span class="account-title">{{ $item['compte']['libelle'] }}</span>
                    </td>
                    <td style="border: none; text-align: right; font-size: 7.5pt;">
                        Débit : <strong>{{ number_format($item['total_debit'], 0, ',', ' ') }} F</strong> &bull;
                        Crédit : <strong>{{ number_format($item['total_credit'], 0, ',', ' ') }} F</strong> &bull;
                        Solde : <strong>{{ $item['solde_final'] >= 0 ? 'Débiteur' : 'Créditeur' }} {{ number_format(abs($item['solde_final']), 0, ',', ' ') }} FCFA</strong>
                    </td>
                </tr>
            </table>
        </div>

        <table class="excel-table zebra">
            <thead>
                <tr>
                    <th style="width: 70px;">Date</th>
                    <th style="width: 90px;">N° Pièce</th>
                    <th style="width: 50px;">Journal</th>
                    <th>Libellé du mouvement</th>
                    <th class="text-right" style="width: 100px;">Débit (FCFA)</th>
                    <th class="text-right" style="width: 100px;">Crédit (FCFA)</th>
                    <th class="text-right" style="width: 110px;">Solde Progressif</th>
                </tr>
            </thead>
            <tbody>
                @foreach($item['mouvements'] as $m)
                    <tr>
                        <td class="text-center">{{ \Carbon\Carbon::parse($m['date'])->format('d/m/Y') }}</td>
                        <td class="font-bold text-center" style="color: #4338ca;">{{ $m['numero_piece'] }}</td>
                        <td class="text-center">{{ $m['journal'] }}</td>
                        <td>{{ $m['libelle'] }}</td>
                        <td class="text-right font-mono font-bold">{{ $m['debit'] > 0 ? number_format($m['debit'], 0, ',', ' ') : '-' }}</td>
                        <td class="text-right font-mono font-bold">{{ $m['credit'] > 0 ? number_format($m['credit'], 0, ',', ' ') : '-' }}</td>
                        <td class="text-right font-mono font-bold" style="color: {{ $m['solde_progressif'] >= 0 ? '#047857' : '#b91c1c' }};">
                            {{ number_format($m['solde_progressif'], 0, ',', ' ') }}
                        </td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr style="background-color: #cbd5e1; font-weight: bold;">
                    <td colspan="4" class="text-right uppercase">Total Mouvements Compte {{ $item['compte']['numero'] }} :</td>
                    <td class="text-right font-mono">{{ number_format($item['total_debit'], 0, ',', ' ') }}</td>
                    <td class="text-right font-mono">{{ number_format($item['total_credit'], 0, ',', ' ') }}</td>
                    <td class="text-right font-mono">{{ number_format($item['solde_final'], 0, ',', ' ') }}</td>
                </tr>
            </tfoot>
        </table>
    @empty
        <div style="border: 1px solid #cbd5e1; padding: 30px; text-align: center; color: #64748b; background: #f8fafc;">
            Aucun compte mouvementé pour la période sélectionnée.
        </div>
    @endforelse

    <div class="signature-section">
        <div class="signature-box">
            <div class="signature-label">Le Responsable Comptable</div>
        </div>
        <div class="signature-box">
            <div class="signature-label">Le Gestionnaire / Caisse</div>
        </div>
        <div class="signature-box">
            <div class="signature-label">La Direction Générale</div>
        </div>
    </div>

    <div class="pdf-footer">
        {{ $boutique->nom ?? 'MalCom' }} &bull; Document officiel extrait du module Comptabilité SYSCOHADA Révisé
    </div>
@endsection
