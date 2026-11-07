<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LigneEcriture extends Model
{
    use HasFactory;

    protected $table = 'lignes_ecritures';

    protected $fillable = [
        'ecriture_id',
        'compte_id',
        'libelle',
        'debit',
        'credit',
    ];

    protected $casts = [
        'debit' => 'float',
        'credit' => 'float',
    ];

    public function ecriture()
    {
        return $this->belongsTo(EcritureComptable::class, 'ecriture_id');
    }

    public function compte()
    {
        return $this->belongsTo(CompteComptable::class, 'compte_id');
    }
}
