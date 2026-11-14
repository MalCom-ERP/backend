<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Laravel\Sanctum\HasApiTokens;

class Vente extends Model
{
    use HasApiTokens;
    use HasFactory;
    protected $guarded = [];
    protected $casts = [
        'date_vente' => 'date',
        'montant_total' => 'float',
        'montant_avance' => 'float',
        'montant_restant' => 'float',
        'details_paiement' => 'array',
    ];
    // On definit la relation entre les ventes et les produits
    public function produit()
    {
        return $this->belongsTo(Produit::class);
    }

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function boutique()
    {
        return $this->belongsTo(Boutique::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // On definit la relation entre les ventes et la detailVente:
    public function detailVentes()
    {
        return $this->hasMany(DetailVente::class);
    }
    // On defini la relation entre vente et FactureVentes
    public function factureVentes()
    {
        return $this->hasMany(FactureVente::class);
    }

    public function paiementsCredit()
    {
        return $this->hasMany(PaiementCredit::class);
    }

    public function bonEnlevement()
    {
        return $this->hasOne(BonEnlevement::class, 'vente_id');
    }

    public function getIsCreditAttribute()
    {
        return $this->type_paiement === 'credit';
    }
    public function scopeVenteProduit($query, $id)
    {
        return $query
            ->join('produits', 'produits.id', '=', 'ventes.produit_id')
            ->select('ventes.*', 'produits.nom', 'produits.prix_vente')
            ->where('ventes.produit_id', $id);
    }

    public function scopeVenteProduitDate($query, $id, $date)
    {
        return $query
            ->join('produits', 'produits.id', '=', 'ventes.produit_id')
            ->select('ventes.*', 'produits.nom', 'produits.prix_vente')
            ->where('ventes.produit_id', $id)
            ->where('ventes.date_vente', $date);
    }
}
