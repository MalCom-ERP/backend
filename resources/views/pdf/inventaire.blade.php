@extends('pdf.layouts.base')

@section('title', 'Rapport d\'Audit des Stocks & Mouvements')
@section('orientation', 'landscape')

@section('fixed_footer')
    <div class="doc-footer-fixed">
        <div><strong>{{ $boutique->nom ?? 'MalCom' }}</strong> • Registre d'audit des mouvements de stock certifié • MalCom Cloud v2.0</div>
    </div>
@endsection

@section('content')
    <!-- Header -->
    <table class="header-table">
        <tr>
            <td style="width: 60%;">
                <div style="font-size: 15pt; font-weight: 900; color: #0f172a; text-transform: uppercase; letter-spacing: 0.5px;">
                    {{ $boutique->nom ?? 'MALCOM COMMERCE' }}
                </div>
                <div style="font-size: 7.5pt; color: #d97706; font-weight: bold; text-transform: uppercase; letter-spacing: 1px; margin-top: 2px;">
                    Contrôle des Stocks & Registre des Mouvements
                </div>
                <div style="font-size: 8pt; color: #475569; margin-top: 4px;">
                    {{ $boutique->adresse ?? '---' }} | Tél : {{ $boutique->telephone ?? '---' }}
                </div>
            </td>
            <td style="width: 40%; text-align: right;">
                <div class="doc-title-badge">
                    REGISTRE D'INVENTAIRE
                </div>
                <table style="width: 100%; border: none; font-size: 8pt; margin-top: 2px;">
                    <tr>
                        <td style="text-align: right; color: #64748b; padding: 1px 4px;">Date d'édition :</td>
                        <td style="text-align: right; font-weight: bold; color: #0f172a;">{{ date('d/m/Y H:i') }}</td>
                    </tr>
                    @if ($filters['start_date'] || $filters['end_date'])
                        <tr>
                            <td style="text-align: right; color: #64748b; padding: 1px 4px;">Période auditée :</td>
                            <td style="text-align: right; font-weight: bold; color: #047857;">
                                {{ $filters['start_date'] ?? 'Origine' }} au {{ $filters['end_date'] ?? 'Ce jour' }}
                            </td>
                        </tr>
                    @endif
                </table>
            </td>
        </tr>
    </table>

    <!-- Executive Stats Cards -->
    <table style="width: 100%; border-collapse: separate; border-spacing: 8px 0; margin-bottom: 12px;">
        <tr>
            <td style="background-color: #ecfdf5; border: 1px solid #a7f3d0; border-radius: 4px; padding: 8px 12px; width: 25%;">
                <div style="font-size: 6.5pt; font-weight: bold; color: #065f46; text-transform: uppercase;">TOTAL ENTRÉES</div>
                <div style="font-size: 13pt; font-weight: 900; color: #047857; margin-top: 2px;">+{{ $stats['totalEntrees'] }} <span style="font-size: 7.5pt; font-weight: normal;">unités</span></div>
            </td>
            <td style="background-color: #fff1f2; border: 1px solid #fecdd3; border-radius: 4px; padding: 8px 12px; width: 25%;">
                <div style="font-size: 6.5pt; font-weight: bold; color: #9f1239; text-transform: uppercase;">TOTAL SORTIES</div>
                <div style="font-size: 13pt; font-weight: 900; color: #be123c; margin-top: 2px;">-{{ $stats['totalSorties'] }} <span style="font-size: 7.5pt; font-weight: normal;">unités</span></div>
            </td>
            <td style="background-color: #eff6ff; border: 1px solid #bfdbfe; border-radius: 4px; padding: 8px 12px; width: 25%;">
                <div style="font-size: 6.5pt; font-weight: bold; color: #1e40af; text-transform: uppercase;">VARIATION NETTE</div>
                <div style="font-size: 13pt; font-weight: 900; color: {{ $stats['netMouvement'] >= 0 ? '#047857' : '#be123c' }}; margin-top: 2px;">
                    {{ $stats['netMouvement'] > 0 ? '+' : '' }}{{ $stats['netMouvement'] }} <span style="font-size: 7.5pt; font-weight: normal;">unités</span>
                </div>
            </td>
            <td style="background-color: #f8fafc; border: 1px solid #cbd5e1; border-radius: 4px; padding: 8px 12px; width: 25%;">
                <div style="font-size: 6.5pt; font-weight: bold; color: #475569; text-transform: uppercase;">LIGNES D'AUDIT</div>
                <div style="font-size: 13pt; font-weight: 900; color: #0f172a; margin-top: 2px;">{{ count($inventaires) }} <span style="font-size: 7.5pt; font-weight: normal;">opérations</span></div>
            </td>
        </tr>
    </table>

    <!-- Data Table -->
    <table class="table-data">
        <thead>
            <tr>
                <th style="width: 13%; text-align: center;">DATE & HEURE</th>
                <th style="width: 27%; text-align: left;">DÉSIGNATION DE L'ARTICLE</th>
                <th style="width: 10%; text-align: center;">FLUX</th>
                <th style="width: 24%; text-align: left;">MOTIF / JUSTIFICATION</th>
                <th style="width: 8%; text-align: center;">QTÉ</th>
                <th style="width: 9%; text-align: right;">P.U. ({{ $boutique->devise ?? 'FCFA' }})</th>
                <th style="width: 9%; text-align: right;">VALEUR TOTALE</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($inventaires as $item)
                @php
                    $pu = $item->type === 'retrait'
                        ? ($item->produit->stock->prix_vente ?? 0)
                        : ($item->produit->stock->prix_achat ?? 0);
                    $total = $item->quantite * $pu;
                @endphp
                <tr>
                    <td style="text-align: center; font-size: 7.5pt; color: #475569;">
                        {{ \Carbon\Carbon::parse($item->created_at)->format('d/m/Y H:i') }}
                    </td>
                    <td>
                        <strong style="text-transform: uppercase; color: #0f172a;">{{ $item->produit->nom ?? 'Article Inconnu' }}</strong>
                        @if($item->user) <span style="font-size: 6.5pt; color: #94a3b8;">(Opérateur: {{ $item->user->name }})</span> @endif
                    </td>
                    <td style="text-align: center;">
                        @if($item->type === 'retrait')
                            <span class="badge badge-danger">SORTIE</span>
                        @else
                            <span class="badge badge-success">ENTRÉE</span>
                        @endif
                    </td>
                    <td style="font-size: 7pt; color: #475569;">{{ $item->description }}</td>
                    <td style="text-align: center; font-weight: bold; color: {{ $item->type === 'retrait' ? '#be123c' : '#047857' }};">
                        {{ $item->type === 'retrait' ? '-' : '+' }}{{ $item->quantite }}
                    </td>
                    <td style="text-align: right; color: #334155;">{{ number_format($pu, 0, ',', ' ') }}</td>
                    <td style="text-align: right; font-weight: bold; color: #0f172a;">{{ number_format($total, 0, ',', ' ') }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <!-- Signatures (Pinned to bottom of the last page) -->
    <div class="signatures-pinned-bottom">
        <table class="signatures-table">
            <tr>
                <td style="width: 33.33%;">
                    <div class="signature-title">Le Gestionnaire des Stocks</div>
                    <div class="signature-line" style="width: 130px;"></div>
                </td>
                <td style="width: 33.33%;">
                    <div class="signature-title">L'Auditeur / Contrôleur</div>
                    <div class="signature-line" style="width: 130px;"></div>
                </td>
                <td style="width: 33.33%;">
                    <div class="signature-title">La Direction Générale</div>
                    <div class="signature-line" style="width: 130px;"></div>
                </td>
            </tr>
        </table>
    </div>
@endsection
