<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\File;
use App\Http\Controllers\AdminController;

/*
|--------------------------------------------------------------------------
| Routes pour l'interface Admin (Laravel Blade)
|--------------------------------------------------------------------------
*/

use App\Http\Controllers\Admin\SuperAdminController;

/*
|--------------------------------------------------------------------------
| Routes pour l'interface Admin (Laravel Blade)
|--------------------------------------------------------------------------
*/

// Route::domain('admin.maboutique.tech')->group(function () {
// Password Reset Routes
Route::get('password/reset', [App\Http\Controllers\Auth\PasswordResetController::class, 'showLinkRequestForm'])->name('password.request');
Route::post('password/email', [App\Http\Controllers\Auth\PasswordResetController::class, 'sendResetLinkEmail'])->name('password.email');
Route::get('password/reset/{token}', [App\Http\Controllers\Auth\PasswordResetController::class, 'showResetForm'])->name('password.reset');
Route::post('password/reset', [App\Http\Controllers\Auth\PasswordResetController::class, 'reset'])->name('password.update');

Route::middleware(['auth'])->prefix('admin')->group(function () {
    Route::get('/dashboard', [SuperAdminController::class, 'dashboard'])->name('admin.dashboard');
    Route::get('/boutiques', [SuperAdminController::class, 'boutiquesIndex'])->name('admin.boutiques.index');
    Route::get('/boutiques/{boutique}', [SuperAdminController::class, 'boutiqueShow'])->name('admin.boutiques.show');
    Route::post('/boutiques', [SuperAdminController::class, 'boutiqueStore'])->name('admin.boutiques.store');
    Route::post('/boutiques/{boutique}/toggle-status', [SuperAdminController::class, 'toggleBoutiqueStatus'])->name('admin.boutiques.toggle-status');
    Route::delete('/boutiques/{boutique}', [SuperAdminController::class, 'boutiqueDestroy'])->name('admin.boutiques.destroy');

    // Per-Boutique User Management
    Route::get('/boutiques/{boutique}/users', [SuperAdminController::class, 'boutiqueUsers'])->name('admin.boutiques.users');
    Route::post('/boutiques/{boutique}/users', [SuperAdminController::class, 'userStore'])->name('admin.boutiques.users.store');
    Route::post('/users/{user}/toggle-status', [SuperAdminController::class, 'toggleUserStatus'])->name('admin.users.toggle-status');
    Route::post('/users/{user}/update-password', [SuperAdminController::class, 'updateUserPassword'])->name('admin.users.update-password');
    Route::delete('/users/{user}', [SuperAdminController::class, 'userDestroy'])->name('admin.users.destroy');

    // Admin User Management
    Route::get('/admins', [SuperAdminController::class, 'adminsIndex'])->name('admin.admins.index');
    Route::post('/admins/{user}/update-limit', [SuperAdminController::class, 'updateBoutiqueLimit'])->name('admin.admins.update-limit');
    Route::delete('/admins/{admin}', [SuperAdminController::class, 'adminDestroy'])->name('admin.admins.destroy');

    // Licences & Abonnements Management
    Route::get('/licences', [SuperAdminController::class, 'licencesIndex'])->name('admin.licences.index');
    Route::post('/licences', [SuperAdminController::class, 'licenceStore'])->name('admin.licences.store');
    Route::post('/boutiques/{boutique}/prolonger-licence', [SuperAdminController::class, 'licenceProlongerDirect'])->name('admin.boutiques.prolonger-licence');
    Route::post('/licences/{licence}/revoquer', [SuperAdminController::class, 'licenceRevoquer'])->name('admin.licences.revoquer');
    Route::delete('/licences/{licence}', [SuperAdminController::class, 'licenceDestroy'])->name('admin.licences.destroy');
});

Route::get('/', function () {
    return redirect()->route('admin.loginPage');
});

Route::get('/loginAdmin', function () {
    return view('login');
})->name('admin.loginPage');

Route::post('/loginAdmin', [SuperAdminController::class, 'login'])->name('admin.login');
Route::post('/logoutAdmin', [SuperAdminController::class, 'logout'])->name('logout');

// });
/*
|--------------------------------------------------------------------------
| Catch-all route pour Angular (frontend)
|--------------------------------------------------------------------------
| Toutes les routes sauf celles contenant "admin" ou "api"
*/

Route::get('/{any}', function () {
    $angularIndexPath = public_path('index.html');
    if (File::exists($angularIndexPath)) {
        return File::get($angularIndexPath);
    }
    abort(404);
})->where('any', '^(?!admin|api).*$');
