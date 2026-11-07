@extends('layouts.admin')

@section('content')
    <div class="space-y-10 animate-fade-in">
        <!-- Header with Shop Identity & Back Button -->
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-6 pb-2 border-b border-slate-200/60">
            <div class="flex items-center gap-5">
                <a href="{{ route('admin.boutiques.index') }}"
                    class="w-12 h-12 rounded-2xl bg-white shadow-sm border border-slate-200 flex items-center justify-center text-slate-500 hover:text-brand-600 hover:border-brand-300 transition-all group cursor-pointer"
                    title="Retour au réseau">
                    <i class="bi bi-arrow-left text-xl group-hover:-translate-x-1 transition-transform"></i>
                </a>
                <div>
                    <div class="flex flex-wrap items-center gap-3">
                        <h1 class="text-3xl lg:text-4xl font-black text-slate-900 tracking-tight">{{ $boutique->nom }}</h1>
                        <span class="px-3 py-1 bg-brand-50 text-brand-700 rounded-xl text-xs font-black uppercase tracking-wider border border-brand-200 shadow-sm">
                            {{ $boutique->nature?->name ?? 'Commerce Général' }}
                        </span>
                        @if ($boutique->isFiliale())
                            <span class="inline-flex items-center gap-1.5 px-3 py-1 bg-amber-50 text-amber-700 rounded-xl text-xs font-black uppercase tracking-wider border border-amber-200 shadow-sm">
                                <i class="bi bi-arrow-return-right"></i> Filiale de {{ $boutique->parent?->nom ?? 'Boutique Principale' }}
                            </span>
                        @elseif ($boutique->filiales && $boutique->filiales->count() > 0)
                            <span class="inline-flex items-center gap-1.5 px-3 py-1 bg-indigo-50 text-indigo-700 rounded-xl text-xs font-black uppercase tracking-wider border border-indigo-200 shadow-sm">
                                <i class="bi bi-diagram-3-fill"></i> Principale ({{ $boutique->filiales->count() }} filiale{{ $boutique->filiales->count() > 1 ? 's' : '' }})
                            </span>
                        @endif
                        @if ($boutique->is_active)
                            <span class="inline-flex items-center gap-1.5 px-3 py-1 bg-emerald-50 text-emerald-700 rounded-full text-xs font-black border border-emerald-200">
                                <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                                Ouverte
                            </span>
                        @else
                            <span class="inline-flex items-center gap-1.5 px-3 py-1 bg-rose-50 text-rose-700 rounded-full text-xs font-black border border-rose-200">
                                <span class="w-2 h-2 rounded-full bg-rose-500"></span>
                                Suspendue
                            </span>
                        @endif
                    </div>
                    <div class="flex flex-wrap items-center gap-3 mt-2.5">
                        <span class="inline-flex items-center gap-1.5 px-3 py-1 bg-slate-100 text-slate-600 rounded-xl text-xs font-bold">
                            <i class="bi bi-geo-alt-fill text-brand-500"></i> {{ $boutique->adresse }}
                        </span>
                        <span class="inline-flex items-center gap-1.5 px-3 py-1 bg-slate-100 text-slate-600 rounded-xl text-xs font-bold">
                            <i class="bi bi-telephone-fill text-emerald-500"></i> {{ $boutique->telephone }}
                        </span>
                        <span class="inline-flex items-center gap-1.5 px-3 py-1 bg-slate-100 text-slate-500 rounded-xl text-xs font-mono font-bold">
                            <i class="bi bi-hash text-slate-400"></i> ID: #{{ $boutique->id }}
                        </span>
                    </div>
                </div>
            </div>

            <!-- Action Buttons -->
            <div class="flex flex-wrap items-center gap-3">
                <a href="{{ route('admin.boutiques.users', $boutique->id) }}"
                    class="btn-action bg-slate-900 text-white hover:bg-black shadow-lg shadow-slate-900/20">
                    <i class="bi bi-people-fill text-base"></i>
                    <span>Gestion Personnel</span>
                </a>
            </div>
        </div>

        <!-- 3 Executive KPI Cards Grid (Fixed & Luxury) -->
        <div class="stats-grid">
            <!-- 1. Chiffre d'Affaires Cumulé -->
            <div class="relative overflow-hidden rounded-3xl bg-gradient-to-br from-indigo-700 via-brand-700 to-slate-950 p-7 text-white shadow-xl shadow-brand-900/20 flex flex-col justify-between">
                <div>
                    <div class="flex items-center justify-between mb-4">
                        <span class="text-[10px] font-black uppercase tracking-[0.2em] text-brand-200 flex items-center gap-2">
                            <span class="w-1.5 h-1.5 rounded-full bg-brand-400 animate-pulse"></span>
                            Performance Cumulative
                        </span>
                        <div class="w-10 h-10 rounded-2xl bg-white/10 backdrop-blur-md flex items-center justify-center text-white text-lg">
                            <i class="bi bi-graph-up-arrow"></i>
                        </div>
                    </div>
                    <div class="mt-2">
                        <div class="flex items-baseline gap-2 flex-wrap">
                            <span class="text-3xl lg:text-4xl font-black tracking-tight text-white tabular-nums">
                                {{ number_format($totalRevenue, 0, ',', ' ') }}
                            </span>
                            <span class="text-sm font-extrabold uppercase tracking-wider text-brand-200">FCFA</span>
                        </div>
                    </div>
                </div>
                <div class="mt-6 pt-4 border-t border-white/10 flex items-center justify-between text-xs text-brand-200/80 font-semibold">
                    <span>Depuis l'ouverture</span>
                    <span class="px-2 py-0.5 rounded-md bg-white/10 text-white font-bold text-[10px]">UEMOA</span>
                </div>
                <!-- Ambient blur -->
                <div class="absolute -right-6 -bottom-6 w-32 h-32 bg-brand-400/20 blur-3xl rounded-full pointer-events-none"></div>
            </div>

            <!-- 2. Volume de Transactions -->
            <div class="glass-card rounded-3xl p-7 flex flex-col justify-between">
                <div>
                    <div class="flex items-center justify-between mb-4">
                        <span class="text-[10px] font-black uppercase tracking-[0.2em] text-slate-400">
                            Transactions Commerciales
                        </span>
                        <div class="w-10 h-10 rounded-2xl bg-amber-50 text-amber-600 flex items-center justify-center text-lg shadow-sm">
                            <i class="bi bi-receipt-cutoff"></i>
                        </div>
                    </div>
                    <div class="mt-2">
                        <div class="flex items-baseline gap-2">
                            <span class="text-3xl lg:text-4xl font-black tracking-tight text-slate-900 tabular-nums">
                                {{ number_format($salesCount, 0, ',', ' ') }}
                            </span>
                            <span class="text-xs font-black uppercase text-slate-400">Factures</span>
                        </div>
                    </div>
                </div>
                <div class="mt-6 pt-4 border-t border-slate-100">
                    <div class="w-full bg-slate-100 h-2 rounded-full overflow-hidden">
                        <div class="bg-gradient-to-r from-amber-400 to-amber-500 h-full w-[70%] rounded-full"></div>
                    </div>
                    <div class="flex items-center justify-between mt-2 text-[10px] font-bold text-slate-400 uppercase tracking-wider">
                        <span>Flux de caisse</span>
                        <span class="text-amber-600">Actif</span>
                    </div>
                </div>
            </div>

            <!-- 3. État Opérationnel & Contrôle d'Accès -->
            <div class="glass-card rounded-3xl p-7 flex flex-col justify-between">
                <div>
                    <div class="flex items-center justify-between mb-4">
                        <span class="text-[10px] font-black uppercase tracking-[0.2em] text-slate-400">
                            État Opérationnel
                        </span>
                        <div class="w-10 h-10 rounded-2xl {{ $boutique->is_active ? 'bg-emerald-50 text-emerald-600' : 'bg-rose-50 text-rose-600' }} flex items-center justify-center text-lg shadow-sm">
                            <i class="bi {{ $boutique->is_active ? 'bi-check-circle-fill' : 'bi-slash-circle-fill' }}"></i>
                        </div>
                    </div>
                    <div class="mt-2">
                        <div class="flex items-center gap-3">
                            <span class="w-3 h-3 rounded-full {{ $boutique->is_active ? 'bg-emerald-500 animate-pulse' : 'bg-rose-500' }}"></span>
                            <span class="text-2xl font-black uppercase tracking-tight {{ $boutique->is_active ? 'text-slate-900' : 'text-rose-600' }}">
                                {{ $boutique->is_active ? 'Ouverte' : 'Suspendue' }}
                            </span>
                        </div>
                        <p class="text-xs font-semibold text-slate-400 mt-1">
                            {{ $boutique->is_active ? 'Accès caisse & ventes totalement autorisés' : 'Accès bloqué aux utilisateurs de la boutique' }}
                        </p>
                    </div>
                </div>

                <div class="mt-6 pt-4 border-t border-slate-100">
                    <form action="{{ route('admin.boutiques.toggle-status', $boutique->id) }}" method="POST">
                        @csrf
                        <button type="submit"
                            class="w-full flex items-center justify-center gap-2 py-2.5 px-4 rounded-xl border {{ $boutique->is_active ? 'border-rose-200 bg-rose-50/60 hover:bg-rose-100 text-rose-700' : 'border-emerald-200 bg-emerald-50/60 hover:bg-emerald-100 text-emerald-700' }} font-black text-xs uppercase tracking-wider transition-all cursor-pointer">
                            <i class="bi {{ $boutique->is_active ? 'bi-lock-fill' : 'bi-unlock-fill' }}"></i>
                            {{ $boutique->is_active ? 'Bloquer l\'accès' : 'Rétablir l\'accès' }}
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Section Abonnement & Licences MalCom -->
        <div class="glass-card rounded-[2.5rem] p-8 shadow-card-soft">
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-6 pb-6 border-b border-slate-100">
                <div class="flex items-center gap-4">
                    <div class="w-14 h-14 rounded-2xl bg-amber-50 text-amber-600 flex items-center justify-center text-2xl shadow-sm">
                        <i class="bi bi-key-fill"></i>
                    </div>
                    <div>
                        <h3 class="text-2xl font-black text-slate-900 tracking-tight">Abonnement & Licences MalCom</h3>
                        <p class="text-xs text-slate-500 font-semibold mt-0.5">Contrôlez la validité de l'accès de la caisse et générez des clés d'activation.</p>
                    </div>
                </div>
                <div class="flex flex-wrap items-center gap-3">
                    <button onclick="document.getElementById('modalGenereCleBoutique').classList.remove('hidden')"
                        class="btn-action bg-brand-600 hover:bg-brand-700 text-white shadow-lg shadow-brand-500/20 text-xs">
                        <i class="bi bi-plus-circle-fill text-sm"></i>
                        <span>Générer une Clé</span>
                    </button>
                    <a href="{{ route('admin.licences.index', ['boutique_id' => $boutique->id]) }}"
                        class="btn-action bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs">
                        <i class="bi bi-list-stars text-sm"></i>
                        <span>Historique Complet</span>
                    </a>
                </div>
            </div>

            <!-- Group Context Banner -->
            @if ($boutique->isFiliale() && $boutique->parent)
                <div class="p-4 mb-6 rounded-2xl bg-amber-50 border border-amber-200 text-amber-900 text-xs font-semibold flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                    <div class="flex items-center gap-2">
                        <i class="bi bi-info-circle-fill text-amber-600 text-base"></i>
                        <span>Cette boutique est une <strong>filiale</strong> rattachée au groupe : <strong>{{ $boutique->parent->nom }}</strong>. L'activation et l'expiration de sa licence dépendent de la boutique principale.</span>
                    </div>
                    <a href="{{ route('admin.boutiques.show', $boutique->parent->id) }}" class="px-3 py-1.5 bg-white text-amber-800 rounded-xl font-bold border border-amber-200 hover:bg-amber-100 transition-colors shadow-sm whitespace-nowrap">
                        Voir Boutique Principale
                    </a>
                </div>
            @elseif ($boutique->filiales && $boutique->filiales->count() > 0)
                <div class="p-4 mb-6 rounded-2xl bg-indigo-50 border border-indigo-200 text-indigo-900 text-xs font-semibold">
                    <div class="flex items-center gap-2 mb-2">
                        <i class="bi bi-diagram-3-fill text-indigo-600 text-base"></i>
                        <span>Boutique Principale d'un groupe de <strong>{{ $boutique->filiales->count() }} filiale(s)</strong>. Toute prolongation ou activation de clé s'applique automatiquement à l'ensemble du réseau ci-dessous :</span>
                    </div>
                    <div class="flex flex-wrap gap-2 pt-1">
                        @foreach($boutique->filiales as $filiale)
                            <a href="{{ route('admin.boutiques.show', $filiale->id) }}" class="inline-flex items-center gap-1.5 px-3 py-1 rounded-xl bg-white border border-indigo-200 text-indigo-800 font-bold hover:bg-indigo-100 transition-colors">
                                <span class="w-1.5 h-1.5 rounded-full {{ $filiale->is_active && !$filiale->isLicenceExpired() ? 'bg-emerald-500' : 'bg-rose-500' }}"></span>
                                {{ $filiale->nom }}
                            </a>
                        @endforeach
                    </div>
                </div>
            @endif

            <!-- Licences Quick Status Tiles -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 py-6 border-b border-slate-100">
                <div class="p-5 bg-slate-50 rounded-2xl border border-slate-100">
                    <span class="text-[10px] font-black uppercase tracking-widest text-slate-400 block mb-2">Statut Actuel Licence</span>
                    @if ($boutique->hasUnlimitedLicence())
                        <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-black bg-purple-100 text-purple-800">
                            <i class="bi bi-infinity"></i> Actif à Vie (Illimité)
                        </span>
                    @elseif ($boutique->isLicenceExpired())
                        <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-black bg-rose-100 text-rose-800">
                            <i class="bi bi-exclamation-octagon-fill"></i> Licence Expirée
                        </span>
                    @else
                        @php $jours = $boutique->joursRestants(); @endphp
                        <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-black {{ $jours <= 7 ? 'bg-amber-100 text-amber-800' : 'bg-emerald-100 text-emerald-800' }}">
                            <span class="w-2 h-2 rounded-full {{ $jours <= 7 ? 'bg-amber-500 animate-pulse' : 'bg-emerald-500' }}"></span>
                            Valide &bull; {{ $jours }} jours restants
                        </span>
                    @endif
                </div>

                <div class="p-5 bg-slate-50 rounded-2xl border border-slate-100">
                    <span class="text-[10px] font-black uppercase tracking-widest text-slate-400 block mb-1">Date d'Échéance Actuelle</span>
                    <p class="text-base lg:text-lg font-black text-slate-800 mt-1">
                        @if ($boutique->hasUnlimitedLicence())
                            Aucune restriction (À Vie)
                        @elseif ($boutique->isLicenceExpired())
                            @if ($boutique->date_expiration_licence && \Carbon\Carbon::parse($boutique->date_expiration_licence)->isPast())
                                Expirée le {{ \Carbon\Carbon::parse($boutique->date_expiration_licence)->format('d/m/Y à H:i') }}
                            @else
                                Non activée (Aucune clé active)
                            @endif
                        @else
                            {{ \Carbon\Carbon::parse($boutique->date_expiration_licence)->format('d/m/Y à H:i') }}
                        @endif
                    </p>
                </div>

                <div class="p-5 bg-slate-50 rounded-2xl border border-slate-100">
                    <span class="text-[10px] font-black uppercase tracking-widest text-slate-400 block mb-2">Prolongation Directe (1 Clic)</span>
                    <form action="{{ route('admin.boutiques.prolonger-licence', $boutique->id) }}" method="POST" class="flex flex-wrap gap-2">
                        @csrf
                        <input type="hidden" name="note" value="Prolongation rapide depuis fiche boutique">
                        <button type="submit" name="duree_jours" value="30" class="px-3 py-1.5 bg-white hover:bg-emerald-50 text-emerald-700 hover:border-emerald-300 border border-slate-200 rounded-xl text-xs font-bold transition-all shadow-sm cursor-pointer">
                            +30j
                        </button>
                        <button type="submit" name="duree_jours" value="90" class="px-3 py-1.5 bg-white hover:bg-emerald-50 text-emerald-700 hover:border-emerald-300 border border-slate-200 rounded-xl text-xs font-bold transition-all shadow-sm cursor-pointer">
                            +90j
                        </button>
                        <button type="submit" name="duree_jours" value="365" class="px-3 py-1.5 bg-white hover:bg-emerald-50 text-emerald-700 hover:border-emerald-300 border border-slate-200 rounded-xl text-xs font-bold transition-all shadow-sm cursor-pointer">
                            +1 An
                        </button>
                        <button type="submit" name="duree_jours" value="99999" class="px-3 py-1.5 bg-purple-50 hover:bg-purple-100 text-purple-700 border border-purple-200 rounded-xl text-xs font-bold transition-all shadow-sm cursor-pointer">
                            À Vie
                        </button>
                    </form>
                </div>
            </div>

            <!-- Clés d'activation récentes de cette boutique -->
            <div class="mt-6">
                <div class="flex items-center justify-between mb-3">
                    <h4 class="text-xs font-black uppercase tracking-widest text-slate-400">Dernières clés d'activation générées</h4>
                </div>
                @if($boutique->licences->count() > 0)
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
                    @foreach($boutique->licences->take(6) as $lic)
                    <div class="p-4 bg-white rounded-2xl border border-slate-200 flex items-center justify-between gap-3 text-xs shadow-sm hover:border-brand-200 transition-colors">
                        <div class="min-w-0">
                            <span class="font-mono font-black text-slate-900 select-all block truncate">{{ $lic->cle_licence }}</span>
                            <span class="text-[10px] text-slate-400 font-semibold block mt-0.5">
                                {{ $lic->duree_jours >= 90000 ? 'À vie' : $lic->duree_jours . 'j' }} &bull; {{ $lic->created_at->format('d/m/Y') }}
                            </span>
                        </div>
                        <div class="flex items-center gap-1.5 flex-shrink-0">
                            <span class="px-2 py-0.5 rounded-full text-[10px] font-black {{ $lic->statut === 'inutilisee' ? 'bg-amber-50 text-amber-700 border border-amber-200' : ($lic->statut === 'active' ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-slate-100 text-slate-600') }}">
                                {{ $lic->statut === 'inutilisee' ? 'En attente' : ($lic->statut === 'active' ? 'Active' : $lic->statut) }}
                            </span>
                            <button onclick="copierCode('{{ $lic->cle_licence }}', this)" title="Copier la clé" class="w-8 h-8 rounded-xl bg-slate-100 hover:bg-brand-50 text-slate-500 hover:text-brand-600 flex items-center justify-center transition-colors cursor-pointer">
                                <i class="bi bi-clipboard text-xs"></i>
                            </button>
                            <form action="{{ route('admin.licences.destroy', $lic) }}" method="POST" onsubmit="return triggerDeleteConfirm(event, 'Supprimer la licence', 'Voulez-vous supprimer définitivement cette clé de licence ?');" class="inline">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="w-8 h-8 rounded-xl bg-rose-50 hover:bg-rose-500 text-rose-500 hover:text-white flex items-center justify-center transition-colors cursor-pointer shadow-sm" title="Supprimer la clé">
                                    <i class="bi bi-trash3-fill text-xs"></i>
                                </button>
                            </form>
                        </div>
                    </div>
                    @endforeach
                </div>
                @else
                <p class="text-xs text-slate-400 italic">Aucune clé de licence générée pour cette boutique.</p>
                @endif
            </div>
        </div>

        <!-- Modal Génération Clé Dédiée à cette Boutique -->
        <div id="modalGenereCleBoutique" class="hidden fixed inset-0 z-[100] flex items-center justify-center p-6">
            <div class="absolute inset-0 bg-slate-950/60 backdrop-blur-md" onclick="this.parentElement.classList.add('hidden')"></div>
            <div class="relative w-full max-w-md bg-white rounded-[2.5rem] p-8 shadow-2xl border border-slate-100">
                <div class="flex items-center justify-between mb-6 pb-4 border-b border-slate-100">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-xl bg-brand-50 text-brand-600 flex items-center justify-center">
                            <i class="bi bi-key-fill text-lg"></i>
                        </div>
                        <h3 class="text-lg font-black text-slate-900">Nouvelle Clé pour {{ $boutique->nom }}</h3>
                    </div>
                    <button onclick="document.getElementById('modalGenereCleBoutique').classList.add('hidden')" class="w-8 h-8 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-500 flex items-center justify-center cursor-pointer">
                        <i class="bi bi-x-lg"></i>
                    </button>
                </div>
                <form action="{{ route('admin.licences.store') }}" method="POST" class="space-y-4">
                    @csrf
                    <input type="hidden" name="boutique_id" value="{{ $boutique->id }}">
                    @if ($boutique->isFiliale())
                        <div class="p-3 bg-amber-50 rounded-xl border border-amber-200 text-amber-800 text-[11px] font-semibold flex items-center gap-2">
                            <i class="bi bi-info-circle-fill text-amber-600"></i>
                            <span>Cette clé sera rattachée à la boutique principale (<strong>{{ $boutique->parent?->nom }}</strong>) et couvrira l'ensemble du groupe.</span>
                        </div>
                    @elseif ($boutique->filiales && $boutique->filiales->count() > 0)
                        <div class="p-3 bg-indigo-50 rounded-xl border border-indigo-200 text-indigo-800 text-[11px] font-semibold flex items-center gap-2">
                            <i class="bi bi-info-circle-fill text-indigo-600"></i>
                            <span>Cette clé couvrira la boutique principale ainsi que ses <strong>{{ $boutique->filiales->count() }} filiale(s)</strong>.</span>
                        </div>
                    @endif
                    <div>
                        <div class="flex items-center justify-between mb-2">
                            <label class="block text-xs font-black uppercase tracking-widest text-slate-400">Durée de Validité</label>
                            <div class="inline-flex p-0.5 bg-slate-100 rounded-xl text-[10px] font-bold">
                                <button type="button" onclick="setDureeModeBoutique('preset')" id="btnModePreset_boutique"
                                    class="px-2.5 py-1 rounded-lg transition-all bg-white text-brand-600 shadow-sm cursor-pointer">
                                    <i class="bi bi-calendar3 mr-1"></i>En Mois
                                </button>
                                <button type="button" onclick="setDureeModeBoutique('custom')" id="btnModeCustom_boutique"
                                    class="px-2.5 py-1 rounded-lg transition-all text-slate-500 hover:text-slate-800 cursor-pointer">
                                    <i class="bi bi-pencil-square mr-1"></i>En Jours (Manuel)
                                </button>
                            </div>
                        </div>

                        <input type="hidden" name="duree_mode" id="duree_mode_boutique" value="preset">

                        <!-- Mode 1: Prédéfinie en mois -->
                        <div id="containerPreset_boutique">
                            <select name="duree_jours" id="selectDureePreset_boutique" required class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-2xl text-xs font-bold focus:bg-white focus:ring-2 focus:ring-brand-500 outline-none">
                                <option value="30">1 Mois (30 jours)</option>
                                <option value="90">3 Mois (90 jours)</option>
                                <option value="180">6 Mois (180 jours)</option>
                                <option value="365">1 An (365 jours)</option>
                                <option value="99999">À Vie (Illimité)</option>
                            </select>
                        </div>

                        <!-- Mode 2: Saisie manuelle en jours -->
                        <div id="containerCustom_boutique" class="hidden space-y-2">
                            <div class="relative">
                                <input type="number" name="duree_jours_custom" id="inputDureeCustom_boutique" min="1" max="99999" placeholder="Ex: 7, 14, 45, 60..."
                                    class="w-full pl-4 pr-16 py-3 bg-slate-50 border border-slate-200 rounded-2xl text-xs font-bold focus:bg-white focus:ring-2 focus:ring-brand-500 outline-none">
                                <span class="absolute right-4 top-1/2 -translate-y-1/2 text-xs font-black text-slate-400 uppercase tracking-wider">Jours</span>
                            </div>
                            <div class="flex items-center gap-1.5 flex-wrap pt-1">
                                <span class="text-[10px] text-slate-400 font-bold uppercase tracking-wider mr-1">Raccourcis :</span>
                                <button type="button" onclick="setQuickDaysBoutique(7)" class="px-2 py-0.5 bg-slate-100 hover:bg-brand-50 hover:text-brand-600 rounded-md text-[10px] font-bold text-slate-600 transition-colors cursor-pointer">7 j</button>
                                <button type="button" onclick="setQuickDaysBoutique(14)" class="px-2 py-0.5 bg-slate-100 hover:bg-brand-50 hover:text-brand-600 rounded-md text-[10px] font-bold text-slate-600 transition-colors cursor-pointer">14 j</button>
                                <button type="button" onclick="setQuickDaysBoutique(45)" class="px-2 py-0.5 bg-slate-100 hover:bg-brand-50 hover:text-brand-600 rounded-md text-[10px] font-bold text-slate-600 transition-colors cursor-pointer">45 j</button>
                                <button type="button" onclick="setQuickDaysBoutique(60)" class="px-2 py-0.5 bg-slate-100 hover:bg-brand-50 hover:text-brand-600 rounded-md text-[10px] font-bold text-slate-600 transition-colors cursor-pointer">60 j</button>
                            </div>
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs font-black uppercase tracking-widest text-slate-400 mb-2">Note Interne (Facultatif)</label>
                        <input type="text" name="note" placeholder="Ex: Règlement reçu" class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-2xl text-sm font-semibold focus:bg-white focus:ring-2 focus:ring-brand-500 outline-none">
                    </div>
                    <div class="pt-4 flex justify-end gap-3">
                        <button type="button" onclick="document.getElementById('modalGenereCleBoutique').classList.add('hidden')" class="px-5 py-2.5 font-bold text-slate-500 hover:text-slate-900 cursor-pointer">Annuler</button>
                        <button type="submit" class="btn-action bg-brand-600 hover:bg-brand-700 text-white">Générer la Clé</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Tables Grid: Top Articles & Flux Récent -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
            <!-- Top Articles Vendus -->
            <div class="card p-0 overflow-hidden shadow-card-soft">
                <div class="p-6 border-b border-slate-100 bg-slate-50/50 flex items-center justify-between">
                    <h3 class="font-black text-slate-900 flex items-center gap-2 text-sm uppercase tracking-wide">
                        <i class="bi bi-star-fill text-amber-400"></i> Top Articles Vendus
                    </h3>
                    <span class="text-[10px] font-black uppercase text-slate-400">Classement Volume</span>
                </div>
                <div class="table-wrapper">
                    <table>
                        <thead>
                            <tr>
                                <th>Désignation</th>
                                <th style="text-align: center;">Quantité</th>
                                <th style="text-align: right;">Total Généré</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($topProducts as $tp)
                                <tr class="hover:bg-slate-50 transition-colors">
                                    <td>
                                        <div class="font-black text-slate-800">{{ $tp->produit->nom }}</div>
                                        <div class="text-[10px] text-slate-400 font-bold uppercase tracking-widest mt-0.5">
                                            {{ $tp->produit->reference ?? 'Sans Réf.' }}
                                        </div>
                                    </td>
                                    <td style="text-align: center;">
                                        <span class="px-2.5 py-1 rounded-lg bg-brand-50 text-brand-700 text-xs font-black border border-brand-200">
                                            {{ $tp->total_qty }}
                                        </span>
                                    </td>
                                    <td style="text-align: right;">
                                        <div class="font-black text-emerald-600 text-sm">
                                            {{ number_format($tp->total_amount, 0, ',', ' ') }}
                                            <span class="text-[10px] font-extrabold text-slate-400 ml-0.5">FCFA</span>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="3" class="text-center py-16">
                                        <i class="bi bi-inbox text-3xl text-slate-300 block mb-2"></i>
                                        <span class="text-slate-400 text-xs italic font-medium">Aucune donnée de vente disponible.</span>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Flux Récent d'Activité -->
            <div class="card p-0 overflow-hidden shadow-card-soft">
                <div class="p-6 border-b border-slate-100 bg-slate-50/50 flex items-center justify-between">
                    <h3 class="font-black text-slate-900 flex items-center gap-2 text-sm uppercase tracking-wide">
                        <i class="bi bi-clock-history text-brand-500"></i> Flux Récent des Ventes
                    </h3>
                    <span class="text-[10px] font-black uppercase text-slate-400">Temps Réel</span>
                </div>
                <div class="table-wrapper">
                    <table>
                        <thead>
                            <tr>
                                <th>Intervenant</th>
                                <th>Moment</th>
                                <th style="text-align: right;">Montant</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($recentSales as $sale)
                                <tr class="hover:bg-slate-50 transition-colors">
                                    <td>
                                        <div class="flex items-center gap-3">
                                            <div class="w-8 h-8 rounded-xl bg-brand-50 text-brand-600 flex items-center justify-center text-xs font-black">
                                                {{ substr($sale->user->name ?? 'S', 0, 1) }}
                                            </div>
                                            <div>
                                                <div class="font-black text-slate-800 text-xs">
                                                    {{ $sale->user->name ?? 'Système' }}
                                                </div>
                                                <div class="text-[9px] text-slate-400 font-bold uppercase tracking-wider">
                                                    {{ $sale->user?->role ?? 'Agent' }}
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="text-xs font-bold text-slate-500 flex items-center gap-1.5">
                                            <i class="bi bi-clock text-slate-400 text-[10px]"></i>
                                            {{ $sale->created_at->diffForHumans() }}
                                        </div>
                                    </td>
                                    <td style="text-align: right;">
                                        <div class="font-black text-slate-900 text-sm">
                                            {{ number_format($sale->montant_total, 0, ',', ' ') }}
                                            <span class="text-[10px] font-bold text-slate-400 ml-0.5">FCFA</span>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="3" class="text-center py-16">
                                        <i class="bi bi-receipt text-3xl text-slate-300 block mb-2"></i>
                                        <span class="text-slate-400 text-xs italic font-medium">Historique des transactions vide.</span>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Copy JS Helper -->
    <script>
        function copierCode(texte, btn) {
            navigator.clipboard.writeText(texte).then(() => {
                const original = btn.innerHTML;
                btn.innerHTML = '<i class="bi bi-check2 text-emerald-600"></i>';
                setTimeout(() => {
                    btn.innerHTML = original;
                }, 2000);
            });
        }

        function setDureeModeBoutique(mode) {
            const inputMode = document.getElementById('duree_mode_boutique');
            const btnPreset = document.getElementById('btnModePreset_boutique');
            const btnCustom = document.getElementById('btnModeCustom_boutique');
            const containerPreset = document.getElementById('containerPreset_boutique');
            const containerCustom = document.getElementById('containerCustom_boutique');
            const inputCustom = document.getElementById('inputDureeCustom_boutique');
            const selectPreset = document.getElementById('selectDureePreset_boutique');

            if (!inputMode) return;
            inputMode.value = mode;

            if (mode === 'custom') {
                containerPreset.classList.add('hidden');
                containerCustom.classList.remove('hidden');
                btnCustom.className = 'px-2.5 py-1 rounded-lg transition-all bg-white text-brand-600 shadow-sm cursor-pointer';
                btnPreset.className = 'px-2.5 py-1 rounded-lg transition-all text-slate-500 hover:text-slate-800 cursor-pointer';
                inputCustom.required = true;
                selectPreset.required = false;
                inputCustom.focus();
            } else {
                containerCustom.classList.add('hidden');
                containerPreset.classList.remove('hidden');
                btnPreset.className = 'px-2.5 py-1 rounded-lg transition-all bg-white text-brand-600 shadow-sm cursor-pointer';
                btnCustom.className = 'px-2.5 py-1 rounded-lg transition-all text-slate-500 hover:text-slate-800 cursor-pointer';
                inputCustom.required = false;
                selectPreset.required = true;
            }
        }

        function setQuickDaysBoutique(days) {
            const inputCustom = document.getElementById('inputDureeCustom_boutique');
            if (inputCustom) {
                inputCustom.value = days;
                inputCustom.focus();
            }
        }
    </script>
@endsection
