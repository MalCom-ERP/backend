<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CommandeAchatDetail extends Model
{
    use HasFactory;

    protected $table = 'commande_achat_details';

    protected $fillable = [
        'commande_achat_id',
        'produit_id',
        'unite_achat',
        'quantite_conditionnement',
        'nombre_colis',
        'quantite_commandee',
        'quantite_recue',
        'prix_achat_unitaire',
        'frais_approche_unitaire',
        'cout_revient_unitaire',
        'montant_ligne',
    ];

    protected $casts = [
        'quantite_conditionnement' => 'integer',
        'nombre_colis' => 'integer',
        'quantite_commandee' => 'integer',
        'quantite_recue' => 'integer',
        'prix_achat_unitaire' => 'float',
        'frais_approche_unitaire' => 'float',
        'cout_revient_unitaire' => 'float',
        'montant_ligne' => 'float',
    ];

    public function commandeAchat()
    {
        return $this->belongsTo(CommandeAchat::class, 'commande_achat_id');
    }

    public function produit()
    {
        return $this->belongsTo(Produit::class, 'produit_id');
    }
}
