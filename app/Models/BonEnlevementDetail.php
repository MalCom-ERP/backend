<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BonEnlevementDetail extends Model
{
    use HasFactory;

    protected $table = 'bon_enlevement_details';

    protected $fillable = [
        'bon_enlevement_id',
        'produit_id',
        'quantite_commandee',
        'quantite_livree',
        'emplacement',
    ];

    protected $casts = [
        'quantite_commandee' => 'integer',
        'quantite_livree' => 'integer',
    ];

    public function bonEnlevement()
    {
        return $this->belongsTo(BonEnlevement::class, 'bon_enlevement_id');
    }

    public function produit()
    {
        return $this->belongsTo(Produit::class, 'produit_id');
    }
}
