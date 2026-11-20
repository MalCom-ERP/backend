@extends('pdf.layouts.base')

@section('title', 'Rapport Hebdomadaire — Semaine ' . ($periode['semaine_iso'] ?? '') . ' (' . ($periode['start_fr'] ?? '') . ' - ' . ($periode['end_fr'] ?? '') . ')')

@section('fixed_footer')
    <div class="doc-footer-fixed">
        <div><strong>{{ $boutique->nom ?? 'MalCom' }}</strong> • Synthèse hebdomadaire de gestion (sans détail nominatif des articles) • MalCom Cloud v2.0</div>
    </div>
@endsection

@php
    $devise = $boutique->devise ?? 'FCFA';
    $brandPrimary = !empty($boutique->couleur_principale) ? $boutique->couleur_principale : '#0f172a';
    $brandSecondary = !empty($boutique->couleur_secondaire) ? $boutique->couleur_secondaire : '#4f46e5';
    $totalEncaisse = max(1, (float) ($totaux['total_encaisse_reel'] ?? 0));
    $varCa = $comparaison['variation_ca_pct'] ?? null;
    $varVtes = $comparaison['variation_ventes_pct'] ?? null;
    $ratioDepenses = ($totaux['ventes_net'] ?? 0) > 0
        ? round((($totaux['depenses'] ?? 0) / $totaux['ventes_net']) * 100, 1)
        : 0;
@endphp

@section('content')
    <!-- En-tête Exécutif -->
    <table class="header-table">
        <tr>
            <td style="width: 56%;">
                @if(!empty($boutique->logo))
                    <div style="margin-bottom: 5px;">
                        <img src="{{ $boutique->logo }}" style="max-height: 40px; max-width: 140px; object-fit: contain;">
                    </div>
                @endif
                <div style="font-size: 14pt; font-weight: 900; color: {{ $brandPrimary }}; text-transform: uppercase; letter-spacing: 0.5px;">
                    {{ $boutique->nom ?? 'MALCOM COMMERCE' }}
                </div>
                <div style="font-size: 7.5pt; color: {{ $brandSecondary }}; font-weight: bold; text-transform: uppercase; letter-spacing: 0.9px; margin-top: 2px;">
                    Rapport Hebdomadaire de Performance &amp; Trésorerie
                </div>
                <div style="font-size: 7.5pt; color: #475569; margin-top: 4px;">
                    {{ $boutique->adresse ?? '---' }} | Tél : {{ $boutique->telephone ?? '---' }}
                </div>
            </td>
            <td style="width: 44%; text-align: right;">
                <div class="doc-title-badge">
                    RAPPORT DE LA SEMAINE
                </div>
                <table style="width: 100%; border: none; font-size: 7.5pt; margin-top: 2px;">
                    <tr>
                        <td style="text-align: right; color: #64748b; padding: 1px 4px;">Semaine ISO :</td>
                        <td style="text-align: right; font-weight: 900; color: #0f172a;">
                            Semaine {{ str_pad((string) ($periode['semaine_iso'] ?? ''), 2, '0', STR_PAD_LEFT) }} / {{ $periode['annee'] ?? '' }}
                        </td>
                    </tr>
                    <tr>
                        <td style="text-align: right; color: #64748b; padding: 1px 4px;">Période auditée :</td>
                        <td style="text-align: right; font-weight: 900; color: #0f172a;">
                            Du {{ $periode['start_fr'] ?? '' }} au {{ $periode['end_fr'] ?? '' }} ({{ $periode['jours_count'] ?? 7 }} j)
                        </td>
                    </tr>
                    <tr>
                        <td style="text-align: right; color: #64748b; padding: 1px 4px;">Date d'édition :</td>
                        <td style="text-align: right; font-weight: bold; color: #475569;">{{ date('d/m/Y H:i') }}</td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    <!-- Ligne KPI Exécutive (4 cartes) -->
    <table style="width: 100%; border-collapse: separate; border-spacing: 5px 0; margin-bottom: 10px;">
        <tr>
            <td style="background-color: #f8fafc; border: 1px solid #cbd5e1; border-radius: 4px; padding: 7px 8px; width: 25%; vertical-align: top;">
                <div style="font-size: 6pt; font-weight: bold; color: #64748b; text-transform: uppercase;">CHIFFRE D'AFFAIRES NET</div>
                <div style="font-size: 11pt; font-weight: 900; color: #0f172a; margin-top: 2px;">
                    {{ number_format($totaux['ventes_net'] ?? 0, 0, ',', ' ') }} <span style="font-size: 6.5pt; font-weight: normal;">{{ $devise }}</span>
                </div>
                <div style="font-size: 6.5pt; margin-top: 3px; color: {{ $varCa === null ? '#64748b' : ($varCa >= 0 ? '#047857' : '#be123c') }}; font-weight: bold;">
                    @if($varCa === null)
                        {{ $stats['nombre_ventes'] ?? 0 }} vente(s) • {{ $stats['total_unites_vendues'] ?? 0 }} unité(s)
                    @else
                        {{ $varCa >= 0 ? '▲ +' : '▼ ' }}{{ $varCa }}% vs sem. préc. ({{ $stats['nombre_ventes'] ?? 0 }} vtes)
                    @endif
                </div>
            </td>
            <td style="background-color: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 4px; padding: 7px 8px; width: 25%; vertical-align: top;">
                <div style="font-size: 6pt; font-weight: bold; color: #166534; text-transform: uppercase;">TOTAL ENCAISSÉ RÉEL</div>
                <div style="font-size: 11pt; font-weight: 900; color: #15803d; margin-top: 2px;">
                    {{ number_format($totaux['total_encaisse_reel'] ?? 0, 0, ',', ' ') }} <span style="font-size: 6.5pt; font-weight: normal;">{{ $devise }}</span>
                </div>
                <div style="font-size: 6.5pt; color: #166534; margin-top: 3px; font-weight: bold;">
                    Recouvrements : {{ number_format($totaux['total_recouvrements'] ?? 0, 0, ',', ' ') }} F
                </div>
            </td>
            <td style="background-color: #fff1f2; border: 1px solid #fecdd3; border-radius: 4px; padding: 7px 8px; width: 25%; vertical-align: top;">
                <div style="font-size: 6pt; font-weight: bold; color: #9f1239; text-transform: uppercase;">CHARGES &amp; DÉPENSES</div>
                <div style="font-size: 11pt; font-weight: 900; color: #be123c; margin-top: 2px;">
                    {{ number_format($totaux['depenses'] ?? 0, 0, ',', ' ') }} <span style="font-size: 6.5pt; font-weight: normal;">{{ $devise }}</span>
                </div>
                <div style="font-size: 6.5pt; color: #9f1239; margin-top: 3px; font-weight: bold;">
                    {{ $stats['nombre_depenses'] ?? 0 }} charge(s) • {{ $ratioDepenses }}% du CA
                </div>
            </td>
            <td style="background-color: #eff6ff; border: 1px solid #bfdbfe; border-radius: 4px; padding: 7px 8px; width: 25%; vertical-align: top;">
                <div style="font-size: 6pt; font-weight: bold; color: #1e40af; text-transform: uppercase;">VENTES À CRÉDIT</div>
                <div style="font-size: 11pt; font-weight: 900; color: #1e40af; margin-top: 2px;">
                    {{ number_format($clients_summary['nouveaux_credits_semaine'] ?? 0, 0, ',', ' ') }} <span style="font-size: 6.5pt; font-weight: normal;">{{ $devise }}</span>
                </div>
                <div style="font-size: 6.5pt; color: #1e3a8a; margin-top: 3px; font-weight: bold;">
                    Encours global : {{ number_format($clients_summary['encours_credit_global'] ?? 0, 0, ',', ' ') }} F
                </div>
            </td>
        </tr>
    </table>

    <!-- Indicateurs de performance clés -->
    <table style="width: 100%; border-collapse: collapse; margin-bottom: 10px; background-color: #f8fafc; border: 1px solid #e2e8f0; border-radius: 4px; font-size: 7.2pt;">
        <tr>
            <td style="padding: 5px 8px; width: 25%; border-right: 1px solid #e2e8f0;">
                <span style="color: #64748b; text-transform: uppercase; font-size: 6pt; font-weight: bold;">Panier Moyen</span><br>
                <strong style="color: #0f172a; font-size: 8.5pt;">{{ number_format($stats['vente_moyenne'] ?? 0, 0, ',', ' ') }} {{ $devise }}</strong>
            </td>
            <td style="padding: 5px 8px; width: 25%; border-right: 1px solid #e2e8f0;">
                <span style="color: #64748b; text-transform: uppercase; font-size: 6pt; font-weight: bold;">Moyenne / Jour</span><br>
                <strong style="color: #0f172a; font-size: 8.5pt;">{{ number_format($stats['moyenne_journaliere_ca'] ?? 0, 0, ',', ' ') }} {{ $devise }}</strong>
            </td>
            <td style="padding: 5px 8px; width: 25%; border-right: 1px solid #e2e8f0;">
                <span style="color: #64748b; text-transform: uppercase; font-size: 6pt; font-weight: bold;">Meilleur Jour</span><br>
                <strong style="color: #047857; font-size: 8.5pt;">
                    @if(!empty($best_day))
                        {{ $best_day['jour_label'] }} ({{ number_format($best_day['ca_total'], 0, ',', ' ') }} F)
                    @else
                        Aucune vente
                    @endif
                </strong>
            </td>
            <td style="padding: 5px 8px; width: 25%;">
                <span style="color: #64748b; text-transform: uppercase; font-size: 6pt; font-weight: bold;">Annulations Semaine</span><br>
                <strong style="color: {{ ($stats['ventes_annulee'] ?? 0) > 0 ? '#be123c' : '#475569' }}; font-size: 8.5pt;">
                    {{ $stats['ventes_annulee'] ?? 0 }} ({{ number_format($totaux['ventes_annulee_net'] ?? 0, 0, ',', ' ') }} F)
                </strong>
            </td>
        </tr>
    </table>

    <!-- Section I : Évolution Journalière de la Semaine -->
    <div style="font-size: 8pt; font-weight: 900; color: #0f172a; text-transform: uppercase; margin-top: 6px; margin-bottom: 3px;">
        I. Évolution Journalière de l'Activité Commerciale
    </div>
    <table class="table-data" style="margin-top: 2px; margin-bottom: 10px;">
        <thead>
            <tr>
                <th style="width: 22%; text-align: left;">JOUR</th>
                <th style="width: 12%; text-align: center;">NB VENTES</th>
                <th style="width: 17%; text-align: right;">COMPTANT</th>
                <th style="width: 16%; text-align: right;">CRÉDIT</th>
                <th style="width: 17%; text-align: right;">CA TOTAL</th>
                <th style="width: 16%; text-align: right;">DÉPENSES</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($daily_breakdown as $day)
                <tr @if(!empty($day['is_best_day'])) style="background-color: #f0fdf4;" @endif>
                    <td style="font-weight: bold; color: #0f172a; font-size: 7.5pt;">
                        {{ $day['jour_label'] }}
                        @if(!empty($day['is_best_day']))
                            <span class="badge badge-success" style="font-size: 5.5pt; padding: 1px 4px; margin-left: 2px;">TOP</span>
                        @endif
                    </td>
                    <td style="text-align: center; font-weight: bold;">{{ $day['nb_ventes'] }}</td>
                    <td style="text-align: right; font-family: monospace;">{{ number_format($day['ca_comptant'], 0, ',', ' ') }}</td>
                    <td style="text-align: right; font-family: monospace; color: #b45309;">{{ number_format($day['ca_credit'], 0, ',', ' ') }}</td>
                    <td style="text-align: right; font-weight: bold; font-family: monospace; color: #0f172a;">{{ number_format($day['ca_total'], 0, ',', ' ') }}</td>
                    <td style="text-align: right; font-family: monospace; color: #be123c;">{{ number_format($day['depenses'], 0, ',', ' ') }}</td>
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr style="background-color: #f1f5f9;">
                <td style="font-weight: 900; color: #0f172a; padding: 6px 8px;">TOTAL SEMAINE</td>
                <td style="text-align: center; font-weight: 900; padding: 6px 8px;">{{ $stats['nombre_ventes'] ?? 0 }}</td>
                <td style="text-align: right; font-weight: bold; padding: 6px 8px;">{{ number_format($totaux['ventes_cash_net'] ?? 0, 0, ',', ' ') }}</td>
                <td style="text-align: right; font-weight: bold; color: #b45309; padding: 6px 8px;">{{ number_format($totaux['ventes_credit_net'] ?? 0, 0, ',', ' ') }}</td>
                <td style="text-align: right; font-weight: 900; color: #0f172a; padding: 6px 8px;">{{ number_format($totaux['ventes_net'] ?? 0, 0, ',', ' ') }} {{ $devise }}</td>
                <td style="text-align: right; font-weight: 900; color: #be123c; padding: 6px 8px;">{{ number_format($totaux['depenses'] ?? 0, 0, ',', ' ') }} {{ $devise }}</td>
            </tr>
        </tfoot>
    </table>

    <!-- Section II : Trésorerie par canal & Synthèse Crédits / Achats globaux -->
    <div style="font-size: 8pt; font-weight: 900; color: #0f172a; text-transform: uppercase; margin-top: 6px; margin-bottom: 3px;">
        II. Ventilation des Encaissements, Crédits Clients &amp; Approvisionnements
    </div>
    <table style="width: 100%; border-collapse: collapse; margin-bottom: 10px;">
        <tr>
            <td style="width: 50%; vertical-align: top; padding-right: 6px;">
                <table class="table-data" style="margin: 0;">
                    <thead>
                        <tr>
                            <th style="text-align: left;">CANAL D'ENCAISSEMENT</th>
                            <th style="text-align: right;">MONTANT ({{ $devise }})</th>
                            <th style="text-align: right; width: 18%;">PART</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>Espèces (Ventes &amp; Avances)</td>
                            <td style="text-align: right; font-weight: bold; font-family: monospace;">{{ number_format($totaux['especes_ventes'] ?? 0, 0, ',', ' ') }}</td>
                            <td style="text-align: right; color: #64748b;">{{ round((($totaux['especes_ventes'] ?? 0) / $totalEncaisse) * 100, 1) }}%</td>
                        </tr>
                        <tr>
                            <td>Orange Money</td>
                            <td style="text-align: right; font-weight: bold; font-family: monospace;">{{ number_format($totaux['orange_money'] ?? 0, 0, ',', ' ') }}</td>
                            <td style="text-align: right; color: #64748b;">{{ round((($totaux['orange_money'] ?? 0) / $totalEncaisse) * 100, 1) }}%</td>
                        </tr>
                        <tr>
                            <td>Moov Money</td>
                            <td style="text-align: right; font-weight: bold; font-family: monospace;">{{ number_format($totaux['moov_money'] ?? 0, 0, ',', ' ') }}</td>
                            <td style="text-align: right; color: #64748b;">{{ round((($totaux['moov_money'] ?? 0) / $totalEncaisse) * 100, 1) }}%</td>
                        </tr>
                        <tr>
                            <td>Wave</td>
                            <td style="text-align: right; font-weight: bold; font-family: monospace;">{{ number_format($totaux['wave'] ?? 0, 0, ',', ' ') }}</td>
                            <td style="text-align: right; color: #64748b;">{{ round((($totaux['wave'] ?? 0) / $totalEncaisse) * 100, 1) }}%</td>
                        </tr>
                        <tr>
                            <td>Carte / Chèque / Virement</td>
                            <td style="text-align: right; font-weight: bold; font-family: monospace;">{{ number_format($totaux['carte_bancaire'] ?? 0, 0, ',', ' ') }}</td>
                            <td style="text-align: right; color: #64748b;">{{ round((($totaux['carte_bancaire'] ?? 0) / $totalEncaisse) * 100, 1) }}%</td>
                        </tr>
                        <tr>
                            <td>Recouvrements Créances ({{ $clients_summary['recouvrements_nb'] ?? 0 }})</td>
                            <td style="text-align: right; font-weight: bold; font-family: monospace; color: #047857;">+ {{ number_format($totaux['total_recouvrements'] ?? 0, 0, ',', ' ') }}</td>
                            <td style="text-align: right; color: #047857;">{{ round((($totaux['total_recouvrements'] ?? 0) / $totalEncaisse) * 100, 1) }}%</td>
                        </tr>
                        <tr style="background-color: #f1f5f9;">
                            <td style="font-weight: 900; color: #0f172a;">TOTAL ENCAISSÉ RÉEL</td>
                            <td style="text-align: right; font-weight: 900; color: #0f172a; font-family: monospace;">{{ number_format($totaux['total_encaisse_reel'] ?? 0, 0, ',', ' ') }}</td>
                            <td style="text-align: right; font-weight: 900;">100%</td>
                        </tr>
                    </tbody>
                </table>
            </td>

            <td style="width: 50%; vertical-align: top; padding-left: 6px;">
                <table class="table-data" style="margin: 0;">
                    <thead>
                        <tr>
                            <th colspan="2" style="text-align: left;">SYNTHÈSE COMMERCIALE, CRÉDITS &amp; ACHATS GLOBAUX</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>Chiffre d'Affaires Brut / Remises</td>
                            <td style="text-align: right; font-family: monospace;">
                                <strong>{{ number_format($totaux['ventes_brut'] ?? 0, 0, ',', ' ') }} F</strong>
                                <span style="color: #be123c;">(−{{ number_format($totaux['remises'] ?? 0, 0, ',', ' ') }})</span>
                            </td>
                        </tr>
                        <tr>
                            <td>Surplus / Majorations réalisés</td>
                            <td style="text-align: right; font-weight: bold; color: #047857; font-family: monospace;">+ {{ number_format($totaux['surplus'] ?? 0, 0, ',', ' ') }} F</td>
                        </tr>
                        <tr>
                            <td>Clients identifiés servis</td>
                            <td style="text-align: right; font-weight: bold;">{{ $clients_summary['clients_uniques_servis'] ?? 0 }} client(s)</td>
                        </tr>
                        <tr>
                            <td>Ventes à Crédit émises (reste dû)</td>
                            <td style="text-align: right; font-weight: bold; color: #b45309; font-family: monospace;">
                                {{ number_format($clients_summary['nouveaux_credits_semaine'] ?? 0, 0, ',', ' ') }} F
                                <span style="font-weight: normal; color: #64748b;">(dû : {{ number_format($clients_summary['reste_nouveaux_credits'] ?? 0, 0, ',', ' ') }} F)</span>
                            </td>
                        </tr>
                        <tr>
                            <td>Encours Crédit Global Boutique</td>
                            <td style="text-align: right; font-weight: 900; color: #b45309; font-family: monospace;">{{ number_format($clients_summary['encours_credit_global'] ?? 0, 0, ',', ' ') }} F</td>
                        </tr>
                        <tr>
                            <td>Commandes &amp; Achats Fournisseurs ({{ $achats_summary['nb_commandes'] ?? 0 }})</td>
                            <td style="text-align: right; font-weight: bold; font-family: monospace;">{{ number_format($achats_summary['montant_total'] ?? 0, 0, ',', ' ') }} F</td>
                        </tr>
                        <tr>
                            <td>Règlements Fournisseurs &amp; Entrées Stock</td>
                            <td style="text-align: right; font-family: monospace;">
                                <strong>{{ number_format($achats_summary['paiements_fournisseurs_semaine'] ?? 0, 0, ',', ' ') }} F</strong>
                                <span style="color: #64748b;">({{ $achats_summary['nb_entrees_stock'] ?? 0 }} réappros)</span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </td>
        </tr>
    </table>

    <!-- Section III : Dépenses par Catégorie -->
    <div style="font-size: 8pt; font-weight: 900; color: #0f172a; text-transform: uppercase; margin-top: 4px; margin-bottom: 3px;">
        III. Répartition des Dépenses d'Exploitation par Catégorie
    </div>
    <table class="table-data" style="margin-top: 2px; margin-bottom: 10px;">
        <thead>
            <tr>
                <th style="width: 48%; text-align: left;">CATÉGORIE DE DÉPENSE</th>
                <th style="width: 16%; text-align: center;">OPÉRATIONS</th>
                <th style="width: 20%; text-align: right;">MONTANT ({{ $devise }})</th>
                <th style="width: 16%; text-align: right;">PART %</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($depenses_par_categorie as $depCat)
                <tr>
                    <td style="font-weight: bold; color: #0f172a;">{{ $depCat['categorie'] }}</td>
                    <td style="text-align: center;">{{ $depCat['count'] }}</td>
                    <td style="text-align: right; font-weight: bold; color: #be123c; font-family: monospace;">- {{ number_format($depCat['montant'], 0, ',', ' ') }}</td>
                    <td style="text-align: right; color: #64748b;">{{ $depCat['part_pct'] }}%</td>
                </tr>
            @empty
                <tr>
                    <td colspan="4" style="text-align: center; padding: 8px; color: #94a3b8; font-style: italic;">
                        Aucune dépense enregistrée sur cette période.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <!-- Section IV : Santé Globale du Stock & Performance Équipe -->
    <div style="font-size: 8pt; font-weight: 900; color: #0f172a; text-transform: uppercase; margin-top: 4px; margin-bottom: 3px;">
        IV. Santé Globale du Stock &amp; Performance de l'Équipe
    </div>
    <table style="width: 100%; border-collapse: collapse; margin-bottom: 8px;">
        <tr>
            <td style="width: 48%; vertical-align: top; padding-right: 6px;">
                <table class="table-data" style="margin: 0;">
                    <thead>
                        <tr>
                            <th colspan="2" style="text-align: left;">INDICATEURS GLOBAUX DU STOCK (INSTANTANÉ)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>Références actives / En stock</td>
                            <td style="text-align: right; font-weight: bold;">{{ $stock_health['en_stock_count'] ?? 0 }} / {{ $stock_health['total_references'] ?? 0 }}</td>
                        </tr>
                        <tr>
                            <td>Références sous seuil d'alerte</td>
                            <td style="text-align: right; font-weight: bold; color: #b45309;">{{ $stock_health['alerte_count'] ?? 0 }}</td>
                        </tr>
                        <tr>
                            <td>Références en rupture (qté = 0)</td>
                            <td style="text-align: right; font-weight: bold; color: #be123c;">{{ $stock_health['rupture_count'] ?? 0 }}</td>
                        </tr>
                        <tr>
                            <td>Valeur Stock au Prix d'Achat</td>
                            <td style="text-align: right; font-weight: bold; font-family: monospace;">{{ number_format($stock_health['valeur_stock_achat'] ?? 0, 0, ',', ' ') }} F</td>
                        </tr>
                        <tr>
                            <td>Valeur Stock au Prix de Vente</td>
                            <td style="text-align: right; font-weight: bold; font-family: monospace; color: #0f172a;">{{ number_format($stock_health['valeur_stock_vente'] ?? 0, 0, ',', ' ') }} F</td>
                        </tr>
                    </tbody>
                </table>
            </td>

            <td style="width: 52%; vertical-align: top; padding-left: 6px;">
                <table class="table-data" style="margin: 0;">
                    <thead>
                        <tr>
                            <th style="text-align: left;">OPÉRATEUR / VENDEUR</th>
                            <th style="text-align: center;">VTES</th>
                            <th style="text-align: right;">CA ({{ $devise }})</th>
                            <th style="text-align: right;">PART</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($performance_vendeurs as $vendeur)
                            <tr>
                                <td style="font-weight: bold; color: #0f172a;">{{ $vendeur['nom'] }}</td>
                                <td style="text-align: center;">{{ $vendeur['nb_ventes'] }}</td>
                                <td style="text-align: right; font-weight: bold; font-family: monospace;">{{ number_format($vendeur['ca_total'], 0, ',', ' ') }}</td>
                                <td style="text-align: right; color: #475569;">{{ $vendeur['part_pct'] }}%</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" style="text-align: center; padding: 8px; color: #94a3b8; font-style: italic;">
                                    Aucune vente enregistrée cette semaine.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>

                <!-- Bandeau Clôtures de caisse -->
                <table style="width: 100%; border-collapse: collapse; margin-top: 6px; border: 1px solid #e2e8f0; background-color: #f8fafc; font-size: 7pt;">
                    <tr>
                        <td style="padding: 5px 8px; color: #475569;">
                            <strong>Clôtures de caisse :</strong>
                            {{ $clotures_resume['caisses_cloturees'] ?? 0 }} journée(s) clôturée(s) sur {{ $clotures_resume['jours_periode'] ?? 7 }}
                        </td>
                        <td style="padding: 5px 8px; text-align: right; font-weight: bold; font-family: monospace; color: {{ ($clotures_resume['cumul_ecart_caisse'] ?? 0) == 0 ? '#047857' : '#be123c' }};">
                            Cumul écart : {{ ($clotures_resume['cumul_ecart_caisse'] ?? 0) > 0 ? '+' : '' }}{{ number_format($clotures_resume['cumul_ecart_caisse'] ?? 0, 0, ',', ' ') }} F
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
                <td>
                    <div class="signature-title">Visa du Gérant / Responsable de Boutique</div>
                    <div class="signature-line"></div>
                </td>
                <td>
                    <div class="signature-title">Visa de la Direction Générale / Contrôle de Gestion</div>
                    <div class="signature-line"></div>
                </td>
            </tr>
        </table>
    </div>
@endsection
