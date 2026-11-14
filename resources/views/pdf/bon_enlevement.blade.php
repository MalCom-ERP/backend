@extends('pdf.layouts.base')

@section('title', 'Bon d\'Enlèvement N° ' . $bon->reference)

@section('fixed_footer')
    <div class="doc-footer-fixed">
        <div><strong>{{ $boutique->nom ?? 'MalCom' }}</strong> • {{ $boutique->footer_bordereau ?? 'Bon officiel d\'enlèvement et de sortie de marchandises • Conforme MalCom' }}</div>
    </div>
@endsection

@section('content')
    @php
        $statutLabel = match($bon->statut) {
            'livre' => 'LIVRÉ / ENLEVÉ',
            'partiel' => 'ENLÈVEMENT PARTIEL',
            'annule' => 'ANNULÉ',
            default => 'EN ATTENTE DE RETRAIT',
        };
        $statutClass = match($bon->statut) {
            'livre' => 'badge-success',
            'partiel' => 'badge-warning',
            'annule' => 'badge-danger',
            default => 'badge-info',
        };
        $emetteur = $bon->boutiqueEmettrice ?: $boutique;
        $entrepot = $bon->entrepot ?: $emetteur;
    @endphp

    <!-- Official Header -->
    <table class="header-table">
        <tr>
            <td style="width: 56%;">
                @if(!empty($emetteur->logo))
                    <div style="margin-bottom: 6px;">
                        <img src="{{ $emetteur->logo }}" style="max-height: 44px; max-width: 150px; object-fit: contain;">
                    </div>
                @endif
                <div style="font-size: 14pt; font-weight: 900; color: {{ !empty($emetteur->couleur_principale) ? $emetteur->couleur_principale : '#0f172a' }}; text-transform: uppercase; letter-spacing: 0.5px;">
                    {{ $emetteur->nom ?? 'MALCOM COMMERCE' }}
                </div>
                <div style="font-size: 7.5pt; color: {{ !empty($emetteur->couleur_secondaire) ? $emetteur->couleur_secondaire : '#4f46e5' }}; font-weight: bold; text-transform: uppercase; letter-spacing: 0.8px; margin-top: 2px;">
                    Bon d'Enlèvement &amp; Ordre de Sortie Dépôt
                </div>
                <div style="font-size: 7.5pt; color: #64748b; margin-top: 5px; line-height: 1.4;">
                    @if(!empty($emetteur->adresse)) <div>{{ $emetteur->adresse }}</div> @endif
                    <div>Tél : {{ $emetteur->telephone ?? 'Non renseigné' }} @if(!empty($emetteur->email)) | Email : {{ $emetteur->email }} @endif</div>
                </div>
            </td>
            <td style="width: 44%; text-align: right;">
                <div class="doc-title-badge">
                    BON D'ENLÈVEMENT
                </div>
                <table style="width: 100%; border: none; font-size: 7.5pt; margin-top: 5px;">
                    <tr>
                        <td style="text-align: right; color: #64748b; padding: 1.5px 4px;">Référence Bon :</td>
                        <td style="text-align: right; font-weight: 800; color: #0f172a; padding: 1.5px 0;">
                            {{ $bon->reference }}
                        </td>
                    </tr>
                    @if($bon->vente_id)
                    <tr>
                        <td style="text-align: right; color: #64748b; padding: 1.5px 4px;">Vente Associée :</td>
                        <td style="text-align: right; font-weight: 700; color: #0f172a; padding: 1.5px 0;">
                            #{{ str_pad($bon->vente_id, 5, '0', STR_PAD_LEFT) }}
                        </td>
                    </tr>
                    @endif
                    <tr>
                        <td style="text-align: right; color: #64748b; padding: 1.5px 4px;">Date d'Émission :</td>
                        <td style="text-align: right; font-weight: 600; color: #0f172a; padding: 1.5px 0;">
                            {{ \Carbon\Carbon::parse($bon->date_emission ?? $bon->created_at)->format('d/m/Y H:i') }}
                        </td>
                    </tr>
                    <tr>
                        <td style="text-align: right; color: #64748b; padding: 1.5px 4px;">Statut Retrait :</td>
                        <td style="text-align: right; padding: 1.5px 0;">
                            <span class="badge {{ $statutClass }}">{{ $statutLabel }}</span>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    <!-- Warehouse & Receiver Info -->
    <table class="info-card-table">
        <tr>
            <td style="width: 48%; padding-right: 12px;">
                <div class="info-card">
                    <div class="info-card-title">DÉPÔT / ENTREPÔT DE RETRAIT</div>
                    <div style="font-size: 9.5pt; font-weight: 800; color: #0f172a; text-transform: uppercase; margin-top: 4px;">
                        {{ $entrepot->nom }}
                    </div>
                    <div style="font-size: 7.5pt; color: #475569; margin-top: 3px; line-height: 1.35;">
                        <div>Adresse dépôt : {{ $entrepot->adresse ?: 'Non précisée' }}</div>
                        <div>Contact dépôt : {{ $entrepot->telephone ?: 'Non précisé' }}</div>
                        <div>Émis par : {{ $bon->user->name ?? 'Caisse' }} ({{ $emetteur->nom }})</div>
                    </div>
                </div>
            </td>
            <td style="width: 4%;"></td>
            <td style="width: 48%; padding-left: 12px;">
                <div class="info-card">
                    <div class="info-card-title">CLIENT &amp; RÉCEPTIONNAIRE / CHAUFFEUR</div>
                    <div style="font-size: 9.5pt; font-weight: 800; color: #0f172a; text-transform: uppercase; margin-top: 4px;">
                        {{ ($bon->client && $bon->client->nom !== 'ANONYME') ? $bon->client->nom : ($bon->recepteur_nom ?: 'CLIENT / PORTEUR') }}
                    </div>
                    <div style="font-size: 7.5pt; color: #475569; margin-top: 3px; line-height: 1.35;">
                        <div>Porteur / Réceptionnaire : <strong>{{ $bon->recepteur_nom ?: 'Non renseigné' }}</strong></div>
                        <div>Téléphone : {{ $bon->recepteur_telephone ?: ($bon->client->telephone ?? 'Non renseigné') }}</div>
                        @if(!empty($bon->chauffeur_immatriculation))
                            <div>Véhicule / Plaque : <strong>{{ $bon->chauffeur_immatriculation }}</strong></div>
                        @endif
                        @if($bon->validePar)
                            <div>Validé en dépôt par : <strong>{{ $bon->validePar->name }}</strong> ({{ \Carbon\Carbon::parse($bon->date_enlevement)->format('d/m/Y H:i') }})</div>
                        @endif
                    </div>
                </div>
            </td>
        </tr>
    </table>

    <!-- Items Table (Quantities & Warehouse Locations - No prices shown for depot confidentiality) -->
    <table class="table-data">
        <thead>
            <tr>
                <th style="width: 6%; text-align: center;">N°</th>
                <th style="width: 14%; text-align: center;">RÉFÉRENCE</th>
                <th style="width: 38%; text-align: left;">DÉSIGNATION ARTICLE</th>
                <th style="width: 18%; text-align: left;">EMPLACEMENT DÉPÔT</th>
                <th style="width: 8%; text-align: center;">CMDÉE</th>
                <th style="width: 8%; text-align: center;">LIVRÉE</th>
                <th style="width: 8%; text-align: center;">RESTE</th>
            </tr>
        </thead>
        <tbody>
            @php
                $idx = 1;
                $totalCommandee = 0;
                $totalLivree = 0;
            @endphp
            @foreach ($bon->details as $detail)
                @php
                    $totalCommandee += $detail->quantite_commandee;
                    $totalLivree += $detail->quantite_livree;
                    $reste = max(0, $detail->quantite_commandee - $detail->quantite_livree);
                @endphp
                <tr>
                    <td style="text-align: center; color: #94a3b8; font-size: 7pt;">{{ $idx++ }}</td>
                    <td style="text-align: center; font-size: 7.5pt; color: #64748b; font-weight: 600;">
                        #{{ $detail->produit->reference ?? str_pad($detail->produit->id ?? $detail->produit_id, 4, '0', STR_PAD_LEFT) }}
                    </td>
                    <td>
                        <div style="font-weight: 700; color: #0f172a; text-transform: uppercase;">
                            {{ $detail->produit->nom ?? ('Article #' . $detail->produit_id) }}
                        </div>
                    </td>
                    <td style="font-size: 7.5pt; color: #475569; font-weight: 600;">
                        {{ $detail->emplacement ?: 'Zone standard' }}
                    </td>
                    @php
                        $qCond = (int)($detail->produit?->quantite_conditionnement ?? 1);
                        $uCond = $detail->produit?->unite_conditionnement;
                        $uBase = $detail->produit?->unite_base ?? '';
                        $nbColisCmd = ($qCond > 1 && !empty($uCond) && $detail->quantite_commandee >= $qCond) ? intdiv((int)$detail->quantite_commandee, $qCond) : 0;
                        $resteCmd = ($nbColisCmd > 0) ? ((int)$detail->quantite_commandee % $qCond) : 0;
                    @endphp
                    <td style="text-align: center; font-weight: 700; color: #0f172a;">
                        <div>{{ $detail->quantite_commandee }}@if(!empty($uBase) && $uBase !== 'Unité') <span style="font-size: 6.5pt; color: #64748b; font-weight: 600;">{{ $uBase }}</span>@endif</div>
                        @if($nbColisCmd > 0)
                            <div style="font-size: 6pt; color: #4f46e5; font-weight: 600;">
                                ({{ $nbColisCmd }} {{ $uCond }}@if($resteCmd > 0) + {{ $resteCmd }} {{ $uBase ?: 'u' }}@endif)
                            </div>
                        @endif
                    </td>
                    <td style="text-align: center; font-weight: 700; color: #059669;">
                        {{ $detail->quantite_livree }}
                    </td>
                    <td style="text-align: center; font-weight: 800; color: {{ $reste > 0 ? '#d97706' : '#059669' }};">
                        {{ $reste }}
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <!-- Summary & Observations -->
    <table style="width: 100%; border-collapse: collapse; margin-top: 6px;">
        <tr>
            <td style="width: 55%; vertical-align: top; padding-right: 15px;">
                <div style="background-color: #f8fafc; border: 1px solid #e2e8f0; border-radius: 4px; padding: 8px 10px;">
                    <div style="font-size: 6.5pt; font-weight: 700; color: #64748b; text-transform: uppercase; margin-bottom: 3px;">
                        OBSERVATIONS &amp; INSTRUCTIONS MAGASINIER
                    </div>
                    <div style="font-size: 7pt; color: #475569; line-height: 1.4;">
                        {{ $bon->motif_ou_note ?: 'Présentez ce bon au chef de dépôt pour contrôler et décharger les quantités avant chargement.' }}
                    </div>
                </div>
            </td>
            <td style="width: 45%; vertical-align: top;">
                <table class="totals-table">
                    <tr>
                        <td class="total-label">TOTAL UNITÉS À ENLEVER</td>
                        <td class="total-amount">{{ $totalCommandee }}</td>
                    </tr>
                    <tr>
                        <td class="total-label">DÉJÀ LIVRÉES / SORTIES</td>
                        <td class="total-amount" style="color: #059669;">{{ $totalLivree }}</td>
                    </tr>
                    <tr class="highlight-row">
                        <td class="total-label" style="font-weight: 900;">RESTE À ENLEVER</td>
                        <td class="total-amount" style="font-size: 10pt;">
                            {{ max(0, $totalCommandee - $totalLivree) }}
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    <!-- Triple Signatures Block -->
    <div class="signatures-pinned-bottom">
        <table class="signatures-table">
            <tr>
                <td style="width: 33%;">
                    <div class="signature-title">Visa Caisse / Émetteur</div>
                    <div class="signature-line"></div>
                </td>
                <td style="width: 34%;">
                    <div class="signature-title">Visa Magasinier / Chef Dépôt</div>
                    <div class="signature-line"></div>
                </td>
                <td style="width: 33%;">
                    <div class="signature-title">Décharge Client / Chauffeur</div>
                    <div class="signature-line"></div>
                </td>
            </tr>
        </table>
    </div>
@endsection
