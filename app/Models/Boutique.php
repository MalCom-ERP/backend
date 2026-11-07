<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Boutique extends Model
{
    use HasFactory;

    protected $fillable = [
        'nom',
        'adresse',
        'telephone',
        'email',
        'is_active',
        'user_id',
        'parent_id',
        'logo',
        'description_facture',
        'description_bordereau',
        'description_recu',
        'footer_facture',
        'footer_bordereau',
        'footer_recu',
        'couleur_principale',
        'couleur_secondaire',
        'devise',
        'format_facture',
        'nature_id',
        'date_expiration_licence'
    ];

    protected $casts = [
        'date_expiration_licence' => 'datetime',
        'is_active' => 'boolean',
    ];

    public function licences()
    {
        return $this->hasMany(Licence::class)->orderByDesc('created_at');
    }

    public function parent()
    {
        return $this->belongsTo(Boutique::class, 'parent_id');
    }

    public function filiales()
    {
        return $this->hasMany(Boutique::class, 'parent_id');
    }

    /**
     * Retourne la boutique principale (racine du groupe d'établissements).
     * Si la boutique courante est déjà principale, elle se retourne elle-même.
     */
    public function getRootBoutique(): Boutique
    {
        if ($this->parent_id) {
            $parent = $this->parent ?: Boutique::find($this->parent_id);
            if ($parent && $parent->id !== $this->id) {
                return $parent->getRootBoutique();
            }
        }
        return $this;
    }

    /**
     * Indique si cette boutique est une filiale rattachée à une boutique principale
     */
    public function isFiliale(): bool
    {
        return !is_null($this->parent_id);
    }

    /**
     * Retourne tous les identifiants de boutiques du groupe (la racine et toutes les filiales)
     * @return int[]
     */
    public function getGroupBoutiqueIds(): array
    {
        $root = $this->getRootBoutique();
        $filialeIds = Boutique::where('parent_id', $root->id)->pluck('id')->toArray();
        return array_values(array_unique(array_merge([$root->id], $filialeIds)));
    }

    /**
     * Retourne la collection de toutes les boutiques du même groupe
     */
    public function getGroupBoutiques()
    {
        $ids = $this->getGroupBoutiqueIds();
        return Boutique::whereIn('id', $ids)->get();
    }

    /**
     * Vérifie si la boutique (ou sa boutique principale de groupe) a une licence illimitée active
     */
    public function hasUnlimitedLicence(): bool
    {
        $root = $this->getRootBoutique();
        if ($root->id !== $this->id) {
            return $root->hasUnlimitedLicence();
        }

        return $this->licences()
            ->where('statut', 'active')
            ->where('duree_jours', '>=', 90000)
            ->exists();
    }

    /**
     * Vérifie si la licence de la boutique (ou de son groupe) a expiré ou n'est plus valide.
     * Pour une filiale, la validité dépend STRICTEMENT de l'état de sa boutique principale.
     */
    public function isLicenceExpired(): bool
    {
        $root = $this->getRootBoutique();
        if ($root->id !== $this->id) {
            return $root->isLicenceExpired();
        }

        if (!$this->is_active) {
            return true;
        }

        // Si la boutique dispose d'une licence illimitée active
        if ($this->hasUnlimitedLicence()) {
            return false;
        }

        // Vérifier s'il existe au moins une licence active non révoquée
        $hasActiveLicence = $this->licences()
            ->where('statut', 'active')
            ->exists();

        if (!$hasActiveLicence) {
            return true;
        }

        // Si aucune date d'expiration n'est fixée
        if ($this->date_expiration_licence === null) {
            return true;
        }

        // Si la date d'expiration est dépassée par rapport à maintenant
        return now()->greaterThan($this->date_expiration_licence);
    }

    /**
     * Retourne le nombre de jours restants avant expiration (ou null si illimité)
     */
    public function joursRestants(): ?int
    {
        $root = $this->getRootBoutique();
        if ($root->id !== $this->id) {
            return $root->joursRestants();
        }

        if ($this->hasUnlimitedLicence()) {
            return null;
        }

        if ($this->date_expiration_licence === null || $this->isLicenceExpired()) {
            return 0;
        }

        $diffHours = now()->diffInHours($this->date_expiration_licence, false);
        if ($diffHours <= 0) {
            return 0;
        }

        return (int) ceil($diffHours / 24);
    }

    /**
     * Recalcule et synchronise l'état de la licence de la boutique principale,
     * et propage immédiatement l'état sur toutes ses filiales.
     */
    public function recalculerLicence(): void
    {
        $root = $this->getRootBoutique();
        if ($root->id !== $this->id) {
            $root->recalculerLicence();
            return;
        }

        // 1. Licence illimitée active ?
        if ($this->hasUnlimitedLicence()) {
            $this->date_expiration_licence = null;
            $this->save();
            $this->syncLicenceToFiliales();
            return;
        }

        // 2. Licences actives avec date d'expiration
        $activeLicences = $this->licences()
            ->where('statut', 'active')
            ->whereNotNull('date_expiration')
            ->get();

        if ($activeLicences->isEmpty()) {
            // Aucune licence active : date d'expiration passée pour bloquer l'accès
            $this->date_expiration_licence = now()->subMinute();
            $this->save();
            $this->syncLicenceToFiliales();
            return;
        }

        // 3. Calculer la date maximale parmi les licences actives
        $maxExp = $activeLicences->max('date_expiration');
        if ($maxExp) {
            $this->date_expiration_licence = $maxExp;
        } else {
            $this->date_expiration_licence = now()->subMinute();
        }

        $this->save();
        $this->syncLicenceToFiliales();
    }

    /**
     * Propage la date d'expiration et le statut actif sur l'ensemble des filiales
     */
    public function syncLicenceToFiliales(): void
    {
        $this->filiales()->update([
            'date_expiration_licence' => $this->date_expiration_licence,
            'is_active' => $this->is_active,
        ]);
    }

    public function nature()
    {
        return $this->belongsTo(Nature::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function users()
    {
        return $this->hasMany(User::class);
    }

    public function ventes()
    {
        return $this->hasMany(Vente::class);
    }

    public function stocks()
    {
        return $this->hasMany(Stock::class);
    }
}

