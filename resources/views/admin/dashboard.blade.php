@extends('layouts.admin')

@section('content')
    <div class="space-y-10 animate-fade-in">
        <!-- Hero Header Executive -->
        <div class="relative overflow-hidden rounded-[2.5rem] bg-gradient-to-br from-slate-950 via-indigo-950 to-slate-900 p-8 lg:p-12 text-white shadow-2xl border border-white/10">
            <div class="relative z-10 flex flex-col md:flex-row md:items-center justify-between gap-8">
                <div class="space-y-3">
                    <div class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full bg-white/10 border border-white/15 text-brand-300 text-[10px] font-black uppercase tracking-[0.2em]">
                        <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span>
                        Système Central MalCom &bull; Zone UEMOA
                    </div>
                    <h1 class="text-4xl lg:text-5xl font-black tracking-tight leading-tight">
                        Cockpit <span class="text-transparent bg-clip-text bg-gradient-to-r from-brand-300 via-indigo-200 to-white">SuperAdmin.</span>
                    </h1>
                    <p class="text-slate-300 font-medium text-sm max-w-xl leading-relaxed">
                        Pilotage centralisé du réseau de boutiques, supervision des flux de caisse et attribution des licences en temps réel.
                    </p>
                </div>
                <div class="flex flex-wrap gap-3">
                    <a href="{{ route('admin.boutiques.index') }}"
                        class="group flex items-center gap-3 px-6 py-3.5 bg-white text-slate-950 rounded-2xl font-black text-xs uppercase tracking-wider hover:bg-brand-50 transition-all shadow-xl hover:scale-105 active:scale-95 cursor-pointer">
                        <i class="bi bi-shop-window text-brand-600 group-hover:rotate-6 transition-transform"></i>
                        <span>Explorer le Réseau</span>
                    </a>
                    <a href="{{ route('admin.licences.index') }}"
                        class="flex items-center gap-3 px-6 py-3.5 bg-white/10 hover:bg-white/20 text-white rounded-2xl font-black text-xs uppercase tracking-wider border border-white/15 transition-all cursor-pointer">
                        <i class="bi bi-key-fill text-amber-400"></i>
                        <span>Gérer Licences</span>
                    </a>
                </div>
            </div>

            <!-- Ambient background glows -->
            <div class="absolute top-0 right-0 -mr-20 -mt-20 w-96 h-96 bg-brand-500/20 blur-[130px] rounded-full pointer-events-none"></div>
            <div class="absolute bottom-0 left-0 -ml-20 -mb-20 w-72 h-72 bg-indigo-500/15 blur-[100px] rounded-full pointer-events-none"></div>
        </div>

        <!-- 3 Core Network Metrics Grid -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <!-- 1. Revenue Card -->
            <div class="glass-card group relative p-8 rounded-3xl overflow-hidden hover:shadow-card-hover transition-all">
                <div class="relative z-10 space-y-4">
                    <div class="w-14 h-14 rounded-2xl bg-brand-50 text-brand-600 flex items-center justify-center text-2xl shadow-inner group-hover:scale-105 transition-transform">
                        <i class="bi bi-bank2"></i>
                    </div>
                    <div>
                        <p class="text-[10px] font-black text-slate-400 uppercase tracking-[0.2em] mb-1">
                            Volume d'Affaires Global
                        </p>
                        <div class="flex items-baseline gap-2 flex-wrap">
                            <span class="text-3xl lg:text-4xl font-black text-slate-900 tracking-tight tabular-nums">
                                {{ number_format($totalSystemRevenue, 0, ',', ' ') }}
                            </span>
                            <span class="text-xs font-black text-brand-600 uppercase">FCFA</span>
                        </div>
                    </div>
                    <div class="pt-4 border-t border-slate-100 flex items-center justify-between text-xs">
                        <span class="text-slate-500 font-semibold">Chiffre d'affaires cumulé</span>
                        <span class="px-2.5 py-0.5 rounded-lg bg-emerald-50 text-emerald-700 text-[10px] font-black border border-emerald-200">
                            Actif
                        </span>
                    </div>
                </div>
                <div class="absolute -right-4 -bottom-4 opacity-[0.03] group-hover:opacity-[0.07] transition-opacity pointer-events-none">
                    <i class="bi bi-bank2 text-[140px] text-slate-900"></i>
                </div>
            </div>

            <!-- 2. Shops Card -->
            <div class="glass-card group relative p-8 rounded-3xl overflow-hidden hover:shadow-card-hover transition-all">
                <div class="relative z-10 space-y-4">
                    <div class="w-14 h-14 rounded-2xl bg-amber-50 text-amber-600 flex items-center justify-center text-2xl shadow-inner group-hover:scale-105 transition-transform">
                        <i class="bi bi-shop"></i>
                    </div>
                    <div>
                        <p class="text-[10px] font-black text-slate-400 uppercase tracking-[0.2em] mb-1">
                            Points de Vente Établis
                        </p>
                        <div class="flex items-baseline gap-2 text-3xl lg:text-4xl font-black text-slate-900 tracking-tight">
                            <span>{{ $boutiquesCount }}</span>
                            <span class="text-xs font-black text-slate-400 uppercase">Établissements</span>
                        </div>
                    </div>
                    <div class="pt-4 border-t border-slate-100 flex items-center justify-between text-xs">
                        <span class="text-slate-500 font-semibold">{{ $boutiquePerformance->count() }} avec activité</span>
                        <a href="{{ route('admin.boutiques.index') }}" class="text-brand-600 font-bold hover:underline flex items-center gap-1 text-[11px]">
                            Voir tout <i class="bi bi-arrow-right"></i>
                        </a>
                    </div>
                </div>
                <div class="absolute -right-4 -bottom-4 opacity-[0.03] group-hover:opacity-[0.07] transition-opacity pointer-events-none">
                    <i class="bi bi-shop text-[140px] text-slate-900"></i>
                </div>
            </div>

            <!-- 3. Staff Card -->
            <div class="glass-card group relative p-8 rounded-3xl overflow-hidden hover:shadow-card-hover transition-all">
                <div class="relative z-10 space-y-4">
                    <div class="w-14 h-14 rounded-2xl bg-emerald-50 text-emerald-600 flex items-center justify-center text-2xl shadow-inner group-hover:scale-105 transition-transform">
                        <i class="bi bi-people-fill"></i>
                    </div>
                    <div>
                        <p class="text-[10px] font-black text-slate-400 uppercase tracking-[0.2em] mb-1">
                            Comptes & Utilisateurs
                        </p>
                        <div class="flex items-baseline gap-2 text-3xl lg:text-4xl font-black text-slate-900 tracking-tight">
                            <span>{{ $usersCount }}</span>
                            <span class="text-xs font-black text-slate-400 uppercase">Membres Actifs</span>
                        </div>
                    </div>
                    <div class="pt-4 border-t border-slate-100 flex items-center justify-between">
                        <div class="flex -space-x-2.5 overflow-hidden">
                            @for ($i = 0; $i < min($usersCount, 4); $i++)
                                <div class="inline-flex h-7 w-7 rounded-full bg-brand-100 text-brand-700 border-2 border-white items-center justify-center text-[10px] font-black">
                                    {{ chr(65 + $i) }}
                                </div>
                            @endfor
                            @if ($usersCount > 4)
                                <div class="inline-flex h-7 w-7 rounded-full bg-slate-900 text-white border-2 border-white items-center justify-center text-[9px] font-black">
                                    +{{ $usersCount - 4 }}
                                </div>
                            @endif
                        </div>
                        <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Rôles Attribués</span>
                    </div>
                </div>
                <div class="absolute -right-4 -bottom-4 opacity-[0.03] group-hover:opacity-[0.07] transition-opacity pointer-events-none">
                    <i class="bi bi-people-fill text-[140px] text-slate-900"></i>
                </div>
            </div>
        </div>

        <!-- Top Performance Leaderboard Section -->
        <div class="glass-card rounded-[2.5rem] overflow-hidden shadow-card-soft">
            <div class="p-8 lg:p-10 flex flex-col md:flex-row md:items-end justify-between gap-6 bg-slate-50/60 border-b border-slate-100">
                <div class="space-y-1.5">
                    <div class="inline-flex items-center gap-2 text-amber-600 text-[10px] font-black uppercase tracking-[0.2em]">
                        <i class="bi bi-trophy-fill text-sm text-amber-500"></i>
                        <span>Classement d'Excellence Retail</span>
                    </div>
                    <h2 class="text-2xl lg:text-3xl font-black text-slate-900 tracking-tight">
                        Top Performance <span class="text-brand-600">Boutiques.</span>
                    </h2>
                    <p class="text-slate-500 font-semibold text-xs">
                        Hiérarchisation automatique selon le chiffre d'affaires cumulé en FCFA.
                    </p>
                </div>
                <div class="flex items-center gap-3">
                    <a href="{{ route('admin.boutiques.index') }}"
                        class="btn-action bg-brand-600 hover:bg-brand-700 text-white text-xs shadow-md shadow-brand-500/20">
                        <i class="bi bi-grid-fill text-xs"></i>
                        <span>Voir Toutes les Boutiques</span>
                    </a>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="border-b border-slate-100 bg-white text-[10px] font-black text-slate-400 uppercase tracking-widest">
                            <th class="px-8 py-5">Rang & Établissement</th>
                            <th class="px-8 py-5">Activité</th>
                            <th class="px-8 py-5 text-right">Chiffre d'Affaires</th>
                            <th class="px-8 py-5 text-center">Statut</th>
                            <th class="px-8 py-5 text-right">Détails</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 bg-white">
                        @forelse ($boutiquePerformance as $index => $boutique)
                            <tr class="hover:bg-slate-50/80 transition-colors group">
                                <td class="px-8 py-5">
                                    <div class="flex items-center gap-4">
                                        <!-- Podium Badge -->
                                        @if ($index === 0)
                                            <div class="w-11 h-11 rounded-2xl bg-amber-500 text-white flex items-center justify-center font-black text-base shadow-lg shadow-amber-500/30 group-hover:scale-105 transition-transform" title="1er du Réseau">
                                                <i class="bi bi-trophy-fill text-sm"></i>
                                            </div>
                                        @elseif ($index === 1)
                                            <div class="w-11 h-11 rounded-2xl bg-slate-300 text-slate-700 flex items-center justify-center font-black text-base shadow-sm group-hover:scale-105 transition-transform" title="2ème du Réseau">
                                                2
                                            </div>
                                        @elseif ($index === 2)
                                            <div class="w-11 h-11 rounded-2xl bg-amber-700/60 text-white flex items-center justify-center font-black text-base shadow-sm group-hover:scale-105 transition-transform" title="3ème du Réseau">
                                                3
                                            </div>
                                        @else
                                            <div class="w-11 h-11 rounded-2xl bg-slate-100 text-slate-500 flex items-center justify-center font-bold text-sm">
                                                {{ $index + 1 }}
                                            </div>
                                        @endif

                                        <div>
                                            <div class="font-black text-slate-900 tracking-tight group-hover:text-brand-600 transition-colors">
                                                {{ $boutique->nom }}
                                            </div>
                                            <div class="text-[11px] font-bold text-slate-400 flex items-center gap-1.5 mt-0.5">
                                                <i class="bi bi-geo-alt-fill text-slate-300 text-[10px]"></i>
                                                {{ $boutique->adresse }}
                                            </div>
                                        </div>
                                    </div>
                                </td>

                                <td class="px-8 py-5">
                                    <span class="px-3 py-1 bg-slate-100 text-slate-600 rounded-xl text-[10px] font-black uppercase tracking-wider">
                                        {{ $boutique->nature?->name ?? 'Standard' }}
                                    </span>
                                </td>

                                <td class="px-8 py-5 text-right">
                                    <div class="font-black text-slate-900 text-base tabular-nums">
                                        {{ number_format($boutique->revenue ?? 0, 0, ',', ' ') }}
                                        <span class="text-[10px] text-brand-600 font-black ml-1">FCFA</span>
                                    </div>
                                </td>

                                <td class="px-8 py-5">
                                    <div class="flex justify-center">
                                        @if ($boutique->is_active)
                                            <span class="inline-flex items-center gap-1.5 px-3 py-1 bg-emerald-50 text-emerald-700 text-[10px] font-black uppercase tracking-wider rounded-full border border-emerald-200">
                                                <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                                                Actif
                                            </span>
                                        @else
                                            <span class="inline-flex items-center gap-1.5 px-3 py-1 bg-rose-50 text-rose-700 text-[10px] font-black uppercase tracking-wider rounded-full border border-rose-200">
                                                <span class="w-1.5 h-1.5 rounded-full bg-rose-500"></span>
                                                Suspendu
                                            </span>
                                        @endif
                                    </div>
                                </td>

                                <td class="px-8 py-5 text-right">
                                    <a href="{{ route('admin.boutiques.show', $boutique) }}"
                                        class="inline-flex items-center justify-center w-10 h-10 rounded-xl bg-slate-100 hover:bg-slate-900 text-slate-500 hover:text-white transition-all shadow-sm">
                                        <i class="bi bi-arrow-right text-sm"></i>
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-center py-20 bg-white">
                                    <i class="bi bi-shop text-4xl text-slate-300 block mb-3"></i>
                                    <p class="text-slate-400 text-sm font-semibold">Aucune boutique enregistrée pour le moment.</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
