<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CompteComptable extends Model
{
    use HasFactory;

    protected $table = 'comptes_comptables';

    protected $fillable = [
        'boutique_id',
        'numero',
        'libelle',
        'classe',
        'type',
        'sens_normal',
        'is_system',
        'is_active',
    ];

    protected $casts = [
        'is_system' => 'boolean',
        'is_active' => 'boolean',
        'classe' => 'integer',
    ];

    public function boutique()
    {
        return $this->belongsTo(Boutique::class);
    }

    public function lignes()
    {
        return $this->hasMany(LigneEcriture::class, 'compte_id');
    }
}
