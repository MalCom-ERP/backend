@extends('pdf.layouts.base')

@section('title', 'Facture Pro-Forma N° PRO-' . \Carbon\Carbon::parse($vente->date_vente)->format('Y') . '-' . str_pad($vente->id, 5, '0', STR_PAD_LEFT))

@section('fixed_footer')
    <div class="doc-footer-fixed">
        <div><strong>{{ $boutique->nom ?? 'MalCom' }}</strong> • Facture Pro-Forma / Devis Commercial émis par MalCom Cloud v2.0</div>
        <div style="margin-top: 1px;">Document prévisionnel sans déstockage physique ni valeur de reçu fiscal. Offre valable 30 jours à compter de la date d'émission.</div>
    </div>
@endsection

@section('content')
    <!-- Official Header -->
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
                <div style="font-size: 7.5pt; color: {{ !empty($boutique->couleur_secondaire) ? $boutique->couleur_secondaire : '#4f46e5' }}; font-weight: bold; text-transform: uppercase; letter-spacing: 0.8px; margin-top: 2px;">
                    {{ $boutique->description ?? 'Proposition Commerciale & Facturation Pro-Forma' }}
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
                <div class="doc-title-badge" style="color: {{ !empty($boutique->couleur_principale) ? $boutique->couleur_principale : '#4f46e5' }};">
                    FACTURE PRO-FORMA
                </div>
                <table style="width: 100%; border: none; font-size: 7.5pt; margin-top: 5px;">
                    <tr>
                        <td style="text-align: right; color: #64748b; padding: 1.5px 4px;">Numéro Pro-Forma :</td>
                        <td style="text-align: right; font-weight: 800; color: #0f172a; padding: 1.5px 0;">
                            PRO-{{ \Carbon\Carbon::parse($vente->date_vente)->format('Y') }}-{{ str_pad($vente->id, 5, '0', STR_PAD_LEFT) }}
                        </td>
                    </tr>
                    <tr>
                        <td style="text-align: right; color: #64748b; padding: 1.5px 4px;">Date d'émission :</td>
                        <td style="text-align: right; font-weight: 600; color: #0f172a; padding: 1.5px 0;">
                            {{ \Carbon\Carbon::parse($vente->date_vente)->format('d/m/Y H:i') }}
                        </td>
                    </tr>
                    <tr>
                        <td style="text-align: right; color: #64748b; padding: 1.5px 4px;">Validité :</td>
                        <td style="text-align: right; padding: 1.5px 0;">
                            <span class="badge badge-info" style="background-color: #eef2ff; color: #4338ca; border: 1px solid #c7d2fe;">30 JOURS</span>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    <!-- Parties Information -->
    <table class="info-card-table">
        <tr>
            <td style="width: 48%; padding-right: 15px;">
                <div class="info-card">
                    <div class="info-card-title">PROPOSITION DESTINÉE À (CLIENT)</div>
                    <div style="font-size: 9.5pt; font-weight: 800; color: #0f172a; text-transform: uppercase; margin-top: 4px;">
                        {{ ($vente->client && $vente->client->nom !== 'ANONYME') ? $vente->client->nom : 'CLIENT / PROSPECT' }}
                    </div>
                    <div style="font-size: 7.5pt; color: #475569; margin-top: 3px; line-height: 1.35;">
                        @if($vente->client && $vente->client->telephone)
                            <div>Tél : <strong>{{ $vente->client->telephone }}</strong></div>
                        @endif
                        @if($vente->client && !empty($vente->client->adresse))
                            <div>Adresse : {{ $vente->client->adresse }}</div>
                        @endif
                    </div>
                </div>
            </td>
            <td style="width: 4%;"></td>
            <td style="width: 48%;">
                <div class="info-card">
                    <div class="info-card-title">CONDITIONS COMMERCIALES</div>
                    <table style="width: 100%; border: none; font-size: 7.5pt; margin-top: 3px;">
                        <tr>
                            <td style="color: #64748b; padding: 1.5px 0;">Agent commercial :</td>
                            <td style="text-align: right; font-weight: 700; color: #0f172a;">{{ $vente->user->name ?? 'Service Vente' }}</td>
                        </tr>
                        <tr>
                            <td style="color: #64748b; padding: 1.5px 0;">Déstockage physique :</td>
                            <td style="text-align: right; font-weight: 700; color: #4f46e5;">À confirmation</td>
                        </tr>
                        <tr>
                            <td style="color: #64748b; padding: 1.5px 0;">Devise de règlement :</td>
                            <td style="text-align: right; font-weight: 700; color: #b45309;">{{ $boutique->devise ?? 'FCFA' }} (Zone UEMOA)</td>
                        </tr>
                    </table>
                </div>
            </td>
        </tr>
    </table>

    <!-- Line Items Table (Using standard .table-data) -->
    <table class="table-data">
        <thead>
            <tr>
                <th style="width: 5%; text-align: center;">N°</th>
                <th style="width: 14%; text-align: center;">RÉF</th>
                <th style="width: 45%; text-align: left;">DÉSIGNATION DE L'ARTICLE</th>
                <th style="width: 8%; text-align: center;">QTÉ</th>
                <th style="width: 14%; text-align: right;">PRIX UNIT.</th>
                <th style="width: 14%; text-align: right;">TOTAL ({{ $boutique->devise ?? 'FCFA' }})</th>
            </tr>
        </thead>
        <tbody>
            @php 
                $lineIndex = 1;
                $calcSubtotal = 0;
            @endphp
            @foreach ($vente->detailVentes as $detail)
                @php 
                    $lineTotal = ($detail->quantite * $detail->prix_unitaire);
                    $calcSubtotal += $lineTotal;
                @endphp
                <tr>
                    <td style="text-align: center; color: #94a3b8; font-size: 7pt;">{{ $lineIndex++ }}</td>
                    <td style="text-align: center; font-size: 7.5pt; color: #64748b; font-weight: 600;">
                        #{{ str_pad($detail->produit->reference ?? $detail->produit->id, 4, '0', STR_PAD_LEFT) }}
                    </td>
                    <td>
                        <div style="font-weight: 700; color: #0f172a; text-transform: uppercase;">
                            {{ $detail->produit->nom }}
                        </div>
                        @if(!empty($detail->produit->categorie))
                            <div style="font-size: 6.5pt; color: #94a3b8;">
                                Catégorie : {{ $detail->produit->categorie->nom }}
                            </div>
                        @endif
                    </td>
                    <td style="text-align: center; font-weight: 700; color: #0f172a;">
                        {{ $detail->quantite }}
                    </td>
                    <td style="text-align: right; color: #475569;">
                        {{ number_format($detail->prix_unitaire, 0, ',', ' ') }}
                    </td>
                    <td style="text-align: right; font-weight: 700; color: #0f172a;">
                        {{ number_format($lineTotal, 0, ',', ' ') }}
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>

    @php
        $remise = (float)($vente->remise ?? 0);
        $netCommercial = ($vente->montant_total > 0) ? (float)$vente->montant_total : ($calcSubtotal - $remise);
    @endphp
    <table style="width: 100%; border-collapse: collapse; margin-top: 6px;">
        <tr>
            <td style="width: 52%; vertical-align: top; padding-right: 15px;">
                <div style="background-color: #f8fafc; border: 1px solid #e2e8f0; border-radius: 4px; padding: 8px 10px;">
                    <div style="font-size: 6.5pt; font-weight: 700; color: #64748b; text-transform: uppercase; margin-bottom: 3px;">
                        MODALITÉS DE LA PROPOSITION PRO-FORMA
                    </div>
                    <div style="font-size: 6.5pt; color: #64748b; line-height: 1.35;">
                        La présente facture pro-forma est établie pour servir et valoir ce que de droit en vue de l'établissement d'un bon de commande, d'un virement bancaire ou de formalités administratives.
                    </div>
                    <div style="font-size: 6pt; color: #94a3b8; margin-top: 4px;">
                        Moyens acceptés à la confirmation : Espèces, Wave, Orange Money, Moov Money, Virement ou Chèque.
                    </div>
                </div>
            </td>
            <td style="width: 48%; vertical-align: top;">
                <table class="totals-table">
                    <tr>
                        <td class="total-label">SOUS-TOTAL BRUT</td>
                        <td class="total-amount">{{ number_format($calcSubtotal, 0, ',', ' ') }} {{ $boutique->devise ?? 'FCFA' }}</td>
                    </tr>
                    @if($remise > 0)
                        <tr>
                            <td class="total-label" style="color: #dc2626;">REMISE COMMERCIALE (-)</td>
                            <td class="total-amount" style="color: #dc2626;">- {{ number_format($remise, 0, ',', ' ') }} {{ $boutique->devise ?? 'FCFA' }}</td>
                        </tr>
                    @endif
                    <tr class="highlight-row">
                        <td class="total-label" style="color: #4f46e5; font-size: 8.5pt;">NET COMMERCIAL À PAYER</td>
                        <td class="total-amount" style="color: #4f46e5; font-size: 11pt; font-weight: 900;">
                            {{ number_format($netCommercial, 0, ',', ' ') }} {{ $boutique->devise ?? 'FCFA' }}
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    <!-- Signatures -->
    <div style="margin-top: 30px; page-break-inside: avoid;">
        <table style="width: 100%; border: none;">
            <tr>
                <td style="width: 50%; text-align: left; vertical-align: top;">
                    <div style="font-size: 7.5pt; font-weight: 800; color: #0f172a; text-transform: uppercase;">
                        POUR LA BOUTIQUE / LE SERVICE COMMERCIAL
                    </div>
                    <div style="font-size: 6.5pt; color: #64748b; margin-top: 1px;">Visa & Cachet</div>
                    <div style="margin-top: 45px; border-bottom: 1px dashed #cbd5e1; width: 75%;"></div>
                </td>
                <td style="width: 50%; text-align: right; vertical-align: top;">
                    <div style="font-size: 7.5pt; font-weight: 800; color: #0f172a; text-transform: uppercase;">
                        BON POUR ACCORD & CONFIRMATION
                    </div>
                    <div style="font-size: 6.5pt; color: #64748b; margin-top: 1px;">Date, Nom et Signature du Client</div>
                    <div style="margin-top: 45px; border-bottom: 1px dashed #cbd5e1; width: 75%; margin-left: auto;"></div>
                </td>
            </tr>
        </table>
    </div>
@endsection
