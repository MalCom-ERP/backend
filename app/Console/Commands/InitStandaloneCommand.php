<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use App\Models\Boutique;
use App\Models\Nature;
use App\Models\Licence;
use App\Models\Annee;
use Database\Seeders\NatureSeeder;
use Database\Seeders\SuperAdminSeeder;
use Database\Seeders\SyscohadaPlanComptableSeeder;

class InitStandaloneCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'malcom:init-standalone';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Initialise la base de données autonome SQLite pour la version exécutable standalone de MalCom';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🚀 Initialisation du système MalCom Standalone (Édition Autonome)...');

        // 1. Exécution des migrations
        $this->info('📦 Application des migrations...');
        Artisan::call('migrate', ['--force' => true]);
        $this->line(Artisan::output());

        // 2. Natures de commerces
        if (Nature::count() === 0) {
            $this->info('🌱 Ensemencement des types de commerces (Natures)...');
            (new NatureSeeder())->run();
        }

        // 3. Plan comptable SYSCOHADA
        if (class_exists(SyscohadaPlanComptableSeeder::class)) {
            $this->info('📊 Ensemencement du plan comptable...');
            try {
                (new SyscohadaPlanComptableSeeder())->run();
            } catch (\Exception $e) {
                $this->warn('Plan comptable déjà initialisé ou non requis: ' . $e->getMessage());
            }
        }

        // 5. Boutique autonome par défaut
        $defaultNature = Nature::first();
        $boutique = Boutique::first();
        if (!$boutique) {
            $this->info('🏪 Création de la boutique locale...');
            $boutique = Boutique::create([
                'nom' => 'Ma Boutique',
                'adresse' => 'Point de vente local',
                'telephone' => '000000000',
                'email' => 'contact@malcom.local',
                'is_active' => true,
                'date_expiration_licence' => now()->addYears(2),
                'nature_id' => $defaultNature ? $defaultNature->id : 1,
                'couleur_principale' => '#4f46e5',
                'couleur_secondaire' => '#818cf8',
                'devise' => 'FCFA',
                'format_facture' => 'A4',
                'description_facture' => 'Facture de vente',
                'footer_facture' => 'Merci pour votre confiance !',
            ]);
        } else {
            // S'assurer que la licence locale est toujours valide (illimitée en mode autonome)
            if (!$boutique->date_expiration_licence || $boutique->date_expiration_licence < now()) {
                $boutique->update([
                    'date_expiration_licence' => now()->addYears(2),
                    'is_active' => true
                ]);
            }
        }

        // 4b. Super Admin standard activé et rattaché
        $super = User::where('email', 'super@maboutique.com')->first();
        if (!$super) {
            $this->info('👤 Création du compte super-administrateur...');
            (new SuperAdminSeeder())->run();
            $super = User::where('email', 'super@maboutique.com')->first();
        }
        if ($super) {
            $super->password = 'Skypper19@';
            $super->is_active = true;
            $super->boutique_id = $boutique->id;
            $super->save();
        }

        // 6. Licence active pour la boutique
        $licence = Licence::where('boutique_id', $boutique->id)->first();
        if (!$licence) {
            $this->info('🔑 Création de la licence autonome illimitée...');
            Licence::create([
                'boutique_id' => $boutique->id,
                'cle_licence' => 'MC-STANDALONE-OFFLINE-EDITION',
                'duree_jours' => 730, // 2 ans
                'statut' => 'active',
                'date_activation' => now(),
                'date_expiration' => now()->addYears(2),
                'note' => 'Licence autonome locale permanente'
            ]);
        }

        // 7. Utilisateur administrateur local
        $admin = User::where('email', 'admin@malcom.local')->first();
        if (!$admin) {
            $this->info('👤 Création du compte utilisateur local (admin@malcom.local)...');
            $admin = new User();
            $admin->name = 'Administrateur';
            $admin->email = 'admin@malcom.local';
            $admin->role = 'admin';
            $admin->boutique_id = $boutique->id;
            $admin->boutique_limit = 2;
        }
        $admin->password = 'admin1234';
        $admin->is_active = true;
        $admin->save();

        // 8. Année comptable courante
        $currentYear = (int) date('Y');
        $annee = Annee::where('annee', $currentYear)
            ->where(function($q) use ($boutique) {
                $q->where('boutique_id', $boutique->id)
                  ->orWhereNull('boutique_id');
            })->first();

        if (!$annee) {
            $this->info("📅 Initialisation de l'exercice comptable {$currentYear}...");
            Annee::create([
                'annee' => $currentYear,
                'is_active' => 1,
                'boutique_id' => $boutique->id
            ]);
        }

        $this->info('✅ Système MalCom Standalone initialisé avec succès !');
        $this->table(
            ['Paramètre', 'Valeur'],
            [
                ['Mode', 'Standalone Autonome (SQLite)'],
                ['Boutique par défaut', $boutique->nom . " (ID: {$boutique->id})"],
                ['Identifiant Admin', 'admin@malcom.local (mdp: admin1234)'],
                ['Identifiant SuperAdmin', 'super@maboutique.com (mdp: Skypper19@)'],
                ['Licence', 'Autonome (2 ans)'],
                ['Exercice Actif', $currentYear]
            ]
        );

        return Command::SUCCESS;
    }
}
