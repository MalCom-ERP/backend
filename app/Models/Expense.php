<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Expense extends Model
{
    use HasFactory;

    protected $fillable = [
        'type',
        'montant',
        'mode_paiement',
        'reference_piece',
        'beneficiaire',
        'description',
        'date',
        'boutique_id',
        'user_id'
    ];

    protected $casts = [
        'montant' => 'float',
        'date' => 'date'
    ];

    public function boutique()
    {
        return $this->belongsTo(Boutique::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
