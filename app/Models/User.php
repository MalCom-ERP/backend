<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'permissions',
        'boutique_id',
        'is_active',
        'boutique_limit'
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'permissions' => 'array',
        ];
    }

    public function isAdminRole(): bool
    {
        return in_array(strtolower(trim((string) $this->role)), ['admin', 'admin1', 'super_admin'], true);
    }

    public function isGestionnaireRole(): bool
    {
        return strtolower(trim((string) $this->role)) === 'gestionnaire';
    }

    public function canEditProducts(): bool
    {
        return $this->isAdminRole();
    }

    public function canManageReappro(): bool
    {
        return $this->isAdminRole();
    }

    public function getEffectivePermissions(): array
    {
        $perms = is_array($this->permissions) ? $this->permissions : [];

        if ($this->isGestionnaireRole()) {
            $perms['can_reappro'] = false;
            $perms['can_change_prices'] = false;
        }

        return $perms;
    }

    /**
     * Vérifie si l'utilisateur possède une permission spécifique (par son rôle par défaut ou par délégation).
     * Permissions supportées :
     * - can_reappro : Réapprovisionnement de stock (Admin uniquement)
     * - can_change_prices : Modification des prix d'achat/vente (Admin uniquement pour gestionnaire)
     * - can_edit_products : Modification / suppression / import de produits (Admin uniquement)
     * - can_view_ca : Consultation du Chiffre d'Affaires (Admin & Comptable par défaut)
     * - can_view_margins : Consultation des marges et prix d'achat (Admin par défaut)
     */
    public function hasPermission(string $permission): bool
    {
        $role = strtolower(trim((string) $this->role));

        if ($this->isAdminRole()) {
            return true;
        }

        if ($this->isGestionnaireRole() && in_array($permission, ['can_reappro', 'can_change_prices', 'can_edit_products'], true)) {
            return false;
        }

        $perms = is_array($this->permissions) ? $this->permissions : [];

        // Si l'admin a explicitement défini la permission (true ou false) pour cet utilisateur
        if (array_key_exists($permission, $perms)) {
            return (bool) $perms[$permission];
        }

        // Droits par défaut selon le rôle si non spécifié dans permissions
        if ($permission === 'can_view_ca' && $role === 'comptable') {
            return true;
        }

        return false;
    }

    public function boutique()
    {
        return $this->belongsTo(Boutique::class);
    }
}
