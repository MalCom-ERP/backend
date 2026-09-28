@extends('layouts.admin')

@section('content')
    <div class="space-y-10 animate-fade-in">
        <!-- Header Section -->
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-6 pb-2 border-b border-slate-200/60">
            <div class="space-y-1">
                <div class="inline-flex items-center gap-2 px-3 py-1 bg-brand-50 text-brand-700 rounded-full text-[10px] font-black uppercase tracking-widest mb-1">
                    <i class="bi bi-shield-lock-fill"></i>
                    Sécurité Haute Instance
                </div>
                <h1 class="text-3xl lg:text-4xl font-black text-slate-900 tracking-tight">
                    Administrateurs <span class="text-brand-600">Système.</span>
                </h1>
                <p class="text-slate-500 font-semibold text-xs">
                    Gestion des comptes administrateurs et contrôle des quotas d'expansion de boutiques.
                </p>
            </div>
            <div class="flex items-center gap-3">
                <div class="glass-card px-6 py-3.5 rounded-2xl flex items-center gap-4 shadow-sm">
                    <div class="w-10 h-10 rounded-xl bg-brand-50 text-brand-600 flex items-center justify-center text-lg">
                        <i class="bi bi-shield-lock-fill"></i>
                    </div>
                    <div>
                        <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Effectif Administrateur</p>
                        <p class="text-xl font-black text-slate-900 leading-none mt-0.5">{{ $admins->total() }}</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main Table Card -->
        <div class="glass-card rounded-[2.5rem] overflow-hidden shadow-card-soft">
            <div class="p-8 border-b border-slate-100 bg-slate-50/50 flex items-center justify-between">
                <h3 class="font-black text-slate-900 text-sm uppercase tracking-wider flex items-center gap-3">
                    <i class="bi bi-person-badge-fill text-brand-600 text-lg"></i>
                    Comptes d'Administration Système
                </h3>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="border-b border-slate-100 bg-white text-[10px] font-black text-slate-400 uppercase tracking-widest">
                            <th class="px-8 py-5">Administrateur</th>
                            <th class="px-8 py-5 text-center">Privilèges</th>
                            <th class="px-8 py-5 text-center">Quota Boutiques</th>
                            <th class="px-8 py-5 text-center">Inscription</th>
                            <th class="px-8 py-5 text-right">Sécurité</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 bg-white">
                        @foreach ($admins as $admin)
                            @if ($admin->role == 'admin' || $admin->role == 'super_admin')
                                <tr class="hover:bg-slate-50/80 transition-colors">
                                    <td class="px-8 py-5">
                                        <div class="flex items-center gap-4">
                                            <div class="w-12 h-12 rounded-2xl {{ $admin->role === 'super_admin' ? 'bg-amber-50 text-amber-600 border border-amber-200' : 'bg-brand-50 text-brand-600 border border-brand-200' }} flex items-center justify-center font-black text-base shadow-sm">
                                                {{ substr($admin->name, 0, 1) }}
                                            </div>
                                            <div>
                                                <p class="font-black text-slate-900 text-xs tracking-tight">{{ $admin->name }}</p>
                                                <p class="text-[11px] font-semibold text-slate-400 mt-0.5">{{ $admin->email }}</p>
                                            </div>
                                        </div>
                                    </td>

                                    <td class="px-8 py-5 text-center">
                                        <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-[10px] font-black uppercase tracking-wider border {{ $admin->role === 'super_admin' ? 'bg-amber-50 text-amber-700 border-amber-200' : 'bg-brand-50 text-brand-700 border-brand-200' }}">
                                            <i class="{{ $admin->role === 'super_admin' ? 'bi bi-star-fill text-amber-500' : 'bi bi-shield-check text-brand-500' }}"></i>
                                            {{ $admin->role === 'super_admin' ? 'Super Admin' : 'Admin' }}
                                        </span>
                                    </td>

                                    <td class="px-8 py-5">
                                        <form action="{{ route('admin.admins.update-limit', $admin->id) }}" method="POST"
                                            class="flex items-center justify-center gap-2">
                                            @csrf
                                            <input type="number" name="boutique_limit" value="{{ $admin->boutique_limit }}" min="1"
                                                class="w-16 px-2.5 py-1.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-black text-slate-900 text-center focus:bg-white focus:ring-2 focus:ring-brand-500 outline-none transition-all">
                                            <button type="submit"
                                                class="w-8 h-8 rounded-xl bg-slate-100 hover:bg-emerald-50 text-slate-400 hover:text-emerald-600 hover:border-emerald-200 border border-slate-200 flex items-center justify-center transition-all cursor-pointer shadow-sm"
                                                title="Mettre à jour le quota">
                                                <i class="bi bi-check-lg text-sm"></i>
                                            </button>
                                        </form>
                                    </td>

                                    <td class="px-8 py-5 text-center">
                                        <span class="text-xs font-bold text-slate-500">
                                            {{ $admin->created_at->format('d/m/Y') }}
                                        </span>
                                    </td>

                                    <td class="px-8 py-5 text-right">
                                        <div class="flex items-center justify-end gap-2">
                                            @if ($admin->id !== Auth::id())
                                                <form action="{{ route('admin.admins.destroy', $admin->id) }}" method="POST"
                                                    onsubmit="return triggerDeleteConfirm(event, 'Supprimer l\'administrateur', 'Voulez-vous vraiment supprimer définitivement le compte de {{ addslashes($admin->name) }} ? Cette action est irréversible.');">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit"
                                                        class="w-9 h-9 rounded-xl bg-rose-50 text-rose-600 hover:bg-rose-500 hover:text-white flex items-center justify-center transition-colors cursor-pointer"
                                                        title="Supprimer définitivement">
                                                        <i class="bi bi-trash3-fill text-xs"></i>
                                                    </button>
                                                </form>
                                            @else
                                                <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-xl bg-emerald-50 text-emerald-700 text-[10px] font-black uppercase tracking-wider border border-emerald-200">
                                                    <i class="bi bi-person-check-fill"></i> Compte Actuel
                                                </span>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @endif
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if ($admins->hasPages())
                <div class="p-6 border-t border-slate-100 bg-slate-50/50">
                    {{ $admins->links() }}
                </div>
            @endif
        </div>
    </div>
@endsection
