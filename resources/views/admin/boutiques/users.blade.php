@extends('layouts.admin')

@section('content')
    <div class="space-y-10 animate-fade-in">
        <!-- Header Section -->
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-6 pb-2 border-b border-slate-200/60">
            <div class="flex items-center gap-5">
                <a href="{{ route('admin.boutiques.show', $boutique) }}"
                    class="w-12 h-12 rounded-2xl bg-white border border-slate-200 text-slate-500 hover:text-brand-600 hover:border-brand-300 flex items-center justify-center transition-all shadow-sm group cursor-pointer"
                    title="Retour à la boutique">
                    <i class="bi bi-arrow-left text-xl group-hover:-translate-x-1 transition-transform"></i>
                </a>
                <div class="space-y-1">
                    <div class="inline-flex items-center gap-2 px-3 py-1 bg-brand-50 text-brand-700 rounded-full text-[10px] font-black uppercase tracking-widest mb-1">
                        <i class="bi bi-shop-window"></i>
                        {{ $boutique->nom }}
                    </div>
                    <h1 class="text-3xl lg:text-4xl font-black text-slate-900 tracking-tight">
                        Ressources <span class="text-brand-600">Humaines.</span>
                    </h1>
                    <p class="text-slate-500 font-semibold text-xs">
                        Gestion des comptes employés, attribution des rôles et contrôle des accès sécurité.
                    </p>
                </div>
            </div>
            <div class="flex items-center gap-3">
                <button onclick="document.getElementById('createUserModal').classList.remove('hidden')"
                    class="btn-action bg-brand-600 hover:bg-brand-700 text-white shadow-lg shadow-brand-500/20 text-xs cursor-pointer">
                    <i class="bi bi-person-plus-fill text-sm"></i>
                    <span>Nouveau Compte</span>
                </button>
            </div>
        </div>

        <!-- Main Table Card -->
        <div class="glass-card rounded-[2.5rem] overflow-hidden shadow-card-soft">
            <div class="p-8 border-b border-slate-100 bg-slate-50/50 flex flex-col md:flex-row md:items-center justify-between gap-4">
                <h3 class="font-black text-slate-900 text-sm uppercase tracking-wider flex items-center gap-3">
                    <i class="bi bi-people-fill text-brand-600 text-lg"></i>
                    Registre du Personnel Affecté ({{ $users->count() }})
                </h3>
                <div class="relative group">
                    <i class="bi bi-search absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 group-focus-within:text-brand-600 transition-colors"></i>
                    <input type="text" id="searchUserInput" onkeyup="filterUsers()" placeholder="Rechercher un membre..."
                        class="pl-11 pr-5 py-3 bg-white border border-slate-200 rounded-2xl text-xs font-bold w-full md:w-80 focus:ring-2 focus:ring-brand-500 outline-none">
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse" id="usersTable">
                    <thead>
                        <tr class="border-b border-slate-100 bg-white text-[10px] font-black text-slate-400 uppercase tracking-widest">
                            <th class="px-8 py-5">Membre du Personnel</th>
                            <th class="px-8 py-5 text-center">Rôle Attribué</th>
                            <th class="px-8 py-5 text-center">Statut d'Accès</th>
                            <th class="px-8 py-5 text-right">Sécurité & Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 bg-white">
                        @forelse ($users as $user)
                            <tr class="hover:bg-slate-50/80 transition-colors user-row {{ !$user->is_active ? 'opacity-70' : '' }}">
                                <td class="px-8 py-5">
                                    <div class="flex items-center gap-4">
                                        <div class="relative">
                                            <div class="w-12 h-12 rounded-2xl {{ $user->role == 'admin' ? 'bg-brand-50 text-brand-700' : 'bg-slate-100 text-slate-600' }} flex items-center justify-center font-black text-base shadow-sm">
                                                {{ substr($user->name, 0, 1) }}
                                            </div>
                                            <div class="absolute -bottom-1 -right-1 w-3.5 h-3.5 rounded-full border-2 border-white {{ $user->is_active ? 'bg-emerald-500' : 'bg-slate-300' }}"></div>
                                        </div>
                                        <div>
                                            <p class="font-black text-slate-900 text-xs tracking-tight user-name">{{ $user->name }}</p>
                                            <p class="text-[11px] font-semibold text-slate-400 mt-0.5">{{ $user->email }}</p>
                                        </div>
                                    </div>
                                </td>

                                <td class="px-8 py-5 text-center">
                                    @php
                                        $roleBadges = [
                                            'admin' => 'bg-brand-50 text-brand-700 border-brand-200',
                                            'gestionnaire' => 'bg-amber-50 text-amber-700 border-amber-200',
                                            'comptable' => 'bg-emerald-50 text-emerald-700 border-emerald-200',
                                            'vendeur' => 'bg-indigo-50 text-indigo-700 border-indigo-200',
                                        ];
                                        $badgeClass = $roleBadges[$user->role] ?? 'bg-slate-100 text-slate-600 border-slate-200';
                                    @endphp
                                    <span class="px-3 py-1 rounded-full text-[10px] font-black uppercase tracking-wider border {{ $badgeClass }}">
                                        {{ $user->role }}
                                    </span>
                                </td>

                                <td class="px-8 py-5">
                                    <div class="flex justify-center">
                                        <form action="{{ route('admin.users.toggle-status', $user) }}" method="POST">
                                            @csrf
                                            <button type="submit"
                                                class="flex items-center gap-1.5 px-3 py-1.5 rounded-full border transition-all cursor-pointer {{ $user->is_active ? 'bg-emerald-50 border-emerald-200 text-emerald-700 hover:bg-emerald-100' : 'bg-slate-100 border-slate-200 text-slate-600 hover:bg-slate-200' }}"
                                                title="{{ $user->is_active ? 'Cliquer pour restreindre' : 'Cliquer pour réactiver' }}">
                                                <i class="bi {{ $user->is_active ? 'bi-shield-check' : 'bi-shield-slash' }} text-xs"></i>
                                                <span class="text-[10px] font-black uppercase tracking-wider">{{ $user->is_active ? 'Actif' : 'Restreint' }}</span>
                                            </button>
                                        </form>
                                    </div>
                                </td>

                                <td class="px-8 py-5 text-right">
                                    <div class="flex items-center justify-end gap-2">
                                        <!-- Bouton Réinitialiser Mot de passe -->
                                        <button onclick="openPasswordModal('{{ $user->id }}', '{{ addslashes($user->name) }}')"
                                            class="w-9 h-9 rounded-xl bg-slate-100 hover:bg-brand-50 text-slate-500 hover:text-brand-600 flex items-center justify-center transition-colors cursor-pointer"
                                            title="Réinitialiser le mot de passe">
                                            <i class="bi bi-key-fill text-xs"></i>
                                        </button>

                                         <!-- Bouton Supprimer -->
                                         <form action="{{ route('admin.users.destroy', $user) }}" method="POST"
                                             onsubmit="return triggerDeleteConfirm(event, 'Supprimer le collaborateur', 'Voulez-vous vraiment supprimer définitivement le compte de {{ addslashes($user->name) }} ({{ addslashes($user->email) }}) ?');">
                                             @csrf
                                             @method('DELETE')
                                            <button type="submit"
                                                class="w-9 h-9 rounded-xl bg-rose-50 text-rose-600 hover:bg-rose-500 hover:text-white flex items-center justify-center transition-colors cursor-pointer"
                                                title="Supprimer définitivement">
                                                <i class="bi bi-trash3-fill text-xs"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="text-center py-20 bg-white">
                                    <i class="bi bi-people text-4xl text-slate-300 block mb-3"></i>
                                    <p class="text-slate-400 text-sm font-semibold">Aucun membre du personnel enregistré pour cette boutique.</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Modal 1 : Créer Utilisateur -->
    <div id="createUserModal" class="hidden fixed inset-0 z-[100] flex items-center justify-center p-6 sm:p-10">
        <div class="absolute inset-0 bg-slate-950/60 backdrop-blur-md" onclick="document.getElementById('createUserModal').classList.add('hidden')"></div>
        <div class="relative w-full max-w-md bg-white rounded-[2.5rem] p-8 shadow-2xl border border-slate-100">
            <div class="flex items-center justify-between mb-6 pb-4 border-b border-slate-100">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-2xl bg-brand-50 text-brand-600 flex items-center justify-center">
                        <i class="bi bi-person-plus-fill text-lg"></i>
                    </div>
                    <h3 class="text-xl font-black text-slate-900 tracking-tight">Nouveau Membre</h3>
                </div>
                <button onclick="document.getElementById('createUserModal').classList.add('hidden')" class="w-8 h-8 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-500 flex items-center justify-center cursor-pointer">
                    <i class="bi bi-x-lg"></i>
                </button>
            </div>

            <form action="{{ route('admin.boutiques.users.store', $boutique) }}" method="POST" class="space-y-4">
                @csrf
                <div>
                    <label class="block text-xs font-black uppercase tracking-widest text-slate-400 mb-2">Nom Complet</label>
                    <input type="text" name="name" required placeholder="ex: Amadou Diallo" class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-2xl text-xs font-bold focus:bg-white focus:ring-2 focus:ring-brand-500 outline-none">
                </div>

                <div>
                    <label class="block text-xs font-black uppercase tracking-widest text-slate-400 mb-2">Adresse E-mail</label>
                    <input type="email" name="email" required placeholder="amadou@boutique.com" class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-2xl text-xs font-bold focus:bg-white focus:ring-2 focus:ring-brand-500 outline-none">
                </div>

                <div>
                    <label class="block text-xs font-black uppercase tracking-widest text-slate-400 mb-2">Rôle Attribué</label>
                    <select name="role" required class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-2xl text-xs font-bold focus:bg-white focus:ring-2 focus:ring-brand-500 outline-none">
                        <option value="vendeur">Vendeur / Caissier</option>
                        <option value="comptable">Comptable</option>
                        <option value="gestionnaire">Gestionnaire de Stock</option>
                        <option value="admin">Administrateur Boutique</option>
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-black uppercase tracking-widest text-slate-400 mb-2">Mot de Passe Initial</label>
                    <input type="password" name="password" required placeholder="••••••••" class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-2xl text-xs font-bold focus:bg-white focus:ring-2 focus:ring-brand-500 outline-none">
                </div>

                <div class="pt-4 flex justify-end gap-3 border-t border-slate-100">
                    <button type="button" onclick="document.getElementById('createUserModal').classList.add('hidden')" class="px-5 py-2.5 font-bold text-xs text-slate-500 hover:text-slate-900 cursor-pointer">Annuler</button>
                    <button type="submit" class="btn-action bg-brand-600 hover:bg-brand-700 text-white text-xs">Créer le Compte</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal 2 : Réinitialiser Mot de passe -->
    <div id="passwordModal" class="hidden fixed inset-0 z-[100] flex items-center justify-center p-6 sm:p-10">
        <div class="absolute inset-0 bg-slate-950/60 backdrop-blur-md" onclick="closePasswordModal()"></div>
        <div class="relative w-full max-w-md bg-white rounded-[2.5rem] p-8 shadow-2xl border border-slate-100">
            <div class="flex items-center justify-between mb-6 pb-4 border-b border-slate-100">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-2xl bg-amber-50 text-amber-600 flex items-center justify-center">
                        <i class="bi bi-key-fill text-lg"></i>
                    </div>
                    <div>
                        <h3 class="text-lg font-black text-slate-900 tracking-tight">Modifier Mot de Passe</h3>
                        <p class="text-[11px] font-bold text-slate-400" id="passwordModalUserName"></p>
                    </div>
                </div>
                <button onclick="closePasswordModal()" class="w-8 h-8 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-500 flex items-center justify-center cursor-pointer">
                    <i class="bi bi-x-lg"></i>
                </button>
            </div>

            <form id="passwordForm" method="POST" action="" class="space-y-4">
                @csrf
                <div>
                    <label class="block text-xs font-black uppercase tracking-widest text-slate-400 mb-2">Nouveau Mot de Passe</label>
                    <input type="password" name="password" required placeholder="Minimum 6 caractères" class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-2xl text-xs font-bold focus:bg-white focus:ring-2 focus:ring-brand-500 outline-none">
                </div>

                <div class="pt-4 flex justify-end gap-3 border-t border-slate-100">
                    <button type="button" onclick="closePasswordModal()" class="px-5 py-2.5 font-bold text-xs text-slate-500 hover:text-slate-900 cursor-pointer">Annuler</button>
                    <button type="submit" class="btn-action bg-amber-600 hover:bg-amber-700 text-white text-xs">Mettre à Jour</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Scripts -->
    <script>
        function openPasswordModal(userId, userName) {
            document.getElementById('passwordModalUserName').textContent = userName;
            document.getElementById('passwordForm').action = `/admin/users/${userId}/update-password`;
            document.getElementById('passwordModal').classList.remove('hidden');
        }

        function closePasswordModal() {
            document.getElementById('passwordModal').classList.add('hidden');
        }

        function filterUsers() {
            const input = document.getElementById('searchUserInput').value.toLowerCase();
            const rows = document.querySelectorAll('.user-row');
            rows.forEach(row => {
                const name = row.querySelector('.user-name')?.textContent.toLowerCase() || '';
                if (name.includes(input)) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        }
    </script>
@endsection
