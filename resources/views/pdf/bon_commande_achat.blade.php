@extends('pdf.layouts.base')

@section('title', ($achat->statut === 'en_commande' ? 'Bon de Commande Fournisseur ' : 'Bordereau de Réception ') . $achat->reference)

@section('fixed_footer')
    <div class="doc-footer-fixed">
        <div><strong>{{ $boutique->nom ?? 'MalCom' }}</strong> • Document Achats &amp; Approvisionnements Fournisseurs • Conforme SYSCOHADA (Compte 401 / 601 / 611)</div>
    </div>
@endsection

@section('content')
    @php
        $isPending = $achat->statut === 'en_commande';
        $docTitle = $isPending ? 'BON DE COMMANDE FOURNISSEUR' : 'BORDEREAU DE RÉCEPTION D\'ACHAT';
        $statutLabel = match($achat->statut) {
            'recu' => 'RÉCEPTIONNÉ EN STOCK',
            'annule' => 'ANNULÉ',
            default => 'EN ATTENTE DE LIVRAISON',
        };
        $statutClass = match($achat->statut) {
            'recu' => 'badge-success',
            'annule' => 'badge-danger',
            default => 'badge-warning',
        };
        $fournisseur = $achat->fournisseur;
        $totalFrais = (float)$achat->frais_transport + (float)$achat->frais_douane + (float)$achat->frais_manutention;
    @endphp

    <!-- En-tête officiel -->
    <table class="header-table">
        <tr>
            <td style="width: 55%;">
                @if(!empty($boutique->logo))
                    <div style="margin-bottom: 6px;">
                        <img src="{{ $boutique->logo }}" style="max-height: 44px; max-width: 150px; object-fit: contain;">
                    </div>
                @endif
                <div style="font-size: 14pt; font-weight: 900; color: {{ !empty($boutique->couleur_principale) ? $boutique->couleur_principale : '#0f172a' }}; text-transform: uppercase; letter-spacing: 0.5px;">
                    {{ $boutique->nom ?? 'MALCOM COMMERCE' }}
                </div>
                <div style="font-size: 7.5pt; color: {{ !empty($boutique->couleur_secondaire) ? $boutique->couleur_secondaire : '#4f46e5' }}; font-weight: bold; text-transform: uppercase; letter-spacing: 0.8px; margin-top: 2px;">
                    {{ $boutique->type_etablissement === 'entrepot' ? 'Entrepôt / Dépôt Central' : 'Boutique & Approvisionnements' }}
                </div>
                <div style="font-size: 7.5pt; color: #64748b; margin-top: 5px; line-height: 1.4;">
                    @if(!empty($boutique->adresse)) <div>{{ $boutique->adresse }}</div> @endif
                    <div>Tél : {{ $boutique->telephone ?? 'Non renseigné' }} @if(!empty($boutique->email)) | Email : {{ $boutique->email }} @endif</div>
                    @if(!empty($boutique->nif) || !empty($boutique->rccm))
                        <div>NIF : {{ $boutique->nif ?? '-' }} | RCCM : {{ $boutique->rccm ?? '-' }}</div>
                    @endif
                </div>
            </td>
            <td style="width: 45%; text-align: right;">
                <div class="doc-title-badge">
                    {{ $docTitle }}
                </div>
                <table style="width: 100%; border: none; font-size: 7.5pt; margin-top: 5px;">
                    <tr>
                        <td style="text-align: right; color: #64748b; padding: 1.5px 4px;">Référence Interne :</td>
                        <td style="text-align: right; font-weight: 800; color: #0f172a; padding: 1.5px 0;">
                            {{ $achat->reference }}
                        </td>
                    </tr>
                    @if(!empty($achat->numero_facture_fournisseur))
                    <tr>
                        <td style="text-align: right; color: #64748b; padding: 1.5px 4px;">Facture / BL Fournisseur :</td>
                        <td style="text-align: right; font-weight: 700; color: #0f172a; padding: 1.5px 0;">
                            {{ $achat->numero_facture_fournisseur }}
                        </td>
                    </tr>
                    @endif
                    <tr>
                        <td style="text-align: right; color: #64748b; padding: 1.5px 4px;">Date Commande :</td>
                        <td style="text-align: right; font-weight: 600; color: #0f172a; padding: 1.5px 0;">
                            {{ \Carbon\Carbon::parse($achat->date_commande)->format('d/m/Y') }}
                        </td>
                    </tr>
                    @if($achat->date_reception)
                    <tr>
                        <td style="text-align: right; color: #64748b; padding: 1.5px 4px;">Date Réception :</td>
                        <td style="text-align: right; font-weight: 600; color: #059669; padding: 1.5px 0;">
                            {{ \Carbon\Carbon::parse($achat->date_reception)->format('d/m/Y H:i') }}
                        </td>
                    </tr>
                    @endif
                    <tr>
                        <td style="text-align: right; color: #64748b; padding: 1.5px 4px;">Statut :</td>
                        <td style="text-align: right; padding: 1.5px 0;">
                            <span class="badge {{ $statutClass }}">{{ $statutLabel }}</span>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    <!-- Fournisseur & Conditions -->
    <table class="info-card-table">
        <tr>
            <td style="width: 50%; padding-right: 10px;">
                <div class="info-card">
                    <div class="info-card-title">FOURNISSEUR / EXPÉDITEUR</div>
                    @if($fournisseur)
                        <div style="font-size: 9pt; font-weight: 800; color: #0f172a;">
                            {{ $fournisseur->entreprise ?: $fournisseur->nom }}
                        </div>
                        @if($fournisseur->entreprise && $fournisseur->nom)
                            <div style="font-size: 7.5pt; color: #475569; margin-top: 2px;">Contact : {{ $fournisseur->nom }}</div>
                        @endif
                        <div style="font-size: 7.5pt; color: #64748b; margin-top: 2px; line-height: 1.35;">
                            @if($fournisseur->pays_ville) <div>Origine : {{ $fournisseur->pays_ville }}</div> @endif
                            @if($fournisseur->telephone) <div>Tél : {{ $fournisseur->telephone }}</div> @endif
                            @if($fournisseur->nif || $fournisseur->rccm)
                                <div>NIF : {{ $fournisseur->nif ?: '-' }} | RCCM : {{ $fournisseur->rccm ?: '-' }}</div>
                            @endif
                        </div>
                    @else
                        <div style="font-size: 8.5pt; font-weight: 700; color: #475569;">Fournisseur divers / Achat direct</div>
                    @endif
                </div>
            </td>
            <td style="width: 50%; padding-left: 10px;">
                <div class="info-card">
                    <div class="info-card-title">RÈGLEMENT &amp; DESTINATION</div>
                    <div style="font-size: 8.5pt; font-weight: 800; color: #0f172a;">
                        Site de réception : {{ $achat->boutique->nom ?? $boutique->nom }}
                    </div>
                    <div style="font-size: 7.5pt; color: #475569; margin-top: 3px; line-height: 1.4;">
                        <div>
                            Mode : <strong>{{ strtoupper($achat->type_paiement) }}</strong>
                            ({{ ucfirst(str_replace('_', ' ', $achat->moyen_paiement)) }})
                        </div>
                        @if($achat->date_echeance && (float)$achat->montant_restant > 0)
                            <div style="color: #b45309; font-weight: 700;">
                                Échéance solde (Compte 401) : {{ \Carbon\Carbon::parse($achat->date_echeance)->format('d/m/Y') }}
                            </div>
                        @elseif((float)$achat->montant_restant <= 0)
                            <div style="color: #059669; font-weight: 700;">Facture entièrement soldée</div>
                        @endif
                        <div>Établi par : {{ $achat->user->name ?? 'Système' }}</div>
                    </div>
                </div>
            </td>
        </tr>
    </table>

    <!-- Tableau des articles -->
    <table class="items-table">
        <thead>
            <tr>
                <th style="width: 28%;">Désignation Article</th>
                <th style="width: 14%; text-align: center;">Conditionnement</th>
                <th style="width: 11%; text-align: center;">Qté Base</th>
                <th style="width: 13%; text-align: right;">P.A. Unitaire HT</th>
                <th style="width: 11%; text-align: right;">Frais / u</th>
                <th style="width: 11%; text-align: right;">Revient / u</th>
                <th style="width: 12%; text-align: right;">Total HT</th>
            </tr>
        </thead>
        <tbody>
            @foreach($achat->details as $detail)
                @php
                    $prod = $detail->produit;
                    $uBase = $prod?->unite_base ?: 'Unité';
                    $qCond = (int)($detail->quantite_conditionnement ?: 1);
                    $nbColis = $detail->nombre_colis;
                @endphp
                <tr>
                    <td>
                        <div style="font-weight: 700; color: #0f172a;">{{ $prod->nom ?? ('Article #' . $detail->produit_id) }}</div>
                        @if(!empty($prod?->reference))
                            <div style="font-size: 6.5pt; color: #64748b;">Réf : {{ $prod->reference }}</div>
                        @endif
                    </td>
                    <td style="text-align: center; font-size: 7.5pt; color: #4f46e5; font-weight: 600;">
                        @if($nbColis && $qCond > 1)
                            {{ $nbColis }} {{ $detail->unite_achat ?: 'Colis' }} (×{{ $qCond }})
                        @else
                            {{ $detail->unite_achat ?: $uBase }}
                        @endif
                    </td>
                    <td style="text-align: center; font-weight: 700; color: #0f172a;">
                        {{ number_format($detail->quantite_commandee, 0, ',', ' ') }}
                        <span style="font-size: 6.5pt; color: #64748b;">{{ $uBase }}</span>
                    </td>
                    <td style="text-align: right;">{{ number_format($detail->prix_achat_unitaire, 0, ',', ' ') }} F</td>
                    <td style="text-align: right; color: #64748b;">
                        +{{ number_format($detail->frais_approche_unitaire, 0, ',', ' ') }} F
                    </td>
                    <td style="text-align: right; font-weight: 700; color: #0f766e;">
                        {{ number_format($detail->cout_revient_unitaire, 0, ',', ' ') }} F
                    </td>
                    <td style="text-align: right; font-weight: 700;">
                        {{ number_format($detail->montant_ligne, 0, ',', ' ') }} F
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <!-- Totaux & Frais d'approche -->
    <table style="width: 100%; border-collapse: collapse; margin-top: 8px;">
        <tr>
            <td style="width: 52%; vertical-align: top; padding-right: 14px;">
                <div style="background-color: #f8fafc; border: 1px solid #e2e8f0; border-radius: 4px; padding: 8px 10px;">
                    <div style="font-size: 6.5pt; font-weight: 700; color: #64748b; text-transform: uppercase; margin-bottom: 3px;">
                        OBSERVATIONS &amp; ANALYSE DES FRAIS D'APPROCHE
                    </div>
                    <div style="font-size: 7pt; color: #475569; line-height: 1.4;">
                        {{ $achat->note ?: 'Les frais de transport, douane et manutention sont automatiquement intégrés au coût de revient unitaire débarqué et imputés au compte SYSCOHADA 611.' }}
                    </div>
                    @if($achat->paiements && $achat->paiements->count() > 0)
                        <div style="margin-top: 6px; border-top: 1px dashed #cbd5e1; padding-top: 5px;">
                            <div style="font-size: 6.5pt; font-weight: 700; color: #334155; text-transform: uppercase;">Historique des Règlements :</div>
                            @foreach($achat->paiements as $p)
                                <div style="font-size: 6.5pt; color: #475569;">
                                    • {{ \Carbon\Carbon::parse($p->date_paiement)->format('d/m/Y') }} : <strong>{{ number_format($p->montant, 0, ',', ' ') }} FCFA</strong> ({{ ucfirst(str_replace('_', ' ', $p->moyen_paiement)) }})
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </td>
            <td style="width: 48%; vertical-align: top;">
                <table class="totals-table">
                    <tr>
                        <td class="total-label">Sous-Total Marchandises HT</td>
                        <td class="total-amount">{{ number_format($achat->sous_total_ht, 0, ',', ' ') }} FCFA</td>
                    </tr>
                    @if((float)$achat->frais_transport > 0)
                    <tr>
                        <td class="total-label">Frais de Transport (611)</td>
                        <td class="total-amount">+{{ number_format($achat->frais_transport, 0, ',', ' ') }} FCFA</td>
                    </tr>
                    @endif
                    @if((float)$achat->frais_douane > 0)
                    <tr>
                        <td class="total-label">Frais de Douane / Transit</td>
                        <td class="total-amount">+{{ number_format($achat->frais_douane, 0, ',', ' ') }} FCFA</td>
                    </tr>
                    @endif
                    @if((float)$achat->frais_manutention > 0)
                    <tr>
                        <td class="total-label">Frais de Manutention</td>
                        <td class="total-amount">+{{ number_format($achat->frais_manutention, 0, ',', ' ') }} FCFA</td>
                    </tr>
                    @endif
                    @if((float)$achat->remise > 0)
                    <tr>
                        <td class="total-label">Remise Fournisseur</td>
                        <td class="total-amount" style="color: #059669;">-{{ number_format($achat->remise, 0, ',', ' ') }} FCFA</td>
                    </tr>
                    @endif
                    <tr class="highlight-row">
                        <td class="total-label" style="font-weight: 900;">TOTAL GÉNÉRAL DÉBARQUÉ</td>
                        <td class="total-amount" style="font-size: 10pt;">{{ number_format($achat->montant_total, 0, ',', ' ') }} FCFA</td>
                    </tr>
                    <tr>
                        <td class="total-label">Montant Payé</td>
                        <td class="total-amount" style="color: #059669;">{{ number_format($achat->montant_paye, 0, ',', ' ') }} FCFA</td>
                    </tr>
                    <tr>
                        <td class="total-label" style="font-weight: 800; color: {{ (float)$achat->montant_restant > 0 ? '#b91c1c' : '#059669' }};">
                            SOLDE DÛ FOURNISSEUR (401)
                        </td>
                        <td class="total-amount" style="font-weight: 900; color: {{ (float)$achat->montant_restant > 0 ? '#b91c1c' : '#059669' }};">
                            {{ number_format($achat->montant_restant, 0, ',', ' ') }} FCFA
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    <!-- Signatures -->
    <div class="signatures-pinned-bottom">
        <table class="signatures-table">
            <tr>
                <td style="width: 33%;">
                    <div class="signature-title">Responsable Achats / Direction</div>
                    <div class="signature-line"></div>
                </td>
                <td style="width: 34%;">
                    <div class="signature-title">Magasinier / Réceptionnaire</div>
                    <div class="signature-line"></div>
                </td>
                <td style="width: 33%;">
                    <div class="signature-title">Fournisseur / Livreur</div>
                    <div class="signature-line"></div>
                </td>
            </tr>
        </table>
    </div>
@endsection
