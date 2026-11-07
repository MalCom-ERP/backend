<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EcritureComptable extends Model
{
    use HasFactory;

    protected $table = 'ecritures_comptables';

    protected $fillable = [
        'boutique_id',
        'journal_id',
        'user_id',
        'date_ecriture',
        'numero_piece',
        'libelle',
        'source_type',
        'source_id',
        'statut',
    ];

    protected $casts = [
        'date_ecriture' => 'date',
    ];

    public function boutique()
    {
        return $this->belongsTo(Boutique::class);
    }

    public function journal()
    {
        return $this->belongsTo(JournalComptable::class, 'journal_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function lignes()
    {
        return $this->hasMany(LigneEcriture::class, 'ecriture_id');
    }

    public function getTotalDebitAttribute()
    {
        return $this->lignes->sum('debit');
    }

    public function getTotalCreditAttribute()
    {
        return $this->lignes->sum('credit');
    }

    public function isEquilibree()
    {
        return abs($this->total_debit - $this->total_credit) < 0.01;
    }
}
