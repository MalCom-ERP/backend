<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Transfert extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'date_transfert' => 'datetime',
        'date_reception' => 'datetime',
    ];

    public function boutiqueSource()
    {
        return $this->belongsTo(Boutique::class, 'boutique_source_id');
    }

    public function boutiqueDest()
    {
        return $this->belongsTo(Boutique::class, 'boutique_dest_id');
    }

    public function auteur()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function recepteur()
    {
        return $this->belongsTo(User::class, 'recepteur_user_id');
    }

    public function details()
    {
        return $this->hasMany(TransfertDetail::class, 'transfert_id');
    }
}
