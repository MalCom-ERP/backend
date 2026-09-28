@extends('layouts.admin')

@section('content')
<div class="space-y-10 animate-fade-in">
    <!-- Header Section -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-6 pb-2 border-b border-slate-200/60">
        <div class="space-y-1">
            <div class="inline-flex items-center gap-2 px-3 py-1 bg-amber-50 text-amber-700 rounded-full text-[10px] font-black uppercase tracking-widest mb-1">
                <i class="bi bi-key-fill"></i>
                Sécurité & Abonnements MalCom
            </div>
            <h1 class="text-3xl lg:text-4xl font-black text-slate-900 tracking-tight">
                Gestion des <span class="text-brand-600">Licences.</span>
            </h1>
            <p class="text-slate-500 font-semibold text-xs">
                Générez des clés d'activation, gérez les abonnements et débloquez les caisses du réseau.
            </p>
        </div>
        <div class="flex flex-wrap gap-3">
            <button onclick="document.getElementById('generateModal').classList.remove('hidden')"
                class="btn-action bg-brand-600 hover:bg-brand-700 text-white shadow-lg shadow-brand-500/20 text-xs cursor-pointer">
                <i class="bi bi-key-fill text-sm"></i>
                <span>Générer une Clé</span>
            </button>
            <button onclick="document.getElementById('prolongModal').classList.remove('hidden')"
                class="btn-action bg-emerald-600 hover:bg-emerald-700 text-white shadow-lg shadow-emerald-500/20 text-xs cursor-pointer">
                <i class="bi bi-clock-history text-sm"></i>
                <span>Prolongation Directe</span>
            </button>
        </div>
    </div>

    <!-- Success Key Generation Banner -->
    @if(session('success_cle'))
    <div class="p-6 lg:p-8 bg-gradient-to-r from-brand-700 via-indigo-700 to-slate-950 rounded-3xl text-white shadow-2xl relative overflow-hidden">
        <div class="relative z-10 flex flex-col md:flex-row items-start md:items-center justify-between gap-6">
            <div class="space-y-3">
                <div class="inline-flex items-center gap-2 px-3 py-1 bg-white/20 rounded-full text-xs font-black uppercase tracking-widest text-brand-100">
                    <i class="bi bi-check2-circle"></i> Clé Prête à Transmettre
                </div>
                <h3 class="text-2xl lg:text-3xl font-black tracking-tight">{{ session('success_cle')['message'] }}</h3>
                <p class="text-xs text-brand-100 font-medium">
                    Boutique : <strong class="text-white">{{ session('success_cle')['boutique'] }}</strong> &bull; 
                    Validité : <strong class="text-white">{{ session('success_cle')['duree'] }}</strong>
                </p>
                <div class="flex flex-wrap items-center gap-3 pt-2">
                    <div class="px-5 py-3 bg-white text-slate-900 rounded-2xl font-mono text-xl lg:text-2xl font-black tracking-wider shadow-inner select-all" id="cleGenereText">
                        {{ session('success_cle')['cle'] }}
                    </div>
                    <button onclick="copierCleModal('{{ session('success_cle')['cle'] }}')" id="btnCopier"
                        class="btn-action bg-white/20 hover:bg-white/30 text-white border border-white/20 text-xs cursor-pointer">
                        <i class="bi bi-clipboard"></i>
                        <span id="labelCopier">Copier la clé</span>
                    </button>
                </div>
            </div>
            <div class="text-xs text-brand-100 max-w-sm bg-white/10 p-5 rounded-2xl backdrop-blur-sm border border-white/10 leading-relaxed">
                <div class="flex items-center gap-2 font-bold text-white mb-1">
                    <i class="bi bi-info-circle-fill text-amber-300"></i> Mode d'emploi
                </div>
                Transmettez cette clé d'activation au gérant de la boutique. Dès qu'il la saisira sur l'écran d'activation de la caisse MalCom, son compte sera débloqué immédiatement.
            </div>
        </div>
        <!-- Ambient decorative shapes -->
        <div class="absolute right-0 top-0 w-80 h-80 bg-brand-400/20 blur-3xl rounded-full pointer-events-none"></div>
    </div>
    @endif

    <!-- 4 Statistics Counters -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
        <div class="glass-card p-6 rounded-3xl flex items-center gap-4">
            <div class="w-12 h-12 rounded-2xl bg-brand-50 text-brand-600 flex items-center justify-center text-xl shadow-sm">
                <i class="bi bi-key-fill"></i>
            </div>
            <div>
                <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Total Clés</p>
                <p class="text-2xl font-black text-slate-900 leading-none mt-1">{{ $stats['total'] }}</p>
            </div>
        </div>

        <div class="glass-card p-6 rounded-3xl flex items-center gap-4">
            <div class="w-12 h-12 rounded-2xl bg-amber-50 text-amber-600 flex items-center justify-center text-xl shadow-sm">
                <i class="bi bi-hourglass-split"></i>
            </div>
            <div>
                <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest">En Attente</p>
                <p class="text-2xl font-black text-slate-900 leading-none mt-1">{{ $stats['inutilisees'] }}</p>
            </div>
        </div>

        <div class="glass-card p-6 rounded-3xl flex items-center gap-4">
            <div class="w-12 h-12 rounded-2xl bg-emerald-50 text-emerald-600 flex items-center justify-center text-xl shadow-sm">
                <i class="bi bi-check-circle-fill"></i>
            </div>
            <div>
                <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Actives</p>
                <p class="text-2xl font-black text-slate-900 leading-none mt-1">{{ $stats['actives'] }}</p>
            </div>
        </div>

        <div class="glass-card p-6 rounded-3xl flex items-center gap-4">
            <div class="w-12 h-12 rounded-2xl bg-rose-50 text-rose-600 flex items-center justify-center text-xl shadow-sm">
                <i class="bi bi-x-circle-fill"></i>
            </div>
            <div>
                <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Expirées / Révoquées</p>
                <p class="text-2xl font-black text-slate-900 leading-none mt-1">{{ $stats['expirees'] + ($stats['revoquees'] ?? 0) }}</p>
            </div>
        </div>
    </div>

    <!-- Filters and Table Registry -->
    <div class="glass-card rounded-[2.5rem] overflow-hidden shadow-card-soft">
        <div class="p-8 border-b border-slate-100 bg-slate-50/50 flex flex-col md:flex-row md:items-center justify-between gap-4">
            <h3 class="font-black text-slate-900 text-sm uppercase tracking-wider flex items-center gap-3">
                <i class="bi bi-shield-check text-brand-600 text-lg"></i>
                Registre des Clés & Abonnements
            </h3>
            <form method="GET" action="{{ route('admin.licences.index') }}" class="flex flex-wrap items-center gap-3">
                <select name="boutique_id" onchange="this.form.submit()" class="bg-white border border-slate-200 rounded-xl text-xs font-bold py-2.5 px-3.5 focus:ring-2 focus:ring-brand-500 outline-none shadow-sm">
                    <option value="">Toutes les Boutiques</option>
                    @foreach($boutiques as $b)
                        @php
                            $filialesCount = $b->filiales ? $b->filiales->count() : 0;
                            $label = $b->nom;
                            if ($b->isFiliale()) {
                                $label .= ' [Filiale de ' . ($b->parent?->nom ?? 'Principale') . ']';
                            } elseif ($filialesCount > 0) {
                                $label .= " [Groupe : Principale + {$filialesCount} filiale" . ($filialesCount > 1 ? 's' : '') . "]";
                            }
                        @endphp
                        <option value="{{ $b->id }}" {{ request('boutique_id') == $b->id ? 'selected' : '' }}>{{ $label }}</option>
                    @endforeach
                </select>

                <select name="statut" onchange="this.form.submit()" class="bg-white border border-slate-200 rounded-xl text-xs font-bold py-2.5 px-3.5 focus:ring-2 focus:ring-brand-500 outline-none shadow-sm">
                    <option value="">Tous les statuts</option>
                    <option value="inutilisee" {{ request('statut') == 'inutilisee' ? 'selected' : '' }}>Inutilisées</option>
                    <option value="active" {{ request('statut') == 'active' ? 'selected' : '' }}>Actives</option>
                    <option value="expiree" {{ request('statut') == 'expiree' ? 'selected' : '' }}>Expirées</option>
                    <option value="revoquee" {{ request('statut') == 'revoquee' ? 'selected' : '' }}>Révoquées</option>
                </select>

                @if(request()->hasAny(['boutique_id', 'statut']))
                    <a href="{{ route('admin.licences.index') }}" class="text-xs text-rose-600 font-bold hover:underline px-2">Réinitialiser</a>
                @endif
            </form>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="border-b border-slate-100 bg-white text-[10px] font-black text-slate-400 uppercase tracking-widest">
                        <th class="px-8 py-5">Clé de Licence</th>
                        <th class="px-8 py-5">Boutique</th>
                        <th class="px-8 py-5">Durée</th>
                        <th class="px-8 py-5 text-center">Statut</th>
                        <th class="px-8 py-5">Dates d'Échéance</th>
                        <th class="px-8 py-5">Générateur</th>
                        <th class="px-8 py-5 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 bg-white">
                    @forelse($licences as $licence)
                        <tr class="hover:bg-slate-50/80 transition-colors">
                            <td class="px-8 py-5">
                                <div class="flex items-center gap-2">
                                    <span class="font-mono font-black text-xs text-slate-900 bg-slate-100 px-3 py-1.5 rounded-xl select-all border border-slate-200">
                                        {{ $licence->cle_licence }}
                                    </span>
                                    <button onclick="copierCodeItem('{{ $licence->cle_licence }}', this)" title="Copier la clé" class="w-7 h-7 rounded-lg bg-slate-100 hover:bg-brand-50 text-slate-500 hover:text-brand-600 flex items-center justify-center transition-colors cursor-pointer">
                                        <i class="bi bi-clipboard text-[10px]"></i>
                                    </button>
                                </div>
                            </td>

                            <td class="px-8 py-5">
                                <a href="{{ route('admin.boutiques.show', $licence->boutique) }}" class="font-black text-xs text-slate-900 hover:text-brand-600 transition-colors">
                                    {{ $licence->boutique?->nom ?? 'Inconnue' }}
                                </a>
                                @if($licence->boutique && $licence->boutique->filiales && $licence->boutique->filiales->count() > 0)
                                    <div class="text-[10px] text-brand-600 font-bold mt-0.5 flex items-center gap-1">
                                        <i class="bi bi-diagram-3-fill"></i> Groupe (+{{ $licence->boutique->filiales->count() }} filiale{{ $licence->boutique->filiales->count() > 1 ? 's' : '' }})
                                    </div>
                                @elseif($licence->boutique && $licence->boutique->isFiliale())
                                    <div class="text-[10px] text-amber-600 font-bold mt-0.5 flex items-center gap-1">
                                        <i class="bi bi-arrow-return-right"></i> Filiale de {{ $licence->boutique->parent?->nom ?? 'Principale' }}
                                    </div>
                                @endif
                            </td>

                            <td class="px-8 py-5">
                                <span class="px-2.5 py-1 rounded-xl text-[10px] font-black uppercase tracking-wider {{ $licence->duree_jours >= 90000 ? 'bg-purple-50 text-purple-700 border border-purple-200' : 'bg-slate-100 text-slate-700' }}">
                                    {{ $licence->duree_jours >= 90000 ? 'À Vie' : $licence->duree_jours . ' Jours' }}
                                </span>
                            </td>

                            <td class="px-8 py-5 text-center">
                                @if($licence->statut === 'inutilisee')
                                    <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-[10px] font-black uppercase bg-amber-50 text-amber-700 border border-amber-200">
                                        <span class="w-1.5 h-1.5 rounded-full bg-amber-500"></span> En Attente
                                    </span>
                                @elseif($licence->statut === 'active')
                                    <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-[10px] font-black uppercase bg-emerald-50 text-emerald-700 border border-emerald-200">
                                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span> Active
                                    </span>
                                @elseif($licence->statut === 'expiree')
                                    <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-[10px] font-black uppercase bg-rose-50 text-rose-700 border border-rose-200">
                                        <span class="w-1.5 h-1.5 rounded-full bg-rose-500"></span> Expirée
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-[10px] font-black uppercase bg-slate-100 text-slate-600 border border-slate-200">
                                        {{ $licence->statut }}
                                    </span>
                                @endif
                            </td>

                            <td class="px-8 py-5">
                                <div class="text-[11px] font-bold text-slate-500">
                                    <span>Générée : {{ $licence->created_at->format('d/m/Y') }}</span>
                                    @if($licence->date_fin)
                                        <div class="text-[10px] text-slate-400 font-semibold mt-0.5">
                                            Fin : {{ \Carbon\Carbon::parse($licence->date_fin)->format('d/m/Y') }}
                                        </div>
                                    @endif
                                </div>
                            </td>

                            <td class="px-8 py-5">
                                <span class="text-xs font-bold text-slate-700">
                                    {{ $licence->createur?->name ?? 'Système' }}
                                </span>
                            </td>

                            <td class="px-8 py-5 text-right">
                                <div class="flex items-center justify-end gap-2">
                                    @if($licence->statut === 'active' || $licence->statut === 'inutilisee')
                                        <form action="{{ route('admin.licences.revoquer', $licence) }}" method="POST" onsubmit="return triggerDeleteConfirm(event, 'Révoquer la licence', 'Voulez-vous vraiment révoquer cette clé de licence ?');">
                                            @csrf
                                            <button type="submit" class="w-8 h-8 rounded-xl bg-amber-50 text-amber-600 hover:bg-amber-500 hover:text-white flex items-center justify-center transition-colors cursor-pointer shadow-sm" title="Révoquer la clé">
                                                <i class="bi bi-slash-circle text-xs"></i>
                                            </button>
                                        </form>
                                    @endif

                                    <form action="{{ route('admin.licences.destroy', $licence) }}" method="POST" onsubmit="return triggerDeleteConfirm(event, 'Supprimer la licence', 'Voulez-vous supprimer définitivement cette clé de licence ?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="w-8 h-8 rounded-xl bg-rose-50 text-rose-600 hover:bg-rose-500 hover:text-white flex items-center justify-center transition-colors cursor-pointer shadow-sm" title="Supprimer définitivement la clé">
                                            <i class="bi bi-trash3-fill text-xs"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center py-20 bg-white">
                                <i class="bi bi-key text-4xl text-slate-300 block mb-3"></i>
                                <p class="text-slate-400 text-sm font-semibold">Aucune clé de licence trouvée avec ces critères.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($licences->hasPages())
            <div class="p-6 border-t border-slate-100 bg-slate-50/50">
                {{ $licences->links() }}
            </div>
        @endif
    </div>
</div>

<!-- Modal 1 : Générer une Clé -->
<div id="generateModal" class="hidden fixed inset-0 z-[100] flex items-center justify-center p-6 sm:p-10">
    <div class="absolute inset-0 bg-slate-950/60 backdrop-blur-md" onclick="document.getElementById('generateModal').classList.add('hidden')"></div>
    <div class="relative w-full max-w-lg bg-white rounded-[2.5rem] p-8 shadow-2xl border border-slate-100">
        <div class="flex items-center justify-between mb-6 pb-4 border-b border-slate-100">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-2xl bg-brand-50 text-brand-600 flex items-center justify-center">
                    <i class="bi bi-key-fill text-lg"></i>
                </div>
                <h3 class="text-xl font-black text-slate-900 tracking-tight">Générer une Clé de Licence</h3>
            </div>
            <button onclick="document.getElementById('generateModal').classList.add('hidden')" class="w-8 h-8 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-500 flex items-center justify-center cursor-pointer">
                <i class="bi bi-x-lg"></i>
            </button>
        </div>

        <form action="{{ route('admin.licences.store') }}" method="POST" class="space-y-4">
            @csrf
            <div>
                <label class="block text-xs font-black uppercase tracking-widest text-slate-400 mb-2">Boutique Bénéficiaire (ou Groupe)</label>
                <select name="boutique_id" required class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-2xl text-xs font-bold focus:bg-white focus:ring-2 focus:ring-brand-500 outline-none">
                    <option value="">Sélectionner un établissement</option>
                    @foreach($boutiques as $b)
                        @php
                            $filialesCount = $b->filiales ? $b->filiales->count() : 0;
                            $label = $b->nom;
                            if ($b->isFiliale()) {
                                $label .= ' (Filiale -> Rattachée à ' . ($b->parent?->nom ?? 'Principale') . ')';
                            } elseif ($filialesCount > 0) {
                                $label .= " [Groupe : Principale + {$filialesCount} filiale" . ($filialesCount > 1 ? 's' : '') . "]";
                            }
                        @endphp
                        <option value="{{ $b->id }}">{{ $label }}</option>
                    @endforeach
                </select>
                <p class="text-[11px] text-slate-400 font-medium mt-1 flex items-center gap-1.5">
                    <i class="bi bi-info-circle text-brand-600"></i>
                    La clé sera rattachée à la boutique principale et activera automatiquement toutes ses filiales.
                </p>
            </div>

            <div>
                <div class="flex items-center justify-between mb-2">
                    <label class="block text-xs font-black uppercase tracking-widest text-slate-400">Durée de l'Abonnement</label>
                    <div class="inline-flex p-0.5 bg-slate-100 rounded-xl text-[10px] font-bold">
                        <button type="button" onclick="setDureeMode('generate', 'preset')" id="btnModePreset_generate"
                            class="px-2.5 py-1 rounded-lg transition-all bg-white text-brand-600 shadow-sm cursor-pointer">
                            <i class="bi bi-calendar3 mr-1"></i>En Mois
                        </button>
                        <button type="button" onclick="setDureeMode('generate', 'custom')" id="btnModeCustom_generate"
                            class="px-2.5 py-1 rounded-lg transition-all text-slate-500 hover:text-slate-800 cursor-pointer">
                            <i class="bi bi-pencil-square mr-1"></i>En Jours (Manuel)
                        </button>
                    </div>
                </div>

                <input type="hidden" name="duree_mode" id="duree_mode_generate" value="preset">

                <!-- Mode 1: Prédéfinie en mois -->
                <div id="containerPreset_generate">
                    <select name="duree_jours" id="selectDureePreset_generate" required class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-2xl text-xs font-bold focus:bg-white focus:ring-2 focus:ring-brand-500 outline-none">
                        <option value="30">1 Mois (30 jours)</option>
                        <option value="90">3 Mois (90 jours)</option>
                        <option value="180">6 Mois (180 jours)</option>
                        <option value="365">1 An (365 jours)</option>
                        <option value="99999">À Vie (Illimité)</option>
                    </select>
                </div>

                <!-- Mode 2: Saisie manuelle en jours -->
                <div id="containerCustom_generate" class="hidden space-y-2">
                    <div class="relative">
                        <input type="number" name="duree_jours_custom" id="inputDureeCustom_generate" min="1" max="99999" placeholder="Ex: 7, 14, 45, 60..."
                            class="w-full pl-4 pr-16 py-3 bg-slate-50 border border-slate-200 rounded-2xl text-xs font-bold focus:bg-white focus:ring-2 focus:ring-brand-500 outline-none">
                        <span class="absolute right-4 top-1/2 -translate-y-1/2 text-xs font-black text-slate-400 uppercase tracking-wider">Jours</span>
                    </div>
                    <div class="flex items-center gap-1.5 flex-wrap pt-1">
                        <span class="text-[10px] text-slate-400 font-bold uppercase tracking-wider mr-1">Raccourcis :</span>
                        <button type="button" onclick="setQuickDays('generate', 7)" class="px-2 py-0.5 bg-slate-100 hover:bg-brand-50 hover:text-brand-600 rounded-md text-[10px] font-bold text-slate-600 transition-colors cursor-pointer">7 j</button>
                        <button type="button" onclick="setQuickDays('generate', 14)" class="px-2 py-0.5 bg-slate-100 hover:bg-brand-50 hover:text-brand-600 rounded-md text-[10px] font-bold text-slate-600 transition-colors cursor-pointer">14 j</button>
                        <button type="button" onclick="setQuickDays('generate', 45)" class="px-2 py-0.5 bg-slate-100 hover:bg-brand-50 hover:text-brand-600 rounded-md text-[10px] font-bold text-slate-600 transition-colors cursor-pointer">45 j</button>
                        <button type="button" onclick="setQuickDays('generate', 60)" class="px-2 py-0.5 bg-slate-100 hover:bg-brand-50 hover:text-brand-600 rounded-md text-[10px] font-bold text-slate-600 transition-colors cursor-pointer">60 j</button>
                    </div>
                </div>
            </div>

            <div>
                <label class="block text-xs font-black uppercase tracking-widest text-slate-400 mb-2">Note Interne (Facultatif)</label>
                <input type="text" name="note" placeholder="Ex: Paiement annuel reçu par virement" class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-2xl text-xs font-semibold focus:bg-white focus:ring-2 focus:ring-brand-500 outline-none">
            </div>

            <div class="pt-4 flex justify-end gap-3 border-t border-slate-100">
                <button type="button" onclick="document.getElementById('generateModal').classList.add('hidden')" class="px-5 py-2.5 font-bold text-xs text-slate-500 hover:text-slate-900 cursor-pointer">Annuler</button>
                <button type="submit" class="btn-action bg-brand-600 hover:bg-brand-700 text-white text-xs">Générer Clé d'Activation</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal 2 : Prolongation Directe -->
<div id="prolongModal" class="hidden fixed inset-0 z-[100] flex items-center justify-center p-6 sm:p-10">
    <div class="absolute inset-0 bg-slate-950/60 backdrop-blur-md" onclick="document.getElementById('prolongModal').classList.add('hidden')"></div>
    <div class="relative w-full max-w-lg bg-white rounded-[2.5rem] p-8 shadow-2xl border border-slate-100">
        <div class="flex items-center justify-between mb-6 pb-4 border-b border-slate-100">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-2xl bg-emerald-50 text-emerald-600 flex items-center justify-center">
                    <i class="bi bi-clock-history text-lg"></i>
                </div>
                <h3 class="text-xl font-black text-slate-900 tracking-tight">Prolongation Directe Immédiate</h3>
            </div>
            <button onclick="document.getElementById('prolongModal').classList.add('hidden')" class="w-8 h-8 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-500 flex items-center justify-center cursor-pointer">
                <i class="bi bi-x-lg"></i>
            </button>
        </div>

        <p class="text-xs text-slate-500 font-semibold mb-4">
            Cette action prolonge directement la date d'échéance de la boutique sélectionnée (et de toutes ses filiales) sans nécessiter la saisie manuelle d'une clé.
        </p>

        <form id="formProlongerDirect" method="POST" action="" class="space-y-4">
            @csrf
            <div>
                <label class="block text-xs font-black uppercase tracking-widest text-slate-400 mb-2">Boutique Cible (ou Groupe)</label>
                <select id="selectBoutiqueProlonger" onchange="updateProlongAction(this.value)" required class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-2xl text-xs font-bold focus:bg-white focus:ring-2 focus:ring-brand-500 outline-none">
                    <option value="">Sélectionner une boutique</option>
                    @foreach($boutiques as $b)
                        @php
                            $filialesCount = $b->filiales ? $b->filiales->count() : 0;
                            $label = $b->nom;
                            if ($b->isFiliale()) {
                                $label .= ' (Filiale -> Rattachée à ' . ($b->parent?->nom ?? 'Principale') . ')';
                            } elseif ($filialesCount > 0) {
                                $label .= " [Groupe : Principale + {$filialesCount} filiale" . ($filialesCount > 1 ? 's' : '') . "]";
                            }
                        @endphp
                        <option value="{{ $b->id }}">{{ $label }}</option>
                    @endforeach
                </select>
                <p class="text-[11px] text-slate-400 font-medium mt-1 flex items-center gap-1.5">
                    <i class="bi bi-check-circle text-emerald-600"></i>
                    La prolongation s'appliquera automatiquement à la boutique et à toutes ses filiales.
                </p>
            </div>

            <div>
                <div class="flex items-center justify-between mb-2">
                    <label class="block text-xs font-black uppercase tracking-widest text-slate-400">Durée à Ajouter</label>
                    <div class="inline-flex p-0.5 bg-slate-100 rounded-xl text-[10px] font-bold">
                        <button type="button" onclick="setDureeMode('prolong', 'preset')" id="btnModePreset_prolong"
                            class="px-2.5 py-1 rounded-lg transition-all bg-white text-emerald-600 shadow-sm cursor-pointer">
                            <i class="bi bi-calendar3 mr-1"></i>En Mois
                        </button>
                        <button type="button" onclick="setDureeMode('prolong', 'custom')" id="btnModeCustom_prolong"
                            class="px-2.5 py-1 rounded-lg transition-all text-slate-500 hover:text-slate-800 cursor-pointer">
                            <i class="bi bi-pencil-square mr-1"></i>En Jours (Manuel)
                        </button>
                    </div>
                </div>

                <input type="hidden" name="duree_mode" id="duree_mode_prolong" value="preset">

                <!-- Mode 1: Prédéfinie en mois -->
                <div id="containerPreset_prolong">
                    <select name="duree_jours" id="selectDureePreset_prolong" required class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-2xl text-xs font-bold focus:bg-white focus:ring-2 focus:ring-brand-500 outline-none">
                        <option value="30">+ 30 jours (1 Mois)</option>
                        <option value="90">+ 90 jours (3 Mois)</option>
                        <option value="180">+ 180 jours (6 Mois)</option>
                        <option value="365">+ 365 jours (1 An)</option>
                        <option value="99999">Accès Illimité (À Vie)</option>
                    </select>
                </div>

                <!-- Mode 2: Saisie manuelle en jours -->
                <div id="containerCustom_prolong" class="hidden space-y-2">
                    <div class="relative">
                        <input type="number" name="duree_jours_custom" id="inputDureeCustom_prolong" min="1" max="99999" placeholder="Ex: 7, 14, 45, 60..."
                            class="w-full pl-4 pr-16 py-3 bg-slate-50 border border-slate-200 rounded-2xl text-xs font-bold focus:bg-white focus:ring-2 focus:ring-brand-500 outline-none">
                        <span class="absolute right-4 top-1/2 -translate-y-1/2 text-xs font-black text-slate-400 uppercase tracking-wider">Jours</span>
                    </div>
                    <div class="flex items-center gap-1.5 flex-wrap pt-1">
                        <span class="text-[10px] text-slate-400 font-bold uppercase tracking-wider mr-1">Raccourcis :</span>
                        <button type="button" onclick="setQuickDays('prolong', 7)" class="px-2 py-0.5 bg-slate-100 hover:bg-emerald-50 hover:text-emerald-600 rounded-md text-[10px] font-bold text-slate-600 transition-colors cursor-pointer">+7 j</button>
                        <button type="button" onclick="setQuickDays('prolong', 14)" class="px-2 py-0.5 bg-slate-100 hover:bg-emerald-50 hover:text-emerald-600 rounded-md text-[10px] font-bold text-slate-600 transition-colors cursor-pointer">+14 j</button>
                        <button type="button" onclick="setQuickDays('prolong', 45)" class="px-2 py-0.5 bg-slate-100 hover:bg-emerald-50 hover:text-emerald-600 rounded-md text-[10px] font-bold text-slate-600 transition-colors cursor-pointer">+45 j</button>
                        <button type="button" onclick="setQuickDays('prolong', 60)" class="px-2 py-0.5 bg-slate-100 hover:bg-emerald-50 hover:text-emerald-600 rounded-md text-[10px] font-bold text-slate-600 transition-colors cursor-pointer">+60 j</button>
                    </div>
                </div>
            </div>

            <div>
                <label class="block text-xs font-black uppercase tracking-widest text-slate-400 mb-2">Motif / Note</label>
                <input type="text" name="note" placeholder="Ex: Déblocage exceptionnel accordé" class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-2xl text-xs font-semibold focus:bg-white focus:ring-2 focus:ring-brand-500 outline-none">
            </div>

            <div class="pt-4 flex justify-end gap-3 border-t border-slate-100">
                <button type="button" onclick="document.getElementById('prolongModal').classList.add('hidden')" class="px-5 py-2.5 font-bold text-xs text-slate-500 hover:text-slate-900 cursor-pointer">Annuler</button>
                <button type="submit" class="btn-action bg-emerald-600 hover:bg-emerald-700 text-white text-xs">Appliquer Immédiatement</button>
            </div>
        </form>
    </div>
</div>

<!-- JS Helpers -->
<script>
    function setDureeMode(context, mode) {
        const inputMode = document.getElementById(`duree_mode_${context}`);
        const btnPreset = document.getElementById(`btnModePreset_${context}`);
        const btnCustom = document.getElementById(`btnModeCustom_${context}`);
        const containerPreset = document.getElementById(`containerPreset_${context}`);
        const containerCustom = document.getElementById(`containerCustom_${context}`);
        const inputCustom = document.getElementById(`inputDureeCustom_${context}`);
        const selectPreset = document.getElementById(`selectDureePreset_${context}`);

        if (!inputMode) return;
        inputMode.value = mode;

        const activeTextClass = context === 'prolong' ? 'text-emerald-600' : 'text-brand-600';

        if (mode === 'custom') {
            containerPreset.classList.add('hidden');
            containerCustom.classList.remove('hidden');
            btnCustom.className = `px-2.5 py-1 rounded-lg transition-all bg-white ${activeTextClass} shadow-sm cursor-pointer`;
            btnPreset.className = 'px-2.5 py-1 rounded-lg transition-all text-slate-500 hover:text-slate-800 cursor-pointer';
            inputCustom.required = true;
            selectPreset.required = false;
            inputCustom.focus();
        } else {
            containerCustom.classList.add('hidden');
            containerPreset.classList.remove('hidden');
            btnPreset.className = `px-2.5 py-1 rounded-lg transition-all bg-white ${activeTextClass} shadow-sm cursor-pointer`;
            btnCustom.className = 'px-2.5 py-1 rounded-lg transition-all text-slate-500 hover:text-slate-800 cursor-pointer';
            inputCustom.required = false;
            selectPreset.required = true;
        }
    }

    function setQuickDays(context, days) {
        const inputCustom = document.getElementById(`inputDureeCustom_${context}`);
        if (inputCustom) {
            inputCustom.value = days;
            inputCustom.focus();
        }
    }

    function updateProlongAction(boutiqueId) {
        const form = document.getElementById('formProlongerDirect');
        if (boutiqueId) {
            form.action = `/admin/boutiques/${boutiqueId}/prolonger-licence`;
        }
    }

    function copierCleModal(cle) {
        navigator.clipboard.writeText(cle).then(() => {
            const label = document.getElementById('labelCopier');
            label.textContent = 'Clé copiée !';
            setTimeout(() => {
                label.textContent = 'Copier la clé';
            }, 2000);
        });
    }

    function copierCodeItem(texte, btn) {
        navigator.clipboard.writeText(texte).then(() => {
            const original = btn.innerHTML;
            btn.innerHTML = '<i class="bi bi-check2 text-emerald-600"></i>';
            setTimeout(() => {
                btn.innerHTML = original;
            }, 2000);
        });
    }
</script>
@endsection
