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
        'config' => 'array',
        'date_peremption' => 'date:Y-m-d',
        'alerte_peremption_jours' => 'integer',
        'exige_imei_serie' => 'boolean',
        'duree_garantie_mois' => 'integer',
        'est_consignable' => 'boolean',
        'montant_consigne_unitaire' => 'float',
    ];

    protected $appends = [
        'statut_peremption',
        'jours_avant_peremption',
    ];

    /**
     * Normalise l'URL d'image produit (ignore default.png et passe par /api/media/{folder}/{filename}).
     */
    public function getImageAttribute($value): ?string
    {
        if (empty($value) || !is_string($value)) {
            return null;
        }

        $trimmed = trim($value);
        if ($trimmed === '' || str_ends_with($trimmed, 'default.png') || str_ends_with($trimmed, 'no-image.png')) {
            return null;
        }

        if (str_starts_with($trimmed, 'data:image/')) {
            return strlen($trimmed) > 260 ? $trimmed : null;
        }

        if (preg_match('#/(?:storage|api/media)/([^/]+)/([^?\#]+)#', $trimmed, $m)) {
            $base = (!app()->runningInConsole() && request())
                ? rtrim(request()->getSchemeAndHttpHost(), '/')
                : rtrim((string) config('app.url'), '/');

            return $base . '/api/media/' . $m[1] . '/' . $m[2];
        }

        if (preg_match('#^([^/:]+/[^?\#]+)$#', ltrim($trimmed, '/'), $m) && !str_starts_with($trimmed, 'http')) {
            $base = (!app()->runningInConsole() && request())
                ? rtrim(request()->getSchemeAndHttpHost(), '/')
                : rtrim((string) config('app.url'), '/');

            return $base . '/api/media/' . $m[1];
        }

        return $trimmed;
    }

    public function getJoursAvantPeremptionAttribute(): ?int
    {
        if (empty($this->date_peremption)) {
            return null;
        }

        $today = \Carbon\Carbon::today();
        $exp = \Carbon\Carbon::parse($this->date_peremption)->startOfDay();

        return (int) $today->diffInDays($exp, false);
    }

    public function getStatutPeremptionAttribute(): ?string
    {
        $jours = $this->jours_avant_peremption;
        if ($jours === null) {
            return null;
        }

        $seuil = max(1, (int) ($this->alerte_peremption_jours ?? 30));
        if ($jours < 0) {
            return 'expire';
        }
        if ($jours <= min(7, $seuil)) {
            return 'critique';
        }
        if ($jours <= $seuil) {
            return 'bientot';
        }

        return 'valide';
    }
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
            ->select(
                'produits.*',
                'stocks.quantite',
                'stocks.prix_vente',
                'stocks.prix_achat',
                'stocks.seuil_alerte',
                'stocks.emplacement',
                'stocks.prix_demi_gros',
                'stocks.prix_gros'
            )
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
