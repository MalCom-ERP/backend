<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('auth:create-super-admin', function () {
    $email = 'admin@maboutique.com';
    if (!User::where('email', $email)->exists()) {
        User::create([
            'name' => 'Super Admin',
            'email' => $email,
            'password' => Hash::make('admin123'), // Change this in production
            'role' => 'admin', // Ensure this role exists in your seeding
        ]);
        $this->info('Super Admin created successfully.');
    } else {
        $this->info('Super Admin already exists.');
    }
})->purpose('Create a Super Admin user')->hourly();

Artisan::command('reports:schedule-daily', function () {
    $this->info('Scheduling daily reports generation...');
    Artisan::call('reports:generate-daily');
})->purpose('Generate and send daily reports')->dailyAt('19:00');

Artisan::command('backups:schedule-daily', function () {
    $this->info('Exécution de la sauvegarde quotidienne par Compte Principal...');
    Artisan::call('backup:accounts', ['--retention' => 14]);
})->purpose('Sauvegarde automatique quotidienne des bases de données par Compte Principal et Globale')->dailyAt('02:00');

