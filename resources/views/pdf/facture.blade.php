@extends('pdf.layouts.base')

@section('title', 'Facture N° ' . str_pad($vente->id, 6, '0', STR_PAD_LEFT))

@section('fixed_footer')
    <div class="doc-footer-fixed">
        <div><strong>{{ $boutique->nom ?? 'MalCom' }}</strong> • {{ $boutique->footer_facture ?? 'Document officiel certifié conforme émis par MalCom Cloud v2.0 • Facture originale' }}</div>
        <div style="margin-top: 1px;">Tout retard de paiement engendre des pénalités légales conformément aux textes en vigueur de l'OHADA et de l'UEMOA.</div>
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
                    {{ $boutique->description_facture ?? $boutique->description ?? 'Système de Gestion Commerciale' }}
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
                    FACTURE COMMERCIALE
                </div>
                <table style="width: 100%; border: none; font-size: 7.5pt; margin-top: 5px;">
                    <tr>
                        <td style="text-align: right; color: #64748b; padding: 1.5px 4px;">Numéro :</td>
                        <td style="text-align: right; font-weight: 800; color: #0f172a; padding: 1.5px 0;">
                            FAC-{{ \Carbon\Carbon::parse($vente->date_vente)->format('Y') }}-{{ str_pad($vente->id, 5, '0', STR_PAD_LEFT) }}
                        </td>
                    </tr>
                    <tr>
                        <td style="text-align: right; color: #64748b; padding: 1.5px 4px;">Date d'émission :</td>
                        <td style="text-align: right; font-weight: 600; color: #0f172a; padding: 1.5px 0;">
                            {{ \Carbon\Carbon::parse($vente->date_vente)->format('d/m/Y H:i') }}
                        </td>
                    </tr>
                    <tr>
                        <td style="text-align: right; color: #64748b; padding: 1.5px 4px;">Statut :</td>
                        <td style="text-align: right; padding: 1.5px 0;">
                            @if($vente->type_paiement === 'credit' && ($vente->montant_restant ?? 0) > 0)
                                <span class="badge badge-warning">EN CRÉDIT (SOLDE DÛ)</span>
                            @else
                                <span class="badge badge-success">PAYÉ / SOLDÉ</span>
                            @endif
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
                    <div class="info-card-title">FACTURÉ À (CLIENT)</div>
                    <div style="font-size: 9.5pt; font-weight: 800; color: #0f172a; text-transform: uppercase; margin-top: 4px;">
                        {{ ($vente->client && $vente->client->nom !== 'ANONYME') ? $vente->client->nom : 'CLIENT DE PASSAGE' }}
                    </div>
                    <div style="font-size: 7.5pt; color: #475569; margin-top: 3px; line-height: 1.35;">
                        <div>Contact : {{ $vente->client->telephone ?? 'Non spécifié' }}</div>
                        @if(!empty($vente->client->adresse))
                            <div>Adresse : {{ $vente->client->adresse }}</div>
                        @endif
                        @if(!empty($vente->client->nif))
                            <div style="font-size: 7pt; color: #64748b;">NIF Client : {{ $vente->client->nif }}</div>
                        @endif
                    </div>
                </div>
            </td>
            <td style="width: 4%;"></td>
            <td style="width: 48%; padding-left: 15px;">
                <div class="info-card">
                    <div class="info-card-title">MODALITÉS DE RÈGLEMENT</div>
                    <table style="width: 100%; border: none; font-size: 7.5pt; margin-top: 4px;">
                        <tr>
                            <td style="color: #64748b; padding: 1.5px 0;">Mode de paiement :</td>
                            <td style="text-align: right; font-weight: 700; color: #0f172a;">{{ strtoupper($vente->type_paiement ?? 'COMPTANT') }}</td>
                        </tr>
                        <tr>
                            <td style="color: #64748b; padding: 1.5px 0;">Opérateur / Caisse :</td>
                            <td style="text-align: right; font-weight: 600; color: #0f172a;">{{ $vente->user->name ?? 'Admin' }}</td>
                        </tr>
                        <tr>
                            <td style="color: #64748b; padding: 1.5px 0;">Devise de facturation :</td>
                            <td style="text-align: right; font-weight: 700; color: #b45309;">{{ $boutique->devise ?? 'FCFA' }} (Zone UEMOA)</td>
                        </tr>
                    </table>
                </div>
            </td>
        </tr>
    </table>

    <!-- Line Items Table (NO vertical grid borders) -->
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
            @php $lineIndex = 1; @endphp
            @foreach ($vente->detailVentes as $detail)
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
                        {{ number_format($detail->montant_total, 0, ',', ' ') }}
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <!-- Summary & Totals Block (INCLUDING ADVANCE & BALANCE IF CREDIT) -->
    @php
        $subTotal = $vente->montant_total + ($vente->remise ?? 0);
        $totalPaid = $vente->montant_total - ($vente->montant_restant ?? 0);
        $isCredit = ($vente->type_paiement === 'credit');
    @endphp
    <table style="width: 100%; border-collapse: collapse; margin-top: 4px;">
        <tr>
            <td style="width: 52%; vertical-align: top; padding-right: 15px;">
                <div style="background-color: #f8fafc; border: 1px solid #e2e8f0; border-radius: 4px; padding: 8px 10px;">
                    <div style="font-size: 6.5pt; font-weight: 700; color: #64748b; text-transform: uppercase; margin-bottom: 3px;">
                        CONDITIONS DE VENTE & MENTIONS LÉGALES
                    </div>
                    <div style="font-size: 6.5pt; color: #64748b; line-height: 1.35;">
                        {{ $boutique->footer_facture ?? 'Les marchandises vendues ne sont ni reprises ni échangées après 48h. Propriété des biens réservée jusqu\'au complet encaissement.' }}
                    </div>
                    <div style="font-size: 6pt; color: #94a3b8; margin-top: 4px;">
                        Régime fiscal : Exonération de TVA selon réglementation UEMOA / Commerce de détail.
                    </div>
                </div>
            </td>
            <td style="width: 48%; vertical-align: top;">
                <table class="totals-table">
                    <tr>
                        <td class="total-label">SOUS-TOTAL BRUT</td>
                        <td class="total-amount">{{ number_format($subTotal, 0, ',', ' ') }} {{ $boutique->devise ?? 'FCFA' }}</td>
                    </tr>
                    @if(($vente->remise ?? 0) > 0)
                        <tr>
                            <td class="total-label" style="color: #dc2626;">REMISE COMMERCIALE (-)</td>
                            <td class="total-amount" style="color: #dc2626;">- {{ number_format($vente->remise, 0, ',', ' ') }} {{ $boutique->devise ?? 'FCFA' }}</td>
                        </tr>
                    @endif
                    <tr>
                        <td class="total-label" style="font-weight: 600; color: #1e293b;">MONTANT NET DE LA VENTE</td>
                        <td class="total-amount" style="font-weight: 700;">{{ number_format($vente->montant_total, 0, ',', ' ') }} {{ $boutique->devise ?? 'FCFA' }}</td>
                    </tr>

                    {{-- Si vente à crédit, afficher clairement l'avance payée et le solde restant --}}
                    @if($isCredit)
                        @if(($vente->montant_avance ?? 0) > 0)
                            <tr>
                                <td class="total-label" style="color: #047857;">AVANCE INITIALE PAYÉE</td>
                                <td class="total-amount" style="color: #047857;">- {{ number_format($vente->montant_avance, 0, ',', ' ') }} {{ $boutique->devise ?? 'FCFA' }}</td>
                            </tr>
                        @endif
                        @if($totalPaid > ($vente->montant_avance ?? 0))
                            <tr>
                                <td class="total-label" style="color: #047857;">VERSEMENTS ULTÉRIEURS</td>
                                <td class="total-amount" style="color: #047857;">- {{ number_format($totalPaid - $vente->montant_avance, 0, ',', ' ') }} {{ $boutique->devise ?? 'FCFA' }}</td>
                            </tr>
                        @endif
                        <tr>
                            <td class="total-label" style="font-weight: 700; color: #047857;">TOTAL DÉJÀ RÉGLÉ</td>
                            <td class="total-amount" style="font-weight: 700; color: #047857;">{{ number_format($totalPaid, 0, ',', ' ') }} {{ $boutique->devise ?? 'FCFA' }}</td>
                        </tr>
                        <tr class="highlight-row">
                            <td class="total-label" style="color: {{ ($vente->montant_restant ?? 0) <= 0 ? '#047857' : '#dc2626' }};">RESTE À PAYER (SOLDE DÛ)</td>
                            <td class="total-amount" style="color: {{ ($vente->montant_restant ?? 0) <= 0 ? '#047857' : '#dc2626' }};">
                                {{ number_format($vente->montant_restant ?? 0, 0, ',', ' ') }} {{ $boutique->devise ?? 'FCFA' }}
                            </td>
                        </tr>
                    @else
                        <tr class="highlight-row">
                            <td class="total-label" style="color: #047857;">NET ENCAISSÉ (COMPTANT)</td>
                            <td class="total-amount" style="color: #047857;">
                                {{ number_format($vente->montant_total, 0, ',', ' ') }} {{ $boutique->devise ?? 'FCFA' }}
                            </td>
                        </tr>
                    @endif
                </table>
            </td>
        </tr>
    </table>

    <!-- Dual Signatures Block (Pinned to bottom of the last page) -->
    <div class="signatures-pinned-bottom">
        <table class="signatures-table">
            <tr>
                <td>
                    <div class="signature-title">Pour la Boutique (Cachet & Signature)</div>
                    <div class="signature-line"></div>
                </td>
                <td>
                    <div class="signature-title">Pour le Client (Bon pour accord & Réception)</div>
                    <div class="signature-line"></div>
                </td>
            </tr>
        </table>
    </div>
@endsection
