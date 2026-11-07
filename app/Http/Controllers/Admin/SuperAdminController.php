<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Boutique;
use App\Models\User;
use App\Models\Vente;
use App\Models\DetailVente;
use App\Models\Nature;
use App\Models\Licence;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class SuperAdminController extends Controller
{
    public function toggleUserStatus(User $user)
    {
        $user->is_active = !$user->is_active;
        $user->save();

        $status = $user->is_active ? 'activé' : 'désactivé';
        return back()->with('success', "Le compte de {$user->name} a été {$status} avec succès.");
    }

    public function dashboard()
    {
        $validStatuses = ['validee', 'payee', 'credit'];
        $boutiquesCount = Boutique::count();
        $usersCount = User::count();
        $totalSystemRevenue = Vente::whereIn('statut', $validStatuses)->sum('montant_total');

        $boutiquePerformance = Boutique::withSum(['ventes as revenue' => function ($query) use ($validStatuses) {
            $query->whereIn('statut', $validStatuses);
        }], 'montant_total')
            ->orderByDesc('revenue')
            ->limit(5)
            ->get();

        return view('admin.dashboard', compact('boutiquesCount', 'usersCount', 'totalSystemRevenue', 'boutiquePerformance'));
    }

    public function boutiquesIndex()
    {
        $boutiques = Boutique::with(['creator', 'nature', 'licences', 'parent', 'filiales'])->get();
        $natures = Nature::where('is_active', true)->get();
        return view('admin.boutiques.index', compact('boutiques', 'natures'));
    }

    public function boutiqueShow(Boutique $boutique)
    {
        $validStatuses = ['validee', 'payee', 'credit'];
        $salesCount = Vente::where('boutique_id', $boutique->id)->whereIn('statut', $validStatuses)->count();
        $totalRevenue = Vente::where('boutique_id', $boutique->id)->whereIn('statut', $validStatuses)->sum('montant_total');

        $topProducts = DetailVente::with('produit')
            ->whereHas('vente', function ($q) use ($boutique, $validStatuses) {
                $q->where('boutique_id', $boutique->id)->whereIn('statut', $validStatuses);
            })
            ->selectRaw('produit_id, SUM(quantite) as total_qty, SUM(montant_total) as total_amount')
            ->groupBy('produit_id')
            ->orderByDesc('total_qty')
            ->limit(5)
            ->get();

        $recentSales = Vente::with('user')
            ->where('boutique_id', $boutique->id)
            ->orderByDesc('created_at')
            ->limit(10)
            ->get();

        $boutique->load(['licences.creator', 'creator', 'parent', 'filiales']);

        return view('admin.boutiques.show', compact('boutique', 'salesCount', 'totalRevenue', 'topProducts', 'recentSales'));
    }

    public function boutiqueStore(Request $request)
    {
        $request->validate([
            'nom' => 'required|string|max:255',
            'adresse' => 'required|string|max:255',
            'telephone' => 'required|string|max:20',
            'nom_admin' => 'required|string|max:255',
            'email_admin' => 'required|email|unique:users,email',
            'password_admin' => 'required|string|min:6',
            'nature_id' => 'required|exists:natures,id',
        ]);

        DB::transaction(function () use ($request) {

            $user = User::create([
                'name' => $request->nom_admin,
                'email' => $request->email_admin,
                'password' => Hash::make($request->password_admin),
                'role' => 'admin',
                'boutique_id' => null,
                'is_active' => true,
                'boutique_limit' => 2 // Default limit for new admins
            ]);

            $boutique = Boutique::create([
                'nom' => $request->nom,
                'adresse' => $request->adresse,
                'telephone' => $request->telephone,
                'is_active' => true,
                'user_id' =>$user->id,
                'email' => $request->email_admin,
                'nature_id' => $request->nature_id
            ]);
            $user->boutique_id = $boutique->id;
            $user->save();
            
        });

        return redirect()->route('admin.boutiques.index')->with('success', 'Boutique et administrateur créés avec succès.');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        if (Auth::attempt($credentials, $request->boolean('remember'))) {
            $request->session()->regenerate();

            return redirect()->intended(route('admin.dashboard'));
        }

        return back()->withErrors([
            'email' => 'Les identifiants fournis ne correspondent pas à nos enregistrements.',
        ])->onlyInput('email');
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('admin.loginPage');
    }

    public function adminsIndex()
    {
        $admins = User::paginate(10); // Standard pagination
        return view('admin.admins.index', compact('admins'));
    }

    public function adminDestroy(User $admin)
    {
        if ($admin->id === Auth::id()) {
            return back()->withErrors(['error' => 'Vous ne pouvez pas supprimer votre propre compte.']);
        }
        $admin->delete();
        return redirect()->route('admin.admins.index')->with('success', 'Administrateur supprimé avec succès.');
    }

    public function updateBoutiqueLimit(Request $request, User $user)
    {
        $request->validate([
            'boutique_limit' => 'required|integer|min:1',
        ]);

        $user->boutique_limit = $request->boutique_limit;
        $user->save();

        return back()->with('success', "Limite de boutiques mise à jour pour {$user->name}.");
    }

    public function toggleBoutiqueStatus(Boutique $boutique)
    {
        $boutique->is_active = !$boutique->is_active;
        $boutique->save();

        if (!$boutique->isFiliale()) {
            $boutique->filiales()->update(['is_active' => $boutique->is_active]);
        }

        $status = $boutique->is_active ? 'activée' : 'désactivée';
        $extra = (!$boutique->isFiliale() && $boutique->filiales()->count() > 0)
            ? " ainsi que toutes ses filiales ({$boutique->filiales()->count()})"
            : "";
        return back()->with('success', "La boutique {$boutique->nom} a été {$status}{$extra} avec succès.");
    }

    public function userStore(Request $request, Boutique $boutique)
    {
        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password' => ['required', 'string', 'min:6'],
            'role' => ['required', 'string', 'in:vendeur,comptable,gestionnaire,admin'],
        ]);

        User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'role' => $request->role,
            'boutique_id' => $boutique->id,
            'is_active' => true
        ]);

        return back()->with('success', "L'utilisateur {$request->name} a été ajouté à la boutique.");
    }

    public function boutiqueUsers(Boutique $boutique)
    {
        $users = $boutique->users()->orderBy('role')->paginate(15);
        return view('admin.boutiques.users', compact('boutique', 'users'));
    }

    public function updateUserPassword(Request $request, User $user)
    {
        $request->validate([
            'password' => ['required', 'string', 'min:6', 'confirmed'],
        ]);

        $user->password = Hash::make($request->password);
        $user->save();

        return back()->with('success', "Le mot de passe de {$user->name} a été mis à jour avec succès.");
    }

    public function userDestroy(User $user)
    {
        if ($user->id === Auth::id()) {
            return back()->withErrors(['error' => 'Vous ne pouvez pas supprimer votre propre compte.']);
        }
        $user->delete();
        return back()->with('success', 'Utilisateur supprimé avec succès.');
    }

    public function boutiqueDestroy(Boutique $boutique)
    {
        $boutique->delete();
        return redirect()->route('admin.boutiques.index')->with('success', 'Boutique supprimée avec succès.');
    }

    /**
     * Page de gestion globale des licences et abonnements
     */
    public function licencesIndex(Request $request)
    {
        $query = Licence::with(['boutique', 'creator'])->latest();

        if ($request->filled('boutique_id')) {
            $query->where('boutique_id', $request->boutique_id);
        }

        if ($request->filled('statut')) {
            $query->where('statut', $request->statut);
        }

        $licences = $query->paginate(20);
        $boutiques = Boutique::with(['filiales', 'parent'])->orderBy('nom')->get();

        $stats = [
            'total' => Licence::count(),
            'inutilisees' => Licence::where('statut', 'inutilisee')->count(),
            'actives' => Licence::where('statut', 'active')->count(),
            'expirees' => Licence::where('statut', 'expiree')->count(),
            'revoquees' => Licence::where('statut', 'revoquee')->count(),
        ];

        return view('admin.licences.index', compact('licences', 'boutiques', 'stats'));
    }

    /**
     * Génère une nouvelle clé d'activation pour une boutique (ou son groupe)
     */
    public function licenceStore(Request $request)
    {
        if ($request->input('duree_mode') === 'custom' && $request->filled('duree_jours_custom')) {
            $request->merge(['duree_jours' => (int) $request->input('duree_jours_custom')]);
        }

        $request->validate([
            'boutique_id' => 'required|exists:boutiques,id',
            'duree_jours' => 'required|integer|min:1',
            'note' => 'nullable|string|max:255',
        ]);

        $targetBoutique = Boutique::find($request->boutique_id);
        $rootBoutique = $targetBoutique ? $targetBoutique->getRootBoutique() : null;
        $boutiqueId = $rootBoutique ? $rootBoutique->id : $request->boutique_id;

        $cle = Licence::generateKey();

        Licence::create([
            'boutique_id' => $boutiqueId,
            'cle_licence' => $cle,
            'duree_jours' => $request->duree_jours,
            'statut' => 'inutilisee',
            'created_by' => Auth::id(),
            'note' => $request->note,
        ]);

        $boutique = $rootBoutique ?: $targetBoutique;
        $dureeLabel = $request->duree_jours >= 90000 ? 'À vie (Illimité)' : "{$request->duree_jours} jours";
        $filialesCount = $boutique->filiales()->count();
        $groupeMsg = $filialesCount > 0 ? " (valable pour la boutique principale et ses {$filialesCount} filiale(s))" : "";

        return back()->with('success_cle', [
            'cle' => $cle,
            'boutique' => $boutique->nom,
            'duree' => $dureeLabel,
            'message' => "Nouvelle clé d'activation générée avec succès pour « {$boutique->nom} »{$groupeMsg} !"
        ]);
    }

    /**
     * Prolonge directement l'abonnement d'une boutique et de l'ensemble de son groupe
     */
    public function licenceProlongerDirect(Request $request, Boutique $boutique)
    {
        if ($request->input('duree_mode') === 'custom' && $request->filled('duree_jours_custom')) {
            $request->merge(['duree_jours' => (int) $request->input('duree_jours_custom')]);
        }

        $request->validate([
            'duree_jours' => 'required|integer|min:1',
            'note' => 'nullable|string|max:255',
        ]);

        // Toujours prolonger la boutique racine du groupe
        $rootBoutique = $boutique->getRootBoutique();

        $duree = (int) $request->duree_jours;
        $isUnlimited = $duree >= 90000;

        if ($isUnlimited) {
            $newExpiration = null;
        } else {
            $base = ($rootBoutique->date_expiration_licence && Carbon::parse($rootBoutique->date_expiration_licence)->isFuture())
                ? Carbon::parse($rootBoutique->date_expiration_licence)
                : Carbon::now();
            $newExpiration = $base->addDays($duree);
        }

        $rootBoutique->date_expiration_licence = $newExpiration;
        $rootBoutique->is_active = true;
        $rootBoutique->save();

        // Synchroniser immédiatement toutes les filiales
        $rootBoutique->syncLicenceToFiliales();

        // Enregistrer une licence active dans l'historique sur la boutique racine
        Licence::create([
            'boutique_id' => $rootBoutique->id,
            'cle_licence' => Licence::generateKey(),
            'duree_jours' => $duree,
            'statut' => 'active',
            'date_activation' => Carbon::now(),
            'date_expiration' => $newExpiration,
            'created_by' => Auth::id(),
            'note' => $request->note ?: 'Prolongation directe par le SuperAdmin',
        ]);

        $filialesCount = $rootBoutique->filiales()->count();
        $groupeMsg = $filialesCount > 0 ? " (et ses {$filialesCount} filiale(s) associées)" : "";

        $msg = $isUnlimited
            ? "Le groupe « {$rootBoutique->nom} »{$groupeMsg} a été activé à vie avec succès !"
            : "Le groupe « {$rootBoutique->nom} »{$groupeMsg} a été prolongé jusqu'au " . $newExpiration->format('d/m/Y') . " (+{$duree} jours).";

        return back()->with('success', $msg);
    }

    /**
     * Révoque une clé de licence
     */
    public function licenceRevoquer(Licence $licence)
    {
        $boutique = $licence->boutique;
        $licence->statut = 'revoquee';
        $licence->save();

        if ($boutique) {
            $boutique->recalculerLicence();
        }

        return back()->with('success', "La clé d'activation « {$licence->cle_licence} » a été révoquée avec succès.");
    }

    /**
     * Supprime définitivement une clé de licence
     */
    public function licenceDestroy(Licence $licence)
    {
        $boutique = $licence->boutique;
        $cle = $licence->cle_licence;
        $licence->delete();

        if ($boutique) {
            $boutique->recalculerLicence();
        }

        return back()->with('success', "La clé de licence « {$cle} » a été définitivement supprimée.");
    }

    /**
     * Centre de Sauvegardes Automatiques & Dumps SQL par Compte Principal / Boutique
     */
    public function backupsIndex()
    {
        $backupController = app(\App\Http\Controllers\BackupController::class);

        // Récupérer tous les Comptes Principaux (admins propriétaires de boutiques)
        $ownerIds = Boutique::whereNotNull('user_id')->pluck('user_id')->unique()->toArray();
        $principalAccounts = User::whereIn('id', $ownerIds)
            ->orWhere('role', 'admin')
            ->orderBy('name')
            ->get()
            ->unique('id')
            ->map(function (User $user) use ($backupController) {
                $boutiqueIds = $backupController->getBoutiqueIdsForAccount($user);
                $boutiques = Boutique::with(['nature', 'parent'])
                    ->whereIn('id', $boutiqueIds)
                    ->orderByRaw('CASE WHEN parent_id IS NULL THEN 0 ELSE 1 END')
                    ->get();

                $ventesCount = !empty($boutiqueIds)
                    ? DB::table('ventes')->whereIn('boutique_id', $boutiqueIds)->count()
                    : 0;
                $produitsCount = !empty($boutiqueIds)
                    ? DB::table('stocks')->whereIn('boutique_id', $boutiqueIds)->count()
                    : 0;
                $usersCount = !empty($boutiqueIds)
                    ? DB::table('users')->whereIn('boutique_id', $boutiqueIds)->orWhere('id', $user->id)->count()
                    : 1;

                // Chercher les sauvegardes automatiques existantes sur le disque pour ce compte
                $slug = \Illuminate\Support\Str::slug($user->name ?: 'compte-' . $user->id);
                $accountDir = storage_path("app/backups/accounts/account_{$user->id}_{$slug}");
                $savedBackups = [];
                if (\Illuminate\Support\Facades\File::isDirectory($accountDir)) {
                    foreach (\Illuminate\Support\Facades\File::files($accountDir) as $f) {
                        $savedBackups[] = [
                            'filename' => $f->getFilename(),
                            'relative_path' => "accounts/account_{$user->id}_{$slug}/" . $f->getFilename(),
                            'size_kb' => round($f->getSize() / 1024, 1),
                            'date' => Carbon::createFromTimestamp($f->getMTime())->format('d/m/Y H:i'),
                            'timestamp' => $f->getMTime(),
                        ];
                    }
                    usort($savedBackups, fn($a, $b) => $b['timestamp'] <=> $a['timestamp']);
                }

                return (object)[
                    'user' => $user,
                    'boutiques' => $boutiques,
                    'boutiques_count' => $boutiques->count(),
                    'ventes_count' => $ventesCount,
                    'produits_count' => $produitsCount,
                    'users_count' => $usersCount,
                    'saved_backups' => $savedBackups,
                    'latest_backup' => $savedBackups[0] ?? null,
                ];
            })
            ->filter(fn($acc) => $acc->boutiques_count > 0 || $acc->user->role === 'admin')
            ->values();

        // Sauvegardes globales sur disque
        $globalDir = storage_path('app/backups/global');
        $globalBackups = [];
        if (\Illuminate\Support\Facades\File::isDirectory($globalDir)) {
            foreach (\Illuminate\Support\Facades\File::files($globalDir) as $f) {
                $globalBackups[] = [
                    'filename' => $f->getFilename(),
                    'relative_path' => 'global/' . $f->getFilename(),
                    'size_kb' => round($f->getSize() / 1024, 1),
                    'date' => Carbon::createFromTimestamp($f->getMTime())->format('d/m/Y H:i'),
                    'timestamp' => $f->getMTime(),
                ];
            }
            usort($globalBackups, fn($a, $b) => $b['timestamp'] <=> $a['timestamp']);
        }

        // Métadonnées de la dernière sauvegarde planifiée
        $lastBackupMeta = null;
        $metaPath = storage_path('app/backups/last_backup.json');
        if (\Illuminate\Support\Facades\File::exists($metaPath)) {
            $lastBackupMeta = json_decode(\Illuminate\Support\Facades\File::get($metaPath), true);
        }

        return view('admin.backups.index', compact('principalAccounts', 'globalBackups', 'lastBackupMeta'));
    }

    /**
     * Déclenche immédiatement la sauvegarde automatique de tous les Comptes Principaux et de la base globale.
     */
    public function backupsRunNow()
    {
        \Illuminate\Support\Facades\Artisan::call('backup:accounts', ['--retention' => 14]);
        return back()->with('success', 'Sauvegarde complète de tous les Comptes Principaux et de la base globale générée avec succès !');
    }

    /**
     * Télécharge en direct un Dump SQL intégral de toute la base de données.
     */
    public function dumpGlobal()
    {
        @ini_set('memory_limit', '512M');
        @set_time_limit(300);

        $backupController = app(\App\Http\Controllers\BackupController::class);
        $dbName = config('database.connections.mysql.database') ?: 'malcom_prod';
        $timestamp = Carbon::now()->format('Y-m-d_H-i-s');
        $filename = "dump_global_malcom_{$dbName}_{$timestamp}.sql";

        return response()->stream(function () use ($backupController) {
            $out = fopen('php://output', 'w');
            $backupController->writeFullDatabaseSqlDump($out);
            fclose($out);
        }, 200, [
            'Content-Type' => 'application/sql; charset=utf-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'Pragma' => 'no-cache',
        ]);
    }

    /**
     * Télécharge en direct le Dump SQL complet d'un Compte Principal (incluant sa boutique principale et toutes ses filiales).
     */
    public function dumpAccount(User $user)
    {
        @ini_set('memory_limit', '512M');
        @set_time_limit(300);

        $backupController = app(\App\Http\Controllers\BackupController::class);
        $boutiqueIds = $backupController->getBoutiqueIdsForAccount($user);
        $slug = \Illuminate\Support\Str::slug($user->name ?: 'compte-' . $user->id);
        $timestamp = Carbon::now()->format('Y-m-d_H-i-s');
        $filename = "dump_compte_principal_{$slug}_{$timestamp}.sql";

        return response()->stream(function () use ($backupController, $user, $boutiqueIds) {
            $out = fopen('php://output', 'w');
            $backupController->writeScopedBoutiquesSqlDump(
                $out,
                $boutiqueIds,
                "COMPTE PRINCIPAL #{$user->id} : {$user->name} ({$user->email})",
                $user->id
            );
            fclose($out);
        }, 200, [
            'Content-Type' => 'application/sql; charset=utf-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'Pragma' => 'no-cache',
        ]);
    }

    /**
     * Télécharge en direct le Dump SQL d'une boutique spécifique (ou avec toutes ses filiales si ?groupe=1).
     */
    public function dumpBoutique(Request $request, Boutique $boutique)
    {
        @ini_set('memory_limit', '512M');
        @set_time_limit(300);

        $includeGroup = $request->boolean('groupe', false);
        $boutiqueIds = $includeGroup ? $boutique->getGroupBoutiqueIds() : [$boutique->id];
        $backupController = app(\App\Http\Controllers\BackupController::class);

        $slug = \Illuminate\Support\Str::slug($boutique->nom ?: 'boutique-' . $boutique->id);
        $prefix = $includeGroup ? 'dump_groupe_boutiques' : 'dump_boutique';
        $timestamp = Carbon::now()->format('Y-m-d_H-i-s');
        $filename = "{$prefix}_{$slug}_{$timestamp}.sql";

        $scopeTitle = $includeGroup
            ? "GROUPE BOUTIQUE : {$boutique->nom} + Filiales"
            : "BOUTIQUE INDIVIDUELLE #{$boutique->id} : {$boutique->nom}";

        return response()->stream(function () use ($backupController, $boutiqueIds, $scopeTitle, $boutique) {
            $out = fopen('php://output', 'w');
            $backupController->writeScopedBoutiquesSqlDump(
                $out,
                $boutiqueIds,
                $scopeTitle,
                $boutique->user_id
            );
            fclose($out);
        }, 200, [
            'Content-Type' => 'application/sql; charset=utf-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'Pragma' => 'no-cache',
        ]);
    }

    /**
     * Télécharge un fichier d'archive SQL stocké sur le serveur.
     */
    public function downloadBackupFile(Request $request)
    {
        $rel = (string)$request->query('file', '');
        $cleanRel = ltrim(str_replace(['..', '\\'], '', $rel), '/');
        $fullPath = storage_path('app/backups/' . $cleanRel);

        if (!$cleanRel || !\Illuminate\Support\Facades\File::exists($fullPath)) {
            return back()->withErrors(['error' => 'Le fichier de sauvegarde demandé est introuvable.']);
        }

        return response()->download($fullPath);
    }

    /**
     * Supprime une archive de sauvegarde stockée sur le serveur.
     */
    public function deleteBackupFile(Request $request)
    {
        $rel = (string)$request->input('file', '');
        $cleanRel = ltrim(str_replace(['..', '\\'], '', $rel), '/');
        $fullPath = storage_path('app/backups/' . $cleanRel);

        if ($cleanRel && \Illuminate\Support\Facades\File::exists($fullPath)) {
            \Illuminate\Support\Facades\File::delete($fullPath);
            return back()->with('success', 'Archive de sauvegarde supprimée avec succès.');
        }

        return back()->withErrors(['error' => 'Fichier introuvable.']);
    }
}

