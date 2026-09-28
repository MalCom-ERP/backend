@extends('pdf.layouts.base')

@section('title', 'Bordereau N° ' . str_pad($vente->id, 6, '0', STR_PAD_LEFT))

@section('fixed_footer')
    <div class="doc-footer-fixed">
        <div><strong>{{ $boutique->nom ?? 'MalCom' }}</strong> • {{ $boutique->footer_bordereau ?? 'Bordereau officiel de livraison de marchandises • Conforme MalCom Cloud v2.0' }}</div>
    </div>
@endsection

@section('content')
    <!-- Official Header (Airy, Prestigious, Ink-Friendly) -->
    <table class="header-table">
        <tr>
            <td style="width: 58%;">
                @if(!empty($boutique->logo))
                    <div style="margin-bottom: 6px;">
                        <img src="{{ $boutique->logo }}" style="max-height: 44px; max-width: 150px; object-fit: contain;">
                    </div>
                @endif
                <div style="font-size: 14pt; font-weight: 900; color: {{ !empty($boutique->couleur_principale) ? $boutique->couleur_principale : '#0f172a' }}; text-transform: uppercase; letter-spacing: 0.5px;">
                    {{ $boutique->nom ?? 'MALCOM COMMERCE' }}
                </div>
                <div style="font-size: 7.5pt; color: {{ !empty($boutique->couleur_secondaire) ? $boutique->couleur_secondaire : '#d97706' }}; font-weight: bold; text-transform: uppercase; letter-spacing: 0.8px; margin-top: 2px;">
                    {{ $boutique->description_bordereau ?? $boutique->description ?? 'Bordereau de Livraison & Décharge Commerciale' }}
                </div>
                <div style="font-size: 7.5pt; color: #64748b; margin-top: 5px; line-height: 1.4;">
                    @if(!empty($boutique->adresse)) <div>{{ $boutique->adresse }}</div> @endif
                    <div>Tél : {{ $boutique->telephone ?? '+223 00 00 00 00' }} @if(!empty($boutique->email)) | Email : {{ $boutique->email }} @endif</div>
                    @if(!empty($boutique->nif) || !empty($boutique->rccm))
                        <div style="font-size: 7pt; color: #94a3b8; margin-top: 2px;">
                            @if(!empty($boutique->nif)) <span>NIF : <strong>{{ $boutique->nif }}</strong></span> @endif
                            @if(!empty($boutique->rccm)) <span style="margin-left: 8px;">RCCM : <strong>{{ $boutique->rccm }}</strong></span> @endif
                        </div>
                    @endif
                </div>
            </td>
            <td style="width: 42%; text-align: right;">
                <div class="doc-title-badge">
                    BORDEREAU DE LIVRAISON
                </div>
                <table style="width: 100%; border: none; font-size: 7.5pt; margin-top: 5px;">
                    <tr>
                        <td style="text-align: right; color: #64748b; padding: 1.5px 4px;">Numéro BL :</td>
                        <td style="text-align: right; font-weight: 800; color: #0f172a; padding: 1.5px 0;">
                            BL-{{ \Carbon\Carbon::parse($vente->date_vente)->format('Y') }}-{{ str_pad($vente->id, 5, '0', STR_PAD_LEFT) }}
                        </td>
                    </tr>
                    <tr>
                        <td style="text-align: right; color: #64748b; padding: 1.5px 4px;">Date de livraison :</td>
                        <td style="text-align: right; font-weight: 600; color: #0f172a; padding: 1.5px 0;">
                            {{ \Carbon\Carbon::parse($vente->date_vente)->format('d/m/Y H:i') }}
                        </td>
                    </tr>
                    <tr>
                        <td style="text-align: right; color: #64748b; padding: 1.5px 4px;">Type :</td>
                        <td style="text-align: right; padding: 1.5px 0;">
                            <span class="badge badge-info">LIVRAISON CLIENT</span>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    <!-- Parties Information (Clean 2-column layout without heavy box borders) -->
    <table class="info-card-table">
        <tr>
            <td style="width: 48%; padding-right: 15px;">
                <div class="info-card">
                    <div class="info-card-title">DESTINATAIRE / RÉCEPTIONNAIRE</div>
                    <div style="font-size: 9.5pt; font-weight: 800; color: #0f172a; text-transform: uppercase; margin-top: 4px;">
                        {{ ($vente->client && $vente->client->nom !== 'ANONYME') ? $vente->client->nom : 'CLIENT DE PASSAGE' }}
                    </div>
                    <div style="font-size: 7.5pt; color: #475569; margin-top: 3px; line-height: 1.35;">
                        <div>Contact : {{ $vente->client->telephone ?? 'Non spécifié' }}</div>
                        @if(!empty($vente->client->adresse))
                            <div>Lieu de livraison : {{ $vente->client->adresse }}</div>
                        @endif
                    </div>
                </div>
            </td>
            <td style="width: 4%;"></td>
            <td style="width: 48%; padding-left: 15px;">
                <div class="info-card">
                    <div class="info-card-title">EXPÉDITION & CONTRÔLE</div>
                    <table style="width: 100%; border: none; font-size: 7.5pt; margin-top: 4px;">
                        <tr>
                            <td style="color: #64748b; padding: 1.5px 0;">Agent préparateur :</td>
                            <td style="text-align: right; font-weight: 600; color: #0f172a;">{{ $vente->user->name ?? 'Admin' }}</td>
                        </tr>
                        <tr>
                            <td style="color: #64748b; padding: 1.5px 0;">Mode d'expédition :</td>
                            <td style="text-align: right; font-weight: 600; color: #0f172a;">Retrait magasin / Livraison</td>
                        </tr>
                        <tr>
                            <td style="color: #64748b; padding: 1.5px 0;">Total Articles :</td>
                            <td style="text-align: right; font-weight: 700; color: #0f172a;">{{ count($vente->detailVentes) }} référence(s)</td>
                        </tr>
                    </table>
                </div>
            </td>
        </tr>
    </table>

    <!-- Items Table (NO vertical grid borders) -->
    <table class="table-data">
        <thead>
            <tr>
                <th style="width: 5%; text-align: center;">N°</th>
                <th style="width: 14%; text-align: center;">RÉFÉRENCE</th>
                <th style="width: 45%; text-align: left;">DÉSIGNATION DU COLIS / ARTICLE</th>
                <th style="width: 10%; text-align: center;">QTÉ EXP.</th>
                <th style="width: 13%; text-align: right;">VALEUR UNIT.</th>
                <th style="width: 13%; text-align: right;">TOTAL VALEUR</th>
            </tr>
        </thead>
        <tbody>
            @php 
                $bIndex = 1; 
                $totalQty = 0;
            @endphp
            @foreach ($vente->detailVentes as $detail)
                @php $totalQty += $detail->quantite; @endphp
                <tr>
                    <td style="text-align: center; color: #94a3b8; font-size: 7pt;">{{ $bIndex++ }}</td>
                    <td style="text-align: center; font-size: 7.5pt; color: #64748b; font-weight: 600;">
                        #{{ str_pad($detail->produit->reference ?? $detail->produit->id, 4, '0', STR_PAD_LEFT) }}
                    </td>
                    <td>
                        <div style="font-weight: 700; color: #0f172a; text-transform: uppercase;">
                            {{ $detail->produit->nom }}
                        </div>
                    </td>
                    <td style="text-align: center; font-weight: 700; color: #0f172a;">
                        {{ $detail->quantite }}
                    </td>
                    <td style="text-align: right; color: #475569;">
                        {{ number_format($detail->prix_unitaire, 0, ',', ' ') }}
                    </td>
                    <td style="text-align: right; font-weight: 700; color: #0f172a;">
                        {{ number_format($detail->montant_total, 0, ',', ' ') }}
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <!-- Summary & Notes -->
    <table style="width: 100%; border-collapse: collapse; margin-top: 4px;">
        <tr>
            <td style="width: 52%; vertical-align: top; padding-right: 15px;">
                <div style="background-color: #f8fafc; border: 1px solid #e2e8f0; border-radius: 4px; padding: 8px 10px;">
                    <div style="font-size: 6.5pt; font-weight: 700; color: #64748b; text-transform: uppercase; margin-bottom: 3px;">
                        RÉSERVE & DÉCHARGE DE RÉCEPTION
                    </div>
                    <div style="font-size: 6.5pt; color: #64748b; line-height: 1.35;">
                        {{ $boutique->footer_bordereau ?? 'Le client atteste avoir reçu en bon état et conforme la totalité des marchandises décrites sur ce bordereau. Toute anomalie ou avarie doit être signalée sous 24h.' }}
                    </div>
                </div>
            </td>
            <td style="width: 48%; vertical-align: top;">
                <table class="totals-table">
                    <tr>
                        <td class="total-label">QUANTITÉ TOTALE COLIS</td>
                        <td class="total-amount">{{ $totalQty }} unité(s)</td>
                    </tr>
                    <tr class="highlight-row">
                        <td class="total-label" style="font-weight: 900;">VALEUR TOTALE DÉCLARÉE</td>
                        <td class="total-amount" style="font-size: 10pt;">
                            {{ number_format($vente->montant_total, 0, ',', ' ') }} {{ $boutique->devise ?? 'FCFA' }}
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    <!-- Dual Signatures Block (Pinned to bottom of the last page) -->
    <div class="signatures-pinned-bottom">
        <table class="signatures-table">
            <tr>
                <td>
                    <div class="signature-title">Expéditeur / Transporteur (Signature & Date)</div>
                    <div class="signature-line"></div>
                </td>
                <td>
                    <div class="signature-title">Destinataire (Date, Cachet & Signature)</div>
                    <div class="signature-line"></div>
                </td>
            </tr>
        </table>
    </div>
@endsection
