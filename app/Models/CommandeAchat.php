<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CommandeAchat extends Model
{
    use HasFactory;

    protected $table = 'commandes_achats';

    protected $fillable = [
        'reference',
        'boutique_id',
        'fournisseur_id',
        'user_id',
        'statut',
        'type_paiement',
        'moyen_paiement',
        'sous_total_ht',
        'frais_transport',
        'frais_douane',
        'frais_manutention',
        'remise',
        'montant_total',
        'montant_paye',
        'montant_restant',
        'date_commande',
        'date_echeance',
        'date_reception',
        'numero_facture_fournisseur',
        'note',
    ];

    protected $casts = [
        'sous_total_ht' => 'float',
        'frais_transport' => 'float',
        'frais_douane' => 'float',
        'frais_manutention' => 'float',
        'remise' => 'float',
        'montant_total' => 'float',
        'montant_paye' => 'float',
        'montant_restant' => 'float',
        'date_commande' => 'date:Y-m-d',
        'date_echeance' => 'date:Y-m-d',
        'date_reception' => 'datetime',
    ];

    public function boutique()
    {
        return $this->belongsTo(Boutique::class, 'boutique_id');
    }

    public function fournisseur()
    {
        return $this->belongsTo(Fournisseur::class, 'fournisseur_id')->withTrashed();
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function details()
    {
        return $this->hasMany(CommandeAchatDetail::class, 'commande_achat_id');
    }

    public function paiements()
    {
        return $this->hasMany(PaiementFournisseur::class, 'commande_achat_id')->orderByDesc('date_paiement')->orderByDesc('id');
    }
}
