<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PaiementFournisseur extends Model
{
    use HasFactory;

    protected $table = 'paiements_fournisseurs';

    protected $fillable = [
        'commande_achat_id',
        'fournisseur_id',
        'boutique_id',
        'user_id',
        'montant',
        'moyen_paiement',
        'reference_paiement',
        'date_paiement',
        'note',
    ];

    protected $casts = [
        'montant' => 'float',
        'date_paiement' => 'date:Y-m-d',
    ];

    public function commandeAchat()
    {
        return $this->belongsTo(CommandeAchat::class, 'commande_achat_id');
    }

    public function fournisseur()
    {
        return $this->belongsTo(Fournisseur::class, 'fournisseur_id')->withTrashed();
    }

    public function boutique()
    {
        return $this->belongsTo(Boutique::class, 'boutique_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
