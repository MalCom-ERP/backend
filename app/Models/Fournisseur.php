<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Fournisseur extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'fournisseurs';

    protected $fillable = [
        'boutique_id',
        'nom',
        'entreprise',
        'telephone',
        'email',
        'adresse',
        'pays_ville',
        'type_fournisseur',
        'nif',
        'rccm',
        'delai_paiement_jours',
        'notes',
        'is_active',
    ];

    protected $casts = [
        'delai_paiement_jours' => 'integer',
        'is_active' => 'boolean',
    ];

    public function boutique()
    {
        return $this->belongsTo(Boutique::class, 'boutique_id');
    }

    public function commandesAchats()
    {
        return $this->hasMany(CommandeAchat::class, 'fournisseur_id');
    }

    public function paiements()
    {
        return $this->hasMany(PaiementFournisseur::class, 'fournisseur_id');
    }
}
