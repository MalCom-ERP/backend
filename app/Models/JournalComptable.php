<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class JournalComptable extends Model
{
    use HasFactory;

    protected $table = 'journaux_comptables';

    protected $fillable = [
        'boutique_id',
        'code',
        'libelle',
    ];

    public function boutique()
    {
        return $this->belongsTo(Boutique::class);
    }

    public function ecritures()
    {
        return $this->hasMany(EcritureComptable::class, 'journal_id');
    }
}
