<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\SoftDeletes;

class Produit extends Model
{
    use HasApiTokens, HasFactory, SoftDeletes;
    protected $guarded = [];

    protected $casts = [
        'config' => 'array'
    ];
    // On definit la relation entre les produits et les categories
    public function categorie()
    {
        return $this->belongsTo(Categorie::class);
    }
    public function stocks()
    {
        return $this->hasMany(Stock::class);
    }

    public function stock()
    {
        // Return first stock for BC, but ideally we should use scoped stocks
        return $this->hasOne(Stock::class);
    }

    public function inventaires()
    {
        return $this->hasMany(Inventaire::class);
    }

    // On definit la relation entre le dont id sera venu produit et le stock qui lui est associe
    public function scopeProduitStock($query, $id, $boutique_id = null)
    {
        $q = $query
            ->join('stocks', 'stocks.produit_id', '=', 'produits.id')
            ->select('produits.*', 'stocks.quantite', 'stocks.prix_vente', 'stocks.prix_achat')
            ->where('stocks.produit_id', $id);

        if ($boutique_id) {
            $q->where('stocks.boutique_id', $boutique_id);
        }

        return $q;
    }

    public function detailVente()
    {

        return $this->hasOne(DetailVente::class);
    }
}
