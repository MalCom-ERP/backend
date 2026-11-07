@extends('pdf.layouts.base')

@section('title', 'Reçu de Crédit N° ' . str_pad($vente->id, 6, '0', STR_PAD_LEFT))

@section('fixed_footer')
    <div class="doc-footer-fixed">
        <div><strong>{{ $boutique->nom ?? 'MalCom' }}</strong> • Reçu officiel de suivi de compte client • Document certifié MalCom Cloud v2.0</div>
        <div style="margin-top: 1px;">Vérifiez toujours l'authenticité de vos règlements avec votre quittance informatisée.</div>
    </div>
@endsection

@section('content')
    <!-- Official Header (Airy, Prestigious, Ink-Friendly) -->
    <table class="header-table">
        <tr>
            <td style="width: 58%;">
                <div style="font-size: 14pt; font-weight: 900; color: #0f172a; text-transform: uppercase; letter-spacing: 0.5px;">
                    {{ $boutique->nom ?? 'MALCOM COMMERCE' }}
                </div>
                <div style="font-size: 7.5pt; color: #d97706; font-weight: bold; text-transform: uppercase; letter-spacing: 0.8px; margin-top: 2px;">
                    {{ $boutique->description ?? 'Gestion des Ventes & Créances Clients' }}
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
                    REÇU & ÉTAT DE CRÉDIT
                </div>
                <table style="width: 100%; border: none; font-size: 7.5pt; margin-top: 5px;">
                    <tr>
                        <td style="text-align: right; color: #64748b; padding: 1.5px 4px;">Réf. Vente :</td>
                        <td style="text-align: right; font-weight: 800; color: #0f172a; padding: 1.5px 0;">
                            CRD-{{ \Carbon\Carbon::parse($vente->date_vente)->format('Y') }}-{{ str_pad($vente->id, 5, '0', STR_PAD_LEFT) }}
                        </td>
                    </tr>
                    <tr>
                        <td style="text-align: right; color: #64748b; padding: 1.5px 4px;">Date d'opération :</td>
                        <td style="text-align: right; font-weight: 600; color: #0f172a; padding: 1.5px 0;">
                            {{ \Carbon\Carbon::parse($vente->date_vente)->format('d/m/Y') }}
                        </td>
                    </tr>
                    <tr>
                        <td style="text-align: right; color: #64748b; padding: 1.5px 4px;">Situation :</td>
                        <td style="text-align: right; padding: 1.5px 0;">
                            @if($vente->montant_restant <= 0)
                                <span class="badge badge-success">INTÉGRALEMENT SOLDÉ</span>
                            @else
                                <span class="badge badge-danger">SOLDE EN COURS</span>
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
                    <div class="info-card-title">CLIENT / DÉBITEUR</div>
                    <div style="font-size: 9.5pt; font-weight: 800; color: #0f172a; text-transform: uppercase; margin-top: 4px;">
                        {{ ($vente->client && $vente->client->nom !== 'ANONYME') ? $vente->client->nom : 'CLIENT DE PASSAGE' }}
                    </div>
                    <div style="font-size: 7.5pt; color: #475569; margin-top: 3px; line-height: 1.35;">
                        <div>Téléphone : <strong>{{ $vente->client->telephone ?? 'Non spécifié' }}</strong></div>
                        @if(!empty($vente->client->adresse))
                            <div>Adresse : {{ $vente->client->adresse }}</div>
                        @endif
                    </div>
                </div>
            </td>
            <td style="width: 4%;"></td>
            <td style="width: 48%; padding-left: 15px;">
                <div class="info-card">
                    <div class="info-card-title">DOSSIER DE CRÉANCE</div>
                    <table style="width: 100%; border: none; font-size: 7.5pt; margin-top: 4px;">
                        <tr>
                            <td style="color: #64748b; padding: 1.5px 0;">Agent / Caissier :</td>
                            <td style="text-align: right; font-weight: 600; color: #0f172a;">{{ $vente->user->name ?? 'Admin' }}</td>
                        </tr>
                        <tr>
                            <td style="color: #64748b; padding: 1.5px 0;">Total Titre de Vente :</td>
                            <td style="text-align: right; font-weight: 700; color: #0f172a;">{{ number_format($vente->montant_total, 0, ',', ' ') }} {{ $boutique->devise ?? 'FCFA' }}</td>
                        </tr>
                        <tr>
                            <td style="color: #64748b; padding: 1.5px 0;">Zone Monétaire :</td>
                            <td style="text-align: right; font-weight: 700; color: #b45309;">FCFA (UEMOA)</td>
                        </tr>
                    </table>
                </div>
            </td>
        </tr>
    </table>

    <!-- Section 1: Articles Purchased -->
    <div style="font-size: 7.5pt; font-weight: 700; color: #0f172a; text-transform: uppercase; margin-top: 8px; margin-bottom: 2px;">
        1. Détail des Articles Facturés
    </div>
    <table class="table-data" style="margin-top: 4px; margin-bottom: 12px;">
        <thead>
            <tr>
                <th style="width: 5%; text-align: center;">N°</th>
                <th style="width: 14%; text-align: center;">RÉFÉRENCE</th>
                <th style="width: 45%; text-align: left;">DÉSIGNATION DE L'ARTICLE</th>
                <th style="width: 8%; text-align: center;">QTÉ</th>
                <th style="width: 14%; text-align: right;">PRIX UNIT.</th>
                <th style="width: 14%; text-align: right;">TOTAL ({{ $boutique->devise ?? 'FCFA' }})</th>
            </tr>
        </thead>
        <tbody>
            @php $itemIndex = 1; @endphp
            @foreach ($vente->detailVentes as $detail)
                <tr>
                    <td style="text-align: center; color: #94a3b8; font-size: 7pt;">{{ $itemIndex++ }}</td>
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

    <!-- Section 2: Payments / Installments History (INCLUDING INITIAL ADVANCE) -->
    <div style="font-size: 7.5pt; font-weight: 700; color: #0f172a; text-transform: uppercase; margin-top: 8px; margin-bottom: 2px;">
        2. Relevé des Règlements & Versements Encaissés
    </div>
    <table class="table-data" style="margin-top: 4px; margin-bottom: 12px;">
        <thead>
            <tr>
                <th style="width: 5%; text-align: center;">N°</th>
                <th style="width: 18%; text-align: center;">DATE & HEURE</th>
                <th style="width: 25%; text-align: left;">TYPE / MODE DE PAIEMENT</th>
                <th style="width: 32%; text-align: left;">DÉTAILS / QUITTANCE</th>
                <th style="width: 20%; text-align: right;">MONTANT ENCAISSÉ</th>
            </tr>
        </thead>
        <tbody>
            @php 
                $payIndex = 1; 
                $hasAnyPayment = false;
            @endphp

            {{-- 1. Avance initiale payée lors de la vente --}}
            @if (($vente->montant_avance ?? 0) > 0)
                @php $hasAnyPayment = true; @endphp
                <tr style="background-color: #f0fdf4;">
                    <td style="text-align: center; color: #047857; font-size: 7pt; font-weight: bold;">{{ $payIndex++ }}</td>
                    <td style="text-align: center; font-size: 7.5pt; color: #0f172a; font-weight: bold;">
                        {{ \Carbon\Carbon::parse($vente->date_vente)->format('d/m/Y') }}
                    </td>
                    <td>
                        <strong style="text-transform: uppercase; color: #047857;">AVANCE INITIALE (À LA VENTE)</strong>
                    </td>
                    <td style="font-size: 7.5pt; color: #047857;">
                        Acompte versé à la validation de la commande
                    </td>
                    <td style="text-align: right; font-weight: 900; color: #047857;">
                        + {{ number_format($vente->montant_avance, 0, ',', ' ') }} {{ $boutique->devise ?? 'FCFA' }}
                    </td>
                </tr>
            @endif

            {{-- 2. Versements ultérieurs enregistrés --}}
            @php $paymentsList = $paiements ?? ($vente->paiementsCredit ?? collect()); @endphp
            @if ($paymentsList && count($paymentsList) > 0)
                @php $hasAnyPayment = true; @endphp
                @foreach ($paymentsList as $p)
                    <tr>
                        <td style="text-align: center; color: #94a3b8; font-size: 7pt;">{{ $payIndex++ }}</td>
                        <td style="text-align: center; font-size: 7.5pt; color: #475569;">
                            {{ \Carbon\Carbon::parse($p->date_paiement)->format('d/m/Y H:i') }}
                        </td>
                        <td>
                            <strong style="text-transform: uppercase; color: #0f172a;">{{ $p->mode_paiement }}</strong>
                        </td>
                        <td style="font-size: 7.5pt; color: #64748b;">
                            {{ $p->notes ?: 'Versement régulier' }}
                            @if($p->user) <span style="font-size: 6.5pt; color: #94a3b8;">(Agent: {{ $p->user->name }})</span> @endif
                        </td>
                        <td style="text-align: right; font-weight: 700; color: #047857;">
                            + {{ number_format($p->montant, 0, ',', ' ') }} {{ $boutique->devise ?? 'FCFA' }}
                        </td>
                    </tr>
                @endforeach
            @endif

            @if (!$hasAnyPayment)
                <tr>
                    <td colspan="5" style="text-align: center; padding: 12px; color: #94a3b8; font-style: italic;">
                        Aucun paiement ni acompte n'a été enregistré à ce jour.
                    </td>
                </tr>
            @endif
        </tbody>
    </table>

    <!-- Section 3: Balance & Recap -->
    @php
        $totalPaid = $vente->montant_total - $vente->montant_restant;
        $versementsUlterieurs = max(0, $totalPaid - ($vente->montant_avance ?? 0));
    @endphp
    <table style="width: 100%; border-collapse: collapse; margin-top: 4px;">
        <tr>
            <td style="width: 52%; vertical-align: top; padding-right: 15px;">
                <div style="background-color: #f8fafc; border: 1px solid #e2e8f0; border-radius: 4px; padding: 8px 10px;">
                    <div style="font-size: 6.5pt; font-weight: 700; color: #64748b; text-transform: uppercase; margin-bottom: 3px;">
                        ENGAGEMENT DE PAIEMENT & MENTIONS LÉGALES
                    </div>
                    <div style="font-size: 6.5pt; color: #64748b; line-height: 1.35;">
                        {{ $boutique->footer_recu ?? 'Le présent relevé certifie les sommes perçues au titre de la vente mentionnée ci-dessus. Tout solde restant est exigible selon les termes convenus.' }}
                    </div>
                    <div style="font-size: 6pt; color: #94a3b8; margin-top: 4px;">
                        Règlement direct auprès de notre caisse ou via nos canaux Mobile Money certifiés.
                    </div>
                </div>
            </td>
            <td style="width: 48%; vertical-align: top;">
                <table class="totals-table">
                    <tr>
                        <td class="total-label">MONTANT TOTAL DE LA FACTURE</td>
                        <td class="total-amount">{{ number_format($vente->montant_total, 0, ',', ' ') }} {{ $boutique->devise ?? 'FCFA' }}</td>
                    </tr>
                    @if (($vente->montant_avance ?? 0) > 0)
                        <tr>
                            <td class="total-label" style="color: #047857;">AVANCE INITIALE PAYÉE</td>
                            <td class="total-amount" style="color: #047857;">- {{ number_format($vente->montant_avance, 0, ',', ' ') }} {{ $boutique->devise ?? 'FCFA' }}</td>
                        </tr>
                    @endif
                    @if ($versementsUlterieurs > 0)
                        <tr>
                            <td class="total-label" style="color: #047857;">VERSEMENTS ULTÉRIEURS</td>
                            <td class="total-amount" style="color: #047857;">- {{ number_format($versementsUlterieurs, 0, ',', ' ') }} {{ $boutique->devise ?? 'FCFA' }}</td>
                        </tr>
                    @endif
                    <tr>
                        <td class="total-label" style="font-weight: 700; color: #047857;">TOTAL DÉJÀ RÉGLÉ / ENCAISSÉ</td>
                        <td class="total-amount" style="color: #047857; font-weight: 700;">{{ number_format($totalPaid, 0, ',', ' ') }} {{ $boutique->devise ?? 'FCFA' }}</td>
                    </tr>
                    <tr class="highlight-row">
                        <td class="total-label" style="color: {{ $vente->montant_restant <= 0 ? '#047857' : '#dc2626' }};">
                            {{ $vente->montant_restant <= 0 ? 'SOLDE (INTÉGRALEMENT RÉGLÉ)' : 'RESTE À PAYER (SOLDE DÛ)' }}
                        </td>
                        <td class="total-amount" style="color: {{ $vente->montant_restant <= 0 ? '#047857' : '#dc2626' }};">
                            {{ number_format($vente->montant_restant, 0, ',', ' ') }} {{ $boutique->devise ?? 'FCFA' }}
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
                    <div class="signature-title">Pour la Caisse / Établissement (Cachet)</div>
                    <div class="signature-line"></div>
                </td>
                <td>
                    <div class="signature-title">Pour le Client (Reconnaissance de dette / Reçu)</div>
                    <div class="signature-line"></div>
                </td>
            </tr>
        </table>
    </div>
@endsection
