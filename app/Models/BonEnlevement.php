<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BonEnlevement extends Model
{
    use HasFactory;

    protected $table = 'bons_enlevement';

    protected $fillable = [
        'reference',
        'vente_id',
        'boutique_emettrice_id',
        'entrepot_id',
        'client_id',
        'user_id',
        'valide_par_user_id',
        'statut',
        'stock_deja_deduit',
        'recepteur_nom',
        'recepteur_telephone',
        'chauffeur_immatriculation',
        'motif_ou_note',
        'date_emission',
        'date_enlevement',
    ];

    protected $casts = [
        'stock_deja_deduit' => 'boolean',
        'date_emission' => 'datetime',
        'date_enlevement' => 'datetime',
    ];

    public function vente()
    {
        return $this->belongsTo(Vente::class, 'vente_id');
    }

    public function boutiqueEmettrice()
    {
        return $this->belongsTo(Boutique::class, 'boutique_emettrice_id');
    }

    public function entrepot()
    {
        return $this->belongsTo(Boutique::class, 'entrepot_id');
    }

    public function client()
    {
        return $this->belongsTo(Client::class, 'client_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function validePar()
    {
        return $this->belongsTo(User::class, 'valide_par_user_id');
    }

    public function details()
    {
        return $this->hasMany(BonEnlevementDetail::class, 'bon_enlevement_id');
    }
}
