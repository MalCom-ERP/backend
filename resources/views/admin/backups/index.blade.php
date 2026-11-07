@extends('layouts.admin')

@section('content')
    <div class="space-y-10 animate-fade-in">
        <!-- Header Section -->
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-6 pb-2 border-b border-slate-200/60">
            <div class="space-y-1">
                <div class="inline-flex items-center gap-2 px-3 py-1 bg-brand-50 text-brand-700 rounded-full text-[10px] font-black uppercase tracking-widest mb-1">
                    <i class="bi bi-database-fill-lock"></i>
                    Sécurité & Continuité des Données
                </div>
                <h1 class="text-3xl lg:text-4xl font-black text-slate-900 tracking-tight">
                    Sauvegardes & <span class="text-brand-600">Dumps SQL.</span>
                </h1>
                <p class="text-slate-500 font-semibold text-xs">
                    Plan de sauvegarde automatique quotidien par Compte Principal et extraction SQL à la demande des boutiques associées.
                </p>
            </div>

            <div class="flex flex-wrap items-center gap-3">
                <form action="{{ route('admin.backups.run-now') }}" method="POST">
                    @csrf
                    <button type="submit"
                        class="btn-action bg-emerald-600 hover:bg-emerald-700 text-white shadow-lg shadow-emerald-500/20 text-xs cursor-pointer">
                        <i class="bi bi-arrow-repeat text-base"></i>
                        <span>Générer Sauvegarde Automatique Maintenant</span>
                    </button>
                </form>

                <a href="{{ route('admin.backups.dump-global') }}"
                    class="btn-action bg-slate-900 hover:bg-black text-white shadow-lg shadow-slate-900/20 text-xs">
                    <i class="bi bi-cloud-arrow-down-fill text-base"></i>
                    <span>Dumper Base Globale Complète</span>
                </a>
            </div>
        </div>

        <!-- Plan de Sauvegarde Automatique Card -->
        <div class="glass-card-dark rounded-[2.5rem] p-8 text-white relative overflow-hidden">
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 relative z-10">
                <div class="lg:col-span-2 space-y-3">
                    <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-emerald-500/20 border border-emerald-400/30 text-emerald-300 text-[10px] font-black uppercase tracking-widest">
                        <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span>
                        Plan Automatisé Actif (Cron + Laravel Scheduler)
                    </div>
                    <h2 class="text-2xl font-black tracking-tight text-white">
                        Architecture de Sauvegarde Isolée par Compte Principal
                    </h2>
                    <p class="text-xs text-slate-300 leading-relaxed max-w-3xl">
                        Chaque nuit à <strong>02h00 UTC</strong>, le serveur exécute automatiquement la commande <code class="px-2 py-0.5 rounded bg-white/10 font-mono text-brand-300">php artisan backup:accounts</code>.
                        Pour chaque <strong>Compte Principal (Administrateur Propriétaire)</strong>, un fichier SQL autonome regroupant sa boutique principale, toutes ses filiales, ses stocks, produits, ventes, factures, clients et écritures comptables SYSCOHADA est archivé avec une rotation de <strong>14 jours</strong>.
                    </p>
                </div>

                <div class="bg-white/5 border border-white/10 rounded-3xl p-5 flex flex-col justify-between space-y-3">
                    <div class="flex items-center justify-between text-xs">
                        <span class="text-slate-400 font-bold uppercase tracking-wider text-[10px]">Dernière exécution auto</span>
                        <span class="font-mono font-bold text-emerald-400">
                            {{ $lastBackupMeta['last_run_formatted'] ?? 'Prête à démarrer' }}
                        </span>
                    </div>
                    <div class="flex items-center justify-between text-xs">
                        <span class="text-slate-400 font-bold uppercase tracking-wider text-[10px]">Comptes Principaux actifs</span>
                        <span class="font-mono font-black text-white text-sm">{{ $principalAccounts->count() }} compte(s)</span>
                    </div>
                    <div class="flex items-center justify-between text-xs">
                        <span class="text-slate-400 font-bold uppercase tracking-wider text-[10px]">Politique de rétention</span>
                        <span class="px-2.5 py-0.5 rounded-lg bg-brand-500/20 text-brand-300 font-bold text-[11px]">14 Jours glissants</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Liste des Comptes Principaux et leurs Boutiques Associées -->
        <div class="glass-card rounded-[2.5rem] overflow-hidden shadow-card-soft">
            <div class="p-8 border-b border-slate-100 bg-slate-50/50 flex flex-col md:flex-row md:items-center justify-between gap-4">
                <div>
                    <h3 class="font-black text-slate-900 text-sm uppercase tracking-wider flex items-center gap-3">
                        <i class="bi bi-diagram-3-fill text-brand-600 text-lg"></i>
                        Dumps SQL par Compte Principal & Boutiques Associées
                    </h3>
                    <p class="text-xs text-slate-500 font-semibold mt-1">
                        Téléchargez en 1 clic le dump SQL consolidé d'un compte principal (toutes ses boutiques) ou d'une boutique spécifique.
                    </p>
                </div>
            </div>

            <div class="divide-y divide-slate-100 bg-white">
                @forelse ($principalAccounts as $account)
                    <div class="p-8 hover:bg-slate-50/60 transition-colors space-y-5">
                        <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-6">
                            <!-- Identité du Compte Principal -->
                            <div class="flex items-start gap-4">
                                <div class="w-14 h-14 rounded-2xl bg-gradient-to-br from-brand-600 to-indigo-800 text-white flex items-center justify-center font-black text-xl shadow-md flex-shrink-0">
                                    {{ strtoupper(substr($account->user->name, 0, 1)) }}
                                </div>
                                <div class="space-y-1">
                                    <div class="flex items-center gap-2.5 flex-wrap">
                                        <h4 class="text-lg font-black text-slate-900 tracking-tight">
                                            {{ $account->user->name }}
                                        </h4>
                                        <span class="px-2.5 py-0.5 rounded-full text-[10px] font-black uppercase tracking-wider bg-brand-50 text-brand-700 border border-brand-200">
                                            Compte Principal #{{ $account->user->id }}
                                        </span>
                                        <span class="px-2.5 py-0.5 rounded-full text-[10px] font-black uppercase tracking-wider bg-emerald-50 text-emerald-700 border border-emerald-200">
                                            {{ $account->boutiques_count }} boutique(s) associée(s)
                                        </span>
                                    </div>
                                    <p class="text-xs font-semibold text-slate-500">
                                        <i class="bi bi-envelope mr-1"></i> {{ $account->user->email }}
                                        <span class="mx-2 text-slate-300">&bull;</span>
                                        <span><strong>{{ number_format($account->produits_count, 0, ',', ' ') }}</strong> réf. en stock</span>
                                        <span class="mx-2 text-slate-300">&bull;</span>
                                        <span><strong>{{ number_format($account->ventes_count, 0, ',', ' ') }}</strong> ventes</span>
                                        <span class="mx-2 text-slate-300">&bull;</span>
                                        <span><strong>{{ $account->users_count }}</strong> utilisateur(s)</span>
                                    </p>
                                </div>
                            </div>

                            <!-- Actions de Dump pour ce Compte Principal -->
                            <div class="flex flex-wrap items-center gap-3">
                                @if ($account->latest_backup)
                                    <a href="{{ route('admin.backups.download', ['file' => $account->latest_backup['relative_path']]) }}"
                                        class="inline-flex items-center gap-2 px-4 py-2.5 rounded-2xl bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold transition-all"
                                        title="Télécharger la dernière sauvegarde automatique du {{ $account->latest_backup['date'] }}">
                                        <i class="bi bi-clock-history text-emerald-600"></i>
                                        <span>Archive Auto ({{ $account->latest_backup['date'] }} &bull; {{ $account->latest_backup['size_kb'] }} Ko)</span>
                                    </a>
                                @endif

                                <a href="{{ route('admin.backups.dump-account', $account->user->id) }}"
                                    class="btn-action bg-brand-600 hover:bg-brand-700 text-white shadow-lg shadow-brand-500/20 text-xs">
                                    <i class="bi bi-database-fill-down text-base"></i>
                                    <span>Dumper tout le Compte ({{ $account->boutiques_count }} boutique{{ $account->boutiques_count > 1 ? 's' : '' }})</span>
                                </a>
                            </div>
                        </div>

                        <!-- Détail des Boutiques Associées au Compte Principal -->
                        @if ($account->boutiques->isNotEmpty())
                            <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4 pt-2">
                                @foreach ($account->boutiques as $b)
                                    <div class="p-4 rounded-2xl bg-slate-50 border border-slate-200/80 flex items-center justify-between gap-3">
                                        <div class="min-w-0">
                                            <div class="flex items-center gap-2">
                                                <span class="w-2 h-2 rounded-full {{ $b->is_active ? 'bg-emerald-500' : 'bg-rose-500' }}"></span>
                                                <a href="{{ route('admin.boutiques.show', $b->id) }}" class="font-black text-xs text-slate-900 hover:text-brand-600 truncate">
                                                    {{ $b->nom }}
                                                </a>
                                                @if ($b->isFiliale())
                                                    <span class="px-2 py-0.5 rounded bg-amber-100 text-amber-800 text-[9px] font-black uppercase">Filiale</span>
                                                @else
                                                    <span class="px-2 py-0.5 rounded bg-indigo-100 text-indigo-800 text-[9px] font-black uppercase">Principale</span>
                                                @endif
                                            </div>
                                            <p class="text-[11px] text-slate-400 font-semibold truncate mt-0.5">
                                                {{ $b->nature?->name ?? 'Commerce' }} &bull; {{ $b->adresse ?: 'Adresse non renseignée' }}
                                            </p>
                                        </div>

                                        <a href="{{ route('admin.backups.dump-boutique', $b->id) }}"
                                            class="px-3 py-1.5 rounded-xl bg-white hover:bg-brand-600 text-slate-700 hover:text-white border border-slate-200 hover:border-brand-600 text-[11px] font-black transition-all flex items-center gap-1.5 flex-shrink-0 shadow-xs"
                                            title="Dumper uniquement la boutique {{ $b->nom }}">
                                            <i class="bi bi-download"></i>
                                            <span>SQL Boutique</span>
                                        </a>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>
                @empty
                    <div class="p-12 text-center text-slate-400 font-semibold text-sm">
                        Aucun compte principal enregistré.
                    </div>
                @endforelse
            </div>
        </div>

        <!-- Historique des Archives Globales -->
        @if (!empty($globalBackups))
            <div class="glass-card rounded-[2.5rem] overflow-hidden shadow-card-soft">
                <div class="p-8 border-b border-slate-100 bg-slate-50/50">
                    <h3 class="font-black text-slate-900 text-sm uppercase tracking-wider flex items-center gap-3">
                        <i class="bi bi-hdd-stack-fill text-emerald-600 text-lg"></i>
                        Archives Automatiques Globales (Serveur VPS)
                    </h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="border-b border-slate-100 bg-white text-[10px] font-black text-slate-400 uppercase tracking-widest">
                                <th class="px-8 py-4">Fichier SQL</th>
                                <th class="px-8 py-4">Date de Génération</th>
                                <th class="px-8 py-4">Taille</th>
                                <th class="px-8 py-4 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 bg-white text-xs">
                            @foreach (array_slice($globalBackups, 0, 10) as $gb)
                                <tr class="hover:bg-slate-50/80">
                                    <td class="px-8 py-4 font-mono font-bold text-slate-800">
                                        <i class="bi bi-filetype-sql text-brand-600 mr-2"></i>{{ $gb['filename'] }}
                                    </td>
                                    <td class="px-8 py-4 font-semibold text-slate-500">{{ $gb['date'] }}</td>
                                    <td class="px-8 py-4 font-mono font-bold text-emerald-600">{{ $gb['size_kb'] }} Ko</td>
                                    <td class="px-8 py-4 text-right">
                                        <div class="inline-flex items-center gap-2">
                                            <a href="{{ route('admin.backups.download', ['file' => $gb['relative_path']]) }}"
                                                class="px-3 py-1.5 rounded-xl bg-brand-50 text-brand-700 hover:bg-brand-600 hover:text-white font-black text-[11px] transition-all">
                                                <i class="bi bi-download mr-1"></i> Télécharger
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif
    </div>
@endsection

