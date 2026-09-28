@extends('layouts.admin')

@section('content')
    <div class="space-y-10 animate-fade-in">
        <!-- Header Section -->
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-6 pb-2 border-b border-slate-200/60">
            <div class="space-y-1">
                <div class="inline-flex items-center gap-2 px-3 py-1 bg-brand-50 text-brand-700 rounded-full text-[10px] font-black uppercase tracking-widest mb-1">
                    <i class="bi bi-diagram-3-fill"></i>
                    Expansion Multi-Boutiques
                </div>
                <h1 class="text-3xl lg:text-4xl font-black text-slate-900 tracking-tight">
                    Réseau d'<span class="text-brand-600">Établissements.</span>
                </h1>
                <p class="text-slate-500 font-semibold text-xs">
                    Gérez les établissements, contrôlez les quotas d'accès et supervisez les validités de licence.
                </p>
            </div>
            <div class="flex items-center gap-3">
                <button onclick="document.getElementById('createModal').classList.remove('hidden')"
                    class="btn-action bg-brand-600 hover:bg-brand-700 text-white shadow-lg shadow-brand-500/20 text-xs cursor-pointer">
                    <i class="bi bi-plus-circle-fill text-base"></i>
                    <span>Expansion Réseau</span>
                </button>
            </div>
        </div>

        <!-- 3 Stats Counters Overview -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <div class="glass-card p-6 rounded-3xl flex items-center gap-5">
                <div class="w-13 h-13 rounded-2xl bg-brand-50 text-brand-600 flex items-center justify-center text-2xl shadow-sm">
                    <i class="bi bi-shop"></i>
                </div>
                <div>
                    <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Total Boutiques</p>
                    <p class="text-3xl font-black text-slate-900 leading-none mt-1">{{ $boutiques->count() }}</p>
                </div>
            </div>

            <div class="glass-card p-6 rounded-3xl flex items-center gap-5">
                <div class="w-13 h-13 rounded-2xl bg-emerald-50 text-emerald-600 flex items-center justify-center text-2xl shadow-sm">
                    <i class="bi bi-check-circle-fill"></i>
                </div>
                <div>
                    <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Actives & Ouvertes</p>
                    <p class="text-3xl font-black text-slate-900 leading-none mt-1">
                        {{ $boutiques->where('is_active', 1)->count() }}
                    </p>
                </div>
            </div>

            <div class="glass-card p-6 rounded-3xl flex items-center gap-5">
                <div class="w-13 h-13 rounded-2xl bg-rose-50 text-rose-600 flex items-center justify-center text-2xl shadow-sm">
                    <i class="bi bi-slash-circle-fill"></i>
                </div>
                <div>
                    <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Suspendues / Bloquées</p>
                    <p class="text-3xl font-black text-slate-900 leading-none mt-1">
                        {{ $boutiques->where('is_active', 0)->count() }}
                    </p>
                </div>
            </div>
        </div>

        <!-- Main Table Card -->
        <div class="glass-card rounded-[2.5rem] overflow-hidden shadow-card-soft">
            <div class="p-8 border-b border-slate-100 bg-slate-50/50 flex flex-col md:flex-row md:items-center justify-between gap-4">
                <h3 class="font-black text-slate-900 text-sm uppercase tracking-wider flex items-center gap-3">
                    <i class="bi bi-list-stars text-brand-600 text-lg"></i>
                    Registre Central des Boutiques
                </h3>
                <div class="relative group">
                    <i class="bi bi-search absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 group-focus-within:text-brand-600 transition-colors"></i>
                    <input type="text" id="searchInput" onkeyup="filterBoutiques()" placeholder="Rechercher une boutique..."
                        class="pl-11 pr-5 py-3 bg-white border border-slate-200 rounded-2xl text-xs font-bold w-full md:w-80 focus:ring-2 focus:ring-brand-500 focus:border-brand-500 transition-all outline-none">
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse" id="boutiquesTable">
                    <thead>
                        <tr class="border-b border-slate-100 bg-white text-[10px] font-black text-slate-400 uppercase tracking-widest">
                            <th class="px-8 py-5">Identité Boutique</th>
                            <th class="px-8 py-5">Propriétaire & Quota</th>
                            <th class="px-8 py-5">Validité Licence</th>
                            <th class="px-8 py-5 text-center">Accès</th>
                            <th class="px-8 py-5 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 bg-white">
                        @forelse ($boutiques as $boutique)
                            <tr class="hover:bg-slate-50/80 transition-colors group boutique-row">
                                <td class="px-8 py-5">
                                    <div class="flex items-center gap-4">
                                        <div class="w-12 h-12 rounded-2xl bg-brand-50 border border-brand-100 text-brand-700 flex items-center justify-center font-black text-lg shadow-sm group-hover:scale-105 transition-transform flex-shrink-0">
                                            {{ substr($boutique->nom, 0, 1) }}
                                        </div>
                                        <div>
                                            <div class="flex items-center gap-2 flex-wrap">
                                                <a href="{{ route('admin.boutiques.show', $boutique) }}" class="font-black text-slate-900 tracking-tight group-hover:text-brand-600 transition-colors boutique-name">
                                                    {{ $boutique->nom }}
                                                </a>
                                                @if ($boutique->isFiliale())
                                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md bg-amber-50 text-amber-700 border border-amber-200 text-[9px] font-black uppercase tracking-wider">
                                                        <i class="bi bi-arrow-return-right"></i> Filiale
                                                    </span>
                                                @elseif ($boutique->filiales && $boutique->filiales->count() > 0)
                                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md bg-indigo-50 text-indigo-700 border border-indigo-200 text-[9px] font-black uppercase tracking-wider">
                                                        <i class="bi bi-diagram-3-fill"></i> Principale ({{ $boutique->filiales->count() }} filiale{{ $boutique->filiales->count() > 1 ? 's' : '' }})
                                                    </span>
                                                @endif
                                            </div>
                                            <div class="flex items-center gap-2 mt-0.5 flex-wrap">
                                                <span class="px-2 py-0.5 rounded-md bg-slate-100 text-slate-600 text-[9px] font-black uppercase tracking-wider">
                                                    {{ $boutique->nature?->name ?? 'Commerce' }}
                                                </span>
                                                @if($boutique->isFiliale() && $boutique->parent)
                                                    <span class="text-[10px] font-bold text-slate-500">
                                                        de <span class="text-slate-700 font-black">{{ $boutique->parent->nom }}</span>
                                                    </span>
                                                @endif
                                                <span class="text-[11px] font-semibold text-slate-400">
                                                    <i class="bi bi-geo-alt"></i> {{ $boutique->adresse }}
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </td>

                                <td class="px-8 py-5">
                                    @if ($boutique->creator)
                                        <div class="space-y-1.5">
                                            <div class="flex items-center gap-2">
                                                <div class="w-6 h-6 rounded-full bg-slate-900 text-white flex items-center justify-center text-[10px] font-black">
                                                    {{ substr($boutique->creator->name, 0, 1) }}
                                                </div>
                                                <span class="text-xs font-bold text-slate-800">{{ $boutique->creator->name }}</span>
                                            </div>
                                            @php
                                                $count = \App\Models\Boutique::where('user_id', $boutique->user_id)->count();
                                                $limit = max((int)$boutique->creator->boutique_limit, 1);
                                                $pct = min(($count / $limit) * 100, 100);
                                                $barColor = $pct >= 90 ? 'bg-rose-500' : ($pct >= 70 ? 'bg-amber-500' : 'bg-brand-500');
                                            @endphp
                                            <div class="w-36 space-y-1">
                                                <div class="flex justify-between text-[9px] font-black uppercase tracking-wider text-slate-400">
                                                    <span>Quota</span>
                                                    <span class="{{ $pct >= 90 ? 'text-rose-600' : 'text-slate-600' }}">{{ $count }} / {{ $limit }}</span>
                                                </div>
                                                <div class="h-1.5 w-full bg-slate-100 rounded-full overflow-hidden">
                                                    <div class="h-full {{ $barColor }} rounded-full" style="width: {{ $pct }}%"></div>
                                                </div>
                                            </div>
                                        </div>
                                    @else
                                        <span class="text-slate-400 italic text-xs">Aucun propriétaire</span>
                                    @endif
                                </td>

                                <td class="px-8 py-5">
                                    @if ($boutique->hasUnlimitedLicence())
                                        <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-[10px] font-black bg-purple-50 text-purple-700 border border-purple-200">
                                            <i class="bi bi-infinity"></i> Permanent
                                        </span>
                                    @elseif ($boutique->isLicenceExpired())
                                        <div class="space-y-0.5">
                                            <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-[10px] font-black bg-rose-50 text-rose-700 border border-rose-200">
                                                <span class="w-1.5 h-1.5 rounded-full bg-rose-500"></span> Expirée
                                            </span>
                                            @if($boutique->date_expiration_licence && \Carbon\Carbon::parse($boutique->date_expiration_licence)->isPast())
                                                <p class="text-[10px] text-rose-500 font-semibold">{{ \Carbon\Carbon::parse($boutique->date_expiration_licence)->format('d/m/Y') }}</p>
                                            @else
                                                <p class="text-[10px] text-rose-400 font-semibold">Aucune clé active</p>
                                            @endif
                                        </div>
                                    @else
                                        @php $jours = $boutique->joursRestants(); @endphp
                                        <div class="space-y-0.5">
                                            <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-[10px] font-black {{ $jours <= 7 ? 'bg-amber-50 text-amber-700 border border-amber-200' : 'bg-emerald-50 text-emerald-700 border border-emerald-200' }}">
                                                <span class="w-1.5 h-1.5 rounded-full {{ $jours <= 7 ? 'bg-amber-500 animate-pulse' : 'bg-emerald-500' }}"></span>
                                                {{ $jours }} j restants
                                            </span>
                                            <p class="text-[10px] text-slate-400 font-semibold">{{ \Carbon\Carbon::parse($boutique->date_expiration_licence)->format('d/m/Y') }}</p>
                                        </div>
                                    @endif
                                    @if($boutique->isFiliale())
                                        <p class="text-[9px] text-indigo-600 font-bold mt-1 flex items-center gap-1">
                                            <i class="bi bi-link-45deg"></i> Synchronisée au groupe
                                        </p>
                                    @endif
                                </td>

                                <td class="px-8 py-5">
                                    <div class="flex justify-center">
                                        <form action="{{ route('admin.boutiques.toggle-status', $boutique) }}" method="POST">
                                            @csrf
                                            <button type="submit"
                                                class="flex items-center gap-1.5 px-3 py-1.5 rounded-full border transition-all cursor-pointer {{ $boutique->is_active ? 'bg-emerald-50 border-emerald-200 text-emerald-700 hover:bg-emerald-100' : 'bg-rose-50 border-rose-200 text-rose-700 hover:bg-rose-100' }}"
                                                title="{{ $boutique->is_active ? 'Cliquer pour suspendre' : 'Cliquer pour activer' }}">
                                                <span class="w-1.5 h-1.5 rounded-full {{ $boutique->is_active ? 'bg-emerald-500 animate-pulse' : 'bg-rose-500' }}"></span>
                                                <span class="text-[10px] font-black uppercase tracking-wider">{{ $boutique->is_active ? 'Ouverte' : 'Suspendue' }}</span>
                                            </button>
                                        </form>
                                    </div>
                                </td>

                                <td class="px-8 py-5 text-right">
                                    <div class="flex items-center justify-end gap-2">
                                        <a href="{{ route('admin.licences.index', ['boutique_id' => $boutique->id]) }}"
                                            title="Licences & Clés"
                                            class="w-9 h-9 rounded-xl bg-amber-50 text-amber-600 hover:bg-amber-500 hover:text-white flex items-center justify-center transition-colors shadow-sm">
                                            <i class="bi bi-key-fill text-xs"></i>
                                        </a>
                                        <a href="{{ route('admin.boutiques.show', $boutique) }}"
                                            title="Fiche Établissement"
                                            class="w-9 h-9 rounded-xl bg-slate-100 text-slate-600 hover:bg-slate-900 hover:text-white flex items-center justify-center transition-colors shadow-sm">
                                            <i class="bi bi-eye-fill text-xs"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-center py-20 bg-white">
                                    <i class="bi bi-shop-window text-4xl text-slate-300 block mb-3"></i>
                                    <p class="text-slate-400 text-sm font-semibold">Aucun établissement enregistré pour le moment.</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Creation Modal (Expansion Réseau) -->
    <div id="createModal" class="hidden fixed inset-0 z-[100] flex items-center justify-center p-6 sm:p-10">
        <div class="absolute inset-0 bg-slate-950/60 backdrop-blur-md"
            onclick="document.getElementById('createModal').classList.add('hidden')"></div>

        <div class="relative w-full max-w-2xl bg-white rounded-[2.5rem] p-8 lg:p-10 shadow-2xl border border-slate-100 max-h-[90vh] overflow-y-auto">
            <div class="flex items-center justify-between mb-8 pb-4 border-b border-slate-100">
                <div class="space-y-0.5">
                    <h2 class="text-2xl font-black text-slate-900 tracking-tight">Nouvel <span class="text-brand-600">Établissement.</span></h2>
                    <p class="text-slate-500 font-semibold text-xs">Configurez une nouvelle boutique et assignez son compte propriétaire.</p>
                </div>
                <button onclick="document.getElementById('createModal').classList.add('hidden')"
                    class="w-10 h-10 rounded-2xl bg-slate-100 text-slate-400 hover:bg-rose-50 hover:text-rose-600 transition-all flex items-center justify-center cursor-pointer">
                    <i class="bi bi-x-lg"></i>
                </button>
            </div>

            <form action="{{ route('admin.boutiques.store') }}" method="POST" class="space-y-6">
                @csrf

                <!-- Section 1 : Boutique -->
                <div class="space-y-4">
                    <h4 class="text-xs font-black text-slate-900 uppercase tracking-widest flex items-center gap-2">
                        <i class="bi bi-building text-brand-600"></i> Informations Établissement
                    </h4>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="space-y-1.5">
                            <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest pl-1">Nom de la Boutique</label>
                            <input type="text" name="nom" required placeholder="ex: Pharmacie Centrale"
                                class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-2xl text-xs font-bold focus:bg-white focus:ring-2 focus:ring-brand-500 outline-none">
                        </div>

                        <div class="space-y-1.5">
                            <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest pl-1">Nature de l'Activité</label>
                            <select name="nature_id" required
                                class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-2xl text-xs font-bold focus:bg-white focus:ring-2 focus:ring-brand-500 outline-none">
                                <option value="">Sélectionner une activité</option>
                                @foreach (\App\Models\Nature::all() as $nature)
                                    <option value="{{ $nature->id }}">{{ $nature->name }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="space-y-1.5">
                            <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest pl-1">Téléphone</label>
                            <input type="text" name="telephone" required placeholder="+223 ..."
                                class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-2xl text-xs font-bold focus:bg-white focus:ring-2 focus:ring-brand-500 outline-none">
                        </div>

                        <div class="space-y-1.5">
                            <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest pl-1">Localisation / Adresse</label>
                            <input type="text" name="adresse" required placeholder="Adresse de l'établissement..."
                                class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-2xl text-xs font-bold focus:bg-white focus:ring-2 focus:ring-brand-500 outline-none">
                        </div>
                    </div>
                </div>

                <!-- Section 2 : Propriétaire -->
                <div class="space-y-4 pt-6 border-t border-slate-100">
                    <h4 class="text-xs font-black text-slate-900 uppercase tracking-widest flex items-center gap-2">
                        <i class="bi bi-person-badge text-brand-600"></i> Compte Propriétaire
                    </h4>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="space-y-1.5 md:col-span-2">
                            <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest pl-1">Nom du Gestionnaire</label>
                            <input type="text" name="nom_admin" required placeholder="Nom complet"
                                class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-2xl text-xs font-bold focus:bg-white focus:ring-2 focus:ring-brand-500 outline-none">
                        </div>

                        <div class="space-y-1.5">
                            <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest pl-1">E-mail de Connexion</label>
                            <input type="email" name="email_admin" required placeholder="admin@boutique.com"
                                class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-2xl text-xs font-bold focus:bg-white focus:ring-2 focus:ring-brand-500 outline-none">
                        </div>

                        <div class="space-y-1.5">
                            <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest pl-1">Mot de Passe</label>
                            <input type="password" name="password_admin" required placeholder="••••••••"
                                class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-2xl text-xs font-bold focus:bg-white focus:ring-2 focus:ring-brand-500 outline-none">
                        </div>
                    </div>
                </div>

                <div class="pt-6 border-t border-slate-100 flex items-center justify-end gap-3">
                    <button type="button" onclick="document.getElementById('createModal').classList.add('hidden')"
                        class="px-6 py-3 font-bold text-xs text-slate-500 hover:text-slate-900 cursor-pointer">
                        Annuler
                    </button>
                    <button type="submit"
                        class="btn-action bg-brand-600 hover:bg-brand-700 text-white shadow-lg shadow-brand-500/20 text-xs">
                        <i class="bi bi-plus-circle-fill text-sm"></i>
                        <span>Créer Boutique & Propriétaire</span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Client-side filtering script -->
    <script>
        function filterBoutiques() {
            const input = document.getElementById('searchInput').value.toLowerCase();
            const rows = document.querySelectorAll('.boutique-row');
            rows.forEach(row => {
                const name = row.querySelector('.boutique-name')?.textContent.toLowerCase() || '';
                if (name.includes(input)) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        }
    </script>
@endsection
