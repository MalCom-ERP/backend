<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TransfertDetail extends Model
{
    use HasFactory;

    protected $guarded = [];

    public function transfert()
    {
        return $this->belongsTo(Transfert::class, 'transfert_id');
    }

    public function produit()
    {
        return $this->belongsTo(Produit::class, 'produit_id');
    }
}
