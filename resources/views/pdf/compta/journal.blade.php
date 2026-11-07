@extends('pdf.layouts.base')

@section('title', 'Journal Général des Écritures')
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
            padding: 5px 6px;
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
            background-color: #f9fafb;
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
                    <div class="doc-badge">JOURNAL GÉNÉRAL DES ÉCRITURES</div>
                    <div style="font-size: 7.5pt; margin-top: 4px; color: #374151;">
                        <strong>Système Comptable :</strong> SYSCOHADA Révisé &bull; UEMOA (FCFA)<br>
                        <strong>Période :</strong> 
                        {{ $filters['start_date'] ?? 'Origine' }} au {{ $filters['end_date'] ?? now()->format('d/m/Y') }}<br>
                        <strong>Journal :</strong> {{ $filters['journal_libelle'] ?? 'Tous' }} &bull; 
                        <strong>Édité le :</strong> {{ now()->format('d/m/Y H:i') }}
                    </div>
                </td>
            </tr>
        </table>
    </div>

    <table class="excel-table zebra">
        <thead>
            <tr>
                <th style="width: 70px;">Date</th>
                <th style="width: 90px;">N° Pièce</th>
                <th style="width: 60px;">Journal</th>
                <th style="width: 65px;">N° Compte</th>
                <th>Libellé de l'Écriture &amp; Intitulé</th>
                <th class="text-right" style="width: 110px;">Débit (FCFA)</th>
                <th class="text-right" style="width: 110px;">Crédit (FCFA)</th>
            </tr>
        </thead>
        <tbody>
            @forelse($ecritures as $e)
                @foreach($e->lignes as $index => $l)
                    <tr>
                        @if($index === 0)
                            <td rowspan="{{ count($e->lignes) }}" class="text-center" style="vertical-align: top; font-weight: bold;">
                                {{ \Carbon\Carbon::parse($e->date_ecriture)->format('d/m/Y') }}
                            </td>
                            <td rowspan="{{ count($e->lignes) }}" style="vertical-align: top; font-weight: bold; color: #1e1b4b;">
                                {{ $e->numero_piece }}
                            </td>
                            <td rowspan="{{ count($e->lignes) }}" class="text-center font-bold" style="vertical-align: top;">
                                {{ $e->journal->code ?? 'OD' }}
                            </td>
                        @endif
                        <td class="text-center font-bold font-mono">
                            {{ $l->compte->numero ?? '-' }}
                        </td>
                        <td>
                            <strong>{{ $e->libelle }}</strong> 
                            <span style="color: #4b5563; font-size: 7pt;">({{ $l->compte->libelle ?? '-' }})</span>
                        </td>
                        <td class="text-right font-bold font-mono">
                            {{ $l->debit > 0 ? number_format($l->debit, 0, ',', ' ') : '-' }}
                        </td>
                        <td class="text-right font-bold font-mono">
                            {{ $l->credit > 0 ? number_format($l->credit, 0, ',', ' ') : '-' }}
                        </td>
                    </tr>
                @endforeach
            @empty
                <tr>
                    <td colspan="7" class="text-center" style="padding: 20px; color: #6b7280;">
                        Aucune écriture enregistrée pour les critères sélectionnés.
                    </td>
                </tr>
            @endforelse
        </tbody>
        <tfoot>
            <tr style="background-color: #d1d5db; font-weight: bold;">
                <td colspan="5" class="text-right uppercase" style="padding: 6px;">
                    TOTAUX GÉNÉRAUX DU JOURNAL :
                </td>
                <td class="text-right font-mono" style="font-size: 8.5pt;">
                    {{ number_format($totalDebit, 0, ',', ' ') }}
                </td>
                <td class="text-right font-mono" style="font-size: 8.5pt;">
                    {{ number_format($totalCredit, 0, ',', ' ') }}
                </td>
            </tr>
        </tfoot>
    </table>

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
        {{ $boutique->nom ?? 'MalCom' }} &bull; Document officiel extrait du module Comptabilité SYSCOHADA Révisé &bull; Page 1
    </div>
@endsection
